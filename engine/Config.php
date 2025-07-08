<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2025 Jim Mason <jmason@ibinx.com>
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
     * @param string $file base filename of configuration file (without extension)
     * @param string $variable variable name in config file (default 'config')
     */
    public function __construct(string $file, string $variable = 'config') {
        $path = dirname(__DIR__) . "/config/{$file}.php";
        if(!is_file($path))
            throw new \Exception("Config file not found: $file");

        // populate the configuration from the given file and variable
        include $path;
        if(isset($$variable) && is_array($$variable))
            $this->config = $$variable;
        else
            throw new \Exception("Error parsing configuration: file={$file}.php, variable={$variable}");
    }

    /**
     * merge an array of entries into this configuration
     *
     * @param array $config array to merge
     */
    public function merge(array $config): void {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * iterate over the entries in the configuration
     *
     * calls user-supplied callback for each entry.
     * iteration ceases upon first non-null return value from the callback
     *
     * @param \Closure $fn callback to invoke for each entry.  Must accept 1 or 2 parameters
     * @return mixed first non-null value returned by a callback, or null if none
     * @throws \InvalidArgumentException if the callback does not accept 1 or 2 arguments
     */
    public function iterate(\Closure $fn): mixed {
        switch((new \ReflectionFunction($fn))->getNumberOfParameters()) {
        case 1:
            foreach($this->config as $entry)
                if(($x = $fn($entry)) !== null)
                    return $x;
            break;
        case 2:
            foreach($this->config as $key => $value)
                if(($x = $fn($key, $value)) !== null)
                    return $x;
            break;
        default:
            throw new \InvalidArgumentException("closure expects 1 or 2 arguments");
        }

        return null;
    }

    /**
     * return the default (first) configuration entry
     *
     * @return mixed default entry
     */
    public function default(): mixed {
        return $this->config[array_keys($this->config)[0]];
    }

    /**
     * determine whether the specified configuration param exists
     *
     * @param string $key name of param to test
     * @return bool true if exists, false otherwise
     */
    public function hasParam(string $key): bool {
        return array_key_exists($key, $this->config);
    }

    /**
     * get a configuration value from the configuration file
     *
     * @param string $key name of param
     * @param mixed $default value if param is not set (optional)
     * @return mixed value or null if not set and no default specified
     */
    public function getParam(string $key, mixed $default = null): mixed {
        return array_key_exists($key, $this->config)?
                   $this->config[$key]:$default;
    }
}
