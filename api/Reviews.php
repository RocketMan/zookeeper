<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2022 Jim Mason <jmason@ibinx.com>
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
use Enm\JsonApi\Server\RequestHandler\NoResourceFetchTrait;
use Enm\JsonApi\Server\RequestHandler\RequestHandlerInterface;


class Reviews implements RequestHandlerInterface {
    use OffsetPaginationTrait;
    use NoRelationshipModificationTrait;

    const FIELDS = [ "airname", "published", "date", "review" ];

    const LINKS_NONE = 0;
    const LINKS_ALBUM = 1;
    const LINKS_ALBUM_TRACKS = 2;
    const LINKS_REVIEW_BODY = 4;
    const LINKS_ALL = ~0;

    private static $paginateOps = [
        "match(review)" => [ -1, "reviews" ],
    ];

    public static function fromRecord($rec) {
        $res = new JsonResource("review", $rec["id"]);
        $res->links()->set(new Link("self", Engine::getBaseUrl()."review/".$rec["id"]));
        foreach(self::FIELDS as $field) {
            switch($field) {
            case "date":
                $value = substr($rec["reviewed"], 0, 10);
                break;
            case "airname":
                $value = $rec["airname"] ?? $rec["realname"];
                break;
            case "published":
                $value = !$rec["private"];
                break;
            default:
                $value = $rec[$field];
                break;
            }
            $res->attributes()->set($field, $value);
        }
        return $res;
    }

    public static function fromArray(array $records, $flags = self::LINKS_NONE) {
        $result = [];
        $wantsAlbum = $flags & self::LINKS_ALBUM;
        $wantsReview = $flags & self::LINKS_REVIEW_BODY;
        foreach($records as $record) {
            if(empty($record["review"]) && $wantsReview) {
                $reviews = Engine::api(IReview::class)->getReviews($record["id"], 1, "", Engine::session()->isAuth("u"), 1);
                $record["review"] = $reviews[0]["review"];
            }
            $resource = self::fromRecord($record);
            $result[] = $resource;

            if($wantsAlbum) {
                // full text reviews album info is incomplete;
                // fetch if the caller has requested it
                $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $record["tag"]);
                $aflags = Albums::LINKS_LABEL;
                if($flags & self::LINKS_ALBUM_TRACKS)
                    $aflags |= Albums::LINKS_TRACKS;
                $res = Albums::fromArray($albums, $aflags)[0];
            } else
                $res = Albums::fromRecord($record, false);

            $relation = new Relationship("album", $res);
            $relation->links()->set(new Link("related", Engine::getBaseUrl()."review/{$record["id"]}/album"));
            $relation->links()->set(new Link("self", Engine::getBaseUrl()."review/{$record["id"]}/relationships/album"));
            $relation->metaInformation()->set("album", $res->attributes()->getOptional("album"));
            $relation->metaInformation()->set("artist", $res->attributes()->getOptional("artist"));
            $resource->relationships()->set($relation);
        }
        return $result;
    }

    public function fetchResource(RequestInterface $request): ResponseInterface {
        $key = $request->id();
        $reviews = Engine::api(IReview::class)->getReviews($key, 1, "", Engine::session()->isAuth("u"), 1);

        if(sizeof($reviews) == 0)
            throw new ResourceNotFoundException("review", $key);

        $flags = self::LINKS_ALBUM;
        if($request->requestsInclude("album") &&
                $request->requestsField("album", "tracks"))
            $flags |= self::LINKS_ALBUM_TRACKS;

        $resource = self::fromArray($reviews, $flags)[0];

        $document = new Document($resource);

        $response = new DocumentResponse($document);
        return $response;
    }

    public function fetchResources(RequestInterface $request): ResponseInterface {
        $flags = self::LINKS_NONE;
        if($request->requestsInclude("album"))
            $flags |= self::LINKS_ALBUM;
        if($request->requestsField("album", "tracks"))
            $flags |= self::LINKS_ALBUM_TRACKS;
        if($request->requestsField("review", "review"))
            $flags |= self::LINKS_REVIEW_BODY;
        return $this->paginateOffset($request, self::$paginateOps, $flags);
    }

    public function fetchRelationship(RequestInterface $request): ResponseInterface {
        $key = $request->id();
        $reviews = Engine::api(IReview::class)->getReviews($key, 1, "", Engine::session()->isAuth("u"), 1);

        if(sizeof($reviews) == 0)
            throw new ResourceNotFoundException("review", $key);

        switch($request->relationship()) {
        case "album":
            $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $reviews[0]['tag']);
            if(sizeof($albums)) {
                $aflags = Albums::LINKS_LABEL;
                if($request->requestsField("album", "tracks"))
                    $aflags |= Albums::LINKS_TRACKS;
                $res = Albums::fromArray($albums, $aflags)[0];
            }
            break;
        case "relationships":
            throw new NotAllowedException("unspecified relationship");
        default:
            throw new NotAllowedException('You are not allowed to fetch the relationship ' . $request->relationship());
        }        

        $document = new Document($res);
        if($request->requestsAttributes())
            $document->links()->set(new Link("self", Engine::getBaseUrl()."review/$key/".$request->relationship()));
        else {
            $document->links()->set(new Link("self", Engine::getBaseUrl()."review/$key/relationships/".$request->relationship()));
            $document->links()->set(new Link("related", Engine::getBaseUrl()."review/$key/".$request->relationship()));
        }

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

        $private = $attrs->getOptional("published", true) ? 0 : 1;
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

        $private = $attrs->getOptional("published", true) ? 0 : 1;
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

        $revapi->deleteReview($reviews[0]["tag"], $user);

        return new EmptyResponse();
    }
}
