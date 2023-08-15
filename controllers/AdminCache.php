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
use ZK\Engine\TemplateFactory;

class AdminCache implements IController {
    protected const VALID_EXTENSIONS = ['html', 'xml'];

    protected $dirs = 0;
    protected $files = 0;
    protected $verbose = false;

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

    /**
     * recursively warm up the template cache for the specified directory
     *
     * @param $factory template factory
     * @param $dir path to target template directory
     * @param $offset start of template name in file path
     */
    protected function warmCacheDir($factory, $dir, $offset) {
        if(!is_dir($dir))
            return;

        foreach(new \DirectoryIterator($dir) as $file) {
            if($file->isDot())
                continue;
            if($file->isFile() &&
                    in_array($file->getExtension(), self::VALID_EXTENSIONS)) {
                $template = substr($file->getPathname(), $offset);
                if($this->verbose)
                    echo "    loading $template\n";
                $factory->load($template);
                $this->files++;
            } else if($file->isDir())
                $this->warmCacheDir($factory, $file->getPathname(), $offset);
        }
    }

    /**
     * recursively warm up the template cache for the specified directory
     *
     * @param $dir path to target template directory
     */
    protected function warmCache($dir) {
        $this->dirs = $this->files = 0;
        echo "warming cache for $dir:\n";
        $this->rmdir($dir . "/.cache");
        echo "  {$this->files} cache files deleted\n";
        $this->dirs = $this->files = 0;
        $factory = new TemplateFactory($dir);
        $this->warmCacheDir($factory, $dir . "/default", strlen($dir) + 9);
        echo "  {$this->files} templates loaded\n";
    }

    public function processRequest() {
        if(php_sapi_name() != "cli") {
            http_response_code(400);
            return;
        }

        if(Engine::param('template_cache_enabled')) {
            $this->verbose = $_REQUEST["verbose"] ?? false;

            switch($_REQUEST["action"] ?? "") {
            case "warmup":
                $this->warmCache(__DIR__ . "/templates");
                $this->warmCache(dirname(__DIR__) . "/ui/templates");
                break;
            case "clean":
            case "clear":
                $this->rmdir(__DIR__ . "/templates/.cache");
                $this->rmdir(dirname(__DIR__) . "/ui/templates/.cache");
                echo "removed {$this->files} files in {$this->dirs} directories\n";
                break;
            default:
                echo "Usage: zk cache:{clear|warmup} [verbose=1]\n";
                break;
            }
        } else
            echo "Template cache is disabled.  No change.\n";
    }
}
