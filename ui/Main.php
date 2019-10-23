<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2019 Jim Mason <jmason@ibinx.com>
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
    protected $ssoUser;
    protected $dn;
    protected $session;

    public function processRequest($dispatcher) {
        $this->session = Engine::session();
        $this->preProcessRequest($dispatcher);

        $isJson = substr($_SERVER["HTTP_ACCEPT"], 0, 16) === 'application/json';
        if ($isJson) {
            $action =  $_REQUEST["action"];
            $subaction =  $_REQUEST["subaction"];
            $dispatcher->dispatch($action, $subaction, $this->session);
        } else {
            $this->emitResponseHeader();
            $this->emitBody($dispatcher);
        }
    }

    protected function preProcessRequest($dispatcher) {
        // Validate the requested action is authorized
        if(!empty($_REQUEST["session"]) && !empty($_REQUEST["action"]) &&
                !$dispatcher->isActionAuth($_REQUEST["action"], $this->session) &&
                $_REQUEST["action"] != "loginValidate" &&
                $_REQUEST["action"] != "logout") {
            $_REQUEST["action"] = "invalidSession";
         }
        
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

    protected function emitResponseHeader() {
        $userAgent = $_SERVER["HTTP_USER_AGENT"];
        $banner = Engine::param('application');
        $station = Engine::param('station');
        $station_full = Engine::param('station_full');
    ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<HTML>
<HEAD>
  <TITLE><?php echo $banner;?></TITLE>
  <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=utf-8">
  <?php UI::emitCSS('css/tablesorter/theme.default.css'); ?>
  <?php UI::emitCSS('css/zoostyle.css'); ?>
  <?php UI::emitCSS(Engine::param('stylesheet')); ?>
  <?php UI::emitCSS('css/about.css'); ?>
  <?php UI::emitJS('js/jquery.min.js'); ?>
  <?php UI::emitJS('js/jquery.tablesorter.min.js'); ?>

  <!-- pull down date & time pickers if browser is stupid -->
  <SCRIPT TYPE="text/javascript"><!--
    <?php ob_start([\JSMin::class, 'minify']); ?>
    var datefield=document.createElement("input");
    datefield.setAttribute("type", "date");
    if (datefield.type!="date") {
        document.write('<link href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.0/themes/base/jquery-ui.css" rel="stylesheet" type="text/css" />');
        document.write('<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.0/jquery-ui.min.js"><\/script>') 

        document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css" />');
        document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.js"><\/script>');
    }
    <?php ob_end_flush(); ?>
  // -->
  </SCRIPT>

  <LINK REL="alternate" TYPE="application/rss+xml" TITLE="<?php echo $station; ?> Radio Music Reviews" HREF="zkrss.php?feed=reviews">
  <LINK REL="alternate" TYPE="application/rss+xml" TITLE="<?php echo $station; ?> Radio Airplay Charts" HREF="zkrss.php?feed=charts">
  <LINK REL="alternate" TYPE="application/rss+xml" TITLE="<?php echo $station; ?> Radio A-File Adds" HREF="zkrss.php?feed=adds">
  <LINK REL="search" TYPE="application/opensearchdescription+xml" HREF="?target=opensearch" title="<?php echo $banner;?>">
</HEAD>
<?php 
    }
    
    protected function emitNavbar($dispatcher, $action) {
        echo "    <P CLASS=\"zktitle\"><A HREF=\"?session=".$this->session->getSessionID()."\">".Engine::param('application')."</A></P>\n";
        echo "    <TABLE CELLPADDING=0>\n";
        $menu = $dispatcher->composeMenu($action, $this->session);
        foreach($menu as $item) {
            echo  "      <TR><TD></TD>" .
                  "<TD><A CLASS=\"" . ($item['selected']?"nav2sel":"nav2") .
                  "\" HREF=\"" .
                  "?session=".$this->session->getSessionID()."&amp;" .
                  "action=".$item['action']."\"><B>".$item['label'] .
                  "</B></A></TD></TR>\n";
        }
        #echo "      <TR><TD COLSPAN=2>&nbsp;</TD></TR>\n";
        if($this->session->isAuth("u")) {
            $logoutDiv = "<DIV style='margin-top:8px'><A CLASS='nav3' HREF='" .  "?session=".$this->session->getSessionID()."&amp;action=logout'><B>Logout</B></A></DIV>";
            $userNameDiv = "<DIV class='nav3s'>(". $this->session->getDN() . ")</DIV>";
            echo "<TR><TD></TD><TD>" . $logoutDiv . $userNameDiv . "</TD></TR>\n";
        } else if(!empty(Engine::param('sso')['client_id'])) {
            echo "      <TR><TD></TD><TD><DIV style='margin-top:8px'><A CLASS=\"nav3\" HREF=\"" .
                 "ssoLogin.php\"><B>Login</B></A>&nbsp;&nbsp;" .
                 "<A STYLE=\"font-size: 90%;\" HREF=\"?action=loginHelp\">(help)</A></DIV></TD></TR>\n";
        } else {
            // no SSO configured; emit classic login link
            echo "      <TR><TD></TD><TD><DIV style='margin-top:8px'><A CLASS=\"nav3\" HREF=\"" .
                 "?action=login\"><B>Login</B></A></DIV></TD></TR>\n";
        }
        echo "    </TABLE>\n";
    }
    
    protected function emitMain($dispatcher, $action, $subaction) {
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

    protected function emitBodyHeader($dispatcher) {
        $urls = Engine::param('urls');
        $station_full = Engine::param('station_full');
?>
    <DIV CLASS="headerLogo">
      <A HREF="<?php echo $urls['home']; ?>">
        <IMG SRC="<?php echo Engine::param('logo'); ?>" ALT="<?php echo $station_full; ?>" TITLE="<?php echo $station_full; ?>">
      </A>
    </DIV>
    <DIV CLASS="headerNavbar">
      <SPAN><?php echo Engine::param('station_slogan'); ?></SPAN>
    </DIV>
<?php
    }
    
    protected function emitBody($dispatcher) {
?>
<BODY onLoad="setFocus()">
<DIV CLASS="box">
  <DIV CLASS="header">
<?php
        $this->emitBodyHeader($dispatcher);
        echo "  </DIV>\n";
        echo "  <DIV CLASS=\"leftNav\">\n";
        $this->emitNavBar($dispatcher, $_REQUEST["action"]);
        echo "  </DIV>\n";
        echo "  <DIV CLASS=\"content\">\n";
        $this->emitMain($dispatcher, $_REQUEST["action"], $_REQUEST["subaction"]);
?>
  </DIV>
  <DIV CLASS="footer">
    <?php echo Engine::param('copyright'); ?><BR>
    <A HREF="#about">Zookeeper Online &copy; 1997-2019 J Mason. All rights reserved.</A>
  </DIV>
</DIV>
<DIV CLASS="lightbox" ID="about">
  <DIV CLASS="lightbox-modal">
    <DIV CLASS="close"><A HREF="#">[x]</A></DIV>
    <DIV CLASS="body">
      <P class="title">Zookeeper Online version <?php echo Engine::VERSION; ?></P>
      <P>Zookeeper Online &copy; 1997-2019 J Mason &lt;jmason@ibinx.com&gt;</P>
      <P>This program is free software; you are welcome to redistribute it
      under certain conditions.  See the <A HREF="LICENSE.md" TARGET="_blank">LICENSE</A>
      for details.</P>
      <P><A HREF="https://zookeeper.ibinx.com/" TARGET="_blank">Zookeeper Online project homepage</A></P>
    </DIV>
  </DIV>
</DIV>
</BODY>
</HTML>
<?php 
    }

    protected function emitLogin($invalid="") {
?>
    <FORM ACTION="?" METHOD=POST>
    <TABLE CELLPADDING=2>
<?php 
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
<?php if(!empty(Engine::param('sso')['client_id'])) { ?>
      <TR><TD>&nbsp;</TD>
          <TD><DIV STYLE="margin-left:50px;margin-top:10px;">&mdash; or &mdash;</DIV></TD></TR>
      <TR><TD>&nbsp;</TD>
          <TD><A HREF="ssoLogin.php"><IMG SRC="img/google-signin.png" ALT="Sign in with Google" WIDTH=154 HEIGHT=24 BORDER=0></A></TD></TR>
<?php } ?>
    </TABLE>
    <INPUT TYPE=HIDDEN NAME=action VALUE="loginValidate">
    </FORM>
<?php
        UI::setFocus("user");
    }
    
    protected function emitLoginHelp() {
    ?>
    <DIV CLASS="subhead">login help</DIV>
    <P>Google single sign-on provides integrated access to your existing
    <?php echo Engine::param('application'); ?> account.  Select the 'login' link in the
    left-hand navigation and enter your <?php echo Engine::param('station'); ?> Google account credentials
    if challenged.</P>
    <P>If you do not yet have a <?php echo Engine::param('station'); ?> Google account, contact the
    <A HREF="mailto:<?php echo Engine::param('email')['pd']; ?>">Program Director</A>.</P>
    <DIV CLASS="subhead">classic login</DIV>
    <P>If you need immediate access but do not yet have a <?php echo Engine::param('station'); ?> Google account,
    go to the <A HREF="?action=login">classic login</A> page and enter your
    existing <?php echo Engine::param('application'); ?> user name and password.
    <B>Classic login may be deprecated or restricted in future.</B></P>
<?php 
    }

    protected function emitLoginValidate() {
        if($this->session->isAuth("u")) {
            echo "       <H3>login successful</H3>\n";
            if($this->session->isAuth("g"))
                echo "   <P><B>IMPORTANT:  This login can be used ONLY at the station.</B></P>\n";
            Editor::emitQueueHook($this->session);
            UI::setFocus();
        } else
            $this->emitLogin("badCredentials");
    }
    
    protected function emitLogout() {
        $logoutURI = Engine::param('sso')['logout_uri'];
        $logoutURI = str_replace("{base_url}", urlencode(UI::getBaseUrl()."?action=logout"), $logoutURI);

        $dn = $this->session->getDN()?$this->session->getDN():$this->dn;
    
        echo "<H3>$dn logged out</H3>\n";
        if($this->ssoUser) {
            echo "<SCRIPT TYPE=\"text/javascript\"><!--\n";
            echo "function setFocus() {";
            echo "window.location.replace(\"$logoutURI\");";    
            echo "} // -->\n</SCRIPT>\n";
        } else
            UI::setFocus();
    }

    protected function doLogin($user, $password) {
        if(Engine::api(IUser::class)->validatePassword($user, $password, 1, $access)) {
            if(Session::checkLocal())
                $access .= 'l';

            // Restrict guest accounts to local subnet only
            if(Session::checkAccess('d', $access) ||
                   Session::checkAccess('g', $access) &&
                        !Session::checkAccess('l', $access)) {
                return;
            }
    
            // Create a session
            $sessionID = md5(uniqid(rand()));
            $this->session->create($sessionID, $user, $access);
        }
    }
    
    protected function doLogout() {
        $this->ssoUser = $this->session->isAuth("u") &&
            !$this->session->isAuth("U");

        if($this->ssoUser)
            setcookie("dn", base64_encode($this->session->getDN()), 0, "/", $_SERVER['SERVER_NAME']);
        else if(isset($_COOKIE["dn"])) {
            $this->dn = base64_decode($_COOKIE["dn"]);
            setcookie("dn", "", time() - 3600, "/", $_SERVER['SERVER_NAME']);
        }
    
        // Kill the session
        $this->session->invalidate();
    }
    
    protected function doSSOOptions() {
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
    
    protected function doSSOOptionsPage() {
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
<?php if($_REQUEST["user"]) { ?>
          <P><FONT CLASS="error">Invalid user or password</FONT></P>
<?php } ?>
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
      <INPUT TYPE="hidden" NAME="ssoOptions" VALUE="<?php echo $_REQUEST["ssoOptions"]; ?>">
    </FORM>
    </DIV>
    <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript"><!--
    <?php ob_start([\JSMin::class, 'minify']); ?>
    function showOld() {
        document.getElementById("oldSect").style.display = "block";
        document.getElementById("newSect").style.display = "none"; }
    function showNew() {
        document.getElementById("oldSect").style.display = "none";
        document.getElementById("newSect").style.display = "block"; }
    function setFocus() {
<?php if($_REQUEST["user"]) { ?>
        document.getElementById("oldRadio").checked = true;
        showOld();
<?php } ?>
    }
    <?php ob_end_flush(); ?>
    // -->
    </SCRIPT>
<?php 
    }
}
