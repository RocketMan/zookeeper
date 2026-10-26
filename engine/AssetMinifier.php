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

        // ignore invalid HTTP dates
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
        header("Cache-Control: public, max-age=31536000, immutable");

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

    /**
     * find JavaScript identifiers and their line/column positions
     *
     * comments, whitespace, and string/template literals are skipped
     *
     * @param string $text JavaScript text to parse
     * @return array token-line-column tuples (positions are zero-based)
     */
    private function jsIdentifiers(string $text): array {
        $tokens = [];
        $length = strlen($text);
        $i = 0;
        $line = 0;
        $col = 0;

        while ($i < $length) {
            $c = $text[$i];
            $next = ($i + 1 < $length) ? $text[$i + 1] : '';

            // line comment
            if ($c === '/' && $next === '/') {
                while ($i < $length && $text[$i] !== "\n") {
                    $i++;
                    $col++;
                }

                continue;
            }

            // block comment
            if ($c === '/' && $next === '*') {
                $i += 2;
                $col += 2;

                while ($i < $length) {
                    if ($text[$i] === '*' &&
                        $i + 1 < $length &&
                        $text[$i + 1] === '/') {
                        $i += 2;
                        $col += 2;
                        break;
                    }

                    if ($text[$i] === "\n") {
                        $line++;
                        $col = 0;
                    } else {
                        $col++;
                    }

                    $i++;
                }

                continue;
            }

            // quoted strings and template literals
            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;

                $i++;
                $col++;

                while ($i < $length) {
                    $c = $text[$i];

                    if ($c === '\\') {
                        $i++;
                        $col++;

                        if ($i < $length) {
                            if ($text[$i] === "\n") {
                                $line++;
                                $col = 0;
                            } else {
                                $col++;
                            }
                            $i++;
                        }

                        continue;
                    }

                    if ($c === $quote) {
                        $i++;
                        $col++;
                        break;
                    }

                    if ($c === "\n") {
                        $line++;
                        $col = 0;
                    } else {
                        $col++;
                    }

                    $i++;
                }

                continue;
            }

            // JavaScript identifier: $, _, letters, and Unicode-free
            // ASCII alphanumeric characters.
            if (preg_match('/[a-z_$]/i', $c)) {
                $start = $i;
                $startLine = $line;
                $startCol = $col;

                while ($i < $length &&
                        preg_match('/[a-z0-9_$]/i', $text[$i])) {
                    $i++;
                    $col++;
                }

                $tokens[] = [
                    'token' => substr($text, $start, $i - $start),
                    'line' => $startLine,
                    'column' => $startCol,
                ];

                continue;
            }

            if ($c === "\n") {
                $line++;
                $col = 0;
            } else {
                $col++;
            }

            $i++;
        }

        return $tokens;
    }

    protected function emitMap(string $target): void {
        header("Content-Type: application/json");

        // for HEAD requests, there is nothing more to do
        if ($this->request->getMethod() == "HEAD")
            return;

        // get (possibly decorated) resource basename
        $uri = $this->request->getUri()->getPath();
        $i = strrpos($uri, ".");
        $base = ($i !== false) ? substr($uri, 0, $i) : $uri;

        $source = file_get_contents($target);

        // generate the destination (minified) contents...
        // this must be the same algorithm used in minifyJS
        $minified = JSMIN::minify($source);

        $sourceTokens = $this->jsIdentifiers($source);
        $generatedTokens = $this->jsIdentifiers($minified);

        $map = new SourceMap();
        $map->file = "{$base}.js";
        $sourceIndex = 0;
        $sourceCount = count($sourceTokens);

        foreach ($generatedTokens as $generated) {
            $token = $generated['token'];

            // find the next occurrence of this identifier in the source
            while ($sourceIndex < $sourceCount &&
                    $sourceTokens[$sourceIndex]['token'] !== $token) {
                $sourceIndex++;
            }

            if ($sourceIndex >= $sourceCount) {
                // should never get here...
                // it means we reached eof on the source
                // without finding the token
                break;
            }

            $original = $sourceTokens[$sourceIndex++];

            $map->addPosition([
                'generated' => [
                    'line' => $generated['line'],
                    'column' => $generated['column'],
                ],
                'source' => [
                    'fileName' => "{$base}.src.js",
                    'line' => $original['line'],
                    'column' => $original['column'],
                ],
            ]);
        }

        // generate the source map JSON
        ob_start("ob_gzhandler");
        $map->save('php://output', 0);
        ob_end_flush(); // ob_gzhandler
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
