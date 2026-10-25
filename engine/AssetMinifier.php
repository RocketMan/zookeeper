<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2026 Jim Mason <jmason@ibinx.com>
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

namespace ZK\Engine;

use axy\sourcemap\SourceMap;
use JSMin\JSMin;

class AssetMinifier {
    public function __construct(
        private Request $request,
    ) {}

    protected function ifNoneMatchMatches(string $header, string $etag): bool {
        $header = trim($header);

        // wildcard matches any existing representation
        if ($header === '*') {
            return true;
        }

        // extract entity tags from a comma-separated list;
        // commas inside the quoted opaque tag are allowed
        if (!preg_match_all(
            '/(?:^|,)\s*(?:W\/)?"([^"]*)"\s*(?=,|$)/',
            $header,
            $matches
        )) {
            return false;
        }

        // If-None-Match uses weak comparison for GET and HEAD:
        // W/"abc" matches "abc".
        $opaqueTag = substr($etag, 1, -1);

        foreach ($matches[1] as $candidate) {
            if ($candidate === $opaqueTag) {
                return true;
            }
        }

        return false;
    }

    protected function isNotModified(string $etag, int $mtime): bool {
        $noneMatch = $this->request->getServerParam('HTTP_IF_NONE_MATCH');

        // If-None-Match takes precedence over If-Modified-Since.
        if ($noneMatch !== null) {
            return $this->ifNoneMatchMatches($noneMatch, $etag);
        }

        $modifiedSince = $this->request->getServerParam(
            'HTTP_IF_MODIFIED_SINCE'
        );

        if ($modifiedSince === null) {
            return false;
        }

        $since = strtotime($modifiedSince);

        // ignore an invalid HTTP dates
        return $since !== false && $mtime <= $since;
    }

    public function processRequest() {
        $basePath = $this->request->getAttribute('basepath');
        $type = $this->request->getAttribute('type');

        // javascript source and map files reference underlying js asset
        switch ($type) {
        case "map":
        case "src.js":
            $rtype = "js";
            break;
        default:
            $rtype = $type;
            break;
        }

        $home = dirname(__DIR__);
        $target = realpath("{$home}{$basePath}.{$rtype}");

        // ensure file exists and is not outside project root
        if ($target === false ||
                !str_starts_with($target, $home . DIRECTORY_SEPARATOR) ||
                !is_file($target) ||
                !is_readable($target)) {
            http_response_code(404);
            return;
        }

        $mtime = filemtime($target);
        $size = filesize($target);
        $etag = '"' . dechex($mtime) . '-' . dechex($size) . '"';

        header("ETag: {$etag}"); // RFC 2616 requires ETag in 304 response
        header("Last-Modified: " . gmdate('D, d M Y H:i:s', $mtime) . " GMT");
        //header("Cache-Control: public, max-age=31536000, immutable");

        if ($this->isNotModified($etag, $mtime)) {
            http_response_code(304); // Not Modified
            return;
        }


        switch ($type) {
        case "css":
            $this->minifyCSS($target);
            break;
        case "js":
            $this->minifyJS($target);
            break;
        case "map":
            $this->emitMap($target);
            break;
        case "src.js":
            $this->emitSource($target);
            break;
        }
    }

    protected function minifyCSS(string $target): void {
        header("Content-Type: text/css");

        // for HEAD requests, there is nothing more to do
        if ($this->request->getMethod() == "HEAD")
            return;

        ob_start("ob_gzhandler");

        // function for compressing the CSS as tightly as possible
        // Adapted from: https://gist.github.com/Xeoncross/2407286
        ob_start(function ($buffer) {
            // Remove CSS comments
            $buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);
            // Remove tabs, spaces, newlines, etc.
            $buffer = str_replace(
                ["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $buffer);
            return $buffer;
        });

        echo file_get_contents($target);

        ob_end_flush(); // compress
        ob_end_flush(); // ob_gzhandler
    }

    protected function minifyJS(string $target): void {
        header("Content-Type: application/javascript");

        // only announce source maps on files which are not already minified
        if (!str_ends_with($target, ".min.js")) {
            $uri = $this->request->getUri()->getPath();
            $i = strrpos($uri, ".");
            $base = ($i !== false) ? substr($uri, 0, $i) : $uri;
            header("SourceMap: {$base}.map");
        }

        // for HEAD requests, there is nothing more to do
        if ($this->request->getMethod() == "HEAD")
            return;

        ob_start("ob_gzhandler");
        ob_start([JSMin::class, 'minify']);

        echo file_get_contents($target);

        ob_end_flush(); // JSMin::minify
        ob_end_flush(); // ob_gzhandler
    }

    protected function emitMap(string $target): void {
        header("Content-Type: application/json");

        // for HEAD requests, there is nothing more to do
        if ($this->request->getMethod() == "HEAD")
            return;

        // get (possibly decorated) resource basename
        $uri = $this->request->getUri()->getPath();
        $i = strrpos($uri, ".");
        $base = ($i !== false)?substr($uri, 0, $i):$uri;

        // generate the destination (minified) contents...
        // this must be the same algorithm used in minifyJS
        $minified = explode("\n", JSMIN::minify(file_get_contents($target)));

        // open the source
        $source = fopen($target, "r");
        $stext = fgets($source);
        $scol = 0;
        $sline = 0;

        // generate the dest => src mappings
        $map = new SourceMap();
        foreach ($minified as $dline => $dtext) {
            // skip comments
            $lead = substr($dtext, 0, 2);
            if ($lead == "/*" || $lead == "//")
                continue;

            $dcol = 0;
            foreach (preg_split("/[^a-z]/i", $dtext, 0, PREG_SPLIT_NO_EMPTY) as $token) {
                // token position in the minified file
                $dcol = strpos($dtext, $token, $dcol);

                // locate corresponding token position in the source file
                $scol = strpos($stext, $token, $scol);
                while ($scol === false && !feof($source)) {
                    $sline++;
                    $scol = 0;
                    $stext = fgets($source);
                    $scol = strpos($stext, $token, $scol);
                }

                if ($scol === false) {
                    // should never get here...
                    // it means we reached eof on the source
                    // without finding the token
                    break 2;
                }

                // add dest => src mapping
                $map->addPosition([
                    'generated' => [
                        'line' => $dline,
                        'column' => $dcol
                    ],
                    'source' => [
                        'fileName' => "{$base}.src.js",
                        'line' => $sline,
                        'column' => $scol
                    ],
                ]);

                $scol++;
                $dcol++;
            }
        }

        fclose($source);

        // generate the source map JSON
        $map->file = "{$base}.js";
        $map->save('php://output', 0);
    }

    protected function emitSource(string $target): void {
        header("Content-Type: application/javascript");

        // for HEAD requests, there is nothing more to do
        if ($this->request->getMethod() == "HEAD")
            return;

        ob_start("ob_gzhandler");
        echo file_get_contents($target);
        ob_end_flush(); // ob_gzhandler
    }
}
