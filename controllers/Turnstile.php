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

namespace ZK\Controllers;

use ZK\Engine\Engine;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class Turnstile implements IController {
    const TTL_SECONDS = 8 * 60 * 60;  // 8 hour token validation

    public static function validate() {
        // Nothing to do if turnstile is disabled or authenticated user
        $config = Engine::param('turnstile');
        if (!$config || !isset($config['secret']) ||
                Engine::session()->isAuth('u'))
            return true;

        $cookie = $_COOKIE['turnstile'] ?? '';
        if (!$cookie) {
            // allow whitelisted traffic through
            $domain = gethostbyaddr(explode(',', $_SERVER['REMOTE_ADDR'])[0]);
            $allowed = array_filter($config['whitelist'] ?? [],
                            fn($suffix) => str_ends_with($domain, $suffix));
            return !empty($allowed);
        }

        $token = json_decode(base64_decode($cookie));

        // check that the token has not expired
        $expires = $token->expires ?? 0;
        if (time() > $expires) {
            setcookie('turnstile', '', time() - 3600);
            return false;
        }

        // validate the signature
        $payload = $token->uuid . '|' . $expires . '|' . ($_SERVER['REMOTE_ADDDR'] ?? '');
        $signature = hash_hmac('sha256', $payload, $config['secret']);
        return hash_equals($signature, $token->signature ?? '');
    }

    public function processRequest() {
        $config = Engine::param('turnstile');
        if (!$config || !isset($config['sitekey']))
            return;

        $qs = SSOCommon::zkQSParams();
        $token = $qs['token'] ?? false;

        if($token) {
            // process turnstile token
            $uuid = sha1(uniqid(rand()));
            try {
                $client = new Client([
                    RequestOptions::HEADERS => [
                        'User-Agent' => Engine::UA
                    ]
                ]);
                $response = $client->post($config['siteverify_uri'], [
                    RequestOptions::FORM_PARAMS => [
                        'secret' => $config['secret'],
                        'response' => $token,
                        'remoteip' => $_SERVER['REMOTE_ADDDR'] ?? '',
                        'idempotency_key' => $uuid,
                    ]
                ]);

                $json = json_decode($response->getBody()->getContents());
                if($json->success) {
                    // create cookie and redirect
                    $expires = time() + self::TTL_SECONDS;
                    $payload = $uuid . '|' . $expires . '|' . ($_SERVER['REMOTE_ADDDR'] ?? '');
                    $signature = hash_hmac('sha256', $payload, $config['secret']);
                    $cookie = base64_encode(json_encode([
                        'uuid' => $uuid,
                        'expires' => $expires,
                        'signature' => $signature,
                    ]));

                    setcookie('turnstile', $cookie, [
                        'expires' => 0,
                        'path' => '/',
                        'domain' => $_SERVER['SERVER_NAME'],
                        'secure' => $_SERVER['REQUEST_SCHEME'] == 'https',
                        'httponly' => true,
                        'samesite' => 'lax'
                    ]);

                    $location = ($qs['location'] ?? '') ?: Engine::getBaseUrl();
                    SSOCommon::zkHttpRedirect($location, []);
                    exit;
                } else {
                    error_log("Turnstile validation failed: " . implode(', ', $json->{'error-codes'} ?? []));
                    $message = "Validation failed";
                }
            } catch (\Exception $e) {
                error_log("Turnstile connection error: " . $e->getMessage());
                $message = "There was a problem accessing the service.";
            }

            $templateName = 'turnstile/error.html';
            $params = [
                'message' => $message,
            ];
        } else {
            // check that cookies are enabled
            if($qs["checkCookie"] ?? false) {
                if(isset($_COOKIE["testcookie"])) {
                    // the cookie test was successful!

                    // clear the test cookie
                    setcookie('testcookie', '', time() - 3600);

                    // do the Turnstile challenge
                    $templateName = 'turnstile/landing.html';
                    $params = [
                        'config' => $config,
                    ];
                } else {
                    // cookies are not enabled; alert user
                    $templateName = 'turnstile/cookies.html';
                    $params = [];
                }
            } else {
                // send a test cookie
                setcookie('testcookie', 'testcookie');
                $rq = [
                    'target' => 'turnstile',
                    'checkCookie' => 1,
                    'location' => $qs['location'] ?? '',
                ];
                $target = Engine::getBaseUrl();
                SSOCommon::zkHttpRedirect($target, $rq);
                exit;
            }
        }

        $templateFactory = new TemplateFactoryXML('html');
        $template = $templateFactory->load($templateName);
        header("Content-type: text/html; charset=UTF-8");
        echo $template->render($params);
    }
}
