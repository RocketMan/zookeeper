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
use Enm\JsonApi\Server\RequestHandler\NoRelationshipModificationTrait;
use Enm\JsonApi\Server\RequestHandler\RequestHandlerInterface;


class Albums implements RequestHandlerInterface {
    use NoRelationshipModificationTrait;

    const FIELDS = [ "artist", "album", "category", "medium", "size",
                     "created", "updated", "location", "bin", "coll" ];

    const TRACK_FIELDS = [ "seq", "artist", "track", "url" ];

    const LINKS_NONE = 0;
    const LINKS_LABEL = 1;
    const LINKS_REVIEWS = 2;
    const LINKS_ALL = ~0;

    private const NONALNUM="/([\.,!\?&~ \-\+=\{\[\(\|\}\]\)])/";
    private const STOPWORDS="/^(a|an|and|at|but|by|for|in|nor|of|on|or|out|so|the|to|up|yet)$/i";

    /**
     * This is the PHP equivalent of the editor.common.js zkAlpha function
     */
    public static function zkAlpha($val, $isTrack=false) {
        $words = preg_split(self::NONALNUM, $val);
        $newVal = join(" ", array_map(function(int $index, string $word) use($words) {
            // words starting with caps are kept as-is
            if(preg_match('/^[A-Z]+/', $word))
                return $word;

            // stopwords are not capitalized, unless first or last
            if(preg_match(self::STOPWORDS, $word) &&
                        $index != 0 &&
                        $index != sizeof($words) - 1) {
                return strtolower($word);
            }

            // otherwise, capitalize the word
            return strtoupper(substr($word, 0, 1)) .
                    strtolower(substr($word, 1));
        }, array_keys($words), array_values($words)));

        if(!$isTrack && substr($newVal, 0, 4) == 'The ')
            $newVal = substr($newVal, 4) . ', The';

        return $newVal;
    }

    public static function getFieldValueToCodeMap() {
        return [
            ["category", array_flip(ILibrary::GENRES)],
            ["medium", array_flip(ILibrary::MEDIA)],
            ["format", array_flip(ILibrary::LENGTHS)],
            ["location", array_flip(ILibrary::LOCATIONS)]
        ];
    }

