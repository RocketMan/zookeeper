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

namespace ZK\Controllers;

use ZK\Engine\Engine;
use ZK\Engine\IChart;
use ZK\Engine\IDJ;
use ZK\Engine\IEditor;
use ZK\Engine\ILibrary;
use ZK\Engine\IPlaylist;
use ZK\Engine\IReview;
use ZK\Engine\JsonApi;
use ZK\Engine\OnNowFilter;
use ZK\Engine\PlaylistObserver;

use ZK\UI\UICommon as UI;

use JsonApiPhp\JsonApi\Attribute;
use JsonApiPhp\JsonApi\CompoundDocument;
use JsonApiPhp\JsonApi\DataDocument;
use JsonApiPhp\JsonApi\Error;
use JsonApiPhp\JsonApi\Error\Code;
use JsonApiPhp\JsonApi\Error\Id;
use JsonApiPhp\JsonApi\Error\Title;
use JsonApiPhp\JsonApi\ErrorDocument;
use JsonApiPhp\JsonApi\Included;
use JsonApiPhp\JsonApi\Link\RelatedLink;
use JsonApiPhp\JsonApi\Link\SelfLink;
use JsonApiPhp\JsonApi\Meta;
use JsonApiPhp\JsonApi\ResourceCollection;
use JsonApiPhp\JsonApi\ResourceIdentifier;
use JsonApiPhp\JsonApi\ResourceIdentifierCollection;
use JsonApiPhp\JsonApi\ResourceObject;
use JsonApiPhp\JsonApi\ToMany;
use JsonApiPhp\JsonApi\ToOne;

abstract class Serializer {
    private $catCodes;

    public abstract function getContentType();
    public abstract function startDocument();
    public abstract function endDocument();
    public abstract function startResponse($name, $attrs=null);
    public abstract function endResponse($name);
    public abstract function emitDataSetArray($name, $fields, $data);
    public abstract function emitDocument($name, $doc);

    protected function getCatCodes() {
        if(!isset($this->catCodes))
            $this->catCodes = Engine::api(IChart::class)->getCategories();

        return $this->catCodes;
    }

    public function newAttrs($code = 0, $message = "Success") {
        $attrs = [];
        $attrs["code"] = $code;
        $attrs["message"] = $message;
        return $attrs;
    }

    public function emitError($request, $code, $message, $opts = null) {
        $attrs = $this->newAttrs($code, $message);
        if($opts)
            $attrs += $opts;
        $attrs["errors"] = [["id" => $attrs["id"]?$attrs["id"]:1, "code" => $code, "title" => $message]];
        $this->startResponse($request, $attrs);
        $this->endResponse($request);
    }

    public function emitDataSet($name, $fields, $rows) {
        $data = $rows->asArray();
        $this->emitDataSetArray($name, $fields, $data);
    }
}

class JSONSerializer extends Serializer {
    const CONTENT_TYPE = "application/vnd.api+json; charset=UTF-8";

    private $nextToken = "";
    private $hasErrors = false;

    public function getContentType() { return JSONSerializer::CONTENT_TYPE; }

    public function startDocument() {}
    public function endDocument() {}

    private function emitAttrs($attrs, $array = false) {
        $next = "";
        foreach($attrs as $key => $value) {
            echo "${next}";
            if(!$array)
                echo "\"$key\":";
            if(is_array($value)) {
                $indexed = isset($value[0]);
                echo $indexed?"[":"{";
                $this->emitAttrs($value, $indexed);
                echo $indexed?"]":"}";
            } else if(is_bool($value))
                echo $value?"true":"false";
            else if($key == "date")
                echo "\"".substr($value, 0, 10)."\"";
            else
                echo "\"".self::jsonspecialchars($value)."\"";
            $next = ",";
        }
    }

    public function startResponse($name, $attrs=null) {
        if(!$attrs || isset($attrs['errors']) && !sizeof($attrs['errors']))
            $attrs = $this->newAttrs();
        else if(!isset($attrs['code']) && isset($attrs['errors'])) {
            $attrs['code'] = $attrs['errors'][0]['code'];
            $attrs['message'] = $attrs['errors'][0]['title'];
        }

        echo $this->nextToken;
        $this->nextToken = "";

        echo "{\"type\":\"$name\"";
        if($attrs && sizeof($attrs)) {
            echo ",";
            $this->emitAttrs($attrs);
        }

        if(!$this->hasErrors)
            echo ",\"data\":[";
    }

    public function endResponse($name) {
        echo $this->hasErrors?"}":"]}";
        $this->hasErrors = false;
        $this->nextToken = ",";
    }

    private function getAFileCatList($cats) {
        $result = "";

        if($cats) {
            $catCodes = $this->getCatCodes();

            $cats = explode(",", $cats);
            foreach($cats as $cat)
                if(substr($catCodes[$cat-1]["name"], 0, 1) != "(")
                    $result .= ',"' . $catCodes[$cat-1]["name"] . '"';
        }

        $result = "[" . substr($result, 1) . "]";

        return $result;
    }

