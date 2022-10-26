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
 * To install, invoke $.fn.fxtime on the desired elements; e.g.,
 *
 *   $(selector).fxtime();
 *
 * The element uses a 12- or 24-hour format depending on the current locale.
 * By default, only hours and minutes are displayed.
 *
 *
 * The following optional attributes are recognized on the input element:
 *
 *   - step='integer'
 *         to display seconds, specify a step value less than 60
 *
 *   - min='hh:mm[:ss]'
 *         minimum time value (in 24-hour format)
 *
 *   - max='hh:mm[:ss]'
 *         maximum time value (in 24-hour format)
 *
 *   - required
 *
 *   If 'min' and/or 'max' are specified, validation will be
 *   performed against the time value, and the pseudo-classes :valid
 *   and :invalid will be set on the element as appropriate.
 *
 *   If 'required' is specified, :valid will be set if and only
 *   if a valid time value has been entered.
 *
 *
 * The following methods are available:
 *
 *   $(selector).fxtime('val') - get 24-hour time value
 *       returns null if time is not set
 *
 *   $(selector).fxtime('val', value) - set 24-hour time value
 *       value format is hh:mm[:ss], where 0 <= hh <= 23, or null
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
 *
 * The element fires the following custom events:
 *
 *   segblur - fires when a segment blurs
 *       segment number is supplied in the detail.seg property of the event
 *
 * NB: If you want to be notified of changes to the element, listen for
 * 'change' events.  'change' is fired when a user changes the time value,
 * before focus is lost.  The element does NOT fire 'input' events.
 */
