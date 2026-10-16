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
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class RequestImpl implements Request {
    protected ?string $locale = null;
    protected array $attributes = [];
    protected array $queryParams = [];

    public function __construct(
        protected Config $config,
        protected ServerRequestInterface $delegate,
    ) {
        // alternative to $_GET that does not munge dots in parameter names
        $params = explode("&", $this->getUri()->getQuery());
        foreach ($params as $param) {
            $nameValue = explode("=", $param);
            $name = urldecode($nameValue[0]);
            $value = count($nameValue) > 1 ? urldecode($nameValue[1]) : '';
            $this->queryParams[$name] = $value;
        }
    }

    public function isCli(): bool {
        return php_sapi_name() == "cli";
    }

    public function getRemoteAddr(): ?string {
        return $this->getServerParam('REMOTE_ADDR');
    }

    public function getMethod(): string {
        return $this->delegate->getMethod();
    }

    public function getUri(): UriInterface {
        // on rewrite, delegate uri has original query string;
        // we use the final, redirected query string
        $uri = $this->isCli() ? new Uri('/') : $this->delegate->getUri();
        $qs = null;////$this->getServerParam('QUERY_STRING');
        return $qs ? $uri->withQuery($qs) : $uri;
    }

    public function getClientScheme(): string {
        return $this->delegate->getUri()->getScheme();
    }

    public function getBaseUrl(): string  {
        if ($this->isCli())
            return '';

        // basepath is set by the router
        $basepath = $this->getAttribute('basepath', '/');
        $path = rtrim($this->getAppBasePath(), '/') . $basepath;

        // api gets path only
        if ($this->hasAttribute('version'))
            return $path;

        $uri = $this->delegate->getUri();

        $port = $uri->getPort();
        $port = (!$port || $port == 443 || $port == 80) ? '' : ':' . $port;

        // compose the URL
        return $this->getClientScheme() . "://" .
               $uri->getHost() . $port .
               $path;
    }

    public function getAppBasePath(): string {
        // mountpath is set by the router
        return $this->getAttribute('mountpath', '/');
    }

    public function getRequestTarget(): string {
        $uri = $this->getUri();
        $query = $uri->getQuery();

        return $uri->getPath() . ($query !== '' ? "?$query" : '');
    }

    public function getQueryParam(string $key, ?string $default = null): ?string {
        return $this->queryParams[$key] ?? $default;
    }

    public function getPostParam(string $key, ?string $default = null): ?string {
        $bodyParams = $this->delegate->getParsedBody();
        return is_array($bodyParams) && array_key_exists($key, $bodyParams)
                ? $bodyParams[$key]
                : $default;
    }

    public function getRequestParam(string $key, ?string $default = null): ?string {
        return array_key_exists($key, $this->queryParams)
                ? $this->queryParams[$key]
                : $this->getPostParam($key, $default);
    }

    public function getTarget(): string {
        return $this->getAttribute(
            'target', 
            $this->getRequestParam('target', '')
        );
    }

    public function getAction(): string {
        return $this->getAttribute(
            'action', 
            $this->getRequestParam('action', '')
        );
    }

    public function getSubaction(): string {
        return $this->getAttribute(
            'subaction', 
            $this->getRequestParam('subaction', '')
        );
    }

    public function getCookie(string $key, ?string $default = null): ?string {
        $cookies = $this->delegate->getCookieParams();
        return is_array($cookies) && array_key_exists($key, $cookies)
                ? $cookies[$key]
                : $default;
    }

    public function getQueryParams(): array {
        return $this->queryParams;
    }

    public function getBody(): StreamInterface {
        return $this->delegate->getBody();
    }

    public function doHttpRedirect(string $url, array $params): void {
        $qs = http_build_query($params);
        if (strlen($qs))
            $url .= '?' . $qs;
        header("Location: " . $url, true, 307);
    }

    public function getServerParam(string $key, ?string $default = null): ?string {
        $serverParams = $this->delegate->getServerParams();
        return $serverParams[$key] ?? $default;
    }

    public function getServerParams(): array {
        return $this->delegate->getServerParams();
    }

    public function addrInSubnet(string $addr, string $subnet): bool {
        $subnet = rtrim($subnet, '.');
        $segCount = substr_count($subnet, '.');
        if(strpos($subnet, '/') === false)
            $subnet .= str_repeat('.0', 3 - $segCount) . '/' . ++$segCount * 8;
        $parts = explode('/', $subnet);
        $netmask = ~(pow(2, 32 - $parts[1]) - 1);
        return (ip2long($addr) & $netmask) == (ip2long($parts[0]) & $netmask);
    }

    public function checkLocal(): bool {
        $local_subnet = $this->config->get('local_subnet');
        return !$local_subnet ||
                    $this->addrInSubnet($this->getRemoteAddr(), $local_subnet);
    }

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

    public function getClientLocale(): string {
        return $this->locale ??= $this->acceptFromHttp($this->getServerParam('HTTP_ACCEPT_LANGUAGE', 'en-US'));
    }

    public function isUsLocale(): bool {
        return !strcasecmp($this->getClientLocale(), 'en_US');
    }

    public function setAttribute(string $key, mixed $value): void {
        $this->attributes[$key] = $value;
    }

    public function hasAttribute(string $key): bool {
        return array_key_exists($key, $this->attributes);
    }

    public function getAttribute(string $key, mixed $default = null): mixed {
        return $this->attributes[$key] ?? $default;
    }
}
