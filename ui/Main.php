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

use ZK\Controllers\IController;
use ZK\Controllers\SSOCommon;
use ZK\Engine\Engine;
use ZK\Engine\IUser;
use ZK\Engine\Session;

use ZK\UI\UICommon as UI;

class Main implements IController {
    private $localUser;
    private $session;

    public function processRequest($dispatcher) {
        $this->session = Engine::session();

        $this->preProcessRequest($dispatcher);
        $this->emitHeader();
        $this->emitBody($dispatcher);
    }

    private function preProcessRequest($dispatcher) {
        // Validate the requested action is authorized
        if(!empty($_REQUEST["session"]) && !empty($_REQUEST["action"]) &&
                !$dispatcher->isActionAuth($_REQUEST["action"], $this->session) &&
                $_REQUEST["action"] != "loginValidate" &&
                $_REQUEST["action"] != "logout")
            $_REQUEST["action"] = "invalidSession";
        
        // Setup/teardown a session
        switch($_REQUEST["action"]) {
        case "loginValidate":
            if(!$this->session->isAuth("u"))
                $this->doLogin($_REQUEST["user"], $_REQUEST["password"]);
            break;
        case "ssoOptions":
            if($this->doSSOOptions())
                exit;
            break;
        case "logout":
            $this->doLogout();
            break;
        }
    }

    private function emitHeader() {
        $userAgent = $_SERVER["HTTP_USER_AGENT"];
        $nn4hack = (substr($userAgent, 0, 10) == "Mozilla/4.") &&
                      preg_match("/\(win/i", $userAgent) &&
                      !preg_match("/Opera /i", $userAgent);
        $banner = Engine::param('application');
        $station = Engine::param('station');
        $station_full = Engine::param('station_full');
    ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<HTML>
<HEAD>
  <TITLE><?echo $banner;?></TITLE>
  <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=iso-8859-1">
  <LINK REL="stylesheet" HREF="<? echo Engine::param('stylesheet'); ?>">
  <LINK REL="stylesheet" HREF="<?
       echo $nn4hack?"css/netscape.css":"css/zoostyle.css"; ?>">
  <LINK REL="stylesheet" HREF="css/about.css">
  <LINK REL="alternate" TYPE="application/rss+xml" TITLE="<? echo $station; ?> Radio Music Reviews" HREF="zkrss.php?feed=reviews">
  <LINK REL="alternate" TYPE="application/rss+xml" TITLE="<? echo $station; ?> Radio Airplay Charts" HREF="zkrss.php?feed=charts">
  <LINK REL="alternate" TYPE="application/rss+xml" TITLE="<? echo $station; ?> Radio A-File Adds" HREF="zkrss.php?feed=adds">
  <LINK REL="search" TYPE="application/opensearchdescription+xml" HREF="opensearch.php" title="<?echo $banner;?>">
</HEAD><?
    }
    
    private function emitNavbar($dispatcher, $action, $session) {
        echo "    <P CLASS=\"zktitle\"><A HREF=\"?session=".$session->getSessionID()."\">".Engine::param('application')."</A></P>\n";
        echo "    <TABLE WIDTH=196 CELLPADDING=0>\n";
        $menu = $dispatcher->composeMenu($action, $session);
        foreach($menu as $item) {
            echo  "      <TR><TD></TD>" .
                  "<TD><A CLASS=\"" . ($item['selected']?"nav2sel":"nav2") .
                  "\" HREF=\"" .
                  "?session=".$session->getSessionID()."&amp;" .
                  "action=".$item['action']."\"><B>".$item['label'] .
                  "</B></A></TD></TR>\n";
        }
        echo "      <TR><TD COLSPAN=2>&nbsp;</TD></TR>\n";
        if($session->isAuth("u")) {
            echo "      <TR><TD></TD><TH CLASS=\"nav3s\">" . $session->getDN() . " is logged in</TH></TR>\n";
            echo "      <TR><TD></TD><TD><A CLASS=\"nav3\" HREF=\"" .
                 "?session=".$session->getSessionID()."&amp;action=logout\"><B>logout</B></A></TD></TR>\n";
        } else if(!empty(Engine::param('sso')['client_id'])) {
            echo "      <TR><TD></TD><TD><A CLASS=\"nav3\" HREF=\"" .
                 "ssoLogin.php\"><B>login</B></A>&nbsp;&nbsp;" .
                 "<A STYLE=\"font-size: 90%;\" HREF=\"?action=loginHelp\">(help)</A></TD></TR>\n";
        } else {
            // no SSO configured; emit classic login link
            echo "      <TR><TD></TD><TD><A CLASS=\"nav3\" HREF=\"" .
                 "?action=login\"><B>login</B></A></TD></TR>\n";
        }
        echo "    </TABLE>\n";
    }
    
