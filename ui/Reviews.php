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
use ZK\Engine\IArtwork;
use ZK\Engine\IDJ;
use ZK\Engine\ILibrary;
use ZK\Engine\IReview;
use ZK\Engine\IUser;

use ZK\UI\UICommon as UI;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

use JSMin\JSMin;

class Reviews extends MenuItem {
    const PLAIN_TEXT_WRAP_LEN = 75;

    const SLACK_BASE = "https://slack.com/api/";

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

    public function viewRecentReviews() {
        $isAuthorized = $this->session->isAuth('u');
        $author = $isAuthorized && ($_GET['dj'] ?? '') == 'Me' ? $this->session->getUser() : '';

        $this->setTemplate("review.recent.html");
        $this->extra = "<span class='sub'><b>Reviews Feed:</b></span> <a type='application/rss+xml' href='zkrss.php?feed=reviews'><img src='img/rss.png' alt='rss'></a>";
        $this->addVar("GENRES", ILibrary::GENRES);

        $reviews = Engine::api(IReview::class)->getRecentReviews($author, 0, 200, $isAuthorized)->asArray();
        $libAPI = Engine::api(ILibrary::class);
        foreach($reviews as &$review) {
            $albums = $libAPI->search(ILibrary::ALBUM_KEY, 0, 1, $review['tag']);
            $review['album'] = $albums[0];
            if(!$review['airname']) {
                $users = $libAPI->search(ILibrary::PASSWD_NAME, 0, 1, $review['user']);
                $review['airname'] = $users[0]['realname'];
            }
        }
        $this->addVar("reviews", $reviews);
    }

    public function viewReview() {
        $this->newEntity(Search::class)->searchByAlbumKey($_REQUEST["tag"]);
    }
    
