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

class Charts extends MenuItem {
    private static $subactions = [
        [ "a", "", "Top 30", "chartTop30" ],
        [ "a", "weekly", "Weekly", "chartWeekly" ],
        [ "a", "amalgamated", "Amalgamated", "chartMonthly" ],
        [ "a", "subscribe", "Subscribe", "emitSubscribe" ],
        [ "n", "chartemail", "E-Mail", "chartEMail" ],
        [ "c", "weeklyCMJ", "", "chartCMJ" ],
    ];

    // ZK chart -> CMJ charttype mapping
    private static $charttype = [
        "5"=>"5",   // hip-hop
        "7"=>"6",   // reggae/world
        "6"=>"7",   // jazz
        "4"=>"4",   // heavy shit
        "3"=>"9",   // dance
    ];

    public function processLocal($action, $subaction) {
        $extra = "<SPAN CLASS=\"sub\"><B>Chart Feed:</B></SPAN> <A TYPE=\"application/rss+xml\" HREF=\"zkrss.php?feed=charts\"><IMG SRC=\"img/rss.gif\" ALIGN=MIDDLE WIDTH=36 HEIGHT=14 BORDER=0 ALT=\"rss\"></A><BR><IMG SRC=\"img/blank.gif\" WIDTH=1 HEIGHT=2 BORDER=0 ALT=\"\">";
        return $this->dispatchSubAction($action, $subaction,
                                            self::$subactions, $extra);
    }

    public function chartTop30() {
        $maintop = 30;
        $top = 10;
        $weeks = Engine::api(IChart::class)->getChartDates(2);
        $count = 0;
        echo "<TABLE CLASS=\"top30\" BORDER=0 CELLPADDING=5 CELLSPACING=0>\n  <TR>\n";
        while($weeks && ($week = $weeks->fetch())) {
            $endDate = $week["week"];
            list($y, $m, $d) = explode("-", $endDate);
            $formatDate = date("l, j F Y", mktime(0,0,0,$m,$d,$y));
            echo "    <TD ALIGN=LEFT VALIGN=TOP CLASS=\"top30\">\n";
            echo "      <TABLE CELLPADDING=5 CELLSPACING=0 BORDER=0>\n";
            echo "        <TR><TH ALIGN=RIGHT COLSPAN=2>Week ending $formatDate</TH></TR>\n";
    
            // JHM FIXME TBD - hardcoded for now
            $this->emitChart2("", $endDate, $maintop);
            $this->emitChart2("", $endDate, $top, "5");   // hip-hop
            $this->emitChart2("", $endDate, $top, "7");   // reggae/world
            if($this->session->isAuth("r"))
                $this->emitChart2("", $endDate, $top, "9");   // reggae
            $this->emitChart2("", $endDate, $top, "6");   // jazz
            $this->emitChart2("", $endDate, $top, "1");   // blues
            $this->emitChart2("", $endDate, $top, "2");   // country
            $this->emitChart2("", $endDate, $top, "4");   // heavy shit
            $this->emitChart2("", $endDate, $top, "3");   // dance
            $this->emitChart2("", $endDate, $top, "8");   // C/X
            echo "      </TABLE>\n    </TD>\n";
        }
        echo "  </TR>\n</TABLE>\n";
        UI::setFocus();
    }
    
    public function emitChartYearNav($currentYear, $header=0) {
        echo "  <TABLE>\n    <TR><TH>View charts for:</TH>";
        echo "<TD>&nbsp;&nbsp;</TD>";
        $years = Engine::api(IChart::class)->getChartYears();
        while($years && ($year = $years->fetch())) {
            
            if($year[0] == $currentYear)
                echo "<TH>$currentYear</TH>";
            else
                echo "<TH><A CLASS=\"nav\" HREF=\"?action=viewChart&amp;subaction=weekly&amp;year=" . $year[0] . "&amp;session=".$this->session->getSessionID()."\">" . $year[0] . "</A></TH>";
            echo "<TD>&nbsp;&nbsp;&nbsp;</TD>";
        }
        $urls = Engine::param('urls');
        if(array_key_exists('old_charts', $urls))
            echo "    <TD><A HREF=\"".$urls['old_charts']."\">Old airplay charts</A> are available here.</TD>";
        echo "    </TR>\n  </TABLE>\n";
    }
    
