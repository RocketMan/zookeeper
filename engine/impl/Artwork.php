<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2022 Jim Mason <jmason@ibinx.com>
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

use GuzzleHttp\Client;

/**
 * Artwork operations
 */
class ArtworkImpl extends DBO implements IArtwork {
    const CACHE_DIR = 'img/.cache/';

    protected function fetchImage($url) {
        // realpath() won't work here if cacheDir doesn't already exist
        $cacheDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "img" .
                DIRECTORY_SEPARATOR . ".cache";

        if(!is_dir($cacheDir) && !mkdir($cacheDir))
            return null;

        $file = sha1(uniqid(rand()));
        $path = $cacheDir . DIRECTORY_SEPARATOR . $file;
        $client = new Client();
        try {
            $client->get($url, [ 'sink' => $path ]);
            switch(mime_content_type($path)) {
            case "image/jpeg":
                $file .= ".jpeg";
                break;
            case "image/png":
                $file .= ".png";
                break;
            case "image/gif":
                $file .= ".gif";
                break;
            case "image/svg+xml":
                $file .= ".svg";
                break;
            }

            $target = $cacheDir . DIRECTORY_SEPARATOR . substr($file, 0, 2);
            if(!is_dir($target))
                mkdir($target);
            $target .= DIRECTORY_SEPARATOR . substr($file, 2, 2);
            if(!is_dir($target))
                mkdir($target);
            $target .= DIRECTORY_SEPARATOR . substr($file, 4);

            if(!rename($path, $target)) {
                error_log("fetchImage: rename failed src=$path, target=$target.");
                unlink($path);
                $file = null;
            }
        } catch(\Exception $e) {
            error_log("fetchImage: " . $e->getMessage());
            $file = null;
        }

        return $file;
    }

    public function getAlbumArt($tag) {
        $query = "SELECT artwork image_id, image_uuid, info_url FROM albummap a " .
            "LEFT JOIN artwork i ON a.artwork = i.id WHERE tag = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $tag);
        return $stmt->executeAndFetch();
    }

    public function getArtistArt($artist) {
        $query = "SELECT artwork image_id, image_uuid, info_url FROM artistmap a " .
            "LEFT JOIN artwork i ON a.artwork = i.id WHERE name = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $artist);
        return $stmt->executeAndFetch();
    }

    public function insertAlbumArt($tag, $imageUrl, $infoUrl) {
        $image = $this->getAlbumArt($tag);
        if($image)
            $uuid = $image['image_uuid'];
        else {
            $imageId = $uuid = null;
            if($imageUrl || $infoUrl) {
                $uuid = $imageUrl ? $this->fetchImage($imageUrl) : null;
                if($imageUrl && !$uuid) {
                    // fetchImage failed; don't cache
                    return null;
                }

                $query = "INSERT INTO artwork (image_uuid, info_url) VALUES (?,?)";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, $uuid);
                $stmt->bindValue(2, $infoUrl);
                if($stmt->execute())
                    $imageId = $this->lastInsertId();
            }

            $query = "INSERT INTO albummap (tag, artwork) VALUES (?,?)";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $tag);
            $stmt->bindValue(2, $imageId);
            $stmt->execute();
        }

        return $uuid;
    }

    public function insertArtistArt($artist, $imageUrl, $infoUrl) {
        $image = $this->getArtistArt($artist);
        if($image)
            $uuid = $image['image_uuid'];
        else {
            $imageId = $uuid = null;
            if($imageUrl || $infoUrl) {
                $uuid = $imageUrl ? $this->fetchImage($imageUrl) : null;
                if($imageUrl && !$uuid) {
                    // fetchImage failed; don't cache
                    return null;
                }

                $query = "INSERT INTO artwork (image_uuid, info_url) VALUES (?,?)";
                $stmt = $this->prepare($query);
                $stmt->bindValue(1, $uuid);
                $stmt->bindValue(2, $infoUrl);
                if($stmt->execute())
                    $imageId = $this->lastInsertId();
            }

            $query = "INSERT INTO artistmap (name, artwork) VALUES (?,?)";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $artist);
            $stmt->bindValue(2, $imageId);
            $stmt->execute();
        }

        return $uuid;
    }

    public function getCachePath($key) {
        return self::CACHE_DIR .
            substr($key, 0, 2) . '/' .
            substr($key, 2, 2) . '/' .
            substr($key, 4);
    }

    public function expireCache($days=7) {
        $query = "DELETE FROM artistmap, artwork USING artistmap " .
                 "LEFT JOIN artwork ON artistmap.artwork = artwork.id " .
                 "WHERE ADDDATE(cached, ?) < NOW()";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $days);
        $success = $stmt->execute();

        $query = "DELETE FROM albummap, artwork USING albummap " .
                 "LEFT JOIN artwork ON albummap.artwork = artwork.id " .
                 "WHERE ADDDATE(cached, ?) < NOW()";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $days);
        $success &= $stmt->execute();

        return $success;
    }

    public function expireEmpty($days=1) {
        $query = "DELETE FROM artistmap " .
                 "WHERE artwork IS NULL AND ADDDATE(cached, ?) < NOW()";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $days);
        $success = $stmt->execute();

        $query = "DELETE FROM albummap " .
                 "WHERE artwork IS NULL AND ADDDATE(cached, ?) < NOW()";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $days);
        $success &= $stmt->execute();

        return $success;
    }
}
