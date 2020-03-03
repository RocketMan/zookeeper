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
    $("#list-type-picker").change(function() {
        var $showType = $(this).val();
        if ($showType == 'active-type') {
            $("#deleted-form").addClass('zk-hidden');
            $("#active-form").removeClass('zk-hidden');
        } else {
            $("#active-form").addClass('zk-hidden');
            $("#deleted-form").removeClass('zk-hidden');
        }
        $("SELECT:not(#list-type-picker)").focus();
    });

    $("#active-list-picker").dblclick(function() {
        $("#active-form").submit();
    });

    $("#delete-list").click(function() {
        if (confirm("Are your sure?")) {
            $("#action-type").val('editListDelete');
            $("#active-form").submit();
        }
    });

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
