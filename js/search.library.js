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

// can be overridden by request params 'q' and 'chunksize', respectively
var maxresults = 50, chunksize = 15;

function encobj(o, html) {
    // we're encoding for the URI fragment, which can contain ':' and ','
    // so we pass those through unescaped for better readability
    var e = encodeURIComponent(JSON.stringify(o)).
        replace(/%3A/g, ':').replace(/%2C/g, ',');
    // for inclusion in HTML, we need to escape ampersand and single quote
    return html?e.replace(/%26/g, '&amp;').replace(/\'/g, '%27'):e;
}

function decobj(s) {
    return JSON.parse(decodeURIComponent(s));
}

function htmlify(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;');
}

function header(title, sortable) {
    var th = $("<TH>", {
        align: 'left',
    });

    if(sortable) {
        var sortBy = $("#sortBy").val();
        var action = title;
        if(sortBy == action) {
            title += "&nbsp;&#x25be;"; // down
            action += "-";
        } else if(sortBy == action + "-")
            title += "&nbsp;&#x25b4;"; // up
        var a = $("<A>", {
            class: 'nav',
            href: '#',
        }).append(title).click(function() {
            $("#sortBy").val(action);
            search(maxresults, 0);
            return false;
        });
        th.html(a);
    } else
        th.html(title);

    return th;
}

function emitMore(table, data) {
    var more = data.more;
    if(more > 0) {
        var offset = data.offset;
        var tr = $("<TR>");
        var td = $("<TD>", {
            colSpan: 4
        }).append("&nbsp;&nbsp;");

        if(data.data.length < more) {
            var numchunks = 10;
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
                            search(chunksize, low);
                            return false;
                        }
                    })(low));
                    td.append(a).append('&nbsp;&nbsp;');
                }
            }
            if((start + i - 1) * chunksize < more)
                td.append("<B>...</B>");
        }
        tr.append(td);
        table.append(tr);
    }
}

function getArtist(node) {
    var name = node.artist;
    if(name.substr(0, 8) == '[coll]: ')
        name = 'Various Artists';
    return htmlify(name);
}

function emitAlbumsEx(table, data) {
    var session = $("#session").val();
    var tr = $("<TR>");
    tr.append(header("Artist", true));
    tr.append(header("", false));
    tr.append(header("Album", true));
    tr.append(header("Collection", false));
    tr.append(header("Media", false).attr('colSpan', 2));
    tr.append(header("Added", false));
    tr.append(header("Label", true));
    table.append($("<THEAD>").append(tr));

    data.data.forEach(function(entry) {
        tr = $("<TR>");
        var td = $("<TD>");
        if(entry.artist.match(/^\[coll\]/i)) {
            // It's a collection; HREF the album key
            td.html($("<A>", {
                href: "?s=byAlbumKey&n=" +
                    encodeURIComponent(entry.tag) +
                    "&q=" + maxresults +
                    "&action=search&session=" + session
            }).html(getArtist(entry)));
        } else {
            td.append(
                $("<A href='#" + encobj({
                    type: 'artists',
                    key: entry.artist,
                    sortBy: 'Artist',
                    form: true
                }, true) + "'>").append(getArtist(entry)));
        }
        tr.append(td);
        var reviewClass = entry.reviewed?"albumReview":"albumNoReview";
        tr.append($("<TD>", {
            style: 'padding: 0 0 0 6px'
        }).append($("<DIV>", {
            class: reviewClass
        })));
        tr.append($("<TD>").html($("<A>", {
            href: "?s=byAlbumKey&n=" +
                encodeURIComponent(entry.tag) +
                "&q=" + maxresults +
                "&action=search&session=" + session
        }).html(htmlify(entry.album))));
        var collection = entry.location;
        collection = (collection == "Library")?entry.category:
            "<I>" + collection + "&nbsp;" + entry.bin + "</I>";
        tr.append($("<TD>").html(collection));
        tr.append($("<TD>").html(entry.medium));
        tr.append($("<TD>").html(entry.size));
        var created = entry.created.split('-');
        tr.append($("<TD>", {
            align: 'center'
        }).html(created[1] + '/' + created[0].substring(2)));
        if(entry.pubkey) {
            tr.append($("<TD>").append(
                $("<A href='#" + encobj({
                    type: 'albumsByPubkey',
                    key: entry.pubkey,
                    sortBy: '',
                    n: ''
                }, true) + "'>").append(htmlify(entry.name))));
        } else {
            tr.append($("<TD>").html("Unknown"));
        }
        table.append(tr);
    });

    emitMore(table, data);
}

