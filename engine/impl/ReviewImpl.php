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
 * Music review operations
 */
class ReviewImpl extends DBO implements IReview {
    private const REVIEW_SHELF = [
        ILibrary::LOCATION_AWAITING_REVIEW,
        ILibrary::LOCATION_IN_REVIEW,
        ILibrary::LOCATION_REVIEWED
    ];

    /* locations to exclude from recent reviews */
    private const EXCLUDE_LOCATIONS = [
        ILibrary::LOCATION_STORAGE,
        ILibrary::LOCATION_DEACCESSIONED
    ];

    private function getRecentSubquery($user = "", $weeks = 0, $loggedIn = 0) {
        // IMPORTANT: If columns change, revisit getRecentReviews below
        $query = "SELECT a.airname, r.user, DATE_FORMAT(r.created, GET_FORMAT(DATE, 'ISO')) reviewed, r.id as rid, u.realname, r.tag, v.category, v.album, v.artist, v.iscoll FROM reviews r ";

        $query .= "INNER JOIN albumvol v ON r.tag = v.tag ";
        $query .= "INNER JOIN users u ON r.user = u.name ";

        if($weeks < 0)
            $query .= "LEFT JOIN currents c ON c.tag = r.tag AND " .
                      "c.adddate <= NOW() AND c.pulldate > NOW() ";
                      
        $query .= "LEFT JOIN airnames a ON r.airname = a.id ";

        // exclude deep storage and deaccessioned albums
        $query .= "WHERE v.location NOT IN (" .
                       implode(',', array_map(function($location) {
                           return "'$location'";
                       }, self::EXCLUDE_LOCATIONS)) . ") ";
    
        if($user)
            $query .= "AND r.user=? ";
        else if(!$loggedIn)
            $query .= "AND r.private=0 ";

        if($weeks > 0)
            $query .= "AND r.created >= ? ";
        else if($weeks < 0)
            $query .= "AND c.tag IS NOT NULL ";

        // suppress 'micro reviews'
        if(!$user)
            $query .= "AND LENGTH(review) > " . self::MICRO_REVIEW_LENGTH . " ";

        return $query;
    }
    
    public function getRecentReviews($user = "", $weeks = 0, $limit = 0, $loggedIn = 0) {
        if($weeks) {
            // The UNION construct is obtuse but efficient, as it allows
            // us to use multiple indexes on the reviews table, thus
            // avoiding a table scan, which MySQL would do otherwise.
            //
            // See: https://www.techfounder.net/2008/10/15/optimizing-or-union-operations-in-mysql/
            //
            // IMPORTANT:  If columns change, revisit loop below
            $query = "SELECT z.airname, z.user, z.reviewed, z.rid, z.realname, z.tag, z.category, z.album, z.artist, z.iscoll FROM (";
            $query .= $this->getRecentSubquery($user, $weeks, $loggedIn);
            $query .= "UNION ";
            $query .= $this->getRecentSubquery($user, -1, $loggedIn);
            $query .= ") AS z GROUP BY z.tag ORDER BY z.rid DESC";
        } else {
            $query = $this->getRecentSubquery($user, 0, $loggedIn);
            $query .= "ORDER BY r.created DESC";
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

        $reviews = $stmt->executeAndFetchAll();

        // move album columns into 'album' property
        foreach($reviews as &$review) {
            $album = array_slice($review, 5);
            array_splice($review, 5);
            $review['album'] = $album;
        }

        return $reviews;
    }

    public function getActiveReviewers($viewAll = 0, $loggedIn = 0) {
        $query = "SELECT a.id, a.airname FROM reviews r, airnames a ";
        $query .= "WHERE a.id = r.airname AND r.airname IS NOT NULL ";
        if(!$viewAll)
            $query .= "AND ADDDATE(r.created, 12*7) > NOW() ";
        if(!$loggedIn)
            $query .= "AND r.private = 0 ";

        // suppress 'micro reviews'
        $query .= "AND LENGTH(review) > " . self::MICRO_REVIEW_LENGTH . " ";

        $query .= "GROUP BY a.airname UNION ";
        $query .= "SELECT u.name, u.realname FROM reviews r, users u ";
        $query .= "WHERE u.name = r.user AND r.airname IS NULL ";
        if(!$viewAll)
            $query .= "AND ADDDATE(r.created, 12*7) > NOW() ";
        if(!$loggedIn)
            $query .= "AND r.private = 0 ";

        // suppress 'micro reviews'
        $query .= "AND LENGTH(review) > " . self::MICRO_REVIEW_LENGTH . " ";

        $query .= "GROUP BY u.name";

        $stmt = $this->prepare($query);
        return $stmt->iterate(\PDO::FETCH_BOTH);
    }
    
    public function getReviews($tag, $byName=1, $user = "", $loggedIn = 0, $byId = 0) {
        settype($tag, "integer");
        if($byName)
            $query = "SELECT r.id, r.created, r.review, " .
                     "r.private, r.user, a.airname, r.tag, realname, exportid, r.airname as aid " .
                     "FROM reviews r " .
                     "LEFT JOIN users u ON u.name = r.user " .
                     "LEFT JOIN airnames a ON a.id = r.airname ";
        else
            $query = "SELECT r.id, created, review, " .
                     "private, user, airname, tag, realname, exportid " .
                     "FROM reviews r " .
                     "LEFT JOIN users u ON u.name = r.user ";
        $query .= $byId?"WHERE r.id=? ":"WHERE tag=? ";
        if($user)
            $query .= "AND user=? ";
        if(!$loggedIn)
            $query .= "AND private = 0 ";
        $query .= "ORDER BY created DESC, r.id DESC";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $tag);
        if($user)
            $stmt->bindValue(2, $user);
        return $stmt->executeAndFetchAll(\PDO::FETCH_BOTH);
    }

