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

class Engine {
    const VERSION = "2.6.0";

    private static $apis;
    private static $config;
    private static $session;
    private static $pdo;

    /**
     * start of day initialization
     */
    public static function init() {
        spl_autoload_register(function($class) {
            // extract leaf class name
            $p = strrchr($class, '\\');
            $p = $p?substr($p, 1):$class;
            /**
             * search path:
             *   1. this directory
             *   2. 'impl' subdirectory
             *   3. class without Impl suffix in 'impl' subdir
             */
            if(is_file(__DIR__."/${p}.php"))
                include __DIR__."/${p}.php";
            else if(is_file(__DIR__."/impl/${p}.php"))
                include __DIR__."/impl/${p}.php";
            else {
                $q = strpos($p, 'Impl');
                if($q !== false)
                   $p = substr($p, 0, $q);
                if(is_file(__DIR__."/impl/${p}.php"))
                   include __DIR__."/impl/${p}.php";
            }
        });

        // application configuration file
        self::$config = new Config();
        self::$config->init(__DIR__.'/../config/config.php');

        // engine configuration file
        self::$apis = new Config();
        self::$apis->init(__DIR__.'/../config/engine_config.php');

        // default PDO
        self::$pdo = self::newPDO();

        self::$session = new Session(self::$pdo);
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
     * instantiate a new PDO object from the config file db parameters
     * @param databaseName name of database (optional)
     * @return PDO
     */
    public static function newPDO($databaseName = 'database') {
        $db = self::config()->getParam('db');
        $dns = $db['driver'] .
                ':host=' . $db['host'] .
                ';dbname=' . $db[$databaseName] .
                ';charset=utf8mb4';
        return new \PDO($dns, $db['user'], $db['pass']);
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
            $api->init(self::$pdo);
            return $api;
        } else
            throw new \Exception("Unknown API '$intf'");
    }

    /**
     * return the ID of the last inserted row
     */
    public static function lastInsertId() {
        return self::$pdo->lastInsertId();
    }
}

// static initialization of the engine
Engine::init();
