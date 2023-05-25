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
function htmlify(s) {
    return s != null?s.replace(/&/g, '&amp;').replace(/</g, '&lt;'):'';
}

function indent() {
    return $("<TD>", {
        class: 'ind'
    });
}

function emitMore(table, response, data, type) {
    var links = response.all ? data.links.first : response.links.first;
    var more = response.all || links.meta.offset == 0 && links.meta.more == 0 ?
                    links.meta.more : links.meta.total;
    if(more > 0) {
        var offset = links.meta.offset;
        var ul = $("<ul>", {
            class: 'pagination'
        }).css('margin', 'auto');

        if(!response.all) {
            var chunksize = 15, numchunks = 10;
            var page = (offset / chunksize) | 0;
            var start = ((page / numchunks) | 0) * numchunks;
            if(start == 0) start = 1;

            for(var i=0; i<=numchunks+1; i++) {
                var cur = (start + i);
                var low = (cur - 1) * chunksize; // scope for closure
                var hi = cur * chunksize;
                if(low >= more)
                    break;
                if(offset >= low && offset < hi)
                    ul.append($("<li>").text(cur));
                else {
                    var a = $("<a>", {
                        class: 'nav',
                        href: '#'
                    }).text(cur).on('click', (function(low) {
                        return function() {
                            search(type, links.href, chunksize, low);
                            return false;
                        }
                    })(low));
                    ul.append($("<li>").append(a));
                }
            }
            if((start + i - 1) * chunksize < more)
                ul.append($("<li>").text("..."));
        } else {
            var offset = -1, size = -1; // scope for closure
            if(more > 25) {
                offset = 0;
                size = 15;
            }
            var a = $("<a>", {
                class: 'nav',
                href: '#',
            }).text(more + ' more...').on('click', (function(size, offset) {
                return function() {
                    search(type, links.href, size, offset);
                    return false;
                }
            })(size, offset));
            ul.append($("<li>", {
                class: 'more',
            }).css('width', 'auto').append(a));
        }
        table.append($("<tr>").append(indent()).append($("<td>", {
            colSpan: 3
        }).append(ul)));
    }
}

function getArtist(node) {
    var name = node.artist;
    if(name.substr(0, 8) == '[coll]: ')
        name = 'Various Artists';
    return htmlify(name);
}

function emitAlbumsEx(table, response, odata, header, tag) {
    var data = response.all ? odata.relationships.album.data : odata;
    var tr = $("<TR>");
    var th = $("<TH>", {
        class: 'sec',
        colSpan: 2
    }).html(header);
    tr.append(th);
    table.append(tr);

    data.forEach(function(entry) {
        var album = response.all ?
                    response.included.find(x => { return x.id == entry.id && x.type == 'album'; }) : entry;
        tr = $("<TR>").append(indent());
        var tagId = "";
        if(tag)
            tagId = "Tag #" + album.id + "&nbsp;&#8226;&nbsp;";
        td = $("<TD>").html(tagId + '<A HREF="?s=byArtist&n=' +
                            encodeURIComponent(album.attributes.artist) +
                            '&q=10&action=search" CLASS="nav">' +
                            getArtist(album.attributes) + '</A>' +
                            "&nbsp;&#8226;&nbsp;");
        var albumx = $("<I>").html('<A HREF="?action=search&s=byAlbumKey&n=' + album.id +
                                   '" CLASS="nav">' + htmlify(album.attributes.album) + '</A>');
        td.append(albumx).append('&nbsp; (' + htmlify(album.relationships && album.relationships.label ? album.relationships.label.meta.name : "Unknown") + ')');
        tr.append(td);
        table.append(tr);
    });

    emitMore(table, response, odata, "albums");
}

function newTable(parent, id) {
    var table = $("<TABLE>", {
        cellPadding: 2,
        cellSpacing: 2,
        border: 0,
        width: "100%"
    });

    var body = $("<TBODY>", {
        id: id
    });

    table.append(body);
    parent.append(table);

    return body;
}

