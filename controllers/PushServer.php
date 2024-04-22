<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2023 Jim Mason <jmason@ibinx.com>
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

namespace ZK\Controllers;

use ZK\Engine\DBO;
use ZK\Engine\Engine;
use ZK\Engine\IArtwork;
use ZK\Engine\ILibrary;
use ZK\Engine\IPlaylist;
use ZK\Engine\OnNowFilter;
use ZK\Engine\PlaylistEntry;
use ZK\Engine\PlaylistObserver;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class NowAiringServer implements MessageComponentInterface {
    const TIME_FORMAT_INTERNAL = "Y-m-d Hi"; // eg, 2019-01-01 1234

    const DISCOGS_BASE = "https://www.discogs.com";
    const DISCOGS_SEARCH = "https://api.discogs.com/database/search";

    const QUERY_DELAY = 5;  // in seconds

    protected $clients;
    protected $loop;
    protected $timer;

    protected $current;
    protected $nextSpin;

    protected $discogs;
    protected $imageQ;

    public static function toJson($show, $spin) {
        $val['name'] = $show?$show['description']:'';
        $val['airname'] = $show?$show['airname']:'';
        $val['show_id'] = $show?(int)$show['id']:0;
        if($show && isset($show['showdate']) && isset($show['showtime'])) {
            $date = $show['showdate'];
            list($from, $to) = explode("-", $show['showtime']);
            $fromStamp = \DateTime::createFromFormat(self::TIME_FORMAT_INTERNAL,
                        $date . " " . $from);
            $toStamp = \DateTime::createFromFormat(self::TIME_FORMAT_INTERNAL,
                        $date . " " . $to);

            // if playlist spans midnight, end time is next day
            if($toStamp < $fromStamp)
                $toStamp->modify("+1 day");

            $val['show_start'] = $fromStamp->format(DATE_RFC3339);
            $val['show_end'] = $toStamp->format(DATE_RFC3339);
        } else {
            $val['show_start'] = '';
            $val['show_end'] = '';
        }
        $val['id'] = $spin?(int)$spin['id']:0;
        $val['track_title'] = $spin?$spin['track']:'';
        $val['track_artist'] = $spin?$spin['artist']:'';
        $val['track_album'] = $spin?$spin['album']:'';
        $tag = $spin?($spin['tag'] ?? ''):'';
        $val['track_tag'] = $tag?$tag:null; // null for empty/zero tag
        $created = $spin?$spin['created']:null;
        $val['track_time'] = $created?$created:'';
        $val['type'] = 'zookeeper';
        $val['event'] = $val['id'] ? 'track' :
                            ($val['show_id'] ? 'show' : 'none');
        return json_encode($val);
    }

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

    public function __construct($loop) {
        $this->clients = new \SplObjectStorage;
        $this->imageQ = new \SplQueue;
        $this->loop = $loop;

        $config = Engine::param('discogs');
        if($config) {
            $apiKey = $config['apikey'];
            $clientId = $config['client_id'];
            $clientSecret = $config['client_secret'];

            if($apiKey || $clientId && $clientSecret) {
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

    /*
     * fetch on-air track from database
     *
     * @returns true if changed, false otherwise
     */
    protected function loadOnNow() {
        $changed = false;
        $event = null;
        $result = Engine::api(IPlaylist::class)->getWhatsOnNow();
        if($show = $result->fetch()) {
            $filter = Engine::api(IPlaylist::class)->getTracksWithObserver($show['id'],
                (new PlaylistObserver())->onSpin(function($entry) use(&$event) {
                    $spin = $entry->asArray();
                    if($spin['tag'])
                        $spin['artist'] = PlaylistEntry::swapNames($spin['artist']);
                    $event = $spin;
                })->on('comment logEvent setSeparator', function($entry) use(&$event) {
                    $event = null;
                }), 0, OnNowFilter::class);
        }
        DBO::release();
        $current = self::toJSON($show, $event);
        if($this->current != $current) {
            $this->current = $current;
            $changed = true;
        }
        $this->nextSpin = $show?$filter->peek():null;
        return $changed;
    }

    protected function worker() {
        // echo "worker awake\n";
        if($this->loadOnNow())
            $this->sendNotification();
    }

    protected function scheduleWorker() {
        if($this->clients->count() > 0) {
            $now = new \DateTime();
            if($this->nextSpin) {
                $next = new \DateTime($this->nextSpin->getCreated());
                $timeToNext = $next->getTimestamp() - $now->getTimestamp();
                if($timeToNext < 0 || $timeToNext > 60)
                    $timeToNext = 0;
            } else
                $timeToNext = 0;

            $delta = $timeToNext?($timeToNext + 1):
                                    (61 - (int)$now->format("s"));

            $this->timer = $this->loop->addTimer($delta, function() {
                $this->worker();
                $this->scheduleWorker();
            });
        }
    }

    public function refreshOnNow() {
        if($this->clients->count() > 0 && $this->loadOnNow())
            $this->sendNotification();
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        if($this->clients->count() == 1) {
            $this->loadOnNow();

            // start worker
            $this->scheduleWorker();
        }
        $this->sendNotification(null, $conn);
        // echo "New connection {$conn->resourceId}\n";
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
                    $afrag = mb_substr($artist, 0, 4);
                    foreach($json->results as $r) {
                        if($r->cover_image &&
                                mb_strpos($r->title, $afrag) !== false &&
                                !preg_match('|/spacer.gif$|', $r->cover_image)) {
                            $result = $r;
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
            error_log("queryDiscogs: ".$e->getMessage());
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
                error_log("queryDiscogsArtistByAlbum: ".$e->getMessage());
            }
        }
        return $success ? $retval : false;
    }

    protected function injectImageData($msg) {
        if($msg && $this->discogs) {
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
                        $result = $this->queryDiscogs($entry['track_artist'], $entry['track_album']);

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
                $entry['image_url'] = isset($imageUuid) ? $imageApi->getCachePath($imageUuid) : ($entry['info_url'] || $entry['track_tag'] ? "img/discogs.svg" : "img/blank.gif");
                $msg = json_encode($entry);

                DBO::release();
            }
        }

        return $msg;
    }

    protected function processImageQueue() {
        if(!$this->imageQ->isEmpty()) {
            $imageApi = Engine::api(IArtwork::class);

            $entry = $this->imageQ->dequeue();
            $artist = $entry->getArtist();

            if($entry->getTag()) {
                $result = $this->queryDiscogs($artist, $entry->getAlbum());

                if($result)
                    $imageUuid = $imageApi->insertAlbumArt($entry->getTag(), $result->imageUrl, $result->infoUrl);
            }

            if(!isset($imageUuid)) {
                $album = $entry->getAlbum();
                $result = strlen(trim($album)) ?
                    $this->queryDiscogsArtistByAlbum($artist, $album) : null;
                if(!$result)
                    $result = $this->queryDiscogs($artist);

                if($result)
                    $imageUuid = $imageApi->insertArtistArt($artist, $result->imageUrl, $result->infoUrl);
            }

            DBO::release();

            if(!$this->imageQ->isEmpty()) {
                $this->loop->addTimer(self::QUERY_DELAY, function() {
                    $this->processImageQueue();
                });
            }
        }
    }

    protected function enqueueEntry($entry, $imageApi) {
        if(!$entry->getCreated()) {
            // no timestamp, don't bother
            return;
        }

        if(preg_match('/(\.gov|\.org|GED|Literacy|NIH|Ad\ Council|Lift\ Jesus)/', implode(' ', $entry->asArray())) || empty(trim($entry->getArtist()))) {
            // it's probably a PSA coded as a spin; let's skip it
            return;
        }

        // fixup artist name
        if($entry->getTag())
            $entry->setArtist(PlaylistEntry::swapNames($entry->getArtist()));

        if($entry->getTag() &&
                $imageApi->getAlbumArt($entry->getTag(), true) ||
                $imageApi->getArtistArt($entry->getArtist(), true)) {
            // the album or artist is already known to us
            return;
        }

        $this->imageQ->enqueue($entry);
    }

    public function loadImages($playlist, $track) {
        $start = $this->imageQ->count();

        $imageApi = Engine::api(IArtwork::class);
        $listApi = Engine::api(IPlaylist::class);

        if($track) {
            $entry = new PlaylistEntry($listApi->getTrack($track));
            if($entry->isType(PlaylistEntry::TYPE_SPIN))
                $this->enqueueEntry($entry, $imageApi);
        } else {
            $visited = [];
            $listApi->getTracksWithObserver($playlist,
                (new PlaylistObserver())->onSpin(function($entry) use($imageApi, &$visited) {
                    $key = $entry->getArtist() . $entry->getTag();
                    if(!key_exists($key, $visited)) {
                        $this->enqueueEntry($entry, $imageApi);
                        $visited[$key] = 1;
                    }
                })
            );
        }

        $queued = $this->imageQ->count() - $start;
        if($queued) {
            echo "loadImages($playlist, $track): $queued queued\n";

            if(!$start) {
                $this->loop->futureTick(function() {
                    $this->processImageQueue();
                });
            }
        }
    }

    public function reloadAlbum($tag, $param) {
        $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $tag);
        if(!count($albums)) {
            echo "reloadAlbum($tag): tag not found\n";
            return;
        }

        $album = $albums[0];

        try {
            switch($album["medium"] ?? null) {
            case 'S':
                $format = "Vinyl, 7\"";
                break;
            case 'T':
            case 'V':
                $format = "Vinyl";
                break;
            case 'M':
                $format = "Cassette";
                break;
            default:
                $format = "CD";
                break;
            }

            $params = [
                "artist" => $album["iscoll"] ?
                    "Various" : PlaylistEntry::swapNames($album["artist"]),
                "release_title" => $album["album"],
                "per_page" => 20
            ];

            $master = $param & 0x1;
            $skip = ($param & ~0xff) >> 8;

            if($master)
                $params["type"] = "master";
            else
                $params["format"] = $format;

            $response = $this->discogs->get('', [
                RequestOptions::QUERY => $params
            ]);

            $page = $response->getBody()->getContents();
            $json = json_decode($page);
            if($json->results && ($result2 = $json->results[0])) {
                foreach($json->results as $r) {
                    if($skip-- > 0)
                        continue;

                    // master releases are definitive
                    if($r->type == "master") {
                        $result2 = $r;
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
                        $result2 = $r;
                        break;
                    }
                }

                $imageUrl = $result2->cover_image &&
                        !preg_match('|/spacer.gif$|', $result2->cover_image) ?
                    $result2->cover_image : null;
                $infoUrl = self::DISCOGS_BASE . $result2->uri;
            }

            if(!empty($imageUrl)) {
                $imageApi = Engine::api(IArtwork::class);
                $imageApi->deleteAlbumArt($tag);
                $uuid = $imageApi->insertAlbumArt($tag, $imageUrl, $infoUrl);
                echo "reloadAlbum($tag): ".($master?'master':$format)." loaded $uuid\n";
            } else
                echo "reloadAlbum($tag): no image found\n";
        } catch(\Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }

    public function sendNotification($msg = null, $client = null) {
        if($msg) {
            if($this->current != $msg)
                $this->current = $msg;
            else
                return;
        } else
            $msg = $this->current;

        $msg = $this->injectImageData($msg);

        if($client)
            $client->send($msg);
        else {
            foreach ($this->clients as $client)
                $client->send($msg);
        }
    }

    public function onMessage(ConnectionInterface $from, $msg) {}

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);

        if($this->clients->count() == 0 && $this->timer) {
            // stop worker
            echo "Cancel timer ".spl_object_hash($this->timer)."\n";
            $this->loop->cancelTimer($this->timer);
            $this->timer = null;
        }
        // echo "Connection {$conn->resourceId} disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        error_log("NowAiringServer: " . $e->getMessage());
        $conn->close();
    }
}

