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

/**
 * zktime - Zookeeper Online time element
 *
 * To install, add class 'zktime' to text input element.
 *
 * The following methods are available:
 *
 *   $(selector).zktime('val') - get 24-hour time value
 *       returns null if time is not set
 *
 *   $(selector).zktime('val', value) - set 24-hour time value
 *       value format is hh:mm:ss, where 0 <= hh <= 23
 *
 *   $(selector).zktime('seg', seg) - get specified segment value
 *       seg:  0 = hours, 1 = minutes, 2 = seconds, 3 = AM/PM
 *
 *   $(selector).zktime('seg', seg, value) - set specified segment
 *   $(selector).zktime('seg', seg, null) - clear specified segment
 *
 *   $(selector).zktime('inc', seg) - increment specified segment
 *   $(selector).zktime('inc', seg, -1) - decrement specified segment
 *
 *   $(selector).zktime('blur', function(seg) {}) - install blur event handler
 *       fires when a segment blurs.  handler receives segment number.
 */
(function($) {
    function getValue(ctl) {
        var val = null;
        var xval = ctl.val().split(' ');
        if(xval.length == 2) {
            var oval = xval[0].split(':');
            if(oval.length == 3 &&
                   oval.every(function(val) {
                       return val.match(/^\d+$/);
                   })) {
                var h = oval[0]*1;
                var ampm = xval[1].toUpperCase();
                if(ampm == 'PM' && h < 12)
                    oval[0] = h + 12;
                else if(ampm == 'AM' && h == 12)
                    oval[0] = '00';
                val = oval.join(':');
            }
        }
        return val;
    }

    function setValue(ctl, val) {
        if(val == null || val == '') {
            ctl.val('--:--:-- AM');
            return;
        }

        var xval = val.split(':');
        if(xval.length == 3) {
            var numh = xval[0].match(/^\d+$/);
            var ampm = !numh || xval[0]*1 < 12 ? 'AM' : 'PM';
            if(numh) {
                var h = xval[0]*1;
                if(h == 0)
                    xval[0] = '12';
                else if(h > 12)
                    xval[0] = String(h - 12);
            }
            ctl.val(xval.map(x => x.padStart(2, '0')).join(':') + ' ' + ampm);
        }
    }

    function getSegment(ctl) {
        return Math.floor(ctl.selectionStart / 3);
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

    function getSegmentValue(ctl, seg) {
        var xval = ctl.val().split(' ');
        var oval = xval[0].split(':');
        return seg < 3 ? oval[seg] : xval[1];
    }

    function setSegment(ctl, seg, val) {
        var xval = ctl.val().split(' ');
        var oval = xval[0].split(':');
        switch(seg*1) {
        case 0:
        case 1:
        case 2:
            oval[seg] = val == null ? '--' : String(val).padStart(2, '0');
            break;
        case 3:
            xval[1] = val;
            break;
        }
        xval[0] = oval.join(':');
        ctl.val(xval.join(' '));
        return this;
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

    function focusCurrent(ctl) {
        ctl.selectionEnd = ctl.selectionStart;
        var seg = getSegment(ctl);
        var inst = $(ctl).data('zktime');
        if(inst.idx !== false && inst.idx != seg)
            blurSeg(ctl, inst.idx);
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

    var max = [ 12, 59, 59 ];
    var min = [ 1, 0, 0 ];
    function inputSegment(ctl, val) {
        var start = ctl.selectionStart;
        var oval, xval = $(ctl).val().split(' ');
        var inst, seg;
        switch(val) {
        case 'A':
            xval[1] = 'AM';
            break;
        case 'P':
            xval[1] = 'PM';
            break;
        default:
            oval = xval[0].split(':');
            seg = getSegment(ctl);
            if(seg >= 3)
                break;

            inst = $(ctl).data('zktime');
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
                }, 0);
            } else
                inst.seg = seg;

            oval[seg] = String(val).padStart(2, '0');
            xval[0] = oval.join(':');
            break;
        }
        $(ctl).val(xval.join(' '));
        ctl.selectionEnd = ctl.selectionStart = start;
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

            if(seg == 0 && val == (up ? max[0] : max[0] - 1)) {
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
                    var inputs = $(this).closest('form').find('input:visible, textarea:visible, button:visible');
                    inputs.filter(':lt(' + inputs.index(this) + '):last').focus();
                }
            } else if(newStart > 11) {
                if(tab) {
                    var inputs = $(this).closest('form').find('input:visible, textarea:visible, button:visible');
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
            switch(this.selectionStart) {
            case 0:
            case 1:
                $(this).val('--:' + $(this).val().substring(3));
                this.selectionStart = 0;
                this.selectionEnd = 2;
                break;
            case 3:
            case 4:
                $(this).val($(this).val().substring(0, 3) + '--:' + $(this).val().substring(6));
                this.selectionStart = 3;
                break;
            case 6:
            case 7:
                $(this).val($(this).val().substring(0, 6) + '--' + $(this).val().substring(8));
                this.selectionStart = 6;
                break;
            }
            break;
        default:
            if(e.which >= 0x30 && e.which <= 0x39 ||
                    e.which >= 0x60 && e.which <= 0x69 ||
                    e.which == 0x41 || e.which == 0x50) {
                if(e.which >= 0x60)
                    e.which -= 0x30;
                inputSegment(this, String.fromCharCode(e.which));
            }
        }
        e.preventDefault();
    }

    function blurSeg(ctl, seg) {
        var inst = $(ctl).data('zktime');
        inst.seg = false;
        if(typeof seg === 'undefined') {
            seg = getSegment(ctl);
            inst.idx = false;
        }

        $.each(inst.blur, function(index, callback) {
            callback.call(ctl, seg);
        });
    }

    $.fn.zktime = function(action, value, value2) {
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
                setSegment(this, value, value2);
            break;
        case 'inc':
            upDownSegment(this, typeof value2 == 'undefined' || value2 >= 0, value);
            break;
        case 'blur':
            if(typeof value === 'function')
                this.data('zktime').blur.push(value);
            break;
        default:
            this.each(function() {
                $(this).data('zktime', { idx: false, seg: false, blur: [] }).zktime('val', null);
            });
            this.select(function(e) {
                if(this.selectionStart != this.selectionEnd - 2)
                    focusCurrent(this);
            }).on("click", function(e) {
                focusCurrent(this);
            }).on("focus", function(e) {
                setTimeout(function(ctl) {
                    initialFocus(ctl);
                }, 0, this);
            }).on("blur", function(e) {
                blurSeg(this);
            }).on("cut copy paste", function(e) {
                e.preventDefault();
            }).keydown(handleKeydown).attr("ondrop", "return false;");
            break;
        };
        return this;
    }

    $().ready(function() {
        $("input.zktime").zktime();
    });
}(jQuery));
