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

/*! Zookeeper Online (C) 1997-2023 Jim Mason <jmason@ibinx.com> | @source: https://zookeeper.ibinx.com/ | @license: magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3.0 */

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
                else if(key == 'url' && val.indexOf('http') != 0)
                    html += 'https://';
                html += val + '"';
                if(key == 'url')
                    html += ' TARGET="_blank"';
                html += '>' + htmlify(val) + '</A>';
                field.html(html);
            } else {
                field.html(htmlify(key != 'bin' || items[index].attributes['location'] == 'Storage' ? val : ''));
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
    const NONALNUM=/([^\p{L}\d'\u{2019}])/u;
    const STOPWORDS=/^(a|an|and|at|but|by|for|in|nor|of|on|or|out|so|the|to|up|yet)$/i;
    $.fn.zkAlpha = function() {
        var val=this.val();
        var track=this.data("zkalpha") === true;
        var newVal=val.split(NONALNUM).map(function(word, index, array) {
            // words starting with caps are kept as-is
            if(word.search(/^\p{Lu}/u) > -1)
                return word;

            // stopwords are not capitalized, unless first or last
            if(word.search(STOPWORDS) > -1 &&
                    index !== 0 &&
                    index !== array.length - 1 &&
                    array[index - 1].match(/\s/))
                return word.toLowerCase();

            // otherwise, capitalize the word
            return word.charAt(0).toUpperCase() +
                   word.substr(1).toLowerCase();
        }).join('');
        if(track != true && newVal.substr(0, 4) == 'The ')
            newVal=newVal.substr(4)+', The';
        this.val(newVal);
        return this;
    }

    $("INPUT[data-zkalpha]").on('change', function(e) {
        $(this).zkAlpha();
    });
    $("INPUT[data-track]").on('focus', function(e) {
        focus = $(this).data("track");
    });
    $("INPUT[data-upper]").on('change', function(e) {
        $(this).val($(this).val().toUpperCase());
    });

    function nextTrack() {
        var form = document.getElementById('editor');
        for(var i=1; typeof(eval('form.track'+i)) != 'undefined'; i++);
        return i;
    }

    $("#comp").on('click', function(e) {
        var disabled = $(this).is(":checked");
        $("INPUT[name=artist], #lartist").css("visibility", disabled ? 'hidden' : 'visible');
        $("INPUT[name=" + (disabled ? "album" : "artist") + "]").trigger('focus');
    });

    $("#location").on('change selectmenuchange', function(e) {
        var storage = $("SELECT[name=location]").val() == 'G';
        $("INPUT[name=bin], #lbin").css("visibility", storage ? 'visible' : 'hidden');
        if(storage)
            $("INPUT[name=bin]").trigger('focus');
    });

    $("#foreign").on('click', function(e) {
        var foreign = $("INPUT[name=foreign]").is(":checked");
        $("#lstate").css("visibility", foreign?'hidden':'visible');
        $("#lzip").html(foreign?'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Country:':'Postal Code:');
    });

    $("#insert").on('click', function(e) {
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
            $("INPUT[name='trackDuration" + j + "' i]").val($("INPUT[name='trackDuration" + (j-1) + "' i]").val());
            $("INPUT[name='trackUrl" + j + "' i]").val($("INPUT[name='trackUrl" + (j-1) + "' i]").val());
            if(coll) {
                $("INPUT[name='artist" + j + "' i]").val($("INPUT[name='artist" + (j-1) + "' i]").val());
            }
        }
        $("INPUT[name='track" + focus + "' i]").val("");
        $("INPUT[name='trackDuration" + focus + "' i]").val("");
        $("INPUT[name='trackUrl" + focus + "' i]").val("");
        if(coll) {
            $("INPUT[name='artist" + focus + "' i]").val("");
        }
        $("INPUT[name='track" + focus + "' i]").trigger('focus');
    });

    $("#delete").on('click', function(e) {
        if(confirm('Delete track ' + focus + '?')) {
            var coll = $('INPUT[name=coll]').val() == "on";
            var last = nextTrack()-1;
            for(var j=focus; j<last; j++) {
                $("INPUT[name='track" + j + "' i]").val($("INPUT[name='track" + (j+1) + "' i]").val());
                $("INPUT[name='trackDuration" + j + "' i]").val($("INPUT[name='trackDuration" + (j+1) + "' i]").val());
                $("INPUT[name='trackUrl" + j + "' i]").val($("INPUT[name='trackUrl" + (j+1) + "' i]").val());
                if(coll) {
                    $("INPUT[name='artist" + j + "' i]").val($("INPUT[name='artist" + (j+1) + "' i]").val());
                }
            }
            $("INPUT[name='track" + last + "' i]").val("");
            $("INPUT[name='trackDuration" + last + "' i]").val("");
            $("INPUT[name='trackUrl" + last + "' i]").val("");
            if(coll) {
                $("INPUT[name='artist" + last + "' i]").val("");
            }
            $("INPUT[name='track" + focus + "' i]").trigger('focus');
        }
    });

    $("INPUT:checkbox#all").on('click', function() {
        var all = $(this).is(":checked");
        $("INPUT:checkbox").prop('checked', all);
    });

    $("#print").on('click', function() {
        var local = $("#local").val() == 1;
        if(local) {
            var selected = $("INPUT:checkbox:checked").length > 0;
            if(!selected)
                alert("Select at least one tag to proceed");
        } else
            alert('tags can be printed to the label printer only at the station');
        return local && selected;
    });

    $("#printToPDF").on('click', function() {
        var selected = $("INPUT:checkbox:checked").length > 0;
        if(!selected)
            alert("Select at least one tag to proceed");
        return selected;
    });

    $("#queueform-next").on('click', function() {
        var selected = $("INPUT:radio:checked").length > 0;
        if(!selected)
            alert("Select a label format");
        return selected;
    });

    $("#queueplace-next").on('click', function() {
        if(count == 0) {
            alert("Select at least one label to print");
            return false;
        } else
            $("#sel").val(sel.join());

        return true;
    });

    $("A[data-label]").on('click', function() {
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

    $("#coll").on('click', function() {
        onSearchNow();
    });

    $("#bup").on('click', function() {
        list.trigger('focus');
        return scrollUp();
    });
    $("#bdown").on('click', function() {
        list.trigger('focus');
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
            $("#select-printer").trigger('focus');
            e.preventDefault();
            return;
        }

        queue.val(printerId);
    });

    $(".zk-popup button").on('click', function() {
        if($(this).hasClass("default")) {
            var printerId =  $("#select-printer").val();
            sessionStorage.setItem($("#user-uuid").val(), printerId);
            $("#print-queue").val(printerId);
            actionButton.trigger('click');
        }

        $(".zk-popup").hide();
        $("*[data-focus]").trigger('focus');
    });

    var printStatus = $("#print-status");
    if(printStatus.length > 0) {
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

    if($("input[name=seq]").val() == "tracks" &&
            $("input[name=tdb]").length == 0 && mediaTypes) {
        var url = "?action=editor&subaction=prefill&album=" +
            encodeURIComponent($("input[name=album]").val()) +
            "&medium=" + encodeURIComponent($("input[name=medium]").val());
        if($("input[name=coll]").length == 0)
            url += "&artist=" + encodeURIComponent($("input[name=artist]").val());

        $.ajax({
            dataType: 'json',
            type: 'GET',
            accept: 'application/json; charset=utf-8',
            url: url
        }).done(function(response) {
            if(response.tracks) {
                var form=$("form");
                response.tracks.forEach(function(track) {
                    var input = $("input[name=track" + track.seq + "]");
                    if(input.length) {
                        input.val(track.title.toLowerCase()).zkAlpha();
                        $("input[name=trackDuration" + track.seq + "]").val(track.time ?? '');
                        $("input[name=trackUrl" + track.seq + "]").val(track.url ?? '');
                        if(track.artist)
                            $("input[name=artist" + track.seq + "]").val(track.artist);
                    } else {
                        form.append($("<input>", {
                            type: 'hidden',
                            name: 'track' + track.seq,
                            class: 'prefill',
                            value: track.title.toLowerCase(),
                            'data-zkalpha': 'true'
                        }).zkAlpha()).append($("<input>", {
                            type: 'hidden',
                            name: 'trackDuration' + track.seq,
                            class: 'prefill',
                            value: track.time ?? ''
                        })).append($("<input>", {
                            type: 'hidden',
                            name: 'trackUrl' + track.seq,
                            class: 'prefill',
                            value: track.url ?? ''
                        }));
                        if(track.artist) {
                            form.append($("<input>", {
                                type: 'hidden',
                                name: 'artist' + track.seq,
                                class: 'prefill',
                                value: track.artist
                            }));
                        }
                    }
                });

                form.append($("<input>", {
                    type: 'hidden',
                    name: 'tdb',
                    class: 'prefill',
                    value: 'true'
                })).append($("<input>", {
                    type: 'hidden',
                    name: 'infoUrl',
                    class: 'prefill',
                    value: response.infoUrl
                }));
                if(response.imageUrl) {
                    form.append($("<input>", {
                        type: 'hidden',
                        name: 'imageUrl',
                        class: 'prefill',
                        value: response.imageUrl
                    }));
                }

                $(".discogs-prefill-confirm").slideDown();
                $(".clear-prefill").removeClass('zk-hidden');
            } else {
                $("#discogs-no-match-album").text($("input[name=album]").val());
                $(".discogs-no-match").slideDown();
            }
        });
    }

    $(".clear-prefill").on('click', function(e) {
        e.preventDefault();
        if(confirm("Clear all tracks?")) {
            $("input.text").val('');
            $("input.prefill").remove();

            $(".discogs-prefill-confirm").slideUp();
            $(".clear-prefill").addClass('zk-hidden');

            $("*[data-focus]").trigger('focus');
        }
    });

    $(".discogs-prefill").on('click', function(e) {
        e.preventDefault();
        var url = "?action=editor&subaction=prefill&album=" +
            encodeURIComponent($("input[name=album]").val()) +
            "&medium=" + encodeURIComponent($("input[name=medium]").val());
        if($("input[name=coll]").length == 0)
            url += "&artist=" + encodeURIComponent($("input[name=artist]").val());

        $.ajax({
            dataType: 'json',
            type: 'GET',
            accept: 'application/json; charset=utf-8',
            url: url
        }).done(function(response) {
            var state = 0; // bit 0 = match, bit 1 = set, bit 2 = mismatch
            if(response.tracks) {
                state = 0x1;
                response.tracks.forEach(function(track) {
                    if(track.url) {
                        var trackurl = $("input[name=trackUrl" + track.seq + "]");
                        if(!trackurl.val()) {
                            if($("input[name=track" + track.seq + "]").val().substring(0, 3) == track.title.substring(0, 3)) {
                                trackurl.val(track.url);
                                state |= 0x2;
                            } else
                                state |= 0x4;
                        }
                    }
                });
            }

            // display alert after fields have populated
            setTimeout(function() {
                alert(state ?
                  (/*state & 0x4 ? "Check Track Names and try again" :*/
                   (state & 0x2 ? "Updated URLs.  Press 'Done!' to save." :
                    "No new URLs found")) :
                      "Album not found in Discogs");
            }, 100);
        });
    });

    $("select.textsp").selectmenu();

    $("*[data-focus]").trigger('focus');
});
