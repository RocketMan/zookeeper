<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2022 Jim Mason <jmason@ibinx.com>
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

use ZK\Engine\Engine;
use ZK\Engine\IDJ;
use ZK\Engine\ILibrary;
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
 *                'airname' => 'airname',
 *                'title' => 'show title',
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

    public function __construct(\React\EventLoop\LoopInterface $loop) {
        $this->loop = $loop;
        $this->subscriber = new \Ratchet\Client\Connector($loop);
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
        if(($event["type"] ?? null) != "schedule" ||
                !preg_match("/zootopia/i", $event["name"]) ||
                !$event["track_title"])
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

            // If there is already a show on-air that is not ours,
            // let it continue.
            if($count && !preg_match("/" .
                            preg_quote($this->config["title"]) . "/i",
                            $json->data[0]->attributes->name))
                return new RejectedPromise("DJ On Air");

            $now = new \DateTime();
            switch($count) {
            case 0:
                // No show is currently on-air; create a new show
                $date = $now->format("Y-m-d");
                $time = $now->format("Hi");

                $tz = $this->config["tz"];
                $end = \DateTime::createFromFormat(IPlaylist::TIME_FORMAT_SQL,
                            $event["show_end"], $tz ? new \DateTimeZone($tz) : null);
                if($tz)
                    $end->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                $time .= "-" . $end->format("Hi");

                // create new show
                return $this->zk->postAsync('api/v1/playlist', [
                    RequestOptions::JSON => [
                        'data' => [
                            'type' => 'show',
                            'attributes' => [
                                'name' => $this->config["title"],
                                'date' => $date,
                                'time' => $time,
                                'airname' => $this->config["airname"]
                            ]
                        ]
                    ]
                ])->then(function($response) use($date, $time, &$show) {
                    $this->log("created " . $this->config["title"] .
                                " with " . $this->config["airname"] .
                                " $date $time");
                    $show = $response->getHeader('Location')[0];

                    // add caption
                    if(isset($this->config["caption"])) {
                        return $this->zk->postAsync($show . '/events', [
                            RequestOptions::JSON => [
                                'data' => [
                                    'type' => 'event',
                                    'attributes' => [
                                        'type' => 'comment',
                                        'comment' => $this->config["caption"],
                                    ]
                                ]
                            ]
                        ])->then(null, function($e) use($date, $time) {
                            $this->log("could not insert caption for show '" .
                                            $this->config["title"] . "' " .
                                            $date . " " . $time);
                            // continue with remaining fulfilled callbacks
                        });
                    }
                });
                break;
            case 1:
                // We are already on-air; use the existing show.
                $show = $json->data[0]->links->self;
                break;
            default:
                // An older show is also on-air.  This could be a show
                // that got extended, or a previously scheduled show
                // that just started.
                //
                // End our show now so the other show will go on-air.
                $time = explode('-', $json->data[0]->attributes->time);
                $now->modify("-1 minutes");
                $time[1] = $now->format("Hi");
                $id = $json->data[0]->id;
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
                    return new RejectedPromise("DJ On Air");
                });
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
                    if(!strcasecmp(PlaylistEntry::swapNames($data->attributes->artist), $event["track_artist"])) {
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
                                'label' => $album->relationships->label->meta->name
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
            if($e instanceof \Exception)
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
        // received within 2 minutes, we'll restart the connection.

        $this->loop->addPeriodicTimer(120, function($timer) use($conn) {
            if($this->lastPing && time() - $this->lastPing > 120) {
                // no socket.io ping in 2 minutes
                $this->lastPing = null;
                $this->loop->cancelTimer($timer);
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

        $conn->on('close', function ($code = null, $reason = null) {
            $this->log("Connection closed: $reason ($code), reconnecting");

            // try to reconnect in 10 seconds
            $this->loop->addTimer(10, function () {
                $this->reconnect();
            });
        });
    }
}
