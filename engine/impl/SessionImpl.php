<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2026 Jim Mason <jmason@ibinx.com>
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

use ZK\Controllers\Challenge;

class SessionImpl extends DBO implements Session {
    private const TOKEN_AUTH = "apikey";

    private $user = null;
    private $displayName = null;
    private $access = null;
    private $sessionID = null;
    private $sessionCookieName = "session";
    private $secure;
    private $challenge;

    public function __construct(
        protected IUserAuth $userDBO,
        protected Challenge $challenger,
        protected PDO $pdo,
        protected Request $request,
    ) {
        parent::__construct($pdo);

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

        $this->secure = $this->request->getClientScheme() == 'https';

        // we no longer accept the session ID as a request parameter;
        // it must be delievered in the request header as a cookie.
        if(!empty($_COOKIE[$this->sessionCookieName]))
            $this->validate($_COOKIE[$this->sessionCookieName]);
        else if(!empty($_SERVER['HTTP_X_APIKEY']))
            $this->authorizeApiKey($_SERVER['HTTP_X_APIKEY']);
        else if(!empty($_SERVER['HTTP_X_CHALLENGE']))
            $this->challenge = $this->challenger->validate($_SERVER['HTTP_X_CHALLENGE']);
    }

    public function getDN(): ?string { return $this->displayName; }
    public function getUser(): ?string { return $this->user; }
    public function isSecure(): bool { return $this->secure; }

    private function setSessionCookie(string $session): void {
        // help prevent CSRF attacks with SameSite cookie flag
        // 'SameSite=Lax' omits the cookie in cross-site POST requests
        // see https://portswigger.net/web-security/csrf/samesite-cookies
        setcookie($this->sessionCookieName, $session, [
            'expires' => 0,
            'path' => '/',
            'domain' => $_SERVER['SERVER_NAME'],
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'lax'
        ]);
    }

    private function clearSessionCookie(): void {
        // Clear the session cookie, if any
        if(isset($_COOKIE[$this->sessionCookieName])) {
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

    private function dbQuery(string $session): array {
        $query = "SELECT user, access, realname FROM sessions WHERE sessionkey=?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $session);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function dbCreate(string $sessionID, string $user, string $access, string $realname): bool {
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

    private function dbDelete(string $session): bool {
        $query = "DELETE FROM sessions WHERE sessionkey= ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $session);
        return $stmt->execute();
    }

    public function purgeOldSessions(): bool {
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

    public function create(string $sessionID, string $user, string $auth): void {
        $row = $this->userDBO->getUser($user);
        if($row)
            $this->displayName = $row['realname'];

        $success = $this->dbCreate($sessionID, $user, $auth, $this->displayName);
        if ($success) {
            $this->user = $user;
            $this->access = $auth;
            $this->sessionID = $sessionID;
            $this->setSessionCookie($sessionID);
        }
    }

    protected function authorizeApiKey(string $apikey): void {
        // invalidate apikey with invalid characters (injection control)
        $user = preg_match("/^[0-9a-f]+$/", $apikey) ?
                    $this->userDBO->lookupAPIKey($apikey) : null;
        if($user) {
            $access = $user['groups'] . ($this->request->checkLocal()?'l':'');

            // Reject disabled and non-local guest accounts
            if($this->checkAccess('d', $access) ||
                   $this->checkAccess('g', $access) &&
                       !$this->checkAccess('l', $access))
                return;

            $this->user = $user['user'];
            $this->access = $access;
            $this->displayName = $user['realname'];
            $this->sessionID = self::TOKEN_AUTH;
        }
    }

    public function validate(string $sessionID): void {
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

    public function invalidate(): void {
        if($this->sessionID) {
            $this->dbDelete($this->sessionID);
            $this->clearSessionCookie();
            $this->sessionID = null;
            $this->access = null;
        }
    }

    public function isAuth(string $mode): bool {
        switch($mode) {
        case "a":    // all
            $allow = true;
            break;
        case "C":    // challenge (auth user or successful challenge)
            $allow = !empty($this->sessionID) || $this->challenge;
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

    public function isLocal(): bool {
        return $this->isAuth('l');
    }

    public function checkAccess(string $mode, string $access): bool {
        switch($mode) {
        case "a":    // all
            $allow = true;
            break;
        case "C":    // challenge (invalid for checkAccess)
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
}
