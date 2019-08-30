<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2019 Jim Mason <jmason@ibinx.com>
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

require('../ui/3rdp/JSMin.php');

$target = realpath(__DIR__.$_SERVER['PATH_INFO']);
if(strncmp($target, __DIR__.DIRECTORY_SEPARATOR, strlen(__DIR__)+1) ||
        !file_exists($target)) {
    http_response_code(404);
    return;
}
    
header("Content-Type: application/javascript");
header("Last-Modified: ".gmdate('D, d M Y H:i:s', filemtime($target))." GMT");

ob_start("ob_gzhandler");
ob_start([\JSMin::class, 'minify']);

require_once($target);

ob_end_flush(); // JSMin::minify
ob_end_flush(); // ob_gzhandler
