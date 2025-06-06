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
use ZK\Engine\IArtwork;
use ZK\Engine\IEditor;
use ZK\Engine\ILibrary;
use ZK\Engine\IReview;

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


class Albums implements RequestHandlerInterface {
    use OffsetPaginationTrait;

    const FIELDS = [ "artist", "album", "category", "medium", "size",
                     "created", "updated", "location", "bin", "coll",
                     "albumart" ];

    const TRACK_FIELDS = [ "seq", "artist", "track", "duration", "url" ];

    const LINKS_NONE = 0;
    const LINKS_LABEL = 1;
    const LINKS_REVIEWS = 2;
    const LINKS_REVIEWS_WITH_BODY = 4;
    const LINKS_TRACKS = 8;
    const LINKS_ARTWORK = 16;
    const LINKS_ALL = ~0;

    private static $paginateOps = [
        "artist" =>               [ ILibrary::ALBUM_ARTIST, null ],
        "album" =>                [ ILibrary::ALBUM_NAME, null ],
        "location" =>             [ ILibrary::ALBUM_LOCATION, null ],
        "track" =>                [ ILibrary::TRACK_NAME, null ],
        "label.id" =>             [ ILibrary::ALBUM_PUBKEY, null ],
        "reviews.airname.id" =>   [ ILibrary::ALBUM_AIRNAME, null ],
        "reviews.hashtag" =>      [ ILibrary::ALBUM_HASHTAG, null ],
        "match(artist)" =>        [ -1, "artists" ],
        "match(artist,album)" =>  [ -1, "albums" ],
        "match(album,artist)" =>  [ -1, "albums" ],
        "match(artist,track)" =>  [ ILibrary::TRACK_NAME, "compilations" ],
        "match(track,artist)" =>  [ ILibrary::TRACK_NAME, "compilations" ],
        "match(track)" =>         [ ILibrary::TRACK_NAME, "tracks" ],
    ];

    private const NONALNUM='/([^\p{L}\d\'\x{2019}])/u';
    private const STOPWORDS="/^(a|an|and|at|but|by|for|in|nor|of|on|or|out|so|the|to|up|yet)$/i";

    /**
     * This is the PHP equivalent of the editor.common.js zkAlpha function
     */
    public static function zkAlpha($val, $isTrack=false) {
        $words = preg_split(self::NONALNUM, $val, 0, PREG_SPLIT_DELIM_CAPTURE);
        $newVal = join('', array_map(function(int $index, string $word) use($words) {
            // words starting with caps are kept as-is
            if(preg_match('/^\p{Lu}/u', $word))
                return $word;

            // stopwords are not capitalized, unless first or last
            if(preg_match(self::STOPWORDS, $word) &&
                        $index != 0 &&
                        $index != sizeof($words) - 1 &&
                        preg_match('/\s/', $words[$index - 1])) {
                return mb_strtolower($word);
            }

            // otherwise, capitalize the word
            return mb_strtoupper(mb_substr($word, 0, 1)) .
                    mb_strtolower(mb_substr($word, 1));
        }, array_keys($words), array_values($words)));

        if(!$isTrack && mb_substr($newVal, 0, 4) == 'The ')
            $newVal = mb_substr($newVal, 4) . ', The';

        return $newVal;
    }

    public static function getFieldValueToCodeMap() {
        return [
            ["category", array_flip(ILibrary::GENRES)],
            ["medium", array_flip(ILibrary::MEDIA)],
            ["size", array_flip(ILibrary::LENGTHS)],
            ["location", array_flip(ILibrary::LOCATIONS)]
        ];
    }

    public static function fromRecord($rec, $wantTracks = true) {
        $res = new JsonResource("album", $rec["tag"]);
        $res->links()->set(new Link("self", Engine::getBaseUrl()."album/".$rec["tag"]));
        foreach(self::FIELDS as $field) {
            $tfield = $field == "coll" ? "iscoll" : $field;
            if(!array_key_exists($tfield, $rec))
                continue;

            switch($field) {
            case "category":
                $value = ILibrary::GENRES[$rec[$field]];
                break;
            case "medium":
                $value = ILibrary::MEDIA[$rec[$field]];
                break;
            case "size":
                $value = ILibrary::LENGTHS[$rec[$field] ?? 'F'];
                break;
            case "location":
                $value = ILibrary::LOCATIONS[$rec[$field]];
                break;
            case "coll":
                $value = $rec["iscoll"]?true:false;
                break;
            case "bin":
                $value = $rec["location"] == ILibrary::LOCATION_STORAGE ? $rec[$field] ?? null : null;
                break;
            default:
                $value = $rec[$field] ?? null;
                break;
            }
            $res->attributes()->set($field, $value);
        }

        // the framework will remove any fields not requested, but track
        // retrieval is expensive, so we avoid it if not needed
        $albumTracks = null;
        if($wantTracks) {
            $fields = self::TRACK_FIELDS;
            if($rec["iscoll"])
                $key = ILibrary::COLL_KEY;
            else {
                $key = ILibrary::TRACK_KEY;
                $fields = array_diff($fields, ["artist"]);
            }
            $tracks = Engine::api(ILibrary::class)->search($key, 0, 200, $rec["tag"]);

            $albumTracks = [];
            foreach($tracks as $track) {
                $r = [];
                foreach($fields as $field)
                    $r[$field] = $track[$field];
                $albumTracks[] = $r;
            }
        }
        $res->attributes()->set("tracks", $albumTracks);
        return $res;
    }

