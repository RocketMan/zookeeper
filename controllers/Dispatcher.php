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

if(!file_exists(__DIR__."/../vendor/autoload.php")) {
    if(php_sapi_name() != "cli") {
        error_log("Composer not configured");
        http_response_code(500); // 500 Internal Server Error
    }
    die("Composer is not configured.  See INSTALLATION.md for information.\n");
}

require_once __DIR__."/../vendor/autoload.php";

use ZK\Engine\Config;
use ZK\Engine\Engine;

class Dispatcher {
    private $menu;
    private $controllers;

    public function __construct() {
        // UI configuration file
        $this->menu = new Config('ui_config', 'menu');
        $customMenu = Engine::param('custom_menu');
        if($customMenu)
            $this->menu->merge($customMenu);

        // Controllers
        $this->controllers = new Config('ui_config', 'controllers');
        $customControllers = Engine::param('custom_controllers');
        if($customControllers)
            $this->controllers->merge($customControllers);
    }

    /**
     * return menu entry that matches the specified action
     */
    public function match($action) {
        return $this->menu->iterate(function($entry) use($action) {
            if($entry[1] == $action || substr($entry[1], -1) == '%' &&
                    substr($entry[1], 0, -1) == substr($action, 0, strlen($entry[1])-1))
                return $entry;
        });
    }

    /**
     * dispatch action to the appropriate menu item/command target
     */
    public function dispatch($action, $subaction, $session) {
        $entry = $this->match($action);

        // If no action was selected or if action is unauthorized,
        // default to the first one
        if(!$entry || !$session->isAuth($entry[0]))
            $entry = $this->menu->default();

        $handler = new $entry[3]();
        if($handler instanceof CommandTarget)
            $handler->process($action, $subaction, $session);
    }

    /**
     * indicate whether the specified action is authorized for the session
     */
    public function isActionAuth($action, $session) {
        $entry = $this->match($action);
        return !$entry || $session->isAuth($entry[0]);
    }

    /**
     * compose the menu for the specified session
     */
    public function composeMenu($action, $session) {
        $result = [];
        $this->menu->iterate(function($entry) use(&$result, $action, $session) {
            if($entry[2] && $session->isAuth($entry[0])) {
                $baseAction = substr($entry[1], -1) == '%'?
                            substr($entry[1], 0, -1):$entry[1];
                $selected = $entry[1] == $action ||
                        substr($entry[1], -1) == '%' &&
                        substr($entry[1], 0, -1) ==
                                substr($action, 0, strlen($entry[1]) - 1);
                $result[] = [ 'action' => $baseAction,
                              'label' => $entry[2],
                              'selected' => $selected ];
            }
        });
        return $result;
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
            $impl->processRequest($this);
    }
}
