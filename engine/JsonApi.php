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

namespace ZK\Engine;

/**
 * JsonApi is a parser helper that provides rudimantary conformance
 * to the JSON:API specification
 *
 * Workflow is to instantiate a JsonApi then visit the top-level data
 * nodes using the `iterateData` method.  As you go, you may add success
 * or failure using the `addSuccess` and `addError` methods.  Upon
 * completion, call `iterateSuccess` to visit the success nodes.
 * `getErrors` returns an array of errors, if any, suitable for the
 * top-level response.
 *
 * Alternatively, you can specify the FLAG_EXCEPTION flag in the ctor,
 * which will cause an Exception to be thrown immediatley upon an error.
 */
class JsonApi {
    const FLAG_ARRAY = 1;
    const FLAG_EXCEPTION = 2;

    private $flags;
    private $data;
    private $included;
    private $internalErrorNr = 0;
    private $errors = [];
    private $success = [];

    /**
     * JsonApi ctor
     *
     * @param $file input data to process
     * @param $type data type
     * @param $flags flag bitmask (optional)
     * @throws \Exception on error if FLAG_EXCEPTION specified
     */
    public function __construct($file, $type, $flags=0) {
        $this->flags = $flags;

        // decode and validate required fields
        $json = json_decode($file, $this->hasFlag(self::FLAG_ARRAY));
        if(!$json)
            $this->addError(-1, json_last_error_msg(), 100);

        if($json && $this->get($json, 'type') == $type)
            $this->data = [ $json ];
        else if($json && is_array($data = $this->get($json, 'data')) &&
                $this->get($data[0], 'type') == $type) 
            $this->data = $data;
        else {
            $this->data = $this->included = null;
            $this->addError(-1, "File is not in the expected format.", 100);
        }

        if($this->data) {
            for($i=0; $i<sizeof($this->data); $i++)
                if(empty($this->get($data = &$this->data[$i], 'lid')))
                    $this->set($data, 'lid', "request-seq-".($i+1));

            $this->included = is_array($included = $this->get($json, 'included'))?$included:null;
        }
    }

    private function hasFlag($flag) {
        return $this->flags & $flag;
    }

    private function get($json, $field) {
        return $this->hasFlag(self::FLAG_ARRAY)?$json[$field]:$json->$field;
    }

    private function set(&$json, $field, $value) {
        if($this->hasFlag(self::FLAG_ARRAY))
            $json[$field] = $value;
        else
            $json->$field = $value;
    }

    public function iterateData($fn) {
        if($this->data) {
            foreach($this->data as $data)
                $fn($data);
        }
    }

    /**
     * get the JSON:API 'included' data node with the specified type and id
     *
     * @param $type type
     * @param $id id
     * @returns included node or null if none found
     */
    public function getIncluded($type, $id) {
        if($this->included) {
            foreach($this->included as $included)
                if($this->get($included, 'type') == $type &&
                        $this->get($included, 'id') == $id)
                    return $included;
        }

        return null;
    }

    public function addError($id, $message, $code = 200) {
        if($this->hasFlag(self::FLAG_EXCEPTION))
            throw new \Exception($message);

        if($id == -1)
            $id = "internal-id-".(++$this->internalErrorNr);

        $this->errors[] = ["id" => $id, "code" => $code, "title" => $message];
    }

    public function getErrors() { return $this->errors; }

    public function addSuccess($id, $attrs=[]) {
        if($id == -1)
            $id = "internal-id-".(++$this->internalErrorNr);

        $attrs['id'] = $id;
        $this->success[] = $attrs;
    }

    public function iterateSuccess($fn) {
        foreach($this->success as $attrs)
            $fn($attrs);
    }
}
