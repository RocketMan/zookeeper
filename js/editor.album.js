//
// Zookeeper Online
//
// @author Jim Mason <jmason@ibinx.com>
// @copyright Copyright (C) 1997-2022 Jim Mason <jmason@ibinx.com>
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

/*! Zookeeper Online (C) 1997-2022 Jim Mason <jmason@ibinx.com> | @source: https://zookeeper.ibinx.com/ | @license: magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3.0 */

function changeSel(index) {
    $("#seltag").val(items[index].id);
}

function getAlbums(key) {
    var url = "api/v1/album?" + key +
        "&page[profile]=cursor&page[size]=" + $("#list-size").val();
    paginateAlbums(null, url);
}

function paginateAlbums(op, url) {
    url += "&fields[album]=-tracks,-coll";
    url += "&fields[label]=name,address,city,state,zip&include=label";

    $.ajax({
        dataType : 'json',
        type: 'GET',
        accept: "application/json; charset=utf-8",
        url: url,
        success: function (response) {
            var list = $("#list");
            list.empty();
            links = response.links;
            items = response.data;
            items.forEach(function(obj) {
                var option = $('<li>');
                option.text(obj.attributes.artist)
                    .on('mousedown', function() {
                        setSelectedIndex(list, $(this).index());
                        changeList(list);
                    });
                list.append(option);
                obj.attributes.tag = obj.id;
                if(obj.relationships != null) {
                    var label = response.included.find(x => x.id == obj.relationships.label.data.id);
                    obj.attributes.name = label.attributes.name;
                    obj.attributes.address = label.attributes.address;
                    obj.attributes.city = label.attributes.city;
                    obj.attributes.state = label.attributes.state;
                    obj.attributes.zip = label.attributes.zip;
                } else {
                    obj.attributes.name = '(Unknown)';
                    obj.attributes.address = '';
                    obj.attributes.city = '';
                    obj.attributes.state = '';
                    obj.attributes.zip = '';
                }
            });

            var delta = $("#list-size").val() - items.length;
            for(var i=0; i<delta; i++)
                list.append($("<li>").html("&nbsp;"));

            switch(op) {
            case 'prevLine':
            case 'prevPage':
                setSelectedIndex(list, 0);
                break;
            case 'nextLine':
            case 'nextPage':
                setSelectedIndex(list, items.length - 1);
                break;
            default:
                setSelectedIndex(list, items.length / 2);
                break;
            }

            changeList(list);
        },
        error: function (jqXHR, textStatus, errorThrown) {
            var json = JSON.parse(jqXHR.responseText);
            var status = (json && json.errors)?
                    json.errors[0].title:('There was a problem retrieving the data: ' + textStatus);
            alert(status);
        }
    });
}

function onSearchNow() {
    var search = $("#search").val();
    // if artist is numeric with correct check digit,
    // treat it as an album tag
    var parseTag = search.match(/^(\d+)(\d)$/);
    search.length > 3 && parseTag != null && parseTag[1]
            .split('').map(Number)
            .reduce((a, b) => a + b, 0) % 10 == parseTag[2] ?
        getAlbums('filter[id]=' + search) :
        getAlbums('filter[artist]=' + ($("#coll").is(':checked')?"[coll]: ":"") + encodeURIComponent(search));
}

function scrollUp() {
    paginateAlbums('prevPage', links.prev);
    return false;
}

function scrollDown() {
    paginateAlbums('nextPage', links.next);
    return false;
}

function lineUp() {
    paginateAlbums('prevLine', links.prevLine);
}

function lineDown() {
    paginateAlbums('nextLine', links.nextLine);
}

$().ready(function() {
    var seltag = $("#seltag").val();
    if(seltag > 0) {
        getAlbums('filter[id]=' + encodeURIComponent(seltag));
        $("#list").focus();
    } else {
        getAlbums('page[before]=');
        $("#search").focus();
    }
});
