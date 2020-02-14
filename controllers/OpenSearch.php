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

use ZK\UI\UICommon as UI;

class OpenSearch implements IController {
    public function processRequest($dispatcher) {
        $baseURL = UI::getBaseURL();
        $favicon = Engine::param('favicon', 'favicon.ico');
        $banner = Engine::param("station")." ".Engine::param("application");
        
        header("Content-type: text/xml; charset=UTF-8");

        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<OpenSearchDescription xmlns=\"http://a9.com/-/spec/opensearch/1.1/\"\n";
        echo "                       xmlns:moz=\"http://www.mozilla.org/2006/browser/search/\">\n";
        echo "  <ShortName>$banner</ShortName>\n";
        echo "  <Description>Search the $banner music database</Description>\n";
        echo "  <Tags>$banner</Tags>\n";
        echo "  <Image height=\"16\" width=\"16\"\n";
        echo "         type=\"image/vnd.microsoft.icon\">${baseURL}${favicon}</Image>\n";
        echo "  <Url type=\"text/html\"\n";
        echo "       template=\"$baseURL?session=&amp;action=find&amp;search={searchTerms}&amp;src=opensearch\"/>\n";
        echo "  <Query role=\"example\" searchTerms=\"outer space\"/>\n";
        echo "  <moz:SearchForm>$baseURL</moz:SearchForm>\n";
        echo "</OpenSearchDescription>\n";
    }
}