    public static function fromArray(array $records, $flags = self::LINKS_NONE) {
        $result = [];
        $labelMap = [];

        // collect review information only if requested.
        //
        // review body retrieval is expensive, so unless
        // it will appear in the response, we avoid it.
        if($flags & self::LINKS_REVIEWS)
            Engine::api(ILibrary::class)->linkReviews($records, Engine::session()->isAuth("u"), $flags & self::LINKS_REVIEWS_WITH_BODY);

        // require authentication to prevent scraping of artwork
        if($flags & self::LINKS_ARTWORK && Engine::session()->isAuth("u"))
            Engine::api(IArtwork::class)->injectAlbumArt($records, Engine::getAppBasePath());

        foreach($records as $record) {
            $resource = self::fromRecord($record, $flags & self::LINKS_TRACKS);
            $result[] = $resource;

            if($flags & self::LINKS_REVIEWS && isset($record["reviews"])) {
                $relations = new ResourceCollection();
                $relation = new Relationship("reviews", $relations);
                $relation->links()->set(new Link("related", Engine::getBaseUrl()."album/{$record["tag"]}/reviews"));
                $resource->relationships()->set($relation);
                foreach($record["reviews"] as $review) {
                    $res = Reviews::fromRecord($review);
                    $relations->set($res);
                }
            }

            if($flags & self::LINKS_LABEL) {
                if(array_key_exists($record["pubkey"], $labelMap))
                    $res = $labelMap[$record["pubkey"]];
                else {
                    if($record["pubkey"]) {
                        $res = Labels::fromRecord($record);
                        $labelMap[$record["pubkey"]] = $res;
                    } else
                        continue;
                }

                $relation = new Relationship("label", $res);
                $relation->links()->set(new Link("related", Engine::getBaseUrl()."album/{$record["tag"]}/label"));
                $relation->links()->set(new Link("self", Engine::getBaseUrl()."album/{$record["tag"]}/relationships/label"));
                $relation->metaInformation()->set("name", $res->attributes()->getOptional("name"));
                $resource->relationships()->set($relation);
            }
        }

        return $result;
    }

    public static function fromAttrs($attrs, $required = false) {
        $album = [];

        foreach(self::FIELDS as $field) {
            if($attrs->has($field)) {
                $value = $attrs->getRequired($field);
                switch($field) {
                case "artist":
                case "album":
                    $value = self::zkAlpha($value);
                    break;
                default:
                    break;
                }
                $album[$field] = $value;
            }
        }

        // map field values to codes
        $maps = self::getFieldValueToCodeMap();
        foreach($maps as $field)
            if($attrs->has($field[0]) || $required) {
                $val = $attrs->getRequired($field[0]);
                if(!isset($field[1][$val]))
                    throw new \InvalidArgumentException("Value $val is not defined for property {$field[0]}");
                $album[$field[0]] = $field[1][$val];
            }

        $tracks = $attrs->getOptional("tracks");
        if($tracks) {
            // reindex tracks
            $tracks = array_combine(range(1, sizeof($tracks)),
                        array_values($tracks));

            // normalize track names
            foreach($tracks as &$track) {
                $track["track"] = self::zkAlpha($track["track"], true);
                if(isset($track["artist"]))
                    $track["artist"] = self::zkAlpha($track["artist"]);
            }
        }

        return [$album, $tracks];
    }

    public function fetchResource(RequestInterface $request): ResponseInterface {
        $id = $request->id();
        $printq = $id == "printq" && Engine::session()->isAuth("m");
        $albums = $printq ?
            Engine::api(IEditor::class)->getQueuedTags(Engine::session()->getUser())->asArray() :
            Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $id);
        if(sizeof($albums) == 0 && !$printq)
            throw new ResourceNotFoundException("album", $request->id());

