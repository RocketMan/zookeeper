<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2024 Jim Mason <jmason@ibinx.com>
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

class SafeSession {
    public function getDN() { return Engine::session()->getDN(); }
    public function getUser() { return Engine::session()->getUser(); }
    public function isUser($user) { return !strcasecmp($this->getUser() ?? '', $user); }

    public function isAuth($mode) {
        return Engine::session()->isAuth($mode);
    }
}

class LazyLoadParams {
    /**
     * list of Engine::params safe for templates
     */
    private const TEMPLATE_SAFE_PARAMS = [
        'copyright',
        'email',
        'favicon',
        'logo',
        'nme',
        'station',
        'station_full',
        'station_slogan',
        'station_title',
        'stylesheet',
        'urls',
    ];

    public $request; // explicit, as we assign by reference later
    private $params = [];

    public function __isset($name) {
        return key_exists($name, $this->params) ||
            in_array($name, self::TEMPLATE_SAFE_PARAMS);
    }

    public function __get($name) {
        return $this->params[$name] ??=
            in_array($name, self::TEMPLATE_SAFE_PARAMS) ?
                Engine::param($name) : null;
    }

    public function __set($name, $value) {
        $this->params[$name] = $value;
    }
}

class TemplateFactory {
    protected $twig;
    protected $app;

    public function __construct(string $templateRoot) {
        $this->app = new LazyLoadParams();
        $this->app->request = &$_REQUEST;
        $this->app->session = new SafeSession();
        $this->app->sso = !empty(Engine::param('sso')['client_id']);
        $this->app->version = Engine::VERSION;

        $path = [];
        foreach([
            Engine::param('custom_template_dir', 'custom'),
            'default',
            ''
        ] as $dir) {
            $rpath = realpath($templateRoot . '/' . $dir);
            if($rpath)
                $path[] = $rpath;
        }

        $cacheDir = Engine::param('template_cache_enabled') ?
                        $templateRoot . '/.cache' : false;
        if($cacheDir && !is_dir($cacheDir) && !mkdir($cacheDir)) {
            error_log("TemplateFactory: cannot create $cacheDir");
            $cacheDir = false; // disable cache
        }

        $loader = new \Twig\Loader\FilesystemLoader($path);
        $this->twig = new \Twig\Environment($loader, [ 'cache' => $cacheDir ]);
        $this->twig->addGlobal('app', $this->app);

        $filter = new \Twig\TwigFilter('decorate', [ '\ZK\Engine\Engine', 'decorate' ]);
        $this->twig->addFilter($filter);
    }

    public function load($template) {
        return $this->twig->load($template);
    }

    /**
     * check whether the cache is stale for the specified template
     *
     * @param $template target template
     * @return true if stale, false if fresh, null if not cached
     */
    public function isCacheStale(string $template): ?bool {
        $cache = $this->twig->getCache(false);
        $key = $cache->generateKey($template, $this->twig->getTemplateClass($template));
        $ts = $cache->getTimestamp($key); // 0 if cache file does not exist
        return $ts === 0 ? null : !$this->twig->isTemplateFresh($template, $ts);
    }
}
