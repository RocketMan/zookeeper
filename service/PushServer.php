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

class PushServer {
    /**
     * This is an endpoint for internal use only.  There should be
     * no need to change it, but if you do, you must also update the
     * corresponding URI in .htaccess in the project root directory.
     */
    const WSSERVER_HOST = "127.0.0.1";
    const WSSERVER_PORT = 32080;

    const RESOLVER_CACHE_SIZE = 500;
    const RESOLVER_CACHE_TIMEOUT = 20000; // in usec

    public static function sendAsyncNotification($show = null, $spin = null) {
        if(!Engine::param('push_enabled', true))
            return;

        $data = ($show != null)?NowAiringServer::toJson($show, $spin):"";
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_sendto($socket, $data, strlen($data), 0,
                        PushServer::WSSERVER_HOST, PushServer::WSSERVER_PORT);
        socket_close($socket);
    }

    public static function lazyLoadImages($playlistId, $trackId = 0) {
        if(!Engine::param('push_enabled', true) ||
                !($config = Engine::param('discogs')) ||
                !$config['apikey'] && !$config['client_id'])
            return;

        $data = "loadImages($playlistId, $trackId)";

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_sendto($socket, $data, strlen($data), 0,
                        PushServer::WSSERVER_HOST, PushServer::WSSERVER_PORT);
        socket_close($socket);
    }

    public static function lruCache(string $key, ?string $value = null) {
        $data = $value ? "resolve($key, $value)" : "resolve($key)";

        $addr = PushServer::WSSERVER_HOST;
        $port = PushServer::WSSERVER_PORT;

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_sendto($socket, $data, strlen($data), 0, $addr, $port);

        $data = null;
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO,
                [ 'sec' => 0, 'usec' => self::RESOLVER_CACHE_TIMEOUT ]);
        socket_recvfrom($socket, $data, 256, 0, $addr, $port);
        socket_close($socket);
        return $data == "null" ? null : $data;
    }
}
