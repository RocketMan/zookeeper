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

function indent() {
    return $("<TD>", {
        class: 'ind'
    });
}

function emitMore(table, data, type) {
    var more = data.more;
    if(more > 0) {
        var offset = data.offset;
        var tr = $("<TR>").append(indent());
        var td = $("<TD>", {
            colSpan: 3
        });

        if(offset != '') {
            var chunksize = 15, numchunks = 10;
            var page = (offset / chunksize) | 0;
            var start = ((page / numchunks) | 0) * numchunks;
            if(start == 0) start = 1;

            td.append('&nbsp;&nbsp;');
            for(var i=0; i<=numchunks+1; i++) {
                var cur = (start + i);
                let low = (cur - 1) * chunksize; // scope for closure
                var hi = cur * chunksize;
                if(low >= more)
                    break;
                if(offset >= low && offset < hi)
                    td.append('<B>' + cur + '</B>&nbsp;&nbsp;');
                else {
                    var a = $("<A>", {
                        class: 'nav',
                        href: '#'
                    }).append('<B>' + cur + '</B>').click(function() {
                        search(type, chunksize, low);
                        return false;
                    });
                    td.append(a).append('&nbsp;&nbsp;');
                }
            }
            if((start + i - 1) * chunksize < more)
                td.append("<B>...</B>");
        } else {
            let offset = -1, size = -1; // scope for closure
            if(more > 25) {
                offset = 0;
                size = 15;
            }
            var a = $("<A>", {
                class: 'nav',
                href: '#'
            }).append('<B>' + more + ' more...</B>').click(function() {
                search(type, size, offset);
                return false;
            });
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

function emitAlbumsEx(table, data, header, tag) {
    var session = $("#session").val();
    var tr = $("<TR>");
    var th = $("<TH>", {
        class: 'sec',
        colSpan: 2
    }).html(header);
    tr.append(th);
    table.append(tr);

    data.data.forEach(function(entry) {
        tr = $("<TR>").append(indent());
        var tagId = "";
        if(tag)
            tagId = "Tag #" + entry.tag + "&nbsp;&#8226;&nbsp;";
        td = $("<TD>").html(tagId + '<A HREF="?s=byArtist&n=' +
                            encodeURIComponent(entry.artist) +
                            '&q=10&action=search&session=' +
                            session + '" CLASS="nav">' +
                            getArtist(entry) + '</A>' +
                            "&nbsp;&#8226;&nbsp;");
        var album = $("<I>").html('<A HREF="?session=' + session +
                                  '&action=findAlbum&n=' + entry.tag +
                                  '" CLASS="nav">' + entry.album + '</A>');
        td.append(album).append('&nbsp; (' + entry.name + ')');
        tr.append(td);
        table.append(tr);
    });

    emitMore(table, data, "albums");
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
    tags: function(table, data) {
        emitAlbumsEx(table, data, "Album Tags:", 1);
    },

    albums: function(table, data) {
        emitAlbumsEx(table, data, "Artists and Albums:", 0);
    },

    compilations: function(table, data) {
        var session = $("#session").val();
        var tr = $("<TR>");
        var th = $("<TH>", {
            class: 'sec',
            colSpan: 2
        }).html("Compilations:");
        tr.append(th);
        table.append(tr);

        data.data.forEach(function(entry) {
            tr = $("<TR>").append(indent());
            var td = $("<TD>").html('<A HREF="?s=byArtist&n=' +
                                    encodeURIComponent(entry.artist) +
                                    '&q=10&action=search&session=' + session +
                                    '" CLASS="nav">' + getArtist(entry) + '</A>' +
                                    "&nbsp;&#8226;&nbsp;");
            var album = $("<I>").html('<A HREF="?session=' + session +
                                      '&action=findAlbum&n=' + entry.tag +
                                      '" CLASS="nav">' + entry.album + '</A>');
            td.append(album);
            td.append('&nbsp;&#8226;&nbsp;"' + entry.track + '"');
            tr.append(td);
            table.append(tr);
        });

        emitMore(table, data, "compilations");
    },

    labels: function(table, data) {
        var session = $("#session").val();
        var tr = $("<TR>");
        var th = $("<TH>", {
            class: 'sec',
            colSpan: 2
        }).html("Labels:");
        tr.append(th);
        table.append(tr);

        data.data.forEach(function(entry) {
            tr = $("<TR>").append(indent());
            var td = $("<TD>").html('<A HREF="?s=byLabelKey&n=' +
                                    entry.pubkey + '&q=10&action=search&session=' +
                                    session + '" CLASS="nav">' +
                                    entry.name + '</A>');
            if(entry.city)
                td.append("&nbsp;&#8226; " + entry.city + "&nbsp;" +
                          entry.state);
            tr.append(td);
            table.append(tr);
        });

        emitMore(table, data, "labels");
    },

    playlists: function(table, data) {
        var session = $("#session").val();
        var tr = $("<TR>");
        var th = $("<TH>", {
            class: 'sec',
            colSpan: 2
        }).html("Playlists:");
        tr.append(th);
        table.append(tr);

        var last, td;
        data.data.forEach(function(entry) {
            var list = entry.list;
            if(list != last) {
                last = list;
                tr = $("<TR>").append(indent());
                var now = new Date();
                var sd = entry.showdate;
                var day = sd.substr(8, 2) * 1;
                var month = sd.substr(5, 2) * 1;
                var year = " " + sd.substr(0, 4);
                td = $("<TD>", {
                    align: 'left'
                }).html('<A HREF="?action=viewDJ&seq=selList&playlist=' +
                        list + '&session=' +
                        session + '" CLASS="nav">' +
                        entry.description + '</A>' +
                        '&nbsp;&#8226;&nbsp;' +
                        day + " " + months[month-1] + year + '&nbsp;&nbsp;(' +
                        entry.airname + ')');
                tr.append(td);
                table.append(tr);
            }

            if(entry.artist) {
                td.append('<BR>&nbsp;&nbsp;&nbsp;&nbsp;<SPAN CLASS="sub">' +
                          getArtist(entry) +
                          '&nbsp;&#8226;&nbsp;<I>' +
                          entry.album + '</I>' +
                          '&nbsp;&#8226;&nbsp;"' +
                          entry.track + '"</SPAN>');
            } else if (comment = entry.comment) {
                td.append('<BR>&nbsp;&nbsp;&nbsp;&nbsp;<SPAN CLASS="sub"><I>' +
                          comment + '...</I></SPAN>');
            }
        });

        emitMore(table, data, "playlists");
    },

    reviews: function(table, data) {
        var session = $("#session").val();
        var tr = $("<TR>");
        var th = $("<TH>", {
            class: 'sec',
            colSpan: 2
        }).html("Reviews:");
        tr.append(th);
        table.append(tr);

        data.data.forEach(function(entry) {
            tr = $("<TR>").append(indent());
            var td = $("<TD>").html('<A HREF="?s=byArtist&n=' +
                                    encodeURIComponent(entry.artist) +
                                    '&q=10&action=search&session=' +
                                    session + '" CLASS="nav">' +
                                    getArtist(entry) + '</A>' +
                                    '&nbsp;&#8226;&nbsp;' +
                                    '<I><A HREF="?session=' + session + '&action=findAlbum&n=' +
                                    entry.tag + '" CLASS="nav">' +
                                    entry.album + '</A></I>' +
                                    '&nbsp;&nbsp;(' +
                                    entry.airname + ')');
            tr.append(td);
            table.append(tr);
        });

        emitMore(table, data, "reviews");
    },

    tracks: function(table, data) {
        var session = $("#session").val();
        var tr = $("<TR>");
        var th = $("<TH>", {
            class: 'sec',
            colSpan: 2
        }).html("Tracks:");
        tr.append(th);
        table.append(tr);

        data.data.forEach(function(entry) {
            tr = $("<TR>").append(indent());
            var td = $("<TD>");
            td.html('<A HREF="?s=byArtist&n=' +
                    encodeURIComponent(entry.artist) +
                    '&q=10&action=search&session=' +
                    session + '" CLASS="nav">' +
                    getArtist(entry) + '</A>' +
                    "&nbsp;&#8226;&nbsp;" +
                    "<I>" +
                    '<A HREF="?session=' + session +
                    '&action=findAlbum&n=' +
                    entry.tag + '" CLASS="nav">' +
                    entry.album + '</A></I>' +
                    '&nbsp;&#8226;&nbsp;"' + entry.track + '"');
            tr.append(td);
            table.append(tr);
        });

        emitMore(table, data, "tracks");
    }
};

function search(type, size, offset) {
    var url = "zkapi.php?method=searchRq" +
        "&type=" + type +
        "&key=" + encodeURIComponent($("#key").val());

    if(size >= 0)
        url += "&size=" + size;

    if(offset >= 0)
        url += "&offset=" + offset;

    $.ajax({
        dataType: 'json',
        type: 'GET',
        accept: 'application/json; charset=utf-8',
        url: url,
        success: function(response) {
            var type = response.dataType;
            if(type != '') {
                lists[type](getTable(type), response.data[0]);
                return;
            }

            clearSavedTable();
            $("#total").html("(" + response.total + " total)");
            var results = $("#results");
            results.empty();
            response.data.forEach(function(list) {
                var type = list.type;
                lists[type](newTable(results, type), list);
            });

            if(response.total == '0') {
                var search = $("#key").val();
                if(search.length < 4 ||
                   search.match(/[\u2000-\u206F\u2E00-\u2E7F\\'!"#$%&()*+,\-.\/:;<=>?@\[\]^_`{|}~]/g) != null) {
                    results.html('TIP: For short names or names with punctuation, try the <A HREF="?action=search&s=byArtist&n=' + encodeURIComponent(search) + '&session=' + $("#session").val() + '">Classic Search</A>.');
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
        search('', 5, -1);
    }

    $(window).on('pageshow', function(e) {
        if(e.originalEvent.persisted) {
            // restore search string on Back
            // schedule for later to avoid webkit's autocomplete=off blanking
            setTimeout(function() {
                $("#search").val($("#key").val());
            }, 10);
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
