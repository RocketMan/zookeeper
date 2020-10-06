<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2020 Jim Mason <jmason@ibinx.com>
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
 * Chart operations
 */
class ChartImpl extends DBO implements IChart {
    public function getCategories() {
        $query = "SELECT id, name, code, director, email " .
                 "FROM categories ORDER BY id";
        $stmt = $this->prepare($query);
        return $stmt->executeAndFetchAll();
    }
    
    public function updateCategory($i, $name, $code, $dir, $email) {
        $query = "UPDATE categories SET " .
                 "name=?, code=?, director=?, email=? " .
                 "WHERE id=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $name);
        $stmt->bindValue(2, strtoupper($code));
        $stmt->bindValue(3, $dir);
        $stmt->bindValue(4, $email);
        $stmt->bindValue(5, $i);
        $success = $stmt->execute();
    
        // mysql_affected_rows will return 0 if the SET values in the
        // query are exactly the same as the existing columns; hence,
        // we cannot distinguish between a degenerate update and a
        // failed update resulting from a bad WHERE clause.  bummer.
        if($stmt->rowCount() >= 0)
            $success = true;
        return $success;
    }
    
    public function getNextAID() {
        $query = "SELECT afile_number, adddate FROM currents " .
                 "ORDER BY adddate DESC, afile_number DESC LIMIT 1";
        $stmt = $this->prepare($query);
        $row = $stmt->executeAndFetch(\PDO::FETCH_BOTH);
        $aid = (int)$row[0] + 1;
        $adate = $row[1];
        if($aid > 999) {
            // afile number may have rolled over; check to see
            $query = "SELECT afile_number FROM currents " .
                 "WHERE afile_number < 300 " .
                 "AND adddate = '" . $adate . "' " .
                 "ORDER BY adddate DESC, afile_number DESC LIMIT 1";
            $stmt = $this->prepare($query);
            if($stmt->execute() && ($row = $stmt->fetch())) {
                // yes, we've rolled over; get the correct aid
                $aid = (int)$row[0] + 1;
            }
        }
        if($aid < 100 || $aid > 999)
            $aid = 100;
        return $aid;
    }
    
    public function getAddDates($limit="") {
        $query = "SELECT adddate FROM currents " .
                 "GROUP BY adddate ORDER BY adddate DESC";
        if($limit)
            $query .= " LIMIT ?";
        $stmt = $this->prepare($query);
        if($limit)
            $stmt->bindValue(1, (int)$limit, \PDO::PARAM_INT);
        return $stmt->iterate(\PDO::FETCH_BOTH);
    }
    
