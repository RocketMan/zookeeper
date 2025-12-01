<?php
/*
 * This is a sample configuration file for Zookeeper Online that you
 * can copy to config.php and customize for your own use.
 *
 * This sample file configures a radio station 'Example Radio' at 99.9FM.
 * The various settings in this file are specific to the example and may
 * be changed as appropriate.
 */
$config = [
    /**
     * name of the application
     */
    'application' => 'Zookeeper Online',

    /**
     * station name
     */
    'station' => 'Example Radio',

    'station_title' => 'Example Radio',

    'station_medium' => 'Example Radio 99.9FM',

    'station_full' => 'Example Fictitious Radio 99.9 FM',

    'station_freq' => '99.9FM',

    'station_slogan' => 'Music with a difference...',

    'copyright' => '&copy; Fictitious Radio, LTD.  All rights reserved.',

    'logo' => 'img/example_banner.png',
    'favicon' => 'img/favicon.ico',

    /**
     * optional custom stylesheet
     */
//    'stylesheet' => 'css/example_style.css',

    /**
     * subdirectory for custom Twig templates
     *
     * this directory is relativve to ui/templates
     *
     * templates in this directory override/extend templates
     * in the ui/templates/default directory
     */
    'custom_template_dir' => 'example',

    /**
     * enable twig template caching
     *
     * if enabled, your webserver user needs write permission
     * to ui/templates/.cache.
     *
     * IMPORTANT: purge ui/templates/.cache any time changes are made
     * to the templates!
     */
    'template_cache_enabled' => false,

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
     * Value may be specified in CIDR subnet notation (e.g., 2.2.2.0/24)
     * or as an address fragment (e.g., 2.2.2).
     *
     * Set this value to 0 to enable guest accounts and tag printing for
     * all addresses.
     */
    'local_subnet' => '2.2.2.0/24',  // IP in subnet 2.2.2.x

    /**
     * URLs
     */
    'urls' => [
        'home' => 'https://www.example.com/',
        'listen' => 'https://www.example.com/listen',
        'contact' => '?action=contact'
    ],

    'contact' => [
        'addr' => 'Example Park 1',
        'city' => 'Example, ZQ',
        'phone' => '+1 949 555 0997',
        'fax' => '+1 949 555 0998',
        'request' => '+1 949 555 0999',
    ],

    /**
     * domains allowed in the Origin header
     */
    'allowed_domains' => [
        "www.example.fm", "www.example.org",
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
        'oauth_userinfo_uri' => 'https://www.googleapis.com/oauth2/v3/userinfo',
    ],

    /**
     * Proof of Work HMAC secret
     */
    'challenge_secret' => '',

    /**
     * Discogs integration
     *
     * see INSTALLATION.md for details
     */
    'discogs' => [
        'apikey' => '',
        'client_id' => '',
        'client_secret' => '',
    ],

    /**
     * Slack integration
     *
     * To enable Slack, specify your bot token and target channel IDs
     */
    'slack' => [
        'token' => null,
        'review_channel' => null,
        'chart_channel' => null,
    ],

    /**
     * database settings
     */
    'db' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'zookeeper',
        'user' => 'root',
        'pass' => '',
    ],

    /**
     * custom menu items
     *
     * menu items here are appended to the defaults
     */
    'custom_menu' => [
//      [ 'a', 'contact%', 'Submit Music', ZK\UI\Example_Contacts::class ],
    ],

    /**
     * custom controllers
     *
     * controllers here override/replace the defaults
     */
    'custom_controllers' => [
//        'main' => ZK\UI\Example_UI_Controller::class,
    ],

    /**
     * enable external playable track links
     */
    'external_links_enabled' => false,

    /**
     * playable track URLs which should be suppressed for
     * non-authenticated users
     */
    'internal_links' => '/^http(|s):\/\/drive\.google\.com\//',

    /**
     * define the non-music entries (NME) that can be
     * entered into the playlist via the playlist editor.
     * Note, that as 'special' tracks they are not shown when
     * playlist is viewed.
     */
    'nme' => [
        ['name' => 'LID', 'args'  => 0],
        ['name' => 'Promo','args' => 1],
//        ['name' => 'PSA',  'args'  => 1]
     ],

    /**
     * enable push notification
     *
     * see INSTALLATION.md for details
     */
    'push_enabled' => true,

    /**
     * push notification proxy
     *
     * see INSTALLATION.md for details
     */