    public function chartWeekly() {
        $station = Engine::param('station', 'KZSU');
    
        $chartAPI = Engine::api(IChart::class);
        $year = $_REQUEST["year"];
        $month = $_REQUEST["month"];
        $day = $_REQUEST["day"];
    
        if(!$month) {
            if(!$year) {
                // current year
                $today = getdate(time());
                $years = $chartAPI->getChartYears();
                if($years) {
                    $yearrec = $years->fetch();
                    $year = $yearrec[0];
                } else
                    $year = $today["year"];
                $month = $today["mon"];
            }
    
    ?>
      <!--P>These charts are based on actual airplay, and are what we
         report on. (For those of you outside the college radio biz, this is
         unusual.) <A HREF="http://www.cmjmusic.com/" TARGET="_blank">College
         Media Journal</A> and <A HREF="http://www.gavin.com/"
         TARGET="_blank">Gavin</A> include our weekly numbers in their reports.</P>
    
      <P>Note that the 'Main' section of our weekly charts now lists all of
         our current new releases which received airplay during the charting
         week.</P-->
    <?php 
            $this->emitChartYearNav($year, 1);
            echo "  <TABLE WIDTH=\"100%\">\n";
            echo "    <TR><TD>\n      <UL>\n";
            $weeks = $chartAPI->getChartDatesByYear($year);
            while($weeks && ($week = $weeks->fetch())) {
                list($y, $m, $d) = explode("-", $week["week"]);
                if($y != $year)
                    continue;
                if($month != $m) {
                    echo "      </UL><UL>\n";
                    $month = $m;
                }
                echo "        <LI><A HREF=\"?action=viewChart&amp;subaction=weekly&amp;year=$y&amp;month=$m&amp;day=$d&amp;session=".$this->session->getSessionID()."\">Week ending ".date("j F Y", mktime(0,0,0,$m,$d,$y))."</A>\n";
            }
            echo "      </UL>\n    </TD></TR>\n";
            echo "  </TABLE>\n";
    
            $this->emitChartYearNav($year, 0);
            UI::setFocus();
            return;
        }
    
        if($monthly) {
            $startDate = $chartAPI->getMonthlyChartStart($month, $year);
            $endDate = $chartAPI->getMonthlyChartEnd($month, $year);
            $displayDate = date("F Y", mktime(0,0,0,$month,1,$year));
            echo "<P CLASS=\"header\">$station chart for month of $displayDate</P>\n";
        } else {
            $startDate = "";
            $endDate = "$year-$month-$day";
            $displayDate = date("j F Y", mktime(0,0,0,$month,$day,$year));
            if($this->session->isAuth("c")) {
    ?>
    <TABLE WIDTH="100%">
      <TR>
        <TH ALIGN=LEFT><?php echo $station; ?> chart for the week ending <?php echo $displayDate; ?></TH>
        <TH ALIGN=RIGHT>
          <FORM ACTION="" METHOD=POST>
            <INPUT TYPE=SUBMIT VALUE=" CMJ Export ">
            <INPUT TYPE=HIDDEN NAME=action VALUE=viewChart>
            <INPUT TYPE=HIDDEN NAME=subaction VALUE=weeklyCMJ>
            <INPUT TYPE=HIDDEN NAME=year VALUE=<?php echo $year; ?>>
            <INPUT TYPE=HIDDEN NAME=month VALUE=<?php echo $month; ?>>
            <INPUT TYPE=HIDDEN NAME=day VALUE=<?php echo $day; ?>>
            <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
          </FORM>
        </TD>
      </TR>
    </TABLE><BR>
    <?php 
            } else
                echo "<P CLASS=\"header\">$station chart for the week ending $displayDate</P>\n";
        }
    
    // weekly = hip hop, reggae/world, jazz, heavy shit, dance, classical/exp
    //     no limits
    // monthly = hip hop, reggae/world, jazz, blues, country, heavy shit, dance
    //     limit to 60 main, 10/cat
    
        $mainLimit = $monthly?"60":"";
        $catLimit = $monthly?"10":"";
    
        // JHM FIXME TBD - hardcoded for now
        $this->emitChart($startDate, $endDate, $mainLimit);
        $this->emitChart($startDate, $endDate, $catLimit, "5"); // hip-hop
        $this->emitChart($startDate, $endDate, $catLimit, "7"); // reggae/world
        if($this->session->isAuth("r"))
            $this->emitChart($startDate, $endDate, $catLimit, "9"); // reggae
        $this->emitChart($startDate, $endDate, $catLimit, "6"); // jazz
        $this->emitChart($startDate, $endDate, $catLimit, "1"); // blues
        $this->emitChart($startDate, $endDate, $catLimit, "2"); // country
        $this->emitChart($startDate, $endDate, $catLimit, "4"); // heavy shit
        $this->emitChart($startDate, $endDate, $catLimit, "3"); // dance
        if(!$monthly)
            $this->emitChart($startDate, $endDate, $catLimit, "8"); // C/X
    
        ////if($this->session->isAuth("u"))
        ////    $this->emitBottom($startDate, $endDate, 90);
    
        UI::setFocus();
    }
    
