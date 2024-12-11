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
var timeout;

function reposition() {
    var pos = $("#cloud span").toArray().reduce(function(carry, span) {
        if(span.offsetLeft < carry.left)
            carry.left = span.offsetLeft;
        if(span.offsetTop < carry.top)
            carry.top = span.offsetTop;
        return carry;
    }, { left: 1000, top: 1000 } );

    $("#cloud").css('margin-left', -pos.left + 'px')
        .css('margin-top', -pos.top+20 + 'px')
        .css('opacity', 1);

    loading = false;
}

function loadCloud() {
    if(trending && !loading) {
        loading = true;
        var width = $(".content").outerWidth();
        $("#cloud").css('opacity', 0).empty().jQCloud(trending, {
            width: width,
            height: 350,
            removeOverflowing: false,
            afterCloudRender: reposition
        });
    }
}

$().ready(function() {
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
