<?php
$config = [
    /**
     * name of the application
     */
    'application' => 'Zookeeper Online',

    /**
     * station name
     */
    'station' => '9MMM',

    'station_full' => '9MMM Fictitious Radio 89.7 FM',

    'copyright' => '&copy; 2002-2018 Fictitious Radio, LTD.  All rights reserved.',

    'logo' => 'img/9mmm_banner.png',

    'stylesheet' => 'css/nostyle.css',

    /**
     * e-mail settings
     */
    'email' => [
        'md' => '',
        'pd' => '',
        'chartman' => '',
        'nobody' => '',
        'reviewlist' => '',
    ],

    'md_name' => 'Music Maestro',  // TBD move me to db

    /**
     * Local subnet
     *
     * This value enables guest account authentication and tag printing
     * only on the specified subnet.
     *
     * Set this value to 0 to enable guest accounts and tag printing for
     * all addresses.
     */
    'local_subnet' => '2.2.2.',  // IP in subnet 2.2.2.x

    /**
     * URLs
     */
    'urls' => [
        'home' => 'https://zookeeper.ibinx.com/master',
        'listen' => 'https://zookeeper.ibinx.com/master',
    ],

    /**
     * domains allowed in the Origin header
     */
    'allowed_domains' => [
        "ibinx.com", "9mmm.fm", "9mmm.org",
    ],

    /**
     * Google OAuth SSO
     */
    'sso' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect_uri' => '',
        'domain' => '',
        'logout_uri' => 'https://www.google.com/accounts/Logout?continue=https://appengine.google.com/_ah/logout%3Fcontinue={base_url}',
        'oauth_auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
        'oauth_token_uri' => 'https://accounts.google.com/o/oauth2/token',
        'oauth_tokeninfo_uri' => 'https://www.googleapis.com/oauth2/v1/tokeninfo',
        'oauth_openidconnect_uri' => 'https://www.googleapis.com/plus/v1/people/{user_id}/openIdConnect',
    ],

    /**
     * database settings
     */
    'db' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => '',
        'user' => '',
        'pass' => '',
    ],
];
