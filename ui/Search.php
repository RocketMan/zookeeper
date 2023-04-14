<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2022 Jim Mason <jmason@ibinx.com>
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
use ZK\Engine\IArtwork;
use ZK\Engine\IDJ;
use ZK\Engine\ILibrary;

use ZK\UI\UICommon as UI;

class Search extends MenuItem {
    private static $actions = [
        [ "find", "ftSearch" ],
        [ "findAlbum", "findAlbum" ],
        [ "search", "doSearch" ],
    ];

    private static $legacySearchActions = [
        [ "", "legacySearchLandingPage" ],
        [ "byAlbum", "searchForm" ],
        [ "byAlbumKey", "searchByAlbumKey" ],
        [ "byArtist", "searchForm" ],
        [ "byTrack", "searchForm" ],
        [ "byLabel", "searchForm" ],
        [ "byLabelKey", "searchForm" ],
        [ "byReviewer", "searchByReviewer" ],
    ];

    // can be overridden by request params 'q' and 'chunksize', respectively
    private $maxresults = 50;
    private $chunksize = 15;

    private $exactMatch = false;

    private $searchText;

    private $searchType;

    public function processLocal($action, $subaction) {
        return $this->dispatchAction($action, self::$actions);
    }

    public function ftSearch() {
        UI::emitJS('js/search.findit.js');

        $search = array_key_exists("search", $_REQUEST)?$_REQUEST["search"]:"";
        echo "<FORM ACTION=\"?\" METHOD=\"POST\">\n";
        echo "<P><B>Find It:</B>&nbsp;&nbsp;<INPUT TYPE=TEXT CLASS=text STYLE=\"width:214px;\" NAME=search id='search' VALUE=\"$search\" autocomplete=off>&nbsp;&nbsp;<SPAN ID=\"total\"></SPAN></P>\n";
        echo "<INPUT TYPE=HIDDEN NAME=action VALUE=\"find\">\n";
        echo "<INPUT TYPE=HIDDEN NAME=key id='key' VALUE=''>\n";
        echo "</FORM>\n";
        echo "<SPAN ID=\"results\">Search the database for music, reviews, and playlists.";
        echo "</SPAN>\n";
    }

    private function HTMLify($arg, $size) {
        return UI::HTMLify($arg, $size, false);
    }

    public function findAlbum() {
        $this->searchByAlbumKey($_REQUEST["n"]);
    }


    // return link to artist or track info.
    private function makeTrackLink($searchBy, $name, $length) {
        $linkUrl = UI::URLify($name);
        $linkHtml = $this->HTMLify($name, $length);
        $link = "<A HREF='?s=${searchBy}&amp;n=$linkUrl" .
                      "&amp;q=". $this->maxresults.
                      "&amp;action=search'>$linkHtml</A>";
        return $link;
    }

    private function emitTrackInfo($trackInfo, $showArtist, $isAuth, $internalLinks, $enableExternalLinks) {
        $url = $trackInfo["url"];

        // if external links are enabled, suppress internal URLs for
        // non-authenticated users; otherwise, suppress all but internal
        // URLs for authenticated users
        if($enableExternalLinks ?
                $internalLinks && preg_match($internalLinks, $url) && !$isAuth :
                !$internalLinks || !preg_match($internalLinks, $url) || !$isAuth)
            $url = '';

        $playLink = $url == '' ? '' : "<DIV class='playTrack'><A target='_blank' href='$url'></A></DIV>";
        echo "<TD>$playLink</TD>";
        echo "<TD>${trackInfo['seq']}.</TD>";

        if ($showArtist) { // collection only
            $artistLink = $this->makeTrackLink('byArtist', $trackInfo['artist'], 20);
            echo "<TD>$artistLink</TD>";
        }

        $titleLink = $this->makeTrackLink('byTrack', $trackInfo['track'], 32);
        echo "<TD>$titleLink</TD>";
    }

    private function emitDiscogsHook($tag) {
        $imageApi = Engine::api(IArtwork::class);
        $image = $imageApi->getAlbumArt($tag);
        if($image && ($uuid = $image["image_uuid"])) {
            $url = $imageApi->getCachePath($uuid);
            $target = ($info = $image["info_url"])?
                "<A HREF='$info' TARGET='_blank'><IMG SRC='$url' title='View album in Discogs'></IMG></A>" :
                "<IMG SRC='$url'></IMG>";
            echo "<div class='album-thumb'>$target</div>";
            return "style='max-width: 564px'";
        }
    }

