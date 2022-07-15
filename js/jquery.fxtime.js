//
// jquery.fxtime -- Firefox-like time element for jQuery
//
// @author Jim Mason <jmason@ibinx.com>
// @copyright Copyright (C) 2022 Jim Mason <jmason@ibinx.com>
// @link https://www.ibinx.com/
// @license MIT
//
// Permission is hereby granted, free of charge, to any person obtaining
// a copy of this software and associated documentation files (the
// "Software"), to deal in the Software without restriction, including
// without limitation the rights to use, copy, modify, merge, publish,
// distribute, sublicense, and/or sell copies of the Software, and to
// permit persons to whom the Software is furnished to do so, subject to
// the following conditions:
//
// The above copyright notice and this permission notice shall be
// included in all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
// EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
// NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
// LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
// OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
// WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
//
// Firefox is a trademark of the Mozilla Foundation ('Mozilla').
// This work incorporates no source code from Firefox or Mozilla,
// and is neither affiliated with nor endorsed by Mozilla.
//

/*! jquery.fxtime (C) 2022 Jim Mason <jmason@ibinx.com> | @source: https://www.ibinx.com/ | @license: magnet:?xt=urn:btih:d3d9a9a6595521f9666a5e94cc830dab83b65699&dn=expat.txt MIT/Expat */


/**
 * jquery.fxtime -- Firefox-like time element for jQuery
 *
 * To install, invoke $.fxtime on the desired elements; e.g.,
 *
 *   $(selector).fxtime();
 *
 *
 * The following methods are available:
 *
 *   $(selector).fxtime('val') - get 24-hour time value
 *       returns null if time is not set
 *
 *   $(selector).fxtime('val', value) - set 24-hour time value
 *       value format is hh:mm:ss, where 0 <= hh <= 23, or null
 *
 *   $(selector).fxtime('seg', seg) - get specified segment value
 *       seg:  0 = hours, 1 = minutes, 2 = seconds, 3 = AM/PM
 *
 *   $(selector).fxtime('seg', seg, value) - set specified segment
 *   $(selector).fxtime('seg', seg, null) - clear specified segment
 *
 *   $(selector).fxtime('inc', seg) - increment specified segment
 *   $(selector).fxtime('inc', seg, -1) - decrement specified segment
 *
 *   $(selector).fxtime('blur', function(seg) {}) - install blur event handler
 *       fires when a segment blurs.  handler receives segment number.
 */
