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
use ZK\Engine\IDJ;
use ZK\Engine\ILibrary;
use ZK\Engine\IReview;

use Enm\JsonApi\Exception\NotAllowedException;
use Enm\JsonApi\Exception\ResourceNotFoundException;
use Enm\JsonApi\Model\Document\Document;
use Enm\JsonApi\Model\Document\DocumentInterface;
use Enm\JsonApi\Model\Request\RequestInterface;
use Enm\JsonApi\Model\Response\CreatedResponse;
use Enm\JsonApi\Model\Resource\JsonResource;
use Enm\JsonApi\Model\Resource\Link\Link;
use Enm\JsonApi\Model\Resource\Relationship\Relationship;
use Enm\JsonApi\Model\Response\DocumentResponse;
use Enm\JsonApi\Model\Response\EmptyResponse;
use Enm\JsonApi\Model\Response\ResponseInterface;
use Enm\JsonApi\Server\RequestHandler\NoRelationshipModificationTrait;
use Enm\JsonApi\Server\RequestHandler\RequestHandlerInterface;


class Reviews implements RequestHandlerInterface {
    use NoRelationshipModificationTrait;

    const FIELDS = [ "airname", "date", "review" ];

    public static function fromRecord($rec) {
        $res = new JsonResource("review", $rec["id"]);
        $res->links()->set(new Link("self", Engine::getBaseUrl()."review/".$rec["id"]));
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
            $relation->links()->set(new Link("related", Engine::getBaseUrl()."review/$key/album"));
            $relation->links()->set(new Link("self", Engine::getBaseUrl()."review/$key/relationships/album"));
            $resource->relationships()->set($relation);
        }

        $document = new Document($resource);

