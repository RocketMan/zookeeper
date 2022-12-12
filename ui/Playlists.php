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
    private static $actions = [
        [ "viewList", "emitPlaylistPicker" ],
        [ "viewListById", "emitViewPlaylist" ],
        [ "viewListDaysByDate", "handlePlaylistDaysByDate" ],
        [ "viewListsByDate", "handlePlaylistsByDate" ],
        [ "editList", "emitListManager" ],
        [ "editListGetHint", "listManagerGetHint" ],
        [ "editListEditor", "emitEditor" ],
        [ "importExport", "emitImportExportList" ],
        [ "viewDJ", "emitViewDJ" ],
        [ "viewDJReviews", "viewDJReviews" ],
        [ "updateDJInfo", "updateDJInfo" ],
    ];

    private $action;
    private $subaction;

    public function processLocal($action, $subaction) {
        $this->action = $action;
        $this->subaction = $subaction;
        return $this->dispatchAction($action, self::$actions);
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
    
    private static function isUsLocale() : bool {
        return UI::getClientLocale() == 'en_US';
    }

    private static function hourToLocale($hour, $full=0) {
        // account for legacy, free-format time encoding
        if(!is_numeric($hour) || !self::isUsLocale())
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
            $dateSpec = self::isUsLocale() ? 'D M d, Y ' : 'D d M Y ';
            return date($dateSpec, strtotime($time));
        }
    }

    public static function makeShowDateAndTime($row) {
        return self::timestampToDate($row['showdate']) . " " .
               self::timeToLocale($row['showtime']);
    }

    public function viewDJReviews() {
        $this->newEntity(Search::class)->doSearch();
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

    public function emitListManager() {
        UI::emitJS("js/jquery.fxtime.js");
        UI::emitJS("js/playlists.pick.js");
        ?>
  <div class='playlist-accordion no-text-select' style='display: none'>
    <h3>My Playlists</h3>
    <div class='active-playlist-container'>
      <div class='float-error'></div>
      <div class='newPlaylist'><button><span>+ Add New Playlist</span></button></div>
      <div>
        <datalist class='airnames'>
        <?php echo $this->getDJAirNames(); ?>
        </datalist>
        <input type='hidden' id='duplicate-suffix' value='<?php
        echo IPlaylist::DUPLICATE_SUFFIX; ?>' />
        <input type='hidden' id='max-description-length' value='<?php
        echo IPlaylist::MAX_DESCRIPTION_LENGTH; ?>' />
        <input type='hidden' id='max-airname-length' value='<?php
        echo IDJ::MAX_AIRNAME_LENGTH; ?>' />
        <table class='playlist-grid active-grid'>
          <colgroup>
            <col style='width: 55px'>
            <col style='width: 170px'>
            <col style='width: 100px'>
            <col style='width: 90px'>
            <col style='width: 70px'>
            <col style='width: 12px'>
            <col style='width: 70px'>
            <col>
          </colgroup>
          <thead><tr>
            <th></th><th>Show</th><th>DJ</th><th>Date</th><th>Start</th><th></th><th>End</th><th></th>
            </tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
    <h3>Deleted Playlists</h3>
    <div class='deleted-playlist-container'>
      <table class='playlist-grid deleted-grid'>
        <colgroup>
          <col style='width: 65px'>
          <col style='width: 170px'>
          <col style='width: 100px'>
          <col style='width: 90px'>
          <col style='width: 70px'>
          <col style='width: 12px'>
          <col style='width: 70px'>
          <col style='width: 90px'>
        </colgroup>
        <thead><tr>
          <th></th><th>Show</th><th>DJ</th><th>Date</th><th>Start</th><th></th><th>End</th><th>Expires</th>
          </tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
  <?php
        if(isset($_POST["duplicate"]) && $_POST["duplicate"]) {
            echo "<input type='hidden' id='duplicate' value='" .
                    $_POST["playlist"] . "' />\n";
        }
    }

    private function getDJAirNames() {
        $airNames = '';
        $records = Engine::api(IDJ::class)->getAirnames($this->session->getUser(), 0, 1);
        while ($records && ($row = $records->fetch())) {
           $newItem = "<OPTION VALUE='".htmlentities($row['airname'], ENT_QUOTES, 'UTF-8')."'>";
           $airNames .= $newItem;
        }

        $airNames .=  "<OPTION VALUE='None'>";
        return $airNames."\n";
    }

    // make header for edit & view playlist
    private function makePlaylistHeader($isEditMode) {
        $editCol = $isEditMode ? "<TD WIDTH='30PX' />" : "";
        $header = "<TR class='playlistHdr' ALIGN=LEFT>" . $editCol . "<TH WIDTH='64px'>Time</TH><TH WIDTH='25%'>" .
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
            "action" => $this->action,
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
            UI::emitJS('js/jquery.fxtime.js');
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

        if(!$editMode && $this->session->isAuth("v"))
            $showDateTime .= "&nbsp;<A HREF='javascript:document.duplist.submit();' TITLE='Duplicate Playlist'>&#x1f4cb;</A><FORM NAME='duplist' ACTION='?' METHOD='POST'><INPUT TYPE='hidden' NAME='action' VALUE='editList'><INPUT TYPE='hidden' NAME='duplicate' VALUE='1'><INPUT TYPE='hidden' NAME='playlist' VALUE='$playlistId'></FORM>";

        $djName = htmlentities($djName, ENT_QUOTES, 'UTF-8');
        $djLink = $djId ? "<a href='?action=viewDJ&amp;seq=selUser&amp;viewuser=$djId' class='nav2'>$djName</a>" : $djName;

        echo "<div class='playlistBanner'><span id='banner-caption'>&nbsp;<span id='banner-description'>".htmlentities($showName, ENT_QUOTES, 'UTF-8')."</span> <span id='banner-dj'>with $djLink</span></span><div>{$showDateTime}&nbsp;</div></div>\n";
?>
    <SCRIPT TYPE="text/javascript"><!--
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
        <div class='pl-form-entry'>
            <input id='show-time' type='hidden' value="<?php echo $playlist['showtime']; ?>" >
            <input id='timezone-offset' type='hidden' value="<?php echo round(date('Z')/-60, 2); /* server TZ equivalent of javascript Date.getTimezoneOffset() */ ?>" >
            <input id='track-playlist' type='hidden' value='<?php echo $playlistId; ?>'>
            <input id='track-action' type='hidden' value='<?php echo $this->action; ?>'>
            <input id='const-prefix' type='hidden' value='<?php echo self::NME_PREFIX; ?>'>
            <label></label><span id='error-msg' class='error'></span>
            <div>
            <?php if(!$editTrack) { ?>
                <div class='dot-menu pull-right' tabindex='-1'>
                  <div class='dot-menu-dots no-text-select'>&#x22ee;</div>
                  <div class='dot-menu-content'>
                    <ul>
                      <li><a href='#' class='nav' data-link='<?php echo Engine::getBaseURL()."?action=viewListById&amp;playlist=$playlistId"; ?>' id='copy-link' title='copy playlist URL to the clipboard'>Link to Playlist</a>
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
                    <div>
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
                $timeSpec = self::isUsLocale() ? 'g:i a' : 'H:i';
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
                <?php if($editTrack) { ?>
                <button type='button' id='edit-save' class='edit-mode default'>Save</button>
                <button type='button' id='edit-delete' class='edit-mode'>Delete</button>
                <button type='button' id='edit-cancel' class='edit-mode'>Cancel</button>
                <?php } else { ?>
                <button type='button' disabled id='track-play' class='track-submit default'>Add <?php echo $isLiveShow?"(Playing Now)<img src='img/play.svg' />":"Item";?></button>
                <button type='button' disabled id='track-add' class='track-submit<?php if(!$isLiveShow) echo " zk-hidden"; ?>'>Add (Upcoming)<img src='img/play-pause.svg' /></button>
                <?php } ?>
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

    public function emitImportExportList() {
       $subactions = [
           [ "u", "", "Export Playlist", "emitExportList" ],
           [ "u", "importJSON", "Import Playlist (JSON)", "emitImportJSON" ],
           [ "u", "importCSV", "Import Playlist (CSV)", "emitImportList" ]
       ];
       $this->dispatchSubaction($this->action, $this->subaction, $subactions);
    }
    
    private function insertTrack($playlistId, $tag, $artist, $track, $album, $label, $spinTime) {
        $id = 0;
        $status = '';
        // Run the query
        $success = Engine::api(IPlaylist::class)->insertTrack($playlistId,
                     $tag, $artist, $track, $album, $label, $spinTime, $id, $status);
    }

    public function emitExportList() {
    ?>
    <FORM ACTION="?" METHOD=POST>
    <B>Select Playlist:</B><BR>
    <TABLE CELLPADDING=0 BORDER=0><TR><TD>
    <SELECT NAME=playlist SIZE=10>
    <?php 
        // Run the query
        $records = Engine::api(IPlaylist::class)->getListsSelNormal($this->session->getUser());
        while($records && ($row = $records->fetch()))
            echo "  <OPTION VALUE=\"$row[0]\">$row[1] -- $row[3]\n";
    ?>
    </SELECT></TD></TR>
    <TR><TD>
       <B>Export As:</B>
       <INPUT TYPE=RADIO NAME=format VALUE=json>JSON
       <INPUT TYPE=RADIO NAME=format VALUE=csv>CSV
       <!--
       <INPUT TYPE=RADIO NAME=format VALUE=xml>XML
       <INPUT TYPE=RADIO NAME=format VALUE=html>HTML
       -->
    </TD></TR>
    <TR><TD>
    <INPUT TYPE=SUBMIT VALUE=" Export Playlist ">
    <INPUT TYPE=HIDDEN NAME=target VALUE="export">
    </TD></TR></TABLE>
    </FORM>
    <div id='json-help' class='user-tip'>

    <h4>About JSON</h4>

    <p>The JSON format preserves all playlist details, including log
    entries, comments, and time marks.  It is the more modern,
    comprehensive export alternative.</p>

    <p>You should choose JSON if you wish to preserve all aspects of
    your playlist for subsequent import.</p>

    </div>
    <div id='csv-help' class='user-tip'>

    <h4>About CSV</h4>

    <p>CSV is a simple, tab-delimited format that preserves track
    details only.</p>

    <p>This format is suitable for use with spreadsheets and legacy
    applications.</p>

    </div>
      <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript"><!--
    <?php ob_start([JSMin::class, 'minify']); ?>
            $('input:radio[name="format"]').change(function() {
                if($(this).is(':checked') && $(this).val() == "json") {
                    $("#json-help").show();
                    $("#csv-help").hide();
                } else {
                    $("#json-help").hide();
                    $("#csv-help").show();
                }
            });
            $().ready(function() {
                $("input[name='format']:eq(0)").click();
                $("select[name='playlist']").focus();
            });
    <?php ob_end_flush(); ?>
      // -->
      </SCRIPT>
    <?php 
    }

    public function emitImportList() {
        $validate = $_POST["validate"];
        $description = mb_substr(trim($_REQUEST["description"]), 0, IPlaylist::MAX_DESCRIPTION_LENGTH);
        $date = $_REQUEST["date"];
        $time = $_REQUEST["time"];
        $airname = $_REQUEST["airname"];
        $playlist = $_REQUEST["playlist"];
        $button = $_REQUEST["button"];
        $djname = mb_substr(trim($_REQUEST["djname"]), 0, IDJ::MAX_AIRNAME_LENGTH);
        $newairname = $_REQUEST["newairname"];
        $userfile = $_FILES['userfile']['tmp_name'];
        $fromtime = $_REQUEST["fromtime"];
        $totime = $_REQUEST["totime"];
        $delimiter = $_REQUEST["delimiter"] ?? "";
        $enclosure = $_REQUEST["enclosure"] ?? "\"";

        $empty = $_POST["empty"] ?? 0;
        if($empty)
            $errorMessage = "<b><font class='error'>Import file contains no data.  Check the format and try again.</font></b>";
    
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

        if(!$userfile || $userfile == "none" ||
                $description == "" ||
                $time == '' ||
                $aid === false ||
                $empty ||
                !checkdate($month, $day, $year)) {
            UI::emitJS('js/jquery.fxtime.js');
            UI::emitJS('js/playlists.import.js');

            if($validate == "edit")
                echo $errorMessage ?? "<b><font class='error'>Ensure fields are not blank and date is valid.</font></b><br>\n";
    ?>
      <form class='import-form import-csv' enctype='multipart/form-data' action='?' method='post'>
        <input type='hidden' name='action' value='importExport'>
        <input type='hidden' name='subaction' value='importCSV'>
        <input type='hidden' name='validate' value='edit'>
        <input type='hidden' name='MAX_FILE_SIZE' value='100000'>
        <input type='hidden' name='date' id='date' value='<?php echo $date; ?>'>
        <input type='hidden' name='fromtime' id='fromtime' value='<?php echo $fromtime; ?>'>
        <input type='hidden' name='totime' id='totime' value='<?php echo $totime; ?>'>
        <input type='hidden' id='date-format' value='<?php echo self::isUsLocale() ? "mm/dd/yy" : "dd-mm-yy"; ?>'>
        <datalist id='airnames'>
        <?php echo $this->getDJAirNames(); ?>
        </datalist>
        <div>
          <label>Import from:</label>
          <div class='group file-area'>
            <input type='file' name='userfile' required>
            <div class='file-overlay'>
              <div class='default'>Drag&hairsp;&amp;&hairsp;Drop file here or <span class='pseudo-button'>Browse Files</span></div>
              <div class='success'>Your file is selected.</div>
            </div>
          </div>
        </div>
        <div>
          <label>Delimiter:</label>
          <div class='group'>
            <input type='text' class='delimiter' name='delimiter' maxlength='1' value='<?php echo htmlentities($delimiter, ENT_QUOTES); ?>'> (empty for tab)
            <div class='pull-right'>
              Field enclosure:
              <input type='text' class='delimiter' name='enclosure' maxlength='1' value='<?php echo htmlentities($enclosure, ENT_QUOTES); ?>'>
            </div>
          </div>
        </div>
        <div>
          <label>Show Name:</label>
          <input type='text' name='description' value='<?php echo stripslashes($description);?>' maxlength='<?php echo IPlaylist::MAX_DESCRIPTION_LENGTH;?>' required>
        </div>
        <div>
          <label>DJ:</label>
          <input type='text' id='airname' name='airname' value='<?php echo $airname; ?>' maxlength='<?php echo IDJ::MAX_AIRNAME_LENGTH; ?>' required>
        </div>
        <div>
          <label>Date / Time:</label>
          <div class='group'>
            <input type='text' class='date' required>
            <div class='pull-right'>
              <input type='text' id='fromtime-entry' class='time' required>
              <div class='time-spacer'>-</div>
              <input type='text' id='totime-entry' class='time' required>
            </div>
          </div>
        </div>
        <div>
          <label></label>
          <input type='submit' value=' Import Playlist '>
        </div>
        <div>
          <label></label>
          <div class='user-tip sub' style='display: inline-block; max-width: 550px;'>
            <h4>CSV Format</h4>
            <p>File must be UTF-8 encoded, with one
            track per line.  Each line may contain 4, 5, or 6 columns:</p>
            <pre style='padding-left: 20px; white-space: normal;'><b>artist&nbsp; track&nbsp; album&nbsp; label</b> &nbsp;<i>or</i><br>
            <b>artist&nbsp; track&nbsp; album&nbsp; tag&nbsp;&nbsp; label</b> &nbsp;<i>or</i><br>
            <b>artist&nbsp; track&nbsp; album&nbsp; tag&nbsp;&nbsp; label&nbsp; timestamp</b></pre>
            <p>where each column is optionally enclosed by the specified field enclosure character, and separated by a delimiter character.  If no delimiter is specified, tab is used.</p>
            <p>Any file data not in this format will be ignored.</p>
          </div>
        </div>
      </form>
    <?php 
        } else {
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
    <SCRIPT TYPE="text/javascript"><!--
        window.open("?action=editListEditor&playlist=<?php echo $playlist; ?>", "_top");
    // -->
    </SCRIPT>
<?php
        }
    }
    
    public function emitImportJSON() {
        $displayForm = true;
        $userfile = $_FILES['userfile']['tmp_name'];
        if($userfile) {
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
                    echo "<B><FONT CLASS='error'>File is not in the expected format.  Ensure file is a valid JSON playlist.</FONT></B><BR>\n";
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
                        echo "<b><font class='error'>Import file contains no entries.</font></b><br>\n";
                    } else {
                        // success
                        $this->lazyLoadImages($playlist);

                        // display the editor
?>
    <SCRIPT TYPE="text/javascript"><!--
        window.open("?action=editListEditor&playlist=<?php echo $playlist; ?>", "_top");
    // -->
    </SCRIPT>
<?php
                        $displayForm = false;
                    }
                } else
                    echo "<B><FONT CLASS='error'>Show details are invalid.</FONT></B><BR>\n";
            }
        }

        if($displayForm) {
            UI::emitJS('js/jquery.fxtime.js');
            UI::emitJS('js/playlists.import.js');
    ?>
      <form class='import-form import-json' enctype="multipart/form-data" action='?' method='post'>
        <input type='hidden' name='action' value='importExport'>
        <input type='hidden' name='subaction' value='importJSON'>
        <input type='hidden' name='MAX_FILE_SIZE' value='100000'>
        <div>
          <label>Import from:</label>
          <div class='group file-area'>
            <input type='file' name='userfile' required>
            <div class='file-overlay'>
              <div class='default'>Drag&hairsp;&amp;&hairsp;Drop file here or <span class='pseudo-button'>Browse Files</span></div>
              <div class='success'>Your file is selected.</div>
            </div>
          </div>
        </div>
        <div>
          <label></label>
          <input type='submit' value=' Import Playlist '>
        </div>
        <div>
          <label></label>
          <div class='user-tip sub' style='display: inline-block'>
            <p>File must be a UTF-8 encoded JSON playlist,
            such as previously exported via Export Playlist.</p>
          </div>
        </div>
      </form>
    <?php
        }
    }

    public function updateDJInfo() {
        $subactions = [
            [ "u", "", "Update Airname", "updateAirname" ],
            [ "u", "manageKeys", "Manage API Keys", "manageKeys" ],
        ];
        $this->dispatchSubaction($this->action, $this->subaction, $subactions);
    }

    public function manageKeys() {
        $api = Engine::api(IUser::class);
        if($_POST["newKey"]) {
            $newKey = sha1(uniqid(rand()));
            $api->addAPIKey($this->session->getUser(), $newKey);
        } else if($_POST["deleteKey"]) {
            $selKeys = [];
            foreach($_POST as $key => $value) {
                if(substr($key, 0, 2) == "id" && $value == "on")
                    $selKeys[] = substr($key, 2);
            }
            if(sizeof($selKeys))
                $api->deleteAPIKeys($this->session->getUser(), $selKeys);
        }
    ?>
       <div class='user-tip' style='display: block; max-width: 550px;'>
       <p>API Keys allow external applications access to your playlists
       and other personal details.</p><p>Generate and share an API Key only
       if you trust the external application.</p>
       </div>
    <?php
        $keys = $api->getAPIKeys($this->session->getUser())->asArray();
        echo "<form action='?' method=post>\n";
        if(sizeof($keys)) {
    ?>
       <p><b>Your API Keys:</b></p>
       <table border=0>
       <tr><th><input name=all id='all' type=checkbox></th><th align=right>API Key</th><th></th></tr>
   <?php
            foreach($keys as $key) {
                echo "<tr><td><input name=id{$key['id']} type=checkbox></td>".
                     "<td class='apikey'>{$key['apikey']}</td>".
                     "<td><a href='#' title='Copy Key to Clipboard' class='copy'>&#x1f4cb;</a></td></tr>\n";
            }
            echo "</table>\n";
            echo "<p><input type=submit class=submit name=deleteKey value=' Remove Key '>&nbsp;&nbsp;&nbsp;\n";
        } else
            echo "<p><b>You have no API Keys.</b></p><p>\n";

        echo "<input type=submit class=submit name=newKey value=' Generate New Key '></p>\n";
        echo "<input type=hidden name=action value='{$this->action}'>\n";
        echo "<input type=hidden name=subaction value='{$this->subaction}'>\n";
        echo "</form>\n";
        UI::emitJS('js/user.apikey.js');
    }

    public function updateAirname() {
        UI::emitJS("js/playlists.pick.js");

        $validate = $_POST["validate"];
        $multi = $_REQUEST["multi"];
        $url = $_REQUEST["url"];
        $email = $_REQUEST["email"];
        $airname = $_REQUEST["airname"];
        $name = trim($_REQUEST["name"]);
    
        if($validate && $airname) {
            // Update DJ info
            $success = Engine::api(IDJ::class)->updateAirname($name,
                     $this->session->getUser(), $url, $email,
                     $multi?0:$airname);
            if($success) {
                echo "<B>Your airname has been updated.</B>\n";
                return;
            } else
                echo "<B><FONT CLASS=\"error\">'$name' is invalid or already exists.</FONT></B>";
            // fall through...
        }
        $airnames = Engine::api(IDJ::class)->getAirnames(
                     $this->session->getUser(), $airname)->asArray();

        switch(sizeof($airnames)) {
        case 0:
            // No airnames
    ?>
    <P><B><FONT CLASS="error">You have no airnames</FONT></B></P>
    <P>Publish at least one playlist or music review to create
       an airname.</P>
    <?php 
            break;
        case 1:
            // Only one airname; emit form
    ?>
    <FORM id="update-airname" ACTION="?" METHOD=POST>
    <P><B>Update airname '<?php echo $airnames[0]['airname'];?>'</B></P>
    <TABLE CELLPADDING=2 BORDER=0>
      <TR><TD ALIGN=RIGHT>Airname:</TD>
        <TD><INPUT id='name' TYPE=TEXT NAME=name required VALUE="<?php echo $name?$name:$airnames[0]['airname'];?>" CLASS=input MAXLENGTH=<?php echo IDJ::MAX_AIRNAME_LENGTH . ($name?" data-focus":"");?> SIZE=40></TD></TR>
      <TR><TD ALIGN=RIGHT>URL:</TD>
        <TD><INPUT TYPE=TEXT NAME=url VALUE="<?php echo $url?$url:$airnames[0]['url'];?>" CLASS=input SIZE=40 MAXLENGTH=80<?php echo $name?"":" data-focus"; ?>></TD></TR>
      <TR><TD ALIGN=RIGHT>e-mail:</TD>
        <TD><INPUT TYPE=TEXT NAME=email VALUE="<?php echo $email?$email:$airnames[0]['email'];?>" CLASS=input SIZE=40 MAXLENGTH=80></TD></TR>
    <?php 
            // Suppress the account update option for local-only accounts,
            // as they tend to be shared.
            if($multi && !$this->session->isAuth("g"))
                echo "  <TR><TD>&nbsp</TD><TD><INPUT id='multi' TYPE=CHECKBOX NAME=multi>&nbsp;Check here to apply the URL and e-mail to all of your DJ airnames</TD></TR>";
    ?>
      <TR><TD COLSPAN=2>&nbsp;</TD></TR>
      <TR><TD>&nbsp;</TD><TD><INPUT TYPE=SUBMIT VALUE="  Update  ">
              <INPUT TYPE=HIDDEN NAME=airname VALUE="<?php echo $airnames[0]['id'];?>">
              <INPUT TYPE=HIDDEN id='oldname' VALUE="<?php echo $airnames[0]['airname'];?>">
              <INPUT TYPE=HIDDEN NAME=action VALUE="updateDJInfo">
              <INPUT TYPE=HIDDEN NAME=validate VALUE="y"></TD></TR>
    </TABLE>
    </FORM>
    <?php 
            break;
        default:
            // Multiple airnames; emit airname selection form
    ?>
    <FORM ACTION="?" METHOD=POST>
    <B>Select Airname:</B><BR>
    <TABLE CELLPADDING=0 BORDER=0><TR><TD>
    <SELECT NAME=airname SIZE=10 data-focus>
    <?php 
            foreach($airnames as $row) {
                 echo "  <OPTION VALUE=\"$row[0]\">$row[1]\n";
            }
    ?>
    </SELECT></TD></TR>
    <TR><TD>
    <INPUT TYPE=SUBMIT VALUE=" Next &gt;&gt; ">
    <INPUT TYPE=HIDDEN NAME=action VALUE="updateDJInfo">
    <INPUT TYPE=HIDDEN NAME=multi VALUE="y">
    </TD></TR></TABLE>
    </FORM>
    <?php 
            break;
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
                      "&amp;q=". $maxresults.
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
        $seq = $_REQUEST["seq"];
        $viewuser = $_REQUEST["viewuser"];
        $playlist = $_REQUEST["playlist"];
    
        settype($playlist, "integer");
        settype($viewuser, "integer");
    
        if(((($seq == "selUser") && $viewuser)) ||
                (($seq == "selList") && !$playlist)) {
            $results = Engine::api(IDJ::class)->getAirnames(0, $viewuser);
            if($results)
                $row = $results->fetch();
    ?>
    <FORM ACTION="?" METHOD=POST>
    <TABLE WIDTH="100%"><TR><TD ALIGN=RIGHT VALIGN=TOP>
    <?php 
            // Emit optional URL and/or e-mail for DJ
            if($row['url']) {
                echo "      <A HREF=\"".$row['url']."\" CLASS=\"nav\"><B>Go to ".$row['airname']."'s website</B></A>\n";
                if($row['email'])
                    echo "      &nbsp; | &nbsp;\n";
            }
            if($row['email']) {
                echo "      <A HREF=\"mailto:".$row['email']."\" CLASS=\"nav\"><B>e-mail ".$row['airname']."</B></A>\n";
            }
    ?>
    </TD></TR></TABLE>
    <TABLE WIDTH="100%" CELLSPACING=0>
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
            echo "    <TABLE WIDTH=\"100%\" CELLSPACING=0 BORDER=0>\n";
            if(sizeof($recentPlays)) {
                echo "<TR><TH COLSPAN=2 ALIGN=LEFT CLASS=\"subhead\">&nbsp;${blname}Recent airplay</TH></TR>";
                $this->emitViewDJAlbum($recentPlays, $block?" CLASS=\"sub\"":"");
            }
    ?>
        </TABLE>
      </TD><?php 
        if(sizeof($recentReviews)) {
            echo "<TD>&nbsp;&nbsp;&nbsp;</TD><TD CLASS=\"recentReviews\"VALIGN=TOP>\n";
            $block = sizeof($recentPlays);
            $blname = (sizeof($topPlays) || sizeof($recentPlays))?"":$row['airname'] . "'s ";
            echo "    <TABLE WIDTH=\"100%\" BORDER=0 CELLSPACING=0>\n";
    
            echo "      <TR><TH COLSPAN=2 ALIGN=LEFT CLASS=\"subhead\">&nbsp;${blname}Recent reviews</TH></TR>\n";
            $this->emitViewDJAlbum($recentReviews, $block?" CLASS=\"sub\"":"", 0, "name");
            if(sizeof($recentReviews) == $count - 1)
                echo "  <TR><TD></TD><TD ALIGN=LEFT CLASS=\"sub\"><A HREF=\"?s=byReviewer&amp;n=$viewuser&amp;p=0&amp;q=50&amp;action=viewDJReviews\" CLASS=\"nav\">More reviews...</A></TD></TR>\n";
            echo "    </TABLE></TD>\n";
        }
    
    ?>
      </TR></TABLE>
    <?php if (sizeof($topPlays) || sizeof($recentPlays) || sizeof($recentReviews)) echo "<BR>\n"; ?>
    <TABLE WIDTH="100%">
      <TR><TH ALIGN=LEFT><?php echo $row['airname'];?>'s playlists:</TH></TR>
      <TR><TD>
         <SELECT NAME=playlist SIZE=6 data-focus>
    <?php 
            // Run the query
            $records = Engine::api(IPlaylist::class)->getPlaylists(0, 0, 0, $viewuser);
            while($row = $records->fetch())
                echo "        <OPTION VALUE=\"$row[0]\">$row[1] -- ".htmlentities($row[3], ENT_QUOTES, 'UTF-8')."\n";
    ?>
        </SELECT></TD></TR>
      <TR><TD>
        <INPUT TYPE=SUBMIT VALUE=" View Playlist ">
        <INPUT TYPE=HIDDEN NAME=viewuser VALUE="<?php echo $viewuser;?>">
        <INPUT TYPE=HIDDEN NAME=action VALUE="viewDJ">
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
    
        $subactions = [
            [ "a", "", "DJs active past 12 weeks", "emitViewDJMain" ],
            [ "a", "viewAll", "All DJs", "emitViewDJMain" ]
        ];
        $this->dispatchSubaction($this->action, $this->subaction, $subactions);
    }
    
    public function emitViewDJMain() {
    ?>
    <TABLE CELLPADDING=2 CELLSPACING=2 BORDER=0 CLASS="djzone">
      <!--TR><TH COLSPAN=2 ALIGN=LEFT>Select a DJ:</TH></TR-->
      <TR><TH COLSPAN=2 ALIGN=LEFT><?php 
        $last = "";
        $dot = 0;
        for($i=0; $i<26; $i++)
            echo "<A HREF=\"#" . chr($i+65) . "\">" . chr($i+65) . "</A>&nbsp;&nbsp;";
        echo "</TH></TR>\n  <TR><TD COLSPAN=2>";
    
        // Run the query
        $records = Engine::api(IDJ::class)->getActiveAirnames($this->subaction == "viewAll");
        $i = 0;
        while($records && ($row = $records->fetch())) {
            $row["sort"] = preg_match("/^the /i", $row[1])?substr($row[1], 4):$row[1];
            // sort symbols beyond Z with the numerics and other special chars
            if(UI::deLatin1ify(mb_strtoupper(mb_substr($row["sort"], 0, 1))) > "Z")
                $row["sort"] = "@".$row["sort"];

            $dj[$i++] = $row;
        }
    
        if(isset($dj))
            usort($dj, function($a, $b) {
                return strcasecmp($a["sort"], $b["sort"]);
            });
    
        for($j = 0; $j < $i; $j++) {
            $row = $dj[$j];
            $cur = UI::deLatin1ify(mb_strtoupper(mb_substr($row["sort"], 0, 1)));
            if($cur < "A") $cur = "#";
            if($cur != $last) {
                $last = $cur;
                echo "</TD></TR>\n  <TR><TD COLSPAN=2>&nbsp;</TD></TR>\n  <TR><TH VALIGN=TOP><A NAME=\"$last\">$last</A>&nbsp;&nbsp;</TH>\n      <TD VALIGN=TOP>";
                $dot = 0;
            }
    
            if($dot)
                echo "&nbsp;&nbsp;&#8226;&nbsp; ";
            else
                $dot = 1;
            
            echo "<A CLASS=\"nav\" HREF=\"".
                 "?action=viewDJ&amp;seq=selUser&amp;viewuser=$row[0]\">";
    
            $displayName = str_replace(" ", "&nbsp;", htmlentities($row[1]));
            echo "$displayName</A>";
        }
        echo "</TD></TR>\n</TABLE>\n";
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
        echo "<b>Playlist Date:&nbsp;</b>";
        echo "<input id='playlist-start-date' type='hidden' value='${startDate}' />";
        echo "<input id='playlist-date'/>";
        echo "<input id='playlist-datepicker' readonly='true' type='hidden' autocomplete='off' />";
        echo "<img id='playlist-calendar' src='img/calendar-icon.svg'></img>";
        echo "<table id='playlist-list'>";
        echo "<thead><tr><th textalign='left' colspan='2' class='subhead'></th></tr></thead>";
        echo "<tbody></tbody></table>";
        UI::emitJS('js/playlists.view.js');
    }

    public function handlePlaylistsByDate() {
        $viewdate = $_REQUEST["viewdate"];
        $records = Engine::api(IPlaylist::class)->getPlaylists(1, 1, $viewdate, 0, 0, 0, 20);
        $tbody = '';
        $count = 0;
        $href = '?action=viewListById&playlist';
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

    public function viewLastPlays($tag, $count=0) {
        $plays = Engine::api(IPlaylist::class)->getLastPlays($tag, $count);
        if($plays) {
            echo "<DIV class='playlistBanner'>&nbsp;Recent Airplay</DIV>";
    
            echo "<TABLE class='recentAirplay' CELLPADDING=2 CELLSPACING=0 BORDER=0>\n";
    
            // Setup date format based on locale
            $dateSpec = self::isUsLocale() ? 'M d, Y' : 'd M Y';
    
            // Ensure we have an even number of plays
            if(sizeof($plays)%2)
                $plays[] = ["description" => ""];
     
            $mid = sizeof($plays)/2;
            for($i=0; $i < sizeof($plays); $i++) {
                if($i%2 == 0) {
                    echo "<TR>";
                    $idx = ($i+2)/2 - 1;
                } else {
                    $idx = $mid + ($i+1)/2 - 1;
                }
                $play = $plays[$idx];
    
                if($play["description"]) {
                    $showDate = date($dateSpec, strtotime($play["showdate"]));
                    $showLink = "<A HREF='".
                         "?action=viewDJ&amp;playlist=".$play["id"].
                         "&amp;seq=selList'>".$play["description"]."</A>";

                    $trackList = implode(", ", $play["tracks"]);    
                    $playNum = $idx + 1;
                    echo "<TD>$playNum.</TD>";
                    echo "<TD class='date' style='min-width:80px'>$showDate:</TD>";
                    echo "<TD>$showLink  <BR> $trackList";
                } else
                    echo "<TD COLSPAN=3></TD>";
                if($i%2)
                    echo "</TR>\n";
                else
                    echo "<TD WIDTH=20></TD>\n";
            }
            echo "</TABLE><BR>\n";
        }
    }
}

