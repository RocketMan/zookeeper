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
    var checkDate = document.createElement("input");
    checkDate.setAttribute("type", "date");
    if (checkDate.type!="date") {
        console.log("registering jquery date picker");
        $('#show-date-picker').datepicker();
        $('input.timepicker').timepicker({timeFormat:'H:mm'});
    }

    function getRoundedDateTime(minutes) {
        let now = new Date();
        now = new Date(now.getTime() - $("#timezone-offset").val() * 60000);
        let ms = 1000 * 60 * minutes; // convert minutes to ms
        let roundedDate = new Date(Math.round(now.getTime() / ms) * ms);
        return roundedDate
    }

    var isUpdate = $("#playlist-id").val().length > 0;
    if (isUpdate) {
        $('#edit-submit-but').prop('value' ,'Update');
    } else {
        var roundedDateTime = getRoundedDateTime(15);
        // returns <YYYY-MM-DD>T<HH:MM:SS.MMM>Z
        var dateTimeAr = roundedDateTime.toISOString().split('T');
        $("#show-date-picker").val(dateTimeAr[0]);

        // set to quarter hour if empty
        if ($("#show-start").val() == '') {
            var showStart = dateTimeAr[1];
            showStart = showStart.substring(0, showStart.length - 8);
            $("#show-start").val(showStart);
        }
    }

    $("#show-airname").blur(function(e) {
        $(this).val($.trim($(this).val()));
    });

    $("#new-show").on("submit", function(e) {
        // check for new airname
        var airname = $('#show-airname').val().trim().toLowerCase();
        var isNew = true;
        $('#airnames option').each(function() {
            if($(this).val().toLowerCase() == airname) {
                isNew = false;
                return false;
            }
        });

        if(isNew && !confirm('Create new air name "' +
               $('#show-airname').val() + '"?')) {
            return false;
        }

        // rearrange DP local format to ISO
        var pickerDate = $('#show-date-picker').val();
        if (pickerDate.indexOf('/') > 0) {
            console.log('adjust datepicker date');
            var dateAr = pickerDate.split('/');
            pickerDate = dateAr[2] + '-' + dateAr[0] + '-' + dateAr[1];
        }

        $('#show-date').val(pickerDate);
        return true;
    });
});

function setFocus() {}
