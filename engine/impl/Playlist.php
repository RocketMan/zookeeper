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

namespace ZK\Engine;


/**
 * Playlist operations
 */
class PlaylistImpl extends DBO implements IPlaylist {
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
        return $stmt->iterate();
    }
    
    public function getPlaylist($playlist, $withAirname=0) {
        if($withAirname)
            $query = "SELECT l.description, l.showdate, l.showtime, " .
                     "       a.id aid, a.airname, l.dj, l.origin, l.id " .
                     "FROM lists l LEFT JOIN airnames a " .
                     "ON l.airname = a.id " .
                     "WHERE l.id = ?";
        else
            $query = "SELECT description, showdate, showtime, airname, dj, origin, id " .
                     "FROM lists WHERE id=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$playlist, \PDO::PARAM_INT);
        return $stmt->executeAndFetch(\PDO::FETCH_BOTH);
    }
    
    public function getPlaylists($onlyPublished=0, $withAirname=0,
                               $showDate="", $airname="", $user="", $desc=1, $limit=null) {
        if($withAirname)
            $query = "SELECT l.id, l.showdate, l.showtime, l.description, " .
                     "a.id airid, a.airname, l.origin " .
                     "FROM lists l " .
                     "LEFT JOIN airnames a ON l.airname = a.id ";
        else
            $query = "SELECT id, showdate, showtime, description, origin FROM lists l ";

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
        // Incur the overhead of checking whether the show spans midnight
        // only if we're within the max show time interval around midnight
        $windowStartMin = 24 * 60 - self::MAX_SHOW_LEN;
        $windowStart = sprintf("%02d%02d", floor($windowStartMin / 60), $windowStartMin % 60);
        $windowEnd = sprintf("%02d%02d", floor(self::MAX_SHOW_LEN / 60), self::MAX_SHOW_LEN % 60);

        $now = new \DateTime("now");
        [$date, $hour] = explode(' ', $now->format(self::TIME_FORMAT));

        $query = "SELECT l.id, l.showdate, l.showtime, l.description, " .
                 "a.id airid, a.airname, l.dj " .
                 "FROM lists l LEFT JOIN airnames a " .
                 "ON l.airname = a.id " .
                 "WHERE l.showdate = ? " .
                 "AND l.airname IS NOT NULL " .
                 "AND LEFT(l.showtime, 4) <= ? " .
                 "AND ( MID(l.showtime, 6, 4) > ? ";

        if($hour >= $windowStart) {
            $query .= "OR MID(l.showtime, 6, 4) < LEFT(l.showtime, 4) ) ";
        } else {
            $query .= ") ";
            if($hour <= $windowEnd) {
                $query .= "OR l.showdate = ? " .
                          "AND l.airname IS NOT NULL " .
                          "AND MID(l.showtime, 6, 4) < LEFT(l.showtime, 4) " .
                          "AND MID(l.showtime, 6, 4) > ? ";
            }
        }

        $query .= "ORDER BY l.showdate DESC, l.showtime DESC, l.id DESC";

        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $date);
        $stmt->bindValue(2, $hour);
        $stmt->bindValue(3, $hour);
        if($hour <= $windowEnd) {
            $now->modify("-1 day");
            $stmt->bindValue(4, $now->format("Y-m-d"));
            $stmt->bindValue(5, $hour);
        }
        return $stmt->iterate(\PDO::FETCH_BOTH);
    }
    
    private function purgeEmptyPlaylist($user, $date, $time) {
        // search for last entered playlist by this DJ on this date
        $query = "SELECT l.id AS lid, t.id AS tid FROM lists l ".
                 "LEFT JOIN tracks t ON l.id = t.list ".
                 "WHERE dj = ? AND showdate = ? AND showtime = ? ".
                 "ORDER BY l.id DESC LIMIT 1";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $stmt->bindValue(2, $date);
        $stmt->bindValue(3, $time);
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

    public function insertPlaylist($user, $date, $time, $description, $airname, $autoPurge = true) {
        list($year, $month, $day) = explode("-", $date);
        $canonicalDate = "$year-$month-$day";

        if($autoPurge)
            $this->purgeEmptyPlaylist($user, $canonicalDate, $time);

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

    protected function slicePlaylist($playlist, $time) {
        // use a fresh connection so we don't adversely affect
        // anything else by changing the attributes, etc.
        $pdo = $this->newPDO();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->exec("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
        $pdo->beginTransaction();

        try {
            $query = "SELECT showdate, showtime FROM lists WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(1, $playlist);
            $from = $stmt->executeAndFetch();
            $date = $from['showdate'];
            $oldTime = explode('-', $from['showtime']);
            $newTime = explode('-', $time);
            if(count($newTime) == 1)
                $newTime[] = $oldTime[1];
            $fromStamp = \DateTime::createFromFormat(self::TIME_FORMAT,
                        $date . " " . $newTime[0]);
            $toStamp = \DateTime::createFromFormat(self::TIME_FORMAT,
                        $date . " " . $newTime[1]);
            // event timestamps include seconds; we adjust toStamp
            // to capture all events within the last minute
            $toStamp->modify("+1 minute");

            // if playlist spans midnight, end time is next day
            if($toStamp < $fromStamp)
                $toStamp->modify("+1 day");

            $query = "SELECT id FROM tracks WHERE list = ? " .
                     "AND created >= ? ORDER BY seq, id LIMIT 1";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(1, $playlist);
            $stmt->bindValue(2, $toStamp->format(self::TIME_FORMAT_SQL));
            $highRow = $stmt->executeAndFetch();
            if($highRow && ($seq = $this->getSeq($playlist, $highRow['id']))) {
                // getSeq() populated seq for the delete
                $query = "DELETE FROM tracks WHERE list = ? AND seq >= ?";
                $stmt = $pdo->prepare($query);
                $stmt->bindValue(1, $playlist);
                $stmt->bindValue(2, $seq);
                $stmt->execute();
            }

            $query = "SELECT id FROM tracks WHERE list = ? " .
                     "AND created < ? ORDER BY seq DESC, id DESC LIMIT 1";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(1, $playlist);
            $stmt->bindValue(2, $fromStamp->format(self::TIME_FORMAT_SQL));
            $lowRow = $stmt->executeAndFetch();
            if($lowRow && ($seq = $this->getSeq($playlist, $lowRow['id']))) {
                // getSeq() populated seq for the delete and update
                $query = "DELETE FROM tracks WHERE list = ? AND seq <= ?";
                $stmt = $pdo->prepare($query);
                $stmt->bindValue(1, $playlist);
                $stmt->bindValue(2, $seq);
                $stmt->execute();

                $query = "UPDATE tracks SET seq = seq - ? WHERE list = ?";
                $stmt = $pdo->prepare($query);
                $stmt->bindValue(1, $seq);
                $stmt->bindValue(2, $playlist);
                $stmt->execute();
            }

            $query = "UPDATE lists SET showtime = ? WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(1, implode('-', $newTime));
            $stmt->bindValue(2, $playlist);
            $stmt->execute();

            // if we made it this far, success!
            $pdo->commit();
        } catch(\Exception $e) {
            error_log("slicePlaylist: " . $e->getMessage());
            $pdo->rollBack();
            throw $e;
        }
    }

    public function duplicatePlaylist($playlist, $time = null) {
        $query = "SELECT dj, showdate, showtime, description, airname " .
                 "FROM lists WHERE id = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $playlist);
        $from = $stmt->executeAndFetch();

        $success = $from?$this->insertPlaylist($from['dj'],
                                     $from['showdate'], $from['showtime'],
                                     $from['description'],
                                     $from['airname'], false):false;
        if($success) {
            $newListId = $this->lastInsertId();

            $query = "UPDATE lists SET origin = ? WHERE id = ?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $playlist);
            $stmt->bindValue(2, $newListId);
            $stmt->execute();

            $query = "INSERT INTO tracks " .
                     "(list, tag, artist, track, album, label, created, seq) ".
                     "SELECT ?, tag, artist, track, album, label, created, seq ".
                     "FROM tracks WHERE list = ? ".
                     "ORDER BY seq, id";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $newListId);
            $stmt->bindValue(2, $playlist);
            $success = $stmt->execute();

            if($success && $time) {
                try {
                    $this->slicePlaylist($newListId, $time);
                } catch(\Exception $e) {
                    $success = false;
                }
            }

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

            if(!$success)
                $this->deletePlaylist($newListId, true);
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
        $query = "SELECT tag, artist, track, album, label, id, created, list FROM tracks " .
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
        while($tracks && ($track = $tracks->fetch()) &&
                !$observer->observe(new PlaylistEntry($track)));
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
    public function getTimestampWindowInternal($playlist, $allowGrace = true) {
        $result = null;
        if($playlist && ($showtime = $playlist['showtime'])) {
            $timeAr = explode("-", $showtime);
            if(count($timeAr) == 2) {
                $timeStr = $playlist['showdate'] . " " . $timeAr[0];
                $start = \DateTime::createFromFormat(self::TIME_FORMAT, $timeStr);
                if($start) {
                    $end = clone $start;

                    // end time can be midnight or later
                    // in this case, adjust to the next day
                    if($timeAr[1] < $timeAr[0])
                        $end->modify("+1 day");

                    $end->setTime(substr($timeAr[1], 0, 2),
                                  substr($timeAr[1], 2, 2));

                    if($allowGrace) {
                        $start->modify(self::GRACE_START);
                        $end->modify(self::GRACE_END);
                    }

                    $result = [
                        "start" => $start,
                        "end" => $end
                    ];
                }
            }
        }
        return $result;
    }

    public function getTimestampWindow($playlistId, $allowGrace = true) {
        $playlist = $this->getPlaylist($playlistId);
        return $this->getTimestampWindowInternal($playlist, $allowGrace);
    }

    // return true if dateTime is within the show time range or null.
    public function isWithinShow($dateTime, $listRow, $allowGrace = true) {
        $retVal = $dateTime == null;
        if ($dateTime != null) {
            $window = $this->getTimestampWindowInternal($listRow, $allowGrace);
            if($window) {
                $retVal = $dateTime >= $window['start'] &&
                          $dateTime <= $window['end'];
            }
        }
        return $retVal;
    }

    // return true if "now" is within the show start/end time & date.
    public function isNowWithinShow($listRow, $allowGrace = true) {
        $nowDateTime = new \DateTime("now");
        return $this->isWithinShow($nowDateTime, $listRow, $allowGrace);
    }

    public function isDateTimeWithinShow($timeStamp, $listRow) {
        $dateTime = new \DateTime($timeStamp);
        return $this->isWithinShow($dateTime, $listRow);
    }

    public function hashPlaylist($playlistId) {
        $query = "SELECT md5(group_concat(concat_ws('|', id, created) ORDER BY seq, id)) hash FROM tracks WHERE list = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, (int)$playlistId, \PDO::PARAM_INT);
        $row = $stmt->executeAndFetch();
        return $row['hash'];
    }

    public function lockPlaylist($playlistId) {
        // MyISAM supports only table-level locking
        $this->exec("LOCK TABLES tracks WRITE, t WRITE, lists READ, l READ");
    }

    public function unlockPlaylist($playlistId) {
        $this->exec("UNLOCK TABLES");
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
    
        $names = "(list, artist, track, album, label, seq, created {$tagName})";
        $values = "VALUES (?, ?, ?, ?, ?, ?, ? {$tagValue});";

        $this->lockPlaylist($playlistId);

        $query = "INSERT INTO tracks {$names} {$values}";
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

        $this->unlockPlaylist($playlistId);

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

        $this->lockPlaylist($row['list']);

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

        $this->unlockPlaylist($row['list']);

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
    
    private function getZootopiaAirname() {
        $proxies = Engine::param('push_proxy');
        if($proxies) {
            foreach($proxies as $proxy) {
                if($proxy['proxy'] ==
                        \ZK\PushNotification\ZootopiaListener::class &&
                        !($proxy['http_endpoints']['recent'] ?? false))
                    return $proxy['http_endpoints']['airname'] ?? null;
            }
        }
    }

    public function getLastPlays($tag, $count=0, $excludeAutomation=true, $excludeRebroadcasts=true) {
        settype($tag, "integer");
        $zootopia = $excludeAutomation ? $this->getZootopiaAirname() : null;
        if($zootopia) {
            if(!is_array($zootopia))
                $zootopia = [ $zootopia ];

            $zootopiaSet = str_repeat("?,", count($zootopia) - 1) . "?";
        }

        $query = "SELECT l.id, l.showdate, l.description, a.airname," .
                 "  count(*) plays," .
                 "  group_concat(t.track ORDER BY t.seq DESC, t.id DESC SEPARATOR 0x1e) tracks" .
                 " FROM tracks t" .
                 " JOIN lists l ON t.list = l.id " .
                 " LEFT JOIN airnames a ON l.airname = a.id" .
                 " WHERE t.tag = ? AND l.airname IS NOT NULL" .
                 ($excludeRebroadcasts ? " AND origin IS NULL" : "") .
                 ($zootopia ? " AND a.airname NOT IN ($zootopiaSet)" : "") .
                 " GROUP BY t.tag, l.id ORDER BY l.showdate DESC, l.showtime DESC";
        if($count)
            $query .= " LIMIT ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $tag);
        $param = 2;
        if($zootopia)
            foreach($zootopia as $airname)
                $stmt->bindValue($param++, $airname);
        if($count)
            $stmt->bindValue($param++, (int)$count, \PDO::PARAM_INT);
    
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

    /**
     * inject image data from the cache
     *
     * We do not query discogs on cache miss, as in general, the
     * cache will already be fully populated for the lookback period,
     * and moreover, discogs imposes limits on rapid, sucessive queries.
     */
    protected function injectImageData(&$entry) {
        $imageApi = Engine::api(IArtwork::class);
        if($entry['track_tag']) {
            // is the album already known to us?
            $image = $imageApi->getAlbumArt($entry['track_tag']);
            if($image) {
                // if yes, reuse it...
                $imageUuid = $image['image_uuid'];
                $infoUrl = $image['info_url'];
            }
        }

        if(!isset($imageUuid)) {
            // is the artist already known to us?
            $image = $imageApi->getArtistArt($entry['track_artist']);
            if($image) {
                // if yes, reuse it...
                $imageUuid = $image['image_uuid'];
                $infoUrl = $image['info_url'];
            }
        }

        $entry['info_url'] = $infoUrl ?? null;
        $entry['image_url'] = isset($imageUuid) ? $imageApi->getCachePath($imageUuid) : ($entry['info_url'] || $entry['track_tag'] ? "img/discogs.svg" : "img/blank.gif");
    }

    public function getPlaysBefore($timestamp, $limit) {
        $res = [];
        $dateTime = new \DateTime($timestamp ?? "now");
        $date = $dateTime->format("Y-m-d");
        $time = $dateTime->format("Hi");
        $timestamp = $dateTime->format(IPlaylist::TIME_FORMAT_SQL);

        $query = "SELECT id, showdate, showtime FROM lists " .
                 "WHERE showdate <= DATE(?) " .
                 "AND airname IS NOT NULL " .
                 "ORDER BY showdate DESC, showtime DESC, id DESC " .
                 "LIMIT 20";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $date);
        $result = $stmt->iterate();
        $nextShowStart = null;
        while(($list = $result->fetch()) && $limit > 0 ) {
            if($list['showdate'] == $date && $list['showtime'] > $time)
                continue;

            $query = "SELECT id, artist track_artist, track track_title, album track_album, tag track_tag, created track_time " .
                 "FROM tracks WHERE list = ? " .
                 "AND created < ? " .
                 "AND artist NOT LIKE '".IPlaylist::SPECIAL_TRACK."%' " .
                 "AND created IS NOT NULL ORDER BY created DESC";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $list['id']);
            $stmt->bindValue(2, $timestamp);
            $tracks = $stmt->iterate();
            $prevLimit = $limit;
            while(($track = $tracks->fetch()) && $limit-- > 0) {
                if(preg_match('/(\.gov|\.org|GED|Literacy|NIH|Ad\ Council)/', implode(' ', $track)) || empty(trim($track['track_artist']))) {
                    // it's probably a PSA coded as a spin; let's skip it
                    $limit++;
                    continue;
                }

                // if spin overlaps later playlist, skip it
                if($nextShowStart && $track['track_time'] >= $nextShowStart) {
                    $limit++;
                    continue;
                }

                if($track['track_tag'])
                    $track['track_artist'] = PlaylistEntry::swapNames($track['track_artist']);
                $this->injectImageData($track);
                $res[] = $track;
            }

            if($prevLimit != $limit &&
                    preg_match('/^(\d{2})(\d{2})\-\d{4}$/', $list['showtime'], $matches)) {
                $matches[] = "00";
                $nextShowStart = $list['showdate'] . " " .
                    implode(':', array_slice($matches, 1));
            }
        }

        return $res;
    }

    public function deletePlaylist($playlist, $permanent = false) {
        if($permanent) {
            $query = "DELETE FROM tracks, lists USING lists " .
                     "LEFT OUTER JOIN tracks ON lists.id = tracks.list " .
                     "WHERE lists.id = ?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $playlist);
            return $stmt->execute();
        }

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

    public function getNormalPlaylistCount($user) {
        $query = "SELECT COUNT(*) count FROM lists ".
                 "LEFT JOIN lists_del ON lists.id = lists_del.listid ".
                 "WHERE dj=? AND deleted IS NULL";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $row = $stmt->executeAndFetch();
        return $row["count"];
    }

    public function getDeletedPlaylistCount($user) {
        $query = "SELECT COUNT(*) count FROM lists ".
                 "LEFT JOIN lists_del ON lists.id = lists_del.listid ".
                 "WHERE dj=? AND deleted IS NOT NULL";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $row = $stmt->executeAndFetch();
        return $row["count"];
    }

    public function getListsSelNormal($user, $pos = 0, $count = 10000) {
        $query = "SELECT l.id, showdate, showtime, description, a.airname, origin ".
                 "FROM lists l ".
                 "LEFT JOIN lists_del ON l.id = lists_del.listid ".
                 "LEFT JOIN airnames a ON l.airname = a.id ".
                 "WHERE l.dj=? AND deleted IS NULL ".
                 "ORDER BY showdate DESC, showtime DESC ".
                 "LIMIT ?, ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $stmt->bindValue(2, $pos, \PDO::PARAM_INT);
        $stmt->bindValue(3, $count, \PDO::PARAM_INT);
        return $stmt->iterate(\PDO::FETCH_BOTH);
    }

    public function getListsSelDeleted($user, $pos = 0, $count = 10000) {
        $query = "SELECT l.id, showdate, showtime, description, ".
                 "ADDDATE(deleted, 30) expires, a.airname, origin ".
                 "FROM lists l ".
                 "LEFT JOIN lists_del ON l.id = lists_del.listid ".
                 "LEFT JOIN airnames a ON lists_del.airname = a.id ".
                 "WHERE l.dj=? AND deleted IS NOT NULL ".
                 "ORDER BY showdate DESC, showtime DESC ".
                 "LIMIT ?, ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $stmt->bindValue(2, $pos, \PDO::PARAM_INT);
        $stmt->bindValue(3, $count, \PDO::PARAM_INT);
        return $stmt->iterate(\PDO::FETCH_BOTH);
    }

    public function isListDeleted($id) {
        $query = "SELECT airname FROM lists_del WHERE listid = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $id);
        $result = $stmt->executeAndFetch();
        return $result?true:false;
    }
}