var lists = {
    artists: function(table, data) {
        emitAlbumsEx(table, data);
    },

    albums: function(table, data) {
        emitAlbumsEx(table, data);
    },

    albumsByPubkey: function(table, data) {
        emitAlbumsEx(table, data);
    },

    tracks: function(table, data) {
        var session = $("#session").val();
        var tr = $("<TR>");
        tr.append(header("Artist", true));
        tr.append(header("Album", true));
        tr.append(header("Track", true));
        tr.append(header("Collection", false));
        tr.append(header("Media", false).attr('colSpan', 2));
        tr.append(header("Label", true));
        table.append($("<THEAD>").append(tr));

        data.data.forEach(function(entry) {
            tr = $("<TR>");
            var td = $("<TD>");
            if(entry.artist.match(/^\[coll\]/i)) {
                // It's a collection; HREF the album key
                td.html($("<A>", {
                    href: "?s=byAlbumKey&n=" +
                        encodeURIComponent(entry.tag) +
                        "&q=" + maxresults +
                        "&action=search&session=" + session
                }).html(getArtist(entry)));
            } else {
                td.append(
                    $("<A href='#" + encobj({
                        type: 'artists',
                        key: entry.artist,
                        sortBy: 'Artist',
                        form: true
                    }, true) + "'>").append(getArtist(entry)));
            }
            tr.append(td);
            tr.append($("<TD>").html($("<A>", {
                href: "?s=byAlbumKey&n=" +
                    encodeURIComponent(entry.tag) +
                    "&q=" + maxresults +
                    "&action=search&session=" + session
            }).html(htmlify(entry.album))));
            tr.append($("<TD>").append(
                $("<A href='#" + encobj({
                    type: 'tracks',
                    key: entry.track,
                    sortBy: 'Track',
                    form: true
                }, true) + "'>").append(htmlify(entry.track))));
            var collection = entry.location;
            collection = (collection == "Library")?entry.category:
                "<I>" + collection + "&nbsp;" + entry.bin + "</I>";
            tr.append($("<TD>").html(collection));
            tr.append($("<TD>").html(entry.medium));
            tr.append($("<TD>").html(entry.size));
            if(entry.pubkey) {
                tr.append($("<TD>").append(
                    $("<A href='#" + encobj({
                        type: 'albumsByPubkey',
                        key: entry.pubkey,
                        sortBy: '',
                        n: ''
                    }, true) + "'>").append(htmlify(entry.name))));
            } else {
                tr.append($("<TD>").html("Unknown"));
            }
            table.append(tr);
        });

        emitMore(table, data);
    },

    labels: function(table, data) {
        var session = $("#session").val();
        var tr = $("<TR>");
        tr.append(header("Name", false));
        tr.append(header("Location", false).attr('colSpan', 2));
        tr.append(header("Last Updated", false));
        table.append($("<THEAD>").append(tr));

        data.data.forEach(function(entry) {
            tr = $("<TR>");
            tr.append($("<TD>").append(
                $("<A href='#" + encobj({
                    type: 'albumsByPubkey',
                    key: entry.pubkey,
                    sortBy: '',
                    n: ''
                }, true) + "'>").append(htmlify(entry.name))));
            tr.append($("<TD>").html(htmlify(entry.city)));
            tr.append($("<TD>").html(htmlify(entry.state)));
            tr.append($("<TD>").html(entry.modified));
            table.append(tr);
        });

        emitMore(table, data);
    },

    reviews: function(table, data) {
        var session = $("#session").val();
        var tr = $("<TR>");
        tr.append(header("Artist", true));
        tr.append(header("Album", true));
        tr.append(header("Label", true));
        tr.append(header("Date", true));
        table.append($("<THEAD>").append(tr));

        data.data.forEach(function(entry) {
            var td = $("<TD>");
            tr = $("<TR>");
            if(entry.artist.match(/^\[coll\]/i)) {
                // It's a collection; HREF the album key
                td.html($("<A>", {
                    href: "?s=byAlbumKey&n=" +
                        encodeURIComponent(entry.tag) +
                        "&q=" + maxresults +
                        "&action=search&session=" + session
                }).html(getArtist(entry)));
            } else {
                td.html($("<A>", {
                    href: "?s=byArtist&n=" +
                        encodeURIComponent(entry.artist) +
                        "&q=" + maxresults +
                        "&action=search&session=" + session
                }).html(getArtist(entry)));
            }
            tr.append(td);
            tr.append($("<TD>").html($("<A>", {
                href: "?s=byAlbumKey&n=" + encodeURIComponent(entry.tag) +
                    "&q=" + maxresults +
                    "&action=search&session=" + session
            }).html(htmlify(entry.album))).append(session.length>0?
                                         " <FONT CLASS='sub'>(Tag&nbsp;#" +
                                         entry.tag + ")<FONT>":""));
            if(entry.pubkey) {
                tr.append($("<TD>").html($("<A>", {
                    href: "?s=byLabelKey&n=" +
                        encodeURIComponent(entry.pubkey) +
                        "&q=" + maxresults +
                        "&action=search&session=" + session
                }).html(htmlify(entry.name))));
            } else {
                tr.append($("<TD>").html("Unknown"));
            }
            tr.append($("<TD>").html(entry.reviewed));
            table.append(tr);
        });

        emitMore(table, data);
    },
};

