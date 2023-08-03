<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2023 Jim Mason <jmason@ibinx.com>
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
use ZK\Engine\TemplateFactory;
use ZK\UI\AddManager;

class ExportAfile implements IController {
    public function processRequest() {
        $results = Engine::api(IChart::class)->getCurrents(date("Y-m-d"));
        $addmgr = new AddManager();
        $addmgr->session = Engine::session();
        $addmgr->addManagerEmitAlbums($results, "", false, true, true, true);

        $templateFact = new TemplateFactory(dirname(__DIR__).'/ui/templates');
        $template = $templateFact->load('currents/export.html');
        echo $template->render($addmgr->getTemplateVars());
    }
}
