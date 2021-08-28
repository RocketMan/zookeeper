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
];
