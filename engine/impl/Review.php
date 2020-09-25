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
 * Music review operations
 */
class ReviewImpl extends BaseImpl implements IReview {
    private function getRecentSubquery($user = "", $weeks = 0, $loggedIn = 0) {
        $query = "SELECT r.tag, a.airname, r.user, DATE_FORMAT(r.created, GET_FORMAT(DATE, 'ISO')) reviewed FROM reviews r ";
        
        if($weeks < 0)
            $query .= "LEFT JOIN currents c ON c.tag = r.tag AND " .
                      "c.adddate <= NOW() AND c.pulldate > NOW() ";
                      
        $query .= "LEFT JOIN airnames a ON r.airname = a.id ";
    
        $op = "AND";
    
        if($user)
            $query .= "WHERE r.user=? ";
        else if(!$loggedIn)
            $query .= "WHERE r.private=0 ";
        else
            $op = "WHERE";

        if($weeks > 0)
            $query .= "$op r.created >= ? ";
        else if($weeks < 0)
            $query .= "$op c.tag IS NOT NULL ";

        return $query;
    }
    
    public function getRecentReviews($user = "", $weeks = 0, $limit = 0, $loggedIn = 0) {
        if($weeks) {
            // The UNION construct is obtuse but efficient, as it allows
            // us to use multiple indexes on the reviews table, thus
            // avoiding a table scan, which MySQL would do otherwise.
            //
            // See: https://www.techfounder.net/2008/10/15/optimizing-or-union-operations-in-mysql/
            $query = "SELECT z.tag, z.airname, z.user, z.reviewed FROM (";
            $query .= $this->getRecentSubquery($user, $weeks, $loggedIn);
            $query .= "UNION ";
            $query .= $this->getRecentSubquery($user, -1, $loggedIn);
            $query .= ") AS z GROUP BY z.tag ORDER BY z.reviewed DESC";
        } else {
            $query = $this->getRecentSubquery($user, 0, $loggedIn);
            $query .= "GROUP BY r.tag ORDER BY reviewed DESC";
        }
            
        if($limit && $limit > 0)
            $query .= " LIMIT ?";

        $stmt = $this->prepare($query);
        
        $p = 1;
        if($user)
            $stmt->bindValue($p++, $user);
        if($weeks) {
            $t = getdate(time());
            $start = date("Y-m-d", mktime(0,0,0,
                                       $t["mon"],
                                       $t["mday"]- $weeks*7,
                                       $t["year"]));
            $stmt->bindValue($p++, $start);
            if($user)
                $stmt->bindValue($p++, $user);
        }
        if($limit)
            $stmt->bindValue($p++, (int)$limit, \PDO::PARAM_INT);
        return $stmt->iterate(\PDO::FETCH_BOTH);
    }
    
    public function getReviews($tag, $byName=1, $user = "", $loggedIn = 0) {
        settype($tag, "integer");
        if($byName)
            $query = "SELECT r.id, r.created, r.review, " .
                     "r.private, r.user, a.airname " .
                     "FROM reviews r " .
                     "LEFT JOIN airnames a ON a.id = r.airname ";
        else
            $query = "SELECT id, created, review, " .
                     "private, user, airname FROM reviews ";
        $query .= "WHERE tag=? ";
        if($user)
            $query .= "AND user=? ";
        if(!$loggedIn)
            $query .= "AND private = 0 ";
        $query .= "ORDER BY created DESC";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $tag);
        if($user)
            $stmt->bindValue(2, $user);
        return $stmt->executeAndFetchAll(\PDO::FETCH_BOTH);
    }
    
    public function insertReview($tag, $private, $airname, $review, $user) {
        $query = "INSERT INTO reviews " .
                 "(tag, user, created, private, review, airname) VALUES (" .
                 "?, ?, " .
                 "now(), " .
                 "?, ?, " .
                 ($airname?"?)":"NULL)");
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $tag);
        $stmt->bindValue(2, $user);
        $stmt->bindValue(3, $private);
        $stmt->bindValue(4, $review);
        if($airname)
            $stmt->bindValue(5, $airname);
        return $stmt->execute()?$stmt->rowCount():0;
    }
    
    public function updateReview($tag, $private, $airname, $review, $user) {
        $query = "UPDATE reviews SET private=?, " .
                 ($airname?"airname=?, ":
                           "airname=NULL, ") .
                 "review=? " .
                 "WHERE tag=? and user=?";
        $stmt = $this->prepare($query);
        $p = 1;
        $stmt->bindValue($p++, $private);
        if($airname)
            $stmt->bindValue($p++, $airname);
        $stmt->bindValue($p++, $review);
        $stmt->bindValue($p++, $tag);
        $stmt->bindValue($p++, $user);
        return $stmt->execute()?$stmt->rowCount():0;
    }
    
    public function deleteReview($tag, $user) {
        $query = "DELETE FROM reviews " .
                 "WHERE tag=? and user=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $tag);
        $stmt->bindValue(2, $user);
        return $stmt->execute()?$stmt->rowCount():0;
    }
    
    public function getRecentReviewsByAirname(&$result, $airname, $count, $loggedIn = 0) {
        $query = "SELECT r.tag, a.airname, r.user, r.created FROM reviews r, airnames a ";
        $query .= "WHERE r.airname=a.id AND a.id=? ";
        if(!$loggedIn)
            $query .= "AND r.private=0 ";
        $query .= "GROUP BY r.tag ORDER BY r.created DESC LIMIT ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $airname);
        $stmt->bindValue(2, (int)$count, \PDO::PARAM_INT);
            $stmt->execute();
        $i = 0;
        $libapi = Engine::api(ILibrary::class);
        while($row = $stmt->fetch()) {
            $album = $libapi->search(ILibrary::ALBUM_KEY, 0, 1, $row[0]);
            $result[$i]["tag"] = $row[0];
    
            // Setup artist correctly for collections
            $albums = $libapi->search(ILibrary::ALBUM_KEY, 0, 1, $row[0]);
            if (preg_match("/^\[coll\]/i", $albums[0]["artist"]))
                $result[$i]["artist"] = "Various Artists";
            else
                $result[$i]["artist"] = $albums[0]["artist"];
    
            $result[$i]["album"] = $album[0]["album"];
    
            // Get the label name
            $labelKey = $albums[0]["pubkey"];
            if(!$labelCache[$labelKey]) {
                $label = $libapi->search(ILibrary::LABEL_PUBKEY, 0, 1, $labelKey);
                $labelCache[$labelKey] = sizeof($label) ?
                                           $label[0]["name"] : "(Unknown)";
            }
            $result[$i++]["label"] = $labelCache[$labelKey];
        } 
    }
}
