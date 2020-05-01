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
use ZK\Engine\IDJ;
use ZK\Engine\ILibrary;

use ZK\UI\UICommon as UI;

class Search extends MenuItem {
    const GENRES = [
        "B"=>"Blues",
        "C"=>"Country",
        "G"=>"General",
        "H"=>"Hip-hop",
        "J"=>"Jazz",
        "K"=>"Childrens",
        "L"=>"Classical",
        "N"=>"Novelty",
        "O"=>"Comedy",
        "P"=>"Spoken Word",
        "R"=>"Reggae",
        "S"=>"Soundtrack",
        "W"=>"World",
    ];
    
    const MEDIA = [
        "C"=>"CD",
        "M"=>"Cassette",
        "S"=>"7\"",
        "T"=>"10\"",
        "V"=>"12\"",
    ];
    
    const LENGTHS = [
        "E"=>"EP",
        "F"=>"Full",
        "S"=>"Single",
    ];
    
    const LOCATIONS = [
        "D"=>"Received",
        "E"=>"Review Shelf",
        "F"=>"Out for Review",
        "H"=>"Pending Appr",
        "C"=>"A-File",
        "G"=>"Storage",
        "L"=>"Library",
        "M"=>"Missing",
        "R"=>"Needs Repair",
        "U"=>"Deaccessioned",
    ];
    
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

    private $pos = 0;

    private $maxresults = 50; //NOTE: this value is typically overriden by the invoking request's 'q' attribute.

    private $noTables = false;

    private $exactMatch = false;

    private $searchText;

    private $searchType;

    private $sortBy;
    
    public function processLocal($action, $subaction) {
        return $this->dispatchAction($action, self::$actions);
    }

    public function ftSearch() {
        UI::emitJS('js/search.findit.js');

        $search = array_key_exists("search", $_REQUEST)?$_REQUEST["search"]:"";
        echo "<FORM ACTION=\"?\" METHOD=\"POST\">\n";
        echo "<P><B>Find It:</B>&nbsp;&nbsp;<INPUT TYPE=TEXT CLASS=text STYLE=\"width:214px;\" NAME=search id='search' VALUE=\"$search\" autocomplete=off>&nbsp;&nbsp;<SPAN ID=\"total\"></SPAN></P>\n";
        echo "<INPUT TYPE=HIDDEN NAME=action VALUE=\"find\">\n";
        echo "<INPUT TYPE=HIDDEN NAME=session id='session' VALUE=\"".$this->session->getSessionID()."\">\n";
        echo "<INPUT TYPE=HIDDEN NAME=key id='key' VALUE=''>\n";
        echo "</FORM>\n";
        echo "<SPAN ID=\"results\">Search the database for music, reviews, and playlists.";
        echo "</SPAN>\n";
    }

    private function HTMLify($arg, $size) {
        return UI::HTMLify($arg, $size, $this->noTables);
    }

    public function findAlbum() {
        $this->searchByAlbumKey($_REQUEST["n"]);
    }

    public function searchByAlbumKey($key=0) {
        $opened = 0;

        if($key)
            $this->searchText = $key;
    
        $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $this->searchText);

        $artist = strcmp(substr($albums[0]["artist"], 0, 8), "[coll]: ")?
                      $albums[0]["artist"]:"Various Artists";
        echo "<TABLE WIDTH=\"100%\">\n  <TR><TH ALIGN=LEFT COLSPAN=5 CLASS=\"secdiv\">" .
                  $this->HTMLify($artist, 20) . " / " .
                  $this->HTMLify($albums[0]["album"], 20);
        if($this->session->isAuth("u"))
            echo "&nbsp;&nbsp;(Tag #".$albums[0]["tag"].")";
        echo "</TH></TR>\n</TABLE>";
        echo "<TABLE>\n";
        echo "  <TR><TD ALIGN=RIGHT>Album:</TD><TD><B>";
    
        echo "<A HREF=\"".
                     "?s=byAlbum&amp;n=". UI::URLify($albums[0]["album"]).
                     "&amp;q=". $this->maxresults.
                     "&amp;action=search&amp;session=".$this->session->getSessionID().
                     "\" CLASS=\"nav\">";
        echo htmlentities($albums[0]["album"]) . "</A></B></TD>";
    
