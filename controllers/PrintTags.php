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
    const FONT_SIZE=13;
    const FONT_SIZE_SUB=11;
    const LINE_SIZE=9;

    const FONT_FACE_TAG="Roboto-Bold";
    const FONT_FILE_TAG="Roboto-Bold.ttf";
    const FONT_SIZE_TAG=33;
    const LINE_SIZE_TAG=14;

    const LABEL_FORM="5161";

    const LINK = "?action=search&s=byAlbumKey&n=%d";

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

    public static function makeLabel($tag, &$sub, &$category) {
        $albums = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $tag);
        if(count($albums) == 0)
            return "";

        $output = "\n\n\n";

        $album = $albums[0];
        $artist = $album["artist"];
        if(mb_strlen($artist) > 30)
            $artist = mb_substr($artist, 0, 30);
        $output .= $artist;

        $title = $album["album"];
        $category = "(" . ILibrary::GENRES[$album["category"]] . ")";
        $maxAlbumLen = 33 - mb_strlen($category);
        if(mb_strlen($title) > $maxAlbumLen + 3)
            $title = mb_substr($title, 0, $maxAlbumLen) . "...";
        $sub = "  " . $title;

        return $output;
    }

    public function processRequest() {
        header("Content-type: application/pdf");

        $form = empty($_REQUEST["form"])?self::LABEL_FORM:$_REQUEST["form"];
        if(array_key_exists($form, self::ADDITIONAL_FORMS))
            $form = self::ADDITIONAL_FORMS[$form];

        $inst = $_REQUEST["inst"] ?? Engine::getBaseUrl();
        $url = $inst . self::LINK;

        $pdf = new \PDF_Label($form);
        $pdf->AddFont(self::FONT_FACE, '', self::FONT_FILE, true);
        $pdf->AddFont(self::FONT_FACE_TAG, '', self::FONT_FILE_TAG, true);
        $pdf->SetFont(self::FONT_FACE, '', self::FONT_SIZE);
        $pdf->SetLineHeight(self::LINE_SIZE);
        $pdf->SetCreator("Zookeeper Online");
        $pdf->AddPage();

        $sub = $category = "";
        $tags = explode(",", $_REQUEST["tags"]);
        foreach($tags as $tag) {
            $output = $tag?self::makeLabel($tag, $sub, $category):"";
            $pdf->Add_Label($output);

            if($tag && $output) {
                // insert half-space separator every three digits
                $tagNum = strrev(implode(" ", str_split(strrev($tag), 3)));
                $tagNum = str_replace(" ", "\u{2009}", $tagNum);

                $pdf->SetFont(self::FONT_FACE_TAG, '', self::FONT_SIZE_TAG);
                $pdf->SetLineHeight(self::LINE_SIZE_TAG);
                $pdf->currentLabel($tagNum);

                if($inst)
                    $pdf->writeQRCode(sprintf($url, $tag), 'R');

                $pdf->SetFont(self::FONT_FACE, '', self::FONT_SIZE_SUB);
                $pdf->SetLineHeight(self::LINE_SIZE);
                $pdf->currentLabel("\n\n\n\n" . $sub);
                $pdf->currentLabel("\n\n\n\n" . $category, 'R');
                $pdf->SetFontSize(self::FONT_SIZE);
            }
        }

        $pdf->Output();
    }
}
