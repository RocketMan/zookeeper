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

use DI\Container;
use DI\ContainerBuilder;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class Zookeeper {
    const VERSION = "3.3.2";

    const UA = "Zookeeper/2.0; (+https://zookeeper.ibinx.com/)";

    private ConfigInterface $engineConfig;
    private Container $container;

    public function __construct() {
        $config = new Config();

        $this->engineConfig = $config->withConfigFrom('engine_config');
        $builder = new ContainerBuilder();
        $builder->addDefinitions(array_map(
            fn($impl) => \DI\autowire($impl),
            $this->engineConfig->get('engine', []),
        ));

        $builder->addDefinitions([
            ConfigInterface::class => $config,
            LoggerInterface::class => \DI\factory(fn(LoggerFactory $fact) => $fact->create()),
            PDO::class => \DI\factory(fn(PDOFactory $fact) => $fact->create()),
            ServerRequestInterface::class => fn() => ServerRequest::fromGlobals(),
            Zookeeper::class => $this,
        ]);

        $this->container = $builder->build();
    }

    public function processRequest() {
        $router = $this->container->get(
            $this->engineConfig->get('router.class')
        );

        try {
            $route = $router->route(
                $this->engineConfig->get('router.routes', [])
            );

            $dispatcher = $this->container->get($route);
            $dispatcher->processRequest();
        } catch(RouteException $e) {
            // requested resource does not match any of our routes
            http_response_code(404);
        }
    }
}
