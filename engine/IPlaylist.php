<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2022 Jim Mason <jmason@ibinx.com>
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
    /**
     * suffix appended to duplicate playlist name
     *
     * date format specifiers may be included inside %...%
     */
    const DUPLICATE_SUFFIX = " (rebroadcast from %M j, Y%)";

    /**
     * regular expression to match the name of a duplicate playlist
     *
     * should match (some substring of) DUPLICATE_SUFFIX
     */
    const DUPLICATE_REGEX = "/\Wrebroadcast\W/i";

    /**
     * comment inserted at beginning of duplicate playlist
     *
     * date format specifiers may be included inside %...%
     */
    const DUPLICATE_COMMENT =
        "Rebroadcast of an episode originally aired on %F j, Y%.";

    /**
     * internal datetime formats (do not change)
     */
    const TIME_FORMAT = "Y-m-d Hi"; // eg, 2019-01-01 1234
    const TIME_FORMAT_SQL = "Y-m-d H:i:s"; // 2019-01-01 12:34:56

    const SPECIAL_TRACK = "~~~~~~~~";
    const COMMENT_FLAG = "C";
    const LOG_FLAG = "L";

    const MAX_DESCRIPTION_LENGTH = 80;

    function getShowdates($year, $month);
    function getPlaylist($playlist, $withAirname=0);
    function getPlaylists($onlyPublished=0, $withAirname=0,
                               $showDate="", $airname="", $user="", $desc=1);
    function getPlaylistsByAirname($airname);
    function getPlaylistsByUser($user, $onlyPublished=0, $withAirname=0);
    function getPlaylistsByDate($date);
    function getWhatsOnNow();
    function isNowWithinShow($listRow, $allowGrace = true);
    function insertPlaylist($user, $date, $time, $description, $airname);
    function updatePlaylist($playlist, $date, $time, $description, $airname,
                               $deleteTracksPastEnd=0);
    function duplicatePlaylist($playlist);
    function reparentPlaylist($playlist, $user);
    function getSeq($list, $id);
    function moveTrack($list, $id, $toId, $clearTimestamp=true);
    function getTrack($id);
    function getTracks($playlist, $desc = 0);
    function getTracksWithObserver($playlist, PlaylistObserver $observer, $desc = 0, $filter = null);
    function getTrackCount($playlist);
    function getTimestampWindow($playlistId);
    function insertTrack($playlistId, $tag, $artist, $track, $album, $label, $spinTimestamp, &$id, &$status);
    function updateTrack($playlistId, $id, $tag, $artist, $track, $album, $label, $dateTime);
    function insertTrackEntry($playlistId, PlaylistEntry $entry, &$status);
    function updateTrackEntry($playlist, PlaylistEntry $entry);
    function deleteTrack($id);
    function getTopPlays($airname=0, $days=41, $count=10);
    function getLastPlays($tag, $count=0);
    function getRecentPlays($airname, $count);
    function getPlaysBefore($timestamp, $limit);
    function deletePlaylist($playlist);
    function restorePlaylist($playlist);
    function purgeDeletedPlaylists($days=30);
    function getDeletedPlaylistCount($user);
    function getListsSelNormal($user);
    function getListsSelDeleted($user);
    function isListDeleted($id);
}
