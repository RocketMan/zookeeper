<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2020 Jim Mason <jmason@ibinx.com>
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
use ZK\Engine\IDJ;
use ZK\Engine\ILibrary;
use ZK\Engine\IUser;

use ZK\UI\UICommon as UI;

class UserAdmin extends MenuItem {
    private static $subactions = [
        [ "x", "", "Users", "adminUsers" ],
        [ "x", "airnames", "Airnames", "adminAirnames" ],
    ];

    public function processLocal($action, $subaction) {
        UI::emitJS("js/useradmin.js");
        return $this->dispatchSubAction($action, $subaction, self::$subactions);
    }

    private function emitColumnHeader($header, $selected = false) {
        echo "    <TH" . ($selected?" class='initial-sort-col'":"").">$header</TH>\n";
    }
    
    public function adminUsers() {
        $seq = $_REQUEST["seq"];
        $uid = $_REQUEST["uid"];
        $auName = $_REQUEST["auName"];
        $auPass = $_REQUEST["auPass"];
        $auGroups = $_REQUEST["auGroups"];
        $auExpire = $_REQUEST["auExpire"];

        if($seq == "editUser" && $_SERVER['REQUEST_METHOD'] == 'POST') {
            // Commit the changes
            $user = Engine::api(ILibrary::class)->search(ILibrary::PASSWD_NAME, 0, 1, $uid);
            if(sizeof($user)) {
                if(Engine::api(IUser::class)->updateUser($uid, $auPass, $auName, $auGroups, $auExpire))
                    echo "<B><FONT CLASS=\"subhead2\">$uid successfully updated</FONT></B>\n";
                else
                    echo "<B><FONT COLOR=\"#ff0000\">Update user failed.  Try again later.</FONT></B>\n";
            } else
                echo "<B><FONT COLOR=\"#ff0000\">Invalid user.  Update failed.</FONT></B>\n";
        } else if($seq == "addUser" && $_SERVER['REQUEST_METHOD'] == 'POST') {
          if($uid) {
             if(Engine::api(IUser::class)->insertUser($uid, $auPass, $auName, $auGroups, $auExpire))
               echo "<B><FONT CLASS=\"subhead2\">$uid successfully added</FONT></B>\n";
             else
               echo "<B><FONT COLOR=\"#ff0000\">Add user failed.  Try again later.</FONT></B>\n";
          } else
             $seq = "newUser";
        }
        if($seq == "newUser") {
    ?>
    <FORM ACTION="?" METHOD=POST>
    <TABLE CELLPADDING=2 CELLSPACING=2">
      <TR>
        <TD ALIGN=RIGHT>Login:</TD>
        <TD><INPUT TYPE=TEXT NAME=uid SIZE=32 data-focus autocomplete='new-password'></TD>
      </TR><TR>
        <TD ALIGN=RIGHT>Name:</TD>
        <TD><INPUT TYPE=TEXT NAME=auName SIZE=32 autocomplete='new-password'></TD>
      </TR><TR>
        <TD ALIGN=RIGHT>Password:</TD>
        <TD><INPUT TYPE=PASSWORD NAME=auPass SIZE=15 autocomplete='new-password'></TD>
      </TR><TR>
        <TD ALIGN=RIGHT>Groups:</TD>
        <TD><INPUT TYPE=TEXT NAME=auGroups SIZE=15></TD>
      </TR><TR>
        <TD ALIGN=RIGHT>Expiration:</TD>
        <TD><INPUT TYPE=TEXT NAME=auExpire SIZE=15></TD>
      </TR><TR>
        <TD>&nbsp;</TD>
        <TD><INPUT TYPE=SUBMIT CLASS=submit VALUE=" Add User "></TD>
      </TR><TR>
        <TD>&nbsp;</TD>
        <TD>&nbsp;</TD>
      </TR><TR>
        <TD>&nbsp;</TD>
        <TD>Groups:<BR>
           c = CMJ chart builder<BR>
           d = disabled account<BR>
           g = station-only login<BR>
           m = music library editor<BR>
           n = a-file add manager<BR>
           v = vaultkeeper (duplicate any playlist)<BR>
           x = administrator</TD>
      </TR>
    </TABLE>
    <INPUT TYPE=HIDDEN NAME=action VALUE="adminUsers">
    <INPUT TYPE=HIDDEN NAME=seq VALUE="addUser">
    </FORM>
    <?php
            return;
        } else if($seq == "selUser") {
            $user = Engine::api(ILibrary::class)->search(ILibrary::PASSWD_NAME, 0, 1, $uid);
            if(sizeof($user)) {
                // Emit edit user form
    ?>
    <FORM ACTION="?" METHOD=POST>
    <TABLE CELLPADDING=2 CELLSPACING=2>
      <TR>
        <TD>&nbsp;</TD>
        <TD WIDTH="100%"><B><FONT SIZE="+1"><?php echo $uid;?></FONT></B></TD>
      </TR><TR>
        <TD>&nbsp;</TD>
        <TD>&nbsp;</TD>
      </TR><TR>
        <TD ALIGN=RIGHT>Name:</TD>
        <TD><INPUT TYPE=TEXT NAME=auName VALUE="<?php echo $user[0]["realname"];?>" data-focus SIZE=32 autocomplete='new-password'></TD>
      </TR><TR>
        <TD ALIGN=RIGHT>Password:</TD>
        <TD><INPUT TYPE=PASSWORD NAME=auPass SIZE=15 autocomplete='new-password'></TD>
      </TR><TR>
        <TD ALIGN=RIGHT>Groups:</TD>
        <TD><INPUT TYPE=TEXT NAME=auGroups VALUE="<?php echo $user[0]["groups"];?>" SIZE=15></TD>
      </TR><TR>
        <TD ALIGN=RIGHT>Expiration:</TD>
        <TD><INPUT TYPE=TEXT NAME=auExpire VALUE="<?php echo $user[0]["expires"];?>" SIZE=15></TD>
      </TR><TR>
        <TD>&nbsp;</TD>
        <TD><INPUT TYPE=SUBMIT CLASS=submit VALUE=" Update User "></TD>
      </TR><TR>
        <TD>&nbsp;</TD>
        <TD>&nbsp;</TD>
      </TR><TR>
        <TD>&nbsp;</TD>
        <TD>Groups:<BR>
           c = CMJ chart builder<BR>
           d = disabled account<BR>
           g = station-only login<BR>
           m = music library editor<BR>
           n = a-file add manager<BR>
           v = vaultkeeper (duplicate any playlist)<BR>
           x = administrator</TD>
      </TR>
    </TABLE>
    <INPUT TYPE=HIDDEN NAME=uid VALUE="<?php echo $uid;?>">
    <INPUT TYPE=HIDDEN NAME=action VALUE="adminUsers">
    <INPUT TYPE=HIDDEN NAME=seq VALUE="editUser">
    </FORM>
    <?php
                return;
            }
        }
    ?>
    <P>
    <FORM ACTION="?" METHOD=POST>
    <INPUT TYPE=SUBMIT CLASS=submit VALUE="  New User  ">
    <INPUT TYPE=HIDDEN NAME=action VALUE="adminUsers">
    <INPUT TYPE=HIDDEN NAME=seq VALUE="newUser">
    <INPUT TYPE=HIDDEN id='nameCol' VALUE='1'>
    </FORM>
    </P>
    <?php 
        // Emit the column headers
        echo "<P><TABLE class='sortable-table'>\n  <THEAD><TR>\n";
        $this->emitColumnHeader("User", true);
        $this->emitColumnHeader("Name");
        $this->emitColumnHeader("Groups");
        $this->emitColumnHeader("Expiration");
        $this->emitColumnHeader("Last Login");
        echo "  </TR></THEAD>\n";
    
        // Get and sort the user list
        $users = Engine::api(ILibrary::class)->search(ILibrary::PASSWD_NAME, 0, 100000, "*");
    
        // Emit the user list
        for($j=0; $j < sizeof($users); $j++) {
            if(!strlen($users[$j]["name"]))
                continue;
            if($users[$j]["name"] == $uid)
                $class = "noQuota";
            else
                $class = "hborder"; 
            echo "  <TR CLASS=\"$class\"><TD><A CLASS=\"nav\" HREF=\"?action=adminUsers&amp;seq=selUser&amp;uid=" . $users[$j]["name"] . "\">" . $users[$j]["name"] . "</A></TD><TD>" .
                        $users[$j]["realname"] . "</TD><TD>" .
                        $users[$j]["groups"] . "&nbsp;</TD><TD CLASS='date'>" .
                        $users[$j]["expires"] . "&nbsp;</TD><TD CLASS='date'>" .
                        $users[$j]["lastlogin"] . "&nbsp;</TD></TR>\n";
        }
        echo "</TABLE>\n";
    }
    