    public function chartMonthly() {
        $station = Engine::param('station', 'KZSU');
    
        $chartAPI = Engine::api(IChart::class);
        $year = $_REQUEST["year"];
        $month = $_REQUEST["month"];
        $day = $_REQUEST["day"];
        $cyear = $_REQUEST["cyear"];
    
        $monthly = 1;
    
        if(!$cyear && !$month) {
            if(!$year) {
                // current year
                $today = getdate(time());
                $month = $today["mon"];
    
    ?>
      <!--P>These charts are based on actual airplay, and are what we
         report on. (For those of you outside the college radio biz, this is
         unusual.) <A HREF="http://www.cmjmusic.com/" TARGET="_blank">College
         Media Journal</A> and <A HREF="http://www.gavin.com/"
         TARGET="_blank">Gavin</A> include our weekly numbers in their reports.</P-->
    <?php 
                echo "  <TABLE WIDTH=\"100%\">\n";
    
                // Determine if we need to include the current month
                $weeks = $chartAPI->getChartDates(1);
                if($weeks && ($curWeek = $weeks->fetch())) {
                    list($y, $m, $d) = explode("-", $curWeek["week"]);
                    $chartEnd = $chartAPI->getMonthlyChartEnd($m, $y);
                    $skipCurMonth = strcmp($curWeek["week"], $chartEnd) != 0;
                }
    
                $year = 0;
                $weeks = $chartAPI->getChartMonths();
                while($weeks && ($week = $weeks->fetch())) {
                    if($skipCurMonth) {
                        $skipCurMonth = 0;
                        continue;
                    }
    
                    $genYearChart = 0;
                    list($y, $m, $d) = explode("-", $week["week"]);
    
                    if($year != $y) {
                        if($year)
                            echo "      </UL>\n    </TD></TR>\n";
                        echo "    <TR><TH CLASS=\"header\" ALIGN=LEFT>Amalgamated charts $y</TH></TR>\n    <TR><TD>\n      <UL>\n";
    
                        if($y >= 2003 &&  // first year we have charts for full year
                                $m == 12)
                            $genYearChart = 1;
                        $year = $y;
                    }
    
                    if($genYearChart)
                        echo "        <LI><A HREF=\"?action=viewChart&amp;subaction=amalgamated&amp;cyear=$y&amp;session=".$this->session->getSessionID()."\">Top 100 for the year $y</A>\n      </UL><UL>\n";
    
                     echo "        <LI><A HREF=\"?action=viewChart&amp;subaction=amalgamated&amp;year=$y&amp;month=$m&amp;session=".$this->session->getSessionID()."\">".date("F Y", mktime(0,0,0,$m,1,$y))."</A>\n";
                }
    
                if($year)
                    echo "      </UL>\n    </TD></TR>\n";
                $urls = Engine::param('urls');
                if(array_key_exists('old_charts', $urls))
                    echo "    <TR><TD><A HREF=\"".$urls['old_charts']."\">Old airplay charts</A> are available here.</TD></TR>\n";
                echo "  </TABLE>\n";
            }
            UI::setFocus();
            return;
        }
    
        if($cyear) {
            $startDate = $chartAPI->getMonthlyChartStart(1, $cyear);
            $endDate = $chartAPI->getMonthlyChartEnd(12, $cyear);
            echo "<P CLASS=\"header\">$station Top 100 for the year $cyear</P>\n";
            $monthly = 0;
        } else if($monthly) {
            $startDate = $chartAPI->getMonthlyChartStart($month, $year);
            $endDate = $chartAPI->getMonthlyChartEnd($month, $year);
            $displayDate = date("F Y", mktime(0,0,0,$month,1,$year));
            echo "<P CLASS=\"header\">$station chart for month of $displayDate</P>\n";
        } else {
            $startDate = "";
            $endDate = "$year-$month-$day";
            $displayDate = date("j F Y", mktime(0,0,0,$month,$day,$year));
            echo "<P CLASS=\"header\">$station chart for the week ending $displayDate</P>\n";
        }
    
    // weekly = hip hop, reggae/world, jazz, heavy shit, dance, classical/exp
    //     no limits
    // monthly = hip hop, reggae/world, jazz, blues, country, heavy shit, dance
    //     limit to 60 main, 10/cat
    
        if($cyear) {
            $mainLimit = "100";
            $catLimit = "30";
        } else {
            $mainLimit = $monthly?($this->session->isAuth("f")?"100":"60"):"";
            $catLimit = $monthly?($this->session->isAuth("f")?"40":"10"):"";
        }
    
        // JHM FIXME TBD - hardcoded for now
        $this->emitChart($startDate, $endDate, $mainLimit);
        $this->emitChart($startDate, $endDate, $catLimit, "5"); // hip-hop
        $this->emitChart($startDate, $endDate, $catLimit, "7"); // reggae/world
        if($this->session->isAuth("r"))
            $this->emitChart($startDate, $endDate, $catLimit, "9"); // reggae
        $this->emitChart($startDate, $endDate, $catLimit, "6"); // jazz
        if($monthly || $cyear) {
            $this->emitChart($startDate, $endDate, $catLimit, "1"); // blues
            $this->emitChart($startDate, $endDate, $catLimit, "2"); // country
        }
        $this->emitChart($startDate, $endDate, $catLimit, "4"); // heavy shit
        $this->emitChart($startDate, $endDate, $catLimit, "3"); // dance
        if(!$monthly)
            $this->emitChart($startDate, $endDate, $catLimit, "8"); // C/X
    
        ////if($this->session->isAuth("u"))
        ////    $this->emitBottom($startDate, $endDate, 90);
    
        UI::setFocus();
    }
    
