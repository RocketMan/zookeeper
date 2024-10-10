<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2024 Jim Mason <jmason@ibinx.com>
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

namespace ZK\UI;

use ZK\Engine\TemplateFactory;

class TemplateFactoryUI extends TemplateFactory {
    public function __construct() {
        parent::__construct(__DIR__ . '/templates');
        $this->app->content = new \stdClass();

        $filter = new \Twig\TwigFilter('smartURL', [ '\ZK\UI\UICommon', 'smartURL' ], [ 'is_safe' => [ 'html' ] ]);
        $this->twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('markdown', [ '\ZK\UI\UICommon', 'markdown' ], [ 'is_safe' => [ 'html' ] ]);
        $this->twig->addFilter($filter);
    }

    public function setContext($menu = null, $menuItem = null, $html = null) {
        $this->app->content->data = $html;
        $this->app->content->template = $menuItem ? $menuItem->getTemplate() : null;
        $this->app->content->title = $menuItem ? $menuItem->getTitle() : null;
        $this->app->menu = $menu ?? [];
        $this->app->submenu = $menuItem ? $menuItem->composeSubmenu($_REQUEST['action'] ?? '', $_REQUEST['subaction'] ?? '') : [];
        $this->app->tertiary = $menuItem ? $menuItem->getTertiary() : null;
        $this->app->extra = $menuItem ? $menuItem->getExtra() : null;
    }
}
