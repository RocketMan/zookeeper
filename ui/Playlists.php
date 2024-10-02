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

    private function emitPlaylistBody($playlist, $editMode) {
        $api = Engine::api(IPlaylist::class);
        $tracks = $api->getTracks($playlist['id'], $editMode)->asArray();
        Engine::api(ILibrary::class)->markAlbumsReviewed($tracks);

        $params = [
            "action" => $this->subaction,
            "editMode" => $editMode,
            "authUser" => $this->session->isAuth("u"),
            "usLocale" => UI::isUsLocale()
        ];

        $entries = array_map(function($track) {
            return new PlaylistEntry($track);
        }, $tracks);

        $this->addVar("params", $params);
        $this->addVar("entries", $entries);
        $this->addVar("editMode", $editMode);
        $this->addVar("isLive", $api->isNowWithinShow($playlist));
    }

    private function emitPlaylistBanner($playlist) {
        $playlistId = $playlist['id'];
        $showName = $playlist['description'];
        $djName = $playlist['airname'] ?? "None";
        $showDateTime = self::makeShowDateAndTime($playlist);

        $this->title = "$showName with $djName " . self::timestampToDate($playlist['showdate']);

        $this->extra = "<span class='sub'><b>Share Playlist:</b></span> <a class='nav share-link' data-link='".Engine::getBaseURL()."?subaction=viewListById&amp;playlist=$playlistId'><span class='fas fa-link'></span></a></span>";

        $this->addVar("showDateTime", $showDateTime);
    }

    private function emitAddForm($playlist) {
        $this->emitPlaylistBanner($playlist);
        $this->emitTrackAdder($playlist);
        $this->setTemplate('list/editor.html');
    }
    
    private function emitTrackAdder($playlist, $editTrack = false) {
        $api = Engine::api(IPlaylist::class);

        $playlistId = $playlist['id'];

        $this->addVar('NME_PREFIX', self::NME_PREFIX);
        $this->addVar('BASE_URL', Engine::getBaseURL());

        /* TZO is server equivalent of javascript Date.getTimezoneOffset() */
        $this->addVar('TZO', round(date('Z')/-60, 2));
        $this->addvar('playlist', $playlist);
        $this->addVar('editTrack', $editTrack);

        $this->addVar('MAX_FIELD_LENGTH', PlaylistEntry::MAX_FIELD_LENGTH);
        $this->addVar('MAX_COMMENT_LENGTH', PlaylistEntry::MAX_COMMENT_LENGTH);

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
        $this->addVar('time', $time);

        // this is probably unnecessary, as desktop browsers *should*
        // degrade 'tel' to 'text', *but* as this is a hack to
        // deal with the lack of keyDown support in mobile input
        // type=text, we'll include 'tel' only for mobile devices...
        $ttype = preg_match('/tablet|mobile|android/i',
                $_SERVER['HTTP_USER_AGENT'] ?? '') ? "tel" : "text";
        $this->addVar("ttype", $ttype);

        // colon is included in 24hr format for symmetry with fxtime,
        // which it is referencing
        $timeSpec = UI::isUsLocale() ? 'g:i a' : 'H:i';
        $startAMPM = $window['start']->format($timeSpec);
        $endAMPM = $window['end']->format($timeSpec);
        $timeMsg = "($startAMPM - $endAMPM)";
        $this->addVar("window", $window);
        $this->addVar("timeMsg", $timeMsg);
    }

    private function emitEditForm($playlist, $id, $album) {
        $entry = new PlaylistEntry($album);
        $showName = $playlist['description'];
        $djName = $playlist['airname'] ?? "None";

        $this->title = "$showName with $djName " . self::timestampToDate($playlist['showdate']);
        $this->emitTrackAdder($playlist, $id);
        $this->addVar("entry", $entry);
        $this->setTemplate('list/editItem.html');
    }

    public function emitEditor() {
        $playlistId = $_REQUEST["playlist"] ?? null;
        $seq = $_REQUEST["seq"] ?? null;
        $id = $_REQUEST["id"] ?? null;

        if($seq == "editTrack") {
            $albuminfo = Engine::api(IPlaylist::class)->getTrack($id);
            if($albuminfo) {
                // if editing a track, always get the playlist from
                // the track, even if one is supplied in the request
                $playlistId = $albuminfo['list'];
            }
        }

        if(is_null($playlistId) || !$this->isOwner($playlistId)) {
            echo "<B>Sorry, the playlist you have requested does not exist.</B>";
            return;
        }

        $playlist = Engine::api(IPlaylist::class)->getPlaylist($playlistId, 1);

        switch ($seq) {
        case "editTrack":
            $this->emitEditForm($playlist, $id, $albuminfo);
            break;
        default:
            $this->emitAddForm($playlist);
            break;
        }

        $this->emitPlaylistBody($playlist, true);
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

        $this->emitPlaylistBanner($row);
        $this->emitPlaylistBody($row, false);

        $this->addVar("playlist", $row);
        $this->setTemplate('list/view.html');
    }
    
    public function emitViewDJ() {
        $seq = $_REQUEST["seq"] ?? '';
        $viewuser = $_REQUEST["viewuser"] ?? 0;
        $playlist = $_REQUEST["playlist"] ?? 0;
    
        settype($playlist, "integer");
        settype($viewuser, "integer");
    
        if(((($seq == "selUser") && $viewuser)) ||
                (($seq == "selList") && !$playlist)) {
            $weeks = 10;
            $limit = 10;
            $count = 10;

            $dj = Engine::api(IDJ::class)->getAirnames(0, $viewuser)->fetch();
            if(!$dj) {
                echo "<b>Sorry, the DJ you have requested does not exist.</b>";
                return;
            }

            $this->tertiary = $dj['airname'];

            if($dj['url'] || $dj['email']) {
                $airname = htmlentities($dj['airname'], ENT_QUOTES, 'UTF-8');
                $extra = "<span>";
                if($dj['url']) {
                    $extra .= "<a href='" .
                        htmlentities($dj['url'], ENT_QUOTES, 'UTF-8') .
                        "' class='nav' target='_blank'><b>Go to {$airname}&#039;s website</b></a>";
                    if($dj['email'])
                        $extra .= " &nbsp; | &nbsp; ";
                }
                if($dj['email'])
                    $extra .= "<a href='mailto:" .
                        htmlentities($dj['email'], ENT_QUOTES, 'UTF-8') .
                        "' class='nav'><b>e-mail {$airname}</b></a>";
                $extra .= "</span>";
                $this->extra = $extra;
            }
    
            $topPlays = Engine::api(IPlaylist::class)->getTopPlays($viewuser, $weeks * 7, $limit);
            $recentPlays = Engine::api(IPlaylist::class)->getRecentPlays($viewuser, $count);
            $recentReviews = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_AIRNAME, 0, $count - 1, $viewuser, "Date-");
            $playlists = Engine::api(IPlaylist::class)->getPlaylists(0, 0, 0, $viewuser)->asArray();

            $this->addVar('topPlays', $topPlays);
            $this->addVar('recentPlays', $recentPlays);
            $this->addVar('recentReviews', $recentReviews);
            $this->addVar('playlists', $playlists);
            $this->addVar('dj', $dj);
            $this->addVar('weeks', $weeks);
            $this->addVar('limit', $limit);
            $this->addVar('count', $count);
            $this->setTemplate('list/bydj.html');

            return;
        } else if(($seq == "selList") && $playlist) {
            $this->viewList($playlist);
            return;
        }

        $this->emitViewDJMain();
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
        $lists = Engine::api(IPlaylist::class)->getPlaylists(1, 1, $viewdate, 0, 0, 0, 20)->asArray();
        $count = count($lists);

        foreach($lists as &$list)
            $list['timerange'] = self::timeToLocale($list['showtime']);

        $factory = new TemplateFactoryUI();
        $template = $factory->load('list/bydate.html');
        $tbody = $template->renderBlock('list', [
            "lists" => $lists
        ]);

        echo json_encode(["count" => $count, "tbody" => $tbody]);
    }
}

