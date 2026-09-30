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

use ZK\Service\NowAiringServer;
use ZK\Service\ServiceDriver;

class ServiceConnector {
    public function __construct(
        protected IConfig $config,
    ) {}

    public function sendAsyncNotification($show = null, $spin = null):void {
        if(!$this->config->get('push_enabled', true))
            return;

        $wsserver = explode(':', ServiceDriver::DEFAULT_WSSERVER);

        $data = ($show != null)?NowAiringServer::toJson($show, $spin):"";
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_sendto($socket, $data, strlen($data), 0,
                        $wsserver[0], $wsserver[1]);
        socket_close($socket);
    }

    public function lazyLoadImages($playlistId, $trackId = 0): void {
        if(!$this->config->get('push_enabled', true) ||
                !($config = $this->config->get('discogs')) ||
                !$config['apikey'] && !$config['client_id'])
            return;

        $wsserver = explode(':', ServiceDriver::DEFAULT_WSSERVER);

        $data = "loadImages($playlistId, $trackId)";

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_sendto($socket, $data, strlen($data), 0,
                        $wsserver[0], $wsserver[1]);
        socket_close($socket);
    }
}
