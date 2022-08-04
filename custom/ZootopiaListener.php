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

    public function __construct(\React\EventLoop\LoopInterface $loop) {
        $this->loop = $loop;
        $this->subscriber = new \Ratchet\Client\Connector($loop);
    }

    protected function reconnect() {
        ($this->subscriber)($this->wsEndpoint)->then([$this, 'proxy'], function ($e) {
            $firstLine = trim(strtok($e->getMessage(), "\n"));
            echo "Could not connect: $firstLine, retrying\n";

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
                !preg_match("/zootopia/i", $event["name"]))
            return;

        try {
            $response = $this->zk->get('api/v1/playlist', [
                RequestOptions::QUERY => [
                    "filter[date]" => "onnow"
                ]
            ]);
        } catch(\Exception $e) {
            echo "logTrack: onnow failed: " . $e->getMessage() . "\n";
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
                echo "logTrack: create show failed: " . $e->getMessage() . "\n";
                return;
            }

            $success = $response->getStatusCode() == 201;
            if(!$success) {
                echo "logTrack: could not create show '" .
                                $this->config["title"] . "' " .
                                $date . " " . $time . "\n";
                return;
            }

            echo "logTrack: created " . $this->config["title"] .
                        " with " . $this->config["airname"] .
                        " $date $time\n";

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
                    echo "logTrack: could not insert caption for show '" .
                                    $this->config["title"] . "' " .
                                    $date . " " . $time . "\n";
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
                if(!strcasecmp($data->attributes->artist, $event["track_artist"])) {
                    $album = $data;
                    break;
                }
            }
        } catch(\Exception $e) {
            echo "logTrack: get tracks failed: " . $e->getMessage() . "\n";
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
            echo "logTrack: insert track failed: " . $e->getMessage() . "\n";
            return;
        }

        $success = $response->getStatusCode() == 200;
        if(!$success)
            echo "logTrack: could not insert track: " .
                    $response->getStatusCode() . " " .
                    $response->getReasonPhrase() . " " .
                    $response->getBody()->getContents() . "\n";
    }

    public function proxy(\Ratchet\Client\WebSocket $conn) {
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
            echo "Connection closed: $reason ($code), reconnecting\n";

            // try to reconnect in 10 seconds
            $this->loop->addTimer(10, function () {
                $this->reconnect();
            });
        });
    }
}
