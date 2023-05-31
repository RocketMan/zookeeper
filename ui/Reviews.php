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

namespace ZK\UI;

use ZK\Engine\Engine;
use ZK\Engine\IDJ;
use ZK\Engine\ILibrary;
use ZK\Engine\IReview;
use ZK\Engine\IUser;

use ZK\UI\UICommon as UI;

use JSMin\JSMin;

class Reviews extends MenuItem {
    const PLAIN_TEXT_WRAP_LEN = 75;

    private static $actions = [
        [ "viewRecent", "viewRecentDispatch" ],
        [ "viewRecentReview", "viewReview" ],
        [ "searchReviewView", "viewReview" ],
        [ "searchReviewEdit", "editReview" ],
    ];

    private static $subactions = [
        [ "a", "", "Recent Reviews", "viewRecentReviews" ],
        [ "a", "viewDJ", "By DJ", "reviewsByDJ" ],
    ];

    private $subaction;

    public function processLocal($action, $subaction) {
        $this->subaction = $subaction;
        $this->dispatchAction($action, self::$actions);
    }

    public function getSubactions($action) {
        return self::$subactions;
    }

    public function viewRecentDispatch() {
        $this->dispatchSubaction('', $this->subaction);
    }

    public function emitReviewHook($tag=0) {
        if(!$tag)
            $tag = $_REQUEST["n"];
        if($this->session->isAuth("u"))
            echo "<A HREF=\"?action=searchReviewEdit&amp;tag=$tag\" CLASS=\"nav\"><B>Write a review of this album</B></A>";
    }

    public function emitViewDJMain() {
        $viewAll = $this->subaction == "viewDJAll";

        // Run the query
        $records = Engine::api(IReview::class)->getActiveReviewers($viewAll);
        $dj = [];
        while($records && ($row = $records->fetch())) {
            $row["sort"] = preg_match("/^the /i", $row[1])?substr($row[1], 4):$row[1];
            // sort symbols beyond Z with the numerics and other special chars
            $row["cur"] = UI::deLatin1ify(mb_strtoupper(mb_substr($row["sort"], 0, 1)));
            if($row["cur"] > "Z") {
                $row["sort"] = "@".$row["sort"];
                $row["cur"] = "@";
            }

            $dj[] = $row;
        }

        if(count($dj))
            usort($dj, function($a, $b) {
                return strcasecmp($a["sort"], $b["sort"]);
            });

        $this->setTemplate("selectdj.html");
        $this->addVar("djs", $dj);
    }

    public function reviewsByDJ() {
        $seq = $_REQUEST["seq"];
        $viewuser = $_REQUEST["viewuser"] ?? null;
        if($seq == "selUser" && $viewuser) {
            $airname = null;
            if(is_numeric($viewuser)) {
                $results = Engine::api(IDJ::class)->getAirnames(0, $viewuser);
                if($results) {
                    $row = $results->fetch();
                    $airname = $row['airname'];
                }
            } else {
                $row = Engine::api(IUser::class)->getUser($viewuser);
                if($row)
                    $airname = $row['realname'];
            }

            if($airname) {
                $this->addVar("airname", $airname);
                $this->addVar("key", $viewuser);
                $this->tertiary = $airname;
                $this->template = "search.reviews.html";
                return;
            }
        }

        $this->emitViewDJMain();
    }
    
    private function emitReviewRow($row, $album) {
        // Album
        $genre = ILibrary::GENRES[$album[0]["category"]];
        echo "<TR CLASS='hborder ${genre}' data-genre='${genre}'>";
        echo "<TD><A HREF='?action=viewRecentReview&amp;tag=$row[0]'>";
        echo htmlentities($album[0]["album"]);
        echo "</A></TD><TD>";
    
        // Artist
        if (preg_match("/^\[coll\]/i", $album[0]["artist"]))
            echo "Various Artists";
        else
            echo htmlentities($album[0]["artist"]);
        echo "</TD><TD>";
    
        // Genre
        echo htmlentities($genre);
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
        echo "</TD><TD CLASS='date'>";
        echo $row["reviewed"];
        echo "</TD></TR>\n";
    }
    
