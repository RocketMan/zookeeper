<?php
/**
 * User Interface configuration
 *
 * Each entry consists of four elements as follows:  (names are for
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
 * The first item in the list is the default if no action is specified.
 */

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
    [ 'u', 'editList%',    'My Playlists',          ZK\UI\Playlists::class ],
    [ 'u', 'importExport', 'Import/Export',         ZK\UI\Playlists::class ],
    [ 'u', 'showLink',     'Link to Playlist',      ZK\UI\Playlists::class ],
    [ 'u', 'updateDJInfo', 'Edit Profile',          ZK\UI\Playlists::class ],
    [ 'a', 'viewDJ%',      'DJ Zone!',              ZK\UI\Playlists::class ],
    [ 'a', 'viewList%',    'Playlists by Date',     ZK\UI\Playlists::class ],
    [ 'u', 'addTrack',     0,                       ZK\UI\Playlists::class ],
    [ 'u', 'moveTrack',    0,                       ZK\UI\Playlists::class ],
    [ 'U', 'changePass',   'Change Password',       ZK\UI\ChangePass::class ],
    [ 'p', 'deepStorage',  'Deep Storage',          ZK\UI\DeepStorage::class ],
    [ 'x', 'adminUsers',   'Administer Users',      ZK\UI\UserAdmin::class ],
    [ 'a', 'viewChart',    'Airplay Charts',        ZK\UI\Charts::class ],
];