    private function emitMain($dispatcher, $action, $subaction) {
        echo "<TABLE WIDTH=\"100%\" CELLPADDING=0 CELLSPACING=0>\n";
        echo "<TR><TD>\n";
        echo "</TD></TR>\n<TR><TD>\n";
        switch($action) {
        case "login":
            $this->emitLogin();
            break;
        case "loginHelp":
            $this->emitLoginHelp();
            break;
        case "invalidSession":
        case "ssoInvalidDomain":
        case "ssoInvalidAssertion":
        case "ssoError":
            $this->emitLogin($action);
            break;
        case "loginValidate":
            $this->emitLoginValidate();
            break;
        case "ssoOptions":
            $this->doSSOOptionsPage();
            break;
        case "logout":
            $this->emitLogout();
            break;
        default:
            // dispatch action
            $dispatcher->dispatch($action, $subaction, $this->session);
            break;
        }
        echo "</TD></TR>\n";
        echo "</TABLE>\n";
    }
    
    private function emitBody($dispatcher) {
        $urls = Engine::param('urls');
?>
    <BODY onLoad="setFocus()">
    <DIV CLASS="box">
      <DIV CLASS="header">
        <DIV CLASS="headerLogo">
          <A HREF="<? echo $urls['home']; ?>">
            <IMG SRC="<? echo Engine::param('logo'); ?>" ALT="<? echo $station_full; ?>" TITLE="<? echo $station_full; ?>">
          </A>
        </DIV>
        <DIV CLASS="headerNavbar">
	<SPAN>Music with a difference...</SPAN>
	</DIV>
      </DIV>
    <?
    echo "  <DIV CLASS=\"leftNav\">\n";
    $this->emitNavBar($dispatcher, $_REQUEST["action"], $this->session);
    echo "  </DIV>\n";
    echo "  <DIV CLASS=\"content\">\n";
    
    $this->emitMain($dispatcher, $_REQUEST["action"], $_REQUEST["subaction"]);
    ?>
      </DIV>
      <DIV CLASS="footer">
        <? echo Engine::param('copyright'); ?><BR>
        <A HREF="#about">Zookeeper Online &copy; 1997-2018 Jim Mason. All rights reserved.</A>
      </DIV>
    </DIV>
    <DIV CLASS="lightbox" ID="about">
      <DIV CLASS="lightbox-modal">
        <DIV CLASS="close"><A HREF="#">[x]</A></DIV>
        <DIV CLASS="body">
          <P class="title">Zookeeper Online version 2.0.0</P>
          <P>Zookeeper Online &copy; 1997-2018 Jim Mason &ltjmason@ibinx.com&gt;</P>
          <P>This program is free software; you are welcome to redistribute it
          under certain conditions.  See the <A HREF="LICENSE.md" TARGET="_blank">LICENSE</A>
          for details.</P>
          <P><A HREF="https://zookeeper.ibinx.com/" TARGET="_blank">Zookeeper Online project homepage</A></P>
        </DIV>
      </DIV>
    </DIV>
    </BODY>
    </HTML>
<?
    }

