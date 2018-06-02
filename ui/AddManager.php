<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2018 Jim Mason <jmason@ibinx.com>
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

use ZK\Engine\Engine;
use ZK\Engine\IChart;
use ZK\Engine\ILibrary;

use ZK\UI\UICommon as UI;

class AddManager extends MenuItem {
    const MIN_REQUIRED = 4;        // minimum required A-File tracks/hour

    private static $subactions = [
        [ "a", "", "Current File", "addManagerMain" ],
        [ "a", "adds", "Adds", "addManagerShowAdd" ],
        [ "n", "newadd", "New Add", "addManagerAdd" ],
        [ "n", "categories", "Categories", "addManagerCats" ],
        [ "n", "addsedit", "", "addManagerEdit" ],
        [ "n", "addsdel", "", "addManagerDel" ],
        [ "n", "addsemail", "", "addManagerEMail" ],
        [ "u", "activity", "Activity", "aFileActivityShowWeekly" ],
    ];

    /**
     * This is the sequence of panels in the Adds genie
     *
     *     Key is seq name of panel
     *     Value is array containing panel proc & next panel seq name
     */
    private $addPanels = [
         "aid"=>      [ "panelAID", "tag" ],
         "tag"=>      [ "panelTag", "info" ],
         "info"=>     [ "panelInfo", "addpull" ],
         "addpull"=>  [ "panelAddPull", "cats" ],
         "cats"=>     [ "panelCats", "summary" ],
         "summary"=>  [ "panelSummary", "aid" ],
         ""=>         [ "panelNull", "aid" ],
    ];

    private $focus;
    private $emitted;
    private $albumAdded;
    private $editingAlbum;
    private $errorMessage;
    private $nextMessage;

    public function processLocal($action, $subaction) {
        $extra = "<SPAN CLASS=\"sub\"><B>Adds Feed:</B></SPAN> <A TYPE=\"application/rss+xml\" HREF=\"zkrss.php?feed=adds\"><IMG SRC=\"img/rss.gif\" ALIGN=MIDDLE WIDTH=36 HEIGHT=14 BORDER=0 ALT=\"rss\"></A><BR><IMG SRC=\"img/blank.gif\" WIDTH=1 HEIGHT=2 BORDER=0 ALT=\"\">";

        return $this->dispatchSubAction($action, $subaction, self::$subactions, $extra);
    }
    
    public function addManagerGetAlbums2(&$records, &$albums) {
        while($records && ($row = $records->fetch())) {
            $row["LABELNAME"] = $row["label"];
            $albums[] = $row;
        }
    }
    
    private function addManagerGetAlbums(&$records, &$albums) {
        $libraryAPI = Engine::api(ILibrary::class);
        while($records && ($row = $records->fetch())) {
            $row["tag"] = $row[2];
            $albumrec = $libraryAPI->search(ILibrary::ALBUM_KEY, 0, 1, $row[2]);
            if(sizeof($albumrec) > 0) {
                //list($y, $m, $d) = explode("-", $row["adddate"]);
                //$diff = ($now - mktime(0,0,0,$m,$d,$y))/86400;  // 86400sec = 1day
                //if($diff > 7)
                //    $row["CLICKS"] = floor($row["plays"]/$diff*100);
                $row["artist"] = $albumrec[0]["artist"];
                $row["album"] = $albumrec[0]["album"];
                $row["medium"] = $albumrec[0]["medium"];
                $labelKey = $albumrec[0]["pubkey"];
                if(!$labelCache[$labelKey]) {
                    // Secondary search for label name
                    $label = $libraryAPI->search(ILibrary::LABEL_PUBKEY, 0, 1, $labelKey);
                    if(sizeof($label)){
                        $labelCache[$labelKey] = $label[0]["name"];
                    } else
                        $labelCache[$labelKey] = "(Unknown)";
                }
                $row["LABELNAME"] = $labelCache[$labelKey];
            }
            $albums[] = $row;
        }
    }
    
    private function addManagerSortFn($a, $b) {
        switch($_REQUEST["sortBy"]) {
        case "Num":
        case "Num-":
            $retval = strcmp($a[1], $b[1]);
            break;
        case "Label":
        case "Label-":
            $retval = strcmp($a["LABELNAME"], $b["LABELNAME"]);
            break;
        case "Title":
        case "Title-":
            $retval = strcmp($a["album"], $b["album"]);
            break;
        case "AddDate":
        case "AddDate-":
            $retval = strcmp($a[3], $b[3]);
            break;
        case "PullDate":
        case "PullDate-":
            $retval = strcmp($a[4], $b[4]);
            break;
        case "**Sizzle":
        case "**Sizzle-":
            $isSetA = isset($a["sizzle"]);
            $isSetB = isset($b["sizzle"]);
            if($isSetA && $isSetB)
                $retval = $b["sizzle"] - $a["sizzle"];
            else if($isSetA)
                $retval = -1;
            else if($isSetB)
                $retval = 1;
            else
                $retval = 0;
    
            // Secondary sort on the A-File number
            if($retval == 0)
                $retval = strcmp($a[1], $b[1]);
            break;
        default:
            $retval = strcmp($a["artist"], $b["artist"]);
            break;
        }
        return (substr($_REQUEST["sortBy"], -1, 1) == "-")?-$retval:$retval;
    }
    
    private function addManagerColHeader($subaction, $header, $static) {
        $command = $header;
        if(!strcmp($header, $_REQUEST["sortBy"])) {
            $command .= "-";
            $selected = 1;
        } else if(!strcmp($header . "-", $_REQUEST["sortBy"]))
            $selected = 2;
    
        if($header == "Num") $width = " WIDTH=60px";
    
        if($header == "%") $header = "&nbsp;%&nbsp;";
    
        if($static)
            echo "        <TH ALIGN=LEFT$width><U>$header</U>";
        else
            echo "        <TH ALIGN=LEFT$width><A CLASS=\"nav\" HREF=\"?session=".$this->session->getSessionID()."&amp;action=addmgr&amp;subaction=$subaction&amp;sortBy=$command&amp;date=".$_REQUEST["date"]."\">$header</A>";
    
        if($selected && !$static)
            echo "&nbsp;<IMG SRC=\"img/arrow_" . (($selected==1)?"down":"up") . "_beta.gif\" BORDER=0 WIDTH=8 HEIGHT=4 ALIGN=MIDDLE ALT=\"sort\">";
    
        // Give extra horizontal padding to column
        ////if(!strcmp($header, "Num"))
        ////    echo "&nbsp;&nbsp;&nbsp;";
    
        echo "</TH>\n";
    }
    
