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

class RouteException extends \Exception {}

class Router {
    public function __construct(
        private Config $config,
        private Request $request,
    ) {}

    public function route(array $routes): string {
        $path = $this->request->getUri()->getPath();

        if ($path !== '/') {
            $basePath = dirname(
                $this->request->getServerParam(
                    'SCRIPT_NAME',
                    '/index.php'
                )
            );

            if ($basePath !== '/') {
                $this->request->setAttribute(
                    'mountpath',
                    substr($path, 0, strlen($basePath) + 1)
                );
                $path = substr($path, strlen($basePath));
            }
        }

        foreach ($routes as $route) {
            if (!preg_match($route['pattern'], $path, $matches))
                continue;

            foreach ($matches as $name => $value)
                if (is_string($name))
                    $this->request->setAttribute($name, $value);

            foreach ($route['attributes'] ?? [] as $name => $value)
                $this->request->setAttribute($name, $value);

            return $route['dispatcher'];
        }

        throw new RouteException("No route matched URI '$path'");
    }
}
