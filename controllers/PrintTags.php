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

namespace ZK\Controllers;

use ZK\Engine\Engine;
use ZK\Engine\ILibrary;

define("_SYSTEM_TTFONTS", dirname(__DIR__)."/fonts/");

class PrintTags implements IController {
    const FONT_FACE="Montserrat-Bold";
    const FONT_FILE="Montserrat-Bold.ttf";
    const FONT_FACE_Z="MontserratZ-Bold";
    const FONT_FILE_Z="MontserratZ-Bold.ttf";
    const FONT_SIZE=13;
    const FONT_SIZE_SUB=11;
    const FONT_SIZE_DATE=7;
    const LINE_SIZE=9;

    const FONT_FACE_TAG="Roboto-Bold";
    const FONT_FILE_TAG="Roboto-Bold.ttf";
    const FONT_SIZE_TAG=33;
    const LINE_SIZE_TAG=14;

    const LABEL_FORM="5161";

    const LINK = "tag/%d";

    const ADDITIONAL_FORMS = [
        'DK-1201' => [
            'orientation'=>'L',
            'paper-size'=>[29, 90], 'metric'=>'mm',
            'marginLeft'=>3, 'marginTop'=>1.5,
            'NX'=>1, 'NY'=>1,
            'SpaceX'=>0, 'SpaceY'=>0,
            'width'=>90, 'height'=>29, 'font-size'=>8
        ],
    ];

    protected $albums = [];

    protected function loadTags($tags) {
        $special = false;
        foreach($tags as $tag) {
            if(!$tag)
                continue;

            $result = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $tag);
            if(count($result) == 0)
                continue;

            $album = $result[0];
            $artist = $album["artist"];
            if(mb_strlen($artist) > 30)
                $artist = mb_substr($artist, 0, 30);

            $title = $album["album"];
            $cat = explode(" - ", ILibrary::GENRES[$album["category"]]);
            $category = "(" . $cat[0] . ")";
            $maxAlbumLen = 32 - mb_strlen($category);
            if(mb_strlen($title) > $maxAlbumLen + 3)
                $title = mb_substr($title, 0, $maxAlbumLen) . "...";

            // Montserrat does not include Greek codepoints;
            // for now, we will substitute MontserratZ in this case.
            //
            // MontserratZ has a wonky Latin and Cyrillic 'a', and as
            // well, the TTF is twice the size of Montserrat.  Thus, we
            // include it in the PDF only if necessary, and use it only
            // for labels that contain Greek text.
            //
            // U+0370 - U+03ff   Greek and Coptic
            // U+1f00 - U+1fff   Greek Extended
            $greek = preg_match("/[\u{0370}-\u{03ff}\u{1f00}-\u{1fff}]/u", $artist.$title);
            $special |= $greek;

            $this->albums[$tag] = [
                'artist' => "\n\n\n" . $artist,
                'title' => "\n\n\n\n  " . $title,
                'category' => "\n\n\n\n" . $category,
                'subcat' => count($cat) > 1 ? str_repeat("\n", 8) . strtoupper($cat[1]) : false,
                'date' => date_format(date_create($album['created']), " m-Y"),
                'special' => $greek
            ];
        }

        return $special;
    }

    public function processRequest() {
        header("Content-type: application/pdf");

        $form = empty($_REQUEST["form"])?self::LABEL_FORM:$_REQUEST["form"];
        if(array_key_exists($form, self::ADDITIONAL_FORMS))
            $form = self::ADDITIONAL_FORMS[$form];

        $inst = $_REQUEST["inst"] ?? Engine::getBaseUrl();
        $url = $inst . self::LINK;

        $pdf = new \PDF_Label($form);
        $pdf->AddFont(self::FONT_FACE_TAG, '', self::FONT_FILE_TAG, true);
        $pdf->AddFont(self::FONT_FACE, '', self::FONT_FILE, true);
        $tags = explode(",", $_REQUEST["tags"] ?? '');
        if($this->loadTags($tags))
            $pdf->AddFont(self::FONT_FACE_Z, '', self::FONT_FILE_Z, true);
        $pdf->SetCreator("Zookeeper Online");
        $pdf->AddPage();

        $empty = [
            'artist' => '',
            'special' => false
        ];

        foreach($tags as $tag) {
            $album = $this->albums[$tag] ?? $empty;
            $face = $album['special'] ? self::FONT_FACE_Z : self::FONT_FACE;
            $pdf->SetFont($face, '', self::FONT_SIZE);
            $pdf->SetLineHeight(self::LINE_SIZE);
            $artist = $album['artist'];
            $pdf->Add_Label($artist);

            if($tag && $artist) {
                $pdf->SetFontSize(self::FONT_SIZE_SUB);
                $pdf->currentLabel($album['title']);
                $pdf->currentLabel($album['category'], 'R');
                $pdf->Set_Font_Size(self::FONT_SIZE_DATE);
                $pdf->verticalText($album['date'], $inst ? -15 : -1, 0);
                if($album['subcat'])
                    $pdf->currentLabel($album['subcat'], 'R');

                // insert half-space separator every three digits
                $tagNum = strrev(implode(" ", str_split(strrev($tag), 3)));
                $tagNum = str_replace(" ", "\u{2009}", $tagNum);

                $pdf->SetFont(self::FONT_FACE_TAG, '', self::FONT_SIZE_TAG);
                $pdf->SetLineHeight(self::LINE_SIZE_TAG);
                $pdf->currentLabel($tagNum);

                if($inst)
                    $pdf->writeQRCode(sprintf($url, $tag), 'R', 1.2);
            }
        }

        $pdf->Output();
    }
}
