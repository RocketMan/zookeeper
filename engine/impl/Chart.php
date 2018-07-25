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
 * Chart operations
 */
class ChartImpl extends BaseImpl implements IChart {
    private static $labelCache;

    public function getCategories() {
        $query = "SELECT id, name, code, director, email " .
                 "FROM categories ORDER BY id";
        $stmt = $this->prepare($query);
        return $this->executeAndFetchAll($stmt);
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
        $row = $this->executeAndFetch($stmt, \PDO::FETCH_BOTH);
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
        return $this->execute($stmt, \PDO::FETCH_BOTH);
    }
    
    public function getAdd($date) {
        $query = "SELECT * FROM currents WHERE adddate = ? ORDER BY id";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $date);
        return $this->execute($stmt, \PDO::FETCH_BOTH);
    }
    
    public function getAdd2($date) {
        $query = "SELECT c.id, afile_number, c.tag, adddate, pulldate, ".
                 "c.category as afile_category, ".
                 "a.artist, a.album, a.medium, a.size, ".
                 "a.created, a.updated, a.category, p.name label ".
                 "FROM currents c, albumvol a, publist p ".
                 "WHERE c.tag = a.tag AND a.pubkey = p.pubkey ".
                 "AND adddate = ? ORDER BY id";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $date);
        return $this->execute($stmt);
    }
    
    public function getCurrents($date) {
        $query = "SELECT id, afile_number, tag, adddate, pulldate, ".
        "category as afile_category FROM currents WHERE adddate <= ? AND ".
                        "pulldate > ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $date);
        $stmt->bindValue(2, $date);
        return $this->execute($stmt, \PDO::FETCH_BOTH);
    }
    
    public function getCurrents2($date) {
        $query = "SELECT c.id, afile_number, c.tag, adddate, pulldate, ".
                 "c.category as afile_category, ".
                 "a.artist, a.album, a.medium, a.size, ".
                 "a.created, a.updated, a.category, p.name label ".
                 "FROM currents c, albumvol a, publist p ".
                 "WHERE c.tag = a.tag AND a.pubkey = p.pubkey ".
                 "AND adddate <= ? AND ".
                        "pulldate > ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $date);
        $stmt->bindValue(2, $date);
        return $this->execute($stmt);
    }
    
    public function getCurrentsWithPlays($date) {
        // First, select out the currents with plays and add those up
        $query = "CREATE TEMPORARY TABLE tmp_current_plays ".
                 "SELECT c.id, afile_number, c.tag, adddate, pulldate, ".
                 "category, count(t.tag) plays FROM tracks t ".
                 "JOIN lists l ON t.list = l.id ".
                 "RIGHT JOIN currents c ON c.tag = t.tag ".
                 "WHERE l.showdate BETWEEN c.adddate AND c.pulldate ".
                 "AND adddate <=? AND pulldate > ? ".
                 "GROUP BY t.tag";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $date);
        $stmt->bindValue(2, $date);
        $stmt->execute();
    
        // Now, get the currents with no plays
        $query = "CREATE TEMPORARY TABLE tmp_current_no_plays ".
                 "SELECT id, afile_number, tag, adddate, pulldate, ".
                 "category, 0 FROM currents ".
                 "WHERE adddate <= ? AND pulldate > ? ".
                 "AND tag NOT IN (SELECT tag FROM tmp_current_plays)";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $date);
        $stmt->bindValue(2, $date);
        $stmt->execute();
    
        // Result is the union of these two result sets
        $query = "SELECT * FROM tmp_current_plays ".
                 "UNION SELECT * FROM tmp_current_no_plays";
        $stmt = $this->prepare($query);
        $result = $this->execute($stmt, \PDO::FETCH_BOTH);
    
        // Clean up
        $stmt = $this->prepare("DROP TABLE tmp_current_plays");
        $stmt->execute();
        $stmt = $this->prepare("DROP TABLE tmp_current_no_plays");
        $stmt->execute();
    
        return $result;
    
        /*
        // JM 2006-08-14
        // This query didn't work, because if an album got spins (t.tag is
        // not NULL), but those spins are outside the a-file period
        // (l.showdate is NULL), then the album will be omitted from the list.
        // But if we remote the (t.tag is NULL or l.showdate is not null)
        // constraint, the album will appear, but the play count will be wrong.
        //
        $query = "SELECT c.id, afile_number, c.tag, adddate, pulldate, ".
                     "category, count(t.tag) plays FROM currents c ".
                     "LEFT JOIN tracks t ON t.tag = c.tag ".
                     "LEFT JOIN lists l ON l.id = t.list ".
                     "AND l.showdate BETWEEN adddate AND pulldate ".
                     "WHERE adddate <= ? AND pulldate > ? ".
                     "AND (t.tag IS NULL OR l.showdate IS NOT NULL) ".
                     "GROUP BY c.tag";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $date);
        $stmt->bindValue(2, $date);
        return $this->execute($stmt);
        */
    }
    
    public function getCurrentsWithPlays2($date=0) {
        if(!$date)
            $date = date("Y-m-d");
    
        // First, select out the currents with plays and add those up
        $query = "CREATE TEMPORARY TABLE tmp_current_plays ".
                 "SELECT c.id, afile_number, c.tag, adddate, pulldate, ".
                 "c.category afile_category, ".
                 "IF(DATEDIFF(?, adddate) > 7, ".
                 "FLOOR(count(t.tag)/DATEDIFF(?, adddate)*100), null) sizzle, ".
                 "a.artist, a.album, a.medium, a.size, ".
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
                 "SELECT c.id, afile_number, c.tag, adddate, pulldate, ".
                 "c.category afile_category, IF(DATEDIFF(?, adddate) > 7, 0, null) sizzle, ".
                 "a.artist, a.album, a.medium, a.size, ".
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
        $result = $this->execute($stmt, \PDO::FETCH_BOTH);
    
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
        return $this->executeAndFetch($stmt, \PDO::FETCH_BOTH);
    }
    
    public function getAlbumByTag($tag) {
        settype($tag, "integer");
        $query = "SELECT * FROM currents WHERE tag = ? ORDER BY adddate DESC";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $tag);
        return $this->executeAndFetch($stmt, \PDO::FETCH_BOTH);
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
        return $this->execute($stmt, \PDO::FETCH_BOTH);
    }
    
    public function getChartDates($limit=0) {
        $query = "SELECT week FROM plays " .
                 "GROUP BY week ORDER BY week DESC";
        if($limit)
            $query .= " LIMIT ?";
        $stmt = $this->prepare($query);
        if($limit)
            $stmt->bindValue(1, (int)$limit, \PDO::PARAM_INT);
        return $this->execute($stmt, \PDO::FETCH_BOTH);
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
        return $this->execute($stmt);
    }
    
    public function getChartYears($limit=0) {
        $query = "SELECT YEAR(week) FROM plays " .
                 "GROUP BY YEAR(week) ORDER BY YEAR(week) DESC";
        if($limit)
            $query .= " LIMIT ?";
        $stmt = $this->prepare($query);
        if($limit)
            $stmt->bindValue(1, (int)$limit, \PDO::PARAM_INT);
        return $this->execute($stmt, \PDO::FETCH_BOTH);
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
        return $this->execute($stmt);
    }
    
    public function getChartSortFn($a, $b) {
        // First, sort by descending number of plays
        $retval = $b["PLAYS"] - $a["PLAYS"];
    
        // Then, sort by descending number of plays from last week
        if(!$retval)
            $retval = (int)$b["PREVWEEK"] - (int)$a["PREVWEEK"];
    
        // Then, sort by tag number
        if(!$retval)
            $retval = $a["tag"] - $b["tag"];
    
        // Next, sort by artist name
        ////if(!$retval)
        ////    $retval = strcasecmp($a["artist"], $b["artist"]);
    
        // Finally, sort by album name
        ////if(!$retval)
        ////    $retval = strcasecmp($a["album"], $b["album"]);
    
        return $retval;
    }
    
    private function getChartFillInAlbumInfo(&$result, $i, $tag) {
        $libAPI = Engine::api(ILibrary::class);

        // Setup artist correctly for collections
        $albums = $libAPI->search(ILibrary::ALBUM_KEY, 0, 1, $tag);
        if (preg_match("/^\[coll\]/i", $albums[0]["artist"]))
            $result[$i]["artist"] = "COLL";
        else
            $result[$i]["artist"] = $albums[0]["artist"];
    
        // Get album name
        $result[$i]["album"] = $albums[0]["album"];
    
        // Get the medium
        $result[$i]["medium"] = $albums[0]["medium"];
    
        // Get the label name
        $labelKey = $albums[0]["pubkey"];
        if(!self::$labelCache[$labelKey]) {
            $label = $libAPI->search(ILibrary::LABEL_PUBKEY, 0, 1, $labelKey);
            self::$labelCache[$labelKey] = sizeof($label) ?
                                       $label[0]["name"] : "(Unknown)";
        }
        $result[$i]["LABEL"] = self::$labelCache[$labelKey];
    }
    
    public function getChart(&$result, $startDate, $endDate, $limit="", $category="") {
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
                         "(lw int primary key auto_increment) ";
    
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
            $result[$i]["PREVWEEK"] = $row[2];
            $result[$i]["PREVRANK"] = $row[3];
    
            $tagCache[$row[0]] = 1;
    
            $this->getChartFillInAlbumInfo($result, $i++, $row[0]);
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
    
                    $tagCache[$row[0]] = 1;
    
                    $this->getChartFillInAlbumInfo($result, $i++, $row[0]);
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
                        if(!$tagCache[$row[0]]) {
                            $result[$i]["tag"] = $row[0];
                            $result[$i]["PLAYS"] = 0;
                            $result[$i]["PREVWEEK"] = $row[1];
                            $result[$i]["PREVRANK"] = 0;
    
                            $tagCache[$row[0]] = 1;
    
                            $this->getChartFillInAlbumInfo($result, $i++, $row[0]);
                        }
                    }
                }
            }
            $stmt = $this->prepare("DROP TABLE tmp_lastweek");
            $stmt->execute();
        }
    }
    
    public function getChart2(&$result, $startDate, $endDate, $limit="", $category="") {
        $query = "SELECT plays.tag, sum(plays) p";
    
        if(!$startDate)
            $query .= ", w.prev, w.lw";
    
        $query .= ", a.artist, a.album, a.medium, a.size,".
                  " a.created, a.updated, a.category, l.name label";
    
        $query .= " FROM plays";
        $query .= " JOIN albumvol a ON plays.tag = a.tag";
        $query .= " JOIN publist l ON a.pubkey = l.pubkey";
    
        if(!$startDate) {
            // We're doing a weekly chart, so go back and get last week's
            // plays as well.
            list($y, $m, $d) = explode("-", $endDate);
            $lastWeek = date("Y-m-d", mktime(0,0,0,$m,$d-7,$y));
    
            $queryLast = "CREATE TEMPORARY TABLE tmp_lastweek " .
                         "(lw int primary key auto_increment) ";
    
            $queryLast .= "SELECT p.tag, sum(plays) prev,";
            $queryLast .= " a.artist, a.album, a.medium, a.size,";
            $queryLast .= " a.created, a.updated, a.category, l.name label";
            $queryLast .= " FROM plays p";
            $queryLast .= " JOIN albumvol a ON p.tag = a.tag";
            $queryLast .= " JOIN publist l ON a.pubkey = l.pubkey";
            $queryLast .= " WHERE week=?";
            if($category)
                $queryLast .= " AND find_in_set(?, p.category)";
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
            $query .= " AND find_in_set(?, plays.category)";
        
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
            $result[$i] = array_merge($row);
            $result[$i]["rank"] = $i+1;
            $result[$i]["LABEL"] = $row["label"];
            $result[$i]["tag"] = $row[0];
            $result[$i]["PLAYS"] = $row[1];
            $result[$i]["PREVWEEK"] = $row[2];
            $result[$i]["PREVRANK"] = $row[3];
    
            $tagCache[$row[0]] = 1;
    
            $i++;
        }
    
        // Cleanup
        if(!$startDate) {
            if(!$limit || $i < $limit) {
                // append zero play albums for this week to fill out chart
                $query = "SELECT w.tag, prev, lw, plays,";
                $query .= " a.artist, a.album, a.medium, a.size,";
                $query .= " a.created, a.updated, a.category, l.name label";
                $query .= " FROM tmp_lastweek w";
                $query .= " JOIN albumvol a ON w.tag = a.tag";
                $query .= " JOIN publist l ON a.pubkey = l.pubkey";
                $query .= " LEFT JOIN plays ON w.tag = plays.tag";
                $query .= " AND plays.week=?";
                $query .= " WHERE plays IS NULL";
                $query .= " ORDER BY prev DESC, w.tag";
                if($limit)
                    $query .= " LIMIT ?" ;
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, $endDate);
                if($limit)
                    $stmt->bindValue(2, (int)($limit - $i), \PDO::PARAM_INT);
    
                $stmt->execute();
                while($row = $stmt->fetch()) {
                    $result[$i] = array_merge($row);
                    $result[$i]["rank"] = $i+1;
                    $result[$i]["LABEL"] = $row["label"];
                    $result[$i]["tag"] = $row[0];
                    $result[$i]["PLAYS"] = 0;
                    $result[$i]["PREVWEEK"] = $row[1];
                    $result[$i]["PREVRANK"] = $row[2];
    
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
    
                    $queryLast = "SELECT p.tag, sum(plays) prev,".
                                 " a.artist, a.album, a.medium, a.size,".
                                 " a.created, a.updated, a.category, l.name label".
                                 " FROM plays p, albumvol a, publist l".
                                 " WHERE week=?".
                                 " AND p.tag = a.tag AND a.pubkey = l.pubkey";
                    if($category)
                        $queryLast .= " AND find_in_set(?, p.category)";
                    $queryLast .= " GROUP BY tag ORDER BY prev";
                    $stmt = $this->prepare($queryLast);
                    $stmt->bindValue(1, $lastWeek);
                    if($category)
                        $stmt->bindValue(2, $category);
                    $stmt->execute();
    
                    while($i < $limit && ($row = $stmt->fetch())) {
                        if(!$tagCache[$row[0]]) {
                            $result[$i] = array_merge($row);
                            $result[$i]["rank"] = $i+1;
                            $result[$i]["LABEL"] = $row["label"];
                            $result[$i]["tag"] = $row[0];
                            $result[$i]["PLAYS"] = 0;
                            $result[$i]["PREVWEEK"] = $row[1];
                            $result[$i]["PREVRANK"] = 0;
    
                            $tagCache[$row[0]] = 1;
    
                            $i++;
                        }
                    }
                }
            }
            $stmt = $this->prepare("DROP TABLE tmp_lastweek");
            $stmt->execute();
        }
    }
    
    public function getBottom(&$result, $startDate, $endDate, $limit="", $category="") {
        $query = "SELECT currents.tag, sum(plays) p FROM currents";
        $query .= " LEFT JOIN plays ON plays.tag = currents.tag";
    
        if($startDate && $endDate)
            $query .= " WHERE (? BETWEEN adddate AND pulldate OR"
                   . " ? BETWEEN adddate and pulldate) AND"
                   . " (plays.tag IS NULL OR week BETWEEN ? AND ?)";
        else if($startDate)
            $query .= " WHERE ? BETWEEN adddate AND pulldate AND"
                   . " (plays.tag IS NULL OR week >= ?)";
        else
            $query .= " WHERE ? BETWEEN adddate AND pulldate AND"
                   . " (plays.tag IS NULL OR week = ?)";
        if($category)
            $query .= " AND find_in_set(?, category)";
    
        $query .= " GROUP BY currents.tag ORDER BY p";
    
        $query .= ", currents.tag";
    
        if($limit)
            $query .= " LIMIT ?";
        $stmt = $this->prepare($query);
        $p = 1;
        if($startDate && $endDate) {
            $stmt->bindValue($p++, $startDate);
            $stmt->bindValue($p++, $endDate);
            $stmt->bindValue($p++, $startDate);
            $stmt->bindValue($p++, $endDate);
        } else if($startDate) {
            $stmt->bindValue($p++, $startDate);
            $stmt->bindValue($p++, $startDate);
        } else {
            $stmt->bindValue($p++, $endDate);
            $stmt->bindValue($p++, $endDate);
        }
        if($category)
            $stmt->bindValue($p++, $category);
        if($limit)
            $stmt->bindValue($p++, (int)$limit, \PDO::PARAM_INT);
            
        $stmt->execute();
    
        // Prepare the results
        $i = 0;
        while($row = $stmt->fetch()) {
            $result[$i]["tag"] = $row[0];
            $result[$i]["PLAYS"] = $row[1];
    
            $this->getChartFillInAlbumInfo($result, $i++, $row[0]);
        }
    }
    
    public function getChartEMail() {
        $query = "SELECT id, chart, address FROM chartemail ORDER BY id";
        $stmt = $this->prepare($query);
        return $this->execute($stmt);
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
        return $this->execute($stmt);
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
        $pdo = Engine::newPDO();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE,
                                        \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES,
                                        false);
        $pdo->beginTransaction();

        try {
            $query = "CREATE TEMPORARY TABLE tmp_maxplays ".
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
        $next = self::getMonthlyChartStart($month+1, $year);
        list($y,$m,$d) = explode("-", $next);
    
        // Offset back 7 days to get last chart of specified month
        return date("Y-m-d", mktime(0,0,0,$m,$d-7,$y));
    }
}
