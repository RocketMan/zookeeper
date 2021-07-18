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

namespace ZK\Engine;

/**
 * OnNowFilter
 *
 * This class is a RowIterator decorator for playlist tracks.  Its `fetch`
 * method returns tracks up to but not beyond the currently on-air track
 * in the playlist.
 *
 * As well, the class provides an additional method `peek` that returns
 * the next track to air.
 *
 */
class OnNowFilter {
    private $queue = [];
    private $delegate;
    private $now;

    public function __construct($delegate) {
        $this->delegate = $delegate;
        $this->now = new \DateTime("now");
    }

    private static function getCreated($row) {
        return $row['created']?
            \DateTime::createFromFormat("Y-m-d H:i:s", $row['created']):null;
    }

    public function fetch() {
        // return look ahead track, if any
        if(sizeof($this->queue))
            return array_shift($this->queue);

        $row = $this->delegate->fetch();
        if($row) {
            $created = self::getCreated($row);
            if($created && $created < $this->now) {
                // timestamp before 'now'
                return $row;
            } else if(!$created) {
                // look ahead to see if untimestamped track is
                // followed by a timestamped track before 'now'
                while($row2 = $this->delegate->fetch()) {
                    array_push($this->queue, $row2);
                    $created = self::getCreated($row2);
                    if($created && $created < $this->now)
                        return $row;
                }
            } else
                array_push($this->queue, $row);
        }
        return false;
    }

    /**
     * look ahead to the next timestamped entry of the specified type
     *
     * the general use case is to call this method after fetch() returns
     * null, to get the next future entry following now ('next on').
     *
     * @param type one of PlaylistEntry::TYPE_* or null for any type
     * @return PlaylistEntry for next entry or null if none
     */
    public function peek($type = null) {
        $next = null;

        foreach($this->queue as $row) {
            $entry = new PlaylistEntry($row);
            if($entry->getCreated() &&
                    ($type === null || $entry->isType($type))) {
                $next = $entry;
                break;
            }
        }

        while(!$next && ($row = $this->delegate->fetch())) {
            array_push($this->queue, $row);
            $entry = new PlaylistEntry($row);
            if($entry->getCreated() &&
                    ($type === null || $entry->isType($type)))
                $next = $entry;
        }

        return $next;
    }
}
