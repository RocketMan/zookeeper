<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2021 Jim Mason <jmason@ibinx.com>
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
class PlaylistImpl extends DBO implements IPlaylist {
    const GRACE_START = "-15 minutes";
    const GRACE_END = "+30 minutes";
    const DUPLICATE_COMMENT =
        "Rebroadcast of an episode originally aired on %F j, Y%.";

    public function getShowdates($year, $month) {
        $yearMonth = sprintf("%04d-%02d", $year, $month) . "-%";
    
        $query = "SELECT showdate FROM lists " .
                 "WHERE airname IS NOT NULL " .
                 "AND showdate LIKE ? " .
                 "GROUP BY showdate ORDER BY showdate DESC";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $yearMonth);
        return $stmt->iterate();
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
        return $stmt->executeAndFetch(\PDO::FETCH_BOTH);
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

        return $stmt->iterate(\PDO::FETCH_BOTH);
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
        return $stmt->iterate(\PDO::FETCH_BOTH);
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
        $result = $stmt->executeAndFetch();
        if($result && !$result['tid']) {
            // DJ's last entered playlist on this date has no tracks; delete it
            //
            // Note:  This is an unceremonious, non-restorable delete, as
            // the empty playlist is deemed to have no value.
            $query = "DELETE FROM lists, lists_del USING lists ".
                     "LEFT OUTER JOIN lists_del ON lists_del.listid = lists.id ".
                     "WHERE lists.id = ?";
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
    
    public function updatePlaylist($playlist, $date, $time, $description, $airname, $deleteTracksPastEnd=0) {
        $query = "SELECT showdate, showtime FROM lists WHERE id = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $playlist);
        $row = $stmt->executeAndFetch();

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

                // rebase timestamps if both start and end times have changed,
                // or if the show date and start time have changed
                list($wasFrom, $wasTo) = explode("-", $row['showtime']);
                if($from != $wasFrom && ($offset || $to != $wasTo)) {
                    $wasFromStamp = \DateTime::createFromFormat(self::TIME_FORMAT,
                                    $date . " " . $wasFrom);
                    $diff = $wasFromStamp->diff($fromStamp);
                    $offset = $diff->h * 60 + $diff->i;
                    $offset *= $diff->invert?-1:1;

                    if($offset) {
                        $query = "UPDATE tracks " .
                                 "SET created = timestampadd(minute, ?, created) " .
                                 "WHERE list = ? AND created IS NOT NULL";
                        $stmt = $this->prepare($query);
                        $stmt->bindValue(1, $offset);
                        $stmt->bindValue(2, $playlist);
                        $stmt->execute();
                    }
                }

                // if playlist spans midnight, end time is next day
                if($toStamp < $fromStamp)
                    $toStamp->modify("+1 day");

                if($deleteTracksPastEnd) {
                    // delete tracks past end of new time range
                    //
                    // we select by id and then getSeq() to ensure
                    // track seq is populated for the delete
                    $query = "SELECT id FROM tracks " .
                             "WHERE list = ? AND created > ? " .
                             "ORDER BY seq, id LIMIT 1";
                    $stmt = $this->prepare($query);
                    $stmt->bindValue(1, $playlist);
                    $stmt->bindValue(2, $toStamp->format(self::TIME_FORMAT_SQL));
                    $end = $stmt->executeAndFetch();
                    if($end && ($seq = $this->getSeq($playlist, $end['id']))) {
                        $query = "DELETE FROM tracks WHERE list = ? AND seq >= ?";
                        $stmt = $this->prepare($query);
                        $stmt->bindValue(1, $playlist);
                        $stmt->bindValue(2, $seq);
                        $stmt->execute();
                    }
                } else {
                    // allow spin timestamps within the grace period
                    $fromStamp->modify(self::GRACE_START);
                    $toStamp->modify(self::GRACE_END);

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

    public function duplicatePlaylist($playlist) {
        $query = "SELECT dj, showdate, showtime, description, airname " .
                 "FROM lists WHERE id = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $playlist);
        $from = $stmt->executeAndFetch();

        $success = $from?$this->insertPlaylist($from['dj'],
                                     $from['showdate'], $from['showtime'],
                                     $from['description'],
                                     $from['airname']):false;
        if($success) {
            $newListId = $this->lastInsertId();
            $query = "INSERT INTO tracks " .
                     "(list, tag, artist, track, album, label, created, seq) ".
                     "SELECT ?, tag, artist, track, album, label, created, seq ".
                     "FROM tracks WHERE list = ? ".
                     "ORDER BY seq, id";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $newListId);
            $stmt->bindValue(2, $playlist);
            $success = $stmt->execute();

            if($success) {
                // insert comment at beginning of playlist
                $comment = preg_replace_callback("/%([^%]*)%/",
                    function($matches) use ($from) {
                        return \DateTime::createFromFormat(
                            self::TIME_FORMAT,
                            $from['showdate'] . " 0000")->format($matches[1]);
                    }, self::DUPLICATE_COMMENT);
                $entry = new PlaylistEntry();
                $entry->setComment($comment);
                $success = $this->insertTrackEntry($newListId, $entry, $status);
                if($success) {
                    $query = "SELECT id FROM tracks WHERE list = ? ".
                             "ORDER BY seq, id LIMIT 1";
                    $stmt = $this->prepare($query);
                    $stmt->bindValue(1, $newListId);
                    if(($first = $stmt->executeAndFetch()) &&
                            $first['id'] != $entry->getId())
                        $this->moveTrack($newListId, $entry->getId(), $first['id']);
                }
            }
        }

        return $success?$newListId:false;
    }

    public function reparentPlaylist($playlist, $user) {
        $query = "UPDATE lists SET dj=? WHERE id=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $stmt->bindValue(2, $playlist);
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
        $row = $stmt->executeAndFetch();
        if(!$row || !$row['seq']) {
            if($list && $this->populateSeq($list))
                $row = $stmt->executeAndFetch();
        }
        return $row?$row['seq']:false;
    }

    private function nextSeq($list) {
        $query = "SELECT MAX(seq) max FROM tracks WHERE list = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $list);
        $row = $stmt->executeAndFetch();
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

    // return timestamp of the newest track or null if none
    private function getLatestSpinTime($playlistId) {
        $query = "SELECT created FROM tracks ".
                 "WHERE list = ? ".
                 "ORDER BY created DESC LIMIT 1";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $playlistId);
        $row = $stmt->executeAndFetch();
        $haveIt = $row != null && $row['created'] != null;
        $latestSpin = $haveIt ? new \DateTime($row['created']) : null;
        return $latestSpin;
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
        $row = $stmt->executeAndFetch();
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
        $row = $stmt->executeAndFetch();
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
        return $stmt->executeAndFetch();
    }
    
    public function getTracks($playlist, $desc = 0) {
        $desc = $desc?"DESC":"";
        $query = "SELECT tag, artist, track, album, label, id, created FROM tracks " .
                 "WHERE list = ? ORDER BY seq $desc, id $desc";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$playlist, \PDO::PARAM_INT);
        return $stmt->iterate();
    }

    public function getTracksWithObserver($playlist, PlaylistObserver $observer, $desc = 0, $filter = null) {
        $tracks = $this->getTracks($playlist, $desc);
        if($tracks && $filter)
            $tracks = new $filter($tracks);
        while($tracks && ($track = $tracks->fetch()))
            $observer->observe(new PlaylistEntry($track));
        return $tracks;
    }

    public function getTrackCount($playlist) {
        $query = "SELECT COUNT(*) AS count FROM tracks WHERE list = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$playlist, \PDO::PARAM_INT);
        $row = $stmt->executeAndFetch();
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
                    $end = clone $start;
                    $start->modify(self::GRACE_START);

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

    // return true if dateTime is within the show time range or null.
    public function isWithinShow($dateTime, $listRow) {
        $retVal = $dateTime == null;
        if ($dateTime != null) {
            $window = $this->getTimestampWindowInternal($listRow);
            if($window) {
                $retVal = $dateTime >= $window['start'] &&
                          $dateTime <= $window['end'];
            }
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
    public function insertTrack($playlistId, $tag, $artist, $track, $album, $label,  $insertTime, &$id, &$status) {
        $row = $this->getPlaylist($playlistId, 1);

        $isWithinShow = $this->isWithinShow($insertTime, $row);
        if (!$isWithinShow) {
            $status = "Spin time is outside of show start/end times. ";
            error_log($status);
            return 0;
        }

        $timeValue = $insertTime != null ? $insertTime->format(self::TIME_FORMAT_SQL) : null;

        $haveTag  = ($tag != 0) && ($tag != "");
        $tagName  = $haveTag ? ", tag" : "";
        $tagValue = $haveTag ? ", ?"   : "";
    
        $names = "(list, artist, track, album, label, seq, created ${tagName})";
        $values = "VALUES (?, ?, ?, ?, ?, ?, ? ${tagValue});";

        $query = "INSERT INTO tracks ${names} ${values}";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$playlistId, \PDO::PARAM_INT); 
        $stmt->bindValue(2, $artist);
        $stmt->bindValue(3, $track);
        $stmt->bindValue(4, $album);
        $stmt->bindValue(5, $label);
        $stmt->bindValue(6, $this->nextSeq($playlistId));
        $stmt->bindValue(7, $timeValue);
        if($haveTag)
            $stmt->bindValue(8, $tag);

        $updateStatus = $stmt->execute()?1:0;
        if(!$updateStatus)
            error_log("insertTrack: " . print_r($stmt->errorInfo(), true));

        $id = $this->lastInsertId();

        if ($updateStatus == 1 && $insertTime != null) {
            // if inserted row is latest, then reordering is unnecessary
            $latestTime = $this->getLatestSpinTime($playlistId);
            if($latestTime != null && $latestTime > $insertTime) {
                $updateStatus = $this->reorderForTime($playlistId,
                                                      $id,
                                                      $insertTime->format(self::TIME_FORMAT_SQL))?2:0;
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

    public function insertTrackEntry($playlist, PlaylistEntry $entry, &$status) {
        $id = 0;
        $spinDateTime = null;
        $timeStr = $entry->getCreated();
        if ($timeStr != null && $timeStr != '') {
            $spinDateTime = new \DateTime($timeStr);
        }

        $success = $this->insertTrack($playlist,
                                      $entry->getTag(), $entry->getArtist(),
                                      $entry->getTrack(), $entry->getAlbum(),
                                      $entry->getLabel(),
                                      $spinDateTime,
                                      $id,
                                      $status);
  
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
        $row = $stmt->executeAndFetch();

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
    
    public function getTopPlays($airname=0, $days=41, $count=10) {
        $over = $airname?"distinct t.list":"*";
        $query = "SELECT t.tag, count($over) plays, l.showdate, IFNULL(a.artist, t.artist) artist, t.album, t.label, count(*)" .
                 " FROM tracks t JOIN lists l ON t.list = l.id " .
                 " LEFT JOIN albumvol a ON a.tag = t.tag " .
                 " WHERE t.artist NOT LIKE '".IPlaylist::SPECIAL_TRACK."%' AND".
                 " t.album <> '' AND t.label <> '' AND";
        if($airname)
            $query .= "    l.airname = ? AND";
        if($days)
            $query .= "    date_add(l.showdate, interval $days day) > now() ";
        $query .= " GROUP BY t.album, t.label ORDER BY 2 DESC, 7 DESC, t.artist LIMIT ?";
        $stmt = $this->prepare($query);
        $p = 1;
        if($airname)
            $stmt->bindValue($p++, (int)$airname, \PDO::PARAM_INT);
        $stmt->bindValue($p++, (int)$count, \PDO::PARAM_INT);
        return $stmt->executeAndFetchAll();
    }
    
    public function getLastPlays($tag, $count=0) {
        settype($tag, "integer");
        $query = "SELECT l.id, l.showdate, l.description, a.airname," .
                 "  count(*) plays," .
                 "  group_concat(t.track ORDER BY t.seq DESC, t.id DESC SEPARATOR 0x1e) tracks" .
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
    
        $spins = $stmt->executeAndFetchAll();
        foreach($spins as &$spin) {
            $spin['tracks'] = explode("\x1e", $spin['tracks']);
            $spin['lasttrack'] = $spin['tracks'][0];
        }
        return $spins;
    }
    
    public function getRecentPlays($airname, $count) {
        $query = "SELECT t.tag, t.artist, t.album, t.label, t.track FROM tracks t, lists l ";
        $query .= "WHERE t.list = l.id AND ";
        $query .= "l.airname = ? AND t.artist NOT LIKE '".IPlaylist::SPECIAL_TRACK."%' ";
        $query .= "GROUP BY l.showdate, t.artist, t.album, t.label ";
        $query .= "ORDER BY l.showdate DESC, t.id DESC LIMIT $count";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$airname, \PDO::PARAM_INT);
        return $stmt->executeAndFetchAll();
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
        $row = $stmt->executeAndFetch();
        $airname = $row?$row['airname']:null;

        // validate airname still exists
        if($airname) {
            $query = "SELECT COUNT(*) FROM airnames WHERE id = ?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $airname);
            $row = $stmt->executeAndFetch(\PDO::FETCH_BOTH);
            if($row && !$row[0])
                $airname = null;
        }

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

    public function purgeDeletedPlaylists($days=30) {
        $query = "DELETE FROM tracks, lists, lists_del USING lists_del " .
                 "INNER JOIN lists " .
                 "LEFT OUTER JOIN tracks ON lists_del.listid = tracks.list " .
                 "WHERE lists_del.listid = lists.id ".
                 "AND ADDDATE(deleted, ?) < NOW()";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $days);
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
        return $stmt->iterate(\PDO::FETCH_BOTH);
    }

    public function getListsSelDeleted($user) {
        $query = "SELECT id, showdate, showtime, description, ADDDATE(deleted, 30) FROM lists " .
                 "LEFT JOIN lists_del ON lists.id = lists_del.listid ".
                 "WHERE dj=? AND deleted IS NOT NULL ".
                 "ORDER BY showdate DESC, showtime DESC";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        return $stmt->iterate(\PDO::FETCH_BOTH);
    }

    /**
     * @param $file import file data
     * @param $user import to user
     * @param $allAirnames allow all airnames (true) or restrict to user's airnames (default)
     * @return id of imported playlist
     * @throws Exception if playlist import is unsuccessful
     */
    public function importPlaylist($file, $user, $allAirnames=false) {
        // parse the file
        $json = json_decode($file);

        // validate json root node is type 'show'
        if(!$json || $json->type != "show") {
            // also allow for 'show' encapsulated within a 'getPlaylistsRs'
            if($json && $json->data[0]->type == "show")
                $json = $json->data[0];
            else
                throw new \Exception("File is not in the expected format.  Ensure file is a valid JSON playlist.");
        }

        if($json && $json->type == "show") {
            // validate the show's properties
            $valid = false;
            list($year, $month, $day) = explode("-", $json->date);
            if($json->airname && $json->name && $json->time &&
                    checkdate($month, $day, $year))
                $valid = true;

            // lookup the airname
            if($valid) {
                $djapi = Engine::api(IDJ::class);
                $airname = $djapi->getAirname($json->airname, $allAirnames?"":$user);
                if(!$airname) {
                    // airname does not exist; try to create it
                    $success = $djapi->insertAirname(mb_substr($json->airname, 0, IDJ::MAX_AIRNAME_LENGTH), $user);
                    if($success > 0) {
                        // success!
                        $airname = $djapi->lastInsertId();
                    } else
                        $valid = false;
                }
            }

            // create the playlist
            if($valid) {
                $this->insertPlaylist($user, $json->date, $json->time, mb_substr($json->name, 0, IPlaylist::MAX_DESCRIPTION_LENGTH), $airname);
                $playlist = $this->lastInsertId();

                // insert the tracks
                $status = '';
                $window = $this->getTimestampWindow($playlist);
                foreach($json->data as $pentry) {
                    $entry = PlaylistEntry::fromJSON($pentry);
                    $created = $entry->getCreated();
                    if($created) {
                        try {
                            $stamp = PlaylistEntry::scrubTimestamp(
                                        new \DateTime($created), $window);
                            $entry->setCreated($stamp?$stamp->format(IPlaylist::TIME_FORMAT_SQL):null);
                        } catch(\Exception $e) {
                            error_log("failed to parse timestamp: $created");
                            $entry->setCreated(null);
                        }
                    }
                    $success = $this->insertTrackEntry($playlist, $entry, $status);
                }

                return $playlist;
            } else
                throw new \Exception("Show details are invalid");
        }
    }
}
