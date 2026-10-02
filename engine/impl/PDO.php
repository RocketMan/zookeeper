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

/**
 * \PDO decorator that logs errors, as well as provides pre-MySQL 5.7.5
 * semantics for GROUP BY queries, and pre-PHP 8 semantics for commit/rollBack
 * after implicit commit
 */
class PDO {
    private const LIBRARY_TABLES = [
        'albumvol', 'colltracknames', 'publist', 'tagqueue', 'tracknames',
        'albummap', 'artistmap', 'artwork'
    ];

    private \PDO $delegate;
    private ?string $library;
    private bool $legacyGroupBy = false;

    /**
     * @param string $dsn Data Source Name
     * @param string $user user name for DSN
     * @param string $pass password for DSN
     * @param string $databaseName database name
     * @param string|null $library database name of library if different
     */
    public function __construct(
        string $dsn,
        string $user,
        string $pass,
        private string $databaseName,
        ?string $library = null
    ) {
        $this->delegate = new \PDO($dsn, $user, $pass);
        $this->delegate->setAttribute(
            \PDO::ATTR_ERRMODE,
            \PDO::ERRMODE_SILENT
        );

        $this->library = $library;
    }

    public function __call(string $method, array $args): mixed {
        $ret = call_user_func_array([$this->delegate, $method], $args);

        if ($ret === false) {
            switch ($method) {
            case 'exec':
            case 'getAttribute':
            case 'inTransaction':
                // these methods can return false in non-error situations
                break;

            default:
                error_log(
                    "PDO::$method: " .
                    $this->delegate->errorInfo()[2]
                );
            }
        }

        return $ret;
    }

    public function commit(): mixed {
        // restore pre-PHP 8 semantics after implicit commit
        // see https://www.php.net/manual/en/migration80.incompatible.php#migration80.incompatible.pdo-mysql
        return $this->delegate->inTransaction() ?
            $this->__call('commit', []) : true;
    }

    public function rollBack(): mixed {
        // restore pre-PHP 8 semantics after implicit commit
        // see https://www.php.net/manual/en/migration80.incompatible.php#migration80.incompatible.pdo-mysql
        return $this->delegate->inTransaction() ?
            $this->__call('rollBack', []) : true;
    }

    public function prepare(string $stmt, array $options = []): BaseStatement|false {
        // disable ONLY_FULL_GROUP_BY for pre-MySQL 5.7.5 legacy behaviour
        // see https://dev.mysql.com/doc/refman/5.7/en/group-by-handling.html
        if (!$this->legacyGroupBy && stripos($stmt, 'GROUP BY') !== false) {
            $resetMode =
                "SET SESSION sql_mode=" .
                "(SELECT REPLACE(@@SESSION.sql_mode," .
                "'ONLY_FULL_GROUP_BY',''))";

            $this->delegate->exec($resetMode);
            $this->legacyGroupBy = true;
        }

        // if library database is different to the main database, qualify
        // library table references with the library database name
        if ($this->library) {
            $replace = [];
            foreach (self::LIBRARY_TABLES as $table)
                $replace[" $table"] = " {$this->library}.$table";
            $stmt = strtr($stmt, $replace);
        }

        $ret = $this->__call('prepare', [$stmt, $options]);

        return $ret ? new BaseStatement($ret) : false;
    }

    public function getDatabaseName(): string {
        return $this->databaseName;
    }
}
