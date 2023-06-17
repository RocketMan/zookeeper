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
use ZK\Engine\IChart;
use ZK\Engine\ILibrary;
use ZK\Engine\IPlaylist;
use ZK\Engine\IReview;

use ZK\UI\UICommon as UI;

class Search extends MenuItem {
    private static $actions = [
        [ "findAlbum", "findAlbum" ],
        [ "search", "doSearch" ],
    ];

    private static $legacySearchActions = [
        [ "", "searchForm" ],
        [ "byAlbum", "searchForm" ],
        [ "byAlbumKey", "searchByAlbumKey" ],
        [ "byArtist", "searchForm" ],
        [ "byTrack", "searchForm" ],
        [ "byLabel", "searchForm" ],
        [ "byLabelKey", "searchForm" ],
    ];

    private static $typeFromLegacy = [
        "byAlbum" => "albums",
        "byArtist" => "artists",
        "byTrack" => "tracks",
        "byLabel" => "labels",
        "byLabelKey" => "albumsByPubkey"
    ];

    public $searchText;

    public $searchType;

    public function processLocal($action, $subaction) {
        return $this->dispatchAction($action, self::$actions);
    }

    public function findAlbum() {
        $this->searchByAlbumKey($_REQUEST["n"]);
    }

    public function searchByAlbumKey($key = null) {
        $tag = $key ?? $this->searchText;

        $libraryApi = Engine::api(ILibrary::class);
        $albums = $libraryApi->search(ILibrary::ALBUM_KEY, 0, 1, $tag);

        $this->setTemplate("album.html");

        if(!count($albums)) {
            $this->addVar("album", null);
            return;
        }

        $this->addVar("album", $albums[0]);
        $this->addVar("GENRES", ILibrary::GENRES);
        $this->addVar("MEDIA", ILibrary::MEDIA);
        $this->addVar("DATE_FORMAT_FULL", UI::isUsLocale() ? 'M d, Y' : 'd M Y');
        $this->addVar("DATE_FORMAT_SHORT", UI::isUsLocale() ? 'M j' : 'j M');

        // album art
        $imageApi = Engine::api(IArtwork::class);
        $image = $imageApi->getAlbumArt($tag);
        if($image && ($uuid = $image["image_uuid"])) {
            $this->addVar("image_url", $imageApi->getCachePath($uuid));
            $this->addVar("info_url", $image["info_url"]);
        }

        // report missing
        if($loggedIn = $this->session->isAuth("u")) {
            $urls = Engine::param('urls');
            if(array_key_exists('report_missing', $urls)) {
                $url = str_replace('%USERNAME%', UI::URLify($this->session->getDN()), $urls['report_missing']);
                $url = str_replace('%ALBUMTAG%', $tag, $url);
                $this->addVar("report_missing_url", $url);
            }
        }

        // currents
        $chartApi = Engine::api(IChart::class);
        $rows = $chartApi->getAlbumByTag($tag);
        $accepted = [];
        foreach($rows as &$row) {
            // suppress overlapping charting periods
            // n^2 complexity, but n will generally be 0,
            // as multiple charting periods are rare
            foreach($accepted as $accept) {
                if($row["adddate"] >= $accept["adddate"] && $row["adddate"] <= $accept["pulldate"] ||
                        $row["pulldate"] >= $accept["adddate"] && $row["pulldate"] <= $accept["pulldate"] ||
                        $row["adddate"] <= $accept["adddate"] && $row["pulldate"] >= $accept["pulldate"]) {
                    continue 2;
                }
            }
            $plays = $chartApi->getAlbumPlays($tag, $row["adddate"], $row["pulldate"], 8)->asArray();
            $row['spins'] = $plays;
            $accepted[] = $row;
        }
        $this->addVar("currents", $accepted);
        $this->addVar("CATMAP", $chartApi->getCategories());

        // recent airplay
        $plays = Engine::api(IPlaylist::class)->getLastPlays($tag, 6);
        $this->addVar("recent", $plays);

        // reviews
        $reviews = Engine::api(IReview::class)->getReviews($tag, 1, "", $loggedIn);
        foreach($reviews as &$review) {
            if(!$review['airname']) {
                $djs = $libraryApi->search(ILibrary::PASSWD_NAME, 0, 1, $review['user']);
                $review['airname'] = $djs[0]['realname'];
            }
        }
        $this->addVar("reviews", $reviews);

        // tracks
        $tracks = $libraryApi->search(ILibrary::COLL_KEY, 0, 200, $tag);
        if(!count($tracks))
            $tracks = $libraryApi->search(ILibrary::TRACK_KEY, 0, 200, $tag);

        $isAuth = $this->session->isAuth('u');
        $internalLinks = Engine::param('internal_links');
        $enableExternalLinks = Engine::param('external_links_enabled');
        foreach($tracks as &$track) {
            $url = $track["url"];

            // if external links are enabled, suppress internal URLs for
            // non-authenticated users; otherwise, suppress all but internal
            // URLs for authenticated users
            if($url && ($enableExternalLinks ?
                    $internalLinks && preg_match($internalLinks, $url) && !$isAuth :
                    !$internalLinks || !preg_match($internalLinks, $url) || !$isAuth))
                $url = '';

            $track["url"] = $url;
        }
        $this->addVar("tracks", $tracks);
    }

    public function doSearch() {
        if(array_key_exists('n', $_REQUEST))
            $this->searchText = stripslashes($_REQUEST['n']);

        $this->searchType =
                array_key_exists('s', $_REQUEST)?$_REQUEST['s']:"";
        $this->dispatchAction($this->searchType, self::$legacySearchActions);
    }
    
    public function searchForm() {
        $this->setTemplate("search.library.html");
        $this->addVar('search', $this);
        $this->addVar('type', self::$typeFromLegacy[$this->searchType] ?? "all");
        $this->addVar('welcome', empty($this->searchType));
    }
}
