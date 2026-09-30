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

class TemplateFactory {
    protected $twig;
    protected $app;

    public function __construct(
        string $templateRoot,
        TemplateFactoryContext $context,
    ) {
        $this->app = $context->lazyLoadParams;
        $this->app->request = &$_REQUEST;
        $this->app->session = $context->safeSession;
        $this->app->sso = !empty($context->config->get('sso.client_id'));
        $this->app->version = Zookeeper::VERSION;

        $path = [];
        foreach([
            $context->config->get('custom_template_dir', 'custom'),
            'default',
            ''
        ] as $dir) {
            $rpath = realpath($templateRoot . '/' . $dir);
            if($rpath)
                $path[] = $rpath;
        }

        $cacheDir = $context->config->get('template_cache_enabled') ?
                        $templateRoot . '/.cache' : false;
        if($cacheDir && !is_dir($cacheDir) && !mkdir($cacheDir)) {
            error_log("TemplateFactory: cannot create $cacheDir");
            $cacheDir = false; // disable cache
        }

        $loader = new \Twig\Loader\FilesystemLoader($path);
        $this->twig = new \Twig\Environment($loader, [ 'cache' => $cacheDir ]);
        $this->twig->addGlobal('app', $this->app);

        $filter = new \Twig\TwigFilter('decorate', [ '\ZK\Engine\Decorator', 'decorateAsset' ]);
        $this->twig->addFilter($filter);
        $filter = new \Twig\TwigFilter('int', fn($val) => (int)$val);
        $this->twig->addFilter($filter);
        $filter = new \Twig\TwigFilter('truncate', function($value, $length = 80, $preserveWords = false, $ellipsis = '...') {
            if (mb_strlen($value) > $length) {
                $ellipsisLen = mb_strlen($ellipsis);

                // When preserving words, separate the ellipsis with a space.
                if ($preserveWords && $ellipsisLen) {
                    $ellipsis = ' ' . $ellipsis;
                    $ellipsisLen++;
                }

                if ($length <= $ellipsisLen)
                    return mb_substr($value, 0, $length);

                $length -= $ellipsisLen;
                $nextChar = mb_substr($value, $length, 1);
                $value = rtrim(mb_substr($value, 0, $length));

                // If we did not rtrim (and hence are not already at the
                // end of a word) and the next character is also not
                // whitespace, then trim to the last whole word.
                if ($preserveWords
                        && mb_strlen($value) == $length
                        && !ctype_space($nextChar)
                        && preg_match('/^(.+?)\s+\S+$/su', $value, $matches)) {
                    $value = $matches[1];
                }

                $value .= $ellipsis;
            }

            return $value;
        });
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
