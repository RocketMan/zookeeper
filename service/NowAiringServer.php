<?php

/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2026 Jim Mason <jmason@ibinx.com>
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

namespace ZK\Service;

use ZK\Engine\Engine;

use Psr\Http\Message\ResponseInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class NowAiringServer implements IService, MessageComponentInterface {
    const TIME_FORMAT_INTERNAL = "Y-m-d Hi"; // eg, 2019-01-01 1234

    const QUERY_DELAY = 5;  // in seconds

    const TTL_SECONDS = 5;  // validity in seconds for injectImageData request

    const FORM_POST = [ 'Content-Type' => 'application/x-www-form-urlencoded' ];

    protected $clients;
    protected $timer;
    protected $secret;

    protected $current;
    protected $nextSpin;

    protected $server;
    protected $imageQ;

    public static function toJson($show, $spin) {
        if (is_array($show) || is_array($spin)) {
            $show = $attrs = (object)$show;
            $spin = (object)$spin;
            if ($show) {
                if (isset($show->description))
                    $attrs->name = $show->description;
                if (isset($show->showdate))
                    $attrs->date = $show->showdate;
                if (isset($show->showtime))
                    $attrs->time = $show->showtime;
            }
        } else
            $attrs = $show ? $show->attributes : null;

        $val['name'] = $show ? $attrs->name : '';
        $val['airname'] = $show ? $attrs->airname : '';
        $val['show_id'] = $show ? (int)$show->id : 0;
        if($show && isset($attrs->date) && isset($attrs->time)) {
            $date = $attrs->date;
            list($from, $to) = explode("-", $attrs->time);
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
        $val['id'] = $spin ? (int)$spin->id : 0;
        $val['track_title'] = $spin ? $spin->track : '';
        $val['track_artist'] = $spin ? $spin->artist : '';
        $val['track_album'] = $spin ? $spin->album : '';
        $tag = $spin ? ($spin->{'xa:relationships'} ?? '') : '';
        $val['track_tag'] = $tag ? $tag->album->data->id : null; // null for empty/zero tag
        if ($spin && ($spin->tag ?? null))
            $val['track_tag'] = $spin->tag;
        $created = $spin ? $spin->created : null;

        $val['track_time'] = $created ? $created : '';
        $val['type'] = 'zookeeper';
        $val['event'] = $val['id'] ? 'track' :
                            ($val['show_id'] ? 'show' : 'none');
        return json_encode($val);
    }

    public function __construct(protected LoopInterface $loop) {
        $this->clients = new \SplObjectStorage;
        $this->imageQ = new \SplQueue;

        $config = Engine::param('discogs');
        if ($config) {
            $apiKey = $config['apikey'] ?? null;
            $clientSecret = $config['client_secret'] ?? null;
            $this->secret = $apiKey ?: $clientSecret;
        }

        $urls = Engine::param('urls');
        $browser = new Browser($loop);
        $this->server = $browser->
                withBase($urls['base_url'])->
                withHeader('User-Agent', Engine::UA);
    }

    /*
     * fetch on-air track from service and dispatch notifications
     */
    protected function loadOnNow() {
        $this->server->get('api/v1/playlist?filter[date]=onnow&ts=1')
            ->then(function(ResponseInterface $response) {
                try {
                    $r = json_decode($response->getBody(), false);
                    $data = $r->data;
                    $show = $current = null;
                    if (count($data)) {
                        $show = $data[0];
                        $events = $show->attributes->events ?? [];
                        $spins = array_filter($events, function($event) {
                            return isset($event->type)
                                && $event->type === 'spin'
                                && isset($event->created);
                        });

                        $now = date('Y-m-d H:i:s');

                        $pastOrCurrentSpins = array_filter($spins, function($spin) use ($now) {
                            return $spin->created <= $now;
                        });

                        $futureSpins = array_filter($spins, function($spin) use ($now) {
                            return $spin->created > $now;
                        });

                        $current = !empty($pastOrCurrentSpins) ? end($pastOrCurrentSpins) : null;
                        $this->nextSpin = !empty($futureSpins) ? reset($futureSpins) : null;
                    }

                    $current = self::toJson($show, $current);
                    if ($this->current != $current) {
                        $this->current = $current;
                        $this->sendNotification();
                    }
                } catch (\Throwable $t) {
                    error_log("NowAiringServer::loadOnNow: " . $t->getMessage());
                }
            }, function() {});
    }

    protected function worker() {
        // echo "worker awake\n";
        $this->loadOnNow();
    }

    protected function scheduleWorker() {
        if($this->clients->count() > 0) {
            $now = new \DateTime();
            if($this->nextSpin) {
                $next = new \DateTime($this->nextSpin->created);
                $timeToNext = $next->getTimestamp() - $now->getTimestamp();
                if($timeToNext < 0 || $timeToNext > 60)
                    $timeToNext = 0;
            } else
                $timeToNext = 0;

            $delta = $timeToNext?($timeToNext + 1):
                                    (61 - (int)$now->format("s"));

            $this->timer = $this->loop->addTimer($delta, function() {
                try {
                    $this->worker();
                } catch(\Throwable $e) {
                    error_log("NowAiringServer::worker: " . $e->getMessage());
                }
                $this->scheduleWorker();
            });
        }
    }

    public function refreshOnNow() {
        if ($this->clients->count() > 0)
            $this->loadOnNow();
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        if($this->clients->count() == 1) {
            $this->loadOnNow();

            // start worker
            $this->scheduleWorker();
        } else
            $this->sendNotification(null, $conn);

        // echo "New connection {$conn->resourceId}\n";
    }

    protected function startQ() {
        $this->loop->futureTick(function() {
            try {
                $this->processImageQueue();
            } catch(\Exception $e) {
                error_log("NowAiringServer::processImageQueue: " . $e->getMessage());
                // TBD delay and retry
            }
        });
    }

    protected function scheduleNext() {
        if(!$this->imageQ->isEmpty()) {
            $this->loop->addTimer(self::QUERY_DELAY, function() {
                try {
                    $this->processImageQueue();
                } catch(\Exception $e) {
                    error_log("NowAiringServer::processImageQueue: " . $e->getMessage());
                    // TBD delay and retry
                }
            });
        }
    }

    protected function signMessage($msg) {
        if (!$this->secret) return "{}";

        $uuid = sha1(uniqid(rand()));
        $expires = time() + self::TTL_SECONDS;
        $payload = $msg . '|' . $uuid . '|' . $expires;
        $sig = hash_hmac('sha256', $payload, $this->secret);
        return json_encode([
            'uuid' => $uuid,
            'expires' => $expires,
            'signature' => $sig
        ]);
    }

    public static function validateSig($msg, $sig, $secret) {
        try {
            $data = json_decode($sig, false);
            if (!$data) return false;
            $expires = $data->expires ?? 0;
            $payload = $msg . '|' . ($data->uuid ?? '') . '|' . $expires;
            $signature = hash_hmac('sha256', $payload, $secret);
            return time() < $expires &&
                    hash_equals($signature, $data->signature ?? '');
        } catch(\Throwable $t) {
            error_log("NowAiringServer::validateSig: " . $t->getMessage());
        }
        return false;
    }

    protected function processImageQueue() {
        if(!$this->imageQ->isEmpty()) {
            $entry = $this->imageQ->dequeue();
            $msg = self::toJson(null, $entry);
            // side-effects db insertion of missing album and artist artwork
            $this->server->post('?target=push&action=injectImageData',
                    self::FORM_POST,
                    http_build_query([
                        'msg' => $msg,
                        'sig' => $this->signMessage($msg)
                    ]))->then(function(ResponseInterface $response) {
                        $this->scheduleNext();
                    }, function(\Exception $e) {
                        error_log("NowAiringServer::processImageQueue: " . $e->getMessage());
                        $this->scheduleNext();
                    });
        }
    }

    protected function enqueueEntry($spin) {
        if(!$spin->created) {
            // no timestamp, don't bother
            return;
        }

        if(preg_match('/(\.gov|\.org|GED|Literacy|NIH|Ad\ Council|Lift\ Jesus)/', $spin->artist . $spin->album . $spin->label) || empty(trim($spin->artist))) {
            // it's probably a PSA coded as a spin; let's skip it
            return;
        }

        $this->imageQ->enqueue($spin);
    }

    public function loadImages($playlist, $track) {
        if($track) {
            $this->server->get("api/v2/playlist/$playlist/events?filter[event.id]=$track&ts=1")
                ->then(function(ResponseInterface $response) {
                    try {
                        $r = json_decode($response->getBody(), false);
                        $data = $r->data;
                        if (count($data)) {
                            $event = $data[0];
                            if ($event->attributes->type == 'spin') {
                                $tag = ($event->relationships ?? null)?->album->data->id ?? 0;
                                if ($tag)
                                    $event->attributes->tag = $tag;
                                $event->attributes->id = $event->id;
                                $this->enqueueEntry($event->attributes);

                                if($this->imageQ->count() == 1)
                                    $this->startQ();
                            }
                        }
                    } catch(\Throwable $e) {
                        error_log("NowAiringServer::loadImages: " . $e->getMessage());
                    }
                });
        } else {
            $this->server->get("api/v1/playlist/$playlist?ts=1")
                ->then(function(ResponseInterface $response) use($playlist, $track) {
                    try {
                        $r = json_decode($response->getBody(), false);
                        $show = $r->data;
                        $events = $show->attributes->events ?? [];
                        $spins = array_filter($events, function($event) {
                            return isset($event->type)
                                && $event->type === 'spin';
                        });

                        $start = $this->imageQ->count();
                        $visited = [];
                        foreach ($spins as $spin) {
                            $tag = ($spin->{'xa:relationships'} ?? null)?->album->data->id ?? 0;
                            $key = $spin->artist . $tag;
                            if (!key_exists($key, $visited)) {
                                if ($tag)
                                    $spin->tag = $tag;
                                $this->enqueueEntry($spin);
                                $visited[$key] = 1;
                            }
                        }

                        $queued = $this->imageQ->count() - $start;
                        if ($queued) {
                            echo "NowAiringServer::loadImages($playlist, $track): $queued queued\n";

                            if (!$start)
                                $this->startQ();
                        }
                    } catch(\Throwable $e) {
                        error_log("NowAiringServer::loadImages: " . $e->getMessage());
                    }
                });
        }
    }

    protected function asyncInjectImageData(string $msg, ?ConnectionInterface $client) {
        $this->server->post('?target=push&action=injectImageData',
                self::FORM_POST,
                http_build_query([
                    'msg' => $msg,
                    'sig' => $this->signMessage($msg)
                ]))->then(function(ResponseInterface $response) use($client) {
                    $msg = $response->getBody();

                    if ($client)
                        $client->send($msg);
                    else {
                        foreach ($this->clients as $client)
                            $client->send($msg);
                    }
                }, function(\Exception $e) use($client, $msg) {
                    error_log("NowAiringServer::asyncInjectImageData: " . $e->getMessage());

                    if ($client)
                        $client->send($msg);
                    else {
                        foreach ($this->clients as $client)
                            $client->send($msg);
                    }
                });
    }

    public function sendNotification(?string $msg = null, ?ConnectionInterface $client = null) {
        if ($msg) {
            if($this->current != $msg)
                $this->current = $msg;
            else
                return;
        } else
            $msg = $this->current;

        if ($msg)
            $this->asyncInjectImageData($msg, $client);
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
        // echo "Connection {$conn->resourceId} disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        error_log("NowAiringServer::onError: " . $e->getMessage());
        $conn->close();
    }

    public function start() {
        $wsserver = new \Ratchet\WebSocket\WsServer($this);
        $wsserver->enableKeepAlive($this->loop, 30);
        $routes = new RouteCollection();
        $routes->add('/push/onair', new Route('/push/onair', [
            '_controller' => $wsserver
        ]));
        $router = new \Ratchet\Http\Router(
            new UrlMatcher($routes, new RequestContext()));
        new IoServer(new \Ratchet\Http\HttpServer($router),
            new \React\Socket\Server(PushServer::WSSERVER_HOST . ":" .
                                     PushServer::WSSERVER_PORT, $this->loop));
    }
}
