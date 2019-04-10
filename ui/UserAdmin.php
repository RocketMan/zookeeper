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

    private $sortBy;

    public function processLocal($action, $subaction) {
        return $this->dispatchSubAction($action, $subaction, self::$subactions);
    }

    private function sortFn($a, $b) {
        switch($this->sortBy) {
        case "Name":
        case "Name-":
            // Field is formatted "FirstName [MI] LastName"; we want to flip it
            // to "LastName FirstName" for the purposes of the sort comparison.
            $name1=explode(" ", $a["realname"]);
            $name2=explode(" ", $b["realname"]);
            $name1c=$name1[sizeof($name1)-1] . " " . $name1[0];
            $name2c=$name2[sizeof($name2)-1] . " " . $name2[0];
            $retval = strcasecmp($name1c, $name2c);
            break;
        case "Groups":
        case "Groups-":
            $retval = strcmp($a["groups"], $b["groups"]);
            break;
        case "Expiration":
        case "Expiration-":
            $retval = strcmp($b["expires"], $a["expires"]);
            break;
        case "Last Login":
        case "Last Login-":
            $retval = strcmp($b["lastlogin"], $a["lastlogin"]);
            break;
        case "Airname":
        case "Airname-":
            $retval = strcasecmp($a["airname"], $b["airname"]);
            break;
        default:
            $retval = strcasecmp($a["name"], $b["name"]);
            break;
        }
        return (substr($this->sortBy, -1, 1) == "-")?-$retval:$retval;
    }
    
    private function emitColumnHeader($header, $subaction="") {
        $command = $header;
        if(!strcmp($header, $this->sortBy)) {
            $command .= "-";
            $selected = 1;
        } else if(!strcmp($header . "-", $this->sortBy))
            $selected = 2;
        echo "    <TH ALIGN=LEFT><A CLASS=\"nav\" HREF=\"?session=".$this->session->getSessionID()."&amp;action=adminUsers&amp;subaction=$subaction&amp;sortBy=$command\">$header</A>";
        if($selected)
            echo "&nbsp;<IMG SRC=\"img/arrow_" . (($selected==1)?"down":"up") . "_beta.gif\" BORDER=0 WIDTH=8 HEIGHT=4 ALIGN=MIDDLE ALT=\"sort\">";
        echo "</TH>\n";
    }
    
    public function adminUsers() {
        $this->sortBy = $_REQUEST["sortBy"];
    
        $seq = $_REQUEST["seq"];
        $uid = $_REQUEST["uid"];
        $auName = $_REQUEST["auName"];
        $auPass = $_REQUEST["auPass"];
        $auGroups = $_REQUEST["auGroups"];
        $auExpire = $_REQUEST["auExpire"];
    
        if(!strlen($this->sortBy))
            $this->sortBy = "User";
    
        if($seq == "editUser") {
            // Commit the changes
            $user = Engine::api(ILibrary::class)->search(ILibrary::PASSWD_NAME, 0, 1, $uid);
            if(sizeof($user)) {
                if(Engine::api(IUser::class)->updateUser($uid, $auPass, $auName, $auGroups, $auExpire))
                    echo "<B><FONT CLASS=\"subhead2\">$uid successfully updated</FONT></B>\n";
                else
                    echo "<B><FONT COLOR=\"#ff0000\">Update user failed.  Try again later.</FONT></B>\n";
            } else
                echo "<B><FONT COLOR=\"#ff0000\">Invalid user.  Update failed.</FONT></B>\n";
        } else if($seq == "addUser") {
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
        <TD><INPUT TYPE=TEXT NAME=uid SIZE=32></TD>
      </TR><TR>
        <TD ALIGN=RIGHT>Name:</TD>
        <TD><INPUT TYPE=TEXT NAME=auName SIZE=32></TD>
      </TR><TR>
        <TD ALIGN=RIGHT>Password:</TD>
        <TD><INPUT TYPE=PASSWORD NAME=auPass SIZE=15></TD>
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
           x = administrator</TD>
      </TR>
    </TABLE>
    <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
    <INPUT TYPE=HIDDEN NAME=action VALUE="adminUsers">
    <INPUT TYPE=HIDDEN NAME=seq VALUE="addUser">
    <INPUT TYPE=HIDDEN NAME=sortBy VALUE="<?php echo $this->sortBy;?>">
    </FORM>
    <?php      UI::setFocus("uid");
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
        <TD><INPUT TYPE=TEXT NAME=auName VALUE="<?php echo $user[0]["realname"];?>" SIZE=32></TD>
      </TR><TR>
        <TD ALIGN=RIGHT>Password:</TD>
        <TD><INPUT TYPE=PASSWORD NAME=auPass SIZE=15></TD>
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
           x = administrator</TD>
      </TR>
    </TABLE>
    <INPUT TYPE=HIDDEN NAME=uid VALUE="<?php echo $uid;?>">
    <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
    <INPUT TYPE=HIDDEN NAME=action VALUE="adminUsers">
    <INPUT TYPE=HIDDEN NAME=seq VALUE="editUser">
    <INPUT TYPE=HIDDEN NAME=sortBy VALUE="<?php echo $this->sortBy;?>">
    </FORM>
    <?php          UI::setFocus("auName");
                return;
            }
        }
    ?>
    <P>
    <FORM ACTION="?" METHOD=POST>
    <INPUT TYPE=SUBMIT CLASS=submit VALUE="  New User  ">
    <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
    <INPUT TYPE=HIDDEN NAME=action VALUE="adminUsers">
    <INPUT TYPE=HIDDEN NAME=seq VALUE="newUser">
    <INPUT TYPE=HIDDEN NAME=sortBy VALUE="<?php echo $this->sortBy;?>">
    </FORM>
    </P>
    <?php 
        // Emit the column headers
        echo "<P><TABLE>\n  <TR>\n";
        $this->emitColumnHeader("User");
        $this->emitColumnHeader("Name");
        $this->emitColumnHeader("Groups");
        $this->emitColumnHeader("Expiration");
        $this->emitColumnHeader("Last Login");
        echo "  </TR>\n";
    
        // Get and sort the user list
        $users = Engine::api(ILibrary::class)->search(ILibrary::PASSWD_NAME, 0, 100000, "*");
        usort($users, array($this, "sortFn"));
    
        // Emit the user list
        for($j=0; $j < sizeof($users); $j++) {
            if(!strlen($users[$j]["name"]))
                continue;
            if($users[$j]["name"] == $uid)
                $class = "noQuota";
            else
                $class = "hborder"; 
            echo "  <TR CLASS=\"$class\"><TD><A CLASS=\"nav\" HREF=\"?session=".$this->session->getSessionID()."&amp;action=adminUsers&amp;seq=selUser&amp;sortBy=$this->sortBy&amp;uid=" . $users[$j]["name"] . "\">" . $users[$j]["name"] . "</A></TD><TD>" .
                        $users[$j]["realname"] . "</TD><TD>" .
                        $users[$j]["groups"] . "&nbsp;</TD><TD>" .
                        $users[$j]["expires"] . "&nbsp;</TD><TD ALIGN=RIGHT>" .
                        $users[$j]["lastlogin"] . "&nbsp;</TD></TR>\n";
        }
        echo "</TABLE>\n";
        UI::setFocus();
    }
    
    public function adminAirnames() {
        $this->sortBy = $_REQUEST["sortBy"];
    
        $seq = $_REQUEST["seq"];
        $uid = $_REQUEST["uid"];
        $aid = $_REQUEST["aid"];
        $auName = $_REQUEST["auName"];
        $auPass = $_REQUEST["auPass"];
        $auGroups = $_REQUEST["auGroups"];
        $auExpire = $_REQUEST["auExpire"];
    
        if(!strlen($this->sortBy))
            $this->sortBy = "Airname";
    
        if($seq == "editAirname") {
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
    <INPUT TYPE=HIDDEN NAME=session VALUE="<?php echo $this->session->getSessionID();?>">
    <INPUT TYPE=HIDDEN NAME=action VALUE="adminUsers">
    <INPUT TYPE=HIDDEN NAME=subaction VALUE="airnames">
    <INPUT TYPE=HIDDEN NAME=seq VALUE="editAirname">
    <INPUT TYPE=HIDDEN NAME=sortBy VALUE="<?php echo $this->sortBy;?>">
    </FORM>
    <?php
            UI::setFocus("auName");
            return;
        }
    
        // Emit the column headers
        echo "<P><TABLE>\n  <TR>\n";
        $this->emitColumnHeader("Airname", "airnames");
        $this->emitColumnHeader("User", "airnames");
        $this->emitColumnHeader("Name", "airnames");
        echo "  </TR>\n";
    
        // Get and sort the airname list
        $result = Engine::api(IDJ::class)->getAirnames();
        while($result && ($row = $result->fetch()))
            $users[] = $row;
        if(sizeof($users))
            usort($users, array($this, "sortFn"));
    
        // Emit the airnames list
        for($j=0; $j < sizeof($users); $j++) {
            if(!strlen($users[$j]["name"]))
                continue;
            if($users[$j]["id"] == $aid)
                $class = "noQuota";
            else
                $class = "hborder"; 
            echo "  <TR CLASS=\"$class\"><TD><A CLASS=\"nav\" HREF=\"?session=".$this->session->getSessionID()."&amp;subaction=airnames&amp;action=adminUsers&amp;seq=selAirname&amp;sortBy=$this->sortBy&amp;aid=" . $users[$j]["id"] . "\">" . $users[$j]["airname"] . "</A></TD><TD>" .
                        $users[$j]["name"] . "</TD><TD>" .
                        $users[$j]["realname"] . "&nbsp;</TD></TR>\n";
        }
        echo "</TABLE>\n";
        UI::setFocus();
    }
}
