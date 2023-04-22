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

namespace ZK\UI;

use ZK\Controllers\CommandTarget;

abstract class MenuItem extends CommandTarget {
    protected $title;
    protected $template;
    protected $extra;

    public function getTitle() { return $this->title; }
    public function getTemplate() { return $this->template; }
    public function getExtra() { return $this->extra; }

    public function newEntity($entityClass) {
        $obj = parent::newEntity($entityClass);
        if($obj)
            $obj->title = &$this->title;
        return $obj;
    }

    public function getSubactions($action) { return []; }

    public function composeSubmenu($action, $subaction) {
        $result = [];
        $subactions = $this->getSubactions($action);
        foreach($subactions as $item) {
            $entry = new MenuEntry($item);
            if($entry->label && $this->session->isAuth($entry->access)) {
                $subactionLen = strlen($entry->action);
                $selected = $subactionLen ?
                    substr($subaction, 0, $subactionLen) == $entry->action :
                    $subaction == $entry->action;
                $result[] = [ 'subaction' => $entry->action,
                              'label' => $entry->label,
                              'selected' => $selected ];
            }
        }
        return $result;
    }

    public function dispatchSubaction($action, $subaction, $extra=0) {
        $this->extra = $extra;

        // Dispatch the selected subaction
        $subactions = $this->getSubactions($action);
        $processed = 0;
        foreach($subactions as $item) {
            $entry = new MenuEntry($item);
            if(($subaction == $entry->action) && $this->session->isAuth($entry->access)) {
                $this->{$entry->implementation}();
                $processed = 1;
                break;
            }
        }
    
        // If no subaction was dispatched, default to the first one
        if(!$processed) {
            $entry = new MenuEntry($subactions[0]);
            $this->{$entry->implementation}();
        }
    }
}
