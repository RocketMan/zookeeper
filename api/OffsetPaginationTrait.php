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
use Enm\JsonApi\Model\Document\Document;
use Enm\JsonApi\Model\Request\RequestInterface;
use Enm\JsonApi\Model\Resource\Link\Link;
use Enm\JsonApi\Model\Response\DocumentResponse;
use Enm\JsonApi\Model\Response\ResponseInterface;

trait OffsetPaginationTrait {
    public static function fromArray(array $records, $flags = 0) {
        $result = [];
        foreach($records as $record) {
            $resource = self::fromRecord($record);
            $result[] = $resource;
        }
        return $result;
    }

    protected function paginateOffset(RequestInterface $request, array $paginateOps, int $links): ResponseInterface {
        $ops = null;
        foreach($paginateOps as $type => $value) {
            if($request->hasFilter($type)) {
                $key = $request->filterValue($type);
                $filter = "filter%5B" . urlencode($type) . "%5D=" . urlencode($key);
                $ops = $value;
                break;
            }
        }

        if(!$ops)
            throw new NotAllowedException("must specify filter");

        $reqOffset = $offset = $request->hasPagination("offset")?
                (int)$request->paginationValue("offset"):0;

        $limit = $request->hasPagination("size")?
                $request->paginationValue("size"):null;
        if(!$limit || $limit > ApiServer::MAX_LIMIT)
            $limit = ApiServer::MAX_LIMIT;

        $sort = $_GET["sort"] ?? "";

        $libraryAPI = Engine::api(ILibrary::class);
        if(!is_array($ops)) {
            [$total, $records] = self::$ops($request, $offset);
            $ops = [ -1, null ];
        } else if($ops[1]) {
            [$total, $retval] = $libraryAPI->searchFullText($ops[1], $key, $limit, $offset);
            $records = $retval[0]["result"];
            $offset += $limit;
            if($offset >= $total)
                $offset = 0;
        } else {
            $total = (int)$libraryAPI->searchPos($ops[0], $offset, -1, $key);
            $records = $libraryAPI->searchPos($ops[0], $offset, $limit, $key, $sort);
        }

        switch($ops[0]) {
        case ILibrary::ALBUM_AIRNAME:
            $result = $this->marshallReviews($records, $links);
            break;
        case ILibrary::TRACK_NAME:
            $result = $this->fromTrackSearch($records);
            break;
        default:
            $result = self::fromArray($records, $links);
            break;
        }
        $document = new Document($result);

        $base = Engine::getBaseUrl().$request->type()."?{$filter}";
        $size = "&page%5Bsize%5D=$limit";

        if($offset)
            $document->links()->set(new Link("next", "{$base}&page%5Boffset%5D={$offset}{$size}"));
        else
            $offset = $total; // no more rows remaining

        $link = new Link("first", "{$base}{$size}");
        $link->metaInformation()->set("total", $total);
        $link->metaInformation()->set("more", $total - $offset);
        $link->metaInformation()->set("offset", $reqOffset);
        $document->links()->set($link);

        return new DocumentResponse($document);
    }
}