function search(size, offset) {
    var url = "zkapi.php?method=libLookupRq" +
        "&type=" + $("#type").val() +
        "&sortBy=" + $("#sortBy").val() +
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
            var results = $(".searchTable");
            results.empty();
            if(response.total > 0) {
                // if this is not the entire result set, paginate
                var data = response.data[0];
                if(data.data.length < data.more &&
                   data.data.length > chunksize)
                    data.data = data.data.slice(0, chunksize);
                // default is sort by artist
                if($("#sortBy").val() == "")
                    $("#sortBy").val("Artist");
                lists[response.dataType](results, data);
            } else {
                results.append("<H2>No " + response.dataType.replace(/[A-Z].*/, '') +
                               " found</H2>");
                if($("#m").is(":checked"))
                    results.append('Hint: Uncheck "Exact match" box to broaden search.');
            }
            $("#n").focus();
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('There was a problem retrieving the JSON data:\n' + textStatus);
        }
    });
}

$().ready(function() {
    $(window).on('pageshow', function(e) {
        if(e.originalEvent.persisted) {
            // restore search fields from bfcache
            // schedule for later to avoid webkit's autocomplete=off blanking
            setTimeout(function() {
                var key, type = $("#type").val();
                switch(type) {
                case "artists":
                case "albums":
                case "tracks":
                case "labels":
                    key = $("#key").val();
                    if(key.slice(-1) == "*")
                        key = key.substr(0, key.length-1);
                    $("#n").val(key);
                    $("INPUT[NAME=s][VALUE=" + type +"]").prop('checked', true);
                    break;
                default:
                    break;
                }
            }, 100);
        }
    });

    $(window).hashchange(function() {
        var hash = location.hash;
        if(hash.length > 0) {
            var params = decobj(hash.substring(1));
            for(property in params)
                $("#" + property).val(params[property]);

            if(params.form) {
                var key = params.key;
                if(key.slice(-1) == "*")
                    key = key.substr(0, key.length-1);
                $("#n").val(key);
                $("INPUT[NAME=s][VALUE=" + params.type +"]").prop('checked', true);
            }

            search(maxresults, 0);
        }
    });

    $("FORM#search").submit(function(e) {
        var n = $("#n").val();
        if(n.length > 0) {
            if($("#m").is(":not(:checked)"))
                n += "*";
            var sel = $('INPUT[NAME=s]:checked');
            location.href='#' + encobj({
                type: sel.val(),
                key: n,
                sortBy: sel.data('sort'),
                form: true
            });
        }
        e.preventDefault();
    });

    if($("#maxresults").length > 0)
        maxresults = $("#maxresults").val();
    if($("#chunksize").length > 0)
        chunksize = $("#chunksize").val();

    if($("#key").val().length > 0) {
        search(maxresults, 0);
    } else if($("#n").val().length > 0)
        $("FORM#search").submit();

    $("#n").focus();
});
