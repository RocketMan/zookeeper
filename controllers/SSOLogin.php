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

use ZK\UI\UICommon as UI;

class SSOLogin implements IController {
    private $action;
    private $ssoOptions;

    public function processRequest($dispatcher) {
        $params = SSOCommon::zkQSParams();
        $state = $params["state"];
        if($state) {
            // process assertion
        
            // get and invalidate the state token
            // returns false if state invalid, string (possibly empty) on success
            $location = Engine::api(IUser::class)->getSsoRedirect($state);
        
            if($location !== false)
                $this->doSSOLogin($params);
            else
                $this->action = "ssoError";
        
            // setup redirection to the application
            $rq = [
                "action" => $this->action,
                "ssoOptions" => $this->ssoOptions,
                "session" => Engine::session()->getSessionID()
            ];
        
            if($location) {
                // external redirection requested
                $target = $location;
            } else
                $target = UI::getBaseUrl();
        
        } else {
            // generate the SSO state token
            $token = Engine::api(IUser::class)->setupSsoRedirect($params["location"]);
        
            // redirect to the Google auth page
            $configParams = Engine::param('sso');
            $rq = [
                "client_id" => $configParams['client_id'],
                "response_type" => "code",
                "scope" => "openid email profile",
                "redirect_uri" => $configParams['redirect_uri'],
                "state" => $token,
                "hd" => $configParams['domain'],
            ];
        
            $target = $configParams['oauth_auth_uri'];
        }
        
        // do the redirection
        SSOCommon::zkHttpRedirect($target, $rq);
    }
    
    public function doSSOLogin($params) {
        $profile = SSOCommon::ssoCheckAssertion($params, $error);
        if($profile) {
            $email = $profile["email"];
            $i = strrpos($email, "@");
            if($i) {
                $account = substr($email, 0, $i);
                $domain = substr($email, $i+1);
                if($domain != Engine::param('sso')['domain']) {
                    // invalid domain
                    $this->action = "ssoInvalidDomain";
                    return;
                }
            } else {
                // invalid e-mail
                $this->action = "ssoInvalidDomain";
                return;
            }
    
            $fullname = $profile["name"];
    
            // try setting up the session by account or name
            if(!SSOCommon::setupSSOByAccount($account) &&
                    !SSOCommon::setupSSOByName($account, $fullname)) {
                // no joy; query user what he wants to do
                $this->ssoOptions = Engine::api(IUser::class)->setupSsoOptions($account, $fullname, $location);
                $this->action = "ssoOptions";
            } else
                // success!  show the login succeeded page
                $this->action = "loginValidate";
        } else {
            // invalid assertion or problem accessing service
            $this->action = $error;
        }
    }
}
