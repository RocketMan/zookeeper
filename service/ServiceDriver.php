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

namespace ZK\Service;

use ZK\Controllers\CommandTarget;
use ZK\Controllers\IController;
use ZK\Engine\Engine;
use ZK\Engine\IArtwork;
use ZK\Engine\ILibrary;
use ZK\Engine\IPlaylist;

use DI\ContainerBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use React\Cache\ArrayCache;
use React\Cache\CacheInterface;
use React\EventLoop\LoopInterface;

class ServiceFactory {
    public function __construct(
        private \DI\Container $container,
    ) {}

    public function create(string $className, array $config): IService {
        // This supports a transitional regime where PushNotification
        // services have been refactored to the new Service namespace.
        if (!class_exists($className)) {
            $fallback = str_replace("\\PushNotification\\", "\\Service\\", $className);
            // We assign only if the refactored class exists, so that on
            // failure, Container::make will report the original class name.
            if (class_exists($fallback))
                $className = $fallback;
        }

        return $this->container->make($className, [
            'config' => $config
        ]);
    }
}

class ServiceDriverInstance {
    public function __construct(
        protected LoopInterface $loop,
        protected NowAiringServer $nas,
        protected DatagramServer $ds,
        protected ServiceFactory $serviceFactory,
    ) {}

    public function run() {
        try {
            // websocket server for subscribers
            $this->nas->start();

            // datagram server for application
            $this->ds->start();

            // setup hosted services, if configured
            $config = Engine::param('hosted_services', Engine::param('push_proxy'));
            if($config) {
                foreach($config as $service) {
                    $app = $this->serviceFactory->create($service['class'] ?? $service['proxy'], $service);
                    $app->start();
                }
            }

            $this->loop->run();
        } catch(\Exception $e) {
            error_log("ServiceDriverInstance::run: " . $e->getMessage());
        }
    }
}

class ServiceDriver extends CommandTarget implements IController {
    private const DISCOGS_BASE = "https://www.discogs.com";
    private const DISCOGS_SEARCH = "https://api.discogs.com/database/search";

    private static $actions = [
        [ "", "emitError" ],
        [ "injectImageData", "injectImageData" ],
    ];

    protected $discogs;
    protected $secret;

    /**
     * perform artist name comparison, accounting for use of ampersand
     *
     * adapted from ZootopiaListener::testArtist
     *
     * @param $albumArtist haystack
     * @param $trackArtist needle
     * @returns true iff needle appears somewhere in haystack
     */
    protected static function testArtist($albumArtist, $trackArtist) {
        if(strpos($trackArtist, '&') !== false &&
                ($i = stripos($albumArtist, ' and ')) !== false)
            $albumArtist = substr_replace($albumArtist, '&', $i + 1, 3);
        else if(strpos($albumArtist, '&') !== false &&
                ($i = stripos($trackArtist, ' and ')) !== false)
            $trackArtist = substr_replace($trackArtist, '&', $i + 1, 3);
        return stripos($albumArtist, $trackArtist) !== false;
    }

    protected function setupDiscogs() {
        $config = Engine::param('discogs');
        if($config) {
            $apiKey = $config['apikey'] ?? null;
            $clientId = $config['client_id'] ?? null;
            $clientSecret = $config['client_secret'] ?? null;

            if($apiKey || $clientId && $clientSecret) {
                $this->secret = $apiKey ?: $clientSecret;
                $this->discogs = new Client([
                    'base_uri' => self::DISCOGS_SEARCH,
                    RequestOptions::HEADERS => [
                        'User-Agent' => Engine::UA,
                        'Authorization' => $apiKey ?
                            "Discogs token=$apiKey" :
                            "Discogs key=$clientId, secret=$clientSecret"
                    ]
                ]);
            }
        }
    }

