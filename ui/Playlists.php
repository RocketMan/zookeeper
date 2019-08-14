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

namespace ZK\UI;

use \Datetime;

use ZK\Engine\Engine;
use ZK\Engine\IDJ;
use ZK\Engine\ILibrary;
use ZK\Engine\IPlaylist;
use ZK\Engine\IReview;

use ZK\UI\UICommon as UI;

class Playlists extends MenuItem {
    //NOTE: update ui_config.php when changing the actions.
    private static $actions = [
        [ "newList", "emitEditListNew" ],
        [ "editList", "emitEditListSel" ],
        [ "newListEditor", "emitEditor" ],
        [ "editListEditor", "emitEditor" ],
        [ "showLink", "emitShowLink" ],
        [ "importExport", "emitImportExportList" ],
        [ "viewDJ", "emitViewDJ" ],
        [ "viewDJReviews", "viewDJReviews" ],
        [ "viewDate", "emitViewDate" ],
        [ "updateDJInfo", "updateDJInfo" ],
        [ "addTrack", "handleAddTrack" ],
    ];

    private $action;
    private $subaction;

    private $noTables = false;
    
    public function processLocal($action, $subaction) {
        $this->action = $action;
        $this->subaction = $subaction;
        return $this->dispatchAction($action, self::$actions);
    }
    
