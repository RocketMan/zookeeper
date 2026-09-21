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

use ZK\Engine\Engine;

use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Promise;
use React\Promise\PromiseInterface;

/**
 * ZootopiaPoller creates playlists by polling the service
 *
 * To use, in the `config.php` configuration file, include the stanza:
 *
 *    'hosted_services' => [
 *        [
 *            'class' => ZK\Service\ZootopiaPoller::class,
 *            'poll_url' => 'target url',
 *            'apikey' => 'apikey',
 *            'base_url' => 'base url',
 *            'title' => 'show title',  // or array of show titles
 *            'airname' => 'airname',   // or array of airnames
 *            'recent' => true|false,   // include in recent airplay (optional; default false)
 *             'tz' => 'tzName',
 *            'caption' => 'caption',
 *        ],
 *        ...more hosted services...
 *    ],
 *
 * where:
 *    'class' specifies this class or a derivative;
 *    'poll_url' target URL to poll;
 *    'apikey' is the Zookeeper API key;
 *    'base_url' base URL of the Zookeeper server (must be slash-terminated);
 *    'airname' airname for playlists;
 *    'title' title for playlists;
 *    'tz' specifies the timezone of the ws_endpoint, if different to
 *         the Zookeeper server, or null if they are the same;
 *    'caption' comment to lead the playlist, or null if none;
 *    'use_upstream_coverart' (optional boolean; default false).
 *
 * If an array of titles and/or airnames are given, one will be selected
 * at random for each new show that is created.  Airnames pair to titles
 * one-to-one; if there are not enough airnames, they are recycled.
 *
 * To create Zootopia playlists from an event stream, see ZootopiaListener.
 *
 * See INSTALLATION.md for details on installing and configuring push
 * notifications.
 */
class ZootopiaPoller extends ZootopiaListener {
    private const POLLING_INTERVAL_DEFAULT = 60; // in seconds
    private const POLLING_INTERVAL_ONAIR = 15;   // in seconds

    protected const UA = "ZootopiaPoller/" . Engine::VERSION;

    protected $service;

    protected function worker(): PromiseInterface {
        return $this->nas->getOnNow()->then(function($onNow) {
            // do not poll if non-zootopia show is on-air
            $count = sizeof($onNow);
            if ($count && array_reduce($onNow, function($carry, $data) {
                return $carry || !$this->testTitle($data->attributes->name);
            }, false)) {
                // sign-off zootopia show, if any
                if ($this->onAir) {
                    $event = [
                        'zootopia' => false
                    ];
                    return $this->processEvent($event);
                }
                return;
            }

            return $this->service->get(
                '' // nothing here, as base is endpoint
            )->then(function(ResponseInterface $response) {
                $event = json_decode($response->getBody(), true);
                if (is_array($event)) {
                    $event["zootopia"] =
                          in_array($event["type"] ?? null,
                              [ "schedule", "zootopia" ])
                          && preg_match("/zootopia/i", $event["name"]);

                    if(!$this->onAir && !$event["zootopia"])
                        return;

                    if ($this->lastEvent == $event)
                        return;
                    $this->lastEvent = $event;

                    return $this->processEvent($event);
                }
                return Promise\reject("invalid response format");
            });
        });
    }

    protected function scheduleWorker($default = 0) {
        $now = new \DateTime();
        $interval = $this->onAir
                ? self::POLLING_INTERVAL_ONAIR
                : $default;
        $delta = $interval
                ? $interval - (int)$now->format('s') % $interval
                : -1;

        $this->loop->addTimer(++$delta, function() {
            $this->worker()->catch(function(\Throwable $t) {
                $this->logger->error($t->getMessage());
            });

            $this->scheduleWorker(self::POLLING_INTERVAL_DEFAULT);
        });
    }

    public function start() {
        $browser = (new Browser($this->loop))->
            withTimeout(static::SERVICE_TIMEOUT)->
            withHeader('User-Agent', self::UA)->
            withHeader('Accept', 'application/json');

        $this->zk = $browser->
            withBase($this->config["base_url"])->
            withHeader('X-APIKEY', $this->config["apikey"]);

        $this->service = $browser->
            withBase($this->config["poll_url"]);

        $this->scheduleWorker();
    }
}
