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
use ZK\Engine\IEditor;
use ZK\Engine\ILibrary;

use Enm\JsonApi\Exception\BadRequestException;
use Enm\JsonApi\Exception\JsonApiException;
use Enm\JsonApi\Exception\ResourceNotFoundException;
use Enm\JsonApi\Model\Document\Document;
use Enm\JsonApi\Model\Request\RequestInterface;
use Enm\JsonApi\Model\Resource\JsonResource;
use Enm\JsonApi\Model\Resource\Link\Link;
use Enm\JsonApi\Model\Response\CreatedResponse;
use Enm\JsonApi\Model\Response\DocumentResponse;
use Enm\JsonApi\Model\Response\EmptyResponse;
use Enm\JsonApi\Model\Response\ResponseInterface;
use Enm\JsonApi\Server\RequestHandler\NoRelationshipFetchTrait;
use Enm\JsonApi\Server\RequestHandler\NoRelationshipModificationTrait;
use Enm\JsonApi\Server\RequestHandler\RequestHandlerInterface;


class Labels implements RequestHandlerInterface {
    use OffsetPaginationTrait;
    use NoRelationshipFetchTrait;
    use NoRelationshipModificationTrait;

    const FIELDS = [ "name", "attention", "address", "city", "state",
                     "zip", "phone", "fax", "mailcount", "maillist",
                     "international", "pcreated", "modified", "url",
                     "email" ];

    private static $paginateOps = [
        "name" => [ ILibrary::LABEL_NAME, null ],
        "match(name)" => [ -1, "labels" ],
    ];

    public static function fromRecord($rec) {
        $res = new JsonResource("label", $rec["pubkey"]);
        $res->links()->set(new Link("self", Engine::getBaseUrl()."label/".$rec["pubkey"]));
        foreach(self::FIELDS as $field) {
            if(!key_exists($field, $rec))
                continue;

            switch($field) {
            case "international":
                $value = $rec[$field] == 'T';
                break;
            default:
                $value = $rec[$field];
                break;
            }
            $res->attributes()->set($field, $value);
        }
        return $res;
    }

    public static function fromAttrs($attrs) {
        $label = [];

        foreach(Labels::FIELDS as $field)
            if($attrs->has($field))
                $label[$field] = $attrs->getOptional($field);

        // normalize label fields
        foreach(["name","attention","address","city","maillist"] as $field) {
            if(isset($label[$field]))
                $label[$field] = Albums::zkAlpha($label[$field], true);
        }

        return $label;
    }

    public function fetchResource(RequestInterface $request): ResponseInterface {
        $key = $request->id();
        $labels = Engine::api(ILibrary::class)->search(ILibrary::LABEL_PUBKEY, 0, 1, $key);
        if(sizeof($labels) == 0)
            throw new ResourceNotFoundException("label", $key);

        $label = $labels[0];
        $resource = self::fromRecord($label);

        $document = new Document($resource);

        $response = new DocumentResponse($document);
        return $response;
    }

    protected function paginateCursor(RequestInterface $request): ResponseInterface {
        // pagination according to the cursor pagination profile
        // https://jsonapi.org/profiles/ethanresnick/cursor-pagination/

        if($request->hasFilter("name")) {
            $op = ILibrary::OP_BY_NAME;
            $key = $request->filterValue("name");
        } else if($request->hasFilter("album.id")) {
            $op = ILibrary::OP_BY_TAG;
            $key = $request->filterValue("album.id");
        } else if($request->hasPagination("before")) {
            // previous page
            $op = ILibrary::OP_PREV_PAGE;
            $key = $request->paginationValue("before");
        } else if($request->hasPagination("after")) {
            // next page
            $op = ILibrary::OP_NEXT_PAGE;
            $key = $request->paginationValue("after");
        } else if($request->hasPagination("previous")) {
            // previous line
            $op = ILibrary::OP_PREV_LINE;
            $key = $request->paginationValue("previous");
        } else if($request->hasPagination("next")) {
            // next line
            $op = ILibrary::OP_NEXT_LINE;
            $key = $request->paginationValue("next");
        } else
            throw new BadRequestException("must specify filter or page");

        $limit = $request->hasPagination("size") ?
                min($request->paginationValue("size"), ApiServer::MAX_LIMIT) :
                ApiServer::DEFAULT_LIMIT;

        $records = Engine::api(ILibrary::class)->listLabels($op, $key, $limit);
        $result = [];
        foreach($records as $record) {
            $resource = self::fromRecord($record);
            $result[] = $resource;
        }

        $document = new Document($result);

        $base = Engine::getBaseUrl()."label?";
        $size = "&page%5Bprofile%5D=cursor&page%5Bsize%5D=$limit";

        $obj = $records[0];
        $prev = urlencode("{$obj["name"]}|{$obj["pubkey"]}");
        $document->links()->set(new Link("prev", "{$base}page%5Bbefore%5D={$prev}{$size}"));
        $document->links()->set(new Link("prevLine", "{$base}page%5Bprevious%5D={$prev}{$size}"));
        $document->links()->set(new Link("nextLine", "{$base}page%5Bnext%5D={$prev}{$size}"));

        $obj = $records[sizeof($records)-1];
        $next = urlencode("{$obj["name"]}|{$obj["pubkey"]}");
        $document->links()->set(new Link("next", "{$base}page%5Bafter%5D={$next}{$size}"));

        $response = new DocumentResponse($document);
        return $response;
    }

