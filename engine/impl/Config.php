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

/**
 * Configuration data
 *
 * NOTE: Do not instantiate this class directly; instead inject ConfigInterface
 */
class Config implements ConfigInterface {
    private $config;

    /**
     * ctor
     *
     * @param string $file base filename of configuration file (without extension)
     * @param string $variable variable name in config file (default 'config')
     */
    public function __construct(string $file = 'config', string $variable = 'config') {
        $this->internalLoad($file, $variable);
    }

    private function internalLoad(string $file, string $variable, bool $merge = false) {
        $path = dirname(__DIR__, 2) . "/config/{$file}.php";
        if(!is_file($path))
            throw new \Exception("Config file not found: $file");

        // populate the configuration from the given file and variable
        include $path;
        if(isset($$variable) && is_array($$variable))
            $this->config = $merge
                ? array_merge($this->config, $$variable)
                : $$variable;
        else
            throw new \Exception("Error parsing configuration: file={$file}.php, variable={$variable}");
    }

    public function withConfigFrom(string $file, string $variable = 'config', bool $merge = false): static {
        $obj = clone $this;
        $obj->internalLoad($file, $variable, $merge);
        return $obj;
    }

    public function merge(array $config): static {
        $obj = clone $this;
        $obj->config = array_merge($obj->config, $config);
        return $obj;
    }

    public function iterate(\Closure $fn): mixed {
        switch((new \ReflectionFunction($fn))->getNumberOfParameters()) {
        case 1:
            foreach($this->config as $entry)
                if(($x = $fn($entry)) !== null)
                    return $x;
            break;
        case 2:
            foreach($this->config as $key => $value)
                if(($x = $fn($key, $value)) !== null)
                    return $x;
            break;
        default:
            throw new \InvalidArgumentException("closure expects 1 or 2 arguments");
        }

        return null;
    }

    public function default(): mixed {
        return reset($this->config);
    }

    public function has(string $key): bool {
        return $this->get($key, $this) !== $this;
    }

    public function get(string $key, mixed $default = null): mixed {
        $value = $this->config;

        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value))
                return $default;
            $value = $value[$part];
        }

        return $value;
    }

    public function asArray(): array {
        return $this->config;
    }
}