    public function getTrending(int $limit = 50) {
        $query = "SELECT hashtag, count(*) freq FROM reviews_hashtags " .
                 "GROUP BY hashtag ORDER BY id DESC LIMIT ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        return $stmt->executeAndFetchAll();
    }

    protected function syncHashtags(int $tag, string $user, ?string $review = null) {
        $this->adviseLock($tag);

        $query = "DELETE FROM reviews_hashtags WHERE tag = ? AND user = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $tag);
        $stmt->bindValue(2, $user);
        $stmt->execute();

        if($review && preg_match_all('/#(\pL\w*)/u', $review, $matches)) {
            $query = "INSERT INTO reviews_hashtags (tag, user, hashtag) VALUES (?, ?, ?)";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $tag);
            $stmt->bindValue(2, $user);
            $normalized = array_unique(array_map('strtolower', $matches[1]));
            $hashtags = array_intersect_key($matches[1], $normalized);
            foreach($hashtags as $hashtag) {
                $stmt->bindValue(3, $hashtag);
                $stmt->execute();
            }
        }

        $this->adviseUnlock($tag);
    }
    
    public function insertReview($tag, $private, $airname, $review, $user) {
        // we must do these first as caller depends on lastInsertId from INSERT
        $this->syncHashtags($tag, $user, $private ? null : $review);
        $prev = $this->updateReviewShelf($tag, null, ILibrary::LOCATION_REVIEWED);

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
        $count = $stmt->execute() ? $stmt->rowCount() : 0;

        // back out hashtags and review shelf on failure
        if(!$count) {
            $this->syncHashtags($tag, $user);

            if($prev)
                $this->updateReviewShelf($tag, $prev['user'], $prev['status']);
        }

        return $count;
    }
    
    public function updateReview($tag, $private, $airname, $review, $user) {
        $query = "UPDATE reviews SET private=?, " .
                 ($airname?"airname=?, ":
                           "airname=NULL, ") .
                 "review=? " .
                 "WHERE tag=? AND user=?";
        $stmt = $this->prepare($query);
        $p = 1;
        $stmt->bindValue($p++, $private);
        if($airname)
            $stmt->bindValue($p++, $airname);
        $stmt->bindValue($p++, $review);
        $stmt->bindValue($p++, $tag);
        $stmt->bindValue($p++, $user);
        $count = $stmt->execute() ? $stmt->rowCount() : 0;

        if($count)
            $this->syncHashtags($tag, $user, $private ? null : $review);

        return $count;
    }
    
    public function deleteReview($tag, $user) {
        $query = "DELETE FROM reviews " .
                 "WHERE tag=? AND user=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $tag);
        $stmt->bindValue(2, $user);
        $count = $stmt->execute() ? $stmt->rowCount() : 0;

        // delete any associated hashtags
        if($count) {
            $this->syncHashtags($tag, $user);
            $this->updateReviewShelf($tag, null, ILibrary::LOCATION_AWAITING_REVIEW);
        }

        return $count;
    }

    public function setExportId($tag, $user, $exportId) {
        $query = "UPDATE reviews SET exportid=? " .
                 "WHERE tag=? AND user=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $exportId);
        $stmt->bindValue(2, $tag);
        $stmt->bindValue(3, $user);
        return $stmt->execute()?$stmt->rowCount():0;
    }

    public function getReviewShelf() {
        $n = count(self::REVIEW_SHELF);
        $query = "SELECT a.*, u.realname FROM albumvol a " .
                 "LEFT JOIN users u ON a.bin = u.name " .
                 "WHERE location IN ( ?" . str_repeat(', ?', $n - 1) . " ) " .
                 "ORDER BY album, artist";
        $stmt = $this->prepare($query);
        foreach(self::REVIEW_SHELF as $status)
            $stmt->bindValue($n--, $status);

        return $stmt->executeAndFetchAll();
    }

    function updateReviewShelf(int $tag, ?string $user = null, ?string $status = null): ?array {
        $query = "SELECT location status, bin user FROM albumvol WHERE tag = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $tag);
        $result = $stmt->executeAndFetch() ?: null;

        // if album is not in a review status, nothing to do
        $ostatus = $result['status'] ?? '';
        if(!in_array($ostatus, self::REVIEW_SHELF))
            return null;

        $location = $ostatus == ILibrary::LOCATION_REVIEWED ?
                      ILibrary::LOCATION_LIBRARY : ILibrary::LOCATION_AWAITING_REVIEW;

        $query = "UPDATE albumvol SET location = ?, bin = ?, updated = NOW() WHERE tag = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $status ??
                ($user ? ILibrary::LOCATION_IN_REVIEW : $location));
        $stmt->bindValue(2, $user);
        $stmt->bindValue(3, $tag);
        return $stmt->execute() ? $result : null;
    }
}
