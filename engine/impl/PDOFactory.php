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

class PDOFactory {
    private const CONNECT_RETRY = 5;
    private const CONNECT_BACKOFF = 4;

    public function __construct(
        private IConfig $config
    ) {}

    public function create(
        string $database = DBO::DATABASE_MAIN
    ): PDO {
        $db = $this->config->get("db.$database");

        $dsn = $this->config->get('db.driver') .
            ':host=' . $this->config->get('db.host') .
            ';dbname=' . $db .
            ';charset=utf8mb4';

        $library = null;

        if ($database === DBO::DATABASE_MAIN) {
            $configuredLibrary =
                $this->config->get(
                    'db.' . DBO::DATABASE_LIBRARY
                );

            if ($configuredLibrary !== null &&
                    $configuredLibrary !== $db)
                $library = $configuredLibrary;
        }

        $retry = self::CONNECT_RETRY;

        while (true) {
            try {
                return new PDO(
                    $dsn,
                    $this->config->get('db.user'),
                    $this->config->get('db.pass'),
                    $db,
                    $library
                );
            } catch(\PDOException $e) {
                if (!$retry--)
                    throw $e;

                sleep(rand(1, self::CONNECT_BACKOFF));
            }
        }
    }
}
