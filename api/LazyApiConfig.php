<?php

namespace ZK\API;

use ZK\Engine\ConfigInterface;

/**
 * configuration loader
 *
 * lazy loads and caches the api configuration
 */
class LazyApiConfig {
    protected ConfigInterface $apiConfig;

    public function __construct(
        protected ConfigInterface $config,
    ) {}

    public function getAppConfig(): ConfigInterface {
        return $this->config;
    }

    public function getApiConfig(): ConfigInterface {
        return $this->apiConfig ??= $this->config->withConfigFrom('controller_config', 'apiControllers');
    }
}