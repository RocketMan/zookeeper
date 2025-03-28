//
// Zookeeper Online
//
// @author Jim Mason <jmason@ibinx.com>
// @copyright Copyright (C) 1997-2024 Jim Mason <jmason@ibinx.com>
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

/*! Zookeeper Online (C) 1997-2024 Jim Mason <jmason@ibinx.com> | @source: https://zookeeper.ibinx.com/ | @license: magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3.0 */

var trending = null;
var loading = false;
var lastsize = 0;
var timeout;

function reposition() {
    // determine the left and top edges of the word cloud
    var pos = loading.children('span').toArray().reduce(function(carry, span) {
        if(span.offsetLeft < carry.left)
            carry.left = span.offsetLeft;
        if(span.offsetTop < carry.top)
            carry.top = span.offsetTop;
        return carry;
    }, { left: 10000, top: 10000 } );

    // reposition the word cloud to the upper left of the content area
    loading.css('margin-left', -pos.left + 'px')
        .css('margin-top', -pos.top+20 + 'px');

    $(".tag-cloud").replaceWith(loading);
    loading.addClass('tag-cloud').removeClass('invisible')
        .parent().css('overflow-x', 'clip');

    $(".loading").remove();

    loading = false;
}

function loadCloud() {
    if(trending && !loading) {
        var content = $(".content");

        // shrink slightly to ensure we remain within the container
        var width = content.outerWidth() - 50;
        var height = content.outerHeight() - 50;

        // avoid re-layout on trivial resize
        if(Math.abs(lastsize - width) < 10)
            return;

        lastsize = width;

        // The div must be in the DOM *and* visible for jQCloud.
        //
        // We absolute position the div offscreen (class 'invisible'),
        // let jQCloud do the layout, and then reposition the div into
        // the normal document flow upon completion.
        loading = $("<div>", {
            class: "jqcloud invisible"
        });

        content.append(loading);

        loading.jQCloud(trending, {
            width: width,
            height: height,
            removeOverflowing: true,
            delayedMode: false,
            afterCloudRender: reposition
        });
    }
}

$().ready(function() {
    if(!$.fn.jQCloud) {
        $(".tag-cloud").append($("<p>", {
            class: 'quiet-notice'
        }).html('The trending cloud is unavailable.'));
        return;
    }

    $(".content").append($("<div>", {
        class: "loading"
    }).append($("<div>")));

    $.ajax({
        dataType: 'json',
        type: 'GET',
        accept: 'application/json; charset=utf-8',
        url: '?action=viewRecent&subaction=trendingData'
    }).done(function(response) {
        trending = response;
        loadCloud();
    });

    window.addEventListener('resize', function(event) {
        clearTimeout(timeout);
        timeout = setTimeout(loadCloud, 100);
    });
});
