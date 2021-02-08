<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2020 Jim Mason <jmason@ibinx.com>
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
 * Row iterator
 *
 * see BaseStatement::iterate
 */
class RowIterator {
    private $stmt;
    private $style;

    public function __construct($stmt, $style) {
        $this->stmt = $stmt;
        $this->style = $style;
    }

    public function fetch() {
        if($this->stmt) {
            $result = $this->stmt->fetch($this->style);
            if(!$result)
                $this->stmt = null;
            return $result;
        } else
            return false;
    }
}

/**
 * BaseStatement is a PDOStatement decorator that logs all errors;
 * as well, it provides several additional utility methods.
 */
class BaseStatement {
    private $delegate;

    public function __construct($delegate) {
        $this->delegate = $delegate;
    }

    public function __call($method, $args) {
        $ret = call_user_func_array([$this->delegate, $method], $args);

        // several methods can return 'false' in a non-error situation,
        // but the most common/only one we use is 'fetch'
        if($ret === false && $method != "fetch")
            error_log("PDOStatement::$method: " .
                      $this->delegate->errorInfo()[2]);

        return $ret;
    }

    /**
     * execute statement and return iterable result set
     *
     * result is an iterator
     *
     * call 'fetch' on the iterator to get each row; fetch
     * returns false after the last row
     *
     * @param style result set style
     * @return iterable result set (empty if none or error)
     */
    public function iterate($style=\PDO::FETCH_ASSOC) {
        return new RowIterator($this->execute()?$this:null, $style);
    }

    /**
     * execute statement and return single row result
     *
     * @param style result set style
     * @return single row result or false if none or error
     */
    public function executeAndFetch($style=\PDO::FETCH_ASSOC) {
        return $this->execute()?$this->fetch($style):false;
    }

    /**
     * execute statement and return multiple row result as array
     *
     * @param style result set style
     * @return result set array or empty array if none or error
     */
    public function executeAndFetchAll($style=\PDO::FETCH_ASSOC) {
        $result = $this->execute()?$this->fetchAll($style):false;
        return $result?$result:[];
    }
}

/**
 * PDO decorator that logs errors, as well as provides pre-MySQL 5.7.5
 * semantics for GROUP BY queries
 */
class BasePDO {
    const LIBRARY_TABLES = [
        "albumvol", "colltracknames", "publist", "tagqueue", "tracknames"
    ];

    private static $replace;

    private $delegate;
    private $legacyGroupBy;

    public static function setLibrary($library) {
        // setup translations for the library table names
        $replace = [];
        foreach(BasePDO::LIBRARY_TABLES as $table)
            $replace[" $table"] = " $library.$table";
        self::$replace = $replace;
    }

    /**
     * ctor has the same signature as PDO:
     *    new BasePDO($dsn [, $user [, $pass [, $options ]]])
     */
    public function __construct() {
        $args = func_get_args();
        $this->delegate = new \PDO(...$args);

        // we do our own exception handling and logging
        $this->delegate->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
    }

    public function __call($method, $args) {
        $ret = call_user_func_array([$this->delegate, $method], $args);

        if($ret === false) {
            switch($method) {
            case "exec":
            case "getAttribute":
            case "inTransaction":
                // these methods return false in non-error situations
                break;
            default:
                error_log("PDO::$method: " .
                          $this->delegate->errorInfo()[2]);
            }
        }

        return $ret;
    }

    public function prepare($stmt, $options = []) {
        // disable ONLY_FULL_GROUP_BY for pre-MySQL 5.7.5 legacy behaviour
        // see https://dev.mysql.com/doc/refman/5.7/en/group-by-handling.html
        if(!$this->legacyGroupBy &&
                stripos($stmt, "GROUP BY") !== false) {
            $resetMode = "SET SESSION sql_mode=" .
                "(SELECT REPLACE(@@SESSION.sql_mode,'ONLY_FULL_GROUP_BY',''))";
            $this->delegate->exec($resetMode);
            $this->legacyGroupBy = true;
        }

        // if library database is different to the main database, qualify
        // library table references with the library database name
        if(self::$replace)
            $stmt = strtr($stmt, self::$replace);

        $ret = $this->__call("prepare", [$stmt, $options]);
        return $ret?new BaseStatement($ret):false;
    }
}

/**
 * DBO is the superclass for all engine objects which require database access
 *
 * general pattern is that the derived class calls the instance method
 * 'prepare' to obtain a PDOStatement on the default database.
 */
abstract class DBO {
    const DATABASE_MAIN = 'database';
    const DATABASE_LIBRARY = 'library';

    // we store these statically, as they are shared across all instances
    private static $dbConfig;
    private static $pdo;

    /**
     * convenience method to retrieve a database configuration parameter
     *
     * @param name parameter
     * @return configuration value or null if does not exist
     */
    private function dbConfig($name) {
        if(!self::$dbConfig) {
            self::$dbConfig = Engine::param('db');

            // setup translation if library database is different to main
            if(array_key_exists(DBO::DATABASE_LIBRARY, self::$dbConfig) &&
                    ($library = self::$dbConfig[DBO::DATABASE_LIBRARY]) !=
                    self::$dbConfig[DBO::DATABASE_MAIN])
                BasePDO::setLibrary($library);
        }

        return array_key_exists($name, self::$dbConfig)?
                self::$dbConfig[$name]:null;
    }

    /**
     * instantiate a new PDO object from the config file db parameters
     *
     * instead of this method, use 'prepare' or 'getPDO' if possible.
     *
     * @param name config file database name key (optional)
     * @return PDO
     */
    protected function newPDO($name = DBO::DATABASE_MAIN) {
        $dsn = $this->dbConfig('driver') .
                ':host=' . $this->dbConfig('host') .
                ';dbname=' . $this->dbConfig($name) .
                ';charset=utf8mb4';
        return new BasePDO($dsn, $this->dbConfig('user'), $this->dbConfig('pass'));
    }

    /**
     * get singleton PDO for the default database
     *
     * note the PDO is shared across all DBO instances
     *
     * this method is not normally directly invoked; instead the
     * convenience method 'prepare' is used to prepare a statement
     * on the default database.
     *
     * @return PDO
     */
    protected function getPDO() {
        if(!self::$pdo)
            self::$pdo = $this->newPDO();

        return self::$pdo;
    }

    /**
     * release the default database singleton
     *
     * this is a work-around for long-running processes
     * until automatic database reconnection gets implemented
     */
    public static function release() {
        self::$pdo = null;
    }

    /**
     * prepare a statement for execution
     *
     * @param stmt SQL statement
     * @param options driver options (optional)
     * @return PDOStatement
     */
    protected function prepare($stmt, $options = []) {
        return $this->getPDO()->prepare($stmt, $options);
    }

    /**
     * return the ID of the last inserted row
     */
    public function lastInsertId() {
        return $this->getPDO()->lastInsertId();
    }
}
