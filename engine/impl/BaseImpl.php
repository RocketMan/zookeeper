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
 * implementation superclass
 */
class BaseImpl {
    private $pdo;
    private $fullGroupBy = true;

    public function init($pdo) {
        $this->pdo = $pdo;
    }

    protected function getPDO() {
        return $this->pdo;
    }

    protected function prepare($arg) {
        // disable ONLY_FULL_GROUP_BY for pre-MySQL 5.7.5 legacy behaviour
        // see https://dev.mysql.com/doc/refman/5.7/en/group-by-handling.html
        if($this->fullGroupBy && stripos($arg, "GROUP BY") !== false) {
            $stmt = "SET SESSION sql_mode=" .
                "(SELECT REPLACE(@@SESSION.sql_mode,'ONLY_FULL_GROUP_BY',''))";
            $this->pdo->exec($stmt);
            $this->fullGroupBy = false;
        }

        $stmt = $this->pdo->prepare($arg);
        if(!$stmt)
            error_log("PDO::prepare: " . $this->pdo->errorInfo()[2]);

        return $stmt?new BaseStatement($stmt):false;
    }
}
