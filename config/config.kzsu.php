<?php
/*
 * This is a configuration file for Zookeeper Online for KZSU Radio.
 *
 * For a more generic configuration file, see config.example.php.
 */
$config = [
    /**
     * name of the application
     */
    'application' => 'Zookeeper Online',

    /**
     * station name
     */
    'station' => 'KZSU',

    'station_title' => 'KZSU Music',

    'station_medium' => 'KZSU 90.1FM',

    'station_full' => 'KZSU Radio 90.1 FM',

    'station_freq' => '90.1FM',

    'copyright' => '&copy; Stanford University. Stanford, California 94305. <A HREF="https://www.stanford.edu/site/terms/" target="_blank">Terms of Use</A>',

    'logo' => 'img/kzsu/kzsu_aharoni.png',
    'favicon' => 'img/kzsu/favicon.ico',

    'stylesheet' => 'css/kzsustyle.css',
    'custom_template_dir' => 'kzsu',
    'template_cache_enabled' => false,

    /**
     * e-mail settings
     */
    'email' => [
        'md' => 'music@kzsu.stanford.edu',
        'pd' => 'pd@kzsu.stanford.edu',
        'chartman' => 'chartman@kzsu.stanford.edu',
        'nobody' => 'nobody@kzsu.stanford.edu',
        'reviewlist' => '',
    ],

    'md_name' => 'Juan Luna-Avin et al.',  // TBD move me to db

    /**
     * Local subnet
     *
     * This value enables guest account authentication and tag printing
     * only on the specified subnet.
     *
     * Set this value to 0 to enable guest accounts and tag printing for
     * all addresses.
     */
    'local_subnet' => '171.66.118.0/25',

    /**
     * URLs
     */
    'urls' => [
        'home' => 'https://kzsu.stanford.edu/',
        'listen' => 'https://kzsu.stanford.edu/live',
        'report_missing' => 'https://spreadsheets.google.com/a/kzsu.stanford.edu/viewform?hl=en&formkey=dGRuMW1GNFVQcXoxbmU3YWZHWlVna0E6MQ&$missingSelect&entry_2=%USERNAME%&entry_1=%ALBUMTAG%',
        //'old_charts' => 'http://kzsu.stanford.edu/charts/',
        'contact' => '?action=contact'
    ],

    'contact' => [
        'addr' => 'PO Box 20510',
        'city' => 'Stanford, CA  94309',
        'phone' => '+1 650 723 4839',
        'fax' => '+1 650 725 5865',
        'request' => '+1 855 723 9010',
    ],

    /**
     * domains allowed in the Origin header
     */
    'allowed_domains' => [
        "ibinx.com", "stanford.edu", "kzsu.org", "kzsu.fm",
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
     * custom menu items
     *
     * menu items here are appended to the defaults
     */
    'custom_menu' => [
    ],

    /**
     * custom controllers
     *
     * controllers here override/replace the defaults
     */
    'custom_controllers' => [
    ],

    /**
     * playable track URLs which should be suppressed for
     * non-authenticated users
     */
    'internal_links' => '/^http(|s):\/\/(drive\.google\.com|.*\.stanford\.edu)\//',

    /**
     * define the non-music entries (NME) that can be
     * entered into the playlist via the playlist editor.
     * Note, that as 'special' tracks they are not shown when
     * playlist is viewed.
     */
    'nme' => [
        ['name' => 'LID', 'args'  => 0],
        ['name' => 'Promo','args' => 1],
        ['name' => 'PSA',  'args'  => 1]
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
    'push_proxy' => [
        [
             'proxy' => ZK\PushNotification\PushHttpProxy::class,
             'ws_endpoint' => 'ws://127.0.0.1:32080/push/onair',
             'http_endpoints' => [
                 "filter" => function($msg) {
                     // don't proxy Zootopia events
                     $zootopia = preg_match('/zootopia/i', $msg);
                     if($zootopia) {
                         if($this->zootopia ?? false)
                             return;

                         // proxy event:none on first Zootopia event
                         $msg = self::newMessage();
                     }

                     $this->zootopia = $zootopia;
                     $this->dispatch($msg);
                 },
             ]
        ],
        [
             'proxy' => ZK\PushNotification\ZootopiaListener::class,
             'ws_endpoint' => 'ws://kzsu.stanford.edu/socket.io/?EIO=4&transport=websocket',
             'http_endpoints' => [
                 'apikey' => '',
                 'base_url' => 'https://zookeeper.stanford.edu/',
                 'airname' => 'Team KZSU',
                 'title' => 'Zootopia',
                 'tz' => null,
                 'caption' => "This playlist was automatically generated from notable music of the past 20 years, curated by the KZSU Music Department and selected for airplay by KZSU DJs.\n\nVisit Zootopia at http://kzsu.rocks/\n",
             ],
        ],
    ],

    /**
     * ISO Date of oldest playlist in the sytem. Used to control
     * how far back the year picker goes in the view plists page.
     */
    'playlist_start_date' => '2000-07-21',

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
                'cols' => 1,
                'message' => '<B>IMPORTANT:</B> If you are trying to print to the kzsu label printer, please choose <B>[&nbsp;&lt;&nbsp;Back&nbsp;]</B> below and then press the <B>[&nbsp;Print&nbsp;]</B> button instead of Print to PDF.'
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
         */
        'print_queue' => 'label',
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
        'box_mode' => "\x1bt1\x1b6", // ESC+t+1 graphics mode, ESC+6 for GCS 2
        /**
         * output to lpr with the specified pdf label template
         * (empty for text/box drawing mode)
         */
        'use_template' => "DK-1201",
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
        'apply_limit_per_dj' => 1,
        'weekly_subscribe' => "https://mailman.stanford.edu/mailman/listinfo/weekly-charts",
        'monthly_subscribe' => "https://mailman.stanford.edu/mailman/listinfo/monthly-charts",
        'weekly_footer' => "\n\n--\n
If you ever want to remove yourself from this mailing list,
visit https://mailman.stanford.edu/mailman/listinfo/weekly-charts.\n
Here's the general information for the list you've subscribed to,
in case you don't already have it:\n
The weekly-charts mailing list is for the distribution of KZSU's
music charts. The charts are emailed out approximately once a week. KZSU's
charts are compiled by tallying each play of every recording in current
rotation (400-450 CDs/LPs/7\"s). Any questions about KZSU's charts
(philosophic or content-wise) can be directed to music@kzsu.stanford.edu.\n",

        'monthly_footer' => "\n\n--\n
If you ever want to remove yourself from this mailing list,
visit https://mailman.stanford.edu/mailman/listinfo/monthly-charts.\n
Here's the general information for the list you've subscribed to,
in case you don't already have it:\n
The monthly-charts mailing list is for the distribution of KZSU's
music charts. The charts are emailed out approximately once a month. KZSU's
charts are compiled by tallying each play of every recording in current
rotation (400-450 CDs/LPs/7\"s). Any questions about KZSU's charts
(philosophic or content-wise) can be directed to music@kzsu.stanford.edu.\n",
    ],
];