var savedTable = null;
var savedTableID = null;

function getTable(id) {
    var table = $("#" + id);
    if(savedTableID != id) {
        if(savedTableID) {
            var oldTable = $("#" + savedTableID);
            oldTable.replaceWith(savedTable);
        }
        savedTable = table.clone(true);
        savedTableID = id;
    }
    table.empty();
    return table;
}

function clearSavedTable() {
    savedTableID = null;
}

var months = [ "Jan", "Feb", "Mar", "Apr", "May", "Jun",
               "Jul", "Aug", "Sep", "Oct", "Nov", "Dec" ];

var lists = {
    tags: function(table, response, data) {
        emitAlbumsEx(table, response, data, "Album Tags:", 1);
    },

    albums: function(table, response, data) {
        emitAlbumsEx(table, response, data, "Artists and Albums:", 0);
    },

    compilations: function(table, response, odata) {
        var data = response.all ? odata.relationships.album.data : odata;
        var tr = $("<TR>");
        var th = $("<TH>", {
            class: 'sec',
            colSpan: 2
        }).html("Compilations:");
        tr.append(th);
        table.append(tr);

        data.forEach(function(entry) {
            var album = response.all ?
                        response.included.find(x => { return x.id == entry.id && x.type == 'album'; }) : entry;
            album.attributes.tracks.forEach(function(track) {
                tr = $("<TR>").append(indent());
                var td = $("<TD>").html('<A HREF="?s=byArtist&n=' +
                                    encodeURIComponent(track.artist) +
                                    '&q=10&action=search" CLASS="nav">' + getArtist(track) + '</A>' +
                                    "&nbsp;&#8226;&nbsp;");
                var albumx = $("<I>").html('<A HREF="?action=search&s=byAlbumKey&n=' + entry.id +
                                      '" CLASS="nav">' + htmlify(album.attributes.album) + '</A>');
                td.append(albumx);
                td.append('&nbsp;&#8226;&nbsp;"' + htmlify(track.track) + '"');
                tr.append(td);
                table.append(tr);
            });
        });

        emitMore(table, response, odata, "compilations");
    },

    labels: function(table, response, odata) {
        var data = response.all ? odata.relationships.label.data : odata;
        var tr = $("<TR>");
        var th = $("<TH>", {
            class: 'sec',
            colSpan: 2
        }).html("Labels:");
        tr.append(th);
        table.append(tr);

        data.forEach(function(entry) {
            var label = response.all ?
                        response.included.find(x => { return x.id == entry.id && x.type == 'label'; }) : entry;
            tr = $("<TR>").append(indent());
            var td = $("<TD>").html('<A HREF="?s=byLabelKey&n=' +
                                    entry.id + '&q=10&action=search" CLASS="nav">' +
                                    htmlify(label.attributes.name) + '</A>');
            if(label.attributes.city)
                td.append("&nbsp;&#8226; " + htmlify(label.attributes.city) + "&nbsp;" +
                          htmlify(label.attributes.state));
            tr.append(td);
            table.append(tr);
        });

        emitMore(table, response, odata, "labels");
    },

    playlists: function(table, response, odata) {
        var data = response.all ? odata.relationships.show.data : odata;
        var tr = $("<TR>");
        var th = $("<TH>", {
            class: 'sec',
            colSpan: 2
        }).html("Playlists:");
        tr.append(th);
        table.append(tr);

        data.forEach(function(entry) {
            var list = response.all ?
                        response.included.find(x => { return x.id == entry.id && x.type == 'show'; }) : entry;
            tr = $("<TR>").append(indent());
            var now = new Date();
            var sd = list.attributes.date;
            var day = sd.substr(8, 2) * 1;
            var month = sd.substr(5, 2) * 1;
            var year = " " + sd.substr(0, 4);
            var td = $("<TD>", {
                align: 'left'
            }).html('<A HREF="?subaction=viewDJ&seq=selList&playlist=' +
                    list.id + '" CLASS="nav">' +
                    htmlify(list.attributes.name) + '</A>' +
                    '&nbsp;&#8226;&nbsp;' +
                    day + " " + months[month-1] + year + '&nbsp;&nbsp;(' +
                    htmlify(list.attributes.airname) + ')');
            tr.append(td);
            table.append(tr);

            list.attributes.events.forEach(function(event) {
                if(event.artist) {
                    td.append('<BR>&nbsp;&nbsp;&nbsp;&nbsp;<SPAN CLASS="sub">' +
                          getArtist(event) +
                          '&nbsp;&#8226;&nbsp;<I>' +
                          htmlify(event.album) + '</I>' +
                          '&nbsp;&#8226;&nbsp;"' +
                          htmlify(event.track) + '"</SPAN>');
                } else if (comment = event.comment) {
                    td.append('<BR>&nbsp;&nbsp;&nbsp;&nbsp;<SPAN CLASS="sub"><I>' +
                          htmlify(comment) + '...</I></SPAN>');
                }
            });
        });

        emitMore(table, response, odata, "playlists");
    },

    reviews: function(table, response, odata) {
        var data = response.all ? odata.relationships.review.data : odata;
        var tr = $("<TR>");
        var th = $("<TH>", {
            class: 'sec',
            colSpan: 2
        }).html("Reviews:");
        tr.append(th);
        table.append(tr);

        data.forEach(function(entry) {
            var review = response.all ?
                        response.included.find(x => { return x.id == entry.id && x.type == 'review'; }) : entry;
            var album = review.relationships.album;
            tr = $("<TR>").append(indent());
            var td = $("<TD>").html('<A HREF="?s=byArtist&n=' +
                                    encodeURIComponent(album.meta.artist) +
                                    '&q=10&action=search" CLASS="nav">' +
                                    getArtist(album.meta) + '</A>' +
                                    '&nbsp;&#8226;&nbsp;' +
                                    '<I><A HREF="?action=search&s=byAlbumKey&n=' +
                                    album.data.id + '" CLASS="nav">' +
                                    htmlify(album.meta.album) + '</A></I>' +
                                    '&nbsp;&nbsp;(' +
                                    htmlify(review.attributes.airname) + ')');
            tr.append(td);
            table.append(tr);
        });

        emitMore(table, response, odata, "reviews");
    },

    tracks: function(table, response, odata) {
        var data = response.all ? odata.relationships.album.data : odata;
        var tr = $("<TR>");
        var th = $("<TH>", {
            class: 'sec',
            colSpan: 2
        }).html("Tracks:");
        tr.append(th);
        table.append(tr);

        data.forEach(function(entry) {
            var album = response.all ?
                        response.included.find(x => { return x.id == entry.id && x.type == 'album'; }) : entry;
            album.attributes.tracks.forEach(function(track) {
                tr = $("<TR>").append(indent());
                var td = $("<TD>");
                td.html('<A HREF="?s=byArtist&n=' +
                        encodeURIComponent(album.attributes.artist) +
                        '&q=10&action=search" CLASS="nav">' +
                        getArtist(album.attributes) + '</A>' +
                        "&nbsp;&#8226;&nbsp;" +
                        "<I>" +
                        '<A HREF="?action=search&s=byAlbumKey&n=' +
                        entry.id + '" CLASS="nav">' +
                        htmlify(album.attributes.album) + '</A></I>' +
                        '&nbsp;&#8226;&nbsp;"' + htmlify(track.track) + '"');
                tr.append(td);
                table.append(tr);
            });
        });

        emitMore(table, response, odata, "tracks");
    }
};

