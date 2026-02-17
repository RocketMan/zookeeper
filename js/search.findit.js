//
// Zookeeper Online
//
// @author Jim Mason <jmason@ibinx.com>
// @copyright Copyright (C) 1997-2026 Jim Mason <jmason@ibinx.com>
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

/*! Zookeeper Online (C) 1997-2026 Jim Mason <jmason@ibinx.com> | @source: https://zookeeper.ibinx.com/ | @license: magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3.0 */

$().ready(function() {
const OPTS = { day: "numeric", month: "short", year: "numeric" };

function htmlify(s) {
    return s != null?s.replace(/&/g, '&amp;').replace(/</g, '&lt;'):'';
}

function makeLink(href, name) {
    return $("<a>", { href: href }).html(name);
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
            var chunksize = 10, numchunks = 10;
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
            if(more > 20) {
                offset = 0;
                size = 10;
            }
            var a = $("<a>", {
                class: 'nav',
                href: '#',
            }).html(`View ${more} more &darr;`).on('click', (function(size, offset) {
                return function() {
                    search(type, links.href, size, offset);
                    return false;
                }
            })(size, offset));
            ul.append($("<li>", {
                class: 'more',
            }).css('width', 'auto').append(a));
        }
        table.append($("<footer>").append(ul));
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

    var th = $("<header>").append($("<div>", { class: 'section-title' }).html(header));
    table.append(th);

    data.forEach(function(entry) {
        var album = response.all ?
                    response.included.find(x => { return x.id == entry.id && x.type == 'album'; }) : entry;
        var href = `?action=search&s=byAlbumKey&n=${album.id}`;
        var tr = $("<article>", { 'data-href': href, tabindex: 0 });
        var albumx = $("<span>", { class: 'album' })
            .append(makeLink(href, htmlify(album.attributes.album)));
        tr.append(albumx);
        var artistx = $("<span>", { class: 'artist' }).html(getArtist(album.attributes));
        tr.append(artistx);
        var labelx = $("<span>", { class: 'meta' }).html(htmlify(album.relationships && album.relationships.label ? album.relationships.label.meta.name : "Unknown"));
        tr.append(labelx);

        if(tag)
            tr.append($("<span>").html("Tag #" + album.id));

        table.append(tr);
    });

    emitMore(table, response, odata, "albums");
}

function newTable(parent, id) {
    var section = $("<section>", { id: id });
    parent.append(section);
    return section;
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

    const showMore = table.has('li.more').length;
    if (showMore) {
        const start = table.outerHeight(true);
        table.css('height', start + 'px');
    }

    table.data('animate', showMore).empty();
    return table;
}

function clearSavedTable() {
    savedTableID = null;
}

var lists = {
    tags: function(table, response, data) {
        emitAlbumsEx(table, response, data, "Album Tags", 1);
    },

    albums: function(table, response, data) {
        emitAlbumsEx(table, response, data, "Artists and Albums", 0);
    },

    compilations: function(table, response, odata) {
        var data = response.all ? odata.relationships.album.data : odata;

        var th = $("<header>").append($("<div>", { class: 'section-title' }).html('Compilations'));
        table.append(th);

        data.forEach(function(entry) {
            var album = response.all ?
                        response.included.find(x => { return x.id == entry.id && x.type == 'album'; }) : entry;
            album.attributes.tracks.forEach(function(track) {
                var href = `?action=search&s=byAlbumKey&n=${album.id}`;
                var tr = $("<article>", { 'data-href': href, tabindex: 0 });
                var albumx = $("<span>", { class: 'album' })
                    .append(makeLink(href, htmlify(album.attributes.album)));
                tr.append(albumx);
                if (track.artist) {
                    var artistx = $("<span>", { class: 'artist' }).html(getArtist(track));
                    tr.append(artistx);
                }
                var trackx = $("<span>", { class: 'meta' }).html(htmlify(track.track));
                tr.append(trackx);
                table.append(tr);
            });
        });

        emitMore(table, response, odata, "compilations");
    },

    labels: function(table, response, odata) {
        var data = response.all ? odata.relationships.label.data : odata;

        var th = $("<header>").append($("<div>", { class: 'section-title' }).html('Labels'));
        table.append(th);

        data.forEach(function(entry) {
            var label = response.all ?
                        response.included.find(x => { return x.id == entry.id && x.type == 'label'; }) : entry;
            var href = `?action=search&s=byLabelKey&n=${label.id}`;
            var tr = $("<article>", { 'data-href': href, tabindex: 0 });
            var td = $("<span>", { class: 'labelName' })
                .append(makeLink(href, htmlify(label.attributes.name)));
            tr.append(td);
            if(label.attributes.city)
                tr.append($("<span>", { class: 'meta' }).html(htmlify(label.attributes.city) + "&nbsp;" +
                                                              htmlify(label.attributes.state)));
            table.append(tr);
        });

        emitMore(table, response, odata, "labels");
    },

    playlists: function(table, response, odata) {
        var data = response.all ? odata.relationships.show.data : odata;

        var th = $("<header>").append($("<div>", { class: 'section-title' }).html('Airplay'));
        table.append(th);

        data.forEach(function(entry) {
            var list = response.all ?
                        response.included.find(x => { return x.id == entry.id && x.type == 'show'; }) : entry;
            tr = $("<article>", { 'data-href': `?subaction=viewDJ&seq=selList&playlist=${list.id}`, tabindex: 0 });

            table.append(tr);

            list.attributes.events.forEach(function(event) {
                var td = $("<div>");
                if (event.artist) {
                    if (event.album) {
                        var albumx = $("<span>", { class: 'album' });
                        albumx.html(htmlify(event.album));
                        td.append(albumx);
                    }
                    var artistx = $("<span>", { class: 'artist' });
                    artistx.html(getArtist(event));
                    td.append(artistx);
                    var trackx = $("<span>");
                    trackx.html('"' + htmlify(event.track) + '"');
                    td.append(trackx);
                } else if (event.comment) {
                    var commentx = $("<span>");
                    commentx.html(htmlify(event.comment) + '...');
                    td.append(commentx);
                }
                tr.append(td);
            });

            var airedOn = $("<div>", { class: 'meta' });
            var showx = $("<span>").html(`<a href="?subaction=viewDJ&amp;seq=selList&amp;playlist=${list.id}">` + htmlify(list.attributes.name) + '</a>');
            airedOn.append(showx);
            var datex = $("<span>").html(
                new Date(list.attributes.date).toLocaleDateString(undefined, OPTS));
            airedOn.append(datex);
            tr.append(airedOn);
        });

        emitMore(table, response, odata, "playlists");
    },

    reviews: function(table, response, odata) {
        var data = response.all ? odata.relationships.review.data : odata;

        var th = $("<header>").append($("<div>", { class: 'section-title' }).html('Reviews'));
        table.append(th);

        data.forEach(function(entry) {
            var review = response.all ?
                        response.included.find(x => { return x.id == entry.id && x.type == 'review'; }) : entry;
            var album = review.relationships.album;
            var href = `?action=search&s=byAlbumKey&n=${album.data.id}`;
            var tr = $("<article>", { 'data-href': href, tabindex: 0 });
            var td = $("<span>", { class: 'album' })
                .append(makeLink(href, htmlify(album.meta.album)));
            tr.append(td);
            td = $("<span>", { class: 'artist' }).html(getArtist(album.meta));
            tr.append(td);
            td = $("<span>", { class: 'meta' }).html('(' +
                                  htmlify(review.attributes.airname) + ')');
            tr.append(td);
            table.append(tr);
        });

        emitMore(table, response, odata, "reviews");
    },

    tracks: function(table, response, odata) {
        var data = response.all ? odata.relationships.album.data : odata;

        var th = $("<header>").append($("<div>", { class: 'section-title' }).html('Tracks'));
        table.append(th);

        data.forEach(function(entry) {
            var album = response.all ?
                        response.included.find(x => { return x.id == entry.id && x.type == 'album'; }) : entry;
            album.attributes.tracks.forEach(function(track) {
                var href = `?action=search&s=byAlbumKey&n=${album.id}`;
                var tr = $("<article>", { 'data-href': href, tabindex: 0 });
                var td = $("<span>", { class: 'album' })
                    .append(makeLink(href, htmlify(album.attributes.album)));
                tr.append(td);
                td = $("<span>", { class: 'artist' }).html(getArtist(album.attributes));
                tr.append(td);
                td = $("<span>", { class: 'meta' }).html('"' + htmlify(track.track) + '"');
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
            fkey: field.val(),
            sortBy: sortBy
        }, false);
    }
}

async function search(type, url, size, offset) {
    if(size >= 0)
        url += "&page[size]=" + size;

    if(offset >= 0)
        url += "&page[offset]=" + offset;

    if(type == null)
        url += "&include=album,label,review,show";

    url += "&fields[album]=artist,album,tracks";
    url += "&fields[label]=name,city,state";
    url += "&fields[review]=-review";

    const pow = await getPoW();

    $.ajax({
        dataType: 'json',
        type: 'GET',
        accept: 'application/json; charset=utf-8',
        url: url,
        headers: { 'X-Challenge': btoa(JSON.stringify(pow)) },
        success: function(response) {
            // hack to keep white body in sync
            const color = $("body").css("background-color");
            if(color == "rgb(255, 255, 255)") {
                $("body").css("--theme-content-background-colour", "#ebebeb")
                    .data("saved-background-colour", color);
                $("div.content").css("background-color", "#ebebeb");
            }

            if(type != null) {
                var table = getTable(type);
                lists[type](table, response, response.data);

                table.find('article').on('click keydown', function(e) {
                    if (e.type == 'keydown' && e.which != 13)
                        return;

                    location.href = this.dataset.href;
                });

                if (table.data('animate')) {
                    const end = table.prop('scrollHeight');
                    requestAnimationFrame(() => {
                        table.css('height', end + 'px');
                    });

                    table.one('transitioned webkitTransitionEnd oTransitionEnd', () => {
                        table.css('height', 'auto');
                    });
                }

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
            $(".topnav-extra").hide();
            var content = $(".content");
            content.empty();
            var results = $("<div>", { class: 'findit' });
            content.append(results);
            response.all = true;
            response.data.forEach(function(list) {
                var type = list.type;
                lists[type](newTable(results, type), response, list);
            });

            $('.findit article').on('click keydown', function(e) {
                if (e.type == 'keydown' && e.which != 13)
                    return;

                location.href = this.dataset.href;
            });

            $('.findit footer').on('click', function() {
                $(this).find('a').trigger('click');
            });
        },
        error: function(jqXHR, textStatus, errorThrown) {
            // rate limited; silently ignore
            if(jqXHR.status == 403)
                return;

            var json;
            try {
                json = JSON.parse(jqXHR.responseText);
            } catch(e) {}
            var status = json && json.errors ?
                    json.errors.map(error => error.title).join(', ') :
                    'There was a problem retrieving the data: ' + errorThrown;
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
                case "artist":
                case "albumsByPubkey":
                case "reviews":
                case "playlists":
                    break;
                default:
                    $(".search-data").val($("#fkey").val());
                    break;
                }
            }, 100);
        }
    });

    $("#type").on('fsearch', async function() {
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
    }).on('keypress', function(e) {
        return e.keyCode != 13;
    }).on('cut paste', function() {
        // run on next tick, as pasted data is not yet in the field
        setTimeout(onSearchNow, 0);
    });
});
