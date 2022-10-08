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
 * DJ operations
 */
class DJImpl extends DBO implements IDJ {
    /*
     * remove airnames which are not linked to a playlist or a music review
     */
    private function purgeUnusedAirnames($user) {
        // derived table is necessary so SELECT can reference DELETE table
        $query = "DELETE FROM airnames WHERE id IN (".
                 "SELECT id FROM (".
                 "SELECT a.id FROM airnames a ".
                 "LEFT JOIN lists l ON a.id = l.airname ".
                 "LEFT JOIN reviews r ON a.id = r.airname ".
                 "WHERE a.dj=? AND l.id IS NULL AND r.id IS NULL) ".
                 "x)";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $stmt->execute();

        // remove reference to purged airnames in deleted playlists
        $query = "UPDATE lists_del l " .
                 "LEFT JOIN airnames a ON a.id = l.airname " .
                 "SET l.airname = NULL " .
                 "WHERE a.airname IS NULL";
        $stmt = $this->prepare($query);
        $stmt->execute();
    }

    public function getAirnames($user=0, $id=0, $noPrune=0) {
        if($user && !$id && !$noPrune)
            $this->purgeUnusedAirnames($user);

        $query = "SELECT a.id, airname, url, email, name, realname FROM airnames a LEFT JOIN users u ON a.dj = u.name ";
        if($id)
            $query .= "WHERE a.id = ?";
        else if($user)
            $query .= "WHERE dj = ? ORDER BY airname";
        else
            $query .= "ORDER BY airname";
        $stmt = $this->prepare($query);
        if($id)
            $stmt->bindValue(1, (int)$id, \PDO::PARAM_INT);
        else if($user)
            $stmt->bindValue(1, $user);
        return $stmt->iterate(\PDO::FETCH_BOTH);
    }
    
    public function getActiveAirnames($viewAll=0) {
        $query = "SELECT a.id, a.airname FROM lists l, airnames a " .
                 "WHERE a.id = l.airname AND l.airname IS NOT NULL ";
         if(!$viewAll)
            $query .= "AND ADDDATE(l.showdate, 12*7) > NOW() ";
        $query .=  "GROUP BY a.airname ORDER BY a.airname";
        $stmt = $this->prepare($query);
        return $stmt->iterate(\PDO::FETCH_BOTH);
    }
    
    public function getAirname($djname, $user="") {
        $query = "SELECT id FROM airnames WHERE airname=?";
        if($user)
            $query .= " AND dj=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $djname);
        if($user)
            $stmt->bindValue(2, $user);
        $result = $stmt->executeAndFetch();
        return $result?$result['id']:0;
    }
    
    public function updateAirname($djname, $user, $url, $email, $id=0) {
        $query = "UPDATE airnames " .
                 "SET url=?, email=?";
        if($id)
            $query .= ", airname=? WHERE id=? AND dj=?";
        else
            $query .= " WHERE dj=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $url);
        $stmt->bindValue(2, $email);
        if($id) {
            $stmt->bindValue(3, $djname);
            $stmt->bindValue(4, $id);
            $stmt->bindValue(5, $user);
        } else
            $stmt->bindValue(3, $user);
        return $stmt->execute();
    }
    
    public function insertAirname($djname, $user) {
        $query = "INSERT INTO airnames " .
                 "(dj, airname) VALUES (?, ?)";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $stmt->bindValue(2, $djname);
        return $stmt->execute()?$stmt->rowCount():0;
    }

    public function reassignAirname($id, $user) {
        $query = "UPDATE airnames SET dj=? WHERE id=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $stmt->bindValue(2, (int)$id, \PDO::PARAM_INT);
        $success = $stmt->execute()?$stmt->rowCount():0;
        if($success > 0) {
            // Reassign the playlists
            $query = "UPDATE lists SET dj=? WHERE airname=?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $user);
            $stmt->bindValue(2, (int)$id, \PDO::PARAM_INT);
            $stmt->execute();

            // Reassign the reviews
            $query = "UPDATE reviews SET user=? WHERE airname=?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $user);
            $stmt->bindValue(2, (int)$id, \PDO::PARAM_INT);
            $stmt->execute();
        }
        return $success;
    }
}
