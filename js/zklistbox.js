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

/**
 * listbox substitute for multi-line select
 *
 * To install, invoke $.fn.zklistbox on the desired <ul> elements; e.g.,
 *
 *   $(selector).zklistbox();
 *
 * The value chosen by the user is proxied into the input field named
 * by the ul element's data-name attribute.
 *
 * The user's double-clicking an element in the list results in submission
 * of the containing form.
 *
 * The UI depends on styles in zoostyle.css.
 */

(function($) {
    $.fn.zklistbox = function() {
        this.on('keydown', function(e) {
            var cur = $(this).find('.state-active').index();
            switch(e.originalEvent.keyCode) {
            case 13: // enter
                $(this).closest("form").submit();
                e.preventDefault();
                return;
            case 38: // up
                if(cur)
                    cur--;
                e.preventDefault();
                break;
            case 40: // down
                if(cur < $(this).find('li').length - 1)
                    cur++;
                e.preventDefault();
                break;
            }
            $(this).find('li').eq(cur).trigger('mousedown');
        }).find('li').on('mousedown', function() {
            var jqthis = $(this);
            jqthis.siblings('li').removeClass('state-active');
            jqthis.addClass('state-active');
            var name = jqthis.closest('ul').data('name');
            if(name)
                $('input[name=' + name + ']').val(jqthis.data('value'));
        }).on('dblclick', function() {
            $(this).closest('form').submit();
        }).first().trigger('mousedown');

        return this;
    };
}(jQuery));
