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

namespace ZK\Engine;

/**
 * Playlist operations
 */
interface IPlaylist {
    const SPECIAL_TRACK = "~~~~~~~~";
    const COMMENT_FLAG = "C";

    function getShowdates($year, $month);
    function getPlaylist($playlist, $withAirname=0);
    function getPlaylists($onlyPublished=0, $withAirname=0,
                               $showDate="", $airname="", $user="", $desc=1);
    function getPlaylistsByAirname($airname);
    function getPlaylistsByUser($user, $onlyPublished=0, $withAirname=0);
    function getPlaylistsByDate($date);
    function getWhatsOnNow();
    function insertPlaylist($user, $date, $time, $description, $airname);
    function updatePlaylist($playlist, $date, $time, $description, $airname);
    function getTrack($id);
    function getTracks($playlist, $desc = 0);
    function insertTrack($playlist, $tag, $artist, $track, $album, $label, $wantTimestamp);
    function updateTrack($playlistId, $id, $tag, $artist, $track, $album, $label);
    function deleteTrack($id);
    function getTopPlays(&$result, $airname=0, $days=41, $count=10);
    function getLastPlays($tag, $count=0);
    function getRecentPlays(&$result, $airname, $count);
    function deletePlaylist($playlist);
    function restorePlaylist($playlist);
    function purgeDeletedPlaylists();
    function getDeletedPlaylistCount($user);
    function getListsSelNormal($user);
    function getListsSelDeleted($user);
    function moveTrackUpDown($playlist, &$id, $up);
}