        $resource = self::fromArray($albums, self::LINKS_ALL);
        $document = new Document($printq ? $resource : $resource[0]);
        $response = new DocumentResponse($document);
        return $response;
    }

    protected function paginateCursor(RequestInterface $request): ResponseInterface {
        // pagination according to the cursor pagination profile
        // https://jsonapi.org/profiles/ethanresnick/cursor-pagination/

        if($request->hasFilter("artist")) {
            $op = ILibrary::OP_BY_NAME;
            $key = $request->filterValue("artist");
        } else if($request->hasFilter("id")) {
            $op = ILibrary::OP_BY_TAG;
            $key = $request->filterValue("id");
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
            throw new NotAllowedException("must specify filter or page");

        $limit = $request->hasPagination("size") ?
                min($request->paginationValue("size"), ApiServer::MAX_LIMIT) :
                ApiServer::DEFAULT_LIMIT;

        $records = Engine::api(ILibrary::class)->listAlbums($op, $key, $limit);
        $links = self::LINKS_LABEL;
        $links |= $request->requestsField("album", "tracks") ? self::LINKS_TRACKS : 0;
        $links |= $request->requestsField("album", "albumart") ? self::LINKS_ARTWORK : 0;
        $result = self::fromArray($records, $links);
        $document = new Document($result);

        $base = Engine::getBaseUrl()."album?";
        $size = "&page%5Bprofile%5D=cursor&page%5Bsize%5D=$limit";

        $obj = $records[0];
        $prev = urlencode("{$obj["artist"]}|{$obj["album"]}|{$obj["tag"]}");
        $document->links()->set(new Link("prev", "{$base}page%5Bbefore%5D={$prev}{$size}"));
        $document->links()->set(new Link("prevLine", "{$base}page%5Bprevious%5D={$prev}{$size}"));
        $document->links()->set(new Link("nextLine", "{$base}page%5Bnext%5D={$prev}{$size}"));

        $obj = $records[sizeof($records)-1];
        $next = urlencode("{$obj["artist"]}|{$obj["album"]}|{$obj["tag"]}");
        $document->links()->set(new Link("next", "{$base}page%5Bafter%5D={$next}{$size}"));

        return new DocumentResponse($document);
    }

    public function fetchResources(RequestInterface $request): ResponseInterface {
        $profile = $request->hasPagination("profile")?
                   $request->paginationValue("profile"):"offset";
        switch($profile) {
        case "cursor":
            $response = $this->paginateCursor($request);
            break;
        case "offset":
            $links = self::LINKS_ALL;
            if(!$request->requestsInclude("reviews"))
                $links &= ~self::LINKS_REVIEWS_WITH_BODY;

            if(!$request->requestsField("album", "tracks"))
                $links &= ~self::LINKS_TRACKS;

            if(!$request->requestsField("album", "albumart"))
                $links &= ~self::LINKS_ARTWORK;

            $response = $this->paginateOffset($request, self::$paginateOps, $links);
            break;
        default:
            throw new NotAllowedException("unknown pagination profile '{$profile}'");
        }

        return $response;
    }

    public function fetchRelationship(RequestInterface $request): ResponseInterface {
        $key = $request->id();
        $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $key);
        if(sizeof($albums) == 0)
            throw new ResourceNotFoundException("album", $key);

        $album = $albums[0];

        switch($request->relationship()) {
        case "label":
            if($album["pubkey"])
                $res = Labels::fromRecord($album);
            break;
        case "reviews":
            $reviews = Engine::api(IReview::class)->getReviews($key);
            if(sizeof($reviews)) {
                $res = new ResourceCollection();
                foreach($reviews as $review) {
                    $r = Reviews::fromRecord($review);
                    $res->set($r);
                }
            }
            break;
        case "relationships":
            throw new NotAllowedException("unspecified relationship");
        default:
            throw new NotAllowedException('You are not allowed to fetch the relationship ' . $request->relationship());
        }

        $document = new Document($res);
        if($request->requestsAttributes())
            $document->links()->set(new Link("self", Engine::getBaseUrl()."album/$key/".$request->relationship()));
        else {
            $document->links()->set(new Link("self", Engine::getBaseUrl()."album/$key/relationships/".$request->relationship()));
            $document->links()->set(new Link("related", Engine::getBaseUrl()."album/$key/".$request->relationship()));
        }

        $response = new DocumentResponse($document);
        return $response;
    }

    public function createResource(RequestInterface $request): ResponseInterface {
        if(!Engine::session()->isAuth("m"))
            throw new NotAllowedException("Operation requires authentication");

        $album = $request->requestBody()->data()->first("album");
        $attrs = $album->attributes();

        $attrs->getRequired("artist");
        $attrs->getRequired("album");
        [$a, $tracks] = self::fromAttrs($attrs, true);

        // try to resolve the label by pubkey
        $id = $album->relationships()->get("label")->related()->first("label")->id();
        $rec = Engine::api(ILibrary::class)->search(ILibrary::LABEL_PUBKEY, 0, 1, $id);
        if(sizeof($rec)) {
            $a["pubkey"] = $id;
            $label = null;
        } else {
            // if not found by pubkey, consult the included relation
            $lr = $request->requestBody()->included()->get("label", $id);

            // try to find by name
            $rec = Engine::api(ILibrary::class)->search(ILibrary::LABEL_NAME, 0, 1, $lr->attributes()->getRequired("name"));
            if(sizeof($rec)) {
                $a["pubkey"] = $rec[0]["pubkey"];
                $label = null;
            } else {
                $label = [];
                $la = $lr->attributes();
                foreach(Labels::FIELDS as $field)
                    if($la->has($field))
                        $label[$field] = $la->getOptional($field);

                // normalize label fields
                foreach(["name","attention","address","city","maillist"] as $field) {
                    if(isset($label[$field]))
                        $label[$field] = self::zkAlpha($label[$field], true);
                }

                $a["pubkey"] = $label["pubkey"] = 0;
                $label["foreign"] = $label["international"] ?? false;
            }
        }

        $a["tag"] = 0;
        $a["format"] = $a["size"];
        if(Engine::api(IEditor::class)->insertUpdateAlbum($a, $tracks, $label)) {
            if($attrs->has('albumart'))
                Engine::api(IArtwork::class)->insertAlbumArt($a['tag'], $attrs->getRequired('albumart'), null);

            return new CreatedResponse(Engine::getBaseUrl()."album/{$a['tag']}");
        }
        throw new \Exception("creation failed");
    }

    public function patchResource(RequestInterface $request): ResponseInterface {
        if(!Engine::session()->isAuth("m"))
            throw new NotAllowedException("Operation requires authentication");

        $key = $request->id();
        if(empty($key))
            throw new BadRequestException("must specify id");

        $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $key);
        if(sizeof($albums) == 0)
            throw new ResourceNotFoundException("album", $key);

        $attrs = $request->requestBody()->data()->first("album")->attributes();
        [$album, $tracks] = self::fromAttrs($attrs);
        $albums[0]["coll"] = $albums[0]["iscoll"]; // pre-merge
        $albums[0] = array_merge($albums[0], $album);
        $albums[0]["format"] = $albums[0]["size"]; // post-merge
        Engine::api(IEditor::class)->insertUpdateAlbum($albums[0], $tracks, null);
        if($attrs->has('albumart')) {
            $aapi = Engine::api(IArtwork::class);
            $aapi->deleteAlbumArt($key);
            $aapi->insertAlbumArt($key, $attrs->getRequired('albumart'), null);
        }

        return new EmptyResponse();
    }

    public function deleteResource(RequestInterface $request): ResponseInterface {
        if(!Engine::session()->isAuth("m"))
            throw new NotAllowedException("Operation requires authentication");

        $key = $request->id();
        if(empty($key))
            throw new BadRequestException("must specify id");

        Engine::api(IEditor::class)->deleteAlbum($key);

        return new EmptyResponse();
    }

    public function addRelatedResources(RequestInterface $request): ResponseInterface {
        if($request->relationship() != "printq" || !Engine::session()->isAuth("m"))
            throw new NotAllowedException('You are not allowed to modify the relationship ' . $request->relationship());

        $tag = $request->id();
        $user = Engine::session()->getUser();
        if(Engine::api(IEditor::class)->enqueueTag($tag, $user))
            return new EmptyResponse();

        throw new \Exception("enqueue failed");
    }

    public function removeRelatedResources(RequestInterface $request): ResponseInterface {
        if($request->relationship() != "printq" || !Engine::session()->isAuth("m"))
            throw new NotAllowedException('You are not allowed to modify the relationship ' . $request->relationship());

        $tag = $request->id();
        if(Engine::api(IEditor::class)->dequeueTag($tag, Engine::session()->getUser()))
            return new EmptyResponse();

        throw new \Exception("dequeue failed");
    }

    public function replaceRelatedResources(RequestInterface $request): ResponseInterface {
        if($request->relationship() != "label")
            throw new NotAllowedException('You are not allowed to modify the relationship ' . $request->relationship());

        if(!Engine::session()->isAuth("m"))
            throw new NotAllowedException("Operation requires authentication");

        $pubkey = $request->requestBody()->data()->first("label")->id();
        $labels = Engine::api(ILibrary::class)->search(ILibrary::LABEL_PUBKEY, 0, 1, $pubkey);
        if(sizeof($labels) == 0)
            throw new ResourceNotFoundException("label", $pubkey);

        $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $request->id());
        if(sizeof($albums) == 0)
            throw new ResourceNotFoundException("album", $request->id());

        $albums[0]["format"] = $albums[0]["size"];
        $albums[0]["pubkey"] = $pubkey;
        Engine::api(IEditor::class)->insertUpdateAlbum($albums[0], null, null);

        return new EmptyResponse();
    }
}
