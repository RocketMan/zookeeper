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

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

interface Request {
    /**
     * get whether request was initiated from the command line
     *
     * @return bool true if command line, false otherwise
     */
    public function isCli(): bool;

    /**
     * get the client's address
     *
     * @return string|null ip address of client or null if cli
     */
    public function getRemoteAddr(): ?string;

    /**
     * get the HTTP method of the request
     *
     * @return string request method
     */
    public function getMethod(): string;

    /**
     * get the URI of the request
     *
     * @return UriInterface URI
     */
    public function getUri(): UriInterface;

    /**
     * get protocol used by the frontend client
     *
     * @return string frontend protocol ('http' or 'https')
     */
    public function getClientScheme(): string;

    /**
     * return the URL of the current request, less leaf filename, if any
     */
    public function getBaseUrl(): string;

    /**
     * get the base path of the request
     *
     * this excludes any request-specific path components
     *
     * @return string base path
     */
    public function getAppBasePath(): string;

    /**
     * get path and query string
     *
     * @return string path and query string
     */
    public function getRequestTarget(): string;

    /**
     * get the specified query string parameter
     *
     * this method correctly observes dots in parameter name
     *
     * @param string $key parameter name
     * @param string $default value if no such parameter (default null)
     * @return string|null parameter value or default
     */
    public function getQueryParam(string $key, ?string $default = null): ?string;

    /**
     * get the specified form-post parameter
     *
     * @param string $key parameter name
     * @param string $default value if no such parameter (default null)
     * @return string|null parameter value or default
     */
    public function getPostParam(string $key, ?string $default = null): ?string;

    /**
     * get the specified request parameter
     *
     * obtains parameter from either the query string or form
     * post.  if present in both, chooses the query string.
     *
     * @param string $key parameter name
     * @param string $default value if no such parameter (default null)
     * @return string|null parameter value or default
     */
    public function getRequestParam(string $key, ?string $default = null): ?string;

    /**
     * get request target
     *
     * request target from router configuration or request parameter
     *
     * @return request target or empty string if none
     */
    public function getTarget(): string;

    /**
     * get request action
     *
     * request action from router configuration or request parameter
     *
     * @return request action or empty string if none
     */
    public function getAction(): string;

    /**
     * get request subaction
     *
     * request subaction from router configuration or request parameter
     *
     *
     * @return request subaction or empty string if none
     */
    public function getSubaction(): string;

    /**
     * get the specified cookie
     *
     * @param string $key cookie name
     * @param string $default value if no cookie (default null)
     * @return string|null cookie value or null
     */
    public function getCookie(string $key, ?string $default = null): ?string;

    /**
     * return query string parameters
     *
     * parameters returned by this method correctly observe dots in names
     *
     * @return array query string parameters
     */
    public function getQueryParams(): array;

    /**
     * return form post parameters
     *
     * @return array post parameters
     */
    public function getPostParams(): array;

    /**
     * get the body of the message
     *
     * @return StreamInterface request body
     */
    public function getBody(): StreamInterface;
    
    /**
     * generate an HTTP redirect
     *
     * @param string $url target URL
     * @param array query string key-value pairs
     * @param int|null redirect code (optional; default 307)
     */
    public function doHttpRedirect(string $url, array $params, ?int $code = null): void;

    /**
     * get data from the request environment, typically derived from the
     * $_SERVER superglobal
     *
     * @param string $key parameter name
     * @param string $default value if data does not exist (default null)
     * @return string|null data
     */
    public function getServerParam(string $key, ?string $default = null): ?string;

    /**
     * get data from the request environment, typically derived from the
     * $_SERVER superglobal
     *
     * @return array
     */
    public function getServerParams(): array;

    /*
     * test if an IP address is in a subnet
     *
     * subnet may be specified in CIDR notation (e.g., 192.168.0.0/24)
     * or as a fragment (e.g., 192.168.0), in which case the
     * number of network bits is inferred from the fragment length.
     *
     * @param string $addr dotted quad address string (e.g., 192.168.0.1)
     * @param string $subnet CIDR subnet or address fragment string
     * @return bool true if and only if the address is in the subnet
     */
    public function addrInSubnet(string $addr, string $subnet): bool;

    /**
     * test whether this request originated from the local subnet
     *
     * the local subnet is defined in the configuration file
     *
     * @return bool true if this request originates from the local subnet
     */
    public function checkLocal(): bool;

    /**
     * polyfill for intl `Locale::acceptFromHttp`
     *
     * @param string $header HTTP Accept-Language header
     * @return string best available locale from the header
     */
    public function acceptFromHttp(string $header): string;

    /**
     * get best locale from the current request
     */
    public function getClientLocale(): string;

    /**
     * determine whether current requests uses US locale
     *
     * @return bool true if en_US locale, false otherwise
     */
    public function isUsLocale(): bool;

    /**
     * check for existence of request metadata
     *
     * @param string $key key name to test
     * @return bool true if key exists, false otherwise
     */
    public function hasAttribute(string $key): bool;

    /**
     * get request metadata
     *
     * @param string $key key name to retrieve
     * @param mixed $default value if metadata does not exist (default null)
     * @return mixed metadata
     */
    public function getAttribute(string $key, mixed $default = null): mixed;
}
