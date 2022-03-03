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

$().ready(function(){
    function localTime(date) {
        var hour = date.getHours();
        var ampm = hour >= 12?"pm":"am";
        var m = date.getMinutes();
        var min = m == 0?'':':' + String(m).padStart(2, '0');
        if(hour > 12)
            hour -= 12;
        return hour + min + ampm;
    }

    var palette = [ '#bdd0c4', '#9ab7d3', '#f5d2d3', '#f7e1d3', '#dfccf1' ];

    function makeCard(spin) {
        var img = $("<DIV>").append($("<A>", {
            href: spin.track_tag ?
                "?s=byAlbumKey&n=" +
                encodeURIComponent(spin.track_tag) +
                "&action=search" :
                spin.info_url,
            target: spin.track_tag ? "_self" : "_blank"
        }).append($("<IMG>", {
            class: "artwork",
            src: spin.image_url
        }).css('background-color', palette[Math.floor((Math.random() * palette.length))])));

        if(spin.track_tag || spin.info_url)
            img.find("A").attr('title', spin.track_tag ?
                               "View album in Zookeeper" :
                               "View artist in Discogs");

        var info = $("<DIV>", {
            class: "info"
        }).append($("<P>", {
            class: "track details"
        }).html(spin.track_title));
        info.append($("<P>", {
            class: "artist details"
        }).html(spin.track_artist));

        var card = $("<DIV>", {
            class: "card"
        }).append(img);
        card.attr("data-id", spin.id);
        card.attr("data-time", spin.track_time);
        card.append(info);
        card.append($("<SPAN>", {
            class: "time"
        }).html(localTime(new Date(spin.track_time))));

        return card;
    }

    function populateCards(replace, before) {
        var target = replace ?
            $("<DIV>", {
                class: "recently-played"
            }).css("display", "none") :
            $(".recently-played");

        if(target.length == 0)
            return;

        var url = '?subaction=recent';
        if(before != null)
            url += "&before=" + encodeURIComponent(before);

        $("div.content").css("background-color", "#eee");

        // hack to keep white body in sync
        var color = $("body").css("background-color");
        if(color == "rgb(255, 255, 255)")
            $("body").css("background-color", "#eee");

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

    $.fn.extend({
        fadeout: function() {
            return this.removeClass("zk-fade-visible").addClass("zk-fade-hidden");
        },
        fadein: function() {
            return this.removeClass("zk-fade-hidden").addClass("zk-fade-visible");
        }
    });

    function connect(last) {
        var socket = new WebSocket($("#push-subscribe").val());
        socket.last = last;
        socket.onmessage = function(message) {
            if(this.last.fader != null) {
                clearInterval(this.last.fader);
                this.last.fader = null;
            }

            var onnow = JSON.parse(message.data);
            if(onnow.show_id == 0) {
                $(".home-show").html("");
                $(".home-currenttrack").fadeout();
                $(".home-datetime").html("[No playlist available]").fadein();
            } else {
                var start = new Date(onnow.show_start);
                var end = new Date(onnow.show_end);
                $(".home-show").html("<A HREF='?action=viewDate&amp;seq=selList&amp;playlist=" + onnow.show_id + "' CLASS='nav'>" + onnow.name + "</A>&nbsp;with&nbsp;" + onnow.airname);
                $(".home-datetime").html(start.toDateString() + " " + localTime(start) + " - " + localTime(end));
                if(onnow.id == 0) {
                    $(".home-currenttrack").fadeout();
                    $(".home-datetime").fadein();
                } else {
                    $(".home-datetime").fadeout();
                    $(".home-currenttrack").html(onnow.track_artist + " &#8211; <I>" + onnow.track_title + "</I> (" + onnow.track_album + ")").fadein();
                    this.last.current = 0;
                    this.last.fader = setInterval(function() {
                        switch(socket.last.current++) {
                        case 0:
                            break;
                        case 1:
                            $(".home-currenttrack").fadeout();
                            $(".home-datetime").fadein();
                            break;
                        case 2:
                            $(".home-datetime").fadeout();
                            $(".home-currenttrack").fadein();
                            socket.last.current = 0;
                            break;
                        }
                    }, 5000);

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

    $("#date").change(function() {
        var date = $(this).val();
        var url = '?subaction=times&date=' + encodeURIComponent(date);

        $.ajax({
            dataType: 'json',
            type: 'GET',
            accept: 'application/json; charset=utf-8',
            url: url
        }).done(function (response) {
            $("#time").empty().append(response.times);
            var time = $("#time").val();
            populateCards(true, time == 'now' ? null : (date + " " + time));
        });
    });

    $("#time").change(function() {
        var time = $(this).val();
        populateCards(true, time == 'now' ? null : ($("#date").val() + " " + time));
    });

    $("#more").click(function() {
        var last = $(".card").last().data("time");
        if(last)
            populateCards(false, last);
    });

    populateCards(false, null);
    connect({ fader: null, open: false });
});
