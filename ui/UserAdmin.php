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

namespace ZK\UI;

use ZK\Engine\Engine;
use ZK\Engine\IDJ;
use ZK\Engine\ILibrary;
use ZK\Engine\IUser;

use ZK\UI\UICommon as UI;

class UserAdmin extends MenuItem {
    private static $actions = [
        [ "adminUsers", "settings" ],
        [ "contact", "contact" ],
        [ "contactGuidelines", "contact" ],
    ];

    private static $subactions = [
        [ "u", "", "Profile", "updateAirnames" ],
        [ "u", "manageKeys", "API Keys", "manageKeys" ],
        [ "U", "changePass", "Change Password", "changePass" ],
        [ "x", "users", "Users", "adminUsers" ],
        [ "x", "airnames", "Airnames", "adminAirnames" ],
    ];

    private $action;
    private $subaction;

    public function getSubactions($action) {
        return self::$subactions;
    }

    public function processLocal($action, $subaction) {
        $this->action = $action;
        $this->subaction = $subaction;
        $this->dispatchAction($action, self::$actions);
    }

    public function settings() {
        if(substr($this->subaction, -1) != "_")
            UI::emitJS("js/useradmin.js");
        return $this->dispatchSubaction($this->action, $this->subaction);
    }

    public function contact() {
        $this->setTemplate("contact.html");
    }

    public function updateAirnames() {
        UI::emitJS("js/playlists.pick.js");

        $validate = $_POST["validate"];
        $multi = $_REQUEST["multi"];
        $url = $_REQUEST["url"];
        $email = $_REQUEST["email"];
        $airname = $_REQUEST["airname"];
        $name = trim($_REQUEST["name"]);

        if($validate && $airname) {
            // Update DJ info
            $success = Engine::api(IDJ::class)->updateAirname($name,
                     $this->session->getUser(), $url, $email,
                     $multi?0:$airname);
            if($success) {
                echo "<B>Your airname has been updated.</B>\n";
                return;
            } else
                echo "<B><FONT CLASS=\"error\">'$name' is invalid or already exists.</FONT></B>";
            // fall through...
        }
        $airnames = Engine::api(IDJ::class)->getAirnames(
                     $this->session->getUser(), $airname)->asArray();

        switch(sizeof($airnames)) {
        case 0:
            // No airnames
    ?>
    <P><B><FONT CLASS="error">You have no airnames</FONT></B></P>
    <P>Publish at least one playlist or music review to create
       an airname.</P>
    <?php
            break;
        case 1:
            // Only one airname; emit form
    ?>
    <FORM id="update-airname" ACTION="?" METHOD=POST>
    <P><B>Update airname '<?php echo $airnames[0]['airname'];?>'</B></P>
    <TABLE CELLPADDING=2 BORDER=0>
      <TR><TD ALIGN=RIGHT>Airname:</TD>
        <TD><INPUT id='name' TYPE=TEXT NAME=name required VALUE="<?php echo $name?$name:$airnames[0]['airname'];?>" CLASS=input MAXLENGTH=<?php echo IDJ::MAX_AIRNAME_LENGTH . ($name?" data-focus":"");?> SIZE=40></TD></TR>
      <TR><TD ALIGN=RIGHT>URL:</TD>
        <TD><INPUT TYPE=TEXT NAME=url VALUE="<?php echo $url?$url:$airnames[0]['url'];?>" CLASS=input SIZE=40 MAXLENGTH=80<?php echo $name?"":" data-focus"; ?>></TD></TR>
      <TR><TD ALIGN=RIGHT>e-mail:</TD>
        <TD><INPUT TYPE=TEXT NAME=email VALUE="<?php echo $email?$email:$airnames[0]['email'];?>" CLASS=input SIZE=40 MAXLENGTH=80></TD></TR>
    <?php
            // Suppress the account update option for local-only accounts,
            // as they tend to be shared.
            if($multi && !$this->session->isAuth("g"))
                echo "  <TR><TD>&nbsp</TD><TD><INPUT id='multi' TYPE=CHECKBOX NAME=multi>&nbsp;Check here to apply the URL and e-mail to all of your DJ airnames</TD></TR>";
    ?>
      <TR><TD COLSPAN=2>&nbsp;</TD></TR>
      <TR><TD>&nbsp;</TD><TD><INPUT TYPE=SUBMIT VALUE="  Update  ">
              <INPUT TYPE=HIDDEN NAME=airname VALUE="<?php echo $airnames[0]['id'];?>">
              <INPUT TYPE=HIDDEN id='oldname' VALUE="<?php echo $airnames[0]['airname'];?>">
              <INPUT TYPE=HIDDEN NAME=action VALUE="adminUsers">
              <INPUT TYPE=HIDDEN NAME=validate VALUE="y"></TD></TR>
    </TABLE>
    </FORM>
    <?php
            break;
        default:
            // Multiple airnames; emit airname selection form
    ?>
    <FORM class="selector" ACTION="?" METHOD=POST>
    <B>Select Airname:</B><BR>
    <TABLE CELLPADDING=0 BORDER=0><TR><TD>
    <ul tabindex='0' class='selector listbox no-text-select'>
    <?php
            foreach($airnames as $row) {
                 echo "  <li data-value=\"$row[0]\">$row[1]</li>\n";
            }
    ?>
    </ul></TD></TR>
    <TR><TD>
        <SCRIPT TYPE="text/javascript"><!--
           $().ready(function() {
               $("ul.selector").on('keydown', function(e) {
                   var cur = $(this).find('.state-active').index();
                   switch(e.originalEvent.keyCode) {
                   case 13: // enter
                       $(this).closest("form").submit();
                       e.preventDefault();
                       return;
                   case 38: // up
                       if(cur)
                           cur--;
                       e.preventDefault();
                       break;
                   case 40: // down
                       if(cur < $(this).find('li').length - 1)
                           cur++;
                       e.preventDefault();
                       break;
                   }
                   $(this).find('li').eq(cur).trigger('mousedown');
               });
               $("ul.selector li").on('mousedown', function() {
                   $("ul.selector li").removeClass('state-active');
                   $("INPUT[NAME=airname]").val($(this).addClass('state-active').data('value'));
               }).on('dblclick', function() {
                   $(this).closest("form").submit();
               }).first().trigger('mousedown');
           });
        // -->
        </SCRIPT>
    <INPUT TYPE=SUBMIT VALUE=" Next &gt;&gt; ">
    <INPUT TYPE=HIDDEN NAME=airname VALUE="">
    <INPUT TYPE=HIDDEN NAME=action VALUE="adminUsers">
    <INPUT TYPE=HIDDEN NAME=multi VALUE="y">
    </TD></TR></TABLE>
    </FORM>
    <?php
            break;
        }
    }

