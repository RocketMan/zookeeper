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
        $("#track-titles").empty();
        $("#error-msg").text('');
        $("#tag-status").text('');
        $("#nme-entry input").val('');

        if (clearArtistList)
            $("#track-artists").empty();

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

    function getDiskInfo(id, refArtist, refTitle) {
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
                    "' data-index='" + htmlify(i+1) +
                    "' value='" + htmlify((i+1) + ". " + artist + track.track) + "'>";
            }
            $("#track-titles").html(options);
            $("#track-artist").val(diskInfo.attributes.artist);
            $("#track-label").val(diskInfo.relationships != null &&
                                  diskInfo.relationships.label != null ?
                                  diskInfo.relationships.label.meta.name :
                                  "(Unknown)");
            $("#track-album").val(diskInfo.attributes.album);
            $("#track-title").val(refTitle);
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

            if(refTitle)
                setAddButtonState(true);
        }).fail(function (jqXHR, textStatus, errorThrown) {
            var json = JSON.parse(jqXHR.responseText);
            if (json && json.errors) {
                if (json.errors[0].status == 404)
                    status = id + ' is not a valid tag.';
                else
                    status = json.errors[0].title;
            } else
                status = 'Error retrieving the data: ' + textStatus;
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
                getDiskInfo(tag, $("#old-track-artist").val(), $("#old-track-title").val());
            $("#comment-data").val($("#old-comment-data").val()).trigger('input');
            $("#nme-id").val($("#old-event-code").val());

            var haveAll = haveAllUserInput();
            setAddButtonState(haveAll);
        }
    });

    $("#manual-entry input").on('input autocomplete', function() {
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
            $("#markdown-help").slideUp();
            $("#markdown-help-link").text("formatting help");
        } else {
            $("#markdown-help").css('padding-left','80px');
            $("#markdown-help").slideDown();
            $("#markdown-help-link").text("hide help");
        }
    });

    $("#nme-entry input").on('input', function() {
        var haveAll = haveAllUserInput();
        setAddButtonState(haveAll);
    });

    $("#edit-save").click(function(){
        // double check that we have everything.
        if (haveAllUserInput() == false) {
            showUserError('A required field is missing');
            return;
        }

        // don't allow submission of album tag in the artist field
        if($("#track-artist").val().replace(/\s+/g, '').match(/^\d+$/) && tagId == 0) {
            showUserError('Album tag is invalid');
            $("#track-artist").focus();
            return;
        }

        // check that the timestamp, if any, is valid
        if($(".fxtime").is(":invalid")) {
            showUserError('Time is outside show start/end times');
            $(".fxtime").focus();
            return;
        }

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
                    json.errors[0].title:('Error updating the item: ' + textStatus);
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
                    json.errors[0].title:('Error deleting the item: ' + textStatus);
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
            url: "?action=editListMoveTrack",
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
        var spinTime, artist, label, album, track, type, eventType, eventCode, comment;
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
        spinTime =  $("#track-time").fxtime('val');

        var postData = {
            playlist: $("#track-playlist").val(),
            type: type,
            tag: tagId,
            artist: artist,
            label: label,
            time: spinTime,
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
            url: "?action=editListAddTrack&oaction=" + $("#track-action").val(),
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
        if($("#track-artist").val().replace(/\s+/g, '').match(/^\d+$/) && tagId == 0) {
            showUserError('Album tag is invalid');
            $("#track-artist").focus();
            return;
        }

        // check that the timestamp, if any, is valid
        if($(".fxtime").is(":invalid")) {
            showUserError('Time is outside show start/end times');
            $(".fxtime").focus();
            return;
        }

        submitTrack(this.id);
    });

    function getArtist(node) {
        var name = node.artist;
        if(name.substr(0, 8) == '[coll]: ')
            name = 'Various Artists';
        return htmlify(name);
    }

    function addArtists(data, qlist) {
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

        qlist(results.children().map(function() {
            return this.value;
        }));

        $(".ui-menu").scrollTop(0);
    }

    function searchLibrary(key, qlist) {
        var url = "api/v1/album?filter[match(artist)]=" +
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
                               response.data : [], qlist);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var json = JSON.parse(jqXHR.responseText);
                var status = (json && json.errors)?
                        json.errors[0].title:('Error retrieving the data: ' + textStatus);
                showUserError(status);
            }
        });
    }

    function searchTag(id, qlist) {
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
                    addArtists([ response.data ], qlist);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                if(jqXHR.status == 404) {
                    // tag does not exist; silently ignore
                    return;
                }

                var json = JSON.parse(jqXHR.responseText);
                var status = (json && json.errors)?
                        json.errors[0].title:('Error retrieving the data: ' + textStatus);
                showUserError(status);
            }
        });
    }

    $("#track-artist").focusout(function() {
        $("#error-msg").text('');
        $(this).removeClass('invalid-input');
        var artist = $(this).val();
        var scrub = artist.replace(/\s+/g, '');
        if(scrub.match(/^\d+$/) && tagId == 0) {
            var opt = $("#track-artists option[data-tag='" + escQuote(scrub) + "']");
            if(opt.length > 0)
                getDiskInfo(opt.data("tag"), opt.data("artist"));
            else
                $(this).addClass('invalid-input');
        } else {
            var opt = $("#track-artists option[value='" + escQuote(artist) + "']");
            if(opt.length > 0)
                getDiskInfo(opt.data("tag"), opt.data("artist"));
        }
    }).on('input', function() {
        var artist = $(this).val();
        var opt = $("#track-artists option[value='" + escQuote(artist) + "']");
        if(opt.length == 0) {
            $("#track-titles").empty();
            // clear auto-filled album info
            if(tagId > 0) {
                tagId = 0;
                $("#track-title").val("");
                $("#track-album").val("");
                $("#track-label").val("");
            }
        }
    }).on('click', function() {
        $(this).autocomplete('search', this.value);
    }).autocomplete({
        minLength: 3,
        source: function(rq, rs) {
            var artist = rq.term;

            // if artist is numeric with correct check digit,
            // treat it as an album tag
            var parseTag = artist.replace(/\s+/g, '').match(/^(\d+)(\d)$/);
            parseTag != null && parseTag[1]
                    .split('').map(Number)
                    .reduce((a, b) => a + b, 0) % 10 == parseTag[2] ?
                searchTag(parseTag[0], rs) : searchLibrary(artist, rs);
        },
        select: function(event, ui) {
            var artist = ui.item.value;
            var opt = $("#track-artists option[value='" + escQuote(artist) + "']");
            if(opt.length > 0)
                getDiskInfo(opt.data("tag"), opt.data("artist"));
        }
    });

    $("#track-title").on('click', function() {
        $(this).autocomplete('search', '');
    }).on('blur autocomplete', function() {
        var index, title = this.value;
        var opt = $("#track-titles option[value='" + escQuote(title) + "']");

        if(opt.length == 0 && (index = title.trim().match(/^\d+$/)))
            opt = $("#track-titles option[data-index='" + escQuote(index[0]) + "']");

        if(opt.length > 0) {
            var artist = opt.data("artist");
            if(artist)
                $("#track-artist").val(artist);
            $("#track-title").val(opt.data("track"));
        }
    }).autocomplete({
        minLength: 0,
        source: function(rq, rs) {
            var term = rq.term.toLowerCase();
            rs($("#track-titles option").map(function() {
                return this.value;
            }).filter(function() {
                return this.toLowerCase().includes(term);
            }));
        },
        select: function(event, ui) {
            $(this).val(ui.item.value).trigger('autocomplete');
            event.preventDefault();
        },
        open: function() {
            $(".ui-menu").scrollTop(0);
        }
    });

    $("#track-album, #track-label").on('change', function() {
        tagId = 0;
    });

    $(".playlistTable .grab").mousedown(grabStart);

    // from user.apikey.js
    function copyToClipboard(text) {
        var temp = $("<input>");
        $("body").append(temp);
        temp.val(text).select();
        document.execCommand("copy");
        temp.remove();
    }

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
                        json.errors[0].title:('Error extending the show time: ' + textStatus);
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
            input.fxtime('seg', 1, null).fxtime('seg', 2, 0);
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
            url: "?action=editListAddTrack",
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
                        json.errors[0].title:('Error updating the track: ' + textStatus);
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

    $(".fxtime").fxtime()
        .keydown(function(e) {
            if(e.which == 0x0d && // Enter key
                    $(this).fxtime('val') &&
                    $('button.default:visible').is(':enabled')) {
                // focus before click to trigger time validation
                $('button.default:visible').focus().click();

                if(this.matches(":invalid"))
                    this.focus();
            }
        }).on('segblur', function(e) {
            if(e.detail.seg == 1) {
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
        }).on('blur', function() {
            if(this.matches(":valid"))
                $("#error-msg").text("");
        });

    $("#track-time")
        .fxtime('val', $("#track-time").data('last-val'))
        .fxtime('seg', 1, null)
        .fxtime('seg', 2, 0);

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

    // stretch track-play if track-add is hidden
    $("#track-add.zk-hidden").prev().outerWidth($("#track-type-pick").outerWidth());

    $("#copy-link").on('click', function() {
        copyToClipboard($(this).data('link'));
        alert('Playlist URL copied to the clipboard!');
    });

    $("*[data-focus]").focus();
});
