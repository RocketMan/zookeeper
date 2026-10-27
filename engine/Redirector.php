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

namespace ZK\Engine;

use GuzzleHttp\Psr7\Uri;

class RedirectException extends \Exception {}

class Redirector {
    public function __construct(
        protected Request $request,
    ) {}

    public function processRequest() {
        $expr = $this->request->getAttribute('redirect');
        if (!$expr)
            throw new RedirectException("Missing attribute 'redirect'");

        $path = preg_replace_callback(
            '/%([a-zA-Z0-9_]+)%/',
            fn($matches) => $this->request->getAttribute($matches[1], ''),
            $expr,
        );

        $url = new Uri($this->request->getBaseUrl());

        $qs = strpos($path, '?');
        if ($qs !== false) {
            $url = $url->withQuery(substr($path, $qs + 1));
            $path = substr($path, 0, $qs);
        }

        $p = $url->getPath();
        $p .= ltrim($path, '/');

        $this->request->doHttpRedirect(
            $url->withPath($p)->__toString(),
            [],
            $this->request->getAttribute('code'),
        );
    }
}
