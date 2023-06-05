//
// Zookeeper Online
//
// @author Jim Mason <jmason@ibinx.com>
// @copyright Copyright (C) 1997-2023 Jim Mason <jmason@ibinx.com>
// @link https://zookeeper.ibinx.com/
// @license GPL-3.0
//
// This code is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License, version 3,
// as published by the Free Software Foundation.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License,
// version 3, along with this program.  If not, see
// http://www.gnu.org/licenses/
//

/*! Zookeeper Online (C) 1997-2023 Jim Mason <jmason@ibinx.com> | @source: https://zookeeper.ibinx.com/ | @license: magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3.0 */

$().ready(function() {
    $("INPUT:checkbox#all").on('click', function() {
        var all = $(this).is(":checked");
        $("INPUT:checkbox").prop('checked', all);
    });
    $("A.copy").on('click', function() {
        var key = $(this).closest("TR").children("TD.apikey").html();
        copyToClipboard(key);
        alert('Key copied to clipboard!');
    });
    $("INPUT[name=deleteKey]").on('click', function(e) {
        if($("INPUT:checkbox:checked").length == 0 ||
           !confirm('Delete the selected keys?\n\nCAUTION: THIS CANNOT BE UNDONE.')) {
            return false;
        }
    });
});
