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
        [ "byAlbum", "searchByAlbum" ],
        [ "byAlbumKey", "searchByAlbumKey" ],
        [ "byArtist", "searchByArtist" ],
        [ "byTrack", "searchByTrack" ],
        [ "byLabel", "searchByLabel" ],
        [ "byLabelKey", "searchByLabelKey" ],
        [ "byReviewer", "searchByReviewer" ],
    ];

    private $pos = 0;

    private $maxresults = 10;

    private $noTables = false;

    private $exactMatch = false;

    private $searchText;

    private $searchType;

    private $sortBy;
    
    public function processLocal($action, $subaction) {
        return $this->dispatchAction($action, self::$actions);
    }

    public function ftSearch() {
        UI::emitJS('js/zooscript.js');
        UI::emitJS('js/zootext.js');
?>
<SCRIPT TYPE="text/javascript" LANGUAGE="JavaScript"><!--
<?php ob_start([\JSMin::class, 'minify']); ?>
lists = [ <?php if($this->session->isAuth("u")) echo "\"Tags\", "; ?>"Albums", "Compilations", "Labels", "Playlists", "Reviews", "Tracks" ];

function onSearch(sync,e) {
   if(sync.Timer) {
      clearTimeout(sync.Timer);
      sync.Timer = null;
   }
   sync.Timer = setTimeout('onSearchNow()', 500);
}

function onSearchNow() {
   loadXMLDoc("zkapi.php?method=searchRq&size=5&key=" + urlEncode(document.forms[0].search.value) + "&session=<?php echo $this->session->getSessionID();?>");
}

function processReqChange(req) {
  if(req.readyState == 4) {
    // document loaded
    if (req.status == 200) {
      // success!
      var rs = req.responseXML.getElementsByTagName("searchRs")[0];
      var type = rs.getAttribute("type");
      if(type != '') {
        var method = type.substr(0,1).toUpperCase() + type.substr(1).toLowerCase();
        var items = req.responseXML.getElementsByTagName(type.toLowerCase());
        if(items && items[0])
          eval('emit' + method + '(getTable(type), items[0])');
        return;
      }
      clearSavedTable();
      document.getElementById("total").innerHTML = "(" + rs.getAttribute("total") + " total)";
      var results = document.getElementById("results");
      while(results.firstChild)
        results.removeChild(results.firstChild);
      for(var i=0; i<lists.length; i++) {
        var items = req.responseXML.getElementsByTagName(lists[i].toLowerCase());
        if(items && items[0])
          eval('emit' + lists[i] + '(emitTable(results, lists[i]), items[0])');
      }
      if(rs.getAttribute("total") == '0') {
        var search = document.forms[0].search.value;
        if(search.length < 4 ||
          search.match(/[\u2000-\u206F\u2E00-\u2E7F\\'!"#$%&()*+,\-.\/:;<=>?@\[\]^_`{|}~]/g) != null) {
          results.innerHTML = 'TIP: For short names or names with punctuation, try the <A HREF="?action=search&s=byArtist&n=' + urlEncode(search) + '&session=<?php echo $this->session->getSessionID();?>">Classic Search</A>.';
        }
      }
    } else {
      alert("There was a problem retrieving the XML data:\n" + req.statusText);
    }
  }
}

function setFocus() {
  document.forms[0].search.focus();
  var val = document.forms[0].search.value;
  if(val.length > 0) onSearchNow();
  document.forms[0].search.value = val;  // reset value to force cursor to end
}
    <?php
        ob_end_flush();
    ?>
// -->
</SCRIPT>
<?php
        $search = array_key_exists("search", $_REQUEST)?$_REQUEST["search"]:"";
        echo "<FORM ACTION=\"?\" METHOD=\"POST\">\n";
        echo "<P><B>Find It:</B>&nbsp;&nbsp;<INPUT TYPE=TEXT CLASS=text STYLE=\"width:214px;\" NAME=search VALUE=\"$search\" autocomplete=off onkeyup=\"onSearch(document.forms[0],event);\" onkeypress=\"return event.keyCode != 13;\">&nbsp;&nbsp;<SPAN ID=\"total\"></SPAN></P>\n";
        echo "<INPUT TYPE=HIDDEN NAME=action VALUE=\"find\">\n";
        echo "<INPUT TYPE=HIDDEN NAME=session VALUE=\"".$this->session->getSessionID()."\">\n";
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
        $this->searchForm("");
        echo "<P><B>Tip:  For a more extensive search, try ".
             "<A HREF=\"".
             "?session=".$this->session->getSessionID()."&amp;action=find\" CLASS=\"nav\">Find It!</A>\n";
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
    
    private function searchForm($title, $tag=0) {
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
    <FORM ACTION="?" METHOD=POST>
    <TABLE WIDTH="100%">
      <TR><TD>
        <TABLE CELLPADDING=2>
          <TR>
            <TD ALIGN=RIGHT><B>Search by:</B></TD>
            <TD><INPUT TYPE=RADIO NAME=s VALUE="byArtist"<?php echo $chkArtist;?>>Artist</TD>
            <TD><INPUT TYPE=RADIO NAME=s VALUE="byAlbum"<?php echo $chkAlbum;?>>Album</TD>
            <TD><INPUT TYPE=RADIO NAME=s VALUE="byTrack"<?php echo $chkTrack;?>>Track</TD>
            <TD><INPUT TYPE=RADIO NAME=s VALUE="byLabel"<?php echo $chkLabel;?>>Label</TD>
          </TR>
          <TR>
            <TD ALIGN=RIGHT><b>For:</b></TD>
            <TD COLSPAN=4><INPUT TYPE=TEXT NAME=n<?php echo $searchFor;?> SIZE=35 CLASS=input autocomplete=off></TD>
          </TR>
          <TR>
            <TD></TD>
            <TD><INPUT TYPE=SUBMIT VALUE="Search"></TD>
            <TD COLSPAN=2><INPUT TYPE=CHECKBOX NAME=m VALUE=1<?php echo $chkExact;?>>Exact match</TD>
            <!-- fix page size to 50 -->
            <TD><input type='hidden' name='q' value=50></input></TD>
          </TR>
        </TABLE>
      </TD></TR>
    </TABLE>
    <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
    <INPUT TYPE=HIDDEN NAME=action VALUE="search">
    </FORM>
    <BR>
    <?php 
        UI::setFocus("n");
    }
    
    private function outputAlbums($albums) {
        Engine::api(ILibrary::class)->markAlbumsReviewed($albums, $this->session->isAuth("u"));

        $opened = 0;
        for($i = 0; $i < sizeof($albums); $i++){
            if (! $opened ) {
                if($this->noTables)
                    echo "<PRE><B>Artist               Album                Coll    Medium  Label               </B>\n";
                else
                    echo "<TABLE class='searchTable' CELLPADDING=2><THEAD><TR><TH >Artist</TH><TH></TH><TH>Album</TH><TH>Collection</TH><TH COLSPAN=2>Media</TH><TH>Added</TH><TH>Label</TH></TR></THEAD>\n";
                $opened = 1;
            }
            $count++;
    
            // Artist
            if (!$this->noTables) {
                echo "  <TR><TD>";
                if(!$albums[$i]["artist"])
                    echo "&nbsp;";
            }
            if (preg_match("/^\[coll\]/i", $albums[$i]["artist"])) {
                // It's a collection; HREF the album key
                echo "<A HREF=\"".
                                "?s=byAlbumKey&amp;n=". UI::URLify($albums[$i]["tag"]).
                                "&amp;q=". $this->maxresults.
                                "&amp;action=search&amp;session=".$this->session->getSessionID().
                                "\">";
            } else {
                echo "<A HREF=\"".
                                "?s=byArtist&amp;n=". UI::URLify($albums[$i]["artist"]).
                                "&amp;q=". $this->maxresults.
                                "&amp;action=search&amp;session=".$this->session->getSessionID().
                                "\">";
            }
            echo $this->HTMLify($albums[$i]["artist"], 20) . "</A>";
            // Album
            if(!$this->noTables) {
                $divClass = $albums[$i]["reviewed"] ? "albumReview" : "albumNoReview";

                echo "</TD><TD style='padding: 0 0 0 6px'><div class='$divClass'></div></TD><TD>";
            }
            echo "<A HREF=\"".
                               "?s=byAlbumKey&amp;n=". UI::URLify($albums[$i]["tag"]).
                               "&amp;q=". $this->maxresults.
                               "&amp;action=search&amp;session=".$this->session->getSessionID().
                               "\">";
            echo $this->HTMLify($albums[$i]["album"], 20) . "</A>";
            if(!$this->noTables)
                echo "</TD><TD>";
            // Genre
            switch($albums[$i]["location"]) {
            case 'G':
                echo "<I>Deep&nbsp;Storage&nbsp;".$albums[$i]["bin"]."</I>";
                break;
            case 'M':
                echo "<I>Missing</I>";
                break;
            case 'E':
                echo "<I>Review Shelf</I>";
                break;
            case 'F':
                echo "<I>Out for Review</I>";
                break;
            case 'U':
                echo "<I>Deaccessioned</I>";
                break;
            default:
                echo $this->HTMLify(Search::GENRES[$albums[$i]["category"]], 7);
                break;
            }
            if(!$this->noTables)
                echo "</TD><TD>";
            // Medium & Length
            echo $this->HTMLify(Search::MEDIA[$albums[$i]["medium"]], 3);
            if(!$this->noTables)
                echo "</TD><TD>";
            echo $this->HTMLify(Search::LENGTHS[$albums[$i]["size"]], 3);
            if(!$this->noTables)
                echo "</TD><TD ALIGN=CENTER>";
            // Add Date
            list($year, $month, $day) = explode("-", $albums[$i]["created"]);
            if(!$this->noTables)
                echo $month. "/". substr($year, 2, 2). "</TD><TD>";
            // Label
            if ($albums[$i]["pubkey"] != 0) {
                $labelKey = $albums[$i]["pubkey"];
                echo "<A HREF=\"".
                                   "?s=byLabelKey&amp;n=". UI::URLify($labelKey).
                                   "&amp;q=". $this->maxresults.
                                   "&amp;action=search&amp;session=".$this->session->getSessionID().
                                   "\">";
    
                if(!$labelCache[$labelKey]) {
                    // Secondary search for label name
                    $labels = Engine::api(ILibrary::class)->search(ILibrary::LABEL_PUBKEY, 0, 1, $labelKey);
                    if(sizeof($labels))
                        $labelCache[$labelKey] = $labels[0]["name"];
                    else
                        $labelCache[$labelKey] = "(Unknown)";
                }
    
                echo $this->HTMLify($labelCache[$labelKey], 20). "</A>";
                if($this->noTables)
                    echo "\n";
                else
                    echo "</TD></TR>\n";
            } else {
                echo $this->HTMLify("Unknown", 20);
                if(!$this->noTables)
                    echo "</TD></TR>";
                echo "\n";
            }
        }
        if($opened && $this->pos>0) {
            echo $this->closeList();

            $m = $this->exactMatch?"&amp;m=1":"";
    
            echo "<P><A HREF=\"".
                                  "?s=$this->searchType&amp;n=". UI::URLify($this->searchText).
                                  "&amp;p=". $this->pos. $m.
                                  "&amp;q=". $this->maxresults.
                                  "&amp;action=search&amp;session=".$this->session->getSessionID().
                                  "\">[Next $this->maxresults albums &gt;&gt;]</A>\n";
            $closed = 1;
        }

        if ($opened) {
            if(!$closed)
                echo $this->closeList();
        } else {
            echo "<H2>No albums found</H2>\n";
            if($this->exactMatch)
                echo "Hint: Uncheck \"Exact match\" box to broaden search.";
        }
    }
    
    public function searchByAlbum() {
        $this->searchForm("Album Search Results");
        $search = $this->searchString();
        $albums = Engine::api(ILibrary::class)->searchPos(ILibrary::ALBUM_NAME, $this->pos, $this->maxresults, $search);
        $this->outputAlbums($albums);
    }        
    
    public function searchByArtist() {
        $this->searchForm("Artist Search Results");
        $search = $this->searchString();
        $albums = Engine::api(ILibrary::class)->searchPos(ILibrary::ALBUM_ARTIST, $this->pos, $this->maxresults, $search);
        $this->outputAlbums($albums);
    }
    
    private function reviewerColHeader($header, $static) {
        $command = $header;
        if(!strcmp($header, $this->sortBy)) {
            $command .= "-";
            $selected = 1;
        } else if(!strcmp($header . "-", $this->sortBy))
            $selected = 2;
    
        if($static)
            echo "  <TH ALIGN=LEFT$width><U>$header</U>";
        else
            echo "  <TH ALIGN=LEFT$width><A CLASS=\"nav\" HREF=\"?s=byReviewer&amp;n=".UI::URLify($this->searchText)."&amp;p=0&amp;q=15&amp;action=viewDJReviews&amp;session=".$this->session->getSessionID()."&amp;sortBy=$command\">$header</A>";
    
        if($selected && !$static)
            echo "&nbsp;<SPAN CLASS=\"sort" . (($selected==1)?"Down":"Up") . "\"><IMG SRC=\"img/blank.gif\" WIDTH=8 HEIGHT=4 ALT=\"\"></SPAN>";
    
        echo "</TH>\n";
    }
    
    private function reviewerAlbums($albums) {
        $opened = 0;
        for($i = 0; $i < sizeof($albums); $i++){
            if (! $opened ) {
                if($this->noTables)
                    echo "<PRE><B>Artist               Album                Label                Date Reviewed</B>\n";
                else {
                    echo "<TABLE CELLPADDING=2 CELLSPACING=0 BORDER=0>\n";
                    $static = 0;
                    $this->reviewerColHeader("Artist", $static);
                    $this->reviewerColHeader("Album", $static);
                    $this->reviewerColHeader("Label", $static);
                    $this->reviewerColHeader("Date Reviewed", $static);
                }
                $opened = 1;
            }
            $count++;
    
            // Artist
            if (!$this->noTables) {
                echo "  <TR><TD>";
                if(!$albums[$i]["artist"])
                    echo "&nbsp;";
            }
            if (preg_match("/^\[coll\]/i", $albums[$i]["artist"])) {
                // It's a collection; HREF the album key
                echo "<A HREF=\"".
                                "?s=byAlbumKey&amp;n=". UI::URLify($albums[$i]["tag"]).
                                "&amp;q=". $this->maxresults.
                                "&amp;action=search&amp;session=".$this->session->getSessionID().
                                "\">";
            } else {
                echo "<A HREF=\"".
                                "?s=byArtist&amp;n=". UI::URLify($albums[$i]["artist"]).
                                "&amp;q=". $this->maxresults.
                                "&amp;action=search&amp;session=".$this->session->getSessionID().
                                "\">";
            }
            echo $this->HTMLify($albums[$i]["artist"], 20) . "</A>";
    
            // Album
            if(!$this->noTables)
                echo "</TD><TD>";
            echo "<A HREF=\"".
                               "?s=byAlbumKey&amp;n=". UI::URLify($albums[$i]["tag"]).
                               "&amp;q=". $this->maxresults.
                               "&amp;action=search&amp;session=".$this->session->getSessionID().
                               "\">";
            echo $this->HTMLify($albums[$i]["album"], 20) . "</A>";
            if($this->session->isAuth("u"))
                echo " <FONT CLASS=\"sub\">(Tag&nbsp;#". $albums[$i]["tag"] .")</FONT>";
            if(!$this->noTables)
                echo "</TD><TD>";
            // Label
            if ($albums[$i]["pubkey"] != 0) {
                $labelKey = $albums[$i]["pubkey"];
                echo "<A HREF=\"".
                                   "?s=byLabelKey&amp;n=". UI::URLify($labelKey).
                                   "&amp;q=". $this->maxresults.
                                   "&amp;action=search&amp;session=".$this->session->getSessionID().
                                   "\">";
    
                echo $this->HTMLify($albums[$i]["name"], 20). "</A>";
            } else {
                echo $this->HTMLify("Unknown", 20);
            }
    
            if(!$this->noTables)
                echo "</TD><TD>";
            // Review date
            echo substr($albums[$i]["reviewed"], 0, 10);
            if($this->noTables)
                echo "\n";
            else
                echo "</TD></TR>\n";
        }

        if($opened && $this->pos>0) {
            echo $this->closeList();

            $m = $this->exactMatch?"&amp;m=1":"";
    
            echo "<P><A HREF=\"".
                                  "?s=$this->searchType&amp;n=". UI::URLify($this->searchText).
                                  "&amp;p=". $this->pos. $m.
                                  "&amp;q=". $this->maxresults.
                                  "&amp;action=viewDJReviews&amp;session=".$this->session->getSessionID().
                                  "&amp;sortBy=$this->sortBy".
                                  "\">[Next $this->maxresults albums &gt;&gt;]</A>\n";
            $closed = 1;
        }
    
        if ($opened) {
            if(!$closed)
                echo $this->closeList();
        } else {
            echo "<H2>No albums found</H2>\n";
        }
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
            echo "<TABLE WIDTH=\"100%\"><TR><TH ALIGN=LEFT CLASS=\"subhead\">$name's Album Reviews</TH></TR></TABLE>\n";
            $albums = Engine::api(ILibrary::class)->searchPos(ILibrary::ALBUM_AIRNAME, $this->pos, $this->maxresults, $this->searchText, $this->sortBy);
            $this->reviewerAlbums($albums);
        }
    }
    
    public function searchByTrack() {
        $libraryAPI = Engine::api(ILibrary::class);
    
        $this->searchForm("Track Search Results");
    
        $search = $this->searchString();
        $tracks = $libraryAPI->searchPos(ILibrary::TRACK_NAME, $this->pos, $this->maxresults, $search);
    
        $libraryAPI->markAlbumsReviewed($tracks, $this->session->isAuth("u"));

        $opened = 0;
        for($i=0; $i < sizeof($tracks); $i++) {
            if (! $opened) {
                if($this->noTables)
                    # 20 20 20 7 7
                    echo "<PRE><B>Artist               Album                Track Name           Coll    Medium  </B>\n";
                else
                    echo "<TABLE class='searchTable' CELLPADDING=2><THEAD><TR><TH>Artist</TH><TH></TH><TH>Album</TH><TH>Track</TH><TH>Collection</TH><TH COLSPAN=2>Media</TH><TH>Label</TH></TR></THEAD>\n";
                $opened = 1;
            }
            $count++;
            $trackName = $tracks[$i]["track"];
            $albumName = $tracks[$i]["album"];
            $artistName = $tracks[$i]["artist"];
    
            // Secondary search for album
            $album = $libraryAPI->search(ILibrary::ALBUM_KEY, 0, 1, $tracks[$i]["tag"]);
            if(sizeof($album)) {
                // Artist
                if(!$this->noTables)
                    echo "  <TR><TD>";
                if(!($artistName || $this->noTables))
                    echo "&nbsp;";
                echo "<A HREF=\"".
                                "?s=byArtist&amp;n=". UI::URLify($artistName).
                                "&amp;action=search&amp;session=".$this->session->getSessionID().
                                "&amp;q=". $this->maxresults.
                                "\">";
                echo $this->HTMLify($artistName, 20), "</A>";
                // Album
                if(!$this->noTables) {
                    $divClass = $albums[$i]["reviewed"] ? "albumReview" : "albumNoReview";

                    echo "</TD><TD style='padding: 0 0 0 6px'><div class='$divClass'></div></TD><TD>";
                }
                echo "<A HREF=\"".
                               "?s=byAlbumKey&amp;n=". UI::URLify($album[0]["tag"]).
                               "&amp;q=". $this->maxresults.
                               "&amp;action=search&amp;session=".$this->session->getSessionID().
                               "\">";
                echo $this->HTMLify($albumName, 20). "</A>";
                if(!$this->noTables)
                    echo "</TD><TD>";
                // Track Name
                echo $this->HTMLify($trackName, 20);
                if(!$this->noTables)
                    echo "</TD><TD>";
                // Genre
                switch($album[0]["location"]) {
                case 'G':
                    echo "<I>Deep&nbsp;Storage&nbsp;".$album[0]["bin"]."</I>";
                    break;
                case 'M':
                    echo "<I>Missing</I>";
                    break;
                case 'E':
                    echo "<I>Review Shelf</I>";
                    break;
                case 'F':
                    echo "<I>Out for Review</I>";
                    break;
                case 'U':
                    echo "<I>Deaccessioned</I>";
                    break;
                default:
                    echo $this->HTMLify(Search::GENRES[$album[0]["category"]], 7);
                    break;
                }
                if(!$this->noTables)
                    echo "</TD><TD>";
                // Medium & Length
                echo $this->HTMLify(Search::MEDIA[$album[0]["medium"]], 3);
                if(!$this->noTables)
                    echo "</TD><TD>";
                echo $this->HTMLify(Search::LENGTHS[$album[0]["size"]], 3);
                if(!$this->noTables)
                    echo "</TD><TD>";
                // Label
                if (($album[0]["pubkey"] != 0) && !$this->noTables) {
                    $labelKey = $album[0]["pubkey"];
                    echo "<A HREF=\"".
                                   "?s=byLabelKey&amp;n=". UI::URLify($labelKey).
                                   "&amp;q=". $this->maxresults.
                                   "&amp;action=search&amp;session=".$this->session->getSessionID().
                                   "\">";
                    if(!$labelCache[$labelKey]) {
                        // Tertiary search for label name
                        $label = $libraryAPI->search(ILibrary::LABEL_PUBKEY, 0, 1, $labelKey);
                        if(sizeof($label))
                            $labelCache[$labelKey] = $label[0]["name"];
                        else
                            $labelCache[$labelKey] = "(Unknown)";
                    }
                    echo $this->HTMLify($labelCache[$labelKey], 20). "</A></TD></TR>\n";
                } else {
                    if(!$this->noTables)
                        echo "Unknown</TD></TR>";
                    echo "\n";
                }
            }
        }
        if($opened && $this->pos>0) {
            echo $this->closeList();

            $m = $this->exactMatch?"&amp;m=1":"";
    
            echo "<P><A HREF=\"".
                                  "?s=byTrack&amp;n=". UI::URLify($this->searchText).
                                  "&amp;p=". $this->pos. $m.
                                  "&amp;q=". $this->maxresults.
                                  "&amp;action=search&amp;session=".$this->session->getSessionID().
                                  "\">[Next $this->maxresults albums &gt;&gt;]</A>\n";
            $closed = 1;
        }
    
        if ($opened) {
            if(!$closed)
                echo $this->closeList();
        } else {
            echo "<H2>No tracks found</H2>\n";
            if($this->exactMatch)
                echo "Hint: Uncheck \"Exact match\" box to broaden search.";
        }
    }
    
    public function searchByLabel() {
        $this->searchForm("Label Search Results");
        $search = $this->searchString();
        $labels = Engine::api(ILibrary::class)->searchPos(ILibrary::LABEL_NAME, $this->pos, $this->maxresults, $search);
        $opened = 0;
        for($i=0; $i < sizeof($labels); $i++) {
            if (! $opened ) {
                if($this->noTables)
                    # 20 20 12
                    echo "<PRE><B>Name                 Location             Last Updated</B>\n";
                else
                    echo "<TABLE class='searchTable' CELLPADDING=2><THEAD><TR><TH>Name</TH><TH COLSPAN=2>Location</TH><TH>Last Updated</TH></TR></THEAD>\n";
                $opened = 1;
            }
            // Name
            if(!$this->noTables) {
                echo "  <TR><TD>";
                if(!$labels[$i]["name"])
                    echo "&nbsp;";
            }
            echo "<A HREF=\"".
                            "?s=byLabelKey&amp;n=". UI::URLify($labels[$i]["pubkey"]).
                            "&amp;q=". $this->maxresults.
                            "&amp;action=search&amp;session=".$this->session->getSessionID().
                            "\">";
            echo $this->HTMLify($labels[$i]["name"], 20). "</A>";
            if(!$this->noTables)
                echo "</TD><TD>";
            // City
            if(!(strlen($labels[$i]["city"]) || $this->noTables))
                echo "&nbsp;";
            echo $this->HTMLify($labels[$i]["city"], 15);
            if(!$this->noTables)
                echo "</TD><TD>";
            if (preg_match("/t/i", $labels[$i]["international"])) {
                // Foreign label
                //
                // Country
                if(!(strlen($labels[$i]["zip"]) || $this->noTables))
                    echo "&nbsp;";
                echo $this->HTMLify($labels[$i]["zip"], 4);
                if(!$this->noTables);
                    echo "</TD><TD>";
            } else {
                // Domestic label
                //
                // State
                if(!(strlen($labels[$i]["state"]) || $this->noTables))
                    echo "&nbsp;";
                echo $this->HTMLify($labels[$i]["state"], 4);
                if(!$this->noTables)
                    echo "</TD><TD>";
            }
            // Last Update
               if(!(strlen($labels[$i]["modified"]) || $this->noTables))
                echo "&nbsp;";
            echo $this->HTMLify($labels[$i]["modified"], 12);
            if(!$this->noTables)
                echo "</TD></TR>";
            echo "\n";
        }
        if($opened && $this->pos>0) {
            echo $this->closeList();

            $m = $this->exactMatch?"&amp;m=1":"";

            echo "<P><A HREF=\"".
                              "?s=byLabel&amp;n=". UI::URLify($this->searchText).
                              "&amp;p=". $this->pos. $m.
                              "&amp;q=". $this->maxresults.
                              "&amp;action=search&amp;session=".$this->session->getSessionID().
                              "\">[Next $this->maxresults labels &gt;&gt;]</A>\n";
            $closed = 1;
        }
    
        if ($opened) {
            if(!$closed)
                echo $this->closeList();
        } else {
            echo "<H2>No labels found</H2>\n";
            if($this->exactMatch)
                echo "Hint: Uncheck \"Exact match\" box to broaden search.";
        }
    }
    
    public function searchByLabelKey() {
        $this->searchForm("Label Search Results");
        $albums = Engine::api(ILibrary::class)->searchPos(ILibrary::ALBUM_PUBKEY, $this->pos, $this->maxresults, $this->searchText);
        $this->outputAlbums($albums);
    }
}