    private function emitChart($startDate, $endDate, $limit="", $category="") {
        $chartAPI = Engine::api(IChart::class);
        $chartAPI->getChart($chart, $startDate, $endDate, $limit, $category);
        Engine::api(ILibrary::class)->markAlbumsReviewed($chart);
        if(sizeof($chart)) {
            echo "<TABLE WIDTH=\"100%\" BORDER=0 CELLPADDING=2 CELLSPACING=0>\n";
            echo "  <TR><TH CLASS=\"subhead\" ALIGN=LEFT";
            if($category) {
                echo " COLSPAN=2>";
                // Get the chart categories
                $cats = $chartAPI->getCategories();
    
                echo strtoupper($cats[$category-1]["name"]);
            } else {
                echo ">";
                echo "MAIN";
                echo "</TH><TH ALIGN=RIGHT>#&nbsp;ARTIST&nbsp;<I>ALBUM</I>&nbsp;(LABEL)";
            }
    
            echo "</TH></TR>\n  <TR><TD COLSPAN=2>\n    <OL>\n";
            for($i=0; $i < sizeof($chart); $i++) {
    
                // Fixup the artist, album, and label names
                $artist = $chart[$i]["artist"];
                $label = str_replace(" Records", "", $chart[$i]["LABEL"]);
                $label = str_replace(" Recordings", "", $label);
    
                // Setup medium
                $album = $chart[$i]["album"];
                switch($chart[$i]["medium"]) {
                case "S":
                    $medium = " 7\"";
                    break;
                case "T":
                    $medium = " 10\"";
                    break;
                case "V":
                    $medium = " 12\"";
                    break;
                default:
                    $medium = "";
                    break;
                }
    
                echo "      <LI>";
                // Artist
                echo UI::HTMLify(strtoupper($artist), 20) . " <I>";
                // Album & Label
                echo "<A CLASS=\"calNav\" HREF=\"".
                             "?s=byAlbumKey&amp;n=". UI::URLify($chart[$i]["tag"]).
                             "&amp;q=10".
                             "&amp;action=search&amp;session=".$this->session->getSessionID().
                             "\">". UI::HTMLify($album, 20) . "</A></I>$medium (" .
                     UI::HTMLify($label, 20) . ")\n";
            }
            echo "  </OL></TD></TR>\n</TABLE><BR>\n";
        }
    }
    
