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
 * Library Editor operations
 */
class EditorImpl extends DBO implements IEditor {
    private function getNextTag() {
        $query = "SELECT MAX(tag) FROM albumvol";
        $stmt = $this->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch();
        $next = floor($row[0] / 10) + 1;
        for($sum = 0, $temp = $next;$temp;$temp = floor($temp/10))
           $sum += $temp % 10;
        return (int)($next * 10 + $sum % 10);
    }
    
    private function getNextPubkey() {
        $query = "SELECT MAX(pubkey) FROM publist";
        $stmt = $this->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch();
        $next = $row[0] + 1;
        return $next;
    }
    
    public function insertUpdateAlbum(&$album, $tracks, $label) {
        if($album["location"] != "G")
           $album["bin"] = "";
    
        $newLabel = $label && !$label["pubkey"] &&
                                   array_key_exists("name", $label);
    
        // Label
        do {
            if($newLabel) {
                $label["pubkey"] = $this->getNextPubkey();
                $query = "INSERT INTO publist " .
                        "(pubkey, name, attention, address, " .
                        "city, state, zip, international, phone, " .
                        "fax, email, url, mailcount, maillist, " .
                        "pcreated, modified) VALUES " .
                        "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, " .
                        "now(), now())";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, $label["pubkey"]);
                $stmt->bindValue(2, trim($label["name"]));
                $stmt->bindValue(3, $label["attention"]);
                $stmt->bindValue(4, $label["address"]);
                $stmt->bindValue(5, $label["city"]);
                $stmt->bindValue(6, $label["state"]);
                $stmt->bindValue(7, $label["zip"]);
                $stmt->bindValue(8, $label["foreign"]?"T":"F");
                $stmt->bindValue(9, $label["phone"]);
                $stmt->bindValue(10, $label["fax"]);
                $stmt->bindValue(11, $label["email"]);
                $stmt->bindValue(12, $label["url"]);
                $stmt->bindValue(13, (int)$label["mailcount"]);
                $stmt->bindValue(14, $label["maillist"]);
            } else if($label && array_key_exists("name", $label)) {
                $query = "UPDATE publist SET name=?, attention=?, " .
                         "address=?, city=?, state=?, zip=?, international=?, " .
                         "phone=?, fax=?, email=?, url=?, mailcount=?, " .
                         "maillist=?, modified=now() WHERE pubkey=?";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, trim($label["name"]));
                $stmt->bindValue(2, $label["attention"]);
                $stmt->bindValue(3, $label["address"]);
                $stmt->bindValue(4, $label["city"]);
                $stmt->bindValue(5, $label["state"]);
                $stmt->bindValue(6, $label["zip"]);
                $stmt->bindValue(7, $label["foreign"]?"T":"F");
                $stmt->bindValue(8, $label["phone"]);
                $stmt->bindValue(9, $label["fax"]);
                $stmt->bindValue(10, $label["email"]);
                $stmt->bindValue(11, $label["url"]);
                $stmt->bindValue(12, (int)$label["mailcount"]);
                $stmt->bindValue(13, $label["maillist"]);
                $stmt->bindValue(14, $label["pubkey"]);
            } else if(!$album["tag"]) {
                $query = "UPDATE publist SET modified=now() WHERE pubkey=?";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, $album["pubkey"]);
            }
    
            //echo "DEBUG: query=$query<BR>";
            if(isset($query)) {
                $stmt->execute();
                if(!$album["pubkey"])
                    $album["pubkey"] = $label["pubkey"];
            }
        } while ($newLabel && $stmt->rowCount() == 0 &&
                           $label["pubkey"] != $this->getNextPubkey());
       
        // Album
        $title = trim($album["album"]);
        $artist = trim($album["artist"]);
        $iscoll = "0";
        if(!$album["location"])
            $album["location"] = "L";
        if(array_key_exists("coll", $album) && $album["coll"]) {
            $artist = "[coll]: $title";
            $iscoll = "1";
        }
    
        $newAlbum = !$album["tag"];
    
        do {
            if($newAlbum) {
                $album["tag"] = $this->getNextTag();
                $query = "INSERT INTO albumvol (tag, artist, " .
                        "album, category, medium, size, location, bin, " .
                        "iscoll, pubkey, created, updated) VALUES (?, ?, " .
                        "?, ?, ?, ?, ?, ?, ?, ?, now(), now())";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, $album["tag"]);
                $stmt->bindValue(2, $artist);
                $stmt->bindValue(3, $title);
                $stmt->bindValue(4, $album["category"]);
                $stmt->bindValue(5, $album["medium"]);
                $stmt->bindValue(6, $album["format"]);
                $stmt->bindValue(7, $album["location"]);
                $stmt->bindValue(8, $album["bin"]);
                $stmt->bindValue(9, $iscoll);
                $stmt->bindValue(10, $album["pubkey"]);
            } else {
                $hasPubKey = array_key_exists("pubkey", $album);
                $query = "UPDATE albumvol SET artist=?, " .
                         "album=?, category=?, medium=?, " .
                         "size=?, location=?, bin=?, iscoll=?, " .
                         ($hasPubKey?"pubkey=?, ":"") .
                         "updated=now() WHERE tag=?";
                $stmt = $this->prepare($query);
                $i=1;
                $stmt->bindValue($i++, $artist);
                $stmt->bindValue($i++, $title);
                $stmt->bindValue($i++, $album["category"]);
                $stmt->bindValue($i++, $album["medium"]);
                $stmt->bindValue($i++, $album["format"]);
                $stmt->bindValue($i++, $album["location"]);
                $stmt->bindValue($i++, $album["bin"]);
                $stmt->bindValue($i++, $iscoll);
                if($hasPubKey)
                    $stmt->bindValue($i++, $album["pubkey"]);
                $stmt->bindValue($i++, $album["tag"]);
            }
            //echo "DEBUG: query=$query, pubkey=".$album["pubkey"]."<BR>\n";
            $stmt->execute();
        } while ($newAlbum && $stmt->rowCount() == 0 &&
                                     $album["tag"] != $this->getNextTag());
    
        // Tracks
        if($tracks || $newAlbum) {
            // We delete from both tracknames and colltracknames
            // because someone could have toggled the 'compilation'
            // checkbox; this ensures no stale track names remain
            // in the other table.
            $query = "DELETE FROM colltracknames WHERE tag=?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $album["tag"]);
            $stmt->execute();
            $query = "DELETE FROM tracknames WHERE tag=?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $album["tag"]);
            $stmt->execute();

            for($i=1; $tracks && array_key_exists($i, $tracks); $i++) {
                $trackRow = $tracks[$i];
                $trackName = trim($trackRow['track']);
                $trackUrl = trim($trackRow['url']);
                $query = "INSERT INTO tracknames (tag, seq, track, url) VALUES (?, ?, ?, ?)";
                if ($iscoll)
                    $query = "INSERT INTO colltracknames (tag, seq, track, url, artist) VALUES (?, ?, ?, ?, ?)";

                $stmt = $this->prepare($query);
                $stmt->bindValue(1, $album["tag"]);
                $stmt->bindValue(2, $i);
                $stmt->bindValue(3, $trackName);
                $stmt->bindValue(4, $trackUrl);
                if ($iscoll)
                    $stmt->bindValue(5, trim($trackRow["artist"]));

                $stmt->execute();
            }
        }
    
        return true;
    }
    
    public function insertUpdateLabel(&$label) {
        $newLabel = $label && !$label["pubkey"];
    
        // Label
        do {
            if($newLabel) {
                $label["pubkey"] = $this->getNextPubkey();
                $query = "INSERT INTO publist " .
                        "(pubkey, name, attention, address, " .
                        "city, state, zip, international, phone, " .
                        "fax, email, url, mailcount, maillist, " .
                        "pcreated, modified) VALUES " .
                        "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, " .
                        "now(), now())";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, $label["pubkey"]);
                $stmt->bindValue(2, trim($label["name"]));
                $stmt->bindValue(3, $label["attention"]);
                $stmt->bindValue(4, $label["address"]);
                $stmt->bindValue(5, $label["city"]);
                $stmt->bindValue(6, $label["state"]);
                $stmt->bindValue(7, $label["zip"]);
                $stmt->bindValue(8, $label["foreign"]?"T":"F");
                $stmt->bindValue(9, $label["phone"]);
                $stmt->bindValue(10, $label["fax"]);
                $stmt->bindValue(11, $label["email"]);
                $stmt->bindValue(12, $label["url"]);
                $stmt->bindValue(13, (int)$label["mailcount"]);
                $stmt->bindValue(14, $label["maillist"]);
            } else if($label) {
                $query = "UPDATE publist SET name=?, attention=?, " .
                         "address=?, city=?, state=?, zip=?, international=?, " .
                         "phone=?, fax=?, email=?, url=?, mailcount=?, " .
                         "maillist=?, modified=now() WHERE pubkey=?";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, trim($label["name"]));
                $stmt->bindValue(2, $label["attention"]);
                $stmt->bindValue(3, $label["address"]);
                $stmt->bindValue(4, $label["city"]);
                $stmt->bindValue(5, $label["state"]);
                $stmt->bindValue(6, $label["zip"]);
                $stmt->bindValue(7, $label["foreign"]?"T":"F");
                $stmt->bindValue(8, $label["phone"]);
                $stmt->bindValue(9, $label["fax"]);
                $stmt->bindValue(10, $label["email"]);
                $stmt->bindValue(11, $label["url"]);
                $stmt->bindValue(12, (int)$label["mailcount"]);
                $stmt->bindValue(13, $label["maillist"]);
                $stmt->bindValue(14, $label["pubkey"]);
            }
    
            //echo "DEBUG: query=$query<BR>";
            if(isset($query))
                $stmt->execute();
        } while ($newLabel && $stmt->rowCount() == 0 &&
                           $label["pubkey"] != $this->getNextPubkey());
    
        return true;
    }

    public function enqueueTag($tag, $user) {
        $query = "INSERT INTO tagqueue (user, tag, keyed) values (?, ?, now())";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $stmt->bindValue(2, $tag);
        return $stmt->execute();
    }
    
    public function dequeueTag($tag, $user) {
        $query = "DELETE FROM tagqueue WHERE user=? and tag=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $stmt->bindValue(2, $tag);
        return $stmt->execute();
    }
    
    public function getQueuedTags($user) {
        $query = "SELECT * FROM tagqueue t " .
                 "LEFT JOIN albumvol a ON t.tag = a.tag " .
                 "WHERE user=? ORDER BY keyed";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        return $stmt->iterate();
    }

    public function getAlbum($tag) {
        $query = "SELECT * FROM albumvol a LEFT JOIN publist p ON a.pubkey = p.pubkey WHERE tag = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $tag);
        return $stmt->executeAndFetch();
    }

    public function getTracks($tag, $isColl) {
        $table = $isColl?"colltracknames":"tracknames";
        $query = "SELECT * FROM $table WHERE tag = ? ORDER BY seq";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $tag);
        return $stmt->iterate();
    }

    public function getLabel($key) {
        $query = "SELECT * FROM publist WHERE pubkey = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $key);
        return $stmt->executeAndFetch();
    }

    public function setLocation($tag, $location, $bin=null) {
        $query = "UPDATE albumvol SET location = ?, " .
                 "updated = now(), bin = ? WHERE tag = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $location);
        $stmt->bindValue(2, $bin);
        $stmt->bindValue(3, $tag);
        return $stmt->execute();
    }
}