    public function emitDataSetArray($name, $fields, $data) {
        $nextToken = "";
        foreach($data as $row) {
            echo "$nextToken{";
            $nextProp = "";
            foreach($fields as $field) {
                $val = $row[$field] ?? "";
                if($name == "albumrec") {
                    switch($field) {
                    case "category":
                        $val = ILibrary::GENRES[$val];
                        break;
                    case "medium":
                        $val = ILibrary::MEDIA[$val];
                        break;
                    case "size":
                        $val = ILibrary::LENGTHS[$val];
                        break;
                    case "location":
                        $val = ILibrary::LOCATIONS[$val];
                        break;
                    case "afile_category":
                        $val = $this->getAFileCatList($val);
                        echo "$nextProp\"charts\": $val";
                        $nextProp = ",";
                        continue 2;
                    }
                } else if($field == "created") {
                    $val = ExportPlaylist::extractTime($val);
                } else if($field == "date") {
                    $val = substr($val, 0, 10);
                }
                echo "$nextProp\"$field\":";
                if(is_array($val)) {
                    $indexed = isset($val[0]);
                    echo $indexed?"[":"{";
                    $this->emitAttrs($val, $indexed);
                    echo $indexed?"]":"}";
                } else
                    echo "\"".
                         self::jsonspecialchars(stripslashes($val)).
                         "\"";
                $nextProp = ",";
            }

            $nextToken = ",";
            echo "}";
        }
    }

    public function emitDocument($name, $doc) {
        echo json_encode($doc);
    }

    public function emitError($request, $code, $message, $opts = null) {
        $error = new ErrorDocument(
            new Error(
                new Id('1'),
                new Code($code),
                new Title($message)));
        $this->emitDocument($request, $error);
    }

    private static function jsonspecialchars($str) {
        // escape backslash, quote, LF, CR, and tab
        $str1 = str_replace(["\\", "\"", "\n", "\r", "\t"],
                            ["\\\\", "\\\"", "\\n", "\\r", "\\t"], $str);

        // escape other control characters
        $str2 = preg_replace_callback('/[\x00-\x1f]/', function($matches) {
            return '\\u00' . bin2hex($matches[0]);
        }, $str1);

        if($str1 != $str2)
            error_log("unexpected control character(s): '$str'");

        return $str2;
    }
}

class XMLSerializer extends Serializer {
    const CONTENT_TYPE = "text/xml";

    public function getContentType() { return XMLSerializer::CONTENT_TYPE; }

    public function startDocument() {
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<xml>\n";
    }

    public function endDocument() {
        echo "</xml>\n";
    }

    public function startResponse($name, $attrs=null) {
        if(!$attrs || isset($attrs['errors']) && !sizeof($attrs['errors']))
            $attrs = $this->newAttrs();
        else if(!isset($attrs['code']) && isset($attrs['errors'])) {
            $attrs['code'] = $attrs['errors'][0]['code'];
            $attrs['message'] = $attrs['errors'][0]['title'];
        }

        echo "<$name";
        foreach($attrs as $key => $value) {
            if(is_bool($value))
                $value = $value?"true":"false";
            if(!is_array($value))
                echo " $key=\"".self::spec2hexAttr($value)."\"";
        }
        echo ">\n";
    }

    public function endResponse($name) {
        echo "</$name>\n";
    }

    private function getAFileCatList($cats) {
        $result = "";

        if($cats) {
            $catCodes = $this->getCatCodes();

            $cats = explode(",", $cats);
            foreach($cats as $cat)
                if(substr($catCodes[$cat-1]["name"], 0, 1) != "(")
                    $result .= "<chart>".$catCodes[$cat-1]["name"]."</chart>";
        }

        return $result;
    }

    public function emitDataSetArray($name, $fields, $data) {
        foreach($data as $row) {
            echo "<$name>\n";
            foreach($fields as $field) {
                $val = $row[$field] ?? "";
                if($name == "albumrec") {
                    switch($field) {
                    case "category":
                        $val = ILibrary::GENRES[$val];
                        break;
                    case "medium":
                        $val = ILibrary::MEDIA[$val];
                        break;
                    case "size":
                        $val = ILibrary::LENGTHS[$val];
                        break;
                    case "location":
                        $val = ILibrary::LOCATIONS[$val];
                        break;
                    case "afile_category":
                        $val = $this->getAFileCatList($val);
                        echo "<charts>$val</charts>\n";
                        continue 2;
                    }
                } else if($field == "created") {
                    $val = ExportPlaylist::extractTime($val);
                } else if($field == "date") {
                    $val = substr($val, 0, 10);
                }
                if(!is_array($val))
                    echo "<$field>".
                         self::spec2hex(stripslashes($val)).
                         "</$field>\n";
            }
            echo "</$name>\n";
        }
    }

    private function emitArray($eltname, $a) {
        switch($eltname) {
        case "getAlbumRs":
            $eltname = "trackrec";
            break;
        case "getPlaylistsRs":
            $eltname = "event";
            break;
        default:
            // unknown, should not get here, default to something
            $eltname = "track";
            break;
        }

        foreach($a as $element)  {
            echo "<{$eltname}>\n";
            foreach($element as $name => $value) {
                if(empty($value) || is_string($value))
                    echo "<{$name}>$value</{$name}>\n";
            }
            echo "</{$eltname}>\n";
        }
    }

