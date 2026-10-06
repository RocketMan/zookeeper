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

class Request {
    protected ?string $locale = null;

    public function __construct(
        protected ConfigInterface $config,
    ) {}

    /**
     * return the URL of the current request, less leaf filename, if any
     */
    public function getBaseUrl(): string  {
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
        return $this->getClientScheme() . "://" .
               $_SERVER['SERVER_NAME'] . $port .
               $this->getAppBasePath();
    }

    /**
     * get protocol used by the frontend client
     *
     * @return string frontend protocol ('http' or 'https')
     */
    public function getClientScheme(): string {
        // Caution: $_SERVER['REQUEST_SCHEME'] reflects the *backend*
        // protocol, which may not be the same as the frontend
        // when a proxy or load balancer is used.
        //
        // Per the doc, PHP sets $_SERVER['HTTPS'] on frontend https.
        return !empty($_SERVER['HTTPS'])
                ? 'https'
                : $_SERVER['REQUEST_SCHEME'] ?? 'http';
    }

    public function getAppBasePath(): string {
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
     * alternative to $_GET[] that does not munge dots in qs param names
     *
     * @return array query string parameters
     */
    public function getQSParams() {
        $result = [];
        $params = explode("&", $_SERVER["QUERY_STRING"]);
        foreach ($params as $param) {
            $nameValue = explode("=", $param);
            $name = urldecode($nameValue[0]);
            $value = count($nameValue) > 1 ? urldecode($nameValue[1]) : '';
            $result[$name] = $value;
        }
        return $result;
    }

    /**
     * generate an HTTP redirect
     *
     * @param string $url target URL
     * @param array query string key-value pairs
     */
    public function doHttpRedirect(string $url, array $params): void {
        $qs = http_build_query($params);
        if (strlen($qs))
            $url .= '?' . $qs;
        header("Location: " . $url, true, 307);
    }
    
    /**
     * return the API version
     */
    public function getApiVer() : float {
        return floatval($_SERVER['REDIRECT_APIVER'] ?? 1);
    }

    /*
     * test if an IP address is in a subnet
     *
     * subnet may be specified in CIDR notation (e.g., 192.168.0.0/24)
     * or as a fragment (e.g., 192.168.0), in which case the
     * number of network bits is inferred from the fragment length.
     *
     * @param $addr dotted quad address string (e.g., 192.168.0.1)
     * @param $subnet CIDR subnet or address fragment string
     * @return bool true if and only if the address is in the subnet
     */
    public static function addrInSubnet($addr, $subnet): bool {
        $subnet = rtrim($subnet, '.');
        $segCount = substr_count($subnet, '.');
        if(strpos($subnet, '/') === false)
            $subnet .= str_repeat('.0', 3 - $segCount) . '/' . ++$segCount * 8;
        $parts = explode('/', $subnet);
        $netmask = ~(pow(2, 32 - $parts[1]) - 1);
        return (ip2long($addr) & $netmask) == (ip2long($parts[0]) & $netmask);
    }

    /**
     * test whether this request originated from the local subnet
     *
     * the local subnet is defined in the configuration file
     *
     * @return bool true if this request originates from the local subnet
     */
    public function checkLocal(): bool {
        $local_subnet = $this->config->get('local_subnet');
        return !$local_subnet ||
                    self::addrInSubnet($_SERVER['REMOTE_ADDR'], $local_subnet);
    }

    /**
     * polyfill for intl `Locale::acceptFromHttp`
     *
     * @param $header HTTP Accept-Language header
     * @return best available locale from the header
     */
    public function acceptFromHttp(string $header): string {
        $locales = array_map(function($locale) {
            // parse /(.+)(;.+=(.+))?/
            $lang = strtok($locale, ';');
            $weight = strtok('=') ? (strtok('') ?: 1) : 1;
            return [ $lang, $weight ];
        }, explode(',', $header));

        usort($locales, function($a, $b) {
            return $b[1] <=> $a[1];
        });

        // Accept-Language encodes locales with a hyphen (RFC 4646),
        // whilst PHP Locale functions return an underscore
        return str_replace('-', '_', $locales[0][0]);
    }

    /**
     * convenience method to get best locale from the current request
     */
    public function getClientLocale(): string {
        return $this->locale ??= $this->acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en-US');
    }

    public function isUsLocale(): bool {
        return !strcasecmp($this->getClientLocale(), 'en_US');
    }
}