    public static function fromRecord($rec, $wantTracks = true) {
        $res = new JsonResource("album", $rec["tag"]);
        $res->links()->set(new Link("self", Engine::getBaseUrl()."album/".$rec["tag"]));
        foreach(self::FIELDS as $field) {
            switch($field) {
            case "category":
                $value = ILibrary::GENRES[$rec[$field]];
                break;
            case "medium":
                $value = ILibrary::MEDIA[$rec[$field]];
                break;
            case "size":
                $value = ILibrary::LENGTHS[$rec[$field]];
                break;
            case "location":
                $value = ILibrary::LOCATIONS[$rec[$field]];
                break;
            case "coll":
                $value = $rec["iscoll"]?true:false;
                break;
            default:
                $value = $rec[$field];
                break;
            }
            $res->attributes()->set($field, $value);
        }

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

            $res->attributes()->set("tracks", $albumTracks);
        }
        return $res;
    }

    public static function fromArray(array $records, $flags = self::LINKS_NONE) {
        $result = [];
        $labelMap = [];
        foreach($records as $record) {
            $resource = self::fromRecord($record);
            $result[] = $resource;

            if($flags & self::LINKS_REVIEWS) {
                $reviews = Engine::api(IReview::class)->getReviews($record["tag"]);
                if(sizeof($reviews)) {
                    $relations = new ResourceCollection();
                    $relation = new Relationship("reviews", $relations);
                    $relation->links()->set(new Link("related", Engine::getBaseUrl()."album/{$record["tag"]}/reviews"));
                    $resource->relationships()->set($relation);
                    foreach($reviews as $review) {
                        $res = Reviews::fromRecord($review);
                        $relations->set($res);
                    }
                }
            }

            if($flags & self::LINKS_LABEL) {
                if(array_key_exists($record["pubkey"], $labelMap))
                    $res = $labelMap[$record["pubkey"]];
                else {
                    $labels = Engine::api(ILibrary::class)->search(ILibrary::LABEL_PUBKEY, 0, 1, $record["pubkey"]);
                    if(sizeof($labels)) {
                        $res = Labels::fromRecord($labels[0]);
                        $labelMap[$record["pubkey"]] = $res;
                    } else
                        continue;
                }

                $relation = new Relationship("label", $res);
                $relation->links()->set(new Link("related", Engine::getBaseUrl()."album/{$record["tag"]}/label"));
                $relation->links()->set(new Link("self", Engine::getBaseUrl()."album/{$record["tag"]}/relationships/label"));
                $relation->metaInformation()->set("name", $labels[0]["name"]);
                $resource->relationships()->set($relation);
            }
        }

        return $result;
    }

    public static function fromAttrs($attrs, $required = false) {
        $album = [];

        // map field values to codes
        $maps = self::getFieldValueToCodeMap();
        foreach($maps as $field)
            if($attrs->has($field[0]) || $required) {
                $val = $attrs->getRequired($field[0]);
                if(!isset($field[1][$val]))
                    throw new \InvalidArgumentException("Value $val is not defined for property {$field[0]}");
                $album[$field[0]] = $field[1][$val];
            }

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
        $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $request->id());
        if(sizeof($albums) == 0)
            throw new ResourceNotFoundException("album", $request->id());

        $resource = self::fromArray($albums, self::LINKS_ALL);
        $document = new Document($resource[0]);
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

        $limit = $request->hasPagination("size")?
                $request->paginationValue("size"):null;
        if(!$limit || $limit > API::MAX_LIMIT)
            $limit = API::MAX_LIMIT;

        $records = Engine::api(ILibrary::class)->listAlbums($op, $key, $limit);
        $result = self::fromArray($records, self::LINKS_LABEL);
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

        $response = new DocumentResponse($document);
        return $response;
    }

    protected function fromTrackSearch(array $records) {
        $result = [];
        $map = [];
        foreach($records as $record) {
            $tag = $record["tag"];
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
                $resource = $map[$tag] = self::fromRecord($record, false);
                // we need to put this back so it is available for the track
                if($record["iscoll"])
                    $record["artist"] = $artist;
                $result[] = $resource;
                $tracks = [];

                $res = Labels::fromRecord($record);
                $relation = new Relationship("label", $res);
                $relation->links()->set(new Link("related", Engine::getBaseUrl()."album/{$record["tag"]}/label"));
                $relation->links()->set(new Link("self", Engine::getBaseUrl()."album/{$record["tag"]}/relationships/label"));
                $relation->metaInformation()->set("name", $record["name"]);
                $resource->relationships()->set($relation);
            }
            $fields = self::TRACK_FIELDS;
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

    protected function marshallReviews(array $records) {
        $result = [];
        foreach($records as $record) {
            $resource = self::fromRecord($record);
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

    protected function paginateOffset(RequestInterface $request): ResponseInterface {
        if($request->hasFilter("artist")) {
            $op = ILibrary::ALBUM_ARTIST;
            $key = $request->filterValue("artist");
            $filter = "filter%5Bartist%5D=" . urlencode($key);
        } else if($request->hasFilter("album")) {
            $op = ILibrary::ALBUM_NAME;
            $key = $request->filterValue("album");
            $filter = "filter%5Balbum%5D=" . urlencode($key);
        } else if($request->hasFilter("track")) {
            $op = ILibrary::TRACK_NAME;
            $key = $request->filterValue("track");
            $filter = "filter%5Btrack%5D=" . urlencode($key);
        } else if($request->hasFilter("label.id")) {
            $op = ILibrary::ALBUM_PUBKEY;
            $key = $request->filterValue("label.id");
            $filter = "filter%5Blabel.id%5D=" . urlencode($key);
        } else if($request->hasFilter("reviews.airname.id")) {
            $op = ILibrary::ALBUM_AIRNAME;
            $key = $request->filterValue("reviews.airname.id");
            $filter = "filter%5Breviews.airname.id%5D=" . urlencode($key);
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

        switch($op) {
        case ILibrary::ALBUM_AIRNAME:
            $result = $this->marshallReviews($records);
            break;
        case ILibrary::TRACK_NAME:
            $result = $this->fromTrackSearch($records);
            break;
        default:
            $result = self::fromArray($records, self::LINKS_ALL);
            break;
        }
        $document = new Document($result);

        $base = Engine::getBaseUrl()."album?{$filter}";
        $size = "&page%5Bprofile%5D=offset&page%5Bsize%5D=$limit";

        if($offset)
            $document->links()->set(new Link("next", "{$base}&page%5Boffset%5D={$offset}{$size}"));
        else
            $offset = $total; // no more rows remaining

        $link = new Link("first", "{$base}{$size}");
        $link->metaInformation()->set("total", $total);
        $link->metaInformation()->set("more", $total - $offset);
        $link->metaInformation()->set("offset", $reqOffset);
        $document->links()->set($link);

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
            $response = $this->paginateOffset($request);
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
            $labels = Engine::api(ILibrary::class)->search(ILibrary::LABEL_PUBKEY, 0, 1, $album["pubkey"]);
        
            if(sizeof($labels))
                $res = Labels::fromRecord($labels[0]);
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
            throw new ResourceNotFoundException("album", $request->relationship());
        }

        $document = new Document($res);
        $document->links()->set(new Link("self", Engine::getBaseUrl()."album/$key/relationships/".$request->relationship()));
        $document->links()->set(new Link("related", Engine::getBaseUrl()."album/$key/".$request->relationship()));

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
        if(sizeof($rec))
            $label = $rec[0];
        else {
            // if not found by pubkey, consult the included relation
            $lr = $request->requestBody()->included()->get("label", $id);

            // try to find by name
            $rec = Engine::api(ILibrary::class)->search(ILibrary::LABEL_NAME, 0, 1, $lr->attributes()->getRequired("name"));
            if(sizeof($rec))
                $label = $rec[0];
            else {
                $label = [];
                $la = $lr->attributes();
                foreach(Labels::FIELDS as $field)
                    if($la->has($field))
                        $label[$field] = $la->getOptional($field);
            }
        }

        // normalize label fields
        foreach(["name","attention","address","city","maillist"] as $field) {
            if(isset($label[$field]))
                $label[$field] = self::zkAlpha($label[$field], true);
        }

        if(Engine::api(IEditor::class)->insertUpdateAlbum($a, $tracks, $label))
            return new CreatedResponse(Engine::getBaseUrl()."album/{$a['tag']}");
        throw new \Exception("creation failed");
    }

    public function patchResource(RequestInterface $request): ResponseInterface {
        if(!Engine::session()->isAuth("m"))
            throw new NotAllowedException("Operation requires authentication");

        $key = $request->id();
        if(empty($key))
            throw new ResourceNotFoundException("album", $key ?? 0);

        $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $key);
        if(sizeof($albums) == 0)
            throw new ResourceNotFoundException("album", $key);

        $attrs = $request->requestBody()->data()->first("album")->attributes();
        [$album, $tracks] = self::fromAttrs($attrs);
        array_merge($albums[0], $album);
        Engine::api(IEditor::class)->insertUpdateAlbum($albums[0], $tracks, null);

        return new EmptyResponse();
    }

    public function deleteResource(RequestInterface $request): ResponseInterface {
        if(!Engine::session()->isAuth("m"))
            throw new NotAllowedException("Operation requires authentication");

        $key = $request->id();
        if(empty($key))
            throw new ResourceNotFoundException("album", $key ?? 0);

        Engine::api(IEditor::class)->deleteAlbum($id);

        return new EmptyResponse();
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

        $albums[0]["pubkey"] = $pubkey;
        Engine::api(IEditor::class)->insertUpdateAlbum($albums[0], null, $labels[0]);

        return new EmptyResponse();
    }
}
