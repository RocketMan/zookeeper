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

namespace ZK\API;

use ZK\Engine\Engine;
use ZK\Engine\ILibrary;

use Enm\JsonApi\Exception\BadRequestException;
use Enm\JsonApi\Model\Document\Document;
use Enm\JsonApi\Model\Request\RequestInterface;
use Enm\JsonApi\Model\Resource\JsonResource;
use Enm\JsonApi\Model\Resource\Link\Link;
use Enm\JsonApi\Model\Resource\Relationship\Relationship;
use Enm\JsonApi\Model\Response\DocumentResponse;
use Enm\JsonApi\Model\Response\ResponseInterface;
use Enm\JsonApi\Server\RequestHandler\NoRelationshipFetchTrait;
use Enm\JsonApi\Server\RequestHandler\NoRelationshipModificationTrait;
use Enm\JsonApi\Server\RequestHandler\NoResourceDeletionTrait;
use Enm\JsonApi\Server\RequestHandler\NoResourceFetchTrait;
use Enm\JsonApi\Server\RequestHandler\NoResourceModificationTrait;
use Enm\JsonApi\Server\RequestHandler\RequestHandlerInterface;

class UnifiedSearch implements RequestHandlerInterface {
    use OffsetPaginationTrait;
    use NoRelationshipFetchTrait;
    use NoRelationshipModificationTrait;
    use NoResourceDeletionTrait;
    use NoResourceFetchTrait;
    use NoResourceModificationTrait;

    // unused stub to hide OffsetPaginationTrait::fromArray
    public static function fromArray(array $records, $flags = 0) {
        return [];
    }

    public function fetchResources(RequestInterface $request): ResponseInterface {
        if($request->hasFilter("*")) {
            $key = $request->filterValue("*");
        } else
            throw new BadRequestException("Must specify filter.  May be one of: *");

        if(!Engine::session()->isAuth('C'))
            throw new BadRequestException("Operation requires challenge");

        $limit = $request->hasPagination("size") ?
                min($request->paginationValue("size"), ApiServer::MAX_LIMIT) :
                ApiServer::DEFAULT_LIMIT;

        $results = Engine::api(ILibrary::class)->searchFullText("", $key, $limit, "");
        $total = $results[0];
        $rres = [];
        foreach($results[1] as $result) {
            $more = $result["more"];
            $type = $result["type"];
            $records = $result["result"];

            $ctx = hash_init("crc32");
            hash_update($ctx, serialize($records));

            $res = new JsonResource($type, hash_final($ctx));
            $rres[] = $res;
            switch($type) {
            case "tags":
                // fall through...
            case "albums":
            case "artists":
                $related = Albums::fromArray($records, Albums::LINKS_LABEL);
                $rel = new Relationship("album", $related);
                $filter = $type == "tags" ? "album/" :
                            "album?filter%5Bmatch%28artist,album%29%5D=";
                break;
            case "labels":
                $related = Labels::fromArray($records);
                $rel = new Relationship("label", $related);
                $filter = "label?filter%5Bmatch%28name%29%5D=";
                break;
            case "playlists":
                $related = $this->fromPlaylistSearch($records);
                $rel = new Relationship("show", $related);
                $filter = "playlist?filter%5Bmatch%28event%29%5D=";
                break;
            case "reviews":
                $related = Reviews::fromArray($records);
                $rel = new Relationship("review", $related);
                $filter = "review?filter%5Bmatch%28review%29%5D=";
                break;
            case "compilations":
                // fall through...
            case "tracks":
                $related = $this->fromTrackSearch($records);
                $rel = new Relationship("album", $related);
                $filter = "album?filter%5Bmatch%28" .
                    ($type == "compilations" ? "artist," : "") .
                    "track%29%5D=";
                break;
            }

            $base = Engine::getBaseUrl().$filter.urlencode($key);
            $first = new Link("first", $base);
            $first->metaInformation()->set("total", $more + sizeof($records));
            $first->metaInformation()->set("more", $more);
            $first->metaInformation()->set("offset", 0);
            $res->links()->set($first);

            $res->relationships()->set($rel);
        }

        $document = new Document($rres);
        $base = Engine::getBaseUrl().$request->type()."?filter%5B%2A%5D=".
                urlencode($key)."&page%5Bsize%5D=".$limit;
        $first = new Link("first", $base);
        $first->metaInformation()->set("total", $total);
        $document->links()->set($first);
        return new DocumentResponse($document);
    }
}