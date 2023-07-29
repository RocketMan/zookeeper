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

namespace ZK\Controllers;

use ZK\Engine\Engine;
use ZK\Engine\IChart;
use ZK\Engine\ILibrary;
use ZK\Engine\IReview;
use ZK\Engine\TemplateFactory;

use ZK\UI\UICommon as UI;

class TemplateFactoryRSS extends TemplateFactory {
    public function __construct() {
        parent::__construct(__DIR__ . '/templates');

        $this->twig->getExtension(\Twig\Extension\EscaperExtension::class)->setEscaper('xml', function($env, $str) {
            return str_replace(['&', '"', "'", '<', '>', '`'],
                ['&amp;' , '&quot;', '&apos;' , '&lt;' , '&gt;', '&apos;'], $str);
        });
    }
}

class RSS extends CommandTarget implements IController {
    private static $actions = [
        [ "", "emitError" ],
        [ "reviews", "recentReviews" ],
        [ "charts", "recentCharts" ],
        [ "adds", "recentAdds" ],
    ];

    private $params = [];

    private static function xmlentities($str) {
       return str_replace(['&', '"', "'", '<', '>', '`'],
           ['&amp;' , '&quot;', '&apos;' , '&lt;' , '&gt;', '&apos;'], $str);
    }

    private static function xmlcdata($str) {
       return "<![CDATA[${str}]]>";
    }
    
    private static function htmlnumericentities($str) {
       // for UTF-8, this is a no-op.  xmlentities has already taken
       // care of the xml entities.
       return $str;
       //return preg_replace_callback('/[^!-%\x27-;=?-~ ]/',
       //   function($m) { return "&#".ord($m[0]).";"; },
       //   $str);
    }

    public function processRequest() {
        $this->session = Engine::session();

        header("Content-type: text/xml");
        ob_start("ob_gzhandler");
        $templateFactory = new TemplateFactoryRSS();
        $template = $templateFactory->load('rss.xml');
        $this->params['baseUrl'] = Engine::getBaseUrl();
        $this->params['feeds'] = [];

        $feeds = explode(',', $_REQUEST['feed']);
        foreach($feeds as $feed)
            $this->processLocal($feed, null);

        echo $template->render($this->params);
        ob_end_flush(); // ob_gzhandler
    }

    public function processLocal($action, $subaction) {
        $this->dispatchAction($action, self::$actions);
    }

    public function composeChartRSS($endDate, &$title, $limit="", $category="") {
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
            $dateSpec = UI::getClientLocale() == 'en_US' ? 'l, F j, Y' : 'l, j F Y';
            $formatDate = date($dateSpec, mktime(0,0,0,$m,$d,$y));
            $title .= "for the week ending $formatDate";
    
            $output = "<p>Rank. ARTIST <i>ALBUM</i> (LABEL)</p>\n";
            $output .= "<p>";
    
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
    
                $output .= (string)($i + 1).". ";
                // Artist
                $output .= self::htmlnumericentities(self::xmlentities(strtoupper($artist))) . " <i>";
                // Album & Label
                $output .= "<a href=\"".Engine::getBaseUrl().
                             "?s=byAlbumKey&amp;n=".$chart[$i]["tag"].
                             "&amp;q=10".
                             "&amp;action=search".
                             "\">". self::htmlnumericentities(self::xmlentities($album)) . "</a></i>$medium (" .
                     self::htmlnumericentities(self::xmlentities($label)) . ")<br/>\n";
            }
    
