//
// Zookeeper Online
//
// @author Jim Mason <jmason@ibinx.com>
// @copyright Copyright (C) 1997-2025 Jim Mason <jmason@ibinx.com>
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

/*! Zookeeper Online (C) 1997-2025 Jim Mason <jmason@ibinx.com> | @source: https://zookeeper.ibinx.com/ | @license: magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3.0 */

$().ready(function(){
    function localTime(date) {
        var m = date.getMinutes();
        var min = m == 0?'':':' + String(m).padStart(2, '0');
        var hour = date.getHours();
        switch(hour) {
        case 0:
            return m ? "12" + min + "am" : "midnight";
        case 12:
            return m ? hour + min + "pm" : "noon";
        default:
            var ampm = hour >= 12?"pm":"am";
            if(hour > 12)
                hour -= 12;
            else if(hour == 0)
                hour = 12;
            return hour + min + ampm;
        }
    }

    function serverDate(s) {
        var date = new Date(s.replace(/[+|-]\d+(:\d+)?$/, 'Z'));
        date.setMinutes(date.getMinutes() + date.getTimezoneOffset());
        return date;
    }

    const palette = [ '#bdd0c4', '#9ab7d3', '#f5d2d3', '#f7e1d3', '#dfccf1' ];

    const station_title = $("#station-title").val();

    function makeCard(spin) {
        var img = $("<div>").css('background-color', palette[Math.floor((Math.random() * palette.length))]
        ).append($("<a>", {
            href: spin.track_tag ?
                "?s=byAlbumKey&n=" +
                encodeURIComponent(spin.track_tag) +
                "&action=search" :
                spin.info_url,
            target: spin.track_tag ? "_self" : "_blank"
        }).append($("<img>", {
            class: "artwork",
            src: spin.image_url
        }).on('load', function() {
            this.style.opacity = 1;
        })));

        if(spin.track_tag || spin.info_url)
            img.find("a").attr('title', spin.track_tag ?
                               "View album in " + station_title :
                               "View artist in Discogs");

        var info = $("<div>", {
            class: "info"
        }).append($("<p>", {
            class: "track details"
        }).html(spin.track_title)
        ).append($("<p>", {
            class: "artist details"
        }).html(spin.track_artist));

        // hack for ES5 to parse as local time
        var spinTime = new Date(spin.track_time.replace(' ','T') + 'Z');
        spinTime.setMinutes(spinTime.getMinutes() + spinTime.getTimezoneOffset());

        var card = $("<div>", {
            class: "card",
            "data-id": spin.id,
            "data-time": spin.track_time
        }).append(img)
        .append(info)
        .append($("<span>", {
            class: "time"
        }).html(localTime(spinTime)));

        return card;
    }

    function getCount() {
        const width = $(document).width();
        var count;

        // widths correspond to the recently-played
        // @media settings in zoostyle.css
        if(width > 1150)
            count = 12;
        else if(width > 900)
            count = 10;
        else if(width > 700)
            count = 8;
        else
            count = 6;

        return count;
    }

    function populateInitial() {
        const target = $(".recently-played");
        if(target.length == 0)
            return;

        $("div.content").css("background-color", "#eee");

        // hack to keep white body in sync
        const color = $("body").css("background-color");
        if(color == "rgb(255, 255, 255)")
            $("body").css("--theme-content-background-colour", "#eee");

        const count = getCount();
        const plays = JSON.parse(document.getElementById("recent-play-data").textContent).slice(0, count);

        plays.forEach(function(spin) {
            if(target.find("div[data-id='" + spin.id + "']").length == 0) {
                var card = makeCard(spin);
                target.append(card);
            }
        });
    }


    function populateCards(replace, before) {
        const target = replace ?
            $("<div>", {
                class: "recently-played"
            }).css("display", "none") :
            $(".recently-played");

        if(target.length == 0)
            return;

        var url = '?subaction=recent';
        if(before != null)
            url += "&before=" + encodeURIComponent(before);

        var count = getCount();

        // completely fill the last row
        const currentCount = target.find(".card").length;
        count -= currentCount % (count / 2);

        url += "&count=" + encodeURIComponent(count);

        $.ajax({
            dataType: 'json',
            type: 'GET',
            accept: 'application/json; charset=utf-8',
            url: url
        }).done(function (response) {
            response.forEach(function(spin) {
                if(target.find("div[data-id='" + spin.id + "']").length == 0) {
                    var card = makeCard(spin);
                    target.append(card);
                }
            });

            if(replace) {
                $(".recently-played").fadeOut('', function() {
                    $(".recently-played").replaceWith(target);
                    $(".recently-played").fadeIn();
                });
            }
        });
    }

    function connect(last) {
        var socket = new WebSocket($("#push-subscribe").val());
        socket.last = last;
        socket.onmessage = function(message) {
            var onnow = JSON.parse(message.data);
            if(onnow.show_id == 0) {
                $(".home-title").html("On Now");
                $(".home-show").html("[No playlist available]");
                $(".home-currenttrack").html("&nbsp;");
            } else {
                var start = serverDate(onnow.show_start);
                var end = serverDate(onnow.show_end);
                $(".home-show").html("<a href='?subaction=viewListById&amp;playlist=" + onnow.show_id + "' class='nav'>" + onnow.name + "</a>&nbsp;with&nbsp;" + onnow.airname);
                $(".home-title").html("On Now: <span class='show-time'>" + localTime(start) + " - " + localTime(end) + " " + $("#tz").val() + "</span>");
                if(onnow.id == 0) {
                    $(".home-currenttrack").html("&nbsp;");
                } else {
                    var track = onnow.track_artist + " &#8211; <i>" + onnow.track_title + "</i>";
                    if(onnow.track_album)
                        track += " (" + onnow.track_album + ")";
                    $(".home-currenttrack").html(track);

                    var time = $("#time").val();
                    var nowPlaying = $(".recently-played");
                    if(time == 'now' &&
                            nowPlaying.find("div[data-id='" + onnow.id + "']").length == 0) {
                        var card = makeCard(onnow);
                        nowPlaying.prepend(card);
                    }
                }
            }
        }

        socket.onopen = function(event) {
            this.last.open = true;
        };

        socket.onclose = function(event) {
            /*
             * if we've lost the connection, retry in 30 seconds.
             *
             * note we only reconnect if we've been successfully
             * connected previously.  otherwise, we assume zookeeper
             * is not running the optional push notification service,
             * so we do not try again.
             */
            if(this.last.open) {
                setTimeout(function() {
                    connect(socket.last);
                }, 30000);
            }
        };
    };

    $("#date").on('change selectmenuchange', function() {
        var date = $(this).val();
        var url = '?subaction=times&date=' + encodeURIComponent(date);

        $.ajax({
            dataType: 'json',
            type: 'GET',
            accept: 'application/json; charset=utf-8',
            url: url
        }).done(function (response) {
            $("#time").empty().append(response.times).selectmenu('refresh');
            var time = $("#time").val();
            populateCards(true, time == 'now' ? null : (date + " " + time));
        });
    }).selectmenu({width: 145});

    $("#time").on('change selectmenuchange', function() {
        var time = $(this).val();
        populateCards(true, time == 'now' ? null : ($("#date").val() + " " + time));
    }).selectmenu({width: 145});

    $("#more").on('click', function() {
        var last = $(".card").last().data("time");
        if(last)
            populateCards(false, last);
    });

    $(".search-data").trigger('focus');

    populateInitial();
    connect({ open: false });
});
