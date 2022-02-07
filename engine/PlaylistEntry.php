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
    const MAX_FIELD_LENGTH = 80;


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

    public static function scrubField($field, $length = PlaylistEntry::MAX_FIELD_LENGTH) {
        $field = trim($field);
        return mb_strlen($field) <= $length?$field:mb_substr($field, 0, $length);
    }

    /**
     * scrub user-supplied timestamp
     *
     * @param timestamp target
     * @param window DateTime array from IPlaylist::getTimestampWindow
     * @return scrubbed timestamp or null if not in show window
     */
    public static function scrubTimestamp(\DateTime $timestamp, array $window) {
        // normalize for non-local timezone
        $timestamp->setTimezone($window['start']->getTimezone());

        // transpose timestamp to show date
        $showDate = $window['start']->format("Y-m-d");
        if($timestamp->format("Y-m-d") != $showDate) {
            list($h, $m, $s) = explode(":", $timestamp->format("H:i:s"));
            $timestamp = new \DateTime($showDate);
            $timestamp->setTime($h, $m, $s);
        }

        // if playlist spans midnight, adjust post-midnight timestamp date
        if($window['end']->format("G") < $window['start']->format("G") &&
                $timestamp < $window['start'])
            $timestamp->modify("+1 day");

        // validate timestamp is within the show time range
        $valid = false;
        for($i=2; $i>0; $i--) {
            if($timestamp >= $window['start'] &&
                    $timestamp <= $window['end']) {
                $valid = true;
                break;
            }

            // try again on 12-hour clock (e.g., treat 03:30 as 15:30)
            $timestamp->modify("+12 hour");
        }

        if(!$valid)
            error_log("Spin time is outside of show start/end times.");

        return $valid?$timestamp:null;
    }

   /**
    *  converts "last, first" to "first last" being careful to not swap
    *  other formats that have commas. call only for ZK library entries
    *  since manual entries don't need this. Test cases: The Band, CSN&Y,
    *  Bing Crosby & Fred Astaire, Bunett, June and Maqueque, Electro, Brad 
    *  Feat. Marwan Kanafaneg, Kallick, Kathy Band: 694717, 418485, 911685, 
    *  914824, 880994, 1134313.
    */
    public static function swapNames($fullName) {
        $suffixMap = [ "band" => "", "with" => "", "and" => "", "feat." => "" ];

        $namesAr = explode(", ", $fullName);
        if (count($namesAr) == 2) {
            $spacesAr = explode(" ", $namesAr[1]);
            $spacesCnt = count($spacesAr);
            if ($spacesCnt == 1) {
                $fullName = $namesAr[1] . " " . $namesAr[0];
            } else if ($spacesCnt > 1) {
                $key = strtolower($spacesAr[1]);
                if (array_key_exists($key, $suffixMap)) {
                    $fullName = $spacesAr[0] . ' ' . $namesAr[0] . ' ' . substr($namesAr[1], strlen($spacesAr[0]));
                }
            }
        }
        return $fullName;
    }

    public static function fromJSON($json) {
        $entry = new PlaylistEntry();
        switch($json->type) {
        case "break":
            $entry->setSetSeparator();
            break;
        case "comment":
            $entry->setComment(self::scrubField($json->comment, PlaylistEntry::MAX_COMMENT_LENGTH));
            break;
        case "logEvent":
            $entry->setLogEvent(self::scrubField($json->event), self::scrubField($json->code));
            break;
        case "spin":
        case "track":
            $entry->setArtist(self::scrubField($json->artist));
            $entry->setTrack(self::scrubField($json->track));
            $entry->setAlbum(self::scrubField($json->album));
            $entry->setLabel(self::scrubField($json->label));
            if(($a = $json->{"xa:relationships"} ?? null) &&
                    ($a = $a->album ?? null) &&
                    ($a = $a->data ?? null) &&
                    ($a->type ?? null == "album") &&
                    ($a = $a->id ?? null) ||
                    ($a = $json->tag ?? null)) {
                $albumrec = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $a);
                if(sizeof($albumrec)) {
                    // don't allow modification of album info if tag is set
                    $entry->setTag($a);
                    $entry->setArtist(self::swapNames($albumrec[0]["artist"]));
                    $entry->setAlbum($albumrec[0]["album"]);
                    $entry->setLabel($albumrec[0]["name"]);
                }
            }
            break;
        }
        $entry->setCreated($json->created);
        return $entry;
    }

    public static function fromArray($array) {
        $entry = new PlaylistEntry();
        switch($array["type"]) {
        case "break":
            $entry->setSetSeparator();
            break;
        case "comment":
            $entry->setComment(self::scrubField($array["comment"], PlaylistEntry::MAX_COMMENT_LENGTH));
            break;
        case "logEvent":
            $entry->setLogEvent(self::scrubField($array["event"]), self::scrubField($array["code"]));
            break;
        case "spin":
        case "track":
            $entry->setTrack(self::scrubField($array["track"]));
            if(isset($array["xa:relationships"])) {
                // using the 'xa' extension
                // see https://github.com/RocketMan/zookeeper/pull/263
                try {
                    $album = $array["xa:relationships"]->get("album")->related()->first("album");
                    $albumrec = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $album->id());
                    if(sizeof($albumrec)) {
                        // don't allow modification of album info if tag is set
                        $entry->setTag($album->id());
                        $entry->setArtist(self::swapNames($albumrec[0]["artist"]));
                        $entry->setAlbum($albumrec[0]["album"]);
                        $entry->setLabel($albumrec[0]["name"]);
                    }
                } catch(\Exception $e) {}
            }

            if(!$entry->getTag()) {
                $entry->setArtist(self::scrubField($array["artist"]));
                $entry->setAlbum(self::scrubField($array["album"]));
                $entry->setLabel(self::scrubField($array["label"]));
            }
            break;
        }

//        if(isset($array["created"]))
            $entry->setCreated($array["created"]);

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
     * return the time component of the 'created' datetime string
     *
     * @return time component on success; original datetime value otherwise
     */
    public function getCreatedTime() {
        $datetime = $this->getCreated();

        // yyyy-mm-dd hh:mm:ss
        if($datetime && strlen($datetime) == 19)
            $datetime = substr($datetime, 11, 8);
        return $datetime;
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