            $output .= "</p>";
        }
        return self::xmlcdata($output);
    }
    
    public function recentCharts() {
       $station = self::xmlentities(Engine::param('station_title', Engine::param('station')));
       $top = $_REQUEST["top"];
       $weeks = $_REQUEST["weeks"];

       if(!$top)
          $top = 30;
       if(!$weeks)
          $weeks = 10;
    
       $title = "$station Airplay Charts";
    
       echo "<channel>\n<title>$title</title>\n";
       echo "<link>".Engine::getBaseUrl()."?action=viewChart</link>\n";
       echo "<description>$station Airplay Charts</description>\n";
       echo "<managingEditor>".Engine::param('email')['md']."</managingEditor>\n";
       echo "<language>en-us</language>\n";
    
       $weeks = Engine::api(IChart::class)->getChartDates($weeks);
       while($weeks && ($week = $weeks->fetch())) {
          $endDate = $week["week"];
          $output = $this->composeChartRSS($endDate, $name, $top);
          $link = Engine::getBaseUrl()."?action=viewChart&amp;subaction=weekly&amp;year=".substr($endDate,0,4)."&amp;month=".substr($endDate,5,2)."&amp;day=".substr($endDate,8,2);
          echo "<item>\n<title>$name</title>\n";
          echo "<guid>$link</guid>\n";
          echo "<link>$link</link>\n";
          echo "<source url=\"".Engine::getBaseUrl()."zkrss.php?feed=charts\">$title</source>\n";
          echo "<pubDate>".date("r", strtotime($endDate))."</pubDate>\n";
          // zk:subtitle is blank as title already contains the date
          echo "<zk:subtitle></zk:subtitle>\n";
          echo "<description>$output</description>\n</item>\n";
       }
       echo "</channel>\n";
    }
    
    public function recentReviews() {
        $dateSpec = UI::isUsLocale() ? 'F j, Y' : 'j F Y';
        $this->params['dateSpec'] = $dateSpec;
        $this->params['GENRES'] = ILibrary::GENRES;
        $this->params['feeds'][] = 'reviews';

        $limit = $_REQUEST['limit'] ?? 50;
        $results = Engine::api(IReview::class)->getRecentReviews('', 0, $limit);
        foreach($results as &$row) {
            $reviews = Engine::api(IReview::class)->getReviews($row['album']['tag'], 0, $row['user']);
            if(count($reviews))
                $row['review'] = $reviews[0]['review'];
        }

        $this->params['reviews'] = $results;
    }
    
    public function composeAddRSS($addDate, &$title) {
        $station = self::xmlentities(Engine::param('station'));
        $results = Engine::api(IChart::class)->getAdd($addDate);
        if($results) {
            $title = "$station Adds ";
    
            list($y, $m, $d) = explode("-", $addDate);
            $dateSpec = UI::getClientLocale() == 'en_US' ? 'l, F j, Y' : 'l, j F Y';
            $formatDate = date($dateSpec, mktime(0,0,0,$m,$d,$y));
            $title .= "for $formatDate";
    
            $output = "<p>Num (Charts) ARTIST <i>Album</i> (Label)</p>\n";
            $output .= "<p>";
    
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
                $output .= self::htmlnumericentities(self::xmlentities(strtoupper($artist))) . " <i>";
                // Album & Label
                $output .= "<a href=\"".Engine::getBaseUrl().
                             "?s=byAlbumKey&amp;n=".$row["tag"].
                             "&amp;q=10".
                             "&amp;action=search".
                             "\">". self::htmlnumericentities(self::xmlentities($album)) . "</a></i>$medium (" .
                     self::htmlnumericentities(self::xmlentities($label)) . ")<br/>\n";
            }
    
            $output .= "</p>";
        }
        return self::xmlcdata($output);
    }
    
    public function recentAdds() {
       $station = self::xmlentities(Engine::param('station_title', Engine::param('station')));
       $weeks = $_REQUEST["weeks"];

       if(!$weeks)
          $weeks = 4;
    
       $title = "$station A-File Adds";
    
       echo "<channel>\n<title>$title</title>\n";
       echo "<link>".Engine::getBaseUrl()."?action=addmgr</link>\n";
       echo "<description>$station A-File Adds</description>\n";
       echo "<managingEditor>".Engine::param('email')['md']."</managingEditor>\n";
       echo "<language>en-us</language>\n";
    
       $weeks = Engine::api(IChart::class)->getAddDates($weeks);
       while($weeks && ($week = $weeks->fetch())) {
          $addDate = $week["adddate"];
          $output = $this->composeAddRSS($addDate, $name);
          $link = Engine::getBaseUrl()."?action=addmgr&amp;subaction=adds&amp;date=$addDate";
          echo "<item>\n<title>$name</title>\n";
          echo "<guid>$link</guid>\n";
          echo "<link>$link</link>\n";
          echo "<source url=\"".Engine::getBaseUrl()."zkrss.php?feed=adds\">$title</source>\n";
          echo "<pubDate>".date("r", strtotime($addDate))."</pubDate>\n";
          // zk:subtitle is blank as title already contains the date
          echo "<zk:subtitle></zk:subtitle>\n";
          echo "<description>$output</description>\n</item>\n";
       }
       echo "</channel>\n";
    }
    
    public function emitError() {
       $this->params['feeds'][] = 'invalid';
    }
}
