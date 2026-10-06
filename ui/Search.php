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

use ZK\Engine\ConfigInterface;
use ZK\Engine\IArtwork;
use ZK\Engine\IChart;
use ZK\Engine\ILibrary;
use ZK\Engine\IPlaylist;
use ZK\Engine\IReview;
use ZK\Engine\Request;
use ZK\Engine\Session;

use ZK\UI\UICommon as UI;

class Search extends MenuItem {
    const HASHTAG_PALETTE_SIZE = 5;  // css colours palette-0..palette-(n-1)

    private static $legacySearchActions = [
        [ "", "searchForm" ],
        [ "byAlbumKey", "searchByAlbumKey" ],
    ];

    private static $typeFromLegacy = [
        "byAlbum" => "albums",
        "byArtist" => "artist",
        "byTrack" => "tracks",
        "byLabel" => "labels",
        "byLabelKey" => "albumsByPubkey",
        "byHashtag" => "hashtags"
    ];

    public $searchText;

    public $searchType;

    public function __construct(
        protected ConfigInterface $config,
        protected Request $request,
        protected Session $session,
        protected TemplateFactoryUI $templateFactory,
        protected ILibrary $libraryDBO,
        protected IArtwork $imageDBO,
        protected IChart $chartDBO,
        protected IPlaylist $playlistDBO,
        protected IReview $reviewDBO,
    ) {
        parent::__construct($session, $templateFactory);
    }

    public function processLocal($action, $subaction) {
        if(array_key_exists('n', $_REQUEST))
            $this->searchText = stripslashes($_REQUEST['n']);

        $this->searchType =
                array_key_exists('s', $_REQUEST)?$_REQUEST['s']:"";
        $this->dispatchAction($this->searchType, self::$legacySearchActions);
    }

    public function searchByAlbumKey($key = null) {
        $tag = $key ?? $this->searchText;

        $albums = $this->libraryDBO->search(ILibrary::ALBUM_KEY, 0, 1, $tag);

        $this->setTemplate("album/view.html");

        if(!count($albums)) {
            $this->addVar("album", null);
            return;
        }

        $this->addVar("album", $albums[0]);
        $this->addVar("GENRES", ILibrary::GENRES);
        $this->addVar("MEDIA", ILibrary::MEDIA);
        $this->addVar("DATE_FORMAT_FULL", $this->request->isUsLocale() ? 'M d, Y' : 'd M Y');
        $this->addVar("DATE_FORMAT_SHORT", $this->request->isUsLocale() ? 'M j' : 'j M');

        // album art
        $image = $this->imageDBO->getAlbumArt($tag);
        if($image && ($uuid = $image["image_uuid"])) {
            $this->addVar("image_url", $this->imageDBO->getCachePath($uuid));
            $this->addVar("info_url", $image["info_url"]);
        }

        // report missing
        if($loggedIn = $this->session->isAuth("u")) {
            $urls = $this->config->get('urls');
            if(array_key_exists('report_missing', $urls)) {
                $url = str_replace('%USERNAME%', UI::URLify($this->session->getDN()), $urls['report_missing']);
                $url = str_replace('%ALBUMTAG%', $tag, $url);
                $this->addVar("report_missing_url", $url);
            }
        }

        // currents
        $rows = $this->chartDBO->getAlbumByTag($tag);
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
            $plays = $this->chartDBO->getAlbumPlays($tag, $row["adddate"], $row["pulldate"], 8)->asArray();
            $row['spins'] = $plays;
            $accepted[] = $row;
        }
        $this->addVar("currents", $accepted);
        $this->addVar("CATMAP", $this->chartDBO->getCategories());

        // recent airplay
        $plays = $this->playlistDBO->getLastPlays($tag, 6);
        $this->addVar("recent", $plays);

        // reviews
        $reviews = $this->reviewDBO->getReviews($tag, 1, "", $loggedIn);
        $this->addVar("reviews", $reviews);

        // hashtags
        $hashtags = array_reduce(array_reverse($reviews), function($carry, $review) {
            return !$review['private'] && preg_match_all('/#\pL\w*/u', $review['review'], $matches) ?
                array_merge($carry, $matches[0]) : $carry;
        }, []);
        $normalized = array_unique(array_map('strtolower', $hashtags));
        $hashtags = array_intersect_key($hashtags, $normalized);
        $index = array_map(function($tag) {
            return hexdec(hash('crc32', $tag)) % self::HASHTAG_PALETTE_SIZE;
        }, $normalized);
        $this->addVar("hashtags", array_map(function($hash, $index) {
            return [ 'name' => $hash, 'index' => $index ];
        }, $hashtags, $index));

        // tracks
        $tracks = $this->libraryDBO->search($albums[0]['iscoll'] ? ILibrary::COLL_KEY : ILibrary::TRACK_KEY, 0, 200, $tag);

        $isAuth = $this->session->isAuth('u');
        $internalLinks = $this->config->get('internal_links');
        $enableExternalLinks = $this->config->get('external_links_enabled');
        foreach($tracks as &$track) {
            if($track["duration"])
                $track["duration"] = preg_replace("/^0(0:0?)?/", "", $track["duration"]);

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

    public function searchForm() {
        $this->setTemplate("search.library.html");
        $this->addVar('search', $this);
        $this->addVar('type', self::$typeFromLegacy[$this->searchType] ?? "all");
        $this->addVar('welcome', empty($this->searchType));
    }
}