    private function makeRecentReviewsHeader() {
        return "<THEAD><TR>" .
             "<TH ALIGN=LEFT>Album</TH>" .
             "<TH ALIGN=LEFT>Artist</TH>" .
             "<TH ALIGN=LEFT>Collection</TH>" .
             "<TH ALIGN=LEFT>Reviewer</TH>" .
             "<TH ALIGN=LEFT>Date</TH>" .
             "</TR></THEAD>";
    }
    
    public function viewRecentReviews() {
        $isAuthorized = $this->session->isAuth("u");
        $author = $isAuthorized && trim($_GET["dj"]) == 'Me' ? $this->session->getUser() : '';        

        echo "<DIV class='categoryPicker form-entry'>";
        $this->extra = "<span class='sub'><b>Reviews Feed:</b></span> <A TYPE='application/rss+xml' HREF='zkrss.php?feed=reviews'>" .
             "<IMG SRC='img/rss.png' ALT='rss'></A>";

        echo "<label class='reviewLabel'>Categories:&nbsp;</label>";
        echo "<span class='review-categories zk-hidden'>";
        // NOTE: final visibility is set via javascript upon page load.
        foreach (ILibrary::GENRES as $genre) {
            echo "<span class='${genre} zk-hidden'>";
            echo "<input style='margin-right: 2px' type='checkbox' id='${genre}' name='genre' value='${genre}'>";
            echo "<span for='${genre}'>$genre</span></span>";
        }
        echo "</span>";
        echo "</DIV>";

        if ($isAuthorized) {
            echo "<div style='display:inline-block' class='form-entry' >";
            echo "<label class='reviewLabel'>Reviewer:</label>";
            echo "<select id='djPicker' name='dj'>";
            $selectedOpt = empty($author) ? ' selected ' : '';
            echo "<option ${selectedOpt}>All</option>";
            $selectedOpt = empty($selectedOpt) ? ' selected ' : '';
            echo "<option ${selectedOpt}>Me</option>";
            echo "</select>";
            echo "</div>";
        }
        echo "<span id='review-count'></span>";

        $reviewsHeader = $this->makeRecentReviewsHeader();
        echo "<TABLE class='sortable-table' style='display: none' WIDTH='100%'>";
        echo $reviewsHeader;
        echo "<TBODY>";

        $results = Engine::api(IReview::class)->getRecentReviews($author, 0, 200, $isAuthorized);
        $libAPI = Engine::api(ILibrary::class);
        while($results && ($row = $results->fetch())) {
            $albums = $libAPI->search(ILibrary::ALBUM_KEY, 0, 1, $row[0]);
            $this->emitReviewRow($row, $albums);
        }
        echo "</TBODY>";
        echo "</TABLE>";

        UI::setFocus();
      ?>

      <SCRIPT><!--
    <?php ob_start([JSMin::class, 'minify']); ?>
        $().ready(function(){
            var INITIAL_SORT_COL = 0; //date
            $('.sortable-table').tablesorter({
                sortList: [[INITIAL_SORT_COL, 0]],
            }).css('display','table');

            function setGenreVisibility(genre, showIt) {
                let genreClass = 'tr.' + genre;
                showIt ?  $(genreClass).show() : $(genreClass).hide();
            }

            let genreMap = {};
            let reviewCnt = 0;
            $('.sortable-table > tbody > tr').each(function(e) {
                reviewCnt++;
                let genre = $(this).data('genre');
                if (genreMap[genre] === undefined) {
                    genreMap[genre] = 0;
                    $(".review-categories span." + genre).removeClass('zk-hidden');
                }
                genreMap[genre]++;
            });
            $("span.review-categories").removeClass('zk-hidden');
            $("#review-count").text(' Found ' + reviewCnt + ' reviews.');

            for (let [genre, count] of Object.entries(genreMap)) {
                $(`span.${genre} > span`).text(`${genre} (${count})`);
            }

            let selectedDj = $('#djPicker').children("option:selected").val();
            let storageKey = 'ReviewCategories-' + selectedDj;
            let categoryStr = localStorage.getItem(storageKey);
            let categories = categoryStr ? JSON.parse(categoryStr) : {};
                
            $(".categoryPicker input").each(function(e) {
                let genre  = $(this).val();
                let isChecked = !(categories[genre] === false);
                setGenreVisibility(genre, isChecked)
                $(this).prop('checked', isChecked);
            });

            $("#djPicker").on('change selectmenuchange', function(e) {
                let selectedDj = $(this).children("option:selected").val();
                window.location.assign('?action=viewRecent&dj=' + selectedDj);
            }).selectmenu();
            
            $(".categoryPicker input").on('change', function(e) {
                let genre = $(this).val();
                let isChecked = $(this).prop('checked');
                let rowClass = "tr." + genre;
                setGenreVisibility(genre, isChecked);
                categories[genre] = isChecked;
                localStorage.setItem(storageKey, JSON.stringify(categories));
            });
        });
    <?php ob_end_flush(); ?>
        // -->
      </SCRIPT>

    <?php
    }

