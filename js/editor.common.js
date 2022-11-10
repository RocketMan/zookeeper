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

/*
 * For the Albums and Labels tabs, implementations for the following
 * are supplied by editor.album.js and editor.label.js, respectively:
 *
 *    * changeSel(index) - set ID of selected list item into form
 *    * scrollUp - page up
 *    * scrollDown - page down
 *    * lineUp - line up
 *    * lineDown - line down
 *    * onSearchNow - warp to search text
 *
 */

var items, links, timer;

function htmlify(s) {
    return s != null?s.replace(/&/g, '&amp;').replace(/</g, '&lt;'):'';
}

function getSelectedIndex(list) {
    return list.find('.state-active').index();
}

function setSelectedIndex(list, idx) {
    list.find('li')
        .removeClass('state-active')
        .eq(idx).addClass('state-active');
}

function changeList(list) {
    var index = getSelectedIndex(list);
    changeSel(index);
    for(var key in items[0].attributes) {
        var field = $("#"+key);
        if(field.length > 0) {
            var val = items[index].attributes[key];
            if(val != null && (key == 'email' || key == 'url')) {
                var html = '<A HREF="';
                if(key == 'email' && val.indexOf('mailto:') != 0)
                    html += 'mailto:';
                else if(key == 'url' && val.indexOf('http://') != 0)
                    html += 'http://';
                html += val + '"';
                if(key == 'url')
                    html += ' TARGET="_blank"';
                html += '>' + htmlify(val) + '</A>';
                field.html(html);
            } else {
                field.html(htmlify(val));
            }
        }
    }
}

function upDown(list, e) {
    var index = getSelectedIndex(list);
    if(e.keyCode == 33 && index == 0) {
        // page up
        scrollUp();
    } else if(e.keyCode == 34 && index == items.length - 1) {
        // page down
        scrollDown();
    } else if(e.keyCode == 38 && index == 0) {
        // line up
        lineUp();
    } else if(e.keyCode == 40 && index == items.length - 1) {
        // line down
        lineDown();
    }
    return true;
}

/**
 * returns true if key changes input value, undefined otherwise
 */
function onKeyDown(list, e) {
    if(timer) {
        clearTimeout(timer);
        timer = null;
    }

    switch(e.keyCode) {
    case 33:
        // page up
        if(getSelectedIndex(list) == 0) {
            upDown(list, e);
            return;
        }
        setSelectedIndex(list, 0);
        break;
    case 38:
        // line up
        if(getSelectedIndex(list) == 0) {
            upDown(list, e);
            return;
        }
        setSelectedIndex(list, getSelectedIndex(list) - 1);
        break;
    case 34:
        // page down
        if(getSelectedIndex(list) == items.length - 1) {
            upDown(list, e);
            return;
        }
        setSelectedIndex(list, items.length - 1);
        break;
    case 40:
        // line down
        if(getSelectedIndex(list) == items.length - 1) {
            upDown(list, e);
            return;
        }
        setSelectedIndex(list, getSelectedIndex(list) + 1);
        break;
    case 9:  // tab
    case 16: // shift
    case 35: // end
    case 36: // home
    case 37: // arrow left
    case 39: // arrow right
        // key does not change the input
        return;
    default:
        // all keys not otherwise handled change the input
        return true;
    }

    changeList(list);
    e.preventDefault();
}

