<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2024 Jim Mason <jmason@ibinx.com>
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

use ZK\Controllers\NowAiringServer;

use Ratchet\RFC6455\Messaging\Frame;

/**
 * PushHttpProxy transforms a websocket push event stream into server-
 * initiated HTTP requests
 *
 * To use, in the `config.php` configuration file, include the stanza:
 *
 *    'push_proxy' => [
 *        [
 *            'proxy' => ZK\PushNotification\PushHttpProxy::class,
 *            'ws_endpoint' => 'wss://example/source/endpoint',
 *            'http_endpoints' => [ 'https://example/target/endpoint' ]
 *        ],
 *        ...repeat for additional proxies...
 *    ],
 *
 * where:
 *    'proxy' specifies this class or a derivative;
 *    'ws_endpoint' is the ws push event stream to subscribe to
 *        generally, this will be your Zookeeper Online ws endpoint
 *        (e.g., wss://example.org/push/onair);
 *    'http_endpoints' is an array of targets to receive the HTTP requests
 *        In addition, you may include a key `filter`, the value of
 *        which is a function that will be called for each event; e.g.,
 *               'filter' => function($event) {
 *                   // apply any desired processing here
 *
 *                   // call the instance method `dispatch` to send
 *                   // the event to the endpoints
 *                   $this->dispatch($event);
 *               }
 *
 * This class POSTs the raw json data to the HTTP endpoint.  If you
 * want a conventional FORM POST, use the subclass PushFormPostProxy.
 *
 * See INSTALLATION.md for details on installing and configuring push
 * notifications.
 *
 * We allow dynamic properties specifically for use by the 'filter'
 * function (see above).
 */
#[\AllowDynamicProperties]
class PushHttpProxy {
    protected $loop;
    protected $subscriber;
    protected $httpClient;
    protected $wsEndpoint;
    protected $httpEndpoints;
    protected $current;

    public function __construct(\React\EventLoop\LoopInterface $loop) {
        $this->loop = $loop;
        $this->subscriber = new Subscriber($loop);
        $this->httpClient = new \React\Http\Browser($loop);
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
        $this->httpEndpoints = $httpEndpoints;
        $this->reconnect();
    }

    protected static function newMessage(?string $json = null) :
            \Ratchet\RFC6455\Messaging\Message {
        if($json === null)
            $json = NowAiringServer::toJson(null, null);

        $msg = new \Ratchet\RFC6455\Messaging\Message();
        $msg->addFrame(new Frame($json, false, Frame::OP_TEXT));
        return $msg;
    }

    public function message(\Ratchet\RFC6455\Messaging\Message $msg) {
        foreach($this->httpEndpoints as $key => $endpoint)
            if(!is_string($key))
                $this->httpClient->post($endpoint,
                            ['Content-Type' => 'application/json'], (string)$msg)->then(null, function($e) {
                                echo "PushHttpProxy: " . $e->getMessage() . "\n";
                            });
    }

    public function dispatch(\Ratchet\RFC6455\Messaging\Message $msg) {
        if(strcmp($this->current, $msg)) {
            $this->current = $msg;
            $this->message($msg);
        }
    }

    public function proxy(\Ratchet\Client\WebSocket $conn) {
        $closure = array_key_exists("filter", $this->httpEndpoints) ?
            \Closure::bind($this->httpEndpoints["filter"], $this, $this) :
            [ $this, 'dispatch' ];

        $conn->on('message', $closure);

        $conn->on('close', function ($code = null, $reason = null) {
            echo "Connection closed: $reason ($code), reconnecting\n";

            // try to reconnect in 10 seconds
            $this->loop->addTimer(10, function () {
                $this->reconnect();
            });
        });
    }
}
