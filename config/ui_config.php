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
    [ 'a', '',             'Playlists',		    ZK\UI\Playlists::class ],
    [ 'a', 'viewRecent%',  'Reviews',               ZK\UI\Reviews::class ],
    [ 'a', 'search',       0,                       ZK\UI\Search::class ],
    [ 'a', 'searchReview%', 0,                      ZK\UI\Reviews::class ],
    [ 'a', 'addmgr',       'Currents',              ZK\UI\AddManager::class ],
    [ 'a', 'viewChart',    'Charts',                ZK\UI\Charts::class ],
    [ 'm', 'editor',       'Library',               ZK\UI\Editor::class ],
    [ 'u', 'adminUsers',   "\u{2699}",              ZK\UI\UserAdmin::class ],
    [ 'a', 'contact%',     0,                       ZK\UI\UserAdmin::class ],
];
