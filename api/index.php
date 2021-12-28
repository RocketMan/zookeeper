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

use ZK\API\ApiServer;
use ZK\Engine\Config;

use Enm\JsonApi\Model\Request\Request;
use Enm\JsonApi\Serializer\Deserializer;
use Enm\JsonApi\Serializer\Serializer;
use GuzzleHttp\Psr7\Uri;

$jsonApi = new ApiServer(new Deserializer(), new Serializer());

$config = new Config('controller_config', 'apiControllers');
$config->iterate(function($type, $handler) use($jsonApi) {
    $jsonApi->addHandler($type, new $handler());
});

try {
    // Remove the uri prefix manually, as Request's api prefix removal
    // is broken for multilevel prefixes.
    $uri = preg_replace("|^{$_SERVER["REDIRECT_PREFIX"]}|", "", $_SERVER["REQUEST_URI"]);

    $request = new Request(
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
