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

namespace ZK\Controllers;

use ZK\Engine\Engine;
use ZK\Engine\IChart;
use ZK\Engine\ILibrary;
use ZK\Engine\IReview;

use ZK\UI\Search;
use ZK\UI\UICommon as UI;

class RSS extends CommandTarget implements IController {
    private static $actions = [
        [ "", "emitError" ],
        [ "reviews", "recentReviews" ],
        [ "charts", "recentCharts" ],
        [ "adds", "recentAdds" ],
    ];

    private static function xmlentities($str) {
       return str_replace(['&', '"', "'", '<', '>', '`'],
           ['&amp;' , '&quot;', '&apos;' , '&lt;' , '&gt;', '&apos;'], $str);
    }
    
    private static function htmlnumericentities($str) {
       // for UTF-8, this is a no-op.  xmlentities has already taken
       // care of the xml entities.
       return $str;
       //return preg_replace_callback('/[^!-%\x27-;=?-~ ]/',
       //   function($m) { return "&#".ord($m[0]).";"; },
       //   $str);
    }

    public function processRequest($dispatcher) {
        $this->session = Engine::session();

        header("Content-type: text/xml");
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<rss version=\"2.0\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\">\n";
        
        $feeds = explode(',', $_REQUEST["feed"]);
        foreach($feeds as $feed)
            $this->processLocal($feed, null);

        echo "</rss>\n";
    }

    public function processLocal($action, $subaction) {
        $this->dispatchAction($action, self::$actions);
    }

