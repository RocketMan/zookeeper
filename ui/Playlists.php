<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2024 Jim Mason <jmason@ibinx.com>
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

use ZK\Controllers\PushServer;
use ZK\Engine\Engine;
use ZK\Engine\IDJ;
use ZK\Engine\ILibrary;
use ZK\Engine\IPlaylist;
use ZK\Engine\IReview;
use ZK\Engine\IUser;
use ZK\Engine\PlaylistEntry;
use ZK\Engine\PlaylistObserver;

use ZK\UI\UICommon as UI;

use JSMin\JSMin;


class Playlists extends MenuItem {
    private const NME_PREFIX = "nme-";

    //NOTE: update ui_config.php when changing the actions.
    private static $subactions = [
        [ "a", "", "On Now", "emitHome" ],
        [ "a", "recent", 0, "recentSpins" ],
        [ "a", "times", 0, "getTimes" ],
        [ "u", "editList", "My Playlists", "emitListManager" ],
        [ "a", "viewList", "By Date", "emitPlaylistPicker" ],
        [ "a", "viewListById", 0, "emitViewPlaylist" ],
        [ "a", "viewListDaysByDate", 0, "handlePlaylistDaysByDate" ],
        [ "a", "viewListsByDate", 0, "handlePlaylistsByDate" ],
        [ "u", "editListGetHint", 0, "listManagerGetHint" ],
        [ "u", "editListEditor", 0, "emitEditor" ],
        [ "a", "viewDJ", "By DJ", "emitViewDJ" ],
        [ "a", "viewTop", "Top Plays", "emitTopPlays" ],
        [ "u", "import", "Import", "emitImportList" ],
    ];

    private $action;
    private $subaction;

    public function getSubactions($action) {
        return self::$subactions;
    }

    public function processLocal($action, $subaction) {
        $this->action = $action;
        $this->subaction = $subaction;
        return $this->dispatchSubaction($action, $subaction);
    }

    // given a time string H:MM, HH:MM, or HHMM, return normalized to HHMM
    // returns empty string if invalid
    private function normalizeTime($t) {
        // is time already in HHMM format?
        if(preg_match('/^\d{4}$/', $t)) {
            $x = $t;
        } else {
            // is time in H:MM or HH:MM format?
            // ...otherwise, it is invalid
            $d = \DateTime::createFromFormat("H:i", $t);
            $x = $d ? $d->format("Hi") : '';
        }

        return $x;
    }
 
    // convert HHMM or (H)H:MM pairs into ZK time range, eg HHMM-HHMM
    // return range string if valid else empty string.
    private function composeTime($fromTime, $toTime) {
        $retVal = '';

        $fromTimeN = $this->normalizeTime($fromTime);
        $toTimeN = $this->normalizeTime($toTime);

        $start = \DateTime::createFromFormat(IPlaylist::TIME_FORMAT, "2019-01-01 " . $fromTimeN);
        $end =  \DateTime::createFromFormat(IPlaylist::TIME_FORMAT, "2019-01-01 " . $toTimeN);

        $validRange = false;
        if ($start && $end) {
            // if end is less than start, assume next day
            if($end < $start)
                $end->modify('+1 day');
            $minutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;
            $validRange = ($minutes > IPlaylist::MIN_SHOW_LEN) && ($minutes < IPlaylist::MAX_SHOW_LEN);
        }

        if ($validRange)
            $retVal = $fromTimeN . "-" . $toTimeN;
        else if ($fromTime || $toTime)
            error_log("Error: invalid playlist time -" . $fromTime . "-, -" . $toTime ."-");

        return $retVal;
    }

    private function isOwner($playlistId) {
        $p = Engine::api(IPlaylist::class)->getPlaylist($playlistId, 0);
        return $p && $p['dj'] == $this->session->getUser();
    }

    /**
     * If the show is within the lookback period, asynchronously
     * load any unknown artist and album art in the playlist.
     *
     * Note that only timestamped entries are loaded.
     */
    protected function lazyLoadImages($playlistId, $trackId = 0) {
        $playlist = Engine::api(IPlaylist::class)->getPlaylist($playlistId);

        // unpublished playlist
        if(!$playlist['airname'])
            return;

        $timeAr = explode("-", $playlist['showtime']);
        if(count($timeAr) != 2)
            return;

        $showStart = \DateTime::createFromFormat(IPlaylist::TIME_FORMAT,
            $playlist['showdate'] . " " . $timeAr[0]);
        $now = new \DateTime();

        // future show
        if($showStart > $now)
            return;

        $now->modify("-7 day");
        if($showStart > $now) {
            // show is within the lookback period
            PushServer::lazyLoadImages($playlistId, $trackId);
        }
    }
    
    private static function hourToLocale($hour, $full=0) {
        // account for legacy, free-format time encoding
        if(!is_numeric($hour) || !UI::isUsLocale())
            return $hour;

        $h = (int)floor($hour/100);
        $m = (int)$hour % 100;
        $min = $m || $full?(":" . sprintf("%02d", $m)):"";
    
        switch($h) {
        case 0:
            return $m?("12" . $min . "am"):"midnight";
        case 12:
            return $m?($h . $min . "pm"):"noon";
        default:
            if($h < 12)
                return $h . $min . "am";
            else
                return ($h - 12) . $min . "pm";
        }
    }
    
    public static function timeToLocale($time) {
        if(strlen($time) == 9 && $time[4] == '-') {
            list($fromtime, $totime) = explode("-", $time);
            return self::hourToLocale($fromtime) . " - " . self::hourToLocale($totime);
        } else
            return strtolower(htmlentities($time));
    }

    public static function timestampToDate($time) {
        if ($time == null || $time == '') {
            return "";
        } else {
            $dateSpec = UI::isUsLocale() ? 'D M d, Y ' : 'D d M Y ';
            return date($dateSpec, strtotime($time));
        }
    }

