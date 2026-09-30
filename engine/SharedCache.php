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

namespace ZK\Engine;

use ZK\Service\ServiceDriver;

class SharedCache {
    public function __construct(
        protected IConfig $config,
    ) {}

    public function get(string $key, ?string $default = null): ?string {
        if (!$this->config->get('push_enabled', true))
            return null;

        $data = "resolve($key)";

        $wsserver = explode(':', ServiceDriver::DEFAULT_WSSERVER);
        $addr = $wsserver[0];
        $port = $wsserver[1];

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_sendto($socket, $data, strlen($data), 0, $addr, $port);

        $data = null;
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO,
                [ 'sec' => 0, 'usec' => ServiceDriver::RESOLVER_CACHE_TIMEOUT ]);
        socket_recvfrom($socket, $data, 256, 0, $addr, $port);
        socket_close($socket);
        return $data == "null" ? $default : $data;
    }

    public function set(string $key, string $value): void {
        if (!$this->config->get('push_enabled', true))
            return;

        $data = "resolve($key, $value)";

        $wsserver = explode(':', ServiceDriver::DEFAULT_WSSERVER);
        $addr = $wsserver[0];
        $port = $wsserver[1];

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_sendto($socket, $data, strlen($data), 0, $addr, $port);
        socket_close($socket);
    }
}