    public function manageKeys() {
        $api = Engine::api(IUser::class);
        if($_POST["newKey"]) {
            $newKey = sha1(uniqid(rand()));
            $api->addAPIKey($this->session->getUser(), $newKey);
        } else if($_POST["deleteKey"]) {
            $selKeys = [];
            foreach($_POST as $key => $value) {
                if(substr($key, 0, 2) == "id" && $value == "on")
                    $selKeys[] = substr($key, 2);
            }
            if(sizeof($selKeys))
                $api->deleteAPIKeys($this->session->getUser(), $selKeys);
        }

        $keys = $api->getAPIKeys($this->session->getUser())->asArray();
        $this->setTemplate("apikeys.html");
        $this->addVar("keys", $keys);
    }

    public function changePass() {
        $this->newEntity(ChangePass::class)->processLocal("adminUsers", "changePass");
    }

    private function emitColumnHeader($header, $selected = false) {
        echo "    <TH" . ($selected?" class='initial-sort-col'":"").">$header</TH>\n";
    }

    private function emitFullName($name) {
        return preg_replace('/^(.*)(?= )/',
                    "<span class='given-name'>$1</span>", $name);
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
           d = disabled account<BR>
           g = station-only login<BR>
           m = music library editor<BR>
           n = a-file add manager<BR>
           v = vaultkeeper (duplicate any playlist)<BR>
           x = administrator</TD>
      </TR>
    </TABLE>
    <INPUT TYPE=HIDDEN NAME=action VALUE="adminUsers">
    <INPUT TYPE=HIDDEN NAME=subaction VALUE="users">
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
    <INPUT TYPE=HIDDEN NAME=subaction VALUE="users">
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
    <INPUT TYPE=HIDDEN NAME=subaction VALUE="users">
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
        foreach($users as $user) {
            if(!strlen($user["name"]))
                continue;
            if($user["name"] == $uid)
                $class = "noQuota";
            else
                $class = "hborder"; 
            echo "  <TR CLASS=\"$class\"><TD><A CLASS=\"nav\" HREF=\"?action=adminUsers&amp;subaction=users&amp;seq=selUser&amp;uid=" . $user["name"] . "\">" . $user["name"] . "</A></TD><TD>" .
                        $this->emitFullName($user["realname"]) . "</TD><TD>" .
                        $user["groups"] . "&nbsp;</TD><TD CLASS='date'>" .
                        $user["expires"] . "&nbsp;</TD><TD CLASS='date'>" .
                        $user["lastlogin"] . "&nbsp;</TD></TR>\n";
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
        $users = Engine::api(IDJ::class)->getAirnames()->asArray();
    
        // Emit the airnames list
        foreach($users as $user) {
            if(!strlen($user["name"]))
                continue;
            if($user["id"] == $aid)
                $class = "noQuota";
            else
                $class = "hborder"; 
            echo "  <TR CLASS=\"$class\"><TD><A CLASS=\"nav\" HREF=\"?subaction=airnames&amp;action=adminUsers&amp;seq=selAirname&amp;aid=" . $user["id"] . "\">" . $user["airname"] . "</A></TD><TD>" .
                        $user["name"] . "</TD><TD>" .
                        $this->emitFullName($user["realname"]) . "&nbsp;</TD></TR>\n";
        }
        echo "</TABLE>\n";
        echo "<FORM>\n";
        echo "    <INPUT TYPE=HIDDEN id='nameCol' VALUE='2'>\n";
        echo "</FORM>\n";
    }
}
