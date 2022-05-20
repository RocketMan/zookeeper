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

use VStelmakh\UrlHighlight\UrlHighlight;


class Playlists extends MenuItem {
    private const NME_PREFIX = "nme-";

    //NOTE: update ui_config.php when changing the actions.
    private static $actions = [
        [ "viewList", "emitPlaylistPicker" ],
        [ "viewListById", "emitViewPlaylist" ],
        [ "viewListDaysByDate", "handlePlaylistDaysByDate" ],
        [ "viewListsByDate", "handlePlaylistsByDate" ],
        [ "newList", "emitEditList" ],
        [ "newListEditor", "emitEditor" ],
        [ "newListPost", "handleListPost" ],
        [ "editList", "emitEditListPicker" ],
        [ "editListDelete", "handleDeleteListPost" ],
        [ "editListDetails", "emitEditList" ],
        [ "editListDetailsPost", "handleListPost" ],
        [ "editListEditor", "emitEditor" ],
        [ "editListRestore", "handleRestoreListPost" ],
        [ "importExport", "emitImportExportList" ],
        [ "showLink", "emitShowLink" ],
        [ "viewDJ", "emitViewDJ" ],
        [ "viewDJReviews", "viewDJReviews" ],
        [ "updateDJInfo", "updateDJInfo" ],
        [ "addTrack", "handleAddTrack" ],
        [ "moveTrack", "handleMoveTrack" ],
    ];

    private $action;
    private $subaction;
    private $urlHighlighter;

    public function processLocal($action, $subaction) {
        $this->action = $action;
        $this->subaction = $subaction;
        return $this->dispatchAction($action, self::$actions);
    }
    
    private function smartURL($name, $detect=true) {
        $name = htmlentities($name);

        if($detect) {
            if(!isset($this->urlHighlighter))
                $this->urlHighlighter = new UrlHighlight();

            $name = $this->urlHighlighter->highlightUrls($name);
        }

        return $name;
    }
    
    private function extractTime($time, &$fromTime, &$toTime) {
        if(strlen($time) == 9 && $time[4] == '-') {
            $fromTime = substr($time, 0, 4);
            $toTime = substr($time, 5, 4);
            return true;
        } else if(!strlen($time)) {
            $fromTime = "0000";
            $toTime = "0000";
            return true;
        } else
            return false;
    }
    
