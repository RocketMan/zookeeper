<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2021 Jim Mason <jmason@ibinx.com>
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

    public function processRequest($dispatcher) {
        $this->session = Engine::session();

        if(isset($_REQUEST["xslt"])) {
            $this->emitXSLT();
            return;
        }

        header("Content-type: text/xml");
        ob_start("ob_gzhandler");
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<?xml-stylesheet type=\"text/xsl\" href=\"zkrss.xslt\"?>\n";
        echo "<rss version=\"2.0\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\"\n";
        echo "    xmlns:zk=\"http://zookeeper.ibinx.com/zkns\">";
        
        $feeds = explode(',', $_REQUEST["feed"]);
        foreach($feeds as $feed)
            $this->processLocal($feed, null);

        echo "</rss>\n";
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
            $formatDate = date("l, j F Y", mktime(0,0,0,$m,$d,$y));
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
                $output .= "<a href=\"".UI::getBaseUrl().
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
          $output = $this->composeChartRSS($endDate, $name, $top);
          $link = UI::getBaseUrl()."?action=viewChart&amp;subaction=weekly&amp;year=".substr($endDate,0,4)."&amp;month=".substr($endDate,5,2)."&amp;day=".substr($endDate,8,2);
          echo "<item>\n<title>$name</title>\n";
          echo "<guid>$link</guid>\n";
          echo "<link>$link</link>\n";
          echo "<source url=\"".UI::getBaseUrl()."zkrss.php?feed=charts\">$title</source>\n";
          echo "<pubDate>".date("r", strtotime($endDate))."</pubDate>\n";
          // zk:subtitle is blank as title already contains the date
          echo "<zk:subtitle></zk:subtitle>\n";
          echo "<description>$output</description>\n</item>\n";
       }
       echo "</channel>\n";
    }
    
    public function recentReviews() {
       $station = Engine::param('station');
       $limit = isset($_REQUEST["limit"])?$_REQUEST["limit"]:50;

       $title = "$station Radio Music Reviews";
    
       echo "<channel>\n<title>$title</title>\n";
       echo "<link>".UI::getBaseUrl()."?action=viewRecent</link>\n";
       echo "<description>Recent album reviews by $station DJs</description>\n";
       echo "<managingEditor>".Engine::param('email')['md']."</managingEditor>\n";
       echo "<ttl>20</ttl>\n";
       echo "<language>en-us</language>\n";
       $results = Engine::api(IReview::class)->getRecentReviews("", 0, $limit);
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
                $review = nl2br(self::xmlentities(trim($review), ENT_QUOTES));
                break;
             }
          }
    
          $output = self::xmlcdata("<p>Review by ".self::xmlentities($djname)."</p><p>$review</p>");
          echo "<item>\n<description>$output</description>\n";
          echo "<title>$name</title>\n";
          echo "<guid isPermaLink=\"false\">review-".$row[0]."-".substr($row[3],0,10)."</guid>\n";
          echo "<category>".self::xmlentities(ILibrary::GENRES[$album[0]["category"]])."</category>\n";
          echo "<link>$link</link>\n";
          //echo "<source url=\"".UI::getBaseUrl()."zkrss.php?feed=reviews\">".self::xmlentities($djname)."</source>\n";
          echo "<dc:creator>".self::xmlentities($djname)."</dc:creator>\n";
          echo "<source url=\"".UI::getBaseUrl()."zkrss.php?feed=reviews\">$title</source>\n";
          $time = strtotime($row[3]);
          echo "<pubDate>".date("r", $time)."</pubDate>\n";
          echo "<zk:subtitle>Reviewed ".date("F j, Y", $time)."</zk:subtitle>\n</item>\n";
       }
       echo "</channel>\n";
    }
    
    public function composeAddRSS($addDate, &$title) {
        $station = Engine::param('station');
        $results = Engine::api(IChart::class)->getAdd($addDate);
        if($results) {
            $title = "$station Adds ";
    
            list($y, $m, $d) = explode("-", $addDate);
            $formatDate = date("l, j F Y", mktime(0,0,0,$m,$d,$y));
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
                $output .= "<a href=\"".UI::getBaseUrl().
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
          $output = $this->composeAddRSS($addDate, $name);
          $link = UI::getBaseUrl()."?action=addmgr&amp;subaction=adds&amp;date=$addDate";
          echo "<item>\n<title>$name</title>\n";
          echo "<guid>$link</guid>\n";
          echo "<link>$link</link>\n";
          echo "<source url=\"".UI::getBaseUrl()."zkrss.php?feed=adds\">$title</source>\n";
          echo "<pubDate>".date("r", strtotime($addDate))."</pubDate>\n";
          // zk:subtitle is blank as title already contains the date
          echo "<zk:subtitle></zk:subtitle>\n";
          echo "<description>$output</description>\n</item>\n";
       }
       echo "</channel>\n";
    }
    
    public function emitError() {
       $message = "Invalid feed: ".$_REQUEST["feed"];
       echo "<channel>\n<title>$message</title>\n<link>".UI::getBaseUrl()."</link>\n<description>$message</description>\n</channel>\n";
    }

    private function emitXSLT() {
        $mtime = max(filemtime(realpath(__FILE__)),
                     filemtime(realpath(__DIR__."/../css/zoostyle.css")));
        header("ETag: \"${mtime}\""); // RFC 2616 requires ETag in 304 response
        if(isset($_SERVER['HTTP_IF_NONE_MATCH']) &&
                strstr($_SERVER['HTTP_IF_NONE_MATCH'], "\"${mtime}\"") !== false ||
                isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) &&
                strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime) {
            http_response_code(304); // Not Modified
            return;
        }
        header("Content-type: application/xslt+xml");
        header("Last-Modified: ".gmdate('D, d M Y H:i:s', $mtime)." GMT");
        ob_start("ob_gzhandler"); ?>
