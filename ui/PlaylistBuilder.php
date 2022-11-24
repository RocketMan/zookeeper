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

namespace ZK\UI;

use ZK\Engine\PlaylistEntry;
use ZK\Engine\PlaylistObserver;
use ZK\UI\UICommon as UI;

use VStelmakh\UrlHighlight\UrlHighlight;

class PlaylistBuilder extends PlaylistObserver {
    private const PARAMS = [ "action", "editMode", "authUser" ];

    protected $params;
    protected $break;

    private static bool $usLocale;
    private static UrlHighlight $urlHighlighter;

    protected static function isUsLocale() : bool {
        if(!isset(self::$usLocale))
            self::$usLocale = UI::getClientLocale() == 'en_US';
        return self::$usLocale;
    }

    protected static function timestampToLocale($timestamp) {
        // colon is included in 24hr format for symmetry with fxtime
        $timeSpec = self::isUsLocale() ? 'h:i a' : 'H:i';
        return $timestamp ? date($timeSpec, $timestamp) : '';
    }

    protected static function smartURL($name) {
        if(!isset(self::$urlHighlighter))
            self::$urlHighlighter = new UrlHighlight();

        return self::$urlHighlighter->highlightUrls(htmlentities($name));
    }

    public static function newInstance(array $params) {
        // validate all parameters are present
        // TBD: when we require PHP 8, replace with named params
        $missing = [];
        foreach(self::PARAMS as $param)
            if(!key_exists($param, $params))
                $missing[] = $param;

        if(sizeof($missing))
            throw new \InvalidArgumentException("missing required parameter(s): " . implode(", ", $missing));

        return new PlaylistBuilder($params);
    }

    protected function makeAlbumLink($entry, $includeLabel) {
        $albumName = $entry->getAlbum();
        $labelName = $entry->getLabel();
        if (empty($albumName) && empty($labelName))
            return "";

        $labelSpan = "<span class='songLabel'> / " . self::smartURL($labelName) . "</span>";
        if($entry->getTag()) {
            $albumTitle = "<A HREF='?s=byAlbumKey&amp;n=" . UI::URLify($entry->getTag()) .
                          "&amp;q=&amp;action=search' CLASS='nav'>".$albumName ."</A>";

            if ($includeLabel) {
                $albumTitle = $albumTitle . $labelSpan;
            }
        } else {
            $albumTitle = self::smartURL($albumName);
            if ($includeLabel) 
                $albumTitle = $albumTitle . $labelSpan;
        }
        return $albumTitle;
    }

    protected function makeEditDiv($entry) {
        $href = "?id=" . $entry->getId() . "&amp;action=" . $this->params["action"] . "&amp;seq=editTrack";
        $editLink = "<a class='songEdit nav' href='$href'>&#x270f;</a>";
        $dnd = "<div class='grab' data-id='" . $entry->getId() . "'>&#x2630;</div>";
        return "<div class='songManager'>" . $dnd . $editLink . "</div>";
    }

    protected function __construct(array $params) {
        $this->params = $params;
        $this->on('comment', function($entry) {
            $editCell = $this->params["editMode"] ? "<TD>" .
                $this->makeEditDiv($entry) . "</TD>" : "";
            $created = $entry->getCreatedTimestamp();
            $timeplayed = self::timestampToLocale($created);
            echo "<TR class='commentRow".($this->params["editMode"]?"Edit":"")."'>" . $editCell .
                 "<TD class='time' data-utc='$created'>$timeplayed</TD>" .
                 "<TD COLSPAN=4>".UI::markdown($entry->getComment()).
                 "</TD></TR>\n";
            $this->break = false;
        })->on('logEvent', function($entry) {
            $created = $entry->getCreatedTimestamp();
            $timeplayed = self::timestampToLocale($created);
            if($this->params["authUser"]) {
                // display log entries only for authenticated users
                $editCell = $this->params["editMode"] ? "<TD>" .
                    $this->makeEditDiv($entry) . "</TD>" : "";
                echo "<TR class='logEntry".($this->params["editMode"]?"Edit":"")."'>" . $editCell .
                     "<TD class='time' data-utc='$created'>$timeplayed</TD>" .
                     "<TD>".$entry->getLogEventType()."</TD>" .
                     "<TD COLSPAN=3>".$entry->getLogEventCode()."</TD>" .
                     "</TR>\n";
                $this->break = false;
            } else if(!$this->break) {
                echo "<TR class='songDivider'>" . $editCell .
                     "<TD class='time' data-utc='$created'>$timeplayed</TD><TD COLSPAN=4><HR></TD></TR>\n";
                $this->break = true;
            }
        })->on('setSeparator', function($entry) {
            if($this->params["editMode"] || !$this->break) {
                $editCell = $this->params["editMode"] ? "<TD>" .
                    $this->makeEditDiv($entry) . "</TD>" : "";
                $created = $entry->getCreatedTimestamp();
                $timeplayed = self::timestampToLocale($created);
                echo "<TR class='songDivider'>" . $editCell .
                     "<TD class='time' data-utc='$created'>$timeplayed</TD><TD COLSPAN=4><HR></TD></TR>\n";
                $this->break = true;
            }
        })->on('spin', function($entry) {
            $editCell = $this->params["editMode"] ? "<TD>" .
                $this->makeEditDiv($entry) . "</TD>" : "";
            $created = $entry->getCreatedTimestamp();
            $timeplayed = self::timestampToLocale($created);
            $reviewCell = $entry->getReviewed() ? "<div class='albumReview'></div>" : "";
            $artistName = PlaylistEntry::swapNames($entry->getArtist());

            $albumLink = $this->makeAlbumLink($entry, true);
            echo "<TR class='songRow'>" . $editCell .
                 "<TD class='time' data-utc='$created'>$timeplayed</TD>" .
                 "<TD>" . self::smartURL($artistName) . "</TD>" .
                 "<TD>" . self::smartURL($entry->getTrack()) . "</TD>" .
                 "<TD>$reviewCell</TD>" .
                 "<TD>$albumLink</TD>" .
                 "</TR>\n";
            $this->break = false;
        });
    }
}