    public function emitDocument($rootname, $doc) {
        if($doc instanceof \JsonSerializable) {
            $root = $doc->jsonSerialize();
            echo "<{$rootname}>\n";
            foreach($root->data as $node) {
                echo "<{$node->type} ";
                echo "id=\"".self::spec2hex($node->id)."\"";
                foreach($node->attributes as $name => $value) {
                    if(empty($value) || is_string($value))
                        echo " {$name}=\"".self::spec2hex($value)."\"";
                    else if(is_bool($value))
                        echo " {$name}=\"".($value?"true":"false")."\"";
                }
                echo ">\n";

                // zkapi responses contain only one contained-list property
                //
                // If this ever changes, the following code will need
                // revisiting.
                foreach($node->attributes as $name => $value) {
                    if(is_array($value)) {
                        $this->emitArray($rootname, $value);
                        break;
                    }
                }
                echo "</{$node->type}>\n";
            }
            echo "</{$rootname}>\n";
        }
    }

    private static function spec2hex($str) {
        return preg_replace("/[[:cntrl:]]/", "", htmlspecialchars($str, ENT_XML1, 'UTF-8'));
    }

    private static function spec2hexAttr($str) {
        return str_replace("\"", "&quot;", self::spec2hex($str));
    }
}

class API extends CommandTarget implements IController {
    const MAX_LIMIT = 35;

    const ALBUM_FIELDS = [
        "tag", "artist", "album", "category", "medium",
        "size", "location", "bin", "created", "updated",
        "pubkey", "name", "address", "city", "state", "zip",
        "reviewed"
    ];

    const LABEL_FIELDS = [
        "pubkey", "name", "attention", "address", "city",
        "state", "zip", "phone", "fax", "email", "url",
        "mailcount", "maillist", "international",
        "pcreated", "modified"
    ];

    const PLAYLIST_FIELDS = [
        "list", "description", "airname", "showdate",
        "artist", "album", "track"
    ];

    const REVIEW_FIELDS = [
        "tag", "artist", "album", "airname", "reviewed",
        "pubkey", "name"
    ];

    const REVIEW_FIELDS_EXT = [
        "airname", "date", "review"
    ];

    const TRACK_FIELDS = [
        "tag", "artist", "album", "category", "medium",
        "size", "location", "bin", "created", "updated",
        "name", "address", "city", "state", "zip", "pubkey",
        "track"
    ];

    const PLAYLIST_DETAIL_FIELDS = [
        "type", "comment", "artist", "track", "album", "label", "tag", "event", "code", "created"
    ];

    const TRACK_DETAIL_FIELDS = [
        "seq", "artist", "track", "url"
    ];

    private static $ftFields = [
        "tags" => API::ALBUM_FIELDS,
        "albums" => API::ALBUM_FIELDS,
        "labels" => API::LABEL_FIELDS,
        "playlists" => API::PLAYLIST_FIELDS,
        "reviews" => API::REVIEW_FIELDS,
        "compilations" => API::TRACK_FIELDS,
        "tracks" => API::TRACK_FIELDS,
    ];

    private static $libKeys = [
        "albums" => [ ILibrary::ALBUM_NAME, "albumrec", API::ALBUM_FIELDS ],
        "albumsByPubkey" => [ ILibrary::ALBUM_PUBKEY, "albumrec", API::ALBUM_FIELDS ],
        "artists" => [ ILibrary::ALBUM_ARTIST, "albumrec", API::ALBUM_FIELDS ],
        "labels" => [ ILibrary::LABEL_NAME, "labelrec", API::LABEL_FIELDS ],
        "reviews" => [ ILibrary::ALBUM_AIRNAME, "reviewrec", API::REVIEW_FIELDS ],
        "tracks" => [ ILibrary::TRACK_NAME, "albumrec", API::TRACK_FIELDS ],
    ];

    private static $methods = [
        [ "", "unknownMethod" ],
        [ "getAlbumsRq", "albumPager" ],
        [ "getLabelsRq", "labelPager" ],
        [ "searchRq", "fullTextSearch" ],
        [ "libLookupRq", "libraryLookup" ],
        [ "getCurrentsRq", "getCurrents" ],
        [ "getChartsRq", "getCharts" ],
        [ "getPlaylistsRq", "getPlaylists" ], // LEGACY **to be deleted**
        [ "albumRq", "getAlbum", "GET" ],
        [ "albumRq", "importAlbum", "POST" ],
        [ "albumRq", "importAlbum", "PUT" ],
        [ "albumRq", "deleteAlbum", "DELETE" ],
        [ "playlistRq", "getPlaylists", "GET" ],
        [ "playlistRq", "importPlaylist", "POST" ],
        [ "playlistRq", "deletePlaylist", "DELETE" ],
        [ "labelRq", "getLabel", "GET" ],
        [ "reviewRq", "getReview", "GET" ],
        [ "reviewRq", "importReview", "POST" ],
        [ "reviewRq", "importReview", "PUT" ],
        [ "reviewRq", "deleteReview", "DELETE" ],
    ];

    /*
     * These APIs permit cross-origin scripting across all origins.
     */
    private static $publicMethods = [
        "getPlaylistsRq"
    ];

    /*
     * Allowed HTTP methods for pre-flighted CORS requests.
     * Technically, this should be unnecessary, as the spec
     * provides a default set of GET, HEAD, and POST.
     * We provide an explicit response just to be sure.
     */
    private static $defaultACAM = "GET, HEAD, POST";

