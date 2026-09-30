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

interface Session {
    /**
     * get display name for the currently authenticated user
     *
     * @return string|null display name
     */
    function getDN(): ?string;

    /**
     * get internal user name for the currently authenticated user
     *
     * @return string|null internal user name
     */
    function getUser(): ?string;

    /**
     * check whether the client session is over a secure protocol
     *
     * @return bool true if client connection is secure, false otherwise
     */
    function isSecure(): bool;

    /**
     * purge sessions that have exceeded their maximum time to live
     *
     * @return bool true on success, false otherwise
     */
    function purgeOldSessions(): bool;

    /**
     * create a new session
     *
     * @param string $sessionID target session ID
     * @param string $user internal user name
     * @param string $auth authorisations
     */
    function create(string $sessionID, string $user, string $auth): void;

    /**
     * validate a session ID
     *
     * This is used by CI.
     */
    function validate(string $sessionID): void;

    /**
     * invalidate (logout) the current session
     */
    function invalidate(): void;

    /**
     * check whether the currently authenticated user is authorised
     * for the specified mode
     *
     * @param string $mode mode to test
     * @return bool true if authorised, false otherwise
     */
    function isAuth(string $mode): bool;

    /**
     * check whether the user is logged in from the local subnet
     *
     * @return bool true if local subnet
     */
    function isLocal(): bool;

    /**
     * check whether a mode is allowed by specific access rights
     *
     * @param string $mode mode to test
     * @param string $access access rights to test against
     * @param bool true if and only if `access` grants authorisation to `mode`
     */
    function checkAccess(string $mode, string $access): bool;
}
