<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2025 Jim Mason <jmason@ibinx.com>
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
    ];

    public function getSubactions($action) { return self::$subactions; }

    public function processLocal($action, $subaction) {
        $extra = "<SPAN CLASS='sub'><B>Chart Feed:</B></SPAN> <A TYPE='application/rss+xml' HREF='zkrss.php?feed=charts'><IMG SRC='img/rss.png' ALT='rss'></A><BR><IMG SRC='img/blank.gif' WIDTH=1 HEIGHT=2 BORDER=0 ALT=''>";
        return $this->dispatchSubaction($action, $subaction, $extra);
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
            $dateSpec = UI::getClientLocale() == 'en_US' ? 'l, F j, Y' : 'l, j F Y';
            $formatDate = date($dateSpec, mktime(0,0,0,$m,$d,$y));
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
        $this->title = "Airplay Top 30";
    }
    
    public function emitChartYearNav($currentYear, $header=0) {
?>
  <div class="chart-year-pick">
  <form action="?" method="POST" autocomplete="off">
  <input type="hidden" name="action" value="viewChart">
  <input type="hidden" name="subaction" value="weekly">
  Weekly charts for
  <select name="year" style='display: none'>
<?php
        $oldestYear = null;
        $years = Engine::api(IChart::class)->getChartYears();
        while($years && ($year = $years->fetch())) {
            echo "    <option value=\"{$year[0]}\"";
            if($year[0] == $currentYear)
                echo " selected";
            echo ">{$year[0]}</option>\n";
            $oldestYear = $year[0];
        }
        ?>
  </select>
  </form>
  </div>
  <script><!--
  $().ready(function() {
      $("div.chart-year-pick select").selectmenu({width: 'auto'})
          .on('change selectmenuchange', function() {
              this.form.submit();
          })
          .selectmenu('menuWidget').css('max-height', '300px');
      $("div.content > div").css('display', 'block');
      $("div.chart-year-pick select").selectmenu('widget').trigger('focus');
  });
  // -->
  </script>
<?php
        return $oldestYear == $currentYear;
    }
    
    public function chartWeekly() {
        $station = Engine::param('station', 'KZSU');
    
        $chartAPI = Engine::api(IChart::class);
        $year = $_REQUEST["year"] ?? 0;
        $month = $_REQUEST["month"] ?? 0;
        $day = $_REQUEST["day"] ?? 0;

        $dateSpec = UI::getClientLocale() == 'en_US' ? 'F j, Y' : 'j F Y';

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
    
            echo "  <div style='display: none'>\n";
            $isOldestYear = $this->emitChartYearNav($year, 1);
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
                echo "        <LI><A HREF=\"?action=viewChart&amp;subaction=weekly&amp;year=$y&amp;month=$m&amp;day=$d\">Week ending ".date($dateSpec, mktime(0,0,0,$m,$d,$y))."</A>\n";
            }
            echo "      </UL>\n    </TD></TR>\n";
            echo "  </TABLE>\n";

            $urls = Engine::param('urls');
            if($isOldestYear && array_key_exists('old_charts', $urls))
                echo "  <P><A HREF=\"".$urls['old_charts']."\">Older airplay charts</A> are available here.</P>\n";
            echo "  </div>\n";

            $this->title = "Weekly charts for $year";
            return;
        }
    
        $startDate = "";
        $endDate = "$year-$month-$day";
        $displayDate = date($dateSpec, mktime(0,0,0,$month,$day,$year));
        echo "<P CLASS=\"header\">$station chart for the week ending $displayDate</P>\n";

        $this->title = "Chart for $displayDate";
    
    // weekly = hip hop, reggae/world, jazz, heavy shit, dance, classical/exp
    //     no limits
    // monthly = hip hop, reggae/world, jazz, blues, country, heavy shit, dance
    //     limit to 60 main, 10/cat
    
        $mainLimit = $catLimit = "";
    
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
        $this->emitChart($startDate, $endDate, $catLimit, "8"); // C/X
    
        UI::setFocus();
    }
    
    public function chartMonthly() {
        $station = Engine::param('station', 'KZSU');
    
        $chartAPI = Engine::api(IChart::class);
        $year = $_REQUEST["year"] ?? 0;
        $month = $_REQUEST["month"] ?? 0;
        $day = $_REQUEST["day"] ?? 0;
        $cyear = $_REQUEST["cyear"] ?? 0;
        $dnum = $_REQUEST["dnum"] ?? 0;

        $config = Engine::param('chart');
        $earliestYear = array_key_exists('earliest_chart_year', $config)?
            (int)$config['earliest_chart_year']:2003;
    
        $monthly = 1;
    
        if(!$dnum && !$cyear && !$month) {
            if(!$year) {
                // current year
                $today = getdate(time());
                $month = $today["mon"];
    
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
    
                    $genDecChart = $genYearChart = 0;
                    list($y, $m, $d) = explode("-", $week["week"]);
    
                    if($year != $y) {
                        if($year)
                            echo "      </UL>\n    </TD></TR>\n";
                        if($m == 12 && $y % 10 == 9) {
                            $genDecChart = 1;
                            $dnum = intdiv($y, 10);
                            $dstart = $y - 9;
                            $name = ($dstart % 100)?"$dstart's":"noughties";
                            if($y - $earliestYear < 10)
                                $name .= " (based on available data)";
                            echo "    <TR><TH CLASS=\"header\" ALIGN=LEFT>Amalgamated charts $dstart - $y</TH></TR>\n    <TR><TD>\n      <UL>\n";
                            echo "        <LI><A HREF=\"?action=viewChart&amp;subaction=amalgamated&amp;dnum=$dnum\">Top 100 for the decennium that was the $name</A>\n      </UL><UL>\n";
                        }
                        echo "    <TR><TH CLASS=\"header\" ALIGN=LEFT>Amalgamated charts $y</TH></TR>\n    <TR><TD>\n      <UL>\n";
    
                        if($y >= $earliestYear &&  // first year we have charts for full year
                                $m == 12)
                            $genYearChart = 1;
                        $year = $y;
                    }
    
                    if($genYearChart)
                        echo "        <LI><A HREF=\"?action=viewChart&amp;subaction=amalgamated&amp;cyear=$y\">Top 100 for the year $y</A>\n      </UL><UL>\n";
    
                     echo "        <LI><A HREF=\"?action=viewChart&amp;subaction=amalgamated&amp;year=$y&amp;month=$m\">".date("F Y", mktime(0,0,0,$m,1,$y))."</A>\n";
                }
    
                if($year)
                    echo "      </UL>\n    </TD></TR>\n";
                $urls = Engine::param('urls');
                if(array_key_exists('old_charts', $urls))
                    echo "    <TR><TD><A HREF=\"".$urls['old_charts']."\">Old airplay charts</A> are available here.</TD></TR>\n";
                echo "  </TABLE>\n";
            }

            $this->title = "Amalgamated charts";
            return;
        }

        if($dnum) {
            $dstart = (int)$dnum * 10;
            $dend = $dstart + 9;
            $startDate = $chartAPI->getMonthlyChartStart(1, $dstart);
            $endDate = $chartAPI->getMonthlyChartEnd(12, $dend);
            $name = "$dstart - $dend";
            if($dend - $earliestYear < 10)
                $name .= " (based on available data)";
            echo "<P CLASS=\"header\">$station Top 100 for the decennium $name</P>\n";
            $this->title = "Chart for $name";
            $monthly = 0;
        } else if($cyear) {
            $startDate = $chartAPI->getMonthlyChartStart(1, $cyear);
            $endDate = $chartAPI->getMonthlyChartEnd(12, $cyear);
            echo "<P CLASS=\"header\">$station Top 100 for the year $cyear</P>\n";
            $this->title = "Chart for $cyear";
            $monthly = 0;
        } else if($monthly) {
            $startDate = $chartAPI->getMonthlyChartStart($month, $year);
            $endDate = $chartAPI->getMonthlyChartEnd($month, $year);
            $displayDate = date("F Y", mktime(0,0,0,$month,1,$year));
            echo "<P CLASS=\"header\">$station chart for month of $displayDate</P>\n";
            $this->title = "Chart for $displayDate";
        } else {
            $startDate = "";
            $endDate = "$year-$month-$day";
            $dateSpec = UI::getClientLocale() == 'en_US' ? 'F j, Y' : 'j F Y';
            $displayDate = date($dateSpec, mktime(0,0,0,$month,$day,$year));
            echo "<P CLASS=\"header\">$station chart for the week ending $displayDate</P>\n";
            $this->title = "Chart for $displayDate";
        }
    
    // weekly = hip hop, reggae/world, jazz, heavy shit, dance, classical/exp
    //     no limits
    // monthly = hip hop, reggae/world, jazz, blues, country, heavy shit, dance
    //     limit to 60 main, 10/cat
    
        if($dnum || $cyear) {
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
        if($monthly || $cyear || $dnum) {
            $this->emitChart($startDate, $endDate, $catLimit, "1"); // blues
            $this->emitChart($startDate, $endDate, $catLimit, "2"); // country
        }
        $this->emitChart($startDate, $endDate, $catLimit, "4"); // heavy shit
        $this->emitChart($startDate, $endDate, $catLimit, "3"); // dance
        if(!$monthly)
            $this->emitChart($startDate, $endDate, $catLimit, "8"); // C/X
    
        UI::setFocus();
    }
    
    private function emitChart($startDate, $endDate, $limit="", $category="") {
        $chart = [];
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
    
                echo mb_strtoupper($cats[$category-1]["name"]);
            } else {
                echo ">";
                echo "MAIN";
                echo "</TH><TH ALIGN=RIGHT>#&nbsp;ARTIST&nbsp;<I>ALBUM</I>&nbsp;(LABEL)";
            }
    
            echo "</TH></TR>\n  <TR><TD COLSPAN=2>\n    <OL>\n";
            for($i=0; $i < sizeof($chart); $i++) {
    
                // Fixup the artist, album, and label names
                $artist = $chart[$i]["artist"];
                $label = str_replace(" Records", "", $chart[$i]["label"]);
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
                echo UI::HTMLify(mb_strtoupper($artist), 20) . " <I>";
                // Album & Label
                echo "<A CLASS=\"calNav\" HREF=\"".
                             "?s=byAlbumKey&amp;n=". UI::URLify($chart[$i]["tag"]).
                             "&amp;action=search\">". UI::HTMLify($album, 20) . "</A></I>$medium (" .
                     UI::HTMLify($label, 20) . ")\n";
            }
            echo "  </OL></TD></TR>\n</TABLE><BR>\n";
        }
    }
    
    private function emitChart2($startDate, $endDate, $limit="", $category="") {
        $chart = [];
        $chartAPI = Engine::api(IChart::class);
        $chartAPI->getChart($chart, $startDate, $endDate, $limit, $category);
        if(sizeof($chart)) {
            echo "          <TR CLASS=\"secdiv\"><TH ALIGN=LEFT CLASS=\"sub\"";
            if($category) {
                echo " COLSPAN=2>";
                // Get the chart categories
                $cats = $chartAPI->getCategories();
    
                echo mb_strtoupper($cats[$category-1]["name"]);
            } else {
                echo ">";
                if($limit) echo "TOP $limit";
                echo "</TH><TH ALIGN=RIGHT CLASS=\"sub\">#&nbsp;ARTIST&nbsp;<I>ALBUM</I>&nbsp;(LABEL)";
            }
    
            echo "</TH>\n            <TR><TD COLSPAN=2 CLASS=\"sub\">\n";
            for($i=0; $i < sizeof($chart); $i++) {
                // Fixup the artist, album, and label names
                $artist = $chart[$i]["artist"];
                $label = str_replace(" Records", "", $chart[$i]["label"]);
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
                echo UI::HTMLify(mb_strtoupper($artist), 20) . " <I>";
                // Album & Label
                echo "<A CLASS=\"calNav\" HREF=\"".
                             "?s=byAlbumKey&amp;n=". UI::URLify($chart[$i]["tag"]).
                             "&amp;action=search\">". UI::HTMLify($album, 20) . "</A></I>$medium (" .
                     UI::HTMLify($label, 20) . ")<BR>\n";
            }
            echo "          </TD></TR>\n";
        }
    }
    
    public function chartEMail() {
        $chartAPI = Engine::api(IChart::class);
        if(($_REQUEST["seq"] ?? '') == "update") {
            $success = true;
            for($i=1; $success && $i<=16; $i++) {
                if(isset($_POST["email".$i])) {
                    $email = $_POST["email".$i];
                    $success &= $chartAPI->updateChartEMail($i, $email);
                }
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
        if(($_REQUEST["seq"] ?? '') == "update") {
            if($success)
                echo "      <TR><TD>&nbsp;</TD><TD CLASS=\"header\" ALIGN=LEFT COLSPAN=2>E-Mail addresses updated.</TD></TR>\n";
            else
                echo "      <TR><TD>&nbsp;</TD><TD CLASS=\"header\" ALIGN=LEFT COLSPAN=2 BGCOLOR=\"#cc0000\">Updated failed.</TD></TR>\n";
        }
    ?>
        </TABLE>
        <INPUT TYPE=HIDDEN NAME=action VALUE="viewChart">
        <INPUT TYPE=HIDDEN NAME=subaction VALUE="chartemail">
        <INPUT TYPE=HIDDEN NAME=seq VALUE="update">
      </FORM>
    <?php 
        UI::setFocus("email1");
    }
    
    public function emitSubscribe() {
        $chart = Engine::param('chart');
        $weeklyPage = array_key_exists('weekly_subscribe', $chart)?
            $chart['weekly_subscribe']:false;
        $monthlyPage = array_key_exists('monthly_subscribe', $chart)?
            $chart['monthly_subscribe']:false;
        $this->setTemplate("charts.subscribe.html");
        $this->addVar("weeklyPage", $weeklyPage);
        $this->addVar("monthlyPage", $monthlyPage);
    }
}