    public function searchByAlbumKey($key=0) {
        $opened = 0;

        $isAuth = $this->session->isAuth('u');
        $internalLinks = Engine::param('internal_links');
        $enableExternalLinks = Engine::param('external_links_enabled');

        if($key)
            $this->searchText = $key;
    
        $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $this->searchText);

        $artist = strcmp(substr($albums[0]["artist"], 0, 8), "[coll]: ")?
                      $albums[0]["artist"]:"Various Artists";

        $this->title = $artist . " / " . $albums[0]["album"];
        echo "<TABLE WIDTH=\"100%\">\n  <TR><TH ALIGN=LEFT COLSPAN=5 CLASS=\"secdiv\">" .
                  $this->HTMLify($artist, 20) . " / " .
                  $this->HTMLify($albums[0]["album"], 20);
        if($isAuth) {
            echo "&nbsp;&nbsp;(Tag #".$albums[0]["tag"].")";
            $this->title .= " (#" . $albums[0]["tag"] . ")";
        }
        echo "</TH></TR>\n</TABLE>";
        $extraAttrs = $this->emitDiscogsHook($this->searchText);
        echo "<TABLE CLASS='album-info' $extraAttrs>\n";

        echo "  <TR><TD ALIGN=RIGHT>Album:</TD><TD><B>";
    
        echo "<A HREF=\"".
                     "?s=byAlbum&amp;n=". UI::URLify($albums[0]["album"]).
                     "&amp;q=". $this->maxresults.
                     "&amp;action=search\" CLASS=\"nav\">";
        echo htmlentities($albums[0]["album"]) . "</A></B></TD>";
    
        $medium = " " . ILibrary::MEDIA[$albums[0]["medium"]];
        if($medium == " CD") $medium = "";
    
        $showMissing = "missing";
        echo "<TD WIDTH=80>&nbsp;</TD>" .
             "<TD ALIGN=RIGHT>Collection:</TD><TD><B>";
        switch($albums[0]["location"]) {
        case 'G':
            echo "<I>Deep&nbsp;Storage&nbsp;".$albums[0]["bin"]."</I>";
            $showMissing = 0;
            break;
        case 'M':
            echo "<I>Missing</I>";
            $showMissing = "found";
            break;
        case 'E':
            echo "<I>Review Shelf</I>";
            break;
        case 'F':
            echo "<I>Out for Review</I>";
            break;
        case 'U':
            echo "<I>Deaccessioned</I>";
            $showMissing = 0;
            break;
        default:
            echo ILibrary::GENRES[$albums[0]["category"]] . $medium;
            break;
        }
        echo "</B>";
        if($isAuth && $showMissing) {
            $urls = Engine::param('urls');
            if(array_key_exists('report_missing', $urls)) {
                $url = str_replace('%USERNAME%', UI::URLify($this->session->getDN()), $urls['report_missing']);
                $url = str_replace('%ALBUMTAG%', $albums[0]["tag"], $url);
                echo "&nbsp;&nbsp;<A HREF=\"$url\" CLASS=\"nav\" TARGET=\"_blank\">[report $showMissing...]</A>";
            }
        }
        echo "</TD></TR>\n";
        echo "  <TR><TD ALIGN=RIGHT>Artist:</TD><TD><B>";
    
        if(strcmp($artist, "Various Artists")) {
            echo "<A HREF=\"".
                         "?s=byArtist&amp;n=". UI::URLify($artist).
                         "&amp;q=". $this->maxresults.
                         "&amp;action=search\" CLASS=\"nav\">";
            echo htmlentities($artist) . "</A></B></TD>";
        } else
            echo htmlentities($artist) . "</B></TD>";
        echo "<TD>&nbsp;</TD>" .
             "<TD ALIGN=RIGHT>Added:</TD><TD class='date'><B>";
        $created = new \DateTime($albums[0]["created"]);
        echo $created->format("M Y") . "</B></TD></TR>\n";
        echo "  <TR><TD ALIGN=RIGHT>Label:</TD><TD><B>";
        if($albums[0]["pubkey"]) {
            echo "<A HREF=\"".
                           "?s=byLabelKey&amp;n=". UI::URLify($albums[0]["pubkey"]).
                           "&amp;q=". $this->maxresults.
                           "&amp;action=search\" CLASS=\"nav\">";
            echo htmlentities($albums[0]["name"]) . "</A>";
        } else
            echo "(Unknown)";
        echo "</B></TD><TD COLSPAN=2>&nbsp;</TD><TD>";
        $this->newEntity(Reviews::class)->emitReviewHook($this->searchText);
        echo "</TD></TR>\n";
    
