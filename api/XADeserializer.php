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

use Enm\JsonApi\Model\Resource\JsonResource;
use Enm\JsonApi\Model\Resource\Relationship\RelationshipInterface;
use Enm\JsonApi\Model\Resource\ResourceCollectionInterface;
use Enm\JsonApi\Model\Resource\ResourceInterface;
use Enm\JsonApi\Serializer\Deserializer;

/**
 * JSON:API deserializer with support for 'xa' extension
 *
 * see https://github.com/RocketMan/zookeeper/pull/263
 */
class XADeserializer extends Deserializer {
    protected function deserializeAttribute($attr) {
        if(is_array($attr)) {
            $data = [];
            foreach($attr as $key => $value) {
                switch((string)$key) {
                case "xa:relationships":
                    $dummy = new JsonResource("dummy", 0);
                    $this->buildResourceRelationships($value, $dummy);
                    $value = $dummy->relationships();
                    break;
                case "xa:links":
                    $dummy = new JsonResource("dummy", 0);
                    foreach($value as $name => $link)
                        $this->buildLink($dummy->links(), $name,
                            is_array($link) ? $link : [ 'href' => $link ]);
                    $value = $dummy->links();
                    break;
                default:
                    $value = $this->deserializeAttribute($value);
                    break;
                }
                $data[$key] = $value;
            }
        } else
            $data = $attr;

        return $data;
    }

    protected function buildResource(ResourceCollectionInterface $collection, array $resourceData): ResourceInterface {
        if (!array_key_exists('type', $resourceData)) {
            throw new \InvalidArgumentException('Invalid resource given!');
        }

        $type = (string)$resourceData['type'];
        $id = array_key_exists('id', $resourceData) ? (string)$resourceData['id'] : '';
        $resource = $this->resource($type, $id);
        $collection->set($resource);

        if (array_key_exists('attributes', $resourceData)) {
            foreach((array)$resourceData['attributes'] as $key => $value)
                $resource->attributes()->set($key, $this->deserializeAttribute($value));
        }

        $relationships = array_key_exists('relationships', $resourceData) ? (array)$resourceData['relationships'] : [];
        $this->buildResourceRelationships($relationships, $resource);

        $links = array_key_exists('links', $resourceData) ? (array)$resourceData['links'] : [];
        foreach ($links as $name => $link) {
            $this->buildLink($resource->links(), $name, \is_array($link) ? $link : ['href' => $link]);
        }

        if (array_key_exists('meta', $resourceData)) {
            $resource->metaInformation()->merge((array)$resourceData['meta']);
        }

        return $resource;
    }
}
