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
use ZK\UI\AddManager;

class ExportAdd implements IController {
    public function processRequest($dispatcher) {
        // Ensure there's a date
        $date = $_REQUEST["date"];
        if(strlen($date) != 10 ||
                        substr($date, 4, 1) != "-" ||
                        substr($date, 7, 1) != "-")
            exit;
        
        // Setup the EOL sequence
        switch($_REQUEST["os"]) {
            case "win":
                $eol = "\r\n";
                break;
            case "mac":
                $eol = "\r";
                break;
            default:
                $eol = "\n";
                break;
        }
        
        header("Content-type: application/csv");
        header("Content-disposition: attachment; filename=add.csv");
        
        // Stuff the categories into an array
        $cats = Engine::api(IChart::class)->getCategories();
        $i=1;
        while($cats && ($row = $cats->fetch()))
            $catcode[$i++] = $row[2];
        
        // Get the add records
        $records = Engine::api(IChart::class)->getAdd2($date);
        
        // Retrieve the albums into an array
        (new AddManager())->addManagerGetAlbums2($records, $albums);
        
        // Emit the albums
        while(list($index, $row) = each($albums)) {
            // Add & pull dates
            echo $row["adddate"] . "\t" .
                 $row["pulldate"] . "\t";
        
            // Categories
            $cats = explode(",", $row["afile_category"]);
            while(list($index, $cat) = each($cats))
                echo $catcode[$cat];
            echo "\t";
        
            // A-File Number
            echo $row["afile_number"] . "\t";
        
            // Fixup the artist and label names
            $artist = preg_match("/^\[coll\]/i", $row["artist"])?"COLL":$row["artist"];
            $label = str_replace(" Records", "", $row["label"]);
            $label = str_replace(" Recordings", "", $label);
        
            // Append 7", 10", or 12" to artist name, as appropriate
            switch($row["medium"]) {
            case "S":
                $artist .= " [7\\\"]";
                break;
            case "T":
                $artist .= " [10\\\"]";
                break;
            case "V":
                $artist .= " [12\\\"]";
                break;
            }
        
            // Emit Artist/Album/Label names
            echo $artist . "\t" .
                 $row["album"] . "\t" .
                 $label . "\t" .
                 $row["tag" ] . $eol;
        }
    }
}