(function($) {
    const intl = new Date().toLocaleTimeString().match(/am|pm/i) == null;

    const max = [ intl ? 23 : 12, 59, 59 ];
    const min = [ intl ? 0 : 1, 0, 0 ];

    function getValue(ctl) {
        var val = null;
        var xval = ctl.val().split(' ');
        if(xval.length == (intl ? 1 : 2)) {
            var oval = xval[0].split(':');
            if(oval.length == 3 &&
                   oval.every(function(val) {
                       return val.match(/^\d+$/);
                   })) {
                var h = oval[0]*1;
                if(!intl) {
                    var ampm = xval[1].toUpperCase();
                    if(ampm == 'PM' && h < 12)
                        oval[0] = h + 12;
                    else if(ampm == 'AM' && h == 12)
                        oval[0] = '00';
                }
                val = oval.join(':');
            }
        }
        return val;
    }

    function setValue(ctl, val) {
        if(val == null || val == '') {
            ctl.val(intl ? '--:--:--' : '--:--:-- AM');
            return;
        }

        var xval = val.split(':');
        if(xval.length == 3) {
            var numh = xval[0].match(/^\d+$/);
            var ampm = !numh || xval[0]*1 < 12 ? 'AM' : 'PM';
            if(numh && !intl) {
                var h = xval[0]*1;
                if(h == 0)
                    xval[0] = '12';
                else if(h > 12)
                    xval[0] = String(h - 12);
            }
            ctl.val(xval.map(x => x.padStart(2, '0')).join(':') + (intl ? '' : ' ' + ampm));
        }
    }

    function getSegmentValue(ctl, seg) {
        var xval = ctl.val().split(' ');
        var oval = xval[0].split(':');
        return seg < 3 ? oval[seg] : xval[1];
    }

    function setSegmentValue(ctl, seg, val) {
        var xval = ctl.val().split(' ');
        var oval = xval[0].split(':');
        switch(seg*1) {
        case 0:
        case 1:
        case 2:
            oval[seg] = val == null ? '--' : String(val).padStart(2, '0');
            break;
        case 3:
            if(!intl)
                xval[1] = val == null ? 'AM' : val;
            break;
        }
        xval[0] = oval.join(':');
        ctl.val(xval.join(' '));
        return this;
    }

    function getSegment(ctl) {
        return Math.floor(ctl.selectionStart / 3);
    }

    function blurSegment(ctl, seg) {
        var inst = $(ctl).data('fxtime');
        inst.seg = false;
        if(typeof seg === 'undefined') {
            seg = getSegment(ctl);
            inst.idx = false;
        }

        $.each(inst.blur, function(index, callback) {
            callback.call(ctl, seg);
        });
    }

    function focusCurrent(ctl) {
        ctl.selectionEnd = ctl.selectionStart;
        var seg = getSegment(ctl);
        var inst = $(ctl).data('fxtime');
        if(inst.idx !== false && inst.idx != seg)
            blurSegment(ctl, inst.idx);
        inst.idx = seg;
        switch(seg) {
        case 0:
            ctl.selectionStart = 0;
            ctl.selectionEnd = 2;
            break;
        case 1:
            ctl.selectionStart = 3;
            ctl.selectionEnd = 5;
            break;
        case 2:
            ctl.selectionStart = 6;
            ctl.selectionEnd = 8;
            break;
        case 3:
            ctl.selectionStart = 9;
            ctl.selectionEnd = 11;
            break;
        }
    }

    function focusSegment(ctl, seg) {
        switch(seg) {
        case 0:
            ctl.selectionStart = 1;
            break;
        case 1:
            ctl.selectionStart = 3;
            break;
        case 2:
            ctl.selectionStart = 6;
            break;
        case 3:
            ctl.selectionStart = 9;
            break;
        }
        focusCurrent(ctl);
    }

    function initialFocus(ctl) {
        var set = false;
        var xval = $(ctl).val().split(' ');
        var val = xval[0].split(':');
        $.each(val, function(index, value) {
            if(!set && !value.match(/^\d+$/)) {
                set = true;
                focusSegment(ctl, index);
            }
        });

        if(!set)
            focusCurrent(ctl);
    }

    function upDownSegment(ctl, up, seg) {
        var start = ctl.selectionStart;
        var xval = $(ctl).val().split(' ');
        var val, oval = xval[0].split(':');
        if(typeof seg === 'undefined')
            seg = getSegment(ctl);
        if(seg == 3)
            xval[1] = xval[1] == 'AM' ? 'PM' : 'AM';
        else {
            if(oval[seg].match(/^\d+$/))
                val = up ? ++oval[seg] : --oval[seg];
            else
                val = up ? min[seg] : max[seg];

            if(val > max[seg])
                val = min[seg];
            else if(val < min[seg])
                val = max[seg];

            if(!intl && seg == 0 && val == (up ? max[0] : max[0] - 1)) {
                setTimeout(function() {
                    upDownSegment(ctl, up, 3);
                }, 0);
            }

            oval[seg] = String(val).padStart(2, '0');
            xval[0] = oval.join(':');
        }
        $(ctl).val(xval.join(' '));
        ctl.selectionEnd = ctl.selectionStart = start;
    }

    function inputSegment(ctl, val) {
        var start = ctl.selectionStart;
        var xval = $(ctl).val().split(' ');
        switch(val) {
        case 'A':
        case 'P':
            if(!intl)
                xval[1] = val == 'A' ? 'AM' : 'PM';
            break;
        default:
            var oval = xval[0].split(':');
            var seg = getSegment(ctl);
            if(seg >= 3)
                break;

            var inst = $(ctl).data('fxtime');
            if(inst.seg === seg && oval[seg].match(/^\d+$/)) {
                var n = oval[seg]*10 + val*1;
                if(n <= max[seg])
                    val = n;
            }

            // use fx semantics -- move focus to next segment when full
            if(inst.seg === seg || val > Math.floor(max[seg] / 10)) {
                if(val < min[seg])
                    val = min[seg];

                var tab = $.Event('keydown');
                tab.which = tab.keyCode = 0x09;
                setTimeout(function() {
                    $(ctl).trigger(tab);
                }, 50);
            } else
                inst.seg = seg;

            oval[seg] = String(val).padStart(2, '0');
            xval[0] = oval.join(':');
            break;
        }
        $(ctl).val(xval.join(' '));
        ctl.selectionEnd = ctl.selectionStart = start;
    }

    function handleKeydown(e) {
        switch(e.which) {
        case 0x09: // tab
        case 0x25: // left
        case 0x27: // right
            var tab = e.which == 0x09;
            var newStart = e.which == 0x25 || tab && e.shiftKey ?
                (this.selectionStart - 3) :
                (this.selectionStart + 3);
            if(newStart < 0) {
                if(tab) {
                    var inputs = $('input:visible, textarea:visible, select:visible, button:visible');
                    inputs.filter(':lt(' + inputs.index(this) + '):last').focus();
                }
            } else if(newStart > (intl ? 8 : 11)) {
                if(tab) {
                    var inputs = $('input:visible, textarea:visible, select:visible, button:visible');
                    inputs.filter(':gt(' + inputs.index(this) + '):first').focus();
                }
            } else
                this.selectionStart = newStart;
            break;
        case 0x26: // up
        case 0xbb: // +
        case 0x3d: // + fx
            upDownSegment(this, true);
            break;
        case 0x28: // down
        case 0xbd: // -
        case 0xad: // - fx
            upDownSegment(this, false);
            break;
        case 0x08: // backspace
        case 0x7f: // delete
        case 0x2e: // delete fx
            var seg = getSegment(this);
            setSegmentValue($(this), seg, null);
            focusSegment(this, seg);
            break;
        default:
            if(e.which >= 0x30 && e.which <= 0x39 ||       // numeric
                    e.which >= 0x60 && e.which <= 0x69 ||  // numeric keypad
                    e.which == 0x41 || e.which == 0x50) {  // A, P
                if(e.which >= 0x60)
                    e.which -= 0x30;
                inputSegment(this, String.fromCharCode(e.which));
            }
            break;
        }
        e.preventDefault();
    }

    $.fn.fxtime = function(action, value, value2) {
        switch(action) {
        case 'val':
            if(typeof value === 'undefined')
                return getValue(this);
            else
                setValue(this, value);
            break;
        case 'seg':
            if(typeof value2 === 'undefined')
                return getSegmentValue(this, value);
            else
                setSegmentValue(this, value, value2);
            break;
        case 'inc':
            upDownSegment(this, typeof value2 == 'undefined' || value2 >= 0, value);
            break;
        case 'blur':
            if(typeof value === 'function')
                this.data('fxtime').blur.push(value);
            break;
        default:
            // each selected element gets its own unique instance data
            this.each(function() {
                $(this).data('fxtime', { idx: false, seg: false, focus: false, blur: [] });
            }).attr('autocomplete', 'off').fxtime('val', null);

            this.select(function(e) {
                if(this.selectionStart != this.selectionEnd - 2)
                    focusCurrent(this);
            }).on("click", function(e) {
                // data.focus is a short-lived state used to synchronize
                // click-initiated focus
                //
                // the idea is that click comes some moments after focus;
                // we don't want the first click to change the default
                // segment, but subsequent clicks may well do.
                var data = $(this).data('fxtime');
                if(data.focus !== false) {
                    focusSegment(this, data.focus);
                    data.focus = false;
                } else
                    focusCurrent(this);
            }).on("focus", function(e) {
                setTimeout(function(ctl) {
                    initialFocus(ctl);

                    var data = $(ctl).data('fxtime');
                    data.focus = getSegment(ctl);
                    setTimeout(function(data) {
                        data.focus = false;
                    }, 250, data);
                }, 0, this);
            }).on("blur", function(e) {
                blurSegment(this);
            }).on("cut copy paste", function(e) {
                e.preventDefault();
            }).keydown(handleKeydown).attr("ondrop", "return false;");
            break;
        };
        return this;
    }
}(jQuery));