class PushServer implements IController {
    /**
     * This is an endpoint for internal use only.  There should be
     * no need to change it, but if you do, you must also update the
     * corresponding URI in .htaccess in the project root directory.
     */
    const WSSERVER_HOST = "127.0.0.1";
    const WSSERVER_PORT = 32080;

    public static function sendAsyncNotification($show = null, $spin = null) {
        if(!Engine::param('push_enabled', true))
            return;

        $data = ($show != null)?NowAiringServer::toJson($show, $spin):"";
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_sendto($socket, $data, strlen($data), 0,
                        PushServer::WSSERVER_HOST, PushServer::WSSERVER_PORT);
        socket_close($socket);
    }

    public static function lazyLoadImages($playlistId, $trackId = 0) {
        if(!Engine::param('push_enabled', true) ||
                !($config = Engine::param('discogs')) ||
                !$config['apikey'] && !$config['client_id'])
            return;

        $data = "loadImages($playlistId, $trackId)";

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_sendto($socket, $data, strlen($data), 0,
                        PushServer::WSSERVER_HOST, PushServer::WSSERVER_PORT);
        socket_close($socket);
    }

    public static function lazyReloadAlbum($tag, $master = 1, $skip = 0) {
        if(!Engine::param('push_enabled', true) ||
                !($config = Engine::param('discogs')) ||
                !$config['apikey'] && !$config['client_id'])
            return;

        $param = $master & 0xff | $skip << 8;
        $data = "reloadAlbum($tag, $param)";

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_sendto($socket, $data, strlen($data), 0,
                        PushServer::WSSERVER_HOST, PushServer::WSSERVER_PORT);
        socket_close($socket);
    }

