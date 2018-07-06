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
 * User operations
 */
class UserImpl extends BaseImpl implements IUser {
    public function getUser($user) {
        $query = "SELECT * FROM users WHERE name = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        return $this->executeAndFetch($stmt);
    }

    public function getUsers() {
        $query = "SELECT name, realname FROM users u ORDER BY name";
        $stmt = $this->prepare($query);
        return $this->execute($stmt);
    }
    
    public function getUserByAccount($account) {
        $query = "SELECT * FROM users WHERE ssoaccount = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $account);
        $stmt->execute();
        return $this->executeAndFetch($stmt);
    }
    
    public function getUserByFullname($fullname) {
        $query = "SELECT * FROM users WHERE realname = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $fullname);
        $stmt->execute();
        return $this->executeAndFetch($stmt);
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
        return $this->executeAndFetch($stmt);
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
        $row = $this->executeAndFetch($stmt);
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
        $result = $this->executeAndFetch($stmt);
        if($result) {
            /*
            if(!$result["password"]) {
                // Authenticate with legacy password
                if(ZKCrypt($password) == $result["legacypass"]) {
                    $salt = substr(md5(uniqid(rand())), 0, 2);
    
                    // Replace legacy password with new password
                    $query = "UPDATE users SET password=?, legacypass=NULL";
                    if($updateTimestamp)
                        $query .= ", lastlogin=now()";
                    $query .= " WHERE name=?";
                    $stmt = $this->prepare($query);
                    $stmt->bindValue(1, $salt.md5($salt.$password));
                    $stmt->execute();
                    $success = 1;
                }
            } else */
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
            $query .= " password=?, legacypass=NULL";
            $comma = ",";
        }
        if($realname != "XXZZ") {
            $query .= $comma." realname=?";
            $comma = ",";
        }
        if($groups != "XXZZ") {
            $query .= $comma." groups=?";
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
            $stmt->bindValue($p++, $expiration);
        $stmt->bindValue($p++, $user);
        $stmt->execute();
        return ($stmt->rowCount() >= 0);
    }
    
    public function insertUser($user, $password, $realname, $groups, $expiration) {
        $salt = substr(md5(uniqid(rand())), 0, 2);
        $query = "INSERT INTO users (name, password, realname, groups, expires) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        $stmt->bindValue(2, $salt.md5($salt.$password));
        $stmt->bindValue(3, $realname);
        $stmt->bindValue(4, $groups);
        $stmt->bindValue(5, $expiration);
        $stmt->execute();
        return ($stmt->rowCount() > 0);
    }
}