    public function addManagerEmitAlbums(&$records, $subaction, $showEdit, $showReview, $static=0) {
        $id = $_REQUEST["id"];
        $showTag = $this->session->isAuth("u");
        $showAvg = $showTag && !$static && $subaction != "adds";
    
        // Setup default sortBy
        $sortBy = $_REQUEST["sortBy"];
        if(!strlen($sortBy))
            $_REQUEST["sortBy"] = "Artist";
    
        // Stuff the categories into an array
        $cats = Engine::api(IChart::class)->getCategories();
        $i=1;
        while($cats && ($row = $cats->fetch()))
            $catcode[$i++] = $row[2];
            
        echo "  <TABLE CELLPADDING=2 CELLSPACING=0 BORDER=0";
        ////if($static) echo " WIDTH=\"100%\"";
        echo ">\n";
        ////if($showEdit)
        ////    echo "    <TR><TH CLASS=\"sub\">Del</TH><TH CLASS=\"sub\">Edit</TH>\n";
        ////else
            echo "    <TR><TH COLSPAN=2></TH>\n";
        $this->addManagerColHeader($subaction, "Num", $static);
        $this->addManagerColHeader($subaction, "Artist", $static);
        echo "        <TH></TH>\n";  // space for [i] icon
        $this->addManagerColHeader($subaction, "Title", $static);
        if(!$static)
            $this->addManagerColHeader($subaction, "Label", $static);
        if($showAvg)
            $this->addManagerColHeader($subaction, "**Sizzle", $static);
        echo "    </TR>\n";
    
        // Get albums into array
        $this->addManagerGetAlbums2($records, $albums);
    
        // Mark reviewed albums
        if($showReview)
            Engine::api(ILibrary::class)->markAlbumsReviewed($albums);
    
        // Sort it
        if($albums) {
            usort($albums, array($this, "addManagerSortFn"));
    
        while(list($index, $row) = each($albums)) {
            echo "    <TR CLASS=\"hborder\"><TD VALIGN=TOP>";
    
            // Edit link
            if($showEdit) {
                echo "<A CLASS=\"nav\" HREF=\"" .
                     "?session=".$this->session->getSessionID()."&amp;action=addmgr&amp;subaction=adds&amp;date=$date\" " .
                     "onClick=\"ConfirmDelete($row[0]); return false;\">[x]</A>&nbsp;</TD><TD VALIGN=TOP>";
                $class = ($id && $id == $row[0])?"sel":"nav";
                echo "<A CLASS=\"$class\" HREF=\"" .
                     "?session=".$this->session->getSessionID()."&amp;action=addmgr&amp;subaction=addsedit&amp;id=$row[0]\">&gt;&gt;</A>&nbsp;&nbsp;";
            } else
                echo "</TD><TD VALIGN=TOP>";
    
            // Categories
            $cats = explode(",", $row[5]);
            while(list($index, $cat) = each($cats))
                echo $catcode[$cat];
    
            // A-File Numbers
            echo "</TD><TD VALIGN=TOP>$row[1]</TD>";
    
            $artistName = htmlentities($row["artist"]) ;
            if($static && strlen($artistName) > 50)
                $artistName = substr($artistName, 0, 50) . "...";
    
            // Artist/Album/Label names
            echo "<TD VALIGN=TOP>" . $artistName . "&nbsp;&nbsp;</TD><TD VALIGN=TOP>";
            if($showReview && $row["REVIEWED"]) {
                echo "<A HREF=\"".
                     "?s=byAlbumKey&amp;n=". UI::URLify($row["tag"]).
                     "&amp;action=search&amp;session=".$this->session->getSessionID().
                     "\"><IMG SRC=\"img/rinfo_beta.gif\" " .
                     "ALT=\"Album Review\" " .
                     "WIDTH=12 HEIGHT=11 BORDER=0></A>";
            }
    
            // Setup medium
            switch($row["medium"]) {
            case "S":
                $medium = "&nbsp;(7\")";
                break;
            case "T":
                $medium = "&nbsp;(10\")";
                break;
            case "V":
                $medium = "&nbsp;(12\")";
                break;
            default:
                $medium = "";
                break;
            }
            $albumName = htmlentities($row["album"]);
            if($static && strlen($albumName) > 50)
                $albumName = substr($albumName, 0, 50) . "...";
            $albumName .= $medium;
    
            $tagNum = $showTag?
                " <FONT CLASS=\"sub\">(Tag&nbsp;#". $row["tag"] .")</FONT>":"";
    
            if($static)
                echo "</TD><TD VALIGN=TOP>" . $albumName . $tagNum . "&nbsp;&nbsp;</TD>";
            else
                echo "</TD><TD VALIGN=TOP><A CLASS=\"nav\" HREF=\"".
                     "?s=byAlbumKey&amp;n=". UI::URLify($row["tag"]).
                     "&amp;action=search&amp;session=".$this->session->getSessionID().
                     "\">" . $albumName . "</A>$tagNum&nbsp;&nbsp;</TD>";
            if(!$static)
                echo "<TD VALIGN=TOP>" . htmlentities($row["LABELNAME"]) . "</TD>";
            if($showAvg)
                echo "<TD ALIGN=RIGHT>".$row["sizzle"]."</TD>";
            echo "</TR>\n";
        }
      }
        echo "  </TABLE>\n";
        if($showAvg)
            echo "  <P><B>**Sizzle</B>: Measure of an album's average daily airplay, ".
                 "available for albums which have been in the A-File for a ".
                 "minimum of 7 days.  Sizzle = (raw spin count while in the ".
                 "A-File / days in A-File) * 100, where days in A-File &gt; 7.</P>\n";
    
        if($showEdit)
            $this->emitConfirmID("Delete",
                        "Delete this album from the add?",
                        "session=".$this->session->getSessionID()."&action=addmgr&subaction=addsdel",
                        "id");
    }
    
    public function addManagerMain() {
        switch($_REQUEST["op"]) {
        case "edit":
            $this->addManagerEdit();
            break;
        case "del":
            $this->addManagerDel();
            break;
        default:
            $displayDate = date("l, j F Y");
            echo "  <TABLE WIDTH=\"100%\">\n    <TR><TH ALIGN=LEFT>";
            echo "A-File as of $displayDate</TH>\n";
            if($this->session->isAuth("n") || $this->session->isAuth("o")) {
                echo "        <TD ALIGN=RIGHT VALIGN=TOP><A CLASS=\"sub\" HREF=\"#top\" onClick='window.open(\"?target=afile\")'>Printable A-File</A></TD>\n";
            }
            echo "    </TR>\n  </TABLE><BR>\n";
            if($this->session->isAuth("u"))
                $results = Engine::api(IChart::class)->getCurrentsWithPlays2(date("Y-m-d"));
            else
                $results = Engine::api(IChart::class)->getCurrents2(date("Y-m-d"));
            $this->addManagerEmitAlbums($results, "", false, true);
            UI::setFocus();
        }
    }
    
