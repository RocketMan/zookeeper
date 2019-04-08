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

namespace ZK\UI;

use ZK\Engine\Engine;
use ZK\Engine\IDJ;
use ZK\Engine\IReview;
use ZK\Engine\ILibrary;

use ZK\UI\UICommon as UI;

class Reviews extends MenuItem {
    private static $actions = [
        [ "viewRecent", "viewRecentReviews" ],
        [ "viewRecentReview", "viewReview" ],
        [ "searchReviewView", "viewReview" ],
        [ "searchReviewEdit", "editReview" ],
    ];

    public function processLocal($action, $subaction) {
        return $this->dispatchAction($action, self::$actions);
    }

    public function emitReviewHook() {
        $tag = $_REQUEST["n"];
        if($this->session->isAuth("u"))
            echo "<A HREF=\"?session=".$this->session->getSessionID()."&amp;action=searchReviewEdit&amp;tag=$tag\" CLASS=\"nav\"><B>Write a review of this album</B></A>";
    }
    
    private function emitReviewRow($row, $album) {
        // Album
        echo "  <TR CLASS=\"hborder\"><TD>";
        echo "<A HREF=\"".
                           "?session=".$this->session->getSessionID()."&amp;action=viewRecentReview&amp;tag=$row[0]\">";
        echo htmlentities($album[0]["album"]);
        echo "</A></TD><TD>";
    
        // Artist
        if (preg_match("/^\[coll\]/i", $album[0]["artist"]))
            echo "Various Artists";
        else
            echo htmlentities($album[0]["artist"]);
        echo "</TD><TD>";
    
        // Genre
        echo htmlentities(Search::GENRES[$album[0]["category"]]);
        echo "</TD><TD>";
    
        // Reviewer
        if($row[1])
            $djname = $row[1];
        else {
            $djs = Engine::api(ILibrary::class)->search(ILibrary::PASSWD_NAME, 0, 1, $row[2]);
            $djname = $djs[0]["realname"];
        }
        echo htmlentities($djname);
    
        // Date
        echo "</TD></TR>\n";
    }
    
    public function viewRecentReviews() {
        echo "<TABLE CELLSPACING=0 WIDTH=\"100%\">\n";
        echo "  <TR><TH ALIGN=LEFT CLASS=\"subhead\">Albums Reviewed by ".Engine::param('station')." DJs <SPAN CLASS=\"subhead2\">during the last 2 weeks</SPAN></TH><TH ALIGN=RIGHT CLASS=\"sub\"><B>Review Feed:</B> <A TYPE=\"application/rss+xml\" HREF=\"zkrss.php?feed=reviews\"><IMG SRC=\"img/rss.gif\" ALIGN=MIDDLE WIDTH=36 HEIGHT=14 BORDER=0 ALT=\"rss\"></A></TH></TR>\n";
        echo "</TABLE>\n<TABLE WIDTH=\"100%\">\n";
        echo "  <TR><TH ALIGN=LEFT>Album</TH><TH ALIGN=LEFT>Artist</TH><TH ALIGN=LEFT>Collection</TH><TH ALIGN=LEFT>Reviewed by</TH></TR>\n";
        $results = Engine::api(IReview::class)->getRecentReviews("", 2, 0, $this->session->isAuth("u"));
        $libAPI = Engine::api(ILibrary::class);
        while($results && ($row = $results->fetch())) {
            $albums = $libAPI->search(ILibrary::ALBUM_KEY, 0, 1, $row[0]);
            $this->emitReviewRow($row, $albums);
        }
        if($this->session->isAuth("u")) {
            $results = Engine::api(IReview::class)->getRecentReviews($this->session->getUser(), 0, 15, 1);
            echo "  <TR><TD COLSPAN=5>&nbsp;</TD></TR>\n";
            echo "<TR><TH COLSPAN=5 CLASS=\"subhead\" ALIGN=LEFT>Your Most Recent Reviews</TH></TR>\n";
            echo "  <TR><TH ALIGN=LEFT>Album</TH><TH ALIGN=LEFT>Artist</TH><TH ALIGN=LEFT>Collection</TH><TH ALIGN=LEFT>Reviewed by</TH><TH>&nbsp;</TH></TR>\n";
            while($results && ($row = $results->fetch())) {
                $albums = $libAPI->search(ILibrary::ALBUM_KEY, 0, 1, $row[0]);
                $this->emitReviewRow($row, $albums);
            }
        }
        echo "</TABLE>\n";
        UI::setFocus();
    }
    