    private function emitBottom($startDate, $endDate, $limit="", $category="") {
        $chartAPI = Engine::api(IChart::class);
        $chartAPI->getBottom($chart, $startDate, $endDate, $limit, $category);
        Engine::api(ILibrary::class)->markAlbumsReviewed($chart);
        if(sizeof($chart)) {
            echo "<TABLE WIDTH=\"100%\" BORDER=0 CELLPADDING=2 CELLSPACING=0>\n";
            echo "  <TR><TH ALIGN=LEFT";
            if($category) {
                echo " COLSPAN=2>";
                // Get the chart categories
                $cats = $chartAPI->getCategories();
    
                echo "LEAST PLAYED&nbsp;";
                echo strtoupper($cats[$category-1]["name"]);
            } else {
                echo ">";
                echo "LEAST PLAYED";
                echo "</TH><TH ALIGN=RIGHT>spins.&nbsp;ARTIST&nbsp;<I>ALBUM</I>&nbsp;(LABEL)";
            }
    
            echo "</TH></TR>\n  <TR><TD COLSPAN=2>\n    <OL>\n";
            for($i=0; $i < sizeof($chart); $i++) {
    
                // Fixup the artist, album, and label names
                $artist = $chart[$i]["artist"];
                $label = str_replace(" Records", "", $chart[$i]["LABEL"]);
                $label = str_replace(" Recordings", "", $label);
    
                // Setup medium
                $album = $chart[$i]["album"];
                switch($chart[$i]["medium"]) {
                case "S":
                    $medium = " 7\"";
                    break;
                case "T":
                    $medium = " 10\"";
                    break;
                case "V":
                    $medium = " 12\"";
                    break;
                default:
                    $medium = "";
                    break;
                }
    
                echo "      <LI VALUE=\"". $chart[$i]["PLAYS"] ."\">";
                // Artist
                echo UI::HTMLify(strtoupper($artist), 20) . " <I>";
                // Album & Label
                echo "<A CLASS=\"calNav\" HREF=\"".
                             "?s=byAlbumKey&amp;n=". UI::URLify($chart[$i]["tag"]).
                             "&amp;q=10".
                             "&amp;action=search&amp;session=".$this->session->getSessionID().
                             "\">". UI::HTMLify($album, 20) . "</A></I>$medium (" .
                     UI::HTMLify($label, 20) . ")<BR>\n";
            }
            echo "  </OL></TD></TR>\n</TABLE><BR>\n";
        }
    }
    
