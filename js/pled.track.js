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

$().ready(function(){
    const NME_ENTRY='nme-entry';
    const NME_PREFIX=$("#const-prefix").val();
    var tagId = "";
    var trackList = []; //populated by ajax query

    $("#track-type-pick").val('tag-entry');
    $("#track-tag").focus();

    function setAddButtonState(enableIt) {
        $("#track-submit").prop("disabled", !enableIt);
    }

    function clearUserInput(clearTagInfo) {
        var mode = $("#track-type-pick").val();
        $("#manual-entry input").val('');
        $("#comment-entry textarea").val('');
        $("#track-title-pick").val('0');
        $("#error-msg").text('');
        $("#tag-status").text('');
        $("#tag-artist").text('');
        $("#nme-entry input").val('');
        setAddButtonState(false);

        if (clearTagInfo) {
            $("#track-tag").val('').focus();
            $("#track-title-pick").find('option').remove();
            trackList = [];
            tagId = "";
        }

        switch(mode) {
        case 'manual-entry':
            $('#track-artist').focus();
            break;
        case 'comment-entry':
            $("#remaining").html("(0/" + $("#comment-max").val() + " characters)");
            $('#comment-data').focus();
            break;
        }
    }

    function isNmeType(entryType)  {
        var retVal = entryType.startsWith(NME_PREFIX);
        return retVal;
    }

    function getEventType(entryType) {
        return entryType.substring(NME_PREFIX.length);
    }

    function getEntryMode()  {
        var entryMode = $('#track-type-pick').val();
        if (isNmeType(entryMode))
            entryMode = NME_ENTRY;

        return entryMode;
    }

    // return true if have all required fields.
    function haveAllUserInput()  {
        var isEmpty = false;
        var entryMode = getEntryMode();

        if (entryMode == 'manual-entry') {
            $("#manual-entry input[required]").each(function() {
                isEmpty = isEmpty || $(this).val().length == 0;
            });
        } else if (entryMode == 'comment-entry') {
            isEmpty = $('#comment-data').val().length == 0;
        } else if (entryMode == NME_ENTRY) {
            isEmpty = $('#nme-id').val().length == 0;
        }

        return !isEmpty;
    }

    function showUserError(msg) {
        $('#error-msg').text(msg);
    }

    function getDiskInfo(id) {
        const INVALID_TAG = 100;
        $("#track-title-pick").find('option').remove();
        clearUserInput(false);
        tagId = ""
        var url = "zkapi.php?method=getTracksRq&json=1&key=" + id;
        $.ajax({
            dataType : 'json',
            type: 'GET',
            accept: "application/json; charset=utf-8",
            url: url,
        }).done(function (diskInfo) { //TODO: success?
            if (diskInfo.code == INVALID_TAG) {
                showUserError(id + ' is not a valid tag.');
                return;
            }

            tagId = id;
            var options = "<option value=''>Select Track</option>";
            trackList = diskInfo.data;
            for (var i=0; i < trackList.length; i++) {
                var track = trackList[i];
                var artist = track.artist ? track.artist + ' - ' : '';
                options += `<option value='${i}' >${i+1}. ${artist} ${track.track}</option>`;
            }
            $("#track-title-pick").find('option').remove().end().append(options);
            $("#track-artist").val(diskInfo.artist);
            $("#track-label").val(diskInfo.label);
            $("#track-album").val(diskInfo.album);
            $("#track-title").val("");
            $("#track-submit").attr("disabled");
            $("#track-submit").prop("disabled", true);
            $("#tag-artist").text(diskInfo.artist  + ' - ' + diskInfo.album);

        }).fail(function (jqXHR, textStatus, errorThrown) {
            showUserError('Ajax error: ' + textStatus);
        });
    }

    $("#track-type-pick").on('change', function() {
        // display the user entry div for this type
        var newType = getEntryMode();
        clearUserInput(true);
        $("#track-entry > div").addClass("zk-hidden");
        $("#" + newType).removeClass("zk-hidden");
        $("#" + newType + " *[data-focus]").focus();
        if (newType == NME_ENTRY) {
            var option = $("option:selected", this);
            var argCnt = $(option).data("args");
            // insert default value if no user entry required.
            if (argCnt == 0) {
                $("#nme-id").val($(option).text());
                setAddButtonState(true);
            }
        }
    });

    $("#track-title-pick").on('change', function() {
        var index= parseInt(this.value);
        var track = trackList[index];
        $("#track-title").val(track.track);
        // collections have an artist per track.
        if (track.artist)
            $("#track-artist").val(track.artist);

        setAddButtonState(true);
    });

    $("#manual-entry input").on('input', function() {
        var haveAll = haveAllUserInput();
        setAddButtonState(haveAll);
    });

    $("#comment-entry textarea").on('input', function() {
        var len = this.value.length;
        $("#remaining").html("(" + len + "/" + $("#comment-max").val() + " characters)");
        setAddButtonState(len > 0);
    });

    $("#markdown-help-link").click(function() {
        if($("#markdown-help").is(":visible")) {
            $("#markdown-help").hide();
            $("#markdown-help-link").text("formatting help");
        } else {
            $("#markdown-help").css('padding-left','80px');
            $("#markdown-help").show();
            $("#markdown-help-link").text("hide help");
        }
    });

    $("#nme-entry input").on('input', function() {
        var haveAll = haveAllUserInput();
        setAddButtonState(haveAll);
    });

    $("INPUT[data-date]").focusout(function() {
        // if field is blank, apply no validation
        var v = $(this).val();
        if(v.length == 0)
            return;

        var d = $(this).data("date");
        var s = $(this).data("start");
        var e = $(this).data("end");

        var start = new Date(d + "T" + s);
        var end = new Date(d + "T" + e);
        var val = new Date(d + "T" + v);

        if(!isNaN(val)) {
            // val or end time can be midnight or later
            // in this case, adjust to the next day
            if(e < s)
                end.setTime(end.getTime() + 86400000);
            if(v < s)
                val.setTime(val.getTime() + 86400000);
        }

        if(isNaN(val) || val < start || val > end) {
            alert('time is not valid');
            $(this).val("").focus();
        } else {
            // if time is after midnight, set edate field to correct date
            $("INPUT[name=edate]").val(val.toISOString().split('T')[0]);
        }
    });

    function moveTrack(list, fromId, toId, tr, si, rows) {
        var postData = {
            session: $("#track-session").val(),
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
            success: function(respObj) {
                // move succeeded, clear timestamp
                tr.find("td").eq(1).html("");
                $("#error-msg").text("");
            },
            error: function (jqXHR, textStatus, errorThrown) {
                // move failed; restore original sequence
                if(tr.index() < si)
                    rows.eq(si+1).after(tr);
                else
                    rows.eq(si).after(tr);

                $('#error-msg').text("Error moving track: " + jqXHR.responseJSON.status);
            }
        });
    }

    // grabStart DnD code adapted from NateS
    //
    // Reference: https://stackoverflow.com/questions/2072848/reorder-html-table-rows-using-drag-and-drop/42720364
    function grabStart(e) {
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
                var listId = $("#track-playlist").val();
                var sourceId = source.data("id");
                var targetId = target.data("id");
                moveTrack(listId, sourceId, targetId, tr, si, rows);
            }
            $(document).unbind("mousemove", move).unbind("mouseup", up);
            b.removeClass("grabCursor").css("userSelect", "none");
            tr.removeClass("grabbed");
        }
        $(document).mousemove(move).mouseup(up);
    }

    function submitTrack(addSeparator) {
        var artist, label, album, track, type, eventType, eventCode, comment;
        var trackType =  $("#track-type-pick").val();

        if (addSeparator) {
            type = $("#const-set-separator").val();
        } else if (isNmeType(trackType)) {
            type = $("#const-log-event").val();
            eventType = getEventType(trackType);
            eventCode = $("#nme-id").val();
        } else if (trackType == 'comment-entry') {
            type = $("#const-comment").val();
            comment = $("#comment-data").val();
        } else {
            type = $("#const-spin").val();
            artist = $("#track-artist").val();
            label =  $("#track-label").val();
            album =  $("#track-album").val();
            track =  $("#track-title").val();
        }

        var postData = {
            playlist: $("#track-playlist").val(),
            session: $("#track-session").val(),
            type: type,
            tag: $("#track-tag").val(),
            artist: artist,
            label: label,
            album: album,
            track: track,
            eventType: eventType,
            eventCode: eventCode,
            comment: comment,
            size: $(".playlistTable > tbody > tr").length,
        };

        $.ajax({
            type: "POST",
            url: "?action=addTrack&oaction=" + $("#track-action").val(),
            dataType : 'json',
            accept: "application/json; charset=utf-8",
            data: postData,
            success: function(respObj) {
                // *1 to coerce to int as switch uses strict comparison
                switch(respObj.seq*1) {
                case -1:
                    // playlist is out of sync with table; reload
                    location.href = "?action=" + $("#track-action").val() +
                        "&playlist=" + $("#track-playlist").val() +
                        "&session=" + $("#track-session").val();
                    break;
                case 0:
                    // playlist is in natural order; prepend
                    $(".playlistTable > tbody").prepend(respObj.row);
                    $(".playlistTable > tbody > tr").eq(0).find(".grab").mousedown(grabStart);
                    break;
                default:
                    // seq specifies the ordinal of the entry,
                    // where 1 is the first (oldest).
                    //
                    // Calculate the zero-based row index from seq.
                    // Table is ordered latest to oldest, which means
                    // we must reverse the sense of seq.
                    var rows = $(".playlistTable > tbody > tr");
                    var index = rows.length - respObj.seq + 1;
                    if(index < rows.length)
                        rows.eq(index).before(respObj.row);
                    else
                        rows.eq(rows.length - 1).after(respObj.row);
                    $(".playlistTable > tbody > tr").eq(index).find(".grab").mousedown(grabStart);
                    break;
                }
                clearUserInput(true);
            },
            error: function (jqXHR, textStatus, errorThrown) {
                showUserError("Your track was not saved: " + jqXHR.responseJSON.status);
            }
        });
    }

    $("#track-submit").click(function(e) {
        // double check that we have everything.
        if (haveAllUserInput() == false) {
            alert('A required field is missing');
            return;
        }
        submitTrack(false);
    });

    $("#track-separator").click(function(e) {
        submitTrack(true);
    });

    $("#track-tag").on('keyup', function(e) {
        showUserError('');
        if (e.keyCode == 13) {
            $(this).blur();
            $('#track-title-pick').focus();
        }
    });

    $("#track-title-pick").on('focus', function() {
        var newId = $("#track-tag").val()
        if (newId.length > 0 && newId != tagId)
            getDiskInfo(newId);
    });

    $(".playlistTable .grab").mousedown(grabStart);

    $("*[data-focus]").focus();
});