        echo "</TABLE>\n<BR>\n";
    
        // Emit Currents data
        $this->newEntity(AddManager::class)->viewCurrents($this->searchText);
    
        // Emit last plays
        $this->newEntity(Playlists::class)->viewLastPlays($this->searchText, 6);
    
        // Emit Review
        $this->newEntity(Reviews::class)->viewReview2($this->searchText);
    
        // Emit Tracks
        echo "<TABLE style='margin-bottom:4px' WIDTH='100%'><TR><TH COLSPAN=6 ALIGN=LEFT CLASS='secdiv'>Track Listing</TH></TR></TABLE>\n";
    
        // Handle collection tracks
        $albums = Engine::api(ILibrary::class)->search(ILibrary::COLL_KEY, 0, 200, $this->searchText);
        for($i = 0; $i < sizeof($albums); $i++) {
            if($i == 0) {
                echo "<TABLE class='trackTable'><TR><TH>&nbsp;</TH><TH></TH><TH ALIGN=LEFT style='min-width:200px'>Artist</TH><TH ALIGN=LEFT>Track Name</TH></TR>\n";
            }

            echo "<TR>";
            echo $this->emitTrackInfo($albums[$i], true, $isAuth, $internalLinks, $enableExternalLinks);
            echo "</TR>\n";
        }

