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

$().ready(function() {
    /**
     * @param time string formatted 'hhmm'
     */
    function localTimeIntl(time) {
        var stime = String(time);
        return stime.length ? stime.substring(0, 2) + ':' + stime.substring(2) : null;
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
        $(this).fxtime('seg', 3, hour < 11 || hour > 22 ? 'AM' : 'PM').select();
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
        var airname = $("#airname").val().trim();
        if(airname.length == 0 ||
               $("#airnames option[value='" + escQuote(airname) + "' i]").length == 0 && !confirm('Create new airname "' + airname + '"?')) {
            $("#airname").val('').focus();
            e.preventDefault();
            return;
        }

        var date = $(".date").datepicker('getDate');
        // correct local timezone to UTC for toISOString
        date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
        $("#date").val(date.toISOString().split('T')[0]);

        $("#fromtime").val($("#fromtime-entry").fxtime('val').replace(':',''));
        $("#totime").val($("#totime-entry").fxtime('val').replace(':',''));
    });

    $("body").on('dragenter dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('drop-active');
    }).on('dragleave', function() {
        $(this).removeClass('drop-active');
    }).on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $("body").removeClass('drop-active');
        if(e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files.length) {
            if(e.originalEvent.dataTransfer.files.length > 1) {
                alert('Please select only one file for import');
                return;
            }
            $("input[type=file]")[0].files = e.originalEvent.dataTransfer.files;
            $(".file-area .success").text(e.originalEvent.dataTransfer.files[0].name);
        }
    });
    $("input[type=file]").on('change', function(e) {
        $(".file-area .success").text(this.files[0].name);
    });

    $("input:invalid").first().focus();
});