    /**
     * query discogs for album or artist
     *
     * To search for an album, supply both artist and album;
     * to search for an artist, supply only the artist name.
     * (To search for an artist by name and album, use the method
     * `queryDiscogsArtistByAlbum`.)
     *
     * @param $artist artist name
     * @param $album album name (optional; if supplied, does album search)
     * @returns false iff communications error, result otherwise (can be empty)
     */
    protected function queryDiscogs($artist, $album = null) {
        $artist = preg_replace('/(^The\s)|(,\sThe$)/', '', $artist);
        $success = true;
        $retval = new \stdClass();
        $retval->imageUrl = $retval->infoUrl = $retval->resourceUrl = null;

        try {
            $query = $album ? [
                "artist" => $artist,
                "release_title" => preg_replace('/\(.*$/', '', $album),
                "per_page" => 40
            ] : [
                "query" => $artist,
                "type" => "artist",
                "per_page" => 40
            ];

            $response = $this->discogs->get('', [
                RequestOptions::QUERY => $query
            ]);

            $page = $response->getBody()->getContents();
            $json = json_decode($page);

            if($json->results && ($result = $json->results[0])) {
                if($album) foreach($json->results as $r) {
                    if($r->master_id != $result->master_id)
                        continue;

                    // master releases are definitive
                    if($r->type == "master") {
                        $result = $r;
                        break;
                    }

                    // ignore promos and limited/special editions
                    if(array_reduce($r->format,
                            function($carry, $item) {
                                return $carry ||
                                    $item == "Promo" ||
                                    strpos($item, "Edition") !== false;
                            }))
                        continue;

                    // prefer CD or vinyl
                    switch($r->format[0]) {
                    case "CD":
                    case "Vinyl":
                        $result = $r;
                        break;
                    }
                } else {
                    // advance to the first artist with artwork
                    //
                    // now that the artist search is broader, ensure at
                    // least a portion of the artist's name is present
                    // to prevent spurious hits
                    $success = false;
                    $afrag = mb_substr($artist, 0, 4);
                    foreach($json->results as $r) {
                        if($r->cover_image &&
                                mb_strpos($r->title, $afrag) !== false &&
                                !preg_match('|/spacer.gif$|', $r->cover_image)) {
                            $result = $r;
                            $success = true;
                            break;
                        }
                    }
                }

                if($result->cover_image &&
                        !preg_match('|/spacer.gif$|', $result->cover_image))
                    $retval->imageUrl = $result->cover_image;
                $retval->infoUrl = self::DISCOGS_BASE . $result->uri;
                $retval->resourceUrl = $result->resource_url;
            }
        } catch(\Exception $e) {
            $success = false;
            error_log("ServiceDriver::queryDiscogs: ".$e->getMessage());
        }

        return $success ? $retval : false;
    }

    /**
     * query discogs for artist by {album, artist} tuple
     *
     * @param $artist artist name
     * @param $album album name
     * @returns result if found, false if no match or error
     */
    protected function queryDiscogsArtistByAlbum($artist, $album) {
        $success = false;
        $retval = new \stdClass();
        $retval->imageUrl = null;

        $albumRec = $this->queryDiscogs($artist, $album);

        if($albumRec && $albumRec->resourceUrl) {
            try {
                $response = $this->discogs->get($albumRec->resourceUrl);

                $page = $response->getBody()->getContents();
                $json = json_decode($page);

                if($json) {
                    $artists = $json->artists ?? null;
                    $artist = preg_replace('/(^The\s)|(,\sThe$)/', '', $artist);
                    if($artists && count($artists)) {
                        foreach($artists as $candidate) {
                            if(self::testArtist($candidate->name, $artist)) {
                                $response = $this->discogs->get($candidate->resource_url);

                                $page = $response->getBody()->getContents();
                                $json = json_decode($page);

                                if($json) {
                                    $images = $json->images ?? null;
                                    if($images && count($images)) {
                                        $retval->imageUrl = $images[0]->uri;
                                        foreach($images as $image) {
                                            if($image->type == "primary") {
                                                $retval->imageUrl = $image->uri;
                                                break;
                                            }
                                        }
                                    }
                                    $retval->infoUrl = $json->uri;
                                    $retval->resourceUrl = $json->resource_url;
                                    $retval->album = $albumRec;
                                    $success = true;
                                }
                                break;
                            }
                        }
                    }
                }
            } catch(\Exception $e) {
                error_log("ServiceDriver::queryDiscogsArtistByAlbum: ".$e->getMessage());
            }
        }
        return $success ? $retval : false;
    }

