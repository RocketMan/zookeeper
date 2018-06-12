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

namespace ZK\Controllers;

use ZK\UI\Editor;
use ZK\UI\UICommon as UI;

class PrintTags implements IController {
    const FONT_FACE="NimbusMono-Bold";
    const FONT_FILE="nimbusmono-bold.ttf";
    const FONT_SIZE=13;
    const LINE_SIZE=9;

    const LABEL_FORM="5161";
    
    public function processRequest($dispatcher) {
        header("Content-type: application/pdf");

        $form = empty($_REQUEST["form"])?self::LABEL_FORM:$_REQUEST["form"];
        
        $pdf = new \PDF_Label($form);
        $pdf->AddFont(self::FONT_FACE, '', self::FONT_FILE, true);
        $pdf->SetFont(self::FONT_FACE, '', self::FONT_SIZE);
        $pdf->Set_Font_Size(self::LINE_SIZE);
        $pdf->SetFontSize(self::FONT_SIZE);
        $pdf->AddPage();

        $tags = explode(",", $_REQUEST["tags"]);
        foreach($tags as $tag) {
            $output = $tag?Editor::makeLabel($tag, UI::CHARSET_UTF8):"";
            $pdf->Add_Label($output);
        }

        $pdf->Output();
    }
}
