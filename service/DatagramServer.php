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

use React\Cache\CacheInterface;
use React\EventLoop\LoopInterface;
use React\Promise;

class DatagramServer implements IService {
    public function __construct(
        protected LoopInterface $loop,
        protected CacheInterface $resolverCache,
        protected NowAiringServer $nas,
    ) {}

    protected function resolve($server, $addr, $key, $value) {
        if($value !== null)
            $this->resolverCache->set($key, $value);
        else
            $value = $this->resolverCache->get($key);

        Promise\resolve($value)->then(function($result) use ($server, $addr) {
            $server->send($result ?? "null", $addr);
        });
    }

    public function start() {
        $dgfact = new \React\Datagram\Factory($this->loop);
        $dgfact->createServer(PushServer::WSSERVER_HOST . ":" .
                              PushServer::WSSERVER_PORT)->then(
            function(\React\Datagram\Socket $client) {
                $client->on('message', function($message, $addr, $client) {
                    // echo "received $message from $addr\n";

                    if(preg_match("/^loadImages\((\d+)(,\s*(\d+))?\)$/", $message, $matches))
                        $this->nas->loadImages($matches[1], $matches[3] ?? 0);
                    else if(preg_match("/^resolve\((.+?)(,\s*(.+))?\)$/", $message, $matches))
                        $this->resolve($client, $addr, $matches[1], $matches[3] ?? null);
                    else if($message && $message[0] == '{')
                        $this->nas->sendNotification($message);
                    else // empty message means poll database
                        $this->nas->refreshOnNow();
            });
        });
    }
}

