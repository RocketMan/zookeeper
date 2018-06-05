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
 * implementation superclass
 */
class BaseImpl {
    private $pdo;

    public function init($pdo) {
        $this->pdo = $pdo;
    }

    protected function getPDO() {
        return $this->pdo;
    }

    protected function prepare($arg) {
        return $this->pdo->prepare($arg);
    }

    /**
     * execute statement and result iterable result set
     *
     * result is an iterator (or false on failure)
     *
     * call 'fetch' on the iterator to get each row; fetch
     * returns false after the last row
     *
     * @param stmt statement to execute
     * @param style result set style
     * @return result set or false on failure
     */
    protected function execute($stmt, $style=\PDO::FETCH_ASSOC) {
        return $stmt->execute()?new RowIterator($stmt, $style):false;
    }

    /**
     * execute statement and return single row result
     *
     * @param stmt statement to execute
     * @param style result set style
     * @return single row result or false on failure
     */
    protected function executeAndFetch($stmt, $style=\PDO::FETCH_ASSOC) {
        return $stmt->execute()?$stmt->fetch($style):false;
    }

    /**
     * execute statement and return multiple row result as array
     *
     * @param stmt statement to execute
     * @param style result set style
     * @return result set array or false on failure
     */
    protected function executeAndFetchAll($stmt, $style=\PDO::FETCH_ASSOC) {
        return $stmt->execute()?$stmt->fetchAll($style):false;
    }
}
