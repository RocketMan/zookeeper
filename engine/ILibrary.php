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

namespace ZK\Engine;

/**
 * Library operations
 */
interface ILibrary {
    const GENRES = [
        "B"=>"Blues",
        "C"=>"Country",
        "G"=>"General",
        "H"=>"Hip-hop",
        "J"=>"Jazz",
        "K"=>"Childrens",
        "L"=>"Classical",
        "N"=>"Novelty",
        "O"=>"Comedy",
        "P"=>"Spoken Word",
        "R"=>"Reggae",
        "S"=>"Soundtrack",
        "W"=>"World",
    ];

    const MEDIA = [
        "C"=>"CD",
        "M"=>"Cassette",
        "S"=>"7\"",
        "T"=>"10\"",
        "V"=>"12\"",
    ];

    const LENGTHS = [
        "E"=>"EP",
        "F"=>"Full",
        "S"=>"Single",
    ];

    const LOCATIONS = [
        "D"=>"Received",
        "E"=>"Review Shelf",
        "F"=>"Out for Review",
        "H"=>"Pending Appr",
        "C"=>"A-File",
        "G"=>"Storage",
        "L"=>"Library",
        "I"=>"Digital",
        "M"=>"Missing",
        "R"=>"Needs Repair",
        "U"=>"Deaccessioned",
    ];

    const ALBUM_AIRNAME = 0;
    const ALBUM_ARTIST = 1;
    const ALBUM_KEY = 2;
    const ALBUM_NAME = 3;
    const ALBUM_PUBKEY = 4;
    const COLL_KEY = 5;
    const LABEL_NAME = 6;
    const LABEL_PUBKEY = 7;
    const PASSWD_NAME = 8;
    const TRACK_KEY = 9;
    const TRACK_NAME = 10;

    const OP_PREV_LINE = 0;
    const OP_NEXT_LINE = 1;
    const OP_PREV_PAGE = 2;
    const OP_NEXT_PAGE = 3;
    const OP_BY_NAME = 4;
    const OP_BY_TAG = 5;

    function search($tableIndex, $pos, $count, $search, $sortBy = 0);
    function searchPos($tableIndex, &$pos, $count, $search, $sortBy = 0);
    function linkReviews(&$albums, $loggedIn = false, $includeBody = false);
    function markAlbumsReviewed(&$albums, $loggedIn = 0);
    function markAlbumsPlayable(&$albums);
    function listAlbums($op, $key, $limit);
    function listLabels($op, $key, $limit);
    function searchFullText($type, $key, $size, $offset);
}