    public function viewReview() {
        $_REQUEST["n"] = $_REQUEST["tag"];
        $this->newEntity(Search::class)->searchByAlbumKey();
    }
    
    public function viewReview2() {
        $tag = $_REQUEST["n"];
        $records = Engine::api(IReview::class)->getReviews($tag, 1, "", $this->session->isAuth("u"));
    
        if($count = sizeof($records)) {
            $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $tag);
            echo "<TABLE WIDTH=\"100%\">\n";
            echo "  <TR><TH ALIGN=LEFT CLASS=\"secdiv\">Album Review" . ($count>1?"s":"") ."</TH>";
            echo "</TR>\n</TABLE>\n";
            $space = 0;
    
            echo "<TABLE CELLPADDING=2 CELLSPACING=2 WIDTH=\"100%\">\n";
            foreach($records as $row) {
                if($row[5])
                    $djname = $row[5];
                else {
                    $djs = Engine::api(ILibrary::class)->search(ILibrary::PASSWD_NAME, 0, 1, $row[4]);
                    $djname = $djs[0]["realname"];
                }
                if($space++)
                    echo "  <TR><TD COLSPAN=3>&nbsp;</TD></TR>\n";
    
                echo "  <TR><TD><B>".htmlentities($djname)."</B><BR>\n";
                echo "      <SPAN CLASS=\"sub\">Reviewed " .
                        substr($row[1], 0, 10);
                echo $row[3]?" <FONT COLOR=\"#cc0000\">(private)</FONT>":"&nbsp;";
                echo "</SPAN></TD></TR>\n";
    
                if($this->session->getUser() == $row[4])
                    echo "  <TR COLSPAN=3><TD><FONT SIZE=-1><A HREF=\"?session=".$this->session->getSessionID()."&amp;action=searchReviewEdit&amp;tag=$tag\">[This is my review and I want to edit it]</A></FONT></TD></TR>\n";
                echo "  <TR><TD COLSPAN=3 CLASS=\"review\">\n";
                echo nl2br(htmlentities($row[2]));
                echo "\n  </TD></TR>\n";
            }
    
            echo "</TABLE><BR>\n";
        }
    }
    
    private function eMailReview($tag, $airname, $review) {
        $instance_nobody = Engine::param('email')['nobody'];
        $address = Engine::param('email')['reviewlist'];

        if(!isset($address)) {
            echo "  <B><FONT COLOR=\"#cc0000\">Noise e-mail not configured.</FONT></B>\n";
            return;
        }
    
        $djAPI = Engine::api(IDJ::class);
        $libAPI = Engine::api(ILibrary::class);
        $revAPI = Engine::api(IReview::class);
        $records = $revAPI->getReviews($tag, 1, "", 1);
        if(sizeof($records) && ($row = $records[0])) {
            if($airname) {
                $airnames = $djAPI->getAirnames($airname);
                while ($arow = $airnames->fetch()) {
                    $name = $arow["airname"];
                    $email = $arow["email"];
                }
            }
        
            if(!$name) {
                $djs = $libAPI->search(ILibrary::PASSWD_NAME, 0, 1, $this->session->getUser());
                $name = $djs[0]["realname"];
            }
    
            // JM 2018-03-16 for now, force all from nobody
            //if(!$email)
                $email = $instance_nobody;
       
            $from = "$name <$email>";
    
            $albums = $libAPI->search(ILibrary::ALBUM_KEY, 0, 1, $tag);
            $artist = strcmp(substr($albums[0]["artist"], 0, 8), "[coll]: ")?
                          UI::deLatin1ify($albums[0]["artist"]):"Various Artists";
            $album = UI::deLatin1ify($albums[0]["album"]);
    
            $subject = "Review: $artist / $album";
    
            // Setup the headers
            $headers = "From: $from\r\n";
    
            // Emit the review
            $body = "$artist / $album\r\n";
    
            $label = $libAPI->search(ILibrary::LABEL_PUBKEY, 0, 1, $albums[0]["pubkey"]);
    
            if(sizeof($label)) {
                $labeln = str_replace(" Records", "", $label[0]["name"]);
                $labeln = str_replace(" Recordings", "", $labeln);
                $body .= "Label: " . UI::deLatin1ify($labeln) . "\r\n";
            } else
                $body .= "Label: (Unknown)\r\n";
    
            $body .= "\n$name\nReviewed " . substr($row[1], 0, 10) . "\r\n\r\n";
    
            $review = $row[2];
            $review = str_replace("\241", "!", $review);
            $review = str_replace("\223", "\"", $review);  // ldquot
            $review = str_replace("\224", "\"", $review);  // rdquot
            $review = str_replace("\205", "...", $review);
            $review = str_replace("\226", "-", $review);   // en dash
            $review = str_replace("\227", "--", $review);   // em dash
            $review = str_replace("\221", "'", $review);   // lsquot
            $review = str_replace("\222", "'", $review);        // rsquot
    
            $body .= \WordWrap::word_wrap($review);
    
            // Emit the postamble
            $body .= "\r\n\r\n--\r\nPost your music reviews online!\r\n";
            $body .= Engine::param('station')." Zookeeper Online:  ".UI::getBaseUrl()."\r\n";
    
            // send the mail
            $stat = mail($address, $subject, $body, $headers);
    
            // Check for errors
            if(!$stat) {
                echo "  <B><FONT COLOR=\"#cc0000\">Possible Problem Sending E-Mail</FONT></B><BR>\n";
                echo "There may have been a problem sending your e-mail.  ";
                echo "</P>\n";
                //echo "The mailer reports the following error:<BR>\n  <PRE>\n";
                //echo error_get_last()['message'];
                //echo "\n</PRE></FONT></B>\n";
            } else {
                echo "  <B><FONT COLOR=\"#ffcc33\">E-Mail Sent!</FONT></B><BR>\n";
            }
        }
    }
    
    public function editReview() {
        $airname = $_REQUEST["airname"];
    
        if($_REQUEST["validate"]) {
            switch($_REQUEST["button"]) {
            case " Setup New Airname... ":
                $displayForm = 1;
                $djname = trim($_REQUEST["djname"]);
                if($_REQUEST["newairname"] == " Add Airname " && $djname) {
                    // Insert new airname
                    $success = Engine::api(IDJ::class)->insertAirname($djname, $this->session->getUser());
                    if($success > 0) {
                        $airname = Engine::lastInsertId();
                        $_REQUEST["button"] = "";
                        $displayForm = 0;
                    } else
                        $errorMessage = "<B><FONT COLOR=\"#cc0000\">Airname '$djname' is invalid or already exists.</FONT></B>";
                }
                if ($displayForm) {
    ?>
    <P CLASS="header">Add New Airname</P>
    <?php echo $errorMessage; ?>
    <FORM ACTION="?" METHOD=POST>
    <TABLE CELLPADDING=0 CELLSPACING=0>
      <TR>
        <TD ALIGN=RIGHT>Airname:</TD>
        <TD><INPUT TYPE=TEXT NAME=djname CLASS=input SIZE=30></TD>
      </TR>
      <TR>
        <TD>&nbsp;</TD>
        <TD><INPUT TYPE=SUBMIT NAME="newairname" VALUE=" Add Airname "></TD>
      </TR>
    </TABLE>
    <INPUT TYPE=HIDDEN NAME=button VALUE=" Setup New Airname... ">
    <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
    <INPUT TYPE=HIDDEN NAME=created VALUE="<?php echo $_REQUEST["created"];?>">
    <INPUT TYPE=HIDDEN NAME=tag VALUE="<?php echo $_REQUEST["tag"];?>">
    <INPUT TYPE=HIDDEN NAME=action VALUE="searchReviewEdit">
    <INPUT TYPE=HIDDEN NAME=validate VALUE="y">
    </FORM>
    <?php 
                    UI::setFocus("djname");
                    return;
                }
                break;
            case " Post Review! ":
                Engine::api(IReview::class)->deleteReview($_REQUEST["tag"], $this->session->getUser());
                $review = substr($_REQUEST["review"], 0, 64000);
                $success = Engine::api(IReview::class)->insertReview($_REQUEST["tag"], $_REQUEST["private"], $airname, $review, $this->session->getUser());
                if($success >= 1) {
                    if($_REQUEST["noise"])
                        $this->eMailReview($_REQUEST["tag"], $airname, $review);
                    echo "<B><FONT COLOR=\"#ffcc33\">Your review has been posted!</FONT></B>\n";
                    $_REQUEST["n"] = $_REQUEST["tag"];
                    $this->newEntity(Search::class)->searchByAlbumKey();
                    return;
                }
                echo "<B><FONT COLOR=\"#cc0000\">Review not posted.  Try again later.</FONT></B>\n";
                break;
            case " Update Review ":
                $review = substr($_REQUEST["review"], 0, 64000);
                $success = Engine::api(IReview::class)->updateReview($_REQUEST["tag"], $_REQUEST["private"], $airname, $review, $this->session->getUser());
                if($success >= 0) {
                    if($_REQUEST["noise"])
                        $this->eMailReview($_REQUEST["tag"], $airname, $review);
                    echo "<B><FONT COLOR=\"#ffcc33\">Your review has been updated.</FONT></B>\n";
                    $_REQUEST["n"] = $_REQUEST["tag"];
                    $this->newEntity(Search::class)->searchByAlbumKey();
                    return;
                }
                echo "<B><FONT COLOR=\"#cc0000\">Review not updated.  Try again later.</FONT></B>\n";
                break;
            case " Delete Review ":
                $success = Engine::api(IReview::class)->deleteReview($_REQUEST["tag"], $this->session->getUser());
                if($success >= 1) {
                    echo "<B><FONT COLOR=\"#ffcc33\">Your review has been deleted.</FONT></B>\n";
                    $_REQUEST["n"] = $_REQUEST["tag"];
                    $this->newEntity(Search::class)->searchByAlbumKey();
                    return;
                }
                echo "<B><FONT COLOR=\"#cc0000\">Delete failed.  Try again later.</FONT></B>\n";
                break;
            }
        }
        $_REQUEST["private"] = 0;
        $results = Engine::api(IReview::class)->getReviews($_REQUEST["tag"], 0, $this->session->getUser(), 1);
        if(sizeof($results)) {
            $saveAirname = $airname;
            extract($results[0]);
            if($saveAirname)
                $airname = $saveAirname;
            $_REQUEST["private"] = $private;
        }
        
        $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $_REQUEST["tag"]);
    ?>
    <P CLASS="header">Review Album:&nbsp;&nbsp;<?php echo $albums[0]["artist"] ." / ". $albums[0]["album"];?></P>
    <FORM ACTION="?" METHOD=POST>
    <TABLE>
      <TR><TD ALIGN=RIGHT>Reviewer:</TD>
          <TD><SELECT NAME=airname>
    <?php 
        $records = Engine::api(IDJ::class)->getAirnames($this->session->getUser());
        while ($row = $records->fetch()) {
           $selected = ($row[0] == $airname)?" SELECTED":"";
           echo "            <OPTION VALUE=\"" . $row[0] ."\"" . $selected .
                ">$row[1]\n";
        }
        $selected = $airname?"":" SELECTED";
        $user = Engine::api(ILibrary::class)->search(ILibrary::PASSWD_NAME, 0, 1, $this->session->getUser());
        echo "            <OPTION VALUE=\"\"$selected>(" . $user[0]["realname"] . ")\n";
    ?>
              </SELECT><INPUT TYPE=SUBMIT NAME=button VALUE=" Setup New Airname... "></TD></TR>
    </TABLE>
    <TABLE>
      <TR><TD>Review:</TD>
          <TD ALIGN=RIGHT><INPUT TYPE=RADIO NAME=private VALUE=0<?php if(!$_REQUEST["private"])echo " CHECKED";?>>Public&nbsp;&nbsp;
                          <INPUT TYPE=RADIO NAME=private VALUE=1<?php if($_REQUEST["private"])echo " CHECKED";?>>Private</TD></TR>
      <TR><TD COLSPAN=2>
        <SPAN CLASS=input><TEXTAREA WRAP=VIRTUAL NAME=review COLS=50 ROWS=20>
