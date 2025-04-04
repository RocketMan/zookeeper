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
    const intl = new Date().toLocaleTimeString().match(/am|pm/i) == null;

    var mobile = window.innerWidth < 700; // match @media min-width in css
    var maxresults = mobile ? 7 : 10, chunksize = mobile ? 7 : 10;
    var editing = null;
    var shownames = null;

    /**
     * @param date string formatted 'yyyy-mm-dd'
     */
    function localDate(date) {
        var dateAr = date.split('-');
        return intl ?
            dateAr[2].padStart(2, '0') + "-" + dateAr[1].padStart(2, '0') + "-" + dateAr[0] :
            dateAr[1].padStart(2, '0') + "/" + dateAr[2].padStart(2, '0') + "/" + dateAr[0];
    }

    /**
     * @param time string formatted 'hhmm'
     */
    function localTimeIntl(time) {
        var stime = String(time);
        return stime.length ? stime.substring(0, 2) + ':' + stime.substring(2) : null;
    }

    /**
     * @param time string formatted 'hhmm'
     */
    function localTime(time) {
        if(intl)
            return localTimeIntl(time);

        var hour = time.substring(0, 2);
        var ampm = hour >= 12?"pm":"am";
        var m = time.substring(2);
        var min = ':' + m;
        if(hour > 12)
            hour -= 12;
        else if(hour == 0)
            hour = 12;
        return String(hour).padStart(2, '0') + min + ' ' + ampm;
    }

    function getRoundedDateTime(minutes) {
        let now = new Date();
        // convert local to UTC for toISOString
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        let ms = 1000 * 60 * minutes; // convert minutes to ms
        let roundedDate = new Date(Math.round(now.getTime() / ms) * ms);
        return roundedDate.toISOString().split('T');
    }

    /**
     * return next date which matches the weekday of the target
     *
     * @param targetDate date string formatted yyyy-MM-dd
     * @returns Date next matching weekday (can be today)
     */
    function getNextMatchingDate(targetDate) {
        const current = new Date();
        const target = new Date(targetDate);
        const difference = (target.getDay() - current.getDay() + 7) % 7;
        current.setDate(current.getDate() + difference);
        return current;
    }

    /**
     * return duration in minutes for a specified interval
     *
     * if the end time preceeds the start, it is assumed to be the next day
     *
     * @param interval string formatted 'hhmm-hhmm'
     * @returns duration in minutes
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

    function isEditing(cancel = false) {
        if(editing && cancel)
            editing.find('#cancel').trigger('click');

        return editing;
    }

    function getErrorMessage(jqXHR, defaultValue) {
        if(jqXHR.status == 403)
            return 'Server busy, try again...';

        try {
            var json = JSON.parse(jqXHR.responseText);
            if (json && json.errors)
                return json.errors.map(error => error.title).join(', ');
        } catch(e) {}

        return defaultValue;
    }

    function showUserError(msg) {
        $(".float-error").text(msg);
    }

    function makeRow(list) {
        var time = list.attributes.time.split('-');
        var tr = $("<tr>").data('id', list.id);
        tr.append($("<td>").append($("<button>", {
            class: 'open'
        }).append($("<span>").html("Open"))));
        tr.append($("<td>", {
            class: 'description'
        }).on('mouseenter', function() {
            if(this.offsetWidth < this.scrollWidth && !this.title)
                this.title = list.attributes.name;
        }).append($("<span>").text(list.attributes.name)));
        tr.append($("<td>", {
            class: 'airname'
        }).on('mouseenter', function() {
            if(this.offsetWidth < this.scrollWidth && !this.title)
                this.title = list.attributes.airname;
        }).append($("<span>").text(list.attributes.airname)));
        tr.append($("<td>", {
            class: 'date'
        }).data('date', list.attributes.date).append($("<span>").html(localDate(list.attributes.date))));
        tr.append($("<td>", {
            class: 'start'
        }).data('start', time[0]).append($("<span>").html(localTime(time[0]))));
        tr.append($("<td>").html("-"));
        tr.append($("<td>", {
            class: 'end'
        }).data('end', time[1]).append($("<span>").html(localTime(time[1]))));
        var td = $("<td>");
        tr.append(td);
        td.append($("<div>", {
            class: 'mobileMenu'
        }).append($("<a>", {
            class: 'listEdit nav action',
            title: 'Actions'
        }).html('&#x2699;')).append($("<div>", {
            class: 'mobileMenuContent'
        }).append($("<button>", {
            class: 'pedit'
        }).html('Edit')).append($("<button>", {
            class: 'pdup'
        }).html('Duplicate')).append($("<button>", {
            class: 'pdel'
        }).html('Delete'))));
        tr.find('.pedit').on('click', pedit);
        tr.find('.pdup').on('click', pdup);
        tr.find('.pdel').on('click', pdel);
        tr.find('.action').on('click', function(e) {
            if (isEditing()) return;
            $(".mobileMenuContent").not($(this).next()).hide();
            $(this).next().slideToggle();
            e.stopPropagation();
        });
        tr.find('.mobileMenuContent').on('click dblclick', function(e) {
            e.stopPropagation();
        });
        tr.find('.open').on('click', function() {
            if (isEditing()) return;
            var id = $(this).closest('tr').data('id');
            window.open("?subaction=editListEditor&playlist=" + id, "_top");
        });
        tr.on('dblclick', function() {
            if (isEditing()) return;
            var id = $(this).data('id');
            window.open("?subaction=editListEditor&playlist=" + id, "_top");
        });

        return tr;
    }

    function fixupAMPM() {
        if(intl || $(this).fxtime('val'))
            return;

        var start = $("#start").fxtime('val');
        if(!start)
            return;

        var hour = start.split(':')[0];
        $(this).fxtime('seg', 3, hour < 11 || hour > 22 ? 'AM' : 'PM').trigger('select');
    }

    function makeEditRow(isNew = 0) {
        var tr = $("<tr>", {
            class: 'selected'
        });
        tr.append($("<td>"));
        tr.append($("<td>").append($("<input>", {
            class: 'description',
            pattern: '.*\\S.*',
            maxlength: $("#max-description-length").val()
        })));
        tr.append($("<td>").append($("<input>", {
            class: 'airname',
            pattern: '.*\\S.*',
            maxlength: $("#max-airname-length").val()
        })));
        tr.append($("<td>").append($("<input>", {
            class: 'date'
        })));
        tr.append($("<td>").append($("<input>", {
            class: 'time',
            id: 'start'
        })));
        tr.append($("<td>").html("-"));
        tr.append($("<td>").append($("<input>", {
            class: 'time',
            id: 'end'
        })));
        var td = $("<td>", {
            class: 'nowrap'
        });
        td.append($("<button>", {
            class: 'default',
            id: 'save'
        }).html(isNew ? "Create" : "Save"));
        td.append($("<button>", {
            id: 'cancel'
        }).html("Cancel"));
        tr.append(td);
        tr.find("input").prop('required', true);
        tr.find(".date").datepicker({
            dateFormat: intl ? 'dd-mm-yy' : 'mm/dd/yy'
        });
        tr.find(".time").fxtime().last().on('focus', fixupAMPM);
        tr.find(".airname").autocomplete({
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
        tr.find(".description").autocomplete({
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
                    url: 'api/v1/playlist?filter[user]=self&fields[show]=name,airname,rebroadcast,date,time',
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
                var name = ui.item.value;
                var show = shownames.find(show => show.name == name);
                var time = show.time.split('-');
                $("input.airname").val(show.airname);
                $("input.date").datepicker('setDate', getNextMatchingDate(show.date));
                $('input.time#start').fxtime('val', localTimeIntl(time[0]));
                $('input.time#end').fxtime('val', localTimeIntl(time[1]));
            }
        }).on('click', function() {
            $(this).autocomplete('search', '');
        });
        return tr;
    }

    function setEditRow(row, list) {
        var time = list.attributes.time ? list.attributes.time.split('-') : null;
        row.data('id', list.id);
        row.find('input.description').val(list.attributes.name);
        row.find('input.airname')
            .prop('disabled', list.attributes.airname && $(".airnames option[value='" + escQuote(list.attributes.airname) + "' i]").length == 0)
            .val(list.attributes.airname);
        row.find('input.date').val(localDate(list.attributes.date));
        row.find('input.time#start').fxtime('val', time ? localTimeIntl(time[0]) : null);
        row.find('input.time#end').fxtime('val', time ? localTimeIntl(time[1]) : null);
        row.data('orig', list);
    }

    function getEditRow(row) {
        var date = row.find('input.date').datepicker('getDate');
        // correct datepicker local timezone to UTC
        date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
        var airname = row.find('input.airname');
        var start = row.find('input#start').fxtime('val').replace(':','');
        var end = row.find('input#end').fxtime('val').replace(':','');
        return {
            id: row.data('id'),
            attributes: {
                name: row.find('input.description').val().trim(),
                airname: airname.val().trim(),
                date: date.toISOString().split('T')[0],
                time: start + '-' + end
            }
        };
    }

    function makeNoneRow(deleted) {
        var tr = $("<tr>").append($("<td>", {
            colSpan: 8,
            class: 'no-playlists'
        }).html("You have no " + (deleted ? "deleted " : "") +
                "playlists"));
        return tr;
    }

    function loadLists(size, offset, deleted) {
        showUserError('');
        var url = "api/v1/playlist?filter[user]=self&fields[show]=-events";

        if(size >= 0)
            url += "&page[size]=" + size;

        if(offset >= 0)
            url += "&page[offset]=" + offset;

        if(deleted)
            url += "&filter[deleted]=1";

        $.ajax({
            dataType: 'json',
            type: 'GET',
            accept: "application/json; charset=utf-8",
            url: url
        }).done(function (response) {
            var grid = deleted ? '.deleted-grid ' : '.active-grid ';
            var body = $(grid + 'tbody').empty();
            // if this is not the entire result set, paginate
            if(response.data.length < response.links.first.meta.total &&
                    response.data.length > chunksize)
                response.data = response.data.slice(0, chunksize);

            if(response.data.length == 0) {
                // hiding the header affects alignment, so just make it transparent
                $(grid + 'thead th').css('color', 'transparent').css('border-color', 'transparent');
                body.append(makeNoneRow(deleted));
                return;
            }

            $(grid + 'thead th').css('color', 'inherit').css('border-color', 'inherit');

            response.data.forEach(function(data) {
                let row = deleted ? makeDeletedRow(data) : makeRow(data);
                body.append(row);
            });
            emitMore(body, response, deleted);

            var dup = $("#duplicate");
            if(!deleted && dup.length > 0) {
                fdup(dup.val());
                dup.remove();
            }

            editing = null;
        }).fail(function (jqXHR, textStatus, errorThrown) {
            var message = getErrorMessage(jqXHR,
                          'Error retrieving the data: ' + errorThrown);
            showUserError(message);
        });
    }

    function prestore(id) {
        showUserError('');

        var postData = {
            data: {
                type: 'show',
                id: id
            }
        };

        $.ajax({
            type: 'PATCH',
            url: 'api/v1/playlist/' + id,
            data: JSON.stringify(postData)
        }).done(function(response) {
            loadLists(maxresults, 0, 0);
            loadLists(maxresults, 0, 1);
        }).fail(function (jqXHR, textStatus, errorThrown) {
            var status = 'Error restoring the list: ' + errorThrown;
            showUserError(status);
        });
    }

    function makeDeletedRow(list) {
        var time = list.attributes.time.split('-');
        var tr = $("<tr>").data('id', list.id);
        tr.append($("<td>").append($("<button>", {
            class: 'restore'
        }).append($("<span>").html("Restore"))));
        tr.append($("<td>", {
            class: 'description'
        }).on('mouseenter', function() {
            if(this.offsetWidth < this.scrollWidth && !this.title)
                this.title = list.attributes.name;
        }).append($("<span>").text(list.attributes.name)));
        tr.append($("<td>", {
            class: 'airname'
        }).on('mouseenter', function() {
            if(this.offsetWidth < this.scrollWidth && !this.title)
                this.title = list.attributes.airname;
        }).append($("<span>").text(list.attributes.airname)));
        tr.append($("<td>", {
            class: 'date'
        }).append($("<span>").html(localDate(list.attributes.date))));
        tr.append($("<td>", {
            class: 'start'
        }).append($("<span>").html(localTime(time[0]))));
        tr.append($("<td>").html("-"));
        tr.append($("<td>", {
            class: 'end'
        }).append($("<span>").html(localTime(time[1]))));
        tr.append($("<td>", {
            class: 'expires'
        }).append($("<span>").html(localDate(list.attributes.expires))));
        tr.find('.restore').on('click', function() {
            var id = $(this).closest('tr').data('id');
            prestore(id);
        });

        return tr;
    }

    function emitMore(table, data, deleted) {
        var meta = data.links.first.meta;
        var more = meta.total;
        if(more > 0) {
            var offset = meta.offset;
            var ul = $("<ul>", {
                class: 'pagination'
            });

            var count = data.data.length;
            if(count < more) {
                var numchunks = 10;
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
                                loadLists(chunksize, low, deleted);
                                return false;
                            }
                        })(low));
                        ul.append($("<li>").append(a));
                    }
                }
                if((start + i - 1) * chunksize < more)
                    ul.append($("<li>").text("..."));
            }

            table.append($("<tr>").append($("<td>", {
                colSpan: 8
            }).append(ul)));
        }
    }

    function checkAirname(row) {
        var input = row.find('input.airname');
        if(input.prop('disabled'))
            return true;

        var airname = input.val().trim();
        return $(".airnames option[value='" + escQuote(airname) + "' i]").length > 0 || confirm('Create new air name "' + airname + '"?');
    }

    function addAirname(row) {
        var input = row.find('input.airname');
        if(input.prop('disabled'))
            return;

        var airname = input.val();
        if($(".airnames option[value='" + escQuote(airname) + "' i]").length == 0)
            $(".airnames").append($("<option>", {
                value: airname
            }));
    }

    function newPlaylist(row) {
        showUserError('');
        row.find("input").removeClass("invalid-input");

        // validate required fields
        var required = row.find("input:invalid");
        if(required.length > 0) {
            required.addClass("invalid-input");
            required.first().trigger('focus');
            return;
        }

        if(!checkAirname(row))
            return;

        var list = getEditRow(row);
        var postData = {
            data: {
                type: 'show',
                attributes: {
                    name: list.attributes.name,
                    airname: list.attributes.airname,
                    date: list.attributes.date,
                    time: list.attributes.time
                }
            }
        };

        if(list.id) {
            postData.data.attributes.rebroadcast = true;
            postData.data.relationships = {
                origin: {
                    data: {
                        type: 'show',
                        id: list.id
                    }
                }
            };
        }

        $.ajax({
            type: 'POST',
            url: 'api/v1/playlist',
            // dataType must be 'text', as success returns 201 Created
            // with empty response.  If this is 'json', parse will fail
            // and not invoke done()
            dataType: 'text',
            contentType: "application/json; charset=utf-8",
            data: JSON.stringify(postData),
        }).done(function (data, textStatus, request) {
            var location = request.getResponseHeader('Location');
            var id = location.split('/').pop();
            window.open("?subaction=editListEditor&playlist=" + id, "_top");
        }).fail(function (jqXHR, textStatus, errorThrown) {
            var message = getErrorMessage(jqXHR, 'Error: ' + errorThrown);
            showUserError(message);
        });
    }

    function updatePlaylist(row) {
        showUserError('');
        row.find("input").removeClass("invalid-input");

        // validate required fields
        var required = row.find("input:invalid");
        if(required.length > 0) {
            required.addClass("invalid-input");
            required.first().trigger('focus');
            return;
        }

        if(!checkAirname(row))
            return;

        var list = getEditRow(row);

        var newTime = list.attributes.time;
        var oldTime = row.data('orig').attributes.time;

        if(duration(newTime) < duration(oldTime)
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
            }
        };

        $.ajax({
            type: 'PATCH',
            url: 'api/v1/playlist/' + list.id,
            dataType: 'json',
            contentType: "application/json; charset=utf-8",
            accept: "application/json; charset=utf-8",
            data: JSON.stringify(postData),
        }).done(function (response) {
            addAirname(row);

            var nrow = makeRow(list);
            row.replaceWith(nrow);
            editing = shownames = null;
        }).fail(function (jqXHR, textStatus, errorThrown) {
            var message = getErrorMessage(jqXHR, 'Error: ' + errorThrown);
            showUserError(message);
        });
    }

    function escQuote(s) {
        return String(s).replace(/\'/g, '\\\'');
    }

    function pedit() {
        $(".mobileMenuContent").hide();
        var row = $(this).closest('tr');
        var data = {
            id: row.data('id'),
            attributes: {
                name: row.find('.description span').text(),
                airname: row.find('.airname span').text(),
                date: row.find('.date').data('date'),
                time: row.find('.start').data('start') + '-' + row.find('.end').data('end')
            }
        };
        var edit = makeEditRow();
        setEditRow(edit, data);
        row.replaceWith(edit);
        edit.find("td input").first().trigger('focus');
        edit.find("button#save").on('click', function() {
            updatePlaylist($(this).closest('tr'));
        });
        edit.find("button#cancel").on('click', function() {
            showUserError('');
            var row = $(this).closest('tr');
            var data = row.data('orig');
            var nrow = makeRow(data);
            row.replaceWith(nrow);
            editing = null;
        });
        editing = edit;
    }

    function pdup() {
        $(".mobileMenuContent").hide();
        var row = $(this).closest('tr');

        var date = new Date(row.find('.date').data('date') + 'T00:00:00Z');
        // treat UTC date as local time for toLocaleString
        date.setMinutes(date.getMinutes() + date.getTimezoneOffset());
        var fmtDate = date.toLocaleString('default', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
        var suffix = $("#duplicate-suffix").val().replace(/%.*%/, fmtDate);
        var now = getRoundedDateTime(15);
        var data = {
            id: row.data('id'),
            attributes: {
                name: row.find('.description span').text() + suffix,
                airname: row.find('.airname span').text(),
                date: now[0],
                time: now[1].replace(':','') + '-'
            }
        };
        var edit = makeEditRow(true);
        setEditRow(edit, data);
        row.after(edit);
        edit.find("td input.time#start").trigger('focus').trigger('select');
        edit.find("button#save").on('click', function() {
            newPlaylist($(this).closest('tr'));
        });
        edit.find("button#cancel").on('click', function() {
            showUserError('');
            $(this).closest('tr').remove();
            if($('.active-grid tbody tr').length == 0) {
                // hiding the header affects alignment, so just make it transparent
                $('.active-grid thead th').css('color', 'transparent').css('border-color', 'transparent');
                $('.active-grid tbody').append(makeNoneRow(0));
            }
            editing = null;
        });
        editing = edit;
    }

    function pdel() {
        showUserError('');
        var doIt = confirm("Delete this playlist?");
        $(".mobileMenuContent").hide();

        if (!doIt) return;

        var row = $(this).closest('tr');
        var id = row.data('id');

        $.ajax({
            type: 'DELETE',
            url: 'api/v1/playlist/' + id,
            accept: "application/json; charset=utf-8"
        }).done(function(response) {
            row.remove();
            loadLists(maxresults, 0, 1);
        }).fail(function (jqXHR, textStatus, errorThrown) {
            var message = getErrorMessage(jqXHR, 'Error: ' + errorThrown);
            showUserError(message);
        });
    }

    function fdup(id) {
        $.ajax({
            type: 'GET',
            url: 'api/v1/playlist/' + id + '?fields[show]=-events',
            accept: "application/json; charset=utf-8"
        }).done(function(response) {
            var date = new Date(response.data.attributes.date + 'T00:00:00Z');
            // treat UTC date as local time for toLocaleString
            date.setMinutes(date.getMinutes() + date.getTimezoneOffset());
            var fmtDate = date.toLocaleString('default', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
            var suffix = $("#duplicate-suffix").val().replace(/%.*%/, fmtDate);
            var now = getRoundedDateTime(15);
            response.data.attributes.name += suffix;
            response.data.attributes.date = now[0];
            response.data.attributes.time = now[1].replace(':','') + '-';
            var edit = makeEditRow(true);
            setEditRow(edit, response.data);
            $(".active-grid tbody").prepend(edit);
            edit.find("td input.time#start").trigger('focus').trigger('select');
            edit.find("button#save").on('click', function() {
                newPlaylist($(this).closest('tr'));
            });
            edit.find("button#cancel").on('click', function() {
                showUserError('');
                $(this).closest('tr').remove();
                if($('.active-grid tbody tr').length == 0) {
                    // hiding the header affects alignment, so just make it transparent
                    $('.active-grid thead th').css('color', 'transparent').css('border-color', 'transparent');
                    $('.active-grid tbody').append(makeNoneRow(0));
                }
                editing = null;
            });
            editing = edit;
        }).fail(function (jqXHR, textStatus, errorThrown) {
            var message = getErrorMessage(jqXHR, 'Error: ' + errorThrown);
            showUserError(message);
        });
    }

    function getHint(row) {
        $.ajax({
            dataType: 'json',
            type: 'GET',
            accept: "application/json; charset=utf-8",
            url: '?subaction=editListGetHint'
        }).done(function (response) {
            if(response.attributes)
                setEditRow(row, response);
            else {
                var now = getRoundedDateTime(15);
                var data = {
                    id: row.data('id'),
                    attributes: {
                        date: now[0],
                        time: now[1].replace(':','') + '-'
                    }
                };
                setEditRow(row, data);
            }
        });
    }

    $("div.content").css('overflow', 'visible');
    $(".playlist-accordion").accordion({ heightStyle: 'fill' }).show().accordion('refresh');
    // 'overflow: visible' lets the popup menu render outside the parent;
    // we have to do this after refresh, as accordion sets overflow style
    // on the content element.
    //
    // 'padding-top' is a hack to prevent accordion shifting layout when
    // opening and closing the content.
    $(".active-playlist-container").css('overflow', 'visible').css('padding-top', '2px');
    $(".newPlaylist button").on('click', function() {
        if (isEditing(true)) return;

        $(".mobileMenuContent").hide();
        $('.active-grid thead th').css('color', 'inherit').css('border-color', 'inherit');
        $('.active-grid .no-playlists').closest('tr').remove();

        var row = makeEditRow(true);
        // prefill based on historical hint
        getHint(row);
        $(".active-grid tbody").prepend(row);
        row.find("td input").first().trigger('focus');
        row.find("button#save").on('click', function() {
            newPlaylist($(this).closest('tr'));
        });
        row.find("button#cancel").on('click', function() {
            showUserError('');
            $(this).closest('tr').remove();
            if($('.active-grid tbody tr').length == 0) {
                // hiding the header affects alignment, so just make it transparent
                $('.active-grid thead th').css('color', 'transparent').css('border-color', 'transparent');
                $('.active-grid tbody').append(makeNoneRow(0));
            }
            editing = null;
        });
        editing = row;
    });

    loadLists(maxresults, 0, 0);
    loadLists(maxresults, 0, 1);

    $(document).tooltip({
        items: 'td',
        position: {
            my: 'center top',
            at: 'right bottom'
        },
        show: true
    }).on('click', function() {
        $(".mobileMenuContent").slideUp();
    });

    window.addEventListener('pageshow', function() {
        if(editing)
            loadLists(maxresults, 0, 0);
    });

    // the following are for Edit Profile

    $("#name").on('blur', function() {
        this.value = this.value.trim();
    });

    $("#multi").on('click', function() {
        if($(this).is(':checked')) {
            $("#name").prop("disabled", true);
            $("#name").val($("#oldname").val());
        } else {
            $("#name").prop("disabled", false);
        }
    });

    $("#update-airname").on("submit", function() {
        var oldAirname = $("#oldname").val();
        if($("#name").val() != oldAirname &&
                !confirm('Change airname "' + oldAirname + '" to "' + $("#name").val() + '"?')) {
            return false;
        }
    });

    $("*[data-focus]").trigger('focus');
});
