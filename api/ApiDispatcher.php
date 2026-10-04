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

use ZK\Engine\Config;
use ZK\Engine\IConfig;
use ZK\Engine\PDO;
use ZK\Engine\PDOFactory;
use ZK\Engine\Zookeeper;

use DI\Container;
use DI\ContainerBuilder;
use GuzzleHttp\Psr7\Uri;

class ApiDispatcher {
    private const CORS_METHODS = "GET, HEAD, POST, PATCH, DELETE";
    private const CORS_MAX_AGE = 3600;

    private IConfig $config;
    private Container $container;

    public function __construct() {
        $this->config = new Config();

        $engineConfig = $this->config->withConfigFrom('engine_config');

        $builder = new ContainerBuilder();
        $builder->addDefinitions(array_map(
            fn($impl) => \DI\autowire($impl),
            $engineConfig->asArray()
        ));

        $builder->addDefinitions([
            IConfig::class => $this->config,
            PDO::class => \DI\factory(fn(PDOFactory $fact) => $fact->create()),
        ]);

        $this->container = $builder->build();
    }

    protected function isPreflight() {
        $preflight = ($_SERVER['REQUEST_METHOD'] ?? null) == "OPTIONS";
        if ($preflight)
            http_response_code(204); // 204 No Content

        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        if ($origin) {
            foreach ($this->config->get('allowed_domains') as $domain) {
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

        $config = $this->config->withConfigFrom('controller_config', 'apiControllers');
        $config->iterate(fn($type, $handler) =>
            $apiServer->addHandler($type, $this->container->get($handler)));

        try {
            // Remove the uri prefix manually, as Request's api prefix removal
            // is broken for multilevel prefixes.
            //
            // assert(strpos($_SERVER["REQUEST_URI"],
            //           $_SERVER["REDIRECT_PREFIX"]) === 0);
            $uri = substr($_SERVER["REQUEST_URI"],
                            strlen($_SERVER["REDIRECT_PREFIX"] ?? ""));

            $request = new ApiRequest(
                $_SERVER["REQUEST_METHOD"],
                new Uri($uri),
                $apiServer->createRequestBody(file_get_contents('php://input')),
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