    private function emitLogin($invalid="") {
    ?>
    <FORM ACTION="?" METHOD=POST>
    <TABLE CELLPADDING=2>
    <?
        switch ($invalid) {
        case "badCredentials":
            if($this->session->isAuth("g"))
                echo "  <TR><TD>&nbsp;</TD><TD><B><FONT CLASS=\"error\">This login can be used only at the station.</FONT></B></TD></TR>\n";
            else if($this->session->isAuth("d"))
                echo "  <TR><TD>&nbsp;</TD><TD><B><FONT CLASS=\"error\">This login is disabled.  Please contact the <A HREF=\"mailto:".Engine::param('email')['md']."\">Music Director</A>.</FONT></B></TD></TR>\n";
            else
                echo "  <TR><TD>&nbsp;</TD><TD><B><FONT CLASS=\"error\">Invalid User or Password</FONT></B></TD></TR>\n";
            break;
        case "invalidSession":
            echo "  <TR><TD>&nbsp;</TD><TD><B><FONT CLASS=\"error\">Your session has expired.  You must login again.</FONT></B></TD></TR>\n";
            break;
        case "ssoInvalidDomain":
            echo "  <TR><TD>&nbsp;</TD><TD><B><FONT CLASS=\"error\">Google login is supported only for ".Engine::param('station')." accounts.</FONT></B></TD></TR>\n";
            break;
        case "ssoInvalidAssertion":
            echo "  <TR><TD>&nbsp;</TD><TD><B><FONT CLASS=\"error\">Google authentication was not successful.</FONT></B></TD></TR>\n";
            break;
        case "ssoError":
            echo "  <TR><TD>&nbsp;</TD><TD><B><FONT CLASS=\"error\">There was a problem accessing Google authentication.  Please try again later.</FONT></B></TD></TR>\n";
            break;
        case "":
            echo "  <TR><TD></TD><TD CLASS=\"sub\">AUTHORIZED USE ONLY!<BR>\n";
            break;
        }
    ?>
      <TR><TD COLSPAN=2>&nbsp;</TD></TR>
      <TR><TD>&nbsp;</TD><TD>Enter your user name and password to login</TD></TR>
      <TR><TD ALIGN=RIGHT>User:</TD>
          <TD><INPUT TYPE=TEXT NAME=user CLASS=input onChange="this.value=this.value.toLowerCase();"></TD></TR>
      <TR><TD ALIGN=RIGHT>Password:</TD>
          <TD><INPUT TYPE=PASSWORD NAME=password CLASS=input></TD></TR>
      <TR><TD>&nbsp;</TD>
          <TD><INPUT TYPE=SUBMIT VALUE="  OK  "></TD></TR>
<? if(!empty(Engine::param('sso')['client_id'])) { ?>
      <TR><TD>&nbsp;</TD>
          <TD><DIV STYLE="margin-left:50px;margin-top:10px;">&mdash; or &mdash;</DIV></TD></TR>
      <TR><TD>&nbsp;</TD>
          <TD><A HREF="ssoLogin.php"><IMG SRC="img/google-signin.png" ALT="Sign in with Google" WIDTH=154 HEIGHT=24 BORDER=0></A></TD></TR>
<? } ?>
    </TABLE>
    <INPUT TYPE=HIDDEN NAME=action VALUE="loginValidate">
    </FORM>
    <? UI::setFocus("user");
    }
    
    private function emitLoginHelp() {
    ?>
    <DIV CLASS="subhead">login help</DIV>
    <P>Google single sign-on provides integrated access to your existing
    <? echo Engine::param('application'); ?> account.  Select the 'login' link in the
    left-hand navigation and enter your <? echo Engine::param('station'); ?> Google account credentials
    if challenged.</P>
    <P>If you do not yet have a <? echo Engine::param('station'); ?> Google account, contact the
    <A HREF="mailto:<? echo Engine::param('email')['pd']; ?>">Program Director</A>.</P>
    <DIV CLASS="subhead">classic login</DIV>
    <P>If you need immediate access but do not yet have a <? echo Engine::param('station'); ?> Google account,
    go to the <A HREF="?action=login">classic login</A> page and enter your
    existing <? echo Engine::param('application'); ?> user name and password.
    <B>Classic login may be deprecated or restricted in future.</B></P>
    <?
    }

    private function emitLoginValidate() {
        if($this->session->isAuth("u")) {
    ?>
       <H3>login successful</H3>
    <?      if($this->session->isAuth("g"))
                echo "   <P><B>IMPORTANT:  This login can be used ONLY at the station.</B></P>\n";
            Editor::emitQueueHook($this->session);
            UI::setFocus();
        } else
            $this->emitLogin("badCredentials");
    }
    
    private function emitLogout() {
        $logoutURI = Engine::param('sso')['logout_uri'];
        $logoutURI = str_replace("{base_url}", urlencode(UI::getBaseUrl()), $logoutURI);
    
        echo "<H3>" . $this->session->getDN() . " logged out</H3>\n";
        if(!$this->localUser) {
            echo "<P>Your Zookeeper Online session has ended.</P>\n";
            echo "<P><B>Please remember to sign out of Google as well.</B></P>\n";
            echo "<P><A HREF=\"$logoutURI\"><B>Sign out of Google now</B></A></P>\n";
        }
        UI::setFocus();
    }

    private function doLogin($user, $password) {
        if(Engine::api(IUser::class)->validatePassword($user, $password, 1, $access)) {
            if(Session::checkLocal())
                $access .= 'G';

            // Restrict guest accounts to local subnet only
            if(Session::checkAccess('d', $access) ||
                   Session::checkAccess('g', $access) &&
                        !Session::checkAccess('G', $access)) {
                return;
            }
    
            // Create a session
            $sessionID = md5(uniqid(rand()));
            $this->session->create($sessionID, $user, $access);
        }
    }
    
