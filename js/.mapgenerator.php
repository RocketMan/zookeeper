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

require_once __DIR__."/../vendor/autoload.php";

use axy\sourcemap\SourceMap;
use JSMin\JSMin;

// ensure target exists and is descendant of this directory
$target = realpath(__DIR__.$_SERVER['PATH_INFO']);
if(strncmp($target, __DIR__.DIRECTORY_SEPARATOR, strlen(__DIR__)+1) ||
        !file_exists($target)) {
    http_response_code(404);
    return;
}

$mtime = filemtime($target);
header("ETag: \"${mtime}\""); // RFC 2616 requires ETag in 304 response
if(isset($_SERVER['HTTP_IF_NONE_MATCH']) &&
        strstr($_SERVER['HTTP_IF_NONE_MATCH'], "\"${mtime}\"") !== false ||
        isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) &&
        strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime) {
    http_response_code(304); // Not Modified
    return;
}

header("Content-Type: application/json");
header("Last-Modified: ".gmdate('D, d M Y H:i:s', $mtime)." GMT");

// for HEAD requests, there is nothing more to do
if($_SERVER['REQUEST_METHOD'] == "HEAD")
    return;

// get (possibly decorated) resource basename
$uri = $_SERVER["REQUEST_URI"];
$i = strrpos($uri, ".");
$base = ($i !== false)?substr($uri, 0, $i):$uri;

// generate the destination (minified) contents...
// this must be the same algorithm used in .jshandler.php
$minified = explode("\n", JSMIN::minify(file_get_contents($target)));

// open the source
$source = fopen($target, "r");
$stext = fgets($source);
$scol = 0;
$sline = 0;

// generate the dest => src mappings
$map = new SourceMap();
foreach($minified as $dline => $dtext) {
    // skip comments
    $lead = substr($dtext, 0, 2);
    if($lead == "/*" || $lead == "//")
        continue;

    $dcol = 0;
    foreach(preg_split("/[^a-z]/i", $dtext, null, PREG_SPLIT_NO_EMPTY) as $token) {
        // token position in the minified file
        $dcol = strpos($dtext, $token, $dcol);

        // locate corresponding token position in the source file
        $scol = strpos($stext, $token, $scol);
        while($scol === false && !feof($source)) {
            $sline++;
            $scol = 0;
            $stext = fgets($source);
            $scol = strpos($stext, $token, $scol);
        }

        if($scol === false) {
            // should never get here...
            // it means we reached eof on the source
            // without finding the token
            break 2;
        }

        // add dest => src mapping
        $map->addPosition([
	    'generated' => [
                'line' => $dline,
                'column' => $dcol
	    ],
	    'source' => [
	        'fileName' => "${base}.src.js",
                'line' => $sline,
                'column' => $scol
	    ],
	]);

        $scol++;
        $dcol++;
    }
}

fclose($source);

// generate the source map JSON
$map->file = "${base}.js";
$map->save('php://output', 0);