    private function smartURL($name, $detect=true) {
        if($detect) {
            //$name = UI::HTMLify($name, 20);
            $name = htmlentities($name);
    
            $words = explode(" ", $name);
            for($i=0; $i<sizeof($words); $i++) {
                $word = $words[$i];
                $len = strlen($word);
                if($word{0} == "(" && $word{$len-1} == ")" ||
                         $word{0} == "\"" && $word{$len-1} == "\"" ||
                         $word{0} == "'" && $word{$len-1} == "'" ||
                         $word{0} == "{" && $word{$len-1} == "}" ||
                         $word{0} == "[" && $word{$len-1} == "]") {
                    $len -= 2;
                    $word = substr($word, 1, $len);
                }
                $at = strrpos($word, "@");
                $prefix = (substr($word, 0, 7) == "http://")?7:0;
                $stroke = strpos(substr($word, $prefix), "/");
                if($stroke) {
                    $len = $stroke + $prefix;
                    $dot = strrpos(substr($word, 0, $len), ".");
                } else
                    $dot = strrpos($word, ".");
                $ipos = strtr($word, "'(){}|\\^~[]`", "            ") != $word;
                if($ipos || $dot && ($dot >= $len - 2 || $dot < $len - 5) ||
                        strpos($word, "..") !== false || is_numeric($word) || is_numeric($word{$len-1}) || is_numeric($word{$dot+1}))
                    $dot = false;
    
                if($at && $dot)
                    // e-mail address
                    $ret .= "<A HREF=\"mailto:$word\">" . $words[$i] . "</A> ";
                else if($dot) {
                    // web address
                    $ret .= "<A TARGET=\"_blank\" HREF=\"";
                    if(!$prefix)
                        $ret .= "http://";
                    $ret .= $word . "\">" . $words[$i] . "</A> ";
                } else
                    $ret .= $words[$i] . " ";
            }
    
            return trim($ret);
        } else
            return htmlentities($name);
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
    
    private function composeTime($fromTime, $toTime) {
        return $fromTime . "-" . $toTime;
    }
    
    // add track from an ajax post from client. return new track row
    // upon success else 400 response.
    public function handleAddTrack() {
        $retVal = [];
        $tag = $_REQUEST["tag"];
        $playlist = trim($_REQUEST["playlist"]);
        $artist = trim($_REQUEST["artist"]);
        $album = trim($_REQUEST["album"]);
        $track = trim($_REQUEST["track"]);
        $label = trim($_REQUEST["label"]);
        $_REQUEST["created"] = '';
        $isSeparator = $artist == IPlaylist::SPECIAL_TRACK;

        $updateStatus = 0; //failure
        $retMsg = 'success';
        if ($isSeparator == False && 
            (empty($playlist) || empty($artist) || empty($album) || 
             empty($track) || empty($label))) {
            $retMsg = "Required field missing: -" . $playlist . "-, -" . $artist . "-, -" . $album . "-, -" . $track . "-, -" . $label . "-";
        } else {
            $updateStatus = Engine::api(IPlaylist::class)->insertTrack($playlist, $tag, $artist, $track, $album, $label, True);

            if ($updateStatus === 0) {
                $retMsg = 'DB update error';
             } else {
                if ($updateStatus === 2) {
                    $date = new \DateTime('now');
                    $created = $date->format('D M d, Y G:i');
                    $_REQUEST["created"] = $created;
                }
                $newRow = $this->makeTrackRow($_REQUEST, $playlist, True);
                $retVal['row'] = $newRow;
            }
        }

        $retVal['status'] = $retMsg;
        if ($updateStatus == 0)
            http_response_code(400);

        echo json_encode($retVal);
    }

    public static function showStartTime($timeRange) {
        $retVal = "??";
        $timeAr = explode("-", $timeRange);
        if (count($timeAr) == 2)
           $retVal = self::hourToAMPM($timeAr[0]);

        return $retVal;
    }

    public static function showEndTime($timeRange) {
        $retVal = "??";
        $timeAr = explode("-", $timeRange);
        if (count($timeAr) == 2)
           $retVal = self::hourToAMPM($timeAr[1]);

        return $retVal;
    }
    
    public static function hourToAMPM($hour, $full=0) {
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
    
    private function emitEditList($editlist) {
        $description = $_REQUEST["description"];
        $date = $_REQUEST["date"];
        $time = $_REQUEST["time"];
        $airname = $_REQUEST["airname"];
        $playlist = $_REQUEST["playlist"];
        $button = $_REQUEST["button"];
        $fromtime = $_REQUEST["fromtime"];
        $totime = $_REQUEST["totime"];
    
        if($editlist)
            $playlist = $editlist;
        if($button == " Setup New Airname... ") {
            $displayForm = 1;
            $djname = trim($_REQUEST["djname"]);
            if($_REQUEST["newairname"] == " Add Airname " && $djname) {
                // Insert new airname
                $success = Engine::api(IDJ::class)->insertAirname($djname, $this->session->getUser());
                if($success > 0) {
                    $airname = Engine::lastInsertId();
                    $button = "";
                    $displayForm = 0;
                } else
                    $errorMessage = "<B><FONT CLASS=\"error\">Airname '$djname' is invalid or already exists.</FONT></B>";
            }
            if ($displayForm) {
    ?>
    <P CLASS="header">Add New Airname</P>
    <?php echo $errorMessage; ?>
    <FORM ACTION="?" METHOD=POST>
    <TABLE CELLPADDING=2 CELLSPACING=0>
      <TR>
        <TD ALIGN=RIGHT>Airname:</TD>
        <TD><INPUT TYPE=TEXT NAME=djname CLASS=input SIZE=30></TD>
      </TR>
      <TR>
        <TD>&nbsp;</TD>
        <TD><INPUT TYPE=SUBMIT NAME="newairname" VALUE=" Add Airname "></TD>
      </TR>
    </TABLE>
    <INPUT TYPE=HIDDEN NAME=button VALUE=" Setup New Airname... ">
    <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
    <INPUT TYPE=HIDDEN NAME=action VALUE="<?php echo $playlist?'editList':'newList';?>">
    <INPUT TYPE=HIDDEN NAME=playlist VALUE="<?php echo $playlist;?>">
    <INPUT TYPE=HIDDEN NAME=description VALUE="<?php echo htmlentities(stripslashes($description));?>">
    <INPUT TYPE=HIDDEN NAME=date VALUE="<?php echo htmlentities(stripslashes($date));?>">
    <INPUT TYPE=HIDDEN NAME=time VALUE="<?php echo htmlentities(stripslashes($time));?>">
    <INPUT TYPE=HIDDEN NAME=fromtime VALUE="<?php echo htmlentities(stripslashes($fromtime));?>">
    <INPUT TYPE=HIDDEN NAME=totime VALUE="<?php echo htmlentities(stripslashes($totime));?>">
    <INPUT TYPE=HIDDEN NAME=validate VALUE="y">
    </FORM>
    <?php 
                UI::setFocus("djname");
                return;
            }
        }
        if($_REQUEST["validate"] == "edit") {
            list($year, $month, $day) = explode("-", $date);
            if(strlen($fromtime) && strlen($totime))
                $time = $this->composeTime($fromtime, $totime);

            if(checkdate($month, $day, $year) &&
                    ($time != "") && ($description != "")) {
                // Success - Run the query
                if($playlist) {
                    $success = Engine::api(IPlaylist::class)->updatePlaylist(
                            $playlist, $date, $time, $description, $airname);
                    $this->action = "editListEditor";
                    $this->emitEditor();
                } else {
                    $success = Engine::api(IPlaylist::class)->insertPlaylist(
                             $this->session->getUser(),
                             $date, $time, $description, $airname);
                    $_REQUEST["playlist"] = Engine::lastInsertId();
                    $this->action = "newListEditor";
                    $this->emitEditor();
                }
                return;
            } else
                echo "<B><FONT CLASS=\"error\">Ensure fields are not blank and date is valid.</FONT></B>\n";
        }
        if($playlist) {
            echo "<P CLASS=\"header\">Edit Show Information</P>\n";
            $row = Engine::api(IPlaylist::class)->getPlaylist($playlist);
            $description = $row[0];
            $date = $row[1];
            $time = $row[2];
            if(!$airname)
                $airname = $row[3];
        } else {
            echo "<P CLASS=\"header\">Enter Show Information</P>\n";
            $date = date("Y-m-d");
        }
    ?>
    <FORM ACTION="?" METHOD=POST>
    <TABLE CELLPADDING=2 CELLSPACING=0>
      <TR>
        <TD ALIGN=RIGHT>Show Name:</TD>
        <TD><INPUT TYPE=TEXT NAME=description VALUE="<?php echo htmlentities(stripslashes($description));?>" CLASS=input SIZE=30></TD>
      </TR><TR>
        <TD ALIGN=RIGHT>Date:</TD>
        <TD><INPUT TYPE=TEXT NAME=date VALUE="<?php echo htmlentities(stripslashes($date));?>" CLASS=input SIZE=15></TD>
      </TR><TR>
        <TD ALIGN=RIGHT>Time Slot:</TD>
    <?php 
        if(strlen($fromtime) && strlen($totime)
                       || $this->extractTime($time, $fromtime, $totime)) {
            // Emit the time in canonical format
            echo "    <TD><SELECT NAME=fromtime>\n";
            for($i=0; $i<24; $i++) {
                for($j=0; $j<60; $j+=30) {
                    $ovalue = sprintf("%02d%02d", $i, $j);
                    $selected = ($ovalue == $fromtime)?" SELECTED":"";
                    echo "          <OPTION VALUE=\"$ovalue\"$selected>".self::hourToAMPM($ovalue, 1)."\n";
                }
            }
            echo "        </SELECT> - <SELECT NAME=totime>\n";
            for($i=0; $i<24; $i++) {
                for($j=0; $j<60; $j+=30) {
                    $ovalue = sprintf("%02d%02d", $i, $j);
                    $selected = ($ovalue == $totime)?" SELECTED":"";
                    echo "          <OPTION VALUE=\"$ovalue\"$selected>".self::hourToAMPM($ovalue, 1)."\n";
                }
            }
            echo "        </SELECT></TD>\n";
        } else
            // Emit the time in legacy format
            echo "    <TD><INPUT TYPE=TEXT NAME=time VALUE=\"". htmlentities(stripslashes($time)) . "\" CLASS=input SIZE=15></TD>\n";
    ?>
      </TR><TR>
        <TD ALIGN=RIGHT>DJ Airname:</TD>
        <TD><SELECT NAME=airname>
    <?php 
        $records = Engine::api(IDJ::class)->getAirnames($this->session->getUser());
        while ($records && ($row = $records->fetch())) {
           $selected = ($row[0] == $airname)?" SELECTED":"";
           echo "            <OPTION VALUE=\"" . $row[0] ."\"" . $selected .
                ">$row[1]\n";
        }
        $selected = $airname?"":" SELECTED";
        echo "            <OPTION VALUE=\"\"$selected>(unpublished playlist)\n";
    ?>
            </SELECT><INPUT TYPE=SUBMIT NAME=button VALUE=" Setup New Airname... "></TD>
      </TR><TR>
        <TD>&nbsp;</TD>
    <?php 
        if($playlist)
            echo "    <TD><INPUT TYPE=SUBMIT onClick=\"return ConfirmTime();\" VALUE=\" Next &gt;&gt; \"></TD>\n";
        else
            echo "    <TD><INPUT TYPE=SUBMIT onClick=\"return ConfirmTime();\" VALUE=\" Create \"></TD>\n";
    ?>
      </TR>
    </TABLE>
    <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
    <INPUT TYPE=HIDDEN NAME=action VALUE="<?php echo $playlist?'editList':'newList';?>">
    <INPUT TYPE=HIDDEN NAME=playlist VALUE="<?php echo $playlist;?>">
    <INPUT TYPE=HIDDEN NAME=validate VALUE="edit">
    </FORM>
    <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript"><!--
    <?php ob_start([\JSMin::class, 'minify']); ?>
    function ConfirmTime() {
      return document.forms[0].fromtime.value != '0000' ||
          document.forms[0].totime.value != '0000' ||
          confirm("If your show is really on midnight - midnight, click 'OK'; otherwise, click 'Cancel' and set the correct time.");
    }
    <?php
        ob_end_flush();
    ?>
    // -->
    </SCRIPT>
    <?php 
        UI::setFocus("description");
    }
    
    private function deletePlaylist($playlist) {
        Engine::api(IPlaylist::class)->deletePlaylist($playlist);
    }
    
    private function emitConfirm($name, $message, $action, $rtaction="") {
    ?>
    <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript"><!--
    <?php ob_start([\JSMin::class, 'minify']); ?>
    function Confirm<?php echo $name; ?>()
    {
    <?php if($rtaction) { ?>
      if(document.forms[0].<?php echo $rtaction; ?>.selectedIndex >= 0) {
        action = document.forms[0].<?php echo $rtaction; ?>.options[document.forms[0].<?php echo $rtaction; ?>.selectedIndex].value;
      } else {
        return;
      }
    <?php } ?>
      answer = confirm("<?php echo $message; ?>");
      if(answer != 0) {
        location = "<?php 
           echo "?$action";
           if($rtaction)
              echo "&$rtaction=\" + action";
           else
              echo "\""; ?>;
      }
    }
    <?php
        ob_end_flush();
    ?>
    // -->
    </SCRIPT>
    <?php 
    }
    
    private function restorePlaylist($playlist) {
        Engine::api(IPlaylist::class)->restorePlaylist($playlist);
    }
    
    private function getDeletedPlaylistCount() {
        return Engine::api(IPlaylist::class)->getDeletedPlaylistCount($this->session->getUser());
    }

    public function emitEditListNew() {
        $this->emitEditList(0);
    }
    
    public function emitEditListSel() {
        $playlist = $_REQUEST["playlist"];
        if(($_REQUEST["button"] == " Delete ") && $playlist)
            $this->deletePlaylist($playlist);
        else if($_REQUEST["validate"] && $playlist) {
            if($this->subaction == "restore") {
                $this->restorePlaylist($playlist);
            } else {
                $this->emitEditList($playlist);
                return;
            }
        }

        $menu[] = [ "u", "", "Playlists", "emitEditListSelNormal" ];
        if($this->getDeletedPlaylistCount())
            $menu[] = [ "u", "restore", "Deleted Playlists", "emitEditListSelDeleted" ];
        else
            $this->subaction = "";
        $this->dispatchSubaction($this->action, $this->subaction, $menu);
    }
    
    // CheckBrowserCaps
    //
    // Check browser's capabilities:
    //     noTables property set true if browser does not support tables
    //
    private function checkBrowserCaps() {
        // For now, we naively assume all browsers support tables except Lynx.
        $this->noTables = (substr($_SERVER["HTTP_USER_AGENT"], 0, 5) == "Lynx/");
    }

    public function emitEditListSelNormal() {
    ?>
    <FORM ACTION="?" METHOD=POST>
    <B>Select Playlist:</B><BR>
    <TABLE CELLPADDING=0 BORDER=0><TR><TD>
    <SELECT NAME=playlist SIZE=10>
    <?php 
        // Setup $noTables for handling of 'Delete' function
        $this->checkBrowserCaps();
    
        $records = Engine::api(IPlaylist::class)->getListsSelNormal($this->session->getUser());
        while($records && ($row = $records->fetch()))
            echo "  <OPTION VALUE=\"$row[0]\">$row[1] -- $row[3]\n";
    ?>
    </SELECT></TD></TR>
    <TR><TD>
    <INPUT TYPE=SUBMIT NAME=button VALUE=" Edit ">&nbsp;&nbsp;&nbsp;
    <?php if($this->noTables) { ?>
    <INPUT TYPE=SUBMIT NAME=button VALUE=" Delete ">
    <?php } else { ?>
    <INPUT TYPE=BUTTON NAME=button onClick="ConfirmDelete()" VALUE=" Delete ">
    <?php } ?>
    <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
    <INPUT TYPE=HIDDEN NAME=action VALUE="editList">
    <INPUT TYPE=HIDDEN NAME=validate VALUE="y">
    </TD></TR></TABLE>
    </FORM>
    <?php 
        if(!$this->noTables)
            $this->emitConfirm("Delete",
                        "This will delete the selected playlist.  Are you sure you want to do this?",
                        "button=+Delete+&session=".$this->session->getSessionID()."&action=editList&validate=y",
                        "playlist");
        UI::setFocus("playlist");
    }
    
    private function makeEditDiv($row, $playlist) {
        $sessionId = $this->session->getSessionID();
        $href = "?session=" . $sessionId . "&amp;playlist=" . $playlist . "&amp;id=" . 
                $row["id"] . "&amp;action=" . $this->action . "&amp;";
        $editLink = "<A CLASS='songEdit' HREF='" . $href ."seq=editTrack'>&#x270f;</a>";
        //NOTE: in edit mode the list is ordered new to old, so up makes it 
        //newer in time order & vice-versa.
        $upLink = "<A CLASS='songUp' HREF='" . $href ."seq=upTrack' />";
        $downLink = "<A CLASS='songDown' HREF='" . $href ."seq=downTrack' />";
        $retVal = "<div class='songManager'>" . $upLink . $downLink . $editLink . "</div>";
        return $retVal;
    }

    public function emitEditListSelDeleted() {
    ?>
    <FORM ACTION="?" METHOD=POST>
    <B>Select Playlist to Restore:</B><BR>
    <TABLE CELLPADDING=0 BORDER=0><TR><TD>
    <SELECT NAME=playlist SIZE=10>
    <?php 
        // Setup $this->noTables for handling of 'Delete' function
        $this->checkBrowserCaps();
    
        $records = Engine::api(IPlaylist::class)->getListsSelDeleted($this->session->getUser());
        while($records && ($row = $records->fetch())) {
            echo "  <OPTION VALUE=\"$row[0]\">$row[1] -- $row[3] (expires $row[4])\n";
        }
    ?>
    </SELECT></TD></TR>
    <TR><TD>
    <INPUT TYPE=SUBMIT NAME=button VALUE=" Restore ">
    <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
    <INPUT TYPE=HIDDEN NAME=action VALUE="editList">
    <INPUT TYPE=HIDDEN NAME=subaction VALUE="restore">
    <INPUT TYPE=HIDDEN NAME=validate VALUE="y">
    </TD></TR></TABLE>
    <P><B>Note: Deleted playlists automatically expire on the date indicated.</B></P></FORM>
    <?php 
        UI::setFocus("playlist");
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
        $this->emitPlaylistBanner($playlistId, $playlist);
        $this->emitTrackEditor($playlistId);
    }
    
    private function emitTrackEditor($playlistId) {
    ?>
        <div class='track-editor'>
            <input id='track-session' type='hidden' value='session VALUE="<?php echo $this->session->getSessionID(); ?>'>
            <input id='track-playlist' type='hidden' value='<?php echo $playlistId; ?>'>
            <div>
                <label>Type:</label>
                <select id='track-type-pick'>
                   <option value='tag-entry'>Tag ID</option>
                   <option value='manual-entry'>Manual</option>
                </select>
            </div>
            <div id='track-entry'>
                <div id='tag-entry'>
                    <div>
                        <label>Tag:</label>
                        <input id='track-tag' />
                        <span class='track-info' id='tag-status'>Enter tag ID followed by Tab or Enter</span>
                    </div>
                    <div>
                        <label>Track:</label>
                        <select id='track-title-pick'>
                        </select>
                    </div>
                    <div style='padding-bottom: 8px'>
                        <label>&nbsp;</label>
                        <span style='color:gray' id='tag-artist'></span>
                    </div>
                </div>
 
                <div id='manual-entry' class='zk-hidden' >
                    <div>
                        <label>Artist:</label>
                        <input id='track-artist' />
                    </div>
                    <div>
                        <label>Track:</label>
                        <input id='track-title' />
                    </div>
                    <div>
                        <label>Album:</label>
                        <input id='track-album' />
                    </div>
                    <div>
                        <label>Label:</label>
                        <input id='track-label' /}>
                    </div>
                </div>
            </div> <!-- track-entry -->
            <div>
                <label></label>
                <button DISABLED id='track-submit' >Add Track</button>
                <button style='margin-left:17px;' id='track-separator'>Add Separator</button>
            </div>
        </div> <!-- track-editor -->
        <hr>

        <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript"><!--
        function setFocus(){}

        $().ready(function(){
            //NOTE: must match php definition
            const SPECIAL_TRACK = "~~~~~~~~";
            var tagId = "";
            var trackList = []; //populated by ajax query

            $("#track-type-pick").val('tag-entry');
            $("#track-tag").focus();

            function setAddButtonState(enableIt) {
                $("#track-submit").prop("disabled", !enableIt);
            }
            
            function clearUserInput(clearTagInfo) {
                var mode = $("#track-type-pick").val();
                $("#manual-entry input").val('');
                $("#track-title-pick").val('0');
                $("#tag-status").text('');
                $("#tag-artist").text('');
                setAddButtonState(false);

                if (clearTagInfo) {
                    $("#track-tag").val('').focus();
                    $("#track-title-pick").find('option').remove();
                    trackList = [];
                    tagId = "";
                }

                if (mode == 'manual-entry') {
                    $('#track-artist').focus();
                }
            }

            // return true if have all required fields.
            function haveAllUserInput()  {
                var isEmpty = false;
                $("#manual-entry input").each(function() {
                    isEmpty = isEmpty || $(this).val().length == 0;
                });

                return !isEmpty;
            }

            function showUserError(msg) {
                $('#tag-status').text(msg).removeClass('track-info').addClass('track-error');
            }

            function getDiskInfo(id) {
                const INVALID_TAG = 100;
                $("#track-title-pick").find('option').remove();
                clearUserInput(false);
                tagId = ""
                var url = "zkapi.php?method=getTracksRq&json=1&key=" + id;
                $.ajax({
                    dataType : 'json',
                    type: 'GET',
                    accept: "application/json; charset=utf-8",
                    url: url,
                }).done(function (diskInfo) { //TODO: success?
                    if (diskInfo.code == INVALID_TAG) {
                        showUserError(id + ' is not a valid tag.');
                        return;
                    }

                    tagId = id;
                    var options = "<option value=''>Select Track</option>";
                    trackList = diskInfo.data;
                    for (var i=0; i < trackList.length; i++) {
                        var track = trackList[i];
                        var artist = track.artist ? track.artist + ' - ' : '';
                        options += `<option value='${i}' >${i+1}. ${artist} ${track.track}</option>`;
                    }
                    $("#track-title-pick").find('option').remove().end().append(options);
                    $("#track-artist").val(diskInfo.artist);
                    $("#track-label").val(diskInfo.label);
                    $("#track-album").val(diskInfo.album);
                    $("#track-title").val("");
                    $("#track-submit").attr("disabled");
                    $("#track-submit").prop("disabled", true);
                    $("#tag-artist").text(diskInfo.artist  + '-' + diskInfo.album);

                }).fail(function (jqXHR, textStatus, errorThrown) {
                    showUserError('Ajax error: ' + textStatus);
                });
            }

            $("#track-type-pick").on('change', function() {
                var newType = this.value;
                clearUserInput(true);
                $("#track-entry > div").addClass("zk-hidden");
                $("#" + newType).removeClass("zk-hidden");
            });


            $("#track-title-pick").on('change', function() {
                var index= parseInt(this.value);
                var track = trackList[index];
                $("#track-title").val(track.track);
                // collections have an artist per track.
                if (track.artist)
                    $("#track-artist").val(track.artist);

                setAddButtonState(true);
            });

            $("#manual-entry input").on('input', function() {
                var haveAll = haveAllUserInput();
                setAddButtonState(haveAll);
            });
            
            function submitTrack(artist) {
                var label, album, title;

                if (artist !== SPECIAL_TRACK) {
                    label =  $("#track-label").val();
                    album =  $("#track-album").val();
                    title =  $("#track-title").val();
                }

                var postData = {
                    playlist: $("#track-playlist").val(),
                    session: $("#track-session").val(),
                    tag: $("#track-tag").val(),
                    artist: artist,
                    label: label,
                    album: album,
                    track: title,
                };

                $.ajax({
                    type: "POST",
                    url: "?action=addTrack",
                    dataType : 'json',
                    accept: "application/json; charset=utf-8",
                    data: postData,
                    success: function(respObj) {
                        $(".playlistTable > tbody").prepend(respObj.row);
                        clearUserInput(true);
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        clearUserInput(true);
                        showUserError("Your track was not saved: " + textStatus);
                    }
                });
            }

            $("#track-submit").click(function(e) {
                // double check that we have everything.
                if (haveAllUserInput() == false) {
                    alert('A required field is missing');
                    return;
                }
                var artist = $("#track-artist").val();
                submitTrack(artist);
            });

            $("#track-separator").click(function(e) {
                submitTrack(SPECIAL_TRACK);
            });

            $("#track-tag").on('keyup', function(e) {
                showUserError('');
                if (e.keyCode == 13) {
                    $(this).blur();
                    $('#track-title-pick').focus();
                }
            });

            $("#track-title-pick").on('focus', function() {
                var newId = $("#track-tag").val()
                if (newId.length > 0 && newId != tagId)
                    getDiskInfo(newId);
            });
        });
        // -->
        </SCRIPT>
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
            echo "      <SELECT NAME=track>\n";
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
    
    private function emitEditForm($playlist, $id, $album, $track) {
      // Setup $this->noTables for handling of 'Delete' function
      $this->checkBrowserCaps();
      $sep = $id && substr($album["artist"], 0, strlen(IPlaylist::SPECIAL_TRACK)) == IPlaylist::SPECIAL_TRACK;
    ?>
      <P CLASS="header"><?php echo $id?"Editing highlighted":"Adding";?> <?php echo $sep?"set separator":"track"?>:</P>
      <FORM ACTION="?" METHOD=POST>
    <?php if($sep) { ?>
      <INPUT TYPE=HIDDEN NAME=separator VALUE="true">
      <TABLE>
    <?php } else if($album == "" || $album["tag"] == "") { ?>
      <TABLE CELLPADDING=0 CELLSPACING=0>
        <TR>
          <TD ALIGN=RIGHT>Artist:</TD>
          <TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=artist MAXLENGTH=80 VALUE="<?php echo htmlentities($album?$album["artist"]:"");?>" CLASS=input SIZE=40></TD>
        </TR>
        <TR>
          <TD ALIGN=RIGHT>Track:</TD>
          <TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=track MAXLENGTH=80 VALUE="<?php echo htmlentities($track);?>" CLASS=input SIZE=40></TD>
        </TR>
        <TR>
          <TD ALIGN=RIGHT>Album:</TD>
          <TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=album MAXLENGTH=80 VALUE="<?php echo htmlentities($album?$album["album"]:"");?>" CLASS=input SIZE=40></TD>
        </TR>
        <TR>
          <TD ALIGN=RIGHT>Label:</TD>
          <TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=label MAXLENGTH=80 VALUE="<?php echo htmlentities($album?$album["label"]:"");?>" CLASS=input SIZE=40></TD>
        </TR>
    <?php } else { ?>
      <INPUT TYPE=HIDDEN NAME=artist VALUE="<?php echo htmlentities($album["artist"]);?>">
      <INPUT TYPE=HIDDEN NAME=album VALUE="<?php echo htmlentities($album["album"]);?>">
      <INPUT TYPE=HIDDEN NAME=otrack VALUE="<?php echo htmlentities(stripslashes($track));?>">
      <INPUT TYPE=HIDDEN NAME=label VALUE="<?php echo htmlentities($album["label"]);?>">
      <TABLE>
        <TR><TD ALIGN=RIGHT>Artist:</TD>
            <TH ALIGN=LEFT><?php echo htmlentities($album["artist"]); ?></TH></TR>
        <TR><TD ALIGN=RIGHT>Album:</TD>
            <TH ALIGN=LEFT><?php echo htmlentities($album["album"]); ?></TH></TR>
        <TR><TD ALIGN=RIGHT>Track:</TD>
            <TD ALIGN=LEFT><?php $this->emitTrackField($album["tag"], $track, $id); ?></TD>
        </TR>
    <?php } ?>
        <TR>
          <TD>&nbsp;</TD>
          <TD>
    <?php if($id) { ?>
              <INPUT TYPE=SUBMIT NAME=button VALUE="  Save  ">&nbsp;&nbsp;&nbsp;
    <?php     if($this->noTables) { ?>
              <INPUT TYPE=SUBMIT NAME=button VALUE=" Delete ">
    <?php     } else { ?>
              <INPUT TYPE=BUTTON NAME=button onClick="ConfirmDelete()" VALUE=" Delete ">
    <?php     } ?>
              <INPUT TYPE=HIDDEN NAME=id VALUE="<?php echo $id;?>">
    <?php } else { ?>
              <INPUT TYPE=SUBMIT VALUE="  Next &gt;&gt;  ">
    <?php } ?>
              <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
              <INPUT TYPE=HIDDEN NAME=playlist VALUE="<?php echo $playlist;?>">
              <INPUT TYPE=HIDDEN NAME=action VALUE="<?php echo $this->action;?>">
              <INPUT TYPE=HIDDEN NAME=tag VALUE="<?php echo $album["tag"];?>">
              <INPUT TYPE=HIDDEN NAME=seq VALUE="editForm">
          </TD>
      </TR>
      </TABLE>
      </FORM>
    <?php 
        if($id && !$this->noTables)
            $this->emitConfirm("Delete",
                        "Delete this track?",
                        "button=+Delete+&session=".$this->session->getSessionID()."&action=$this->action&playlist=$playlist&id=$id&seq=editForm");
        if($sep)
            UI::setFocus();
        else
            UI::setFocus(($album == "" || $album["tag"] == "")?"artist":"track");
    }
    
    private function emitTrackForm($playlist, $id, $album, $track) {
    ?>
      <P CLASS="header"><?php echo $id?"Editing highlighted":"Adding";?> track:</P>
      <FORM ACTION="?" METHOD=POST>
      <INPUT TYPE=HIDDEN NAME=artist VALUE="<?php echo htmlentities($album["artist"]);?>">
      <INPUT TYPE=HIDDEN NAME=album VALUE="<?php echo htmlentities($album["album"]);?>">
      <INPUT TYPE=HIDDEN NAME=label VALUE="<?php echo htmlentities($album["label"]);?>">
      <TABLE>
        <TR><TD ALIGN=RIGHT>Artist:</TD>
            <TH ALIGN=LEFT><?php echo htmlentities($album["artist"]); ?></TH></TR>
        <TR><TD ALIGN=RIGHT>Album:</TD>
            <TH ALIGN=LEFT><?php echo htmlentities($album["album"]); ?></TH></TR>
        <TR><TD ALIGN=RIGHT>Track:</TD>
            <TD><INPUT TYPE=TEXT NAME=track MAXLENGTH=80 CLASS=input VALUE="<?php echo htmlentities($track);?>"></TD></TR>
        <TR><TD></TD><TD>
    <?php if($id) { ?>
          <INPUT TYPE=SUBMIT VALUE="  Save  ">
          <INPUT TYPE=HIDDEN NAME=id VALUE="<?php echo $id;?>">
    <?php } else { ?>
          <INPUT TYPE=SUBMIT VALUE="  Next &gt;&gt;  ">
    <?php } ?>
          <INPUT TYPE=HIDDEN NAME=tag VALUE="<?php echo $album["tag"];?>">
          <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
          <INPUT TYPE=HIDDEN NAME=action VALUE="<?php echo $this->action;?>">
          <INPUT TYPE=HIDDEN NAME=playlist VALUE="<?php echo $playlist;?>">
          <INPUT TYPE=HIDDEN NAME=seq VALUE="editForm">
        </TD></TR>
      </TABLE>
      </FORM>
    <?php 
        UI::setFocus("track");
    }
    
    private function insertTrack($playlist, $tag, $artist, $track, $album, $label, $wantTimestamp) {
        // Run the query
        $success = Engine::api(IPlaylist::class)->insertTrack($playlist,
                     $tag, $artist, $track, $album, $label, $wantTimestamp);    
    }
    
    private function updateTrack($playlistId, $id, $tag, $artist, $track, $album, $label) {
        // Run the query
        Engine::api(IPlaylist::class)->updateTrack($playlistId, $id, $tag, $artist, $track, $album, $label);
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
        echo "      <TH ALIGN=RIGHT VALIGN=TOP><A CLASS=\"sub\" HREF=\"#top\" onClick='window.open(\"$script&amp;session=".$this->session->getSessionID()."&amp;playlist=$playlist&amp;format=html\")'>Print</A></TH>\n    </TR>\n  </TABLE>\n";
    }
    
    private function insertSetSeparator($playlist) {
        $specialTrack = IPlaylist::SPECIAL_TRACK;
        $this->insertTrack($playlist, 0, $specialTrack, $specialTrack, $specialTrack, $specialTrack, true);
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
    ?>
    <TABLE CELLPADDING=0 CELLSPACING=0 WIDTH="100%">
    <TR><TD>
    <?php 
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
                    // Secondary search for label name
                    $lab = Engine::api(ILibrary::class)->search(ILibrary::LABEL_PUBKEY, 0, 1, $albumrec[0]["pubkey"]);
                    if(sizeof($lab)){
                        $albumrec[0]["label"] = $lab[0]["name"];
                    } else
                        $albumrec[0]["label"] = "(Unknown)";
                    $this->emitEditForm($playlist, $id, $albumrec[0], $track);
                }
            } else
                $this->emitEditForm($playlist, $id, "", "");
            break;
        case "editForm":
            if(($button == " Delete ") && $id) {
                $this->deleteTrack($id);
                $id = "";
                $this->emitTagForm($playlist, "");
            } else if($separator) {
                $id = "";
                $this->emitTagForm($playlist, "");
            } else if(($artist == "") || ($album == "") ||
                            (($label == "") && ($tag == ""))) {
                $albuminfo = ["tag"=>$tag,
                              "artist"=>stripslashes($artist),
                              "album"=>stripslashes($album),
                              "label"=>stripslashes($label)];
                $this->emitEditForm($playlist, $id, $albuminfo, stripslashes($track));
            } else if(($track == "") && ($ctrack == "")) {
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
                    $this->updateTrack($playlist, $id, $tag, $artist, $track, $album, $label);
                    $id = "";
                } else
                    $this->insertTrack($playlist, $tag, $artist, $track, $album, $label, true);
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
        case "upTrack":
            Engine::api(IPlaylist::class)->moveTrackUpDown($playlist, $id, 1);
            $this->emitTagForm($playlist, "");
            break;
        case "downTrack":
            Engine::api(IPlaylist::class)->moveTrackUpDown($playlist, $id, 0);
            $this->emitTagForm($playlist, "");
            break;
        default:
            $this->emitTagForm($playlist, "");
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
       $menu[] = [ "u", "", "Export Playlist", "emitExportList" ];
       $menu[] = [ "u", "import", "Import Playlist", "emitImportList" ];
       $this->dispatchSubaction($this->action, $this->subaction, $menu);
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
       <INPUT TYPE=RADIO NAME=format VALUE=csv CHECKED>CSV
       <INPUT TYPE=RADIO NAME=format VALUE=xml>XML
       <INPUT TYPE=RADIO NAME=format VALUE=html>HTML
    </TD></TR>
    <TR><TD>
    <INPUT TYPE=SUBMIT VALUE=" Export Playlist ">
    <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
    <INPUT TYPE=HIDDEN NAME=target VALUE="export">
    </TD></TR></TABLE>
    </FORM>
    <?php 
        UI::setFocus("playlist");
    }
    
