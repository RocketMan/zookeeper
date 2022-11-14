<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2022 Jim Mason <jmason@ibinx.com>
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


class Session extends DBO {
    private const TOKEN_AUTH = "apikey";

    private $user;
    private $displayName;
    private $access = null;
    private $sessionID = null;
    private $sessionCookieName = "session";
    private $secure;

    public function __construct() {
        // Cookies are shared between all instances on the same server.
        //
        // As the state they represent may differ between instances,
        // we must scope the session cookie to each instance.
        if(!empty($_SERVER['SERVER_PORT'])) {
            $port = $_SERVER['SERVER_PORT'];
            switch($port) {
            case 80:
            case 443:
               // standard port, no suffix
               break;
            default:
               // non-standard port, apply suffix
               $this->sessionCookieName .= "-" . $port;
               break;
            }
        }

        $this->secure = empty($_SERVER['REQUEST_SCHEME'])?false:
            $_SERVER['REQUEST_SCHEME'] == 'https';

        // we no longer accept the session ID as a request parameter;
        // it must be delievered in the request header as a cookie.
        if(!empty($_COOKIE[$this->sessionCookieName]))
            $this->validate($_COOKIE[$this->sessionCookieName]);
        else if(!empty($_SERVER['HTTP_X_APIKEY']))
            $this->authorizeApiKey($_SERVER['HTTP_X_APIKEY']);
    }

    public function getDN() { return $this->displayName; }
    public function getUser() { return $this->user; }

    private function setSessionCookie($session) {
        // help prevent CSRF attacks with SameSite cookie flag
        // 'SameSite=Lax' omits the cookie in cross-site POST requests
        // see https://portswigger.net/web-security/csrf/samesite-cookies
        if(PHP_VERSION_ID < 70300) {
            // work-around for missing SameSite flag in php 7.2 and earlier
            setcookie($this->sessionCookieName, $session, 0, "/; samesite=lax", $_SERVER['SERVER_NAME'], $this->secure, true);
        } else {
            setcookie($this->sessionCookieName, $session, [
                'expires' => 0,
                'path' => '/',
                'domain' => $_SERVER['SERVER_NAME'],
                'secure' => $this->secure,
                'httponly' => true,
                'samesite' => 'lax'
            ]);
        }
    }

    private function clearSessionCookie() {
        // Clear the session cookie, if any
        if(isset($_COOKIE[$this->sessionCookieName])) {
            if(PHP_VERSION_ID < 70300) {
                // work-around for missing SameSite flag in php 7.2 and earlier
                setcookie($this->sessionCookieName, "",
                          time() - 3600, "/; samesite=lax",
                          $_SERVER['SERVER_NAME'], $this->secure, true);
            } else {
                setcookie($this->sessionCookieName, "", [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'domain' => $_SERVER['SERVER_NAME'],
                    'secure' => $this->secure,
                    'httponly' => true,
                    'samesite' => 'lax'
                ]);
            }
        }
    }

    private function dbQuery($session) {
        $query = "SELECT user, access, realname FROM sessions WHERE sessionkey=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $session);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function dbCreate($sessionID, $user, $access, $realname) {
        $query = "INSERT INTO sessions " .
                     "(sessionkey, user, access, realname, logon) " .
                     "VALUES (?, ?, ?, ?, now())";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $sessionID);
        $stmt->bindValue(2, $user);
        $stmt->bindValue(3, $access);
        $stmt->bindValue(4, $realname);
        return $stmt->execute();
    }

    private function dbDelete($session) {
        $query = "DELETE FROM sessions WHERE sessionkey= ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $session);
        return $stmt->execute();
    }

    public function purgeOldSessions() {
        $query = "DELETE FROM ssoredirect WHERE ".
                 "DATE_ADD(created, INTERVAL 1 DAY) < NOW()";
        $stmt = $this->prepare($query);
        $success = $stmt->execute();

        $query = "DELETE FROM ssosetup WHERE ".
                 "DATE_ADD(created, INTERVAL 1 DAY) < NOW()";
        $stmt = $this->prepare($query);
        $success &= $stmt->execute();

        $query = "DELETE FROM sessions WHERE ".
                 "DATE_ADD(logon, INTERVAL 2 DAY) < NOW()";
        $stmt = $this->prepare($query);
        $success &= $stmt->execute();

        return $success;
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

    public function authorizeApiKey($apikey) {
        // invalidate apikey with invalid characters (injection control)
        $user = preg_match("/^[0-9a-f]+$/", $apikey) ?
                    Engine::api(IUser::class)->lookupAPIKey($apikey) : null;
        if($user) {
            $access = $user['groups'] . (self::checkLocal()?'l':'');

            // Reject disabled and non-local guest accounts
            if(self::checkAccess('d', $access) ||
                   self::checkAccess('g', $access) &&
                       !self::checkAccess('l', $access))
                return;

            $this->user = $user['user'];
            $this->access = $access;
            $this->displayName = $user['realname'];
            $this->sessionID = self::TOKEN_AUTH;
        }
    }

    public function validate($sessionID) {
        // invalidate session with invalid characters (injection control)
        $row = preg_match("/^[0-9a-f]+$/", $sessionID) ?
                $this->dbQuery($sessionID) : null;

        if($row) {
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
        case "T":    // token authentication
            $allow = $this->sessionID == self::TOKEN_AUTH;
            break;
        case "u":    // authenticated users only
            $allow = !empty($this->sessionID);
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
        case "T":    // token authentication (invalid for checkAccess)
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

    /*
     * test if an IP address is in a subnet
     *
     * subnet may be specified in CIDR notation (e.g., 192.168.0.0/24)
     * or as a fragment (e.g., 192.168.0), in which case the
     * number of network bits is inferred from the fragment length.
     *
     * @param $addr dotted quad address string (e.g., 192.168.0.1)
     * @param $subnet CIDR subnet or address fragment string
     * @return true if and only if the address is in the subnet
     */
    public static function addrInSubnet($addr, $subnet) {
        $subnet = rtrim($subnet, '.');
        $segCount = substr_count($subnet, '.');
        if(strpos($subnet, '/') === false)
            $subnet .= str_repeat('.0', 3 - $segCount) . '/' . ++$segCount * 8;
        $parts = explode('/', $subnet);
        $netmask = ~(pow(2, 32 - $parts[1]) - 1);
        return (ip2long($addr) & $netmask) == (ip2long($parts[0]) & $netmask);
    }

    public static function checkLocal() {
        $local_subnet = Engine::param('local_subnet');
        return !$local_subnet ||
                    self::addrInSubnet($_SERVER['REMOTE_ADDR'], $local_subnet);
    }
}