    private function emitChart2($startDate, $endDate, $limit="", $category="") {
        $chartAPI = Engine::api(IChart::class);
        $chartAPI->getChart($chart, $startDate, $endDate, $limit, $category);
        if(sizeof($chart)) {
            echo "          <TR CLASS=\"secdiv\"><TH ALIGN=LEFT CLASS=\"sub\"";
            if($category) {
                echo " COLSPAN=2>";
                // Get the chart categories
                $cats = $chartAPI->getCategories();
    
                echo strtoupper($cats[$category-1]["name"]);
            } else {
                echo ">";
                if($limit) echo "TOP $limit";
                echo "</TH><TH ALIGN=RIGHT CLASS=\"sub\">#&nbsp;ARTIST&nbsp;<I>ALBUM</I>&nbsp;(LABEL)";
            }
    
            echo "</TH>\n            <TR><TD COLSPAN=2 CLASS=\"sub\">\n";
            for($i=0; $i < sizeof($chart); $i++) {
                // Fixup the artist, album, and label names
                $artist = $chart[$i]["artist"];
                $label = str_replace(" Records", "", $chart[$i]["LABEL"]);
                $label = str_replace(" Recordings", "", $label);
    
                // Setup medium
                $album = $chart[$i]["album"];
                switch($chart[$i]["medium"]) {
                case "S":
                    $medium = " 7\"";
                    break;
                case "T":
                    $medium = " 10\"";
                    break;
                case "V":
                    $medium = " 12\"";
                    break;
                default:
                    $medium = "";
                    break;
                }
    
                echo "              ".(string)($i + 1).". ";
                // Artist
                echo UI::HTMLify(strtoupper($artist), 20) . " <I>";
                // Album & Label
                echo "<A CLASS=\"calNav\" HREF=\"".
                             "?s=byAlbumKey&amp;n=". UI::URLify($chart[$i]["tag"]).
                             "&amp;q=10".
                             "&amp;action=search&amp;session=".$this->session->getSessionID().
                             "\">". UI::HTMLify($album, 20) . "</A></I>$medium (" .
                     UI::HTMLify($label, 20) . ")<BR>\n";
            }
            echo "          </TD></TR>\n";
        }
    }
    
    public function emitChartCMJ($startDate, $endDate, $limit="", $category="") {
        $chartAPI = Engine::api(IChart::class);
        $chartAPI->getChart($chart, $startDate, $endDate, $limit, $category);
        if(sizeof($chart)) {
            if($category) {
                // Get the chart categories
                $cats = $chartAPI->getCategories();
    
                echo "<Playlist charttype_id:" . self::$charttype[$category] . ">\n";
                echo "## " . $cats[$category-1]["name"] . " playlist\n\n";
            } else {
                echo "<Playlist charttype_id:1>\n";
                echo "## Top 200 playlist\n\n";
            }
    
            for($i=0; $i < sizeof($chart); $i++) {
                // Fixup the artist, album, and label names
                $artist = preg_replace("/, [Tt]he$/", "", $chart[$i]["artist"]);
                $album = preg_replace("/, [Tt]he$/", "", $chart[$i]["album"]);
                $label = str_replace(" Records", "", $chart[$i]["LABEL"]);
                $label = str_replace(" Recordings", "", $label);
    
                // Quote singles in the hip hop chart only
                if($category == "5") {
                    switch($chart[$i]["medium"]) {
                    case "S":
                        $medium = " 7\"";
                        break;
                    case "T":
                        $medium = " 10\"";
                        break;
                    case "V":
                        $medium = " 12\"";
                        break;
                    default:
                        $medium = "";
                        break;
                    }
                }
    
                if($artist == "COLL") {
                    $artist = "VARIOUS ARTISTS";
                }
    
                // Rank
                echo ($i+1) . "\t";
    
                // Setup medium
                // Artist
                echo UI::HTMLify(strtoupper($artist), 20) . "\t";
                // Album
                echo ($medium?"\"":"") . UI::HTMLify($album, 20) . ($medium?"\"\t":"\t");
                // Label
                echo UI::HTMLify($label, 20) . "\t";
                // Spins
                echo $chart[$i]["PLAYS"] . "\n";
            }
    
            // Fill out chart to required number of entries
            for($i=sizeof($chart); $i<$limit; $i++) {
                $dummy = md5(uniqid(rand()));
                echo substr($dummy, 0, 5) . "\t" .
                     substr($dummy, 5, 5) . "\t" .
                     substr($dummy, 10, 0) . "\t0\n";
            }
    
            echo "\n</Playlist>\n\n";
        }
    }
    
