<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2023 Jim Mason <jmason@ibinx.com>
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

class PushFormPostProxy extends PushHttpProxy {
    public function message(\Ratchet\RFC6455\Messaging\Message $msg) {
        $val = json_decode($msg, true);
        $qs = http_build_query($val);
        foreach($this->httpEndpoints as $key => $endpoint) {
            if(!is_string($key)) {
                try {
                    $this->httpClient->post($endpoint,
                            ['Content-Type' => 'application/x-www-form-urlencoded'], $qs);
                } catch(\Exception $e) {
                    error_log("PushFormPostProxy: $endpoint:" . $e->getMessage());
                }
            }
        }
    }
}
