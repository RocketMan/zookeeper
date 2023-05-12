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

$().ready(function(){
    $.tablesorter.addParser({
        id: 'fullName',
        is: function(s) {
            return false;
        },
        format: function(s) {
            // name is formatted "FirstName [MI] LastName";
            // we want to flip it to "LastName FirstName"
            // for the purposes of the sort comparison.
            var name = s.split(' ');
            return name[name.length-1] + ' ' + name[0];
        },
        type: 'text'
    });

    var sortTable = $('.sortable-table');
    sortTable.tablesorter({
        headers: {
            0: { sorter: 'text' },
            [$("#nameCol").val()]: { sorter: 'fullName' }
        }
    }).css('display','table');

    sortTable.find('TH.initial-sort-col').trigger('sort');

    $("*[data-focus]").trigger('focus');
});
