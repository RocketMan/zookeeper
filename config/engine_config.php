<?php
/**
 * map the engine interfaces onto the implementation classes
 */
$config = [
    ZK\Engine\IArtwork::class =>        ZK\Engine\ArtworkImpl::class,
    ZK\Engine\IDJ::class =>             ZK\Engine\DJImpl::class,
    ZK\Engine\IChart::class =>          ZK\Engine\ChartImpl::class,
    ZK\Engine\IEditor::class =>         ZK\Engine\EditorImpl::class,
    ZK\Engine\ILibrary::class =>        ZK\Engine\LibraryImpl::class,
    ZK\Engine\IPlaylist::class =>       ZK\Engine\PlaylistImpl::class,
    ZK\Engine\IReview::class =>         ZK\Engine\ReviewImpl::class,
    ZK\Engine\IUser::class =>           ZK\Engine\UserImpl::class,
];
