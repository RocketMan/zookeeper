<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2019 Jim Mason <jmason@ibinx.com>
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
        /*"\u{100}"*/ "\xc4\x80"=>"A",  /*"\u{102}"*/ "\xc4\x82"=>"A",
        /*"\u{104}"*/ "\xc4\x84"=>"A",  /*"\u{105}"*/ "\xc4\x85"=>"a",
        /*"\u{101}"*/ "\xc4\x81"=>"a",  /*"\u{103}"*/ "\xc4\x83"=>"a",
        /*"\u{106}"*/ "\xc4\x86"=>"C",  /*"\u{108}"*/ "\xc4\x88"=>"C",
        /*"\u{10a}"*/ "\xc4\x8a"=>"C",  /*"\u{10c}"*/ "\xc4\x8c"=>"C",
        /*"\u{107}"*/ "\xc4\x87"=>"c",  /*"\u{109}"*/ "\xc4\x89"=>"c",
        /*"\u{10b}"*/ "\xc4\x8b"=>"c",  /*"\u{10d}"*/ "\xc4\x8d"=>"c",
        /*"\u{10e}"*/ "\xc4\x8e"=>"D",  /*"\u{110}"*/ "\xc4\x90"=>"D",
        /*"\u{10f}"*/ "\xc4\x8f"=>"d",  /*"\u{111}"*/ "\xc4\x91"=>"d",
        /*"\u{112}"*/ "\xc4\x92"=>"E",  /*"\u{114}"*/ "\xc4\x94"=>"E",
        /*"\u{116}"*/ "\xc4\x96"=>"E",  /*"\u{118}"*/ "\xc4\x98"=>"E",
        /*"\u{11a}"*/ "\xc4\x9a"=>"E",  /*"\u{11b}"*/ "\xc4\x9b"=>"e",
        /*"\u{113}"*/ "\xc4\x93"=>"e",  /*"\u{115}"*/ "\xc4\x95"=>"e",
        /*"\u{117}"*/ "\xc4\x97"=>"e",  /*"\u{119}"*/ "\xc4\x99"=>"e",
        /*"\u{11c}"*/ "\xc4\x9c"=>"G",  /*"\u{11e}"*/ "\xc4\x9e"=>"G",
        /*"\u{120}"*/ "\xc4\xa0"=>"G",  /*"\u{122}"*/ "\xc4\xa2"=>"G",
        /*"\u{11d}"*/ "\xc4\x9d"=>"g",  /*"\u{11f}"*/ "\xc4\x9f"=>"g",
        /*"\u{121}"*/ "\xc4\xa1"=>"g",  /*"\u{123}"*/ "\xc4\xa3"=>"g",
        /*"\u{124}"*/ "\xc4\xa4"=>"H",  /*"\u{126}"*/ "\xc4\xa6"=>"H",
        /*"\u{125}"*/ "\xc4\xa5"=>"h",  /*"\u{127}"*/ "\xc4\xa7"=>"h",
        /*"\u{128}"*/ "\xc4\xa8"=>"I",  /*"\u{12a}"*/ "\xc4\xaa"=>"I",
        /*"\u{12c}"*/ "\xc4\xac"=>"I",  /*"\u{12e}"*/ "\xc4\xae"=>"I",
        /*"\u{130}"*/ "\xc4\xb0"=>"I",  /*"\u{131}"*/ "\xc4\xb1"=>"i",
        /*"\u{129}"*/ "\xc4\xa9"=>"i",  /*"\u{12b}"*/ "\xc4\xab"=>"i",
        /*"\u{12d}"*/ "\xc4\xad"=>"i",  /*"\u{12f}"*/ "\xc4\xaf"=>"i",
        /*"\u{132}"*/ "\xc4\xb2"=>"IJ", /*"\u{133}"*/ "\xc4\xb3"=>"ij",
        /*"\u{134}"*/ "\xc4\xb4"=>"J",  /*"\u{135}"*/ "\xc4\xb5"=>"j",
        /*"\u{136}"*/ "\xc4\xb6"=>"K",  /*"\u{137}"*/ "\xc4\xb7"=>"k",
        /*"\u{138}"*/ "\xc4\xb8"=>"k",
        /*"\u{139}"*/ "\xc4\xb9"=>"L",  /*"\u{13b}"*/ "\xc4\xbb"=>"L",
        /*"\u{13d}"*/ "\xc4\xbd"=>"L",  /*"\u{13f}"*/ "\xc4\xbf"=>"L",
        /*"\u{141}"*/ "\xc5\x81"=>"L",  /*"\u{142}"*/ "\xc5\x82"=>"l",
        /*"\u{13a}"*/ "\xc4\xba"=>"l",  /*"\u{13c}"*/ "\xc4\xbc"=>"l",
        /*"\u{13e}"*/ "\xc4\xbe"=>"l",  /*"\u{140}"*/ "\xc5\x80"=>"l",
        /*"\u{143}"*/ "\xc5\x83"=>"N",  /*"\u{145}"*/ "\xc5\x85"=>"N",
        /*"\u{147}"*/ "\xc5\x87"=>"N",  /*"\u{148}"*/ "\xc5\x88"=>"n",
        /*"\u{144}"*/ "\xc5\x84"=>"n",  /*"\u{146}"*/ "\xc5\x86"=>"n",
        /*"\u{14a}"*/ "\xc5\x8a"=>"NG", /*"\u{14b}"*/ "\xc5\x8b"=>"ng",
        /*"\u{14c}"*/ "\xc5\x8c"=>"O",  /*"\u{14e}"*/ "\xc5\x8e"=>"O",
        /*"\u{150}"*/ "\xc5\x90"=>"O",  /*"\u{151}"*/ "\xc5\x91"=>"o",
        /*"\u{14d}"*/ "\xc5\x8d"=>"o",  /*"\u{14f}"*/ "\xc5\x8f"=>"o",
        /*"\u{152}"*/ "\xc5\x92"=>"OE", /*"\u{153}"*/ "\xc5\x93"=>"oe",
        /*"\u{154}"*/ "\xc5\x94"=>"R",  /*"\u{156}"*/ "\xc5\x96"=>"R",
        /*"\u{158}"*/ "\xc5\x98"=>"R",  /*"\u{159}"*/ "\xc5\x99"=>"r",
        /*"\u{155}"*/ "\xc5\x95"=>"r",  /*"\u{157}"*/ "\xc5\x97"=>"r",
        /*"\u{15a}"*/ "\xc5\x9a"=>"S",  /*"\u{15c}"*/ "\xc5\x9c"=>"S",
        /*"\u{15e}"*/ "\xc5\x9e"=>"S",  /*"\u{160}"*/ "\xc5\xa0"=>"S",
        /*"\u{15b}"*/ "\xc5\x9b"=>"s",  /*"\u{15d}"*/ "\xc5\x9d"=>"s",
        /*"\u{15f}"*/ "\xc5\x9f"=>"s",  /*"\u{161}"*/ "\xc5\xa1"=>"s",
        /*"\u{17f}"*/ "\xc5\xbf"=>"s",
        /*"\u{162}"*/ "\xc5\xa2"=>"T",  /*"\u{164}"*/ "\xc5\xa4"=>"T",
        /*"\u{166}"*/ "\xc5\xa6"=>"T",  /*"\u{167}"*/ "\xc5\xa7"=>"t",
        /*"\u{163}"*/ "\xc5\xa3"=>"t",  /*"\u{165}"*/ "\xc5\xa5"=>"t",
        /*"\u{168}"*/ "\xc5\xa8"=>"U",  /*"\u{16a}"*/ "\xc5\xaa"=>"U",
        /*"\u{16c}"*/ "\xc5\xac"=>"U",  /*"\u{16e}"*/ "\xc5\xae"=>"U",
        /*"\u{170}"*/ "\xc5\xb0"=>"U",  /*"\u{172}"*/ "\xc5\xb2"=>"U",
        /*"\u{169}"*/ "\xc5\xa9"=>"u",  /*"\u{16b}"*/ "\xc5\xab"=>"u",
        /*"\u{16d}"*/ "\xc5\xad"=>"u",  /*"\u{16f}"*/ "\xc5\xaf"=>"u",
        /*"\u{171}"*/ "\xc5\xb1"=>"u",  /*"\u{173}"*/ "\xc5\xb3"=>"u",
        /*"\u{174}"*/ "\xc5\xb4"=>"W",  /*"\u{175}"*/ "\xc5\xb5"=>"w",
        /*"\u{176}"*/ "\xc5\xb6"=>"Y",  /*"\u{178}"*/ "\xc5\xb8"=>"Y",
        /*"\u{177}"*/ "\xc5\xb7"=>"y",
        /*"\u{179}"*/ "\xc5\xb9"=>"Z",  /*"\u{17b}"*/ "\xc5\xbb"=>"Z",
        /*"\u{17d}"*/ "\xc5\xbd"=>"Z",  /*"\u{17e}"*/ "\xc5\xbe"=>"z",
        /*"\u{17a}"*/ "\xc5\xba"=>"z",  /*"\u{17c}"*/ "\xc5\xbc"=>"z",
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

        $port = ":" . $_SERVER['SERVER_PORT'];
        if($port == ":443" || $port == ":80")
            $port = "";
    
        // compose the URL
        return $_SERVER['REQUEST_SCHEME'] . "://" .
               $_SERVER['SERVER_NAME'] . $port .
               preg_replace("{/[^/]+$}", "/", $uri);
    }
    
    public static function startsWith($string, $startString) {
        $len = strlen($startString); 
        return (substr($string, 0, $len) === $startString); 
    }

    /**
     * return the specified markdown as html
     *
     * html found in the input text is automatically escaped;
     * newlines are automatically converted to line breaks.
     */
    public static function markdown($text) {
        return \Parsedown::instance()->
               setBreaksEnabled(true)->
               setSafeMode(true)->
               text($text);
    }

    public static function markdownHelp() { ?>

        <div id='markdown-help' class='markdown-help'>
          <table>
            <tr><th>type this:</th><th>to get this:</th></tr>
            <tr><td>*italics*</td><td><I>italics</I></td></tr>
            <tr><td>**bold**</td><td><b>bold</b></td></tr>
            <tr><td>* item 1<br>* item 2<br>* item 3</td><td><ul><li>item 1</li><li>item 2</li><li>item 3</li></ul></td></tr>
            <tr><td>1. item 1<br>2. item 2<br>3. item 3</td><td><ol><li>item 1</li><li>item 2</li><li>item 3</li></ol></td></tr>
            <tr><td>## heading</td><td><H2>heading</H2></td></tr>
            <tr><td style="font-size: small;">[RocketMan](https://github.com/RocketMan)</td><td><A HREF="https://github.com/RocketMan">RocketMan</A></td></tr>
          </table>
        </div>

<?php
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
    public static function HTMLify($arg, $size, $noTables=0) {
        if ($noTables) {
            # truncate/pad output for non-table browsers
            $format = "%-" . $size . "s ";
            $arg = sprintf($format, substr(self::deLatin1ify($arg), 0, $size));
        }
    
        return htmlentities($arg, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * convert numeric arg to HTML
     * 
     * (This method just returns arg for table browsers.)
     */
    public static function HTMLifyNum($arg, $size, $noTables=0) {
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
    *  converts "last, first" to "first last" being careful to not swap
    *  other formats that have commas. call only for ZK library entries
    *  since manual entries don't need this. Test cases: The Band, CSN&Y,
    *  Bing Crosby & Fred Astaire, Bunett, June and Maqueque, Electro, Brad 
    *  Feat. Marwan Kanafaneg, Kallick, Kathy Band: 694717, 418485, 911685, 
    *  914824, 880994, 1134313.
    */
    public static function swapNames($fullName) {
        $suffixMap = [ "band" => "", "with" => "", "and" => "", "feat." => "" ];
    
        $namesAr = explode(", ", $fullName);
        if (count($namesAr) == 2) {
            $spacesAr = explode(" ", $namesAr[1]);
            $spacesCnt = count($spacesAr);
            if ($spacesCnt == 1) {
                $fullName = $namesAr[1] . " " . $namesAr[0];
            } else if ($spacesCnt > 1) {
                $key = strtolower($spacesAr[1]);
                if (array_key_exists($key, $suffixMap)) {
                    $fullName = $spacesAr[0] . ' ' . $namesAr[0] . ' ' . substr($namesAr[1], strlen($spacesAr[0]));
                }
            }
        }
        return $fullName;
    }

    /**
     * decorate the specified asset for cache control
     *
     * @param asset path to target asset
     * @return HTML-encoded URI of decorated asset
     */
    public static function decorate($asset) {
        $mtime = filemtime(__DIR__.'/../'.$asset);
        $ext = strrpos($asset, '.');
        return htmlspecialchars($mtime && $ext !== FALSE?
            substr($asset, 0, $ext).'-'.$mtime.
            substr($asset, $ext):$asset, ENT_QUOTES, 'UTF-8');
    }

    /**
     * emit a decorated LINK to the specified stylesheet
     *
     * @param asset path to target stylesheet
     */
    public static function emitCSS($asset) {
        echo "<LINK REL=\"stylesheet\" HREF=\"" .
             self::decorate($asset) . "\">\n";
    }

    /**
     * emit a decorated SCRIPT to the specified JavaScript resource
     *
     * @param asset path to target JavaScript resource
     */
    public static function emitJS($asset) {
        echo "<SCRIPT TYPE=\"text/javascript\" SRC=\"" .
             self::decorate($asset) . "\"></SCRIPT>\n";
    }
    
    /**
     * emit in-line JavaScript setFocus function
     *
     * @param control input field to receive focus (or none)
     */
    public static function setFocus($control = "") {
        echo "<SCRIPT TYPE=\"text/javascript\"><!--\n";
        echo "function setFocus() {";
        if($control)
            echo "document.forms[0].$control.focus();";
        echo "} // -->\n</SCRIPT>\n";
    }
}
