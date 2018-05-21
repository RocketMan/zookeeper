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

use ZK\Engine\ILibrary;


/**
 * Playlist operations
 */
class PlaylistImpl extends BaseImpl implements IPlaylist {
    public function getShowdates($year, $month) {
        $yearMonth = sprintf("%04d-%02d", $year, $month) . "-%";
    
        $query = "SELECT showdate FROM lists " .
                 "WHERE airname IS NOT NULL " .
                 "AND showdate LIKE ? " .
                 "GROUP BY showdate ORDER BY showdate DESC";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $yearMonth);
        return $this->execute($stmt);
    }
    
    public function getPlaylist($playlist, $withAirname=0) {
        if($withAirname)
            $query = "SELECT l.description, l.showdate, l.showtime, " .
                     "       a.id, a.airname, l.dj " .
                     "FROM lists l LEFT JOIN airnames a " .
                     "ON l.airname = a.id " .
                     "WHERE l.id = ?";
        else
            $query = "SELECT description, showdate, showtime, airname, dj " .
                     "FROM lists WHERE id=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$playlist, \PDO::PARAM_INT);
        return $this->executeAndFetch($stmt, \PDO::FETCH_BOTH);
    }
    
    public function getPlaylists($onlyPublished=0, $withAirname=0,
                               $showDate="", $airname="", $user="", $desc=1) {
        if($withAirname)
            $query = "SELECT l.id, l.showdate, l.showtime, l.description, " .
                     "a.id, a.airname FROM lists l LEFT JOIN airnames a " .
                     "ON l.airname = a.id ";
        else
            $query = "SELECT id, showdate, showtime, description FROM lists l ";
        if($user)
            $query .= "WHERE l.dj=? ";
        else if($airname)
            $query .= "WHERE l.airname=? ";
        else if($showDate)
            $query .= "WHERE l.showdate=? ";
        if($onlyPublished)
            $query .= "AND l.airname IS NOT NULL ";
        $desc = $desc?"DESC":"";
        $query .= "ORDER BY l.showdate $desc, l.showtime $desc, l.id $desc";
        $stmt = $this->prepare($query);

        $p = 1;
        if($user)
            $stmt->bindValue($p++, $user);
        else if($airname)
            $stmt->bindValue($p++, $airname);
        else if($showDate)
            $stmt->bindValue($p++, $showDate);

        return $this->execute($stmt, \PDO::FETCH_BOTH);
    }
    
    public function getPlaylistsByAirname($airname) {
        return $this->getPlaylists(0, 0, "", $airname);
    }
    
    public function getPlaylistsByUser($user, $onlyPublished=0, $withAirname=0) {
        return $this->getPlaylists($onlyPublished, $withAirname, "", "", $user);
    }
    
    public function getPlaylistsByDate($date) {
        return $this->getPlaylists(1, 1, $date);
    }
    
    public function getWhatsOnNow() {
        $hour = date("Hi");
        $query = "SELECT l.id, l.showdate, l.showtime, l.description, " .
                 "a.id airid, a.airname FROM lists l LEFT JOIN airnames a " .
                 "ON l.airname = a.id ";
        $query .= "WHERE l.showdate=? ";
        $query .= "AND LEFT(l.showtime, 4) <= ? ";

        // Incur the overhead of checking whether the show ends at midnight
        // only if it's after 6pm.
        if($hour >= 1800) {
            $query .= "AND ( ( MID(l.showtime, 6, 2) = 0 ) OR ";
            $query .= " ( MID(l.showtime, 6, 4) > ? ) ) ";
        } else
            $query .= "AND MID(l.showtime, 6, 4) > ? ";
        $query .= "AND l.airname IS NOT NULL ";
        $query .= "ORDER BY l.showtime DESC LIMIT 1";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, date("Y-m-d"));
        $stmt->bindValue(2, $hour);
        $stmt->bindValue(3, $hour);
        return $this->execute($stmt);
    }
    
    public function insertPlaylist($user, $date, $time, $description, $airname) {
        list($year, $month, $day) = explode("-", $date);
        $query = "INSERT INTO lists " .
                 "(dj, showdate, showtime, description, airname) VALUES " .
                 "(?, ?, ?, ?, " .
                 ($airname?"?":"NULL") . ")";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $stmt->bindValue(2, "$year-$month-$day");
        $stmt->bindValue(3, $time);
        $stmt->bindValue(4, $description);
        if($airname)
            $stmt->bindValue(5, (int)$airname, \PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    public function updatePlaylist($playlist, $date, $time, $description, $airname) {
        list($year, $month, $day) = explode("-", $date);
        $query = "UPDATE lists SET showdate=?, " .
                 "showtime=?, " .
                 "description=?, " .
                 ($airname?"airname=? ":
                           "airname=NULL ") .
                 "WHERE id=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, "$year-$month-$day");
        $stmt->bindValue(2, $time);
        $stmt->bindValue(3, $description);
        if($airname) {
            $stmt->bindValue(4, (int)$airname, \PDO::PARAM_INT);
            $stmt->bindValue(5, $playlist);
        } else
            $stmt->bindValue(4, $playlist);
        return $stmt->execute();
    }

    public function getTrack($id) {
        $query = "SELECT tag, artist, track, album, label, id FROM tracks " .
                 "WHERE id = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$id, \PDO::PARAM_INT);
        return $this->executeAndFetch($stmt);
    }
    
    public function getTracks($playlist, $desc = 0) {
        $query = "SELECT tag, artist, track, album, label, id FROM tracks " .
                 "WHERE list = ? ORDER BY id";
        if($desc)
            $query .= " DESC";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$playlist, \PDO::PARAM_INT);
        return $this->execute($stmt);
    }
    
    public function insertTrack($playlist, $tag, $artist, $track, $album, $label) {
        // Insert tag?
        $noTag = ($tag == 0) || ($tag == "");
    
        $query = "INSERT INTO tracks " .
                 "(list, artist, track, album, label" . ($noTag?")":", tag)") .
                 " VALUES (?, ?, ?, ?, ?" .
                 ($noTag?")":", ?)");
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$playlist, \PDO::PARAM_INT);
        $stmt->bindValue(2, $artist);
        $stmt->bindValue(3, $track);
        $stmt->bindValue(4, $album);
        $stmt->bindValue(5, $label);
        if(!$noTag)
            $stmt->bindValue(6, $tag);
        return $stmt->execute();
    }
    
    public function updateTrack($id, $tag, $artist, $track, $album, $label) {
        $query = "UPDATE tracks SET ";
        $query .= "artist=?, " .
                  "track=?, " .
                  "album=?, " .
                  "label=?, " .
                  "tag=" . ($tag?"?":"NULL");
        $query .= " WHERE id = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $artist);
        $stmt->bindValue(2, $track);
        $stmt->bindValue(3, $album);
        $stmt->bindValue(4, $label);
        if($tag) {
            $stmt->bindValue(5, $tag);
            $stmt->bindValue(6, (int)$id, \PDO::PARAM_INT);
        } else
            $stmt->bindValue(5, (int)$id, \PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    public function deleteTrack($id) {
        $query = "DELETE FROM tracks WHERE id = ?";
          $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$id, \PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    public function getTopPlays(&$result, $airname=0, $days=41, $count=10) {
        $over = $airname?"distinct t.list":"*";
        $query = "SELECT t.tag, count($over), l.showdate, t.artist, t.album, t.label, count(*)" .
                 " FROM tracks t, lists l" .
                 " WHERE t.list = l.id AND".
                 " t.artist NOT LIKE '".IPlaylist::SPECIAL_TRACK."%' AND";
        if($airname)
            $query .= "    l.airname = ? AND";
        if($days)
            $query .= "    date_add(l.showdate, interval $days day) > now() ";
        $query .= " GROUP BY t.album, t.label ORDER BY 2 DESC, 7 DESC, 3 DESC, 1 DESC LIMIT ?";
        $stmt = $this->prepare($query);
        $p = 1;
        if($airname)
            $stmt->bindValue($p++, (int)$airname, \PDO::PARAM_INT);
        $stmt->bindValue($p++, (int)$count, \PDO::PARAM_INT);
        $stmt->execute();
        $i = 0;
        while($row = $stmt->fetch(\PDO::FETCH_BOTH)) {
            $result[$i]["tag"] = $row[0];
            $result[$i]["PLAYS"] = $row[1];
    
            // Setup artist correctly for collections
            $result[$i]["artist"] = $row["artist"];
            if($row[0]) {
                $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $row[0]);
                if (preg_match("/^\[coll\]/i", $albums[0]["artist"]))
                    $result[$i]["artist"] = "Various Artists";
            }
    
            // Get album name
            $result[$i]["album"] = $row["album"];
    
            // Get the label name
            $result[$i++]["label"] = $row["label"];
        }
    }
    
    public function getLastPlays($tag, $count=0) {
        settype($tag, "integer");
        $query = "SELECT l.id, l.showdate, l.description, a.airname," .
                 "        count(*) plays" .
                 " FROM tracks t" .
                 " JOIN lists l ON t.list = l.id " .
                 " LEFT JOIN airnames a ON l.airname = a.id" .
                 " WHERE t.tag = ? AND l.airname IS NOT NULL" .
                 " GROUP BY t.tag, l.id ORDER BY l.showdate DESC, l.showtime DESC";
        if($count)
            $query .= " LIMIT ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $tag);
        if($count)
            $stmt->bindValue(2, (int)$count, \PDO::PARAM_INT);
    
        return $this->execute($stmt);
    }
    
    public function getRecentPlays(&$result, $airname, $count) {
        $query = "SELECT t.tag, t.artist, t.album, t.label, t.track FROM tracks t, lists l ";
        $query .= "WHERE t.list = l.id AND ";
        $query .= "l.airname = ? AND t.artist NOT LIKE '".IPlaylist::SPECIAL_TRACK."%' ";
        $query .= "GROUP BY l.showdate, t.artist, t.album, t.label ";
        $query .= "ORDER BY l.showdate DESC, t.id DESC LIMIT $count";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$airname, \PDO::PARAM_INT);
        $stmt->execute();
        $i = 0;
    
        while($row = $stmt->fetch(\PDO::FETCH_BOTH)) {
            $result[$i]["tag"] = $row["tag"];
    
            // Setup artist correctly for collections
            $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $row["tag"]);
            if (preg_match("/^\[coll\]/i", $row["artist"]))
                $result[$i]["artist"] = "Various Artists";
            else
                $result[$i]["artist"] = $row["artist"];
    
            $result[$i]["album"] = $row["album"];
            $result[$i++]["label"] = $row["label"];
        }
    }

    public function deletePlaylist($playlist) {
        // fetch the airname from the playlists table
        $row = $this->getPlaylist($playlist);
        $airname = $row?$row['airname']:null;
   
        // insert into the deleted playlists table
        $query = "INSERT INTO lists_del VALUES ".
                 "(?, ?, NOW())";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $playlist);
        $stmt->bindValue(2, $airname?$airname:null);
        $stmt->execute();

        // update the playlist
        $query = "UPDATE lists SET airname = NULL ".
                 "WHERE id = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $playlist);
        $stmt->execute();
    }
    
    public function restorePlaylist($playlist) {
        // fetch the airname from the deleted playlists table
        $query = "SELECT airname FROM lists_del " .
                 "WHERE listid = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $playlist);
        $row = $this->executeAndFetch($stmt);
        $airname = $row?$row[0]:null;
       
        // update the playlist
        $query = "UPDATE lists SET airname = ? WHERE id = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $airname?$airname:null);
        $stmt->bindValue(2, $playlist);
        $stmt->execute();
    
        // clean up the deleted playlists table
        $query = "DELETE FROM lists_del WHERE listid = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $playlist);
        $stmt->execute();
    }

    public function purgeDeletedPlaylists() {
        $query = "DELETE FROM tracks, lists, lists_del USING lists_del " .
                 "INNER JOIN lists " .
                 "LEFT OUTER JOIN tracks ON lists_del.listid = tracks.list " .
                 "WHERE lists_del.listid = lists.id ".
                 "AND ADDDATE(deleted, 30) < NOW()";
        $stmt = $this->prepare($query);
        return $stmt->execute();
    }

    public function getDeletedPlaylistCount($user) {
        $query = "SELECT COUNT(*) FROM lists ".
                 "LEFT JOIN lists_del ON lists.id = lists_del.listid ".
                 "WHERE dj=? AND deleted IS NOT NULL";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $stmt->execute();
        while($row = $stmt->fetch())
            $count = $row[0];
        return $count;
    }

    public function getListsSelNormal($user) {
        $query = "SELECT id, showdate, showtime, description FROM lists " .
                 "LEFT JOIN lists_del ON lists.id = lists_del.listid ".
                 "WHERE dj=? AND deleted IS NULL ".
                 "ORDER BY showdate DESC, showtime DESC";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        return $this->execute($stmt, \PDO::FETCH_BOTH);
    }

    public function getListsSelDeleted($user) {
        $query = "SELECT id, showdate, showtime, description, ADDDATE(deleted, 30) FROM lists " .
                 "LEFT JOIN lists_del ON lists.id = lists_del.listid ".
                 "WHERE dj=? AND deleted IS NOT NULL ".
                 "ORDER BY showdate DESC, showtime DESC";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        return $this->execute($stmt, \PDO::FETCH_BOTH);
    }

    public function moveTrackUpDown($playlist, &$id, $up) {
        $query = "SELECT id, tag, artist, track, album, label FROM tracks " .
                 "WHERE list = ? ORDER BY id";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $playlist);
        $stmt->execute();
        unset($prevRow);
        while($row = $stmt->fetch(\PDO::FETCH_BOTH)) {
            if($row[0] == $id)
                break;
            $prevRow = $row;
        }
    
        $swap = 0;
        if($row[0] == $id) {
            if($up) {
                // move track up
                $prevRow = $row;
                if($row = $stmt->fetch(\PDO::FETCH_BOTH)) {
                    $swap = 1;
                    $id = $row[0];
                }
            } else if(sizeof($prevRow)){
                // move track down
                $swap = 1;
                $id = $prevRow[0];
            }
        }
    
        if($swap) {
            $query = "UPDATE tracks " .
                        "SET tag = ?, " .
                        "artist = ?, " .
                        "track = ?, " .
                        "album = ?, " .
                        "label = ? " .
                        "WHERE id = ?";
            $stmt = $this->prepare($query);

            $stmt->bindValue(1, $prevRow[1]?$prevRow[1]:null);
            $stmt->bindValue(2, $prevRow[2]);
            $stmt->bindValue(3, $prevRow[3]);
            $stmt->bindValue(4, $prevRow[4]);
            $stmt->bindValue(5, $prevRow[5]);
            $stmt->bindValue(6, (int)$row[0], \PDO::PARAM_INT);
            $stmt->execute();

            $stmt->bindValue(1, $row[1]?$row[1]:null);
            $stmt->bindValue(2, $row[2]);
            $stmt->bindValue(3, $row[3]);
            $stmt->bindValue(4, $row[4]);
            $stmt->bindValue(5, $row[5]);
            $stmt->bindValue(6, (int)$prevRow[0], \PDO::PARAM_INT);
            $stmt->execute();
        }
    }
}
