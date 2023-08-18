<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2023 Jim Mason <jmason@ibinx.com>
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

use JSMin\JSMin;

class AddManager extends MenuItem {
    const MIN_REQUIRED = 4;        // minimum required A-File tracks/hour

    const MAX_CAT_COUNT = 16;      // requires DB schema change if > 16

    const DAY_START_TIME = "0600";
    const DAY_END_TIME = "0000";

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
    private $categoryMapCache; // cache for virtual property 'categoryMap'

    public function __get($var) {
        // lazy load the chart categoryMap
        if($var == 'categoryMap') {
            if(!isset($this->categoryMapCache))
                $this->categoryMapCache = Engine::api(IChart::class)->getCategories(self::MAX_CAT_COUNT);
            return $this->categoryMapCache;
        }
    }

    public function getSubactions($action) {
        return self::$subactions;
    }

    public function processLocal($action, $subaction) {
        $extra = "";
        if(!$subaction &&
                 ($this->session->isAuth("n") || $this->session->isAuth("o")))
            $extra .= "<A CLASS='nav' HREF='#top' onClick=window.open('?target=afile')>Print View</A>&nbsp;&nbsp;";
        $extra .= "<SPAN CLASS='sub'><B>Adds Feed:</B></SPAN> <A TYPE='application/rss+xml' HREF='zkrss.php?feed=adds'><IMG SRC='img/rss.png' ALT='rss'></A><BR><IMG SRC='img/blank.gif' WIDTH=1 HEIGHT=2 BORDER=0 ALT=''>";

        return $this->dispatchSubaction($action, $subaction, $extra);
    }

