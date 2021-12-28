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
use ZK\Engine\OnNowFilter;
use ZK\Engine\PlaylistEntry;
use ZK\Engine\PlaylistObserver;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class NowAiringServer implements MessageComponentInterface {
    const TIME_FORMAT_INTERNAL = "Y-m-d Hi"; // eg, 2019-01-01 1234

    protected $clients;
    protected $loop;
    protected $timer;

    protected $current;
    protected $nextSpin;

    public static function toJson($show, $spin) {
        $val['name'] = $show?$show['description']:'';
        $val['airname'] = $show?$show['airname']:'';
        $val['show_id'] = $show?(int)$show['id']:0;
        if($show && isset($show['showdate']) && isset($show['showtime'])) {
            $date = $show['showdate'];
            list($from, $to) = explode("-", $show['showtime']);
            $fromStamp = \DateTime::createFromFormat(self::TIME_FORMAT_INTERNAL,
                        $date . " " . $from);
            $toStamp = \DateTime::createFromFormat(self::TIME_FORMAT_INTERNAL,
                        $date . " " . $to);

            // if playlist spans midnight, end time is next day
            if($toStamp < $fromStamp)
                $toStamp->modify("+1 day");

            $val['show_start'] = $fromStamp->format(DATE_RFC3339);
            $val['show_end'] = $toStamp->format(DATE_RFC3339);
        } else {
            $val['show_start'] = '';
            $val['show_end'] = '';
        }
        $val['id'] = $spin?(int)$spin['id']:0;
        $val['track_title'] = $spin?$spin['track']:'';
        $val['track_artist'] = $spin?$spin['artist']:'';
        $val['track_album'] = $spin?$spin['album']:'';
        return json_encode($val);
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
        $event = null;
        $result = Engine::api(IPlaylist::class)->getWhatsOnNow();
        if($show = $result->fetch()) {
            $filter = Engine::api(IPlaylist::class)->getTracksWithObserver($show['id'],
                (new PlaylistObserver())->onSpin(function($entry) use(&$event) {
                    $spin = $entry->asArray();
                    $spin['artist'] = PlaylistEntry::swapNames($spin['artist']);
                    $event = $spin;
                })->onComment(function($entry) use(&$event) {
                    $event = null;
                })->onLogEvent(function($entry) use(&$event) {
                    $event = null;
                })->onSetSeparator(function($entry) use(&$event) {
                    $event = null;
                }), 0, OnNowFilter::class);
        }
        DBO::release();
        $current = self::toJSON($show, $event);
        if($this->current != $current) {
            $this->current = $current;
            $changed = true;
        }
        $this->nextSpin = $show?$filter->peek():null;
        return $changed;
    }

    protected function worker() {
        // echo "worker awake\n";
        if($this->loadOnNow())
            $this->sendNotification();
    }

    protected function scheduleWorker() {
        if($this->clients->count() > 0) {
            $now = new \DateTime();
            if($this->nextSpin) {
                $next = new \DateTime($this->nextSpin->getCreated());
                $timeToNext = $next->getTimestamp() - $now->getTimestamp();
                if($timeToNext < 0 || $timeToNext > 60)
                    $timeToNext = 0;
            } else
                $timeToNext = 0;

            $delta = $timeToNext?($timeToNext + 1):
                                    (61 - (int)$now->format("s"));

            $this->timer = $this->loop->addTimer($delta, function() {
                $this->worker();
                $this->scheduleWorker();
            });
        }
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
            $this->scheduleWorker();
        }
        $this->sendNotification(null, $conn);
        echo "New connection {$conn->resourceId}\n";
    }

    public function sendNotification($msg = null, $client = null) {
        if($msg) {
            if($this->current != $msg)
                $this->current = $msg;
            else
                return;
        } else
            $msg = $this->current;

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
    /**
     * This is an endpoint for internal use only.  There should be
     * no need to change it, but if you do, you must also update the
     * corresponding URI in .htaccess in the project root directory.
     */
    const WSSERVER_HOST = "127.0.0.1";
    const WSSERVER_PORT = 32080;

    public static function sendAsyncNotification($show = null, $spin = null) {
        if(!Engine::param('push_enabled', true))
            return;

        $data = ($show != null)?NowAiringServer::toJson($show, $spin):"";
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_sendto($socket, $data, strlen($data), 0,
                        PushServer::WSSERVER_HOST, PushServer::WSSERVER_PORT);
        socket_close($socket);
    }

    public function processRequest() {
        if(php_sapi_name() != "cli") {
            http_response_code(400);
            return;
        }

        if(!Engine::param('push_enabled', true)) {
            echo "Push notification is disabled.\n\n";
            echo "See INSTALLATION.md for more information.\n";
            return;
        }

        try {
            // websocket server for subscribers
            $loop = \React\EventLoop\Factory::create();
            $nas = new NowAiringServer($loop);
            $wsserver = new \Ratchet\WebSocket\WsServer($nas);
            $wsserver->enableKeepAlive($loop, 30);
            $routes = new RouteCollection();
            $routes->add('/push/onair', new Route('/push/onair', [
                '_controller' => $wsserver
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
                        // echo "received $message from $addr\n";
                        // empty message means poll database
                        if($message)
                            $nas->sendNotification($message);
                        else
                            $nas->refreshOnNow();
                });
            });

            // push proxy server, if configured
            $config = Engine::param('push_proxy');
            if($config) {
                foreach($config as $proxy) {
                    $app = new $proxy['proxy']($loop);
                    $app->connect($proxy['ws_endpoint'],
                                    $proxy['http_endpoints']);
                }
            }

            $loop->run();
        } catch(\Exception $e) {
            error_log("PushServer: " . $e->getMessage());
        }
    }
}
