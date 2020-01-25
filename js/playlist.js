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

function moveTrack(list, fromId, toId) {
    var success = false;
    var postData = {
        playlist: list,
        fromId: fromId,
        toId: toId
    };

    $.ajax({
        type: "POST",
        url: "?action=moveTrack",
        dataType : "json",
        accept: "application/json; charset=utf-8",
        data: postData,
        async: false,
        success: function(respObj) {
            success = true;
        },
        error: function (jqXHR, textStatus, errorThrown) {
            $('#error-msg').text("Error moving track: " + jqXHR.responseJSON.status);
        }
    });

    return success;
}

// DnD code adapted from NateS
//
// Reference: https://stackoverflow.com/questions/2072848/reorder-html-table-rows-using-drag-and-drop/42720364

$(".playlistTable .grab").mousedown(function (e) {
    var tr = $(e.target).closest("TR"), si = tr.index(), sy = e.pageY, b = $(document.body), drag;
    b.addClass("grabCursor").css("userSelect", "none");
    tr.addClass("grabbed");
    function move (e) {
        if (!drag && Math.abs(e.pageY - sy) < 10) return;
        drag = true;
        tr.siblings().each(function() {
            var s = $(this), i = s.index(), y = s.offset().top;
            if (e.pageY >= y && e.pageY < y + s.outerHeight()/2) {
                if (i < tr.index())
                    tr.insertBefore(s);
                else
                    tr.insertAfter(s);
                return false;
            }
        });
    }
    function up (e) {
        if (drag && si != tr.index()) {
            drag = false;
            var source = tr.find(".grab");
            var rows = tr.closest("table").find("tr");
            var ti = tr.index();
            var target = rows.eq(ti+(si<ti?0:2)).find(".grab");
            var listId = source.data("list");
            var sourceId = source.data("id");
            var targetId = target.data("id");
            if(moveTrack(listId, sourceId, targetId)) {
                // move succeeded, clear timestamp
                tr.find("td").eq(1).html("");
            } else {
                // move failed; restore original sequence
                if(tr.index() < si)
                    rows.eq(si+1).after(tr);
                else
                    rows.eq(si).after(tr);
            }
        }
        $(document).unbind("mousemove", move).unbind("mouseup", up);
        b.removeClass("grabCursor").css("userSelect", "none");
        tr.removeClass("grabbed");
    }
    $(document).mousemove(move).mouseup(up);
});

