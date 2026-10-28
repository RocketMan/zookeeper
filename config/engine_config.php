<?php
/**
 * configuration for routing and engine dependency injection
 */
$config = [
    'engine' => [
        ZK\Engine\IArtwork::class =>        ZK\Engine\ArtworkImpl::class,
        ZK\Engine\IDJ::class =>             ZK\Engine\DJImpl::class,
        ZK\Engine\IChart::class =>          ZK\Engine\ChartImpl::class,
        ZK\Engine\IEditor::class =>         ZK\Engine\EditorImpl::class,
        ZK\Engine\ILibrary::class =>        ZK\Engine\LibraryImpl::class,
        ZK\Engine\IPlaylist::class =>       ZK\Engine\PlaylistImpl::class,
        ZK\Engine\IReview::class =>         ZK\Engine\ReviewImpl::class,
        ZK\Engine\IUser::class =>           ZK\Engine\UserImpl::class,
        ZK\Engine\IUserAuth::class =>       ZK\Engine\UserAuthImpl::class,
        ZK\Engine\Request::class =>         ZK\Engine\RequestImpl::class,
        ZK\Engine\Session::class =>         ZK\Engine\SessionImpl::class,
    ],
    'router' => [
        'class' => ZK\Engine\Router::class,
        'routes' => [
            [
                'pattern' => '#^(?<basepath>/api/v(?<version>\d+(?:\.\d+)*)/)(?<path>(?<type>\w+).*)$#',
                'dispatcher' => ZK\API\ApiDispatcher::class,
                'attributes' => [ 'versionRegexp' => '#(?<=/api/v)\d+(?:\.\d+)*(?=/)#' ],
            ],
            [
                'pattern' => '#^(?<basepath>/(?:css|js)/.+)-\d+\.(?<type>css|js|map|src\.js)$#',
                'dispatcher' => ZK\Engine\AssetMinifier::class,
            ],
            [
                'pattern' => '#^(?<basepath>/.+)\.(?<type>md)$#',
                'dispatcher' => ZK\Engine\MarkdownHandler::class,
            ],
            [
                'pattern' => '#^/tag/(?<tag>\d+)$#',
                'dispatcher' => ZK\Engine\Redirector::class,
                'attributes' => [
                    'redirect' => '/?action=search&s=byAlbumKey&n=%tag%',
                    'code' => 302,
                ],
            ],
            [
                'pattern' => '#^/zkrss\.php$#',
                'dispatcher' => ZK\Engine\Dispatcher::class,
                'attributes' => [ 'target' => 'rss' ],
            ],
            [
                'pattern' => '#^/ssoLogin\.php$#',
                'dispatcher' => ZK\Engine\Dispatcher::class,
                'attributes' => [ 'target' => 'sso' ],
            ],
            [
                'pattern' => '#^/(?:index\.php)?$#',
                'dispatcher' => ZK\Engine\Dispatcher::class,
            ],
        ],
    ],
];
