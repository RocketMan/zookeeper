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
    protected $action;
    protected $authUser;
    protected $break;
    protected $editMode;
    protected $playlistId;

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

    public static function newInstance(int $playlistId, string $action, bool $editMode, bool $authUser) : PlaylistBuilder {
        $inst = new PlaylistBuilder();

        $inst->playlistId = $playlistId;
        $inst->action = $action;
        $inst->editMode = $editMode;
        $inst->authUser = $authUser;

        return $inst;
    }

    protected function makeAlbumLink($entry, $includeLabel) {
        $albumName = $entry->getAlbum();
        $labelName = $entry->getLabel();
        if (empty($albumName) && empty($labelName))
            return "";

        $labelSpan = "<span class='songLabel'> / " . $this->smartURL($labelName) . "</span>";
        if($entry->getTag()) {
            $albumTitle = "<A HREF='?s=byAlbumKey&amp;n=" . UI::URLify($entry->getTag()) .
                          "&amp;q=&amp;action=search' CLASS='nav'>".$albumName ."</A>";

            if ($includeLabel) {
                $albumTitle = $albumTitle . $labelSpan;
            }
        } else {
            $albumTitle = $this->smartURL($albumName);
            if ($includeLabel) 
                $albumTitle = $albumTitle . $labelSpan;
        }
        return $albumTitle;
    }

    protected function makeEditDiv($entry) {
        $href = "?playlist=" . $this->playlistId . "&amp;id=" .
                $entry->getId() . "&amp;action=" . $this->action . "&amp;";
        $editLink = "<A CLASS='songEdit nav' HREF='" . $href ."seq=editTrack'>&#x270f;</a>";
        //NOTE: in edit mode the list is ordered new to old, so up makes it 
        //newer in time order & vice-versa.
        $dnd = "<DIV class='grab' data-id='".$entry->getId()."'>&#x2630;</DIV>";
        $retVal = "<div class='songManager'>" . $dnd . $editLink . "</div>";
        return $retVal;
    }

    protected function __construct() {
        $this->on('comment', function($entry) {
            $editCell = $this->editMode ? "<TD>" .
                $this->makeEditDiv($entry) . "</TD>" : "";
            $created = $entry->getCreatedTimestamp();
            $timeplayed = self::timestampToLocale($created);
            echo "<TR class='commentRow".($this->editMode?"Edit":"")."'>" . $editCell .
                 "<TD class='time' data-utc='$created'>$timeplayed</TD>" .
                 "<TD COLSPAN=4>".UI::markdown($entry->getComment()).
                 "</TD></TR>\n";
            $this->break = false;
        })->on('logEvent', function($entry) {
            $created = $entry->getCreatedTimestamp();
            $timeplayed = self::timestampToLocale($created);
            if($this->authUser) {
                // display log entries only for authenticated users
                $editCell = $this->editMode ? "<TD>" .
                    $this->makeEditDiv($entry) . "</TD>" : "";
                echo "<TR class='logEntry".($this->editMode?"Edit":"")."'>" . $editCell .
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
            if($this->editMode || !$this->break) {
                $editCell = $this->editMode ? "<TD>" .
                    $this->makeEditDiv($entry) . "</TD>" : "";
                $created = $entry->getCreatedTimestamp();
                $timeplayed = self::timestampToLocale($created);
                echo "<TR class='songDivider'>" . $editCell .
                     "<TD class='time' data-utc='$created'>$timeplayed</TD><TD COLSPAN=4><HR></TD></TR>\n";
                $this->break = true;
            }
        })->on('spin', function($entry) {
            $editCell = $this->editMode ? "<TD>" .
                $this->makeEditDiv($entry) . "</TD>" : "";
            $created = $entry->getCreatedTimestamp();
            $timeplayed = self::timestampToLocale($created);
            $reviewCell = $entry->getReviewed() ? "<div class='albumReview'></div>" : "";
            $artistName = PlaylistEntry::swapNames($entry->getArtist());

            $albumLink = $this->makeAlbumLink($entry, true);
            echo "<TR class='songRow'>" . $editCell .
                 "<TD class='time' data-utc='$created'>$timeplayed</TD>" .
                 "<TD>" . $this->smartURL($artistName) . "</TD>" .
                 "<TD>" . $this->smartURL($entry->getTrack()) . "</TD>" .
                 "<TD>$reviewCell</TD>" .
                 "<TD>$albumLink</TD>" .
                 "</TR>\n";
            $this->break = false;
        });
    }
}
