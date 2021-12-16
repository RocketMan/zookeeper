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

use ZK\Engine\Engine;
use ZK\Engine\ILibrary;
use ZK\Engine\IReview;

use Enm\JsonApi\Exception\NotAllowedException;
use Enm\JsonApi\Exception\ResourceNotFoundException;
use Enm\JsonApi\Model\Document\Document;
use Enm\JsonApi\Model\Document\DocumentInterface;
use Enm\JsonApi\Model\Request\RequestInterface;
use Enm\JsonApi\Model\Resource\JsonResource;
use Enm\JsonApi\Model\Resource\Link\Link;
use Enm\JsonApi\Model\Resource\Relationship\Relationship;
use Enm\JsonApi\Model\Response\DocumentResponse;
use Enm\JsonApi\Model\Response\ResponseInterface;
use Enm\JsonApi\Server\RequestHandler\NoRelationshipModificationTrait;
use Enm\JsonApi\Server\RequestHandler\RequestHandlerInterface;


class Reviews implements RequestHandlerInterface {
    use NoRelationshipModificationTrait;

    const FIELDS = [ "airname", "date", "review" ];
                     
    public static function fromRecord($rec) {
        $res = new JsonResource("review", $rec["id"]);
        $res->links()->set(new Link("self", Engine::getBaseUrl()."/review/".$rec["id"]));
        foreach(self::FIELDS as $field) {
            switch($field) {
            case "date":
                $value = substr($rec["created"], 0, 10);
                break;
            default:
                $value = $rec[$field];
                break;
            }
            $res->attributes()->set($field, $value);
        }
        return $res;
    }
    
    public function fetchResource(RequestInterface $request): ResponseInterface {
        $key = $request->id();
        $reviews = Engine::api(IReview::class)->getReviews($key, 1, "", Engine::session()->isAuth("u"), 1);

        if(sizeof($reviews) == 0)
            throw new ResourceNotFoundException("review", $key);
            
        $resource = self::fromRecord($reviews[0]);

        $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $reviews[0]['tag']);
        if(sizeof($albums)) {
            $res = Albums::fromRecord($albums[0]);
            $relation = new Relationship("album", $res);
            $relation->links()->set(new Link("related", Engine::getBaseUrl()."/review/$key/album"));
            $relation->links()->set(new Link("self", Engine::getBaseUrl()."/review/$key/relationships/album"));
            $resource->relationships()->set($relation);
        }

        $document = new Document($resource);

        $response = new DocumentResponse($document);
        return $response;
    }

    public function fetchResources(RequestInterface $request): ResponseInterface {
        // TBD add filter-based album retrieval
        throw new NotAllowedException("review fetch by id only");
    }

    public function fetchRelationship(RequestInterface $request): ResponseInterface {
        $key = $request->id();
        $reviews = Engine::api(IReview::class)->getReviews($key, 1, "", Engine::session()->isAuth("u"), 1);

        if(sizeof($reviews) == 0)
            throw new ResourceNotFoundException("review", $key);

        switch($request->relationship()) {
        case "album":
            $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $reviews[0]['tag']);
            if(sizeof($albums))
                $res = Albums::fromRecord($albums[0]);
            break;
        case "relationships":
            throw new NotAllowedException("unspecified relationship");
        default:
            throw new ResourceNotFoundException("review", $request->relationship());
        }        

        $document = new Document($res);
        $document->links()->set(new Link("self", Engine::getBaseUrl()."/review/$key/relationships/".$request->relationship()));
        $document->links()->set(new Link("related", Engine::getBaseUrl()."/review/$key/".$request->relationship()));

        $response = new DocumentResponse($document);
        return $response;
    }

    public function createResource(RequestInterface $request): ResponseInterface {
        throw new NotAllowedException("TBD");
    }
    public function patchResource(RequestInterface $request): ResponseInterface {
        throw new NotAllowedException("TBD");
    }

    public function deleteResource(RequestInterface $request): ResponseInterface {
        throw new NotAllowedException("TBD");
    }
}
