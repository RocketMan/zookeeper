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


class Session {
    private $user;
    private $displayName;
    private $access = null;
    private $sessionID = null;
    private $sessionCookieName = "session";
    private $secure = true;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;

        // Cookies are shared between all instances on the same server.
        //
        // As the state they represent may differ between instances,
        // we must scope the session cookie to each instance.
        $port = $_SERVER['SERVER_PORT'];
        switch($port) {
        case 80:
           $this->secure = false;
           // fall through...
        case 443:
           // standard port, no suffix
           break;
        default:
           // non-standard port, apply suffix
           $this->sessionCookieName .= "-" + $port;
           break;
        }

        // we no longer accept the session ID as a request parameter;
        // it must be delievered in the request header as a cookie.
        if(!empty($_COOKIE[$this->sessionCookieName]))
            $this->validate($_COOKIE[$this->sessionCookieName]);
    }

    public function getDN() { return $this->displayName; }
    public function getSessionID() { return $this->sessionID; }
    public function getUser() { return $this->user; }

    private function setSessionCookie($session) {
        setcookie($this->sessionCookieName, $session, 0, "/", $_SERVER['SERVER_NAME'], $this->secure, true);
        setcookie("port", mt_rand(), 0, "/", $_SERVER['SERVER_NAME'], $this->secure, true);
    }

    private function clearSessionCookie() {
        // Clear the session cookie, if any
        if(isset($_COOKIE[$this->sessionCookieName]))
            setcookie($this->sessionCookieName, "",
                      time() - 3600, "/", $_SERVER['SERVER_NAME'],
                      $this->secure, true);
        if(isset($_COOKIE['port']))
            setcookie("port", "", time() - 3600, "/", $_SERVER['SERVER_NAME'],
                      $this->secure, true);
    }

    private function validatePort($sessionID, $portID) {
        // port id is the hashed UA, cookie (if any), and perturbation constant
        $local = md5($_SERVER['HTTP_USER_AGENT'] . $_COOKIE['port'] . "uioer");
        if($portID) {
            // compare the calculated port id with the recorded one
            $success = $portID == $local;
        } else {
            // first time through; setup the port id
            $this->dbUpdate($local, $sessionID);
            $success = true;
        }
        return $success;
    }

    private function dbQuery($session) {
        $query = "SELECT user, access, realname, portid FROM sessions WHERE sessionkey=?";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(1, $session);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function dbCreate($sessionID, $user, $access, $realname) {
        $query = "INSERT INTO sessions " .
                     "(sessionkey, user, access, realname, logon) " .
                     "VALUES (?, ?, ?, ?, now())";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(1, $sessionID);
        $stmt->bindValue(2, $user);
        $stmt->bindValue(3, $access);
        $stmt->bindValue(4, $realname);
        return $stmt->execute();
    }

    private function dbDelete($session) {
        $query = "DELETE FROM sessions WHERE sessionkey= ?";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(1, $session);
        return $stmt->execute();
    }

    private function dbUpdate($portID, $sessionID) {
        $query = "UPDATE sessions SET portid = ? WHERE sessionkey = ?";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(1, $portID);
        $stmt->bindValue(2, $sessionID);
        return $stmt->execute();
    }

    public function purgeOldSessions() {
        $query = "DELETE FROM sessions WHERE ".
                 "DATE_ADD(logon, INTERVAL 2 DAY) < NOW()";
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute();
    }

    public function create($sessionID, $user, $auth) {
        $row = Engine::api(IUser::class)->getUser($user);
        if($row)
            $this->displayName = $row['realname'];
        $this->user = $user;
        $this->access = $auth;
        $this->sessionID = $sessionID;

        $this->dbCreate($sessionID, $user, $auth, $this->displayName);
        $this->setSessionCookie($sessionID);
    }

    public function validate($sessionID) {
        // invalidate session with invalid characters (injection control)
        if(strlen($sessionID) != strspn($sessionID, "0123456789abcdef"))
            $sessionID = "";
    
        $row = $this->dbQuery($sessionID);
        if($row &&
               $this->validatePort($sessionID, $row['portid'])) {
            // Session found
            $this->user = $row['user'];
            $this->access = $row['access'];
            $this->displayName = $row['realname'];
            $this->sessionID = $sessionID;
        } else {
            // Failure
            $this->sessionID = null;
            $this->access = null;
            $this->clearSessionCookie();
        }
    }

    public function invalidate() {
        if($this->sessionID) {
            $this->dbDelete($this->sessionID);
            $this->clearSessionCookie();
            $this->sessionID = null;
            $this->access = null;
        }
    }

    public function isAuth($mode) {
        switch($mode) {
        case "a":    // all
            $allow = true;
            break;
        case "u":    // authenticated users only
            $allow = $this->sessionID;
            break;
        case "U":    // local (not SSO) user
            $allow = $this->sessionID &&
                             !preg_match("/s/i", $this->access);
            break;
        case "":     // empty mode is invalid
            $allow = false;
            break;
        default:     // specific user mode
            $allow = $this->access &&
                               preg_match("/".$mode."/i", $this->access);
            break;
        }
        return $allow;
    }

    public function isLocal() {
        return $this->isAuth('l');
    }

    public static function checkAccess($mode, $access) {
        switch($mode) {
        case "a":    // all
            $allow = true;
            break;
        case "u":    // authenticated user (invalid for checkAccess)
        case "U":    // local (not SSO) user (invalid for checkAccess)
        case "":     // empty mode is invalid
            $allow = false;
            break;
        default:     // specific user mode
            $allow = $access &&
                               preg_match("/".$mode."/i", $access);
            break;
        }
        return $allow;
    }

    public static function checkLocal() {
        $local_subnet = Engine::param('local_subnet');
        return !$local_subnet ||
                    substr($_SERVER['REMOTE_ADDR'], 0, strlen($local_subnet))
                        == $local_subnet;
    }
}