<?xml version="1.0" encoding="UTF-8"?>
<!-- Zookeeper Online (C) 1997-2021 Jim Mason <jmason@ibinx.com> | @source: https://zookeeper.ibinx.com/ | @license: magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3.0 -->
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:zk="http://zookeeper.ibinx.com/zkns"
    xmlns="http://www.w3.org/1999/xhtml" version="1.0">
<xsl:output method="xml"/>
<xsl:template match="/">
<html lang="en">
<head>
<meta charset="UTF-8"/>
<title><xsl:value-of select="rss/channel/title"/></title>
<link rel="stylesheet" href="<?php echo UI::decorate("css/zoostyle.css"); ?>"/>
<style type="text/css">
<xsl:text><![CDATA[
.rss-channel > .rss-item {
  padding: 2px 0px;
}
]]>
</xsl:text>
</style>
<script type="text/javascript"><?php
  /*
   * RSS feeds contain HTML markup which the browser will escape;
   * that is, it will display HTML tags as literal text.  The
   * disable-output-escaping attribute is not supported on all
   * browsers (FX, for example).  We work around these limitations
   * by storing HTML content in a data attribute and then setting
   * the content into the document via innerHTML.
   */ ?>
<xsl:text><![CDATA[
function fixup() {
   var nodes = document.getElementsByClassName("description");
   Array.prototype.slice.call(nodes).forEach(function(node) {
      node.innerHTML = node.dataset.description;
   });
}
]]>
</xsl:text>
</script>
</head>
<body onload="fixup()">
  <div class="box">
    <div class="user-tip" style="display: block">
      <p>This is a Really Simple Syndication (RSS) feed.  RSS is a family of
      web feed formats used to publish frequently updated content.</p>
      <p>To subscribe to this feed, drag or copy the address into your
      RSS feed reader.  New to RSS?
      <a href="https://www.google.com/search?q=how+to+get+started+with+rss+feeds"
      title="Getting started with RSS" target="_blank">Learn more</a>.</p>
    </div>
    <xsl:apply-templates select="rss/channel"/>
  </div>
</body>
</html>
</xsl:template>
<xsl:template match="channel">
  <h2><xsl:value-of select="title"/></h2>
  <div class="rss-channel">
    <xsl:apply-templates select="item"/>
  </div>
</xsl:template>
<xsl:template match="item">
  <div class="rss-item">
    <h3>
      <a class="nav" href="{link}">
        <xsl:value-of select="title"/>
      </a><br/>
      <span class="sub">
        <xsl:value-of select="zk:subtitle"/>
      </span>
    </h3>
    <p class="description" data-description="{description}"/>
  </div>
</xsl:template>
</xsl:stylesheet>
<?php
        ob_end_flush(); // ob_gzhandler
    }
}
