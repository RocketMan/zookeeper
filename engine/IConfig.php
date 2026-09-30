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
 * Immutable configuration interface
 *
 * Mutator methods return a *new* instance; the original instance is unchanged
 */
interface IConfig {
    /**
     * return a new configuration from the specified file and variable;
     * the original configuration remains unchanged
     *
     * @param string $file configuration file name
     * @param string $variable variable name (default 'config')
     * @param bool $merge true to include existing (default false)
     * @return IConfig new configuration
     */
    function withConfigFrom(string $file, string $variable = 'config', bool $merge = false): static;
    
    /**
     * return a new configuration with array entries merged;
     * the original configuration remains unchanged
     *
     * @param array $config array to merge
     * @return IConfig new array with merged entries
     */
    function merge(array $config): static;

    /**
     * iterate over the entries in the configuration
     *
     * calls user-supplied closure for each configuration entry;
     * iteration ceases upon first non-null return value from the callback
     *
     * @param \Closure $fn callback to invoke for each entry.  Must accept 1 or 2 parameters
     * @return mixed first non-null value returned by a callback, or null if none
     * @throws \InvalidArgumentException if the callback does not accept 1 or 2 arguments
     */
    function iterate(\Closure $fn): mixed;

    /**
     * return the default (first) configuration entry
     *
     * @return mixed default entry
     */
    function default(): mixed;
    
    /**
     * determine whether the specified configuration key exists
     *
     * @param string $key name of key to test
     * @return bool true if exists, false otherwise
     */
    function has(string $key): bool;

    /**
     * get a configuration value
     *
     * @param string $key name of key
     * @param mixed $default value if key is not set (optional)
     * @return mixed value or null if not set and no default specified
     */
    function get(string $key, mixed $default = null): mixed;

    /**
     * return configuration as an associative array
     *
     * @return array of associative key-value pairs
     */
    function asArray(): array;
}