        $response = new DocumentResponse($document);
        return $response;
    }

    public function fetchResources(RequestInterface $request): ResponseInterface {
        if($request->hasFilter("airname.id")) {
            $op = ILibrary::ALBUM_AIRNAME;
            $key = $request->filterValue("airname.id");
            $filter = "filter%5Bairname.id%5D=" . urlencode($key);
        } else
            throw new NotAllowedException("must specify filter");

        $reqOffset = $offset = $request->hasPagination("offset")?
                $request->paginationValue("offset"):0;

        $limit = $request->hasPagination("size")?
                $request->paginationValue("size"):null;
        if(!$limit || $limit > API::MAX_LIMIT)
            $limit = API::MAX_LIMIT;

        $sort = $_GET["sort"] ?? "";

        $libraryAPI = Engine::api(ILibrary::class);
        $total = (int)$libraryAPI->searchPos($op, $offset, -1, $key);
        $records = $libraryAPI->searchPos($op, $offset, $limit, $key, $sort);
        $result = [];
        foreach($records as $record) {
            $resource = self::fromRecord($record);
            $result[] = $resource;

            // ILibrary::ALBUM_AIRNAME returns album details in the result
            $res = Albums::fromRecord($record);
            $relation = new Relationship("album", $res);
            $relation->links()->set(new Link("related", Engine::getBaseUrl()."review/{$record["id"]}/album"));
            $relation->links()->set(new Link("self", Engine::getBaseUrl()."review/{$record["id"]}/relationships/album"));
            $resource->relationships()->set($relation);

            $label = new JsonResource("label", $record["pubkey"]);
            $label->links()->set(new Link("self", Engine::getBaseUrl()."label/".$record["pubkey"]));
            $relation = new Relationship("label", $label);
            $relation->links()->set(new Link("related", Engine::getBaseUrl()."album/{$record["tag"]}/label"));
            $relation->links()->set(new Link("self", Engine::getBaseUrl()."album/{$record["tag"]}/relationships/label"));
            $relation->metaInformation()->set("name", $record["name"]);
            $res->relationships()->set($relation);
        }
        $document = new Document($result);

        $base = Engine::getBaseUrl()."review?{$filter}";
        $size = "&page%5Bprofile%5D=offset&page%5Bsize%5D=$limit";

        if($offset)
            $document->links()->set(new Link("next", "{$base}&page%5Boffset%5D={$offset}{$size}"));
        else
            $offset = $total; // no more rows remaining

        $link = new Link("first", "{$base}{$size}");
        $link->metaInformation()->set("total", $total);
        $link->metaInformation()->set("more", $reqOffset?$total:($total - $offset));
        $link->metaInformation()->set("offset", (int)$reqOffset);
        $document->links()->set($link);

        $response = new DocumentResponse($document);
        return $response;
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
                $res = Albums::fromArray($albums, Albums::LINKS_ALL)[0];
            break;
        case "relationships":
            throw new NotAllowedException("unspecified relationship");
        default:
            throw new ResourceNotFoundException("review", $request->relationship());
        }        

        $document = new Document($res);
        $document->links()->set(new Link("self", Engine::getBaseUrl()."review/$key/relationships/".$request->relationship()));
        $document->links()->set(new Link("related", Engine::getBaseUrl()."review/$key/".$request->relationship()));

        $response = new DocumentResponse($document);
        return $response;
    }

    public function createResource(RequestInterface $request): ResponseInterface {
        $review = $request->requestBody()->data()->first("review");
        $tag = $review->relationships()->get("album")->related()->first("album")->id();
        $album = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $tag);
        if(sizeof($album) == 0)
            throw new ResourceNotFoundException("album", $tag);

        $djapi = Engine::api(IDJ::class);
        $user = Engine::session()->getUser();
        $attrs = $review->attributes();
        $an = $attrs->getRequired("airname");
        $airname = $djapi->getAirname($an);
        if(!$airname) {
            // airname does not exist; try to create it
            $success = $djapi->insertAirname(mb_substr($an, 0, IDJ::MAX_AIRNAME_LENGTH), $user);
            if(!$success)
                throw new NotAllowedException("Cannot create airname $an");

            $airname = $djapi->lastInsertId();
        }

        $private = $attrs->getOptional("private", false);
        $review = $attrs->getRequired("review");

        $revapi = Engine::api(IReview::class);
        $reviews = $revapi->getReviews($tag, 1, $user, 0);
        if(sizeof($reviews))
            throw new NotAllowedException("review already exists, use PATCH");

        if($revapi->insertReview($tag, $private, $airname, $review, $user))
            return new CreatedResponse(Engine::getBaseUrl()."review/{$revapi->lastInsertId()}");

        throw new \Exception("creation failed");
    }
    public function patchResource(RequestInterface $request): ResponseInterface {
        $review = $request->requestBody()->data()->first("review");
        $tag = $review->relationships()->get("album")->related()->first("album")->id();
        $album = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $tag);
        if(sizeof($album) == 0)
            throw new ResourceNotFoundException("album", $tag);

        $djapi = Engine::api(IDJ::class);
        $user = Engine::session()->getUser();
        $attrs = $review->attributes();
        $an = $attrs->getRequired("airname");
        $airname = $djapi->getAirname($an);
        if(!$airname) {
            // airname does not exist; try to create it
            $success = $djapi->insertAirname(mb_substr($an, 0, IDJ::MAX_AIRNAME_LENGTH), $user);
            if(!$success)
                throw new NotAllowedException("Cannot create airname $an");

            $airname = $djapi->lastInsertId();
        }

        $private = $attrs->getOptional("private", false);
        $review = $attrs->getRequired("review");

        $revapi = Engine::api(IReview::class);
        $reviews = $revapi->getReviews($tag, 1, $user, 0);
        if(!sizeof($reviews))
            throw new NotAllowedException("review does not exist, use POST");

        if($revapi->updateReview($tag, $private, $airname, $review, $user))
            return new EmptyResponse();

        throw new \Exception("update failed");
    }

    public function deleteResource(RequestInterface $request): ResponseInterface {
        $session = Engine::session();
        if(!$session->isAuth("u"))
            throw new NotAllowedException("Operation requires authentication");

        $key = $request->id();
        $revapi = Engine::api(IReview::class);
        $reviews = $revapi->getReviews($key, 1, "", Engine::session()->isAuth("u"), 1);

        if(sizeof($reviews) == 0)
            throw new ResourceNotFoundException("review", $key);

        $user = $session->getUser();
        if($user != $reviews[0]["user"])
            throw new NotAllowedException("only review owner may delete");

        $revapi->deleteReview($key, $user);

        return new EmptyResponse();
    }
}
