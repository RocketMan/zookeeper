<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2023 Jim Mason <jmason@ibinx.com>
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

use ZK\Engine\Engine;

use Erusev\Parsedown\Configurables;
use Erusev\Parsedown\Parsedown;
use Erusev\Parsedown\State;

use VStelmakh\UrlHighlight\UrlHighlight;

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
        /*"\u{2018}"*/ "\xe2\x80\x98"=>"'", /*"\u{2019}"*/ "\xe2\x80\x99"=>"'",
        /*"\u{201c}"*/ "\xe2\x80\x9c"=>'"', /*"\u{201d}"*/ "\xe2\x80\x9d"=>'"',
    ];

    private static $cyrillicToLatin = [
        "А" => "A", "а" => "a", "Б" => "B", "б" => "b",
        "В" => "V", "в" => "v", "Г" => "G", "г" => "g",
        "Д" => "D", "д" => "d", "Е" => "E", "е" => "e",
        "Ё" => "Ë", "ё" => "ë", "Ж" => "Zh", "ж" => "zh",
        "З" => "Z", "з" => "z", "И" => "I", "и" => "i",
        "Й" => "J", "й" => "j", "К" => "K", "к" => "k",
        "Л" => "L", "л" => "l", "М" => "M", "м" => "m",
        "Н" => "N", "н" => "n", "О" => "O", "о" => "o",
        "П" => "P", "п" => "p", "Р" => "R", "р" => "r",
        "С" => "S", "с" => "s", "Т" => "T","т" => "t",
        "У" => "U", "у" => "u", "Ф" => "F", "ф" => "f",
        "Х" => "Kh", "х" => "kh", "Ц" => "Ts", "ц" => "ts",
        "Ч" => "Ch", "ч" => "ch", "Ш" => "Sh","ш" => "sh",
        "Щ" => "Shch", "щ" => "shch", "Ъ" => "\"\"", "ъ" => "\"",
        "Ы" => "Y", "ы" => "y", "Ь" => "''", "ь" => "'",
        "Э" => "È", "э" => "è", "Ю" => "Yu", "ю" => "yu",
        "Я" => "Ya", "я" => "ya"
    ];

    private static $greekToLatin = [
        "Α" => "A", "α" => "a", "Β" => "V", "β" => "v",
        "Γ" => "G", "γ" => "g", "γγ" => "ng", "γκ" => "ng",
        "γξ" => "nx", "γχ" => "nch", "Δ" => "D", "δ" => "d",
        "Ε" => "E", "ε" => "e", "Ζ" => "Z", "ζ" => "z",
        "Η" => "H", "η" => "h", "Θ" => "Th", "θ" => "th",
        "Ι" => "I", "ι" => "i", "Κ" => "K", "κ" => "k",
        "Λ" => "L", "λ" => "l", "Μ" => "M", "μ" => "m",
        "Ν" => "N", "ν" => "n", "Ξ" => "X", "ξ" => "x",
        "Ο" => "O", "ο" => "o", "Π" => "P", "π" => "p",
        "Ρ" => "R", "ρ" => "r", "Σ" => "S", "σ" => "s", "ς" => "s",
        "Τ" => "T", "τ" => "t", "Υ" => "Y", "υ" => "y",
        "Φ" => "F", "φ" => "f", "Χ" => "Ch", "χ" => "ch",
        "Ψ" => "Ps", "ψ" => "ps", "Ω" => "w", "ω" => "w"
    ];

    private static $singletons = [];

    protected static function getSingleton(string $name, \Closure $factory) {
        return self::$singletons[$name] ??= $factory();
    }

    public static function smartURL($name, $detect=true) {
        $name = htmlentities($name);

        if($detect) {
            $name = self::getSingleton('urlHighlighter', function() {
                return new UrlHighlight();
            })->highlightUrls($name);
        }

        return $name;
    }

    /**
     * return the specified markdown as html
     *
     * html found in the input text is automatically escaped;
     * newlines are automatically converted to line breaks.
     */
    public static function markdown($text) {
        return self::getSingleton('markdown', function() {
            return new Parsedown(new State([
                new Configurables\Breaks(true),
                new Configurables\SafeMode(true)
            ]));
        })->toHtml($text);
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
     * return the URL of the current request, less leaf filename, if any
     * @deprecated use Engine::getBaseUrl()
     */
    public static function getBaseUrl() {
        return Engine::getBaseUrl();
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

    private static function transliterate($pair, $string) {
        if(extension_loaded('intl'))
            return transliterator_transliterate($pair, $string);

        switch($pair) {
        case "Greek-Latin/BGN":
            $matrix = self::$greekToLatin;
            break;
        case "Russian-Latin/BGN":
            $matrix = self::$cyrillicToLatin;
            break;
        default:
            error_log("unknown transliteration: $pair");
            $matrix = null;
            break;
        }

        return $matrix ? strtr($string, $matrix) : $string;
    }

    public static function deLatin1ify($string,
                                    $charset=UICommon::CHARSET_ASCII) {
        // input is already UTF-8
        if($charset == UICommon::CHARSET_UTF8)
            return $string;

        // cyrillic and greek to latin1
        if(preg_match("/[\u{0370}-\u{03ff}]/u", $string))
            $string = self::transliterate('Greek-Latin/BGN', $string);

        if(preg_match("/[\u{0400}-\u{045f}]/u", $string))
            $string = self::transliterate('Russian-Latin/BGN', $string);

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
     * polyfill for intl `Locale::acceptFromHttp`
     *
     * @param $header HTTP Accept-Language header
     * @return best available locale from the header
     */
    public static function acceptFromHttp($header) {
        $locales = [];
        foreach(explode(',', $header) as $locale) {
            $parts = explode(';', $locale);
            $weight = count($parts) == 2 ? explode('=', $parts[1])[1] : 1;
            $locales[] = [ $parts[0], $weight ];
        }

        usort($locales, function($a, $b) {
            return $b[1] <=> $a[1];
        });

        // Accept-Language encodes locales with a hyphen (RFC 4646),
        // whilst PHP Locale functions return an underscore
        return str_replace('-', '_', $locales[0][0]);
    }

    /**
     * convenience method to get best locale from the current request
     */
    public static function getClientLocale() {
        return self::getSingleton('clientLocale', function() {
            return self::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en-US');
        });
    }

    public static function isUsLocale() : bool {
        return self::getClientLocale() == 'en_US';
    }

    /**
     * emit a decorated LINK to the specified stylesheet
     *
     * @param asset path to target stylesheet
     */
    public static function emitCSS($asset) {
        echo "<link rel=\"stylesheet\" href=\"" .
             Engine::decorate($asset) . "\">\n";
    }

    /**
     * emit a decorated SCRIPT to the specified JavaScript resource
     *
     * @param asset path to target JavaScript resource
     */
    public static function emitJS($asset) {
        echo "<script src=\"" .
             Engine::decorate($asset) . "\"></script>\n";
    }

    /**
     * emit in-line code to set a JavaScript variable
     *
     * @param $name variable name
     * @param $value variable value (can be scalar, object, or array)
     */
    public static function emitJSVar($name, $value) {
        echo "<script><!--\n";
        echo "var $name = " . json_encode($value) . ";\n";
        echo "// -->\n</script>\n";
    }

    /**
     * emit in-line JavaScript setFocus function
     *
     * @param control input field to receive focus (or none)
     */
    public static function setFocus($control = "") {
        if($control) {
            echo "<SCRIPT><!--\n".
                 "$().ready(function(){".
                 "$('*[name=$control]').trigger('focus');".
                 "}); // -->\n</SCRIPT>\n";
        }
    }
}
