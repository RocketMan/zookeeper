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
use ZK\Engine\ILibrary;

use Enm\JsonApi\Exception\NotAllowedException;
use Enm\JsonApi\Model\Document\Document;
use Enm\JsonApi\Model\Request\RequestInterface;
use Enm\JsonApi\Model\Resource\Link\Link;
use Enm\JsonApi\Model\Resource\Relationship\Relationship;
use Enm\JsonApi\Model\Resource\ResourceCollection;
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

    protected function fromTrackSearch(array $records) {
        $result = [];
        $map = [];
        foreach($records as $record) {
            $tag = $record["tag"] ?? null;
            if(!$tag) {
                error_log("Warning: skipping orphaned track: " . $record["track"]);
                continue;
            }
            if(array_key_exists($tag, $map)) {
                $resource = $map[$tag];
                $tracks = $resource->attributes()->getOptional("tracks");
            } else {
                // ILibrary::TRACK_NAME returns '[coll]: title' in the
                // album field (normally in artist) and the track artist
                // in artist.  For reconstituting the album record, we
                // temporarily restore the original artist.
                if($record["iscoll"]) {
                    $artist = $record["artist"];
                    $record["artist"] = $record["album"];
                }
                $resource = $map[$tag] = Albums::fromRecord($record, false);
                // we need to put this back so it is available for the track
                if($record["iscoll"])
                    $record["artist"] = $artist;
                $result[] = $resource;
                $tracks = [];

                if($record["pubkey"]) {
                    // full text label info is sometimes incomplete;
                    // optionally backfill the name for now; we may get rid
                    // of this if there is a perceptable performance hit
                    if(!$record["name"]) {
                        $labels = Engine::api(ILibrary::class)->search(ILibrary::LABEL_PUBKEY, 0, 1, $record["pubkey"]);
                        if(sizeof($labels))
                            $record["name"] = $labels[0]["name"];
                    }
                    $res = Labels::fromRecord($record);
                    $relation = new Relationship("label", $res);
                    $relation->links()->set(new Link("related", Engine::getBaseUrl()."album/{$record["tag"]}/label"));
                    $relation->links()->set(new Link("self", Engine::getBaseUrl()."album/{$record["tag"]}/relationships/label"));
                    $relation->metaInformation()->set("name", $record["name"]);
                    $resource->relationships()->set($relation);
                }
            }
            $fields = Albums::TRACK_FIELDS;
            if(!$record["iscoll"])
                $fields = array_diff($fields, ["artist"]);

            $r = [];
            foreach($fields as $field)
                $r[$field] = $record[$field];

            $tracks[] = $r;
            $resource->attributes()->set("tracks", $tracks);
        }
        return $result;
    }

    protected function fromPlaylistSearch(array $records) {
        $result = [];
        $map = [];
        foreach($records as $record) {
            $list = $record["list"];
            if(array_key_exists($list, $map)) {
                $resource = $map[$list];
                $events = $resource->attributes()->getOptional("events");
            } else {
                $resource = $map[$list] = Playlists::fromRecord($record, Playlists::LINKS_NONE);
                $result[] = $resource;
                $events = [];
            }

            $fields = [ "artist", "album", "track" ];

            $r = [ "type" => "spin" ];
            foreach($fields as $field)
                $r[$field] = $record[$field];

            $events[] = $r;
            $resource->attributes()->set("events", $events);
        }
        return $result;
    }

    protected function marshallReviews(array $records, $flags) {
        $result = [];
        foreach($records as $record) {
            $resource = Albums::fromRecord($record, $flags & Albums::LINKS_TRACKS);
            $result[] = $resource;

            $relations = new ResourceCollection();
            $relation = new Relationship("reviews", $relations);
            $relation->links()->set(new Link("related", Engine::getBaseUrl()."album/{$record["tag"]}/reviews"));
            $resource->relationships()->set($relation);
            $res = Reviews::fromRecord($record);
            $res->metaInformation()->set("date", $record["reviewed"]);
            $relations->set($res);

            $res = Labels::fromRecord($record);
            $relation = new Relationship("label", $res);
            $relation->links()->set(new Link("related", Engine::getBaseUrl()."album/{$record["tag"]}/label"));
            $relation->links()->set(new Link("self", Engine::getBaseUrl()."album/{$record["tag"]}/relationships/label"));
            $relation->metaInformation()->set("name", $record["name"]);
            $resource->relationships()->set($relation);
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
            throw new NotAllowedException("Must specify filter.  May be one of: ".implode(", ", array_keys($paginateOps)));

        $reqOffset = $offset = $request->hasPagination("offset")?
                (int)$request->paginationValue("offset"):0;

        $limit = $request->hasPagination("size") ?
                min($request->paginationValue("size"), ApiServer::MAX_LIMIT) :
                ApiServer::DEFAULT_LIMIT;

        $sort = $_GET["sort"] ?? "";

        $libraryAPI = Engine::api(ILibrary::class);
        if(!is_array($ops)) {
            [$total, $records] = self::$ops($request, $type, $key, $offset, $limit);
            $ops = [ -1, null ];
        } else if($ops[1]) {
            [$total, $retval] = $libraryAPI->searchFullText($ops[1], $key, $limit, $offset);
            $records = $total ? $retval[0]["result"] : [];
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
        case Playlists::PLAYLIST_SEARCH:
            $result = $this->fromPlaylistSearch($records);
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

        $link = new Link("first", "{$base}");
        $link->metaInformation()->set("total", $total);
        $link->metaInformation()->set("more", $total - $offset);
        $link->metaInformation()->set("offset", $reqOffset);
        $document->links()->set($link);

        return new DocumentResponse($document);
    }
}
