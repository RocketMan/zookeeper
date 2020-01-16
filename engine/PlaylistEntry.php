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

namespace ZK\Engine;

/**
 * PlaylistEntry
 *
 * This class represents a single entry in a playlist.  It can be a
 * track from an album, a log event (LID, PSA, etc.), set separator,
 * or comment.
 *
 * ctors:
 *     `new PlaylistEntry()` instantiates an empty instance
 *     `new PlaylistEntry($row)` instantiate from a database row,
 *            where `$row` is an associative array of the database columns
 *
 * The methods `getType` and `isType` permit inspection of the entry type.
 * 
 * Setters and getters are supplied to access the properties of the entry.
 * (Note that spin properties (e.g., getArtist, getTrack()) are implemented
 * via introspection, so you will not see them declared here, though they are
 * available for use.)
 */
class PlaylistEntry {
    const TYPE_SPIN = 0;
    const TYPE_COMMENT = 1;
    const TYPE_LOG_EVENT = 2;
    const TYPE_SET_SEPARATOR = 3;

    const MAX_COMMENT_LENGTH = 240;


    protected $entry = [];


    public function __construct() {
        $ctor = "__construct" . func_num_args();
        if(method_exists($this, $ctor))
            call_user_func_array([$this, $ctor], func_get_args());
    }

    public function __construct1($entry) {
        $this->entry = $entry;
    }

    public function __call($name, $args) {
        if(substr($name, 0, 3) === "get") {
            $property = strtolower(substr($name, 3));
            if(array_key_exists($property, $this->entry))
                return $this->entry[$property];
            else
                return "";
        }
        
        if(substr($name, 0, 3) === "set") {
            $property = strtolower(substr($name, 3));
            $this->entry[$property] = $args[0];
            return $this;
        }
    }

    public static function fromJSON($json) {
        $entry = new PlaylistEntry();
        switch($json->type) {
        case "break":
            $entry->setSetSeparator();
            break;
        case "comment":
            $entry->setComment($json->comment);
            break;
        case "logEvent":
            $entry->setLogEvent($json->event, $json->code);
            break;
        case "track":
            $entry->setArtist($json->artist);
            $entry->setTrack($json->track);
            $entry->setAlbum($json->album);
            $entry->setLabel($json->label);
            $entry->setTag($json->tag);
            break;
        }
        $entry->setCreated($json->created);
        return $entry;
    }

    /**
     * return the type of this playlist entry
     *
     * returns:
     *    one of the PlaylistEntry::TYPE_* constants
     */
    public function getType() {
        if(substr($this->entry['artist'], 0, strlen(IPlaylist::SPECIAL_TRACK)) === IPlaylist::SPECIAL_TRACK) {
            if(strpos($this->entry['artist'], IPlaylist::COMMENT_FLAG) > 0)
                $type = self::TYPE_COMMENT;
            else if(strpos($this->entry['artist'], IPlaylist::LOG_FLAG) > 0)
                $type = self::TYPE_LOG_EVENT;
            else
                $type = self::TYPE_SET_SEPARATOR;
        } else
            $type = self::TYPE_SPIN;

        return $type;
    }

    /**
     * test if this playlist entry is of the specified type
     *
     * param:
     *    $type - one of PlaylistEntry::TYPE_* constants
     *
     * returns:
     *    true if type matches, false otherwise
     */
    public function isType($type) {
        return $this->getType() === $type;
    }

    /**
     * return comment text
     *
     * returns:
     *    comment text, or empty string if this is not a comment entry
     */
    public function getComment() {
        return $this->isType(self::TYPE_COMMENT)?
                $this->entry['track'].$this->entry['album'].$this->entry['label']:'';
    }

    /**
     * set the specified comment text into this entry
     *
     * param:
     *   $comment - comment text
     */
    public function setComment($comment) {
        $this->entry['artist'] = IPlaylist::SPECIAL_TRACK . IPlaylist::COMMENT_FLAG;
        if(mb_strlen($comment) < 80) {
            $this->entry['track'] = $comment;
        } else {
            $this->entry['track'] = mb_substr($comment, 0, 80);
            $rest = mb_substr($comment, 80);
            if(mb_strlen($rest) < 80) {
                $this->entry['album'] = $rest;
            } else {
                $this->entry['album'] = mb_substr($rest, 0, 80);
                $this->entry['label'] = mb_substr($rest, 80);
            }
        }
        return $this;
    }

    /**
     * get the event log type for event entries
     *
     * returns:
     *    log type, or empty if this is not an event entry
     */
    public function getLogEventType() {
        return $this->isType(self::TYPE_LOG_EVENT)?$this->entry['album']:'';
    }

    /**
     * get the event log code for event entries
     *
     * returns:
     *    log code, or empty if this is not an event entry
     */
    public function getLogEventCode() {
        return $this->isType(self::TYPE_LOG_EVENT)?$this->entry['track']:'';
    }

    /**
     * set the specified log event into this entry
     *
     * params:
     *    $type - event type
     *    $code - event code
     */
    public function setLogEvent($type, $code) {
        $this->entry['artist'] = IPlaylist::SPECIAL_TRACK . IPlaylist::LOG_FLAG;
        $this->entry['album'] = $type;
        $this->entry['track'] = $code;
        return $this;
    }

    /**
     * set an set separator into this entry
     */
    public function setSetSeparator() {
        $this->entry['artist'] = IPlaylist::SPECIAL_TRACK;
        return $this;
    }

    /**
     * get the value of the PlaylistEntry as an associative array
     *
     * CAUTION: the values returned by this method are undefined
     * for types other than TYPE_SPIN.
     */
    public function asArray() {
        return $this->entry;
    }
}