(function($) {
    const intl = new Date().toLocaleTimeString().match(/am|pm/i) == null;

    const max = [ intl ? 23 : 12, 59, 59 ];
    const min = [ intl ? 0 : 1, 0, 0 ];

    var fxdata = new WeakMap();

    function triggerChange(ctl) {
        var event = new Event('change', { bubbles: true });
        ctl.dispatchEvent(event);
    }

    function getValue(jqctl) {
        if(jqctl.length > 0) {
            var val = null;
            var ctl = jqctl[0]; // like $.fn.val(), use only the first one
            var xval = ctl.value.split(' ');
            if(xval.length == (intl ? 1 : 2)) {
                var oval = xval[0].split(':');
                if(oval.length == fxdata.get(ctl).count &&
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
    }

    function setValue(jqctl, val) {
        jqctl.each(function() {
            var inst = fxdata.get(this);
            var segCount = inst.count;
            if(val == null || val == '') {
                var segs = Array(segCount).fill('--').join(':');
                this.value = inst.value = segs + (intl ? '' : ' AM');
                this.setCustomValidity(this.required ? 'required' : '');
                return;
            }

            var xval = val.split(':');
            if(xval.length >= segCount) {
                var numh = xval[0].match(/^\d+$/);
                var ampm = !numh || xval[0]*1 < 12 ? 'AM' : 'PM';
                if(numh && !intl) {
                    var h = xval[0]*1;
                    if(h == 0)
                        xval[0] = '12';
                    else if(h > 12)
                        xval[0] = String(h - 12);
                }

                this.value = inst.value = xval.slice(0, segCount)
                        .map(x => x.padStart(2, '0'))
                        .join(':') + (intl ? '' : ' ' + ampm);
                validate(this);
            }
        });
    }

    function getSegmentValue(jqctl, seg) {
        if(jqctl.length > 0) {
            var ctl = jqctl[0]; // like $.fn.val(), use only the first one
            var xval = ctl.value.split(' ');
            var oval = xval[0].split(':');
            return seg < 3 ? oval[seg] : xval[1];
        }
    }

    function setSegmentValue(jqctl, seg, val) {
        jqctl.each(function() {
            var xval = this.value.split(' ');
            var oval = xval[0].split(':');
            switch(seg*1) {
            case 0:
            case 1:
            case 2:
                if(seg < oval.length)
                    oval[seg] = val == null ? '--' : String(val).padStart(2, '0');
                break;
            case 3:
                if(!intl)
                    xval[1] = val == null ? 'AM' : val;
                break;
            }
            xval[0] = oval.join(':');
            this.value = fxdata.get(this).value = xval.join(' ');
            triggerChange(this);
        });
    }

    function getSegment(ctl) {
        var seg = Math.floor(ctl.selectionStart / 3);

        // AM/PM is always segment number 3
        if(seg == 2 && fxdata.get(ctl).count == 2)
            seg++;

        return seg;
    }

    function blurSegment(ctl, seg) {
        var inst = fxdata.get(ctl);
        inst.seg = false;
        if(typeof seg === 'undefined') {
            seg = getSegment(ctl);
            inst.idx = false;
        }

        var event = new CustomEvent('segblur', { detail: { seg: seg } });
        ctl.dispatchEvent(event);
    }

    function focusCurrent(ctl) {
        ctl.selectionEnd = ctl.selectionStart;
        var seg = getSegment(ctl);
        var inst = fxdata.get(ctl);
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
            var hasSeconds = inst.count == 3;
            ctl.selectionStart = hasSeconds ? 9 : 6;
            ctl.selectionEnd = hasSeconds ? 11 : 8;
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
            var hasSeconds = fxdata.get(ctl).count == 3;
            ctl.selectionStart = hasSeconds ? 9 : 6;
            break;
        }
        focusCurrent(ctl);
    }

    function initialFocus(ctl) {
        var xval = ctl.value.split(' ');
        var val = xval[0].split(':');
        var valid = val.every(function(value, index) {
            return value.match(/^\d+$/) || focusSegment(ctl, index);
        });

        if(valid)
            focusCurrent(ctl);
    }

    function upDownSegment(ctl, up, seg) {
        var start = ctl.selectionStart;
        var xval = ctl.value.split(' ');
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
        ctl.value = fxdata.get(ctl).value = xval.join(' ');
        if(ctl == document.activeElement)
            ctl.selectionEnd = ctl.selectionStart = start;
        triggerChange(ctl);
    }

    function inputSegment(ctl, val) {
        var start = ctl.selectionStart;
        var xval = ctl.value.split(' ');
        var inst = fxdata.get(ctl);
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

            if(inst.seg === seg && oval[seg].match(/^\d+$/)) {
                var n = oval[seg]*10 + val*1;
                if(n <= max[seg])
                    val = n;
            }

            // use fx semantics -- move focus to next segment when full
            if(inst.seg === seg || val > Math.floor(max[seg] / 10)) {
                if(val < min[seg])
                    val = min[seg];

                setTimeout(function() {
                    var tab = new KeyboardEvent('keydown',
                                        { bubbles: true, cancelable: true,
                                          which: 0x09, keyCode: 0x09 });
                    ctl.dispatchEvent(tab);
                }, 50);
            } else
                inst.seg = seg;

            oval[seg] = String(val).padStart(2, '0');
            xval[0] = oval.join(':');
            break;
        }
        ctl.value = inst.value = xval.join(' ');
        ctl.selectionEnd = ctl.selectionStart = start;
        triggerChange(ctl);
    }

    function handleKeydown(e) {
        switch(e.which) {
        case 0xba: // :
        case 0x3b: // : fx
            // allow colon to advance only if we've been typing in this segment
            if(fxdata.get(this).seg !== getSegment(this))
                break;
            // fall through...
        case 0x09: // tab
        case 0x25: // left
        case 0x27: // right
            var tab = e.which == 0x09;
            var newStart = e.which == 0x25 || tab && e.shiftKey ?
                (this.selectionStart - 3) :
                (this.selectionStart + 3);

            var limit = 5;
            if(fxdata.get(this).count == 3)
                limit += 3; // has seconds
            if(!intl)
                limit += 3; // has AM/PM

            if(newStart < 0 || newStart > limit) {
                if(tab)
                    return true;
            } else {
                this.selectionStart = newStart;
                focusCurrent(this);
            }
            break;
        case 0x26: // up
        case 0xbb: // +
        case 0x3d: // + fx
            upDownSegment(this, true);
            focusCurrent(this);
            break;
        case 0x28: // down
        case 0xbd: // -
        case 0xad: // - fx
            upDownSegment(this, false);
            focusCurrent(this);
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
                focusCurrent(this);
            }
            break;
        }
        e.preventDefault();
    }

    /**
     * failsafe to filter input delivered by some means other
     * than keydown, such as from a touch device, browser emoji
     * insert, etc.
     */
    function filterInput(e) {
        var c, inst = fxdata.get(this);
        switch(Math.abs(inst.value.length - this.value.length)) {
        case 1: // one character was inserted
            c = this.value.charCodeAt(this.selectionStart - 1);
            switch(c) {
            case 0x41:
            case 0x61:
                c = 0x41; // A
                break;
            case 0x50:
            case 0x70:
                c = 0x50; // P
                break;
            case 0x2b:
            case 0x3d:
                c = 0x26; // +
                break;
            case 0x2d:
            case 0x5f:
                c = 0x28; // -
                break;
            case 0x3a:
                c = 0x3b; // :
                break;
            default:
                if(c < 0x30 || c > 0x39) // non-numeric
                    c = false;
                break;
            }
            break;
        case 2: // one character was deleted
            c = 0x7f; // delete
            break;
        default: // multiple characters were added/deleted
            c = false;
            break;
        }

        // wipe out whatever the user just did and let the
        // keydown handler process the input as needed
        this.value = inst.value;
        focusSegment(this, inst.idx);

        if(c !== false) {
            e.which = e.keyCode = c;
            handleKeydown.call(this, e);
        }
    }

    function newTime(str) {
        var date = str ?
            new Date('2022-01-01T' + str +
                     (str.length < 8 ? ':00' : '') + 'Z') : NaN;
        return isNaN(date) ? null : date;
    }

    function validate(ctl) {
        var time = newTime(getValue([ctl]));
        if(!time) {
            ctl.setCustomValidity(ctl.required ? 'required' : '');
            return;
        }

        var inst = fxdata.get(ctl);
        if(inst.start && time < inst.start) {
            if(!inst.end) {
                ctl.setCustomValidity('out of bounds');
                return;
            } else
                time.setDate(time.getDate() + 1);
        }

        ctl.setCustomValidity(inst.end &&
                              inst.end < time ? 'out of bounds' : '');
    }

    $.fn.fxtime = function(action, value, value2) {
        switch(action) {
        case 'val':
            if(arguments.length == 1)
                return getValue(this);
            else
                setValue(this, value);
            break;
        case 'seg':
            if(arguments.length == 2)
                return getSegmentValue(this, value);
            else if(arguments.length == 3)
                setSegmentValue(this, value, value2);
            break;
        case 'inc':
            if(arguments.length >= 2)
                this.each(function() {
                    upDownSegment(this, typeof value2 === 'undefined' || value2 >= 0, value);
                });
            break;
        default:
            // each selected element gets its own unique instance data
            this.each(function() {
                var start = newTime(this.getAttribute('min'));
                var end = newTime(this.getAttribute('max'));
                if(start && end && end < start)
                    end.setDate(end.getDate() + 1);

                var step = this.getAttribute('step');
                var count = step && step < 60 ? 3 : 2;

                fxdata.set(this, {
                    start: start,
                    end: end,
                    count: count,
                    idx: false,
                    seg: false,
                    focus: false
                });
            }).css('caret-color', 'transparent')
                .css('cursor', 'default')
                .fxtime('val', null);

            this.off().on("select", function(e) {
                if(this.selectionStart != this.selectionEnd - 2)
                    focusCurrent(this);
            }).on("change", function(e) {
                validate(this);
            }).on("click", function(e) {
                // fxdata.focus is a short-lived state used to synchronize
                // click-initiated focus
                //
                // the idea is that click comes some moments after focus;
                // we don't want the first click to change the default
                // segment, but subsequent clicks may well do.
                var data = fxdata.get(this);
                if(data.focus !== false) {
                    focusSegment(this, data.focus);
                    data.focus = false;
                } else
                    focusCurrent(this);
            }).on("focus", function(e) {
                setTimeout(function(ctl) {
                    initialFocus(ctl);

                    var data = fxdata.get(ctl);
                    data.focus = getSegment(ctl);
                    setTimeout(function(data) {
                        data.focus = false;
                    }, 250, data);
                }, 0, this);
            }).on("blur", function(e) {
                blurSegment(this);
            }).on("cut copy paste", function(e) {
                e.preventDefault();
            }).on("keydown", handleKeydown)
                .on("input", filterInput)
                .attr("ondrop", "return false;");
            break;
        };
        return this;
    }
}(jQuery));
