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

namespace ZK\Engine;


/**
 * Library operations
 */
class LibraryImpl extends DBO implements ILibrary {
    const DEFAULT_FT_LIMIT = 35;
    const MAX_FT_LIMIT = 200;

    private static $ftSearch = [
         //   elt name   rec name    table    index    fields     query
         [ "tags", "albumrec", "albumvol", "tag",
                  "SELECT * FROM albumvol " .
                  "LEFT JOIN publist ON albumvol.pubkey = publist.pubkey " .
                  "WHERE FIND_IN_SET(tag, ?)" ],
         [ "albums", "albumrec", "albumvol", "artist,album",
                  "SELECT * FROM albumvol " .
                  "LEFT JOIN publist ON albumvol.pubkey = publist.pubkey " .
                  "WHERE MATCH (artist,album) AGAINST(? IN BOOLEAN MODE) ".
                  "AND location != 'U' " .
                  "ORDER BY artist, album, tag" ],
         [ "artists", "albumrec", null, "artist",
                  "SELECT tag, artist, album, category, medium, " .
                  "created, updated, a.pubkey, location, bin, iscoll, " .
                  "name, address, city, state, zip, attention, phone, fax, " .
                  "international, pcreated, modified, p.url, email " .
                  "FROM albumvol a " .
                  "LEFT JOIN publist p ON a.pubkey = p.pubkey " .
                  "WHERE MATCH (artist) AGAINST(? IN BOOLEAN MODE) " .
                  "AND location != 'U' " .
                  "AND iscoll = 0 " .
                  "UNION SELECT c.tag, c.artist, album, category, medium, " .
                  "created, updated, a.pubkey, location, bin, iscoll, " .
                  "name, address, city, state, zip, attention, phone, fax, " .
                  "international, pcreated, modified, p.url, email " .
                  "FROM colltracknames c " .
                  "LEFT JOIN albumvol a ON a.tag = c.tag " .
                  "LEFT JOIN publist p ON a.pubkey = p.pubkey " .
                  "WHERE MATCH (c.artist) AGAINST(? IN BOOLEAN MODE) " .
                  "AND location != 'U' " .
                  "GROUP BY tag " .
                  "ORDER BY artist, album, tag" ],
         [ "compilations", "albumrec", "colltracknames", "artist,track",
                  "SELECT c.tag, c.artist, album, category, medium, size, ".
                  "location, bin, created, updated, track, seq, url, pubkey, ".
                  "1 iscoll, duration FROM colltracknames c " .
                  "LEFT JOIN albumvol ON albumvol.tag = c.tag " .
                  "WHERE MATCH (c.artist,track) AGAINST(? IN BOOLEAN MODE) ".
                  "AND location != 'U' " .
                  "ORDER BY c.artist, album, c.tag, c.seq" ],
         [ "labels", "labelrec", "publist", "name",
                  "SELECT * FROM publist " .
                  "WHERE MATCH (name) AGAINST(? IN BOOLEAN MODE) ".
                  "ORDER BY name" ],
         [ "playlists", "playlistrec", "tracks", "artist,album,track",
                  "SELECT list, description, a.airname, showdate, " .
                  "artist, album, track, showtime, origin FROM tracks t " .
                  "LEFT JOIN lists l ON t.list = l.id " .
                  "LEFT JOIN airnames a ON l.airname = a.id " .
                  "WHERE l.airname IS NOT NULL AND " .
                  "t.artist NOT LIKE '" . IPlaylist::SPECIAL_TRACK . "%' AND " .
                  "MATCH (artist,album,track) AGAINST(? IN BOOLEAN MODE) " .
                  "ORDER BY showdate DESC, list DESC, t.id" ],
         [ "reviews", "reviewrec", "reviews", "review",
                  "SELECT r.tag, av.artist, av.album, an.airname, " .
                  "DATE_FORMAT(r.created, GET_FORMAT(DATE, 'ISO')) reviewed, r.id, u.realname " .
                  "FROM reviews r " .
                  "LEFT JOIN albumvol av ON r.tag = av.tag " .
                  "LEFT JOIN airnames an ON r.airname = an.id " .
                  "LEFT JOIN users u ON r.user = u.name " .
                  "WHERE private = 0 AND " .
                  "MATCH (review) AGAINST(? IN BOOLEAN MODE) " .
                  "ORDER BY r.created DESC" ],
         [ "tracks", "albumrec", "tracknames", "track",
                  "SELECT t.tag, artist, album, category, medium, size, ".
                  "location, bin, created, updated, track, seq, url, pubkey, ".
                  "0 iscoll, duration FROM tracknames t ".
                  "LEFT JOIN albumvol ON albumvol.tag = t.tag ".
                  //"LEFT JOIN publist ON albumvol.pubkey = publist.pubkey ".
                  "WHERE MATCH (track) AGAINST(? IN BOOLEAN MODE) ".
                  "AND location != 'U' " .
                  "ORDER BY artist, album, t.tag" ]
    ];

    /**
     * characters in each element are coalesced for searching
     */
    private static $coalesce = [
        "'\u{0060}\u{00b4}\u{2018}\u{2019}", // single quotation mark
        "\"\u{201c}\u{201d}",                // double quotation mark
    ];

