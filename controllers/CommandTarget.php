<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2018 Jim Mason <jmason@ibinx.com>
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

namespace ZK\Controllers;

abstract class CommandTarget {
    public $session;

    abstract public function processLocal($action, $subaction);

    public function process($action, $subaction, $session) {
        $this->session = $session;
        $this->processLocal($action, $subaction);
    }

    public function dispatchAction($action, &$actions) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $processed = false;
        foreach($actions as $item) {
            if($item[0] == $action && ($item[2] ?? $method) == $method) {
                $this->{$item[1]}();
                $processed = true;
            }
        }

        if(!$processed)
            $this->{$actions[0][1]}();
    }

    public function newEntity($entityClass) {
        $obj = new $entityClass();
        $obj->session = $this->session;
        return $obj;
    }
}