    public function addManagerShowAdd() {
        $date = $_REQUEST["date"];
        if(!UI::isNumeric($date))
            return;
    
        // Setup default sortBy
        $sortBy = $_REQUEST["sortBy"];
        if(!strlen($sortBy))
            $_REQUEST["sortBy"] = "Num";
    
    ?>
      <TABLE CELLPADDING=2 CELLSPACING=0 WIDTH="100%" BORDER=0>
        <TR>
          <TH ALIGN=LEFT>
            <FORM ACTION="" METHOD=POST>
              Adds for:
              <SELECT NAME=date onChange='this.form.submit()'>
    <?php 
        $records = Engine::api(IChart::class)->getAddDates(52);
        $datevalid = false;
        while($records && ($row = $records->fetch())) {
            if(!$first) $first = $row[0];
            $selected = ($row[0] == $date)?" SELECTED":"";
            $datevalid |= $selected != "";
            echo "            <OPTION VALUE=\"$row[0]\"$selected>$row[0]\n";
        }
    ?>          </SELECT>
              <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
              <INPUT TYPE=HIDDEN NAME=action VALUE="addmgr">
              <INPUT TYPE=HIDDEN NAME=subaction VALUE="adds">
              <INPUT TYPE=HIDDEN NAME=seq VALUE="update">
            </FORM>
          </TH>
    <?php if($this->session->isAuth("n")) { ?>
          <TD ALIGN=RIGHT>
            <FORM ACTION="?" METHOD=POST>
              <SELECT NAME=os>
                  <OPTION VALUE="win">Windows
                  <OPTION VALUE="mac">Mac OS 9
                  <OPTION VALUE="unix">Unix/OS X
                  <OPTION VALUE="email">E-Mail
              </SELECT>
              <INPUT TYPE=BUTTON NAME=button onClick="onExport();" VALUE=" Export ">
              <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
              <INPUT TYPE=HIDDEN NAME=date VALUE="">
              <INPUT TYPE=HIDDEN NAME=target VALUE="addexp">
            </FORM>
          </TD>
    <?php  } ?>
        </TR>
      </TABLE>
    <?php 
        if(!$datevalid && $first) $date = $first;
        if($date) {
            $records = Engine::api(IChart::class)->getAdd2($date);
            $this->addManagerEmitAlbums($records, "adds", $this->session->isAuth("n"), true);
        }
        UI::setFocus();
        if($this->session->isAuth("n")) {
    ?>
    <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript"><!--
    function onExport() {
      if(document.forms[1].os.value == "email") {
        document.forms[0].subaction.value = "addsemail";
        document.forms[0].submit();
      } else {
        document.forms[1].date.value = document.forms[0].date.value;
        document.forms[1].submit();
      }
    }
    // -->
    </SCRIPT>
    <?php 
        }
    }
    
