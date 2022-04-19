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

use ZK\Controllers\PushServer;
use ZK\Engine\Engine;
use ZK\Engine\IDJ;
use ZK\Engine\ILibrary;
use ZK\Engine\IPlaylist;
use ZK\Engine\PlaylistEntry;
use ZK\Engine\PlaylistObserver;

use Enm\JsonApi\Exception\BadRequestException;
use Enm\JsonApi\Exception\NotAllowedException;
use Enm\JsonApi\Exception\ResourceNotFoundException;
use Enm\JsonApi\Model\Document\Document;
use Enm\JsonApi\Model\Request\RequestInterface;
use Enm\JsonApi\Model\Resource\JsonResource;
use Enm\JsonApi\Model\Resource\Link\Link;
use Enm\JsonApi\Model\Resource\Relationship\Relationship;
use Enm\JsonApi\Model\Resource\ResourceCollection;
use Enm\JsonApi\Model\Response\CreatedResponse;
use Enm\JsonApi\Model\Response\DocumentResponse;
use Enm\JsonApi\Model\Response\EmptyResponse;
use Enm\JsonApi\Model\Response\ResponseInterface;
use Enm\JsonApi\Server\RequestHandler\RequestHandlerInterface;


class Playlists implements RequestHandlerInterface {
    use OffsetPaginationTrait;

    const PLAYLIST_SEARCH = 1000;

    const LINKS_NONE = 0;
    const LINKS_EVENTS = 1;
    const LINKS_ALBUMS = 2;
    const LINKS_ALBUMS_DETAILS = 4;
    const LINKS_ORIGIN = 8;
    const LINKS_ALL = ~0;

    private static $paginateOps = [
        "date" => "paginate",
        "id" => "paginate",
        "match(event)" => [ self::PLAYLIST_SEARCH, "playlists" ],
    ];

    private static function paginate(RequestInterface $request, $type, $key, &$offset): array {
        switch($type) {
        case "date":
            if(strtolower($key) == "onnow") {
                $result = Engine::api(IPlaylist::class)->getWhatsOnNow()->asArray();
                break;
            }
            $result = [];
            $keys = explode(",", $key);
            foreach($keys as $key) {
                $rows = Engine::api(IPlaylist::class)->getPlaylistsByDate($key)->asArray();
                if(sizeof($rows))
                    $result = array_merge($result, $rows);
            }
            break;
        case "id":
            $result = [];
            $keys = explode(",", $key);
            foreach($keys as $key) {
                $row = Engine::api(IPlaylist::class)->getPlaylist($key, 1);
                if(!$row)
                    throw new ResourceNotFoundException("show", $key);
                $row["list"] = $key;
                $published = $row['airname'] || $row['dj'] == Engine::session()->getUser();
                if($published)
                    $result[] = $row;
            }
            break;
        }

        $size = sizeof($result);
        $offset = 0;
        return [$size, $result];
    }

