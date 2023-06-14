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
    return s != null?s.replace(/&/g, '&amp;').replace(/</g, '&lt;'):'';
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
        }).append(title).on('click', function() {
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
    var meta = data.links.first.meta;
    var more = meta.total;
    if(more > 0) {
        var offset = meta.offset;
        var ul = $("<ul>", {
            class: 'pagination'
        });

        // for track search, we paginate by tracks
        var count = data.data.length;
        if(count > 0 && data.data[0].attributes.tracks) {
            data.data.forEach(function(entry) {
                count += entry.attributes.tracks.length - 1;
            });
        }

        if(count < more) {
            var numchunks = 10;
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
                            search(chunksize, low);
                            return false;
                        }
                    })(low));
                    ul.append($("<li>").append(a));
                }
            }
            if((start + i - 1) * chunksize < more)
                ul.append($("<li>").text("..."));
        }

        table.append($("<tr>").append($("<td>", {
            colSpan: 4
        }).append(ul)));
    }
}

function getArtist(node) {
    var name = node.artist;
    if(name.substr(0, 8) == '[coll]: ')
        name = 'Various Artists';
    return htmlify(name);
}

function emitAlbumsEx(table, data) {
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
        var attrs = entry.attributes;
        if(attrs.artist.match(/^\[coll\]/i)) {
            // It's a collection; HREF the album key
            td.html($("<A>", {
                href: "?s=byAlbumKey&n=" +
                    encodeURIComponent(entry.id) +
                    "&action=search"
            }).html(getArtist(attrs)));
        } else {
            td.append(
                $("<A href='#" + encobj({
                    type: 'artists',
                    fkey: attrs.artist,
                    sortBy: 'Artist',
                    form: true
                }, true) + "'>").append(getArtist(attrs)));
        }
        tr.append(td);
        var reviewClass = (entry.relationships && entry.relationships.reviews)?"albumReview":"albumNoReview";
        tr.append($("<TD>", {
            style: 'padding: 0 0 0 6px'
        }).append($("<DIV>", {
            class: reviewClass
        })));
        tr.append($("<TD>").html($("<A>", {
            href: "?s=byAlbumKey&n=" +
                encodeURIComponent(entry.id) +
                "&action=search"
        }).html(htmlify(attrs.album))));
        var collection = attrs.location;
        collection = (collection == "Library")?attrs.category:
            "<I>" + collection + "&nbsp;" + (attrs.bin?attrs.bin:'') + "</I>";
        tr.append($("<TD>").html(collection));
        tr.append($("<TD>").html(attrs.medium));
        tr.append($("<TD>").html(attrs.size));
        var created = attrs.created.split('-');
        tr.append($("<TD>", {
            align: 'center',
            class: 'date'
        }).html(created[1] + '/' + created[0].substring(2)));
        if(entry.relationships && entry.relationships.label) {
            var label = entry.relationships.label;
            tr.append($("<TD>").append(
                $("<A href='#" + encobj({
                    type: 'albumsByPubkey',
                    fkey: label.data.id,
                    sortBy: '',
                    n: ''
                }, true) + "'>").append(htmlify(label.meta.name))));
        } else {
            tr.append($("<TD>").html("(Unknown)"));
        }
        table.append(tr);
    });

    emitMore(table, data);
}