    public function viewReview() {
        $this->newEntity(Search::class)->searchByAlbumKey($_REQUEST["tag"]);
    }
    
    public function viewReview2($tag=0) {
        if(!$tag)
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
                    echo "  <TR COLSPAN=3><TD><FONT SIZE=-1><A HREF=\"?action=searchReviewEdit&amp;tag=$tag\">[This is my review and I want to edit it]</A></FONT></TD></TR>\n";
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
    
        $libAPI = Engine::api(ILibrary::class);
        $revAPI = Engine::api(IReview::class);
        $records = $revAPI->getReviews($tag, 1, "", 1);
        if(sizeof($records) && ($row = $records[0])) {
            $djs = $libAPI->search(ILibrary::PASSWD_NAME, 0, 1, $this->session->getUser());
            $name = $djs[0]["realname"];
    
            // JM 2018-03-16 for now, force all from nobody
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
    
            if(isset($albums[0]["name"])) {
                $labeln = str_replace(" Records", "", $albums[0]["name"]);
                $labeln = str_replace(" Recordings", "", $labeln);
                $body .= "Label: " . UI::deLatin1ify($labeln) . "\r\n";
            } else
                $body .= "Label: (Unknown)\r\n";
    
            $body .= "\n$name\nReviewed " . substr($row[1], 0, 10) . "\r\n\r\n";
    
            $review = $row[2];
    
            $body .= wordwrap($review, self::PLAIN_TEXT_WRAP_LEN, "\r\n", true);
    
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
        if(!$this->session->isAuth("u")) {
            $this->newEntity(Home::class)->emitHome();
            return;
        }

        $airname = mb_substr(trim($_REQUEST["airname"]), 0, IDJ::MAX_AIRNAME_LENGTH);

        $user = Engine::api(ILibrary::class)->search(ILibrary::PASSWD_NAME, 0, 1, $this->session->getUser());
        $self = "(" . $user[0]["realname"] . ")";
        $errorMessage = "";
        if($_POST["validate"]) {
            $review = mb_substr(trim($_REQUEST["review"]), 0, IReview::MAX_REVIEW_LENGTH);

            // lookup the airname
            $aid = null;
            if($airname && strcasecmp($airname, $self)) {
                $djapi = Engine::api(IDJ::class);
                $aid = $djapi->getAirname($airname, $this->session->getUser());
                if(!$aid) {
                    // airname does not exist; try to create it
                    $success = $djapi->insertAirname(mb_substr($airname, 0, IDJ::MAX_AIRNAME_LENGTH), $this->session->getUser());
                    if($success > 0) {
                        // success!
                        $aid = $djapi->lastInsertId();
                    } else {
                        $errorMessage = "<p><b><font class='error'>Airname '$airname' is invalid or already exists.</font></b></p>";
                        $airname = "";
                        $aid = false;
                        $_REQUEST["button"] = "";
                    }
                }
            }

            switch($_REQUEST["button"]) {
            case " Post Review! ":
                Engine::api(IReview::class)->deleteReview($_REQUEST["tag"], $this->session->getUser());
                $success = Engine::api(IReview::class)->insertReview($_REQUEST["tag"], $_REQUEST["private"], $aid, $review, $this->session->getUser());
                if($success >= 1) {
                    if($_REQUEST["noise"])
                        $this->eMailReview($_REQUEST["tag"], $aid, $review);
                    echo "<B><FONT COLOR=\"#ffcc33\">Your review has been posted!</FONT></B>\n";
                    $this->newEntity(Search::class)->searchByAlbumKey($_REQUEST["tag"]);
                    return;
                }
                $errorMessage = "<B><FONT COLOR=\"#cc0000\">Review not posted.  Try again later.</FONT></B>\n";
                break;
            case " Update Review ":
                $review = mb_substr(trim($_REQUEST["review"]), 0, IReview::MAX_REVIEW_LENGTH);
                $success = Engine::api(IReview::class)->updateReview($_REQUEST["tag"], $_REQUEST["private"], $aid, $review, $this->session->getUser());
                if($success >= 0) {
                    if($_REQUEST["noise"])
                        $this->eMailReview($_REQUEST["tag"], $aid, $review);
                    echo "<B><FONT COLOR=\"#ffcc33\">Your review has been updated.</FONT></B>\n";
                    $this->newEntity(Search::class)->searchByAlbumKey($_REQUEST["tag"]);
                    return;
                }
                $errorMessage = "<B><FONT COLOR=\"#cc0000\">Review not updated.  Try again later.</FONT></B>\n";
                break;
            case " Delete Review ":
                $success = Engine::api(IReview::class)->deleteReview($_REQUEST["tag"], $this->session->getUser());
                if($success >= 1) {
                    echo "<B><FONT COLOR=\"#ffcc33\">Your review has been deleted.</FONT></B>\n";
                    $this->newEntity(Search::class)->searchByAlbumKey($_REQUEST["tag"]);
                    return;
                }
                $errorMessage = "<B><FONT COLOR=\"#cc0000\">Delete failed.  Try again later.</FONT></B>\n";
                break;
            }
        }
        $_REQUEST["private"] = 0;
        $results = Engine::api(IReview::class)->getReviews($_REQUEST["tag"], 1, $this->session->getUser(), 1);
        if(sizeof($results)) {
            $saveAirname = $airname;
            extract($results[0]);
            if($saveAirname)
                $airname = $saveAirname;
            $_REQUEST["private"] = $private;
        }
        
        $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $_REQUEST["tag"]);

        $airnames = [];
        $records = Engine::api(IDJ::class)->getAirnames($this->session->getUser(), 0, $djname);
        while ($row = $records->fetch())
           $airnames[] = $row['airname'];
        $airnames[] = $self;

        $this->template = "review.html";
        $this->addVar("id", $id ?? 0);
        $this->addVar("album", $albums[0]);
        $this->addVar("errorMessage", $errorMessage);
        $this->addVar("airnames", $airnames);
        $this->addVar("airname", $airname);
        $this->addVar("self", $self);
        $this->addVar("review", $review);
        $this->addVar("private", $_REQUEST["private"] ?? 0);
        $this->addVar("MAX_AIRNAME_LENGTH", IDJ::MAX_AIRNAME_LENGTH);
        $this->addVar("MAX_REVIEW_LENGTH", IReview::MAX_REVIEW_LENGTH);
    }
}
