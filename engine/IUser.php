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
interface IUser {
    function getUser($user);
    function getUsers();
    function getUserByAccount($account);
    function getUserByFullname($fullname);
    function assignAccount($user, $account);
    function updateLastLogin($id);
    function setupSsoOptions($account, $fullname, $url);
    function getSsoOptions($ssoSession);
    function teardownSsoOptions($ssoSession);
    function setupSsoRedirect($url);
    function getSsoRedirect($ssoSession);
    function createNewAccount($fullname, $account);
    function validatePassword($user, $password, $updateTimestamp, &$groups=0);
    function updateUser($user, $password, $realname="XXZZ", $groups="XXZZ", $expiration="XXZZ");
    function insertUser($user, $password, $realname, $groups, $expiration);
    function querySession($session);
    function createSession($session, $user, $access);
    function setPortID($session, $portid);
    function deleteSession($session);
}
