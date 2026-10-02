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

    /**
     * return one row from the result set
     *
     * @return row or false if none or error
     */
    public function fetch() {
        if($this->stmt) {
            $result = $this->stmt->fetch($this->style);
            if(!$result)
                $this->stmt = null;
            return $result;
        } else
            return false;
    }

    /**
     * return remaining result set rows as an array
     *
     * @return result set array or empty array if none or error
     */
    public function asArray() {
        $result = $this->stmt ? $this->stmt->fetchAll($this->style) : false;
        return $result ?: [];
    }
}

class ArrayRowIterator extends RowIterator {
    protected $rows;

    public function __construct($rows) {
        $this->rows = $rows;
    }

    public function fetch() {
        return count($this->rows) ? array_shift($this->rows) : false;
    }

    public function asArray() {
        $result = $this->rows;
        $this->rows = [];
        return $result;
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
        return new RowIterator($this->execute() ? $this : null, $style);
    }

    /**
     * execute statement and return single row result
     *
     * @param style result set style
     * @return single row result or false if none or error
     */
    public function executeAndFetch($style=\PDO::FETCH_ASSOC) {
        return $this->execute() ? $this->fetch($style) : false;
    }

    /**
     * execute statement and return multiple row result as array
     *
     * @param style result set style
     * @return result set array or empty array if none or error
     */
    public function executeAndFetchAll($style=\PDO::FETCH_ASSOC) {
        $result = $this->execute() ? $this->fetchAll($style) : false;
        return $result ?: [];
    }
}

/**
 * DBO is the superclass for all engine objects which require database access
 *
 * general pattern is that the derived class calls the instance method
 * 'prepare' to obtain a PDOStatement on the default database.
 */
abstract class DBO {
    public const DATABASE_MAIN = 'database';
    public const DATABASE_LIBRARY = 'library';

    private array $locks = [];

    public function __construct(
        private PDO $pdo
    ) {}

    /**
     * prepare a statement for execution
     *
     * @param string $stmt SQL statement
     * @param array $options driver options (optional)
     * @return BaseStatement|false
     */
    protected function prepare(
        string $stmt,
        array $options = []
    ): BaseStatement|false {
        return $this->pdo->prepare($stmt, $options);
    }

    /**
     * execute a statement
     *
     * @param string $stmt SQL statement
     * @return int|false number of rows affected or false on failure
     */
    protected function exec(string $stmt): int|false {
        return $this->pdo->exec($stmt);
    }

    protected function getAdvisoryLockName(int $id): string {
        // as advisory locks are global, we qualify with the db name
        return implode('-', [
            $this->pdo->getDatabaseName(),
            (new \ReflectionClass($this))->getShortName(),
            $id
        ]);
    }

    /**
     * acquire an advisory lock for the specified identifier
     *
     * The lock is specific to the {DBO, id} tuple.  Thus, there is
     * no collision between locks made on different DBOs.
     *
     * This method does not return until the lock has been acquired.
     *
     * The lock is automatically released upon close of the current
     * session, or by calling @see DBO::adviseUnlock()
     *
     * @param int $id target identifier
     */
    public function adviseLock(int $id): void {
        if (!array_key_exists($id, $this->locks)) {
            $stmt = $this->prepare("DO get_lock(?, -1)");
            $stmt->bindValue(1, $this->getAdvisoryLockName($id));
            $stmt->execute();

            $this->locks[$id] = 0;
        }

        $this->locks[$id]++;
    }

    /**
     * release advisory lock
     *
     * @see DBO::adviseLock()
     *
     * @param int $id target identifier
     */
    public function adviseUnlock(int $id): void {
        if (array_key_exists($id, $this->locks) &&
                !--$this->locks[$id]) {
            $stmt = $this->prepare("DO release_lock(?)");
            $stmt->bindValue(1, $this->getAdvisoryLockName($id));
            $stmt->execute();

            unset($this->locks[$id]);
        }
    }

    /**
     * return the ID of the last inserted row
     * @return string|false last inserted row id
     */
    public function lastInsertId(): string|false {
        return $this->pdo->lastInsertId();
    }
}
