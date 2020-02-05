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

/*
 * The following methods are expected externally:
 *
 *    * changeSel(index) - set ID of selected list item into form
 *    * scrollUp - page up
 *    * scrollDown - page down
 *    * lineUp - line up
 *    * lineDown - line down
 *    * onSearchNow - warp to search text
 *
 */

var items, focus;

function urlEncode(url) {
    return encodeURI(url).replace(/\+/g, '%2B');
}

function changeList() {
    var list = $("#list");
    var index = list.prop('selectedIndex');
    changeSel(index);
    for(var key in items[0]) {
        var field = $("#"+key);
        if(field.length > 0) {
            if(key == 'email' || key == 'url') {
                var html = '<A HREF="';
                if(key == 'email' && items[index][key].indexOf('mailto:') != 0)
                    html += 'mailto:';
                else if(key == 'url' && items[index][key].indexOf('http://') != 0)
                    html += 'http://';
                html += items[index][key] + '"';
                if(key == 'url')
                    html += ' TARGET="_blank"';
                html += '>' + items[index][key] + '</A>';
                field.html(html);
            } else {
                field.html(items[index][key]);
            }
        }
    }
}

function upDown(e) {
    var list = $("#list");
    var index = list.prop('selectedIndex');
    var length = list.find("option").length;
    if(e.keyCode == 33 && index == 0) {
        // page up
        scrollUp();
    } else if(e.keyCode == 34 && index == length-1) {
        // page down
        scrollDown();
    } else if(e.keyCode == 38 && index == 0) {
        // line up
        lineUp();
    } else if(e.keyCode == 40 && index == length-1) {
        // line down
        lineDown();
    }
    return true;
}

function onSearch(sync, e) {
    if(e.type == 'keyup' && (e.keyCode == 33 || e.keyCode == 34 ||
                             e.keyCode == 38 || e.keyCode == 40)) {
        switch(e.keyCode) {
        case 33:
            // page up
            if(sync.list.selectedIndex == 0) {
                upDown(e);
                return;
            }
            sync.list.selectedIndex = 0;
            break;
        case 38:
            // line up
            if(sync.list.selectedIndex == 0) {
                upDown(e);
                return;
            }
            sync.list.selectedIndex--;
            break;
        case 34:
            // page down
            if(sync.list.selectedIndex == sync.list.length-1) {
                upDown(e);
                return;
            }
            sync.list.selectedIndex = sync.list.length-1;
            break;
        case 40:
            // line down
            if(sync.list.selectedIndex == sync.list.length-1) {
                upDown(e);
                return;
            }
            sync.list.selectedIndex++;
            break;
        }
        changeList();
        return;
    }

    if(sync.Timer) {
        clearTimeout(sync.Timer);
        sync.Timer = null;
    }
    sync.Timer = setTimeout('onSearchNow()', 250);
}

const NONALNUM=/([\.,!\?&~ \-\+=\{\[\(\|\}\]\)])/;
const STOPWORDS=/^(a|an|and|at|but|by|for|in|nor|of|on|or|out|so|the|to|up|yet)$/i;
function zkAlpha(control, track = false) {
    var val=control.value;
    var newVal=val.split(NONALNUM).map(function(word, index, array) {
        // words starting with caps are kept as-is
        if(word.search(/^[A-Z]+/) > -1)
            return word;

        // stopwords are not capitalized, unless first or last
        if(word.search(STOPWORDS) > -1 &&
                index !== 0 &&
                index !== array.length - 1)
            return word.toLowerCase();

        // otherwise, capitalize the word
        return word.charAt(0).toUpperCase() +
               word.substr(1).toLowerCase();
    }).join('');
    if(track != true && newVal.substr(0, 4) == 'The ')
        newVal=newVal.substr(4)+', The';
    control.value=newVal;
}

function setComp() {
    var disabled = $("INPUT[name=coll]").is(":checked");
    $("INPUT[name=artist]").css("visibility", disabled?'hidden':'visible');
    $("#lartist").css("visibility", disabled?'hidden':'visible');
    disabled?$("INPUT[name=album]").focus():$("INPUT[name=artist]").focus();
}

function setLocation() {
    var storage = $("SELECT[name=location]").val() == 'G';
    $("INPUT[name=bin]").css("visibility", storage?'visible':'hidden');
    $("#lbin").css("visibility", storage?'visible':'hidden');
    if(storage)
        $("INPUT[name=bin]").focus();
}

function setForeign() {
    var foreign = $("INPUT[name=foreign]").is(":checked");
    $("#lstate").css("visibility", foreign?'hidden':'visible');
    $("#lzip").html(foreign?'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Country:':'Postal Code:');
}

function cf(f) { focus = f; }

function nextTrack() {
    var form = document.forms[0];
    for(var i=1; typeof(eval('form.track'+i)) != 'undefined'; i++);
    return i;
}

function insertTrack(coll = false) {
    var form = $('FORM');
    var next = nextTrack();
    $('<INPUT>').attr({
        name: 'track' + next,
        type: 'hidden'
    }).appendTo(form);
    if(coll) {
        $('<INPUT>').attr({
            name: 'artist' + next,
            type: 'hidden'
        }).appendTo(form);
    }
    for(var j=next; j>focus; j--) {
        $("INPUT[name='track" + j + "' i]").val($("INPUT[name='track" + (j-1) + "' i]").val());
        if(coll) {
            $("INPUT[name='artist" + j + "' i]").val($("INPUT[name='artist" + (j-1) + "' i]").val());
        }
    }
    $("INPUT[name='track" + focus + "' i]").val("");
    if(coll) {
        $("INPUT[name='artist" + focus + "' i]").val("");
    }
    $("INPUT[name='track" + focus + "' i]").focus();
}

function deleteTrack(coll = false) {
    if(confirm('Delete track ' + focus + '?')) {
        var last = nextTrack()-1;
        for(var j=focus; j<last; j++) {
            $("INPUT[name='track" + j + "' i]").val($("INPUT[name='track" + (j+1) + "' i]").val());
            if(coll) {
                $("INPUT[name='artist" + j + "' i]").val($("INPUT[name='artist" + (j+1) + "' i]").val());
            }
        }
        $("INPUT[name='track" + last + "' i]").val("");
        if(coll) {
            $("INPUT[name='artist" + last + "' i]").val("");
        }
        $("INPUT[name='track" + focus + "' i]").focus();
    }
}
