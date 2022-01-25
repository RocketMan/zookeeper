<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2022 Jim Mason <jmason@ibinx.com>
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
use Enm\JsonApi\Model\Request\RequestInterface;
use Enm\JsonApi\Model\Resource\ResourceInterface;

/**
 * Zookeeper custom implementation of Request
 */
class ApiRequest extends Request {
    protected $requestCache = [];
    protected $fieldNegation = [];

    /**
     * hack to access private properties of superclass
     */
    public function __get($name) {
        $ref = new \ReflectionClass(parent::class);
        $prop = $ref->getProperty($name);
        $prop->setAccessible(true);
        return $prop->getValue($this);
    }

    /**
     * add support for fields negation (e.g., fields[resource]=-notWanted)
     */
    public function requestsField(string $type, string $name): bool {
        $wantsField = $this->requestsAttributes();
        if($wantsField) {
            $fields = $this->fields;
            if(key_exists($type, $fields)) {
                $negatedName = "-" . $name;

                if(!key_exists($type, $this->fieldNegation)) {
                    $this->fieldNegation[$type] = false;
                    foreach($fields[$type] as $field) {
                        if(substr($field, 0, 1) == "-") {
                            $this->fieldNegation[$type] = true;
                            if($field === $negatedName) return false;
                            break;
                        }
                    }
                }

                $wantsField = $this->fieldNegation[$type] ?
                        !in_array($negatedName, $fields[$type], true) :
                        in_array($name, $fields[$type], true);
            }
        }

        return $wantsField;
    }

    /**
     * Fix to use derived class for the subrequest
     */
    public function createSubRequest(
        string $relationship,
        ?ResourceInterface $resource = null,
        bool $keepFilters = false
    ): RequestInterface {
        $requestKey = $relationship . ($keepFilters ? '-filtered' : '-not-filtered');
        if (!key_exists($requestKey, $this->requestCache)) {
            $includes = [];
            foreach ($this->includes as $include) {
                if (strpos($include, '.') !== false && strpos($include, $relationship . '.') === 0) {
                    $includes[] = explode('.', $include, 2)[1];
                }
            }

            $queryFields = [];
            foreach ($this->fields as $type => $fields) {
                $queryFields[$type] = implode(',', $fields);
            }

            $type = $resource ? $resource->type() : $this->type();
            $id = $resource ? $resource->id() : $this->id();
            $relationshipPart = '/' . $relationship;
            if (!$this->requestsInclude($relationship)) {
                $relationshipPart = '/relationships' . $relationshipPart;
            }

            $subRequest = new static(
                $this->method(),
                $this->uri()
                    ->withPath(($this->fileInPath ? '/' . $this->fileInPath : '') . ($this->apiPrefix ? '/' . $this->apiPrefix : '') . '/' . $type . '/' . $id . $relationshipPart)
                    ->withQuery(
                        http_build_query([
                            'fields' => $queryFields,
                            'filter' => $keepFilters ? $this->filter : [],
                            'include' => implode(',', $includes)
                        ])
                    ),
                null,
                $this->apiPrefix
            );

            $subRequest->headers = $this->headers;
            $subRequest->fieldNegation = &$this->fieldNegation;

            $this->requestCache[$requestKey] = $subRequest;
        }

        return $this->requestCache[$requestKey];
    }
}
