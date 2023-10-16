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

namespace ZK\Controllers;

use ZK\Engine\Engine;
use ZK\Engine\ILibrary;
use ZK\Engine\IPlaylist;
use ZK\Engine\PlaylistEntry;
use ZK\Engine\PlaylistObserver;

use ZK\UI\Playlists;
use ZK\UI\UICommon as UI;

class ExportPlaylist extends CommandTarget implements IController {
    private static $actions = [
        [ "html", "emitHTML" ],
        [ "xml", "emitXML" ],
        [ "csv", "emitCSV" ],
        [ "json", "emitJSON" ],
    ];

    private $dj;
    private $airname;
    private $user;
    private $show;
    private $date;
    private $time;
    private $records;

    public function processRequest() {
        // Ensure user has selected a playlist
        $playlist = intval($_REQUEST["playlist"]);
        if($playlist == 0) {
            $referer = $_SERVER["HTTP_REFERER"];
            if($referer)
                header("Location: $referer");
            exit;
        }
        
        // Get the show and DJ information
        $row = Engine::api(IPlaylist::class)->getPlaylist($playlist, 1);
        if($row) {
            $this->airname = $row[4];
            $this->user = $row[5];
            if($row[4]) {
                $this->dj = $row[4];
            } else {
                $user = Engine::api(ILibrary::class)->search(ILibrary::PASSWD_NAME, 0, 1, $row[5]);
                $this->dj = $user[0]["realname"];
            }
        
            $this->show = $row[0];
            $this->date = $row[1];
            $this->time = $row[2];

            // Run the query to get the tracks
            $this->records = Engine::api(IPlaylist::class)->getTracks($playlist);
        }

        // Emit the result in the requested format
        $this->process($_REQUEST["format"], null, Engine::session());
    }

    public function processLocal($action, $subaction) {
        $this->dispatchAction($action, self::$actions);
    }

    public function emitJSON() {
        header("Location: ".Engine::getBaseUrl().
               "api/v1/playlist/".$_REQUEST["playlist"]);
    }

    public function emitXML() {
        header("Content-type: application/xml");
        header("Content-disposition: attachment; filename=playlist.xml");
    
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        if(!$this->records ||
                !$this->airname && $this->user != $this->session->getUser())
            return;
        echo "<playlist>\n";
        echo "  <show name=\"$this->show\"\n";
        echo "        dj=\"".$this->dj."\"\n";
        echo "        date=\"$this->date\"/>\n";

        $observer = (new PlaylistObserver())->onComment(function($entry) use(&$break) {
            echo "  <comment>".htmlspecialchars(stripslashes($entry->getComment()))."</comment>\n";
            $break = false;
        })->onLogEvent(function($entry) use(&$break) {
            if(!$break) {
                echo "  <break/>\n";
                $break = true;
            }
        })->onSetSeparator(function($entry) use(&$break) {
            if(!$break) {
                echo "  <break/>\n";
                $break = true;
            }
        })->onSpin(function($entry) use(&$break, &$i) {
            echo "  <track>\n";
            $artist = $entry->getArtist();
            if($artist)
                echo "    <artist name=\"".htmlspecialchars(stripslashes($artist))."\"/>\n";
            $track = $entry->getTrack();
            if($track)
                echo "    <track name=\"".htmlspecialchars(stripslashes($track))."\"/>\n";
            $album = $entry->getAlbum();
            if($album)
                echo "    <album name=\"".htmlspecialchars(stripslashes($album))."\"/>\n";
            $label = $entry->getLabel();
            if($label)
                echo "    <label name=\"".htmlspecialchars(stripslashes($label))."\"/>\n";
            $tag = $entry->getTag();
            if($tag)
                echo "    <tag number=\"$tag\"/>\n";
            echo "    <sequence number=\"".$i++."\"/>\n";
            echo "  </track>\n";
            $break = false;
        });

        $i=1;
        $break = false;
        while($row = $this->records->fetch()) {
            $observer->observe(new PlaylistEntry($row));
        }
        echo "</playlist>\n";
    }

    /**
     * extract the time component of a datetime string
     *
     * @param datetime string in IPlaylist::TIME_FORMAT_SQL format
     * @return time component on success; original input value otherwise
     */
    public static function extractTime($datetime) {
        // yyyy-mm-dd hh:mm:ss
        if(strlen($datetime) == 19)
            $datetime = substr($datetime, 11, 8);
        return $datetime;
    }

