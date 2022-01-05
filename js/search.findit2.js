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
        var tr = $("<TR>").append(indent());
        var td = $("<TD>", {
            colSpan: 3
        });

        if(!response.all) {
            var chunksize = 15, numchunks = 10;
            var page = (offset / chunksize) | 0;
            var start = ((page / numchunks) | 0) * numchunks;
            if(start == 0) start = 1;

            td.append('&nbsp;&nbsp;');
            for(var i=0; i<=numchunks+1; i++) {
                var cur = (start + i);
                var low = (cur - 1) * chunksize; // scope for closure
                var hi = cur * chunksize;
                if(low >= more)
                    break;
                if(offset >= low && offset < hi)
                    td.append('<B>' + cur + '</B>&nbsp;&nbsp;');
                else {
                    var a = $("<A>", {
                        class: 'nav',
                        href: '#'
                    }).append('<B>' + cur + '</B>').click((function(low) {
                        return function() {
                            search(type, links.href, chunksize, low);
                            return false;
                        }
                    })(low));
                    td.append(a).append('&nbsp;&nbsp;');
                }
            }
            if((start + i - 1) * chunksize < more)
                td.append("<B>...</B>");
        } else {
            var offset = -1, size = -1; // scope for closure
            if(more > 25) {
                offset = 0;
                size = 15;
            }
            var a = $("<A>", {
                class: 'nav',
                href: '#'
            }).append('<B>' + more + ' more...</B>').click((function(size, offset) {
                return function() {
                    search(type, links.href, size, offset);
                    return false;
                }
            })(size, offset));
            td.append(a);
        }
        tr.append(td);
        table.append(tr);
    }
}