var requestMap = {
    artists: "album?filter[artist]=",
    albums: "album?filter[album]=",
    albumsByPubkey: "album?filter[label.id]=",
    tracks: "album?filter[track]=",
    labels: "label?filter[name]=",
    reviews: "album?filter[reviews.airname.id]="
};

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
        var tr = $("<TR>");
        tr.append(header("Artist", true));
        tr.append(header("Album", true));
        tr.append(header("Track", true));
        tr.append(header("Collection", false));
        tr.append(header("Media", false).attr('colSpan', 2));
        tr.append(header("Label", true));
        table.append($("<THEAD>").append(tr));

        data.data.forEach(function(entry) {
            var attrs = entry.attributes;
            attrs.tracks.forEach(function(track) {
                tr = $("<TR>");
                var td = $("<TD>");
                var artist = track.artist != null?track.artist:attrs.artist;
                td.append(
                    $("<A href='#" + encobj({
                        type: 'artists',
                        fkey: artist,
                        sortBy: 'Artist',
                        form: true
                    }, true) + "'>").append(htmlify(artist)));
                tr.append(td);
                tr.append($("<TD>").html($("<A>", {
                    href: "?s=byAlbumKey&n=" +
                        encodeURIComponent(entry.id) +
                        "&action=search"
                }).html(htmlify(attrs.album))));
                tr.append($("<TD>").append(
                    $("<A href='#" + encobj({
                        type: 'tracks',
                        fkey: track.track,
                        sortBy: 'Track',
                        form: true
                    }, true) + "'>").append(htmlify(track.track))));
                var collection = attrs.location;
                collection = (collection == "Library")?attrs.category:
                    "<I>" + collection + "&nbsp;" + (attrs.bin?attrs.bin:'') + "</I>";
                tr.append($("<TD>").html(collection));
                tr.append($("<TD>").html(attrs.medium));
                tr.append($("<TD>").html(attrs.size));
                if(entry.relationships && entry.relationships.label) {
                    var label = entry.relationships.label;
                    tr.append($("<TD>").append(
                        $("<A href='#" + encobj({
                            type: 'albumsByPubkey',
                            fkey: label.data.id,
                            sortBy: '',
                            n: ''
                        }, true) + "'>").append(htmlify(label.meta.name))));
                } else {
                    tr.append($("<TD>").html("(Unknown)"));
                }
                table.append(tr);
            })
        });

        emitMore(table, data);
    },

    labels: function(table, data) {
        var tr = $("<TR>");
        tr.append(header("Name", false));
        tr.append(header("Location", false).attr('colSpan', 2));
        tr.append(header("Last Updated", false));
        table.append($("<THEAD>").append(tr));

        data.data.forEach(function(entry) {
            tr = $("<TR>");
            var attrs = entry.attributes;
            tr.append($("<TD>").append(
                $("<A href='#" + encobj({
                    type: 'albumsByPubkey',
                    fkey: entry.id,
                    sortBy: '',
                    n: ''
                }, true) + "'>").append(htmlify(attrs.name))));
            tr.append($("<TD>").html(htmlify(attrs.city)));
            tr.append($("<TD>").html(htmlify(attrs.state)));
            tr.append($("<TD>", { class: 'date' }).html(attrs.modified));
            table.append(tr);
        });

        emitMore(table, data);
    },

    reviews: function(table, data) {
        var showTag = $("#showTag").val();
        var tr = $("<TR>");
        tr.append(header("Artist", true));
        tr.append(header("Album", true));
        tr.append(header("Label", true));
        tr.append(header("Date", true));
        table.append($("<THEAD>").append(tr));

        data.data.forEach(function(entry) {
            var td = $("<TD>");
            var attrs = entry.attributes;
            tr = $("<TR>");
            if(attrs.artist.match(/^\[coll\]/i)) {
                // It's a collection; HREF the album key
                td.html($("<A>", {
                    href: "?s=byAlbumKey&n=" +
                        encodeURIComponent(entry.id) +
                        "&action=search"
                }).html(getArtist(attrs)));
            } else {
                td.html($("<A>", {
                    href: "?s=byArtist&n=" +
                        encodeURIComponent(attrs.artist) +
                        "&action=search"
                }).html(getArtist(attrs)));
            }
            tr.append(td);
            tr.append($("<TD>").html($("<A>", {
                href: "?s=byAlbumKey&n=" + encodeURIComponent(entry.id) +
                    "&action=search"
            }).html(htmlify(attrs.album))).append(showTag == 'true'?
                                         " <FONT CLASS='sub'>(Tag&nbsp;#" +
                                         entry.id + ")<FONT>":""));
            if(entry.relationships && entry.relationships.label) {
                var label = entry.relationships.label;
                tr.append($("<TD>").html($("<A>", {
                    href: "?s=byLabelKey&n=" +
                        encodeURIComponent(label.data.id) +
                        "&action=search"
                }).html(htmlify(label.meta.name))));
            } else {
                tr.append($("<TD>").html("(Unknown)"));
            }
            tr.append($("<TD>", { class: 'date' }).html(entry.relationships.reviews.data[0].meta.date));
            table.append(tr);
        });

        emitMore(table, data);
    },
};

