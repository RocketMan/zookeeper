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
use ZK\Engine\IPlaylist;
use ZK\Engine\PlaylistEntry;
use ZK\Engine\PlaylistObserver;

class ArtworkControl implements IController {
    protected $verbose = false;

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
                echo "Album queued for reload (please wait)\n";
                PushServer::lazyReloadAlbum($tag, $_REQUEST["master"] ?? 1, $_REQUEST["skip"] ?? 0);
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
