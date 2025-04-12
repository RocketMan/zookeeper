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

$().ready(function() {
    const intl = new Date().toLocaleTimeString().match(/am|pm/i) == null;

    var shownames = null;

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

    function escQuote(s) {
        return String(s).replace(/\'/g, '\\\'');
    }

    $(".date").datepicker({
        dateFormat: $("#date-format").val()
    }).datepicker('setDate', new Date($("#date").val()));

    $(".time").fxtime();
    $("#fromtime-entry").fxtime('val', localTimeIntl($("#fromtime").val()));
    $("#totime-entry").fxtime('val', localTimeIntl($("#totime").val())).on('focus', function() {
        var start = $("#fromtime-entry").fxtime('val');
        if(!start || $(this).fxtime('val'))
            return;

        var hour = start.split(':')[0];
        $(this).fxtime('seg', 3, hour < 11 || hour > 22 ? 'AM' : 'PM').trigger('select');
    });

    $("#description").on('click', function() {
        $(this).autocomplete('search', '');
    }).autocomplete({
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
            var name = ui.item.value;
            var show = shownames.find(show => show.name == name);
            $("#airname").val(show.airname);
        }
    });

    $("#airname").on('click', function() {
        $(this).autocomplete('search', '');
    }).autocomplete({
        minLength: 0,
        source: function(rq, rs) {
            var term = rq.term.toLowerCase();
            rs($("#airnames option").map(function() {
                return this.value;
            }).filter(function() {
                return this.toLowerCase().includes(term);
            }));
            $(".ui-menu").scrollTop(0);
        }
    });

    // limit length on mobile
    $("input").on('keyup', function() {
        var max = this.getAttribute('maxlength');
        if(max && this.value.length > max)
            this.value = this.value.substring(0, max);
    });

    $(".import-csv").on('submit', function(e) {
        e.preventDefault();

        if($("input[name=format]:checked").val() == "csv") {
            var airname = $("#airname").val().trim();
            if(airname.length == 0 ||
                   $("#airnames option[value='" + escQuote(airname) + "' i]").length == 0 && !confirm('Create new airname "' + airname + '"?')) {
                $("#airname").val('').trigger('focus');
                return;
            }

            var date = $(".date").datepicker('getDate');

            // correct local timezone to UTC for toISOString
            date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
            $("#date").val(date.toISOString().split('T')[0]);

            if(["fromtime", "totime"].some(function(id) {
                var time = $(`#${id}-entry`).fxtime('val');
                if(time)
                    $(`#${id}`).val(time.replace(':', ''));
                else {
                    $(`#${id}-entry`).trigger('focus');
                    return true;
                }
            })) return;
        }

        $('body').append("<div class='overlay'>").append("<div class='overlay-loader'>");

        var formData = new FormData($('form.import-form')[0]);

        $.ajax({
            dataType: 'json',
            type: 'POST',
            accept: 'application/json; charset=utf-8',
            url: '?',
            data: formData,
            processData: false,
            contentType: false,
            statusCode: {
                // unusual date and time
                422: function() {
                    var showdate = new Date($("#date").val() + 'T00:00:00Z');
                    var showtime = [ $("#fromtime").val(), $("#totime").val() ];
                    $("#confirm-date-time-msg").text(showdate.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'numeric', day: 'numeric' }) + ' ' + localTime(showtime[0]) + ' - ' + localTime(showtime[1]));
                    $("#confirm-operation").text("importing");
                    $(".zk-popup button").off().on('click', function() {
                        $(".zk-popup").hide();
                    });
                    $(".zk-popup button#continue").on('click', function() {
                        $("#require-usual-slot").val('0');
                        $(".import-csv").trigger('submit');
                    });
                    $("#error-msg").text('');
                    $("#confirm-date-time").show();
                }
            }
        }).done(function(response) {
            $("#error-msg").text(response.message);
            if(response.success)
                window.open(response.url, "_top");
        }).fail(function(jqXHR, textStatus, errorThrown) {
            if(jqXHR.status == 422) return; // already handled above
            var message = jqXHR.status == 403 ?
                'Server busy, try again...' :
                'Error: ' + errorThrown;
            $("#error-msg").text(message);
        }).always(function() {
            $('.overlay, .overlay-loader').remove();
        });
    });

    $("body").on('dragenter dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('drop-active');
    }).on('dragleave', function(e) {
        if(e.target.matches('body'))
            $(this).removeClass('drop-active');
    }).on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drop-active');
        if(e.originalEvent.dataTransfer &&
                e.originalEvent.dataTransfer.files.length) {
            var files = e.originalEvent.dataTransfer.files;
            if(files.length > 1) {
                alert('Please select only one file for import');
                return;
            }
            $("input[type=file]")[0].files = files;
            $(".file-area .success").text(files[0].name);
        }
    });
    $("input[type=file]").on('change', function(e) {
        $(".file-area .success").text(this.files[0].name);
    });

    $("input[name=format]").on('change', function(e) {
        switch(this.value) {
        case 'csv':
            $(".csv-fields").slideDown();
            $(".info-csv").css('display', 'inline-block');
            $(".info-json").hide();
            $(".csv-required").attr('required', true);
            break;
        case 'json':
            $(".csv-fields").slideUp();
            $(".info-csv").hide();
            $(".info-json").css('display', 'inline-block');
            $(".csv-required").attr('required', false);
            break;
        }
    });

    $("input:invalid").first().trigger('focus');
});