    public function fetchResources(RequestInterface $request): ResponseInterface {
        $profile = $request->hasPagination("profile")?
                   $request->paginationValue("profile"):"offset";
        switch($profile) {
        case "cursor":
            $response = $this->paginateCursor($request);
            break;
        case "offset":
            $response = $this->paginateOffset($request, self::$paginateOps, 0);
            break;
        default:
            throw new BadRequestException("unknown pagination profile '{$profile}'");
        }

        return $response;
    }

    public function createResource(RequestInterface $request): ResponseInterface {
        if(!Engine::session()->isAuth("m"))
            throw new AuthenticationException("Operation requires authentication");

        $lr = $request->requestBody()->data()->first("label");
        $attrs = $lr->attributes();

        // try to find by name
        $name = Albums::zkAlpha($attrs->getRequired("name"), true);
        $rec = Engine::api(ILibrary::class)->search(ILibrary::LABEL_NAME, 0, 1, $name);
        if(sizeof($rec))
            throw new JsonApiException("label with this name already exists");

        $label = self::fromAttrs($attrs);
        $label["pubkey"] = 0;
        $label["foreign"] = $label["international"] ?? false;

        if(Engine::api(IEditor::class)->insertUpdateLabel($label))
            return new CreatedResponse(Engine::getBaseUrl()."label/{$label['pubkey']}");

        throw new \Exception("creation failed");
    }

    public function patchResource(RequestInterface $request): ResponseInterface {
        if(!Engine::session()->isAuth("m"))
            throw new AuthenticationException("Operation requires authentication");

        $key = $request->id();
        if(empty($key))
            throw new BadRequestException("must specify id");

        $rec = Engine::api(ILibrary::class)->search(ILibrary::LABEL_PUBKEY, 0, 1, $key);
        if(sizeof($rec) == 0)
            throw new ResourceNotFoundException("label", $key);
        $label = $rec[0];

        $lr = $request->requestBody()->data()->first("label");
        $attrs = $lr->attributes();

        if($attrs->has("name")) {
            // check for duplicate name
            $name = Albums::zkAlpha($attrs->getRequired("name"), true);
            $alt = Engine::api(ILibrary::class)->search(ILibrary::LABEL_NAME, 0, 10, $name);
            if(sizeof($alt) > 1 || sizeof($alt) && $alt[0]["pubkey"] != $key)
                throw new JsonApiException("label with this name already exists");
        }

        $label = array_merge($label, self::fromAttrs($attrs));
        $label["foreign"] = $label["international"];

        if(Engine::api(IEditor::class)->insertUpdateLabel($label))
            return new EmptyResponse();

        throw new \Exception("update failed");
    }

    public function deleteResource(RequestInterface $request): ResponseInterface {
        if(!Engine::session()->isAuth("m"))
            throw new AuthenticationException("Operation requires authentication");

        $key = $request->id();
        if(empty($key))
            throw new BadRequestException("must specify id");

        Engine::api(IEditor::class)->deleteLabel($key);

        return new EmptyResponse();
    }
}