    public static function fromRecord($rec, $flags) {
        $id = $rec["list"] ?? $rec["id"];
        $res = new JsonResource("show", $id);
        $res->links()->set(new Link("self", Engine::getBaseUrl()."playlist/".$id));
        $attrs = $res->attributes();
        $attrs->set("name", $rec["description"]);
        $attrs->set("date", $rec["showdate"]);
        $attrs->set("time", $rec["showtime"]);
        $attrs->set("airname", $rec["airname"]);

        $origin = $rec["origin"];
        $attrs->set("isRebroadcast", $origin ? true : false);
        if($origin) {
            if($flags & self::LINKS_ORIGIN) {
                $row = Engine::api(IPlaylist::class)->getPlaylist($origin, 1);
                $row['list'] = $origin;
                $rel = self::fromRecord($row, $flags);
            } else
                $rel = new JsonResource("show", $origin);

            $relation = new Relationship("origin", $rel);
            $relation->links()->set(new Link("related", Engine::getBaseUrl()."playlist/$id/origin"));
            $res->relationships()->set($relation);
        }

        if($flags & self::LINKS_EVENTS) {
            $relations = new ResourceCollection();

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
                })->onSpin(function($entry) use(&$events, $relations, $flags) {
                    $spin = $entry->asArray();
                    $spin["type"] = "spin";
                    $spin["artist"] = PlaylistEntry::swapNames($spin["artist"]);
                    $spin["created"] = $entry->getCreatedTime();
                    if($spin["tag"] && $flags & self::LINKS_ALBUMS) {
                        $tag = $spin["tag"];
                        if($flags & self::LINKS_ALBUMS_DETAILS &&
                                sizeof($albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $tag)))
                            $res = Albums::fromArray($albums, Albums::LINKS_ALL)[0];
                        else
                            $res = new JsonResource("album", $tag);
                        $relations->set($res);
                        // using the 'xa' extension
                        // see https://github.com/RocketMan/zookeeper/pull/263
                        $spin["xa:relationships"] = new Relationship("album", $res);
                    }
                    unset($spin["tag"]);
                    unset($spin["id"]);
                    $events[] = $spin;
                }));

            if(sizeof($events))
                $res->attributes()->set("events", $events);

            if(!$relations->isEmpty()) {
                $relation = new Relationship("albums", $relations);
                $relation->links()->set(new Link("related", Engine::getBaseUrl()."playlist/$id/albums"));
                $res->relationships()->set($relation);
            }
        }

        return $res;
    }

    public static function fromArray(array $records, $flags = self::LINKS_NONE) {
        $result = [];
        foreach($records as $record) {
            $resource = self::fromRecord($record, $flags);
            $result[] = $resource;
        }
        return $result;
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
        $flags = self::LINKS_NONE;
        if($request->requestsField("show", "events"))
            $flags |= self::LINKS_EVENTS | self::LINKS_ALBUMS;
        if($request->requestsInclude("albums"))
            $flags |= self::LINKS_ALBUMS_DETAILS;
        if($request->requestsInclude("origin"))
            $flags |= self::LINKS_ORIGIN;

        $resource = self::fromRecord($row, $flags);

        $document = new Document($resource);
        $response = new DocumentResponse($document);
        $response->headers()->set('Content-Type', ApiServer::CONTENT_TYPE);
        return $response;
    }

    public function fetchResources(RequestInterface $request): ResponseInterface {
        $flags = self::LINKS_NONE;
        if($request->requestsField("show", "events"))
            $flags |= self::LINKS_EVENTS | self::LINKS_ALBUMS;
        if($request->requestsInclude("albums"))
            $flags |= self::LINKS_ALBUMS_DETAILS;
        if($request->requestsInclude("origin"))
            $flags |= self::LINKS_ORIGIN;

        $response = $this->paginateOffset($request, self::$paginateOps, $flags);
        $response->headers()->set('Content-Type', ApiServer::CONTENT_TYPE);
        return $response;
    }

    public function fetchRelationship(RequestInterface $request): ResponseInterface {
        $flags = Albums::LINKS_ALL;
        if(!$request->requestsField("album", "tracks"))
            $flags &= ~Albums::LINKS_TRACKS;

        $id = $request->id();

        $relations = new ResourceCollection();

        switch($request->relationship()) {
        case "albums":
            Engine::api(IPlaylist::class)->getTracksWithObserver($id,
            (new PlaylistObserver())->onSpin(function($entry) use($relations, $flags) {
                $tag = $entry->getTag();
                if($tag) {
                    if(sizeof($albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $tag)))
                        $res = Albums::fromArray($albums, $flags)[0];
                    else
                        $res = new JsonResource("album", $tag);

                    $relations->set($res);
                }
            }));
            break;
        case "events":
            if(!$request->requestsInclude("album"))
                $flags = Albums::LINKS_NONE;
            Engine::api(IPlaylist::class)->getTracksWithObserver($id,
            (new PlaylistObserver())->onComment(function($entry) use($relations) {
                $e = new JsonResource("event", $entry->getId());
                $a = $e->attributes();
                $a->set("type", "comment");
                $a->set("comment", $entry->getComment());
                $a->set("created", $entry->getCreatedTime());
                $relations->set($e);
            })->onLogEvent(function($entry) use($relations) {
                $e = new JsonResource("event", $entry->getId());
                $a = $e->attributes();
                $a->set("type", "logEvent");
                $a->set("event", $entry->getLogEventType());
                $a->set("code", $entry->getLogEventCode());
                $a->set("created", $entry->getCreatedTime());
                $relations->set($e);
            })->onSetSeparator(function($entry) use($relations) {
                $e = new JsonResource("event", $entry->getId());
                $a = $e->attributes();
                $a->set("type", "break");
                $a->set("created", $entry->getCreatedTime());
                $relations->set($e);
            })->onSpin(function($entry) use($relations, $flags) {
                $e = new JsonResource("event", $entry->getId());
                $a = $e->attributes();
                $a->set("type", "spin");
                $attrs = $entry->asArray();
                unset($attrs["tag"]);
                unset($attrs["id"]);
                $a->merge($attrs);
                $a->set("artist", PlaylistEntry::swapNames($entry->getArtist()));
                $a->set("created", $entry->getCreatedTime());

                $tag = $entry->getTag();
                if($tag) {
                    if($flags && sizeof($albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $tag)))
                        $res = Albums::fromArray($albums, $flags)[0];
                    else
                        $res = new JsonResource("album", $tag);

                    $e->relationships()->set(new Relationship("album", $res));
                }

                $relations->set($e);
            }));
            break;
        case "origin":
            $api = Engine::api(IPlaylist::class);
            $list = $api->getPlaylist($id);
            $origin = $list['origin'];
            if($origin) {
                $row = $api->getPlaylist($origin, 1);
                $row['list'] = $origin;
                $rel = self::fromRecord($row, self::LINKS_ALL);
                $relations->set($rel);
            }
            break;
        case "relationships":
            throw new NotAllowedException("unspecified relationship");
        default:
            throw new NotAllowedException('You are not allowed to fetch the relationship ' . $request->relationship());
        }

        $document = new Document($relations);
        if($request->requestsAttributes())
            $document->links()->set(new Link("self", Engine::getBaseUrl()."playlist/$id/".$request->relationship()));
        else {
            $document->links()->set(new Link("self", Engine::getBaseUrl()."playlist/$id/relationships/".$request->relationship()));
            $document->links()->set(new Link("related", Engine::getBaseUrl()."playlist/$id/".$request->relationship()));
        }

        $response = new DocumentResponse($document);
        $response->headers()->set('Content-Type', ApiServer::CONTENT_TYPE);
        return $response;
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
        $aid = $djapi->getAirname($airname, $session->isAuth("v")?"":$user);
        if(!$aid) {
            // airname does not exist; try to create it
            $success = $djapi->insertAirname(mb_substr($airname, 0, IDJ::MAX_AIRNAME_LENGTH), $user);
            if($success > 0) {
                // success!
                $aid = $djapi->lastInsertId();
            } else
                throw new \InvalidArgumentException("airname is invalid");
        }

        // create the playlist
        $papi = Engine::api(IPlaylist::class);
        $papi->insertPlaylist($user, $date, $time, mb_substr($name, 0, IPlaylist::MAX_DESCRIPTION_LENGTH), $aid);
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

        if($playlist) {
            if($aid && $papi->isNowWithinShow(
                    ["showdate" => $date, "showtime" => $time]))
                PushServer::sendAsyncNotification();

            if($events)
                PushServer::lazyLoadImages($playlist);

            return new CreatedResponse(Engine::getBaseUrl()."playlist/$playlist");
        }

        throw new \Exception("creation failed");
    }

    public function patchResource(RequestInterface $request): ResponseInterface {
        $session = Engine::session();
        if(!$session->isAuth("u"))
            throw new NotAllowedException("Operation requires authentication");

        $key = $request->id();
        if(empty($key))
            throw new BadRequestException("must specify id");

        $api = Engine::api(IPlaylist::class);
        $list = $api->getPlaylist($key);
        if(!$list || $api->isListDeleted($key))
            throw new ResourceNotFoundException("show", $key);

        if($list['dj'] != $session->getUser())
            throw new NotAllowedException("not owner");

        $show = $request->requestBody()->data()->first("show");
        $attrs = $show->attributes();

        // validate the show's properties
        $name = $attrs->has("name")?$attrs->getRequired("name"):$list['description'];
        $time = $attrs->has("time")?$attrs->getRequired("time"):$list['showtime'];

        if($attrs->has("date")) {
            list($year, $month, $day) = explode("-", $date = $attrs->getRequired("date"));
            if(!checkdate($month, $day, $year))
                throw new \InvalidArgumentException("date is invalid");
        } else
            $date = $list['showdate'];

        if($attrs->has("airname")) {
            // lookup the airname
            $airname = $attrs->getRequired("airname");
            $djapi = Engine::api(IDJ::class);
            $user = $session->getUser();
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
        } else
            $airname = $list['airname'];

        $success = $api->updatePlaylist($key, $date, $time, $name, $airname);

        return $success ?
            new EmptyResponse() :
            new BadRequestException("DB update error");
    }

    public function deleteResource(RequestInterface $request): ResponseInterface {
        if(!Engine::session()->isAuth("u"))
            throw new NotAllowedException("Operation requires authentication");

        $key = $request->id();
        if(empty($key))
            throw new BadRequestException("must specify id");

        $api = Engine::api(IPlaylist::class);
        $list = $api->getPlaylist($key);
        if(!$list || $api->isListDeleted($key))
            throw new ResourceNotFoundException("show", $key);

        if($list['dj'] != Engine::session()->getUser())
            throw new NotAllowedException("not owner");
    
        $api->deletePlaylist($key);

        return new EmptyResponse();
    }

    public function addRelatedResources(RequestInterface $request): ResponseInterface {
        if($request->relationship() != "events") {
            throw new NotAllowedException('You are not allowed to modify the relationship ' . $request->relationship());
        }

        if(!Engine::session()->isAuth("u"))
            throw new NotAllowedException("Operation requires authentication");

        $key = $request->id();
        if(empty($key))
            throw new BadRequestException("must specify id");

        $api = Engine::api(IPlaylist::class);
        $list = $api->getPlaylist($key);
        if(!$list || $api->isListDeleted($key))
            throw new ResourceNotFoundException("show", $key);

        if($list['dj'] != Engine::session()->getUser())
            throw new NotAllowedException("not owner");

        $event = $request->requestBody()->data()->first("event");
        $entry = PlaylistEntry::fromArray($event->attributes()->all());

        try {
            $album = $event->relationships()->get("album")->related()->first("album");
            $albumrec = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $album->id());
            if(sizeof($albumrec)) {
                // don't allow modification of album info if tag is set
                $entry->setTag($album->id());
                $entry->setArtist(PlaylistEntry::swapNames($albumrec[0]["artist"]));
                $entry->setAlbum($albumrec[0]["album"]);
                $entry->setLabel($albumrec[0]["name"]);
            }
        } catch(\Exception $e) {}

        $autoTimestamp = false;
        if($created = $entry->getCreated()) {
            $window = $api->getTimestampWindow($key);
            try {
                $stamp = PlaylistEntry::scrubTimestamp(new \DateTime($created), $window);
                $entry->setCreated($stamp?$stamp->format(IPlaylist::TIME_FORMAT_SQL):null);
            } catch(\Exception $e) {
                error_log("failed to parse timestamp: $created");
                $entry->setCreated(null);
            }
        } else if($autoTimestamp = $api->isNowWithinShow($list))
            $entry->setCreated((new \DateTime("now"))->format(IPlaylist::TIME_FORMAT_SQL));

        $status = '';
        $success = $api->insertTrackEntry($key, $entry, $status);

        if($success && $list['airname']) {
            if($autoTimestamp) {
                $list['id'] = $key;
                if($entry->isType(PlaylistEntry::TYPE_SPIN)) {
                    $spin = $entry->asArray();
                    $spin['artist'] = PlaylistEntry::swapNames($spin['artist']);
                } else
                    $spin = null;

                PushServer::sendAsyncNotification($list, $spin);
            } else if($api->isNowWithinShow($list))
                PushServer::sendAsyncNotification();

            if(!$autoTimestamp && isset($stamp))
                PushServer::lazyLoadImages($key, $entry->getId());
        }

        return $success ?
            new DocumentResponse(new Document(new JsonResource("event", $entry->getId()))) :
            new BadRequestException($status ?? "DB update error");
    }

    public function replaceRelatedResources(RequestInterface $request): ResponseInterface {
        if($request->relationship() != "events") {
            throw new NotAllowedException('You are not allowed to modify the relationship ' . $request->relationship());
        }

        if(!Engine::session()->isAuth("u"))
            throw new NotAllowedException("Operation requires authentication");

        $key = $request->id();
        if(empty($key))
            throw new BadRequestException("must specify id");

        $api = Engine::api(IPlaylist::class);
        $list = $api->getPlaylist($key);
        if(!$list || $api->isListDeleted($key))
            throw new ResourceNotFoundException("show", $key);

        if($list['dj'] != Engine::session()->getUser())
            throw new NotAllowedException("not owner");

        $event = $request->requestBody()->data()->first("event");

        $id = $event->id();
        $track = $api->getTrack($id);
        if(!$track || $track['list'] != $key)
            throw new NotAllowedException("event not in list");

        // TBD allow changes instead of complete relacement
        $entry = PlaylistEntry::fromArray($event->attributes()->all());
        $entry->setId($id);

        try {
            $album = $event->relationships()->get("album")->related()->first("album");
            $entry->setTag($album->id());
        } catch(\Exception $e) {}

        $created = $entry->getCreated();
        if($created) {
            $window = $api->getTimestampWindow($key);
            try {
                $stamp = PlaylistEntry::scrubTimestamp(new \DateTime($created), $window);
                $entry->setCreated($stamp?$stamp->format(IPlaylist::TIME_FORMAT_SQL):null);
            } catch(\Exception $e) {
                error_log("failed to parse timestamp: $created");
                $entry->setCreated(null);
            }
        }

        $success = $api->updateTrackEntry($key, $entry);

        if($success && $list['airname'] && $api->isNowWithinShow($list))
            PushServer::sendAsyncNotification();

        if($success && isset($stamp))
            PushServer::lazyLoadImages($key, $id);

        return $success ?
            new EmptyResponse() :
            new BadRequestException("DB update error");
    }

    public function removeRelatedResources(RequestInterface $request): ResponseInterface {
        if($request->relationship() != "events") {
            throw new NotAllowedException('You are not allowed to modify the relationship ' . $request->relationship());
        }

        if(!Engine::session()->isAuth("u"))
            throw new NotAllowedException("Operation requires authentication");

        $key = $request->id();
        if(empty($key))
            throw new BadRequestException("must specify id");

        $api = Engine::api(IPlaylist::class);
        $list = $api->getPlaylist($key);
        if(!$list || $api->isListDeleted($key))
            throw new ResourceNotFoundException("show", $key);

        if($list['dj'] != Engine::session()->getUser())
            throw new NotAllowedException("not owner");

        $event = $request->requestBody()->data()->first("event");

        $id = $event->id();
        $track = $api->getTrack($id);
        if(!$track || $track['list'] != $key)
            throw new NotAllowedException("event not in list");

        $success = $api->deleteTrack($id);

        return $success ?
            new EmptyResponse() :
            new BadRequestException("DB update error");
    }
}
