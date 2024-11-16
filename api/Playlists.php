<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2024 Jim Mason <jmason@ibinx.com>
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

use ZK\UI\PlaylistBuilder;

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
    const LINKS_REVIEWS_WITH_BODY = 16;
    const LINKS_ALL = ~0;

    private static $paginateOps = [
        "date" => "paginate",
        "id" => "paginate",
        "user" => "paginate",
        "match(event)" => [ self::PLAYLIST_SEARCH, "playlists" ],
    ];

    private static function paginate(RequestInterface $request, $type, $key, &$offset, $limit): array {
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
        case "user":
            if(!$key || $key == "self")
                $key = Engine::session()->getUser();
            if(!$key)
                throw new \InvalidArgumentException("Must supply value for user filter");
            $api = Engine::api(IPlaylist::class);
            if($request->hasFilter("deleted") &&
                    $request->filterValue("deleted")) {
                $rows = $api->getListsSelDeleted($key, $offset, $limit);
                $count = $api->getDeletedPlaylistCount($key);
            } else {
                $rows = $api->getListsSelNormal($key, $offset, $limit);
                $count = $api->getNormalPlaylistCount($key);
            }
            $result = $rows->asArray();
            $offset += sizeof($result);
            return [ $count, $result ];
        }

        $size = sizeof($result);
        $offset = 0;
        return [$size, $result];
    }

    private static function fetchEvents($playlist, $aflags) {
        $relations = new ResourceCollection();

        Engine::api(IPlaylist::class)->getTracksWithObserver($playlist,
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
        })->onSpin(function($entry) use($relations, $aflags) {
            $e = new JsonResource("event", $entry->getId());
            $a = $e->attributes();
            $a->set("type", "spin");
            $attrs = $entry->asArray();
            unset($attrs["tag"]);
            unset($attrs["id"]);
            $a->merge($attrs);
            $a->set("created", $entry->getCreatedTime());

            $tag = $entry->getTag();
            if($tag) {
                $a->set("artist", PlaylistEntry::swapNames($entry->getArtist()));
                if($aflags && sizeof($albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $tag)))
                    $res = Albums::fromArray($albums, $aflags)[0];
                else
                    $res = new JsonResource("album", $tag);

                $relation = new Relationship("album", $res);
                $relation->links()->set(new Link("related", Engine::getBaseUrl()."album/$tag"));
                $e->relationships()->set($relation);
            }

            $relations->set($e);
        }));

        return $relations;
    }

    public static function fromRecord($rec, $flags) {
        $id = $rec["list"] ?? $rec["id"];
        $res = new JsonResource("show", $id);
        $res->links()->set(new Link("self", Engine::getBaseUrl()."playlist/".$id));
        $attrs = $res->attributes();
        $attrs->set("name", $rec["description"]);
        $attrs->set("date", $rec["showdate"]);
        $attrs->set("time", $rec["showtime"]);
        $attrs->set("airname", $rec["airname"] ?? "None");
        if(isset($rec["expires"]))
            $attrs->set("expires", $rec["expires"]);

        $origin = $rec["origin"] ?? null;
        $attrs->set("rebroadcast", $origin || preg_match(IPlaylist::DUPLICATE_REGEX, $rec["description"]));
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
            if(Engine::getApiVer() >= 2) {
                $aflags = $flags & self::LINKS_ALBUMS_DETAILS ?
                            Albums::LINKS_ALL : Albums::LINKS_NONE;

                if(!($flags & self::LINKS_REVIEWS_WITH_BODY))
                    $aflags &= ~Albums::LINKS_REVIEWS_WITH_BODY;

                $relations = self::fetchEvents($id, $aflags);
                $relation = new Relationship("events", $relations);
                $relation->links()->set(new Link("related", Engine::getBaseUrl()."playlist/$id/events"));
                $res->relationships()->set($relation);
                return $res;
            }

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
                    if($spin["tag"])
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
        $apiver = Engine::getApiVer();
        if($apiver >= 2 || $request->requestsField("show", "events"))
            $flags |= self::LINKS_EVENTS | self::LINKS_ALBUMS;
        if($request->requestsInclude("events.album") ||
                $apiver < 2 && $request->requestsInclude("albums"))
            $flags |= self::LINKS_ALBUMS_DETAILS;
        if($request->requestsInclude("origin"))
            $flags |= self::LINKS_ORIGIN;
        if($request->requestsInclude("events.album.reviews") ||
                $apiver < 2 && $request->requestsInclude("albums.reviews"))
            $flags |= self::LINKS_REVIEWS_WITH_BODY;

        $resource = self::fromRecord($row, $flags);

        $document = new Document($resource);
        $response = new DocumentResponse($document);
        if(Engine::getApiVer() < 2)
            $response->headers()->set('Content-Type', ApiServer::CONTENT_TYPE);
        return $response;
    }

    public function fetchResources(RequestInterface $request): ResponseInterface {
        $flags = self::LINKS_NONE;
        $apiver = Engine::getApiVer();
        if($apiver >= 2 || $request->requestsField("show", "events"))
            $flags |= self::LINKS_EVENTS | self::LINKS_ALBUMS;
        if($request->requestsInclude("events.album") ||
                $apiver < 2 && $request->requestsInclude("albums"))
            $flags |= self::LINKS_ALBUMS_DETAILS;
        if($request->requestsInclude("origin"))
            $flags |= self::LINKS_ORIGIN;

        $response = $this->paginateOffset($request, self::$paginateOps, $flags);
        if(Engine::getApiVer() < 2)
            $response->headers()->set('Content-Type', ApiServer::CONTENT_TYPE);
        return $response;
    }

    public function fetchRelationship(RequestInterface $request): ResponseInterface {
        $flags = Albums::LINKS_ALL;
        if(!$request->requestsField("album", "tracks"))
            $flags &= ~Albums::LINKS_TRACKS;

        $id = $request->id();

        $api = Engine::api(IPlaylist::class);
        $list = $api->getPlaylist($id);
        if(!$list || $api->isListDeleted($id))
            throw new ResourceNotFoundException("show", $id);

        // unpublished playlists are visible to owner only
        if(!$list["airname"] && $list["dj"] != Engine::session()->getUser())
            throw new ResourceNotFoundException("show", $id);

        $relations = new ResourceCollection();

        switch($request->relationship()) {
        case "albums":
            if(Engine::getApiVer() >= 2)
                throw new NotAllowedException('You are not allowed to fetch the  relationship ' . $request->relationship());

            if(!$request->requestsInclude("reviews"))
                $flags &= ~Albums::LINKS_REVIEWS_WITH_BODY;

            $api->getTracksWithObserver($id,
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
            if(!$request->requestsInclude("album.reviews"))
                $flags &= ~Albums::LINKS_REVIEWS_WITH_BODY;

            $relations = self::fetchEvents($id, $flags);
            break;
        case "origin":
            $origin = $list['origin'];
            if($origin) {
                $row = $api->getPlaylist($origin, 1);
                if($row) {
                    $row['list'] = $origin;
                    $rel = self::fromRecord($row, self::LINKS_ALL);
                    $relations->set($rel);
                }
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
        if(Engine::getApiVer() < 2)
            $response->headers()->set('Content-Type', ApiServer::CONTENT_TYPE);
        return $response;
    }

    private function validateTime($time) {
        $timeAr = explode('-', $time);
        if(sizeof($timeAr) != 2)
            throw new \InvalidArgumentException("time is invalid");

        $start = \DateTime::createFromFormat(IPlaylist::TIME_FORMAT, "2019-01-01 " . $timeAr[0]);
        $end =  \DateTime::createFromFormat(IPlaylist::TIME_FORMAT, "2019-01-01 " . $timeAr[1]);
        if(!$start || !$end)
            throw new \InvalidArgumentException("time is invalid");
        if($end < $start)
            $end->modify('+1 day');
        $minutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;
        if($minutes < IPlaylist::MIN_SHOW_LEN || $minutes > IPlaylist::MAX_SHOW_LEN)
            throw new \InvalidArgumentException("Invalid time range (min " . IPlaylist::MIN_SHOW_LEN . " minutes, max " . (IPlaylist::MAX_SHOW_LEN / 60) . " hours)");
    }

    public function createResource(RequestInterface $request): ResponseInterface {
        $session = Engine::session();
        if(!$session->isAuth("u"))
            throw new NotAllowedException("Operation requires authentication");

        $user = $session->getUser();

        $show = $request->requestBody()->data()->first("show");
        $attrs = $show->attributes();

        // validate the show's properties
        $papi = Engine::api(IPlaylist::class);
        $dup = $attrs->getOptional("rebroadcast", false);
        $airname = $dup ? $attrs->getOptional("airname") : $attrs->getRequired("airname");
        if($dup) {
            $origin = $show->relationships()->get("origin")->related()->first("show")->id();
            $list = $papi->getPlaylist($origin);
            if(!$list || !$session->isAuth("v") && $user != $list["dj"])
                throw new \InvalidArgumentException("origin is invalid");

            // ascend the origin list
            $topOrigin = $list;
            while($topOrigin && $topOrigin['origin'])
                $topOrigin = $papi->getPlaylist($topOrigin['origin']);

            // if root origin does not exist, infer owner from airname
            if(!$topOrigin && $list['airname'])
                $topOrigin = Engine::api(IDJ::class)->getAirnames(0, $list['airname'])->fetch();
        }
        $foreign = $dup && $topOrigin && $topOrigin['dj'] != $user;
        $time = $attrs->getRequired("time");
        $this->validateTime($time); // raises exception on invalid

        list($year, $month, $day) = explode("-", $date = $attrs->getRequired("date"));
        if(!checkdate($month, $day, $year))
            throw new \InvalidArgumentException("date is invalid");

        // lookup the airname
        $aid = null;
        if($airname && strcasecmp($airname, "none") && !$foreign) {
            $djapi = Engine::api(IDJ::class);
            $aid = $djapi->getAirname($airname, $user);
            if(!$aid) {
                // airname does not exist; try to create it
                $success = $djapi->insertAirname(mb_substr($airname, 0, IDJ::MAX_AIRNAME_LENGTH), $user);
                if($success > 0) {
                    // success!
                    $aid = $djapi->lastInsertId();
                } else
                    throw new \InvalidArgumentException("airname is invalid");
            }
        }

        if($dup) {
            // duplicate an existing playlist
            $fromTime = $show->metaInformation()->getOptional("fromtime");
            if($fromTime && !preg_match('/^\d{4}(\-\d{4})?$/', $fromTime))
                throw new \Exception("fromtime invalid");

            $playlist = $papi->duplicatePlaylist($origin, $fromTime);
            if(!$playlist)
                throw new \Exception("duplication failed");

            if($session->isAuth("v") && $user != $list["dj"]) {
                $papi->reparentPlaylist($playlist, $user);
                $aid = null; // force original airname
            }

            $suffix = preg_replace_callback("/%([^%]*)%/",
                function($matches) use ($list) {
                    return \DateTime::createFromFormat(
                        IPlaylist::TIME_FORMAT,
                        $list["showdate"] . " 0000")->format($matches[1]);
                }, IPlaylist::DUPLICATE_SUFFIX);
            $description = $list["description"];
            if(mb_strlen($description) + mb_strlen($suffix) > IPlaylist::MAX_DESCRIPTION_LENGTH)
                $description = mb_substr($description, 0, IPlaylist::MAX_DESCRIPTION_LENGTH - mb_strlen($suffix) - 3) . "...";
            $description .= $suffix;

            $name = $attrs->getOptional("name", $description);
            $papi->updatePlaylist($playlist, $date, $time, mb_substr($name, 0, IPlaylist::MAX_DESCRIPTION_LENGTH), $aid ??= $list["airname"], true);
        } else {
            // create a new playlist
            $name = $attrs->getRequired("name");
            $papi->insertPlaylist($user, $date, $time, mb_substr($name, 0, IPlaylist::MAX_DESCRIPTION_LENGTH), $aid);
            $playlist = $papi->lastInsertId();
        }

        // insert the tracks
        $events = $attrs->getOptional("events");
        if($events && Engine::getApiVer() < 2) {
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

        if($show->relationships()->has("events")) {
            $window = $papi->getTimestampWindow($playlist);
            $included = $request->requestBody()->included();
            foreach($show->relationships()->get("events")->related()->all() as $er) {
                $event = $included->get("event", $er->id());
                $pentry = $event->attributes()->all();
                $entry = PlaylistEntry::fromArray($pentry);
                $created = $entry->getCreated();
                if($created == "auto") {
                    $autoTimestamp = $papi->isNowWithinShow(
                            ["showdate" => $date, "showtime" => $time]);
                    $created = $autoTimestamp ? (new \DateTime("now"))->format(IPlaylist::TIME_FORMAT_SQL) : null;
                    $entry->setCreated($created);
                } else if($created) {
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

            if($events || $dup)
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
        if(!$list)
            throw new ResourceNotFoundException("show", $key);

        if($list['dj'] != $session->getUser())
            throw new NotAllowedException("not owner");

        $show = $request->requestBody()->data()->first("show");
        $attrs = $show->attributes();

        // validate the show's properties
        $name = $attrs->has("name")?$attrs->getRequired("name"):$list['description'];
        $time = $attrs->has("time")?$attrs->getRequired("time"):$list['showtime'];
        $this->validateTime($time); // raises exception on invalid

        if($attrs->has("date")) {
            list($year, $month, $day) = explode("-", $date = $attrs->getRequired("date"));
            if(!checkdate($month, $day, $year))
                throw new \InvalidArgumentException("date is invalid");
        } else
            $date = $list['showdate'];

        if($attrs->has("airname")) {
            // lookup the airname
            $airname = $attrs->getRequired("airname");
            $aid = null;
            if(!empty($airname) && strcasecmp($airname, "none")) {
                $djapi = Engine::api(IDJ::class);
                $user = $session->getUser();
                $aid = $djapi->getAirname($airname, $user);
                if(!$aid) {
                    // if foreign and unchanged, keep it
                    if($list['airname'] &&
                            $djapi->getAirname($airname, "") == $list['airname'])
                        $aid = $list['airname'];
                    else {
                        // airname does not exist; try to create it
                        $success = $djapi->insertAirname(mb_substr($airname, 0, IDJ::MAX_AIRNAME_LENGTH), $user);
                        if($success > 0) {
                            // success!
                            $aid = $djapi->lastInsertId();
                        } else
                            throw new \InvalidArgumentException("airname is invalid");
                    }
                }
            }
        } else
            $aid = $list['airname'];

        if($api->isListDeleted($key)) {
            $api->restorePlaylist($key);

            // if caller is doing a restore AND update, and has not
            // specified the airname, we must fetch it after the restore
            if(!$attrs->isEmpty() && !$aid) {
                $list = $api->getPlaylist($key);
                $aid = $list['airname'];
            }
        }

        $success = $attrs->isEmpty() ? true :
                        $api->updatePlaylist($key, $date, $time, $name, $aid, true);

        if($success) {
            PushServer::sendAsyncNotification();
            return new EmptyResponse();
        }

        throw new BadRequestException("DB update error");
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

        PushServer::sendAsyncNotification();

        return new EmptyResponse();
    }

    private function injectMetadata($api, $rqMeta, $rsMeta, $listId, $hashStatus, $entry) {
        $action = $rqMeta->getOptional("action", "");
        $fragment = PlaylistBuilder::newInstance([
            "action" => $action,
            "editMode" => true,
            "authUser" => true
        ])->observe($entry);
        $rsMeta->set("html", $fragment);

        // seq is one of:
        //   -1     client playlist is out of sync with the service
        //   0      playlist is in natural order
        //   > 0    ordinal of inserted entry
        $rsMeta->set("seq", $hashStatus ?: $api->getSeq(0, $entry->getId()));

        // return hash code only if playlist is in sync
        if(!$hashStatus)
            $rsMeta->set("hash", $api->hashPlaylist($listId));

        // track is in the grace period?
        $window = $api->getTimestampWindow($listId, false);
        $rsMeta->set("runsover", $entry->getCreated() &&
                new \DateTime($entry->getCreated()) >= $window['end']);
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
        $list = $api->getPlaylist($key, 1);
        if(!$list || $api->isListDeleted($key))
            throw new ResourceNotFoundException("show", $key);

        if($list['dj'] != Engine::session()->getUser())
            throw new NotAllowedException("not owner");

        $event = $request->requestBody()->data()->first("event");
        $entry = PlaylistEntry::fromArray($event->attributes()->all());

        // set to 0 (in sync) else -1 (out of sync)
        $hashStatus = $event->metaInformation()->getOptional("hash");
        if(!is_null($hashStatus))
            $hashStatus = $hashStatus == $api->hashPlaylist($key) ? 0 : -1;

        try {
            $album = $event->relationships()->get("album")->related()->first("album");
            $albumrec = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $album->id());
            if(sizeof($albumrec)) {
                // don't allow modification of album info if tag is set
                $entry->setTag($album->id());
                if(!$albumrec[0]["iscoll"])
                    $entry->setArtist($albumrec[0]["artist"]);
                $entry->setAlbum($albumrec[0]["album"]);
                $entry->setLabel($albumrec[0]["name"]);
            }
        } catch(\Exception $e) {}

        // validate required fields
        $invalid = false;
        switch($entry->getType()) {
        case PlaylistEntry::TYPE_COMMENT:
            $invalid = empty($entry->getComment());
            break;
        case PlaylistEntry::TYPE_LOG_EVENT:
            $invalid = empty($entry->getLogEventType()) || empty($entry->getLogEventCode());
            break;
        case PlaylistEntry::TYPE_SPIN:
            $invalid = empty($entry->getArtist()) || empty($entry->getTrack());
            break;
        }

        if($invalid)
            throw new \InvalidArgumentException("missing required field");

        $autoTimestamp = false;
        $created = $entry->getCreated();
        if($created == "auto" || !$created && Engine::getApiVer() < 1.1) {
            $autoTimestamp = $api->isNowWithinShow($list);
            $created = $autoTimestamp ? (new \DateTime("now"))->format(IPlaylist::TIME_FORMAT_SQL) : null;
        }

        if($created) {
            $window = $api->getTimestampWindow($key);
            try {
                $stamp = PlaylistEntry::scrubTimestamp(new \DateTime($created), $window);
                if($stamp)
                    $entry->setCreated($stamp->format(IPlaylist::TIME_FORMAT_SQL));
                else
                    throw new \Exception("Time is outside show start/end times");
            } catch(\Exception $e) {
                throw new \InvalidArgumentException($e->getMessage());
            }
        } else
            $entry->setCreated(null);

        $status = '';
        $success = $api->insertTrackEntry($key, $entry, $status);

        if($success && $list['airname']) {
            if($autoTimestamp) {
                $list['id'] = $key;
                if($entry->isType(PlaylistEntry::TYPE_SPIN)) {
                    $spin = $entry->asArray();
                    if(isset($spin['tag']))
                        $spin['artist'] = PlaylistEntry::swapNames($spin['artist']);
                } else
                    $spin = null;

                PushServer::sendAsyncNotification($list, $spin);
            } else if($api->isNowWithinShow($list))
                PushServer::sendAsyncNotification();

            if(!$autoTimestamp && isset($stamp))
                PushServer::lazyLoadImages($key, $entry->getId());
        }

        if($success) {
            $res = new JsonResource("event", $entry->getId());
            if($event->metaInformation()->getOptional("wantMeta"))
                $this->injectMetadata($api, $event->metaInformation(), $res->metaInformation(), $key, $hashStatus, $entry);
            return new DocumentResponse(new Document($res));
        }

        throw new BadRequestException($status ?? "DB update error");
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

        $api->lockPlaylist($key);
        try {

        $id = $event->id();
        $track = $api->getTrack($id);
        if(!$track || $track['list'] != $key)
            throw new NotAllowedException("event not in list");

        // set to 0 (in sync) else -1 (out of sync)
        $hashStatus = $event->metaInformation()->getOptional("hash");
        if(!is_null($hashStatus))
            $hashStatus = $hashStatus == $api->hashPlaylist($key) ? 0 : -1;

        // TBD allow changes instead of complete relacement
        $entry = $event->attributes()->getOptional("type") ?
            PlaylistEntry::fromArray($event->attributes()->all()) :
            new PlaylistEntry($track);

        if($event->attributes()->getOptional("created") == "auto") {
            $created = $api->isNowWithinShow($list) ? (new \DateTime("now"))->format(IPlaylist::TIME_FORMAT_SQL) : null;
            $entry->setCreated($created);
        }

        $entry->setId($id);

        try {
            $album = $event->relationships()->get("album")->related()->first("album");
            $albumrec = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $album->id());
            if(sizeof($albumrec)) {
                // don't allow modification of album info if tag is set
                $entry->setTag($album->id());
                if(!$albumrec[0]["iscoll"])
                    $entry->setArtist($albumrec[0]["artist"]);
                $entry->setAlbum($albumrec[0]["album"]);
                $entry->setLabel($albumrec[0]["name"]);
            }
        } catch(\Exception $e) {}

        $created = $entry->getCreated();
        if($created) {
            $window = $api->getTimestampWindow($key);
            try {
                $stamp = PlaylistEntry::scrubTimestamp(new \DateTime($created), $window);
                if($stamp)
                    $entry->setCreated($stamp->format(IPlaylist::TIME_FORMAT_SQL));
                else
                    throw new \Exception("Time is outside show start/end times");
            } catch(\Exception $e) {
                throw new \InvalidArgumentException($e->getMessage());
            }
        }

        $success = $event->attributes()->isEmpty() ?
                        true : $api->updateTrackEntry($key, $entry);

        if($success &&
                ($moveTo = $event->metaInformation()->getOptional("moveTo")))
            $success = $api->moveTrack($key, $id, $moveTo);

        if($success && $event->metaInformation()->getOptional("wantMeta")) {
            $res = new JsonResource("event", $entry->getId());
            $this->injectMetadata($api, $event->metaInformation(), $res->metaInformation(), $key, $hashStatus, $entry);
            return new DocumentResponse(new Document($res));
        }

        } finally {
            $api->unlockPlaylist($key);
        }

        if($success && $list['airname'] && $api->isNowWithinShow($list))
            PushServer::sendAsyncNotification();

        if($success && isset($stamp))
            PushServer::lazyLoadImages($key, $id);

        if($success)
            return new EmptyResponse();

        throw new BadRequestException("DB update error");
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

        if($success)
            return new EmptyResponse();

        throw new BadRequestException("DB update error");
    }
}
