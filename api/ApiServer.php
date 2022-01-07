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

namespace ZK\API;

use Enm\JsonApi\Model\Document\DocumentInterface;
use Enm\JsonApi\Model\Request\RequestInterface;
use Enm\JsonApi\Model\Resource\ResourceInterface;
use Enm\JsonApi\Model\Response\EmptyResponse;
use Enm\JsonApi\Model\Response\ResponseInterface;
use Enm\JsonApi\Server\JsonApiServer;

/**
 * Zookeeper custom implementation of JsonApiServer
 */
class ApiServer extends JsonApiServer {
    const MAX_LIMIT = 35;

    const CORS_METHODS = "GET, HEAD, POST, PATCH, DELETE";
    const CORS_MAX_AGE = 3600;

    /**
     * determine if the specified ORIGIN is allowed for CORS requests
     *
     * default implementation always indicates not allowed
     *
     * @param $origin target ORIGIN
     * @return true if allowed, false otherwise
     */
    function originAllowed(string $origin): bool { return false; }

    /**
     * This fixes a bug in the upstream handling of include for
     * resource collections.
     *
     * See https://github.com/eosnewmedia/JSON-API-Common/issues/27
     */
    protected function includeRelated(
        DocumentInterface $document,
        ResourceInterface $resource,
        RequestInterface $request
    ): void {
        foreach ($resource->relationships()->all() as $relationship) {
            $shouldIncludeRelationship = $request->requestsInclude($relationship->name());
            $subRequest = $request->createSubRequest($relationship->name(), $resource);
            foreach ($relationship->related()->all() as $related) {
                if ($shouldIncludeRelationship) {
                    $document->included()->set($related);
                    $this->cleanUpResource($document->included()->get($related->type(), $related->id()), $subRequest);
                }
                $this->includeRelated($document, $related, $subRequest);
            }
        }
    }

    /**
     * add support for fields negation (e.g., fields[resource]=-notWanted)
     */
    protected function cleanUpResource(
        ResourceInterface $resource,
        RequestInterface $request
    ): void {
        // If no fields have been requested for a given type, then
        // `requestsField` will return true for any candidate name.
        // This means it will tell us there are negations even if none.
        //
        // To detect this case, we first test an unlikely field name.
        // If it succeeds, we know that no fields have been requested.
        if ($request->requestsAttributes() &&
                !$request->requestsField($resource->type(), "-#&*(Q@")) {
            // There is a field filter, so we can trust `requestsField`
            $negation = false;
            foreach ($resource->attributes()->all() as $key => $value) {
                if ($request->requestsField($resource->type(), "-" . $key)) {
                    $resource->attributes()->remove($key);
                    $negation = true;
                }
            }

            if ($negation) {
                // There was one or more negation, finish the clean-up
                if (!$request->requestsRelationships()) {
                    foreach ($resource->relationships()->all() as $relationship)
                        $resource->relationships()->removeElement($relationship);
                }
                return;
            }
        }

        // in all other cases, delegate to the superclass
        parent::cleanUpResource($resource, $request);
    }

    /**
     * add support for CORS
     */
    public function handleRequest(
        RequestInterface $request
    ): ResponseInterface {
        switch($request->method()) {
        case "OPTIONS":
            // CORS preflight
            $response = new EmptyResponse();
            $response->headers()->set("Access-Control-Allow-Methods", self::CORS_METHODS);
            $response->headers()->set("Access-Control-Max-Age", self::CORS_MAX_AGE);
            break;
        default:
            $response = parent::handleRequest($request);
            break;
        }

        if($request->headers()->has('HTTP_ORIGIN') &&
                $this->originAllowed($origin = $request->headers()->getRequired('HTTP_ORIGIN'))) {
            $response->headers()->set("Access-Control-Allow-Origin", $origin);
            $response->headers()->set("Access-Control-Allow-Credentials", "true");
        }

        return $response;
    }
}
