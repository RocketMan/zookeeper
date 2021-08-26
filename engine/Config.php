<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2021 Jim Mason <jmason@ibinx.com>
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
 * Access configuration data
 */
class Config {
    private $config;

    /**
     * ctor
     *
     * @param file base filename of configuration file (without extension)
     * @param variable variable name in config file (default 'config')
     */
    public function __construct($file, $variable = 'config') {
        // populate the configuration from the given file and variable
        include __DIR__."/../config/${file}.php";
        if(isset($$variable) && is_array($$variable))
            $this->config = $$variable;
        else
            throw new \Exception("Error parsing configuration: file=${file}.php, variable=${variable}");
    }

    /**
     * merge an array of entries into this configuration
     *
     * @param config array to merge
     */
    public function merge($config) {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * iterate over the entries in the configuration
     *
     * calls user-supplied callback for each entry.
     * iteration ceases upon first non-null return value from the callback
     *
     * @param fn callback with signature 'function($entry)'
     * @return first value returned by a callback, if any
     */
    public function iterate($fn) {
        foreach($this->config as $entry)
            if(($x = $fn($entry)) !== null)
                return $x;
    }

    /**
     * return the default (first) configuration entry
     *
     * @return default entry
     */
    public function default() {
        return $this->config[array_keys($this->config)[0]];
    }

    /**
     * determine whether the specified configuration param exists
     *
     * @param key name of param to test
     * @return true if exists, false otherwise
     */
    public function hasParam($key) {
        return array_key_exists($key, $this->config);
    }

    /**
     * get a configuration value from the configuration file
     *
     * @param key name of param
     * @param default value if param is not set (optional)
     * @return value or null if not set and no default specified
     */
    public function getParam($key, $default = null) {
        return array_key_exists($key, $this->config)?
                   $this->config[$key]:$default;
    }
}