    private function postReview($tag) {
        // nothing to do if Slack is not configured
        $config = Engine::param('slack');
        if(!$config || !($token = $config['token']) ||
                !($channel = $config['review_channel'])) {
            echo "  <h4 class='error'>Slack is not configured.</h4>\n";
            return;
        }

        // find the album
        $libAPI = Engine::api(ILibrary::class);
        $albums = $libAPI->search(ILibrary::ALBUM_KEY, 0, 1, $tag);
        if(!count($albums))
            return;

        // get the album art, if any
        $imageApi = Engine::api(IArtwork::class);
        $image = $imageApi->getAlbumArt($tag);
        $albumArt = $image && ($uuid = $image["image_uuid"]) ?
                        $imageApi->getCachePath($uuid) : null;

        // find the review
        $user = $this->session->getUser();
        $reviews = Engine::api(IReview::class)->getReviews($tag, false, $user, true);
        if(!count($reviews))
            return;

        $artist = $albums[0]["iscoll"] ? "Various Artists" : $albums[0]["artist"];
        $album = $albums[0]["album"];

        $reviewer = $reviews[0]["realname"];
        $created = $reviews[0]["created"];
        $review = $reviews[0]["review"];

        $base = Engine::getBaseUrl();
        $title = Engine::param('station_title');

        // compose the message
        $header = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => ":headphones: *$artist / $album*\n\n$reviewer\nReviewed " . substr($created, 0, 10),
            ],
        ];

        if($albumArt) {
            $header['accessory'] = [
                'type' => 'image',
                'image_url' => Engine::getBaseUrl() . $albumArt,
                'alt_text' => 'album art',
            ];
        }

        $body = [
            'type' => 'section',
            'text' => [
                'type' => 'plain_text',
                'text' => $review,
            ],
        ];

        $footer = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "<$base?s=byAlbumKey&amp;n=$tag&amp;action=search|View this review in $title>.",
            ],
        ];

        $client = new Client([
            'base_uri' => self::SLACK_BASE,
            RequestOptions::HEADERS => [
                'User-Agent' => Engine::UA,
                'Authorization' => 'Bearer ' . $token
            ]
        ]);

        try {
            $response = $client->post('chat.postMessage', [
                RequestOptions::JSON => [
                    'channel' => $channel,
                    'text' => "$artist / $album",
                    'blocks' => [
                        $header,
                        $body,
                        $footer,
                    ],
                ]
            ]);

            // Slack returns success/failure in 'ok' property
            $body = $response->getBody()->getContents();
            $json = json_decode($body);
            if(!$json->ok)
                error_log("postMessage: $body");
        } catch(\Exception $e) {
            error_log("postMessage: " . $e->getMessage());
        }
    }

    private function eMailReview($tag) {
        $instance_nobody = Engine::param('email')['nobody'];
        $address = Engine::param('email')['reviewlist'];

        if(!isset($address)) {
            echo "  <h4 class='error'>Noise e-mail not configured.</h4>\n";
            return;
        }

        $libAPI = Engine::api(ILibrary::class);
        $revAPI = Engine::api(IReview::class);
        $records = $revAPI->getReviews($tag, false, $this->session->getUser(), true);
        if(sizeof($records) && ($row = $records[0])) {
            $name = $row["realname"];
    
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
    
            $body .= "\n$name\nReviewed " . substr($row["created"], 0, 10) . "\r\n\r\n";
    
            $review = $row["review"];
    
            $body .= wordwrap($review, self::PLAIN_TEXT_WRAP_LEN, "\r\n", true);
    
            // Emit the postamble
            $body .= "\r\n\r\n--\r\nPost your music reviews online!\r\n";
            $body .= Engine::param('station')." Zookeeper Online:  ".UI::getBaseUrl()."\r\n";
    
            // send the mail
            $stat = mail($address, $subject, $body, $headers);
    
            // Check for errors
            if(!$stat) {
                echo "  <h4 class='error'>Possible Problem Sending E-Mail</h4><BR>\n";
                echo "There may have been a problem sending your e-mail.  ";
                echo "</P>\n";
                //echo "The mailer reports the following error:<BR>\n  <PRE>\n";
                //echo error_get_last()['message'];
                //echo "\n</PRE></FONT></B>\n";
            } else {
                echo "  <h4>E-Mail Sent!</h4><BR>\n";
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
            case "post-review":
                Engine::api(IReview::class)->deleteReview($_REQUEST["tag"], $this->session->getUser());
                $success = Engine::api(IReview::class)->insertReview($_REQUEST["tag"], $_REQUEST["private"], $aid, $review, $this->session->getUser());
                if($success >= 1) {
                    if($_REQUEST["noise"])
                        $this->postReview($_REQUEST["tag"]);
                    echo "<h4>Your review has been posted!</h4>\n";
                    $this->newEntity(Search::class)->searchByAlbumKey($_REQUEST["tag"]);
                    return;
                }
                $errorMessage = "<h4 class='error'>Review not posted.  Try again later.</h4>\n";
                break;
            case "edit-save":
                $review = mb_substr(trim($_REQUEST["review"]), 0, IReview::MAX_REVIEW_LENGTH);
                $success = Engine::api(IReview::class)->updateReview($_REQUEST["tag"], $_REQUEST["private"], $aid, $review, $this->session->getUser());
                if($success >= 0) {
                    if($_REQUEST["noise"])
                        $this->postReview($_REQUEST["tag"]);
                    echo "<h4>Your review has been updated.</h4>\n";
                    $this->newEntity(Search::class)->searchByAlbumKey($_REQUEST["tag"]);
                    return;
                }
                $errorMessage = "<h4 class='error'>Review not updated.  Try again later.</h4>\n";
                break;
            case "edit-delete":
                $success = Engine::api(IReview::class)->deleteReview($_REQUEST["tag"], $this->session->getUser());
                if($success >= 1) {
                    echo "<h4>Your review has been deleted.</h4>\n";
                    $this->newEntity(Search::class)->searchByAlbumKey($_REQUEST["tag"]);
                    return;
                }
                $errorMessage = "<h4 class='error'>Delete failed.  Try again later.</h4>\n";
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

        $this->template = "review.edit.html";
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
