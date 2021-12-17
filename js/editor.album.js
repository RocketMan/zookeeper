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
    $("#seltag").val(items[index].tag);
}

function getAlbums(op, key) {
    var url = "zkapi.php?method=getAlbumsRq" +
        "&operation=" + op +
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
                option.val(obj.tag).text(obj.artist);
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
            $("#up").val(obj.artist + "|" + obj.album + "|" + obj.tag);
            obj = items[items.length-1];
            $("#down").val(obj.artist + "|" + obj.album + "|" + obj.tag);
        },
        error: function (jqXHR, textStatus, errorThrown) {
            alert('There was a problem retrieving the JSON data:\n' + textStatus);
        }
    });
}

function onSearchNow() {
    var search = $("#search").val();
    if(search.lastIndexOf('.') == search.length-1 && search.length > 3)
        getAlbums('searchByTag', search);
    else
        getAlbums('searchByName', ($("#coll").is(':checked')?"[coll]: ":"") + search);
}

function scrollUp() {
    getAlbums("prevPage", $("#up").val());
    return false;
}

function scrollDown() {
    getAlbums("nextPage", $("#down").val());
    return false;
}

function lineUp() {
    getAlbums('prevLine', $("#up").val());
}

function lineDown() {
    getAlbums('nextLine', $("#up").val());
}

$().ready(function() {
    var seltag = $("#seltag").val();
    if(seltag > 0) {
        getAlbums('searchByTag', seltag);
        $("#list").focus();
    } else {
        scrollUp();
        $("#search").focus();
    }

    $("#search").keyup(function(e) { onSearch(document.forms[0], e); }).
        keypress(function(e) { return e.keyCode != 13; });
    $("#coll").click(function(e) { onSearch(document.forms[0], e); });
    $("#bup").click(scrollUp);
    $("#bdown").click(scrollDown);
    $("#list").keydown(upDown).change(changeList);
});
