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
use ZK\Engine\IPlaylist;
use ZK\Engine\PlaylistEntry;
use ZK\Engine\PlaylistObserver;

use Enm\JsonApi\Exception\NotAllowedException;
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
use Enm\JsonApi\Server\RequestHandler\NoResourceModificationTrait;
use Enm\JsonApi\Server\RequestHandler\RequestHandlerInterface;


class Playlists implements RequestHandlerInterface {
    use OffsetPaginationTrait;
    use NoRelationshipFetchTrait;
    use NoRelationshipModificationTrait;
    use NoResourceModificationTrait;

    private static $paginateOps = [
        "date" => "paginate",
        "onNow" => "paginate",
        "id" => "paginate",
        "match(event)" => [ -1, "playlists" ],
    ];

    private static function paginate(RequestInterface $request, &$offset): array {
        if($request->hasFilter("date")) {
            // by date
            $keys = $request->filterValue("date", ",");
            $rows = [];
            foreach($keys as $key) {
                $result = Engine::api(IPlaylist::class)->getPlaylistsByDate($key)->asArray();
                if(sizeof($result))
                    $rows = array_merge($rows, $result);
            }
            $result = new ArrayIterator($rows);
        } else if($request->hasFilter("onNow")) {
            $result = Engine::api(IPlaylist::class)->getWhatsOnNow();
            $filter = OnNowFilter::class;
        } else if($request->hasFilter("id")) {
            // by id
            $keys = $request->filterValue("id", ",");
            $rows = [];
            foreach($keys as $key) {
                $row = Engine::api(IPlaylist::class)->getPlaylist($key, 1);
                if(!$row)
                    throw new ResourceNotFoundException("show", $key);
                $row["list"] = $key;
                $published = $row['airname'] || $row['dj'] == Engine::session()->getUser();
                if($published)
                    $rows[] = $row;
            }
            $result = new ArrayIterator($rows);
        }

        $retval = $result->asArray();
        $size = sizeof($retval);
        $offset += $size;
        return [$size, $retval];
    }

    public static function fromRecord($rec) {
        $id = $rec["list"];
        $res = new JsonResource("show", $id);
        $res->links()->set(new Link("self", Engine::getBaseUrl()."playlist/".$id));
        $attrs = $res->attributes();
        $attrs->set("name", $rec["description"]);
        $attrs->set("date", $rec["showdate"]);
        $attrs->set("time", $rec["showtime"]);
        $attrs->set("airname", $rec["airname"]);

        $events = [];
        Engine::api(IPlaylist::class)->getTracksWithObserver($id,
            (new PlaylistObserver())->onComment(function($entry) use(&$events) {
                $events[] = ["type" => "comment",
                             "comment" => $entry->getComment(),
                             "created" => $entry->getCreatedTime()];
            })->onLogEvent(function($entry) use(&$events) {
                $events[] = ["type" => "logEvent",
                             "event" => $entry->getLogEventType(),
                             "code" => $entry->getLogEventCode(),
                             "created" => $entry->getCreatedTime()];
            })->onSetSeparator(function($entry) use(&$events) {
                $events[] = ["type" => "break",
                             "created" => $entry->getCreatedTime()];
            })->onSpin(function($entry) use(&$events) {
                $spin = $entry->asArray();
                $spin["type"] = "track";
                $spin["artist"] = PlaylistEntry::swapNames($spin["artist"]);
                $spin["created"] = $entry->getCreatedTime();
                unset($spin["id"]);
                $events[] = $spin;
            }), 0, $filter);

        if(sizeof($events))
            $res->attributes()->set("events", $events);

        return $res;
    }

    public function fetchResource(RequestInterface $request): ResponseInterface {
        $key = $request->id();
        $api = Engine::api(IPlaylist::class);
        $row = $api->getPlaylist($key, 1);
        if(!$row || $api->isListDeleted($key))
            throw new ResourceNotFoundException("show", $key);

        // unpublished playlists are visible to owner only
        if(!$row["airname"] && $row["dj"] != Engine::session()->getUser())
            throw new ResourceNotFoundException("show", $key);

        $row["list"] = $key;
        $resource = self::fromRecord($row);

        $document = new Document($resource);
        $response = new DocumentResponse($document);
        return $response;
    }

    public function fetchResources(RequestInterface $request): ResponseInterface {
        return $this->paginateOffset($request, self::$paginateOps, 0);
    }

    public function createResource(RequestInterface $request): ResponseInterface {
        $session = Engine::session();
        if(!$session->isAuth("u"))
            throw new NotAllowedException("Operation requires authentication");

        $user = $session->getUser();

        $show = $request->requestBody()->data()->first("show");
        $attrs = $show->attributes();

        // validate the show's properties
        $name = $attrs->getRequired("name");
        $airname = $attrs->getRequired("airname");
        $time = $attrs->getRequired("time");
        list($year, $month, $day) = explode("-", $date = $attrs->getRequired("date"));
        if(!checkdate($month, $day, $year))
            throw new \InvalidArgumentException("date is invalid");

        // lookup the airname
        $djapi = Engine::api(IDJ::class);
        $airname = $djapi->getAirname($airname, $session->isAuth("v")?"":$user);
        if(!$airname) {
            // airname does not exist; try to create it
            $success = $djapi->insertAirname(mb_substr($airname, 0, IDJ::MAX_AIRNAME_LENGTH), $user);
            if($success > 0) {
                // success!
                $airname = $djapi->lastInsertId();
            } else
                throw new \InvalidArgumentException("airname is invalid");
        }

        // create the playlist
        $papi = Engine::api(IPlaylist::class);
        $papi->insertPlaylist($user, $date, $time, mb_substr($name, 0, IPlaylist::MAX_DESCRIPTION_LENGTH), $airname);
        $playlist = $papi->lastInsertId();

        // insert the tracks
        $events = $attrs->getOptional("events");
        if($events) {
            $status = '';
            $window = $papi->getTimestampWindow($playlist);
            foreach($events as $pentry) {
                $entry = PlaylistEntry::fromArray($pentry);
                $created = $entry->getCreated();
                if($created) {
                    try {
                        $stamp = PlaylistEntry::scrubTimestamp(
                                    new \DateTime($created), $window);
                        $entry->setCreated($stamp?$stamp->format(IPlaylist::TIME_FORMAT_SQL):null);
                    } catch(\Exception $e) {
                        error_log("failed to parse timestamp: $created");
                        $entry->setCreated(null);
                    }
                }
                $success = $papi->insertTrackEntry($playlist, $entry, $status);
            }
        }

        if($playlist)
            return new CreatedResponse(Engine::getBaseUrl()."playlist/$playlist");

        throw new \Exception("creation failed");
    }

    public function deleteResource(RequestInterface $request): ResponseInterface {
        if(!Engine::session()->isAuth("u"))
            throw new NotAllowedException("Operation requires authentication");

        $key = $request->id();
        if(empty($key))
            throw new ResourceNotFoundException("show", $key ?? 0);

        $api = Engine::api(IPlaylist::class);
        $list = $api->getPlaylist($key);
        if(!$list || $api->isListDeleted($key))
            throw new ResourceNotFoundException("show", $key);

        if($list['dj'] != Engine::session()->getUser())
            throw new NotAllowedException("not owner");
    
        $api->deletePlaylist($key);

        return new EmptyResponse();
    }
}

class ArrayIterator {
    private $rows;

    public function __construct($rows) {
        $this->rows = $rows;
    }

    public function fetch() {
        return array_shift($this->rows);
    }

    public function asArray() {
        $result = $this->rows;
        $this->rows = null;
        return $result;
    }
}
