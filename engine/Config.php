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

/**
 * Access configuration data
 */
class Config {
    private $config;

    public function init($file) {
        // populate the config property from the given file
        include $file;
        if(isset($config) && is_array($config))
            $this->config = $config;
    }

    /**
     * get a configuration value from the configuration file
     *
     * @param key name of param
     * @param default value if param is not set (optional)
     * @return value or null if not set and no default specified
     */
    public function getParam($key, $default = null) {
        return isset($this->config) &&
                   array_key_exists($key, $this->config)?
                   $this->config[$key]:$default;
    }

    /**
     * set a configurate value in the in-memory configuration data
     *
     * @param key name of param
     * @param value value to set
     */
    public function setParam($key, $value) {
        if(isset($this->config))
            $this->config[$key] = $value;
    }
}
