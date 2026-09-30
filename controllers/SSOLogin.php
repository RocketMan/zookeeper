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

use ZK\Engine\Engine;
use ZK\Engine\IConfig;
use ZK\Engine\IUser;
use ZK\Engine\Request;
use ZK\Engine\Session;

class SSOLogin implements IController {
    private $action;
    private $ssoOptions;

    public function __construct(
        protected IConfig $config,
        protected Request $request,
        protected Session $session,
        protected IUser $userDBO,
        protected SSOCommon $ssoCommon,
    ) {}

    public function processRequest() {
        $params = $this->request->getQSParams();
        $state = $params["state"] ?? false;
        if($state) {
            // process assertion
        
            // get and invalidate the state token
            // returns false if state invalid, string (possibly empty) on success
            $location = $this->userDBO->getSsoRedirect($state);
        
            if($location !== false)
                $this->doSSOLogin($params);
            else
                $this->action = "ssoError";
        
            // setup redirection to the application
            if($location) {
                // deep link redirection requested
                $rq = [];
                $target = $location;
            } else {
                $rq = [
                    "action" => $this->action,
                    "ssoOptions" => $this->ssoOptions
                ];
                $target = $this->request->getBaseUrl();
            }
        } else {
            // check that cookies are enabled
            if($params["checkCookie"] ?? false) {
                if(isset($_COOKIE["testcookie"])) {
                    // the cookie test was successful!

                    // clear the test cookie
                    setcookie("testcookie", "", time() - 3600);

                    // generate the SSO state token
                    $token = $this->userDBO->setupSsoRedirect($params["location"] ?? '');
        
                    // redirect to the Google auth page
                    $configParams = $this->config->get('sso');
                    $rq = [
                        "client_id" => $configParams['client_id'],
                        "response_type" => "code",
                        "scope" => "openid email profile",
                        "redirect_uri" => $configParams['redirect_uri'],
                        "state" => $token,
                        "hd" => $configParams['domain'],
                    ];

                    // force account selection on shared local machine
                    if ($this->session->checkLocal())
                        $rq["prompt"] = "select_account";
        
                    $target = $configParams['oauth_auth_uri'];
                } else {
                    // cookies are not enabled; alert user
                    $rq = [ "action" => "cookiesDisabled" ];
                    $target = $this->request->getBaseUrl();
                }
            } else if(empty($this->config->get('sso.client_id'))) {
                // not SSO; redirect to legacy login
                $rq = [
                    "action" => "login",
                    "location" => $params["location"] ?? '',
                ];
                $target = $this->request->getBaseUrl();
            } else {
                // send a test cookie
                setcookie("testcookie", "testcookie");
                $rq = [
                    "target" => "sso",
                    "checkCookie" => 1,
                    "location" => $params["location"] ?? '',
                ];
                $target = $this->request->getBaseUrl();
            }
        }
        
        // do the redirection
        $this->request->doHttpRedirect($target, $rq);
    }
    
    public function doSSOLogin($params) {
        $error = '';
        $profile = $this->ssoCommon->ssoCheckAssertion($params, $error);
        if($profile) {
            $email = $profile["email"];
            $i = strrpos($email, "@");
            if($i) {
                $account = substr($email, 0, $i);
                $domain = substr($email, $i+1);
                if($domain != $this->config->get('sso.domain')) {
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
            if(!$this->ssoCommon->setupSSOByAccount($account) &&
                    !$this->ssoCommon->setupSSOByName($account, $fullname)) {
                // no joy; query user what he wants to do
                $location = $this->userDBO->getSsoRedirect($params['state']);
                $this->ssoOptions = $this->userDBO->setupSsoOptions($account, $fullname, $location);
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
