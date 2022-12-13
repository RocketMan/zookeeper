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

class PlaylistBuilder extends PlaylistObserver {
    private const PARAMS = [ "action", "editMode", "authUser" ];

    protected $params;
    protected $break;

    protected static function timestampToLocale($timestamp) {
        // colon is included in 24hr format for symmetry with fxtime
        $timeSpec = UI::isUsLocale() ? 'h:i a' : 'H:i';
        return $timestamp ? date($timeSpec, $timestamp) : '';
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

        if(empty($albumName) && empty($labelName))
            return "";

        $albumTitle = $entry->getTag() ?
            "<a href='?s=byAlbumKey&amp;n=" . htmlentities($entry->getTag()) .
            "&amp;action=search' class='nav'>$albumName</a>" :
            UI::smartURL($albumName);

        if($includeLabel)
            $albumTitle .= "<span class='songLabel'> / " . UI::smartURL($labelName) . "</span>";

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
            $editCell = $this->params["editMode"] ? "<td>" .
                $this->makeEditDiv($entry) . "</td>" : "";
            $created = $entry->getCreatedTimestamp();
            $timeplayed = self::timestampToLocale($created);
            echo "<tr class='commentRow".($this->params["editMode"]?"Edit":"")."'>" . $editCell .
                 "<td class='time' data-utc='$created'>$timeplayed</td>" .
                 "<td colspan=4>".UI::markdown($entry->getComment()).
                 "</td></tr>\n";
            $this->break = false;
        })->on('logEvent', function($entry) {
            $created = $entry->getCreatedTimestamp();
            $timeplayed = self::timestampToLocale($created);
            if($this->params["authUser"]) {
                // display log entries only for authenticated users
                $editCell = $this->params["editMode"] ? "<td>" .
                    $this->makeEditDiv($entry) . "</td>" : "";
                echo "<tr class='logEntry".($this->params["editMode"]?"Edit":"")."'>" . $editCell .
                     "<td class='time' data-utc='$created'>$timeplayed</td>" .
                     "<td>".$entry->getLogEventType()."</td>" .
                     "<td colspan=3>".$entry->getLogEventCode()."</td>" .
                     "</tr>\n";
                $this->break = false;
            } else if(!$this->break) {
                echo "<tr class='songDivider'>" .
                     "<td class='time' data-utc='$created'>$timeplayed</td><td colspan=4><hr></td></tr>\n";
                $this->break = true;
            }
        })->on('setSeparator', function($entry) {
            if($this->params["editMode"] || !$this->break) {
                $editCell = $this->params["editMode"] ? "<td>" .
                    $this->makeEditDiv($entry) . "</td>" : "";
                $created = $entry->getCreatedTimestamp();
                $timeplayed = self::timestampToLocale($created);
                echo "<tr class='songDivider'>" . $editCell .
                     "<td class='time' data-utc='$created'>$timeplayed</td><td colspan=4><hr></td></tr>\n";
                $this->break = true;
            }
        })->on('spin', function($entry) {
            $editCell = $this->params["editMode"] ? "<td>" .
                $this->makeEditDiv($entry) . "</td>" : "";
            $created = $entry->getCreatedTimestamp();
            $timeplayed = self::timestampToLocale($created);
            $reviewCell = $entry->getReviewed() ? "<div class='albumReview'></div>" : "";
            $artistName = PlaylistEntry::swapNames($entry->getArtist());

            $albumLink = $this->makeAlbumLink($entry, true);
            echo "<tr class='songRow'>" . $editCell .
                 "<td class='time' data-utc='$created'>$timeplayed</td>" .
                 "<td>" . UI::smartURL($artistName) . "</td>" .
                 "<td>" . UI::smartURL($entry->getTrack()) . "</td>" .
                 "<td>$reviewCell</td>" .
                 "<td>$albumLink</td>" .
                 "</tr>\n";
            $this->break = false;
        });
    }
}
