<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2018 Jim Mason <jmason@ibinx.com>
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
use ZK\Engine\IUser;

class SSOCommon {
    const UA = "Zookeeper-SSO/2.0; (+https://zookeeper.ibinx.com/)";

    public static function zkHttpGet($url, $params = 0, $accessToken = 0) {
        if($params)
            $url .= "?" . http_build_query($params);
    
        $headers = array('Connection: close');
        if($accessToken)
            $headers[] = "Authorization: Bearer " . $accessToken;
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, SSOCommon::UA);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
    
    public static function zkHttpPost($url, $params, $accessToken = 0) {
        $postdata = http_build_query($params);
    
        $headers = array('Connection: close');
        if($accessToken)
            $headers[] = "Authorization: Bearer " . $accessToken;
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, SSOCommon::UA);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
    
    public static function zkHttpRedirect($url, $params) {
        $qs = http_build_query($params);
        header("Location: " . $url . "?" . $qs, true, 307);
    }
    
    // alternative to $_GET[] that does not munge dots in qs param names
    public static function zkQSParams() {
        $result = array();
        $params = explode("&", $_SERVER["QUERY_STRING"]);
        foreach ($params as $param) {
            $nameValue = explode("=", $param);
            $name = urldecode($nameValue[0]);
            $value = urldecode($nameValue[1]);
            $result[$name] = $value;
        }
        return $result;
    }
    
    // validate the assertion
    public static function ssoCheckAssertion($params, &$error) {
        $configParams = Engine::param('sso');
        $OAuth_token_uri = $configParams['oauth_token_uri'];
        $OAuth_tokeninfo_uri = $configParams['oauth_tokeninfo_uri'];
        $OAuth_openIdConnect_uri = $configParams['oauth_openidconnect_uri'];

        $SSO_client_id = $configParams['client_id'];
        $SSO_client_secret = $configParams['client_secret'];
        $SSO_redirect_uri = $configParams['redirect_uri'];
    
        $code = $params["code"];
        if($code) {
            // positive authorization received; get the access token
            $params = [
                "client_id" => $SSO_client_id,
                "code" => $code,
                "client_secret" => $SSO_client_secret,
                "redirect_uri" => $SSO_redirect_uri,
                "grant_type" => "authorization_code"
            ];
    
            $token = self::zkHttpPost($OAuth_token_uri, $params);
            $token = json_decode($token, true);
    
            $idToken = $token["id_token"];
            if($idToken) {
                // open the id_token
                $tokeninfo = self::zkHttpGet($OAuth_tokeninfo_uri . "?id_token=" . urlencode($idToken));
                $tokeninfo = json_decode($tokeninfo, true);
    
                $userId = $tokeninfo["user_id"];
                if($userId) {
                    // get the profile
                    $url = str_replace("{user_id}", urlencode($userId), $OAuth_openIdConnect_uri);
                    $profile = self::zkHttpGet($url, 0, $token["access_token"]);
                    return json_decode($profile, true);
                }
            }
    
            $error = "ssoInvalidAssertion";
            return false;
        } else {
            $error = "ssoInvalidAssertion";
            return false;
        }
    }
    
    public static function setupSSOByAccount($account) {
        $retval = false;
        $row = Engine::api(IUser::class)->getUserByAccount($account);
        if($row) {
            $user = $row["name"];
            $access = $row["groups"] . "s";
            $session = md5(uniqid(rand()));
    
            // Restrict guest accounts to local subnet only
            if(Engine::session()->checkAccess('d', $access) ||
                   Engine::session()->checkAccess('g', $access) && !Engine::session()->isLocal()) {
                $session = "";
            } else {
                // Create a session
                Engine::api(IUser::class)->updateLastLogin($row["id"]);
                Engine::session()->create($session, $row["name"], $access);
            }
            $retval = true;
        }
        return $retval;
    }
    
    public static function setupSSOByName($account, $name) {
        $retval = false;
        $row = Engine::api(IUser::class)->getUserByFullname($name);
        if($row) {
            Engine::api(IUser::class)->assignAccount($row["name"], $account);
            $retval = self::setupSSOByAccount($account);
        }
        return $retval;
    }
}
