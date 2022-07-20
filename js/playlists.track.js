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
    const NME_ENTRY='nme-entry';
    const NME_PREFIX=$("#const-prefix").val();
    var tagId = 0;
    var seq = 0;

    function htmlify(s) {
        return s?String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/\'/g, '&#39;'):"";
    }

    function escQuote(s) {
        return String(s).replace(/\'/g, '\\\'');
    }

    function setAddButtonState(enableIt) {
        var hasPending = $("#track-time").data("live") && getPending() != null;
        $("#track-add").prop("disabled", !enableIt);
        $("#track-play").prop("disabled", !enableIt || hasPending);

        $("#edit-save").prop("disabled", !enableIt);
    }

    function clearUserInput(clearArtistList) {
        $("#manual-entry input").removeClass('invalid-input').val('');
        $("#comment-entry textarea").val('');
        $("#track-title").attr('list',''); // webkit hack
        $("#track-titles").empty();
        $("#error-msg").text('');
        $("#tag-status").text('');
        $("#nme-entry input").val('');

        if (clearArtistList) {
            $("#track-artist").attr('list',''); // webkit hack
            $("#track-artists").empty();
            $("#track-artist").attr('list','track-artists'); // webkit hack
        }

        tagId = 0;

        setAddButtonState(false);

        var mode = $("#track-type-pick").val();
        switch(mode) {
        case 'manual-entry':
            $('#track-artist').focus();
            break;
        case 'comment-entry':
            $("#remaining").html("(0/" + $("#comment-max").val() + " characters)");
            $('#comment-data').focus();
            break;
        case 'set-separator':
            setAddButtonState(true);
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
        clearUserInput(false);

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
            tagId = id;
            var options = "";
            var diskInfo = response.data;
            var trackList = diskInfo.attributes.tracks;
            for (var i=0; i < trackList.length; i++) {
                var track = trackList[i];
                var artist = track.artist ? track.artist + ' - ' : '';
                options += "<option data-track='" + htmlify(track.track) +
                    "' data-artist='" + htmlify(track.artist) +
                    "' value='" + htmlify((i+1) + ". " + artist + track.track) + "'>";
            }
            $("#track-titles").html(options);
            $("#track-title").attr('list','track-titles'); // webkit hack
            $("#track-artist").val(diskInfo.attributes.artist);
            $("#track-label").val(diskInfo.relationships != null &&
                                  diskInfo.relationships.label != null ?
                                  diskInfo.relationships.label.meta.name :
                                  "(Unknown)");
            $("#track-album").val(diskInfo.attributes.album);
            $("#track-title").val("");
            $(".track-submit").attr("disabled");
            $(".track-submit").prop("disabled", true);
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
        }).fail(function (jqXHR, textStatus, errorThrown) {
            var json = JSON.parse(jqXHR.responseText);
            if (json && json.errors) {
                if (json.errors[0].status == 404)
                    status = id + ' is not a valid tag.';
                else
                    status = json.errors[0].title;
            } else
                status = 'There was a problem retrieving the data: ' + textStatus;
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

        if($("#edit-type").length > 0) {
            ['artist','album','label','title'].forEach(function(field) {
                $("#track-" + field).val($("#old-track-" + field).val());
            });
            var tag = $("#old-track-tag").val();
            if(tag)
                tagId = tag;
            $("#comment-data").val($("#old-comment-data").val());
            $("#nme-id").val($("#old-event-code").val());

            var haveAll = haveAllUserInput();
            setAddButtonState(haveAll);
        }
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
        var fxtime = $(this).hasClass('fxtime');
        var v = fxtime ? $(this).fxtime('val') : $(this).val();
        if(v == null || v.length == 0) {
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
            if(fxtime)
                $(this).focus();
            else
                $(this).val("").focus();
            showUserError('Time is invalid');
        } else {
            // if we massaged time for webkit, set canonical value
            if(!fxtime && $(this).val() != v)
                $(this).val(v);

            // if time is after midnight, set edate field to correct date
            $("INPUT[name=edate]").val(val.toISOString().split('T')[0]);
            $(this).removeClass('invalid-input');
            showUserError('');
        }
    });

    $("#edit-save").click(function(){
        // double check that we have everything.
        if (haveAllUserInput() == false) {
            showUserError('A required field is missing');
            return;
        }

        // don't allow submission of album tag in the artist field
        if($("#track-artist").val().trim().match(/^\d+$/) && tagId == 0) {
            showUserError('Album tag is invalid');
            $("#track-artist").focus();
            return;
        }

        // check that the timestamp, if any, is valid
        if($("INPUT[data-date].invalid-input").length > 0)
            return;

        var itemType, comment, eventType, eventCode;
        var artist, label, album, track;
        var trackType = $('#track-type-pick').val();
        switch(trackType) {
        case 'manual-entry':
            itemType = 'spin';
            artist = $("#track-artist").val();
            label =  $("#track-label").val();
            album =  $("#track-album").val();
            track =  $("#track-title").val();
            break;
        case 'comment-entry':
            itemType = 'comment';
            comment = $("#comment-data").val();
            break;
        case 'set-separator':
            itemType = 'break';
            break;
        default:
            itemType = 'logEvent';
            eventType = getEventType(trackType);
            eventCode = $("#nme-id").val();
            break;
        }

        var playlistId = $('#track-playlist').val();
        var postData = {
            data: {
                type: 'event',
                id: $('#track-id').val(),
                attributes: {
                    type: itemType,
                    artist: artist,
                    album: album,
                    track: track,
                    label: label,
                    comment: comment,
                    event: eventType,
                    code: eventCode,
                    created: $("#edit-time").fxtime('val')
                }
            }
        };

        if(trackType == 'manual-entry' && tagId) {
            postData.data.relationships = {
                album: {
                    data: {
                        type: 'album',
                        id: tagId
                    }
                }
            };
        }

        $.ajax({
            type: 'PATCH',
            url: 'api/v1/playlist/' + playlistId + '/events',
            dataType: 'json',
            contentType: "application/json; charset=utf-8",
            accept: "application/json; charset=utf-8",
            data: JSON.stringify(postData),
            success: function(response) {
                location.href = "?action=" + $("#track-action").val() +
                    "&playlist=" + playlistId;
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var json = JSON.parse(jqXHR.responseText);
                var status = (json && json.errors)?
                    json.errors[0].title:('There was a problem extending the show time: ' + textStatus);
                showUserError(status);
            }
        });
    });

    $("#edit-delete").click(function(){
        if(!confirm("Delete this item?"))
            return;

        var playlistId = $('#track-playlist').val();
        var postData = {
            data: {
                type: 'event',
                id: $('#track-id').val()
            }
        };

        $.ajax({
            type: 'DELETE',
            url: 'api/v1/playlist/' + playlistId + '/events',
            dataType: 'json',
            contentType: "application/json; charset=utf-8",
            accept: "application/json; charset=utf-8",
            data: JSON.stringify(postData),
            success: function(response) {
                location.href = "?action=" + $("#track-action").val() +
                    "&playlist=" + playlistId;
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var json = JSON.parse(jqXHR.responseText);
                var status = (json && json.errors)?
                    json.errors[0].title:('There was a problem extending the show time: ' + textStatus);
                showUserError(status);
            }
        });
    });

    $("#edit-cancel").click(function(){
        location.href = "?action=" + $("#track-action").val() +
            "&playlist=" + $("#track-playlist").val();
    });

    // display highlight on track edit
    if($("#track-id").length > 0) {
        var id = $("#track-id").val();
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
                tr.find("td").eq(1).data('utc','').html('');
                $("#error-msg").text("");
                updatePlayable();
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

    function submitTrack(id) {
        console.log("enter submitTrack");
        var showDate, spinTime, artist, label, album, track, type, eventType, eventCode, comment;
        var trackType =  $("#track-type-pick").val();

        if (trackType == 'set-separator') {
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
        spinTime =  $("#track-time").fxtime('val');

        var postData = {
            playlist: $("#track-playlist").val(),
            type: type,
            tag: tagId,
            artist: artist,
            label: label,
            time: spinTime,
            date: showDate,
            album: album,
            track: track,
            eventType: eventType,
            eventCode: eventCode,
            comment: comment,
            cue: id == 'track-add' ? 1 : 0,
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

                updatePlayable();
                clearUserInput(true);
                $("#track-time").fxtime('seg', 1, null).fxtime('seg', 2, 0);
                if(spinTime != null)
                    $("#track-time").data('last-val', spinTime);

                $("#track-type-pick").val('manual-entry').trigger('change');

                if(respObj.runsover) {
                    $("#extend-show").show();
                    $("#extend-time").focus();
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                const msg = jqXHR.responseJSON ? jqXHR.responseJSON.status : errorThrown;
                showUserError("Your track was not saved: " + msg);
            }
        });
    }

    $(".track-submit").click(function(e) {
        // double check that we have everything.
        if (haveAllUserInput() == false) {
            showUserError('A required field is missing');
            return;
        }

        // don't allow submission of album tag in the artist field
        if($("#track-artist").val().trim().match(/^\d+$/) && tagId == 0) {
            showUserError('Album tag is invalid');
            $("#track-artist").focus();
            return;
        }

        // check that the timestamp, if any, is valid
        if($("INPUT[data-date].invalid-input").length > 0)
            return;

        submitTrack(this.id);
    });

    function getArtist(node) {
        var name = node.artist;
        if(name.substr(0, 8) == '[coll]: ')
            name = 'Various Artists';
        return htmlify(name);
    }

    function addArtists(data) {
        var artist = $("#track-artist");
        artist.attr('list', ''); // webkit hack

        var results = $("#track-artists");
        results.empty();

        data.forEach(function(entry) {
            var attrs = entry.attributes;
            var row = htmlify(attrs.artist) + " - " +
                htmlify(attrs.album) + " (#" +
                entry.id + ")";
            results.append("<option data-tag='" + entry.id +
                           "' data-artist='" + htmlify(attrs.artist) +
                           "' value='" + row + "'>");
        });

        artist.attr('list', 'track-artists'); // webkit hack
    }

    function searchLibrary(key) {
        var url = "api/v1/album?filter[artist]=" +
            encodeURIComponent(key) + "*" +
            "&page[size]=50&fields[album]=artist,album";

        $.ajax({
            dataType : 'json',
            type: 'GET',
            accept: "application/json; charset=utf-8",
            url: url,
            seq: ++seq,
            success: function(response) {
                // process only the last-issued search{Library,Tag} request
                if(this.seq == seq)
                    addArtists(response.links.first.meta.total > 0 ?
                               response.data : []);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var json = JSON.parse(jqXHR.responseText);
                var status = (json && json.errors)?
                        json.errors[0].title:('There was a problem retrieving the data: ' + textStatus);
                showUserError(status);
            }
        });
    }

    function searchTag(id) {
        var url = "api/v1/album/" + id +
            "?fields[album]=artist,album";

        $.ajax({
            dataType : 'json',
            type: 'GET',
            accept: "application/json; charset=utf-8",
            url: url,
            seq: ++seq,
            success: function(response) {
                // process only the last-issued search{Library,Tag} request
                if(this.seq == seq)
                    addArtists([ response.data ]);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                if(jqXHR.status == 404) {
                    // tag does not exist; silently ignore
                    return;
                }

                var json = JSON.parse(jqXHR.responseText);
                var status = (json && json.errors)?
                        json.errors[0].title:('There was a problem retrieving the data: ' + textStatus);
                showUserError(status);
            }
        });
    }

    $("#track-artist").focusout(function() {
        $(this).removeClass('invalid-input');
        var artist = $(this).val();
        if(artist.match(/^\d+$/) && tagId == 0) {
            var opt = $("#track-artists option[data-tag='" + escQuote(artist) + "']");
            if(opt.length > 0) {
                getDiskInfo(opt.data("tag"), opt.data("artist"));
                $("#track-title").focus();
            } else
                $(this).addClass('invalid-input');
        }
    }).on('input', function() {
        var artist = $(this).val();
        var opt = $("#track-artists option[value='" + escQuote(artist) + "']");
        if(opt.length > 0) {
            getDiskInfo(opt.data("tag"), opt.data("artist"));
        } else {
            // clear auto-filled album info
            if(tagId > 0) {
                tagId = 0;
                $("#track-title").val("");
                $("#track-title").attr('list',''); // webkit hack
                $("#track-titles").empty();
                $("#track-title").attr('list','track-titles'); // webkit hack
                $("#track-album").val("");
                $("#track-label").val("");
            }

            if(artist.length > 3) {
                // if artist is numeric with correct check digit,
                // treat it as an album tag
                var parseTag = artist.match(/^(\d+)(\d)$/);
                parseTag != null && parseTag[1]
                        .split('').map(Number)
                        .reduce((a, b) => a + b, 0) % 10 == parseTag[2] ?
                    searchTag(artist) : searchLibrary(artist);
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

    $("#track-album, #track-label").on('change', function() {
        tagId = 0;
    });

    $(".playlistTable .grab").mousedown(grabStart);

    // from home.js
    function localTime(date) {
        var hour = date.getHours();
        var ampm = hour >= 12?"pm":"am";
        var m = date.getMinutes();
        var min = m == 0?'':':' + String(m).padStart(2, '0');
        if(hour > 12)
            hour -= 12;
        else if(hour == 0)
            hour = 12;
        return hour + min + ampm;
    }

    $(".zk-popup button#extend").click(function() {
        var showTime = $("#show-time").val().split('-');

        var edate = new Date("2022-01-01T" +
                           showTime[1].substring(0, 2) + ":" +
                           showTime[1].substring(2, 4) + ":00Z");
        edate.setMinutes(edate.getMinutes() + edate.getTimezoneOffset());

        var extend = $("#extend-time").val();
        edate.setMinutes(edate.getMinutes() + extend*1);

        showTime[1] = String(edate.getHours()).padStart(2, '0') +
                      String(edate.getMinutes()).padStart(2, '0');
        showTime = showTime.join('-');

        var playlistId = $("#track-playlist").val();
        var postData = {
            data: {
                type: 'show',
                id: playlistId,
                attributes: {
                    time: showTime
                }
            }
        };

        $.ajax({
            type: 'PATCH',
            url: 'api/v1/playlist/' + playlistId,
            dataType: 'json',
            contentType: "application/json; charset=utf-8",
            accept: "application/json; charset=utf-8",
            data: JSON.stringify(postData),
            success: function(response) {
                $("#show-time").val(showTime);

                var banner = $(".playlistBanner > DIV");
                var prefix = banner.html().split('-')[0];
                banner.html(prefix + " - " + localTime(edate) + "&nbsp;");
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var json = JSON.parse(jqXHR.responseText);
                var status = (json && json.errors)?
                        json.errors[0].title:('There was a problem extending the show time: ' + textStatus);
                showUserError(status);
            }
        });
    });

    $(".zk-popup button").click(function() {
        $(".zk-popup").hide();
        $("*[data-focus]").focus();
    });

    $("div.toggle-time-entry").click(function() {
        var timeEntry = $("#time-entry");
        if(timeEntry.hasClass('zk-hidden'))
            timeEntry.slideDown().removeClass('zk-hidden');
        else {
            $("#error-msg").text('');
            var input = timeEntry.slideUp().addClass('zk-hidden').find('input');
            if(input.hasClass('fxtime'))
                input.fxtime('seg', 1, null).fxtime('seg', 2, 0);
            else
                input.val('');
        }
    });

    function getPending() {
        var highlight = null;
        var now = Date.now() / 1000;

        $(".playlistTable > tbody > tr").each(function() {
            var timestamp = $(this).find(".time").data("utc");
            if(!timestamp)
                highlight = this;
            else if(timestamp < now)
                return false;
        });

        return highlight;
    }

    function timestampTrack(row) {
        var postData = {
            playlist: $("#track-playlist").val(),
            tid: $(row).find(".grab").data("id"),
            oaction: $("#track-action").val(),
            size: $(".playlistTable > tbody > tr").length,
        };

        $.ajax({
            dataType : 'json',
            type: 'POST',
            accept: "application/json; charset=utf-8",
            url: "?action=addTrack",
            data: postData,
            success: function(respObj) {
                // *1 to coerce to int as switch uses strict comparison
                switch(respObj.seq*1) {
                case -1:
                    // playlist is out of sync with table; reload
                    location.href = "?action=" + $("#track-action").val() +
                        "&playlist=" + $("#track-playlist").val();
                    break;
                default:
                    // seq specifies the ordinal of the entry,
                    // where 1 is the first (oldest).
                    //
                    // Calculate the zero-based row index from seq.
                    // Table is ordered latest to oldest, which means
                    // we must reverse the sense of seq.

                    playable.detach();
                    $(row).remove();

                    var rows = $(".playlistTable > tbody > tr");
                    var index = rows.length - respObj.seq + 1;
                    if(index == 0)
                        $(".playlistTable > tbody").prepend(respObj.row);
                    else if(index < rows.length)
                        rows.eq(index).before(respObj.row);
                    else
                        rows.eq(rows.length - 1).after(respObj.row);

                    $(".playlistTable > tbody > tr").eq(index).find(".grab").mousedown(grabStart);

                    updatePlayable();

                    if(respObj.runsover) {
                        $("#extend-show").show();
                        $("#extend-time").focus();
                    }
                    break;
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var json = JSON.parse(jqXHR.responseText);
                var status = (json && json.errors)?
                        json.errors[0].title:('There was a problem retrieving the data: ' + textStatus);
                showUserError(status);
            }
        });
    }

    var playable = null;
    function updatePlayable() {
        if(!$("#track-time").data("live"))
            return;

        var highlight = getPending();
        if(highlight != null) {
            if(playable == null)
                playable = $("<div>", {class: 'play-now'}).append($("<button>").text("play now"));

            $(highlight).find('.time').append(playable);

            // drag loses the event handler in some cases
            // this ensures we always have exactly one handler bound
            playable.off().click(function() {
                timestampTrack(highlight);
            });
        } else if(playable != null) {
            playable.remove();
            playable = null;
        }

        $("#track-play").prop("disabled", !$("#track-add").is(":enabled") || highlight != null);
    }

    updatePlayable();

    $("#track-type-pick").html($("#track-type-pick option").sort(function(a, b) {
        return b.value == 'manual-entry' || a.value != 'manual-entry' &&
            a.text.toLowerCase() > b.text.toLowerCase() ? 1 : -1;
    })).val('manual-entry');

    $("#track-time").fxtime().
        fxtime('val', $("#track-time").data('last-val')).
        fxtime('seg', 1, null).
        fxtime('seg', 2, 0).
        fxtime('blur', function(seg) {
            if(seg == 1) {
                // auto-bump hour if new minute is well less than previous
                var current = $(this).fxtime('val');
                if(current != null) {
                    // user may have already bumped hour; if so, don't do it
                    var last = $(this).data('last-val').split(':');
                    var now = current.split(':');
                    if(last[0] == now[0] && last[1] - now[1] > 30)
                        $(this).fxtime('inc', 0);
                }
            }
        }).keydown(function(e) {
            if(e.which == 0x0d && // Enter key
                    $(this).fxtime('val') &&
                    $('#track-play').is(':enabled')) {
                // focus before click to trigger time validation
                $('#track-play').focus().click();

                if($(this).hasClass('invalid-input'))
                    $(this).focus();
            }
        });

    $("#edit-time").fxtime();
    if($("#edit-type").length > 0) {
        var type = $("#edit-type").val();
        $("#track-type-pick").val(type).trigger('change');

        var created = $("#old-created").val();
        if(created)
            $("#edit-time").fxtime('val', created);
        else
            $("#edit-time").fxtime('val', $("#edit-time").data('last-val'))
                .fxtime('seg', 1, null)
                .fxtime('seg', 2, 0);
    }

    $("*[data-focus]").focus();
});
