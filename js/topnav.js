//
// Zookeeper Online
//
// @author Jim Mason <jmason@ibinx.com>
// @copyright Copyright (C) 1997-2023 Jim Mason <jmason@ibinx.com>
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

$().ready(function() {
    $(".nav-items > li > a").on('click', function(e) {
        if(!$(".nav-items.active").length)
            return;
        e.preventDefault();

        $(".nav-items > li.open > a").not(this).trigger('click');
        var submenu = $(this).next("ul");
        if(!submenu.length) {
            submenu = $("<ul>");
            var li = $(this).closest("li");
            var action = li.data("action");
            $.ajax({
                dataType: 'json',
                type: 'GET',
                accept: "application/json; charset=utf-8",
                url: "?action=" + action + "&subaction=_"
            }).done(function(response) {
                response.forEach(function(subitem) {
                    var li = $("<li>").append($("<a>", {
                        href: '?action=' + action + '&subaction=' + subitem.subaction}).text(subitem.label));
                    submenu.append(li);
                });
                   
                li.append(submenu).addClass("open");
                submenu.slideToggle();
            });
            return;
        }

        submenu.is(":visible") ?
            $(this).closest("li").removeClass("open") :
            $(this).closest("li").addClass("open");
        submenu.slideToggle();
    });

    $(".menu-icon span").on('click', function() {
        $(this).add("hide");
        $(".nav-items").addClass("active");
        $(".search-icon").addClass("hide");
        $(".cancel-icon").addClass("show");
        $(".nav-items > li.selected a").trigger('click');
    });
    
    $(".cancel-icon").on('click', function() {
        $(this).removeClass("show");
        $(".nav-items").removeClass("active");
        $(".menu-icon span").removeClass("hide");
        $(".search-icon").removeClass("hide");
        $("nav form").removeClass("active");
        $(".nav-items > li").removeClass("open");
        $(".nav-items ul").css("display", "");
    });
    
    $(".search-icon").on('click', function() {
        $(this).addClass("hide");
        $("nav form").addClass("active");
        $(".cancel-icon").addClass("show");
    });

    $(window).on('resize', function() {
        if(document.documentElement.clientWidth >= 1024 &&
                $(".cancel-icon.show").length)
            $(".cancel-icon").trigger('click');
    });

    $("#search-filter").selectmenu({width: 'auto', position: {
        my: 'left+1 top', at: 'left bottom'}});
    var width = $("#search-filter-button").get(0).offsetWidth;
    $(".search-data").css('padding-left', (width + 8) + "px")
        .css('padding-right', ($("#search-submit").get(0).offsetWidth + 4) + "px");

    $("nav form").on('submit', function() {
        // trigger paste for immediate search
        $(".search-data").trigger('focus').trigger('paste');
        return false;
    });

    $("#search-filter").on('change selectmenuchange', function() {
        var button = $("#search-filter-button");
        if($("#search-filter option[value='" + this.value + "']").data("default"))
            button.removeClass('override');
        else
            button.addClass('override');

        var width = button.get(0).offsetWidth;
        $(".search-data").css('padding-left', (width + 8) + "px")
            .trigger('focus').trigger('typechange');
    });
});