    public function adminAirnames() {
        $seq = $_REQUEST["seq"];
        $uid = $_REQUEST["uid"];
        $aid = $_REQUEST["aid"];
        $auName = $_REQUEST["auName"];
        $auPass = $_REQUEST["auPass"];
        $auGroups = $_REQUEST["auGroups"];
        $auExpire = $_REQUEST["auExpire"];
    
        if($seq == "editAirname" && $_SERVER['REQUEST_METHOD'] == 'POST') {
           if($uid) {
              // Get the airname
              $result = Engine::api(IDJ::class)->getAirnames(0, $aid);
              $row = $result->fetch();
    
              // Reassign the airname, playlists, and reviews
              $success = Engine::api(IDJ::class)->reassignAirname($aid, $uid) > 0;
    
              if($success) {
                  echo "<B><FONT CLASS=\"subhead2\">".$row["airname"]." successfully updated</FONT></B>\n";
              } else
                  echo "<B><FONT COLOR=\"#ff0000\">Airname user failed.  Try again later.</FONT></B>\n";
           } else
              $seq = "selAirname";
        }
        if($seq == "selAirname") {
            $result = Engine::api(IDJ::class)->getAirnames(0, $aid);
            $row = $result->fetch();
    ?>
    <FORM ACTION="?" METHOD=POST>
    <TABLE CELLPADDING=2 CELLSPACING=2 WIDTH="100%">
      <TR>
        <TD>&nbsp;</TD>
        <TD WIDTH="100%"><B><FONT SIZE="+1"><?php echo $row["airname"];?></FONT></B></TD>
      </TR><TR>
        <TD>&nbsp;</TD>
        <TD>&nbsp;</TD>
      </TR><TR>
        <TD ALIGN=RIGHT>User:</TD>
        <TD><?php echo $row["name"]." (".$row["realname"].")";?></TD>
      </TR><TR>
        <TD ALIGN=RIGHT VALIGN=TOP>Move&nbsp;To:</TD>
        <TD>
          <SELECT NAME=uid SIZE=10>
    <?php 
            $result = Engine::api(IUser::class)->getUsers();
            while($row = $result->fetch()) {
                echo "        <OPTION VALUE=\"".$row["name"]."\">".$row["name"].
                     " (".$row["realname"].")\n";
            }
    ?>
          </SELECT>
        </TD>
      </TR><TR>
        <TD>&nbsp;</TD>
        <TD><INPUT TYPE=SUBMIT CLASS=submit VALUE=" Move Airname "></TD>
      </TR>
    </TABLE>
    <INPUT TYPE=HIDDEN NAME=aid VALUE="<?php echo $aid;?>">
    <INPUT TYPE=HIDDEN NAME=action VALUE="adminUsers">
    <INPUT TYPE=HIDDEN NAME=subaction VALUE="airnames">
    <INPUT TYPE=HIDDEN NAME=seq VALUE="editAirname">
    </FORM>
    <?php
            return;
        }
    
        // Emit the column headers
        echo "<P><TABLE class='sortable-table'>\n  <THEAD><TR>\n";
        $this->emitColumnHeader("Airname", true);
        $this->emitColumnHeader("User");
        $this->emitColumnHeader("Name");
        echo "  </TR></THEAD>\n";
    
        // Get and sort the airname list
        $result = Engine::api(IDJ::class)->getAirnames();
        while($result && ($row = $result->fetch()))
            $users[] = $row;
    
        // Emit the airnames list
        for($j=0; $j < sizeof($users); $j++) {
            if(!strlen($users[$j]["name"]))
                continue;
            if($users[$j]["id"] == $aid)
                $class = "noQuota";
            else
                $class = "hborder"; 
            echo "  <TR CLASS=\"$class\"><TD><A CLASS=\"nav\" HREF=\"?subaction=airnames&amp;action=adminUsers&amp;seq=selAirname&amp;aid=" . $users[$j]["id"] . "\">" . $users[$j]["airname"] . "</A></TD><TD>" .
                        $users[$j]["name"] . "</TD><TD>" .
                        $users[$j]["realname"] . "&nbsp;</TD></TR>\n";
        }
        echo "</TABLE>\n";
        echo "<FORM>\n";
        echo "    <INPUT TYPE=HIDDEN id='nameCol' VALUE='2'>\n";
        echo "</FORM>\n";
    }
}
