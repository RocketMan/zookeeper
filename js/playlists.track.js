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

    function htmlify(s) {
        return s?s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/\'/g, '&#39;'):"";
    }

    function escQuote(s) {
        return s.replace(/\'/g, '\\\'');
    }

    function setAddButtonState(enableIt) {
        $("#track-submit").prop("disabled", !enableIt);
    }

    function clearUserInput(clearTagInfo) {
        var mode = $("#track-type-pick").val();
        $("#track-time").val('');
        $("#manual-entry input").val('');
        $("#comment-entry textarea").val('');
        $("#track-artists").empty();
        $("#track-title-pick").val('0');
        $("#track-titles").empty();
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

    function getDiskInfo(id, refArtist) {
        $("#track-title-pick").find('option').remove();
        $("#track-title").attr('list',''); // webkit hack
        $("#track-titles").empty();
        clearUserInput(false);
        tagId = ""
        // chars [ and ] in the QS param name are supposed to be %-encoded.
        // It seems to work ok without and reads better in the server logs,
        // but this may need revisiting if there are problems.
        var url = "api/v1/album/" + id +
            "?fields[album]=artist,album,tracks";
        $.ajax({
            dataType : 'json',
            type: 'GET',
            accept: "application/json; charset=utf-8",
            url: url,
        }).done(function (response) { //TODO: success?
            if (response.errors) {
                showUserError(id + ' is not a valid tag.');
                return;
            }

            tagId = id;
            if($("#track-tag").val() != id)
                $("#track-tag").val(id);
            var options = "<option value=''>Select Track</option>";
            var options2 = "";
            var diskInfo = response.data;
            trackList = diskInfo.attributes.tracks;
            for (var i=0; i < trackList.length; i++) {
                var track = trackList[i];
                var artist = track.artist ? track.artist + ' - ' : '';
                options += `<option value='${i}' >${i+1}. ${artist} ${track.track}</option>`;
                options2 += "<option data-track='" + htmlify(track.track) +
                    "' data-artist='" + htmlify(track.artist) +
                    "' value='" + htmlify((i+1) + ". " + artist + track.track) + "'>";
            }
            $("#track-title-pick").find('option').remove().end().append(options);
            $("#track-titles").html(options2);
            $("#track-title").attr('list','track-titles'); // webkit hack
            $("#track-artist").val(diskInfo.attributes.artist);
            $("#track-label").val(diskInfo.relationships.label.meta.name);
            $("#track-album").val(diskInfo.attributes.album);
            $("#track-title").val("");
            $("#track-submit").attr("disabled");
            $("#track-submit").prop("disabled", true);
            if(refArtist) {
                var tracks = $("#track-titles option[data-artist='" +
                               escQuote(refArtist) + "']");
                // for a compilation...
                if(tracks.length > 0) {
                    // ...remove all artists but this one
                    $("#track-titles option").not("[data-artist='" +
                                                  escQuote(refArtist) +
                                                  "']").remove();
                    // ...prefill the artist
                    $("#track-artist").val(refArtist);
                    // ...if only one track by this artist, select it
                    if(tracks.length == 1) {
                        $("#track-title").val(tracks.data("track"));
                        setAddButtonState(true);
                    }
                }
            }
            $("#tag-artist").text(diskInfo.attributes.artist  + ' - ' + diskInfo.attributes.album);

        }).fail(function (jqXHR, textStatus, errorThrown) {
            var json = JSON.parse(jqXHR.responseText);
            var status = (json && json.errors)?
                    json.errors[0].title:('Ajax error: ' + textStatus);
            showUserError(status);
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
        if(v.length == 0) {
            $(this).removeClass('invalid-input');
            return;
        }

        // no time picker in webkit; be flexible in accepting input...
        // ...accept am/pm time
        var ampm = true;
        var offset = 0;
        var vlc = v.toLowerCase();
        var am = vlc.indexOf('am');
        var pm = vlc.indexOf('pm');
        if(am > 0)
            v = v.substring(0, am);
        else if(pm > 0) {
            v = v.substring(0, pm);
            offset = 12;
        } else
            ampm = false;

        // ...accept time without separator (e.g., 1234)
        if(v.length > 2 && v.indexOf(':') < 0)
            v = v.substr(0, v.length-2) + ':' + v.substr(v.length-2);

        // ...accept time without leading zero (e.g., 2:34)
        v = v.trim().padStart(5, '0');

        // ...fix special case of 12am/pm
        var hour = v.substr(0, 2)*1;
        if(ampm && hour == 12)
            offset -= 12;

        // ...coerce am/pm to 24 hour time
        if(offset != 0)
            v = (hour + offset).toString().padStart(2, '0') + v.substr(2);

        var d = $(this).data("date");
        var s = $(this).data("start");
        var e = $(this).data("end");

        var start = new Date(d + "T" + s + "Z");
        var end = new Date(d + "T" + e + "Z");
        var val = new Date(d + "T" + v + "Z");

        if(!isNaN(val)) {
            // val or end time can be midnight or later
            // in this case, adjust to the next day
            if(e < s)
                end.setTime(end.getTime() + 86400000);
            if(v < s)
                val.setTime(val.getTime() + 86400000);
        }

        if(isNaN(val) || val < start || val > end) {
            $(this).removeClass('prefilled-input');
            $(this).addClass('invalid-input');
            $(this).val("").focus();
            showUserError('Spin time is outside of show start/end times.');
        } else {
            // if we massaged time for webkit, set canonical value
            if($(this).val() != v)
                $(this).val(v);

            // if time is after midnight, set edate field to correct date
            $("INPUT[name=edate]").val(val.toISOString().split('T')[0]);
            $(this).removeClass('invalid-input');
            showUserError('');
        }
    });

    $("#edit-save").click(function(){
        // submit form only if time field is valid/empty
        if($("INPUT[data-date].invalid-input").length == 0)
            $(this).closest("FORM").submit();
    });

    $("#edit-delete").click(function(){
        if(confirm("Delete this entry?")) {
            // we need to indicate that the ' Delete ' button was pressed
            var input = $("<INPUT>").attr({
                type: 'hidden',
                name: 'button',
                value: ' Delete '
            });
            $(this).closest("FORM").append(input).submit();
        }
    });

    // display highlight on track edit
    if($("FORM#edit").length > 0) {
        var id = $("FORM#edit INPUT[name=id]").val();
        $("DIV[data-id=" + id + "]").closest("TR").addClass("highlight");
    }

    function moveTrack(list, fromId, toId, tr, si, rows) {
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
        console.log("enter submitTrack");
        var showDate, spinTime, artist, label, album, track, type, eventType, eventCode, comment;
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
        showDate =  $("#show-date").val();
        spinTime =  $("#track-time").val();

        var postData = {
            playlist: $("#track-playlist").val(),
            type: type,
            tag: $("#track-tag").val(),
            artist: artist,
            label: label,
            time: spinTime,
            date: showDate,
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
                        "&playlist=" + $("#track-playlist").val();
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
                const msg = jqXHR.responseJSON ? jqXHR.responseJSON.status : errorThrown;
                showUserError("Your track was not saved: " + msg);
            }
        });
    }

    $("#track-submit").click(function(e) {
        // double check that we have everything.
        if (haveAllUserInput() == false) {
            alert('A required field is missing');
            return;
        }
        // check that the timestamp, if any, is valid
        if($("INPUT[data-date].invalid-input").length > 0)
            return;
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

    function getArtist(node) {
        var name = node.artist;
        if(name.substr(0, 8) == '[coll]: ')
            name = 'Various Artists';
        return htmlify(name);
    }

    function searchLibrary(key) {
        var url = "zkapi.php?method=libLookupRq" +
            "&type=artists" +
            "&key=" + encodeURIComponent(key) + "*" +
            "&size=50";
        var results = $("#track-artists");
        $.ajax({
            dataType : 'json',
            type: 'GET',
            accept: "application/json; charset=utf-8",
            url: url,
            success: function(response) {
                $("#track-artist").attr('list',''); // webkit hack
                results.empty();
                if(response.total > 0) {
                    var data = response.data[0];
                    data.data.forEach(function(entry) {
                        var row = htmlify(entry.artist) + " - " +
                            htmlify(entry.album) + " (#" +
                            entry.tag + ")";
                        results.append("<option data-tag='" + entry.tag +
                                       "' data-artist='" + htmlify(entry.artist) +
                                       "' value='" + row + "'>");
                    });
                    $("#track-artist").attr('list','track-artists'); // webkit hack
                    $("#track-artist").focus(); // webkit hack
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('There was a problem retrieving the JSON data:\n' + textStatus);
            }
        });
    }

    $("#track-artist").on('input', function() {
        var artist = $(this).val();
        var opt = $("#track-artists option[value='" + escQuote(artist) + "']");
        if(opt.length > 0) {
            getDiskInfo(opt.data("tag"), opt.data("artist"));
        } else {
            $("#track-tag").val("");
            $("#track-artists").empty();
            $("#track-titles").empty();
            if(artist.length > 3) {
                searchLibrary(artist);
            }
        }
    });

    $("#track-title").click(function() {
        $("#track-titles").show().hide();
    }).on('change textInput input', function() {
        var title = $("#track-title").val();
        var opt = $("#track-titles option[value='" + escQuote(title) + "']");
        if(opt.length > 0) {
            var artist = opt.data("artist");
            if(artist)
                $("#track-artist").val(artist);
            $("#track-title").val(opt.data("track"));
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