        if($i)
            echo $this->closeList();
        else {
            // Handle non-collection tracks
            $tracks = Engine::api(ILibrary::class)->search(ILibrary::TRACK_KEY, 0, 200, $this->searchText);
    
            $mid = sizeof($tracks) / 2;
            for($i = 0; $i < $mid; $i++){
                if(!$opened) {
                    echo "<TABLE class='trackTable'>\n";
                    $opened = 1;
                }

                if($mid - $i < 1)
                    echo "<TR><TD COLSPAN=4>&nbsp;</TD>";
                else {
                    echo "<TR>";
                    $this->emitTrackInfo($tracks[$i], false, $isAuth, $internalLinks, $enableExternalLinks); // left side
                    echo "<TD>&nbsp;</TD>"; // replace with a right pad
                }

                $this->emitTrackInfo($tracks[$i + $mid], false, $isAuth, $internalLinks, $enableExternalLinks); // right side
                echo "</TR>\n";
            }
            if($opened) echo $this->closeList();
        }
    }
    
    public function doSearch() {
        if(array_key_exists('m', $_REQUEST))
            $this->exactMatch = $_REQUEST['m'];
        if(array_key_exists('n', $_REQUEST))
            $this->searchText = stripslashes($_REQUEST['n']);
        if(array_key_exists('q', $_REQUEST) && $_REQUEST['q'])
            $this->maxresults = (integer)$_REQUEST['q'];
        if(array_key_exists('chunksize', $_REQUEST) && $_REQUEST['chunksize'])
            $this->chunksize = (integer)$_REQUEST['chunksize'];
        if($this->maxresults < $this->chunksize)
            $this->maxresults = $this->chunksize;

        $this->searchType = $this->searchText &&
                array_key_exists('s', $_REQUEST)?$_REQUEST['s']:"";
        $this->dispatchAction($this->searchType, self::$legacySearchActions);
    }
    
    public function legacySearchLandingPage() {
        $this->searchForm();
    }
    
    // returns closing tag for output
    private function closeList() {
        $close = "</TABLE>\n";
        return $close;
    }
    
    public function searchForm() {
        UI::emitJS('js/jquery.bahashchange.min.js');
        UI::emitJS('js/search.library.js');

        switch($this->searchType){
        case "byArtist":
        case "byCollArtist":
            $chkArtist = " checked";
            break;
        case "byAlbum":
            $chkAlbum = " checked";
            break;
        case "byTrack":
        case "byCollTrack":
            $chkTrack = " checked";
            break;
        case "byLabel":
            $chkLabel = " checked";
            break;
        case "byLabelKey":
            $labelKey = 1;
            break;
        }
        if ($chkArtist || $chkAlbum || $chkTrack || $chkLabel ) {
            if($this->exactMatch)
                $chkExact =" checked";
            if($this->searchText)
                $searchFor =" VALUE=\"".htmlspecialchars($this->searchText)."\"";
        } else {
            // Default to search by artist
            $chkArtist = " checked";
        }
    
        switch ($this->maxresults) {
        case 15:
            $o_fifteen = " SELECTED";
            break;
        case 20:
            $o_twenty = " SELECTED";
            break;
        case 50:
            $o_fifty = " SELECTED";
            break;
        default:
            $o_ten = " SELECTED";
            break;
        }
    ?>
    <FORM ACTION="?" METHOD=POST id='search'>
    <TABLE WIDTH="100%">
      <TR><TD>
        <TABLE CELLPADDING=2>
          <TR>
            <TD ALIGN=RIGHT><B>Search by:</B></TD>
            <TD><INPUT TYPE=RADIO NAME=s VALUE="artists" data-sort="Artist"<?php echo $chkArtist;?>>Artist</TD>
            <TD><INPUT TYPE=RADIO NAME=s VALUE="albums" data-sort="Album"<?php echo $chkAlbum;?>>Album</TD>
            <TD><INPUT TYPE=RADIO NAME=s VALUE="tracks" data-sort="Track"<?php echo $chkTrack;?>>Track</TD>
            <TD><INPUT TYPE=RADIO NAME=s VALUE="labels" data-sort=""<?php echo $chkLabel;?>>Label</TD>
          </TR>
          <TR>
            <TD ALIGN=RIGHT><b>For:</b></TD>
            <TD COLSPAN=4><INPUT TYPE=TEXT id='n'<?php echo $searchFor;?> SIZE=35 CLASS=input autocomplete=off></TD>
          </TR>
          <TR>
            <TD></TD>
            <TD><INPUT TYPE=SUBMIT VALUE="Search"></TD>
            <TD COLSPAN=2><INPUT TYPE=CHECKBOX id='m' VALUE=1<?php echo $chkExact;?>>Exact match</TD>
            <!-- fix page size to maxresults -->
            <TD><input type='hidden' id='maxresults' value='<?php echo $this->maxresults; ?>'></TD>
          </TR>
        </TABLE>
      </TD></TR>
    </TABLE>
    <INPUT TYPE=HIDDEN id='sortBy' value=''>
    <INPUT TYPE=HIDDEN id='type' value='<?php echo $labelKey?"albumsByPubkey":""; ?>'>
    <INPUT TYPE=HIDDEN id='key' value='<?php echo $labelKey?htmlspecialchars($this->searchText):""; ?>'>
    <INPUT TYPE=HIDDEN id='chunksize' value='<?php echo $this->chunksize; ?>'>
    </FORM>
    <BR>
    <TABLE class='searchTable' CELLPADDING=2 CELLSPACING=0 BORDER=0>
    <TR><TD><B>Tip:  For a more extensive search,
               try <A HREF="?action=find" CLASS="nav">Find It!</A></B></TD></TR>
    </TABLE>
    <?php 
    }
    
    public function searchByReviewer() {
        $sortBy = array_key_exists("sortBy", $_REQUEST)?$_REQUEST["sortBy"]:"";
        if(!$sortBy)$sortBy="Artist";
    
        if($this->searchText) {
            $airnames = Engine::api(IDJ::class)->getAirnames($this->session->getUser(), $this->searchText);
            if ($arow = $airnames->fetch())
                $name = $arow["airname"];
        }
    
        if($name) {
            UI::emitJS('js/jquery.bahashchange.min.js');
            UI::emitJS('js/search.library.js');
            echo "<form>\n<input id='showTag' type='hidden' value='" . ($this->session->isAuth('u')?'true':'false') . "'>\n";
            echo "<input id='type' type='hidden' value='reviews'>\n";
            echo "<input id='sortBy' type='hidden' value='".$sortBy."'>\n";
            echo "<input id='key' type='hidden' value='" . $this->searchText . "'>\n";
            echo "<input id='maxresults' type='hidden' value='" . $this->maxresults . "'>\n";
            echo "<input id='chunksize' type='hidden' value='" . $this->chunksize . "'\n>";
            echo "</form>\n";
            echo "<h2>$name's Album Reviews</h2>\n";
            echo "<table class='searchTable' id='results'></table>\n";
        } else
            echo "<h2>Unknown DJ</h2>\n";
    }
}
