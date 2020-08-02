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
        echo "<H1>". Engine::param('station'). " Music :: " . Engine::param('application') . "</H1>\n";
        $requestLine = Engine::param('contact')['request'];
        if ($requestLine)
            echo "<div class='home-hdr'><label>Request Line:</label> $requestLine</div>";

        $musicDirEmail = Engine::param('email')['md'];
        $musicDirName = Engine::param('md_name');
        echo "<div class='home-hdr'><label>Music Director:</label> <A HREF='mailto: $musicDirEmail'>$musicDirName</A></div>";

        $this->emitWhatsOnNow();
        $this->emitTopPlays();
        echo "<div style='border:0; position:absolute; bottom:0px' CLASS='subhead'>For complete album charting, see our ";
        echo "<A CLASS='subhead' HREF='?action=viewChart'><B>Airplay Charts</B></A></div>";
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
         echo "<TABLE WIDTH=\"100%\">\n";

          echo "<TR><TH ALIGN=LEFT CLASS=\"subhead\">";
          $formatEndDate = date("l, j F Y", mktime(0,0,0,$m,$d,$y));
          echo "Our Top $limit Albums\n" .
          "    <BR><FONT CLASS=\"subhead2\">for the ";
          echo ($numweeks == 1)?"week":"$numweeks week period";
          echo " ending $formatEndDate</FONT></TH><TD ALIGN=RIGHT STYLE=\"vertical-align: bottom;\"></TR></TABLE>";

          echo "<TABLE width='100%' class='top-albums'><TR style='border-bottom:1px solid gray'><TH></TH><TH ALIGN=LEFT COLSPAN=2>Artist</TH><TH ALIGN=LEFT>Album/Label</TH></TR>\n";
          for($i=0; $i < sizeof($topPlays); $i++) {
             $tagId = $topPlays[$i]["tag"];
             $artist = $topPlays[$i]["artist"];
             $haveReview = $topPlays[$i]["reviewed"];
             $album = UI::HTMLify($topPlays[$i]["album"], 20);
             $label = UI::HTMLify($topPlays[$i]["label"], 20);

             // Setup artist correctly for collections & swap names if from library
             if (preg_match("/^COLL$/i", $artist))
                 $artist = "Various Artists";
             else if ($tagId)
                 $artist = UI::swapNames($artist);

             $artist = UI::HTMLify($artist, 20);
             echo "<TR>";
             echo "<TD style='font-weight:bold; padding-right:0px' ALIGN=LEFT>".(string)($i + 1).".</TD>";
             echo "<TD>$artist</TD>";

             $reviewClass = $haveReview ? "albumReview" : "albumNoReview";

             echo "<td style='padding-right:4px'><div class='$reviewClass'></div></td>";
             // Album
             echo "<TD>" .
                  "<A CLASS='nav' HREF='?s=byAlbumKey&amp;n=" . UI::URLify($tagId).
                  "&amp;action=search'>".
                  "$album</A> / $label </TD>";
             echo "</TR>\n";
          }
          echo "</TABLE>\n";
       }
    }
    
    private function emitWhatsOnNow() {
        echo "<div class='subhead'>On Now:<div class='home-onnow'>\n";
        $record = Engine::api(IPlaylist::class)->getWhatsOnNow();
        if($record && ($row = $record->fetch())) {
            $airId = $row["airid"];
            $airName = htmlentities($row["airname"]);
            $description = htmlentities($row["description"]);
            $showDateTime = Playlists::makeShowDateAndTime($row);
            $hrefAirName =  "?action=viewDJ&amp;seq=selUser&amp;viewuser=$airId";
            $hrefPL = "?action=viewDate&amp;seq=selList&amp;playlist=$row[0]";
            echo "<A HREF='$hrefPL' CLASS='nav'>$description</A>&nbsp;with&nbsp;";
            echo "<A HREF='$hrefAirName' CLASS='calNav'>$airName</A>";
            echo "<div class='home-datetime'>$showDateTime</div>";
        } else {
            echo "<div class='home-datetime'>[No playlist available]</div>";
        }
        echo "</div></div>\n";
    }
}