<?php echo htmlentities($review);?></TEXTAREA></SPAN><BR>
      </TD></TR>
      <TR><TD ALIGN=LEFT COLSPAN=2>
    <?php 
        if($id) {
    ?>
          <INPUT TYPE=SUBMIT NAME=button VALUE=" Update Review ">&nbsp;&nbsp;&nbsp;
              <INPUT TYPE=SUBMIT NAME=button VALUE= " Delete Review ">
    <?php  } else { ?>
          <INPUT TYPE=SUBMIT NAME=button VALUE=" Post Review! ">
    <?php    $email = " CHECKED";
           } ?>
      </TD></TR>
      <TR><TD ALIGN=LEFT COLSPAN=2>
        <INPUT TYPE=CHECKBOX NAME=noise<?php echo $email;?>>E-mail review to Noise
      </TD></TR>
    </TABLE>
    <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
    <INPUT TYPE=HIDDEN NAME=created VALUE="<?php echo $_REQUEST["created"];?>">
    <INPUT TYPE=HIDDEN NAME=tag VALUE="<?php echo $_REQUEST["tag"];?>">
    <INPUT TYPE=HIDDEN NAME=action VALUE="searchReviewEdit">
    <INPUT TYPE=HIDDEN NAME=validate VALUE="y">
    </FORM>
    <?php 
        UI::setFocus("review");
    }
}