    public function addManagerEmitAlbums(&$records, $subaction, $static=0, $sort=0) {
        $this->addVar('catmap', Engine::api(IChart::class)->getCategories());

        // Get albums into an array
        $albums = $records->asArray();
        if($sort)
            usort($albums, function($a, $b) {
                // primary sort by artist name
                $cmp = $a["artist"] <=> $b["artist"];
                switch($cmp) {
                case 0:
                    // secondary sort by album title
                    $cmp = $a["album"] <=> $b["album"];
                    break;
                default:
                    break;
                }
                return $cmp;
            });

        // Mark reviewed albums
        $libraryAPI = Engine::api(ILibrary::class);
        $libraryAPI->markAlbumsReviewed($albums);
        if(!$static && $this->session->isAuth("u"))
            $libraryAPI->markAlbumsPlayable($albums);

        $this->setTemplate('currents/albums.html');
        $this->addVar('albums', $albums);
        $this->addVar('static', $static);
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
            if($this->session->isAuth("u"))
                $results = Engine::api(IChart::class)->getCurrentsWithPlays(date("Y-m-d"));
            else
                $results = Engine::api(IChart::class)->getCurrents(date("Y-m-d"));

            $this->addManagerEmitAlbums($results, "", false, true);
        }
    }
    
    public function addManagerShowAdd() {
        $date = $_REQUEST["date"] ?? "";
        $records = Engine::api(IChart::class)->getAddDates(52)->asArray();
        $this->addVar('adddates', $records);

        if(count($records) && !array_reduce($records, function($carry, $item) use($date) {
            return $carry |= $item['adddate'] == $date;
        }, false))
            $_REQUEST['date'] = $date = $records[0]['adddate'];

        if($date) {
            $records = Engine::api(IChart::class)->getAdd($date);
            $this->addManagerEmitAlbums($records, "adds");
            $this->setTemplate('currents/adds.html');
        }
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
            $name = isset($albumrec[0]["name"])?$albumrec[0]["name"]:"(Unknown)";
            echo "          <TR><TD ALIGN=RIGHT>Label:</TD><TH>" . htmlentities($name) . "</TH></TR>\n";
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
        // Setup selected categories
        $cl = explode(",", $catlist);
        foreach($cl as $index => $cat)
             $selcats[(int)$cat - 1] = "X";
    
        $this->skipVar("catlist");
    
        // Emit the checkbox table 
        for($i=0; $i<4; $i++) {
            echo "        <TR><TD>";
            $selected = $selcats[$i]?" CHECKED":"";
            if($this->categoryMap[$i]["name"])
                echo "<INPUT TYPE=CHECKBOX NAME=cat$i$selected>".htmlentities(stripslashes($this->categoryMap[$i]["name"]));
            echo "</TD><TD>";
            $selected = $selcats[$i+4]?" CHECKED":"";
            if($this->categoryMap[$i+4]["name"])
                echo "<INPUT TYPE=CHECKBOX NAME=cat".($i+4)."$selected>".htmlentities(stripslashes($this->categoryMap[$i+4]["name"]));
            echo "</TD><TD>";
            $selected = $selcats[$i+8]?" CHECKED":"";
            if($this->categoryMap[$i+8]["name"])
                echo "<INPUT TYPE=CHECKBOX NAME=cat".($i+8)."$selected>".htmlentities(stripslashes($this->categoryMap[$i+8]["name"]));
            echo "</TD><TD>";
            $selected = $selcats[$i+12]?" CHECKED":"";
            if($this->categoryMap[$i+12]["name"])
                echo "<INPUT TYPE=CHECKBOX NAME=cat".($i+12)."$selected>".htmlentities(stripslashes($this->categoryMap[$i+12]["name"]));
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
    
        if($validate && $_SERVER['REQUEST_METHOD'] == 'POST') {
            // Add the album
            $emitted = false;
            $catstr = "";
            for($i=0; $i<self::MAX_CAT_COUNT; $i++)
                if($_POST["cat".$i]) {
                    if($emitted) $catstr .= ",";
                    $catstr .= (string)($i+1);
                    $emitted = true;
                }
            if(Engine::api(IChart::class)->addAlbum($aid, $tag, $adddate, $pulldate, $catstr)) {
                // Clear the form data
                $this->skipVar("aid");
                $this->skipVar("tag");
                for($i=0; $i<self::MAX_CAT_COUNT; $i++)
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
            <TR><TD ALIGN=RIGHT>Add Date:</TD><TD ALIGN=LEFT class='date'><?php echo $adddate;?></TD></TR>
            <TR><TD ALIGN=RIGHT>Pull Date:</TD><TD ALIGN=LEFT class='date'><?php echo $pulldate;?></TD></TR>
            <TR><TD ALIGN=RIGHT>Categories:</TD><TD ALIGN=LEFT><?php 
        $emitted = false;
        for($i=0; $i<self::MAX_CAT_COUNT; $i++)
            if($_POST["cat".$i]) {
                if($emitted) echo ", ";
                echo htmlentities(stripslashes($this->categoryMap[$i]["name"]));
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
                echo "  <FORM id='add-manager' ACTION=\"\" METHOD=POST>\n";
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
    
        if($validate && $_SERVER['REQUEST_METHOD'] == 'POST') {
            // Add the album
            $emitted = false;
            $catstr = "";
            for($i=0; $i<self::MAX_CAT_COUNT; $i++)
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
            <TR><TD ALIGN=RIGHT>Add Date:</TD><TD ALIGN=LEFT class='date'><?php echo $adddate;?></TD></TR>
            <TR><TD ALIGN=RIGHT>Pull Date:</TD><TD ALIGN=LEFT class='date'><?php echo $pulldate;?></TD></TR>
            <TR><TD ALIGN=RIGHT>Categories:</TD><TD ALIGN=LEFT><?php 
        $emitted = false;
        for($i=0; $i<self::MAX_CAT_COUNT; $i++)
            if($_POST["cat".$i]) {
                if($emitted) echo ", ";
                echo htmlentities(stripslashes($this->categoryMap[$i]["name"]));
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
                echo "  <FORM id='add-manager' ACTION=\"\" METHOD=POST>\n";
                echo "    <TABLE CELLPADDING=0 CELLSPACING=0 BORDER=0>\n      <TR><TH ALIGN=LEFT>$title</TH></TR>\n      <TR><TD HEIGHT=130 VALIGN=MIDDLE>\n";
    
            }
    
            // Handle default case
            if(!$seq || !$this->addPanels[$seq]) {
                $seq = "";
    
                // Pull in the values for this album
                $row = Engine::api(IChart::class)->getAlbum($id);
                if($row) {
                        $_REQUEST["aid"] = $row["afile_number"];
                        $_REQUEST["tag"] = $row["tag"];
                        $_REQUEST["adddate"] = $row["adddate"];
                        $_REQUEST["pulldate"] = $row["pulldate"];
                        $_REQUEST["catlist"] = $row["category"];
                    $this->emitHidden("tag", $row["tag"]);
                    $this->emitHidden("adddate", $row["adddate"]);
                    $this->emitHidden("pulldate", $row["pulldate"]);
                    $this->emitHidden("catlist", $row["category"]);
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
    
        // Setup the date for AddManagerShowAdd() redisplay
        $row = Engine::api(IChart::class)->getAlbum($id);
        if($row) {
            $_REQUEST["date"] = $row["adddate"];
        }
    
        if($id && $_SERVER['REQUEST_METHOD'] == 'POST')
            Engine::api(IChart::class)->deleteAlbum($id);
        $this->addManagerShowAdd();
    }
    
    public function addManagerCats() {
        $seq = $_REQUEST["seq"] ?? '';
        $this->addVar('seq', $seq);

        if($seq == "update" && $_SERVER['REQUEST_METHOD'] == 'POST') {
            $success = true;
            for($i=1; $success && $i<=self::MAX_CAT_COUNT; $i++) {
                $name = $_POST["name".$i];
                $code = $_POST["code".$i];
                $dir = $_POST["dir".$i];
                $email = $_POST["email".$i];
                $success &= Engine::api(IChart::class)->updateCategory($i, $name, $code, $dir, $email);
            }
            $this->addVar('success', $success);
        }

        $this->addVar('cats', $this->categoryMap);
        $this->setTemplate('currents/cats.html');
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
                // Fetch the add        
                $albums = Engine::api(IChart::class)->getAdd($date)->asArray();
    
                $from = Engine::param('application')." <$instance_chartman>";
                $subject = Engine::param('station_title').": Adds for $date";
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
                foreach($albums as $index => $row) {
                    $ac = "";
                    $catsx = explode(",", $row["afile_category"]);
                    foreach($catsx as $index => $cat)
                        if($cat)
                            $ac .= $this->categoryMap[$cat-1]["code"];
                    if($format == "tab") {
                        $line = $row["adddate"] . "\t" . $row["pulldate"] . "\t" . $ac . "\t" .
                              $row["afile_number"] . "\t";
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
                              $ac, $row["afile_number"], substr(UI::deLatin1ify($row["artist"]), 0, 28),
                              substr(UI::deLatin1ify($row["album"]), 0, 30),
                              substr(UI::deLatin1ify($row["label"]), 0, 12));
                    $body .= $line;
                }
                if($format == "tab")
                    $body .= "\r\n--" . $boundary . "--\r\n";
                else {
                    // Emit the postamble
                    $body .= "\n--\nPost your music reviews online!\r\n";
                    $body .= Engine::param('station_title')." ".
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
      <FORM id='add-manager' ACTION="" METHOD=POST>
        <TABLE CELLPADDING=2 BORDER=0>
          <TR><TD ALIGN=RIGHT>Add For:</TD><TD ALIGN=LEFT><?php echo date("j F Y", mktime(0,0,0,$m,$d,$y));?></TD></TR>
          <TR><TD ALIGN=RIGHT>E-Mail To:</TD><TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=address VALUE="<?php echo htmlentities($address);?>" CLASS=INPUT SIZE=30></TD></TR>
          <TR><TD ALIGN=RIGHT>Format:</TD><TD ALIGN=LEFT><INPUT TYPE=RADIO NAME=format VALUE="normal" CHECKED>Normal&nbsp;(for&nbsp;Noise)&nbsp;&nbsp;&nbsp;<INPUT TYPE=RADIO NAME=format VALUE="tab">Tab&nbsp;Delimited</TD></TR>
          <TR><TD></TD><TD><INPUT TYPE=SUBMIT VALUE=" E-Mail Add "></TD></TR>
        </TABLE>
        <INPUT TYPE=HIDDEN NAME=date VALUE="<?php echo $date;?>">
        <INPUT TYPE=HIDDEN NAME=action VALUE="addmgr">
        <INPUT TYPE=HIDDEN NAME=subaction VALUE="addsemail">
      </FORM>
    <?php 
        UI::setFocus("address");
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
        $lastShowEnd = self::DAY_START_TIME;
        $lastDate = null;
        $lastDateRaw = null;

        // Get albums into array
        $this->aFileActivityGetReport($records, $shows);

        $result = [];
        foreach($shows as $show) {
            $showTime = $show["showtime"];
            $startStopAr = explode("-", $showTime);
            $showStart = $startStopAr[0];
            $showEnd = $startStopAr[1];
            list($y, $m, $d) = explode("-", $show["showdate"]);
            $showDate = date("D", mktime(0,0,0,$m,$d,$y));

            if($showDate != $lastDate ) {
                if($showStart != self::DAY_END_TIME &&
                        $lastDate && $lastShowEnd != self::DAY_END_TIME) {
                    $result[] = [
                        'noplaylist' => true,
                        'date' => $lastDateRaw . " " . $lastDate,
                        'time' => $lastShowEnd . "-" . self::DAY_END_TIME
                    ];
                }
            } else if($showStart > $lastShowEnd &&
                        $showStart != self::DAY_START_TIME) {
                // insert no playlist row if there is a gap in the regular 
                // program day, eg 6am - 11:59:59pm.
                $result[] = [
                    'noplaylist' => true,
                    'date' => $show['showdate'] . " " . $showDate,
                    'time' => $lastShowEnd . "-" . $showStart
                ];
            }

            $lastShowEnd = $showEnd;
            $lastDate = $showDate;
            $lastDateRaw = $show['showdate'];

            $show["date"] = $show['showdate'] . ' ' . $showDate;
            $show["time"] = $showTime;
            $show["noquota"] = $show["afile"] < $show["duration"] * self::MIN_REQUIRED;
            $result[] = $show;
            $total += $show["total"];
            $afile += $show["afile"];
        }
    
        $percent = 0;
        if($total > 0)
            $percent = round($afile / $total * 100);

        $this->addVar('shows', $result);
        $this->addVar('total', $total);
        $this->addVar('afile', $afile);
        $this->addVar('percent', $percent);
    }
    
    public function aFileActivityShowWeekly() {
        $dates = Engine::api(IChart::class)->getChartDates(52)->asArray();
        $this->addVar('dates', $dates);
        $first = count($dates) ? $dates[0]['week'] : null;
        $date = $_REQUEST["date"] ?? $first;
        $this->addVar('seldate', $date);
        $this->setTemplate('currents/activity.html');

        if($date) {
            $records = Engine::api(IChart::class)->getWeeklyActivity($date);
            $this->aFileActivityEmitReport($records, "activity");
        }
    }

    public function emitPrintableCurrentFile() {
        $results = Engine::api(IChart::class)->getCurrents(date("Y-m-d"));
        $this->addManagerEmitAlbums($results, "", true, true);
        $this->setTemplate('currents/export.html');
    }
}
