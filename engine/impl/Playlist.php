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
class PlaylistImpl extends BaseImpl implements IPlaylist {
    const TIME_FORMAT = "Y-m-d Hi"; // eg, 2019-01-01 1234
    const TIME_FORMAT_SQL = "Y-m-d H:i:s"; // 2019-01-01 12:34:56
    const GRACE_START = "-15 minutes";
    const GRACE_END = "+30 minutes";

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
                               $showDate="", $airname="", $user="", $desc=1, $limit=null) {
        if($withAirname)
            $query = "SELECT l.id, l.showdate, l.showtime, l.description, " .
                     "a.id airid, a.airname FROM lists l LEFT JOIN airnames a " .
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

        if ($limit)
            $query .= " limit $limit ";

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
        $query .= "ORDER BY l.showtime DESC, l.id DESC LIMIT 1";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, date("Y-m-d"));
        $stmt->bindValue(2, $hour);
        $stmt->bindValue(3, $hour);
        return $this->execute($stmt, \PDO::FETCH_BOTH);
    }
    
    private function purgeEmptyPlaylist($user, $date) {
        // search for last entered playlist by this DJ on this date
        $query = "SELECT l.id AS lid, t.id AS tid FROM lists l ".
                 "LEFT JOIN tracks t ON l.id = t.list ".
                 "WHERE dj = ? AND showdate = ? ".
                 "ORDER BY l.id DESC LIMIT 1";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $stmt->bindValue(2, $date);
        $result = $this->executeAndFetch($stmt);
        if($result && !$result['tid']) {
            // DJ's last entered playlist on this date has no tracks; delete it
            //
            // Note:  This is an unceremonious, non-restorable delete, as
            // the empty playlist is deemed to have no value.
            $query = "DELETE FROM lists WHERE id = ?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $result['lid']);
            $stmt->execute();
        }
    }

    public function insertPlaylist($user, $date, $time, $description, $airname) {
        list($year, $month, $day) = explode("-", $date);
        $canonicalDate = "$year-$month-$day";

        $this->purgeEmptyPlaylist($user, $canonicalDate);

        $query = "INSERT INTO lists " .
                 "(dj, showdate, showtime, description, airname) VALUES " .
                 "(?, ?, ?, ?, " .
                 ($airname?"?":"NULL") . ")";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $stmt->bindValue(2, $canonicalDate);
        $stmt->bindValue(3, $time);
        $stmt->bindValue(4, $description);
        if($airname)
            $stmt->bindValue(5, (int)$airname, \PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    public function updatePlaylist($playlist, $date, $time, $description, $airname) {
        $query = "SELECT showdate, showtime FROM lists WHERE id = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $playlist);
        $row = $this->executeAndFetch($stmt);

        if($row) {
            $oldDate = \DateTime::createFromFormat(self::TIME_FORMAT,
                        $row['showdate'] . " 0000");
            $newDate = \DateTime::createFromFormat(self::TIME_FORMAT,
                        $date . " 0000");
            $offset = $oldDate->diff($newDate)->format("%r%a");

            if($offset) {
                // fixup spin timestamps for playlist date change
                $query = "UPDATE tracks " .
                         "SET created = timestampadd(day, ?, created) " .
                         "WHERE list = ? AND created IS NOT NULL";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, $offset);
                $stmt->bindValue(2, $playlist);
                $stmt->execute();
            }

            if($row['showtime'] != $time) {
                // fixup spin timestamps for playlist time change
                list($from, $to) = explode("-", $time);
                $fromStamp = \DateTime::createFromFormat(self::TIME_FORMAT,
                            $date . " " . $from);
                $toStamp = \DateTime::createFromFormat(self::TIME_FORMAT,
                            $date . " " . $to);

                // if playlist spans midnight, end time is next day
                if($toStamp < $fromStamp)
                    $toStamp->modify("+1 day");

                // clear spin timestamps outside new time range
                $query = "UPDATE tracks SET created = NULL " .
                         "WHERE list = ? AND " .
                         "created NOT BETWEEN ? AND ?";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, $playlist);
                $stmt->bindValue(2, $fromStamp->format(self::TIME_FORMAT_SQL));
                $stmt->bindValue(3, $toStamp->format(self::TIME_FORMAT_SQL));
                $stmt->execute();
            }
        }

        $query = "UPDATE lists SET showdate=?, " .
                 "showtime=?, " .
                 "description=?, " .
                 ($airname?"airname=? ":
                           "airname=NULL ") .
                 "WHERE id=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $date);
        $stmt->bindValue(2, $time);
        $stmt->bindValue(3, $description);
        if($airname) {
            $stmt->bindValue(4, (int)$airname, \PDO::PARAM_INT);
            $stmt->bindValue(5, $playlist);
        } else
            $stmt->bindValue(4, $playlist);
        return $stmt->execute();
    }

    private function populateSeq($list) {
        $query = "SET @seq = 0; ".
                 "UPDATE tracks SET seq = (@seq := @seq + 1) ".
                 "WHERE list = ? ORDER BY id";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$list, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getSeq($list, $id) {
        $query = "SELECT seq FROM tracks WHERE id = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$id, \PDO::PARAM_INT);
        $row = $this->executeAndFetch($stmt);
        if(!$row || !$row['seq']) {
            if($list && $this->populateSeq($list))
                $row = $this->executeAndFetch($stmt);
        }
        return $row?$row['seq']:false;
    }

    private function nextSeq($list) {
        $query = "SELECT MAX(seq) max FROM tracks WHERE list = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $list);
        $row = $this->executeAndFetch($stmt);
        return $row && $row['max']?$row['max'] + 1:0;
    }

    public function moveTrack($list, $id, $toId, $clearTimestamp=true) {
        $fromSeq = $this->getSeq($list, $id);
        $toSeq = $this->getSeq($list, $toId);

        if($fromSeq && $toSeq) {
            if($fromSeq < $toSeq) {
                $setClause = "seq = seq - 1";
                $whereClause = "seq > ? AND seq <= ?";
            } else {
                $setClause = "seq = seq + 1";
                $whereClause = "seq < ? AND seq >= ?";
            }
            $query = "UPDATE tracks SET $setClause ".
                     "WHERE $whereClause AND list = ?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $fromSeq);
            $stmt->bindValue(2, $toSeq);
            $stmt->bindValue(3, $list);
            if($stmt->execute()) {
                $clear = $clearTimestamp?", created = NULL":"";
                $query = "UPDATE tracks SET seq = ? $clear WHERE id = ?";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, $toSeq);
                $stmt->bindValue(2, $id);
                return $stmt->execute();
            } else {
                error_log("moveTrack: " . print_r($stmt->errorInfo(), true));
            }
        }
        return false;
    }

    private function reorderForTime($list, $id, $timestamp) {
        $curSeq = $this->getSeq($list, $id);

        $query = "SELECT id, seq FROM tracks ".
                 "WHERE list = ? ".
                 "AND created < ? ".
                 "ORDER BY created DESC, seq DESC ".
                 "LIMIT 1";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $list);
        $stmt->bindValue(2, $timestamp);
        $row = $this->executeAndFetch($stmt);
        $lowid = $row?$row['id']:0;

        if($lowid) {
            $lowSeq = $row['seq'];
            if($lowSeq > $curSeq) {
                return $this->moveTrack($list, $id, $lowid, false);
            }
        }

        $query = "SELECT id, seq FROM tracks ".
                 "WHERE list = ? ".
                 "AND created > ? ".
                 "ORDER BY created, seq ".
                 "LIMIT 1";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $list);
        $stmt->bindValue(2, $timestamp);
        $row = $this->executeAndFetch($stmt);
        $highid = $row?$row['id']:0;

        if($highid) {
            $highSeq = $row['seq'];
            if($highSeq < $curSeq) {
                return $this->moveTrack($list, $id, $highid, false);
            }
        }

        // entry is already in order, return success
        return true;
    }

    public function getTrack($id) {
        $query = "SELECT tag, artist, track, album, label, id, created FROM tracks " .
                 "WHERE id = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$id, \PDO::PARAM_INT);
        return $this->executeAndFetch($stmt);
    }
    
    public function getTracks($playlist, $desc = 0) {
        $desc = $desc?"DESC":"";
        $query = "SELECT tag, artist, track, album, label, id, created FROM tracks " .
                 "WHERE list = ? ORDER BY seq $desc, id $desc";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$playlist, \PDO::PARAM_INT);
        return $this->execute($stmt);
    }

    public function getTracksWithObserver($playlist, PlaylistObserver $observer, $desc = 0, $filter = null) {
        $tracks = $this->getTracks($playlist, $desc);
        if($tracks && $filter)
            $tracks = new $filter($tracks);
        while($tracks && ($track = $tracks->fetch()))
            $observer->observe(new PlaylistEntry($track));
    }

    public function getTrackCount($playlist) {
        $query = "SELECT COUNT(*) AS count FROM tracks WHERE list = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$playlist, \PDO::PARAM_INT);
        $row = $this->executeAndFetch($stmt);
        return $row?$row['count']:0;
    }

    // NOTE: this routine must be tolerant of improperly formatted dates.
    public function getTimestampWindowInternal($playlist) {
        $result = null;
        if($playlist && ($showtime = $playlist['showtime'])) {
            $timeAr = explode("-", $showtime);
            if(count($timeAr) == 2) {
                $timeStr = $playlist['showdate'] . " " . $timeAr[0];
                $start = \DateTime::createFromFormat(self::TIME_FORMAT, $timeStr);
                if($start) {
                    $start->modify(self::GRACE_START);
                    $end = clone $start;

                    // end time can be midnight or later
                    // in this case, adjust to the next day
                    if($timeAr[1] < $timeAr[0])
                        $end->modify("+1 day");

                    $end->setTime(substr($timeAr[1], 0, 2),
                                  substr($timeAr[1], 2, 2));
                    $end->modify(self::GRACE_END);

                    $result = [
                        "start" => $start,
                        "end" => $end
                    ];
                }
            }
        }
        return $result;
    }

    public function getTimestampWindow($playlistId) {
        $playlist = $this->getPlaylist($playlistId);
        return $this->getTimestampWindowInternal($playlist);
    }

    public function isWithinShow($dateTime, $listRow) {
        $retVal = false;
        $window = $this->getTimestampWindowInternal($listRow);
        if($window) {
            $retVal = $dateTime >= $window['start'] &&
                      $dateTime <= $window['end'];
        }
        return $retVal;
    }

    // return true if "now" is within the show start/end time & date.
    public function isNowWithinShow($listRow) {
        $nowDateTime = new \DateTime("now");
        return $this->isWithinShow($nowDateTime, $listRow);
    }

    public function isDateTimeWithinShow($timeStamp, $listRow) {
        $dateTime = new \DateTime($timeStamp);
        return $this->isWithinShow($dateTime, $listRow);
    }

    // insert playlist track. return following: 0 - fail, 1 - success no 
    // timestamp, 2 - sucess with timestamp.
    public function insertTrack($playlistId, $tag, $artist, $track, $album, $label, $wantTimestamp, &$id = null) {
        $row = $this->getPlaylist($playlistId, 1);

        // log time iff 'now' is within playlist start/end time.
        $doTimestamp = $wantTimestamp && $this->isNowWithinShow($row);
        $timeName    = $doTimestamp ? "created, " : "";
        $timeValue   = $doTimestamp ? "NOW(), "   : "";

        // Insert tag?
        $haveTag  = ($tag != 0) && ($tag != "");
        $tagName  = $haveTag ? ", tag" : "";
        $tagValue = $haveTag ? ", ?"   : "";
    
        $names = "(" . $timeName . "list, artist, track, album, label, seq " . $tagName . ")";
        $values = " VALUES (" . $timeValue . "?, ?, ?, ?, ?, ?" . $tagValue . ");";

        $query = "INSERT INTO tracks " . ($names) . ($values);
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$playlistId, \PDO::PARAM_INT); 
        $stmt->bindValue(2, $artist);
        $stmt->bindValue(3, $track);
        $stmt->bindValue(4, $album);
        $stmt->bindValue(5, $label);
        $stmt->bindValue(6, $this->nextSeq($playlistId));
        if($haveTag)
            $stmt->bindValue(7, $tag);

        $updateStatus = $stmt->execute();
        if(!$updateStatus)
            error_log("insertTrack: " . print_r($stmt->errorInfo(), true));

        $id = Engine::lastInsertId();

        if ($updateStatus == 1 && $doTimestamp) {
            // if inserted row is latest, then reordering is unnecessary
            $query = "SELECT id FROM tracks ".
                     "WHERE list = ? ".
                     "ORDER BY created DESC LIMIT 1";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, (int)$playlistId, \PDO::PARAM_INT);
            $row = $this->executeAndFetch($stmt);
            if($row && $row['id'] != $id) {
                $updateStatus = $this->reorderForTime($playlistId,
                                                      $id,
                                                      date(self::TIME_FORMAT_SQL))?2:0;
            } else
                $updateStatus = 2;
        }

        return $updateStatus;
    }
    
    public function updateTrack($playlistId, $id, $tag, $artist, $track, $album, $label, $dateTime) {
        $playlist = $this->getPlaylist($playlistId, 1);
        $trackRow  = $this->getTrack($id);
        $timestamp = $trackRow['created'];
        $timeChanged = false;

        if ($dateTime) {
            if ($this->isDateTimeWithinShow($dateTime, $playlist)) {
                $timestamp = $dateTime;
                $timeChanged = true;
            } else {
                error_log("Error: ignoring time update for $id, $dateTime");
            }
        }

        $query = "UPDATE tracks SET ";
        $query .= "artist=?, " .
                  "track=?, " .
                  "album=?, " .
                  "label=?, " .
                  "created=?, " .
                  "tag=" . ($tag?"?":"NULL");
        $query .= " WHERE id = ?";

        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $artist);
        $stmt->bindValue(2, $track);
        $stmt->bindValue(3, $album);
        $stmt->bindValue(4, $label);
        $stmt->bindValue(5, $timestamp);
        if($tag) {
            $stmt->bindValue(6, $tag);
            $stmt->bindValue(7, (int)$id, \PDO::PARAM_INT);
        } else
            $stmt->bindValue(6, (int)$id, \PDO::PARAM_INT);

        $success = $stmt->execute();
        if(!$success)
            error_log("updateTrack: " . print_r($stmt->errorInfo(), true));

        if($success && $timeChanged)
            $success = $this->reorderForTime($playlistId, $id, $timestamp);

        return $success;
    }

    public function insertTrackEntry($playlist, PlaylistEntry $entry, $wantTimestamp) {
        $id = 0;
        $success = $this->insertTrack($playlist,
                                      $entry->getTag(), $entry->getArtist(),
                                      $entry->getTrack(), $entry->getAlbum(),
                                      $entry->getLabel(),
                                      $wantTimestamp,
                                      $id);
        if($success)
            $entry->setId($id);
        return $success;
    }

    public function updateTrackEntry($playlist, PlaylistEntry $entry) {
        return $this->updateTrack($playlist, $entry->getId(),
                                      $entry->getTag(),
                                      $entry->getArtist(),
                                      $entry->getTrack(),
                                      $entry->getAlbum(),
                                      $entry->getLabel(),
                                      $entry->getCreated());
    }
    
    public function deleteTrack($id) {
        $query = "SELECT list, seq FROM tracks WHERE id = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$id, \PDO::PARAM_INT);
        $row = $this->executeAndFetch($stmt);

        $query = "DELETE FROM tracks WHERE id = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$id, \PDO::PARAM_INT);
        $success = $stmt->execute();

        if($success && $row && $row['seq']) {
            $query = "UPDATE tracks SET seq = seq - 1 ".
                     "WHERE seq > ? AND list = ?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $row['seq']);
            $stmt->bindValue(2, $row['list']);
            $success = $stmt->execute();
        }

        return $success;
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
    
        return $this->executeAndFetchAll($stmt);
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
}
