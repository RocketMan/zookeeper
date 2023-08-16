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

class CacheControl implements IController {
    protected const TEMPLATE_ROOTS = [
        'ui/templates',
        'controllers/templates',
    ];
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
            if($file->isFile() && unlink($file->getPathname()))
                $this->files++;
            else if($file->isDir() && !$file->isDot())
                $this->rmdir($file->getPathname(), true);
        }

        if($self && \rmdir($dir))
            $this->dirs++;
    }

    /**
     * recursively visit templates in the specified directory
     *
     * @param $dir path to target template directory
     * @param $visitor function to invoke for each visited template
     * @param $offset start of template name in file path (optional)
     */
    protected function visitTemplateDir($dir, $visitor, $offset = null) {
        if(!is_dir($dir) || !($visitor instanceof \Closure))
            return;

        $offset ??= strlen($dir) + 1;

        foreach(new \DirectoryIterator($dir) as $file) {
            if($file->isFile() &&
                    in_array($file->getExtension(), self::VALID_EXTENSIONS)) {
                $template = substr($file->getPathname(), $offset);
                $visitor($template);
            } else if($file->isDir() && !$file->isDot())
                $this->visitTemplateDir($file->getPathname(), $visitor, $offset);
        }
    }

    /**
     * warm up the cache for the specified template directory
     *
     * @param $dir target template directory
     */
    protected function warmCache($dir) {
        echo "warming $dir:\n";
        $path = $this->base . $dir;
        $this->rmdir($path . "/.cache");
        $this->dirs = $this->files = 0;
        $factory = new TemplateFactory($path);
        $this->visitTemplateDir($path . "/default", function($template) use($factory) {
            if($this->verbose)
                echo "  INFO loading $template\n";
            $factory->load($template);
            $this->files++;
        });
        echo "  {$this->files} templates loaded\n";
    }

    /**
     * check the cache for the specified template directory
     *
     * @param $dir target template directory
     */
    protected function checkCache($dir) {
        echo "checking $dir:\n";
        $this->stale = $this->fresh = $this->uncached = 0;
        $path = $this->base . $dir;
        $factory = new TemplateFactory($path);
        $this->visitTemplateDir($path . "/default", function($template) use ($factory) {
            $stale = $factory->isCacheStale($template);
            if($stale) {
                $this->stale++;
                if($this->verbose)
                    echo "  STALE $template\n";
            } else
                $stale === null ? $this->uncached++ : $this->fresh++;
        });
        echo "  {$this->stale} stale, {$this->fresh} fresh, {$this->uncached} uncached templates\n";
    }

    public function processRequest() {
        if(php_sapi_name() != "cli") {
            http_response_code(400);
            return;
        }

        if(!Engine::param('template_cache_enabled')) {
            echo "Template cache is disabled.  No change.\n";
            return;
        }

        $this->verbose = $_REQUEST["verbose"] ?? false;
        $this->base = dirname(__DIR__) . '/';

        switch($_REQUEST["action"] ?? "") {
        case "clean":
        case "clear":
            foreach(self::TEMPLATE_ROOTS as $root)
                $this->rmdir($this->base . $root . "/.cache");
            echo "removed {$this->files} files in {$this->dirs} directories\n";
            break;
        case "check":
            foreach(self::TEMPLATE_ROOTS as $root)
                $this->checkCache($root);
            break;
        case "warmup":
            foreach(self::TEMPLATE_ROOTS as $root)
                $this->warmCache($root);
            break;
        default:
            echo "Usage: zk cache:{check|clear|warmup} [verbose=1]\n";
            break;
        }
    }
}
