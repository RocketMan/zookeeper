<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2021 Jim Mason <jmason@ibinx.com>
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

use ZK\Engine\Config;
use ZK\Engine\Engine;

class Dispatcher {
    private $controllers;

    public function __construct() {
        // Controllers
        $this->controllers = new Config('controller_config', 'controllers');
        $customControllers = Engine::param('custom_controllers');
        if($customControllers)
            $this->controllers->merge($customControllers);
    }

    /**
     * dispatch request to the specified controller
     */
    public function processRequest($controller="") {
        $implClass = empty($controller) ||
                        !($p = $this->controllers->getParam($controller)) ?
            $this->controllers->default():$p;
        $impl = new $implClass();
        if($impl instanceof IController)
            $impl->processRequest();
    }
}
