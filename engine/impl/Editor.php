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

    private const NONALNUM="/([\.,!\?&~ \-\+=\{\[\(\|\}\]\)])/";
    private const STOPWORDS="/^(a|an|and|at|but|by|for|in|nor|of|on|or|out|so|the|to|up|yet)$/i";

    /**
     * This is the PHP equivalent of the editor.common.js zkAlpha function
     */
    private static function zkAlpha($val, $isTrack=false) {
        $words = preg_split(self::NONALNUM, $val);
        $newVal = join(" ", array_map(function(int $index, string $word) use($words) {
            // words starting with caps are kept as-is
            if(preg_match('/^[A-Z]+/', $word))
                return $word;

            // stopwords are not capitalized, unless first or last
            if(preg_match(self::STOPWORDS, $word) &&
                        $index != 0 &&
                        $index != sizeof($words) - 1) {
                return strtolower($word);
            }

            // otherwise, capitalize the word
            return strtoupper(substr($word, 0, 1)) .
                    strtolower(substr($word, 1));
        }, array_keys($words), array_values($words)));

        if(!$isTrack && substr($newVal, 0, 4) == 'The ')
            $newVal = substr($newVal, 4) . ', The';

        return $newVal;
    }

    public function importAlbum($json, $update = false) {
        // map field values to codes
        $maps = [["category", array_flip(ILibrary::GENRES)],
                 ["medium", array_flip(ILibrary::MEDIA)],
                 ["format", array_flip(ILibrary::LENGTHS)],
                 ["location", array_flip(ILibrary::LOCATIONS)]];

        $json->iterateData(function($data) use($json, $update, $maps) {
            $message = "";
            if(empty($data['artist']) || empty($data['album']))
                $message .= "artist or album missing, ";

            // convert field values to codes
            foreach($maps as $field)
                if(key_exists($data[$field[0]], $field[1]))
                    $data[$field[0]] = $field[1][$data[$field[0]]];
                else
                    $message .= $field[0]. " '{$data[$field[0]]}', ";

            if($message) {
                $json->addError($data['lid'], "Bad field(s): " . substr($message, 0, -2));
                return;
            }

            // allow inline or related label
            if(isset($data['label']['name']) ||
                    isset($data['label']['pubkey'])) {
                // inline
                $label = $data['label'];
            } else if(is_array($data['relationships']) &&
                    is_array($data['relationships']['label']) &&
                    is_array($data['relationships']['label']['data'])) {
                // related
                $rel = $data['relationships']['label']['data'];
                $label = $json->getIncluded($rel['type'], $rel['id']);
                if(!$label) {
                    $json->addError($data['lid'], "missing included label");
                    return;
                }
            } else {
                $json->addError($data['lid'], "missing label");
                return;
            }

            // try to find label by name if no pubkey specified
            if(empty($label['pubkey'])) {
                $rec = Engine::api(ILibrary::class)->search(ILibrary::LABEL_NAME, 0, 1, $label['name']);
                if(sizeof($label))
                    $label['pubkey'] = $rec['pubkey'];
            }
            $data['pubkey'] = $label['pubkey'];

            // reindex tracks
            $tracks = array_combine(range(1, sizeof($data['data'])),
                        array_values($data['data']));

            // normalize artist, album, and track names
            foreach(["artist", "album"] as $field)
                $data[$field] = self::zkAlpha($data[$field]);

            foreach($tracks as &$track) {
                $track["track"] = self::zkAlpha($track["track"], true);
                if(isset($track["artist"]))
                    $track["artist"] = self::zkAlpha($track["artist"]);
            }

            // normalize label fields
            foreach(["name","attention","address","city","maillist"] as $field) {
                if(isset($label[$field]))
                    $label[$field] = self::zkAlpha($label[$field], true);
            }

            if(!$update)
                unset($data["tag"]);
            else if(empty($data["tag"]))
                $data["tag"] = $data["id"];

            if($this->insertUpdateAlbum($data, $tracks, $label))
                $json->addSuccess($data["tag"], ["lid" => $data["lid"]]);
            else
                $json->addError($data['lid'], "insert failed");
        });
    }

    public function deleteAlbum($tag) {
        // use a fresh connection so we don't adversely affect
        // anything else by changing the attributes, etc.
        $pdo = $this->newPDO();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->beginTransaction();

        try {
            $query = "SELECT count(*) c FROM albumvol WHERE tag = ?";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(1, $tag);
            $result = $stmt->executeAndFetch();
            if($result['c'] == 0)
                throw new \Exception("album does not exist");

            $query = "SELECT count(*) c FROM reviews WHERE tag = ?";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(1, $tag);
            $result = $stmt->executeAndFetch();
            if($result['c'])
                throw new \Exception("album has reviews");

            $query = "SELECT count(*) c FROM currents WHERE tag = ?";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(1, $tag);
            $result = $stmt->executeAndFetch();
            if($result['c'])
                throw new \Exception("album has been in the a-file");

            $query = "SELECT count(*) c FROM plays WHERE tag = ?";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(1, $tag);
            $result = $stmt->executeAndFetch();
            if($result['c'])
                throw new \Exception("album has charted");

            $query = "DELETE FROM tagqueue WHERE tag = ?";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(1, $tag);
            $stmt->execute();

            $query = "DELETE FROM tracknames WHERE tag = ?";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(1, $tag);
            $stmt->execute();

            $query = "DELETE FROM colltracknames WHERE tag = ?";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(1, $tag);
            $stmt->execute();

            $query = "DELETE FROM albumvol WHERE tag = ?";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(1, $tag);
            $stmt->execute();

            // any spins which reference this album will already have
            // a private copy of the artist/album/label name; all that
            // remains is to remove the link to the album
            $query = "UPDATE tracks SET tag = NULL WHERE tag = ?";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(1, $tag);
            $stmt->execute();

            // if we made it this far, success!
            $pdo->commit();
        } catch(\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
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

    public function getNumQueuedTags($user) {
        $count = 0;
        $query = "SELECT count(*) FROM tagqueue WHERE user=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        if($stmt->execute()) {
            $row = $stmt->fetch();
            $count = $row[0];
        }
        return $count;
    }

    public function getQueuedTags($user) {
        $query = "SELECT * FROM tagqueue t " .
                 "LEFT JOIN albumvol a ON t.tag = a.tag " .
                 "WHERE user=? ORDER BY keyed";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        return $stmt->iterate();
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
