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

use ZK\Controllers\IController;

use DI\Container;
use DI\ContainerBuilder;
use Psr\Log\LoggerInterface;

class Dispatcher {
    private Container $container;
    private string $default;

    public function __construct() {
        $config = new Config();

        $controllerConfig = $config->withConfigFrom('controller_config', 'controllers');

        $customControllers = $config->get('custom_controllers');
        if ($customControllers)
            $controllerConfig = $controllerConfig->merge($customControllers);

        $this->default = $controllerConfig->default();

        $controllerAndEngineConfig = $controllerConfig->withConfigFrom('engine_config', 'config', true);

        $builder = new ContainerBuilder();
        $builder->addDefinitions(array_map(
            fn($impl) => \DI\autowire($impl),
            $controllerAndEngineConfig->asArray()
        ));

        $builder->addDefinitions([
            ConfigInterface::class => $config,
            Dispatcher::class => $this,
            LoggerInterface::class => \DI\factory(fn(LoggerFactory $fact) => $fact->create()),
            PDO::class => \DI\factory(fn(PDOFactory $fact) => $fact->create()),
        ]);

        $this->container = $builder->build();
    }

    /**
     * get the singleton for a specified class
     *
     * @param string $className name of target class
     * @return target class
     */
    public function get(string $className) {
        return $this->container->get($className);
    }

    /**
     * return a new instance of the specified class
     *
     * @param string $className class to instantiate
     * @param array<String, String> names and values of ctor parameters (optional)
     * @return new instance of specified class
     */
    public function make(string $className, array $params = []) {
        return $this->container->make($className, $params);
    }

    /**
     * dispatch request to the specified controller
     */
    public function processRequest(string $controller = '') {
        $controller = empty($controller) ||
                        !$this->container->has($controller)
                ? $this->default
                : $controller;

        $impl = $this->container->get($controller);
        if($impl instanceof IController)
            return $impl->processRequest();

        throw new \Exception("Invalid target '$controller'");
    }
}
