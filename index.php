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

if(!file_exists(__DIR__."/vendor/autoload.php")) {
    if(php_sapi_name() != "cli") {
        error_log("Composer not configured");
        http_response_code(500); // 500 Internal Server Error
    }
    die("Composer is not configured.  See INSTALLATION.md for information.\n");
}

require_once __DIR__."/vendor/autoload.php";

use ZK\Controllers\Dispatcher;

$dispatcher = new Dispatcher();
$dispatcher->processRequest($_REQUEST["target"]);
