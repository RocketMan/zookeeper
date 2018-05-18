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
 * DJ operations
 */
class DJImpl extends BaseImpl implements IDJ {
    function getAirnames($user=0, $id=0) {
        $query = "SELECT a.id, airname, url, email, name, realname FROM airnames a LEFT JOIN users u ON a.dj = u.name ";
        if($id)
            $query .= "WHERE a.id = ?";
        else if($user)
            $query .= "WHERE dj = ? ORDER BY airname";
        $stmt = $this->prepare($query);
        if($id)
            $stmt->bindValue(1, (int)$id, \PDO::PARAM_INT);
        else if($user)
            $stmt->bindValue(1, $user);
        return $this->execute($stmt, \PDO::FETCH_BOTH);
    }
    
    function getActiveAirnames($viewAll=0) {
        $query = "SELECT a.id, a.airname FROM lists l, airnames a " .
                 "WHERE a.id = l.airname AND l.airname IS NOT NULL ";
         if(!$viewAll)
            $query .= "AND ADDDATE(l.showdate, 12*7) > NOW() ";
        $query .=  "GROUP BY a.airname ORDER BY a.airname";
        $stmt = $this->prepare($query);
        return $this->execute($stmt, \PDO::FETCH_BOTH);
    }
    
    function updateAirname($url, $email, $id=0, $user="") {
        $query = "UPDATE airnames " .
                 "SET url=?, email=?";
        if($id)
            $query .= " WHERE id=?";
        else
            $query .= " WHERE dj=?";
            $stmt = $this->prepare($query);
        $stmt->bindValue(1, $url);
        $stmt->bindValue(2, $email);
        $stmt->bindValue(3, $id?$id:$dj);
        return $stmt->execute()?$stmt->rowCount():0;
    }
    
    function insertAirname($djname, $user) {
        $query = "INSERT INTO airnames " .
                 "(dj, airname) VALUES (?, ?)";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $stmt->bindValue(2, $djname);
        return $stmt->execute()?$stmt->rowCount():0;
    }

    function reassignAirname($id, $user) {
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
