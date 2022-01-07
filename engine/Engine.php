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

class Engine {
    const VERSION = "2.11.0-DEV";

    private static $apis;
    private static $config;
    private static $session;

    /*
     * install autoloader for the engine implementation classes
     */
    private static function customAutoloader() {
        spl_autoload_register(function($class) {
            // search for file without Impl suffix in the 'impl' subdir
            $prefix = str_replace("\\", "\\x5c", __NAMESPACE__."\\");
            if(preg_match("/${prefix}(.+)Impl$/", $class, $matches) &&
                    is_file($path = __DIR__."/impl/${matches[1]}.php")) {
                include $path;
            }
        });
    }

    /**
     * start of day initialization
     */
    public static function init() {
        self::customAutoloader();

        // application configuration file
        self::$config = new Config('config');

        // engine configuration file
        self::$apis = new Config('engine_config');

        self::$session = new Session();
    }

    /**
     * get the configuration file
     * @return configuration file
     */
    public static function config() { return self::$config; }

    /**
     * get a configuration value from the configuration file
     *
     * this is a convenience method for config()->getParam()
     *
     * @param key name of param
     * @param default value if param is not set (optional)
     * @return value or null if not set and no default specified
     */
    public static function param($key, $default = null) {
        return self::$config->getParam($key, $default);
    }

    /**
     * return the session singleton
     */
    public static function session() {
        return self::$session;
    }

    /**
     * instantiate an API
     * @param intf interface
     * @return implementation
     * @throws Exception if no implementation for specified interface
     */
    public static function api($intf) {
        $impl = self::$apis->getParam($intf);
        if($impl) {
            $api = new $impl();
            return $api;
        } else
            throw new \Exception("Unknown API '$intf'");
    }

    /**
     * return the URL of the current request, less leaf filename, if any
     */
    public static function getBaseUrl() {
        // api gets path only
        // must be absolute path for FILTER_VALIDATE_URL
        if(isset($_SERVER['REDIRECT_APIVER']))
            return $_SERVER['REDIRECT_PREFIX'] ?? "/";

        if(php_sapi_name() == "cli")
            return "";

        $uri = $_SERVER['REQUEST_URI'];

        // strip the query string, if any
        $qpos = strpos($uri, "?");
        if($qpos !== false)
            $uri = substr($uri, 0, $qpos);

        $port = ":" . $_SERVER['SERVER_PORT'];
        if($port == ":443" || $port == ":80")
            $port = "";

        // compose the URL
        return $_SERVER['REQUEST_SCHEME'] . "://" .
               $_SERVER['SERVER_NAME'] . $port .
               preg_replace("{/[^/]+$}", "/", $uri);
    }
}

// static initialization of the engine
Engine::init();
