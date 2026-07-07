<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2024 Jim Mason <jmason@ibinx.com>
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
use ZK\Engine\IArtwork;
use ZK\Engine\ILibrary;
use ZK\Engine\IPlaylist;
use ZK\Engine\PlaylistEntry;
use ZK\Engine\PlaylistObserver;
use ZK\Service\PushServer;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class ArtworkControl implements IController {
    private const DISCOGS_BASE = "https://www.discogs.com";
    private const DISCOGS_SEARCH = "https://api.discogs.com/database/search";

    protected $discogs;
    protected $verbose = false;

    protected function setupDiscogs() {
        $config = Engine::param('discogs');
        if($config) {
            $apiKey = $config['apikey'] ?? null;
            $clientId = $config['client_id'] ?? null;
            $clientSecret = $config['client_secret'] ?? null;

            if($apiKey || $clientId && $clientSecret) {
                $this->discogs = new Client([
                    'base_uri' => self::DISCOGS_SEARCH,
                    RequestOptions::HEADERS => [
                        'User-Agent' => Engine::UA,
                        'Authorization' => $apiKey ?
                            "Discogs token=$apiKey" :
                            "Discogs key=$clientId, secret=$clientSecret"
                    ]
                ]);
            }
        }
    }

    public function reloadAlbum($tag, $master, $skip) {
        $this->setupDiscogs();

        $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $tag);
        if(!count($albums)) {
            echo "reloadAlbum($tag): tag not found\n";
            return;
        }

        $album = $albums[0];

        try {
            switch($album["medium"] ?? null) {
            case 'S':
                $format = "Vinyl, 7\"";
                break;
            case 'T':
            case 'V':
                $format = "Vinyl";
                break;
            case 'M':
                $format = "Cassette";
                break;
            default:
                $format = "CD";
                break;
            }

            $params = [
                "artist" => $album["iscoll"] ?
                    "Various" : PlaylistEntry::swapNames($album["artist"]),
                "release_title" => $album["album"],
                "per_page" => 20
            ];

            if($master)
                $params["type"] = "master";
            else
                $params["format"] = $format;

            $response = $this->discogs->get('', [
                RequestOptions::QUERY => $params
            ]);

            $page = $response->getBody()->getContents();
            $json = json_decode($page);
            if($json->results && ($result2 = $json->results[0])) {
                foreach($json->results as $r) {
                    if($skip-- > 0)
                        continue;

                    // master releases are definitive
                    if($r->type == "master") {
                        $result2 = $r;
                        break;
                    }

                    // ignore promos and limited/special editions
                    if(array_reduce($r->format,
                            function($carry, $item) {
                                return $carry ||
                                    $item == "Promo" ||
                                    strpos($item, "Edition") !== false;
                            }))
                        continue;

                    // prefer CD or vinyl
                    switch($r->format[0]) {
                    case "CD":
                    case "Vinyl":
                        $result2 = $r;
                        break;
                    }
                }

                $imageUrl = $result2->cover_image &&
                        !preg_match('|/spacer.gif$|', $result2->cover_image) ?
                    $result2->cover_image : null;
                $infoUrl = self::DISCOGS_BASE . $result2->uri;
            }

            if(!empty($imageUrl)) {
                $imageApi = Engine::api(IArtwork::class);
                $imageApi->deleteAlbumArt($tag);
                $uuid = $imageApi->insertAlbumArt($tag, $imageUrl, $infoUrl);
                echo "reloadAlbum($tag): ".($master?'master':$format)." loaded $uuid\n";
            } else
                echo "reloadAlbum($tag): no image found\n";
        } catch(\Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }

    protected function refreshList($playlist) {
        $count = 0;
        $imageApi = Engine::api(IArtwork::class);
        Engine::api(IPlaylist::class)->getTracksWithObserver($playlist,
            (new PlaylistObserver())->on('spin', function($entry) use($imageApi, &$count) {
                if(!$entry->getTag() && $entry->getCreated()) {
                    $artist = $entry->getArtist();
                    if($this->verbose)
                        echo "    deleting $artist\n";
                    $imageApi->deleteArtistArt($artist);
                    $count++;
                }
            })
        );

        if($count) {
            echo "$count images queued for reload (please wait)\n";
            PushServer::lazyLoadImages($playlist);
        } else
            echo "No artist artwork found.  No change.\n";
    }

    public function processRequest() {
        if(php_sapi_name() != "cli") {
            http_response_code(400);
            return;
        }

        // The heavy lifting is done by the push notification server.
        // If it is not enabled, there is no point in proceeding.
        if(!Engine::param('push_enabled', true)) {
            echo "Push notification is disabled.  No change.\n";
            return;
        }

        $this->verbose = $_REQUEST["verbose"] ?? false;

        switch($_REQUEST["action"] ?? "") {
        case "delete":
            $imageApi = Engine::api(IArtwork::class);
            if($tag = $_REQUEST["tag"] ?? null) {
                $success = $imageApi->deleteAlbumArt($tag);
                echo $success ? "Deleted album art\n" : "Album not found\n";
                break;
            } else if($artist = $_REQUEST["artist"] ?? null) {
                $success = $imageApi->deleteArtistArt($artist);
                echo $success ? "Deleted artist art\n" : "Artist not found\n";
                break;
            }
            echo "Usage: zk artwork:delete {tag|artist}=id|name\n";
            break;
        case "reload":
            if($tag = $_REQUEST["tag"] ?? null) {
                $this->reloadAlbum($tag, $_REQUEST["master"] ?? 1, $_REQUEST["skip"] ?? 0);
                break;
            } else if($list = $_REQUEST["list"] ?? null) {
                $this->refreshList($list);
                break;
            }
            // fall through...
        default:
            echo "Usage: zk artwork:reload {tag|list}=id [master=0] [skip=0]\n";
            break;
        }
    }
}
