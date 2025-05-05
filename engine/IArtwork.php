<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2025 Jim Mason <jmason@ibinx.com>
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

/**
 * Artwork operations
 */
interface IArtwork {
    function getAlbumArt($tag, $newRef = false);
    function getArtistArt($artist, $newRef = false);
    /*
     * insert album art if it does not already exist
     *
     * $imageURL can be a URL of the image to load, empty string
     * to prevent this tag from having associated album art, or
     * null to prevent temporary re-query of the provider (effective
     * until expired by expireEmpty).
     *
     * @param $tag int tag number
     * @param $imageUrl ?string URL, empty string, or null
     * @param $infoUrl ?string URL or null
     * @return ?string uuid or null
     */
    function insertAlbumArt($tag, $imageUrl, $infoUrl);
    function insertArtistArt($artist, $imageUrl, $infoUrl);
    function getCachePath($key);
    function deleteAlbumArt($tag);
    function deleteArtistArt($artist);
    function expireCache($days=10, $expireAlbums=false);
    function expireEmpty($days=1);
    /**
     * Add `albumart` property for each album
     *
     * albumart will be an absolute URI path if album art exists,
     * null if no album art is cached, or empty string if the album
     * is prevented from having album art.
     *
     * @param $albums target album array (in/out)
     */
    function injectAlbumArt(array &$albums): void;
}