    /*
     * words to exclude from a full-text search
     */
    private static $ftExclude = [
        "a", "an", "and", "or", "the"
    ];

    private static function orderBy($sortBy) {
        if(substr($sortBy, -1) == "-") {
            $sortBy = substr($sortBy, 0, -1);
            $desc = " DESC";
        } else
            $desc = "";

        switch(strtolower($sortBy)) {
        case "album":
            $query = "ORDER BY album$desc, artist$desc, tag ";
            break;
        case "label":
            $query = "ORDER BY name$desc, album$desc, artist$desc ";
            break;
        case "created":
        case "added":
            $query = "ORDER BY a.created$desc, artist$desc ";
            break;
        case "date":
            $query = "ORDER BY r.created$desc ";
            break;
        case "track":
            $query = "ORDER BY track$desc, artist$desc, album$desc ";
            break;
        default:
            // "Artist"
            $query = "ORDER BY artist$desc, album$desc, tag ";
            break;
        }
        return $query;
    }

    public function search($tableIndex, $pos, $count, $search, $sortBy = 0) {
        return $this->searchPos($tableIndex, $pos, $count, $search, $sortBy);
    }

    public function searchPos($tableIndex, &$pos, $count, $search, $sortBy = 0) {
        $retVal = array();
      
        // 2007-08-04 thwart injection attacks by aborting if we encounter
        // 'union select'
        $searchl = strtolower($search);
        $posUnion = strpos($searchl, "union");
        $posSelect = strpos($searchl, "select");
        if($posUnion !== FALSE && $posSelect > $posUnion)
            return $count >= 0?$retVal:0;
      
        // select one more than requested (for pagination)
        $count += 1;
        $olen = strlen($search);
      
        // JM 2006-11-28 escape '_', '%'
        $search = preg_replace('/([_%])/', '\\\\$1', $search);
      
        // remove semicolons to thwart injection attacks
        $search = preg_replace('/([;])/', '_', $search);

        if(substr($search, strlen($search)-1, 1) == "*")
            $search = substr($search, 0, strlen($search)-1)."%";

        switch($tableIndex) {
        case ILibrary::ALBUM_ARTIST:
            $query = "SELECT tag, artist, album, category, medium, size, ".
                     "created, updated, a.pubkey, location, bin, iscoll, ".
                     "name, address, city, state, zip, ".
                     "attention, phone, fax, international, mailcount, ".
                     "maillist, pcreated, modified, p.url, email ".
                     "FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey ".
                     "WHERE artist LIKE ? ".
                     "UNION SELECT c.tag, c.artist, a.artist, category, medium, size, ".
                     "created, updated, a.pubkey, location, bin, iscoll, ".
                     "name, address, city, state, zip, ".
                     "attention, phone, fax, international, mailcount, ".
                     "maillist, pcreated, modified, p.url, email ".
                     "FROM colltracknames c, albumvol a LEFT JOIN publist p ".
                     "ON a.pubkey = p.pubkey WHERE c.tag = a.tag ".
                     "AND c.artist LIKE ? ".
                     "GROUP BY c.tag ".
                     self::orderBy($sortBy).
                     "LIMIT ?, ?";
            $bindType = 4;
            break;
        case ILibrary::ALBUM_KEY:
            settype($search, "integer");
            $query = "SELECT * FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey WHERE tag=? LIMIT ?, ?";
            $bindType = 3;
            break;
        case ILibrary::ALBUM_NAME:
            $query = "SELECT tag, artist, album, category, medium, size, ".
                     "created, updated, a.pubkey, location, bin, iscoll, ".
                     "name, address, city, state, zip, ".
                     "attention, phone, fax, international, mailcount, ".
                     "maillist, pcreated, modified, p.url, email ".
                     "FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey ".
                     "WHERE album LIKE ? ".
                     self::orderBy($sortBy).
                     "LIMIT ?, ?";
            $bindType = 3;
            break;
        case ILibrary::ALBUM_PUBKEY:
            settype($search, "integer");
            $query = "SELECT tag, artist, album, category, medium, size, ".
                     "created, updated, a.pubkey, location, bin, iscoll, ".
                     "name, address, city, state, zip, ".
                     "attention, phone, fax, international, mailcount, ".
                     "maillist, pcreated, modified, p.url, email ".
                     "FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey ".
                     "WHERE a.pubkey=? ".
                     self::orderBy($sortBy).
                     "LIMIT ?, ?";
            $bindType = 3;
            break;
        case ILibrary::COLL_KEY:
            settype($search, "integer");
            $query = "SELECT * FROM colltracknames WHERE tag=? ORDER BY seq LIMIT ?, ?";
            $bindType = 3;
            break;
        case ILibrary::LABEL_NAME:
            $query = "SELECT * FROM publist WHERE name LIKE ? LIMIT ?, ?";
            $bindType = 3;
            break;
        case ILibrary::LABEL_PUBKEY:
            $query = "SELECT * FROM publist WHERE pubkey=?";
            $bindType = 1;
            break;
        case ILibrary::TRACK_KEY:
            $query = "SELECT * FROM tracknames WHERE tag=? ORDER BY seq LIMIT ?, ?";
            $bindType = 3;
            break;
        case ILibrary::PASSWD_NAME:
            $query = "SELECT * FROM users WHERE name LIKE ? ORDER BY name LIMIT ?, ?";
            $bindType = 3;
            break;
        case ILibrary::TRACK_NAME:
            // JM 2006-11-27 disallow wildcard track searches on short strings
            // to prevent interminable UNION filesort
            if($olen < 4 && substr($search, -1) == "%")
                $search = substr($search, 0, -1);
      
            $query = "SELECT a.tag, track, artist, album, seq, ".
                     "category, medium, size, location, bin, ".
                     "a.pubkey, name, address, city, state, zip, iscoll, ".
                     "t.url, t.duration ".
                     "FROM tracknames t, albumvol a ".
                     "LEFT JOIN publist p ON a.pubkey = p.pubkey ".
                     "WHERE a.tag = t.tag AND ".
                     "track LIKE ? ".
                     "UNION SELECT a.tag, track, c.artist, a.artist album, seq, ".
                     "category, medium, size, location, bin, ".
                     "a.pubkey, name, address, city, state, zip, iscoll, ".
                     "c.url, c.duration ".
                     "FROM colltracknames c LEFT JOIN albumvol a ON a.tag = c.tag ".
                     "LEFT JOIN publist p ON a.pubkey = p.pubkey ".
                     "WHERE track LIKE ? ".
                     self::orderBy($sortBy).
                     "LIMIT ?, ?";
            $bindType = 4;
            break;
        case ILibrary::ALBUM_AIRNAME:
            $query = "SELECT r.id, artist, album, category, medium, ".
                     "size, a.created, a.updated, a.pubkey, location, bin, a.tag, iscoll, ".
                     "p.name, DATE_FORMAT(r.created, GET_FORMAT(DATE, 'ISO')) reviewed ".
                     "FROM reviews r LEFT JOIN albumvol a ON a.tag = r.tag ".
                     "LEFT JOIN publist p ON p.pubkey = a.pubkey ";
            $query .= is_numeric($search) ?
                     ($search ? "WHERE r.airname = ? " : "WHERE ? = 0 ") :
                     "WHERE r.user = ? AND r.airname IS NULL ";
            if(!Engine::session()->isAuth("u"))
                $query .= "AND r.private = 0 ";
            $query .= self::orderBy($sortBy);
            $query .= "LIMIT ?, ?";
            $bindType = 3;
            break;
        case ILibrary::ALBUM_LOCATION:
            $query = "SELECT * FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey WHERE location=? ";
            $bindType = 3;
            $params = explode('|', $search);
            if(count($params) > 1) {
                $query .= "AND bin=? ";
                [ $search, $search2 ] = array_slice($params, 0, 2);
                $bindType++;
            }
            $query .= self::orderBy($sortBy);
            $query .= "LIMIT ?, ?";
            break;
        case ILibrary::ALBUM_HASHTAG:
            $query = "SELECT artist, album, category, medium, size, ".
                     "a.created, a.updated, a.pubkey, location, bin, a.tag, iscoll, p.name ".
                     "FROM reviews_hashtags r LEFT JOIN albumvol a ON a.tag = r.tag ".
                     "LEFT JOIN publist p ON p.pubkey = a.pubkey ".
                     "WHERE hashtag = ? GROUP BY r.tag ";
            $query .= self::orderBy($sortBy);
            $query .= "LIMIT ?, ?";
            $bindType = 3;
            break;
        default:
            error_log("searchPos: unknown key '$tableIndex'");
            return;
        }

        // Collation for utf8mb4 coalesces related characters, such as
        // 'a', 'a-umlaut', 'a-acute', and so on, for searching.  However,
        // it does not coalese various punctuation, such as apostrophe
        // and right single quotation mark U+2019 (’).
        //
        // If the search string includes any such punctuation, we will
        // massage the query to match related characters as well.
        //
        // We employ MySQL RLIKE (REGEXP) for this purpose; however,
        // it is expensive; thus, we let LIKE do the heavy lifting in
        // a derived table and then run RLIKE over the result.
        $cchars = implode(self::$coalesce);
        if(preg_match("/[$cchars]/u", $search) &&
                preg_match('/(\w+) LIKE /', $query, $matches)) {
            $key = $matches[1];

            $rlike = preg_quote($search);
            foreach(self::$coalesce as $c) {
                // pre-MySQL 8, RLIKE is not unicode-aware, so do bytewise test
                $rlike = preg_replace("/[$c]/u", "(" .
                    implode("|", preg_split("//u", $c, 0, PREG_SPLIT_NO_EMPTY)) .
                    ")", $rlike);
            }

            if(substr($rlike, -1) == "%")
                $rlike = substr($rlike, 0, -1);

            $search = preg_replace("/[$cchars]/u", "_", $search);
        } else
            $rlike = null;

        if($count > 0) {
            if($rlike)
                $query = "SELECT * FROM ( $query ) x WHERE $key RLIKE ?";

            $stmt = $this->prepare($query);
            switch($bindType) {
            case 1:
                $stmt->bindValue(1, $search);
                break;
            case 3:
                $stmt->bindValue(1, $search);
                $stmt->bindValue(2, (int)$pos, \PDO::PARAM_INT);
                $stmt->bindValue(3, (int)$count, \PDO::PARAM_INT);
                break;
            case 4:
                $stmt->bindValue(1, $search);
                $stmt->bindValue(2, $search2 ?? $search);
                $stmt->bindValue(3, (int)$pos, \PDO::PARAM_INT);
                $stmt->bindValue(4, (int)$count, \PDO::PARAM_INT);
                break;
            }

            if($rlike)
                $stmt->bindValue($bindType + 1, $rlike);

            $stmt->execute();

            // copy the requested rows
            $askCount = $count;
            while(--$count && ($row = $stmt->fetch()))
                $retVal[] = $row;

            // adjust the pagination position if there are more rows
            $pos = (!$count && $stmt->fetch())?$pos + $askCount - 1:0;
        } else {
            // caller has requested total row count instead of data

            // strip ORDER BY and/or LIMIT clauses
            $ob = strpos($query, " ORDER BY");
            if($ob)
                $query = substr($query, 0, $ob);
            else if($lim = strpos($query, " LIMIT"))
                $query = substr($query, 0, $lim);

            // For UNION queries and for queries which contain GROUP BY,
            // we must count the number of aggregate rows.
            //
            // This will work also for simple queries, but in that
            // case, we just do a count, as it's a tad more efficient.
            if(strpos($query, " UNION SELECT ") ||
                    strpos($query, " GROUP BY ")) {
                $query = "SELECT COUNT(*) FROM (" . $query . ") x";
            } else {
                $from = strpos($query, "FROM");
                $query = $rlike ? "SELECT COUNT(*) FROM ( SELECT $key " .
                        substr($query, $from) . " ) x" :
                        "SELECT COUNT(*) " . substr($query, $from);
            }

            if($rlike)
                $query .= " WHERE $key RLIKE ?";

            $stmt = $this->prepare($query);
            switch($bindType) {
            case 1:
            case 3:
                $stmt->bindValue(1, $search);
                break;
            case 4:
                $stmt->bindValue(1, $search);
                $stmt->bindValue(2, $search2 ?? $search);
                break;
            }

            if($rlike)
                $stmt->bindValue($bindType < 4 ? 2 : 3, $rlike);

            $retVal = $stmt->execute() && ($row = $stmt->fetch())?$row[0]:-1;
        }
      
        return $retVal;
    }