    public function emitError() {
        http_response_code(400);
    }

    public function injectImageData() {
        $msg = $_POST['msg'] ?? '{}';
        $sig = $_POST['sig'] ?? '{}';

        if($this->discogs &&
                NowAiringServer::validateSig($msg, $sig, $this->secret)) {
            $entry = json_decode($msg, true);
            if($entry['id']) {
                $imageApi = Engine::api(IArtwork::class);

                if($entry['track_tag']) {
                    // is the album already known to us?
                    $image = $imageApi->getAlbumArt($entry['track_tag'], true);
                    if($image) {
                        // if yes, reuse it...
                        $imageUuid = $image['image_uuid'];
                        $infoUrl = $image['info_url'];
                    } else {
                        // otherwise, query Discogs
                        $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $entry['track_tag']);
                        $iscoll = count($albums) ? $albums[0]["iscoll"] : false;
                        $result = $this->queryDiscogs($iscoll ? "Various" : $entry['track_artist'], $entry['track_album']);

                        if($result) {
                            $imageUuid = $imageApi->insertAlbumArt($entry['track_tag'], $result->imageUrl, $result->infoUrl);
                            $infoUrl = $result->infoUrl;
                        }
                    }
                }

                if(!isset($imageUuid) &&
                        strlen(trim($entry['track_artist']))) {
                    // is the artist already known to us?
                    $image = $imageApi->getArtistArt($entry['track_artist'], true);
                    if($image) {
                        // if yes, reuse it...
                        $imageUuid = $image['image_uuid'];
                        $infoUrl = $image['info_url'];
                    } else {
                        // otherwise, query Discogs
                        $result = strlen(trim($entry['track_album'])) ?
                            $this->queryDiscogsArtistByAlbum($entry['track_artist'], $entry['track_album']) : null;
                        if(!$result)
                            $result = $this->queryDiscogs($entry['track_artist']);

                        if($result) {
                            $imageUuid = $imageApi->insertArtistArt($entry['track_artist'], $result->imageUrl, $result->infoUrl);
                            $infoUrl = $result->infoUrl;
                        }
                    }
                }

                $entry['info_url'] = $infoUrl ?? null;
                $entry['image_url'] = isset($imageUuid) ? $imageApi->getCachePath($imageUuid) : ($entry['track_tag'] ? "img/album-sleeve.svg" : null);
                $msg = json_encode($entry);
            }
        }

        header("Content-Type: application/json");
        echo $msg;
    }

    public function processLocal($action, $subaction) {
        $this->dispatchAction($action, self::$actions);
    }

    public function processRequest() {
        if (!Engine::param('push_enabled', true)) {
            if (php_sapi_name() != "cli") {
                error_log("Push notification is disabled");
                http_response_code(500); // 500 Internal Server Error
            }

            die("Push notification is disabled.\n\n" .
                "See INSTALLATION.md for more information.\n");
            return;
        }

        if(php_sapi_name() != "cli") {
            $this->setupDiscogs();
            $this->processLocal($_REQUEST['action'] ?? '', null);
            return;
        }

        $builder = new ContainerBuilder();
        $builder->addDefinitions([
            CacheInterface::class => \DI\create(ArrayCache::class)->constructor(PushServer::RESOLVER_CACHE_SIZE),
            LoopInterface::class => function() {
                return \React\EventLoop\Loop::get();
            },
        ]);

        $container = $builder->build();
        $server = $container->get(ServiceDriverInstance::class);
        $server->run();
    }
}