    public function emitCSV() {
        header("Content-type: application/csv");
        header("Content-disposition: attachment; filename=playlist.csv");
    
        if(!$this->records ||
                !$this->airname && $this->user != $this->session->getUser())
            return;
    
        // emit the show info
        echo "Show\n$this->show\n\n";
        echo "DJ\n$this->dj\n\n";
        echo "Date\n$this->date\n\n";
    
        // emit the tracks
        echo "Artist\tTrack\tAlbum\tTag\tLabel\tTimestamp\n";
        $observer = (new PlaylistObserver())->onSpin(function($entry) {
            echo ($entry->getTag() ? PlaylistEntry::swapNames($entry->getArtist()) : $entry->getArtist())."\t".
                 $entry->getTrack()."\t".
                 $entry->getAlbum()."\t".
                 $entry->getTag()."\t".
                 $entry->getLabel()."\t".
                 self::extractTime($entry->getCreated())."\n";
        });
        while($row = $this->records->fetch()) {
            $observer->observe(new PlaylistEntry($row));
        }
    }
    
    public function emitHTML() {
        list($y,$m,$d) = explode("-", $this->date);
        $usLocale = UI::getClientLocale() == 'en_US';
        $dateSpec = $usLocale ? 'D M d, Y ' : 'D d M Y ';
        $displayDate = date($dateSpec, mktime(0,0,0,$m,$d,$y));
        $displayTime = Playlists::timeToLocale($this->time);
    ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<HTML>
<HEAD>
<TITLE><?php echo "Zookeeper playlist for $this->show, $displayDate";?></TITLE>
<STYLE><!--
  TD { font-family: verdana, arial, helvetica, sans-serif;
       font-size: 11px; }
  TH { font-family: verdana, arial, helvetica, sans-serif;
       font-size: 11px;
       background: #cccccc; }
  .sub { font-family: verdana, arial, helvetica, sans-serif;
       font-size: 10px; }
-->
</STYLE>
</HEAD>
<BODY BGCOLOR="#ffffff">
<?php 
        if(!$this->records ||
                !$this->airname && $this->user != $this->session->getUser()) {
            echo "<B>Sorry, the playlist you have requested does not exist.</B>\n";
            echo "</BODY>\n</HTML>\n";
            return;
        }
    ?>
    <TABLE WIDTH="100%" BORDER=0 CELLPADDING=2 CELLSPACING=2>
    <TR><TD ALIGN=CENTER>
      <TABLE WIDTH="90%" CELLPADDING=2 CELLSPACING=2>
    <?php 
        echo "    <TR><TD VALIGN=BOTTOM ALIGN=LEFT><H3>$this->show</H3></TD>\n";
        echo "        <TD VALIGN=BOTTOM ALIGN=RIGHT><H3>DJ: ".$this->dj."</H3></TD>\n";
        echo "        <TD VALIGN=BOTTOM ALIGN=RIGHT><H3>$displayDate / $displayTime</H3></TD></TR>\n";
    ?>
      </TABLE>
    </TD></TR>
    <TR><TD ALIGN=CENTER>
      <TABLE CELLPADDING=2 WIDTH="90%">
        <TR><TH>Artist</TH><TH>Track</TH><TH COLSPAN=2>Album/Label</TH></TR>
    <?php 
        // Print the tracks
        $observer = (new PlaylistObserver())->onComment(function($entry) use(&$break) {
            echo "    <TR><TD COLSPAN=4>".UI::markdown($entry->getComment())."</TD></TR>\n";
            $break = false;
        })->onLogEvent(function($entry) use(&$break) {
            if(!$break) {
                echo "    <TR><TD COLSPAN=4><HR SIZE=2 NOSHADE></TD></TR>\n";
                $break = true;
            }
        })->onSetSeparator(function($entry) use(&$break) {
            if(!$break) {
                echo "    <TR><TD COLSPAN=4><HR SIZE=2 NOSHADE></TD></TR>\n";
                $break = true;
            }
        })->onSpin(function($entry) use(&$break) {
            echo "    <TR><TD ALIGN=LEFT VALIGN=TOP>".htmlentities($entry->getTag() ? PlaylistEntry::swapNames($entry->getArtist()) : $entry->getArtist()) . "</TD><TD ALIGN=LEFT VALIGN=TOP>" .
                 htmlentities($entry->getTrack()). "</TD><TD ALIGN=LEFT>" .
                 htmlentities($entry->getAlbum()). "<BR><FONT CLASS=\"sub\">" .
                 htmlentities($entry->getLabel()). "</FONT></TD><TD ALIGN=RIGHT VALIGN=TOP>";
            if($this->session->isAuth("u"))
                 echo htmlentities($entry->getTag());
            echo "</TD></TR>\n";
            $break = false;
        });

        $break = false;
        while($row = $this->records->fetch()) {
            $observer->observe(new PlaylistEntry($row));
        }
    ?>
      </TABLE>
    </TD></TR>
    </TABLE>
</BODY>
</HTML>
    <?php
    }
}