    public static function makeShowDateAndTime($row) {
        return self::timestampToDate($row['showdate']) . " " .
               self::timeToLocale($row['showtime']);
    }

    public static function makeShowTime($row) {
        return self::timeToLocale($row['showtime']);
    }

    public function listManagerGetHint() {
        $hint = null;
        $now = new \DateTime("now");
        $today = $now->format("Y-m-d");
        $lastWeek = $now->modify("-7 day")->format("Y-m-d");

        // see if there is a PL on this day last week. if so use it.
        $playlists = Engine::api(IPlaylist::class)->getPlaylists(1, 1, "", 1, $this->session->getUser(), 1, 10);
        $djapi = Engine::api(IDJ::class);
        while ($playlists && ($playlist = $playlists->fetch())) {
            // skip duplicated lists with foreign airnames
            $aid = $djapi->getAirname($playlist['airname'], $this->session->getUser());
            if(!$aid)
                continue;

            if ($playlist['showdate'] == $lastWeek) {
                $sourcePlaylist = $playlist;
                $sourcePlaylist['showdate'] = $today;
                $hint = [
                    "attributes" => [
                        "name" => $sourcePlaylist["description"],
                        "airname" => $sourcePlaylist["airname"],
                        "date" => $sourcePlaylist["showdate"],
                        "time" => $sourcePlaylist["showtime"]
                    ]
                ];
                break;
            }
        }
        echo $hint ? json_encode($hint) : "{}";
    }

    public function emitHome() {
        $this->newEntity(Home::class)->emitHome();
    }

    public function recentSpins() {
        $this->newEntity(Home::class)->recentSpins();
    }

    public function getTimes() {
        $this->newEntity(Home::class)->getTimes();
    }

    public function emitListManager() {
        $this->setTemplate("list/mylists.html");
        $this->addVar('airnames', $this->getDJAirNames());
        $this->addVar('duplicate', isset($_POST["duplicate"]) && $_POST["duplicate"] ? $_POST["playlist"] : false);
        $this->addVar('DUPLICATE_SUFFIX', IPlaylist::DUPLICATE_SUFFIX);
        $this->addVar('MAX_DESCRIPTION_LENGTH', IPlaylist::MAX_DESCRIPTION_LENGTH);
        $this->addVar('MAX_AIRNAME_LENGTH', IDJ::MAX_AIRNAME_LENGTH);
    }

    private function getDJAirNames() {
        $airNames = [];
        $records = Engine::api(IDJ::class)->getAirnames($this->session->getUser(), 0, 1);
        while ($records && ($row = $records->fetch()))
           $airNames[] = $row['airname'];

        $airNames[] = 'None';
        return $airNames;
    }

    // make header for edit & view playlist
    private function makePlaylistHeader($isEditMode) {
        $editCol = $isEditMode ? "<TD />" : "";
        $header = "<TR class='playlistHdr'>" . $editCol . "<TH WIDTH='64px'>Time</TH><TH WIDTH='25%'>" .
                  "Artist</TH><TH WIDTH='25%'>Track</TH><TH></TH><TH>Album/Label</TH></TR>";
        return $header;
    }

    private function emitPlaylistBody($playlist, $editMode) {
        $header = $this->makePlaylistHeader($editMode);
        $editCell = "";
        echo "<TABLE class='playlistTable' CELLPADDING=1>\n";
        echo "<THEAD>" . $header . "</THEAD>";

        $api = Engine::api(IPlaylist::class);
        $entries = $api->getTracks($playlist, $editMode)->asArray();
        Engine::api(ILibrary::class)->markAlbumsReviewed($entries);

        $observer = PlaylistBuilder::newInstance([
            "action" => $this->subaction,
            "editMode" => $editMode,
            "authUser" => $this->session->isAuth("u")
        ]);
        echo "<TBODY>\n";
        if($entries != null && sizeof($entries) > 0) {
            foreach($entries as $entry)
                $observer->observe(new PlaylistEntry($entry));
        }
        echo "</TBODY></TABLE>\n";

        if($editMode) {
            UI::emitJS('js/playlists.track.js');
        } else {
            $show = $api->getPlaylist($playlist);
            if($api->isNowWithinShow($show))
                UI::emitJS('js/playlists.live.js');
        }
    }

    private function emitPlaylistBanner($playlistId, $playlist, $editMode) {
        $showName = $playlist['description'];
        $djId = $playlist['id'];
        $djName = $playlist['airname'] ?? "None";
        $showDateTime = self::makeShowDateAndTime($playlist);

        $this->title = "$showName with $djName " . self::timestampToDate($playlist['showdate']);

        $this->extra = "<span class='sub'><b>Share Playlist:</b></span> <a class='nav share-link' data-link='".Engine::getBaseURL()."?subaction=viewListById&amp;playlist=$playlistId'><span class='fas fa-link'></span></a></span>";

        if(!$editMode && $this->session->isAuth("v"))
            $showDateTime .= "&nbsp;<A HREF='javascript:document.duplist.submit();' TITLE='Duplicate Playlist'><span class='fas fa-clone dup-playlist'></span></A><FORM NAME='duplist' ACTION='?' METHOD='POST'><INPUT TYPE='hidden' NAME='subaction' VALUE='editList'><INPUT TYPE='hidden' NAME='duplicate' VALUE='1'><INPUT TYPE='hidden' NAME='playlist' VALUE='$playlistId'></FORM>";

        $djName = htmlentities($djName, ENT_QUOTES, 'UTF-8');
        $djLink = $djId ? "<a href='?subaction=viewDJ&amp;seq=selUser&amp;viewuser=$djId' class='nav2'>$djName</a>" : $djName;

        echo "<div class='playlistBanner'><span id='banner-caption'>&nbsp;<span id='banner-description'>".htmlentities($showName, ENT_QUOTES, 'UTF-8')."</span> <span id='banner-dj'>with $djLink</span></span><div>{$showDateTime}&nbsp;</div></div>\n";
?>
    <SCRIPT><!--
    <?php ob_start([JSMin::class, 'minify']); ?>
    // Truncate the show name (banner-description) so that the combined
    // show name, DJ name, and date/time fit on one line.
    $().ready(function() {
        var maxWidth = $(".playlistBanner").outerWidth();
        var dateWidth = $(".playlistBanner div").outerWidth();
        if($("#banner-caption").outerWidth() + dateWidth > maxWidth) {
            var width = maxWidth - $("#banner-dj").outerWidth() - dateWidth - 12;
            $("#banner-description").outerWidth(width);
        }

        $(".share-link").on('click', function() {
            navigator.clipboard.writeText($(this).data('link')).then(function() {
                alert('Playlist URL copied to the clipboard!');
            });
        });
    });
    <?php ob_end_flush(); ?>
    // -->
    </SCRIPT>
    <?php
    }

