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
use ZK\Engine\ILibrary;
use ZK\Engine\IPlaylist;

class ExportPlaylist extends CommandTarget implements IController {
    private static $actions = [
        [ "html", "emitHTML" ],
        [ "xml", "emitXML" ],
        [ "csv", "emitCSV" ],
    ];

    private $dj;
    private $show;
    private $date;
    private $time;
    private $records;

    public function processRequest($dispatcher) {
        // Ensure user has selected a playlist
        $playlist = intval($_REQUEST["playlist"]);
        if($playlist == 0)
            exit;
        
        // Get the show and DJ information
        $row = Engine::api(IPlaylist::class)->getPlaylist($playlist, 1);
        if($row) {
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

    public function emitXML() {
        header("Content-type: application/xml");
        header("Content-disposition: attachment; filename=playlist.xml");
    
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        if(!$this->records)
            return;
        echo "<playlist>\n";
        echo "  <show name=\"$this->show\"\n";
        echo "        dj=\"".$this->dj."\"\n";
        echo "        date=\"$this->date\"/>\n";
    
        $i=1;
        while($row = $this->records->fetch()) {
            if(substr($row['artist'], 0, strlen(IPlaylist::SPECIAL_TRACK)) == IPlaylist::SPECIAL_TRACK)
                echo "  <break/>\n";
            else {
                echo "  <track>\n";
                if($row['artist'] != "")
                    echo "    <artist name=\"".htmlspecialchars($row['artist'])."\"/>\n";
                if($row['track'] != "")
                    echo "    <trackname name=\"".htmlspecialchars($row['track'])."\"/>\n";
                if($row['album'] != "")
                    echo "    <album name=\"".htmlspecialchars($row['album'])."\"/>\n";
                if($row['label'] != "")
                    echo "    <label name=\"".htmlspecialchars($row['label'])."\"/>\n";
                if($row['tag'] != "")
                    echo "    <tag number=\"".$row['tag']."\"/>\n";
                echo "    <sequence number=\"".$i++."\"/>\n";
                echo "  </track>\n";
            }
        }
        echo "</playlist>\n";
    }
    
    public function emitCSV() {
        header("Content-type: application/csv");
        header("Content-disposition: attachment; filename=playlist.csv");
    
        if(!$this->records)
            return;
    
        // emit the show info
        echo "Show\n$this->show\n\n";
        echo "DJ\n$this->dj\n\n";
        echo "Date\n$this->date\n\n";
    
        // emit the tracks
        echo "Artist\tTrack\tAlbum\tTag\tLabel\n";
        while($row = $this->records->fetch()) {
            if(substr($row['artist'], 0, strlen(IPlaylist::SPECIAL_TRACK)) != IPlaylist::SPECIAL_TRACK)
                echo $row['artist']."\t".$row['track']."\t".$row['album']."\t".$row['tag']."\t".$row['label']."\n";
        }
    }
    
    public function emitHTML() {
        list($y,$m,$d) = split("-", $this->date);
        $displayDate = date("D, j M Y", mktime(0,0,0,$m,$d,$y));
    ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<HTML>
<HEAD>
<TITLE><?echo "Zookeeper playlist for $this->show, $displayDate";?></TITLE>
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
<?
        if(!$this->records) {
            echo "<B>Sorry, the playlist you have requested does not exist.</B>\n";
            echo "</BODY>\n</HTML>\n";
            return;
        }
    ?>
    <TABLE WIDTH="100%" BORDER=0 CELLPADDING=2 CELLSPACING=2>
    <TR><TD ALIGN=CENTER>
      <TABLE WIDTH="90%" CELLPADDING=2 CELLSPACING=2>
    <?
        echo "    <TR><TD VALIGN=BOTTOM ALIGN=LEFT><H3>$this->show</H3></TD>\n";
        echo "        <TD VALIGN=BOTTOM ALIGN=RIGHT><H3>DJ: ".$this->dj."</H3></TD>\n";
        echo "        <TD VALIGN=BOTTOM ALIGN=RIGHT><H3>$displayDate / $this->time</H3></TD></TR>\n";
    ?>
      </TABLE>
    </TD></TR>
    <TR><TD ALIGN=CENTER>
      <TABLE CELLPADDING=2 WIDTH="90%">
        <TR><TH>Artist</TH><TH>Track</TH><TH COLSPAN=2>Album/Label</TH></TR>
    <?
        // Print the tracks
        while($row = $this->records->fetch()) {
            if(substr($row['artist'], 0, strlen(IPlaylist::SPECIAL_TRACK)) == IPlaylist::SPECIAL_TRACK)
                echo "    <TR><TD COLSPAN=4><HR SIZE=2 NOSHADE></TD></TR>\n";
            else {
                echo "    <TR><TD ALIGN=LEFT VALIGN=TOP>".htmlentities($row['artist']) . "</TD><TD ALIGN=LEFT VALIGN=TOP>" .
                     htmlentities($row['track']). "</TD><TD ALIGN=LEFT>" .
                     htmlentities($row['album']). "<BR><FONT CLASS=\"sub\">" .
                     htmlentities($row['label']). "</FONT></TD><TD ALIGN=RIGHT VALIGN=TOP>";
                if($this->session->isAuth("u"))
                     echo htmlentities($row['tag']);
                echo "</TD></TR>\n";
            }
        }
    ?>
      </TABLE>
    </TD></TR>
    </TABLE>
</BODY>
</HTML>
    <?
    }
}