        $medium = " " . Search::MEDIA[$albums[0]["medium"]];
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
            echo Search::GENRES[$albums[0]["category"]] . $medium;
            break;
        }
        echo "</B>";
        if($this->session->isAuth("u") && $showMissing) {
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
                         "&amp;action=search&amp;session=".$this->session->getSessionID().
                         "\" CLASS=\"nav\">";
            echo htmlentities($artist) . "</A></B></TD>";
        } else
            echo htmlentities($artist) . "</B></TD>";
        echo "<TD>&nbsp;</TD>" .
             "<TD ALIGN=RIGHT>Added:</TD><TD><B>";
        list ($year, $month, $day) = explode("-", $albums[0]["created"]);
    
        echo "$month/$year</B></TD></TR>\n";
        echo "  <TR><TD ALIGN=RIGHT>Label:</TD><TD><B>";
        if($albums[0]["pubkey"] != 0) {
            $label = Engine::api(ILibrary::class)->search(ILibrary::LABEL_PUBKEY, 0, 1, $albums[0]["pubkey"]);
            if(sizeof($label)) {
                echo "<A HREF=\"".
                               "?s=byLabelKey&amp;n=". UI::URLify($albums[0]["pubkey"]).
                               "&amp;q=". $this->maxresults.
                               "&amp;action=search&amp;session=".$this->session->getSessionID().
                               "\" CLASS=\"nav\">";
                echo htmlentities($label[0]["name"]) . "</A>";
            } else
                echo "(Unknown)";
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
        echo "<TABLE WIDTH=\"100%\">\n  <TR><TH COLSPAN=5 ALIGN=LEFT CLASS=\"secdiv\">Track Listing</TH></TR></TABLE>\n";
    
        // Handle collection tracks
        $albums = Engine::api(ILibrary::class)->search(ILibrary::COLL_KEY, 0, 200, $this->searchText);
        for($i = 0; $i < sizeof($albums); $i++) {
            if($i == 0) {
                if($this->noTables)
                    // 3 20 32
                    echo "<PRE><B>  # Artist               Track Name                      </B>\n";
                else
                    echo "<TABLE>\n  <TR><TH>&nbsp;</TH><TH ALIGN=LEFT>Artist</TH><TH ALIGN=LEFT>Track Name</TH></TR>\n";
            }
    
            // Number
            if($this->noTables)
                echo UI::HTMLifyNum($albums[$i]["seq"], 3, 1);
            echo "  <TR><TD ALIGN=RIGHT>".$albums[$i]["seq"].".</TD><TD>";
    
            // Artist Name
            echo "<A HREF=\"".
                               "?s=byArtist&amp;n=". UI::URLify($albums[$i]["artist"]).
                               "&amp;q=". $this->maxresults.
                               "&amp;action=search&amp;session=".$this->session->getSessionID().
                               "\">";
            echo $this->HTMLify($albums[$i]["artist"], 20), "</A>";
            if(!$this->noTables)
                echo "</TD><TD>\n";
    
            // Track Name
            echo "<A HREF=\"".
                               "?s=byTrack&amp;n=". UI::URLify($albums[$i]["track"]).
                               "&amp;q=". $this->maxresults.
                               "&amp;action=search&amp;session=".$this->session->getSessionID().
                               "\">";
            echo $this->HTMLify($albums[$i]["track"], 32). "</A>";
            if(!$this->noTables)
                echo "</TD></TR>";
            echo "\n";
        }
        if($i)
            echo $this->closeList();
        else {
            // Handle non-collection tracks
            $tracks = Engine::api(ILibrary::class)->search(ILibrary::TRACK_KEY, 0, 200, $this->searchText);
    
            $mid = sizeof($tracks) / 2;
            for($i = 0; $i < $mid; $i++){
                if(!$opened) {
                    if($this->noTables)
                        // 3 32
                        echo "<PRE><B>  # Track Name                      </B>\n";
                    else
                        echo "<TABLE>\n";
    
                    $opened = 1;
                }
                // Number
    
                if($mid - $i < 1)
                    if($this->noTables)
                        echo UI::HTMLify(" ", 36, 1);
                    else
                        echo "  <TR><TD COLSPAN=3>&nbsp;</TD>";
                else {
                    if($this->noTables)
                        echo UI::HTMLifyNum($tracks[$i]["seq"], 3, 1);
                    else
                        echo "  <TR><TD ALIGN=RIGHT>".$tracks[$i]["seq"].".</TD><TD>";
                    // Name
                    echo "<A HREF=\"".
                                 "?s=byTrack&amp;n=". UI::URLify($tracks[$i]["track"]).
                                 "&amp;q=". $this->maxresults.
                                 "&amp;action=search&amp;session=".$this->session->getSessionID().
                                 "\">";
                    echo $this->HTMLify($tracks[$i]["track"], 32), "</A>";
                    if(!$this->noTables)
                        echo "</TD><TD>&nbsp;</TD>";
                }
    
                if($this->noTables)
                    echo UI::HTMLifyNum($tracks[$mid + $i]["seq"], 3, 1);
                else
                    echo "<TD ALIGN=RIGHT>".$tracks[$mid + $i]["seq"].".</TD><TD>";
                // Name
                echo "<A HREF=\"".
                                    "?s=byTrack&amp;n=". UI::URLify($tracks[$mid + $i]["track"]).
                                    "&amp;q=". $this->maxresults.
                                    "&amp;action=search&amp;session=".$this->session->getSessionID().
                                    "\">";
                echo $this->HTMLify($tracks[$mid + $i]["track"], 32), "</A>";
    
                if(!$this->noTables)
                    echo "</TD></TR>";
                echo "\n";
    
            }
            if($opened) echo $this->closeList();
        }
    
        UI::setFocus();
    }
    
    public function doSearch() {
        $this->checkBrowserCaps();
    
        if(array_key_exists('m', $_REQUEST))
            $this->exactMatch = $_REQUEST['m'];
        if(array_key_exists('n', $_REQUEST))
            $this->searchText = stripslashes($_REQUEST['n']);
        if(array_key_exists('p', $_REQUEST))
            $this->pos = (integer)$_REQUEST['p'];
        if(array_key_exists('q', $_REQUEST) && $_REQUEST['q'])
            $this->maxresults = (integer)$_REQUEST['q'];

        $this->searchType = $this->searchText &&
                array_key_exists('s', $_REQUEST)?$_REQUEST['s']:"";
        $this->dispatchAction($this->searchType, self::$legacySearchActions);
    }
    
    public function legacySearchLandingPage() {
        $this->searchForm();
    }
    
    // returns closing tag for output
    private function closeList() {
        if($this->noTables)
        $close = "</PRE>\n";
            else
        $close = "</TABLE>\n";
        return $close;
    }
    
    private function searchString() {
        $searchString = $this->searchText;
        if(!$this->exactMatch)
            $searchString .= "*";
        return $searchString;
    }
    
    // CheckBrowserCaps
    //
    // Check browser's capabilities:
    //     noTables property set true if browser does not support tables
    //
    private function checkBrowserCaps() {
        // For now, we naively assume all browsers support tables except Lynx.
        $this->noTables = (substr($_SERVER["HTTP_USER_AGENT"], 0, 5) == "Lynx/");
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
            <TD><INPUT TYPE=RADIO NAME=s VALUE="artists"<?php echo $chkArtist;?>>Artist</TD>
            <TD><INPUT TYPE=RADIO NAME=s VALUE="albums"<?php echo $chkAlbum;?>>Album</TD>
            <TD><INPUT TYPE=RADIO NAME=s VALUE="tracks"<?php echo $chkTrack;?>>Track</TD>
            <TD><INPUT TYPE=RADIO NAME=s VALUE="labels"<?php echo $chkLabel;?>>Label</TD>
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
            <TD><input type='hidden' id='maxresults' value='<?php echo $this->maxresults; ?>'></input></TD>
          </TR>
        </TABLE>
      </TD></TR>
    </TABLE>
    <INPUT TYPE=HIDDEN id='session' VALUE="<?php echo $this->session->getSessionID();?>">
    <INPUT TYPE=HIDDEN id='sortBy' value=''>
    <INPUT TYPE=HIDDEN id='type' value='<?php echo $labelKey?"albumsByPubkey":""; ?>'>
    <INPUT TYPE=HIDDEN id='key' value='<?php echo $labelKey?htmlspecialchars($this->searchText):""; ?>'>
    </FORM>
    <BR>
    <TABLE class='searchTable' CELLPADDING=2 CELLSPACING=0 BORDER=0>
    <TR><TD><B>Tip:  For a more extensive search,
               try <A HREF="?session=<?php echo $this->session->getSessionID(); ?>&amp;action=find" CLASS="nav">Find It!</A></B></TD></TR>
    </TABLE>
    <?php 
    }
    
    public function searchByReviewer() {
        $this->sortBy = array_key_exists("sortBy", $_REQUEST)?$_REQUEST["sortBy"]:"";
        if(!$this->sortBy)$this->sortBy="Artist";
    
        if($this->searchText) {
            $airnames = Engine::api(IDJ::class)->getAirnames($this->session->getUser(), $this->searchText);
            if ($arow = $airnames->fetch())
                $name = $arow["airname"];
        }
    
        if($name) {
            UI::emitJS('js/jquery.bahashchange.min.js');
            UI::emitJS('js/search.library.js');
            echo "<FORM>\n<INPUT id='session' type='hidden' value='" . $this->session->getSessionID() . "'>\n";
            echo "<INPUT id='type' type='hidden' value='reviews'>\n";
            echo "<INPUT id='sortBy' type='hidden' value='".$this->sortBy."'>\n";
            echo "<INPUT id='key' type='hidden' value='" . $this->searchText . "'>\n";
            echo "<INPUT id='maxresults' type='hidden' value='" . $this->maxresults . "'>\n";
            echo "</FORM>\n";
            echo "<TABLE WIDTH=\"100%\"><TR><TH ALIGN=LEFT CLASS=\"subhead\">$name's Album Reviews</TH></TR></TABLE><hr/>\n";
            echo "<TABLE CLASS=\"searchTable\" CELLPADDING=2 CELLSPACING=0 BORDER=0 id=\"results\"></TABLE>\n";
        } else
            echo "<H2>Unknown DJ</H2>\n";
    }
}
