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

use ZK\Engine\ILibrary;

class PlaylistEntryFactory {
    public function __construct(
        protected ILibrary $libraryDBO,
    ) {}

    public function fromJSON($json): PlaylistEntry {
        $entry = new PlaylistEntry();
        switch($json->type) {
        case "break":
            $entry->setSetSeparator();
            break;
        case "comment":
            $entry->setComment(PlaylistEntry::scrubField($json->comment, PlaylistEntry::MAX_COMMENT_LENGTH));
            break;
        case "logEvent":
            $entry->setLogEvent(PlaylistEntry::scrubField($json->event), PlaylistEntry::scrubField($json->code));
            break;
        case "spin":
        case "track":
            $entry->setArtist(PlaylistEntry::scrubField($json->artist));
            $entry->setTrack(PlaylistEntry::scrubField($json->track));
            $entry->setAlbum(PlaylistEntry::scrubField($json->album));
            $entry->setLabel(PlaylistEntry::scrubField($json->label));
            if(($a = $json->{"xa:relationships"} ?? null) &&
                    ($a = $a->album ?? null) &&
                    ($a = $a->data ?? null) &&
                    ($a->type ?? null == "album") &&
                    ($a = $a->id ?? null) ||
                    ($a = $json->tag ?? null)) {
                $albumrec = $this->libraryDBO->search(ILibrary::ALBUM_KEY, 0, 1, $a);
                if(sizeof($albumrec)) {
                    // don't allow modification of album info if tag is set
                    $entry->setTag($a);
                    if(!$albumrec[0]["iscoll"])
                        $entry->setArtist($albumrec[0]["artist"]);
                    $entry->setAlbum($albumrec[0]["album"]);
                    $entry->setLabel($albumrec[0]["name"]);
                }
            }

            if(!$entry->getTag() && $entry->getTrack() &&
                    $entry->getArtist() && $entry->getAlbum()) {
                $artist = PlaylistEntry::swapNames($entry->getArtist());
                $album = $entry->getAlbum();
                $tracks = $this->libraryDBO->search(ILibrary::TRACK_NAME, 0, 200, $entry->getTrack());
                foreach($tracks as $t) {
                    if(mb_strtolower(PlaylistEntry::swapNames($t['artist'])) == mb_strtolower($artist) &&
                            // ILibrary::TRACK_NAME encodes compilation album title as '[coll]: title'
                            mb_strtolower(mb_substr($t['album'], $t['iscoll'] ? 8 : 0, 8)) == mb_strtolower(mb_substr($album, 0, 8))) {
                        $entry->setTag($t['tag']);
                        break;
                    }
                }
            }
            break;
        }
        $entry->setCreated($json->created);
        return $entry;
    }

    public function fromArray($array): PlaylistEntry {
        $entry = new PlaylistEntry();
        switch($array["type"]) {
        case "break":
            $entry->setSetSeparator();
            break;
        case "comment":
            $entry->setComment(PlaylistEntry::scrubField($array["comment"], PlaylistEntry::MAX_COMMENT_LENGTH));
            break;
        case "logEvent":
            $entry->setLogEvent(PlaylistEntry::scrubField($array["event"]), PlaylistEntry::scrubField($array["code"]));
            break;
        case "spin":
        case "track":
            $entry->setTrack(PlaylistEntry::scrubField($array["track"]));
            if(isset($array["xa:relationships"])) {
                // using the 'xa' extension
                // see https://github.com/RocketMan/zookeeper/pull/263
                try {
                    $album = $array["xa:relationships"]->get("album")->related()->first("album");
                    $albumrec = $this->libraryDBO->search(ILibrary::ALBUM_KEY, 0, 1, $album->id());
                    if(sizeof($albumrec)) {
                        // don't allow modification of album info if tag is set
                        $entry->setTag($album->id());
                        if(!$albumrec[0]["iscoll"])
                            $entry->setArtist($albumrec[0]["artist"]);
                        $entry->setAlbum($albumrec[0]["album"]);
                        $entry->setLabel($albumrec[0]["name"]);
                    }
                } catch(\Exception $e) {}
            }

            if(!$entry->getTag()) {
                $entry->setArtist(PlaylistEntry::scrubField($array["artist"]));
                $entry->setAlbum(PlaylistEntry::scrubField($array["album"]));
                $entry->setLabel(PlaylistEntry::scrubField($array["label"]));
            }
            break;
        }

        if(isset($array["created"]))
            $entry->setCreated($array["created"]);

        return $entry;
    }
}
