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
    protected $templateVars = [];
    protected $extra;
    protected $tertiary;

    public function getTitle() { return $this->title; }
    public function getTemplate() { return $this->template; }
    public function getTemplateVars() { return $this->templateVars; }
    public function getExtra() { return $this->extra; }
    public function getTertiary() { return $this->tertiary; }

    protected function setTemplate($template) {
        $this->template = $template;
    }

    protected function addVar($key, $value) {
        $this->templateVars[$key] = $value;
    }

    public function newEntity($entityClass) {
        $obj = parent::newEntity($entityClass);
        if($obj) {
            $obj->title = &$this->title;
            $obj->template = &$this->template;
            $obj->templateVars = &$this->templateVars;
            $obj->extra = &$this->extra;
            $obj->tertiary = &$this->tertiary;
        }
        return $obj;
    }

    public function getSubactions($action) { return []; }

    public function composeSubmenu($action, $subaction) {
        $result = [];
        $active = false;
        $subactions = $this->getSubactions($action);
        foreach($subactions as $item) {
            $entry = new MenuEntry($item);
            if($entry->label && $this->session->isAuth($entry->access)) {
                $subactionLen = strlen($entry->action);
                $selected = $subactionLen ?
                    substr($subaction, 0, $subactionLen) == $entry->action :
                    $subaction == $entry->action;
                $active |= $selected;
                $result[] = [ 'subaction' => $entry->action,
                              'label' => $entry->label,
                              'selected' => $selected ];
            }
        }

        if(!$active && count($result))
            $result[0]['selected'] = true;

        return $result;
    }

    public function dispatchSubaction($action, $subaction, $extra=0) {
        if(substr($subaction, -1) == "_") {
            echo json_encode($this->composeSubmenu($action, substr($subaction, 0, -1)));
            return;
        }

        $this->extra = $extra;

        // Dispatch the selected subaction
        $subactions = $this->getSubactions($action);
        $processed = 0;
        $deferred = null;
        foreach($subactions as $item) {
            $entry = new MenuEntry($item);
            $subactionLen = strlen($entry->action);
            $selected = $subactionLen ?
                substr($subaction, 0, $subactionLen) == $entry->action :
                $subaction == $entry->action;
            if($selected && $this->session->isAuth($entry->access)) {
                if($subaction == $entry->action) {
                    $this->{$entry->implementation}();
                    $processed = 1;
                    break;
                }

                // stem matched; this will become the default, provided
                // no other entry matches exactly
                $deferred = $entry;
            }
        }
    
        // If no subaction was dispatched, default to the first one
        if(!$processed) {
            $entry = $deferred ?? new MenuEntry($subactions[0]);
            $this->{$entry->implementation}();
        }
    }
}