    public function emitChartRSS($endDate, &$title, $limit="", $category="") {
        Engine::api(IChart::class)->getChart($chart, "", $endDate, $limit, $category);
        if(sizeof($chart)) {
            $title = Engine::param('station'). " ";
            if($category) {
                $cats = Engine::api(IChart::class)->getCategories();
                $title .= strtoupper($cats[$category-1]["name"]) . " CHART ";
            } else {
                if($limit) $title .= " TOP $limit ";
            }
    
            list($y, $m, $d) = explode("-", $endDate);
            $formatDate = date("l, j F Y", mktime(0,0,0,$m,$d,$y));
            $title .= "for the week ending $formatDate";
    
            $output = "&lt;p&gt;Rank. ARTIST &lt;I&gt;ALBUM&lt;/I&gt; (LABEL)&lt;/p&gt;\n";
            $output .= "&lt;p&gt;";
    
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
    
                $output .= (string)($i + 1).". ";
                // Artist
                $output .= self::htmlnumericentities(self::xmlentities(strtoupper($artist))) . " &lt;I&gt;";
                // Album & Label
                $output .= "&lt;A HREF=\"".UI::getBaseUrl().
                             "?s=byAlbumKey&amp;n=".$chart[$i]["tag"].
                             "&amp;q=10".
                             "&amp;action=search&amp;session=".$this->session->getSessionID().
                             "\"&gt;". self::htmlnumericentities(self::xmlentities($album)) . "&lt;/A&gt;&lt;/I&gt;$medium (" .
                     self::htmlnumericentities(self::xmlentities($label)) . ")&lt;BR&gt;\n";
            }
    
            $output .= "&lt;/p&gt;";
        }
        return $output;
    }
    
    public function recentCharts() {
       $station = Engine::param('station');
       $top = $_REQUEST["top"];
       $weeks = $_REQUEST["weeks"];

       if(!$top)
          $top = 30;
       if(!$weeks)
          $weeks = 10;
    
       $title = "$station Radio Airplay Charts";
    
       echo "<channel>\n<title>$title</title>\n";
       echo "<link>".UI::getBaseUrl()."?action=viewChart</link>\n";
       echo "<description>$station Radio Airplay Charts</description>\n";
       echo "<managingEditor>".Engine::param('email')['md']."</managingEditor>\n";
       echo "<language>en-us</language>\n";
    
       $weeks = Engine::api(IChart::class)->getChartDates($weeks);
       while($weeks && ($week = $weeks->fetch())) {
          $endDate = $week["week"];
          $output = $this->emitChartRSS($endDate, $name, $top);
          $link = UI::getBaseUrl()."?action=viewChart&amp;subaction=weekly&amp;year=".substr($endDate,0,4)."&amp;month=".substr($endDate,5,2)."&amp;day=".substr($endDate,8,2);
          echo "<item>\n<title>$name</title>\n";
          echo "<guid>$link</guid>\n";
          echo "<link>$link</link>\n";
          echo "<source url=\"".UI::getBaseUrl()."zkrss.php?feed=charts\">$title</source>\n";
          echo "<pubDate>".date("r", strtotime($endDate))."</pubDate>\n";
          echo "<description>$output</description>\n</item>\n";
       }
       echo "</channel>\n";
    }
    
    public function recentReviews() {
       $station = Engine::param('station');
       $weeks = $_REQUEST["weeks"];
       $limit = $_REQUEST["limit"];

       if(!$weeks)
          $weeks = 2;

       if(!$limit)
          $limit = 20;
    
       $title = "$station Radio Music Reviews";
    
       echo "<channel>\n<title>$title</title>\n";
       echo "<link>".UI::getBaseUrl()."?action=viewRecent</link>\n";
       echo "<description>Recent album reviews by $station DJs</description>\n";
       echo "<managingEditor>".Engine::param('email')['md']."</managingEditor>\n";
       echo "<ttl>20</ttl>\n";
       echo "<language>en-us</language>\n";
       $results = Engine::api(IReview::class)->getRecentReviews("", $weeks, $limit);
       while($results && ($row = $results->fetch())) {
          // Link to album
          $link = UI::getBaseUrl()."?action=viewRecentReview&amp;tag=$row[0]";
    
          // DJ
          if($row[1])
              $djname = $row[1];
          else {
              $djs = Engine::api(ILibrary::class)->search(ILibrary::PASSWD_NAME, 0, 1, $row[2]);
              $djname = $djs[0]["realname"];
          }
    
          // Album / Artist
          $album = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $row[0]);
          if (preg_match("/^\[coll\]/i", $album[0]["artist"]))
              $name = "Various Artists";
          else
              $name = self::xmlentities($album[0]["artist"]);
          $name .= " / " . self::xmlentities($album[0]["album"]);
    
          // Review
          $reviews = Engine::api(IReview::class)->getReviews($row[0]);
          foreach($reviews as $review) {
             if($review["user"] == $row[2]) {
                $review = $review[2];
                //if(strlen($review) > 500)
                //   $review = substr($review, 0, 497) . "...";
                $review = nl2br(self::xmlentities($review, ENT_QUOTES));
                $review = self::xmlentities($review);
                break;
             }
          }
    
          //echo "<item>\n<description>&lt;p&gt;&lt;a href=\"$link\"&gt;$name&lt;/a&gt; review by ".self::xmlentities($djname)."&lt;/p&gt;$review</description>\n";
          echo "<item>\n<description>&lt;p&gt;Review by ".self::xmlentities($djname)."&lt;/p&gt;&lt;p&gt;$review&lt;/p&gt;</description>\n";
          echo "<title>$name</title>\n";
          echo "<guid isPermaLink=\"false\">review-".$row[0]."-".substr($row[3],0,10)."</guid>\n";
          echo "<category>".self::xmlentities(Search::GENRES[$album[0]["category"]])."</category>\n";
          echo "<link>$link</link>\n";
          //echo "<source url=\"".UI::getBaseUrl()."zkrss.php?feed=reviews\">".self::xmlentities($djname)."</source>\n";
          echo "<dc:creator>".self::xmlentities($djname)."</dc:creator>\n";
          echo "<source url=\"".UI::getBaseUrl()."zkrss.php?feed=reviews\">$title</source>\n";
          echo "<pubDate>".date("r",strtotime($row[3]))."</pubDate>\n</item>\n";
       }
       echo "</channel>\n";
    }
    
    public function emitAddRSS($addDate, &$title) {
        $station = Engine::param('station');
        $results = Engine::api(IChart::class)->getAdd($addDate);
        if($results) {
            $title = "$station Adds ";
    
            list($y, $m, $d) = explode("-", $addDate);
            $formatDate = date("l, j F Y", mktime(0,0,0,$m,$d,$y));
            $title .= "for $formatDate";
    
            $output = "&lt;p&gt;Num (Charts) ARTIST &lt;I&gt;Album&lt;/I&gt; (Label)&lt;/p&gt;\n";
            $output .= "&lt;p&gt;";
    
            // Get the chart categories
            $cats = Engine::api(IChart::class)->getCategories();
    
            while($row = $results->fetch()) {
                // Fixup the artist, album, and label names
                $artist = $row["artist"];
                $label = $row["label"];
    
                // Setup medium
                $album = $row["album"];
                switch($row["medium"]) {
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
    
                $output .= $row["afile_number"]." ";
    
                // Categories
                $codes = "";
                $acats = explode(",", $row["afile_category"]);
                foreach($acats as $index => $cat)
                    if($cat)
                        $codes .= $cats[$cat-1]["code"];
    
                if($codes == "")
                    $codes = "G";
    
                $output .= "(".$codes.") ";
    
                // Artist
                $output .= self::htmlnumericentities(self::xmlentities(strtoupper($artist))) . " &lt;I&gt;";
                // Album & Label
                $output .= "&lt;A HREF=\"".UI::getBaseUrl().
                             "?s=byAlbumKey&amp;n=".$row["tag"].
                             "&amp;q=10".
                             "&amp;action=search&amp;session=".$this->session->getSessionID().
                             "\"&gt;". self::htmlnumericentities(self::xmlentities($album)) . "&lt;/A&gt;&lt;/I&gt;$medium (" .
                     self::htmlnumericentities(self::xmlentities($label)) . ")&lt;BR&gt;\n";
            }
    
            $output .= "&lt;/p&gt;";
        }
        return $output;
    }
    
    public function recentAdds() {
       $station = Engine::param('station');
       $weeks = $_REQUEST["weeks"];

       if(!$weeks)
          $weeks = 4;
    
       $title = "$station Radio A-File Adds";
    
       echo "<channel>\n<title>$title</title>\n";
       echo "<link>".UI::getBaseUrl()."?action=addmgr</link>\n";
       echo "<description>$station Radio A-File Adds</description>\n";
       echo "<managingEditor>".Engine::param('email')['md']."</managingEditor>\n";
       echo "<language>en-us</language>\n";
    
       $weeks = Engine::api(IChart::class)->getAddDates($weeks);
       while($weeks && ($week = $weeks->fetch())) {
          $addDate = $week["adddate"];
          $output = $this->emitAddRSS($addDate, $name);
          $link = UI::getBaseUrl()."?action=addmgr&amp;subaction=adds&amp;date=$addDate";
          echo "<item>\n<title>$name</title>\n";
          echo "<guid>$link</guid>\n";
          echo "<link>$link</link>\n";
          echo "<source url=\"".UI::getBaseUrl()."zkrss.php?feed=adds\">$title</source>\n";
          echo "<pubDate>".date("r", strtotime($addDate))."</pubDate>\n";
          echo "<description>$output</description>\n</item>\n";
       }
       echo "</channel>\n";
    }
    
    public function emitError() {
       $message = "Invalid feed: ".$_REQUEST["feed"];
       echo "<channel>\n<title>$message</title>\n<link>".UI::getBaseUrl()."</link>\n<description>$message</description>\n</channel>\n";
    }
}
