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

namespace ZK\Controllers;

use ZK\Engine\Engine;
use ZK\Engine\IChart;
use ZK\Engine\IDJ;
use ZK\Engine\IPlaylist;
use ZK\Engine\IUser;
use ZK\Engine\PlaylistEntry;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class Validate implements IController {
    private $success = true;
    private $session;
    private $testUser;
    private $testPass;

    private const TEST_NAME = "TEST User";
    private const TEST_ACCESS = "qr";  // some unused roles for safety
    private const TEST_COMMENT = "TEST comment!";
    private const TEST_TRACK = "TEST Grommet Track"; // second word is search key

    private const FAIL = "\033[0;31m";
    private const OK = "\033[0;32m";
    private const SKIP = "\033[0;33m";
    private const NORMAL = "\033[0m";

    private function doTest($name, $runTest = null) {
        if($runTest === null)
            $runTest = $this->success;

        echo "\t${name}: ";
        if(!$runTest)
            echo self::SKIP."SKIPPED".self::NORMAL."\n";
        return $runTest;
    }

    private function showSuccess($success, $critical = true) {
        if($critical)
            $this->success &= $success;

        if($success)
            echo self::OK."OK";
        else {
            echo self::FAIL."FAILED!";
            if(!$critical)
                echo " (soft failure)";
        }
        echo self::NORMAL."\n";
    }

    public function processRequest() {
        if(php_sapi_name() != "cli") {
            http_response_code(400);
            return;
        }

        if(!isset($_REQUEST["url"])) {
            echo "Usage: zk validate url=__path to zookeeper__\n";
            exit(1);
        }

        $this->session = Engine::session();
        echo "\nStarting Validation...\n\n";
        try {
            $this->validateCreateUser();
            $this->validateSignon();
            $this->validatePlaylists();
            $this->validateCategories();
            $this->validateDeleteUser();
        } catch (\Exception $e) {
            // if there is a db configuration issue (wrong db name
            // or password, etc.), we'll get an exception.
            echo self::FAIL."\nFATAL: ".$e->getMessage().self::NORMAL."\n";
            $this->success = false;
        }
        echo "\nDone.\n";
        exit($this->success?0:1);
    }

    public function validateCreateUser() {
        $api = Engine::api(IUser::class);

        $this->doTest("create user");
        $this->testUser = "__".substr(md5(uniqid(rand())), 0, 6);
        $this->testPass = md5(uniqid(rand()));
        $success = $api->insertUser($this->testUser, $this->testPass,
                self::TEST_NAME, self::TEST_ACCESS, "");
        $this->showSuccess($success);

        if($this->doTest("validate user", $success)) {
            $user = $api->getUser($this->testUser);
            $success2 = $user['realname'] == self::TEST_NAME;
            $this->showSuccess($success2);
        }

        if(!$success) {
            error_reporting(0);
            $api->deleteUser($this->testUser);
            error_reporting(E_ALL & ~E_NOTICE);

            $this->testUser = null;
        }
    }

    public function validateSignon() {
        if($this->doTest("validate signon")) {
            $this->showSuccess(
                Engine::api(IUser::class)->validatePassword(
                    $this->testUser, $this->testPass, 1, $access));
        }

        if($this->doTest("validate session")) {
            // Suppress warnings from session cookie creation
            error_reporting(E_ERROR);
            $sessionID = md5(uniqid(rand()));
            $this->session->create($sessionID, $this->testUser, $access);

            // Validate session
            $this->session->validate($sessionID);
            $success = $this->session->isAuth(substr(self::TEST_ACCESS, 1, 1));
            $this->showSuccess($success);

            // Resume normal error reporting
            error_reporting(E_ALL & ~E_NOTICE);
        }
    }

    public function validatePlaylists() {
        if($this->doTest("create api key")) {
            $apiKey = sha1(uniqid(rand()));
            $success = Engine::api(IUser::class)->addAPIKey($this->testUser, $apiKey);
            if($success)
                $apiKeyId = Engine::api(IUser::class)->lastInsertId();
            $this->showSuccess($success);
        }

        $client = new Client([
            'base_uri' => $_REQUEST["url"],
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
                'X-APIKEY' => $apiKey
            ]
        ]);

        if($this->doTest("create airname")) {
            $djapi = Engine::api(IDJ::class);
            $airname = self::TEST_NAME." ".$this->testUser; // make unique
            $success = $djapi->insertAirname($airname, $this->testUser);
            $this->showSuccess($success);
        } else
            $success = false;

        if($this->doTest("create playlist", $success)) {
            $response = $client->post('api/v1/playlist', [
                RequestOptions::JSON => [
                    'data' => [
                        'type' => 'show',
                        'attributes' => [
                            'name' => 'TEST Show',
                            'date' => '2020-01-01',
                            'time' => '1200-1400',
                            'airname' => $airname
                        ]
                    ]
                ]
            ]);

            $success = $response->getStatusCode() == 201;
            if($success) {
                $list = $response->getHeader('Location')[0];
                $pid = basename($list);
            }

            $this->showSuccess($success);
        }

        if($this->doTest("insert comment", $success)) {
            $response = $client->post($list . '/events', [
                RequestOptions::JSON => [
                    'data' => [
                        'type' => 'event',
                        'attributes' => [
                            'type' => 'comment',
                            'comment' => self::TEST_COMMENT
                        ]
                    ]
                ]
            ]);

            $success2 = $response->getStatusCode() == 200;
            if($success2) {
                $json = json_decode($r = $response->getBody()->getContents());
                if($json !== null && $json->data)
                    $cid = $json->data->id;
                else
                    $success2 = false;
            }
echo "DEBUG: code=".$response->getStatusCode()." s2=".($success2?"1":"0")." body=".$r.".\n";
            $this->showSuccess($success2);
        } else
            $success2 = false;

        if($this->doTest("insert spin", $success)) {
            $response = $client->post($list . '/events', [
                RequestOptions::JSON => [
                    'data' => [
                        'type' => 'event',
                        'attributes' => [
                            'type' => 'spin',
                            'artist' => 'TEST, Artist',
                            'album' => 'TEST Album',
                            'track' => self::TEST_TRACK,
                            'label' => 'TEST Label'
                        ]
                    ]
                ]
            ]);

            $success3 = $response->getStatusCode() == 200;
            if($success3) {
                $json = json_decode($response->getBody()->getContents());
                if($json !== null && $json->data)
                    $sid = $json->data->id;
                else
                    $success3 = false;
            }
            $this->showSuccess($success3);
        } else
            $success3 = false;

        if($this->doTest("move track", $success2 && $success3)) {
            $response = $client->post('', [
                RequestOptions::FORM_PARAMS => [
                    "action" => "moveTrack",
                    "playlist" => $pid,
                    "fromId" => $sid,
                    "toId" => $cid
                ]
            ]);

            $success4 = $response->getStatusCode() == 200;
            $this->showSuccess($success4);
        } else
            $success4 = false;

        if($this->doTest("view playlist", $success4)) {
            $response = $client->get('', [
                RequestOptions::QUERY => [
                    "action" => "viewListById",
                    "subaction" => "",
                    "playlist" => $pid
                ],
                RequestOptions::HEADERS => [
                    "Accept" => "text/html"
                ]
            ]);
            $page = $response->getBody()->getContents();

            // scrape the page looking for the comment and spin we inserted.
            // both should be present, and the comment should follow the spin
            $commentPos = strpos($page, self::TEST_COMMENT);
            $trackPos = strpos($page, self::TEST_TRACK);
            $success5 = $commentPos !== false && $trackPos !== false &&
                    $commentPos > $trackPos;
            $this->showSuccess($success5);
        }

        if($this->doTest("validate search", $success3)) {
            $response = $client->get('api/v1/search', [
                RequestOptions::QUERY => [
                    "page[size]" => 5,
                    "filter[*]" => explode(' ', self::TEST_TRACK)[1],
                    "include" => "show"
                ]
            ]);
            $page = $response->getBody()->getContents();

            // parse the json looking for the spin
            $success6 = false;
            $json = json_decode($page);
            $included = $json->included;
            foreach($included as $data) {
                if($data->type == "show") {
                    foreach($data->attributes->events as $event) {
                        if($event->track == self::TEST_TRACK) {
                            $success6 = true;
                            break 2;
                        }
                    }
                    break;
                }
            }
            $this->showSuccess($success6);
        }

        if($this->doTest("delete playlist", $success)) {
            $response = $client->delete($list);
            $success = $response->getStatusCode() == 204;
            $this->showSuccess($success);
        }

        if($this->doTest("purge playlists", $success)) {
            $success = Engine::api(IPlaylist::class)->purgeDeletedPlaylists(0);
            $this->showSuccess($success);
        }

        if($this->doTest("release api key", $success)) {
            $success = Engine::api(IUser::class)->deleteAPIKeys($this->testUser, [ $apiKeyId ]);
            $this->showSuccess($success);
        }
    }

    public function validateCategories() {
        if($this->doTest("validate categories", isset($this->testUser))) {
            $cats = Engine::api(IChart::class)->getCategories();
            $success = sizeof($cats) == 16;
            $this->showSuccess($success);
        }
    }

    public function validateDeleteUser() {
        if($this->doTest("delete user", isset($this->testUser))) {
            // invalidate session
            $this->session->invalidate();

            $success = Engine::api(IUser::class)->deleteUser($this->testUser);
            $this->showSuccess($success);
        }
    }
}
