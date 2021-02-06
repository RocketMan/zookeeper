<?php
/**
 *
 * Navigation and command processing array
 *
 * Each array entry is itself an array which consists of four elements
 * as follows:  (names are for convenience; array is numeric/keyless)
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
 *    * menu -   text displayed in the navigation menu for this command;
 *               this should be 0 for commands which have no corresponding
 *               menu item
 *
 *    * class -  MenuItem class to which command is dispatched
 *
 * The first item in the list is the default if no action is specified.
 */

//NOTE: server must be restarted for changes to take effect.
$menu = [
//  access, action,        menu label,              implementation class
    [ 'a', 'home',         0,                       ZK\UI\Home::class ],
    [ 'a', 'find%',        'Find It!',              ZK\UI\Search::class ],
    [ 'a', 'search',       'Classic Search',        ZK\UI\Search::class ],
    [ 'm', 'editor',       'Library Editor',        ZK\UI\Editor::class ],
    [ 'a', 'viewRecent%',  'Recent Reviews',        ZK\UI\Reviews::class ],
    [ 'a', 'searchReview%', 0,                      ZK\UI\Reviews::class ],
    [ 'a', 'addmgr',       'A-File',                ZK\UI\AddManager::class ],
    [ 'u', 'newList%',     'New Playlist',          ZK\UI\Playlists::class ],
    [ 'u', 'editList%',    'Edit Playlist',         ZK\UI\Playlists::class ],
    [ 'u', 'importExport', 'Import/Export',         ZK\UI\Playlists::class ],
    [ 'u', 'showLink',     'Link to Playlist',      ZK\UI\Playlists::class ],
    [ 'u', 'updateDJInfo', 'Update Airname',        ZK\UI\Playlists::class ],
    [ 'a', 'viewDJ%',      'DJ Zone!',              ZK\UI\Playlists::class ],
    [ 'a', 'viewDate',     'Playlists by Date',     ZK\UI\Playlists::class ],
    [ 'u', 'addTrack',     0,                       ZK\UI\Playlists::class ],
    [ 'u', 'moveTrack',    0,                       ZK\UI\Playlists::class ],
    [ 'U', 'changePass',   'Change Password',       ZK\UI\ChangePass::class ],
    [ 'p', 'deepStorage',  'Deep Storage',          ZK\UI\DeepStorage::class ],
    [ 'x', 'adminUsers',   'Administer Users',      ZK\UI\UserAdmin::class ],
    [ 'a', 'viewChart',    'Airplay Charts',        ZK\UI\Charts::class ],
];

/**
 * IController implementations
 *
 * The first item in the list is the default if no target is specified.
 */
$controllers = [
    'main' =>         ZK\UI\Main::class,
    'addexp' =>       ZK\Controllers\ExportAdd::class,
    'export' =>       ZK\Controllers\ExportPlaylist::class,
    'afile' =>        ZK\Controllers\ExportAfile::class,
    'opensearch' =>   ZK\Controllers\OpenSearch::class,
    'daily' =>        ZK\Controllers\RunDaily::class,
    'print' =>        ZK\Controllers\PrintTags::class,
    'rss' =>          ZK\Controllers\RSS::class,
    'api' =>          ZK\Controllers\API::class,
    'sso' =>          ZK\Controllers\SSOLogin::class,
    'push' =>         ZK\Controllers\PushServer::class,
];