function encobj(o, html) {
    // we're encoding for the URI fragment, which can contain ':' and ','
    // so we pass those through unescaped for better readability
    var e = encodeURIComponent(JSON.stringify(o)).
        replace(/%3A/g, ':').replace(/%2C/g, ',');
    // for inclusion in HTML, we need to escape ampersand and single quote
    return html?e.replace(/%26/g, '&amp;').replace(/\'/g, '%27'):e;
}

function searchAll() {
    var type = $("#search-filter").val();
    var sortBy;
    switch(type) {
    case "artists":
        sortBy = "Artist";
        break;
    case "albums":
        sortBy = "Album";
        break;
    case "tracks":
        sortBy = "Track";
        break;
    default:
        sortBy = "";
        break;
    }
    if(field.val().trim().length) {
        document.location = "#" + encobj({
            type: type,
            fkey: field.val() + "*",
            sortBy: sortBy
        }, false);
    }
}

function search(type, url, size, offset) {
    if(size >= 0)
        url += "&page[size]=" + size;

    if(offset >= 0)
        url += "&page[offset]=" + offset;

    if(type == null)
        url += "&include=album,label,review,show";

    url += "&fields[album]=artist,album,tracks";
    url += "&fields[label]=name,city,state";
    url += "&fields[review]=-review";

    $.ajax({
        dataType: 'json',
        type: 'GET',
        accept: 'application/json; charset=utf-8',
        url: url,
        success: function(response) {
            if(type != null) {
                lists[type](getTable(type), response, response.data);
                return;
            }

            clearSavedTable();
            var total = response.links.first.meta.total;
            var rcount = $("#total");
            if(!rcount.length) {
                rcount = $("<div>", {
                    class: 'result-count',
                    id: 'total'
                });
                $("body").append(rcount);
            }
            var search = $("#fkey").val().trim();
            rcount.html((total ? total : "No") + " items found");
            $(".nav-items li").removeClass("selected");
            $(".breadcrumbs").hide();
            var results = $(".content");
            results.empty();
            response.all = true;
            response.data.forEach(function(list) {
                var type = list.type;
                lists[type](newTable(results, type), response, list);
            });
        },
        error: function(jqXHR, textStatus, errorThrown) {
            var json = JSON.parse(jqXHR.responseText);
            var status = (json && json.errors)?
                    json.errors[0].title:('There was a problem retrieving the data: ' + textStatus);
            alert(status);
        }
    });
}

    function onSearchNow() {
        // for the benefit of the pagination links, copy the search
        // string to a hidden field so it is preserved on Back
        $("#fkey").val($(".search-data").val());
        searchAll();
    }

    $(window).on('pageshow', function(e) {
        if(e.originalEvent.persisted) {
            // restore search string on Back
            // schedule for later to avoid webkit's autocomplete=off blanking
            setTimeout(function() {
                switch($("#type").val()) {
                case "albumsByPubkey":
                case "reviews":
                    break;
                default:
                    var key = $("#fkey").val();
                    if(key.slice(-1) == "*")
                        key = key.substr(0, key.length-1);
                    $(".search-data").val(key);
                    break;
                }
            }, 100);
        }
    });

    $("#type").on('fsearch', function() {
        var url = "api/v1/search?filter[*]=" +
                    encodeURIComponent($("#fkey").val());
        search(null, url, 5, -1);
    });

    var field = $(".search-data");
    field.on('keyup typechange', function(e) {
        // nothing to do if search field has not changed
        if(e.type == 'keyup' && this.value == $("#fkey").val())
            return;

        var sync = field.closest("form").get(0); // access underlying DOM element
        if(sync.Timer) {
            clearTimeout(sync.Timer);
            sync.Timer = null;
        }
        sync.Timer = setTimeout(onSearchNow, 500);
    }).keypress(function(e) {
        return e.keyCode != 13;
    }).on('cut paste', function() {
        // run on next tick, as pasted data is not yet in the field
        setTimeout(onSearchNow, 0);
    });
});
