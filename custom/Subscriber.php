<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2025 Jim Mason <jmason@ibinx.com>
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
 * Portions of this code are adapted from ratchet/pawl (C) 2014 Chris Boden
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */

namespace ZK\PushNotification;

use Ratchet\Client\Connector;
use Ratchet\RFC6455\Handshake\ClientNegotiator;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectorInterface;

/**
 * Subscriber is a Ratchet\Client\Connector shim to deal with inconsistencies
 * in ratchet/rfc6455 that were introduced between versions 0.3.x and 0.4.0.
 *
 * See https://github.com/ratchetphp/Pawl/issues/166
 */
class Subscriber extends Connector {
    public function __construct(LoopInterface $loop = null, ConnectorInterface $connector = null) {
        $this->_loop = $loop ?: Loop::get();

        if (null === $connector) {
            $connector = new \React\Socket\Connector([
                'timeout' => 20
            ], $this->_loop);
        }

        $this->_connector  = $connector;
        $this->_negotiator = new ClientNegotiator;
    }
}
