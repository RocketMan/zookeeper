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

namespace ZK\Engine;

/**
 * PlaylistObserver
 *
 * This class holds a set of lambda functions which are invoked for
 * specific types of PlaylistEntries.
 *
 * General usage pattern is:
 *
 *    someFunctionThatWantsAnObserver(...
 *        (new PlaylistObserver())->
 *            onXXX(function(PlaylistEntry $entry) {...})->
 *            onYYY(function(PlaylistEntry $entry) {...})...
 *        ...)
 *
 *    where onXXX etc. are one of:
 *       onComment
 *       onLogEvent
 *       onSetSeparator
 *       onSpin
 *
 * One or more onXXX functions may be chained to the PlaylistObserver instance.
 *
 * Alternatively, you may use the form:
 *
 *    on(types, function)
 *
 * where 'types' is a space-separated string of entry types; e.g.,
 *
 *    on('comment logEvent', function($entry) {...})...
 *
 * In this case, the function will be installed for all the specified types.
 *
 * If the lambda function returns a trueish value, the observer will
 * stop iteration.
 * 
 */
class PlaylistObserver {
    private $comment;
    private $logEvent;
    private $setSeparator;
    private $spin;


    public function __call($method, $args) {
        if(isset($this->$method) && $this->$method instanceof \Closure)
            return call_user_func_array($this->$method, $args);
    }

    /**
     * install lambda function to handle comment entries
     */
    public function onComment(\Closure $comment) {
        $this->comment = $comment;
        return $this;
    }

    /**
     * install lambda function to handle log event entries
     */
    public function onLogEvent(\Closure $logEvent) {
        $this->logEvent = $logEvent;
        return $this;
    }

    /**
     * install lambda function to handle set separator entries
     */
    public function onSetSeparator(\Closure $setSeparator) {
        $this->setSeparator = $setSeparator;
        return $this;
    }

    /**
     * install lambda function to handle spin entries
     */
    public function onSpin(\Closure $spin) {
        $this->spin = $spin;
        return $this;
    }

    /**
     * install lambda function to handle one or more entry types
     */
    public function on(string $types, \Closure $fn) {
        foreach(explode(' ', $types) as $type)
            $this->$type = $fn;

        return $this;
    }
    
    private function observeComment($entry) {
        return $this->comment ? $this->comment($entry) : null;
    }

    private function observeLogEvent($entry) {
        return $this->logEvent ? $this->logEvent($entry) : null;
    }

    private function observeSetSeparator($entry) {
        return $this->setSeparator ? $this->setSeparator($entry) : null;
    }

    private function observeSpin($entry) {
        return $this->spin ? $this->spin($entry) : null;
    }

    /**
     * observe the specified PlaylistEntry
     */
    public function observe(PlaylistEntry $entry) {
        $retVal = null;
        switch($entry->getType()) {
        case PlaylistEntry::TYPE_SPIN:
            $retVal = $this->observeSpin($entry);
            break;
        case PlaylistEntry::TYPE_LOG_EVENT:
            $retVal = $this->observeLogEvent($entry);
            break;
        case PlaylistEntry::TYPE_COMMENT:
            $retVal = $this->observeComment($entry);
            break;
        case PlaylistEntry::TYPE_SET_SEPARATOR:
            $retVal = $this->observeSetSeparator($entry);
            break;
        }
        return $retVal;
    }
}
