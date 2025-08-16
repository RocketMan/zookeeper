<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2025 Jim Mason <jmason@ibinx.com>
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
use ZK\Engine\IEditor;
use ZK\Engine\ILibrary;

use ZK\UI\UICommon as UI;

class DeepStorage extends MenuItem {
    public function processLocal($action, $subaction) {
        $userfile = $_FILES['userfile']['tmp_name'] ?? '';
        if(!$userfile || $_SERVER['REQUEST_METHOD'] != 'POST') {
    ?>
      <FORM ENCTYPE="multipart/form-data" ACTION="?" METHOD=post>
        <INPUT TYPE=hidden name=action value="editor">
        <INPUT TYPE=hidden name=subaction value="deepStorage">
        <INPUT TYPE=hidden name=MAX_FILE_SIZE value=100000>
        <TABLE BORDER=0 CELLPADDING=2 style='margin-top: 2px'>
          <TR><TD ALIGN=RIGHT>Send this tab-delimited file:</TD><TD><INPUT NAME=userfile TYPE=file required></TD></TR>
          <TR><TD ALIGN=RIGHT>Tag column number:</TD><TD><INPUT TYPE=text name=column value="1" LENGTH=10></TD></TR>
          <TR><TD ALIGN=RIGHT>Box number:</TD><TD><INPUT TYPE=text name=bin LENGTH=10></TD></TR>
          <TR><TD ALIGN=RIGHT>Deaccession:</TD><TD><INPUT TYPE=checkbox name=deacc value="U"></TD></TR>
          <TR><TD ALIGN=RIGHT>Missing:</TD><TD><INPUT TYPE=checkbox name=deacc value="M"></TD></TR>
          <TR><TD ALIGN=RIGHT>TEST ONLY (no update):</TD><TD><INPUT TYPE=checkbox name=test></TD></TR>
          <TR><TD COLSPAN=2><INPUT TYPE=submit VALUE="Send File"></TD></TR>
        </TABLE>
      </FORM>
    <?php 
            UI::setFocus("userfile");
        } else {
            if($_REQUEST['test'])
               echo "<TABLE>\n  <TR><TH>Tag</TH><TH>Artist</TH><TH>Album</TH></TR>\n";
            $column = $_REQUEST['column'] - 1;
            $count = 0;
            $fd = fopen($userfile, "r");
            while(!feof($fd)) {
              $line = explode("\t", fgets($fd, 1024));
              if(count($line) >= $column) {
                  $album = Engine::api(ILibrary::class)->search(ILibrary::ALBUM_KEY, 0, 1, $line[$column]);
                  if(sizeof($album)) {
                      if($_REQUEST['test'])
                         echo "  <TR><TD>" . $line[$column] . "</TD><TD>" .
                               $album[0]["artist"] . "</TD><TD>" .
                               $album[0]["album"] . "</TD></TR>\n";
                      else if($_REQUEST['deacc']) {
                         // Change status to "Deaccessioned" (U) or "Missing" (M)
                         Engine::api(IEditor::class)->setLocation($line[$column], $_REQUEST['deacc']);
                      } else {
                         // Change status to "Deep Storage" (G) and set box number
                         Engine::api(IEditor::class)->setLocation($line[$column], ILibrary::LOCATION_STORAGE, $_REQUEST['bin']);
                      }
                      $count++;
                  }
              }
            }
          if($_REQUEST['test'])
              echo "</TABLE>\n";
          else
              echo "<B>Updated $count records.</B>\n";
          fclose($fd);
       }
    }
}
