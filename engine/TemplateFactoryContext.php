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

class SafeSession {
    public function __construct(
        private Session $session,
    ) {}

    public function getDN() { return $this->session->getDN(); }
    public function getUser() { return $this->session->getUser(); }
    public function isUser($user) { return !strcasecmp($this->getUser() ?? '', $user); }

    public function isAuth($mode) {
        return $this->session->isAuth($mode);
    }
}

class LazyLoadParams {
    /**
     * list of IConfig keys safe for templates
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

    public function __construct(
        protected IConfig $config,
    ) {}

    public function __isset($name) {
        return key_exists($name, $this->params) ||
            in_array($name, self::TEMPLATE_SAFE_PARAMS);
    }

    public function __get($name) {
        return $this->params[$name] ??=
            in_array($name, self::TEMPLATE_SAFE_PARAMS) ?
                $this->config->get($name) : null;
    }

    public function __set($name, $value) {
        $this->params[$name] = $value;
    }
}

class TemplateFactoryContext {
    public function __construct(
        public SafeSession $safeSession,
        public LazyLoadParams $lazyLoadParams,
        public IConfig $config,
    ) {}
}
