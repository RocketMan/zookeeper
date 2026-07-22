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

/**
 * Contract for a hosted service
 *
 * The constructor is dependency injected, including an array config
 * parameter with configuration metadata.
 *
 * As the service runs on a ReactPHP event loop, it must never perform
 * any operation which could block.
 */
interface IService {
    const WS_ENDPOINT = "ws_endpoint";
    const HTTP_ENDPOINTS = "http_endpoints";

    /**
     * Start the service
     *
     * This method is invoked prior to starting of the event loop
     */
    function start();
}