    private function editPlaylist($playlistId) {
        $this->emitPlaylistBody($playlistId, true);
    }

    private function emitTagForm($playlistId, $message) {
        $playlist = Engine::api(IPlaylist::class)->getPlaylist($playlistId, 1);
        $this->emitPlaylistBanner($playlistId, $playlist, true);
        $this->emitTrackAdder($playlistId, $playlist);
    }
    
    private function emitTrackAdder($playlistId, $playlist, $editTrack = false) {
        $isLiveShow = !$editTrack && Engine::api(IPlaylist::class)->isNowWithinShow($playlist);
        $nmeAr = Engine::param('nme');
        $nmeOpts = '';
        $nmePrefix = self::NME_PREFIX;
        if ($nmeAr) {
            foreach ($nmeAr as $nme)
                $nmeOpts = $nmeOpts . "<option data-args='" . $nme['args'] . "' value='" . $nmePrefix . $nme['name'] . "'>" . $nme['name'] . "</option>";
        }

    ?>
        <div class='pl-form-entry form-entry'>
            <input id='show-time' type='hidden' value="<?php echo $playlist['showtime']; ?>" >
            <input id='timezone-offset' type='hidden' value="<?php echo round(date('Z')/-60, 2); /* server TZ equivalent of javascript Date.getTimezoneOffset() */ ?>" >
            <input id='track-playlist' type='hidden' value='<?php echo $playlistId; ?>'>
            <input id='track-action' type='hidden' value='<?php echo $this->subaction; ?>'>
            <input id='const-prefix' type='hidden' value='<?php echo self::NME_PREFIX; ?>'>
            <label></label><span id='error-msg' class='error'></span>
            <div>
            <?php if(!$editTrack) { ?>
                <div class='dot-menu pull-right' tabindex='-1'>
                  <div class='dot-menu-dots no-text-select'>&#x22ee;</div>
                  <div class='dot-menu-content'>
                    <ul>
                      <li><a href='#' class='nav share-link' data-link='<?php echo Engine::getBaseURL()."?subaction=viewListById&amp;playlist=$playlistId"; ?>' title='copy playlist URL to the clipboard'>Link to Playlist</a>
                      <li><a href='?target=export&amp;playlist=<?php echo $playlistId; ?>&amp;format=csv' class='nav' download='playlist.csv' title='export playlist as CSV'>Export CSV</a>
                      <li><a href='api/v1/playlist/<?php echo $playlistId; ?>' class='nav' download='playlist.json' title='export playlist as JSON'>Export JSON</a>
                      <li><a href='?target=export&amp;playlist=<?php echo $playlistId; ?>&amp;format=html' class='nav' target='_blank' title='printable playlist (opens in new window)'>Print View</a>
                    </ul>
                  </div>
                </div>
            <?php } ?>
                <label>Type:</label>
                <select id='track-type-pick'>
                   <option value='manual-entry'>Music</option>
                   <option value='comment-entry'>Comment</option>
                   <option value='set-separator'>Mic Break (separator)</option>
                   <?php echo $nmeOpts; ?>
                </select>
            </div>
            <div id='track-entry'>
                <div id='manual-entry'>
                    <div style='white-space: nowrap'>
                        <label>Artist / Tag:</label>
                        <input required id='track-artist' autocomplete='off' maxlength=<?php echo PlaylistEntry::MAX_FIELD_LENGTH;?> data-focus />
                        <span class='track-info' id='tag-status'>Artist name or tag number</span>
                        <datalist id='track-artists'>
                        </datalist>
                    </div>
                    <div>
                        <label>Track:</label>
                        <input required id='track-title' maxlength=<?php echo PlaylistEntry::MAX_FIELD_LENGTH;?> autocomplete='off'/>
                        <datalist id='track-titles'>
                        </datalist>
                    </div>
                    <div>
                        <label>Album:</label>
                        <input id='track-album' maxlength=<?php echo PlaylistEntry::MAX_FIELD_LENGTH;?> />
                    </div>
                    <div>
                        <label>Label:</label>
                        <input id='track-label' maxlength=<?php echo PlaylistEntry::MAX_FIELD_LENGTH;?> />
                    </div>
                </div>
                <div id='comment-entry' class='zk-hidden' >
                    <div>
                        <label style='vertical-align: top'>Comment:</label>
                        <textarea wrap=virtual id='comment-data' rows=4 maxlength=<?php echo PlaylistEntry::MAX_COMMENT_LENGTH; ?> required data-focus></textarea>
                        <div style='display: inline-block;'>
                            <span class='remaining' id='remaining'>(0/<?php echo PlaylistEntry::MAX_COMMENT_LENGTH; ?> characters)</span><br/>
                            <a id='markdown-help-link' href='#'>formatting help</a>
                        </div>
                        <input id='comment-max' type='hidden' value='<?php echo PlaylistEntry::MAX_COMMENT_LENGTH; ?>'>

                    </div>
                    <?php UI::markdownHelp(); ?>
                </div>
                <div id='nme-entry' class='zk-hidden' >
                    <div>
                        <label>Name/ID:</label>
                        <input id='nme-id' maxlength=<?php echo PlaylistEntry::MAX_FIELD_LENGTH;?> data-focus/>
                    </div>
                </div>
            </div> <!-- track-entry -->
            <?php
                $api = Engine::api(IPlaylist::class);
                $window = $api->getTimestampWindow($playlistId);
                $time = null;
                $api->getTracksWithObserver($playlistId,
                    (new PlaylistObserver())->on('comment logEvent setSeparator spin', function($entry) use(&$time, $editTrack) {
                        $created = $entry->getCreatedTime();
                        if($created) $time = $created;
                        return $editTrack === $entry->getId();
                    })
                );
                if(!$time) {
                    $startTime = $api->getTimestampWindow($playlistId, false)['start'];
                    $time = $startTime->format('H:i:s');
                }

                // this is probably unnecessary, as desktop browsers *should*
                // degrade 'tel' to 'text', *but* as this is a hack to
                // deal with the lack of keyDown support in mobile input
                // type=text, we'll include 'tel' only for mobile devices...
                $ttype = preg_match('/tablet|mobile|android/i',
                        $_SERVER['HTTP_USER_AGENT'] ?? '') ? "tel" : "text";

                // colon is included in 24hr format for symmetry with fxtime,
                // which it is referencing
                $timeSpec = UI::isUsLocale() ? 'g:i a' : 'H:i';
                $startAMPM = $window['start']->format($timeSpec);
                $endAMPM = $window['end']->format($timeSpec);
                $timeMsg = "($startAMPM - $endAMPM)";

                echo "<div id='time-entry'".($isLiveShow?" class='zk-hidden'":"").">
                    <label>Time:</label>
                    <input id='".($editTrack ? "edit" : "track")."-time' class='fxtime' type='$ttype' step='1' min='".$window['start']->format('H:i')."' max='".$window['end']->format('H:i')."' data-live='".($isLiveShow?1:0)."' data-last-val='$time' />
                    <span class='track-info'>$timeMsg</span>
                </div>\n";
            ?>
            <div>
                <label></label>
                <div class='action-area'>
                <?php if($editTrack) { ?>
                <button type='button' id='edit-save' class='edit-mode default'>Save</button>
                <button type='button' id='edit-delete' class='edit-mode'>Delete</button>
                <button type='button' id='edit-cancel' class='edit-mode'>Cancel</button>
                <?php } else { ?>
                <button type='button' disabled id='track-play' class='track-submit default'>Add <?php echo $isLiveShow?"(Playing Now)<img src='img/play.svg' />":"Item";?></button>
                <button type='button' disabled id='track-add' class='track-submit<?php if(!$isLiveShow) echo " zk-hidden"; ?>'>Add (Upcoming)<img src='img/play-pause.svg' /></button>
                <?php } ?>
                </div>
            </div>
            <div class='toggle-time-entry<?php if (!$isLiveShow) echo " zk-hidden"; ?>'><div><!--&#x1f551;--></div></div>
        </div> <!-- track-editor -->
        <hr>
        <div id="extend-show" class="zk-popup">
            <div class="zk-popup-content">
                <h4>You have reached the end time of your show.</h4>
                <p>Extend by:
                <select id="extend-time">
                    <option value="5">5 minutes</option>
                    <option value="10">10 minutes</option>
                    <option value="15">15 minutes</option>
                    <option value="30">30 minutes</option>
                    <option value="60">1 hour</option>
                </select></p>
                <div class="zk-popup-actionarea">
                    <button type="button">Cancel</button>
                    <button type="button" class="default" id="extend">Extend</button>
                </div>
            </div>
        </div> <!-- extend-show -->
    <?php
    }