    private static $pager_operations = [
        "prevLine" => ILibrary::OP_PREV_LINE,
        "nextLine" => ILibrary::OP_NEXT_LINE,
        "prevPage" => ILibrary::OP_PREV_PAGE,
        "nextPage" => ILibrary::OP_NEXT_PAGE,
        "searchByName" => ILibrary::OP_BY_NAME,
        "searchByTag" => ILibrary::OP_BY_TAG,
    ];

    private $limit;
    private $serializer;
    private $base;

    public function processRequest() {
        $wantXml = $_REQUEST["xml"] ?? false || isset($_SERVER["HTTP_ACCEPT"]) &&
                substr($_SERVER["HTTP_ACCEPT"], 0, 8) == "text/xml";
        $this->serializer = $wantXml?new XMLSerializer():new JSONSerializer();

        if($this->emitHeader($_REQUEST["method"]))
            return;

        $this->session = Engine::session();
        $this->limit = $_REQUEST["size"];
        if(!$this->limit || $this->limit > API::MAX_LIMIT)
            $this->limit = API::MAX_LIMIT;
        $this->serializer->startDocument();
        $this->processLocal($_REQUEST["method"], null);
        $this->serializer->endDocument();
    }

    public function processLocal($action, $subaction) {
        $apiver = $_REQUEST["apiver"] ?? 1;
        $this->base = UI::getBaseUrl();
        if(($pos = strpos($this->base, "api/v")) !== false)
            $this->base = substr($this->base, 0, $pos);
        $this->base .= "api/v{$apiver}";

        $this->dispatchAction($action, self::$methods);
    }

    public function unknownMethod() {
        $method = $_REQUEST["method"];
        $this->serializer->emitError($method?$method:"status", 10, "Invalid method: $method");
    }

    public function albumPager() {
        $op = self::$pager_operations[$_REQUEST["operation"]];
        if(isset($op)) {
            $records = Engine::api(ILibrary::class)->listAlbums(
                     $op,
                     $_REQUEST["key"],
                     $this->limit);
            $this->serializer->startResponse("getAlbumsRs");
            $this->serializer->emitDataSetArray("albumrec", API::ALBUM_FIELDS, $records);
            $this->serializer->endResponse("getAlbumsRs");
        } else
            $this->serializer->emitError("getAlbumsRs", 20, "Invalid operation: ".$_REQUEST["operation"]);
    }

    public function labelPager() {
        $op = self::$pager_operations[$_REQUEST["operation"]];
        if(isset($op)) {
            $records = Engine::api(ILibrary::class)->listLabels(
                     $op,
                     $_REQUEST["key"],
                     $this->limit);
            $this->serializer->startResponse("getLabelsRs");
            $this->serializer->emitDataSetArray("labelrec", API::LABEL_FIELDS, $records);
            $this->serializer->endResponse("getLabelsRs");
        } else
            $this->serializer->emitError("getLabelsRs", 20, "Invalid operation: ".$_REQUEST["operation"]);
    }

    public function fullTextSearch() {
        $results = Engine::api(ILibrary::class)->searchFullText(
            $_REQUEST["type"], $_REQUEST["key"], $this->limit,
            $_REQUEST["offset"]);
        $attrs = $this->serializer->newAttrs();
        $attrs["total"] = $results[0];
        $attrs["dataType"] = $_REQUEST["type"];
        $this->serializer->startResponse("searchRs", $attrs);
        foreach ($results[1] as $result) {
            $attrs = [];
            $attrs["more"] = $result["more"];
            $attrs["offset"] = $result["offset"];
            $this->serializer->startResponse($result["type"], $attrs);
            $this->serializer->emitDataSetArray($result["recordName"], self::$ftFields[$result["type"]], $result["result"]);
            $this->serializer->endResponse($result["type"]);
        }
        $this->serializer->endResponse("searchRs");
    }

    public function libraryLookup() {
        $type = self::$libKeys[$_REQUEST["type"]];
        if(!$type) {
            $this->serializer->emitError("libLookupRs", 20, "Invalid type: ".$_REQUEST["type"]);
            return;
        }

        $key = $_REQUEST["key"];
        $offset = $_REQUEST["offset"];
        $libraryAPI = Engine::api(ILibrary::class);
        $total = $libraryAPI->searchPos($type[0], $offset, -1, $key);
        $attrs = $this->serializer->newAttrs();
        $attrs["total"] = $total;
        $attrs["dataType"] = $_REQUEST["type"];
        $this->serializer->startResponse("libLookupRs", $attrs);

        $results = $libraryAPI->searchPos($type[0], $offset, $this->limit, $key, $_REQUEST["sortBy"]);
        switch($type[0]) {
        case ILibrary::LABEL_NAME:
        case ILibrary::ALBUM_AIRNAME:
            break;
        default:
            $libraryAPI->markAlbumsReviewed($results, $this->session->isAuth("u"));
            break;
        }
        $attrs = [];
        // conform to the more/offset semantics of searchRq
        if(!$offset) $offset = $total; // no more rows remaining
        $attrs["more"] = $_REQUEST["offset"] == ""?($total - $offset):$total;
        $attrs["offset"] = $_REQUEST["offset"];
        $this->serializer->startResponse($_REQUEST["type"], $attrs);
        $this->serializer->emitDataSetArray($type[1], $type[2], $results);
        $this->serializer->endResponse($_REQUEST["type"]);
        $this->serializer->endResponse("libLookupRs");
    }