    public function processRequest() {
        if(php_sapi_name() != "cli") {
            http_response_code(400);
            return;
        }

        if(!Engine::param('push_enabled', true)) {
            echo "Push notification is disabled.\n\n";
            echo "See INSTALLATION.md for more information.\n";
            return;
        }

        try {
            // websocket server for subscribers
            $loop = \React\EventLoop\Factory::create();
            $nas = new NowAiringServer($loop);
            $wsserver = new \Ratchet\WebSocket\WsServer($nas);
            $wsserver->enableKeepAlive($loop, 30);
            $routes = new RouteCollection();
            $routes->add('/push/onair', new Route('/push/onair', [
                '_controller' => $wsserver
            ]));
            $router = new \Ratchet\Http\Router(
                new UrlMatcher($routes, new RequestContext()));
            new IoServer(new \Ratchet\Http\HttpServer($router),
                new \React\Socket\Server(PushServer::WSSERVER_HOST . ":" .
                                         PushServer::WSSERVER_PORT, $loop));

            // datagram server for application
            $dgfact = new \React\Datagram\Factory($loop);
            $dgfact->createServer(PushServer::WSSERVER_HOST . ":" .
                                  PushServer::WSSERVER_PORT)->then(
                function(\React\Datagram\Socket $client) use($nas) {
                    $client->on('message', function($message, $addr, $client) use($nas) {
                        // echo "received $message from $addr\n";

                        if(preg_match("/^loadImages\((\d+)(,\s*(\d+))?\)$/", $message, $matches))
                            $nas->loadImages($matches[1], $matches[3] ?? 0);
                        else if(preg_match("/^reloadAlbum\((\d+)(,\s*(\d+))?\)$/", $message, $matches))
                            $nas->reloadAlbum($matches[1], $matches[3] ?? 1);
                        else if($message && $message[0] == '{')
                            $nas->sendNotification($message);
                        else // empty message means poll database
                            $nas->refreshOnNow();
                });
            });

            // push proxy server, if configured
            $config = Engine::param('push_proxy');
            if($config) {
                foreach($config as $proxy) {
                    $app = new $proxy['proxy']($loop);
                    $app->connect($proxy['ws_endpoint'],
                                    $proxy['http_endpoints']);
                }
            }

            $loop->run();
        } catch(\Exception $e) {
            error_log("PushServer: " . $e->getMessage());
        }
    }
}
