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

namespace ZK\API;

use ZK\Engine\Request;

class ClientRequest {
    protected array $typeMap;

    public function __construct(
        LazyApiConfig $config,
        protected Request $request,
    ) {
        $this->typeMap = array_flip($config->getApiConfig()->asArray());
    }

    /**
     * return the API version of the current request
     *
     * @return float api version
     */
    public function getApiVer(): float {
        return (float)$this->request->getAttribute('version', 1);
    }

    /**
     * return base url for the specified handler
     *
     * If the current request targets this handler, the request's api
     * version is used; if the request targets any other handler,
     * then the handler's default version is used.
     *
     * If handler is omitted or null, the request's api version is used.
     *
     * @param string|null $handler (default null)
     * @return string base url
     */
    public function getBaseUrl(?string $handler = null): string {
        $url = $this->request->getBaseUrl();
        if (is_null($handler))
            return $url;

        $type = $this->typeMap[$handler];
        $requestType = $this->request->getAttribute('type');

        if ($type === $requestType)
            return $url;

        $pattern = $this->request->getAttribute('versionRegexp');
        return preg_replace($pattern, $handler::API_VERSION, $url);
    }

    /**
     * get the base path of the request
     *
     * this excludes any request-specific path components
     *
     * @return string base path
     */
    public function getAppBasePath(): string {
        return $this->request->getAppBasePath();
    }

    /**
     * get the specified query string parameter
     *
     * this method correctly observes dots in parameter name
     *
     * @param string $key parameter name
     * @param string $default value if no such parameter (default null)
     * @return string|null parameter value or default
     */
    public function getQueryParam(string $key, ?string $default = null): ?string {
        return $this->request->getQueryParam($key, $default);
    }
}