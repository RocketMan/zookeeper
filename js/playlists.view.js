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
    // fetch the days with playlists for the selected month and enable the on the calendar.
    function setAvailableDays(showDate) {
        let url = 'index.php?action=playlistDaysByDate&viewdate=' + showDate;
        $.ajax({
            dataType : 'json',
            type: 'GET',
            accept: "application/json; charset=utf-8",
            url: url,
        }).done(function (goodDays) {
            let excludeDays = $('td[data-handler="selectDay"]').filter(function(){
                let day = $(this).text(); 
                if (day.length == 1)
                    day = '0' + day;

                let excludeDay = !goodDays.includes(day);
                return excludeDay;
            });
            excludeDays.unbind('click').addClass(['ui-datepicker-unselectable', 'ui-state-disabled']);
        });
    }

    // get playlists for a given ISO date & update the display table.
    function getPlaylists(isoDate) {
        let timestamp = Date.parse(isoDate + 'T00:00:00');
        if (!timestamp)
            errMsg = 'Date entry ' + isoDate + ' is not a valid date.';
        else if (playlistStartTimestamp && timestamp < playlistStartTimestamp) {
            alert("Sorry, the earliest playlist date is " + playlistStartIso);
            return;
        }
        
        setDateDisplay(isoDate);
        let url = 'index.php?action=playlistsByDate&viewdate=' + isoDate;
        $('#playlist-list tbody').html('');
        $.ajax({
            dataType : 'json',
            type: 'GET',
            accept: "application/json; charset=utf-8",
            url: url,
        }).done(function (respObj) {
            let options = { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' };
            let date = new Date(isoDate  + 'T00:00:00');
            prettyDate = date.toLocaleString('default', options);
            $('#playlist-list thead th').html(`Playlists for ${prettyDate}:`);
            $('#playlist-list tbody').html(respObj.tbody);
        });
    }

    function setDateDisplay(isoDate) {
        let dateAr = isoDate.split('-');
        let localDate = `${dateAr[1]}-${dateAr[2]}-${dateAr[0]}`;
        if (isUSLocale) 
            localDate = `${dateAr[1]}/${dateAr[2]}/${dateAr[0]}`;
            
        $("#playlist-date").val(localDate);
    }

    let locale = (new Intl.NumberFormat()).resolvedOptions().locale
    let isUSLocale = locale.endsWith('-US');
    $("#playlist-date").attr('placeholder', (isUSLocale ? 'mm/dd/yyyy<ENTER>' : 'dd-mm-yyyy<ENTER>'));

    let playlistStartTimestamp = null;
    let startYear = '2020';
    let playlistStartIso = $('#playlist-start-date').val();
    let isoStartAr = playlistStartIso.split('-');
    if (isoStartAr.length == 3) {
        playlistStartTimestamp = Date.parse(playlistStartIso + 'T00:00:00');
        startYear = isoStartAr[0];
    }
 
    let nowDate = new Date();
    $("#playlist-datepicker").datepicker({
        changeYear: true, 
        changeMonth: true,
        setDate: new Date(),
        dateFormat: 'yy-mm-dd',
        yearRange: startYear + ':' + nowDate.getFullYear(),
        onChangeMonthYear: function(year, month) {
            setAvailableDays(`${year}-${month}-1`);
        },
        beforeShow: function(elem, datePicker) {
            // set days available for the current date.
            let dateStr = $("#playlist-datepicker").val();
            let month = datePicker.settings.setDate.getMonth() + 1;
            let year = datePicker.settings.setDate.getFullYear();
            if (datePicker.currentYear) {
                year = datePicker.currentYear;
                month = datePicker.currentMonth + 1;
            }
            let isoDate = `${year}-${month}-1`;
            setAvailableDays(isoDate);
        },
    });


    $("#playlist-calendar").on('click', function(event) {
        $("#playlist-datepicker").datepicker("show");
    });

    $("#playlist-datepicker").on('click', function(event) {
        let dateAr = $("#playlist-datepicker").val().split('/');
        let isoDate = `${dateAr[2]}-${dateAr[0]}-1`;
        setAvailableDays(isoDate);
    });

    // invoked when user manually enters a date.
    $("#playlist-date").on('change', function(event) {
        let splitChar = isUSLocale ? '/' : '-';
        let errMsg = null;
        let isoDate = '';
        let dateStr = $(this).val();
        let dateAr = dateStr.split(splitChar);
        if (dateAr.length == 3) {
            let year = dateAr[2];
            let  month = isUSLocale ? dateAr[0] : dateAr[1];
            month = (month.length == 1 ? '0' : '') + month;
            let day = isUSLocale ? dateAr[1] : dateAr[0];
            day = (day.length == 1 ? '0' : '') + day;
            isoDate = `${year}-${month}-${day}`;
            let timestamp = Date.parse(isoDate + 'T00:00:00');
            if (!timestamp)
                errMsg = 'Date entry ' + dateStr + ' is not a valid date.';
        } else {
            errMsg = 'The date entry ' + dateStr + ' is improperly formatted.';
       }

       if (errMsg) {
           alert(errMsg);
       } else {
           getPlaylists(isoDate);
       }
    });

    $("#playlist-datepicker").on('change', function(event) {
        event.preventDefault();
        let isoDate = $("#playlist-datepicker").datepicker().val();
        getPlaylists(isoDate);
    });

    nowIso = `${nowDate.getFullYear()}-${("0" + (nowDate.getMonth()+1)).slice(-2)}-${("0" + nowDate.getDate()).slice(-2)}`;
    getPlaylists(nowIso);
});
