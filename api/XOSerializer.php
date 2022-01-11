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

use Enm\JsonApi\Model\Resource\Link\LinkCollectionInterface;
use Enm\JsonApi\Model\Resource\Link\LinkInterface;
use Enm\JsonApi\Model\Resource\Relationship\RelationshipCollectionInterface;
use Enm\JsonApi\Model\Resource\Relationship\RelationshipInterface;
use Enm\JsonApi\Model\Resource\ResourceInterface;
use Enm\JsonApi\Serializer\Serializer;

/**
 * JSON:API serializer with support for 'xattr' extension
 *
 * see https://github.com/RocketMan/zookeeper/pull/263
 */
class XOSerializer extends Serializer {
    protected function serializeAttribute($attr) {
        if(is_array($attr)) {
            $data = [];
            foreach($attr as $key => $value) {
                switch((string)$key) {
                case "xattr:relationships":
                    // xattr:relationships may contain a single
                    // Relationship or a RelationshipCollection
                    $values = [];
                    if($value instanceof RelationshipInterface)
                        $values[$value->name()] = 
                            $this->serializeRelationship($value);
                    else {
                        assert($value instanceof RelationshipCollectionInterface);
                        foreach($value->all() as $relationship)
                            $values[$relationship->name()] = 
                                $this->serializeRelationship($relationship);
                    }
                    $value = $values;
                    break;
                case "xattr:links":
                    // xattr:links may contain a single Link or a LinkCollection
                    $values = [];
                    if($value instanceof LinkInterface)
                        $values[$value->name()] = $this->serializeLink($value);
                    else {
                        assert($value instanceof LinkCollectionInterface);
                        foreach($value->all() as $link)
                            $values[$link->name()] = $this->serializeLink($link);
                    }
                    $value = $values;
                    break;
                default:
                    $value = $this->serializeAttribute($value);
                    break;
                }
                $data[$key] = $value;
            }
        } else
            $data = $attr;

        return $data;
    }

    protected function serializeResource(ResourceInterface $resource, bool $identifierOnly = true): array {
        $data = [
            'type' => $resource->type(),
            'id' => $resource->id(),
        ];

        if (!$resource->metaInformation()->isEmpty()) {
            $data['meta'] = $resource->metaInformation()->all();
        }

        if ($identifierOnly) {
            if ($resource instanceof RelatedMetaInformationInterface && !$resource->relatedMetaInformation()->isEmpty()) {
                foreach ($resource->relatedMetaInformation()->all() as $key => $value) {
                    $data['meta'][$key] = $value;
                }
            }

            return $data;
        }

        if (!$resource->attributes()->isEmpty()) {
            $data['attributes'] = [];
            foreach($resource->attributes()->all() as $key => $value) {
                $data['attributes'][$key] = $this->serializeAttribute($value);
            }
        }

        foreach ($resource->relationships()->all() as $relationship) {
            $data['relationships'][$relationship->name()] = $this->serializeRelationship($relationship);
        }

        foreach ($resource->links()->all() as $link) {
            $data['links'][$link->name()] = $this->serializeLink($link);
        }

        return $data;
    }
}
