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

    public function processRequest($dispatcher) {
        $this->emitHeader();
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<xml>\n";
        $this->session = Engine::session();
        $this->limit = $_REQUEST["size"];
        if(!$this->limit || $this->limit > API::MAX_LIMIT)
            $this->limit = API::MAX_LIMIT;
        $this->processLocal($_REQUEST["method"], null);
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
            echo "<getAlbumsRs".$this->emitSuccess().">\n";
            $this->emitDataSetArray("albumrec", API::ALBUM_FIELDS, $records);
            echo "</getAlbumsRs>\n";
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
            echo "<getLabelsRs".$this->emitSuccess().">\n";
            $this->emitDataSetArray("labelrec", API::LABEL_FIELDS, $records);
            echo "</getLabelsRs>\n";
        } else
            $this->emitError("getLabelsRs", 20, "Invalid operation: ".$_REQUEST["operation"]);
    }

    public function fullTextSearch() {
        $results = Engine::api(ILibrary::class)->searchFullText(
            $_REQUEST["type"], $_REQUEST["key"], $this->limit,
            $_REQUEST["offset"]);
        echo "<searchRs total=\"".$results[0]."\" type=\"".$_REQUEST["type"]."\">\n";
        foreach ($results[1] as $result) {
            echo "<".$result["type"]." more=\"".$result["more"]."\" offset=\"".$result["offset"]."\">\n";
            $this->emitDataSetArray($result["recordName"], self::$ftFields[$result["type"]], $result["result"]);
            echo "</".$result["type"].">\n";
        }
        echo "</searchRs>\n";
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
    
        // main chart
        Engine::api(IChart::class)->getChart2($records, 0, $date, 30);
        echo "<getChartRs chart=\"General\" week-ending=\"$date\"".$this->emitSuccess().">\n";
        $this->emitDataSetArray("albumrec", $chartfields, $records, 0);
        echo "</getChartRs>\n";
    
        // genre charts
        $genres = array(5, 7, 6, 1, 2, 4, 3, 8);
        for($i=0; $i<sizeof($genres); $i++) {
             unset($records);
             Engine::api(IChart::class)->getChart2($records, 0, $date, 10, $genres[$i]);
             echo "<getChartRs chart=\"".$this->catCodes[$genres[$i]-1]."\" week-ending=\"$date\"".$this->emitSuccess().">\n";
             $this->emitDataSetArray("albumrec", $chartfields, $records, 0);
             echo "</getChartRs>\n";
        }
    }
    
    public function getPlaylists() {
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
        echo "<getPlaylistsRs".$this->emitSuccess().">\n";
        while($row = $result->fetch()) {
             echo "<show name=\"".self::spec2hex(stripslashes($row["description"]))."\" ".
                   "date=\"".$row["showdate"]."\" ".
                   "time=\"".$row["showtime"]."\" ".
                   "airname=\"".self::spec2hex(stripslashes($row["airname"]))."\" ".
                   "id=\"".($id?$id:$row[0])."\"";
             if($includeTracks && $includeTracks != "false") {
                 echo ">\n";
                 $tracks = Engine::api(IPlaylist::class)->getTracks($id?$id:$row[0]);
                 while($track = $tracks->fetch()) {
                     if(substr($track["artist"], 0, strlen(IPlaylist::SPECIAL_TRACK)) == IPlaylist::SPECIAL_TRACK)
                         if(strpos($track["artist"], IPlaylist::COMMENT_FLAG) > 0)
                            echo "<comment>".self::spec2hex(stripslashes($track["track"].$track["album"].$track["label"]))."</comment>\n";
                         else
                            echo "<break/>\n";
                     else {
                         echo "<track";
                         if($track["artist"] != "")
                              echo " artist=\"".self::spec2hex(stripslashes($track["artist"]))."\"";
                         if($track["track"] != "")
                              echo " track=\"".self::spec2hex(stripslashes($track["track"]))."\"";
                         if($track["album"] != "")
                              echo " album=\"".self::spec2hex(stripslashes($track["album"]))."\"";
                         if($track["label"] != "")
                              echo " label=\"".self::spec2hex(stripslashes($track["label"]))."\"";
                         if($track["tag"] != "")
                              echo " tag=\"".$track["tag"]."\"";
                         echo "/>\n";
                     }
                 }
                 echo "</show>\n";
             } else
                 echo "/>\n";
        }
        echo "</getPlaylistsRs>\n";
    }
    
    public function getCurrents() {
        $currentfields = API::ALBUM_FIELDS;
        array_push($currentfields, "label", "afile_number",
                    "adddate", "pulldate", "afile_category", "sizzle");
        self::array_remove($currentfields, "name", "address",
                    "city", "state", "zip");
    
        $records = Engine::api(IChart::class)->getCurrentsWithPlays2();
        echo "<getCurrentsRs".$this->emitSuccess().">\n";
        $this->emitDataSet("albumrec", $currentfields, $records);
        echo "</getCurrentsRs>\n";
    }

    private function emitStatus($code, $message) {
        return " code=\"$code\" message=\"$message\"";
    }
    
    private function emitSuccess() {
        return $this->emitStatus(0, "Success");
    }
    
    private function emitError($request, $code, $message) {
        echo "<$request";
        echo $this->emitStatus($code, $message);
        echo ">\n</$request>\n";
    }
    
    private function emitHeader() {
        header("Content-type: text/xml; charset=UTF-8");
    
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
        for($i=0; $i<sizeof($data); $i++) {
             echo "<$name>\n";
             for($j=0; $j<sizeof($fields); $j++) {
                 $field = $fields[$j];
                 $row = $data[$i];
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
                        $this->emitAFileCat($val);
                        continue 2;
                    }
                 }
                 echo "<$field>".
                      self::spec2hex(stripslashes($val)).
                      "</$field>\n";
             }
    
             echo "</$name>\n";
        }
    }

    private function getAFileCats() {
        $catCodes = array();
        $result = Engine::api(IChart::class)->getCategories();
        while($row = $result->fetch())
             $catCodes[] = $row["name"];
        return $catCodes;
    }

    private function emitAFileCat($cats) {
        if(!isset($this->catCodes))
            $this->catCodes = $this->getAFileCats();

        echo "<charts>\n";
        if($cats) {
             $cats = explode(",", $cats);
             for($i=0; $i<sizeof($cats); $i++)
                 if(substr($this->catCodes[$cats[$i]-1], 0, 1) != "(")
                    echo "<chart>".$this->catCodes[$cats[$i]-1]."</chart>\n";
        }
        echo "</charts>\n";
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
        return preg_replace("/[[:cntrl:]]/", "", htmlspecialchars($str));
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
