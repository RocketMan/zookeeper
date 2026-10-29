<?php
/**
 * User Interface configuration
 *
 * Each entry consists of five elements as follows:  (names are for
 * convenience; array is numeric/keyless)
 *
 *    * access - specifies the required access level to use this
 *               function:  "a" is all, "u" is authenticated users,
 *               "U" is locally authenticated users (not SSO);
 *               if any other character is used, it must be present
 *               in the authenticated user's access mask
 *
 *    * action - name of the action for internal dispatch;
 *               must be globally unique.  This is the URL-encoded or
 *               form-posted "action" string generated for the menu item.
 *               The character % may be appended to indicate a stemming
 *               match of the field against the current "action" string
 *               (e.g, if an entry contains "someThing%" for the action,
 *               it will be selected if the action string is any of these:
 *               "someThing", "someThingElse", "someThingMore", etc.)
 *
 *    * label -  text displayed in the navigation menu for this command;
 *               this should be 0 for commands which have no corresponding
 *               menu item
 *
 *    * implementation - MenuItem class to which action is dispatched
 *
 *    * subactions - array of subactions, each of which contains:
 *          - access - required access level for subaction;
 *          - subaction - name of the subaction for internal dispatch;
 *          - label - text displayed in menu or 0 if no menu item;
 *          - method - method to which subaction is dispatched
 *
 * The first item in the list is the default if no action is specified.
 */

$menu = [
//  access, action,        menu label,      implementation class,  subactions
    [ 'a', '',             'Playlists',     ZK\UI\Playlists::class, [
        [ 'a', '',              'On Now',          'emitHome' ],
        [ 'a', 'recent',        0,                 'recentSpins' ],
        [ 'a', 'times',         0,                 'getTimes' ],
        [ 'u', 'editList',      'My Playlists',    'emitListManager' ],
        [ 'a', 'viewList',      'By Date',         'emitPlaylistPicker' ],
        [ 'a', 'viewListById',  0,                 'emitViewPlaylist' ],
        [ 'a', 'viewListDaysByDate', 0,            'handlePlaylistDaysByDate'],
        [ 'a', 'viewListsByDate',    0,            'handlePlaylistsByDate' ],
        [ 'u', 'editListGetHint',    0,            'listManagerGetHint' ],
        [ 'u', 'editListEditor',     0,            'emitEditor' ],
        [ 'a', 'viewDJ',        'By DJ',           'emitViewDJ' ],
        [ 'u', 'import',        'Import',          'emitImportList' ],
    ]],

    [ 'a', 'viewRecent%',  'Reviews',       ZK\UI\Reviews::class, [
        [ 'a', '',              'Recent Reviews',  'viewRecentReviews' ],
        [ 'a', 'viewDJ',        'By DJ',           'reviewsByDJ' ],
        [ 'a', 'viewHashtag',   'Trending',        'viewTrending' ],
        [ 'u', 'viewReviewShelf',  'Review Shelf', 'viewReviewShelf' ],
        [ 'u', 'updateReviewShelf', 0,             'updateReviewShelf' ],
    ]],

    [ 'a', 'search',        0,              ZK\UI\Search::class, [] ],
    [ 'a', 'searchReview%', 0,              ZK\UI\Reviews::class, [] ],

    [ 'a', 'addmgr',       'Currents',      ZK\UI\AddManager::class, [
        [ 'a', '',              'Current File',    'addManagerMain' ],
        [ 'a', 'adds',          'Adds',            'addManagerShowAdd' ],
        [ 'n', 'newadd',        'New Add',         'addManagerAdd' ],
        [ 'n', 'categories',    'Categories',      'addManagerCats' ],
        [ 'n', 'addsedit',      0,                 'addManagerEdit' ],
        [ 'n', 'addsdel',       0,                 'addManagerDel' ],
        [ 'n', 'addsemail',     0,                 'addManagerEMail' ],
        [ 'u', 'activity',      'Activity',        'aFileActivityShowWeekly' ],
    ]],

    [ 'a', 'viewChart',    'Charts',        ZK\UI\Charts::class, [
        [ 'a', '',              'Top 30',          'chartTop30' ],
        [ 'a', 'weekly',        'Weekly',          'chartWeekly' ],
        [ 'a', 'amalgamated',   'Amalgamated',     'chartMonthly' ],
        [ 'a', 'subscribe',     'Subscribe',       'emitSubscribe' ],
        [ 'n', 'chartemail',    'E-Mail',          'chartEMail' ],
    ]],

    [ 'm', 'editor',       'Library',       ZK\UI\Editor::class, [
        [ 'm', '',              'Albums',          'musicEditor' ],
        [ 'm', 'labels',        'Labels',          'musicEditor' ],
        [ 'e', 'deepStorage',   'Deep Storage',    'deepStorage' ],
        [ 'm', 'tagq',          'Queued Tags',     'tagQueue' ],
    ]],

    [ 'u', 'adminUsers',   "\u{2699}",      ZK\UI\UserAdmin::class, [
        [ 'u', '',              'Profile',         'updateAirnames' ],
        [ 'u', 'manageKeys',    'API Keys',        'manageKeys' ],
        [ 'U', 'changePass',    'Change Password', 'changePass' ],
        [ 'x', 'users',         'Users',           'adminUsers' ],
        [ 'x', 'airnames',      'Airnames',        'adminAirnames' ],
    ]],

    [ 'a', 'contact%',     0,               ZK\UI\UserAdmin::class, [] ],
];