    private static function displayName($airname, $realname) {
        return $airname?$airname:$realname;
    }
    
    public function linkReviews(&$albums, $loggedIn = false, $includeBody = false) {
        $chain = [];
        $tags = [];
        for($i = 0; $albums != null && $i < sizeof($albums); $i++) {
            $tag = array_key_exists("tag", $albums[$i])?$albums[$i]["tag"]:0;
            if($tag && is_numeric($tag)) {
                if(array_key_exists($tag, $tags))
                    $chain[$i] = $tags[$tag];

                $tags[$tag] = $i;
            }
        }

        if(count($tags) == 0)
            return;

        $ib = $includeBody?"":"null";
        $query = "SELECT tag, a.airname, realname, r.id, r.created, r.private, " .
                 "$ib review FROM reviews r " .
                 "LEFT JOIN users u ON r.user = u.name " .
                 "LEFT JOIN airnames a ON r.airname = a.id WHERE " .
                 "tag IN (" . implode(',', array_keys($tags)) . ")";
        if(!$loggedIn)
            $query .= " AND private = 0";
        $query .= " GROUP BY tag";
        $stmt = $this->prepare($query);
        $stmt->execute();
        while($row = $stmt->fetch()) {
            $review = [
                "id" => $row['id'],
                "airname" => self::displayName($row[1], $row[2]),
                "private" => $row['private'],
                "reviewed" => $row['created'],
                "review" => $row['review']
            ];
            for($next = $tags[$row[0]]; $next >= 0; $next = array_key_exists($next, $chain)?$chain[$next]:-1) {
                $reviews = $albums[$next]["reviews"] ?? [];
                $reviews[] = $review;
                $albums[$next]["reviews"] = $reviews;
            }
        }
    }

