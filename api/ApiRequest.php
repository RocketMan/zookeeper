<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2021 Jim Mason <jmason@ibinx.com>
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

namespace ZK\API;

use Enm\JsonApi\Model\Request\Request;

/**
 * Zookeeper custom implementation of Request
 */
class ApiRequest extends Request {
    /**
     * hack to access private properties of superclass
     */
    protected function getProperty($name) {
        $ref = new \ReflectionClass(parent::class);
        $prop = $ref->getProperty($name);
        $prop->setAccessible(true);
        return $prop->getValue($this);
    }

    /**
     * add support for fields negation (e.g., fields[resource]=-notWanted)
     */
    public function requestsField(string $type, string $name): bool {
        // If caller requests a negated name, he wants to know if the
        // negation literally appears in the fields list, versus the
        // normal semantics of wanting to know if the field is requsted.
        // See ApiServer::cleanUpResource which depends on this behaviour.
        //
        // In this case, delegate to the superclass.
        if(substr($name, 0, 1) == "-")
            return parent::requestsField($type, $name);

        $wantsField = $this->requestsAttributes();
        if($wantsField) {
            $fields = $this->getProperty("fields");
            if(key_exists($type, $fields)) {
                $neg = false;
                foreach($fields[$type] as $field) {
                    if(substr($field, 0, 1) == "-") {
                        if($field === "-" . $name) return false;
                        $neg = true;
                        break;
                    }
                }

                $wantsField = $neg ?
                        !in_array("-" . $name, $fields[$type], true) :
                        in_array($name, $fields[$type], true);
            }
        }

        return $wantsField;
    }
}
