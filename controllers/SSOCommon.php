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

namespace ZK\Controllers;

use ZK\Engine\IConfig;
use ZK\Engine\IUser;
use ZK\Engine\Session;
use ZK\Engine\Zookeeper;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class SSOCommon {
    public function __construct(
        protected Session $session,
        protected IConfig $config,
        protected IUser $userDBO,
    ) {}

    // validate the assertion
    public function ssoCheckAssertion($params, &$error) {
        $configParams = $this->config->get('sso');
        $OAuth_token_uri = $configParams['oauth_token_uri'];
        $OAuth_tokeninfo_uri = $configParams['oauth_tokeninfo_uri'];
        $OAuth_userinfo_uri = $configParams['oauth_userinfo_uri'];

        $SSO_client_id = $configParams['client_id'];
        $SSO_client_secret = $configParams['client_secret'];
        $SSO_redirect_uri = $configParams['redirect_uri'];
    
        $code = $params["code"];
        if($code) {
            try {
                // positive authorization received; get the access token
                $client = new Client([
                    RequestOptions::HEADERS => [
                        'User-Agent' => Zookeeper::UA
                    ]
                ]);

                $response = $client->post($OAuth_token_uri, [
                    RequestOptions::FORM_PARAMS => [
                        "client_id" => $SSO_client_id,
                        "code" => $code,
                        "client_secret" => $SSO_client_secret,
                        "redirect_uri" => $SSO_redirect_uri,
                        "grant_type" => "authorization_code"
                    ]
                ]);

                $token = json_decode($response->getBody()->getContents(), true);
    
                $idToken = $token["id_token"];
                if($idToken) {
                    // open the id_token
                    $response = $client->get($OAuth_tokeninfo_uri . "?id_token=" . urlencode($idToken));

                    $tokeninfo = json_decode($response->getBody()->getContents(), true);

                    $userId = $tokeninfo["user_id"];
                    if($userId) {
                        // get the profile
                        $response = $client->get($OAuth_userinfo_uri, [
                            RequestOptions::HEADERS => [
                                "Authorization" => "Bearer " . $token["access_token"]
                            ]
                        ]);

                        return json_decode($response->getBody()->getContents(), true);
                    }
                }

                $error = "ssoInvalidAssertion";
                return false;
            } catch(\Exception $e) {
                error_log("ssoCheckAssertion: " . $e->getMessage());
                $error = "ssoError";
                return false;
            }
        } else {
            $error = "ssoInvalidAssertion";
            return false;
        }
    }
    
    public function setupSSOByAccount($account) {
        $retval = false;
        $row = $this->userDBO->getUserByAccount($account);
        if($row) {
            $user = $row["name"];
            $access = $row["groups"] . "s";
            $session = md5(uniqid(rand()));

            if($this->session->checkLocal())
                $access .= 'l';
    
            // Restrict guest accounts to local subnet only
            if($this->session->checkAccess('d', $access) ||
                   $this->session->checkAccess('g', $access) &&
                       !$this->session->checkAccess('l', $access)) {
                $session = "";
            } else {
                // Create a session
                $this->userDBO->updateLastLogin($row["id"]);
                $this->session->create($session, $row["name"], $access);
            }
            $retval = true;
        }
        return $retval;
    }

    public function setupSSOByName($account, $name) {
        $row = $this->userDBO->getUserByFullname($name);
        if($row)
            $this->userDBO->assignAccount($row["name"], $account);
        else
            $this->userDBO->createNewAccount($name, $account);

        return $this->setupSSOByAccount($account);
    }
}
