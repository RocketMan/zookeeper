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
    const intl = new Date().toLocaleTimeString().match(/am|pm/i) == null;

    var mobile = window.innerWidth < 1024; // match @media min-width in css
    var maxresults = mobile ? 7 : 10, chunksize = mobile ? 7 : 10;
    var editing = null;

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

    function isEditing(cancel = false) {
        if(editing && cancel)
            editing.find('#cancel').trigger('click');

        return editing;
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
        }).append($("<span>").html(list.attributes.name)));
        tr.append($("<td>", {
            class: 'airname'
        }).on('mouseenter', function() {
            if(this.offsetWidth < this.scrollWidth && !this.title)
                this.title = list.attributes.airname;
        }).data('foreign', list.attributes.fairname).append($("<span>").html(list.attributes.airname)));
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
        tr.find('.action').on('click', function() {
            if (isEditing()) return;
            $(".mobileMenuContent").not($(this).next()).hide();
            $(this).next().slideToggle();
        });
        tr.find('.open').on('click', function() {
            if (isEditing()) return;
            var id = $(this).closest('tr').data('id');
            window.open("?action=editListEditor&playlist=" + id, "_top");
        });
        tr.on('dblclick', function() {
            if (isEditing()) return;
            var id = $(this).data('id');
            window.open("?action=editListEditor&playlist=" + id, "_top");
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
        $(this).fxtime('seg', 3, hour < 11 || hour > 22 ? 'AM' : 'PM').select();
    }

    function makeEditRow(isNew = 0) {
        var tr = $("<tr>", {
            class: 'selected'
        });
        tr.append($("<td>"));
        tr.append($("<td>").append($("<input>", {
            class: 'description',
            maxlength: $("#max-description-length").val()
        })));
        tr.append($("<td>").append($("<input>", {
            class: 'airname',
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
        var td = $("<td>");
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
        return tr;
    }

    function setEditRow(row, list) {
        var time = list.attributes.time ? list.attributes.time.split('-') : null;
        row.data('id', list.id);
        row.find('input.description').val(list.attributes.name);
        row.find('input.airname')
            .prop('disabled', list.attributes.fairname)
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
                name: row.find('input.description').val(),
                airname: airname.val(),
                fairname: airname.prop('disabled'),
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
            url += "&deleted=1";

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
            var json = JSON.parse(jqXHR.responseText);
            if (json && json.errors) {
                status = json.errors[0].title;
            } else
                status = 'Error retrieving the data: ' + textStatus;
            showUserError(status);
        });
    }

    function prestore(id) {
        showUserError('');

        $.ajax({
            type: 'POST',
            url: '?action=editListRestore',
            data: {
                playlist: id
            }
        }).done(function(response) {
            loadLists(maxresults, 0, 0);
            loadLists(maxresults, 0, 1);
        }).fail(function (jqXHR, textStatus, errorThrown) {
            var status = 'Error restoring the list: ' + textStatus;
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
        }).append($("<span>").html(list.attributes.name)));
        tr.append($("<td>", {
            class: 'airname'
        }).on('mouseenter', function() {
            if(this.offsetWidth < this.scrollWidth && !this.title)
                this.title = list.attributes.airname;
        }).append($("<span>").html(list.attributes.airname)));
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
                        }).text(cur).click((function(low) {
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

        var airname = input.val();
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
            required.first().focus();
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
            window.open("?action=editListEditor&playlist=" + id, "_top");
        }).fail(function (jqXHR, textStatus, errorThrown) {
            var json = JSON.parse(jqXHR.responseText);
            if (json && json.errors) {
                status = json.errors[0].title;
            } else
                status = 'Error: ' + textStatus;
            showUserError(status);
        });
    }

    function updatePlaylist(row) {
        showUserError('');
        row.find("input").removeClass("invalid-input");

        // validate required fields
        var required = row.find("input:invalid");
        if(required.length > 0) {
            required.addClass("invalid-input");
            required.first().focus();
            return;
        }

        if(!checkAirname(row))
            return;

        var list = getEditRow(row);
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
            editing = null;
        }).fail(function (jqXHR, textStatus, errorThrown) {
            var json = JSON.parse(jqXHR.responseText);
            if (json && json.errors) {
                status = json.errors[0].title;
            } else
                status = 'Error: ' + textStatus;
            showUserError(status);
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
                name: row.find('.description span').html(),
                airname: row.find('.airname span').html(),
                fairname: row.find('.airname').data('foreign'),
                date: row.find('.date').data('date'),
                time: row.find('.start').data('start') + '-' + row.find('.end').data('end')
            }
        };
        var edit = makeEditRow();
        setEditRow(edit, data);
        row.replaceWith(edit);
        edit.find("td input").first().focus();
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
                name: row.find('.description span').html() + suffix,
                airname: row.find('.airname span').html(),
                fairname: row.find('.airname').data('foreign'),
                date: now[0],
                time: now[1].replace(':','') + '-'
            }
        };
        var edit = makeEditRow(true);
        setEditRow(edit, data);
        row.after(edit);
        edit.find("td input.time#start").focus().select();
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
            var json = JSON.parse(jqXHR.responseText);
            if (json && json.errors) {
                status = json.errors[0].title;
            } else
                status = 'Error: ' + textStatus;
            showUserError(status);
        });
    }

    function fdup(id) {
        $.ajax({
            type: 'GET',
            url: 'api/v1/playlist/' + id,
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
            response.data.attributes.fairname = $(".airnames option[value='" + escQuote(response.data.attributes.airname) + "']").length == 0;
            var edit = makeEditRow(true);
            setEditRow(edit, response.data);
            $(".active-grid tbody").prepend(edit);
            edit.find("td input.time#start").focus().select();
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
            var json = JSON.parse(jqXHR.responseText);
            if (json && json.errors) {
                status = json.errors[0].title;
            } else
                status = 'Error: ' + textStatus;
            showUserError(status);
        });
    }

    function getHint(row) {
        $.ajax({
            dataType: 'json',
            type: 'GET',
            accept: "application/json; charset=utf-8",
            url: '?action=editListGetHint'
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
        row.find("td input").first().focus();
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
    });

    // the following are for Edit Profile

    $("#name").blur(function() {
        $(this).val($(this).val().trim());
    });

    $("#multi").click(function() {
        if($(this).is(':checked')) {
            $("#name").attr("disabled","disabled");
            $("#name").val($("#oldname").val());
        } else {
            $("#name").removeAttr("disabled");
        }
    });

    $("#update-airname").on("submit", function() {
        var oldAirname = $("#oldname").val();
        if($("#name").val() != oldAirname &&
                !confirm('Change airname "' + oldAirname + '" to "' + $("#name").val() + '"?')) {
            return false;
        }
    });

    $("*[data-focus]").focus();
});
