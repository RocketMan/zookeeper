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
 * User operations
 */
class UserImpl extends DBO implements IUser {
    public function getUser($user) {
        $query = "SELECT * FROM users WHERE name = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        return $stmt->executeAndFetch();
    }

    public function getUsers() {
        $query = "SELECT name, realname FROM users u ORDER BY name";
        $stmt = $this->prepare($query);
        return $stmt->iterate();
    }
    
    public function getUserByAccount($account) {
        $query = "SELECT * FROM users WHERE ssoaccount = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $account);
        return $stmt->executeAndFetch();
    }
    
    public function getUserByFullname($fullname) {
        $query = "SELECT * FROM users WHERE realname = ? AND ssoaccount IS NULL";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $fullname);
        return $stmt->executeAndFetch();
    }
    
    public function assignAccount($user, $account) {
        $query = "UPDATE users SET ssoaccount=? WHERE name=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $account);
        $stmt->bindValue(2, $user);
        $stmt->execute();
    }
    
    public function updateLastLogin($id) {
        $query = "UPDATE users SET lastlogin=now() WHERE id=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $id);
        $stmt->execute();
    }
    
    public function setupSsoOptions($account, $fullname, $url) {
        $session = md5(uniqid(rand()));
        $query = "INSERT INTO ssosetup " .
                     "(sessionkey, fullname, account, created, url) " .
                     "VALUES (?, ?, ?, now(), ";
        $query .= ($url?"?":"NULL") . ")";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $session);
        $stmt->bindValue(2, $fullname);
        $stmt->bindValue(3, $account);
        if($url)
            $stmt->bindValue(4, $url);
        $stmt->execute();
        return $session;
    }
    
    public function getSsoOptions($ssoSession) {
        $query = "SELECT * FROM ssosetup WHERE sessionkey=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $ssoSession);
        return $stmt->executeAndFetch();
    }
    
    public function teardownSsoOptions($ssoSession) {
        $query = "DELETE FROM ssosetup WHERE sessionkey=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $ssoSession);
        $stmt->execute();
    }
    
    public function setupSsoRedirect($url) {
        $session = md5(uniqid(rand()));
        $query = "INSERT INTO ssoredirect " .
                     "(sessionkey, url, created) " .
                     "VALUES (?, ?, now())";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $session);
        $stmt->bindValue(2, $url);
        $stmt->execute();
        return $session;
    }
    
    public function getSsoRedirect($ssoSession) {
        // invalidate SSO session with invalid characters (injection control)
        if(strlen($ssoSession) != strspn($ssoSession, "0123456789abcdef"))
            $ssoSession = "";
    
        $url = false;
        $query = "SELECT * FROM ssoredirect WHERE sessionkey=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $ssoSession);
        $row = $stmt->executeAndFetch();
        if($row)
            $url = $row["url"];
        if($url !== false) {
            $query = "DELETE FROM ssoredirect WHERE sessionkey=?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $ssoSession);
            $stmt->execute();
        }
        return $url;
    }
    
    public function createNewAccount($fullname, $account) {
        $user = $account;
        if(strlen($user) > 8)
            $user = substr($user, 0, 8);
        $base = $user;
        $success = false;
        $index = 1;
        while(!$success) {
            $success = true;
            $result = $this->getUser($user);
            if($result) {
                $success = false;
                $max = $index < 10?7:6;
                if(strlen($base) > $max)
                    $base = substr($base, 0, $max);
                $user = $base . (string)$index++;
            }
        }
        $this->insertUser($user, md5(uniqid(rand())), $fullname, "", "");
        $this->assignAccount($user, $account);
        return $user;
    }
    
    public function validatePassword($user, $password, $updateTimestamp, &$groups=0) {
        $success = 0;
    
        $userl = strtolower($user.$password);
        $posUnion = strpos($userl, " union ");
        $posSelect = strpos($userl, " select ");
        if($posUnion !== FALSE && $posSelect > $posUnion ||
                strpos($userl, ";") !== FALSE)
            return 0;
    
        $query = "SELECT * FROM users WHERE name=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $result = $stmt->executeAndFetch();
        if($result) {
            if(md5(substr($result["password"], 0, 2).$password) ==
                          substr($result["password"], 2)) {
                if($updateTimestamp) {
                    $query = "UPDATE users SET lastlogin=now() WHERE name=?";
                    $stmt = $this->prepare($query);
                    $stmt->bindValue(1, $user);
                    $stmt->execute();
                }
                $success = 1;
            }
        }
        if($success)
            $groups = $result["groups"];
        return $success;
    }
    
    public function updateUser($user, $password, $realname="XXZZ", $groups="XXZZ", $expiration="XXZZ") {
        $comma = "";
        $query = "UPDATE users SET";
        if($password) {
            $query .= " password=?";
            $comma = ",";
        }
        if($realname != "XXZZ") {
            $query .= $comma." realname=?";
            $comma = ",";
        }
        if($groups != "XXZZ") {
            $query .= $comma." `groups`=?";
            $comma = ",";
        }
        if($expiration != "XXZZ")
            $query .= $comma." expires=?";
    
        $query .= " WHERE name=?";
        $stmt = $this->prepare($query);
        $p = 1;
        if($password) {
            $salt = substr(md5(uniqid(rand())), 0, 2);
            $stmt->bindValue($p++, $salt.md5($salt.$password));
        }
        if($realname != "XXZZ")
            $stmt->bindValue($p++, $realname);
        if($groups != "XXZZ")
            $stmt->bindValue($p++, $groups);
        if($expiration != "XXZZ")
            $stmt->bindValue($p++, $expiration ? $expiration : NULL);
        $stmt->bindValue($p++, $user);
        $stmt->execute();
        return ($stmt->rowCount() >= 0);
    }
    
    public function insertUser($user, $password, $realname, $groups, $expiration) {
        $salt = substr(md5(uniqid(rand())), 0, 2);
        $query = "INSERT INTO users (name, password, realname, `groups`, expires) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $stmt->bindValue(2, $salt.md5($salt.$password));
        $stmt->bindValue(3, $realname);
        $stmt->bindValue(4, $groups);
        $stmt->bindValue(5, $expiration ? $expiration : NULL);
        $stmt->execute();
        return ($stmt->rowCount() > 0);
    }

    public function deleteUser($user) {
        // validate this user has no playlists nor reviews
        $query = "SELECT COUNT(*) c FROM lists WHERE dj = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $result = $stmt->executeAndFetch();
        if($result['c'])
            return false;

        $query = "SELECT COUNT(*) c FROM reviews WHERE user = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $result = $stmt->executeAndFetch();
        if($result['c'])
            return false;

        // remove any airnames
        Engine::api(IDJ::class)->getAirnames($user);

        // remove any api keys
        $query = "DELETE FROM apikeys WHERE user = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $stmt->execute();

        $query = "DELETE FROM users WHERE name = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        return $stmt->execute();
    }

    public function getAPIKeys($user) {
        $query = "SELECT id, apikey FROM apikeys WHERE user = ? ORDER BY id";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        return $stmt->iterate();
    }

    public function addAPIKey($user, $apikey) {
        $query = "INSERT INTO apikeys (user, apikey) VALUES (?, ?)";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $stmt->bindValue(2, $apikey);
        return $stmt->execute();
    }

    public function deleteAPIKeys($user, array $ids) {
        $in = str_repeat("?, ", sizeof($ids) - 1) . "?";
        $query = "DELETE FROM apikeys WHERE user=? AND id IN ($in)";
        $stmt = $this->prepare($query);
        $params = array_merge([$user], $ids);
        return $stmt->execute($params);
    }

    public function lookupAPIKey($apikey) {
        $query = "SELECT user, `groups`, realname FROM apikeys a ".
                 "LEFT JOIN users u ON a.user = u.name ".
                 "WHERE apikey=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $apikey);
        return $stmt->executeAndFetch();
    }
}
