<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2026 Jim Mason <jmason@ibinx.com>
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

use ZK\Engine\PlaylistEntry;
use ZK\Engine\PlaylistObserver;
use ZK\Engine\Request;

class PlaylistBuilder extends PlaylistObserver {
    private const PARAMS = [ "action", "editMode", "authUser" ];

    protected $template;
    protected $params;
    protected $break;

    public function __construct(
        protected Request $request,
        TemplateFactoryUI $templateFact,
    ) {
        $this->template = $templateFact->load('list/body.html');
    }

    public function newInstance(array $params) {
        // validate all parameters are present
        // TBD: when we require PHP 8, replace with named params
        $missing = [];
        foreach(self::PARAMS as $param)
            if(!key_exists($param, $params))
                $missing[] = $param;

        if(sizeof($missing))
            throw new \InvalidArgumentException("missing required parameter(s): " . implode(", ", $missing));

        $this->params = $params;
        $this->params['usLocale'] = $this->request->isUsLocale();
        $this->break = false;
        $this->off()->on('comment', function($entry) {
            $fragment = $this->renderBlock('comment', $entry);
            $this->break = false;
            return $fragment;
        })->on('logEvent', function($entry) {
            $fragment = $this->renderBlock('logEvent', $entry);
            $this->break = !$this->params['authUser'];
            return $fragment;
        })->on('setSeparator', function($entry) {
            $fragment = $this->renderBlock('setSeparator', $entry);
            $this->break = true;
            return $fragment;
        })->on('spin', function($entry) {
            $fragment = $this->renderBlock('spin', $entry);
            $this->break = false;
            return $fragment;
        });

        return clone $this;
    }

    protected function renderBlock($block, $entry) {
        return $this->template->renderBlock($block, [
            "params" => $this->params,
            "break" => $this->break,
            "entry" => $entry
        ]);
    }
}
