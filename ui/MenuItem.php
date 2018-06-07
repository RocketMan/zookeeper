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

use ZK\Controllers\CommandTarget;

abstract class MenuItem extends CommandTarget {
    public function dispatchSubaction($action, $subaction, &$subactions, $extra=0) {
        // Emit the secondary navbar
        if($extra)
            echo "  <TABLE CELLPADDING=0 CELLSPACING=0 BORDER=0 WIDTH=\"100%\">\n    <TR><TD VALIGN=BOTTOM CLASS=\"secCell\">\n";

        echo  "  <TABLE CELLPADDING=0 CELLSPACING=0 BORDER=0>\n    <TR>\n";
        foreach($subactions as $item)
            if($item[2] && $this->session->isAuth($item[0]))
                $this->emitSecondaryNavSel($action, $subaction, $item[1], $item[2]);
        echo "    </TR>\n  </TABLE>\n";

        if($extra)
            echo "</TD><TH ALIGN=RIGHT CLASS=\"secCell\">$extra</TH></TR></TABLE>\n";

        echo "  <TABLE CELLPADDING=0 CELLSPACING=0 BORDER=0 WIDTH=\"100%\">\n";
        echo "    <TR><TD CLASS=\"linkrow\" HEIGHT=5><IMG SRC=\"img/blank.gif\" HEIGHT=5 WIDTH=1 ALT=\"\"></TD></TR>\n";
        echo "  </TABLE><BR>\n";
    
        // Dispatch the selected subaction
        $processed = 0;
        foreach($subactions as $item) {
            if(($subaction == $item[1]) && $this->session->isAuth($item[0])) {
                $this->{$item[3]}();
                $processed = 1;
                break;
            }
        }
    
        // If no subaction was dispatched, default to the first one
        if(!$processed) {
            $this->{$subactions[0][3]}();
        }
    }

    private function emitSecondaryNavSel($action,
                            $subAction, $menuSubAction, $description) {
        $description = preg_replace("/ /", "&nbsp;", $description);
        $subActionLen = strlen($menuSubAction);
        $selected = (($subActionLen?(substr($subAction, 0, $subActionLen) == $menuSubAction):($subAction == $menuSubAction))?" CLASS=\"secSel\"":" CLASS=\"secNorm\"");    echo "      <TD ALIGN=CENTER$selected>&nbsp;&nbsp;&nbsp;" .
             "<A CLASS=\"linkhead\" HREF=\"" .
             "?session=".$this->session->getSessionID()."&amp;action=$action&amp;subaction=$menuSubAction\">$description</A>&nbsp;&nbsp;&nbsp;</TD>\n";
        echo "      <TD WIDTH=1 BGCOLOR=\"#c0c0c0\"><IMG SRC=\"img/blank.gif\" WIDTH=1 HEIGHT=1 ALT=\"\"></TD>\n";
    }
}
