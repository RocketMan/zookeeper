<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2021 Jim Mason <jmason@ibinx.com>
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

namespace ZK\Controllers;

use ZK\Engine\DBO;
use ZK\Engine\Engine;
use ZK\Engine\IPlaylist;
use ZK\Engine\PlaylistObserver;
use ZK\UI\UICommon as UI;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

if(file_exists(__DIR__."/../vendor/autoload.php")) {
    include(__DIR__."/../vendor/autoload.php");

    class NowAiringServer implements MessageComponentInterface {
        protected $clients;
        protected $loop;
        protected $timer;

        protected $show;
        protected $spin;

        public static function toJson($show, $spin) {
            $val['name'] = $show?$show['description']:'';
            $val['airname'] = $show?$show['airname']:'';
            $val['show_id'] = $show?(int)$show['id']:0;
            $val['id'] = $spin?(int)$spin['id']:0;
            $val['track_title'] = $spin?$spin['track']:'';
            $val['track_artist'] = $spin?$spin['artist']:'';
            $val['track_album'] = $spin?$spin['album']:'';
            return json_encode($val);
        }

        public static function fromJson($msg, &$show, &$spin) {
            $val = json_decode($msg, true);
            $show['description'] = $val['name'];
            $show['airname'] = $val['airname'];
            $show['id'] = $val['show_id'];
            $spin['id'] = $val['id'];
            $spin['track'] = $val['track_title'];
            $spin['artist'] = $val['track_artist'];
            $spin['album'] = $val['track_album'];
        }

        public function __construct($loop) {
            $this->clients = new \SplObjectStorage;
            $this->loop = $loop;
        }

        /*
         * fetch on-air track from database
         *
         * @returns true if changed, false otherwise
         */
        protected function loadOnNow() {
            $changed = false;
            $result = Engine::api(IPlaylist::class)->getWhatsOnNow();
            if($show = $result->fetch()) {
                if(!$this->show || $this->show['id'] != $show['id'])
                    $changed = true;
                $this->show = $show;
                $event = null;
                Engine::api(IPlaylist::class)->getTracksWithObserver($show['id'],
                    (new PlaylistObserver())->onSpin(function($entry) use(&$event) {
                        $spin = $entry->asArray();
                        $spin['artist'] = UI::swapNames($spin['artist']);
                        $event = $spin;
                    }), 0, OnNowFilter::class);
                if($event && $this->spin && $event['id'] != $this->spin['id'] ||
                        ($event xor $this->spin)) {
                    $this->spin = $event;
                    $changed = true;
                }
            } else if($this->show) {
                $this->show = null;
                $this->spin = null;
                $changed = true;
            }
            DBO::release();
            return $changed;
        }

        protected function worker() {
            echo "worker awake\n";
            if($this->loadOnNow())
                $this->sendNotification();
        }

        public function refreshOnNow() {
            if($this->clients->count() > 0 && $this->loadOnNow())
                $this->sendNotification();
        }

        public function onOpen(ConnectionInterface $conn) {
            $this->clients->attach($conn);
            if($this->clients->count() == 1) {
                $this->loadOnNow();

                // start worker
                $now = new \DateTime();
                $sec = $now->format("s");
                $delta = 62 - (int)$sec;
                $this->timer = $this->loop->addTimer($delta, function() {
                    // last client disconnected before the initial timeout?
                    if($this->clients->count() > 0) {
                        $this->timer = $this->loop->addPeriodicTimer(60, function() {
                            $this->worker();
                        });
                        echo "New periodic timer ".spl_object_hash($this->timer)."\n";
                        $this->worker();
                    }
                });
                echo "New one-shot timer ".spl_object_hash($this->timer)."\n";
            }
            $this->sendNotification(null, $conn);
            echo "New connection {$conn->resourceId}\n";
        }

        public function sendNotification($msg = null, $client = null) {
            if($msg)
                self::fromJson($msg, $this->show, $this->spin);
            else
                $msg = self::toJson($this->show, $this->spin);

            if($client)
                $client->send($msg);
            else {
                foreach ($this->clients as $client)
                    $client->send($msg);
            }
        }

        public function onMessage(ConnectionInterface $from, $msg) {}

        public function onClose(ConnectionInterface $conn) {
            $this->clients->detach($conn);

            if($this->clients->count() == 0 && $this->timer) {
                // stop worker
                echo "Cancel timer ".spl_object_hash($this->timer)."\n";
                $this->loop->cancelTimer($this->timer);
                $this->timer = null;
            }
            echo "Connection {$conn->resourceId} disconnected\n";
        }

        public function onError(ConnectionInterface $conn, \Exception $e) {
            error_log("NowAiringServer: " . $e->getMessage());
            $conn->close();
        }
    }

    class PushServer implements IController {
        const WSSERVER_HOST = "127.0.0.1";
        const WSSERVER_PORT = 32080;

        public static function sendAsyncNotification($show = null, $spin = null) {
            $data = ($show != null && $spin != null)?
                    NowAiringServer::toJson($show, $spin):"";
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            socket_sendto($socket, $data, strlen($data), 0,
                            PushServer::WSSERVER_HOST, PushServer::WSSERVER_PORT);
            socket_close($socket);
        }

        public function processRequest($dispatcher) {
            if(php_sapi_name() != "cli") {
                http_response_code(400);
                return;
            }

            try {
                // websocket server for subscribers
                $loop = \React\EventLoop\Factory::create();
                $nas = new NowAiringServer($loop);
                $routes = new RouteCollection();
                $routes->add('/push/onair', new Route('/push/onair', [
                    '_controller' => new \Ratchet\WebSocket\WsServer($nas)
                ]));
                $router = new \Ratchet\Http\Router(
                    new UrlMatcher($routes, new RequestContext()));
                new IoServer(new \Ratchet\Http\HttpServer($router),
                    new \React\Socket\Server(PushServer::WSSERVER_HOST . ":" .
                                             PushServer::WSSERVER_PORT, $loop));

                // datagram server for application
                $dgfact = new \React\Datagram\Factory($loop);
                $dgfact->createServer(PushServer::WSSERVER_HOST . ":" .
                                      PushServer::WSSERVER_PORT)->then(
                    function(\React\Datagram\Socket $client) use($nas) {
                        $client->on('message', function($message, $addr, $client) use($nas) {
                            echo "received $message from $addr\n";
                            // empty message means poll database
                            if($message)
                                $nas->sendNotification($message);
                            else
                                $nas->refreshOnNow();
                    });
                });

                $loop->run();
            } catch(\Exception $e) {
                error_log("PushServer: " . $e->getMessage());
            }
        }
    }
} else {
    /*
     * PushServer stub for push disabled or dependencies missing
     */
    class PushServer implements IController {
        public static function sendAsyncNotification($show = null, $spin = null) {}

        public function processRequest($dispatcher) {
            if(php_sapi_name() != "cli") {
                http_response_code(400);
                return;
            }

            echo "Push notification dependencies are missing.\n\n";
            echo "See INSTALLATION.md for more information.\n";
        }
    }
}
