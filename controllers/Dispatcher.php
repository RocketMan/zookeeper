<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2019 Jim Mason <jmason@ibinx.com>
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

include __DIR__."/../engine/Engine.php";

use ZK\Engine\Engine;

class Dispatcher {
    private $menu;
    private $controllers;

    public function __construct() {
        spl_autoload_register(function($class) {
            // extract leaf class name
            $p = strrchr($class, '\\');
            $p = $p?substr($p, 1):$class;
            if(is_file(__DIR__."/../custom/${p}.php"))
                include __DIR__."/../custom/${p}.php";

            else if(is_file(__DIR__."/${p}.php"))
                include __DIR__."/${p}.php";

            else if(is_file(__DIR__."/../ui/${p}.php"))
                include __DIR__."/../ui/${p}.php";

            else if(is_file(__DIR__."/../ui/3rdp/${p}.php"))
                include __DIR__."/../ui/3rdp/${p}.php";
        });

        // UI configuration file
        include __DIR__.'/../config/ui_config.php';
        if(isset($menu) && is_array($menu)) {
            $this->menu = $menu;
            $customMenu = Engine::param('custom_menu');
            if($customMenu)
                $this->menu = array_merge($this->menu, $customMenu);
        }

        // Controllers
        if(isset($controllers)) {
            $this->controllers = $controllers;
            $customControllers = Engine::param('custom_controllers');
            if($customControllers)
                $this->controllers = array_merge($this->controllers, $customControllers);
        }
    }

    /**
     * return menu entry that matches the specified action
     */
    public function match($action) {
        foreach($this->menu as $entry) {
            if($entry[1] == $action || substr($entry[1], -1) == '%' &&
                    substr($entry[1], 0, -1) == substr($action, 0, strlen($entry[1])-1))
                return $entry;
        }
    }

    /**
     * dispatch action to the appropriate menu item/command target
     */
    public function dispatch($action, $subaction, $session) {
        $entry = $this->match($action);

        // If no action was selected or if action is unauthorized,
        // default to the first one
        if(!$entry || !$session->isAuth($entry[0]))
            $entry = $this->menu[0];

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
        $result = array();
        foreach ($this->menu as $entry) {
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
        }
        return $result;
    }

    /**
     * dispatch request to the specified controller
     */
    public function processRequest($controller="") {
        if(isset($this->controllers)) {
            if(empty($controller) ||
                    !array_key_exists($controller, $this->controllers))
                $controller = array_keys($this->controllers)[0];
            $impl = new $this->controllers[$controller]();
            if($impl instanceof IController)
                $impl->processRequest($this);
        }
    }
}
