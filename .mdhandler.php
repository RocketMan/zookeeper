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

require_once __DIR__."/vendor/autoload.php";

use ZK\UI\UICommon as UI;

$stylesheet = "css/zoostyle.css";

$target = realpath(__DIR__.$_SERVER['REDIRECT_ASSET']);
if(strncmp($target, __DIR__.DIRECTORY_SEPARATOR, strlen(__DIR__)+1) ||
        !file_exists($target) || substr($target, -3) != '.md' )  {
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

header("Content-Type: text/html");
header("Last-Modified: ".gmdate('D, d M Y H:i:s', $mtime)." GMT");

// for HEAD requests, there is nothing more to do
if($_SERVER['REQUEST_METHOD'] == "HEAD")
    return;

ob_start("ob_gzhandler");

echo "<!DOCTYPE html>\n<HTML lang=\"en\">\n<HEAD>\n";
UI::emitCSS($stylesheet);
echo "<TITLE>".basename($_SERVER['REQUEST_URI'])."</TITLE>\n";
echo "</HEAD>\n<BODY>\n<DIV class='box'>\n";

ob_start("markdown");

function markdown($buffer) {
    return Parsedown::instance()->text($buffer);
}

require_once($target);

ob_end_flush(); // markdown

echo "\n</DIV>\n</BODY>\n</HTML>\n";

ob_end_flush(); // ob_gzhandler
