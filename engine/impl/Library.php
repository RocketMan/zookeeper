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
 * Library operations
 */
class LibraryImpl extends BaseImpl implements ILibrary {
    const MAX_FT_LIMIT = 35;

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
         [ "compilations", "albumrec", "colltracknames", "artist,track",
                  "SELECT c.tag, c.artist, album, category, medium, size, ".
                  "location, bin, created, updated, track FROM colltracknames c " .
                  "LEFT JOIN albumvol ON albumvol.tag = c.tag " .
                  "WHERE MATCH (c.artist,track) AGAINST(? IN BOOLEAN MODE) ".
                  "AND location != 'U' " .
                  "ORDER BY c.artist, album, c.tag" ],
         [ "labels", "labelrec", "publist", "name",
                  "SELECT * FROM publist " .
                  "WHERE MATCH (name) AGAINST(? IN BOOLEAN MODE) ".
                  "ORDER BY name" ],
         [ "playlists", "playlistrec", "tracks", "artist,album,track",
                  "SELECT list, description, a.airname, showdate, " .
                  "artist, album, track FROM tracks t " .
                  "LEFT JOIN lists l ON t.list = l.id " .
                  "LEFT JOIN airnames a ON l.airname = a.id " .
                  "WHERE l.airname IS NOT NULL AND " .
                  "MATCH (artist,album,track) AGAINST(? IN BOOLEAN MODE) " .
                  "ORDER BY showdate DESC, list DESC, t.id" ],
         [ "reviews", "reviewrec", "reviews", "review",
                  "SELECT r.tag, av.artist, av.album, an.airname, DATE_FORMAT(r.created, GET_FORMAT(DATE, 'ISO')) created FROM reviews r " .
                  "LEFT JOIN albumvol av ON r.tag = av.tag " .
                  "LEFT JOIN airnames an ON r.airname = an.id " .
                  "WHERE private = 0 AND r.airname IS NOT NULL AND " .
                  "MATCH (review) AGAINST(? IN BOOLEAN MODE) " .
                  "ORDER BY r.created DESC" ],
         [ "tracks", "albumrec", "tracknames", "track",
                  "SELECT t.tag, artist, album, category, medium, size, ".
                  "location, bin, created, updated, track FROM tracknames t ".
                  "LEFT JOIN albumvol ON albumvol.tag = t.tag ".
                  //"LEFT JOIN publist ON albumvol.pubkey = publist.pubkey ".
                  "WHERE MATCH (track) AGAINST(? IN BOOLEAN MODE) ".
                  "AND location != 'U' " .
                  "ORDER BY artist, album, t.tag" ]
    ];
    
    public function search($tableIndex, $pos, $count, $search, $sortBy = 0) {
        return $this->searchPos($tableIndex, $pos, $count, $search);
    }
    
    public function searchPos($tableIndex, &$pos, $count, $search, $sortBy = 0) {
        $retVal = array();
      
        // 2007-08-04 thwart injection attacks by aborting if we encounter
        // a semicolon or 'union select'
        $searchl = strtolower($search);
        $posUnion = strpos($searchl, "union");
        $posSelect = strpos($searchl, "select");
        if($posUnion !== FALSE && $posSelect > $posUnion ||
                strpos($searchl, ";") !== FALSE)
            exit;
      
        // select one more than requested (for pagination)
        $count += 1;
        $olen = strlen($search);
      
        // JM 2006-11-28 escape '_', '%'
        $search = preg_replace('/([_%])/', '\\\\$1', $search);
      
        if(substr($search, strlen($search)-1, 1) == "*")
            $search = substr($search, 0, strlen($search)-1)."%";

        $db = Engine::param('db');
        if(array_key_exists('library', $db) &&
                $db['library'] != $db['database'] &&
                $tableIndex != ILibrary::PASSWD_NAME) {                
            $mapper = clone $this;
            $mapper->init(Engine::newPDO('library'));
        } else
            $mapper = $this;
        
        switch($tableIndex) {
        case ILibrary::ALBUM_ARTIST:
            $query = "SELECT tag, artist, album, category, medium, size, ".
                     "created, updated, pubkey, location, bin, iscoll ".
                     "FROM albumvol WHERE artist LIKE ? ".
                     "UNION SELECT c.tag, c.artist, a.artist, category, medium, size, ".
                     "created, updated, pubkey, location, bin, iscoll ".
                     "FROM colltracknames c, albumvol a WHERE c.tag = a.tag ".
                     "AND c.artist LIKE ? ".
                     "ORDER BY artist, album, tag LIMIT ?, ?";
            $bindType = 4;
            break;
        case ILibrary::ALBUM_KEY:
            settype($search, "integer");
            $query = "SELECT * FROM albumvol WHERE tag=? LIMIT ?, ?";
            $bindType = 3;
            break;
        case ILibrary::ALBUM_NAME:
            $query = "SELECT * FROM albumvol WHERE album LIKE ? ORDER BY album, artist, tag LIMIT ?, ?";
            $bindType = 3;
            break;
        case ILibrary::ALBUM_PUBKEY:
            settype($search, "integer");
            $query = "SELECT * FROM albumvol WHERE pubkey=? ORDER BY artist, album, tag LIMIT ?, ?";
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
      
            $query = "SELECT a.tag, track, artist, album, seq ".
                     "FROM tracknames t, albumvol a WHERE a.tag = t.tag AND ".
                     "track LIKE ? ".
                     "UNION SELECT a.tag, track, c.artist, a.artist, seq ".
                     "FROM colltracknames c LEFT JOIN albumvol a ON a.tag = c.tag ".
                     "WHERE track LIKE ? ".
                     "ORDER BY track, artist, album LIMIT ?, ?";
            $bindType = 4;
            break;
        case ILibrary::ALBUM_AIRNAME:
            $query = "SELECT a.id, artist, album, category, medium, ".
                     "size, a.created, a.updated, a.pubkey, location, bin, a.tag, iscoll, ".
                     "p.name, r.created reviewed ".
                     "FROM albumvol a JOIN reviews r ON a.tag = r.tag LEFT JOIN publist p ON p.pubkey = a.pubkey ";
            $query .= "WHERE r.airname = ? ";
            if(!Engine::session()->isAuth("u"))
                $query .= "AND r.private = 0 ";
            $desc = "";
            $sb = $sortBy;
            if(substr($sb, -1) == "-") {
                $sb = substr($sb, 0, -1);
                $desc = " DESC" ;
            }
            if($sb == "Album")
                $query .= "ORDER BY album$desc, artist$desc, a.tag ";
            else if($sb == "Label")
                $query .= "ORDER BY p.name$desc, album$desc, artist$desc ";
            else if($sb == "Date Reviewed")
                $query .= "ORDER BY r.created$desc ";
            else // "Artist"
                $query .= "ORDER BY artist$desc, album$desc, a.tag ";
            $query .= "LIMIT ?, ?";
            $bindType = 3;
            break;
        default:
            echo "DEBUG: ERROR! Unknown key '$tableIndex'<BR>\n";
            return;
        }
      
        if($query) {
            $stmt = $mapper->prepare($query);
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
                $stmt->bindValue(2, $search);
                $stmt->bindValue(3, (int)$pos, \PDO::PARAM_INT);
                $stmt->bindValue(4, (int)$count, \PDO::PARAM_INT);
                break;
            }
            $stmt->execute();

            // copy the requested rows
            $askCount = $count;
            while(--$count && ($row = $stmt->fetch()))
                $retVal[] = $row;

            // adjust the pagination position if there are more rows
            $pos = (!$count && $stmt->fetch())?$pos + $askCount - 1:0;
        }
      
        return $retVal;
    }

    private static function displayName($airname, $realname) {
        return $airname?$airname:$realname;
    }
    
    // For a given $albums array, add 'reviewed' and 'reviewer' columns
    // for each album which has at least one music review.
    //
    public function markAlbumsReviewed(&$albums, $loggedIn = 0) {
        $chain = [];
        $tags = [];
        $queryset = "";
        for($i = 0; $albums != null && $i < sizeof($albums); $i++) {
            $tag = array_key_exists("tag", $albums[$i])?$albums[$i]["tag"]:0;
            if($tag) {
                if(array_key_exists($tag, $tags))
                    $chain[$i] = $tags[$tag];
                else
                    $queryset .= ", $tag";
                $tags[$tag] = $i;
            }
        }
        $query = "SELECT tag, a.airname, realname FROM reviews r " .
                 "LEFT JOIN users u ON r.user = u.name " .
                 "LEFT JOIN airnames a ON r.airname = a.id WHERE " .
                 "tag IN (0" . $queryset . ")";
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

    public function getNumQueuedTags($user) {
        $count = 0;
        $query = "SELECT count(*) FROM tagqueue WHERE user=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        if($stmt->execute()) {
            $row = $stmt->fetch();
            $count = $row[0];
        }
        return $count;
    }

    public function listAlbums($op, $key, $limit) {
        $cache = [];
        $reverse = 0;
        $parts = explode('|', $key);
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
            echo "DEBUG: ERROR! Unknown operation '$op'<BR>\n";
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
        $reverse = 0;
        $parts = explode('|', $key);
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
            echo "DEBUG: ERROR! Unknown operation '$op'<BR>\n";
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
                $parts = explode('|', $key);
                $query = "SELECT * FROM publist WHERE name <> '' AND name > ? OR (name = ? AND pubkey > ?) ORDER BY name, pubkey LIMIT ?";
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

    public static function ftfilter($elt) {
       return strlen($elt) > 1 &&
          $elt != "an" && $elt != "and" && $elt != "or" && $elt != "the";
    }

    public function searchFullText($type, $key, $size, $offset) {
        $retVal = array();
        $loggedIn = Engine::session()->isAuth("u");

        // Limit maximum number of results
        if(!$size || $size > LibraryImpl::MAX_FT_LIMIT)
            $size = LibraryImpl::MAX_FT_LIMIT;

        // Construct full text query string
        if(substr($key, 0, 2) == "\\\"") {
             $search = $key;
        } else {
             $words = array_filter(explode(" ", $key), array(__CLASS__, "ftfilter"));
             $search = "+".implode(" +",$words);
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
            if($type && self::$ftSearch[$i][0] != $type)
                continue;
            $query = self::$ftSearch[$i][4];

            $from = strpos($query, "FROM");
            $query = "SELECT COUNT(*) " . substr($query, $from);
            $ob = strpos($query, " ORDER BY");
            if($ob)
                $query = substr($query, 0, $ob);
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $i?$search:$key);
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
                if($type && self::$ftSearch[$i][0] != $type)
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
