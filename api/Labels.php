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

use Enm\JsonApi\Exception\NotAllowedException;
use Enm\JsonApi\Exception\ResourceNotFoundException;
use Enm\JsonApi\Model\Document\Document;
use Enm\JsonApi\Model\Request\RequestInterface;
use Enm\JsonApi\Model\Resource\JsonResource;
use Enm\JsonApi\Model\Resource\Link\Link;
use Enm\JsonApi\Model\Response\DocumentResponse;
use Enm\JsonApi\Model\Response\ResponseInterface;
use Enm\JsonApi\Server\RequestHandler\NoRelationshipFetchTrait;
use Enm\JsonApi\Server\RequestHandler\NoRelationshipModificationTrait;
use Enm\JsonApi\Server\RequestHandler\NoResourceDeletionTrait;
use Enm\JsonApi\Server\RequestHandler\RequestHandlerInterface;


class Labels implements RequestHandlerInterface {
    use NoRelationshipFetchTrait;
    use NoRelationshipModificationTrait;
    use NoResourceDeletionTrait;

    const FIELDS = [ "name", "attention", "address", "city", "state",
                     "zip", "phone", "fax", "mailcount", "maillist",
		     "international", "pcreated", "modified", "url",
		     "email" ];
		     
    public static function fromRecord($rec) {
        $res = new JsonResource("label", $rec["pubkey"]);
	$res->links()->set(new Link("self", Engine::getBaseUrl()."/label/".$rec["pubkey"]));
	foreach(self::FIELDS as $field)
	    $res->attributes()->set($field, $rec[$field]);
	return $res;
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

    public function fetchResources(RequestInterface $request): ResponseInterface {
        // TBD add filter-based label retrieval
        throw new NotAllowedException("label fetch by id only");
    }

    public function createResource(RequestInterface $request): ResponseInterface {
        throw new NotAllowedException("TBD");
    }

    public function patchResource(RequestInterface $request): ResponseInterface {
        throw new NotAllowedException("TBD");
    }
}