//    'push_proxy' => [
//        [
//             'proxy' => ZK\PushNotification\PushHttpProxy::class,
//             'ws_endpoint' => 'ws://127.0.0.1:32080/push/onair',
//             'http_endpoints' => [ /* URLs here */ ]
//        ]
//    ],

    /**
     * ISO Date of oldest playlist in the sytem. Used to control
     * how far back the year picker goes in the view plists page.
     */
    'playlist_start_date' => '2020-01-01',

    /**
     * label printer
     */
    'label_printer' => [
        /**
         * supported print methods (0 or more of 'lpr', 'pdf')
         */
        'print_methods' => [ 'pdf' ],
        /**
         * supported PDF label templates
         */
        'labels' => [
            'DK-1201' => [
                'name' => 'Brother Label Printer',
                'code' => 'DK-1201',
                'rows' => 1,
                'cols' => 1
            ],
            '5161' => [
                'name' => 'Avery 5161',
                'code' => '5161',
                'rows' => 10,
                'cols' => 2
            ],
        ],
        /**
         * lpr print queue name
         *
         * this can be a string (the queue name) or an array if you
         * have multiple printers
         */
        'print_queue' => [
            [
                'queue' => 'example-lpr-queue',
                'description' => 'Example Description',
                /*
                 * 'preferred' is the IP address of a computer for which
                 * this printer should be offered as the default.
                 *
                 * It may be a dotted-quad IP address, a CIDR netmask,
                 * or array of IP addresses and/or netmasks.
                 */
                'preferred' => '192.168.0.103'
                /*
                 * You may optionally specify any of the printer
                 * characteristics (use_template, charset, darkness,
                 * text_mode, box_mode); if supplied, they override
                 * the defaults
                 */
            ],
            [
                'queue' => 'example-lpr-queue-2',
                'description' => 'Example Description 2',
            ]
        ],
        /**
         * lpr charset is one of UTF-8, LATIN-1, or ASCII
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
        'box_mode' => "",
        /**
         * output to lpr with the specified pdf label template
         * (empty for text/box drawing mode)
         */
        'use_template' => "",
    ],

    /**
     * chart configuration
     */
    'chart' => [
        /**
          * suspend_until:
          *
          * suspend charting until the specified date (YYYY-mm-dd)
          *
          * specify the charting (end) date of the first chart to run.
          * For example, suspend_until 2020-04-12 will chart commencing
          * with the week 2020-04-05 - 2020-04-12.
          */
        /*'suspend_until' => '2020-04-12',*/
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
visit https://www.example.com/mailman/listinfo/weekly-charts.\n
Here's the general information for the list you've subscribed to,
in case you don't already have it:\n
The weekly-charts mailing list is for the distribution of Example Radio's
music charts. The charts are emailed out approximately once a week. Example
Radio's charts are compiled by tallying each play of every recording in
current rotation (400-450 CDs/LPs/7\"s). Any questions about Example Radio's
charts (philosophic or content-wise) can be directed to music@example.com.\n",

        'monthly_footer' => "\n\n--\n
If you ever want to remove yourself from this mailing list,
visit https://www.example.com/mailman/listinfo/monthly-charts.\n
Here's the general information for the list you've subscribed to,
in case you don't already have it:\n
The monthly-charts mailing list is for the distribution of Example Radio's
music charts. The charts are emailed out approximately once a month. Example
Radio's charts are compiled by tallying each play of every recording in
current rotation (400-450 CDs/LPs/7\"s). Any questions about Example Radio's
charts (philosophic or content-wise) can be directed to music@example.com.\n",
    ],
];