    public function getAdd($date) {
        $query = "SELECT c.id, afile_number, c.tag, adddate, pulldate, ".
                 "c.category as afile_category, ".
                 "a.artist, a.album, a.medium, a.size, a.iscoll, ".
                 "a.created, a.updated, a.category, p.name label ".
                 "FROM currents c, albumvol a, publist p ".
                 "WHERE c.tag = a.tag AND a.pubkey = p.pubkey ".
                 "AND adddate = ? ORDER BY id";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $date);
        return $stmt->iterate();
    }
    
    public function getCurrents($date, $sort=0) {
        $query = "SELECT c.id, afile_number, c.tag, adddate, pulldate, ".
                 "c.category as afile_category, ".
                 "a.artist, a.album, a.medium, a.size, a.iscoll, ".
                 "a.created, a.updated, a.category, p.name label ".
                 "FROM currents c, albumvol a, publist p ".
                 "WHERE c.tag = a.tag AND a.pubkey = p.pubkey ".
                 "AND adddate <= ? AND pulldate > ?";
        if($sort)
            $query .= " ORDER BY a.artist, a.album";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $date);
        $stmt->bindValue(2, $date);
        return $stmt->iterate();
    }
    
    public function getCurrentsWithPlays($date=0) {
        if(!$date)
            $date = date("Y-m-d");
    
        // First, select out the currents with plays and add those up
        $query = "CREATE TEMPORARY TABLE tmp_current_plays ".
                 "ENGINE=MEMORY ".
                 "SELECT c.id, afile_number, c.tag, adddate, pulldate, ".
                 "c.category afile_category, ".
                 "IF(DATEDIFF(?, adddate) > 7, ".
                 "FLOOR(count(t.tag)/DATEDIFF(?, adddate)*100), null) sizzle, ".
                 "a.artist, a.album, a.medium, a.size, a.iscoll, ".
                 "a.created, a.updated, a.category, p.name label ".
                 "FROM tracks t ".
                 "JOIN lists l ON t.list = l.id ".
                 "JOIN albumvol a ON t.tag = a.tag ".
                 "JOIN publist p ON a.pubkey = p.pubkey ".
                 "RIGHT JOIN currents c ON c.tag = t.tag ".
                 "WHERE l.showdate BETWEEN c.adddate AND c.pulldate ".
                 "AND adddate <=? AND pulldate > ? ".
                 "GROUP BY t.tag";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $date);
        $stmt->bindValue(2, $date);
        $stmt->bindValue(3, $date);
        $stmt->bindValue(4, $date);
        $stmt->execute();
    
        // Now, get the currents with no plays
        $query = "CREATE TEMPORARY TABLE tmp_current_no_plays ".
                 "ENGINE=MEMORY ".
                 "SELECT c.id, afile_number, c.tag, adddate, pulldate, ".
                 "c.category afile_category, IF(DATEDIFF(?, adddate) > 7, 0, null) sizzle, ".
                 "a.artist, a.album, a.medium, a.size, a.iscoll, ".
                 "a.created, a.updated, a.category, p.name label ".
                 "FROM currents c, albumvol a, publist p ".
                 "WHERE adddate <= ? AND pulldate > ? ".
                 "AND c.tag = a.tag AND a.pubkey = p.pubkey ".
                 "AND c.tag NOT IN (SELECT tag FROM tmp_current_plays)";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $date);
        $stmt->bindValue(2, $date);
        $stmt->bindValue(3, $date);
        $stmt->execute();
    
        // Result is the union of these two result sets
        $query = "SELECT * FROM tmp_current_plays ".
                 "UNION SELECT * FROM tmp_current_no_plays";
        $stmt = $this->prepare($query);
        $result = $stmt->iterate(\PDO::FETCH_BOTH);
    
        // Clean up
        $stmt = $this->prepare("DROP TABLE tmp_current_plays");
        $stmt->execute();
        $stmt = $this->prepare("DROP TABLE tmp_current_no_plays");
        $stmt->execute();
    
        return $result;
    }
    
    public function addAlbum($aid, $tag, $adddate, $pulldate, $cats) {
        $query = "INSERT INTO currents " .
                 "(afile_number, tag, adddate, pulldate, category) " .
                 "VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $aid);
        $stmt->bindValue(2, $tag);
        $stmt->bindValue(3, $adddate);
        $stmt->bindValue(4, $pulldate);
        $stmt->bindValue(5, $cats);
        return $stmt->execute() && $stmt->rowCount() >= 0;
    }
    
    public function updateAlbum($id, $aid, $tag, $adddate, $pulldate, $cats) {
        $query = "UPDATE currents SET " .
                 "afile_number=?, tag=?, adddate=?, " .
                 "pulldate=?, category=? " .
                 "WHERE id=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $aid);
        $stmt->bindValue(2, $tag);
        $stmt->bindValue(3, $adddate);
        $stmt->bindValue(4, $pulldate);
        $stmt->bindValue(5, $cats);
        $stmt->bindValue(6, $id);
    
        // rowCount will return 0 if the SET values in the
        // query are exactly the same as the existing columns; hence,
        // we cannot distinguish between a degenerate update and a
        // failed update resulting from a bad WHERE clause.  bummer.
        return $stmt->execute() && $stmt->rowCount() >= 0;
    }
    
    public function deleteAlbum($id) {
        $query = "DELETE FROM currents WHERE id = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $id);
        return $stmt->execute() && $stmt->rowCount() >= 0;
    }
    
    public function getAlbum($id) {
        settype($id, "integer");
        $query = "SELECT * FROM currents WHERE id = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $id);
        return $stmt->executeAndFetch(\PDO::FETCH_BOTH);
    }
    
    public function getAlbumByTag($tag) {
        settype($tag, "integer");
        $query = "SELECT * FROM currents WHERE tag = ? ORDER BY adddate DESC";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $tag);
        return $stmt->executeAndFetch(\PDO::FETCH_BOTH);
    }
    
    public function getAlbumPlays($tag, $startDate="", $endDate="", $limit="") {
        settype($tag, "integer");
        $query = "SELECT * FROM plays WHERE tag = ?";
        if($startDate && $endDate) {
            $dow = date("w", strtotime($endDate));
            if($dow) {
                // adjust endDate forward to the chart date
                list($y,$m,$d) = explode("-", $endDate);
                $endDate = date("Y-m-d", mktime(0,0,0,
                                   $m,
                                   $d+7-$dow,
                                   $y));
            }
            $query .= " AND week BETWEEN ? AND ?";
        }
        $query .= " ORDER BY week DESC";
        if($limit)
            $query .= " LIMIT ?";
        $stmt = $this->prepare($query);
        $p = 1;
        $stmt->bindValue($p++, $tag);
        if($startDate && $endDate) {
            $stmt->bindValue($p++, $startDate);
            $stmt->bindValue($p++, $endDate);
        }
        if($limit)
            $stmt->bindValue($p++, (int)$limit, \PDO::PARAM_INT);
        return $stmt->iterate(\PDO::FETCH_BOTH);
    }
    
    public function getChartDates($limit=0) {
        $query = "SELECT week FROM plays " .
                 "GROUP BY week ORDER BY week DESC";
        if($limit)
            $query .= " LIMIT ?";
        $stmt = $this->prepare($query);
        if($limit)
            $stmt->bindValue(1, (int)$limit, \PDO::PARAM_INT);
        return $stmt->iterate(\PDO::FETCH_BOTH);
    }
    
    public function getChartDatesByYear($year, $limit=0) {
        $query = "SELECT week FROM plays " .
                 "WHERE week >= ? AND week <= ? " .
                 "GROUP BY week ORDER BY week DESC";
        if($limit)
            $query .= " LIMIT ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, "$year-01-01");
        $stmt->bindValue(2, "$year-12-31");
        if($limit)
            $stmt->bindValue(3, (int)$limit, \PDO::PARAM_INT);
        return $stmt->iterate();
    }
    
    public function getChartYears($limit=0) {
        $query = "SELECT YEAR(week) FROM plays " .
                 "GROUP BY YEAR(week) ORDER BY YEAR(week) DESC";
        if($limit)
            $query .= " LIMIT ?";
        $stmt = $this->prepare($query);
        if($limit)
            $stmt->bindValue(1, (int)$limit, \PDO::PARAM_INT);
        return $stmt->iterate(\PDO::FETCH_BOTH);
    }
    
    public function getChartMonths($limit=0) {
        $query = "SELECT week FROM plays " .
                 "GROUP BY YEAR(week), MONTH(week) " .
                 "ORDER BY week DESC";
        if($limit)
            $query .= " LIMIT ?";
        $stmt = $this->prepare($query);
        if($limit)
            $stmt->bindValue(1, (int)$limit, \PDO::PARAM_INT);
        return $stmt->iterate();
    }
    
    private function getChartAlbumInfo(&$albums) {
        $queryset = "";
        $tags = [];
        foreach($albums as &$album) {
            $tag = $album["tag"];
            $queryset .= ", $tag";
            $tags[$tag] = &$album;
        }
        $query = "SELECT tag, artist, album, category, medium, size, name " .
                 "FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey " .
                 "WHERE tag IN (0" . $queryset . ")";
        $stmt = $this->prepare($query);
        $stmt->execute();
        while($row = $stmt->fetch()) {
            $album = &$tags[$row["tag"]];
            if (preg_match("/^\[coll\]/i", $row["artist"]))
                $album["artist"] = "COLL";
            else
                $album["artist"] = $row["artist"];
            $album["album"] = $row["album"];
            $album["category"] = $row["category"];
            $album["medium"] = $row["medium"];
            $album["size"] = $row["size"];
            $album["label"] = $row["name"];
        }
    }
    
    public function getChart(&$result, $startDate, $endDate, $limit="", $category="") {
        $tagCache = [];
        $query = "SELECT plays.tag, sum(plays) p";
    
        if(!$startDate)
            $query .= ", w.prev, w.lw";
    
        $query .= " FROM plays";
    
        if(!$startDate) {
            // We're doing a weekly chart, so go back and get last week's
            // plays as well.
            list($y, $m, $d) = explode("-", $endDate);
            $lastWeek = date("Y-m-d", mktime(0,0,0,$m,$d-7,$y));
    
            $queryLast = "CREATE TEMPORARY TABLE tmp_lastweek " .
                         "(lw int primary key auto_increment, unique(tag)) " .
                         "ENGINE=MEMORY ";
    
            $queryLast .= "SELECT tag, sum(plays) prev FROM plays";
            $queryLast .= " WHERE week=?";
            if($category)
                $queryLast .= " AND find_in_set(?, category)";
            $queryLast .= " GROUP BY tag";
            $stmt = $this->prepare($queryLast);
            $stmt->bindValue(1, $lastWeek);
            if($category)
                $stmt->bindValue(2, $category);
            $stmt->execute();
    
            $query .= " LEFT JOIN tmp_lastweek w ON w.tag = plays.tag";
        }
    
        if($startDate && $endDate)
            $query .= " WHERE week BETWEEN ? AND ?";
        else if($startDate)
            $query .= " WHERE week >= ?";
        else
            $query .= " WHERE week = ?";
        if($category)
            $query .= " AND find_in_set(?, category)";
    
        $query .= " GROUP BY plays.tag ORDER BY p DESC";
    
        if(!$startDate)
            $query .= ", prev DESC";
    
        $query .= ", plays.tag";
    
        if($limit)
            $query .= " LIMIT ?";

        $stmt = $this->prepare($query);
        $p = 1;
        if($startDate && $endDate) {
            $stmt->bindValue($p++, $startDate);
            $stmt->bindValue($p++, $endDate);
        } else if($startDate)
            $stmt->bindValue($p++, $startDate);
        else
            $stmt->bindValue($p++, $endDate);
        if($category)
            $stmt->bindValue($p++, $category);
        if($limit)
            $stmt->bindValue($p++, (int)$limit, \PDO::PARAM_INT);
        $stmt->execute();
    
        //echo "DEBUG LAST: $queryLast<BR>";
        //echo "DEBUG: $query<BR>";
    
        // Prepare the results
        $i = 0;
        while($row = $stmt->fetch()) {
            $result[$i]["tag"] = $row[0];
            $result[$i]["PLAYS"] = $row[1];
            $result[$i]["rank"] = $i+1;
            if(!$startDate) {
                $result[$i]["PREVWEEK"] = $row[2];
                $result[$i]["PREVRANK"] = $row[3];
            }
    
            $tagCache[$row[0]] = 1;
    
            $i++;
        }
    
        // Cleanup
        if(!$startDate) {
            if(!$limit || $i < $limit) {
                // append zero play albums for this week to fill out chart
                $query = "SELECT w.tag, prev, lw, plays FROM tmp_lastweek w";
                $query .= " LEFT JOIN plays ON w.tag = plays.tag";
                $query .= " AND plays.week=?";
                $query .= " WHERE plays IS NULL ORDER BY prev DESC, w.tag";
                if($limit)
                    $query .= " LIMIT ?";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, $endDate);
                if($limit)
                    $stmt->bindValue(2, (int)($limit - $i), \PDO::PARAM_INT);
                $stmt->execute();    

                while($row = $stmt->fetch()) {
                    $result[$i]["tag"] = $row[0];
                    $result[$i]["PLAYS"] = 0;
                    $result[$i]["PREVWEEK"] = $row[1];
                    $result[$i]["PREVRANK"] = $row[2];
                    $result[$i]["rank"] = $i+1;
    
                    $tagCache[$row[0]] = 1;
    
                    $i++;
                }
    
                // Unbounded charts may need padding, too
                if(!$limit || $limit > 17)
                    $limit = 17;  // a respectable number
    
                // If there's STILL not enough, go back until we pad it out
                $d -= 14;
                $maxWeeks = 10;
                while($maxWeeks-- && $i < $limit) {
                    $lastWeek = date("Y-m-d", mktime(0,0,0,$m,$d,$y));
                    $d -= 7;
    
                    $queryLast = "SELECT tag, sum(plays) prev FROM plays".
                                 " WHERE week=?";
                    if($category)
                        $queryLast .= " AND find_in_set(?, category)";
                    $queryLast .= " GROUP BY tag ORDER BY prev";
                    $stmt = $this->prepare($queryLast);
                    $stmt->bindValue(1, $lastWeek);
                    if($category)
                        $stmt->bindValue(2, $category);
                    $stmt->execute();
                    while($i < $limit && ($row = $stmt->fetch())) {
                        if(!array_key_exists($row[0], $tagCache)) {
                            $result[$i]["tag"] = $row[0];
                            $result[$i]["PLAYS"] = 0;
                            $result[$i]["PREVWEEK"] = $row[1];
                            $result[$i]["PREVRANK"] = 0;
                            $result[$i]["rank"] = $i+1;
    
                            $tagCache[$row[0]] = 1;
    
                            $i++;
                        }
                    }
                }
            }
            $stmt = $this->prepare("DROP TABLE tmp_lastweek");
            $stmt->execute();
        }
        
        $this->getChartAlbumInfo($result);
    }
    
    public function getChartEMail() {
        $query = "SELECT id, chart, address FROM chartemail ORDER BY id";
        $stmt = $this->prepare($query);
        return $stmt->iterate();
    }
    
    public function updateChartEMail($i, $address) {
        $query = "UPDATE chartemail SET " .
                 "address=? " .
                 "WHERE id=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $address);
        $stmt->bindValue(2, $i);
        $success = $stmt->execute();
    
        // rowCount will return 0 if the SET values in the
        // query are exactly the same as the existing columns; hence,
        // we cannot distinguish between a degenerate update and a
        // failed update resulting from a bad WHERE clause.  bummer.
        if($stmt->rowCount() >= 0)
            $success = true;
        return $success;
    }
    
    public function getWeeklyActivity($date) {
        list($y, $m, $d) = explode("-", $date);
        $endDate = date("Y-m-d", mktime(0,0,0,$m,$d-1,$y));
        $startDate = date("Y-m-d", mktime(0,0,0,$m,$d-7,$y));
        $query = "SELECT l.id, l.dj, a.airname, l.showdate, l.showtime, l.description, " .
                 "count(*) total, count(c.tag) afile " . 
                 "FROM lists l " .
                 "JOIN tracks t ON l.id = t.list " .
                 "JOIN airnames a ON a.id = l.airname " .
                 "LEFT JOIN currents c " .
                     "ON c.tag = t.tag " .
                         "AND l.showdate BETWEEN c.adddate AND c.pulldate " .
                 "WHERE l.showdate between ? AND ? " .
                         "AND t.artist NOT LIKE '" .
                         IPlaylist::SPECIAL_TRACK . "%' " .
                 "GROUP BY l.id;";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $startDate);
        $stmt->bindValue(2, $endDate);
        return $stmt->iterate();
    }

    public function doChart($chartDate, $maxSpins, $limitPerDJ=0) {
        // ending date is 1 day before the chart date
        $endDate = \DateTime::createFromFormat('Y-m-d',
                                                $chartDate)->modify('-1 day');
        $endDateS = $endDate->format('Y-m-d');

        // chart period is 7 days inclusive before the ending date
        $startDate = clone $endDate;
        $startDate->modify('-6 day'); // this is 6 as days are inclusive
        $startDateS = $startDate->format('Y-m-d');

        // use a fresh connection so we don't adversely affect
        // anything else by changing the attributes, etc.
        $pdo = $this->newPDO();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE,
                                        \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES,
                                        false);
        $pdo->beginTransaction();

        try {
            $query = "CREATE TEMPORARY TABLE tmp_maxplays ".
                "ENGINE=MEMORY ".
                "SELECT t.list, t.tag, ".
                   "LEAST(COUNT(*), $maxSpins) plays, c.category ".
                "FROM lists l ".
                "JOIN tracks t ON t.list = l.id ".
                "LEFT JOIN currents c ".
                   "ON c.tag = t.tag ".
                      "AND l.showdate BETWEEN c.adddate AND c.pulldate ".
                "WHERE l.airname IS NOT NULL ".
                   "AND l.showdate BETWEEN '$startDateS' AND '$endDateS' ".
                   "AND c.afile_number IS NOT NULL ";
            $query .= $limitPerDJ?
                   "GROUP BY t.tag, l.dj":"GROUP BY t.tag, t.list";
            $pdo->exec($query);

            $query = "INSERT INTO plays (week, tag, category, plays) ".
                "SELECT '$chartDate', tag, category, sum(plays) ".
                "FROM tmp_maxplays ".
                "GROUP BY tag";
            $pdo->exec($query);

            $query = "DROP TABLE tmp_maxplays";
            $pdo->exec($query);

            $pdo->commit();

            // if we made it this far, success!
            return true;
        } catch(Exception $e) {
            $pdo->rollBack();
            error_log("doChart: " . $e->getMessage());
            return false;
        }
    }

    public function getMonthlyChartStart($month, $year) {
        // Determine day of week for the first of the month
        $dow = date("w", mktime(0,0,0,$month,1,$year));
    
        // Calculate delta to first chart for month
        $delta = (($dow < 4)?8:15) - $dow;
    
        return date("Y-m-d", mktime(0,0,0,$month,$delta,$year));
    }
    
    public function getMonthlyChartEnd($month, $year) {
        // Get start chart for following month
        $next = $this->getMonthlyChartStart($month+1, $year);
        list($y,$m,$d) = explode("-", $next);
    
        // Offset back 7 days to get last chart of specified month
        return date("Y-m-d", mktime(0,0,0,$m,$d-7,$y));
    }
}
