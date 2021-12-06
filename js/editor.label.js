//
// Zookeeper Online
//
// @author Jim Mason <jmason@ibinx.com>
// @copyright Copyright (C) 1997-2020 Jim Mason <jmason@ibinx.com>
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

/*! Zookeeper Online (C) 1997-2020 Jim Mason <jmason@ibinx.com> | @source: https://zookeeper.ibinx.com/ | @license: magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3.0 */

function changeSel(index) {
    $("#selpubkey").val(items[index].pubkey);
}

function getLabels(op, key) {
    var url = "api/v1/getLabels" +
        "?operation=" + op +
        "&size=" + $("#list-size").val() +
        "&key=" + encodeURIComponent(key);

    $.ajax({
        dataType : 'json',
        type: 'GET',
        accept: "application/json; charset=utf-8",
        url: url,
        success: function (response) {
            var list = $("#list");
            list.empty();
            items = response.data;
            items.forEach(function(obj) {
                var option = $('<option/>');
                option.val(obj.pubkey).text(obj.name);
                list.append(option);
            });

            switch(op) {
            case 'prevLine':
            case 'prevPage':
                list.prop('selectedIndex', 0);
                break;
            case 'nextLine':
            case 'nextPage':
                list.prop('selectedIndex', items.length - 1);
                break;
            default:
                list.prop('selectedIndex', items.length / 2);
                break;
            }

            changeList();

            var obj = items[0];
            $("#up").val(obj.name + "|" + obj.pubkey);
            obj = items[items.length-1];
            $("#down").val(obj.name + "|" + obj.pubkey);
        },
        error: function (jqXHR, textStatus, errorThrown) {
            alert('There was a problem retrieving the JSON data:\n' + textStatus);
        }
    });
}

function onSearchNow() {
    var search = $("#search").val();
    getLabels('searchByName', search);
}

function scrollUp() {
    getLabels("prevPage", $("#up").val());
    return false;
}

function scrollDown() {
    getLabels("nextPage", $("#down").val());
    return false;
}

function lineUp() {
    getLabels('prevLine', $("#up").val());
}

function lineDown() {
    getLabels('nextLine', $("#up").val());
}

$().ready(function() {
    var seltag = $("#seltag").val();
    var name = $("#req-name").val();
    if(seltag > 0) {
        getLabels('searchByTag', seltag);
        $("#list").focus();
    } else if(name.length > 0) {
        getLabels('searchByName', name);
        $("#list").focus();
    } else {
        scrollUp();
        $("#search").focus();
    }

    $("#search").keyup(function(e) { onSearch(document.forms[0], e); }).
        keypress(function(e) { return e.keyCode != 13; });
    $("#bup").click(scrollUp);
    $("#bdown").click(scrollDown);
    $("#list").keydown(upDown).change(changeList);
});
