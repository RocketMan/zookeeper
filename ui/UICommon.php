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
    const CHARSET_ASCII = 0;
    const CHARSET_LATIN1 = 1;
    const CHARSET_UTF8 = 2;

    private static $latinExtendedA = [
        "\u{100}"=>"A", "\u{102}"=>"A", "\u{104}"=>"A",
        "\u{101}"=>"a", "\u{103}"=>"a", "\u{105}"=>"a",
        "\u{106}"=>"C", "\u{108}"=>"C", "\u{10a}"=>"C", "\u{10c}"=>"C",
        "\u{107}"=>"c", "\u{109}"=>"c", "\u{10b}"=>"c", "\u{10d}"=>"c",
        "\u{10e}"=>"D", "\u{110}"=>"D",
        "\u{10f}"=>"d", "\u{111}"=>"d",
        "\u{112}"=>"E", "\u{114}"=>"E", "\u{116}"=>"E", "\u{118}"=>"E",
        "\u{11a}"=>"E",
        "\u{113}"=>"e", "\u{115}"=>"e", "\u{117}"=>"e", "\u{119}"=>"e",
        "\u{11b}"=>"e",
        "\u{11c}"=>"G", "\u{11e}"=>"G", "\u{120}"=>"G", "\u{122}"=>"G",
        "\u{11d}"=>"g", "\u{11f}"=>"g", "\u{121}"=>"g", "\u{123}"=>"g",
        "\u{124}"=>"H", "\u{126}"=>"H", "\u{125}"=>"h", "\u{127}"=>"h",
        "\u{128}"=>"I", "\u{12a}"=>"I", "\u{12c}"=>"I", "\u{12e}"=>"I",
        "\u{130}"=>"I",
        "\u{129}"=>"i", "\u{12b}"=>"i", "\u{12d}"=>"i", "\u{12f}"=>"i",
        "\u{131}"=>"i",
        "\u{132}"=>"IJ", "\u{133}"=>"ij",
        "\u{134}"=>"J", "\u{135}"=>"j",
        "\u{136}"=>"K", "\u{137}"=>"k", "\u{138}"=>"k",
        "\u{139}"=>"L", "\u{13b}"=>"L", "\u{13d}"=>"L", "\u{13f}"=>"L",
        "\u{141}"=>"L",
        "\u{13a}"=>"l", "\u{13c}"=>"l", "\u{13e}"=>"l", "\u{140}"=>"l",
        "\u{142}"=>"l",
        "\u{143}"=>"N", "\u{145}"=>"N", "\u{147}"=>"N",
        "\u{144}"=>"n", "\u{146}"=>"n", "\u{148}"=>"n",
        "\u{14a}"=>"NG", "\u{14b}"=>"ng",
        "\u{14c}"=>"O", "\u{14e}"=>"O", "\u{150}"=>"O",
        "\u{14d}"=>"o", "\u{14f}"=>"o", "\u{151}"=>"o",
        "\u{152}"=>"OE", "\u{153}"=>"oe",
        "\u{154}"=>"R", "\u{156}"=>"R", "\u{158}"=>"R",
        "\u{155}"=>"r", "\u{157}"=>"r", "\u{159}"=>"r",
        "\u{15a}"=>"S", "\u{15c}"=>"S", "\u{15e}"=>"S", "\u{160}"=>"S",
        "\u{15b}"=>"s", "\u{15d}"=>"s", "\u{15f}"=>"s", "\u{161}"=>"s",
        "\u{17f}"=>"s",
        "\u{162}"=>"T", "\u{164}"=>"T", "\u{166}"=>"T",
        "\u{163}"=>"t", "\u{165}"=>"t", "\u{167}"=>"t",
        "\u{168}"=>"U", "\u{16a}"=>"U", "\u{16c}"=>"U", "\u{16e}"=>"U",
        "\u{170}"=>"U", "\u{172}"=>"U",
        "\u{169}"=>"u", "\u{16b}"=>"u", "\u{16d}"=>"u", "\u{16f}"=>"u",
        "\u{171}"=>"u", "\u{173}"=>"u",
        "\u{174}"=>"W", "\u{175}"=>"w",
        "\u{176}"=>"Y", "\u{178}"=>"Y", "\u{177}"=>"y",
        "\u{179}"=>"Z", "\u{17b}"=>"Z", "\u{17d}"=>"Z",
        "\u{17a}"=>"z", "\u{17c}"=>"z", "\u{17e}"=>"z",
    ];

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
    
        $arg = htmlentities($arg, ENT_QUOTES, 'UTF-8');
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

    public static function deLatin1ify($string,
                                    $charset=UICommon::CHARSET_ASCII) {
        // input is already UTF-8
        if($charset == UICommon::CHARSET_UTF8)
            return $string;

        // flatten latin extended to latin1
        $string = strtr($string, self::$latinExtendedA);

        // convert remaining code points to latin1
        $string = mb_convert_encoding($string, 'ISO-8859-1', 'UTF-8');

        if($charset == UICommon::CHARSET_LATIN1)
            return $string;

        // flatten latin1 to ASCII
        $string = strtr($string,
             "\x91\x92\x93\x94\x96\x97\xa1\xaa\xba\xbf\xc0\xc1\xc2\xc3\xc5\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd8\xd9\xda\xdb\xdd\xe0\xe1\xe2\xe3\xe5\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf8\xf9\xfa\xfb\xfd\xff",
             "''\"\"--!ao?AAAAACEEEEIIIIDNOOOOOUUUYaaaaaceeeeiiiidnooooouuuyy"); 
        $string = strtr($string, "\xc4\xc6\xd6\xdc\xe4\xe6\xf6\xfc",
                                   "AAOUaaou");
        $string = strtr($string,
              [ "\x85"=>"...", "\xde"=>"Th", "\xdf"=>"ss", "\xfe"=>"th" ] );

        return $string;
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