    public function chartEMail() {
        $chartAPI = Engine::api(IChart::class);
        if($_REQUEST["seq"] == "update") {
            $success = true;
            for($i=1; $success && $i<=16; $i++) {
                $email = $_POST["email".$i];
                $success &= $chartAPI->updateChartEMail($i, $email);
            }
        }
    ?>
      <FORM ACTION="" METHOD=POST>
        <TABLE CELLPADDING=2 CELLSPACING=0 BORDER=0>
          <TR><TH ALIGN=RIGHT>&nbsp;</TH><TH ALIGN=LEFT>E-Mail Addresses</TH></TR>
    <?php 
        $addresses = $chartAPI->getChartEMail();
        while($addresses && ($row = $addresses->fetch())) {
            $i = $row["id"];
            echo "      <TR><TD ALIGN=RIGHT>".htmlentities(stripslashes($row["chart"])).":</TD>\n";
            echo "          <TD ALIGN=LEFT><INPUT TYPE=TEXT NAME=email$i VALUE=\"".htmlentities(stripslashes($row["address"]))."\" CLASS=input SIZE=50 MAXLENGTH=255></TD></TR>\n";
        }
    ?>
          <TR><TD>&nbsp;</TD>
              <TD COLSPAN=3 ALIGN=LEFT><INPUT TYPE=SUBMIT VALUE=" Update Addresses "></TD></TR>
    <?php 
        if($_REQUEST["seq"] == "update") {
            if($success)
                echo "      <TR><TD>&nbsp;</TD><TD CLASS=\"header\" ALIGN=LEFT COLSPAN=2>E-Mail addresses updated.</TD></TR>\n";
            else
                echo "      <TR><TD>&nbsp;</TD><TD CLASS=\"header\" ALIGN=LEFT COLSPAN=2 BGCOLOR=\"#cc0000\">Updated failed.</TD></TR>\n";
        }
    ?>
        </TABLE>
        <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
        <INPUT TYPE=HIDDEN NAME=action VALUE="viewChart">
        <INPUT TYPE=HIDDEN NAME=subaction VALUE="chartemail">
        <INPUT TYPE=HIDDEN NAME=seq VALUE="update">
      </FORM>
    <?php 
        UI::setFocus("email1");
    }
    
