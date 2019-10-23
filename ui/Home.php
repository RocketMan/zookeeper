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

use ZK\Engine\Engine;
use ZK\Engine\IChart;
use ZK\Engine\ILibrary;
use ZK\Engine\IPlaylist;

use ZK\UI\UICommon as UI;

class Home extends MenuItem {
    public function processLocal($action, $subaction) {
        echo "    <H2>". Engine::param('station'). " Music :: " . Engine::param('application') . "</H2>\n";
        if($this->session->isAuth("u"))
            echo "    <P>Remember to <B>logout</B> when you have finished.</P>\n";
        $this->emitWhatsOnNow();
        $this->emitTopPlays();
        echo "    <TABLE WIDTH=\"100%\">\n";
        echo "      <TR><TH ALIGN=LEFT CLASS=\"subhead\">For complete album charting, see our\n";
        echo "          <A CLASS=\"subhead\" HREF=\"?session=".$this->session->getSessionID()."&amp;action=viewChart\"><B>Airplay Charts</B></A>.\n      </TH></TR>\n    </TABLE>\n";
        UI::setFocus();
    }

    private function emitTopPlays($numweeks=1, $limit=10) {
       // Determine last chart date
       $weeks = Engine::api(IChart::class)->getChartDates(1);
       if($weeks && ($lastWeek = $weeks->fetch()))
          list($y,$m,$d) = explode("-", $lastWeek["week"]);
    
       if(! isset($y) || !$y)
          return;    // No charts!  bail.
    
       if(!$numWeeks || $numWeeks == 1)
          Engine::api(IChart::class)->getChart($topPlays, "", $lastWeek["week"], $limit);
       else {
          // Determine start chart date that will yield $numweeks worth of charts
          $startDate = date("Y-m-d", mktime(0,0,0,
                                          $m,
                                          $d-(($numweeks-1)*7),
                                          $y));
    
          Engine::api(IChart::class)->getChart($topPlays, $startDate, "", $limit);
       }
    
       Engine::api(ILibrary::class)->markAlbumsReviewed($topPlays);
       if(sizeof($topPlays)) {
          $nn4 = (substr($_SERVER["HTTP_USER_AGENT"], 0, 10) == "Mozilla/4.");
          if($nn4)
              echo "<TABLE BORDER=0 CELLPADDING=2 CELLSPACING=0 WIDTH=\"100%\">\n";
          else
              echo "<TABLE WIDTH=\"100%\">\n";
          echo "  <TR><TH ALIGN=LEFT CLASS=\"subhead\">";
          $formatEndDate = date("l, j F Y", mktime(0,0,0,$m,$d,$y));
          echo Engine::param('station') . "'s Top $limit Albums\n" .
               "    <BR><FONT CLASS=\"subhead2\">for the ";
          echo ($numweeks == 1)?"week":"$numweeks week period";
          echo " ending $formatEndDate</FONT></TH><TD ALIGN=RIGHT STYLE=\"vertical-align: bottom;\">Music Director: <A HREF=\"mailto:" . Engine::param('email')['md'] . "\">" . Engine::param('md_name') . "</A></TD></TR>\n";
          echo "</TABLE>\n<TABLE>\n";
          echo "  <TR><TH></TH><TH ALIGN=LEFT>Artist</TH><TH></TH><TH ALIGN=LEFT>Album</TH><TH ALIGN=LEFT>Label</TH></TR>\n";
          for($i=0; $i < sizeof($topPlays); $i++) {
             echo "  <TR><TD ALIGN=RIGHT>".(string)($i + 1).".</TD><TD>";
    
             // Setup artist correctly for collections
             $artist = preg_match("/^COLL$/i", $topPlays[$i]["artist"])?"Various Artists":$topPlays[$i]["artist"];
    
             echo UI::HTMLify($artist, 20) . "</TD><TD>";
             if($topPlays[$i]["REVIEWED"])
                 echo "<A CLASS=\"albumReview\" HREF=\"".
                      "?s=byAlbumKey&amp;n=". UI::URLify($topPlays[$i]["tag"]).
                      "&amp;action=search&amp;session=".$this->session->getSessionID().
                      "\"><IMG SRC=\"img/blank.gif\" WIDTH=12 HEIGHT=11 ALT=\"[i]\"></A></TD><TD>";
             else
                echo "</TD><TD>";
             // Album
             echo "<A CLASS=\"nav\" HREF=\"".
                             "?s=byAlbumKey&amp;n=". UI::URLify($topPlays[$i]["tag"]).
                             "&amp;action=search&amp;session=".$this->session->getSessionID().
                             "\">";
             echo UI::HTMLify($topPlays[$i]["album"], 20) . "</A></TD><TD>" .
                  UI::HTMLify($topPlays[$i]["label"], 20) . "</TD></TR>\n";
          }
          echo "</TABLE>\n";
       }
    }
    
    private function emitWhatsOnNow() {
        $record = Engine::api(IPlaylist::class)->getWhatsOnNow();
        echo "<TABLE WIDTH=\"100%\" BORDER=0 CELLPADDING=2 CELLSPACING=0 STYLE=\"border-style: solid; border-width: 1px 0px 1px 0px; border-color: #cccccc\">\n  <TR><TH ALIGN=LEFT COLSPAN=3 CLASS=\"subhead\">";
        echo "On ". Engine::param('station') . " now:</TH></TR>\n  ";
        if($record && ($row = $record->fetch())) {
            echo "<TR><TH ALIGN=LEFT COLSPAN=3><A HREF=\"".
                 "?action=viewDJ&amp;seq=selUser&amp;viewuser=".$row["airid"]."&amp;session=".$this->session->getSessionID().
                 "\" CLASS=\"calNav\">" . htmlentities($row["airname"]) . "</A>&nbsp;&nbsp;&nbsp;";
            echo "<A HREF=\"".
                 "?action=viewDate&amp;seq=selList&amp;playlist=".$row[0].
                 "&amp;session=".$this->session->getSessionID()."\" CLASS=\"nav\">" . htmlentities($row["description"]);
            echo "</A></TH></TR>\n  ";
            echo "<TR><TH ALIGN=RIGHT VALIGN=BOTTOM CLASS=\"sub\">" . date("l, j M") . "&nbsp;&nbsp;</TH>\n      <TH ALIGN=LEFT VALIGN=BOTTOM CLASS=\"sub\">" . Playlists::timeToAMPM($row["showtime"]) . " " . date("T") . "</TH>\n";
            $requestLine = Engine::param('contact')['request'];
            if($requestLine)
                echo "      <TD ALIGN=RIGHT VALIGN=BOTTOM ROWSPAN=2>Request Line:&nbsp;&nbsp;$requestLine&nbsp;&nbsp;&nbsp;".Engine::param('station_freq')."</TD></TR>\n";
            else
                echo "      <TD></TD></TR>\n";
            echo "  <TR>" . Playlists::timeToZulu($row["showtime"]). "</TR>\n";
        } else {
            echo "<TR><TH ALIGN=LEFT COLSPAN=3>[No playlist available]</TH></TR>\n  ";
            echo "<TR><TH COLSPAN=2>&nbsp;</TH>\n";
            $requestLine = Engine::param('contact')['request'];
            if($requestLine)
                echo "    <TD ALIGN=RIGHT VALIGN=BOTTOM>Request Line:&nbsp;&nbsp;$requestLine&nbsp;&nbsp;&nbsp;".Engine::param('station_freq')."</TD></TR>\n";
            else
                echo "    <TD></TD></TR>\n";
        }
        echo "</TABLE><BR>\n";
    }
}
