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

/*! Zookeeper Online (C) 1997-2023 Jim Mason <jmason@ibinx.com> | @source: https://zookeeper.ibinx.com/ | @license: magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3.0 */

$().ready(function(){
    const intl = new Date().toLocaleTimeString().match(/am|pm/i) == null;

    const NME_ENTRY='nme-entry';
    const NME_PREFIX=$("#const-prefix").val();
    var seq = 0;
    var row;
    var updating;

    function htmlify(s) {
        return s?String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/\'/g, '&#39;'):"";
    }

    function escQuote(s) {
        return String(s).replace(/\'/g, '\\\'');
    }

    function setAddButtonState(enableIt, parent) {
        var hasPending = $(".track-time", parent).data("live") && getPending() != null;
        $("#track-add", parent).prop("disabled", !enableIt);
        $("#track-play", parent).prop("disabled", !enableIt || hasPending);

        $("#edit-insert", parent).prop("disabled", !enableIt);
        $("#edit-save", parent).prop("disabled", !enableIt);
    }

    function clearUserInput(clearArtistList, parent) {
        $(".manual-entry input", parent).removeClass('invalid-input').val('');
        $(".comment-entry textarea", parent).val('');
        $(".track-titles", parent).empty();
        $(".error-msg", parent).text('');
        $(".tag-status", parent).text('');
        $(".nme-entry input", parent).val('');

        if (clearArtistList)
            $(".track-artists", parent).empty();

        $(".track-album", parent).data('tagId', 0);

        setAddButtonState(false, parent);

        var mode = $(".track-type-pick", parent).val();
        switch(mode) {
        case 'manual-entry':
            $('.track-artist', parent).trigger('focus');
            break;
        case 'comment-entry':
            $(".remaining", parent).html("(0/" + $("#comment-max").val() + " characters)");
            $('.comment-data', parent).trigger('focus');
            break;
        case 'set-separator':
            setAddButtonState(true, parent);
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

    function getEntryMode(parent)  {
        var entryMode = $(".track-type-pick", parent).val();
        if (isNmeType(entryMode))
            entryMode = NME_ENTRY;

        return entryMode;
    }

    // return true if have all required fields.
    function haveAllUserInput(parent)  {
        var isEmpty = false;
        var entryMode = getEntryMode(parent);

        if (entryMode == 'manual-entry') {
            $(".manual-entry input[required]", parent).each(function() {
                isEmpty = isEmpty || $(this).val().length == 0;
            });
        } else if (entryMode == 'comment-entry') {
            isEmpty = $('.comment-data', parent).val().length == 0;
        } else if (entryMode == NME_ENTRY) {
            isEmpty = $('.nme-id', parent).val().length == 0;
        }

        return !isEmpty;
    }

    function getErrorMessage(jqXHR, defaultValue) {
        if(jqXHR.status == 403)
            return 'Server busy, try again...';
        else if(jqXHR.status == 401 &&
                confirm('Your session is no longer active.  Select OK to sign in again and continue.')) {
            location.href = '?target=sso&location=' + encodeURIComponent(location.href);
            return;
        }

        try {
            var json = JSON.parse(jqXHR.responseText);
            if (json && json.errors)
                return json.errors.map(error => error.title).join(', ');
        } catch(e) {}

        return defaultValue;
    }

    function showUserError(msg, parent) {
        $(".error-msg", parent).text(msg);
    }

    function setUpdating() {
        updating = true;
        $('#in-progress').css('background-color', 'transparent').show();
        $('body').addClass('waiting');
    }

    function clearUpdating(jqXHR, textStatus) {
        $('body').removeClass('waiting');
        $('#in-progress').hide();
        updating = false;
    }

    function getDiskInfo(id, refArtist, parent, refTitle) {
        clearUserInput(false, parent);

        if (id < 0) {
            // need to do this asynchronously for autocomplete
            setTimeout(function() {
                var entry = parent.data('entries')[ -id - 1 ];
                $(".track-artist", parent).val(entry.artist);
                $(".track-label", parent).val(entry.label);
                $(".track-album", parent).data('tagId', 0).val(entry.album);
                $(".track-submit", parent).attr("disabled");
                $(".track-submit", parent).prop("disabled", true);
            }, 0);
            return;
        }

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
            $(".track-album", parent).data('tagId', id);
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
            $(".track-titles", parent).html(options);
            $(".track-artist", parent).val(diskInfo.attributes.artist);
            $(".track-label", parent).val(diskInfo.relationships != null &&
                                  diskInfo.relationships.label != null ?
                                  diskInfo.relationships.label.meta.name :
                                  "(Unknown)");
            $(".track-album", parent).val(diskInfo.attributes.album);
            $(".track-title", parent).val(refTitle);
            $(".track-submit", parent).attr("disabled");
            $(".track-submit", parent).prop("disabled", true);
            if(refArtist) {
                var tracks = $(".track-titles option[data-artist='" +
                               escQuote(refArtist) + "']", parent);
                // for a compilation...
                if(tracks.length > 0) {
                    // ...remove all artists but this one
                    $(".track-titles option", parent).not("[data-artist='" +
                                                  escQuote(refArtist) +
                                                  "']").remove();
                    // ...prefill the artist
                    $(".track-artist", parent).val(refArtist);
                    // ...if only one track by this artist, select it
                    if(tracks.length == 1) {
                        $(".track-title", parent).val(tracks.data("track"));
                        setAddButtonState(true, parent);
                    }
                }
            }

            if(refTitle)
                setAddButtonState(true, parent);
        }).fail(function (jqXHR, textStatus, errorThrown) {
            var message = jqXHR.status == 404 ?
                id + ' is not a valid tag.' :
                getErrorMessage(jqXHR,
                        'Error retrieving the data: ' + errorThrown);
            showUserError(message, parent);
        });
    }

    $(".track-type-pick").on('change', function() {
        // display the user entry div for this type
        var parent = $(this).closest('div.form-entry');
        var newType = getEntryMode(parent);
        clearUserInput(true, parent);
        $(".track-entry > div", parent).addClass("zk-hidden");
        $(`.${newType}`, parent).removeClass("zk-hidden");
        $(`.${newType} *[data-focus]`, parent).trigger('focus');

        var event;
        if(parent.hasClass('pl-inline-edit') && row
                && (event = row.data('event'))) {
            ['artist','album','label','title'].forEach(function(field) {
                var attr = field == 'title' ? 'track' : field;
                $(".track-" + field, parent).val(event.attributes[attr]);
            });
            var tag = event.relationships?.album?.data.id ?? 0;
            if(tag)
                getDiskInfo(tag, event.attributes.artist, parent, event.attributes.track);
            $(".comment-data", parent).val(event.attributes.comment).trigger('input');
            $(".nme-id", parent).val(event.attributes.code);

            var haveAll = haveAllUserInput(parent);
            setAddButtonState(haveAll, parent);
        }

        if (newType == NME_ENTRY) {
            var option = $(".pl-add-track .track-type-pick option[value='" + $(this).val() + "']");
            var argCnt = option.data("args");
            // insert default value if no user entry required.
            if (argCnt == 0 && $(".nme-id", parent).val().length == 0) {
                $(".nme-id", parent).val(option.text());
                setAddButtonState(true, parent);
            }
        }
    });

    $('.manual-entry input').on('input autocomplete', function() {
        var parent = $(this).closest('div.form-entry');
        var haveAll = haveAllUserInput(parent);
        setAddButtonState(haveAll, parent);
    });

    $('.comment-entry textarea').on('input', function() {
        var parent = $(this).closest('div.form-entry');
        var len = this.value.length;
        $(".remaining", parent).html("(" + len + "/" + $("#comment-max").val() + " characters)");
        setAddButtonState(len > 0, parent);
    });

    $(".markdown-help-link").on('click', function() {
        var parent = $(this).closest('div.form-entry');
        if($(".markdown-help", parent).is(":visible")) {
            $(".markdown-help", parent).slideUp();
            $(".markdown-help-link", parent).text("formatting help");
        } else {
            $(".markdown-help", parent).css('padding-left','80px');
            $(".markdown-help", parent).slideDown();
            $(".markdown-help-link", parent).text("hide help");
        }
    });

    $('.nme-entry input').on('input', function() {
        var parent = $(this).closest('div.form-entry');
        var haveAll = haveAllUserInput(parent);
        setAddButtonState(haveAll, parent);
    });

    function addActions(target) {
        $(".grab", target).on('pointerdown', grabStart);
        $(".pl-stack a", target).on('click', showActions);
        $(".songInsert", target).on('click', inlineInsert);
        $(".songEdit", target).on('click', inlineEdit);
        $(".songDelete", target).on('click', inlineDelete);
        $(".pl-stack", target).on('click dblclick', function(e) {
            e.stopPropagation();
        });
    }

    $("#edit-insert").on('click', function(){
        if (updating) return;

        // double check that we have everything.
        var parent = $(this).closest('div.form-entry');
        if (haveAllUserInput(parent) == false) {
            showUserError('A required field is missing', parent);
            return;
        }

        // don't allow submission of album tag in the artist field
        if($(".track-artist", parent).val().replace(/\s+/g, '').match(/^\d+$/) && !$(".track-album", parent).data('tagId')) {
            showUserError('Album tag is invalid', parent);
            $(".track-artist", parent).trigger('focus');
            return;
        }

        // check that the timestamp, if any, is valid
        if($(".fxtime", parent).is(":invalid")) {
            showUserError('Time is outside show start/end times', parent);
            $(".fxtime", parent).trigger('focus');
            return;
        }

        var itemType, comment, eventType, eventCode;
        var artist, label, album, track;
        var trackType = $(".track-type-pick", parent).val();
        switch(trackType) {
        case 'manual-entry':
            itemType = 'spin';
            artist = $(".track-artist", parent).val();
            label =  $(".track-label", parent).val();
            album =  $(".track-album", parent).val();
            track =  $(".track-title", parent).val();
            break;
        case 'comment-entry':
            itemType = 'comment';
            comment = $(".comment-data", parent).val();
            break;
        case 'set-separator':
            itemType = 'break';
            break;
        default:
            itemType = 'logEvent';
            eventType = getEventType(trackType);
            eventCode = $(".nme-id", parent).val();
            break;
        }

        var playlistId = $('#track-playlist').val();
        var postData = {
            data: {
                type: 'event',
                meta: {
                    wantMeta: true,
                    moveTo: row.find(".songManager .grab").data('id'),
                    hash: $("#track-hash").val(),
                },
                attributes: {
                    type: itemType,
                    artist: artist,
                    album: album,
                    track: track,
                    label: label,
                    comment: comment,
                    event: eventType,
                    code: eventCode,
                    created: $(".track-time", parent).fxtime('val')
                }
            }
        };

        var tagId = $(".track-album", parent).data('tagId');
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

        setUpdating();
        $.ajax({
            type: 'POST',
            url: 'api/v1.1/playlist/' + playlistId + '/events',
            dataType: 'json',
            contentType: "application/json; charset=utf-8",
            accept: "application/json; charset=utf-8",
            data: JSON.stringify(postData),
            complete: clearUpdating,
            success: function(response) {
                var meta = response.data.meta;
                switch(meta.seq*1) {
                case -1:
                    // playlist is out of sync with table; reload
                    location.href = "?subaction=" + $("#track-action").val() +
                        "&playlist=" + playlistId;
                    break;
                default:
                    // hide the inline edit
                    var overlay = $(".pl-inline-edit");
                    var dummy = overlay.closest("tr");
                    overlay.hide().insertAfter($("#extend-show"));
                    dummy.remove();

                    // seq specifies the ordinal of the entry,
                    // where 1 is the first (oldest).
                    //
                    // Calculate the zero-based row index from seq.
                    // Table is ordered latest to oldest, which means
                    // we must reverse the sense of seq.
                    var rows = $(".playlistTable > tbody > tr");
                    var index = rows.length - meta.seq;
                    rows.eq(index++).after(meta.html);

                    var target = $(".playlistTable > tbody > tr").eq(index);
                    addActions(target);

                    $("#track-hash").val(meta.hash);

                    row = null;

                    updatePlayable();
                    break;
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var message = getErrorMessage(jqXHR,
                              'Error updating the item: ' + errorThrown);
                showUserError(message, parent);
            }
        });
    });

    $("#edit-save").on('click', function(){
        if (updating) return;

        // double check that we have everything.
        var parent = $(this).closest('div.form-entry');
        if (haveAllUserInput(parent) == false) {
            showUserError('A required field is missing', parent);
            return;
        }

        // don't allow submission of album tag in the artist field
        if($(".track-artist", parent).val().replace(/\s+/g, '').match(/^\d+$/) && !$(".track-album", parent).data('tagId')) {
            showUserError('Album tag is invalid', parent);
            $(".track-artist", parent).trigger('focus');
            return;
        }

        // check that the timestamp, if any, is valid
        if($(".fxtime", parent).is(":invalid")) {
            showUserError('Time is outside show start/end times', parent);
            $(".fxtime", parent).trigger('focus');
            return;
        }

        var itemType, comment, eventType, eventCode;
        var artist, label, album, track;
        var trackType = $(".track-type-pick", parent).val();
        switch(trackType) {
        case 'manual-entry':
            itemType = 'spin';
            artist = $(".track-artist", parent).val();
            label =  $(".track-label", parent).val();
            album =  $(".track-album", parent).val();
            track =  $(".track-title", parent).val();
            break;
        case 'comment-entry':
            itemType = 'comment';
            comment = $(".comment-data", parent).val();
            break;
        case 'set-separator':
            itemType = 'break';
            break;
        default:
            itemType = 'logEvent';
            eventType = getEventType(trackType);
            eventCode = $(".nme-id", parent).val();
            break;
        }

        var playlistId = $('#track-playlist').val();
        var postData = {
            data: {
                type: 'event',
                id: row.find(".songManager .grab").data('id'),
                meta: {
                    wantMeta: true,
                    hash: $("#track-hash").val(),
                },
                attributes: {
                    type: itemType,
                    artist: artist,
                    album: album,
                    track: track,
                    label: label,
                    comment: comment,
                    event: eventType,
                    code: eventCode,
                    created: $(".track-time", parent).fxtime('val') ?? 'clear'
                }
            }
        };

        var tagId = $(".track-album", parent).data('tagId');
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

        setUpdating();
        $.ajax({
            type: 'PATCH',
            url: 'api/v1/playlist/' + playlistId + '/events',
            dataType: 'json',
            contentType: "application/json; charset=utf-8",
            accept: "application/json; charset=utf-8",
            data: JSON.stringify(postData),
            complete: clearUpdating,
            success: function(response) {
                var meta = response.data.meta;
                switch(meta.seq*1) {
                case -1:
                    // playlist is out of sync with table; reload
                    location.href = "?subaction=" + $("#track-action").val() +
                        "&playlist=" + playlistId;
                    break;
                default:
                    // hide the inline edit
                    var overlay = $(".pl-inline-edit");
                    var dummy = overlay.closest("tr");
                    overlay.hide().insertAfter($("#extend-show"));
                    dummy.remove();

                    // seq specifies the ordinal of the entry,
                    // where 1 is the first (oldest).
                    //
                    // Calculate the zero-based row index from seq.
                    // Table is ordered latest to oldest, which means
                    // we must reverse the sense of seq.
                    var rows = $(".playlistTable > tbody > tr");
                    var index = rows.length - meta.seq;
                    if(index == row.index()) {
                        row.replaceWith(meta.html);
                    } else if(index < row.index()) {
                        row.remove();
                        rows.eq(index).before(meta.html);
                    } else {
                        row.remove();
                        rows.eq(index).after(meta.html);
                    }

                    var target = $(".playlistTable > tbody > tr").eq(index);
                    addActions(target);

                    $("#track-hash").val(meta.hash);

                    row = null;

                    updatePlayable();
                    break;
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var message = getErrorMessage(jqXHR,
                              'Error updating the item: ' + errorThrown);
                showUserError(message, parent);
            }
        });
    });

    function inlineDelete() {
        if (updating) return;

        $(".error-msg").text("");

        if(!confirm("Delete this item?")) {
            closeInlineEdit(undefined, true);
            return;
        }

        var playlistId = $('#track-playlist').val();
        var postData = {
            data: {
                type: 'event',
                id: row.find(".songManager .grab").data('id'),
                meta: {
                    wantMeta: true,
                    hash: $("#track-hash").val(),
                }
            }
        };

        setUpdating();
        $.ajax({
            type: 'DELETE',
            url: 'api/v1/playlist/' + playlistId + '/events',
            dataType: 'json',
            contentType: "application/json; charset=utf-8",
            accept: "application/json; charset=utf-8",
            data: JSON.stringify(postData),
            complete: clearUpdating,
            success: function(response) {
                var meta = response.data.meta;
                var hash = meta.hash;
                if (!hash) {
                    // playlist is out of sync with table; reload
                    location.href = "?subaction=" + $("#track-action").val() +
                        "&playlist=" + playlistId;
                    return;
                }
                $("#track-hash").val(hash);

                // hide the inline edit
                var overlay = $(".pl-inline-edit");
                var dummy = overlay.closest("tr");
                overlay.hide().insertAfter($("#extend-show"));
                dummy.remove();

                // delete the row
                row.remove();
                row = null;

                $(".pl-stack-content").hide();
                updatePlayable();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var message = getErrorMessage(jqXHR,
                              'Error deleting the item: ' + errorThrown);
                showUserError(message, $('.pl-add-track'));
            }
        });
    }

    function moveTrack(list, fromId, toId, tr, si, rows) {
        var postData = {
            data: {
                type: 'event',
                id: fromId,
                meta: {
                    wantMeta: true,
                    hash: $("#track-hash").val(),
                    moveTo: toId
                }
            }
        };

        setUpdating();
        $.ajax({
            type: "PATCH",
            url: "api/v1/playlist/" + list + "/events",
            dataType : "json",
            accept: "application/json; charset=utf-8",
            data: JSON.stringify(postData),
            complete: clearUpdating,
            success: function(respObj) {
                var meta = respObj.data.meta;
                if(meta.seq == -1) {
                    // playlist is out of sync with table; reload
                    location.href = "?subaction=" + $("#track-action").val() +
                        "&playlist=" + $("#track-playlist").val();
                }

                // move succeeded, clear timestamp
                tr.find("td").eq(1).data('utc','').html('');
                $(".error-msg").text("");
                $("#track-hash").val(meta.hash);
                updatePlayable();
            },
            error: function (jqXHR, textStatus, errorThrown) {
                // move failed; restore original sequence
                if(tr.index() < si)
                    rows.eq(si+1).after(tr);
                else
                    rows.eq(si).after(tr);

                var status = getErrorMessage(jqXHR, errorThrown);
                showUserError("Error moving track: " + status, $('div.pl-add-track'));
            }
        });
    }

    // grabStart DnD code adapted from NateS
    //
    // Reference: https://stackoverflow.com/questions/2072848/reorder-html-table-rows-using-drag-and-drop/42720364
    function grabStart(e) {
        e.preventDefault();

        // disable draghandle longpress on mobile
        $(this).off('contextmenu').on('contextmenu', function(e) {
            e.preventDefault();
        });

        if (row || updating) return; // disable resequencing during inline edit

        var tr = $(e.target).closest("TR"), si = tr.index(), sy = e.pageY, b = $(document.body), drag;
        if (b.hasClass("grabCursor")) return; // already dragging
        window.getSelection().empty();
        b.addClass("grabCursor no-text-select");
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
            $(document).off("pointermove", move).off("pointerup", up);
            b.removeClass("grabCursor no-text-select");
            tr.removeClass("grabbed");
        }
        $(document).on('pointermove', move).on('pointerup', up);
    }

    $(".track-submit").on('click', function(e) {
        if (updating) return;

        // double check that we have everything.
        var parent = $(this).closest('div.form-entry');
        if (haveAllUserInput(parent) == false) {
            showUserError('A required field is missing', parent);
            return;
        }

        // don't allow submission of album tag in the artist field
        if($(".track-artist", parent).val().replace(/\s+/g, '').match(/^\d+$/) && !$(".track-album", parent).data('tagId')) {
            showUserError('Album tag is invalid', parent);
            $(".track-artist", parent).trigger('focus');
            return;
        }

        // check that the timestamp, if any, is valid
        if($(".fxtime", parent).is(":invalid")) {
            showUserError('Time is outside show start/end times', parent);
            $(".fxtime", parent).trigger('focus');
            return;
        }

        var itemType, comment, eventType, eventCode;
        var artist, label, album, track, spinTime;
        var trackType =  $(".track-type-pick", parent).val();

        if (trackType == 'set-separator') {
            itemType = 'break';
        } else if (isNmeType(trackType)) {
            itemType = 'logEvent';
            eventType = getEventType(trackType);
            eventCode = $(".nme-id", parent).val();
        } else if (trackType == 'comment-entry') {
            itemType = 'comment';
            comment = $(".comment-data", parent).val();
        } else {
            itemType = 'spin';
            artist = $(".track-artist", parent).val();
            label =  $(".track-label", parent).val();
            album =  $(".track-album", parent).val();
            track =  $(".track-title", parent).val();
        }
        spinTime =  $(".track-time", parent).fxtime('val');

        if(!spinTime && this.id == 'track-play')
            spinTime = 'auto';

        var postData = {
            data: {
                type: 'event',
                attributes: {
                    type: itemType,
                    artist: artist,
                    album: album,
                    track: track,
                    label: label,
                    comment: comment,
                    event: eventType,
                    code: eventCode,
                    created: spinTime
                },
                meta: {
                    wantMeta: true,
                    action: $("#track-action").val(),
                    hash: $("#track-hash").val()
                }
            }
        };

        var tagId = $(".track-album", parent).data('tagId');
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

        var playlistId = $("#track-playlist").val();
        setUpdating();
        $.ajax({
            type: "POST",
            // use API v1.1 or later for correct auto timestamp semantics
            url: "api/v1.1/playlist/" + playlistId + "/events",
            dataType: 'json',
            contentType: "application/json; charset=utf-8",
            accept: "application/json; charset=utf-8",
            data: JSON.stringify(postData),
            complete: clearUpdating,
            success: function(respObj) {
                var meta = respObj.data.meta;
                // *1 to coerce to int as switch uses strict comparison
                switch(meta.seq*1) {
                case -1:
                    // playlist is out of sync with table; reload
                    location.href = "?subaction=" + $("#track-action").val() +
                        "&playlist=" + $("#track-playlist").val();
                    break;
                case 0:
                    // playlist is in natural order; prepend
                    $(".playlistTable > tbody").prepend(meta.html);
                    var target = $(".playlistTable > tbody > tr").eq(0);
                    addActions(target);
                    break;
                default:
                    // close the inline edit, if any
                    if (row)
                        closeInlineEdit(undefined, true);

                    // seq specifies the ordinal of the entry,
                    // where 1 is the first (oldest).
                    //
                    // Calculate the zero-based row index from seq.
                    // Table is ordered latest to oldest, which means
                    // we must reverse the sense of seq.
                    var rows = $(".playlistTable > tbody > tr");
                    var index = rows.length - meta.seq + 1;
                    if(index < rows.length)
                        rows.eq(index).before(meta.html);
                    else
                        rows.eq(rows.length - 1).after(meta.html);
                    var target = $(".playlistTable > tbody > tr").eq(index);
                    addActions(target);
                    break;
                }

                $("#track-hash").val(meta.hash);

                updatePlayable();
                clearUserInput(true, parent);
                $(".track-time", parent).fxtime('seg', 1, null).fxtime('seg', 2, 0);
                if(spinTime != null)
                    $(".track-time", parent).data('last-val', spinTime);

                $('.track-type-pick', parent).val('manual-entry').trigger('change');

                if(meta.runsover) {
                    $("#extend-show").show();
                    $("#extend-time").trigger('focus');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                var message = getErrorMessage(jqXHR,
                              'Your track was not saved: ' + errorThrown);
                showUserError(message, parent);
            }
        });
    });

    function getArtist(node) {
        var name = node.artist;
        if(name.substr(0, 8) == '[coll]: ')
            name = 'Various Artists';
        return htmlify(name);
    }

    function addArtists(data, qlist, parent, key) {
        var results = $(".track-artists", parent);
        results.empty();
        if (key) {
            var entries = searchPlaylistArtists(key);
            parent.data('entries', entries);
            entries.forEach(function(attrs, index) {
                var row = htmlify(attrs.artist) + " - " + htmlify(attrs.album);
                results.append("<option data-tag='" + (-index - 1) +
                               "' data-artist='" + htmlify(attrs.artist) +
                               "' value='" + row + "'>");
            });
        }
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

    function searchLibrary(key, qlist, parent) {
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
                               response.data : [], qlist, parent, key);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // if not last request or rate limited, silently ignore
                if(this.seq != seq || jqXHR.status == 403)
                    return;

                var message = getErrorMessage(jqXHR,
                              'Error retrieving the data: ' + errorThrown);
                showUserError(message, parent);
            }
        });
    }

    function searchTag(id, qlist, parent) {
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
                    addArtists([ response.data ], qlist, parent);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // if not last request, rate limited, or tag does not exist, silently ignore
                if(this.seq != seq || [403,404].includes(jqXHR.status))
                    return;

                var message = getErrorMessage(jqXHR,
                              'Error retrieving the data: ' + errorThrown);
                showUserError(message, parent);
            }
        });
    }

    // simple RegExp.escape polyfill for pre-Baseline 2025
    if (!RegExp.escape) {
        RegExp.escape = function(str) {
            return String(str).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        };
    }

    function searchPlaylistArtists(artist) {
        var preg = new RegExp('\\b' + RegExp.escape(artist), 'i');

        var spins = $(".playlistTable tr.songRow").filter(function() {
            // exclude library albums, as these are included by searchLibrary
            return $(this).find("td > a.nav").length === 0;
        }).map(function() {
            var row = $(this);
            var albumLabel = row.find("td:nth-child(6)").text().split(' / ');
            return {
                artist: row.find("td:nth-child(3)").text(),
                album: albumLabel[0],
                label: albumLabel[1] ?? ''
            };
        }).get().filter(spin => preg.test(spin.artist) && spin.album);

        // deduplicate and sort
        var seen = new Set();
        return spins.filter(spin => {
            spin.key = spin.artist.toLowerCase() + '|' + spin.album.toLowerCase();
            return !seen.has(spin.key) && seen.add(spin.key);
        }).sort((a, b) => a.key.localeCompare(b.key));
    }

    $(".track-artist").on('focusout', function() {
        var parent = $(this).closest('div.form-entry');
        var artist = $(this).val();
        var scrub = artist.replace(/\s+/g, '');
        $(this).removeClass('invalid-input');
        $(".error-msg", parent).text('');
        if(scrub.match(/^\d+$/) && !$(".track-album", parent).data('tagId')) {
            var opt = $(".track-artists option[data-tag='" + escQuote(scrub) + "']", parent);
            if(opt.length > 0)
                getDiskInfo(opt.data("tag"), opt.data("artist"), parent);
            else
                $(this).addClass('invalid-input');
        } else {
            var opt = $(".track-artists option[value='" + escQuote(artist) + "']", parent);
            if(opt.length > 0)
                getDiskInfo(opt.data("tag"), opt.data("artist"), parent);
        }
    }).on('input', function() {
        var artist = $(this).val();
        var parent = $(this).closest('div.form-entry');
        var opt = $(".track-artists option[value='" + escQuote(artist) + "']", parent);
        if(opt.length == 0) {
            $(".track-titles", parent).empty();
            // clear auto-filled album info
            if($(".track-album", parent).data('tagId')) {
                $(".track-title", parent).val("");
                $(".track-album", parent).data('tagId', 0).val("");
                $(".track-label", parent).val("");
            }
        }
    }).on('click', function() {
        $(this).autocomplete('search', this.value);
    }).autocomplete({
        minLength: 1,
        delay: 400,
        source: function(rq, rs) {
            var artist = rq.term;
            var parent = $(this.element).closest('div.form-entry');

            // if artist is numeric with correct check digit,
            // treat it as an album tag
            var parseTag = artist.replace(/\s+/g, '').match(/^(\d+)(\d)$/);
            parseTag != null && parseTag[1]
                    .split('').map(Number)
                    .reduce((a, b) => a + b, 0) % 10 == parseTag[2] ?
                searchTag(parseTag[0], rs, parent) : searchLibrary(artist, rs, parent);
        },
        select: function(event, ui) {
            var artist = ui.item.value;
            var parent = $(this).closest('div.form-entry');
            var opt = $(".track-artists option[value='" + escQuote(artist) + "']", parent);
            if(opt.length > 0)
                getDiskInfo(opt.data("tag"), opt.data("artist"), parent);
        }
    });

    $(".track-title").on('click', function() {
        $(this).autocomplete('search', '');
    }).on('blur autocomplete', function() {
        var index, title = this.value;
        var parent = $(this).closest('div.form-entry');
        var opt = $(".track-titles option[value='" + escQuote(title) + "']", parent);

        if(opt.length == 0 && (index = title.trim().match(/^\d+$/)))
            opt = $(".track-titles option[data-index='" + escQuote(index[0]) + "']", parent);

        if(opt.length > 0) {
            var artist = opt.data("artist");
            if(artist)
                $(".track-artist", parent).val(artist);
            $(".track-title", parent).val(opt.data("track"));
        }
    }).autocomplete({
        minLength: 0,
        source: function(rq, rs) {
            var parent = $(this.element).closest('div.form-entry');
            var term = rq.term.toLowerCase();
            rs($(".track-titles option", parent).map(function() {
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

    $(".track-album, .track-label").on('change', function() {
        var parent = $(this).closest('div.form-entry');
        $(".track-album", parent).data('tagId', 0);
        $(".track-titles", parent).empty();
    });

    /**
     * from playlists.pick.js
     *
     * @param time string formatted 'hhmm'
     */
    function localTimeIntl(time) {
        var stime = String(time);
        return stime.length ? stime.match(/\d{2}/g).join(':') : null;
    }

    /**
     * @param date Date containing the time, or a time string formatted 'hhmm'
     */
    function localTime(date) {
        var hour, m;
        if(date instanceof Date) {
            hour = date.getHours();
            m = date.getMinutes();

            date = String(hour).padStart(2, '0') + String(m).padStart(2, '0');
        } else {
            hour = date.substring(0, 2);
            m = date.substring(2);
        }

        if(intl)
            return date;

        var ampm = hour >= 12?"pm":"am";
        var min = m == 0?'':':' + String(m).padStart(2, '0');
        if(hour > 12)
            hour -= 12;
        else if(hour == 0)
            hour = 12;
        return hour + min + ampm;
    }

    $(".zk-popup button#extend").on('click', function() {
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

                var banner = $("#banner-date-time");
                var prefix = banner.html().split(' - ')[0];
                banner.html(prefix + ' - ' + localTime(edate));
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var message = getErrorMessage(jqXHR,
                              'Error extending the show time: ' + errorThrown);
                showUserError(message, $('div.pl-add-track'));
            }
        });
    });

    $(".zk-popup button").on('click', function() {
        $(".zk-popup").hide();
        $("*[data-focus]").trigger('focus');
    });

    $("div.toggle-time-entry").on('click', function() {
        var timeEntry = $("#time-entry");
        if(timeEntry.hasClass('zk-hidden'))
            timeEntry.slideDown().removeClass('zk-hidden');
        else {
            $(".pl-add-track .error-msg").text('');
            var input = timeEntry.slideUp().addClass('zk-hidden').find('input');
            input.fxtime('seg', 1, null).fxtime('seg', 2, 0);
        }
    });

    function getPending() {
        var highlight = null;
        var now = Date.now() / 1000;

        $(".playlistTable > tbody > tr:not(.dummy)").each(function() {
            var timestamp = $(this).find(".time").data("utc");
            if(!timestamp)
                highlight = this;
            else if(timestamp < now)
                return false;
        });

        return highlight;
    }

    function timestampTrack(row) {
        if (updating) return;

        var postData = {
            data: {
                type: 'event',
                id: $(row).find(".grab").data("id"),
                attributes: {
                    created: 'auto'
                },
                meta: {
                    wantMeta: true,
                    action: $("#track-action").val(),
                    hash: $("#track-hash").val()
                }
            }
        };

        var playlistId = $("#track-playlist").val();
        setUpdating();
        $.ajax({
            dataType : 'json',
            type: 'PATCH',
            contentType: "application/json; charset=utf-8",
            accept: "application/json; charset=utf-8",
            url: "api/v1/playlist/" + playlistId + "/events",
            data: JSON.stringify(postData),
            complete: clearUpdating,
            success: function(respObj) {
                var meta = respObj.data.meta;
                // *1 to coerce to int as switch uses strict comparison
                switch(meta.seq*1) {
                case -1:
                    // playlist is out of sync with table; reload
                    location.href = "?subaction=" + $("#track-action").val() +
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
                    var index = rows.length - meta.seq + 1;
                    if(index == 0)
                        $(".playlistTable > tbody").prepend(meta.html);
                    else if(index < rows.length)
                        rows.eq(index).before(meta.html);
                    else
                        rows.eq(rows.length - 1).after(meta.html);

                    var target = $(".playlistTable > tbody > tr").eq(index);
                    addActions(target);

                    $("#track-hash").val(meta.hash);

                    updatePlayable();

                    if(meta.runsover) {
                        $("#extend-show").show();
                        $("#extend-time").trigger('focus');
                    }
                    break;
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var message = getErrorMessage(jqXHR,
                              'Error updating the track: ' + errorThrown);
                showUserError(message, row.closest('div.form-entry'));
            }
        });
    }

    var playable = null;
    function updatePlayable() {
        if(!$(".pl-add-track .track-time").data("live"))
            return;

        var highlight = getPending();
        if(highlight != null) {
            if(playable == null)
                playable = $("<div>", {class: 'play-now'}).append($("<button>").text("play now"));

            $(highlight).find('.time').append(playable);

            // drag loses the event handler in some cases
            // this ensures we always have exactly one handler bound
            playable.off().on('click', function() {
                timestampTrack(highlight);
            });

            if(row)
                closeInlineEdit(undefined, true);
        } else if(playable != null) {
            playable.remove();
            playable = null;
        }

        $("#track-play").prop("disabled", !$("#track-add").is(":enabled") || highlight != null);
    }

    updatePlayable();

    $('.track-type-pick').html($('.pl-add-track .track-type-pick option').sort(function(a, b) {
        return b.value == 'manual-entry' || a.value != 'manual-entry' &&
            a.text.toLowerCase() > b.text.toLowerCase() ? 1 : -1;
    })).val('manual-entry').trigger('change');

    $(".fxtime").fxtime()
        .on('keydown', function(e) {
            if(e.which == 0x0d && // Enter key
                    $(this).fxtime('val') &&
                    $('button.default:visible').is(':enabled')) {
                // focus before click to trigger time validation
                $('button.default:visible', $(this).closest('.pl-form-entry')).trigger('focus').trigger('click');

                if(this.matches(":invalid"))
                    this.trigger('focus');
            }
        }).on('segblur', function(e) {
            if(e.detail.seg == 1 && $(this).closest('.pl-add-track').length) {
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
                $(this).closest('pl-form-entry').find('.error-msg').text('');
        });

    $(".fxtime")
        .fxtime('val', $(".pl-add-track .track-time").data('last-val'))
        .fxtime('seg', 1, null)
        .fxtime('seg', 2, 0);

    function closeInlineEdit(event, fast = false) {
        $(".pl-stack-content").hide();

        var overlay = $(".pl-inline-edit");
        var dummy = overlay.closest("tr");
        if (fast) {
            overlay.hide().insertAfter($("#extend-show"));
            dummy.remove();
            row.off().removeClass('selected').data('event', null);
            row = null;
        } else {
            overlay.slideUp('fast', function() {
                overlay.insertAfter($("#extend-show"));
                dummy.remove();
                row.off().removeClass('selected').data('event', null);
                row = null;
            });
        }
    }

    function inlineEdit(event) {
        event.preventDefault();

        $(".error-msg").text("");

        $(".pl-stack-content").hide();

        var url = "api/v2/playlist/" + $("#track-playlist").val() +
            "/events?filter[event.id]=" + row.find(".songManager .grab").data('id');
        $.ajax({
            dataType : 'json',
            type: 'GET',
            accept: "application/json; charset=utf-8",
            url: url,
            success: function (response) {
                var event = response.data[0];
                row.data('event', event);

                var type;
                switch(event.attributes.type) {
                case 'comment':
                    type = 'comment-entry';
                    break;
                case 'break':
                    type = 'set-separator';
                    break;
                case 'logEvent':
                    type = NME_PREFIX + event.attributes.event;
                    break;
                default: // spin
                    type = 'manual-entry';
                    break;
                }

                var overlay = $(".pl-inline-edit");
                var dummy = $("<tr class='dummy'><td colspan=6></td></tr>");
                dummy.find("td").append(overlay);
                dummy.insertAfter(row);

                $('#edit-insert').hide();
                $('#edit-save').show();

                overlay.slideDown();

                $(".track-type-pick", overlay).val(type).trigger('change');
                if(event.attributes.created)
                    $(".track-time", overlay).fxtime('val', event.attributes.created);
                else
                    $(".track-time", overlay)
                        .fxtime('seg', 0, null)
                        .fxtime('seg', 1, null)
                        .fxtime('seg', 2, 0);

                var haveAll = haveAllUserInput(overlay);
                setAddButtonState(haveAll, overlay);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                row.off().removeClass('selected');
                row = null;

                var message = getErrorMessage(jqXHR,
                              'Error updating the item: ' + errorThrown);
                showUserError(message, $(".pl-add-track"));
            }
        });
    };

    function inlineInsert(event) {
        event.preventDefault();

        $(".error-msg").text("");

        row.off().removeClass('selected');
        $(".pl-stack-content").hide();

        // look back from the current event to find the most recently used hour
        var hour = null;
        $(".playlistTable > tbody > tr .time").slice(row.index()+1).each(function() {
            if (this.dataset.hour !== '') {
                hour = this.dataset.hour;
                return false;
            }
        });

        var overlay = $(".pl-inline-edit");
        var dummy = $("<tr class='dummy'><td colspan=6></td></tr>");
        dummy.find("td").append(overlay);
        dummy.insertAfter(row);

        $('#edit-insert').show();
        $('#edit-save').hide();

        $(".track-time", overlay).fxtime('seg', 0, hour).fxtime('seg', 1, null).fxtime('seg', 2, 0);
        $('.track-type-pick', overlay).val('manual-entry').trigger('change');
        overlay.slideDown();

        clearUserInput(true, overlay);
    };

    function showActions(e) {
        e.preventDefault();

        var oldRow = row;
        if(row)
            closeInlineEdit(event, true);

        row = $(this).closest("tr");

        // selecting action on the already open row closes it
        if (oldRow && oldRow[0] == row[0]) {
            row = null;
            return;
        }

        row.addClass('selected').on('click', function(e) {
            e.stopPropagation();
        });
        $(this).next().css('display', 'flex');
    }

    addActions($(".playlistTable"));
    $("#edit-cancel").on('click', closeInlineEdit);

    // stretch track-play if track-add is hidden
    $("#track-add.zk-hidden").prev().outerWidth($(".pl-add-track .track-type-pick").outerWidth());

    /* BEGIN PLAYLIST EDIT */
    var show;
    function openPlaylistEdit() {
        var url = "api/v1/playlist/" + $("#track-playlist").val()
            + '?fields[show]=name,airname,date,time';

        $.ajax({
            dataType : 'json',
            type: 'GET',
            accept: "application/json; charset=utf-8",
            url: url,
            success: function (response) {
                show = response.data.attributes;

                $(".playlistBanner").hide();
                $(".pl-banner-edit input").removeClass("invalid-input");
                $(".pl-banner-edit").css('display', 'flex');

                $(".airname input")
                    .prop('disabled', show.airname && $(".airnames option[value='" + escQuote(show.airname) + "' i]").length == 0)
                    .val(show.airname);

                $(".date input").datepicker('setDate',
                        $.datepicker.parseDate('yy-mm-dd', show.date));

                var time = show.time.split('-').map(function(t) {
                    return localTimeIntl(t);
                });
                $(".start input").fxtime('val', time[0]);
                $(".end input").fxtime('val', time[1]);
                $(".description input").val(show.name).trigger('focus');
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var message = getErrorMessage(jqXHR, errorThrown);
                showUserError(message, $(".pl-add-track"));
            }
        });
    }

    function closePlaylistEdit() {
        $(".pl-banner-edit").hide();
        $(".playlistBanner").show();
        $(".pl-add-track .track-type-pick").trigger('change');
    }

    /**
     * modelled on checkAirname() from playlists.pick.js
     */
    function checkAirname() {
        var input = $(".airname input");
        if(input.prop('disabled'))
            return true;

        var airname = input.val().trim();
        return $(".airnames option[value='" + escQuote(airname) + "' i]").length > 0 || confirm('Create new air name "' + airname + '"?');
    }

    /**
     * from playlists.pick.js
     */
    function duration(interval) {
        return interval.split('-').map(function(time) {
            return new Date('1970-01-01T' + localTimeIntl(time) + ':00Z');
        }).reduce(function(start, end) {
            if(end < start)
                end.setDate(end.getDate() + 1);
            return (end.getTime() - start.getTime()) / 60000;
        });
    }

    /**
     * semantics of getEditRow() from playlists.pick.js
     */
    function getBannerEdit() {
        var date = $('.date input').datepicker('getDate');
        // correct datepicker local timezone to UTC
        date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
        var airname = $(".airname input");
        var time = $(".pl-banner-edit .time input").map(function() {
            return $(this).fxtime('val').replace(':','');
        }).toArray().join('-');

        return {
            id: $("#track-playlist").val(),
            attributes: {
                name: $(".description input").val().trim(),
                airname: airname.val().trim(),
                date: date.toISOString().split('T')[0],
                time: time
            }
        };
    }

    /**
     * modelled on updatePlaylist() from playlists.pick.js
     */
    function updatePlaylist(requireUsualSlot = true) {
        showUserError('', $(".pl-add-track"));
        $(".pl-banner-edit input").removeClass("invalid-input");

        // validate required fields
        var required = $(".pl-banner-edit input:invalid");
        if(required.length > 0) {
            required.addClass("invalid-input");
            required.first().trigger('focus');
            return;
        }

        if(requireUsualSlot && !checkAirname()) {
            $(".airname input").trigger('focus');
            return;
        }

        var list = getBannerEdit();

        var newTime = list.attributes.time;
        var oldTime = show.time;

        if(requireUsualSlot && duration(newTime) < duration(oldTime)
               && !confirm("Show has been shortened.  Tracks outside the new time will be deleted.\n\nAre you sure you want to do this?"))
            return;

        var postData = {
            data: {
                type: 'show',
                id: list.id,
                attributes: {
                    name: list.attributes.name,
                    airname: list.attributes.airname,
                    date: list.attributes.date,
                    time: list.attributes.time
                }
            },
            meta: {
                requireUsualSlot: requireUsualSlot
            }
        };

        $.ajax({
            type: 'PATCH',
            url: 'api/v1/playlist/' + list.id,
            dataType: 'json',
            contentType: "application/json; charset=utf-8",
            accept: "application/json; charset=utf-8",
            data: JSON.stringify(postData),
            statusCode: {
                // unusual date and time
                422: function() {
                    var showdate = new Date(list.attributes.date + 'T00:00:00Z');
                    var showtime = list.attributes.time.split('-');
                    $("#confirm-date-time-msg").text(showdate.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'numeric', day: 'numeric', timeZone: 'UTC' }) + ' ' + localTime(showtime[0]) + ' - ' + localTime(showtime[1]));
                    $("#confirm-operation").text("updating");
                    $("#confirm-date-time button").off().on('click', function() {
                        $("#confirm-date-time").hide();
                    });
                    $("#confirm-date-time button#continue").on('click', function() {
                        updatePlaylist(false);
                    });
                    $("#confirm-date-time").show();
                }
            }
        }).done(function (response) {
            // NB, not only can the banner change, but the entries
            // can as well if the date or time changes.
            //
            // Thus, we reload the playlist to pick up any changes
            // rather than trying to effect updates dynamically.
            location.href = "?subaction=" + $("#track-action").val() +
                "&playlist=" + list.id;
        }).fail(function (jqXHR, textStatus, errorThrown) {
            if(jqXHR.status == 422) return; // already handled above
            var message = getErrorMessage(jqXHR, 'Error: ' + errorThrown);
            showUserError(message, $('.pl-add-track'));
        });
    }

    $("#pl-banner-edit-button").on('click', function() {
        openPlaylistEdit();
    });

    $("#pl-banner-save").on('click', function() {
        updatePlaylist();
    });

    $("#pl-banner-cancel").on('click', function() {
        closePlaylistEdit();
    });

    /**
     * modelled on tr.find(".description").autocomplete of playlists.pick.js
     */
    var shownames = null;
    $(".description input").autocomplete({
        minLength: 0,
        source: function(rq, rs) {
            var term = rq.term.toLowerCase();
            if(shownames) {
                rs(shownames.filter(function(show) {
                    return show.name.toLowerCase().startsWith(term);
                }).map(show => show.name));
                return;
            }

            $.ajax({
                type: 'GET',
                accept: 'application/json; charset=utf-8',
                url: 'api/v1/playlist?filter[user]=self&fields[show]=name,airname,rebroadcast',
            }).done(function(response) {
                shownames = response.data.map(show => show.attributes)
                    .sort((a, b) => Intl.Collator().compare(a.name, b.name))
                    .filter(function(show, pos, shows) {
                        return !pos ||
                            show.name.localeCompare(shows[pos - 1].name,
                                                    undefined,
                                                    { sensitivity: 'base' });
                    })
                    .filter(function(show) {
                        return !show.rebroadcast;
                    });

                rs(shownames.filter(function(show) {
                    return show.name.toLowerCase().startsWith(term);
                }).map(show => show.name));
            });
        },
        select: function(event, ui) {
            var airname = $(".airname input");
            if (!airname.prop('disabled')) {
                var name = ui.item.value;
                var show = shownames.find(show => show.name == name);
                airname.val(show.airname);
            }
        }
    }).on('click', function() {
        $(this).autocomplete('search', '');
    });

    /**
     * modelled on tr.find(".airname").autocomplete of playlists.pick.js
     */
    $(".airname input").autocomplete({
        minLength: 0,
        source: function(rq, rs) {
            var term = rq.term.toLowerCase();
            rs($(".airnames option").map(function() {
                return this.value;
            }).filter(function() {
                return this.toLowerCase().includes(term);
            }));
        }
    }).on('click', function() {
        $(this).autocomplete('search', '');
    });

    $(".time input").fxtime();
    $(".date input").datepicker({
        dateFormat: intl ? 'dd-mm-yy' : 'mm/dd/yy'
    });

    /* END PLAYLIST EDIT*/

    $(document).on('click', function() {
        var stack = $(".pl-stack-content");
        if (row && stack.is(":visible")) {
            stack.hide();
            row.off().removeClass('selected');
            row = null;
        }
    });
});
