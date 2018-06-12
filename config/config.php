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

    'station_medium' => '9MMM 89.7FM',

    'station_full' => '9MMM Fictitious Radio 89.7 FM',

    'station_freq' => '89.7FM',

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

    'contact' => [
        'addr' => 'Morgan Park 1',
        'city' => 'Westfield, ZQ',
        'phone' => '+1 949 555 0899',
        'fax' => '+1 949 555 0898',
        'request' => '+1 949 555 0897',
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

    /**
     * label printer
     */
    'label_printer' => [
        /**
         * lpr print queue name
         */
        'print_queue' => 'label',
        /**
         * charset is one of UTF-8, LATIN-1, or ASCII
         */
        'charset' => 'ASCII',
        /**
         * number of times to strike (1..n)
         */
        'darkness' => 3,
        /**
         * escape seq to switch to character mode, if any (empty for UTF-8)
         */
        'text_mode' => "",
        /**
         * escape seq to switch to box drawing mode, if any (empty for UTF-8)
         */
        'box_mode' => "\x1bt1\x1b6", // ESC+t+1 graphics mode, ESC+6 for GCS 2
    ],

    /**
     * chart configuration
     */
    'chart' => [
        /**
          * max_spins:
          *
          * number of spins per show (or DJ) to count for the charts
          */
        'max_spins' => 1,
        /**
          * apply_limit_per_dj:
          *
          * 0 to limit spin count per show; 1 to limit spin count per DJ
          */
        'apply_limit_per_dj' => 0,
        'weekly_footer' => "\n\n--\n
If you ever want to remove yourself from this mailing list,
visit https://zookeeper.ibinx.com/mailman/listinfo/weekly-charts.\n
Here's the general information for the list you've subscribed to,
in case you don't already have it:\n
The weekly-charts mailing list is for the distribution of 9MMM's
music charts. The charts are emailed out approximately once a week. 9MMM's
charts are compiled by tallying each play of every recording in current
rotation (400-450 CDs/LPs/7\"s). Any questions about 9MMM's charts
(philosophic or content-wise) can be directed to music@9mmm.fm.\n",

        'monthly_footer' => "\n\n--\n
If you ever want to remove yourself from this mailing list,
visit https://zookeeper.ibinx.com/mailman/listinfo/monthly-charts.\n
Here's the general information for the list you've subscribed to,
in case you don't already have it:\n
The monthly-charts mailing list is for the distribution of 9MMM's
music charts. The charts are emailed out approximately once a month. 9MMM's
charts are compiled by tallying each play of every recording in current
rotation (400-450 CDs/LPs/7\"s). Any questions about 9MMM's charts
(philosophic or content-wise) can be directed to music@9mmm.fm.\n",
    ],
];
