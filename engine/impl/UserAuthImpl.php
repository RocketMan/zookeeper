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


class UserAuthImpl extends DBO implements IUserAuth {
    public function getUser($user) {
        $query = "SELECT * FROM users WHERE name = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $user);
        return $stmt->executeAndFetch();
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