    // split ZK time range into an array of start/end ISO times.
    private function zkTimeRangeToISOTimeAr($zkTimeRange) {
        $retVal = ['',''];
        $timeAr = explode("-", $zkTimeRange);
        if (count($timeAr) == 2) {
            $retVal[0] = substr($timeAr[0], 0, 2) . ':' . substr($timeAr[0], 2,4);
            $retVal[1] = substr($timeAr[1], 0, 2) . ':' . substr($timeAr[1], 2,4);
        }
        
        return $retVal;
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
 
    // return time portion of YYYY-MM-DD HH:MM:SS as HH:MM for use in HTML
    // timepicker.
    private function getTimepickerTime($isoTime) {
        $ISO_TIME_LENGTH = 19;
        $retVal = trim($isoTime);
        if (strlen($retVal) == $ISO_TIME_LENGTH)
            $retVal = substr($isoTime, 11, 5);

        return $retVal;
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
    
    // add track from an ajax post from client. return new track row
    // upon success else 400 response.
    public function handleAddTrack() {
        $retVal = [];
        $playlistId = trim($_POST["playlist"]);
        $playlistApi = Engine::api(IPlaylist::class);
        $playlist = $playlistApi->getPlaylist($playlistId, 1);

        if(!$playlist || $playlist['dj'] != $this->session->getUser()) {
            $retVal['status'] = 'access error';
            http_response_code(400);
            echo json_encode($retVal);
            return;
        }

        $isLiveShow = $playlistApi->isNowWithinShow($playlist);

        $type = $_REQUEST["type"];
        $size = $_REQUEST["size"];
        if(isset($size) && $playlistId) {
            $count = $playlistApi->getTrackCount($playlistId);
            // if size matches count, set to 0 (in sync) else -1 (out of sync)
            $size = $count == $size?0:-1;
        }

        $spinDateTime = null;
        $spinTime = $_REQUEST["time"]; // optional paramter, may be empty
        $spinDate = $_REQUEST["date"];
        if ($spinTime != '')
            $spinDateTime = new \DateTime("${spinDate} ${spinTime}");

        $entry = null;
        switch($type) {
        case PlaylistEntry::TYPE_SET_SEPARATOR:
            $entry = (new PlaylistEntry())->setSetSeparator();
            break;
        case PlaylistEntry::TYPE_COMMENT:
            $entry = (new PlaylistEntry())->setComment(mb_substr(trim(str_replace("\r\n", "\n", $_REQUEST["comment"])), 0, PlaylistEntry::MAX_COMMENT_LENGTH));
            break;
        case PlaylistEntry::TYPE_LOG_EVENT:
            $entry = (new PlaylistEntry())->setLogEvent(
                $_REQUEST["eventType"], trim($_REQUEST["eventCode"]));
            break;
        default:
            // spin
            $artist = trim($_REQUEST["artist"]);
            $track = trim($_REQUEST["track"]);
            if (empty($playlistId) || empty($artist) || empty($track)) {
                $retMsg = "required field missing: -" . $playlistId . "-, -" . $artist . "-, -" . $track . "-";
            } else {
                // set the review flag for PlaylistObserver
                if($_REQUEST["tag"])
                    Engine::api(ILibrary::class)->markAlbumsReviewed($_=[&$_REQUEST]);
                $entry = new PlaylistEntry($_REQUEST);
            }
            break;
        }

        if($entry) {
            $retMsg = 'success';
            $id = '';
            $status = '';
            if ($isLiveShow) {
                $spinDateTime = new \DateTime("now");
            }

            if ($spinDateTime != null)
                $entry->setCreated($spinDateTime->format(IPlaylist::TIME_FORMAT_SQL));

            $updateStatus = $playlistApi->insertTrackEntry($playlistId, $entry, $status);

            if (!$updateStatus) {
                $retMsg = $status == '' ? "DB update error" : $status;
            } else {
                // JM 2019-08-15 action and id need to be set
                // for hyperlinks genereated by makePlaylistObserver (#54)
                $this->action = $_REQUEST["oaction"];
                ob_start();
                $this->makePlaylistObserver($playlistId, true)->observe($entry);
                $newRow = ob_get_contents();
                ob_end_clean();

                $retVal['row'] = $newRow;
                // seq is one of:
                //   -1     client playlist is out of sync with the service
                //   0      playlist is in natural order
                //   > 0    ordinal of inserted entry
                $retVal['seq'] = $size ? $size : $playlistApi->getSeq(0, $entry->getId());

                if($isLiveShow && $playlist['airname']) {
                    $playlist['id'] = $playlistId;
                    if($type == PlaylistEntry::TYPE_SPIN) {
                        $spin = $entry->asArray();
                        $spin['artist'] = PlaylistEntry::swapNames($spin['artist']);
                    } else
                        $spin = null;
                    PushServer::sendAsyncNotification($playlist, $spin);

                    // track is in the grace period?
                    $window = $playlistApi->getTimestampWindow($playlistId, false);
                    $retVal['runsover'] = $spinDateTime >= $window['end'];
                } else if(!$isLiveShow)
                    $this->lazyLoadImages($playlistId, $entry->getId());
            }
        } else
            $updateStatus = 0; //failure

        $retVal['status'] = $retMsg;
        http_response_code($updateStatus == 0 ? 400 : 200);
        echo json_encode($retVal);
    }

    public function handleMoveTrack() {
        $retVal = [];
        $list = $_POST["playlist"];
        $from = $_REQUEST["fromId"];
        $to = $_REQUEST["toId"];

        if(!isset($list) || !$this->isOwner($list)) {
            $retVal['status'] = 'access error';
            http_response_code(400);
            echo json_encode($retVal);
            return;
        }

        if($list && $from && $to && $from != $to) {
            $success = Engine::api(IPlaylist::class)->moveTrack($list, $from, $to);
            $retMsg = $success?"success":"DB update error";
        } else {
            $success = false;
            $retMsg = "invalid request";
        }

        $retVal['status'] = $retMsg;
        http_response_code($success?200:400);
        echo json_encode($retVal);
    }

    public static function hourToAMPM($hour, $full=0) {
        // account for legacy, free-format time encoding
        if(!is_numeric($hour))
            return $hour;

        $h = (int)floor($hour/100);
        $m = (int)$hour % 100;
        $min = $m || $full?(":" . sprintf("%02d", $m)):"";
    
        switch($h) {
        case 0:
            return $m?($h . $min . "am"):"midnight";
        case 12:
            return $m?($h . $min . "pm"):"noon";
        default:
            if($h < 12)
                return $h . $min . "am";
            else
                return ($h - 12) . $min . "pm";
        }
    }
    
    public static function timeToAMPM($time) {
        if(strlen($time) == 9 && $time[4] == '-') {
            list($fromtime, $totime) = explode("-", $time);
            return self::hourToAMPM($fromtime) . " - " . self::hourToAMPM($totime);
        } else
            return strtolower(htmlentities($time));
    }

    public static function timestampToDate($time) {
        if ($time == null || $time == '') {
            return "";
        } else {
            return date('D M d, Y ', strtotime($time));
        }
    }

    public static function timestampToAMPM($time) {
        if ($time == null || $time == '') {
            return "";
        } else {
            return date('h:i a', strtotime($time));
        }
    }
    
    public static function timeToZulu($time) {
        if(strlen($time) == 9 && $time[4] == '-') {
            $d = getdate(time());
            $day = $d["mday"];
            $month = $d["mon"];
            $year = $d["year"];
            list($fromtime, $totime) = explode("-", $time);
            $starttime = mktime(substr($fromtime, 0, 2), substr($fromtime, 2, 2), 0, $month, $day, $year);
            $z = date("Z");
            $zday = date("j", date("U", $starttime)-$z);
    
            $result = "<TH ALIGN=RIGHT VALIGN=BOTTOM CLASS=\"sub\">";
            if($zday != $day)
                $result .=  "(" . date("j M", date("U")-$z);
            else
                $openParen = "(";
            $result .= "&nbsp;&nbsp;</TH>\n      <TH ALIGN=LEFT VALIGN=BOTTOM CLASS=\"sub\">$openParen";
            $result .= date("Hi", $starttime-$z) . " - ";
            $result .= date("Hi", mktime(substr($totime, 0, 2), substr($totime, 2, 2), 0, $month, $day, $year)-$z);
            $result .= "&nbsp;UTC)</TH>";
            return $result;
        } else
            return "";
    }
    
    public function viewDJReviews() {
        $this->newEntity(Search::class)->doSearch();
    }
    
    // emits the html & javascript to edit & create a new playlist.
    // TODO: show error message if post was incorrect
    private function emitEditListForm($airName, $description, $zkTimeRange, $date, $playlistId, $errorMsg) {
        $isoDate = $date ? str_replace("/", "-", $date) : '';
        $isoTimeAr = $this->zkTimeRangeToISOTimeAr($zkTimeRange);
        $airNames = $this->getDJAirNames();
        $foreignAirname = !empty($airName) && !preg_match("/\'$airName\'/", $airNames);
        $duplicate = isset($_POST["duplicate"]) && $_POST["duplicate"];
        $userAction = $duplicate ? "Create Duplicate " : ($playlistId ? "Edit " : "Create ");
        ?>

        <DIV CLASS='playlistBanner'>&nbsp; <?php echo $userAction;?> Playlist</DIV>
        <span class='error'><?php echo $errorMsg; ?></span>

        <FORM id='new-show' class='pl-form-entry' ACTION="?" METHOD=POST>
            <div>
                <label>Show Name:</label>
                <input id='show-description' required name='description' size=30 maxlength=<?php echo IPlaylist::MAX_DESCRIPTION_LENGTH;?> value="<?php echo htmlentities(stripslashes($description));?>" data-focus/>
            </div>
            <div>
                <label>Show Date:</label>
                <INPUT id='show-date-picker' required type='date' value="<?php echo $duplicate?"":$isoDate;?>" />
            </div>
            <div>
                <label>Start Time:</label>
                <INPUT id='show-start' class='timepicker' step='60' required type='time' value="<?php echo $duplicate?"":$isoTimeAr[0]; ?>" NAME='fromtime' />
            </div>
            <div>
                <label>End Time:</label>
                <INPUT id='show-end' step='60' class='timepicker' required type='time' value="<?php echo $duplicate?"":$isoTimeAr[1]; ?>" NAME='totime' />
            </div>
            <div>
                <label>Air Name:</label>
                <INPUT id='show-airname' TYPE='text' LIST='airnames' NAME='airname' required autocomplete="off" maxlength=<?php echo IDJ::MAX_AIRNAME_LENGTH;?> VALUE='<?php
                      echo (!is_null($airName)?
                              $airName:($description?"None":"")) . "'";

                      // disable airname field when reparenting
                      //
                      // we could use 'readonly', but it doesn't grey out
                      // and is still focusable.  'disabled' gives what
                      // we want, but as it does not submit its value, we
                      // include the airname in an extra hidden field.
                      if($foreignAirname)
                          echo " disabled /><INPUT name='airname' type='hidden' value='$airName'"; ?> />
                <DATALIST id='airnames'>
                  <?php
                      // if a vaultkeeper has reparented a playlist,
                      // allow it to keep the existing airname
                      if($foreignAirname)
                          echo "<OPTION VALUE='$airName'>";
                      echo $airNames; ?>
                </DATALIST>
            </div>
            <div>
                <label></label>
                <INPUT id='edit-submit-but' TYPE=SUBMIT NAME=button VALUE="Create">
            </div>

            <INPUT id='show-date' TYPE=HIDDEN NAME='date' VALUE="">
            <?php
                if($duplicate)
                    echo "<INPUT TYPE=HIDDEN NAME=duplicate id='duplicate' VALUE=1>\n";
                // if action does not already end in 'Post', append it
                $suffix = substr_compare($this->action, "Post", -4)?"Post":"";
            ?>
            <INPUT TYPE=HIDDEN NAME=action VALUE="<?php echo $this->action.$suffix; ?>">
            <INPUT id='playlist-id' TYPE=HIDDEN NAME=playlist VALUE="<?php echo $playlistId;?>">
            <INPUT id='timezone-offset' type=hidden value='<?php echo round(date('Z')/-60, 2); /* server TZ equivalent of javascript now.getTimezoneOffset() */ ?>'>
        </FORM>

    <?php
        UI::emitJS('js/playlists.info.js');
    }
    
    // handles post for playlist creation and edit
    public function handleListPost() {
        $description = mb_substr(trim($_POST["description"]), 0, IPlaylist::MAX_DESCRIPTION_LENGTH);
        $date = $_REQUEST["date"];
        $fromtime = substr($_REQUEST["fromtime"], 0, 5);
        $totime   = substr($_REQUEST["totime"], 0, 5);
        $showTime = $this->composeTime($fromtime, $totime);
        list($year, $month, $day) = explode("-", $date);

        $goodDate = checkdate($month, $day, $year);
        $goodTime = $showTime !== '';
        $goodDescription = $description !== '';

        $airname = mb_substr(trim($_REQUEST["airname"]), 0, IDJ::MAX_AIRNAME_LENGTH);
        $goodAirname = $airname !== '';

        if($goodDate && $goodTime && $goodDescription && $goodAirname) {
            $playlistId = $_REQUEST["playlist"];
            $update = isset($playlistId) && $playlistId > 0;
            $duplicate = isset($_POST["duplicate"]) && $_POST["duplicate"];
            if($update &&
                    !($duplicate && $this->session->isAuth("v")) &&
                    !$this->isOwner($playlistId)) {
                $this->emitEditListError('access error');
                return;
            }

            $api = Engine::api(IPlaylist::class);

            // if this DJ already has a live playlist in-progress,
            // rejoin it rather than creating a new live playlist
            if(!$update && !$this->session->isAuth("v") && $api->isNowWithinShow(
                    ["showdate" => $date, "showtime" => $showTime], false)) {
                $onnow = $api->getWhatsOnNow()->fetch();
                if($onnow && $onnow['dj'] == $this->session->getUser()) {
                    echo "<SCRIPT TYPE=\"text/javascript\"><!--\n".
                         "\$().ready(function(){".
                         "location.href='?action=newListEditor&playlist=".
                         $onnow['id']."';});\n".
                         "// -->\n</SCRIPT>\n";
                    return;
                }
            }

            // process the airname
            if(!strcasecmp($airname, "None")) {
                // unpublished playlist
                $aid = 0;
            } else {
                // if airname is unchanged, use it as-is
                //
                // this allows a vaultkeeper who has reparented another
                // user's playlist to keep the existing airname on the list
                if($update) {
                    $playlist = $api->getPlaylist($playlistId, 1);
                    if($playlist["airname"] == $airname)
                        $aid = $playlist["id"];
                }

                // lookup airname for this DJ
                $djapi = Engine::api(IDJ::class);
                if(!isset($aid))
                    $aid = $djapi->getAirname($airname, $this->session->getUser());
                if(!$aid) {
                    // airname does not exist; try to create it
                    $success = $djapi->insertAirname($airname, $this->session->getUser());
                    if($success > 0) {
                        // success!
                        $aid = $djapi->lastInsertId();
                    } else {
                        // airname creation failed
                        // alert the user and re-display the form
                        $errorMessage = "'$airname' is invalid or already exists.";
                        $this->emitEditListError($errorMessage);
                        return;
                    }
                }
            }

            if($update) {
                if($duplicate) {
                    $playlistId = $_REQUEST["playlist"] = $api->duplicatePlaylist($playlistId);
                    $playlist = $api->getPlaylist($playlistId, 1);
                    if($this->session->isAuth("v") && $this->session->getUser() != $playlist["dj"])
                        $api->reparentPlaylist($playlistId, $this->session->getUser());
                }

                // update existing playlist
                $success = $api->updatePlaylist(
                        $playlistId, $date, $showTime, $description, $aid, $duplicate);

                if($success)
                    $this->lazyLoadImages($playlistId);

                $action = "editListEditor";
            } else {
                // create new playlist
                $success = $api->insertPlaylist(
                         $this->session->getUser(),
                         $date, $showTime, $description, $aid);

                $playlistId = $success?$api->lastInsertId():0;
                $action = "newListEditor";
            }

            if($success) {
                PushServer::sendAsyncNotification();

                // force browser nav to new/edit playlist so subsequent
                // reloads in the track editor work as expected
                echo "<SCRIPT TYPE=\"text/javascript\"><!--\n".
                     "\$().ready(function(){".
                     "location.href='?action=$action&playlist=".
                     $playlistId."';});\n".
                     "// -->\n</SCRIPT>\n";
                return;
            } else {
                $this->emitEditListError("Internal error.  Try again.");
            }
        } else {
            $errMsg = "Missing field";
            if ($goodDate == false)
                $errMsg = "Invalid date " . $date;
            else if ($goodTime == false)
                $errMsg = "Invalid time range (min 1/4 hour, max 6 hours) " . $fromtime . " - " . $totime;

            $this->emitEditListError($errMsg);
        }
    }

    // emitEditList functionality if there are errors
    protected function emitEditListError($errMsg="") {
        $description = $_REQUEST["description"];
        $date = $_REQUEST["date"];
        $airname = $_REQUEST["airname"];
        $playlistId = $_REQUEST["playlist"];
        $button = $_REQUEST["button"];
        $fromtime = substr($_REQUEST["fromtime"], 0, 5);
        $totime   = substr($_REQUEST["totime"], 0, 5);
        $showTime = $this->composeTime($fromtime, $totime);

        if($errMsg)
            $errMsg = "<B><FONT CLASS='error'>$errMsg</FONT></B>";

        $this->emitEditListForm($airname, $description, $showTime, $date, $playlistId, $errMsg);
    }

    private function getDJAirNames() {
        $airNames = '';
        $records = Engine::api(IDJ::class)->getAirnames($this->session->getUser());
        while ($records && ($row = $records->fetch())) {
           $newItem = "<OPTION VALUE='".$row['airname']."'>";
           $airNames .= $newItem;
        }

        $airNames .=  "<OPTION VALUE='None'>";
        return $airNames."\n";
    }

    // build the form used to add/modify playlist meta data
    public function emitEditList() {
        $playlistId = $_REQUEST["playlist"];
        $description = '';
        $date = '';
        $time = '';
        $airName = '';

        $sourcePlaylist = null;
        $api = Engine::api(IPlaylist::class);
        if(isset($playlistId) && $playlistId > 0) {
            $sourcePlaylist = $api->getPlaylist($playlistId, 1);
        } else {
            $WEEK_SECONDS = 60 *60 * 24 * 7;
            $nowDateStr =  (new \DateTime())->format("Y-m-d");
            $nowDateTimestamp =  (new \DateTime($nowDateStr))->getTimestamp();

            // see if there is a PL on this day last week. if so use it.
            $playlists = $api->getPlaylists(1, 1, "", 1, $this->session->getUser(), 1, 10);
            $djapi = Engine::api(IDJ::class);
            while ($playlists && ($playlist = $playlists->fetch())) {
                // skip duplicated lists with foreign airnames
                $aid = $djapi->getAirname($playlist['airname'], $this->session->getUser());
                if(!$aid)
                    continue;
                $showDate = new \DateTime($playlist['showdate']);
                $dateInterval = $nowDateTimestamp - $showDate->getTimestamp();
                if ($dateInterval == $WEEK_SECONDS) {
                    $sourcePlaylist = $playlist;
                    break;
                }
            }
        }

        if ($sourcePlaylist) {
            $description = $sourcePlaylist['description'];
            $date = $sourcePlaylist['showdate'];
            $time = $sourcePlaylist['showtime'];
            $airName = $sourcePlaylist['airname'];

            if(isset($_POST["duplicate"]) && $_POST["duplicate"]) {
                $suffix = preg_replace_callback("/%([^%]*)%/",
                    function($matches) use ($date) {
                        return \DateTime::createFromFormat(
                            IPlaylist::TIME_FORMAT,
                            $date . " 0000")->format($matches[1]);
                    }, IPlaylist::DUPLICATE_SUFFIX);
                if(mb_strlen($description) + mb_strlen($suffix) > IPlaylist::MAX_DESCRIPTION_LENGTH)
                    $description = mb_substr($description, 0, IPlaylist::MAX_DESCRIPTION_LENGTH - mb_strlen($suffix) - 3) . "...";
                $description .= $suffix;
            }
        }

        $this->emitEditListForm($airName, $description, $time, $date, $playlistId, null);
    }

    private function restorePlaylist($playlist) {
        Engine::api(IPlaylist::class)->restorePlaylist($playlist);
    }
    
    private function getDeletedPlaylistCount() {
        return Engine::api(IPlaylist::class)->getDeletedPlaylistCount($this->session->getUser());
    }

    public function handleDeleteListPost() {
        $playlistId = $_POST["playlist"];
        if(isset($playlistId) && $this->isOwner($playlistId)) {
            Engine::api(IPlaylist::class)->deletePlaylist($playlistId);
            PushServer::sendAsyncNotification();
        }

        $this->emitEditListPicker();
    }

    public function handleRestoreListPost() {
        $playlistId = $_POST["playlist"];
        if(isset($playlistId) && $this->isOwner($playlistId)) {
            $this->restorePlaylist($playlistId);
            PushServer::sendAsyncNotification();
        }

        $this->emitEditListPicker();
    }

    // emit form for selecting a playlist for editing, deletion or undelete.
    public function emitEditListPicker() {
        UI::emitJS("js/playlists.pick.js");
        $activePlaylists = Engine::api(IPlaylist::class)->getListsSelNormal($this->session->getUser());
        $playlists = "";
        $activeCount = 0;
        while($activePlaylists && ($row = $activePlaylists->fetch())) {
            $playlists = $playlists . "<OPTION VALUE='$row[0]'>$row[1] -- $row[3]</OPTION>";

           $activeCount++;
        }

        $deletedPlaylists = Engine::api(IPlaylist::class)->getListsSelDeleted($this->session->getUser());
        $deletedOptions = '';
        $deletedCount = 0;
        while($deletedPlaylists && ($row = $deletedPlaylists->fetch())) {
            $deletedOptions .= "<OPTION VALUE='$row[0]'>$row[1] -- $row[3] (expires $row[4])";
            $deletedCount++;
        }
        $typeVisibility = strlen($deletedOptions) == 0 ? 'zk-hidden' : '';
        ?>

        <div CLASS='playlistBanner'>&nbsp; Select Playlist</div>
        <div class='form-entry <?php echo $typeVisibility; ?>' >
            <label>Type:</label>
            <select id='list-type-picker'>
                <option value='active-type'>Active</option>
                <option value='deleted-type'>Deleted</option>
            </select>
        </div>

        <form class='pl-form zk-hidden' id='deleted-form' ACTION="?" METHOD=POST>
        <B>Deleted Playlists (<?php echo $deletedCount; ?>):</B><BR>
        <select sytle='width:400px' name=playlist size=10>
            <?php echo $deletedOptions; ?>
        </select>
        <div style='margin-top:4px'>
            <input TYPE=SUBMIT VALUE=" Restore ">
        </div>
        <input TYPE=hidden name=action VALUE="editListRestore">
        </form>
      
        <form class='pl-form' id='active-form' ACTION="?" METHOD=POST>
            <B>Active Playlists (<?php echo $activeCount; ?>):</B><BR>
            <select id='active-list-picker' style='width:400px' name=playlist SIZE=10 data-focus>
                <?php echo $playlists; ?>
            </select>
            <div style='margin-top:4px'>
                <input TYPE=SUBMIT VALUE=" Edit ">&nbsp;&nbsp;&nbsp;
                <input TYPE=SUBMIT NAME="duplicate" VALUE="Duplicate">&nbsp;&nbsp;&nbsp;
                <input id='delete-list' TYPE=BUTTON VALUE="Delete">
            </div>
            <input id='action-type' TYPE=hidden name=action VALUE="editListDetails">
        </form>
        <?php
    }
    
    private function makeEditDiv($entry, $playlist) {
        $href = "?playlist=" . $playlist . "&amp;id=" .
                $entry->getId() . "&amp;action=" . $this->action . "&amp;";
        $editLink = "<A CLASS='songEdit' HREF='" . $href ."seq=editTrack'>&#x270f;</a>";
        //NOTE: in edit mode the list is ordered new to old, so up makes it 
        //newer in time order & vice-versa.
        $dnd = "<DIV class='grab' data-id='".$entry->getId()."'>&#x2630;</DIV>";
        $retVal = "<div class='songManager'>" . $dnd . $editLink . "</div>";
        return $retVal;
    }

    // make header for edit & view playlist
    private function makePlaylistHeader($isEditMode) {
        $editCol = $isEditMode ? "<TD WIDTH='30PX' />" : "";
        $header = "<TR class='playlistHdr' ALIGN=LEFT>" . $editCol . "<TH WIDTH='64px'>Time</TH><TH WIDTH='25%'>" .
                  "Artist</TH><TH WIDTH='25%'>Track</TH><TH></TH><TH>Album/Label</TH></TR>";
        return $header;
    }

    private function editPlaylist($playlistId) {
        $this->emitPlaylistBody($playlistId, true);
    }

    private function emitTagForm($playlistId, $message) {
        $playlist = Engine::api(IPlaylist::class)->getPlaylist($playlistId, 1);
        $this->emitPlaylistBanner($playlistId, $playlist, true);
        $this->emitTrackAdder($playlistId, $playlist);
    }
    
    private function emitTrackAdder($playlistId, $playlist) {
        $isLiveShow = Engine::api(IPlaylist::class)->isNowWithinShow($playlist);
        $nmeAr = Engine::param('nme');
        $nmeOpts = '';
        $nmePrefix = self::NME_PREFIX;
        if ($nmeAr) {
            foreach ($nmeAr as $nme)
                $nmeOpts = $nmeOpts . "<option data-args='" . $nme['args'] . "' value='" . $nmePrefix . $nme['name'] . "'>" . $nme['name'] . "</option>";
        }

    ?>
        <div class='pl-form-entry'>
            <input id='show-date' name='edate' type='hidden' value="<?php echo $playlist['showdate']; ?>" >
            <input id='show-time' type='hidden' value="<?php echo $playlist['showtime']; ?>" >
            <input id='track-playlist' type='hidden' value='<?php echo $playlistId; ?>'>
            <input id='track-action' type='hidden' value='<?php echo $this->action; ?>'>
            <input id='const-prefix' type='hidden' value='<?php echo self::NME_PREFIX; ?>'>
            <input id='const-set-separator' type='hidden' value='<?php echo PlaylistEntry::TYPE_SET_SEPARATOR; ?>'>
            <input id='const-log-event' type='hidden' value='<?php echo PlaylistEntry::TYPE_LOG_EVENT; ?>'>
            <input id='const-comment' type='hidden' value='<?php echo PlaylistEntry::TYPE_COMMENT; ?>'>
            <input id='const-spin' type='hidden' value='<?php echo PlaylistEntry::TYPE_SPIN; ?>'>
            <label></label><span id='error-msg' class='error'></span>
            <div>
                <a style='padding-right:4px;' href='#' class='nav pull-right' onClick=window.open('?target=export&amp;playlist=<?php echo $playlistId ?>&amp;format=html')>Print View</a>

                <label>Type:</label>
                <select id='track-type-pick'>
                   <option value='manual-entry'>Music</option>
                   <option value='comment-entry'>Comment</option>
                   <?php echo $nmeOpts; ?>
                </select>
            </div>
            <div id='track-entry'>
                <div id='manual-entry'>
                    <div>
                        <label>Artist / Tag:</label>
                        <input required id='track-artist' list='track-artists' autocomplete='off' maxlength=<?php echo PlaylistEntry::MAX_FIELD_LENGTH;?> data-focus />
                        <span class='track-info' id='tag-status'>Artist (last name, first name) or tag number</span>
                        <datalist id='track-artists'>
                        </datalist>
                    </div>
                    <div>
                        <label>Track:</label>
                        <input required id='track-title' list='track-titles' maxlength=<?php echo PlaylistEntry::MAX_FIELD_LENGTH;?> autocomplete='off'/>
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
            <?php if (!$isLiveShow) {
                $window = Engine::api(IPlaylist::class)->getTimestampWindow($playlistId);
                echo "<div>
                    <label>Time:</label>
                    <input id='track-time' class='timepicker' step='60' type='time' data-date='".$window['start']->format('Y-m-d')."' data-start='".$window['start']->format('H:i')."' data-end='".$window['end']->format('H:i')."' />
                </div>";
            }?>
            <div>
                <label></label>
                <button DISABLED id='track-submit' >Add Item</button>
                <button style='margin-left:17px;' id='track-separator'>Add Separator</button>
            </div>
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
    private function emitTrackField($tag, $seltrack, $id) {
        $matched = 0;
        $track = Engine::api(ILibrary::class)->search(ILibrary::COLL_KEY, 0, 100, $tag);
        if(sizeof($track)>0) {
            echo "      <SELECT NAME=ctrack>\n";
            for($i = 0; $i < sizeof($track); $i++) {
                if($track[$i]["track"] == $seltrack) {
                    $matched = 1;
                    $selected = " SELECTED";
                } else
                    $selected = "";
                echo "        <OPTION VALUE=\"".$track[$i]["seq"]."\"$selected>".$track[$i]["seq"].". ".htmlentities($track[$i]["artist"])." - ".htmlentities($track[$i]["track"])."\n";
            }
        } else {
            echo "      <SELECT NAME=track data-focus>\n";
            $track = Engine::api(ILibrary::class)->search(ILibrary::TRACK_KEY, 0, 100, $tag);
            for($i = 0; $i < sizeof($track); $i++) {
                if($track[$i]["track"] == $seltrack) {
                    $matched = 1;
                    $selected = " SELECTED";
                } else
                    $selected = "";
                echo "        <OPTION VALUE=\"".htmlentities($track[$i]["track"])."\"$selected>".$track[$i]["seq"].". ".htmlentities($track[$i]["track"])."\n";
            }
        }
    
        $selected = (($matched==0) && ($id!=0))?" SELECTED":"";
        echo "        <OPTION VALUE=\"\"$selected> -- Enter Custom Track Title -- \n";
        echo "      </SELECT>\n";
    }
    
    private function emitEditForm($playlistId, $id, $album, $track) {
      $entry = new PlaylistEntry($album);
      $window = Engine::api(IPlaylist::class)->getTimestampWindow($playlistId);
      $startTime = $window['start'];
      $endTime = $window['end'];
      $nowTime = new \DateTime("now");
      $isLive = $nowTime >= $startTime && $nowTime <= $endTime;
      $endTime = $isLive ? $nowTime : $endTime;
      $startAMPM = $startTime->format('g:i a');
      $endAMPM = $endTime->format('g:i a');
      $edate = $startTime->format('Y-m-d');
      $showTimeRange = "$startAMPM - $endAMPM";
      $timepickerClass = "timepicker";
      if($isLive && !$entry->getCreated()) {
          // pre-fill empty time in live playlist with 'now'
          $entry->setCreated($nowTime->format(IPlaylist::TIME_FORMAT_SQL));
          $timepickerClass .= " prefilled-input";
      }
      $timepickerTime = $this->getTimepickerTime($entry->getCreated());
      $sep = $id && $entry->isType(PlaylistEntry::TYPE_SET_SEPARATOR);
      $event = $id && $entry->isType(PlaylistEntry::TYPE_LOG_EVENT);
      $comment = $id && $entry->isType(PlaylistEntry::TYPE_COMMENT);
    ?>
      <DIV class='playlistBanner'><?php echo $id?"Editing highlighted":"Adding";?> <?php
      switch($entry->getType()) {
      case PlaylistEntry::TYPE_SET_SEPARATOR:
          echo "set separator";
          break;
      case PlaylistEntry::TYPE_LOG_EVENT:
          echo "program log entry";
          break;
          case PlaylistEntry::TYPE_COMMENT:
          echo "comment";
          break;
      default:
          echo "track";
          break;
      } ?></DIV>
      <FORM ACTION="?" id='edit' METHOD=POST>
      <input id='track-playlist' type='hidden' value='<?php echo $playlistId; ?>'>
      <TABLE>
    <?php if($sep) { ?>
      <INPUT TYPE=HIDDEN NAME=separator VALUE="true">
    <?php } else if($comment) { ?>
      <INPUT TYPE=HIDDEN NAME=comment VALUE="true">
        <TR>
          <TD ALIGN=RIGHT STYLE='vertical-align: top'>Comment:</TD>
          <TD ALIGN=LEFT><TEXTAREA WRAP=VIRTUAL NAME=ctext id=ctext ROWS=4 MAXLENGTH=<?php echo PlaylistEntry::MAX_COMMENT_LENGTH; ?> STYLE='width: 280px !important' REQUIRED data-focus><?php
              $comment = $entry->getComment();
              $len = mb_strlen(str_replace("\r\n", "\n", $comment));
              echo htmlentities($comment); ?></TEXTAREA><div style='display: inline-block;'><span class='remaining' id='remaining'>(<?php echo $len."/".PlaylistEntry::MAX_COMMENT_LENGTH; ?> characters)</span><br/><a id='markdown-help-link' href='#'>formatting help</a></div>
</TD>
        </TR>
    <?php } else if($event) { ?>
      <INPUT TYPE=HIDDEN NAME=logevent VALUE="true">
        <TR>
          <TD ALIGN=RIGHT>Type:</TD><TD ALIGN=LEFT><SELECT NAME=etype STYLE='width: 290px !important' data-focus>
<?php
          $current = $entry->getLogEventType();
          $nmeAr = Engine::param('nme');
          if ($nmeAr) {
              foreach ($nmeAr as $nme) {
                  $selected = ($nme['name'] == $current)?" SELECTED":"";
                  echo "            <OPTION VALUE='" . $nme['name'] . "'$selected>" . $nme['name'] . "</OPTION>\n";
               }
          }
?>
          </SELECT></TD>
        </TR>
        <TR>
          <TD ALIGN=RIGHT>Name/ID:</TD>
          <TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=ecode MAXLENGTH=<?php echo PlaylistEntry::MAX_FIELD_LENGTH;?> required VALUE="<?php echo htmlentities($entry->getLogEventCode());?>" CLASS=input STYLE='width: 280px !important' data-focus></TD>
        </TR>
    <?php } else if($album == "" || $album["tag"] == "") { ?>
        <TR>
          <TD ALIGN=RIGHT>Artist:</TD>
          <TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=artist MAXLENGTH=<?php echo PlaylistEntry::MAX_FIELD_LENGTH;?> VALUE="<?php echo htmlentities($album?$album["artist"]:"");?>" CLASS=input SIZE=40 REQUIRED data-focus></TD>
        </TR>
        <TR>
          <TD ALIGN=RIGHT>Track:</TD>
          <TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=track MAXLENGTH=<?php echo PlaylistEntry::MAX_FIELD_LENGTH;?> VALUE="<?php echo htmlentities($track);?>" CLASS=input SIZE=40 REQUIRED></TD>
        </TR>
        <TR>
          <TD ALIGN=RIGHT>Album:</TD>
          <TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=album MAXLENGTH=<?php echo PlaylistEntry::MAX_FIELD_LENGTH;?> VALUE="<?php echo htmlentities($album?$album["album"]:"");?>" CLASS=input SIZE=40></TD>
        </TR>
        <TR>
          <TD ALIGN=RIGHT>Label:</TD>
          <TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=label MAXLENGTH=<?php echo PlaylistEntry::MAX_FIELD_LENGTH;?> VALUE="<?php echo htmlentities($album?$album["label"]:"");?>" CLASS=input SIZE=40></TD>
        </TR>
    <?php } else { ?>
      <INPUT TYPE=HIDDEN NAME=artist VALUE="<?php echo htmlentities($album["artist"]);?>">
      <INPUT TYPE=HIDDEN NAME=album VALUE="<?php echo htmlentities($album["album"]);?>">
      <INPUT TYPE=HIDDEN NAME=otrack VALUE="<?php echo htmlentities(stripslashes($track));?>">
      <INPUT TYPE=HIDDEN NAME=label VALUE="<?php echo htmlentities($album["label"]);?>">
        <TR><TD ALIGN=RIGHT>Artist:</TD>
            <TH ALIGN=LEFT><?php echo htmlentities($album["artist"]); ?></TH></TR>
        <TR><TD ALIGN=RIGHT>Album:</TD>
            <TH ALIGN=LEFT><?php echo htmlentities($album["album"]); ?></TH></TR>
        <TR><TD ALIGN=RIGHT>Track:</TD>
            <TD ALIGN=LEFT><?php $this->emitTrackField($album["tag"], $track, $id); ?></TD>
        </TR>
    <?php } ?>
      <TR>
          <TD ALIGN=RIGHT>Time:</TD>
          <TD ALIGN=LEFT>
              <INPUT class='<?php echo $timepickerClass; ?>' NAME=etime step='60' type='time' value="<?php echo $timepickerTime ?>" data-date='<?php echo $edate;?>' data-start='<?php echo $startTime->format('H:i');?>' data-end='<?php echo $endTime->format('H:i');?>'/> <span style='font-size:8pt;'>(<?php echo $showTimeRange ?>)</span>
              <INPUT type='hidden' NAME='edate' value="<?php echo $edate;?>" />
          </TD>
        </TR>
        <TR>
          <TD>&nbsp;</TD>
          <TD>
    <?php if($id) { ?>
              <INPUT TYPE=BUTTON NAME=button id='edit-save' VALUE="  Save  ">&nbsp;&nbsp;&nbsp;
              <INPUT TYPE=BUTTON NAME=button id='edit-delete' VALUE=" Delete ">
              <INPUT TYPE=HIDDEN NAME=id VALUE="<?php echo $id;?>">
    <?php } else { ?>
              <INPUT TYPE=SUBMIT VALUE="  Next &gt;&gt;  ">
    <?php } ?>
              <INPUT TYPE=HIDDEN NAME=playlist VALUE="<?php echo $playlistId;?>">
              <INPUT TYPE=HIDDEN NAME=action VALUE="<?php echo $this->action;?>">
              <INPUT TYPE=HIDDEN NAME=tag VALUE="<?php echo $album["tag"];?>">
              <INPUT TYPE=HIDDEN NAME=seq VALUE="editForm">
          </TD>
      </TR>
      </TABLE>
      <HR>
    <?php UI::markdownHelp(); ?>
      </FORM>
      <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript"><!--
    <?php ob_start([JSMin::class, 'minify']); ?>
            $("#ctext").on('input', function(e) {
                var len = this.value.length;
                $("#remaining").html("(" + len + "/<?php echo PlaylistEntry::MAX_COMMENT_LENGTH; ?> characters)");
            });
    <?php ob_end_flush(); ?>
      // -->
      </SCRIPT>
    <?php 
    }
    
    private function emitTrackForm($playlist, $id, $album, $track) {
    ?>
      <DIV class='playlistBanner'><?php echo $id?"Editing highlighted":"Adding";?> track</DIV>
      <FORM ACTION="?" id='edit' METHOD=POST>
      <INPUT TYPE=HIDDEN NAME=artist VALUE="<?php echo htmlentities($album["artist"]);?>">
      <INPUT TYPE=HIDDEN NAME=album VALUE="<?php echo htmlentities($album["album"]);?>">
      <INPUT TYPE=HIDDEN NAME=label VALUE="<?php echo htmlentities($album["label"]);?>">
      <INPUT TYPE=HIDDEN NAME=etime VALUE="<?php echo $_REQUEST["etime"]; ?>">
      <INPUT TYPE=HIDDEN NAME=edate VALUE="<?php echo $_REQUEST["edate"]; ?>">
      <TABLE>
        <TR><TD ALIGN=RIGHT>Artist:</TD>
            <TH ALIGN=LEFT><?php echo htmlentities($album["artist"]); ?></TH></TR>
        <TR><TD ALIGN=RIGHT>Album:</TD>
            <TH ALIGN=LEFT><?php echo htmlentities($album["album"]); ?></TH></TR>
        <TR><TD ALIGN=RIGHT>Track:</TD>
            <TD><INPUT TYPE=TEXT NAME=track MAXLENGTH=<?php echo PlaylistEntry::MAX_FIELD_LENGTH;?> CLASS=input VALUE="<?php echo htmlentities($track);?>" data-focus></TD></TR>
        <TR><TD></TD><TD>
    <?php if($id) { ?>
          <INPUT TYPE=SUBMIT VALUE="  Save  ">
          <INPUT TYPE=HIDDEN NAME=id VALUE="<?php echo $id;?>">
    <?php } else { ?>
          <INPUT TYPE=SUBMIT VALUE="  Next &gt;&gt;  ">
    <?php } ?>
          <INPUT TYPE=HIDDEN NAME=tag VALUE="<?php echo $album["tag"];?>">
          <INPUT TYPE=HIDDEN NAME=action VALUE="<?php echo $this->action;?>">
          <INPUT TYPE=HIDDEN NAME=playlist VALUE="<?php echo $playlist;?>">
          <INPUT TYPE=HIDDEN NAME=seq VALUE="editForm">
        </TD></TR>
      </TABLE>
      </FORM>
    <?php 
    }
    
    private function insertTrack($playlistId, $tag, $artist, $track, $album, $label, $spinTime) {
        $id = 0;
        $status = '';
        // Run the query
        $success = Engine::api(IPlaylist::class)->insertTrack($playlistId,
                     $tag, $artist, $track, $album, $label, $spinTime, $id, $status);    
    }
    
    private function deleteTrack($id) {
        // Run the query
        $success = Engine::api(IPlaylist::class)->deleteTrack($id);
    }
    
    private function emitPlaylistTitle($playlist) {
        // Print the header
        $script = "?target=export";
        $row = Engine::api(IPlaylist::class)->getPlaylist($playlist);
        $showDateTime = self::makeShowDateAndTime($row);
        echo "<TABLE CELLPADDING=0 CELLSPACING=0 WIDTH=\"100%\">\n    <TR>\n      <TH ALIGN=LEFT>";
        echo "$row[0]</TH>\n      <TH ALIGN=RIGHT>$showDateTime</TH>\n";
        echo "      <TH ALIGN=RIGHT VALIGN=TOP><A CLASS=\"sub\" HREF=\"#top\" onClick='window.open(\"$script&amp;playlist=$playlist&amp;format=html\")'>Print</A></TH>\n    </TR>\n  </TABLE>\n";
    }
    
    private function insertSetSeparator($playlist) {
        //WARNING: $this->insertTrackEntry() does not exist. is this dead code?
        $this->insertTrackEntry($playlist, (new PlaylistEntry())->setSetSeparator(), null);
    }
    
    public function emitEditor() {
        $artist = $_REQUEST["artist"];
        $track = $_REQUEST["track"];
        $ctrack = $_REQUEST["ctrack"];
        $album = $_REQUEST["album"];
        $tag = $_REQUEST["tag"];
        $playlist = $_REQUEST["playlist"];
        $id = $_REQUEST["id"];
        $seq = $_REQUEST["seq"];
        $button = $_REQUEST["button"];
        $otrack = $_REQUEST["otrack"];
        $label = $_REQUEST["label"];
        $separator = $_REQUEST["separator"];
        $logevent = $_REQUEST["logevent"];
        $comment = $_REQUEST["comment"];
    ?>
    <TABLE CELLPADDING=0 CELLSPACING=0 WIDTH="100%">
    <TR><TD>
    <?php
        $message = "";
        if(!isset($playlist) || !$this->isOwner($playlist)) {
            $seq = "error";
            $message = "access error";
        }
        switch ($seq) {
        case "tagForm":
            if($separator) {
                $this->insertSetSeparator($playlist);
                $this->emitTagForm($playlist, "");
            } else if($tag != "") {
                // Lookup tag
                $albumrec = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $tag);
                if(sizeof($albumrec) == 0) {
                    // invalid tag
                    $this->emitTagForm($playlist, "Invalid Tag");
                } else {
                    $albumrec[0]["label"] = isset($albumrec[0]["name"])?
                          $albumrec[0]["name"]:"(Unknown)";
                    $this->emitEditForm($playlist, $id, $albumrec[0], $track);
                }
            } else
                $this->emitEditForm($playlist, $id, "", "");
            break;
        case "editForm":
            $status = '';
            $nme = $separator || $logevent || $comment;
            if(($button == " Delete ") && $id) {
                $this->deleteTrack($id);
                $id = "";
                $this->emitTagForm($playlist, "");
            } else if(!$nme && $artist == "") {
                $albuminfo = ["tag"=>$tag,
                              "artist"=>stripslashes($artist),
                              "album"=>stripslashes($album),
                              "label"=>stripslashes($label)];
                $this->emitEditForm($playlist, $id, $albuminfo, stripslashes($track));
            } else if(!$nme &&
                          ($track == "") && ($ctrack == "")) {
                $albuminfo = ["tag"=>$tag,
                              "artist"=>stripslashes($artist),
                              "album"=>stripslashes($album),
                              "label"=>stripslashes($label)];
                $this->emitTrackForm($playlist, $id, $albuminfo, stripslashes($otrack));
            } else {
                if($ctrack) {
                    $track = Engine::api(ILibrary::class)->search(ILibrary::COLL_KEY, 0, 100, $tag);
                    for($i = 0; $i < sizeof($track); $i++) {
                        if($track[$i]["seq"] == $ctrack) {
                            $artist = $track[$i]["artist"];
                            $track = $track[$i]["track"];
                            break;
                        }
                    }
                }
                if($id) {
                    $timestamp = null;
                    if ($_REQUEST["etime"])  //in case user blanked out the time
                        $timestamp = $_REQUEST["edate"] . " " . $_REQUEST["etime"] . ":00";

                    if($nme) {
                        $entry = (new PlaylistEntry())->setId($id);
                        if($logevent)
                            $entry->setLogEvent($_REQUEST["etype"], $_REQUEST["ecode"]);
                        else if($comment)
                            $entry->setComment(mb_substr(trim(str_replace("\r\n", "\n", $_REQUEST["ctext"])), 0, PlaylistEntry::MAX_COMMENT_LENGTH));
                        else
                            $entry->setSetSeparator();

                        $entry->setCreated($timestamp);
                        Engine::api(IPlaylist::class)->updateTrackEntry($playlist,
                                $entry);
                    } else {
                        $playlistApi = Engine::api(IPlaylist::class);
                        $playlistApi->updateTrack($playlist, $id, $tag, $artist, $track, $album, $label, $timestamp);
                        $list = $playlistApi->getPlaylist($playlist);
                        if($list['airname'] &&
                                $playlistApi->isNowWithinShow($list))
                            PushServer::sendAsyncNotification();

                        $this->lazyLoadImages($playlist, $id);
                    }
                    $id = "";
                } else
                    $this->insertTrack($playlist, $tag, $artist, $track, $album, $label, null);
                $this->emitTagForm($playlist, "");
            }
            break;
        case "editTrack":
            // Run the query
            $albuminfo = Engine::api(IPlaylist::class)->getTrack($id);
            if($albuminfo)
                $track = $albuminfo['track'];
            $this->emitEditForm($playlist, $id, $albuminfo, $track);
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
    
        if($button == " Setup New Airname... ") {
            $displayForm = 1;
            $djname = trim($djname);
            if($newairname == " Add Airname " && $djname) {
                // Insert new airname
                $api = Engine::api(IDJ::class);
                $success = $api->insertAirname($djname, $this->session->getUser());
                if($success > 0) {
                    $airname = $api->lastInsertId();
                    $button = "";
                    $displayForm = 0;
                } else
                    $errorMessage = "<B><FONT CLASS=\"error\">Airname '$djname' is invalid or already exists.</FONT></B>";
            }
            if ($displayForm) {
    ?>
    <P CLASS="header"><b>Add Air Name</b></P>
    <?php echo $errorMessage; ?>
    <FORM ACTION="?" METHOD=POST>
    <TABLE CELLPADDING=2 CELLSPACING=0>
      <TR>
        <TD ALIGN=RIGHT>Airname:</TD>
        <TD><INPUT TYPE=TEXT NAME=djname SIZE=30 maxlength=<?php echo IDJ::MAX_AIRNAME_LENGTH;?> data-focus></TD>
      </TR>
      <TR>
        <TD>&nbsp;</TD>
        <TD><INPUT TYPE=SUBMIT NAME="newairname" VALUE=" Add Airname "></TD>
      </TR>
    </TABLE>
    <INPUT TYPE=HIDDEN NAME=button VALUE=" Setup New Airname... ">
    <INPUT TYPE=HIDDEN NAME=action VALUE="importExport">
    <INPUT TYPE=HIDDEN NAME=subaction VALUE="importCSV">
    <INPUT TYPE=HIDDEN NAME=playlist VALUE="<?php echo $playlist;?>">
    <INPUT TYPE=HIDDEN NAME=description VALUE="<?php echo htmlentities(stripslashes($description));?>">
    <INPUT TYPE=HIDDEN NAME=date VALUE="<?php echo htmlentities(stripslashes($date));?>">
    <INPUT TYPE=HIDDEN NAME=time VALUE="<?php echo htmlentities(stripslashes($time));?>">
    <INPUT TYPE=HIDDEN NAME=fromtime VALUE="<?php echo htmlentities(stripslashes($fromtime));?>">
    <INPUT TYPE=HIDDEN NAME=totime VALUE="<?php echo htmlentities(stripslashes($totime));?>">
    <INPUT TYPE=HIDDEN NAME=validate VALUE="y">
    </FORM>
    <?php 
                return;
            }
        }
        if(!$date)
            $date = date("Y-m-d");
        list($year, $month, $day) = explode("-", $date);
        $time = $this->composeTime($fromtime, $totime);
        if(!$userfile || $userfile == "none" ||
                $description == "" ||
                $time == '' ||
                !checkdate($month, $day, $year)) {
            if($validate == "edit")
                echo "<B><FONT CLASS='error'>Ensure fields are not blank and date is valid.</FONT></B><BR>\n";
    ?>
      <FORM ENCTYPE="multipart/form-data" ACTION="?" METHOD=post>
        <INPUT TYPE=HIDDEN NAME=action VALUE="importExport">
        <INPUT TYPE=HIDDEN NAME=subaction VALUE="importCSV">
        <INPUT TYPE=HIDDEN NAME=validate VALUE="edit">
        <INPUT TYPE=HIDDEN NAME=MAX_FILE_SIZE VALUE=100000>
        <TABLE CELLPADDING=2 CELLSPACING=0>
          <TR>
            <TD ALIGN=RIGHT>Show Name:</TD>
            <TD><INPUT TYPE=TEXT NAME=description VALUE="<?php echo stripslashes($description);?>" SIZE=30 maxlength=<?php echo IPlaylist::MAX_DESCRIPTION_LENGTH;?> data-focus></TD>
          </TR><TR>
            <TD ALIGN=RIGHT>Date:</TD>
            <TD><INPUT TYPE=TEXT NAME=date VALUE="<?php echo stripslashes($date);?>" SIZE=15></TD>
          </TR><TR>
            <TD ALIGN=RIGHT>Time Slot:</TD>
    <?php 
        if(strlen($fromtime) && strlen($totime)
                       || $this->extractTime($time, $fromtime, $totime)) {
            // Emit the time in canonical format
            echo "        <TD><SELECT NAME=fromtime>\n";
            for($i=0; $i<24; $i++) {
                for($j=0; $j<60; $j+=30) {
                    $ovalue = sprintf("%02d%02d", $i, $j);
                    $selected = ($ovalue == $fromtime)?" SELECTED":"";
                    echo "              <OPTION VALUE=\"$ovalue\"$selected>$ovalue\n";
                }
            }
            echo "            </SELECT> - <SELECT NAME=totime>\n";
            for($i=0; $i<24; $i++) {
                for($j=0; $j<60; $j+=30) {
                    $ovalue = sprintf("%02d%02d", $i, $j);
                    $selected = ($ovalue == $totime)?" SELECTED":"";
                    echo "              <OPTION VALUE=\"$ovalue\"$selected>$ovalue\n";
                }
            }
            echo "            </SELECT></TD>\n";
        } else
            // Emit the time in legacy format
            echo "        <TD><INPUT TYPE=TEXT NAME=time VALUE=\"". htmlentities(stripslashes($time)) . "\" CLASS=input SIZE=15></TD>\n";
    ?>
          </TR><TR>
            <TD ALIGN=RIGHT>Airname:</TD>
            <TD><SELECT NAME=airname>
    <?php 
            $records = Engine::api(IDJ::class)->getAirnames($this->session->getUser(), 0, $djname);
            while ($records && ($row = $records->fetch())) {
                $selected = ($row[0] == $airname)?" SELECTED":"";
                echo "              <OPTION VALUE=\"" . $row[0] ."\"" . $selected .
                     ">$row[1]\n";
            }
            $selected = $airname?"":" SELECTED";
            echo "              <OPTION VALUE=\"\"$selected>(unpublished playlist)\n";
    ?>
                </SELECT><INPUT TYPE=SUBMIT NAME=button VALUE=" Setup New Airname... "></TD>
          </TR><TR>
            <TD ALIGN=RIGHT>Import from file:</TD><TD><INPUT NAME=userfile TYPE=file></TD>
          </TR><TR>
            <TD>&nbsp;</TD>
            <TD><INPUT TYPE=submit VALUE=" Import Playlist "></TD>
          </TR><TR>
            <TD>&nbsp;</TD>
            <TD CLASS="sub"><div class='user-tip' style='display: block; max-width: 550px;'>
                <h4>CSV Format</h4>
                <p>File must be UTF-8 encoded and tab delimited, with one
                track per line.  Each line may contain 4, 5, or 6 columns:</p>
                <p>&nbsp;&nbsp;&nbsp;&nbsp;<B>artist&nbsp; track&nbsp; album&nbsp; label</B> &nbsp;or<BR><BR>
                &nbsp;&nbsp;&nbsp;&nbsp;<B>artist&nbsp; track&nbsp; album&nbsp; tag&nbsp; label</B> &nbsp;or<BR><BR>
                &nbsp;&nbsp;&nbsp;&nbsp;<B>artist&nbsp; track&nbsp; album&nbsp; tag&nbsp; label&nbsp; timestamp</B>,</p>
                <p>where each column is separated by a tab character.</p>
                <p>Any file data not in this format will be ignored.</p></div></TD>
          </TR>
        </TABLE>
      </FORM>
    <?php 
        } else {
            // Create the playlist
            $api = Engine::api(IPlaylist::class);
            $success = $api->insertPlaylist($this->session->getUser(), $date, $time, $description, $airname);
            $playlist = $api->lastInsertId();
    
            // Insert the tracks
            $count = 0;
            $fd = new \SplFileObject($userfile, "r");
            $window = $api->getTimestampWindow($playlist);
            while($fd->valid()) {
                $line = $fd->fgetcsv("\t");
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
            unset($_POST["validate"]);

            $this->lazyLoadImages($playlist);

            $_REQUEST["playlist"] = $playlist;
            $this->action = "newListEditor";
            $this->emitEditor();
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
                    }

                    $this->lazyLoadImages($playlist);

                    // display the editor
                    $_REQUEST["playlist"] = $playlist;
                    $this->action = "newListEditor";
                    $this->emitEditor();
                    $displayForm = false;
                } else
                    echo "<B><FONT CLASS='error'>Show details are invalid.</FONT></B><BR>\n";
            }
        }

        if($displayForm) {
    ?>
      <FORM ENCTYPE="multipart/form-data" ACTION="?" METHOD=post>
        <INPUT TYPE=HIDDEN NAME=action VALUE="importExport">
        <INPUT TYPE=HIDDEN NAME=subaction VALUE="importJSON">
        <INPUT TYPE=HIDDEN NAME=MAX_FILE_SIZE VALUE=100000>
        <TABLE CELLPADDING=2 CELLSPACING=0>
          <TR>
            <TD ALIGN=RIGHT>Import from file:</TD><TD><INPUT NAME=userfile TYPE=file data-focus></TD>
          </TR><TR>
            <TD>&nbsp;</TD>
            <TD><INPUT TYPE=submit VALUE=" Import Playlist "></TD>
          </TR><TR>
            <TD>&nbsp;</TD>
            <TD CLASS="sub"><div class='user-tip' style='display: block'>
                <p>File must be a UTF-8 encoded JSON playlist,
                such as previously exported via Export Playlist.</p></div></TD>
          </TR>
        </TABLE>
      </FORM>
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
    public function emitShowLink() {
        UI::emitJS("js/playlists.pick.js");

        $validate = $_POST["validate"];
        $playlist = $_REQUEST["playlist"];
        $airname = $_REQUEST["airname"];
    
        if($validate && ($playlist == "all" || $airname)) {
            $airnames = Engine::api(IDJ::class)->getAirnames($this->session->getUser(), $airname)->asArray();
            if(sizeof($airnames) == 1) {
                // User has only one airname; show the link now
                $row = $airnames[0];
    ?>
    <B>Here is the URL to access all of <?php echo $row[1];?>'s playlists:</B>
    <P CLASS="sub"><B>
    <?php echo UI::getBaseUrl();?>?action=viewDJ&amp;seq=selUser&amp;viewuser=<?php echo $row[0];?>
    </B></P>
    <P>Cut-and-paste the above URL to provide direct access to your playlists.</P>
    <?php 
           } else {
                // User has multiple airnames; let them pick one
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
    <INPUT TYPE=SUBMIT VALUE=" Show URL ">
    <INPUT TYPE=HIDDEN NAME=action VALUE="showLink">
    <INPUT TYPE=HIDDEN NAME=validate VALUE="y">
    </TD></TR></TABLE>
    </FORM>
    <?php 
           }
           return;
        } else if($validate && $playlist) {
    ?>
    <TABLE CELLPADDING=0 CELLSPACING=0 WIDTH="100%">
    <TR><TD VALIGN=TOP>
    <B>Here is the URL for this playlist:</B>
    <P CLASS="sub"><B>
    <?php echo UI::getBaseUrl();?>?action=viewDJ&amp;seq=selList&amp;playlist=<?php echo $playlist;?>
    </B></P>
    <P>Cut-and-paste the above URL to provide direct access to this playlist.</P>
    </TD></TR>
    <TR><TD>&nbsp;</TD></TR>
    <TR><TD>
    <HR>
    <?php      $this->viewList($playlist, "showLink"); ?>
    </TD></TR>
    </TABLE>
    <?php 
            return;
        }
    ?>
    <FORM ACTION="?" METHOD=POST>
    <B>Select Playlist:</B><BR>
    <TABLE CELLPADDING=0 BORDER=0><TR><TD>
    <SELECT NAME=playlist SIZE=10 data-focus>
      <OPTION VALUE="all"> -- URL for all of my playlists --
    <?php 
        // Run the query
        $records = Engine::api(IPlaylist::class)->getPlaylists(1, 0, 0, 0, $this->session->getUser());
        while($records && ($row = $records->fetch()))
            echo "  <OPTION VALUE=\"$row[0]\">$row[1] -- $row[3]\n";
    ?>
    </SELECT></TD></TR>
    <TR><TD>
    <INPUT TYPE=SUBMIT VALUE=" Show URL ">
    <INPUT TYPE=HIDDEN NAME=action VALUE="showLink">
    <INPUT TYPE=HIDDEN NAME=validate VALUE="y">
    </TD></TR></TABLE>
    </FORM>
    <?php 
    }
    
    private function makeAlbumLink($entry, $includeLabel) {
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

    private function makePlaylistObserver($playlist, $editMode) {
        $break = false;
        return (new PlaylistObserver())->onComment(function($entry) use($playlist, $editMode, &$break) {
                $editCell = $editMode ? "<TD>" .
                    $this->makeEditDiv($entry, $playlist) . "</TD>" : "";
                $timeplayed = self::timestampToAMPM($entry->getCreated());
                echo "<TR class='commentRow".($editMode?"Edit":"")."'>" . $editCell .
                     "<TD class='time'>$timeplayed</TD>" .
                     "<TD COLSPAN=4>".UI::markdown($entry->getComment()).
                     "</TD></TR>\n";
                $break = false;
            })->onLogEvent(function($entry) use($playlist, $editMode, &$break) {
                $timeplayed = self::timestampToAMPM($entry->getCreated());
                if($this->session->isAuth("u")) {
                    // display log entries only for authenticated users
                    $editCell = $editMode ? "<TD>" .
                        $this->makeEditDiv($entry, $playlist) . "</TD>" : "";
                    echo "<TR class='logEntry".($editMode?"Edit":"")."'>" . $editCell .
                         "<TD class='time'>$timeplayed</TD>" .
                         "<TD>".$entry->getLogEventType()."</TD>" .
                         "<TD COLSPAN=3>".$entry->getLogEventCode()."</TD>" .
                         "</TR>\n";
                    $break = false;
                } else if(!$break) {
                    echo "<TR class='songDivider'>" . $editCell .
                         "<TD class='time'>$timeplayed</TD><TD COLSPAN=4><HR></TD></TR>\n";
                    $break = true;
                }
            })->onSetSeparator(function($entry) use($playlist, $editMode, &$break) {
                if($editMode || !$break) {
                    $editCell = $editMode ? "<TD>" .
                        $this->makeEditDiv($entry, $playlist) . "</TD>" : "";
                    $timeplayed = self::timestampToAMPM($entry->getCreated());
                    echo "<TR class='songDivider'>" . $editCell .
                         "<TD class='time'>$timeplayed</TD><TD COLSPAN=4><HR></TD></TR>\n";
                    $break = true;
                }
            })->onSpin(function($entry) use($playlist, $editMode, &$break) {
                $editCell = $editMode ? "<TD>" .
                    $this->makeEditDiv($entry, $playlist) . "</TD>" : "";
                $timeplayed = self::timestampToAMPM($entry->getCreated());
                $reviewCell = $entry->getReviewed() ? "<div class='albumReview'></div>" : "";
                $artistName = PlaylistEntry::swapNames($entry->getArtist());

                $albumLink = $this->makeAlbumLink($entry, true);
                echo "<TR class='songRow'>" . $editCell .
                     "<TD class='time'>$timeplayed</TD>" .
                     "<TD>" . $this->smartURL($artistName) . "</TD>" .
                     "<TD>" . $this->smartURL($entry->getTrack()) . "</TD>" .
                     "<TD>$reviewCell</TD>" .
                     "<TD>$albumLink</TD>" .
                     "</TR>\n";
                $break = false;
            });
    }

    private function emitPlaylistBody($playlist, $editMode) {
        $header = $this->makePlaylistHeader($editMode);
        $editCell = "";
        echo "<TABLE class='playlistTable' CELLPADDING=1>\n";
        echo "<THEAD>" . $header . "</THEAD>";

        $entries = Engine::api(IPlaylist::class)->getTracks($playlist, $editMode)->asArray();
        Engine::api(ILibrary::class)->markAlbumsReviewed($entries);

        $observer = $this->makePlaylistObserver($playlist, $editMode);
        echo "<TBODY>\n";
        if($entries != null && sizeof($entries) > 0)
            foreach($entries as $entry)
                $observer->observe(new PlaylistEntry($entry));
        echo "</TBODY></TABLE>\n";

        if($editMode)
            UI::emitJS('js/playlists.track.js');
    }

    public static function makeShowDateAndTime($row) {
        return self::timestampToDate($row['showdate']) . " " .
               self::timeToAMPM($row['showtime']);
    }

    private function emitPlaylistBanner($playlistId, $playlist, $editMode) {
        $showName = $playlist['description'];
        $djId = $playlist['id'];
        $djName = $playlist['airname'];
        $showDateTime = self::makeShowDateAndTime($playlist);

        $this->title = "$showName with $djName " . self::timestampToDate($playlist['showdate']);

        if(!$editMode && $this->session->isAuth("v"))
            $showDateTime .= "&nbsp;<A HREF='javascript:document.duplist.submit();' TITLE='Duplicate Playlist'>&#x1f4cb;</A><FORM NAME='duplist' ACTION='?' METHOD='POST'><INPUT TYPE='hidden' NAME='action' VALUE='editListDetails'><INPUT TYPE='hidden' NAME='duplicate' VALUE='1'><INPUT TYPE='hidden' NAME='playlist' VALUE='$playlistId'></FORM>";

        $dateDiv = "<DIV>".$showDateTime."&nbsp;</DIV>";
        $djLink = "<A HREF='?action=viewDJ&amp;seq=selUser&amp;viewuser=$djId' CLASS='nav2'>$djName</A>";

        echo "<DIV CLASS='playlistBanner'>&nbsp;" . $showName . " with " . $djLink.$dateDiv . "</DIV>\n";
    }

    public  function emitViewPlayList() {
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
    
    private function emitViewDJSortFn($a, $b) {
        return strcasecmp($a["sort"], $b["sort"]);
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
    
            echo $this->smartURL($artist) . "&nbsp;&#8226; <I>";
    
            // Album
            if($result[$i]["tag"])
                 echo "<A CLASS=\"nav\" HREF=\"".
                      "?s=byAlbumKey&amp;n=". UI::URLify($result[$i]["tag"]).
                      "&amp;q=". $maxresults.
                      "&amp;action=search\">";
    
            echo $this->smartURL($result[$i]["album"], !$result[$i]["tag"]);
            if($result[$i]["tag"])
                echo "</A>";
            echo "</I>";
            if($label)
                echo "&nbsp;&#8226; (".$this->smartURL($label) . ")";
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
                echo "        <OPTION VALUE=\"$row[0]\">$row[1] -- $row[3]\n";
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
            usort($dj, array($this, "emitViewDJSortFn"));
    
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
        echo "<img id='playlist-calendar' src='img/calendar-icon.png'></img>";
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
            $timeRange = self::timeToAMPM($row[2]);
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
    
            // Setup date format differently if plays extend into another year
            $now = getdate(time());
            list($y,$m,$d) = explode("-", $plays[sizeof($plays)-1]["showdate"]);
            $dateSpec = ($y == $now["year"])?"D, d M":"D, d M y";
    
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
                    $showDate = date('M d, Y', strtotime($play["showdate"]));
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

