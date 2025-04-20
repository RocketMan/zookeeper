<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2025 Jim Mason <jmason@ibinx.com>
 * @link https://zookeeper.ibinx.com/
 * @license GPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License,
 * version 3, along with this program.  If not, see
 * http://www.gnu.org/licenses/
 *
 */

namespace ZK\PushNotification;

use ZK\Engine\IPlaylist;
use ZK\Engine\PlaylistEntry;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\RequestOptions;

/**
 * ZootopiaListener creates playlists from a Zootopia event stream
 *
 * To use, in the `config.php` configuration file, include the stanza:
 *
 *    'push_proxy' => [
 *        [
 *            'proxy' => ZK\PushNotification\ZootopiaListener::class,
 *            'ws_endpoint' => 'wss://example/kzsu/socket.io/endpoint',
 *            'http_endpoints' => [
 *                'apikey' => 'apikey',
 *                'base_url' => 'base url',
 *                'title' => 'show title',  // or array of show titles
 *                'airname' => 'airname',   // or array of airnames
 *                'recent' => true|false,   // include in recent airplay (optional; default false)
 *                'delete' => true|false,   // delete (true) or prematurely end (false) show if conflict detected (optional; default false)
 *                'tz' => 'tzName',
 *                'caption' => 'caption',
 *            ]
 *        ],
 *        ...repeat for additional proxies...
 *    ],
 *
 * where:
 *    'proxy' specifies this class or a derivative;
 *    'ws_endpoint' is the socket.io event stream to subscribe to;
 *    'apikey' is the Zookeeper API key;
 *    'base_url' base URL of the Zookeeper server (must be slash-terminated);
 *    'airname' airname for playlists;
 *    'title' title for playlists;
 *    'tz' specifies the timezone of the ws_endpoint, if different to
 *         the Zookeeper server, or null if they are the same;
 *    'caption' comment to lead the playlist, or null if none.
 *
 * If an array of titles and/or airnames are given, one will be selected
 * at random for each new show that is created.  Airnames pair to titles
 * one-to-one; if there are not enough airnames, they are recycled.
 *
 * See INSTALLATION.md for details on installing and configuring push
 * notifications.
 */
class ZootopiaListener {
    protected $loop;
    protected $subscriber;
    protected $wsEndpoint;
    protected $config;
    protected $handler;
    protected $zk;
    protected $lastPing;
    protected $onAir;

    private const TIDY_START = 5; // number of minutes to round show start/end

    /**
     * test zootopia artist name against zookeeper artist
     *
     * this does PlaylistEntry::swapNames on the zookeeper artist name to
     * restore it to original form for the comparison, but in addition,
     * it also matches zookeeper albums keyed with 'and', as is often
     * the case, against zootopia artist entries that use '&'.
     *
     * @param $albumArtist zookeeper artist name
     * @param $trackArtist zootopia artist name
     * @return true if match, false otherwise
     */
    protected static function testArtist($albumArtist, $trackArtist) {
        return !strcasecmp($swap = PlaylistEntry::swapNames($albumArtist), $trackArtist) ||
            strpos($trackArtist, '&') !== false &&
            ($i = stripos($swap, ' and ')) !== false &&
            !strcasecmp(substr_replace($swap, '&', $i + 1, 3), $trackArtist);
    }

    /**
     * test candidate show title against zootopia show title(s)
     *
     * @param $title candidate to test
     * @return true if match, false otherwise
     */
    protected function testTitle($title) {
        return is_array($this->config["title"]) ?
            ($matches = preg_grep("/" . preg_quote($title) . "/i",
                    $this->config["title"])) && count($matches) :
            preg_match("/" . preg_quote($this->config["title"]) . "/i",
                    $title);
    }

    public function __construct(\React\EventLoop\LoopInterface $loop) {
        $this->loop = $loop;
        $this->subscriber = new Subscriber($loop);
        $this->handler = new CurlMultiHandler();
    }

    protected function log($msg) {
        $logName = (new \ReflectionClass($this))->getShortName();
        echo "$logName: $msg\n";
    }

    protected function reconnect() {
        ($this->subscriber)($this->wsEndpoint)->then([$this, 'proxy'], function ($e) {
            $firstLine = trim(strtok($e->getMessage(), "\n"));
            $this->log("Could not connect: $firstLine, retrying");

            // try again in 60 seconds
            $this->loop->addTimer(60, function () {
                $this->reconnect();
            });
        });
    }

