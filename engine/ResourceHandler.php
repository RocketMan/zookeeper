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

abstract class ResourceHandler {
    public function __construct(
        protected Request $request,
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

    protected function getType(): string {
        return $this->request->getAttribute('type');
    }

    protected function getCacheControl(): ?string {
        return null;
    }

    protected function prepareRequest(): ?string {
        $basePath = $this->request->getAttribute('basepath');
        $type = $this->getType();

        $home = dirname(__DIR__);
        $target = realpath("{$home}{$basePath}.{$type}");

        // ensure file exists and is not outside project root
        if ($target === false ||
                !str_starts_with($target, $home . DIRECTORY_SEPARATOR) ||
                !is_file($target) ||
                !is_readable($target)) {
            http_response_code(404);
            return null;
        }

        $mtime = filemtime($target);
        $size = filesize($target);
        $etag = '"' . dechex($mtime) . '-' . dechex($size) . '"';

        header("ETag: {$etag}"); // RFC 2616 requires ETag in 304 response
        header("Last-Modified: " . gmdate('D, d M Y H:i:s', $mtime) . " GMT");
        $cacheControl = $this->getCacheControl();
        if ($cacheControl !== null)
            header($cacheControl);

        if ($this->isNotModified($etag, $mtime)) {
            http_response_code(304); // Not Modified
            return null;
        }

        return $target;
    }

    abstract public function processRequest();
}