    private function emitEditForm($playlistId, $id, $album) {
    ?>
      <DIV class='playlistBanner'>&nbsp;Editing highlighted item</DIV>
      <input type='hidden' id='track-id' value='<?php echo $id; ?>'>
    <?php
        $entry = new PlaylistEntry($album);
        switch($entry->getType()) {
        case PlaylistEntry::TYPE_SET_SEPARATOR:
            $type = "set-separator";
            break;
        case PlaylistEntry::TYPE_COMMENT:
            $type = "comment-entry";
            echo "<input type='hidden' id='old-comment-data' value='" .
                    htmlentities($entry->getComment(), ENT_QUOTES, 'UTF-8') . "' />\n";
            break;
        case PlaylistEntry::TYPE_LOG_EVENT:
            $type = self::NME_PREFIX . $entry->getLogEventType();
            echo "<input type='hidden' id='old-event-code' value='" .
                    htmlentities($entry->getLogEventCode(), ENT_QUOTES, 'UTF-8') . "' />\n";
            break;
        default:
            $type = "manual-entry";
            foreach (['tag', 'artist', 'album', 'label', 'title'] as $field)
                echo "<input type='hidden' id='old-track-$field' value='" .
                    htmlentities($album[$field == 'title' ? 'track' : $field], ENT_QUOTES, 'UTF-8') . "' />\n";
            break;
        }
        echo "<input type='hidden' id='old-created' value='" . $entry->getCreatedTime() . "' />\n";
        echo "<input type='hidden' id='edit-type' value='$type' />\n";

        $playlist = Engine::api(IPlaylist::class)->getPlaylist($playlistId, 1);
        $showName = $playlist['description'];
        $djName = $playlist['airname'] ?? "None";

        $this->title = "$showName with $djName " . self::timestampToDate($playlist['showdate']);
        $this->emitTrackAdder($playlistId, $playlist, $id);
    }