function getArtist(node) {
    var name = node.artist;
    if(name.substr(0, 8) == '[coll]: ')
        name = 'Various Artists';
    return name;
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
                    response.included.find(x => x.id == entry.id) : entry;
        tr = $("<TR>").append(indent());
        var tagId = "";
        if(tag)
            tagId = "Tag #" + album.id + "&nbsp;&#8226;&nbsp;";
        td = $("<TD>").html(tagId + '<A HREF="?s=byArtist&n=' +
                            encodeURIComponent(album.attributes.artist) +
                            '&q=10&action=search" CLASS="nav">' +
                            getArtist(album.attributes) + '</A>' +
                            "&nbsp;&#8226;&nbsp;");
        var albumx = $("<I>").html('<A HREF="?action=findAlbum&n=' + album.id +
                                  '" CLASS="nav">' + album.attributes.album + '</A>');
        td.append(albumx).append('&nbsp; (' + (album.relationships && album.relationships.label ? album.relationships.label.meta.name : "Unknown") + ')');
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
                        response.included.find(x => x.id == entry.id) : entry;
            album.attributes.tracks.forEach(function(track) {
                tr = $("<TR>").append(indent());
                var td = $("<TD>").html('<A HREF="?s=byArtist&n=' +
                                    encodeURIComponent(track.artist) +
                                    '&q=10&action=search" CLASS="nav">' + getArtist(track) + '</A>' +
                                    "&nbsp;&#8226;&nbsp;");
                var albumx = $("<I>").html('<A HREF="?action=findAlbum&n=' + entry.id +
                                      '" CLASS="nav">' + album.attributes.album + '</A>');
                td.append(albumx);
                td.append('&nbsp;&#8226;&nbsp;"' + track.track + '"');
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
                        response.included.find(x => x.id == entry.id) : entry;
            tr = $("<TR>").append(indent());
            var td = $("<TD>").html('<A HREF="?s=byLabelKey&n=' +
                                    entry.id + '&q=10&action=search" CLASS="nav">' +
                                    label.attributes.name + '</A>');
            if(label.attributes.city)
                td.append("&nbsp;&#8226; " + label.attributes.city + "&nbsp;" +
                          label.attributes.state);
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
                        response.included.find(x => x.id == entry.id) : entry;
            tr = $("<TR>").append(indent());
            var now = new Date();
            var sd = list.attributes.date;
            var day = sd.substr(8, 2) * 1;
            var month = sd.substr(5, 2) * 1;
            var year = " " + sd.substr(0, 4);
            var td = $("<TD>", {
                align: 'left'
            }).html('<A HREF="?action=viewDJ&seq=selList&playlist=' +
                    list.id + '" CLASS="nav">' +
                    list.attributes.name + '</A>' +
                    '&nbsp;&#8226;&nbsp;' +
                    day + " " + months[month-1] + year + '&nbsp;&nbsp;(' +
                    list.attributes.airname + ')');
            tr.append(td);
            table.append(tr);

            list.attributes.events.forEach(function(event) {
                if(event.artist) {
                    td.append('<BR>&nbsp;&nbsp;&nbsp;&nbsp;<SPAN CLASS="sub">' +
                          getArtist(event) +
                          '&nbsp;&#8226;&nbsp;<I>' +
                          event.album + '</I>' +
                          '&nbsp;&#8226;&nbsp;"' +
                          event.track + '"</SPAN>');
                } else if (comment = event.comment) {
                    td.append('<BR>&nbsp;&nbsp;&nbsp;&nbsp;<SPAN CLASS="sub"><I>' +
                          comment + '...</I></SPAN>');
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
                        response.included.find(x => x.id == entry.id) : entry;
            var album = review.relationships.album;
            tr = $("<TR>").append(indent());
            var td = $("<TD>").html('<A HREF="?s=byArtist&n=' +
                                    encodeURIComponent(album.meta.artist) +
                                    '&q=10&action=search" CLASS="nav">' +
                                    getArtist(album.meta) + '</A>' +
                                    '&nbsp;&#8226;&nbsp;' +
                                    '<I><A HREF="?action=findAlbum&n=' +
                                    album.data.id + '" CLASS="nav">' +
                                    album.meta.album + '</A></I>' +
                                    '&nbsp;&nbsp;(' +
                                    review.attributes.airname + ')');
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
                        response.included.find(x => x.id == entry.id) : entry;
            album.attributes.tracks.forEach(function(track) {
                tr = $("<TR>").append(indent());
                var td = $("<TD>");
                td.html('<A HREF="?s=byArtist&n=' +
                        encodeURIComponent(album.attributes.artist) +
                        '&q=10&action=search" CLASS="nav">' +
                        getArtist(album.attributes) + '</A>' +
                        "&nbsp;&#8226;&nbsp;" +
                        "<I>" +
                        '<A HREF="?action=findAlbum&n=' +
                        entry.id + '" CLASS="nav">' +
                        album.attributes.album + '</A></I>' +
                        '&nbsp;&#8226;&nbsp;"' + track.track + '"');
                tr.append(td);
                table.append(tr);
            });
        });

        emitMore(table, response, odata, "tracks");
    }
};

function searchAll() {
    var url = "api/v1/search?filter[*]=" +
                encodeURIComponent($("#key").val());
    search(null, url, 5, -1);
}

function search(type, url, size, offset) {
    if(size >= 0)
        url += "&page[size]=" + size;

    if(offset >= 0)
        url += "&page[offset]=" + offset;

    if(type == null)
        url += "&include=album,label,review,show";

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
            $("#total").html("(" + total + " total)");
            var results = $("#results");
            results.empty();
            response.all = true;
            response.data.forEach(function(list) {
                var type = list.type;
                lists[type](newTable(results, type), response, list);
            });

            if(total == '0') {
                var search = $("#key").val();
                if(search.length < 4 ||
                   search.match(/[\u2000-\u206F\u2E00-\u2E7F\\'!"#$%&()*+,\-.\/:;<=>?@\[\]^_`{|}~]/g) != null) {
                    results.html('TIP: For short names or names with punctuation, try the <A HREF="?action=search&s=byArtist&n=' + encodeURIComponent(search) + '">Classic Search</A>.');
                }
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('There was a problem retrieving the JSON data:\n' + textStatus);
        }
    });
}

$().ready(function() {
    function onSearchNow() {
        // for the benefit of the pagination links, copy the search
        // string to a hidden field so it is preserved on Back
        $("#key").val($("#search").val());
        searchAll();
    }

    $(window).on('pageshow', function(e) {
        if(e.originalEvent.persisted) {
            // restore search string on Back
            // schedule for later to avoid webkit's autocomplete=off blanking
            setTimeout(function() {
                $("#search").val($("#key").val());
            }, 100);
        }
    });

    var field = $("#search");
    field.keyup(function() {
        var sync = $("FORM").get(0); // access underlying DOM element
        if(sync.Timer) {
            clearTimeout(sync.Timer);
            sync.Timer = null;
        }
        sync.Timer = setTimeout(onSearchNow, 500);
    }).keypress(function(e) {
        return e.keyCode != 13;
    });

    field.focus();
    var val = field.val();
    if(val.length > 0)
        onSearchNow();
    field.val(val); // reset value to force cursor to end
});