$().ready(function() {
    var focus, sel, count = 0, max = -1;

    const NONALNUM=/([\.,!\?&~ \-\+=\{\[\(\|\}\]\)])/;
    const STOPWORDS=/^(a|an|and|at|but|by|for|in|nor|of|on|or|out|so|the|to|up|yet)$/i;
    function zkAlpha(control) {
        var val=control.val();
        var track=control.data("zkalpha") === true;
        var newVal=val.split(NONALNUM).map(function(word, index, array) {
            // words starting with caps are kept as-is
            if(word.search(/^\p{Lu}/u) > -1)
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
        control.val(newVal);
    }

    $("INPUT[data-zkalpha]").change(function(e) {
        zkAlpha($(this));
    });
    $("INPUT[data-track]").focus(function(e) {
        focus = $(this).data("track");
    });
    $("INPUT[data-upper]").change(function(e) {
        $(this).val($(this).val().toUpperCase());
    });

    function nextTrack() {
        var form = document.forms[0];
        for(var i=1; typeof(eval('form.track'+i)) != 'undefined'; i++);
        return i;
    }

    $("#comp").click(function(e) {
        var disabled = $(this).is(":checked");
        $("INPUT[name=artist]").css("visibility", disabled?'hidden':'visible');
        $("#lartist").css("visibility", disabled?'hidden':'visible');
        disabled?$("INPUT[name=album]").focus():$("INPUT[name=artist]").focus();
    });

    $("#location").change(function(e) {
        var storage = $("SELECT[name=location]").val() == 'G';
        $("INPUT[name=bin]").css("visibility", storage?'visible':'hidden');
        $("#lbin").css("visibility", storage?'visible':'hidden');
        if(storage)
            $("INPUT[name=bin]").focus();
    });

    $("#foreign").click(function(e) {
        var foreign = $("INPUT[name=foreign]").is(":checked");
        $("#lstate").css("visibility", foreign?'hidden':'visible');
        $("#lzip").html(foreign?'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Country:':'Postal Code:');
    });

    $("#insert").click(function(e) {
        var form = $('FORM');
        var coll = $('INPUT[name=coll]').val() == "on";
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
            $("INPUT[name='trackUrl" + j + "' i]").val($("INPUT[name='trackUrl" + (j-1) + "' i]").val());
            if(coll) {
                $("INPUT[name='artist" + j + "' i]").val($("INPUT[name='artist" + (j-1) + "' i]").val());
            }
        }
        $("INPUT[name='track" + focus + "' i]").val("");
        $("INPUT[name='trackUrl" + focus + "' i]").val("");
        if(coll) {
            $("INPUT[name='artist" + focus + "' i]").val("");
        }
        $("INPUT[name='track" + focus + "' i]").focus();
    });

    $("#delete").click(function(e) {
        if(confirm('Delete track ' + focus + '?')) {
            var coll = $('INPUT[name=coll]').val() == "on";
            var last = nextTrack()-1;
            for(var j=focus; j<last; j++) {
                $("INPUT[name='track" + j + "' i]").val($("INPUT[name='track" + (j+1) + "' i]").val());
                $("INPUT[name='trackUrl" + j + "' i]").val($("INPUT[name='trackUrl" + (j+1) + "' i]").val());
                if(coll) {
                    $("INPUT[name='artist" + j + "' i]").val($("INPUT[name='artist" + (j+1) + "' i]").val());
                }
            }
            $("INPUT[name='track" + last + "' i]").val("");
            $("INPUT[name='trackUrl" + last + "' i]").val("");
            if(coll) {
                $("INPUT[name='artist" + last + "' i]").val("");
            }
            $("INPUT[name='track" + focus + "' i]").focus();
        }
    });

    $("INPUT:checkbox#all").click(function() {
        var all = $(this).is(":checked");
        $("INPUT:checkbox").prop('checked', all);
    });

    $("#print").click(function() {
        var local = $("#local").val() == 1;
        if(local) {
            var selected = $("INPUT:checkbox:checked").length > 0;
            if(!selected)
                alert("Select at least one tag to proceed");
        } else
            alert('tags can be printed to the label printer only at the station');
        return local && selected;
    });

    $("#printToPDF").click(function() {
        var selected = $("INPUT:checkbox:checked").length > 0;
        if(!selected)
            alert("Select at least one tag to proceed");
        return selected;
    });

    $("#queueform-next").click(function() {
        var selected = $("INPUT:radio:checked").length > 0;
        if(!selected)
            alert("Select a label format");
        return selected;
    });

    $("#queueplace-next").click(function() {
        if(count == 0) {
            alert("Select at least one label to print");
            return false;
        } else
            $("#sel").val(sel.join());

        return true;
    });

    $("A[data-label]").click(function() {
        var elt = $(this);
        var idx = elt.data("label");
        if(max == -1) {
            max = $("#max-count").val();
            sel = Array($("#num-labels").val()*1).fill(0);
        }

        if(sel[idx]) {
            elt.css({
                background: 'white',
                border: 'solid #696969 2px'
            });
            sel[idx] = 0;
            if(count == max) {
                for(i=0; i<sel.length; i++) {
                    if(!sel[i]) {
                        $("#label" + i).css('background', 'white');
                    }
                }
            }
            count--;
        } else {
            if(count == max) return;
            elt.css({
                background: 'beige',
                border: 'solid green 2px'
            });
            sel[idx] = 1;
            count++;
            if(count == max) {
                for(i=0; i<sel.length; i++) {
                    if(!sel[i]) {
                        $("#label" + i).css('background', '#c3c3c3');
                    }
                }
            }
        }
    });

    var list = $("#list").on('keydown', function(e) {
        onKeyDown(list, e);
    });

    $("#search").on('keydown', function(e) {
        if(onKeyDown(list, e)) {
            // input has changed; schedule a search
            timer = setTimeout(function() {
                timer = null;
                onSearchNow();
            }, 250);
        }
    }).on('keypress', function(e) {
        return e.keyCode != 13;
    }).on('cut paste', function() {
        if(!timer) {
            // run on next tick, as pasted data is not yet in the field
            setTimeout(onSearchNow, 0);
        }
    });

    $("#coll").click(function() {
        onSearchNow();
    });

    $("#bup").on('click', function() {
        list.focus();
        return scrollUp();
    });
    $("#bdown").on('click', function() {
        list.focus();
        return scrollDown();
    });

    var actionButton;
    $("input[name='print'], input[name='next'][value='  Done!  ']").on('click', function(e) {
        // if no printer selection, or printer has already been selected,
        // there is nothing to do
        var queue = $("#print-queue");
        if(queue.length == 0 || queue.val().length > 0)
            return;

        // we only print from track screen if album is new
        if($(this).attr('name') == 'next' &&
                $("input[name=new]").val().length == 0)
            return;

        var printerId = sessionStorage.getItem($("#user-uuid").val());
        if(!printerId) {
            actionButton = this;
            $("#select-printer-dialog").show();
            $("#select-printer").focus();
            e.preventDefault();
            return;
        }

        queue.val(printerId);
    });

    $(".zk-popup button").click(function() {
        if($(this).hasClass("default")) {
            var printerId =  $("#select-printer").val();
            sessionStorage.setItem($("#user-uuid").val(), printerId);
            $("#print-queue").val(printerId);
            actionButton.click();
        }

        $(".zk-popup").hide();
        $("*[data-focus]").focus();
    });

    var printStatus = $("#print-status");
    if(printStatus) {
        var queue = sessionStorage.getItem($("#user-uuid").val());
        $.ajax({
            dataType: 'json',
            type: 'GET',
            accept: "application/json; charset=utf-8",
            url: '?action=editor&subaction=status&printqueue=' +
                        (queue ? encodeURIComponent(queue) : "")
        }).done(function (response) {
            printStatus.text(response.text);
        });
    }

    $("*[data-focus]").focus();
});