    // For a given $albums array, add 'reviewed' and 'reviewer' columns
    // for each album which has at least one music review.
    //
    public function markAlbumsReviewed(&$albums, $loggedIn = 0) {
        $chain = [];
        $tags = [];
        for($i = 0; $albums != null && $i < sizeof($albums); $i++) {
            $tag = array_key_exists("tag", $albums[$i])?$albums[$i]["tag"]:0;
            if($tag && is_numeric($tag)) {
                if(array_key_exists($tag, $tags))
                    $chain[$i] = $tags[$tag];

                $tags[$tag] = $i;
            }
        }

        if(count($tags) == 0)
            return;

        $query = "SELECT tag, a.airname, realname FROM reviews r " .
                 "LEFT JOIN users u ON r.user = u.name " .
                 "LEFT JOIN airnames a ON r.airname = a.id WHERE " .
                 "tag IN (" . implode(',', array_keys($tags)) . ")";
        if(!$loggedIn)
            $query .= " AND private = 0";
        $query .= " GROUP BY tag";
        $stmt = $this->prepare($query);
        $stmt->execute();
        while($row = $stmt->fetch()) {
            for($next = $tags[$row[0]]; $next >= 0; $next = array_key_exists($next, $chain)?$chain[$next]:-1) {
                $albums[$next]["reviewed"] = 1;
                $albums[$next]["reviewer"] = self::displayName($row[1], $row[2]);
            }
        }
    }

