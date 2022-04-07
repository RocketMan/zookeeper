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
    const CACHE_DIR = 'img/.cache/';  // must be slash-terminated

    protected function fetchImage($url) {
        // realpath() won't work here if cacheDir doesn't already exist
        $cacheDir = dirname(__DIR__, 2);
        foreach(explode('/', self::CACHE_DIR) as $dir)
            $cacheDir .= DIRECTORY_SEPARATOR . $dir;

        if(!is_dir($cacheDir) && !mkdir($cacheDir)) {
            error_log("fetchImage: cannot create $cacheDir");
            return null;
        }

        $file = sha1(uniqid(rand()));
        $path = $cacheDir . $file;
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
            case "image/webp":
                $file .= ".webp";
                break;
            }

            $target = $cacheDir . substr($file, 0, 2);
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
            if(is_file($path))
                unlink($path);
            $file = null;
        }

        return $file;
    }

    public function getAlbumArt($tag, $newRef = false) {
        $query = "SELECT artwork image_id, image_uuid, info_url FROM albummap a " .
            "LEFT JOIN artwork i ON a.artwork = i.id WHERE tag = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $tag);
        $result = $stmt->executeAndFetch();
        if($result && $result['image_id'] && $newRef) {
            $query = "UPDATE albummap SET cached = NOW() WHERE tag = ?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $tag);
            $stmt->execute();
        }
        return $result;
    }

    public function getArtistArt($artist, $newRef = false) {
        $query = "SELECT artwork image_id, image_uuid, info_url FROM artistmap a " .
            "LEFT JOIN artwork i ON a.artwork = i.id WHERE name = ?";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $artist);
        $result = $stmt->executeAndFetch();
        if($result && $result['image_id'] && $newRef) {
            $query = "UPDATE artistmap SET cached = NOW() WHERE name = ?";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $artist);
            $stmt->execute();
        }
        return $result;
    }

    public function insertAlbumArt($tag, $imageUrl, $infoUrl) {
        $image = $this->getAlbumArt($tag, true);
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
        $image = $this->getArtistArt($artist, true);
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
        // this is a URI-path, not a file path, so we use forward slash
        return self::CACHE_DIR .
            substr($key, 0, 2) . '/' .
            substr($key, 2, 2) . '/' .
            substr($key, 4);
    }

    public function expireCache($days=10, $expireAlbums=false) {
        $count = 0;
        $cacheDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;

        $query = "SELECT image_uuid FROM artwork " .
                 "LEFT JOIN artistmap ON artistmap.artwork = artwork.id " .
                 "WHERE ADDDATE(cached, ?) < NOW()";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $days);
        $images = $stmt->executeAndFetchAll();

        $query = "DELETE FROM artistmap, artwork USING artistmap " .
                 "LEFT JOIN artwork ON artistmap.artwork = artwork.id " .
                 "WHERE ADDDATE(cached, ?) < NOW()";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $days);
        $success = $stmt->execute();

        if($success) {
            $count = count($images);
            foreach($images as $image) {
                if($image['image_uuid']) {
                    $path = $cacheDir . $this->getCachePath($image['image_uuid']);
                    unlink(realpath($path));
                }
            }
        }

        if($success && $expireAlbums) {
            $query = "SELECT image_uuid FROM artwork " .
                     "LEFT JOIN albummap ON albummap.artwork = artwork.id " .
                     "WHERE ADDDATE(cached, ?) < NOW()";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $days);
            $images = $stmt->executeAndFetchAll();

            $query = "DELETE FROM albummap, artwork USING albummap " .
                     "LEFT JOIN artwork ON albummap.artwork = artwork.id " .
                     "WHERE ADDDATE(cached, ?) < NOW()";
            $stmt = $this->prepare($query);
            $stmt->bindValue(1, $days);
            $success = $stmt->execute();

            if($success) {
                $count += count($images);
                foreach($images as $image) {
                    if($image['image_uuid']) {
                        $path = $cacheDir . $this->getCachePath($image['image_uuid']);
                        unlink(realpath($path));
                    }
                }
            }
        }

        return $success ? $count : false;
    }

    public function expireEmpty($days=1) {
        $query = "DELETE FROM artistmap " .
                 "WHERE artwork IS NULL AND ADDDATE(cached, ?) < NOW()";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $days);
        $success = $stmt->execute();

        $count = $success ? $stmt->rowCount() : 0;

        $query = "DELETE FROM albummap " .
                 "WHERE artwork IS NULL AND ADDDATE(cached, ?) < NOW()";
        $stmt = $this->prepare($query);
        $stmt->bindValue(1, $days);
        $success &= $stmt->execute();

        return $success ? $stmt->rowCount() + $count : false;
    }
}
