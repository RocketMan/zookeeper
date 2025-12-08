<?php
/**
 * Target to controller mappings
 *
 * The first item is the default if no target is specified.
 */
$controllers = [
    'main' =>         ZK\UI\UIController::class,
    'addexp' =>       ZK\Controllers\ExportAdd::class,
    'export' =>       ZK\Controllers\ExportPlaylist::class,
    'afile' =>        ZK\Controllers\ExportAfile::class,
    'opensearch' =>   ZK\Controllers\OpenSearch::class,
    'artwork' =>      ZK\Controllers\ArtworkControl::class,
    'cache' =>        ZK\Controllers\CacheControl::class,
    'daily' =>        ZK\Controllers\RunDaily::class,
    'print' =>        ZK\Controllers\PrintTags::class,
    'rss' =>          ZK\Controllers\RSS::class,
    'sso' =>          ZK\Controllers\SSOLogin::class,
    'push' =>         ZK\Controllers\PushServer::class,
    'validate' =>     ZK\Controllers\Validate::class,
    'challenge' =>    ZK\Controllers\Challenge::class,
    'turnstile' =>    ZK\Controllers\Turnstile::class,
];

/**
 * API controllers
 *
 * Each entry is a JSON:API type to RequestHandler mapping
 */
$apiControllers = [
    'album' =>        ZK\API\Albums::class,
    'label' =>        ZK\API\Labels::class,
    'review' =>       ZK\API\Reviews::class,
    'playlist' =>     ZK\API\Playlists::class,
    'search' =>       ZK\API\UnifiedSearch::class,
];