    // For a given $albums array, add a 'playable' column
    // for each album which has at least one playable track.
    //
    public function markAlbumsPlayable(&$albums) {
        $enableExternalLinks = Engine::param('external_links_enabled');
        $internalLinks = Engine::param('internal_links');

        if(!$enableExternalLinks && !$internalLinks)
            return;

        $chain = [];
        $tags = [];
        $queryset = [];
        $querysetcoll = [];
        for($i = 0; $albums != null && $i < sizeof($albums); $i++) {
            $tag = array_key_exists("tag", $albums[$i])?$albums[$i]["tag"]:0;
            if($tag && is_numeric($tag)) {
                if(array_key_exists($tag, $tags))
                    $chain[$i] = $tags[$tag];
                else {
                    if(array_key_exists("iscoll", $albums[$i]) &&
                            $albums[$i]["iscoll"])
                        $querysetcoll[] = $tag;
                    else
                        $queryset[] = $tag;
                }
                $tags[$tag] = $i;
            }
        }

        if(count($tags) == 0)
            return;

        $urlFilter = $enableExternalLinks ? "url <> ''" : "url RLIKE ?";

        $query = $queryset ? "SELECT tag FROM tracknames " .
                 "WHERE $urlFilter AND tag IN (" . implode(',', $queryset) . ") " .
                 "GROUP BY tag" : "";

        if($querysetcoll) {
            if($query)
                $query .= " UNION ";

            $query .= "SELECT tag FROM colltracknames ".
                      "WHERE $urlFilter AND tag IN (" . implode(',', $querysetcoll) . ") ".
                      "GROUP BY tag";
        }
        $stmt = $this->prepare($query);
        if(!$enableExternalLinks) {
            // RLIKE doesn't like (|s) so replace with equivalent s?
            $rlike = preg_replace(['/\(\|s\)/', '/\/(.*)\//'], ['s?', '\1'], $internalLinks);
            $stmt->bindValue(1, $rlike);
            if($queryset && $querysetcoll)
                $stmt->bindValue(2, $rlike);
        }
        $stmt->execute();
        while($row = $stmt->fetch()) {
            for($next = $tags[$row[0]]; $next >= 0; $next = array_key_exists($next, $chain)?$chain[$next]:-1) {
                $albums[$next]["playable"] = 1;
            }
        }
    }