    public function getCharts() {
        $date = $_REQUEST["date"];

        $chartfields = API::ALBUM_FIELDS;
        array_push($chartfields, "label", "rank");
        self::array_remove($chartfields, "name", "address", "city", "state", "zip", "location", "bin", "created", "updated");
    
        if(!$date) {
             $weeks = Engine::api(IChart::class)->getChartDates(1);
             $week = $weeks->fetch();
             $date = $week["week"];
        }
    
        $this->serializer->startResponse("getChartsRs");
        
        // main chart
        Engine::api(IChart::class)->getChart($records, 0, $date, 30);

        $attrs = [];
        $attrs["chart"] = "General";
        $attrs["week-ending"] = $date;
        $this->serializer->startResponse("chart", $attrs);
        $this->serializer->emitDataSetArray("albumrec", $chartfields, $records, 0);
        $this->serializer->endResponse("chart");
    
        // genre charts
        $catCodes = Engine::api(IChart::class)->getCategories();

        $genres = [5, 7, 6, 1, 2, 4, 3, 8];
        for($i=0; $i<sizeof($genres); $i++) {
             unset($records);
             Engine::api(IChart::class)->getChart($records, 0, $date, 10, $genres[$i]);
             $attrs = [];
             $attrs["chart"] = $catCodes[$genres[$i]-1]["name"];
             $attrs["week-ending"] = $date;
             $this->serializer->startResponse("chart", $attrs);
             $this->serializer->emitDataSetArray("albumrec", $chartfields, $records, 0);
             $this->serializer->endResponse("chart");
        }
        
        $this->serializer->endResponse("getChartsRs");
    }
    
    public function getPlaylists() {
        $id = 0;
        $key = $_REQUEST["key"];
        $includeTracks = $_REQUEST["includeTracks"];
        $filter = null;

        if(!empty($_REQUEST["id"])) {
            $key = $_REQUEST["id"];
            $includeTracks = "true";
            $_REQUEST["operation"] = "byID";
        }

        switch($_REQUEST["operation"]) {
        case "byID":
            $row = Engine::api(IPlaylist::class)->getPlaylist($key, 1);
            $published = !$row || $row['airname'] || $row['dj'] == $this->session->getUser();
            $result = new SingleRowIterator($published?$row:false);
            $id = $key;
            break;
        case "byDate":
            $result = Engine::api(IPlaylist::class)->getPlaylistsByDate($key);
            break;
        case "onNow":
            $result = Engine::api(IPlaylist::class)->getWhatsOnNow();
            $filter = OnNowFilter::class;
            break;
        default:
            if(isset($_REQUEST["operation"]))
                $this->serializer->emitError("getPlaylistsRs", 20, "Invalid operation: ".$_REQUEST["operation"]);
            else
                $this->serializer->emitError("getPlalistsRs", 20, "missing id");
            return;
        }

        $rshow = [];
        while($row = $result->fetch()) {
            $tracks = [];
            if($includeTracks && $includeTracks != "false") {
                $events = [];
                Engine::api(IPlaylist::class)->getTracksWithObserver($id?$id:$row["id"],
                    (new PlaylistObserver())->onComment(function($entry) use(&$events) {
                        $events[] = ["type" => "comment",
                                     "comment" => $entry->getComment(),
                                     "created" => $entry->getCreated()];
                    })->onLogEvent(function($entry) use(&$events) {
                        $events[] = ["type" => "logEvent",
                                     "event" => $entry->getLogEventType(),
                                     "code" => $entry->getLogEventCode(),
                                     "created" => $entry->getCreated()];
                    })->onSetSeparator(function($entry) use(&$events) {
                        $events[] = ["type" => "break",
                                     "created" => $entry->getCreated()];
                    })->onSpin(function($entry) use(&$events) {
                        $spin = $entry->asArray();
                        $spin["type"] = "track";
                        $spin["artist"] = UI::swapNames($spin["artist"]);
                        unset($spin["id"]);
                        $events[] = $spin;
                    }), 0, $filter);

                  if(sizeof($events))
                      $tracks[] = new Attribute("events", $events);
            }

            $rshow[] = new ResourceObject('show', $id?$id:$row["id"],
                new Attribute("name", $row["description"]),
                new Attribute("date", $row["showdate"]),
                new Attribute("time", $row["showtime"]),
                new Attribute("airname", $row["airname"]),
                ...$tracks);
        }

        if($_REQUEST["operation"] != "byID" || sizeof($rshow)) {
            $dd = new DataDocument(new ResourceCollection(...$rshow));
            $this->serializer->emitDocument("getPlaylistsRs", $dd);
        } else
            $this->serializer->emitError("getPlaylistsRs", 200, "invalid id $key");
    }

    private static function getAttributes($fields, $record) {
        $result = [];
        foreach($fields as $field) {
            $value = $record[$field];
            if($field == "created") {
                $value = ExportPlaylist::extractTime($value);
            } else if($field == "date") {
                $value = substr($value, 0, 10);
            }
            $result[] = new Attribute($field, $value);
        }
        return $result;
    }

