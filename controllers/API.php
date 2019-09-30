<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2018 Jim Mason <jmason@ibinx.com>
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

use ZK\UI\Search;
use ZK\UI\UICommon as UI;

class API extends CommandTarget implements IController {
    const MAX_LIMIT = 35;

    const ALBUM_FIELDS = [
        "tag", "artist", "album", "category", "medium",
        "size", "location", "bin", "created", "updated",
        "name", "address", "city", "state", "zip"
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
        "tag", "artist", "album", "airname", "created"
    ];

    const TRACK_FIELDS = [
        "tag", "artist", "album", "category", "medium",
        "size", "location", "bin", "created", "updated",
        "name", "address", "city", "state", "zip",
        "track"
    ];

    const PLAYLIST_DETAIL_FIELDS = [
        "type", "comment", "artist", "track", "album", "label", "tag"
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

    private static $methods = [
        [ "", "unknownMethod" ],
        [ "getAlbumsRq", "albumPager" ],
        [ "getLabelsRq", "labelPager" ],
        [ "searchRq", "fullTextSearch" ],
        [ "getCurrentsRq", "getCurrents" ],
        [ "getChartsRq", "getCharts" ],
        [ "getPlaylistsRq", "getPlaylists" ],
        [ "getTracksRq", "getTracks" ],
    ];

    private static $pager_operations = [
        "prevLine" => ILibrary::OP_PREV_LINE,
        "nextLine" => ILibrary::OP_NEXT_LINE,
        "prevPage" => ILibrary::OP_PREV_PAGE,
        "nextPage" => ILibrary::OP_NEXT_PAGE,
        "searchByName" => ILibrary::OP_BY_NAME,
        "searchByTag" => ILibrary::OP_BY_TAG,
    ];

    private $limit;
    private $catCodes;
    private $json;
    private $indent = 0;
    private $nextToken = "";

    public function processRequest($dispatcher) {
        $this->json = $_REQUEST["json"] ||
            substr($_SERVER["HTTP_ACCEPT"], 0, 16) == "application/json";
        $this->emitHeader();
        if(!$this->json)
            echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<xml>\n";
        $this->session = Engine::session();
        $this->limit = $_REQUEST["size"];
        if(!$this->limit || $this->limit > API::MAX_LIMIT)
            $this->limit = API::MAX_LIMIT;
        $this->processLocal($_REQUEST["method"], null);
        if(!$this->json)
            echo "</xml>\n";
    }

    public function processLocal($action, $subaction) {
        $this->dispatchAction($action, self::$methods);
    }

    public function unknownMethod() {
        $method = $_REQUEST["method"];
        $this->emitError($method?$method:"status", 10, "Invalid method: $method");
    }
    
    public function albumPager() {
        $op = self::$pager_operations[$_REQUEST["operation"]];
        if(isset($op)) {
            $records = Engine::api(ILibrary::class)->listAlbums(
                     $op,
                     $_REQUEST["key"],
                     $this->limit);
            $this->startResponse("getAlbumsRs");
            $this->emitDataSetArray("albumrec", API::ALBUM_FIELDS, $records);
            $this->endResponse("getAlbumsRs");
        } else
            $this->emitError("getAlbumsRs", 20, "Invalid operation: ".$_REQUEST["operation"]);
    }

    public function labelPager() {
        $op = self::$pager_operations[$_REQUEST["operation"]];
        if(isset($op)) {
            $records = Engine::api(ILibrary::class)->listLabels(
                     $op,
                     $_REQUEST["key"],
                     $this->limit);
            $this->startResponse("getLabelsRs");
            $this->emitDataSetArray("labelrec", API::LABEL_FIELDS, $records);
            $this->endResponse("getLabelsRs");
        } else
            $this->emitError("getLabelsRs", 20, "Invalid operation: ".$_REQUEST["operation"]);
    }

    public function fullTextSearch() {
        $results = Engine::api(ILibrary::class)->searchFullText(
            $_REQUEST["type"], $_REQUEST["key"], $this->limit,
            $_REQUEST["offset"]);
        $attrs = $this->addSuccess();
        $attrs["total"] = $results[0];
        $attrs[$this->json?"dataType":"type"] = $_REQUEST["type"];
        $this->startResponse("searchRs", $attrs);
        foreach ($results[1] as $result) {
            $attrs = [];
            $attrs["more"] = $result["more"];
            $attrs["offset"] = $result["offset"];
            $this->startResponse($result["type"], $attrs);
            $this->emitDataSetArray($result["recordName"], self::$ftFields[$result["type"]], $result["result"]);
            $this->endResponse($result["type"]);
        }
        $this->endResponse("searchRs");
    }

    public function getCharts() {
        if(!isset($this->catCodes))
            $this->catCodes = $this->getAFileCats();

        $date = $_REQUEST["date"];

        $chartfields = API::ALBUM_FIELDS;
        array_push($chartfields, "label", "rank");
        self::array_remove($chartfields, "name", "address", "city", "state", "zip", "location", "bin");
    
        if(!$date) {
             $weeks = Engine::api(IChart::class)->getChartDates(1);
             $week = $weeks->fetch();
             $date = $week["week"];
        }
    
        $this->startResponse("getChartRs");
        
        // main chart
        Engine::api(IChart::class)->getChart2($records, 0, $date, 30);

        $attrs = [];
        $attrs["chart"] = "General";
        $attrs["week-ending"] = $date;
        $this->startResponse("chart", $attrs);
        $this->emitDataSetArray("albumrec", $chartfields, $records, 0);
        $this->endResponse("chart");
    
        // genre charts
        $genres = [5, 7, 6, 1, 2, 4, 3, 8];
        for($i=0; $i<sizeof($genres); $i++) {
             unset($records);
             Engine::api(IChart::class)->getChart2($records, 0, $date, 10, $genres[$i]);
             $attrs = [];
             $attrs["chart"] = $this->catCodes[$genres[$i]-1]["name"];
             $attrs["week-ending"] = $date;
             $this->startResponse("chart", $attrs);
             $this->emitDataSetArray("albumrec", $chartfields, $records, 0);
             $this->endResponse("chart");
        }
        
        $this->endResponse("getChartRs");
    }
    
    public function getPlaylists() {
        $id = 0;
        $key = $_REQUEST["key"];
        $includeTracks = $_REQUEST["includeTracks"];

        switch($_REQUEST["operation"]) {
        case "byID":
             $result = new SingleRowIterator(Engine::api(IPlaylist::class)->getPlaylist($key, 1));
             $id = $key;
             break;
        case "byDate":
             $result = Engine::api(IPlaylist::class)->getPlaylistsByDate($key);
             break;
        case "onNow":
             $result = Engine::api(IPlaylist::class)->getWhatsOnNow();
             break;
        default:
             $this->emitError("getPlaylistsRs", 20, "Invalid operation: ".$_REQUEST["operation"]);
             return;
        }
        $this->startResponse("getPlaylistsRs");
        if($this->json) {
            while($row = $result->fetch()) {
                $attrs = [];
                $attrs["name"] = $row["description"];
                $attrs["date"] = $row["showdate"];
                $attrs["time"] = $row["showtime"];
                $attrs["airname"] = $row["airname"];
                $attrs["id"] = $id?$id:$row["id"];
                $this->startResponse("show", $attrs);
                if($includeTracks && $includeTracks != "false") {
                    $tracks = Engine::api(IPlaylist::class)->getTracks($id?$id:$row["id"]);
                    $events = [];
                    while($track = $tracks->fetch()) {
                        if(substr($track["artist"], 0, strlen(IPlaylist::SPECIAL_TRACK)) == IPlaylist::SPECIAL_TRACK)
                            if(strpos($track["artist"], IPlaylist::COMMENT_FLAG) > 0)
                                $events[] = ["type" => "comment", "comment" => $track["track"].$track["album"].$track["label"]];
                            else
                                $events[] = ["type" => "break"];
                        else {
                            $track["type"] = "track";
                            $events[] = $track;
                        }
                    }
                    $this->emitDataSetArray("event", API::PLAYLIST_DETAIL_FIELDS, $events);
                }
                $this->endResponse("show");
            }
        } else {
            while($row = $result->fetch()) {
                echo "<show name=\"".self::spec2hexAttr(stripslashes($row["description"]))."\" ".
                     "date=\"".$row["showdate"]."\" ".
                     "time=\"".$row["showtime"]."\" ".
                     "airname=\"".self::spec2hexAttr(stripslashes($row["airname"]))."\" ".
                     "id=\"".($id?$id:$row["id"])."\"";
                if($includeTracks && $includeTracks != "false") {
                    echo ">\n";
                    $tracks = Engine::api(IPlaylist::class)->getTracks($id?$id:$row["id"]);
                    while($track = $tracks->fetch()) {
                        if(substr($track["artist"], 0, strlen(IPlaylist::SPECIAL_TRACK)) == IPlaylist::SPECIAL_TRACK)
                            if(strpos($track["artist"], IPlaylist::COMMENT_FLAG) > 0)
                                echo "<comment>".self::spec2hex(stripslashes($track["track"].$track["album"].$track["label"]))."</comment>\n";
                            else
                                echo "<break/>\n";
                        else {
                            echo "<track";
                            if($track["artist"] != "")
                                echo " artist=\"".self::spec2hexAttr(stripslashes($track["artist"]))."\"";
                            if($track["track"] != "")
                                echo " track=\"".self::spec2hexAttr(stripslashes($track["track"]))."\"";
                            if($track["album"] != "")
                                echo " album=\"".self::spec2hexAttr(stripslashes($track["album"]))."\"";
                            if($track["label"] != "")
                                echo " label=\"".self::spec2hexAttr(stripslashes($track["label"]))."\"";
                            if($track["tag"] != "")
                                echo " tag=\"".$track["tag"]."\"";
                            echo "/>\n";
                        }
                    }
                    echo "</show>\n";
                } else
                    echo "/>\n";
            }
        }
        $this->endResponse("getPlaylistsRs");
    }

    public function getTracks() {
        $key = $_REQUEST["key"];
        $fields = API::TRACK_DETAIL_FIELDS;

        $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $key);
        if(!$key || !sizeof($albums)) {
            $this->emitError("getTracksRs", 100, "Unknown tag", ["tag" => $key]);
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

        $attrs = $this->addSuccess();
        $attrs["tag"] = $key;
        $attrs["artist"] = $artist;
        $attrs["album"] = $albums[0]["album"];
        $attrs["label"] = $label;
        $attrs["collection"] = Search::GENRES[$albums[0]["category"]];
        $attrs["medium"] = Search::MEDIA[$albums[0]["medium"]];
        $attrs["isCompilation"] = $albums[0]["iscoll"]?"true":"false";
        $this->startResponse("getTracksRs", $attrs);
        $this->emitDataSetArray("trackrec", $fields, $records);
        $this->endResponse("getTracksRs");
    }
    
    public function getCurrents() {
        $currentfields = API::ALBUM_FIELDS;
        array_push($currentfields, "label", "afile_number",
                    "adddate", "pulldate", "afile_category", "sizzle");
        self::array_remove($currentfields, "name", "address",
                    "city", "state", "zip");
    
        $records = Engine::api(IChart::class)->getCurrentsWithPlays2();
        $this->startResponse("getCurrentsRs");
        $this->emitDataSet("albumrec", $currentfields, $records);
        $this->endResponse("getCurrentsRs");
    }

    private function startResponse($name, $attrs=null) {
        if(!$attrs)
            $attrs = $this->addSuccess();
            
        if($this->json) {
            echo $this->nextToken;
            $this->nextToken = "";
            $this->indent += 1;
            $i = $this->indent > 1?$this->indent+1:$this->indent;
            $indent = str_repeat("  ", $i);
            echo "{\n$indent\"type\": \"$name\",\n";
            foreach($attrs as $key => $value)
                echo "$indent\"$key\": \"".self::jsonspecialchars($value)."\",\n";
            echo "$indent\"data\": [";
        } else {
            echo "<$name";
            foreach($attrs as $key => $value)
                echo " $key=\"".self::spec2hexAttr($value)."\"";
            echo ">\n";
        }
    }

    private function endResponse($name) {
        if($this->json) {
            $this->nextToken = ",\n".str_repeat("  ", $this->indent);
            $this->indent -= 1;
            $i = $this->indent?$this->indent+1:$this->indent;
            echo "\n".str_repeat("  ", $i+1)."]\n".str_repeat("  ", $i)."}";
        } else
            echo "</$name>\n";
    }

    private function addStatus($code, $message) {
        $attrs = [];
        $attrs["code"] = $code;
        $attrs["message"] = $message;
        return $attrs;
    }
    
    private function addSuccess() {
        return $this->addStatus(0, "Success");
    }
    
    private function emitError($request, $code, $message, $opts = null) {
        $attrs = $this->addStatus($code, $message);
        if($opts)
            $attrs += $opts;
        $this->startResponse($request, $attrs);
        $this->endResponse($request);
    }
    
    private function emitHeader() {
        header("Content-type: ".
            ($this->json?"application/vnd.api+json":"text/xml").
            "; charset=UTF-8");
    
        $origin = $_SERVER['HTTP_ORIGIN'];
        if($origin) {
            $allowed_domains = Engine::param('allowed_domains');
            for($i=0; $i < sizeof($allowed_domains); $i++) {
               if(preg_match("/" . preg_quote($allowed_domains[$i]) . "$/", $origin)) {
                   header("Access-Control-Allow-Origin: " . $origin);
                   break;
               }
            }
        }
    }
    
    private function emitDataSet($name, $fields, $rows) {
        $data = array();
        while($row = $rows->fetch())
             $data[] = $row;
        $this->emitDataSetArray($name, $fields, $data);
    }

    private function emitDataSetArray($name, $fields, &$data) {
        $indent = str_repeat("  ", $this->indent*2);
        $nextToken = "";
        foreach($data as $row) {
             echo $this->json?"$nextToken{\n":"<$name>\n";
             $nextProp = "";
             foreach($fields as $field) {
                 $val = $row[$field];
                 if($name == "albumrec") {
                    switch($field) {
                    case "category":
                        $val = Search::GENRES[$val];
                        break;
                    case "medium":
                        $val = Search::MEDIA[$val];
                        break;
                    case "size":
                        $val = Search::LENGTHS[$val];
                        break;
                    case "location":
                        $val = Search::LOCATIONS[$val];
                        break;
                    case "afile_category":
                        $val = $this->getAFileCatList($val);
                        if($this->json)
                            echo "$nextProp$indent  \"charts\": $val";
                        else
                            echo "<charts>$val</charts>\n";
                        $nextProp = ",\n";
                        continue 2;
                    }
                 }
                 if($this->json)
                     echo "$nextProp$indent  \"$field\": \"".
                          self::jsonspecialchars(stripslashes($val)).
                          "\"";
                 else
                     echo "<$field>".
                          self::spec2hex(stripslashes($val)).
                          "</$field>\n";
                 $nextProp = ",\n";
             }

             $nextToken = ",\n$indent";
             echo $this->json?"\n$indent}":"</$name>\n";
        }
    }

    private function getAFileCats() {
        return Engine::api(IChart::class)->getCategories();
    }

    private function getAFileCatList($cats) {
        $result = "";
        
        if(!isset($this->catCodes))
            $this->catCodes = $this->getAFileCats();

        if($this->json) {
            $pre = ", \"";
            $post = "\"";
        } else {
            $pre = "<chart>";
            $post = "</chart>\n";
        }
        
        if($cats) {
             $cats = explode(",", $cats);
             foreach($cats as $cat)
                 if(substr($this->catCodes[$cat-1]["name"], 0, 1) != "(")
                    $result .= $pre.$this->catCodes[$cat-1]["name"].$post;
        }

        if($this->json)
            $result = "[" . substr($result, 2) . "]";

        return $result;
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
    
    private static function spec2hex($str) {
        return preg_replace("/[[:cntrl:]]/", "", htmlspecialchars($str, ENT_XML1, 'UTF-8'));
    }

    private static function spec2hexAttr($str) {
        return str_replace("\"", "&quot;", self::spec2hex($str));
    }
    
    private static function jsonspecialchars($str) {
        return str_replace(["\\", "\"", "\n", "\r", "\t"],
                           ["\\\\", "\\\"", "\\n", "\\r", "\\t"], $str);
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