    public function emitEditor() {
        $playlist = $_REQUEST["playlist"] ?? null;
        $seq = $_REQUEST["seq"] ?? null;
        $id = $_REQUEST["id"] ?? null;
    ?>
    <TABLE CELLPADDING=0 CELLSPACING=0 WIDTH="100%">
    <TR><TD>
    <?php
        if($seq == "editTrack") {
            $albuminfo = Engine::api(IPlaylist::class)->getTrack($id);
            if($albuminfo) {
                // if editing a track, always get the playlist from
                // the track, even if one is supplied in the request
                $playlist = $albuminfo['list'];
            }
        }

        $message = "";
        if(is_null($playlist) || !$this->isOwner($playlist)) {
            $seq = "error";
            $message = "access error";
        }

        switch ($seq) {
        case "editTrack":
            $this->emitEditForm($playlist, $id, $albuminfo);
            break;
        default:
            $this->emitTagForm($playlist, $message);
            break;
        }
    ?>
    </TD></TR>
    <TR><TD>
    <?php $this->editPlaylist($playlist, $id); ?>
    </TD></TR>
    </TABLE>
    <?php 
    }

    private function insertTrack($playlistId, $tag, $artist, $track, $album, $label, $spinTime) {
        $id = 0;
        $status = '';
        // Run the query
        $success = Engine::api(IPlaylist::class)->insertTrack($playlistId,
                     $tag, $artist, $track, $album, $label, $spinTime, $id, $status);
    }

