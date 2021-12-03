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
 * Library Editor operations
 */
interface IEditor {
    const LOCATION_DEACCESSIONED = 'U';
    const LOCATION_DEEP_STORAGE = 'G';
    const LOCATION_MISSING = 'M';

    const MAX_PLAYABLE_URL_LENGTH = 2083;

    function insertUpdateAlbum(&$album, $tracks, $label);
    function insertUpdateLabel(&$label);
    function importAlbum($json);
    function enqueueTag($tag, $user);
    function dequeueTag($tag, $user);
    function getNumQueuedTags($user);
    function getQueuedTags($user);
    function setLocation($tag, $location, $bin=null);
}
