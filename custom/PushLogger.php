<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2022 Jim Mason <jmason@ibinx.com>
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

/**
 * PushLogger logs playlist start and end events via stdout
 *
 * To use, in the `config.php` configuration file, include the stanza:
 *
 *    'push_proxy' => [
 *        [
 *            'proxy' => ZK\PushNotification\PushLogger::class,
 *            'ws_endpoint' => 'ws://127.0.0.1:32080/push/onair',
 *            'http_endpoints' => []
 *        ],
 *        ...repeat for additional proxies...
 *    ],
 *
 * See INSTALLATION.md for details on installing and configuring push
 * notifications.
 */
class PushLogger extends PushHttpProxy {
    protected $event = [ "show_id" => 0 ];

    public function message(\Ratchet\RFC6455\Messaging\Message $msg) {
        $event = json_decode($msg, true);
        if($event && $event['show_id'] != $this->event['show_id']) {
            echo "Now airing: " .
                ($event['show_id'] ? $event['name'] . " with " . $event['airname'] : "[no playlist]") . "\n";

            $this->event = $event;
        }
    }
}