    public function emitImportList() {
        $validate = $_POST["validate"];
        $format = $_REQUEST["format"] ?? "json";
        if($format == "csv") {
            $description = mb_substr(trim($_REQUEST["description"]), 0, IPlaylist::MAX_DESCRIPTION_LENGTH);
            $date = $_REQUEST["date"];
            $time = $_REQUEST["time"];
            $airname = $_REQUEST["airname"];
            $djname = mb_substr(trim($_REQUEST["djname"]), 0, IDJ::MAX_AIRNAME_LENGTH);
            $fromtime = $_REQUEST["fromtime"];
            $totime = $_REQUEST["totime"];
        }
        $delimiter = $_REQUEST["delimiter"] ?? "";
        $enclosure = $_REQUEST["enclosure"] ?? "\"";
        $userfile = $_FILES['userfile']['tmp_name'];
        $playlist = $_REQUEST["playlist"];
        $button = $_REQUEST["button"];

        $empty = $_POST["empty"] ?? 0;
        if($empty)
            $errorMessage = "<b><font class='error'>Import file contains no data.  Check the format and try again.</font></b>";

        if($format == "csv") {
            if(!$date)
                $date = date("Y-m-d");
            list($year, $month, $day) = explode("-", $date);

            $time = $this->composeTime($fromtime, $totime);

            if($validate == "edit" && !$time) {
                $errorMessage = "<b><font class='error'>Invalid time range (min " . IPlaylist::MIN_SHOW_LEN . " minutes, max " . (IPlaylist::MAX_SHOW_LEN / 60) . " hours)</font></b>";
                $totime = "";
            }

            // lookup the airname
            $aid = null;
            if($validate == "edit" && $airname && strcasecmp($airname, "none")) {
                $djapi = Engine::api(IDJ::class);
                $aid = $djapi->getAirname($airname, $this->session->getUser());
                if(!$aid) {
                    // airname does not exist; try to create it
                    $success = $djapi->insertAirname(mb_substr($airname, 0, IDJ::MAX_AIRNAME_LENGTH), $this->session->getUser());
                    if($success > 0) {
                        // success!
                        $aid = $djapi->lastInsertId();
                    } else {
                        $errorMessage = "<b><font class='error'>Airname '$airname' is invalid or already exists.</font></b>";
                        $airname = "";
                        $aid = false;
                    }
                }
            }
        }

        if($userfile && $format == "json") {
            // read the JSON file
            $file = file_get_contents($userfile);

            // parse the file
            $json = json_decode($file);

            // allow type 'show' in root node (legacy)
            if(!$json || $json->type != "show") {
                // 'show' encapsulated within data
                if($json && is_array($json->data) && $json->data[0]->type == "show")
                    $json = $json->data[0];
                else if($json && $json->data && $json->data->type == "show")
                    $json = $json->data;
                else
                    $errorMessage = "<B><FONT CLASS='error'>File is not in the expected format.  Ensure file is a valid JSON playlist.</FONT></B><BR>\n";
            }

            if($json && $json->type == "show") {
                // allow for legacy attributes at the data level
                $attrs = isset($json->attributes)?$json->attributes:$json;

                // validate the show's properties
                $valid = false;
                list($year, $month, $day) = explode("-", $attrs->date);
                if($attrs->airname && $attrs->name && $attrs->time &&
                        checkdate($month, $day, $year))
                    $valid = true;

                // lookup the airname
                if($valid) {
                    $djapi = Engine::api(IDJ::class);
                    $airname = $djapi->getAirname($attrs->airname, $this->session->getUser());
                    if(!$airname) {
                        // airname does not exist; try to create it
                        $success = $djapi->insertAirname(mb_substr($attrs->airname, 0, IDJ::MAX_AIRNAME_LENGTH), $this->session->getUser());
                        if($success > 0) {
                            // success!
                            $airname = $djapi->lastInsertId();
                        } else
                            $valid = false;
                    }
                }

                // create the playlist
                if($valid) {
                    $papi = Engine::api(IPlaylist::class);
                    $papi->insertPlaylist($this->session->getUser(), $attrs->date, $attrs->time, mb_substr($attrs->name, 0, IPlaylist::MAX_DESCRIPTION_LENGTH), $airname);
                    $playlist = $papi->lastInsertId();

                    // insert the tracks
                    $count = 0;
                    $status = '';
                    $window = $papi->getTimestampWindow($playlist);
                    $data = isset($json->attributes)?$attrs->events:$json->data;
                    foreach($data as $pentry) {
                        $entry = PlaylistEntry::fromJSON($pentry);
                        $created = $entry->getCreated();
                        if($created) {
                            try {
                                $stamp = PlaylistEntry::scrubTimestamp(
                                            new \DateTime($created), $window);
                                $entry->setCreated($stamp?$stamp->format(IPlaylist::TIME_FORMAT_SQL):null);
                            } catch(\Exception $e) {
                                error_log("failed to parse timestamp: $created");
                                $entry->setCreated(null);
                            }
                        }
                        $success = $papi->insertTrackEntry($playlist, $entry, $status);
                        $count++;
                    }

                    if($count == 0) {
                        $papi->deletePlaylist($playlist);
                        $errorMessage = "<b><font class='error'>Import file contains no entries.</font></b><br>\n";
                    } else {
                        // success
                        $this->lazyLoadImages($playlist);

                        // display the editor
?>
    <SCRIPT><!--
        window.open("?subaction=editListEditor&playlist=<?php echo $playlist; ?>", "_top");
    // -->
    </SCRIPT>
<?php
                        $displayForm = false;
                    }
                } else
                    $errorMessage = "<B><FONT CLASS='error'>Show details are invalid.</FONT></B><BR>\n";
            }
        }

        if(!$userfile || $userfile == "none" || $errorMessage || $empty ||
                $format == "csv" && (
                    $description == "" ||
                    $time == '' ||
                    $aid === false ||
                    !checkdate($month, $day, $year))) {
            $this->setTemplate("list/import.html");
            $this->addVar('errorMessage',
                    $validate == "edit" ?
                    ($errorMessage ?? "<b><font class='error'>Ensure fields are not blank and date is valid.</font></b><br>\n") : false);
            $this->addVar('format', $format);
            $this->addVar('date', $date);
            $this->addVar('fromtime', $fromtime);
            $this->addVar('totime', $totime);
            $this->addVar('dateformat', UI::isUsLocale() ? "mm/dd/yy" : "dd-mm-yy");
            $this->addVar('airnames', $this->getDJAirNames());
            $this->addVar('delimiter', $delimiter);
            $this->addVar('enclosure', $enclosure);
            $this->addVar('description', stripslashes($description));
            $this->addVar('MAX_DESCRIPTION_LENGTH', IPlaylist::MAX_DESCRIPTION_LENGTH);
            $this->addVar('airname', $airname ?? '');
            $this->addVar('MAX_AIRNAME_LENGTH', IDJ::MAX_AIRNAME_LENGTH);
        } else if($format == "csv"){
            // Create the playlist
            $api = Engine::api(IPlaylist::class);
            $success = $api->insertPlaylist($this->session->getUser(), $date, $time, $description, $aid);
            $playlist = $api->lastInsertId();

            // empty delimiter is tab
            if(strlen(trim($delimiter)) == 0)
                $delimiter = "\t";

            // Insert the tracks
            $count = 0;
            $fd = new \SplFileObject($userfile, "r");
            $window = $api->getTimestampWindow($playlist);
            while($fd->valid()) {
                $line = $fd->fgetcsv($delimiter, $enclosure);
                switch(count($line)) {
                case 4:
                    // artist track album label
                    $this->insertTrack($playlist,
                             0,               // tag
                             PlaylistEntry::scrubField($line[0]),  // artist
                             PlaylistEntry::scrubField($line[1]),  // track
                             PlaylistEntry::scrubField($line[2]),  // album
                             PlaylistEntry::scrubField($line[3]), null); // label
                    $count++;
                    break;
                case 5:
                case 6:
                    // artist track album tag label
                    if($line[3]) {
                        // Lookup tag
                        $albumrec = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $line[3]);
                        if(sizeof($albumrec) == 0) {
                            // invalid tag
                            $line[3] = "";
                        } else {
                            // update artist and album from tag
                            if(!$albumrec[0]["iscoll"])
                                $line[0] = $albumrec[0]["artist"];
                            $line[2] = $albumrec[0]["album"];
    
                            // update label name
                            $line[4] = isset($albumrec[0]["name"])?
                                $albumrec[0]["name"]:"(Unknown)";
                        }
                    }

                    if(count($line) == 6 && $line[5]) {
                        try {
                            $timestamp = PlaylistEntry::scrubTimestamp(
                                            new \DateTime($line[5]), $window);
                        } catch(\Exception $e) {
                            error_log("failed to parse timestamp: {$line[5]}");
                            $timestamp = null;
                        }
                    } else
                        $timestamp = null;

                    $this->insertTrack($playlist,
                             trim($line[3]),  // tag
                             PlaylistEntry::scrubField($line[0]),  // artist
                             PlaylistEntry::scrubField($line[1]),  // track
                             PlaylistEntry::scrubField($line[2]),  // album
                             PlaylistEntry::scrubField($line[4]),  // label
                             $timestamp);     // timestamp
                    $count++;
                    break;
                }
            }
            // echo "<B>Imported $count tracks.</B>\n";
            $fd = null; // close

            if($count == 0) {
                $api->deletePlaylist($playlist);
                $_POST["empty"] = 1;
                $this->emitImportList();
                return;
            }

            $this->lazyLoadImages($playlist);
?>
    <SCRIPT><!--
        window.open("?subaction=editListEditor&playlist=<?php echo $playlist; ?>", "_top");
    // -->
    </SCRIPT>
<?php
        }
    }
    
    public function emitViewPlayList() {
        $playlistId = $_REQUEST["playlist"];
        $this->viewList($playlistId);
    }

    private function viewList($playlistId) {
        $row = Engine::api(IPlaylist::class)->getPlaylist($playlistId, 1);
        if(!$row ||
                !$row['airname'] && $this->session->getUser() != $row['dj']) {
            echo "<B>Sorry, the playlist you have requested does not exist.</B>";
            return;
        }

        if($this->subaction == "viewDJ" && $row['airname'])
            $this->tertiary = $row['airname'];

        $this->emitPlaylistBanner($playlistId, $row, false);
        $this->emitPlaylistBody($playlistId, false);
    }
    
    private function emitViewDJAlbum(&$result, $class="", $count=0, $labelField="label") {
        for($i=0; $i < sizeof($result); $i++) {
            echo "  <TR><TD VALIGN=TOP ALIGN=\"right\"$class>";
            if($count)
                echo (string)($i + 1).".&nbsp;";
            else
                echo "&nbsp;&#8226;&nbsp;";
            echo "</TD><TD$class>";
    
            // Setup artist and label
            $artist = preg_match("/^(\[)?COLL(?(1)\]|$)/i", $result[$i]["artist"])?"Various Artists":$result[$i]["artist"];
            $label = str_replace(" Records", "", $result[$i][$labelField]);
            $label = str_replace(" Recordings", "", $label);
    
            echo UI::smartURL($artist) . "&nbsp;&#8226; <I>";
    
            // Album
            if($result[$i]["tag"])
                 echo "<A CLASS=\"nav\" HREF=\"".
                      "?s=byAlbumKey&amp;n=". UI::URLify($result[$i]["tag"]).
                      "&amp;action=search\">";
    
            echo UI::smartURL($result[$i]["album"], !$result[$i]["tag"]);
            if($result[$i]["tag"])
                echo "</A>";
            echo "</I>";
            if($label)
                echo "&nbsp;&#8226; (".UI::smartURL($label) . ")";
            echo "</TD></TR>\n";
        }
    }
    
    public function emitViewDJ() {
        UI::emitJS('js/zklistbox.js');

        $seq = $_REQUEST["seq"] ?? '';
        $viewuser = $_REQUEST["viewuser"] ?? 0;
        $playlist = $_REQUEST["playlist"] ?? 0;
    
        settype($playlist, "integer");
        settype($viewuser, "integer");
    
        if(((($seq == "selUser") && $viewuser)) ||
                (($seq == "selList") && !$playlist)) {
            $results = Engine::api(IDJ::class)->getAirnames(0, $viewuser);
            if($results) {
                $row = $results->fetch();
                $this->tertiary = $row['airname'];
            }
    ?>
    <FORM ACTION="?" class="selector" METHOD=POST>
    <TABLE WIDTH="100%"><TR><TD ALIGN=RIGHT VALIGN=TOP>
    <?php 
            // Emit optional URL and/or e-mail for DJ
            if($row['url']) {
                echo "      <A HREF=\"".$row['url']."\" CLASS=\"nav\" target='_blank'><B>Go to ".$row['airname']."'s website</B></A>\n";
                if($row['email'])
                    echo "      &nbsp; | &nbsp;\n";
            }
            if($row['email']) {
                echo "      <A HREF=\"mailto:".$row['email']."\" CLASS=\"nav\"><B>e-mail ".$row['airname']."</B></A>\n";
            }
    ?>
    </TD></TR></TABLE>
    <TABLE CELLSPACING=0>
      <?php 
            $weeks = 10;
            $limit = 10;
            $formatEndDate = date("l, j F Y");
    
            $topPlays = Engine::api(IPlaylist::class)->getTopPlays($viewuser, $weeks * 7, $limit);
            if(sizeof($topPlays)) {
                echo "<TR><TH COLSPAN=2 ALIGN=LEFT CLASS=\"subhead\">&nbsp;".$row['airname']."'s top $limit<BR>&nbsp;<FONT CLASS=\"subhead2\">for the $weeks week period ending $formatEndDate</FONT></TH></TR>\n";
                $this->emitViewDJAlbum($topPlays, "", 1);
            }
    ?>
    </TABLE>
    <?php if (sizeof($topPlays)) echo "<BR>\n"; ?>
    <TABLE WIDTH="100%" CELLPADDING=0 CELLSPACING=0 BORDER=0>
      <TR><TD CLASS="recentPlays" VALIGN=TOP>
    <?php 
            $count = 10;
            $recentPlays = Engine::api(IPlaylist::class)->getRecentPlays($viewuser, $count);
            $recentReviews = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_AIRNAME, 0, $count - 1, $viewuser, "Date-");

            $block = sizeof($recentReviews);
            $blname = sizeof($topPlays)?"":$row['airname'] . "'s ";
            echo "    <TABLE CELLSPACING=0 BORDER=0>\n";
            if(sizeof($recentPlays)) {
                echo "<TR><TH COLSPAN=2 ALIGN=LEFT CLASS=\"subhead\">&nbsp;${blname}Recent airplay</TH></TR>";
                $this->emitViewDJAlbum($recentPlays, $block?" CLASS=\"sub\"":"");
            }
    ?>
        </TABLE>
      </TD><?php 
        if(sizeof($recentReviews)) {
            $block = sizeof($recentPlays);
            echo "<TD>".($block?"&nbsp;&nbsp;&nbsp;":"")."</TD><TD CLASS=\"recentReviews\"VALIGN=TOP>\n";
            $blname = (sizeof($topPlays) || sizeof($recentPlays))?"":$row['airname'] . "'s ";
            echo "    <TABLE BORDER=0 CELLSPACING=0>\n";
    
            echo "      <TR><TH COLSPAN=2 ALIGN=LEFT CLASS=\"subhead\">&nbsp;${blname}Recent reviews</TH></TR>\n";
            $this->emitViewDJAlbum($recentReviews, $block?" CLASS=\"sub\"":"", 0, "name");
            if(sizeof($recentReviews) == $count - 1)
                echo "  <TR><TD></TD><TD ALIGN=LEFT CLASS=\"sub\"><A HREF=\"?action=viewRecent&amp;subaction=viewDJ&amp;seq=selUser&viewuser=$viewuser\" CLASS=\"nav\">More reviews...</A></TD></TR>\n";
            echo "    </TABLE></TD>\n";
        }
    
    ?>
      </TR></TABLE>
    <?php if (sizeof($topPlays) || sizeof($recentPlays) || sizeof($recentReviews)) echo "<BR>\n"; ?>
    <TABLE>
      <TR><TH ALIGN=LEFT><?php echo $row['airname'];?>'s playlists:</TH></TR>
      <TR><TD>
         <ul tabindex='0' class='selector listbox no-text-select' data-name='playlist'>
    <?php 
            // Run the query
            $records = Engine::api(IPlaylist::class)->getPlaylists(0, 0, 0, $viewuser);
            while($row = $records->fetch())
                echo "        <li data-value=\"$row[0]\">$row[1] -- ".htmlentities($row[3], ENT_QUOTES, 'UTF-8')."</li>\n";
    ?>
        </ul></TD></TR>
      <TR><TD>
        <SCRIPT><!--
           $().ready(function() {
               $("ul.selector").zklistbox().trigger('focus');
           });
        // -->
        </SCRIPT>
        <INPUT TYPE=SUBMIT VALUE=" View Playlist ">
        <INPUT TYPE=HIDDEN NAME=playlist VALUE="">
        <INPUT TYPE=HIDDEN NAME=viewuser VALUE="<?php echo $viewuser;?>">
        <INPUT TYPE=HIDDEN NAME=subaction VALUE="viewDJ">
        <INPUT TYPE=HIDDEN NAME=seq VALUE="selList">
      </TD></TR>
    </TABLE>
    </FORM>
    <?php 
            return;
        } else if(($seq == "selList") && $playlist) {
            $this->viewList($playlist);
            return;
        }

        $this->emitViewDJMain();
    }

    public function emitTopPlays() {
        $days = min($_REQUEST['days'] ?? 7, 42);
        $limit = min($_REQUEST['limit'] ?? 30, 100);

        $topPlays = Engine::api(IPlaylist::class)->getTopPlays(0, $days, $limit);
        Engine::api(ILibrary::class)->markAlbumsReviewed($topPlays);

        foreach($topPlays as &$entry) {
            if($entry['tag'] && !$entry['iscoll'])
                $entry['artist'] = PlaylistEntry::swapNames($entry['artist']);
        }

        $this->setTemplate('airplay.html');
        $this->addVar('days', $days);
        $this->addVar('limit', $limit);
        $this->addVar('topPlays', $topPlays);
    }
    
    public function emitViewDJMain() {
        $viewAll = $this->subaction == "viewDJAll";

        // Run the query
        $records = Engine::api(IDJ::class)->getActiveAirnames($viewAll);
        $dj = [];
        while($records && ($row = $records->fetch())) {
            $row["sort"] = preg_match("/^(the|dj)\s+(.+)/i", $row[1], $matches) ? $matches[2] : $row[1];
            // sort symbols beyond Z with the numerics and other special chars
            $row["cur"] = UI::deLatin1ify(mb_strtoupper(mb_substr($row["sort"], 0, 1)));
            if($row["cur"] > "Z") {
                $row["sort"] = "@".$row["sort"];
                $row["cur"] = "@";
            }

            $dj[] = $row;
        }
    
        if(count($dj))
            usort($dj, function($a, $b) {
                return strcasecmp($a["sort"], $b["sort"]);
            });
    
        $this->setTemplate("selectdj.html");
        $this->addVar("djs", $dj);
    }
    
    // return list of days in month that have at least 1 playlist.
    public function handlePlaylistDaysByDate() {
        $date = $_REQUEST["viewdate"];
        $dateAr = explode('-', $date);
        $records = Engine::api(IPlaylist::class)->getShowdates($dateAr[0], $dateAr[1]);
        $showdates = array();
        while ($records && ($row = $records->fetch())) {
            $showdates[] = explode('-', $row['showdate'])[2];
        }

        $length = count($showdates);
        echo json_encode($showdates);
    }

    // emit page for picking playlists for a given month.
    public function emitPlaylistPicker() {
        $startDate = Engine::param('playlist_start_date');
        $this->setTemplate("list/bydate.html");
        $this->addVar("startDate", $startDate);
    }

    public function handlePlaylistsByDate() {
        $viewdate = $_REQUEST["viewdate"];
        $records = Engine::api(IPlaylist::class)->getPlaylists(1, 1, $viewdate, 0, 0, 0, 20);
        $tbody = '';
        $count = 0;
        $href = '?subaction=viewListById&playlist';
        while($records && ($row = $records->fetch())) {
            $timeRange = self::timeToLocale($row[2]);
            $title = htmlentities($row[3]);
            $djs = htmlentities($row[5]);
            $tbody .= "<TR>" .
                 "<TD ALIGN='RIGHT' CLASS='sub time range'>$timeRange&nbsp;</TD>" .
                 "<TD><A CLASS='nav' HREF='$href=$row[0]'>$title</A>&nbsp;&nbsp;($djs)</TD>" .
                 "</TR>\n";
            $count = $count + 1;
        }
        echo json_encode(["count" => $count, "tbody" => $tbody]);
    }
}

