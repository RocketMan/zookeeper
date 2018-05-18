<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2018 Jim Mason <jmason@ibinx.com>
 * @link https://zookeeper.ibinx.com/
 * @license GPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License,
 * version 3, along with this program.  If not, see
 * http://www.gnu.org/licenses/
 *
 */

namespace ZK\UI;

class UICommon {
    /**
     * return the URL of the current request, less leaf filename, if any
     */
    public static function getBaseUrl() {
        $uri = $_SERVER['REQUEST_URI'];
    
        // strip the query string, if any
        $qpos = strpos($uri, "?");
        if($qpos !== false)
            $uri = substr($uri, 0, $qpos);
    
        // compose the URL
        return $_SERVER['REQUEST_SCHEME'] . "://" .
               $_SERVER['SERVER_NAME'] .
               preg_replace("{/[^/]+$}", "/", $uri);
    }
    
    /**
     * encode the specified argument for inclusion in a URL
     *
     * semantics of urlencode, but also encodes double quotes (")
     * and strips LFs
     */
    public static function URLify($arg) {
        $arg = urlencode($arg);
        $arg = preg_replace("/\"/", "%22", $arg);
        $arg = preg_replace("/\n/", "", $arg);
        return $arg;
    }
    
    /**
     * convert arg to HTML
     */
    public static function HTMLify($arg, $size) {
        global $noTables;
        if ($noTables) {
            # truncate/pad output for non-table browsers
            $format = "%-" . $size . "s ";
            $arg = sprintf($format, substr($arg, 0, $size));
        }
    
        $arg = htmlentities($arg, ENT_QUOTES, 'ISO-8859-1');
        $arg = str_replace("\241", "&iexcl;", $arg);
        $arg = str_replace("\223", "&#8220;", $arg);   // ldquot
        $arg = str_replace("\224", "&#8221;", $arg);   // rdquot
        $arg = str_replace("\205", "...", $arg);
        $arg = str_replace("\226", "&#8211;", $arg);   // en dash
        $arg = str_replace("\227", "&#8212;", $arg);   // em dash
        $arg = str_replace("\221", "&#8216;", $arg);   // lsquot
        return str_replace("\222", "&#8217;", $arg);   // rsquot
    }
    
    /**
     * convert numeric arg to HTML
     * 
     * (This method just returns arg for table browsers.)
     */
    public static function HTMLifyNum($arg, $size) {
        global $noTables;
        if ($noTables) {
            # right justify output for non-table browsers
            $format = "%" . $size . "d ";
            $arg = sprintf($format, $arg);
        }
        return $arg;
    }

    public static function isNumeric($target) {
        return !$target || preg_replace("/[0-9\-]/", "", $target) == "";
    }

    public static function deLatin1ify($string) {
        $string = mb_convert_encoding($string, 'ISO-8859-1');
        $string = strtr($string,
             "\x91\x92\x93\x94\x96\x97\xa1\xaa\xba\xbf\xc0\xc1\xc2\xc3\xc5\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd8\xd9\xda\xdb\xdd\xe0\xe1\xe2\xe3\xe5\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf8\xf9\xfa\xfb\xfd\xff",
             "''\"\"--!ao?AAAAACEEEEIIIIDNOOOOOUUUYaaaaaceeeeiiiidnooooouuuyy"); 
        $string = strtr($string, "\xc4\xc6\xd6\xdc\xdf\xe4\xe6\xf6\xfc",
                                   "AAOUsaaou");
        $string = strtr($string, array("\x85"=>"...", "\xde"=>"/Th/", "\xfe"=>"/th/"));
        return($string);
    }
    
    /**
     * emit in-line JavaScript setFocus function
     *
     * @param control input field to receive focus (or none)
     */
    public static function setFocus($control = "") {
        echo "<SCRIPT LANGUAGE=\"JavaScript\" TYPE=\"text/javascript\"><!--\n";
        echo "    function setFocus() {\n";
        if($control)
            echo "      document.forms[0].$control.focus();\n";
        echo "    }\n    // -->\n    </SCRIPT>\n";
    }
}
