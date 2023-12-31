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

namespace ZK\Engine;

/**
 * DynamicPropertyTrait implements dynamic properties for a class.
 *
 * Automatic dynamic properties are deprecated as of PHP 8.2;
 * see: https://www.php.net/manual/en/migration82.deprecated.php
 *
 * This trait adds support for dynamic properties to any class,
 * for any version of PHP.
 */
trait DynamicPropertyTrait {
    protected $propertyMap = [];

    public function __isset($var) {
        return key_exists($var, $this->propertyMap);
    }

    public function __get($var) {
        if(key_exists($var, $this->propertyMap))
            return $this->propertyMap[$var];

        $shortName = (new \ReflectionClass($this))->getShortName();
        trigger_error("Undefined property $shortName::$var", E_USER_WARNING);
    }

    public function __set($var, $val) {
        $this->propertyMap[$var] = $val;
    }

    public function __unset($var) {
        unset($this->propertyMap[$var]);
    }
}