    public function connect(string $wsEndpoint, array $httpEndpoints) {
        $this->wsEndpoint = $wsEndpoint;
        $this->config = $httpEndpoints;
        $this->zk = new Client([
            'handler' => HandlerStack::create($this->handler),
            'base_uri' => $this->config["base_url"],
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
                'X-APIKEY' => $this->config["apikey"]
            ]
        ]);
        $this->reconnect();
    }

    /*
     * run pending Guzzle promises on the React event loop
     */
    protected function unpackPromisesAsync() {
        // we must use Closure, as handler->handles is private
        $this->loop->addPeriodicTimer(0, \Closure::bind(function($timer) {
            $this->tick();
            if(empty($this->handles) && Promise\Utils::queue()->isEmpty())
                \React\EventLoop\Loop::cancelTimer($timer);
        }, $this->handler, $this->handler));
    }

    public function addTrack($event) {
        $event["zootopia"] = in_array($event["type"] ?? null, ["schedule", "zootopia"]) &&
                preg_match("/zootopia/i", $event["name"]) &&
                $event["track_title"];

        if(!$this->onAir && !$event["zootopia"])
            return;

        $show = null;
        $trackName = null;

        // get 'on now'
        $this->zk->getAsync('api/v1/playlist', [
            RequestOptions::QUERY => [
                "filter[date]" => "onnow",
                "fields[show]" => "-events"
            ]
        ])->then(function($response) use($event, &$show) {
            $page = $response->getBody()->getContents();
            $json = json_decode($page);
            $count = sizeof($json->data);

            $now = new \DateTime();
            switch($count) {
            case 0:
                if(!$event["zootopia"]) {
                    $this->onAir = false;
                    return new RejectedPromise("Zootopia signed off");
                }

                // No show is currently on-air; create a new show
                $date = $now->format("Y-m-d");
                $min = intval($now->format("i")) % self::TIDY_START;
                if($min)
                    $now->modify("-$min minutes");
                $time = $now->format("Hi");

                $tz = $this->config["tz"];
                if(!empty($event["show_end"])) {
                    $end = \DateTime::createFromFormat(IPlaylist::TIME_FORMAT_SQL,
                            $event["show_end"], $tz ? new \DateTimeZone($tz) : null);
                    if($tz)
                        $end->setTimezone(new \DateTimeZone(date_default_timezone_get()));

                    $showLen = $end->getTimestamp() - $now->getTimestamp();
                }

                if(empty($event["show_end"]) || $showLen > IPlaylist::MAX_SHOW_LEN * 60 || $showLen < 0) {
                    // generate synthetic show end time
                    $end = clone $now;
                    $end->modify("+" . floor(IPlaylist::MAX_SHOW_LEN / 2) . " minutes");
                    $min = intval($end->format("i"));
                    if($min)
                        $end->modify("-$min minutes");
                } else if($showLen < IPlaylist::MIN_SHOW_LEN * 60)
                    return new RejectedPromise("show too short");

                $time .= "-" . $end->format("Hi");

                // create new show
                $title = $this->config["title"];
                $airname = $this->config["airname"];
                if(is_array($title)) {
                    $index = rand(0, count($title) - 1);
                    $title = $title[$index];
                    if(is_array($airname))
                        $airname = $airname[$index % count($airname)];
                }
                $show = "$title with $airname $date $time";

                return $this->zk->postAsync('api/v1/playlist', [
                    RequestOptions::JSON => [
                        'data' => [
                            'type' => 'show',
                            'attributes' => [
                                'name' => $title,
                                'date' => $date,
                                'time' => $time,
                                'airname' => $airname,
                            ]
                        ]
                    ]
                ])->then(function($response) use($time, &$show) {
                    $this->log("created $show");
                    $lshow = $show;
                    $show = $response->getHeader('Location')[0];
                    $this->onAir = true;

                    // add caption
                    if(isset($this->config["caption"])) {
                        return $this->zk->postAsync($show . '/events', [
                            RequestOptions::JSON => [
                                'data' => [
                                    'type' => 'event',
                                    'attributes' => [
                                        'type' => 'comment',
                                        'comment' => $this->config["caption"],
                                        'created' => explode('-', $time)[0],
                                    ]
                                ]
                            ]
                        ])->then(null, function($e) use($lshow) {
                            $this->log("could not insert caption for $lshow");
                            // continue with remaining fulfilled callbacks
                        });
                    }
                });
                break;
            case 1:
                if(!$this->testTitle($json->data[0]->attributes->name)) {
                    $this->onAir = false;
                    return new RejectedPromise("DJ On Air");
                }

                if($event["zootopia"]) {
                    // We are already on-air; use the existing show.
                    $show = $json->data[0]->links->self;
                    $this->onAir = true;
                    break;
                }

                // Automation has concluded; end our show now.
                // fall through...
            default:
                // Another show is also on-air
                $zootopia = null;
                foreach($json->data as $data) {
                    if($this->testTitle($data->attributes->name)) {
                        $zootopia = $data;
                        break;
                    }
                }

                if(!$zootopia) {
                    // Multiple shows are on, but none of them ours.
                    $this->onAir = false;
                    return new RejectedPromise("DJ On Air");
                }

                // End our show now
                $min = intval($now->format("i")) % self::TIDY_START;
                if($min)
                    $now->modify("-$min minutes");
                $time = explode('-', $zootopia->attributes->time);
                $start = \DateTime::createFromFormat(IPlaylist::TIME_FORMAT,
                            $zootopia->attributes->date . " " . $time[0]);
                $showLen = $now->getTimestamp() - $start->getTimestamp();
                // delete if new end time is at or before the start time, or
                // if the resulting truncated show is less than the minimum
                $delete = $showLen < IPlaylist::MIN_SHOW_LEN * 60 ||
                            ($this->config["delete"] ?? false);
                $id = $zootopia->id;
                if($delete) {
                    return $this->zk->deleteAsync('api/v1/playlist/' . $id)->then(function() {
                        $this->log("another show detected, deleting our show");
                        $this->onAir = false;
                        return new RejectedPromise("DJ On Air");
                    });
                } else {
                    $time[1] = $now->format("Hi");
                    return $this->zk->patchAsync('api/v1/playlist/' . $id, [
                        RequestOptions::JSON => [
                            'data' => [
                                'type' => 'show',
                                'id' => $id,
                                'attributes' => [
                                    'time' => implode('-', $time)
                                ]
                            ]
                        ]
                    ])->then(function() {
                        $this->log("another show detected, ending our show");
                        $this->onAir = false;
                        return new RejectedPromise("DJ On Air");
                    });
                }
                break;
            }
        })->then(function() use($event, &$trackName) {
            // lookup album by track name
            $trackName = preg_match("/^(.+)( \(\d+\))$/", $event["track_title"], $matches) ? $matches[1] : $event["track_title"];

            return $this->zk->getAsync('api/v1/album', [
                RequestOptions::QUERY => [
                    "filter[track]" => $trackName,
                    "page[size]" => 200
                ]
            ])->then(function($response) use($event) {
                // filter by artist
                $page = $response->getBody()->getContents();
                $json = json_decode($page);
                $album = null;
                foreach($json->data as $data) {
                    if($data->attributes->coll) {
                        foreach($data->attributes->tracks as $track) {
                            if(self::testArtist($track->artist, $event["track_artist"])) {
                                $album = $data;
                                $album->attributes->artist = $track->artist;
                                break 2;
                            }
                        }
                    } else if(self::testArtist($data->attributes->artist, $event["track_artist"])) {
                        $album = $data;
                        break;
                    }
                }

                return $album;
            }, function($e) {
                $this->log("get tracks failed: " . $e->getMessage());

                // continue with remaining fulfilled callbacks
                return null;
            });
        })->then(function($album) use($event, &$show, &$trackName) {
            // add track
            if($album) {
                return $this->zk->postAsync($show . '/events', [
                    RequestOptions::JSON => [
                        'data' => [
                            'type' => 'event',
                            'attributes' => [
                                'type' => 'spin',
                                'artist' => $album->attributes->artist,
                                'album' => $album->attributes->album,
                                'track' => $trackName,
                                'label' => isset($album->relationships->label) ?
                                    $album->relationships->label->meta->name :
                                    "(Unknown)"
                            ],
                            'relationships' => [
                                'album' => [
                                    'data' => [
                                        'type' => 'album',
                                        'id' => $album->id
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]);
            } else {
                return $this->zk->postAsync($show . '/events', [
                    RequestOptions::JSON => [
                        'data' => [
                            'type' => 'event',
                            'attributes' => [
                                'type' => 'spin',
                                'artist' => $event["track_artist"],
                                'album' => "",
                                'track' => $trackName,
                                'label' => ""
                            ]
                        ]
                    ]
                ]);
            }
        })->then(null, function($e) {
            if($e instanceof \Throwable)
                $this->log($e->getMessage());
        });

        $this->unpackPromisesAsync();
    }

    public function proxy(\Ratchet\Client\WebSocket $conn) {
        // The WebSocket server may not have its keepalive correctly
        // configured, in which case it will quietly stop sending data
        // after a certain period of inactivity.
        //
        // The socket.io pings on top of WebSockets *should* keep the
        // underlying connection alive, but experientially, this seems
        // not always to be the case.
        //
        // To ensure the connection has not quietly gone away, we
        // examine the socket.io ping times:  If a ping has not been
        // received within 60 seconds, we'll restart the connection.

        $timer = $this->loop->addPeriodicTimer(60, function() use($conn) {
            if($this->lastPing && time() - $this->lastPing > 60) {
                // no socket.io ping in 60 seconds
                $conn->close(1006, 'Underlying connection timed out');
            }
        });

        $conn->on('message', function($msg) use($conn) {
            if(preg_match("/^(\d+)(.+)?$/", $msg, $matches)) {
                switch($matches[1]) {
                case 0:
                    // init
                    $conn->send('40');
                    break;
                case 2:
                    // ping
                    $conn->send('3');
                    $this->lastPing = time();
                    break;
                case 42:
                    // message
                    if(preg_match("/^\[\"(.+)\",(?={)(.+)\]$/", $matches[2], $matches) &&
                            $matches[1] == "newtrack")
                        $this->addTrack(json_decode($matches[2], true));
                    break;
                }
            }
        });

        $conn->on('close', function($code = null, $reason = null) use($timer) {
            $this->lastPing = null;
            $this->loop->cancelTimer($timer);

            $this->log("Connection closed: $reason ($code), reconnecting");

            // try to reconnect in 10 seconds
            $this->loop->addTimer(10, function () {
                $this->reconnect();
            });
        });
    }
}