    public function chartCMJ() {
        $year = $_REQUEST["year"];
        $month = $_REQUEST["month"];
        $day = $_REQUEST["day"];
    
        $startDate = "";
        $endDate = "$year-$month-$day";
        $displayDate = date("j F Y", mktime(0,0,0,$month,$day,$year));
        echo "<TABLE WIDTH=\"100%\"><TR><TH ALIGN=LEFT>CMJ upload chart for the week ending $displayDate</TH><!--TH ALIGN=RIGHT><A HREF=\"?action=viewChart&amp;subaction=weekly&amp;year=$year&amp;month=$month&amp;day=$day&amp;session=".$this->session->getSessionID()."\" CLASS=\"nav\">[Back to Weekly]</A></TH--></TR></TABLE><BR>\n";
        ////echo "<P CLASS=\"header\">CMJ upload chart for the week ending $displayDate</P>\n";
    
    
    // weekly = hip hop, reggae/world, jazz, heavy shit, dance, classical/exp
    //     no limits
    // monthly = hip hop, reggae/world, jazz, blues, country, heavy shit, dance
    //     limit to 60 main, 10/cat
    
    ?>
    <FORM NAME="textform">
      <TEXTAREA NAME="textfield" cols="72" rows="10">
    <?php 
        $mainLimit = "30";
        $catLimit = "10";
    
        // JHM FIXME TBD - hardcoded for now
        $this->emitChartCMJ($startDate, $endDate, $mainLimit);
        $this->emitChartCMJ($startDate, $endDate, $catLimit, "5"); // hip-hop
        $this->emitChartCMJ($startDate, $endDate, $catLimit, "7"); // reggae/world
        $this->emitChartCMJ($startDate, $endDate, $catLimit, "6"); // jazz
        $this->emitChartCMJ($startDate, $endDate, $catLimit, "4"); // heavy shit
        $this->emitChartCMJ($startDate, $endDate, $catLimit, "3"); // dance
        ////$this->emitChartCMJ($startDate, $endDate, $catLimit, "8"); // C/X
    
    ?>
    </TEXTAREA><BR><BR>
    <?php ob_start(); ?>
    <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript">
    <!--
    function highlightmetasearch() {
      document.textform.textfield.select(); document.textform.textfield.focus();
    }
    function copymetasearch() {
      highlightmetasearch();
      textRange = document.textform.textfield.createTextRange();
      textRange.execCommand("RemoveFormat");
      textRange.execCommand("Copy");
      alert("The chart has been copied to your clipboard.\n\nUse CTRL V to paste the chart into the upload form on the CMJ website.");
      }
    
    if ((navigator.appName == "Microsoft Internet Explorer") && (parseInt(navigator.appVersion) >= 4)) {
        document.write('<INPUT type="button" value="  Copy to Clipboard  " onClick="copymetasearch();">');
        document.write('<P><B>Instructions:</B><OL><LI>Open the CMJ website import chart page in another browser window and clear the textbox;<LI>Press Copy to Clipboard in this window;<LI>Use CTRL V to paste the chart into the upload form on the CMJ website.</OL></P>\n');
      } else {
        highlightmetasearch();
        document.write('<P><B>Instructions:</B><OL><LI>Open the CMJ website import chart page in another browser window and clear the textbox;<LI>Press CTRL C in this window to copy chart to clipboard;<LI>Use CTRL V to paste the chart into the upload form on the CMJ website.</OL></P>\n');
      }
    <?php
        $script = ob_get_contents();
        ob_end_clean();
        echo \JSMin::minify($script);
    ?>
    // -->
    </SCRIPT>
    </FORM>
    <?php 
        UI::setFocus("textfield");
    }
    
    public function emitSubscribe() {
        $station = Engine::param('station');
    ?>
    <P>
    The <?php echo $station; ?> Music Department maintains two e-mail lists:</P>
    <UL>
    
    <LI><B>Weekly Charts e-mail list</B> - Each week, the number of plays of
    the releases in rotation are tallied up, and a chart is made showing a
    ranked list of the most played stuff on our airwaves for that week.
    This is what's reported to the trades (CMJ, Gavin, etc).  If you'd like
    to receive this chart each week by e-mail, please visit the Weekly Charts page.
    
    <LI><B>Monthly Charts e-mail list</B> - Each month, the number of plays
    of the releases in rotation for the previous month are tallied up, and
    a chart is made showing a ranked list of the most played stuff on our
    airwaves for that month. If you'd like to receive this chart each week
    by e-mail, please visit the Monthly Charts page.
    </UL>
    
    <P>These mailing lists are one-way lists, which means you'll receive
    e-mail only from <?php echo $station; ?>.  Any reply by a subscriber will return to <?php echo $station; ?>
    but will not be sent to everyone else on the list.  Also, each
    subscriber's e-mail address is suppressed from the header so other
    subscribers can't collect the addresses to create a spamming list.</P>
    
    <P>Any attempts to use these lists or their contents for unsolicited
    e-mail will earn our everlasting contempt.  Subscriptions from
    fraudulent addresses and spam companies will be summarily deleted.</P>
    <?php 
        $email = Engine::param('email')['md'];
        if($email)
            echo "    <P>Any questions can be sent to <A HREF=\"mailto:$email\">$email</A>.</P>\n";
        UI::setFocus();
    }
}