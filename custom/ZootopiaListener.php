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
    protected $zk;
    protected $lastPing;

    public function __construct(\React\EventLoop\LoopInterface $loop) {
        $this->loop = $loop;
        $this->subscriber = new \Ratchet\Client\Connector($loop);
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
            'base_uri' => $this->config["base_url"],
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
                'X-APIKEY' => $this->config["apikey"]
            ],
            RequestOptions::HTTP_ERRORS => false
        ]);
        $this->reconnect();
    }

    public function logTrack($event) {
        if(($event["type"] ?? null) != "schedule" ||
                !preg_match("/zootopia/i", $event["name"]) ||
                !$event["track_title"])
            return;

        try {
            $response = $this->zk->get('api/v1/playlist', [
                RequestOptions::QUERY => [
                    "filter[date]" => "onnow",
                    "fields[show]" => "-events"
                ]
            ]);
        } catch(\Exception $e) {
            $this->log("get onnow failed: " . $e->getMessage());
            return;
        }

        $page = $response->getBody()->getContents();
        $json = json_decode($page);
        if(sizeof($json->data) &&
                !preg_match("/" .
                    preg_quote($this->config["title"]) . "/i",
                    $json->data[0]->attributes->name))
            return;

        $now = new \DateTime();
        if(sizeof($json->data)) {
            $show = $json->data[0]->links->self;
        } else {
            $date = $now->format("Y-m-d");
            $time = $now->format("Hi");

            $tz = $this->config["tz"];
            $end = \DateTime::createFromFormat(IPlaylist::TIME_FORMAT_SQL,
                        $event["show_end"], $tz ? new \DateTimeZone($tz) : null);
            if($tz)
                $end->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            $time .= "-" . $end->format("Hi");

            try {
                $response = $this->zk->post('api/v1/playlist', [
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
                ]);
            } catch(\Exception $e) {
                $this->log("create show failed: " . $e->getMessage());
                return;
            }

            $success = $response->getStatusCode() == 201;
            if(!$success) {
                $this->log("could not create show '" .
                                $this->config["title"] . "' " .
                                $date . " " . $time);
                return;
            }

            $this->log("created " . $this->config["title"] .
                        " with " . $this->config["airname"] .
                        " $date $time");

            $show = $response->getHeader('Location')[0];

            if(isset($this->config["caption"])) {
                $response = $this->zk->post($show . '/events', [
                    RequestOptions::JSON => [
                        'data' => [
                            'type' => 'event',
                            'attributes' => [
                                'type' => 'comment',
                                'comment' => $this->config["caption"],
                            ]
                        ]
                    ]
                ]);

                $success = $response->getStatusCode() == 200;
                if(!$success) {
                    $this->log("could not insert caption for show '" .
                                    $this->config["title"] . "' " .
                                    $date . " " . $time);
                }
            }
        }

        // lookup album
        $album = null;
        $trackName = preg_match("/^(.+)( \(\d+\))$/", $event["track_title"], $matches) ? $matches[1] : $event["track_title"];

        try {
            $response = $this->zk->get('api/v1/album', [
                RequestOptions::QUERY => [
                    "filter[track]" => $trackName,
                    "page[size]" => 200
                ]
            ]);

            $page = $response->getBody()->getContents();
            $json = json_decode($page);
            $album = null;
            foreach($json->data as $data) {
                if(!strcasecmp(PlaylistEntry::swapNames($data->attributes->artist), $event["track_artist"])) {
                    $album = $data;
                    break;
                }
            }
        } catch(\Exception $e) {
            $this->log("get tracks failed: " . $e->getMessage());
        }

        // add track
        try {
            if($album) {
                $response = $this->zk->post($show . '/events', [
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
                $response = $this->zk->post($show . '/events', [
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
        } catch(\Exception $e) {
            $this->log("insert track failed: " . $e->getMessage());
            return;
        }

        $success = $response->getStatusCode() == 200;
        if(!$success)
            $this->log("could not insert track: " .
                    $response->getStatusCode() . " " .
                    $response->getReasonPhrase() . " " .
                    $response->getBody()->getContents());
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

        $this->loop->addPeriodicTimer(120, function($timer) use ($conn) {
            if($this->lastPing && time() - $this->lastPing > 120) {
                // no socket.io ping in 2 minutes
                $this->lastPing = null;
                $this->loop->cancelTimer($timer);
                $conn->close(1000, 'abort');

                $this->log("Connection timed out, reconnecting");
                $this->reconnect();
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
                        $this->logTrack(json_decode($matches[2], true));
                    break;
                }
            }
        });

        $conn->on('close', function ($code = null, $reason = null) {
            if($reason == 'abort')
                return;

            $this->log("Connection closed: $reason ($code), reconnecting");

            // try to reconnect in 10 seconds
            $this->loop->addTimer(10, function () {
                $this->reconnect();
            });
        });
    }
}
