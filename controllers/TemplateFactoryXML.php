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
use ZK\Engine\TemplateFactory;

class TemplateFactoryXML extends TemplateFactory {
    public function __construct() {
        parent::__construct(__DIR__ . '/templates');

        // setup an xml escaper and make it the default strategy
        $escaper = $this->twig->getExtension(\Twig\Extension\EscaperExtension::class);
        $escaper->setEscaper('xml', function($env, $str) {
            return str_replace(['&', '"', "'", '<', '>', '`'],
                ['&amp;' , '&quot;', '&apos;' , '&lt;' , '&gt;', '&apos;'], $str);
        });
        $escaper->setDefaultStrategy('xml');

        $this->app->baseUrl = Engine::getBaseUrl();
        $this->app->UA = Engine::UA;
    }
}