    private static function zkfeof(&$fd, &$tempbuf) {
        return feof($fd) && !strlen($tempbuf);
    }
    
    private static function zkfgets(&$fd, $buflen, &$tempbuf) {
        // Continue reading until we hit a CR or LF
        for($posn = strpos($tempbuf, "\n"), $posr = strpos($tempbuf, "\r");
                !is_int($posn) && !is_int($posr) && !feof($fd);
                $posn = strpos($tempbuf, "\n"), $posr = strpos($tempbuf, "\r"))
            $tempbuf .= fread($fd, $buflen);
    
        if(is_int($posn) && is_int($posr))
            // We hit both a CR and LF; use the first one 
            $pos = min($posn, $posr);
        else
            // We hit either CR or LF alone, or neither
            $pos = $posn + $posr;
    
        if($pos) {
            // We hit a CR or LF; return the line
            $out = substr($tempbuf, 0, $pos);
    
            // Advance buf past CR, LF, or CRLF to next line
            $tempbuf = substr($tempbuf,
                          ($posr && substr($tempbuf, $pos+1, 1) == "\n")?($pos+2):($pos+1));
            return $out;
        } else {
            // EOF; return buffer remains, if any
            $out = $tempbuf;
            $tempbuf = "";
            return $out;
        }
    }
    
