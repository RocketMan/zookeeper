//
// Zookeeper Online
//
// @author Jim Mason <jmason@ibinx.com>
// @copyright Copyright (C) 1997-2021 Jim Mason <jmason@ibinx.com>
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

/*! Zookeeper Online (C) 1997-2021 Jim Mason <jmason@ibinx.com> | @source: https://zookeeper.ibinx.com/ | @license: magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3.0 */

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

    var last = { show_id: 0, id: 0 };
    var socket = new WebSocket($("#push-subscribe").val());
    socket.onmessage = function(message) {
        var onnow = JSON.parse(message.data);
        if(onnow.show_id != last.show_id || onnow.id != last.id) {
            clearInterval(last.flip);
            if(onnow.show_id == 0) {
                $(".home-show").html("");
                $(".home-currenttrack").hide();
                $(".home-datetime").html("[No playlist available]").show();
            } else {
                var start = new Date(onnow.show_start);
                var end = new Date(onnow.show_end);
                $(".home-show").html("<A HREF='?action=viewDate&amp;seq=selList&amp;playlist=" + onnow.show_id + "' CLASS='nav'>" + onnow.name + "</A>&nbsp;with&nbsp;" + onnow.airname);
                $(".home-datetime").html(start.toDateString() + " " + localTime(start) + " - " + localTime(end));
                if(onnow.id == 0) {
                    $(".home-currenttrack").hide();
                    $(".home-datetime").show();
                } else {
                    $(".home-datetime").hide();
                    $(".home-currenttrack").html(onnow.track_artist + " &#8211; <I>" + onnow.track_title + "</I> (" + onnow.track_album + ")").show();
                    onnow.current = 0;
                    onnow.flip = setInterval(function() {
                        switch(last.current++) {
                        case 0:
                            break;
                        case 1:
                            $(".home-currenttrack").hide();
                            $(".home-datetime").show();
                            break;
                        case 2:
                            $(".home-datetime").hide();
                            $(".home-currenttrack").show();
                            last.current = 0;
                            break;
                        }
                    }, 4000);
                }
            }
            last = onnow;
        }
    };
});
