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

namespace ZK\Controllers;

use ZK\Engine\Engine;

class ClearCache implements IController {
    protected $dirs = 0;
    protected $files = 0;

    /**
     * recursively remove contents of specified directory
     *
     * @param $dir path to target directory
     * @param $self true to remove $dir itself (optional, default false)
     *
     * increments $this->dirs and $this->files counters with
     * number of removed directories and files, respectively.
     */
    protected function rmdir($dir, $self = false) {
        if(!is_dir($dir))
            return;

        foreach(new \DirectoryIterator($dir) as $file) {
            if($file->isDot())
                continue;
            if($file->isFile() && unlink($file->getPathname()))
                $this->files++;
            else if($file->isDir())
                $this->rmdir($file->getPathname(), true);
        }

        if($self && \rmdir($dir))
            $this->dirs++;
    }

    public function processRequest() {
        if(php_sapi_name() != "cli") {
            http_response_code(400);
            return;
        }

        if(Engine::param('template_cache_enabled')) {
            $this->rmdir(__DIR__ . "/templates/.cache");
            $this->rmdir(dirname(__DIR__) . "/ui/templates/.cache");
        }

        echo "removed {$this->files} files in {$this->dirs} directories\n";
    }
}