    public function emitImportList() {
        $validate = $_REQUEST["validate"];
        $description = $_REQUEST["description"];
        $date = $_REQUEST["date"];
        $time = $_REQUEST["time"];
        $airname = $_REQUEST["airname"];
        $playlist = $_REQUEST["playlist"];
        $button = $_REQUEST["button"];
        $djname = $_REQUEST["djname"];
        $newairname = $_REQUEST["newairname"];
        $userfile = $_FILES['userfile']['tmp_name'];
        $fromtime = $_REQUEST["fromtime"];
        $totime = $_REQUEST["totime"];
    
        if($button == " Setup New Airname... ") {
            $displayForm = 1;
            $djname = trim($djname);
            if($newairname == " Add Airname " && $djname) {
                // Insert new airname
                $success = Engine::api(IDJ::class)->insertAirname($djname, $this->session->getUser());
                if($success > 0) {
                    $airname = Engine::lastInsertId();
                    $button = "";
                    $displayForm = 0;
                } else
                    $errorMessage = "<B><FONT CLASS=\"error\">Airname '$djname' is invalid or already exists.</FONT></B>";
            }
            if ($displayForm) {
    ?>
    <P CLASS="header">Add New Airname</P>
    <?php echo $errorMessage; ?>
    <FORM ACTION="?" METHOD=POST>
    <TABLE CELLPADDING=2 CELLSPACING=0>
      <TR>
        <TD ALIGN=RIGHT>Airname:</TD>
        <TD><INPUT TYPE=TEXT NAME=djname SIZE=30></TD>
      </TR>
      <TR>
        <TD>&nbsp;</TD>
        <TD><INPUT TYPE=SUBMIT NAME="newairname" VALUE=" Add Airname "></TD>
      </TR>
    </TABLE>
    <INPUT TYPE=HIDDEN NAME=button VALUE=" Setup New Airname... ">
    <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
    <INPUT TYPE=HIDDEN NAME=action VALUE="importExport">
    <INPUT TYPE=HIDDEN NAME=subaction VALUE="import">
    <INPUT TYPE=HIDDEN NAME=playlist VALUE="<?php echo $playlist;?>">
    <INPUT TYPE=HIDDEN NAME=description VALUE="<?php echo htmlentities(stripslashes($description));?>">
    <INPUT TYPE=HIDDEN NAME=date VALUE="<?php echo htmlentities(stripslashes($date));?>">
    <INPUT TYPE=HIDDEN NAME=time VALUE="<?php echo htmlentities(stripslashes($time));?>">
    <INPUT TYPE=HIDDEN NAME=fromtime VALUE="<?php echo htmlentities(stripslashes($fromtime));?>">
    <INPUT TYPE=HIDDEN NAME=totime VALUE="<?php echo htmlentities(stripslashes($totime));?>">
    <INPUT TYPE=HIDDEN NAME=validate VALUE="y">
    </FORM>
    <?php 
                UI::setFocus("djname");
                return;
            }
        }
        if(!$date)
            $date = date("Y-m-d");
        list($year, $month, $day) = explode("-", $date);
        if(strlen($fromtime) && strlen($totime))
            $time = $this->composeTime($fromtime, $totime);
        if(!$userfile || $userfile == "none" ||
                $description == "" ||
                $time == "" ||
                !checkdate($month, $day, $year)) {
            if($validate == "edit")
                echo "<B><FONT CLASS=\"error\">Ensure fields are not blank and date is valid.</FONT></B><BR>\n";
    ?>
      <FORM ENCTYPE="multipart/form-data" ACTION="?" METHOD=post>
        <INPUT TYPE=HIDDEN NAME=action VALUE="importExport">
        <INPUT TYPE=HIDDEN NAME=subaction VALUE="import">
        <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
        <INPUT TYPE=HIDDEN NAME=validate VALUE="edit">
        <INPUT TYPE=HIDDEN NAME=MAX_FILE_SIZE VALUE=100000>
        <TABLE CELLPADDING=2 CELLSPACING=0>
          <TR>
            <TD ALIGN=RIGHT>Show Name:</TD>
            <TD><INPUT TYPE=TEXT NAME=description VALUE="<?php echo stripslashes($description);?>" SIZE=30></TD>
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
            <TD ALIGN=RIGHT>DJ Airname:</TD>
            <TD><SELECT NAME=airname>
    <?php 
            $records = Engine::api(IDJ::class)->getAirnames($this->session->getUser());
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
            <TD CLASS="sub">NOTE: File must be UTF-8 encoded and tab delimited,<BR>
                with one track per line.  Each line may contain either 4 or 5 columns:<BR><BR>
                &nbsp;&nbsp;&nbsp;&nbsp;<B>artist&nbsp; track&nbsp; album&nbsp; label</B> &nbsp;or<BR><BR>&nbsp;&nbsp;&nbsp;&nbsp;<B>artist&nbsp; track&nbsp; album&nbsp; tag&nbsp; label</B>,<BR><BR>
                where each column is separated by a tab character.<BR>
                Any file data not in this format will be ignored.</TD>
          </TR><TR>
            <TD>&nbsp;</TD>
            <TD><INPUT TYPE=submit VALUE=" Import Playlist "></TD>
          </TR>
        </TABLE>
      </FORM>
    <?php 
            UI::setFocus("description");
        } else {
            // Create the playlist
            $success = Engine::api(IPlaylist::class)->insertPlaylist($this->session->getUser(), $date, $time, $description, $airname);
            $playlist = Engine::lastInsertId();
    
            // Insert the tracks
            $count = 0;
            $fd = fopen($userfile, "r");
            while(!self::zkfeof($fd, $tempbuf)) {
                $line = explode("\t", self::zkfgets($fd, 1024, $tempbuf));
                if(count($line) == 4) {
                    // artist track album label
                    $this->insertTrack($playlist,
                                     0,               // tag
                                     trim($line[0]),  // artist
                                     trim($line[1]),  // track
                                     trim($line[2]),  // album
                                     trim($line[3]), false); // label
                    $count++;
                } else if(count($line) == 5) {
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
    
                            // Secondary search for label name
                            $lab = Engine::api(ILibrary::class)->search(ILibrary::LABEL_PUBKEY, 0, 1, $albumrec[0]["pubkey"]);
                            if(sizeof($lab))
                                $line[4] = $lab[0]["name"];
                            else
                                $line[4] = "(Unknown)";
                        }
                    }
    
                    $this->insertTrack($playlist,
                                     trim($line[3]),  // tag
                                     trim($line[0]),  // artist
                                     trim($line[1]),  // track
                                     trim($line[2]),  // album
                                     trim($line[4]), false); // label
                    $count++;
                }
            }
            // echo "<B>Imported $count tracks.</B>\n";
            fclose($fd);
            unset($_REQUEST["validate"]);

