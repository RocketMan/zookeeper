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

class Engine {
    const VERSION = "3.2.5";

    const UA = "Zookeeper/2.0; (+https://zookeeper.ibinx.com/)";

    private static Config $apis;
    private static Config $config;
    private static Session $session;

    private static array $apiCache = [];

    /**
     * start of day initialization
     */
    public static function init() {
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
     * return concrete API implementation
     *
     * @param string $intf interface name
     * @return object API implementation
     * @throws \Exception if no implementation for specified interface
     */
    public static function api(string $intf): object {
        $impl = self::$apis->getParam($intf);
        if($impl)
            return self::$apiCache[$intf] ??= new $impl();

        throw new \Exception("Unknown API '$intf'");
    }

    /**
     * return the API version
     */
    public static function getApiVer() : float {
        return floatval($_SERVER['REDIRECT_APIVER'] ?? 1);
    }

    public static function getAppBasePath() : string {
        $uri = isset($_SERVER['REDIRECT_APIVER']) ?
                       ($_SERVER['REDIRECT_BASE'] ?? '/') :
                        $_SERVER['REQUEST_URI'];

        // strip the query string, if any
        $qpos = strpos($uri, "?");
        if($qpos !== false)
            $uri = substr($uri, 0, $qpos);

        return preg_replace("{/[^/]+$}", "/", $uri);
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

        $port = ":" . $_SERVER['SERVER_PORT'];
        if($port == ":443" || $port == ":80")
            $port = "";

        // compose the URL
        return $_SERVER['CLIENT_SCHEME'] . "://" .
               $_SERVER['SERVER_NAME'] . $port .
               self::getAppBasePath();
    }

    /**
     * decorate the specified asset for cache control
     *
     * @param asset path to target asset
     * @return HTML-encoded URI of decorated asset
     */
    public static function decorate($asset) {
        $mtime = filemtime(dirname(__DIR__) . '/' . $asset);
        $ext = strrpos($asset, '.');
        return htmlspecialchars($mtime && $ext !== FALSE?
            substr($asset, 0, $ext) . '-' . $mtime .
            substr($asset, $ext):$asset, ENT_QUOTES, 'UTF-8');
    }
}

// static initialization of the engine
Engine::init();
