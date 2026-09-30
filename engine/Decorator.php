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

class Decorator {
    /**
     * decorate the specified asset for cache control
     *
     * @param string $asset application-relative path to target asset
     * @return string HTML-encoded URI of decorated asset
     */
    public static function decorateAsset(string $asset): string {
        $mtime = filemtime(dirname(__DIR__) . '/' . $asset);
        $ext = strrpos($asset, '.');
        return htmlspecialchars($mtime && $ext !== false
            ? substr($asset, 0, $ext) . '-' . $mtime . substr($asset, $ext)
            : $asset, ENT_QUOTES, 'UTF-8');
    }
}