    public function getAlbum() {
        $key = $_REQUEST["id"];
        $include = explode(",", $_REQUEST["include"] ?? "");
        $fields = API::TRACK_DETAIL_FIELDS;
        $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $key);

        if(!empty($_REQUEST["relation"])) {
            $relation = $_REQUEST["relation"];
            unset($_REQUEST["relation"]);
            switch($relation) {
            case "reviews":
                $this->getReview(0);
                break;
            case "label":
                if(sizeof($albums)) {
                    header("Location: {$this->base}/label/{$albums[0]['pubkey']}");
                    return;
                }
                // fall through...
            default:
                http_response_code(400);
                break;
            }
            return;
        }

        if(!$key || !sizeof($albums)) {
            $this->serializer->emitError("getAlbumRs", 100, "Unknown tag $key");
            return;
        }

        $labels = Engine::api(ILibrary::class)->search(ILibrary::LABEL_PUBKEY, 0, 1, $albums[0]["pubkey"]);

        $artist = strcmp(substr($albums[0]["artist"], 0, 8), "[coll]: ")?
                      $albums[0]["artist"]:"Various Artists";
        $label = sizeof($labels)?$labels[0]["name"]:"(Unknown)";

        if($albums[0]["iscoll"])
            $records = Engine::api(ILibrary::class)->search(ILibrary::COLL_KEY, 0, 200, $key);
        else {
            $records = Engine::api(ILibrary::class)->search(ILibrary::TRACK_KEY, 0, 200, $key);
            self::array_remove($fields, "artist");
        }

        $rc = [];
        $rcd = [];
        $incc = [];
        if(sizeof($labels)) {
            $rcd[] = new ToOne("label",
                     new ResourceIdentifier("label", $albums[0]["pubkey"]),
                     new RelatedLink("{$this->base}/album/$key/label"),
                     new Meta("name", $label));

            if(in_array("label", $include)) {
                $l = $labels[0];
                $incc[] = new ResourceObject("label", $l["pubkey"],
                        new SelfLink("{$this->base}/label/{$l['id']}"),
                            ...self::getAttributes(API::LABEL_FIELDS, $l));
            }
        }

        $reviews = Engine::api(IReview::class)->getReviews($key);
        if(sizeof($reviews)) {
            foreach($reviews as $review) {
                $rc[] = new ResourceIdentifier("review", $review['id']);
                if(in_array("reviews", $include)) {
                    $review["date"] = $review["created"];
                    $incc[] = new ResourceObject("review", $review['id'],
                       new SelfLink("{$this->base}/review/{$review['id']}"),
                       new ToOne("album",
                           new ResourceIdentifier("album", $review['tag'])),
                       ...self::getAttributes(API::REVIEW_FIELDS_EXT, $review));
                }
            }
        }

        if(!empty($rc))
            $rcd[] = new ToMany("reviews",
                        new ResourceIdentifierCollection(...$rc),
                        new RelatedLink("{$this->base}/album/$key/reviews"));

        if(!empty($records)) {
            $out = [];
            foreach($records as &$record) {
                $r = [];
                foreach($fields as $field)
                    $r[$field] = $record[$field];
                $out[] = $r;
            }
            $rcd[] = new Attribute("tracks", $out);
        }

        $ralbum = new ResourceObject('album', $key,
                new SelfLink("{$this->base}/album/$key"),
                new Attribute("artist", $artist),
                new Attribute("album", $albums[0]["album"]),
                new Attribute("category", ILibrary::GENRES[$albums[0]["category"]]),
                new Attribute("medium", ILibrary::MEDIA[$albums[0]["medium"]]),
                new Attribute("location", ILibrary::LOCATIONS[$albums[0]["location"]]),
                new Attribute("coll", $albums[0]["iscoll"]?true:false),
                ...$rcd);