    public function listAlbums($op, $key, $limit) {
        $cache = [];
        $reverse = 0;
        $split = '';
        $parts = array_pad(explode('|', $key), 3, '');
        switch($op) {
        case ILibrary::OP_PREV_LINE:
            $query = "SELECT * FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey WHERE artist < ? OR (artist = ? AND album < ?) OR (artist = ? AND album = ? AND tag < ?) ORDER BY artist DESC, album DESC, tag DESC LIMIT 1";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $parts[0]);
            $stmt->bindValue(2, $parts[0]);
            $stmt->bindValue(3, $parts[1]);
            $stmt->bindValue(4, $parts[0]);
            $stmt->bindValue(5, $parts[1]);
            $stmt->bindValue(6, $parts[2]);
            $stmt->execute();
            $records = $stmt->fetchAll();
            $num = sizeof($records);
            if($num != 1) {
                // we're on the first page
                $query = "SELECT * FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey ORDER BY artist, album, tag LIMIT ?";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, (int)$limit, \PDO::PARAM_INT);
            } else {
                $cache = $records;
                $query = "SELECT * FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey WHERE artist > ? OR (artist = ? AND album > ?) OR (artist = ? AND album = ? AND tag >= ?) ORDER BY artist, album, tag LIMIT ?";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, $parts[0]);
                $stmt->bindValue(2, $parts[0]);
                $stmt->bindValue(3, $parts[1]);
                $stmt->bindValue(4, $parts[0]);
                $stmt->bindValue(5, $parts[1]);
                $stmt->bindValue(6, $parts[2]);
                $stmt->bindValue(7, (int)($limit)-1, \PDO::PARAM_INT);
            }
            break;
        case ILibrary::OP_NEXT_LINE:
            $query = "SELECT * FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey WHERE artist > ? OR (artist = ? AND album > ?) OR (artist = ? AND album = ? AND tag > ?) ORDER BY artist, album, tag LIMIT ?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $parts[0]);
            $stmt->bindValue(2, $parts[0]);
            $stmt->bindValue(3, $parts[1]);
            $stmt->bindValue(4, $parts[0]);
            $stmt->bindValue(5, $parts[1]);
            $stmt->bindValue(6, $parts[2]);
            $stmt->bindValue(7, (int)$limit, \PDO::PARAM_INT);
            break;
        case ILibrary::OP_PREV_PAGE:
            $query = "SELECT * FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey WHERE artist < ? OR (artist = ? AND album < ?) OR (artist = ? AND album = ? AND tag <= ?) ORDER BY artist DESC, album DESC, tag DESC LIMIT ?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $parts[0]);
            $stmt->bindValue(2, $parts[0]);
            $stmt->bindValue(3, $parts[1]);
            $stmt->bindValue(4, $parts[0]);
            $stmt->bindValue(5, $parts[1]);
            $stmt->bindValue(6, $parts[2]);
            $stmt->bindValue(7, (int)$limit, \PDO::PARAM_INT);
            $reverse = 1;
            break;
        case ILibrary::OP_NEXT_PAGE:
            $query = "SELECT * FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey WHERE artist > ? OR (artist = ? AND album > ?) OR (artist = ? AND album = ? AND tag >= ?) ORDER BY artist, album, tag LIMIT ?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $parts[0]);
            $stmt->bindValue(2, $parts[0]);
            $stmt->bindValue(3, $parts[1]);
            $stmt->bindValue(4, $parts[0]);
            $stmt->bindValue(5, $parts[1]);
            $stmt->bindValue(6, $parts[2]);
            $stmt->bindValue(7, (int)$limit, \PDO::PARAM_INT);
            break;
        case ILibrary::OP_BY_NAME:
        case ILibrary::OP_BY_TAG:
            if($op == ILibrary::OP_BY_TAG) {
                $query = "SELECT artist, album FROM albumvol WHERE tag = ?";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, $key);
                $stmt->execute();
                $row = $stmt->fetchAll();
                $aartist = $row[0]["artist"];
                $aalbum = $row[0]["album"];
            } else {
                while($key && !$split) {
                    $query = "SELECT artist FROM albumvol WHERE artist >= ? LIMIT 1";
                    $stmt = $this->prepare($query);
                    $stmt->bindValue(1, $key);
                    $stmt->execute();
                    $row = $stmt->fetchAll();
                    if(sizeof($row))
                        $split = $row[0]["artist"];
                    else
                        $key = substr($key, 0, strlen($key)-1);
                }
            }
    
            if($split || $op == ILibrary::OP_BY_TAG) {
                if($op == ILibrary::OP_BY_TAG) {
                    $query = "SELECT * FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey WHERE artist < ? OR (artist = ? AND album < ?) OR (artist = ? AND album = ? AND tag < ?) ORDER BY artist DESC, album DESC, tag DESC LIMIT ?";
                    $stmt = $this->prepare($query);
                    $stmt->bindValue(1, $aartist);
                    $stmt->bindValue(2, $aartist);
                    $stmt->bindValue(3, $aalbum);
                    $stmt->bindValue(4, $aartist);
                    $stmt->bindValue(5, $aalbum);
                    $stmt->bindValue(6, $key);
                    $stmt->bindValue(7, (int)($limit/2), \PDO::PARAM_INT);
                } else {
                    $query = "SELECT * FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey WHERE artist < ? ORDER BY artist DESC, album DESC, tag DESC LIMIT ?";
                    $stmt = $this->prepare($query);
                    $stmt->bindValue(1, $split);
                    $stmt->bindValue(2, (int)($limit/2), \PDO::PARAM_INT);
                }
    
                $stmt->execute();
                $records = $stmt->fetchAll();
                $num = sizeof($records);
                if((int)($limit/2) && $num < (int)($limit/2)) {
                    // we're on the first page
                    $query = "SELECT * FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey ORDER BY artist, album, tag LIMIT ?";
                    $stmt = $this->prepare($query);
                    $stmt->bindValue(1, (int)$limit, \PDO::PARAM_INT);
                    $selected = $num;
                } else {
                    $cache = array_reverse($records);
                    $selected = $num;
                    if($op == ILibrary::OP_BY_TAG) {
                        $query = "SELECT * FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey WHERE artist > ? OR (artist = ? AND album > ?) OR (artist = ? AND album = ? AND tag >= ?) ORDER BY artist, album, tag LIMIT ?";
                        $stmt = $this->prepare($query);
                        $stmt->bindValue(1, $aartist);
                        $stmt->bindValue(2, $aartist);
                        $stmt->bindValue(3, $aalbum);
                        $stmt->bindValue(4, $aartist);
                        $stmt->bindValue(5, $aalbum);
                        $stmt->bindValue(6, $key);
                        $stmt->bindValue(7, (int)($limit) - (int)($limit/2), \PDO::PARAM_INT);
                    } else {
                        $query = "SELECT * FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey WHERE artist >= ? ORDER BY artist, album, tag LIMIT ?";
                        $stmt = $this->prepare($query);
                        $stmt->bindValue(1, $split);
                        $stmt->bindValue(2, (int)($limit) - (int)($limit/2), \PDO::PARAM_INT);
                    }
                }
            } else {
                $query = "SELECT * FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey ORDER BY artist, album, tag LIMIT ?";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, (int)$limit, \PDO::PARAM_INT);
            }
            break;
        default:
            error_log("listAlbums: unknown operation '$op'");
            return;
        }
        $stmt->execute();
        $records = $stmt->fetchAll();
        if(sizeof($records) + sizeof($cache) != $limit) {
            $stmt = null;
            if($reverse) {
                // Handle case of back scroll on the first page
                $query = "SELECT * FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey ORDER BY artist, album, tag LIMIT ?";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, (int)$limit, \PDO::PARAM_INT);
            } else if ($op < ILibrary::OP_BY_NAME) {
                // Handle case of forward scroll on the last page
                $query = "SELECT * FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey WHERE artist > ? OR (artist = ? AND album > ?) OR (artist = ? AND album = ? AND tag >= ?) ORDER BY artist, album, tag LIMIT ?";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, $parts[0]);
                $stmt->bindValue(2, $parts[0]);
                $stmt->bindValue(3, $parts[1]);
                $stmt->bindValue(4, $parts[0]);
                $stmt->bindValue(5, $parts[1]);
                $stmt->bindValue(6, $parts[2]);
                $stmt->bindValue(7, (int)$limit, \PDO::PARAM_INT);
            }
            if($stmt) {
                $stmt->execute();
                $records = $stmt->fetchAll();
                $reverse = 0;
            }
        }
        $result = $reverse?array_reverse($records):$records;
        if($cache)
            $result = array_merge($cache, $result);
        return $result;
    }

    public function listLabels($op, $key, $limit) {
        $cache = [];
        $reverse = 0;
        $split = '';
        $parts = array_pad(explode('|', $key), 2, '');
        switch($op) {
        case ILibrary::OP_PREV_LINE:
            $query = "SELECT * FROM publist WHERE name <> '' AND name < ? OR (name = ? AND pubkey < ?) ORDER BY name DESC, pubkey DESC LIMIT 1";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $parts[0]);
            $stmt->bindValue(2, $parts[0]);
            $stmt->bindValue(3, $parts[1]);
            $stmt->execute();
            $records = $stmt->fetchAll();
            $num = sizeof($records);
            if($num != 1) {
              // we're on the first page
                $query = "SELECT * FROM publist WHERE name <> '' ORDER BY name, pubkey LIMIT ?";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, (int)$limit, \PDO::PARAM_INT);
            } else {
                $cache = $records;
                $query = "SELECT * FROM publist WHERE name <> '' AND name > ? OR (name = ? AND pubkey >= ?) ORDER BY name, pubkey LIMIT ?";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, $parts[0]);
                $stmt->bindValue(2, $parts[0]);
                $stmt->bindValue(3, $parts[1]);
                $stmt->bindValue(4, (int)($limit)-1, \PDO::PARAM_INT);
            }
            break;
        case ILibrary::OP_NEXT_LINE:
            $query = "SELECT * FROM publist WHERE name <> '' AND name > ? OR (name = ? AND pubkey > ?) ORDER BY name, pubkey LIMIT ?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $parts[0]);
            $stmt->bindValue(2, $parts[0]);
            $stmt->bindValue(3, $parts[1]);
            $stmt->bindValue(4, (int)$limit, \PDO::PARAM_INT);
            break;
        case ILibrary::OP_PREV_PAGE:
            $query = "SELECT * FROM publist WHERE name <> '' AND name < ? OR (name = ? AND pubkey <= ?) ORDER BY name DESC, pubkey DESC LIMIT ?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $parts[0]);
            $stmt->bindValue(2, $parts[0]);
            $stmt->bindValue(3, $parts[1]);
            $stmt->bindValue(4, (int)$limit, \PDO::PARAM_INT);
            $reverse = 1;
            break;
        case ILibrary::OP_NEXT_PAGE:
            $query = "SELECT * FROM publist WHERE name <> '' AND name > ? OR (name = ? AND pubkey >= ?) ORDER BY name, pubkey LIMIT ?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $parts[0]);
            $stmt->bindValue(2, $parts[0]);
            $stmt->bindValue(3, $parts[1]);
            $stmt->bindValue(4, (int)$limit, \PDO::PARAM_INT);
            break;
        case ILibrary::OP_BY_NAME:
        case ILibrary::OP_BY_TAG:
            if($op == ILibrary::OP_BY_TAG) {
                $query = "SELECT l.name FROM publist l, albumvol a WHERE a.tag = $key AND a.pubkey = l.pubkey LIMIT 1";
                $stmt = $this->prepare($query);
                $stmt->execute();
                $row = $stmt->fetchAll();
                if(sizeof($row))
                    $split = $row[0]["name"];
            } else {
                while($key && !$split) {
                    $query = "SELECT name FROM publist WHERE name <> '' AND name >= ? LIMIT 1";
                    $stmt = $this->prepare($query);
                    $stmt->bindValue(1, $key);
                    $stmt->execute();
                    $records = $stmt->fetchAll();
                    if(sizeof($records))
                        $split = $records[0]["name"];
                    else
                        $key = substr($key, 0, strlen($key)-1);
                }
            }
    
            if($split) {
                $query = "SELECT * FROM publist WHERE name <> '' AND name < ? ORDER BY name DESC, pubkey DESC LIMIT ?";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, $split);
                $stmt->bindValue(2, (int)($limit/2), \PDO::PARAM_INT);
                $stmt->execute();
                $records = $stmt->fetchAll();
                $num = sizeof($records);
                if((int)($limit/2) && $num < (int)($limit/2)) {
                    // we're on the first page
                    $query = "SELECT * FROM publist WHERE name <> '' ORDER BY name, pubkey LIMIT ?";
                    $stmt = $this->prepare($query);
                    $stmt->bindValue(1, (int)$limit, \PDO::PARAM_INT);
                    $selected = $num;
                } else {
                    $cache = array_reverse($records);
                    $selected = $num;
                    $query = "SELECT * FROM publist WHERE name <> '' AND name >= ? ORDER BY name, pubkey LIMIT ?";
                    $stmt = $this->prepare($query);
                    $stmt->bindValue(1, $split);
                    $stmt->bindValue(2, (int)($limit) - sizeof($cache), \PDO::PARAM_INT);
                }
            } else {
                $query = "SELECT * FROM publist WHERE name <> '' ORDER BY name, pubkey LIMIT ?";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, (int)$limit, \PDO::PARAM_INT);
            }
            break;
        default:
            error_log("listLabels: unknown operation '$op'");
            return;
        }
        $stmt->execute();
        $records = $stmt->fetchAll();
        if(sizeof($records) + sizeof($cache) != $limit) {
            $stmt = 0;
            if($reverse) {
                // Handle case of back scroll on the first page
                $query = "SELECT * FROM publist WHERE name <> '' ORDER BY name, pubkey LIMIT ?";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, (int)$limit, \PDO::PARAM_INT);
            } else if ($op < ILibrary::OP_BY_NAME) {
                // Handle case of forward scroll on the last page
                $parts = array_pad(explode('|', $key), 2, '');
                $query = "SELECT * FROM publist WHERE name <> '' AND name > ? OR (name = ? AND pubkey >= ?) ORDER BY name, pubkey LIMIT ?";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, $parts[0]);
                $stmt->bindValue(2, $parts[0]);
                $stmt->bindValue(3, $parts[1]);
                $stmt->bindValue(4, (int)$limit, \PDO::PARAM_INT);
            }
            if($stmt) {
                $stmt->execute();
                $records = $stmt->fetchAll();
                $reverse = 0;
            }
        }
        $result = $reverse?array_reverse($records):$records;
        if($cache)
            $result = array_merge($cache, $result);
        return $result;
    }

    public function searchFullText($type, $key, $size, $offset) {
        $retVal = array();
        $loggedIn = Engine::session()->isAuth("u");

        // Limit maximum number of results
        $size = $size ? min($size, self::MAX_FT_LIMIT) :
                            self::DEFAULT_FT_LIMIT;

        // Construct full text query string
        if(substr($key, 0, 2) == "\\\"") {
             $search = $key;
        } else {
             $words = array_filter(preg_split('/\W+/u', $key, 0, PREG_SPLIT_NO_EMPTY), function($word) {
                 return !in_array($word, self::$ftExclude);
             });
             $search = "+" . implode(" +", $words);
        }
    
        // JM 2010-09-26 remove semicolons to thwart injection attacks
        $search = preg_replace('/([;])/', '', $search);
    
        // JM 2010-09-26 if search string includes 'union select', abort
        $searchl = strtolower($search);
        $posUnion = strpos($searchl, "union");
        $posSelect = strpos($searchl, "select");
        if($posUnion !== FALSE && $posSelect > $posUnion)
            $type = "bogus";
    
        // Count results
        $total = 0;
        for($i=($loggedIn?0:1); $i<sizeof(self::$ftSearch); $i++) {
            if($type && self::$ftSearch[$i][0] != $type ||
                    !$type && is_null(self::$ftSearch[$i][2]))
                continue;
            $query = self::$ftSearch[$i][4];

            $ob = strpos($query, " ORDER BY");
            if($ob)
                $query = substr($query, 0, $ob);

            // For UNION queries, we must count number of aggregate rows.
            //
            // This will work also for simple queries, but in that
            // case, we just do a count, as it's a tad more efficient.
            if(strpos($query, " UNION SELECT ")) {
                $query = "SELECT COUNT(*) FROM (" . $query . ") x";
                $paramCount = 2;
            } else {
                $from = strpos($query, "FROM");
                $query = "SELECT COUNT(*) " . substr($query, $from);
                $paramCount = 1;
            }

            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $i?$search:$key);
            if($paramCount == 2)
                $stmt->bindValue(2, $search);
            $stmt->execute();
            $result = $stmt->fetchAll();
            // for GROUP BY expressions, there is one COUNT row per result row
            if(strpos(self::$ftSearch[$i][4], "GROUP BY"))
                $num = sizeof($result);
            else
                $num = (integer)$result[0][0];
            $total += $num;
            $rsize[$i] = $num;
        }

        if($total) {
            // Fetch results
            for($i=($loggedIn?0:1); $i<sizeof(self::$ftSearch); $i++) {
                if($type && self::$ftSearch[$i][0] != $type ||
                        !$type && is_null(self::$ftSearch[$i][2]))
                    continue;
                if($rsize[$i]) {
                    if($offset != "") {
                        $l = " LIMIT $size OFFSET $offset";
                    } else if($rsize[$i] == $size + 1) {
                        $l = " LIMIT ".($size + 1);
                        $rsize[$i] -= $size + 1;
                    } else {
                        $l = " LIMIT $size";
                        $rsize[$i] -= $size;
                    }
                    $query = self::$ftSearch[$i][4].$l;
                    $stmt = $this->prepare($query);
                    $stmt->bindValue(1, $i?$search:$key);
                    if(strpos($query, " UNION SELECT "))
                        $stmt->bindValue(2, $search);
                    $stmt->execute();
                    $result = $stmt->fetchAll();
                    $more = ($l && $rsize[$i] > 0)?$rsize[$i]:0;
                    $retVal[] = [
                        "type" => self::$ftSearch[$i][0],
                        "recordName" => self::$ftSearch[$i][1],
                        "more" => $more,
                        "offset" => $offset,
                        "result" => $result
                    ];
                }
            }
        }
        return [ $total, $retVal ];
    }
}
