<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2025 Jim Mason <jmason@ibinx.com>
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
        [ "a", "viewHashtag", "Trending", "viewTrending" ],
        [ "a", "trendingData", 0, "getTrendingData" ],
        [ "u", "viewReviewShelf", "Review Shelf", "viewReviewShelf" ],
        [ "u", "updateReviewShelf", 0, "updateReviewShelf" ],
    ];

    private $subaction;

    /**
     * ILibrary::GENRES exclusive collection prefix, if any
     */
    private static function getGenres(): array {
        return array_map(function($genre) {
            return array_reverse(explode(" - ", $genre))[0];
        }, ILibrary::GENRES);
    }

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
        $isAuthorized = $this->session->isAuth('u');

        // Run the query
        $records = Engine::api(IReview::class)->getActiveReviewers($viewAll, $isAuthorized);
        $dj = [];
        while($records && ($row = $records->fetch())) {
            $row["sort"] = preg_match("/^(the|dj)\s+(.+)/i", $row[1], $matches) ? $matches[2] : $row[1];
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
        $seq = $_REQUEST["seq"] ?? '';
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
                $this->setTemplate("review/search.html");
                return;
            }
        }

        $this->emitViewDJMain();
    }

    public function viewTrending() {
        $this->setTemplate("review/trending.html");
    }

    public function getTrendingData() {
        $limit = 50;
        $scale = $limit / 5;
        $trending = Engine::api(IReview::class)->getTrending($limit);
        $data = array_map(function($entry) use(&$limit, $scale) {
            return [
                'text' => $entry['hashtag'],
                'weight' => $entry['freq'] * $scale + floor($limit-- / 10),
                'link' => '?action=search&s=byHashtag&n=' . urlencode($entry['hashtag'])
            ];
        }, $trending);

        echo json_encode($data);
    }

    public function viewReviewShelf() {
        $albums = Engine::api(IReview::class)->getReviewShelf();
        Engine::api(ILibrary::class)->markAlbumsPlayable($albums);
        $this->addVar('GENRES', self::getGenres());
        $this->addVar('albums', $albums);
        $this->setTemplate("review/shelf.html");
    }

    public function updateReviewShelf() {
        $op = $_REQUEST['op'] ?? false;
        $tag = $_REQUEST['tag'] ?? false;
        if(!$op || !$tag) {
            http_response_code(400); // bad request
            return;
        }

        switch($op) {
        case 'claim':
            Engine::api(IReview::class)->updateReviewShelf($tag, $this->session->getUser());
            break;
        case 'release':
            // fall through...
        case 'dtm':
            Engine::api(IReview::class)->updateReviewShelf($tag, null);
            break;
        }

        $api = Engine::api(ILibrary::class);
        $albums = $api->search(ILibrary::ALBUM_KEY, 0, 1, $tag);
        $api->markAlbumsPlayable($albums);
        $album = $albums[0];

        if($album['bin']) {
            $user = Engine::api(ILibrary::class)->search(ILibrary::PASSWD_NAME, 0, 1, $album['bin']);
            if(count($user))
                $album['realname'] = $user[0]['realname'];
        }

        $this->claimReview($tag, $op);

        $this->addVar('GENRES', self::getGenres());
        $this->addVar('album', $album);
        $this->setTemplate("review/shelf.html");
        $html = $this->render('album');

        echo json_encode(['html' => $html]);
    }

    public function viewRecentReviews() {
        $isAuthorized = $this->session->isAuth('u');
        $author = $isAuthorized && ($_GET['dj'] ?? '') == 'Me' ? $this->session->getUser() : '';

        $this->setTemplate("review/recent.html");
        $this->extra = "<span class='sub'><b>Reviews Feed:</b></span> <a type='application/rss+xml' href='zkrss.php?feed=reviews&amp;fmt=1'><img src='img/rss.png' alt='rss'></a>";
        $this->addVar("GENRES", self::getGenres());

        $reviews = Engine::api(IReview::class)->getRecentReviews($author, 0, 200, $isAuthorized);
        $this->addVar("reviews", $reviews);
    }

    public function viewReview() {
        $this->newEntity(Search::class)->searchByAlbumKey($_REQUEST["tag"]);
    }
    
    private function claimReview($tag, $op) {
        // nothing to do if Slack is not configured
        $config = Engine::param('slack');
        if($op == 'dtm' ||
                !$config || !($token = $config['token']) ||
                !($channel = $config['review_channel'])) {
            return;
        }

        // find the album
        $libAPI = Engine::api(ILibrary::class);
        $albums = $libAPI->search(ILibrary::ALBUM_KEY, 0, 1, $tag);
        if(!count($albums))
            return;

        $artist = $albums[0]["iscoll"] ? "Various Artists" : $albums[0]["artist"];
        $album = $albums[0]["album"];

        $user = $libAPI->search(ILibrary::PASSWD_NAME, 0, 1, $this->session->getUser());
        if(!count($user))
            return;

        $reviewer = $user[0]["realname"];
        $unclaim = $op == 'release';
        $action = $unclaim ? "has returned" : "is reviewing";
        $verb = $unclaim ? "returned" : "claimed";

        $base = Engine::getBaseUrl();
        $title = Engine::param('station_title');

        // compose the message
        $body = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => ":headphones: $reviewer $action <$base?s=byAlbumKey&amp;n=$tag&amp;action=search|$artist / $album>"
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
            $options = [
                'channel' => $channel,
                'text' => "$reviewer $verb $artist / $album",
                'blocks' => [
                    $body,
                ],
            ];

            $method = 'chat.postMessage';

            $response = $client->post($method, [
                RequestOptions::JSON => $options
            ]);

            // Slack returns success/failure in 'ok' property
            $body = $response->getBody()->getContents();
            $json = json_decode($body);
            if(!$json->ok)
                error_log("claimReview: $body");
        } catch(\Exception $e) {
            error_log("claimReview: " . $e->getMessage());
        }
    }

    private function postReview($tag) {
        // nothing to do if Slack is not configured
        $config = Engine::param('slack');
        if(!$config || !($token = $config['token']) ||
                !($channel = $config['review_channel'])) {
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
        $reviewApi = Engine::api(IReview::class);
        $reviews = $reviewApi->getReviews($tag, true, $user, true);
        if(!count($reviews))
            return;

        $artist = $albums[0]["iscoll"] ? "Various Artists" : $albums[0]["artist"];
        $album = $albums[0]["album"];

        $reviewer = $reviews[0]["realname"];
        $created = $reviews[0]["created"];
        $review = $reviews[0]["review"];
        $exportid = $reviews[0]["exportid"];

        // append reviewer airname, if any
        if($reviews[0]["airname"] && strncasecmp($reviewer, $reviews[0]["airname"], strlen($reviews[0]["airname"])))
            $reviewer .= " (" . $reviews[0]["airname"] . ")";

        // truncate the review at the track breakout
        if(preg_match('/(.+?)(?=(\r?\n)[\p{P}\p{S}\s]*\d+[\p{P}\p{S}\d]*\s)/su', $review, $matches) && $matches[1])
            $review = $matches[1];

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
                'image_url' => $base . $albumArt,
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
                'text' => "<$base?s=byAlbumKey&amp;n=$tag&amp;action=search|View full review in $title>.",
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
            $options = [
                'channel' => $channel,
                'text' => "$artist / $album",
                'blocks' => [
                    $header,
                    $body,
                    $footer,
                ],
            ];

            if($exportid) {
                $options['ts'] = $exportid;
                $method = 'chat.update';
            } else
                $method = 'chat.postMessage';

            $response = $client->post($method, [
                RequestOptions::JSON => $options
            ]);

            // Slack returns success/failure in 'ok' property
            $body = $response->getBody()->getContents();
            $json = json_decode($body);
            if($json->ok) {
                if(!$exportid) {
                    $exportid = $json->ts;
                    $reviewApi->setExportId($tag, $user, $exportid);
                }
            } else
                error_log("postReview: $body");
        } catch(\Exception $e) {
            error_log("postReview: " . $e->getMessage());
        }
    }

    private function unpostReview($exportId) {
        // nothing to do if not exported or Slack is not configured
        $config = Engine::param('slack');
        if(!$exportId || !$config || !($token = $config['token']) ||
                !($channel = $config['review_channel'])) {
            return;
        }

        $client = new Client([
            'base_uri' => self::SLACK_BASE,
            RequestOptions::HEADERS => [
                'User-Agent' => Engine::UA,
                'Authorization' => 'Bearer ' . $token
            ]
        ]);

        try {
            $response = $client->post('chat.delete', [
                RequestOptions::JSON => [
                    'channel' => $channel,
                    'ts' => $exportId
                ]
            ]);

            // Slack returns success/failure in 'ok' property
            $body = $response->getBody()->getContents();
            $json = json_decode($body);
            if(!$json->ok)
                error_log("unpostReview: $body");
        } catch(\Exception $e) {
            error_log("unpostReview: " . $e->getMessage());
        }
    }

    private function eMailReview($tag) {
        $instance_nobody = Engine::param('email')['nobody'];
        $address = Engine::param('email')['reviewlist'];

        if(!isset($address)) {
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
        }
    }
    
    public function editReview() {
        if(!$this->session->isAuth("u")) {
            $this->newEntity(Home::class)->emitHome();
            return;
        }

        $airname = mb_substr(trim($_REQUEST["airname"] ?? ''), 0, IDJ::MAX_AIRNAME_LENGTH);

        $user = Engine::api(ILibrary::class)->search(ILibrary::PASSWD_NAME, 0, 1, $this->session->getUser());
        $self = "(" . $user[0]["realname"] . ")";
        $errorMessage = "";
        if($_POST["validate"] ?? false) {
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
                    if($_REQUEST["noise"] ?? 0)
                        $this->postReview($_REQUEST["tag"]);
                    $this->newEntity(Search::class)->searchByAlbumKey($_REQUEST["tag"]);
                    return;
                }
                $errorMessage = "<h4 class='error'>Review not posted.  Try again later.</h4>\n";
                break;
            case "edit-save":
                $review = mb_substr(trim($_REQUEST["review"]), 0, IReview::MAX_REVIEW_LENGTH);
                $success = Engine::api(IReview::class)->updateReview($_REQUEST["tag"], $_REQUEST["private"], $aid, $review, $this->session->getUser());
                if($success >= 0) {
                    if($_REQUEST["noise"] ?? false)
                        $this->postReview($_REQUEST["tag"]);
                    $this->newEntity(Search::class)->searchByAlbumKey($_REQUEST["tag"]);
                    return;
                }
                $errorMessage = "<h4 class='error'>Review not updated.  Try again later.</h4>\n";
                break;
            case "edit-delete":
                $reviews = Engine::api(IReview::class)->getReviews($_REQUEST["tag"], false, $this->session->getUser(), true);
                $success = Engine::api(IReview::class)->deleteReview($_REQUEST["tag"], $this->session->getUser());
                if($success >= 1) {
                    if(count($reviews))
                        $this->unpostReview($reviews[0]['exportid']);

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
        $records = Engine::api(IDJ::class)->getAirnames($this->session->getUser());
        while ($row = $records->fetch())
           $airnames[] = $row['airname'];
        $airnames[] = $self;

        $slack = Engine::param('slack');
        $export = $slack && $slack['token'] && $slack['review_channel'];

        $this->setTemplate("review/edit.html");
        $this->addVar("id", $id ?? 0);
        $this->addVar("album", $albums[0]);
        $this->addVar("errorMessage", $errorMessage);
        $this->addVar("airnames", $airnames);
        $this->addVar("airname", $airname);
        $this->addVar("self", $self);
        $this->addVar("review", $review ?? '');
        $this->addVar("private", $_REQUEST["private"] ?? 0);
        $this->addVar("exported", !$export || isset($exportid));
        $this->addVar("MAX_AIRNAME_LENGTH", IDJ::MAX_AIRNAME_LENGTH);
        $this->addVar("MAX_REVIEW_LENGTH", IReview::MAX_REVIEW_LENGTH);
    }
}