        if(!empty($incc))
            $dd = new CompoundDocument(new ResourceCollection($ralbum), new Included(...$incc));
        else
            $dd = new DataDocument(new ResourceCollection($ralbum));
        $this->serializer->emitDocument("getAlbumRs", $dd);
    }

    public function getCurrents() {
        $currentfields = API::ALBUM_FIELDS;
        array_push($currentfields, "label", "afile_number",
                    "adddate", "pulldate", "afile_category", "sizzle");
        self::array_remove($currentfields, "name", "address",
                    "city", "state", "zip");
    
        $records = Engine::api(IChart::class)->getCurrentsWithPlays();
        $this->serializer->startResponse("getCurrentsRs");
        $this->serializer->emitDataSet("albumrec", $currentfields, $records);
        $this->serializer->endResponse("getCurrentsRs");
    }

    public function importPlaylist() {
        try {
            if(!$this->session->isAuth("u"))
                throw new \Exception("Operation requires authentication");

            $file = file_get_contents("php://input");
            $json = new JsonApi($file, "show");

            $api = Engine::api(IPlaylist::class);
            $api->importPlaylist($json, $this->session->getUser(), $this->session->isAuth("v"));

            $this->serializer->startResponse("importPlaylistsRs", ["errors" => $json->getErrors()]);
            $json->iterateSuccess(function($attrs) {
                $this->serializer->startResponse("show", $attrs);
                $this->serializer->endResponse("show");
            });
            $this->serializer->endResponse("importPlaylistsRs");
        } catch (\Exception $e) {
            $this->serializer->emitError("importPlaylistsRs", 200, $e->getMessage());
        }
    }

    public function deletePlaylist() {
        try {
            if(!$this->session->isAuth("u"))
                throw new \Exception("Operation requires authentication");

            if(empty($_REQUEST["id"]))
                throw new \Exception("missing id");

            $id = $_REQUEST["id"];
            $api = Engine::api(IPlaylist::class);
            $list = $api->getPlaylist($id);

            if(!$list)
                throw new \Exception("playlist does not exist");

            if($list['dj'] != $this->session->getUser())
                throw new \Exception("not owner");

            $api->deletePlaylist($id);
            $this->serializer->startResponse("deletePlaylistRs");
            $this->serializer->startResponse("show", ["id" => $id]);
            $this->serializer->endResponse("show");
            $this->serializer->endResponse("deletePlaylistRs");
        } catch(\Exception $e) {
            $this->serializer->emitError("deletePlaylistRs", 200, $e->getMessage());
        }
    }

    public function importAlbum() {
        try {
            if(!$this->session->isAuth("m"))
                throw new \Exception("Operation requires authentication");

            $file = file_get_contents("php://input");
            $json = new JsonApi($file, "album", JsonApi::FLAG_ARRAY);

            $api = Engine::api(IEditor::class);
            $api->importAlbum($json, $_SERVER['REQUEST_METHOD'] == 'PUT');

            $this->serializer->startResponse("importAlbumsRs", ["errors" => $json->getErrors()]);
            $json->iterateSuccess(function($attrs) {
                $this->serializer->startResponse("album", $attrs);
                $this->serializer->endResponse("album");
            });
            $this->serializer->endResponse("importAlbumsRs");
        } catch (\Exception $e) {
            $this->serializer->emitError("importAlbumsRs", 200, $e->getMessage());
        }
    }

    public function deleteAlbum() {
        $id = $_REQUEST["id"];
        try {
            if(!$this->session->isAuth("m"))
                throw new \Exception("Operation requires authentication");

            if(empty($id))
                throw new \Exception("missing id");

            Engine::api(IEditor::class)->deleteAlbum($id);
            $this->serializer->startResponse("deleteAlbumRs");
            $this->serializer->startResponse("album", ["id" => $id]);
            $this->serializer->endResponse("album");
            $this->serializer->endResponse("deleteAlbumRs");
        } catch(\Exception $e) {
            $this->serializer->emitError("deleteAlbumRs", 200, $e->getMessage(), ["id" => $id]);
        }
    }

    public function getLabel() {
        $fields = API::LABEL_FIELDS;
        self::array_remove($fields, "pubkey");
        if(!$this->session->isAuth("u"))
            self::array_remove($fields, "attention", "address",
                    "phone", "fax", "email", "mailcount",
                    "maillist", "international");

        $key = $_REQUEST["id"];
        $records = Engine::api(ILibrary::class)->search(ILibrary::LABEL_PUBKEY, 0, 1, $key);

        if(sizeof($records)) {
            $rlabels = [];
            foreach($records as $record) {
                $attrs = self::getAttributes($fields, $record);
                $rlabels[] = new ResourceObject('label', $record["pubkey"],
                    new SelfLink("{$this->base}/label/{$record["pubkey"]}"),
                    ...$attrs);
            }
            $dd = new DataDocument(new ResourceCollection(...$rlabels));
            $this->serializer->emitDocument("getLabelsRs", $dd);
        } else
            $this->serializer->emitError("getLabelsRs", 200, "invalid id");
    }

    public function getReview($byId = 1) {
        $reviews = Engine::api(IReview::class)->getReviews($_REQUEST["id"], 1, "", $this->session->isAuth("u"), $byId);

        if(!empty($_REQUEST["relation"])) {
            $relation = $_REQUEST["relation"];
            unset($_REQUEST["relation"]);
            switch($relation) {
            case "album":
                if(sizeof($reviews)) {
                    header("Location: {$this->base}/album/{$reviews[0]["tag"]}");
                    return;
                }
                // fall through...
            default:
                http_response_code(400);
                break;
            }
            return;
        }

        if(sizeof($reviews)) {
            $rreview = [];
            foreach($reviews as $review) {
                $review["date"] = $review["created"];
                $key = $review["id"];

                $rreview[] = new ResourceObject("review", $key,
                    new SelfLink("{$this->base}/review/${key}"),
                    new ToOne("album",
                        new ResourceIdentifier("album", $review['tag']),
                        new RelatedLink("{$this->base}/review/${key}/album")),
                    ...self::getAttributes(API::REVIEW_FIELDS_EXT, $review));
            }
            $dd = new DataDocument(new ResourceCollection(...$rreview));
            $this->serializer->emitDocument("getReviewRs", $dd);
        } else
            $this->serializer->emitError("getReviewRs", 200, "invalid id");
    }

    public function importReview() {
        try {
            if(!$this->session->isAuth("u"))
                throw new \Exception("Operation requires authentication");

            $file = file_get_contents("php://input");
            $json = new JsonApi($file, "review");
            $json->iterateData(function($data) use($json) {
                if(is_object($data->relationships) &&
                        is_object($data->relationships->album) &&
                        is_object($data->relationships->album->data) &&
                        $data->relationships->album->data->type == "album") {
                    $tag = $data->relationships->album->data->id;
                    $album = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $tag);
                    if(!sizeof($album)) {
                        $json->addError($data->lid, "album does not exist");
                        return;
                    }
                } else {
                    $json->addError($data->lid, "missing album relation");
                    return;
                }

                $valid = $data->airname && $data->review;
                if($valid) {
                    $djapi = Engine::api(IDJ::class);
                    $user = $this->session->getUser();
                    $airname = $djapi->getAirname($data->airname);
                    if(!$airname) {
                        // airname does not exist; try to create it
                        $success = $djapi->insertAirname(mb_substr($data->airname, 0, IDJ::MAX_AIRNAME_LENGTH), $user);
                        if($success > 0) {
                            // success!
                            $airname = $djapi->lastInsertId();
                        } else
                            $valid = false;
                    }
                }

                // create/update the review
                $revapi = Engine::api(IReview::class);
                if($valid) {
                    $private = (isset($data->private) && $data->private)?"1":"0";
                    if($_SERVER['REQUEST_METHOD'] == 'PUT') {
                        // update the review
                        $reviews = $revapi->getReviews($data->id, 1, $user, 0, 1);
                        if(sizeof($reviews))
                            $valid = $revapi->updateReview($reviews[0]["tag"], $private, $airname, $data->review, $user);
                        else {
                            $json->addError($data->lid, "review does not exist, use POST");
                            return;
                        }
                    } else {
                        $reviews = $revapi->getReviews($tag, 1, $user, 0);
                        if(sizeof($reviews)) {
                            $json->addError($data->lid, "review already exists, use PUT");
                            return;
                        } else
                            $valid = $revapi->insertReview($tag, $private, $airname, $data->review, $user);
                        if($valid)
                            $data->id = $revapi->lastInsertId();
                    }
                }

                if($valid)
                    $json->addSuccess($data->id, ["lid" => $data->lid]);
                else
                    $json->addError(isset($data->id)?$data->id:$data->lid, "Review is invalid");
            });

            $this->serializer->startResponse("importReviewRs", ["errors" => $json->getErrors()]);
            $json->iterateSuccess(function($attrs) {
                $this->serializer->startResponse("review", $attrs);
                $this->serializer->endResponse("review");
            });
            $this->serializer->endResponse("importReviewRs");
        } catch (\Exception $e) {
            $this->serializer->emitError("importReviewRs", 200, $e->getMessage());
        }
    }

    public function deleteReview() {
        $id = $_REQUEST["id"];
        try {
            if(!$this->session->isAuth("u"))
                throw new \Exception("Operation requires authentication");

            if(empty($id))
                throw new \Exception("missing id");

            $user = $this->session->getUser();
            $revapi = Engine::api(IReview::class);
            $reviews = $revapi->getReviews($id, 1, $user, 0, 1);
            if(!sizeof($reviews))
                throw new \Exception("invalid id");
            if($user != $reviews[0]["user"])
                throw new \Exception("only review owner may delete");

            $revapi->deleteReview($reviews[0]['tag'], $user);
            $this->serializer->startResponse("deleteReviewRs");
            $this->serializer->startResponse("review", ["id" => $id]);
            $this->serializer->endResponse("review");
            $this->serializer->endResponse("deleteReviewRs");
        } catch(\Exception $e) {
            $this->serializer->emitError("deleteReviewRs", 200, $e->getMessage(), ["id" => $id]);
        }
    }

    private function emitHeader($method) {
        $preflight = ($_SERVER['REQUEST_METHOD'] ?? null) == "OPTIONS";
        if($preflight)
            http_response_code(204); // 204 No Content

        // Even if we return 204 above, PHP is going to include
        // a default Content-type anyway, if we do not supply one.
        // Go ahead and give the Content-type in all cases.
        header("Content-type: ". $this->serializer->getContentType());

        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        if($origin) {
            if($method && in_array($method, self::$publicMethods)) {
                header("Access-Control-Allow-Origin: *");
            } else {
                $allowed_domains = Engine::param('allowed_domains');
                for($i=0; $i < sizeof($allowed_domains); $i++) {
                    if(preg_match("/" . preg_quote($allowed_domains[$i]) . "$/", $origin)) {
                        header("Access-Control-Allow-Origin: " . $origin);
                        header("Access-Control-Allow-Credentials: true");
                        break;
                    }
                }
            }

            if($preflight) {
                header("Access-Control-Allow-Methods: " . self::$defaultACAM);
                header("Access-Control-Max-Age: 7200");
            }
        }

        return $preflight;
    }
    
    private static function array_remove(&$array) {
        $args = func_get_args();
        array_shift($args);
        $size = sizeof($array);
        for($i=0; $i<$size; $i++)
             if(in_array($array[$i], $args))
                 unset($array[$i]);
        $array = array_merge($array);
    }
}

class SingleRowIterator {
    private $row;

    public function __construct($row) {
        $this->row = $row;
    }

    public function fetch() {
        $result = $this->row;
        $this->row = false;
        return $result;
    }
}
