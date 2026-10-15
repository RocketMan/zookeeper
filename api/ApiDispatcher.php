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
use ZK\Engine\Zookeeper;

use DI\Container;
use DI\ContainerBuilder;
use GuzzleHttp\Psr7\Uri;
use Psr\Log\LoggerInterface;

class ApiDispatcher {
    private const CORS_METHODS = "GET, HEAD, POST, PATCH, DELETE";
    private const CORS_MAX_AGE = 3600;

    public function __construct(
        protected LazyApiConfig $config,
        protected Container $container,
        protected Request $request,
    ) {}

    protected function isPreflight() {
        $preflight = $this->request->getMethod() == "OPTIONS";
        if ($preflight)
            http_response_code(204); // 204 No Content

        $origin = $this->request->getServerParam('HTTP_ORIGIN');
        if ($origin) {
            foreach ($this->config->getAppConfig()->get('allowed_domains') as $domain) {
                if (preg_match("/" . preg_quote($domain) . "$/", $origin)) {
                    header("Access-Control-Allow-Origin: $origin");
                    header("Access-Control-Allow-Credentials: true");
                    break;
                }
            }

            if ($preflight) {
                header("Access-Control-Allow-Methods: " . self::CORS_METHODS);
                header("Access-Control-Max-Age: " . self::CORS_MAX_AGE);
            }
        }

        return $preflight;
    }

    protected function serveRequest() {
        $apiServer = new ApiServer(new XADeserializer(), new XASerializer());

        $apiConfig = $this->config->getApiConfig();

        $apiConfig->iterate(fn($type, $handler) =>
            $apiServer->addHandler($type, $this->container->get($handler)));

        try {
            // Construct the uri prefix manually, as Request's api prefix
            // removal is broken for multilevel prefixes.
            $uri = $this->request->getAttribute('path')
                    . '?' . $this->request->getUri()->getQuery();

            $request = new ApiRequest(
                $this->request->getMethod(),
                new Uri($uri),
                $apiServer->createRequestBody($this->request->getBody()),
                null);

            $response = $apiServer->handleRequest($request);
        } catch(\Exception $e) {
            $response = $apiServer->handleException($e);
        }

        header("HTTP/1.1 " . $response->status());
        header("X-Powered-By: " . Zookeeper::UA);
        foreach ($response->headers()->all() as $header => $value)
            header("$header: $value");

        ob_start("ob_gzhandler");
        echo $apiServer->createResponseBody($response);
        ob_end_flush();
    }

    public function processRequest() {
        if (!$this->isPreflight())
            $this->serveRequest();
    }
}