    private function emitConfirmID($name, $message, $action, $id="", $rtaction="") {
    ?>
    <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript"><!--
    function Confirm<?php echo $name; ?>(<?php if($id) echo "id"; ?>)
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
           else if($id)
              echo "&$id=\" + id";
           else
              echo "\""; ?>;
      }
    }
    // -->
    </SCRIPT>
    <?php 
    }
    
    public function panelInfo($validate) {
        $libraryAPI = Engine::api(ILibrary::class);
        if($validate)
            return true;
    
        echo "        <TABLE CELLPADDING=2 CELLSPACING=0 BORDER=0>\n";
    
        // Artist and Album names
        $albumrec = $libraryAPI->search(ILibrary::ALBUM_KEY, 0, 1, $_REQUEST["tag"]);
        if(sizeof($albumrec) > 0) {
            echo "          <TR><TD ALIGN=RIGHT>Artist:</TD><TH>" . htmlentities($albumrec[0]["artist"]) . "&nbsp;&nbsp;</TH></TR>\n";
            echo "          <TR><TD ALIGN=RIGHT>Album:</TD><TH>" . htmlentities($albumrec[0]["album"]) . "&nbsp;&nbsp;</TH></TR>\n";
            $labelKey = $albumrec[0]["pubkey"];
            if(!$labelCache[$labelKey]) {
                // Secondary search for label name
                $label = $libraryAPI->search(ILibrary::LABEL_PUBKEY, 0, 1, $labelKey);
                if(sizeof($label)){
                    $labelCache[$labelKey] = $label[0]["name"];
                } else
                    $labelCache[$labelKey] = "(Unknown)";
            }
            echo "          <TR><TD ALIGN=RIGHT>Label:</TD><TH>" . htmlentities($labelCache[$labelKey]) . "</TH></TR>\n";
            $this->emitHidden("artist", $artist = $albumrec[0]["artist"]);
            $this->emitHidden("album", $album = $albumrec[0]["album"]);
        }
        echo "        </TABLE>\n";
    }
    
    public function panelAddPull($validate) {
        $adddate = $_REQUEST["adddate"];
        $pulldate = $_REQUEST["pulldate"];
    
        if($validate) {
            list($ay, $am, $ad) = explode("-", $adddate);
            list($py, $pm, $pd) = explode("-", $pulldate);
            if(checkdate($am, $ad, $ay) && checkdate($pm, $pd, $py))
                return true;
            else {
                $this->errorMessage = "Ensure dates are valid";
                return;
            }
        }
        if(!$this->errorMessage) {
            // Setup defaults
            if(!$adddate)
                $adddate = date("Y-m-d");
            if(!$pulldate)
                $pulldate = date("Y-m-d", mktime(0,0,0,date("m"),date("d")+63,date("Y")));
        }
    ?>
          <TABLE CELLPADDING=2 CELLSPACING=0 BORDER=0>
            <TR><TD>&nbsp;</TD>
                <TD><FONT CLASS="error"><B><?php echo $message;?></B></FONT></TD></TR>
            <TR><TD ALIGN=RIGHT>Add Date:</TD>
                <TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=adddate VALUE="<?php echo $adddate;?>" CLASS=input SIZE=15 MAXLENGTH=15></TD></TR>
                <TD ALIGN=RIGHT>Pull Date:</TD>
                <TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=pulldate VALUE="<?php echo $pulldate;?>" CLASS=input SIZE=15 MAXLENGTH=15></TD></TR>
          </TABLE>
    <?php 
        $this->skipVar("adddate");
        $this->skipVar("pulldate");
        $this->panelSetFocus("adddate");
    }
    
    public function panelAID($validate) {
        // Check to ensure AID is numeric
        $aid = $_REQUEST["aid"];
        $temp = (float)$aid;
        $temp = (string)$temp;
    
        if($validate)
            if($temp == $aid && $aid >= 100 && $aid <= 999)
                return true;
            else {
                $this->errorMessage = "Invalid Number";
                return;
        }
    
        // Setup default
        if(!$aid)
            $aid = Engine::api(IChart::class)->getNextAID();
    ?>
          <TABLE CELLPADDING=2 CELLSPACING=0 BORDER=0>
            <TR><TD COLSPAN=2>&nbsp;</TD></TR>
            <TR><TD ALIGN=RIGHT>A-File ID:</TD>
                <TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=aid VALUE="<?php echo $aid;?>" CLASS=input SIZE=15 MAXLENGTH=15></TD></TR>
          </TABLE>
    <?php 
        $this->skipVar("aid");
        $this->panelSetFocus("aid");
    }
    
    public function panelTag($validate) {
        // Check to ensure tag is numeric
        $tag = $_REQUEST["tag"];
        $temp = (float)$tag;
        $temp = (string)$temp;
    
        if($validate) {
            if($tag && $temp == $tag) {
                // Lookup tag
                $albumrec = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $tag);
                if(sizeof($albumrec) != 0) {
                    return true;
                }
            }
            $this->errorMessage = "Invalid tag";
            return;
        }
    ?>
          <TABLE CELLPADDING=2 CELLSPACING=0 BORDER=0>
            <TR><TD COLSPAN=2>&nbsp;</TD></TR>
            <TR><TD ALIGN=RIGHT>Album Tag:</TD>
                <TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=tag VALUE="<?php echo $tag;?>" CLASS=input SIZE=15 MAXLENGTH=15></TD></TR>
          </TABLE>
    <?php 
        $this->skipVar("tag");
        $this->skipVar("artist");
        $this->skipVar("album");
        $this->panelSetFocus("tag");
    }
    
    public function panelCats($validate) {
        $catlist = $_REQUEST["catlist"];
    
        if($validate)
            return true;
    ?>
          <TABLE CELLPADDING=2 CELLSPACING=0 BORDER=0>
    <?php 
        // Stuff the categories into an array
        $records = Engine::api(IChart::class)->getCategories();
        $i=0;
        while($records && ($row = $records->fetch()))
            $cats[$i++] = $row[1];
    
        // Setup selected categories
        $cl = explode(",", $catlist);
        while(list($index, $cat) = each($cl))
             $selcats[(int)$cat - 1] = "X";
    
        $this->skipVar("catlist");
    
        // Emit the checkbox table 
        for($i=0; $i<4; $i++) {
            echo "        <TR><TD>";
            $selected = $selcats[$i]?" CHECKED":"";
            if($cats[$i])
                echo "<INPUT TYPE=CHECKBOX NAME=cat$i$selected>".htmlentities(stripslashes($cats[$i]));
            echo "</TD><TD>";
            $selected = $selcats[$i+4]?" CHECKED":"";
            if($cats[$i+4])
                echo "<INPUT TYPE=CHECKBOX NAME=cat".($i+4)."$selected>".htmlentities(stripslashes($cats[$i+4]));
            echo "</TD><TD>";
            $selected = $selcats[$i+8]?" CHECKED":"";
            if($cats[$i+8])
                echo "<INPUT TYPE=CHECKBOX NAME=cat".($i+8)."$selected>".htmlentities(stripslashes($cats[$i+8]));
            echo "</TD><TD>";
            $selected = $selcats[$i+12]?" CHECKED":"";
            if($cats[$i+12])
                echo "<INPUT TYPE=CHECKBOX NAME=cat".($i+12)."$selected>".htmlentities(stripslashes($cats[$i+12]));
            echo "</TD></TR>\n";
        }
    ?>
          </TABLE>
    <?php 
    }
    
    public function panelSummary($validate) {
        $adddate = $_REQUEST["adddate"];
        $pulldate = $_REQUEST["pulldate"];
        $aid = $_REQUEST["aid"];
        $tag = $_REQUEST["tag"];
        $artist = $_REQUEST["artist"];
        $album = $_REQUEST["album"];
    
        if($validate) {
            // Add the album
            $emitted = false;
            $catstr = "";
            for($i=0; $i<16; $i++)
                if($_POST["cat".$i]) {
                    if($emitted) $catstr .= ",";
                    $catstr .= (string)($i+1);
                    $emitted = true;
                }
            if(Engine::api(IChart::class)->addAlbum($aid, $tag, $adddate, $pulldate, $catstr)) {
                // Clear the form data
                $this->skipVar("aid");
                $this->skipVar("tag");
                for($i=0; $i<16; $i++)
                    $this->skipVar("cat".$i);
                $this->emitHidden("catlist", $catstr);
                $_REQUEST["aid"] = "";
                $this->albumAdded = true;
                return true;
            } else {
                $this->errorMessage = "Add failed";
                return;
            }
        }
    ?>
          <TABLE CELLPADDING=2 CELLSPACING=0 BORDER=0>
            <TR><TD ALIGN=RIGHT>Number:</TD><TH ALIGN=LEFT><?php echo $aid;?></TH></TR>
            <TR><TD ALIGN=RIGHT>Artist:</TD><TH ALIGN=LEFT><?php echo htmlentities(stripslashes($artist));?></TH></TR>
            <TR><TD ALIGN=RIGHT>Album:</TD><TH ALIGN=LEFT><?php echo htmlentities(stripslashes($album));?></TH></TR>
            <TR><TD ALIGN=RIGHT>Add Date:</TD><TD ALIGN=LEFT><?php echo $adddate;?></TD></TR>
            <TR><TD ALIGN=RIGHT>Pull Date:</TD><TD ALIGN=LEFT><?php echo $pulldate;?></TD></TR>
            <TR><TD ALIGN=RIGHT>Categories:</TD><TD ALIGN=LEFT><?php 
        // Stuff the categories into an array
        $records = Engine::api(IChart::class)->getCategories();
        $i=0;
        while($records && ($row = $records->fetch()))
            $cats[$i++] = $row[1];
    
        $emitted = false;
        for($i=0; $i<16; $i++)
            if($_POST["cat".$i]) {
                if($emitted) echo ", ";
                echo htmlentities(stripslashes($cats[$i]));
                $emitted = true;
            }
    ?></TD></TR>
          </TABLE>
    <?php 
        $this->nextMessage = "Add Album";
    }
    
    private function skipVar($name) {
        $this->emitted[$name] = "X";
    }
    
    private function panelSetFocus($name) {
        $this->focus = $name;
    }
    
    private function emitHidden($name, $value) {
        $post = $_SERVER["REQUEST_METHOD"] == "POST";
    
        if($post)
            $_POST[$name] = $value;
        else
            $_GET[$name] = $value;
    }
    
    private function emitVars() {
        $post = $_SERVER["REQUEST_METHOD"] == "POST";
    
        foreach($post?$_POST:$_GET as $key => $value)
            if(!$this->emitted[$key])
                 echo "    <INPUT TYPE=HIDDEN NAME=$key VALUE=\"" . htmlentities(stripslashes($value)) . "\">\n";
    }
    
    private function addManagerGetTitle($seq) {
        if($this->errorMessage)
            $title = "<FONT CLASS=\"error\">$this->errorMessage</FONT>";
        else
            switch($seq) {
            case "aid":
                $title = "Enter A-File Number";
                if($this->albumAdded)
                    $title = "Album Added!  $title for next album.";
                if($this->editingAlbum)
                    $title = "Editing Add.  Press Next to review details.";
                break;
            case "addpull":
                $title = "Enter Add and Pull Dates";
                break;
            case "tag":
                $title = "Enter Album Tag";
                break;
            case "info":
                $title = "Confirm Album";
                break;
            case "cats":
                $title = "Select Reporting Categories";
                break;
            case "summary":
                $title = "Add Album";
                break;
            default:
                $title = "Enter A-File Number";
                break;
            }
        return $title;
    }
    
    public function panelNull($validate) {
        return $validate;
    }
    
    public function addManagerAdd() {
        $seq = $_REQUEST["seq"];
    
        // We're always going to make two passes:
        //    Pass 1:  Call step $seq to validate
        //    Pass 2a: If $seq validates, call $next to display
        //    Pass 2b: If $seq doesn't validate, call $seq to redisplay
    
        for($i=0; $i<2; $i++) {
            if($i == 1) {
                // Emit header
                $title = $this->addManagerGetTitle($seq);
                echo "  <FORM ACTION=\"\" METHOD=POST>\n";
                echo "    <TABLE CELLPADDING=0 CELLSPACING=0 BORDER=0>\n      <TR><TH ALIGN=LEFT>$title</TH></TR>\n      <TR><TD HEIGHT=130 VALIGN=MIDDLE>\n";
    
            }
    
            // Handle default case
            if(!$this->addPanels[$seq])
                $seq = "";
    
            // Dispatch to panel
            $next = $this->addPanels[$seq][0];
            $status = $this->$next($i==0);
            if($status)
                $seq = $this->addPanels[$seq][1];
        }
    ?>
          </TD></TR>
        </TABLE>
    <?php 
        echo "    <INPUT TYPE=SUBMIT VALUE=\"" . ($this->nextMessage?$this->nextMessage:"  Next &gt;&gt;  ") . "\">\n";
        $this->emitHidden("seq", $seq);
        $this->emitVars();
        echo "  </FORM>\n";
        UI::setFocus($this->focus);
    }
    
    public function panelSummaryEdit($validate) {
        $adddate = $_REQUEST["adddate"];
        $pulldate = $_REQUEST["pulldate"];
        $id = $_REQUEST["id"];
        $aid = $_REQUEST["aid"];
        $tag = $_REQUEST["tag"];
        $artist = $_REQUEST["artist"];
        $album = $_REQUEST["album"];
    
        if($validate) {
            // Add the album
            $emitted = false;
            for($i=0; $i<16; $i++)
                if($_POST["cat".$i]) {
                    if($emitted) $catstr .= ",";
                    $catstr .= (string)($i+1);
                    $emitted = true;
                }
            if(Engine::api(IChart::class)->updateAlbum($id, $aid, $tag, $adddate, $pulldate, $catstr))
                return true;
            else {
                $this->errorMessage = "Update failed";
                return;
            }
        }
    ?>
          <TABLE CELLPADDING=2 CELLSPACING=0 BORDER=0>
            <TR><TD ALIGN=RIGHT>Number:</TD><TH ALIGN=LEFT><?php echo $aid;?></TH></TR>
            <TR><TD ALIGN=RIGHT>Artist:</TD><TH ALIGN=LEFT><?php echo htmlentities(stripslashes($artist));?></TH></TR>
            <TR><TD ALIGN=RIGHT>Album:</TD><TH ALIGN=LEFT><?php echo htmlentities(stripslashes($album));?></TH></TR>
            <TR><TD ALIGN=RIGHT>Add Date:</TD><TD ALIGN=LEFT><?php echo $adddate;?></TD></TR>
            <TR><TD ALIGN=RIGHT>Pull Date:</TD><TD ALIGN=LEFT><?php echo $pulldate;?></TD></TR>
            <TR><TD ALIGN=RIGHT>Categories:</TD><TD ALIGN=LEFT><?php 
        // Stuff the categories into an array
        $records = Engine::api(IChart::class)->getCategories();
        $i=0;
        while($records && ($row = $records->fetch()))
            $cats[$i++] = $row[1];
    
        $emitted = false;
        for($i=0; $i<16; $i++)
            if($_POST["cat".$i]) {
                if($emitted) echo ", ";
                echo htmlentities(stripslashes($cats[$i]));
                $emitted = true;
            }
    ?></TD></TR>
          </TABLE>
    <?php 
        $this->nextMessage = "Update Album";
    }
    
    public function addManagerEdit() {
        $id = $_REQUEST["id"];
        $seq = $_REQUEST["seq"];
    
        $aid = $_REQUEST["aid"];
        $tag = $_REQUEST["tag"];
        $adddate = $_REQUEST["adddate"];
        $pulldate = $_REQUEST["pulldate"];
        $catlist = $_REQUEST["catlist"];
        $date = $_REQUEST["date"];
    
        // We're always going to make two passes:
        //    Pass 1:  Call step $seq to validate
        //    Pass 2a: If $seq validates, call $next to display
        //    Pass 2b: If $seq doesn't validate, call $seq to redisplay
    
        // Update final sequence for edit operation
        $this->addPanels["summary"] = [ "panelSummaryEdit", "fin" ];
        $this->addPanels["fin"] = [ "panelNull", "" ];
    
        for($i=0; $i<2; $i++) {
            if($i == 1 && $seq != "fin") {
                // Emit header
                $title = $this->addManagerGetTitle($seq);
                echo "  <FORM ACTION=\"\" METHOD=POST>\n";
                echo "    <TABLE CELLPADDING=0 CELLSPACING=0 BORDER=0>\n      <TR><TH>$title</TH></TR>\n      <TR><TD HEIGHT=130 VALIGN=MIDDLE>\n";
    
            }
    
            // Handle default case
            if(!$seq || !$this->addPanels[$seq]) {
                $seq = "";
    
                // Pull in the values for this album
                $row = Engine::api(IChart::class)->getAlbum($id);
                if($row) {
                        $_REQUEST["aid"] = $row[1];
                        $_REQUEST["tag"] = $row[2];
                        $_REQUEST["adddate"] = $row[3];
                        $_REQUEST["pulldate"] = $row[4];
                        $_REQUEST["catlist"] = $row[5];
                    $this->emitHidden("tag", $row[2]);
                    $this->emitHidden("adddate", $row[3]);
                    $this->emitHidden("pulldate", $row[4]);
                    $this->emitHidden("catlist", $row[5]);
                    $this->editingAlbum = true;
                }
            }
    
            if($seq == "fin") {
                echo "<P><FONT CLASS=\"header\">Album updated!</FONT></P>\n";
                $_REQUEST["date"] = $adddate;
                $this->addManagerShowAdd();
                return;
            }
    
            // Dispatch to panel
            $next = $this->addPanels[$seq][0];
            $status = $this->$next($i==0);
            if($status)
                $seq = $this->addPanels[$seq][1];
        }
    ?>
          </TD></TR>
        </TABLE>
    <?php 
        echo "    <INPUT TYPE=SUBMIT VALUE=\"" . ($this->nextMessage?$this->nextMessage:"  Next &gt;&gt;  ") . "\">\n";
        $this->emitHidden("seq", $seq);
        $this->emitVars();
        echo "  </FORM>\n";
        UI::setFocus($this->focus);
    }
    
    public function addManagerDel() {
        $id = $_REQUEST["id"];
        $date = $_REQUEST["date"];
    
        // Setup the date for AddManagerShowAdd() redisplay
        $row = Engine::api(IChart::class)->getAlbum($id);
        if($row) {
            $date = $row[3];
        }
    
        Engine::api(IChart::class)->deleteAlbum($id);
        $this->addManagerShowAdd();
    }
    
    public function addManagerCats() {
        $seq = $_REQUEST["seq"];
    
        if($seq == "update") {
            $success = true;
            for($i=1; $success && $i<=16; $i++) {
                $name = $_POST["name".$i];
                $code = $_POST["code".$i];
                $dir = $_POST["dir".$i];
                $success &= Engine::api(IChart::class)->updateCategory($i, $name, $code, $dir);
            }
        }
    ?>
      <FORM ACTION="" METHOD=POST>
        <TABLE CELLPADDING=2 CELLSPACING=0 BORDER=0>
          <TR><TH>&nbsp;</TH><TH>Category</TH><TH>Code&nbsp;</TH><TH>Director</TH></TR>
    <?php 
        $cats = Engine::api(IChart::class)->getCategories();
        while($cats && ($row = $cats->fetch())) {
            $i = $row[0];
            echo "      <TR><TD ALIGN=RIGHT>$i.</TD>\n";
            echo "          <TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=name$i VALUE=\"".htmlentities(stripslashes($row[1]))."\" CLASS=input SIZE=20 MAXLENGTH=80></TD>\n";
            echo "          <TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=code$i VALUE=\"".htmlentities(stripslashes($row[2]))."\" CLASS=input SIZE=4 MAXLENGTH=1></TD>\n";
            echo "          <TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=dir$i VALUE=\"".htmlentities(stripslashes($row[3]))."\" CLASS=input SIZE=30 MAXLENGTH=80></TD></TR>\n";
        }
    ?>
          <TR><TD>&nbsp;</TD>
              <TD COLSPAN=3 ALIGN=LEFT><INPUT TYPE=SUBMIT VALUE=" Update Categories "></TD></TR>
    <?php 
        if($seq == "update") {
            if($success)
                echo "      <TR><TD>&nbsp;</TD><TD CLASS=\"header\" ALIGN=LEFT COLSPAN=2>Categories updated.</TD></TR>\n";
            else
                echo "      <TR><TD>&nbsp;</TD><TD CLASS=\"header\" ALIGN=LEFT COLSPAN=2 CLASS=\"error\">Updated failed.</TD></TR>\n";
        }
    ?>
        </TABLE>
        <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
        <INPUT TYPE=HIDDEN NAME=action VALUE="addmgr">
        <INPUT TYPE=HIDDEN NAME=subaction VALUE="categories">
        <INPUT TYPE=HIDDEN NAME=seq VALUE="update">
      </FORM>
    <?php 
        UI::setFocus("name1");
    }
    
    public function addManagerEMail() {
        $instance_chartman = Engine::param('email')['chartman'];
        $date = $_REQUEST["date"];
        $address = $_REQUEST["address"];
        $format = $_REQUEST["format"];
    
    
        list($y,$m,$d) = explode("-", $date);
    
        if($address) {
            // Allow only alphanumeric and {@,-,.} in address
            for($i=0; $i<strlen($address); $i++) {
                $c = strtolower(substr($address, $i, 1));
                if(!($c == "@" || $c == "-" || $c == "." ||
                     ($c >= "0" && $c <= "9") ||
                     ($c >= "a" && $c <= "z")))
                    break;
            }
    
            if($i != strlen($address)) {
                echo "  <P CLASS=\"header\">E-Mail address is invalid.</P>\n";
            } else {
                // Stuff the categories into an array
                $cats = Engine::api(IChart::class)->getCategories();
                $i=1;
                while($cats && ($row = $cats->fetch()))
                    $catcode[$i++] = $row[2];
    
                // Fetch the add        
                $records = Engine::api(IChart::class)->getAdd2($date);
                $this->addManagerGetAlbums($records, $albums);
    
                $from = Engine::param('application')." <$instance_chartman>";
                $subject = Engine::param('station') + ": Adds for $date";
                $body = "";
    
                if($format == "tab") {
                    $boundary = "zk-part-" . md5(uniqid(rand()));
                    $mime = "Content-Type: multipart/mixed; boundary=\"";
                    $mime .= $boundary . "\"\r\nMIME-Version: 1.0\r\n";
    
                    $body .= "--" . $boundary . "\r\n";
                    $body .= "Content-Type: text/plain\r\n\r\n";
                    $body .= "Zookeeper $subject are attached\r\n";
    
                    $body .= "--" . $boundary . "\r\n";
                    $body .= "Content-Type: text/csv; charset=\"iso-8859-1\"\r\n";
                    $body .= "Content-Disposition: attachment; filename=\"";
                    $body .= $subject . ".csv\"\r\n\r\n";
                }
    
                // Setup the headers
                $headers = "From: $from\r\n$mime";
    
                // Emit the add
                while(list($index, $row) = each($albums)) {
                    $ac = "";
                    $cats = explode(",", $row[5]);
                    while(list($index, $cat) = each($cats))
                        $ac .= $catcode[$cat];
                    if($format == "tab") {
                        $line = $row[3] . "\t" . $row[4] . "\t" . $ac . "\t" .
                              $row[1] . "\t";
                        $artist = preg_match("/^\[coll\]/i", $row["artist"])?"COLL":$row["artist"];
                        $label =  str_replace(" Records", "", $row["label"]);
                        $label = str_replace(" Recordings", "", $label);
    
                        // Append 7", 10", or 12" to artist name, as appropriate
                        switch($row["medium"]) {
                        case "S":
                            $artist .= " [7\\\"]";
                            break;
                        case "T":
                            $artist .= " [10\\\"]";
                            break;
                        case "V":
                            $artist .= " [12\\\"]";
                            break;
                        }
    
                        // Emit Artist/Album/Label names
                        $line .= $artist . "\t" .
                                 $row["album"] . "\t" .
                                 $label . "\t" .
                                 $row["tag"] . "\r\n";
                    } else
                        $line = sprintf("%-2s %3d %-28s %-30s %-12s\r\n",
                              $ac, $row[1], substr(UI::deLatin1ify($row["artist"]), 0, 28),
                              substr(UI::deLatin1ify($row["album"]), 0, 30),
                              substr(UI::deLatin1ify($row["LABELNAME"]), 0, 12));
                    $body .= $line;
                }
                if($format == "tab")
                    $body .= "\r\n--" . $boundary . "--\r\n";
                else {
                    // Emit the postamble
                    $body .= "\n--\nPost your music reviews online!\r\n";
                    $body .= Engine::param('station')." ".
                             Engine::param('application').":  ".
                             UI::getBaseUrl()."\r\n";
                }
    
                // send the mail
                $stat = mail($address, $subject, $body, $headers);
    
                // Check for errors
                     if(!$stat) {
                    echo "  <P CLASS=\"header\">Possible Problem Sending E-Mail</P>\n";
                    echo "  <P>There may have been a problem sending your e-mail.  ";
                    echo "</P>\n";
                    // no error messages from PHP mail() function
                    //echo "The mailer reports the following error:</P>\n  <PRE>\n";
                    //echo error_get_last()['message'];
                    //echo "\n</PRE>\n";
                } else {
                    echo "  <P CLASS=\"header\">E-Mail Sent!</P>\n";
                    echo "  <P>Please check to see whether your e-mail was ";
                    echo "received.  If not, you may try again.</P>\n";
                }
            }
            echo "  <HR>\n";
        }
    ?>
      <FORM ACTION="" METHOD=POST>
        <TABLE CELLPADDING=2 BORDER=0>
          <TR><TD ALIGN=RIGHT>Add For:</TD><TD ALIGN=LEFT><?php echo date("j F Y", mktime(0,0,0,$m,$d,$y));?></TD></TR>
          <TR><TD ALIGN=RIGHT>E-Mail To:</TD><TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=address VALUE="<?php echo htmlentities($address);?>" CLASS=INPUT SIZE=30></TD></TR>
          <TR><TD ALIGN=RIGHT>Format:</TD><TD ALIGN=LEFT><INPUT TYPE=RADIO NAME=format VALUE="normal" CHECKED>Normal&nbsp;(for&nbsp;Noise)&nbsp;&nbsp;&nbsp;<INPUT TYPE=RADIO NAME=format VALUE="tab">Tab&nbsp;Delimited</TD></TR>
          <TR><TD></TD><TD><INPUT TYPE=SUBMIT VALUE=" E-Mail Add "></TD></TR>
        </TABLE>
        <INPUT TYPE=HIDDEN NAME=date VALUE="<?php echo $date;?>">
        <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
        <INPUT TYPE=HIDDEN NAME=action VALUE="addmgr">
        <INPUT TYPE=HIDDEN NAME=subaction VALUE="addsemail">
      </FORM>
    <?php 
        UI::setFocus("address");
    }
    
    public function viewCurrents($tag) {
        $row = Engine::api(IChart::class)->getAlbumByTag($tag);
        if($row) {
            // Stuff the categories into an array
            $i=1;
            $categories = Engine::api(IChart::class)->getCategories();
            while($categories && ($cat = $categories->fetch()))
                $cats[$i++] = $cat["name"];
    
            $header = 0;
     
            if(!$header) {
                $today = date("Y-m-d");
                if($this->session->isAuth("u") &&
                        $row["pulldate"] > $today &&
                        $row["adddate"] <= $today)
                    $num = "&nbsp;&nbsp;(File #".$row["afile_number"].")";
                echo "<TABLE WIDTH=\"100%\">\n";
                echo "  <TR><TH ALIGN=LEFT CLASS=\"secdiv\">A-File Activity$num</TH>";
                echo "</TR>\n</TABLE>\n";
                $header = 1;
            }
    
            echo "<TABLE CELLPADDING=2 CELLSPACING=2>\n";
            echo "  <TR><TD ALIGN=RIGHT>Add Date:</TD>";
            echo "<TD>" .$row["adddate"]. "</TD><TD WIDTH=20>&nbsp;</TD>";
            echo "<TD ALIGN=RIGHT>Pull Date:</TD>";
            echo "<TD>" .$row["pulldate"]. "</TD><TD WIDTH=20>&nbsp;</TD>";
    
            if($row["category"]) {
                echo "<TD ALIGN=RIGHT>Charts:</TD><TD>";
                $emitted = false;
                $albumcats = explode(",", $row["category"]);
                while(list($index, $cat) = each($albumcats)) {
                    if(!$cats[$cat] || substr($cats[$cat], 0, 1) == "(") continue;
                    if($emitted) echo ", ";
                    echo htmlentities(stripslashes($cats[$cat]));
                    $emitted = true;
                }
                echo "</TD>";
            }
            echo "</TR>\n</TABLE>\n";
    
            $plays = Engine::api(IChart::class)->getAlbumPlays($tag, $row["adddate"], $row["pulldate"], 8);
            $i = 0;
            while($plays && ($play = $plays->fetch()))
                $week[$i++] = array($play["week"], $play["plays"]);
    
            if($i) {
                echo "<TABLE CELLPADDING=4 CELLSPACING=0 BORDER=0>\n";
                echo "  <TR><TD ALIGN=RIGHT CLASS=\"sub\">Week Ending:</TD>";
                for($j=0; $j<$i; $j++) {
                    list($y,$m,$d) = explode("-", $week[$j][0]);
                    $displayDate = date("j M", mktime(0,0,0,$m,$d,$y));
                    echo "<TD ALIGN=CENTER CLASS=\"currentsTop\">$displayDate</TD>";
                }
                echo "</TR>\n";
    
    
                echo "  <TR><TD ALIGN=RIGHT CLASS=\"sub\">Airplays:</TD>";
                for($j=0; $j<$i; $j++) {
                    echo "<TD ALIGN=CENTER CLASS=\"currentsBottom\">".$week[$j][1]."</TD>";
                }
                echo "</TR>\n";
                echo "</TABLE>\n";
            }
            echo "<BR>\n";
        }
    }

    private function aFileActivitySortFn($a, $b) {
        switch($_REQUEST["sortBy"]) {
        case "DJ":
        case "DJ-":
            // Field is formatted "FirstName [MI] LastName"; we want to flip it
            // to "LastName FirstName" for the purposes of the sort comparison.
            $name1=explode(" ", $a["name"]);
            $name2=explode(" ", $b["name"]);
            $name1c=$name1[sizeof($name1)-1] . " " . $name1[0];
            $name2c=$name2[sizeof($name2)-1] . " " . $name2[0];
            $retval = strcasecmp($name1c, $name2c);
            break;
        case "Airname":
        case "Airname-":
            $retval = strcasecmp($a["airname"], $b["airname"]);
            break;
        case "Show":
        case "Show-":
            $retval = strcasecmp($a["description"], $b["description"]);
            break;
        case "D":
        case "D-":
            $retval = $a["duration"] - $b["duration"];
            break;
        case "Tracks":
        case "Tracks-":
            $retval = $a["total"] - $b["total"];
            break;
        case "AFile":
        case "AFile-":
            $retval = $a["afile"] - $b["afile"];
            break;
        case "%":
        case "%-":
            $retval = $a["percent"] - $b["percent"];
            break;
        default:
            $retval = strcmp($a["showdate"], $b["showdate"]);
            if($retval == 0)
                $retval = $a["start"] - $b["start"];
            break;
        }
        return (substr($_REQUEST["sortBy"], -1, 1) == "-")?-$retval:$retval;
    }
    
    private function aFileActivityGetReport(&$records, &$albums) {
        while($row = $records->fetch()) {
            $userName = Engine::api(ILibrary::class)->search(ILibrary::PASSWD_NAME, 0, 1, $row["dj"]);
            if(sizeof($userName) && $userName[0]["realname"])
                $row["name"] = $userName[0]["realname"];
            else
                $row["name"] = $row["dj"];
    
            if($row["total"] > 0)
                $row["percent"] = round($row["afile"] / $row["total"] * 100);
            else
                $row["percent"] = 0;
    
            $start = substr($row["showtime"], 0, 2);
            $end = substr($row["showtime"], 5, 2);
            $row["start"] = $start;
            $row["end"] = $end;
    
            if($end < $start)
                $end += 24;
            $duration = $end - $start;
            if($duration == 0)
                $duration = 3;
            $row["duration"] = $duration;
    
            $albums[] = $row;
        }
    }
    
    private function aFileActivityEmitReport(&$records, $subaction, $static=0) {
        $total = 0;
        $afile = 0;
        $last = -1;
    
        // Setup default sortBy
        $sortBy = $_REQUEST["sortBy"];
        if(!strlen($sortBy))
            $_REQUEST["sortBy"] = "Date";
    
        echo "  <TABLE CELLPADDING=2 CELLSPACING=0 BORDER=0";
        echo " CLASS=\"afileactivity\"";
        echo ">\n";
        echo "    <TR>\n";
        $this->addManagerColHeader($subaction, "Date", $static);
        echo "        <TD></TD>\n";
        $this->addManagerColHeader($subaction, "DJ", $static);
        $this->addManagerColHeader($subaction, "Airname", $static);
        $this->addManagerColHeader($subaction, "Show", $static);
        $this->addManagerColHeader($subaction, "D", $static);
        $this->addManagerColHeader($subaction, "Tracks", $static);
        $this->addManagerColHeader($subaction, "AFile", $static);
        $this->addManagerColHeader($subaction, "%", $static);
        echo "    </TR>\n";
    
        // Get albums into array
        $this->aFileActivityGetReport($records, $albums);
    
        // Sort it
        usort($albums, array($this, "aFileActivitySortFn"));
    
        while(list($index, $row) = each($albums)) {
            if($_REQUEST["sortBy"] == "Date" && $last > 5 && $last != $row["start"]) {
                list($y, $m, $d) = explode("-", $lastDate);
                $showDate = str_replace(" ", "&nbsp;", date("d D", mktime(0,0,0,$m,$d,$y)));
    
                echo "    <TR CLASS=\"noPlaylist\">\n      <TD VALIGN=TOP>$showDate</TD>\n      <TD VALIGN=TOP CLASS=\"sub2\">".$last."00&#8209;".$row["start"]."00&nbsp;</TD>\n      <TD COLSPAN=7>No playlist</TD>\n    </TR>\n";
            }
            $last = $row["end"];
            $lastDate = $row["showdate"];
    
            if($row["afile"] < $row["duration"] * AddManager::MIN_REQUIRED)
                echo "    <TR CLASS=\"noQuota\">\n";
            else
                echo "    <TR CLASS=\"hborder\">\n";
    
            // Date
            list($y, $m, $d) = explode("-", $row["showdate"]);
            $showDate = str_replace(" ", "&nbsp;", date("d D", mktime(0,0,0,$m,$d,$y)));
            echo "      <TD VALIGN=TOP>".$showDate."</TD>\n";
            echo "      <TD VALIGN=TOP CLASS=\"sub\">".str_replace("-", "&#8209;", $row["showtime"])."&nbsp;</TD>\n";
    
            // User/Airname/Show names
            $name = str_replace(" ", "&nbsp;", htmlentities($row["name"]));
            echo "      <TD VALIGN=TOP>" . $name . "</TD>\n";
            //$name = str_replace(" ", "&nbsp;", htmlentities($row["airname"]));
            $name = htmlentities($row["airname"]);
            echo "      <TD VALIGN=TOP>" . $name . "</TD>\n";
    
            echo "      <TD VALIGN=TOP><A CLASS=\"nav\" HREF=\"".
                         "?action=viewDJ&amp;playlist=".$row["id"].
                         "&amp;seq=selList&amp;session=".$this->session->getSessionID()."\">".
                         htmlentities($row["description"]) . "</A></TD>\n";
    
            // Duration
            echo "      <TD VALIGN=TOP ALIGN=RIGHT>" . $row["duration"] . "</TD>\n";
            
            // Totals
            echo "      <TD VALIGN=TOP ALIGN=RIGHT>" . $row["total"] . "</TD>\n";
            echo "      <TD VALIGN=TOP ALIGN=RIGHT>" . $row["afile"] . "</TD>\n";
            echo "      <TD VALIGN=TOP ALIGN=RIGHT>" . $row["percent"] . "</TD>\n";
    
            $total += $row["total"];
            $afile += $row["afile"];
    
            echo "    </TR>\n";
        }
    
        if($total > 0)
            $percent = round($afile / $total * 100);
        else
            $percent = 0;
        echo "    <TR>\n      <TD COLSPAN=6>&nbsp;</TD>\n      <TD><HR></TD>\n      <TD><HR></TD>\n      <TD><HR></TD>\n    </TR>\n";
        echo "    <TR>\n";
        echo "      <TH COLSPAN=6 ALIGN=RIGHT>Total:</TH>\n";
        echo "      <TH VALIGN=TOP ALIGN=RIGHT>" . $total . "</TH>\n";
        echo "      <TH VALIGN=TOP ALIGN=RIGHT>" . $afile . "</TH>\n";
        echo "      <TH VALIGN=TOP ALIGN=RIGHT>" . $percent . "</TH>\n";
        echo "    </TR>\n";
     
        echo "  </TABLE>\n";
    }
    
    public function aFileActivityShowWeekly() {
        $date = $_REQUEST["date"];
    
        // Setup default sortBy
        $sortBy = $_REQUEST["sortBy"];
        if(!strlen($sortBy))
            $_REQUEST["sortBy"] = "Date";
    
    ?>
      <TABLE CELLPADDING=2 CELLSPACING=0 WIDTH="100%" BORDER=0>
        <TR>
          <TH ALIGN=LEFT>
            <FORM ACTION="" METHOD=POST>
              Activity for week ending:
              <SELECT NAME=date onChange='this.form.submit()'>
    <?php 
        $records = Engine::api(IChart::class)->getChartDates(52);
        $datevalid = false;
        while($row = $records->fetch()) {
            if(!$first) $first = $row[0];
            $selected = ($row[0] == $date)?" SELECTED":"";
            $datevalid |= $selected != "";
            echo "            <OPTION VALUE=\"$row[0]\"$selected>$row[0]\n";
        }
    ?>          </SELECT>
              <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
              <INPUT TYPE=HIDDEN NAME=action VALUE="addmgr">
              <INPUT TYPE=HIDDEN NAME=subaction VALUE="activity">
              <INPUT TYPE=HIDDEN NAME=seq VALUE="update">
            </FORM>
          </TH>
        </TR>
      </TABLE>
    <?php 
        if(!$datevalid && $first) $date = $first;
        if($date) {
            $records = Engine::api(IChart::class)->getWeeklyActivity($date);
            $this->aFileActivityEmitReport($records, "activity");
        }
        UI::setFocus();
    }
}