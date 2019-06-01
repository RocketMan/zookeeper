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

namespace ZK\Controllers;

use ZK\Engine\Engine;
use ZK\Engine\IChart;
use ZK\UI\AddManager;

class ExportAfile implements IController {
    public function processRequest($dispatcher) {
        $userAgent = $_SERVER["HTTP_USER_AGENT"];
        ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<HTML>
<HEAD>
  <TITLE>KZSU Zookeeper Online</TITLE>
  <STYLE TYPE="text/css"><!--
      BODY, P, TD { font-family: verdana, arial, helvetica, sans-serif;
           color: #000000;
           font-size: 80% }
      TH, .header { font-family: verdana, arial, helvetica, sans-serif;
           font-size: 100%;
           font-weight: bold;
           color: #000000; }
      H2 { font-family: verdana, arial, helvetica, sans-serif;
           font-size: 100%;
           color: #000000; }
-->
  </STYLE>
</HEAD>
<BODY>
<?php 
        $displayDate = date("l, j F Y");
        $station = Engine::param("station");
        echo "  <H2 CLASS=\"header\">$station A-FILE AS OF " . strtoupper($displayDate) . "</H2>\n";
        $results = Engine::api(IChart::class)->getCurrents2(date("Y-m-d"));
        $addmgr = new AddManager();
        $addmgr->session = Engine::session();
        $addmgr->addManagerEmitAlbums($results, "", false, false, true);
        echo "</BODY>\n</HTML>\n";
    }
}