    private function doLogout() {
        $this->localUser = $this->session->isAuth("U");
    
        // Kill the session
        $this->session->invalidate();
    }
    
    private function doSSOOptions() {
        $success = false;
        switch($_REQUEST["account"]) {
        case "old":
            if(Engine::api(IUser::class)->validatePassword($_REQUEST["user"], $_REQUEST["password"], 0, $access) &&
                    !Session::checkAccess('d', $access) &&
                    !Session::checkAccess('g', $access)) {
                $row = Engine::api(IUser::class)->getSsoOptions($_REQUEST["ssoOptions"]);
                if($row) {
                    $account = $row['account'];
                    $location = $row['url'];
                    Engine::api(IUser::class)->assignAccount($_REQUEST["user"], $account);
                    $success = true;
                }
            }
            break;
        case "new":
            $row = Engine::api(IUser::class)->getSsoOptions($_REQUEST["ssoOptions"]);
            if($row) {
                $account = $row['account'];
                $location = $row['url'];
                $user = Engine::api(IUser::class)->createNewAccount($row['fullname'], $account);
                $success = true;
            }
            break;
        default:
            break;
        }
    
        if($success) {
            // show the login succeeded page
            Engine::api(IUser::class)->teardownSsoOptions($_REQUEST["ssoOptions"]);
            SSOCommon::setupSSOByAccount($account);
            $_REQUEST["action"] = "loginValidate";
            if($location) {
                $rq = array(
                    "action" => $action,
                    "session" => Engine::session()->getSessionID(),
                    "access" => $access
                );
                SSOCommon::zkHttpRedirect($location, $rq);
                return true;
            }
        }
    
        return false;
    }
    
    private function doSSOOptionsPage() {
        $success = false;
        $row = Engine::api(IUser::class)->getSsoOptions($_REQUEST["ssoOptions"]);
        if($row)
            $success = true;
        if(!$success) {
            $this->emitLogin("ssoError");
            return;
        }
    ?>
    <P CLASS="subhead2">This is your first login with Google.  Please choose an option:</P>
    <DIV style="margin-left:20px;">
    <FORM ACTION="?" METHOD=POST>
      <P><INPUT TYPE="radio" NAME="account" VALUE="old" ID="oldRadio" onClick="showOld();">I already have a Zookeeper account and wish to use it</P>
      <DIV ID="oldSect" style="display:none;margin-left:50px;">
    <? if($_REQUEST["user"]) { ?>
          <P><FONT CLASS="error">Invalid user or password</FONT></P>
    <? } ?>
          <P>Enter your existing Zookeeper login</P>
          <TABLE>
              <TR><TD ALIGN=RIGHT>User:</TD>
                  <TD><INPUT TYPE=TEXT NAME=user CLASS=input onChange="this.value=this.value.toLowerCase();"></TD></TR>
              <TR><TD ALIGN=RIGHT>Password:</TD>
                  <TD><INPUT TYPE=PASSWORD NAME=password CLASS=input></TD></TR>
              <TR><TD>&nbsp;</TD>
                  <TD><INPUT TYPE=SUBMIT VALUE="  Continue  "></TD></TR>
          </TABLE>
      </DIV>
      <P><INPUT TYPE="radio" NAME="account" VALUE="new" onClick="showNew();">I would like to create a new Zookeeper account</P>
      <DIV ID="newSect" style="display:none;margin-left:50px;">
         <INPUT TYPE=SUBMIT VALUE="  Continue  ">
      </DIV>
      <INPUT TYPE="hidden" NAME="action" VALUE="ssoOptions">
      <INPUT TYPE="hidden" NAME="ssoOptions" VALUE="<?echo $_REQUEST["ssoOptions"]; ?>">
    </FORM>
    </DIV>
    <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript"><!--
    function showOld() {
        document.getElementById("oldSect").style.display = "block";
        document.getElementById("newSect").style.display = "none"; }
    function showNew() {
        document.getElementById("oldSect").style.display = "none";
        document.getElementById("newSect").style.display = "block"; }
    function setFocus() {
    <? if($_REQUEST["user"]) { ?>
        document.getElementById("oldRadio").checked = true;
        showOld();
    <? } ?>
    }
    // -->
    </SCRIPT>
    <?
    }
}
