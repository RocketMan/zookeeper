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

require_once __DIR__."/../vendor/autoload.php";

use ZK\API\ApiRequest;
use ZK\API\ApiServer;
use ZK\Engine\Config;
use ZK\Engine\Engine;

use Enm\JsonApi\Serializer\Deserializer;
use Enm\JsonApi\Serializer\Serializer;
use GuzzleHttp\Psr7\Uri;

const CORS_METHODS = "GET, HEAD, POST, PATCH, DELETE";

function isPreflight() {
    $preflight = ($_SERVER['REQUEST_METHOD'] ?? null) == "OPTIONS";
    if($preflight)
        http_response_code(204); // 204 No Content

    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    if($origin) {
        foreach(Engine::param('allowed_domains') as $domain) {
            if(preg_match("/" . preg_quote($domain) . "$/", $origin)) {
                header("Access-Control-Allow-Origin: {$origin}");
                header("Access-Control-Allow-Credentials: true");
                break;
            }
        }

        if($preflight) {
            header("Access-Control-Allow-Methods: " . CORS_METHODS);
            header("Access-Control-Max-Age: 3600");
        }
    }

    return $preflight;
}

function serveRequest() {
    $jsonApi = new ApiServer(new Deserializer(), new Serializer());

    $config = new Config('controller_config', 'apiControllers');
    $config->iterate(function($type, $handler) use($jsonApi) {
        $jsonApi->addHandler($type, new $handler());
    });

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
            $jsonApi->createRequestBody(file_get_contents('php://input')),
            null);

        $response = $jsonApi->handleRequest($request);
    } catch(\Exception $e) {
        $response = $jsonApi->handleException($e);
    }

    //header("HTTP/1.1 ".$response->status());
    foreach($response->headers()->all() as $header => $value)
        header("{$header}: {$value}");

    ob_start("ob_gzhandler");
    echo $jsonApi->createResponseBody($response);
    ob_end_flush();
}

// BEGIN MAINLINE
if(!isPreflight())
    serveRequest();