function search(size, offset) {
    var suffix, type = $("#type").val();
    if(!type || !requestMap[type])
        return;
    switch(type) {
    case "albumsByPubkey":
    case "reviews":
        suffix = "";
        break;
    default:
        suffix = "*";
        break;
    }

    var url = "api/v1/" + requestMap[type] +
        encodeURIComponent($("#fkey").val() + suffix) +
        "&sort=" + $("#sortBy").val() +
        "&fields[label]=name,city,state,modified";

    if(type != 'tracks')
        url += "&fields[album]=-tracks";

    // For track search, limit the max results for the initial page,
    // as the JSON:API counts track search results by track and not by
    // album.  Thus, we are unable to slice a bigger result to size.
    if(size >= 0)
        url += "&page[size]=" +
                (type == 'tracks' ? Math.min(size, chunksize) : size);

    if(offset >= 0)
        url += "&page[offset]=" + offset;

    $.ajax({
        dataType: 'json',
        type: 'GET',
        accept: 'application/json; charset=utf-8',
        url: url,
        success: function(response) {
            var total = response.links.first.meta.total;
            var results;
            if(type == "reviews") {
                // reviews uses pre-existing table, as there is
                // some other content already on the page (the header)
                results = $(".searchTable");
            } else {
                var rcount = $("#total");
                if(!rcount.length) {
                    rcount = $("<div>", {
                        class: 'result-count',
                        id: 'total'
                    });
                    $("body").append(rcount);
                }
                var ttype;
                switch(type) {
                case "albumsByPubkey":
                    ttype = "albums";
                    break;
                default:
                    ttype = type;
                    break;
                }
                rcount.html((total ? total : "No") + " " + ttype + " found");
                $(".nav-items li").removeClass("selected");
                $(".breadcrumbs").hide();
                $(".topnav-extra").hide();

                // always re-create the table, even if it exists,
                // to clear out any other possible content (review header)
                results = $("<table>", {
                    class: 'searchTable',
                    cellpadding: 2,
                    cellspacing: 0,
                    border: 0
                });
                $(".content").empty().append(results);
            }
            results.empty();
            if(total > 0) {
                // if this is not the entire result set, paginate
                if(response.data.length < total &&
                   response.data.length > chunksize)
                    response.data = response.data.slice(0, chunksize);
                // default is sort by artist
                if($("#sortBy").val() == "")
                    $("#sortBy").val("Artist");
                lists[type](results, response);
            } else {
                if($("#m").is(":checked"))
                    results.append('Hint: Uncheck "Exact match" box to broaden search.');
                else
                    results.append("<p>Select 'All' in the Search bar to expand your search.</p>")
            }
            var field = $("input.search-data");
            var val = field.val();
            field.get(0).setSelectionRange(val.length, val.length);
            field.trigger('focus');
        },
        error: function(jqXHR, textStatus, errorThrown) {
            var json = JSON.parse(jqXHR.responseText);
            var status = (json && json.errors)?
                    json.errors[0].title:('There was a problem retrieving the data: ' + textStatus);
            alert(status);
        }
    });
}

    $(window).on('pageshow', function(e) {
        if(e.originalEvent.persisted) {
            // restore search fields from bfcache
            // schedule for later to avoid webkit's autocomplete=off blanking
            setTimeout(function() {
                switch($("#type").val()) {
                case "artists":
                case "albums":
                case "tracks":
                case "labels":
                    $(".search-data").val($("#fkey").val());
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

            switch(params.type) {
            case "albumsByPubkey":
                $("#search-filter").val('all').selectmenu('refresh');
                var width = $("#search-filter-button").get(0).offsetWidth;
                $(".search-data").val('')
                    .css('padding-right', (width + 6) + "px");
                $("#search-filter-button").removeClass('override');
                break;
            case "all":
            case "artists":
            case "albums":
            case "tracks":
            case "labels":
                $("#search-filter").val(params.type).selectmenu('refresh');
                var width = $("#search-filter-button").get(0).offsetWidth;
                $(".search-data").val(params.fkey)
                    .css('padding-right', (width + 6) + "px");
                if(params.type == "all")
                    $("#search-filter-button").removeClass('override');
                else
                    $("#search-filter-button").addClass('override');
                break;
            }

            if(params.type == "all")
                $("#type").trigger('fsearch');
            else
                search(maxresults, 0);
        } else {
            $(".search-data").val("");
            $("#search-filter").val("all").selectmenu('refresh');
            $("#search-filter-button").removeClass('override');
        }
    });

    $("#type").on('lsearch', function() {
        if($("#fkey").val().length == 0)
            $("#fkey").val($(".search-data").val());
        search(maxresults, 0);
    });
});
