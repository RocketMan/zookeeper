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
use ZK\Engine\ILibrary;
use ZK\Engine\IPlaylist;
use ZK\Engine\IReview;
use ZK\Engine\OnNowFilter;
use ZK\Engine\PlaylistObserver;

use ZK\UI\UICommon as UI;

abstract class Serializer {
    private $catCodes;

    public abstract function getContentType();
    public abstract function startDocument();
    public abstract function endDocument();
    public abstract function startResponse($name, $attrs=null);
    public abstract function endResponse($name);
    public abstract function emitDataSetArray($name, $fields, &$data);

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

    public function getContentType() { return JSONSerializer::CONTENT_TYPE; }

    public function startDocument() {}
    public function endDocument() {}

    public function startResponse($name, $attrs=null) {
        if(!$attrs)
            $attrs = $this->newAttrs();

        echo $this->nextToken;
        $this->nextToken = "";

        echo "{\"type\":\"$name\",";
        foreach($attrs as $key => $value)
            echo "\"$key\":\"".self::jsonspecialchars($value)."\",";
        echo "\"data\":[";
    }

    public function endResponse($name) {
        $this->nextToken = ",";
        echo "]}";
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

    public function emitDataSetArray($name, $fields, &$data) {
        $nextToken = "";
        foreach($data as $row) {
            echo "$nextToken{";
            $nextProp = "";
            foreach($fields as $field) {
                $val = $row[$field];
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
                }
                echo "$nextProp\"$field\":\"".
                     self::jsonspecialchars(stripslashes($val)).
                     "\"";
                $nextProp = ",";
            }

            $nextToken = ",";
            echo "}";
        }
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
        if(!$attrs)
            $attrs = $this->newAttrs();
        echo "<$name";
        foreach($attrs as $key => $value)
            echo " $key=\"".self::spec2hexAttr($value)."\"";
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

    public function emitDataSetArray($name, $fields, &$data) {
        foreach($data as $row) {
            echo "<$name>\n";
            foreach($fields as $field) {
                $val = $row[$field];
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
                }
                echo "<$field>".
                     self::spec2hex(stripslashes($val)).
                     "</$field>\n";
            }
            echo "</$name>\n";
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
        "seq", "artist", "track"
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
        [ "getPlaylistsRq", "getPlaylists" ],
        [ "getTracksRq", "getTracks" ],
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

    public function processRequest($dispatcher) {
        $wantXml = $_REQUEST["xml"] || 
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
             $this->serializer->emitError("getPlaylistsRs", 20, "Invalid operation: ".$_REQUEST["operation"]);
             return;
        }
        $this->serializer->startResponse("getPlaylistsRs");
        while($row = $result->fetch()) {
            $attrs = [];
            $attrs["name"] = $row["description"];
            $attrs["date"] = $row["showdate"];
            $attrs["time"] = $row["showtime"];
            $attrs["airname"] = $row["airname"];
            $attrs["id"] = $id?$id:$row["id"];
            $this->serializer->startResponse("show", $attrs);
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
                        $events[] = $spin;
                    }), 0, $filter);
                $this->serializer->emitDataSetArray("event", API::PLAYLIST_DETAIL_FIELDS, $events);
            }
            $this->serializer->endResponse("show");
        }
        $this->serializer->endResponse("getPlaylistsRs");
    }

    public function getTracks() {
        $key = $_REQUEST["key"];
        $fields = API::TRACK_DETAIL_FIELDS;

        $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $key);
        if(!$key || !sizeof($albums)) {
            $this->serializer->emitError("getTracksRs", 100, "Unknown tag", ["tag" => $key]);
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

        $attrs = $this->serializer->newAttrs();
        $attrs["tag"] = $key;
        $attrs["artist"] =  $artist;
        $attrs["album"] = $albums[0]["album"];
        $attrs["label"] = $label;
        $attrs["collection"] = ILibrary::GENRES[$albums[0]["category"]];
        $attrs["medium"] = ILibrary::MEDIA[$albums[0]["medium"]];
        $attrs["isCompilation"] = $albums[0]["iscoll"]?"true":"false";
        $this->serializer->startResponse("getTracksRs", $attrs);
        $this->serializer->emitDataSetArray("trackrec", $fields, $records);
        $this->serializer->endResponse("getTracksRs");
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

    private function emitHeader($method) {
        $preflight = $_SERVER['REQUEST_METHOD'] == "OPTIONS";
        if($preflight)
            http_response_code(204); // 204 No Content

        // Even if we return 204 above, PHP is going to include
        // a default Content-type anyway, if we do not supply one.
        // Go ahead and give the Content-type in all cases.
        header("Content-type: ". $this->serializer->getContentType());

        $origin = $_SERVER['HTTP_ORIGIN'];
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