            $_REQUEST["playlist"] = $playlist;
            $this->action = "newListEditor";
        }
    }
    
    public function updateDJInfo() {
        $validate = $_REQUEST["validate"];
        $multi = $_REQUEST["multi"];
        $url = $_REQUEST["url"];
        $email = $_REQUEST["email"];
        $airname = $_REQUEST["airname"];
    
        if($validate && $airname) {
            // Update DJ info
    
            if(!strcmp($url, "http://"))
                $url = "";
    
            $success = Engine::api(IDJ::class)->updateAirname($url,
                     $email, $multi?0:$airname, $this->session->getUser());
            if($success >= 0) {
                echo "<B>Your profile has been updated.</B>\n";
                return;
            } else
                echo "<B><FONT CLASS=\"error\">Update failed.  Try again later.</FONT></B>";
            // fall through...
        }
        $results = Engine::api(IDJ::class)->getAirnames(
                     $this->session->getUser(), $airname);
        $airnames = array();
        while($results && ($row = $results->fetch()))
            $airnames[] = $row;
    
        switch(sizeof($airnames)) {
        case 0:
            // No airnames
    ?>
    <P><B><FONT CLASS="error">You have no published playlists or airnames.</FONT></B></P>
    <P>You must setup a DJ Airname for at least one playlist
       before you can update your profile.</P>
    <?php 
            UI::setFocus();
            break;
        case 1:
            // Only one airname; emit form
            $url = "http://";
    ?>
    <FORM ACTION="?" METHOD=POST>
    <P><B>Update website and e-mail for airname '<?php echo $airnames[0]['airname'];?>'</B></P>
    <TABLE CELLPADDING=2 BORDER=0>
      <TR><TD ALIGN=RIGHT>URL:</TD>
        <TD><INPUT TYPE=TEXT NAME=url VALUE="<?php echo $airnames[0]['url'];?>" CLASS=input SIZE=40 MAXLENGTH=80></TD></TR>
      <TR><TD ALIGN=RIGHT>e-mail:</TD>
        <TD><INPUT TYPE=TEXT NAME=email VALUE="<?php echo $airnames[0]['email'];?>" CLASS=input SIZE=40 MAXLENGTH=80></TD></TR>
    <?php 
            // As we know that multiple DJs are using the 'music' account
            // (tsk, tsk), let's supress the account update option for music.
            if($multi && $this->session->getUser() != "music" && $this->session->getUser() != "kzsu")
                echo "  <TR><TD>&nbsp</TD><TD><INPUT TYPE=CHECKBOX NAME=multi>&nbsp;Check here to apply this update to all of your DJ airnames</TD></TR>";
    ?>
      <TR><TD COLSPAN=2>&nbsp;</TD></TR>
      <TR><TD>&nbsp;</TD><TD><INPUT TYPE=SUBMIT VALUE="  Update  ">
              <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
              <INPUT TYPE=HIDDEN NAME=airname VALUE="<?php echo $airnames[0]['id'];?>">
              <INPUT TYPE=HIDDEN NAME=action VALUE="updateDJInfo">
              <INPUT TYPE=HIDDEN NAME=validate VALUE="y"></TD></TR>
    </TABLE>
    </FORM>
    <?php 
            UI::setFocus("url");
            break;
        default:
            // Multiple airnames; emit airname selection form
    ?>
    <FORM ACTION="?" METHOD=POST>
    <B>Select Airname:</B><BR>
    <TABLE CELLPADDING=0 BORDER=0><TR><TD>
    <SELECT NAME=airname SIZE=10>
    <?php 
            foreach($airnames as $row) {
                 echo "  <OPTION VALUE=\"$row[0]\">$row[1]\n";
            }
    ?>
    </SELECT></TD></TR>
    <TR><TD>
    <INPUT TYPE=SUBMIT VALUE=" Next &gt;&gt; ">
    <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
    <INPUT TYPE=HIDDEN NAME=action VALUE="updateDJInfo">
    <INPUT TYPE=HIDDEN NAME=multi VALUE="y">
    </TD></TR></TABLE>
    </FORM>
    <?php 
            UI::setFocus("airname");
            break;
        }
    }
    
    public function emitShowLink() {
        $validate = $_REQUEST["validate"];
        $playlist = $_REQUEST["playlist"];
        $airname = $_REQUEST["airname"];
    
        if($validate && ($playlist == "all" || $airname)) {
            $results = Engine::api(IDJ::class)->getAirnames($this->session->getUser(), $airname);
            $airnames = array();
            while($results && ($row = $results->fetch()))
                $airnames[] = $row;
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
    <SELECT NAME=airname SIZE=10>
    <?php 
                foreach($airnames as $row) {
                    echo "  <OPTION VALUE=\"$row[0]\">$row[1]\n";
                }
    ?>
    </SELECT></TD></TR>
    <TR><TD>
    <INPUT TYPE=SUBMIT VALUE=" Show URL ">
    <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
    <INPUT TYPE=HIDDEN NAME=action VALUE="showLink">
    <INPUT TYPE=HIDDEN NAME=validate VALUE="y">
    </TD></TR></TABLE>
    </FORM>
    <?php 
           }
           UI::setFocus("airname");
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
            UI::setFocus();
            return;
        }
    ?>
    <FORM ACTION="?" METHOD=POST>
    <B>Select Playlist:</B><BR>
    <TABLE CELLPADDING=0 BORDER=0><TR><TD>
    <SELECT NAME=playlist SIZE=10>
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
    <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
    <INPUT TYPE=HIDDEN NAME=action VALUE="showLink">
    <INPUT TYPE=HIDDEN NAME=validate VALUE="y">
    </TD></TR></TABLE>
    </FORM>
    <?php 
        UI::setFocus("playlist");
    }
    
    private function viewListGetAlbums(&$records, &$albums) {
        while($row = $records->fetch()) {
            $row["LABELNAME"] = $row["label"];
            $albums[] = $row;
        }
    }
    
    private function makeAlbumLink($row, $includeLabel) {
        $albumName = $row["album"];
        $labelSpan = "<span class='songLabel'> / " . $this->smartURL($row["label"]) . "</span>";
        if($row["tag"]) {
            $albumTitle = "<A HREF='?s=byAlbumKey&amp;n=" . UI::URLify($row["tag"]) .
                          "&amp;q=&amp;action=search&amp;session=" . $this->session->getSessionID() .
                          "' CLASS='nav'>".$albumName ."</A>";

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


    // converts "last, first" to "first last" 
    private function swapNames($fullName) {
       $retVal = $fullName;
       $names1 = explode(", ", $fullName);
       if (count($names1) >= 2) {
           $names2 = explode(" ", $names1[1]);
           $extras = array_slice($names2, 1);
           $retVal = $names2[0] . " " . $names1[0] . " " . join(" ",  $extras);
       }
       return $retVal;
    }

    private function makeTrackRow($row, $playlist, $editMode) {
              $retVal = "";
              $editCell = $editMode ?  $editCell = "<TD>" . 
                          $this->makeEditDiv($row, $playlist) . "</TD>" : "";

              $timeplayed = self::timestampToAMPM($row["created"]);

              if(substr($row["artist"], 0, strlen(IPlaylist::SPECIAL_TRACK)) == IPlaylist::SPECIAL_TRACK) {
                $retVal = "<TR class='songDivider'>".$editCell.
                          "<TD>".$timeplayed . "</TD><TD COLSPAN=4><HR></TD></TR>";
              } else {
                  $reviewCell = $row["REVIEWED"] ? $REVIEW_DIV : "";
                  $artistName = $this->swapNames($row["artist"]);
                  $albumLink = $this->makeAlbumLink($row, true);
                  $retVal = "<TR class='songRow'>" . $editCell .
                         "<TD>" . $timeplayed . "</TD>" .
                         "<TD>" . $this->smartURL($artistName) . "</TD>" .
                         "<TD>" . $this->smartURL($row["track"]) . "</TD>" .
                         "<TD>" . $reviewCell . "</TD>" .
                         "<TD>" . $albumLink . "</TD>" .
                      "</TR>\n"; 
              }
              return $retVal;
            }

    private function emitPlaylistBody($playlist, $editMode) {
        $REVIEW_DIV =  "<div class='albumReview'></div>";
        $header = $this->makePlaylistHeader($editMode);
        $editCell = "";
        echo "<TABLE class='playlistTable' CELLPADDING=1>";
        echo "<THEAD>" . $header . "</THEAD>";

        $records = Engine::api(IPlaylist::class)->getTracks($playlist, $editMode);
        $this->viewListGetAlbums($records, $albums);
        Engine::api(ILibrary::class)->markAlbumsReviewed($albums);

        echo "<TBODY>";
        if($albums != null && sizeof($albums) > 0) {
            foreach($albums as $index => $row) {
                echo $this->makeTrackRow($row, $playlist, $editMode);
            }
        }
        echo "</TBODY></TABLE>\n";
    }

    private function makeShowDateAndTime($row) {
        $showStart = self::showStartTime($row[2]);
        $showEnd = self::showEndTime($row[2]);
        $showDate = self::timestampToDate($row[1]);
        $showDateTime = $showDate . " " . $showStart . "-" . $showEnd;
        return $showDateTime;
    }

    private function emitPlaylistBanner($playlistId, $playlist) {
        $showName = $playlist['description'];
        $djId = $playlist['id'];
        $djName = $playlist['airname'];
        $showDateTime = $this->makeShowDateAndTime($playlist);

        // make print view header
        echo "<TABLE WIDTH='100%'><TR><TD ALIGN=RIGHT><A HREF='#top' " .
             "CLASS='nav' onClick=window.open('?target=export&amp;session=" . 
             $this->session->getSessionID() . "&amp;playlist=" . $playlistId . 
             "&amp;format=html')>Print View</A></TD></TR></TABLE>";

        $dateDiv = "<DIV>".$showDateTime."&nbsp;</div>";
        $djLink = "<A HREF='?action=viewDJ&amp;seq=selUser&amp;session=" . 
        $this->session->getSessionID() . "&amp;viewuser=$djId' CLASS='nav2'>$djName</A>";

        echo "<DIV CLASS='playlistBanner'>&nbsp;" . $showName . " with " . $djLink.$dateDiv . "</div>";
    }

    private function viewList($playlistId) {
        $row = Engine::api(IPlaylist::class)->getPlaylist($playlistId, 1);
        if( !$row) {
            echo "<B>Sorry, playlist does not exist " . $playlistId . "</B>";
            return;
        }

        $this->emitPlaylistBanner($playlistId, $row);
        $this->emitPlaylistBody($playlistId, false);
    }
    
    private function emitViewDJSortFn($a, $b) {
        return strcasecmp($a["sort"], $b["sort"]);
    }
    
    private function emitViewDJAlbum(&$result, $class="", $count=0) {
        for($i=0; $i < sizeof($result); $i++) {
            echo "  <TR><TD VALIGN=TOP ALIGN=\"right\"$class>";
            if($count)
                echo (string)($i + 1).".&nbsp;";
            else
                echo "&nbsp;&#8226&nbsp;";
            echo "</TD><TD$class>";
    
            // Setup artist and label
            $artist = preg_match("/^COLL$/i", $result[$i]["artist"])?"Various Artists":$result[$i]["artist"];
            $label = str_replace(" Records", "", $result[$i]["label"]);
            $label = str_replace(" Recordings", "", $label);
    
            echo $this->smartURL($artist) . "&nbsp;&#8226; <I>";
    
            // Album
            if($result[$i]["tag"])
                 echo "<A CLASS=\"nav\" HREF=\"".
                      "?s=byAlbumKey&amp;n=". UI::URLify($result[$i]["tag"]).
                      "&amp;q=". $maxresults.
                      "&amp;action=search&amp;session=".$this->session->getSessionID().
                      "\">";
    
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
        $recentReviews = [];
        $recentPlays = [];
        $topPlays = [];
    
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
    
            Engine::api(IPlaylist::class)->getTopPlays($topPlays, $viewuser, $weeks * 7, $limit);
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
            Engine::api(IPlaylist::class)->getRecentPlays($recentPlays, $viewuser, $count);
            Engine::api(IReview::class)->getRecentReviewsByAirname($recentReviews, $viewuser, $count-1);
    
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
            $this->emitViewDJAlbum($recentReviews, $block?" CLASS=\"sub\"":"");
            if(sizeof($recentReviews) == $count - 1)
                echo "  <TR><TD></TD><TD ALIGN=LEFT CLASS=\"sub\"><A HREF=\"?s=byReviewer&amp;n=$viewuser&amp;p=0&amp;q=15&amp;action=viewDJReviews&amp;session=".$this->session->getSessionID()."\" CLASS=\"nav\">More reviews...</A></TD></TR>\n";
            echo "    </TABLE></TD>\n";
        }
    
    ?>
      </TR></TABLE>
    <?php if (sizeof($topPlays) || sizeof($recentPlays) || sizeof($recentReviews)) echo "<BR>\n"; ?>
    <TABLE WIDTH="100%">
      <TR><TH ALIGN=LEFT><?php echo $row['airname'];?>'s playlists:</TH></TR>
      <TR><TD>
         <SELECT NAME=playlist SIZE=6>
    <?php 
            // Run the query
            $records = Engine::api(IPlaylist::class)->getPlaylists(0, 0, 0, $viewuser);
            while($row = $records->fetch())
                echo "        <OPTION VALUE=\"$row[0]\">$row[1] -- $row[3]\n";
    ?>
        </SELECT></TD></TR>
      <TR><TD>
        <INPUT TYPE=SUBMIT VALUE=" View Playlist ">
        <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
        <INPUT TYPE=HIDDEN NAME=viewuser VALUE="<?php echo $viewuser;?>">
        <INPUT TYPE=HIDDEN NAME=action VALUE="viewDJ">
        <INPUT TYPE=HIDDEN NAME=seq VALUE="selList">
      </TD></TR>
    </TABLE>
    </FORM>
    <?php 
            UI::setFocus("playlist");
            return;
        } else if(($seq == "selList") && $playlist) {
            $this->viewList($playlist);
            UI::setFocus();
            return;
        }
    
        $menu[] = [ "a", "", "DJs active past 12 weeks", "emitViewDJMain" ];
        $menu[] = [ "a", "viewAll", "All DJs", "emitViewDJMain" ];
        $this->dispatchSubaction($this->action, $this->subaction, $menu);
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
            $dj[$i++] = $row;
        }
    
        if(isset($dj))
        usort($dj, array($this, "emitViewDJSortFn"));
    
        for($j = 0; $j < $i; $j++) {
            $row = $dj[$j];
            $cur = strtoupper(substr($row["sort"], 0, 1));
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
                 "?action=viewDJ&amp;seq=selUser&amp;viewuser=$row[0]&amp;session=".$this->session->getSessionID().
                 "\">";
    
            $displayName = str_replace(" ", "&nbsp;", htmlentities($row[1]));
            echo "$displayName</A>";
        }
        echo "</TD></TR>\n</TABLE>\n";
    
        UI::setFocus();
    }
    
    public function emitViewDate() {
        $seq = $_REQUEST["seq"];
        $viewdate = $_REQUEST["viewdate"];
        $playlist = $_REQUEST["playlist"];
        $month = $_REQUEST["month"];
        $year = $_REQUEST["year"];
    
        settype($playlist, "integer");
    
        if(($seq == "selList") && $playlist) {
            $this->viewList($playlist);
            UI::setFocus();
            return;
        }
    
        echo "<P><B>Select Date:</B></P>\n";
    
        if(!$month || !$year) {
            // Set default calendar display to current month
            $d = getdate(time());
            $month = $d["mon"];
            $year = $d["year"];
        }
    
        // Run the query
        $records = Engine::api(IPlaylist::class)->getShowdates($year, $month);
        unset($dates);
        while($records && ($row = $records->fetch()))
            $dates .= $row['showdate'] . "|";
    
        // Display the calendar
        $cal = new ZKCalendar;
        $cal->setSession($this->session);
        $cal->setDates($dates);
        echo $cal->getMonthView($month, $year);
    
        if(((($seq == "selDate") && $viewdate)) ||
            (($seq == "selList") && !$playlist)) {
            list($y,$m,$d) = explode("-", $viewdate);
            $displayDate = date("l, j F Y", mktime(0,0,0,$m,$d,$y));
    ?>
    <BR>
    <TABLE WIDTH="100%">
    <TR><TH COLSPAN=3 ALIGN=LEFT CLASS="subhead">Playlists for <?php echo $displayDate;?>:</TH></TR>
    </TABLE>
    <TABLE CELLPADDING=2 CELLSPACING=2>
    <?php 
            // Run the query
            $records = Engine::api(IPlaylist::class)->getPlaylists(1, 1, $viewdate, 0, 0, 0);
            $i=0;
            while($records && ($row = $records->fetch())) {
                    echo "<TR><TD ALIGN=\"RIGHT\" CLASS=\"sub\">" . self::timeToAMPM($row[2]) . "&nbsp;</TD>\n";
                    echo "    <TD><A HREF=\"".
                         "?action=viewDate&amp;seq=selList&amp;playlist=".$row[0].
                         "&amp;session=".$this->session->getSessionID()."\" CLASS=\"nav\">" .
                          htmlentities($row[3]) . "</A>&nbsp;&nbsp;";
                    echo "(" . htmlentities($row[5]) . ")</TD></TR>\n";
                    $i += 1;
            }
    ?>
    </TABLE>
    <?php 
        }
        UI::setFocus();
    }
    
    public function viewLastPlays($tag, $count=0) {
        $plays = Engine::api(IPlaylist::class)->getLastPlays($tag, $count);
        if($plays) {
            echo "<TABLE WIDTH=\"100%\">\n";
            echo "  <TR><TH ALIGN=LEFT CLASS=\"secdiv\">Recent Airplay</TH>";
            echo "</TR>\n</TABLE>\n";
    
            echo "<TABLE CELLPADDING=4 CELLSPACING=0 BORDER=0>\n";
    
            // Setup date format differently if plays extend into another year
            $now = getdate(time());
            list($y,$m,$d) = explode("-", $plays[sizeof($plays)-1]["showdate"]);
            $dateSpec = ($y == $now["year"])?"D, d M":"D, d M y";
    
            // Ensure we have an even number of plays
            if(sizeof($plays)%2)
                $plays[] = array("airname"=>"");
     
            $mid = sizeof($plays)/2;
            for($i=0; $i<sizeof($plays); $i++) {
                if($i%2 == 0) {
                    echo "  <TR>";
                    $idx = ($i+2)/2 - 1;
                } else {
                    echo "      ";
                    $idx = $mid + ($i+1)/2 - 1;
                }
    
                if($plays[$idx]["airname"]) {
                    list($y,$m,$d) = explode("-", $plays[$idx]["showdate"]);
                    $formatDate = preg_replace("/ /", "&nbsp;", date($dateSpec, mktime(0,0,0,$m,$d,$y)));
                      
                    echo "<TD ALIGN=RIGHT VALIGN=TOP CLASS=\"sub\">".($idx+1).".</TD>";
                    echo "<TD ALIGN=RIGHT VALIGN=TOP CLASS=\"sub\">$formatDate:</TD>";
                    echo "<TD ALIGN=LEFT VALIGN=TOP CLASS=\"sub\">".$plays[$idx]["airname"]."<BR>";
                    echo "<A HREF=\"".
                         "?action=viewDJ&amp;playlist=".$plays[$idx]["id"].
                         "&amp;seq=selList&amp;session=".$this->session->getSessionID()."\">".$plays[$idx]["description"]."</A></TD>";
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

class ZKCalendar extends \Calendar {
    function setSession($session) {
        $this->session = $session;
    }
    function setDates($dates) {
        $this->dates = $dates;
    }
    function getCalendarLink($month, $year) {
        return "?session=".$this->session->getSessionID()."&amp;action=viewDate&amp;month=$month&amp;year=$year";
    }
    function getDateLink($day, $month, $year) {
        $link = "";
        $testDate = date("Y-m-d", mktime(0,0,0,$month,$day,$year));

        if(strstr($this->dates, $testDate))
            $link = "?session=".$this->session->getSessionID()."&amp;action=viewDate&amp;seq=selDate&amp;viewdate=$testDate&amp;month=$month&amp;year=$year";

        return $link;
    }
}

