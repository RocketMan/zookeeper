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
    'daily' =>        ZK\Controllers\RunDaily::class,
    'print' =>        ZK\Controllers\PrintTags::class,
    'rss' =>          ZK\Controllers\RSS::class,
    'api' =>          ZK\Controllers\API::class,
    'sso' =>          ZK\Controllers\SSOLogin::class,
    'push' =>         ZK\Controllers\PushServer::class,
    'validate' =>     ZK\Controllers\Validate::class,
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
