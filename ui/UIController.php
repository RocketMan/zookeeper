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

use ZK\Controllers\IController;
use ZK\Controllers\SSOCommon;
use ZK\Engine\Config;
use ZK\Engine\Engine;
use ZK\Engine\IUser;
use ZK\Engine\Session;

use ZK\UI\UICommon as UI;

use JSMin\JSMin;

class MenuEntry {
    private const FIELDS = [ 'access', 'action', 'label', 'implementation' ];

    private $entry;

    public function __construct($entry) {
        $this->entry = $entry;
    }

    public function __get($var) {
        $index = array_search($var, self::FIELDS);
        return $index !== false?$this->entry[$index]:null;
    }
}

class SafeSession {
    public function getDN() { return Engine::session()->getDN(); }
    public function getUser() { return Engine::session()->getUser(); }
    public function isUser($user) { return !strcasecmp($this->getUser(), $user); }

    public function isAuth($mode) {
        return Engine::session()->isAuth($mode);
    }
}

class LazyLoadParams {
    /**
     * list of Engine::params safe for templates
     */
    private const TEMPLATE_SAFE_PARAMS = [
        'copyright',
        'email',
        'favicon',
        'logo',
        'station',
        'station_full',
        'station_slogan',
        'station_title',
        'stylesheet',
        'urls',
    ];

    public function __isset($name) {
        return in_array($name, self::TEMPLATE_SAFE_PARAMS);
    }

    public function __get($name) {
        $this->$name = in_array($name, self::TEMPLATE_SAFE_PARAMS) ?
                    Engine::param($name) : null;
        return $this->$name;
    }
}

class UIController implements IController {
    /**
     * parent directory for Twig templates
     */
    private const TEMPLATE_BASE = __DIR__ . '/templates';

    protected $ssoUser;
    protected $dn;
    protected $session;
    protected $menu;

    protected $menuItem;

    /**
     * return menu item that matches the specified action
     */
    protected function match($action) {
        return $this->menu->iterate(function($entry) use($action) {
            $item = new MenuEntry($entry);
            $itemAction = $item->action;
            if($itemAction == $action || substr($itemAction, -1) == '%' &&
                    substr($itemAction, 0, -1) == substr($action, 0, strlen($itemAction)-1))
                return $item;
        });
    }

    /**
     * dispatch action to the appropriate menu item/command target
     */
    public function dispatch($action, $subaction) {
        $item = $this->match($action);

        // If no action was selected or if action is unauthorized,
        // default to the first one
        if(!$item || !$this->session->isAuth($item->access))
            $item = new MenuEntry($this->menu->default());

        $implClass = $item->implementation;
        $this->menuItem = new $implClass();
        if($this->menuItem instanceof MenuItem)
            $this->menuItem->process($action, $subaction, $this->session);
    }

    /**
     * indicate whether the specified action is authorized for the session
     */
    public function isActionAuth($action) {
        $item = $this->match($action);
        return !$item || $this->session->isAuth($item->access);
    }

    /**
     * compose the menu for the current session
     */
    public function composeMenu($action) {
        $result = [];
        $active = false;
        $this->menu->iterate(function($entry) use(&$result, &$active, $action) {
            $item = new MenuEntry($entry);
            $itemAction = $item->action;
            if($item->label && $this->session->isAuth($item->access)) {
                $baseAction = substr($itemAction, -1) == '%'?
                            substr($itemAction, 0, -1):$itemAction;
                $selected = $itemAction == $action ||
                        substr($itemAction, -1) == '%' &&
                        substr($itemAction, 0, -1) ==
                                substr($action, 0, strlen($itemAction) - 1);
                $active |= $selected;
                $result[] = [ 'action' => $baseAction,
                              'label' => $item->label,
                              'selected' => $selected ];
            }
        });

        if(!$active && count($result) &&
                $this->session->isAuth("u") &&
                $action == "loginValidate")
            $result[0]['selected'] = true;

        return $result;
    }

    public function processRequest() {
        $this->session = Engine::session();

        // UI configuration file
        $this->menu = new Config('ui_config', 'menu');
        $customMenu = Engine::param('custom_menu');
        if($customMenu)
            $this->menu->merge($customMenu);

        $this->preProcessRequest();

        $action = $_REQUEST["action"] ?? '';
        $subaction = $_REQUEST["subaction"] ?? '';
        $isJson = isset($_SERVER["HTTP_ACCEPT"]) &&
                substr($_SERVER["HTTP_ACCEPT"], 0, 16) === 'application/json';
        ob_start("ob_gzhandler");
        if ($isJson) {
            header("Content-Type: application/json");
            $this->dispatch($action, $subaction);
        } else {
            ob_start();
            $this->emitMain($action, $subaction);
            $data = ob_get_contents();
            ob_end_clean();

            $app = new LazyLoadParams();
            $app->content = new \stdClass();
            $app->content->data = $data;
            $app->content->template = $this->menuItem ? $this->menuItem->getTemplate() : null;
            $app->content->title = $this->menuItem ? $this->menuItem->getTitle() : null;
            $app->extra = $this->menuItem ? $this->menuItem->getExtra() : null;
            $app->menu = $this->composeMenu($_REQUEST['action']);
            $app->submenu = $this->menuItem ? $this->menuItem->composeSubmenu($_REQUEST['action'], $_REQUEST['subaction']) : [];
            $app->tertiary = $this->menuItem ? $this->menuItem->getTertiary() : null;
            $app->request = $_REQUEST;
            $app->session = new SafeSession();
            $app->sso = !empty(Engine::param('sso')['client_id']);
            $app->version = Engine::VERSION;
            // var_dump($app);

            $path = [];
            foreach([
                Engine::param('custom_template_dir', 'custom'),
                'default',
                ''
            ] as $dir) {
                $rpath = realpath(self::TEMPLATE_BASE . '/' . $dir);
                if($rpath)
                    $path[] = $rpath;
            }

            $cacheDir = Engine::param('template_cache_enabled') ?
                            self::TEMPLATE_BASE . '/.cache' : false;
            if($cacheDir) {
                if(!is_dir($cacheDir) && !mkdir($cacheDir)) {
                    error_log("UIController: cannot create $cacheDir");
                    $cacheDir = false; // disable cache
                }
            }

            $loader = new \Twig\Loader\FilesystemLoader($path);
            $twig = new \Twig\Environment($loader, [ 'cache' => $cacheDir ]);
            $twig->addGlobal('app', $app);

            $filter = new \Twig\TwigFilter('decorate', function($asset) {
                return UI::decorate($asset);
            });
            $twig->addFilter($filter);

            $template = $twig->load('index.html');
            echo $template->render($this->menuItem ? $this->menuItem->getTemplateVars() : []);
        }
        ob_end_flush(); // ob_gzhandler
    }

    protected function preProcessRequest() {
        // Validate the requested action is authorized
        if(!empty($_REQUEST["action"]) &&
                !$this->isActionAuth($_REQUEST["action"]) &&
                $_REQUEST["action"] != "loginValidate" &&
                $_REQUEST["action"] != "logout") {
            $_REQUEST["action"] = "invalidAction";
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
        case "login":
            if($this->checkCookiesEnabled())
                exit;
            break;
        case "logout":
            $this->doLogout();
            break;
        case "find":
            // redirect full-text search from v2.x
            $qs = "?action=search&s=all&n=".urlencode($_REQUEST["search"]??'');
            header("Location: ".Engine::getBaseUrl().$qs, true, 301); // 301 Moved Permanently
            exit;
        case "viewDJReviews";
            // redirect DJ review URLs from v2.x
            $qs = "?action=viewRecent&subaction=viewDJ&seq=selUser&viewuser=".urlencode($_REQUEST["n"]??'');
            header("Location: ".Engine::getBaseUrl().$qs, true, 301); // 301 Moved Permanently
            exit;
        case "viewList":
        case "viewListById":
        case "viewDJ":
            // redirect playlist URLs from v2.x
            $_REQUEST["subaction"] = $_REQUEST["action"];
            $_REQUEST["action"] = "";
            $qs = "?" . http_build_query($_REQUEST);
            header("Location: ".Engine::getBaseUrl().$qs, true, 301); // 301 Moved Permanently
            exit;
        case "viewDate":
            // redirect playlist URLs from the legacy date picker
            $qs = isset($_REQUEST["playlist"])?"?subaction=viewListById&playlist=".urlencode($_REQUEST["playlist"]):"?subaction=viewList";
            header("Location: ".Engine::getBaseUrl().$qs, true, 301); // 301 Moved Permanently
            exit;
        }
    }

    protected function emitMain($action, $subaction) {
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
        case "cookiesDisabled":
            $this->emitLogin($action);
            break;
        case "ssoOptions":
            $this->doSSOOptionsPage();
            break;
        case "logout":
            $this->emitLogout();
            break;
        case "loginValidate":
            if(!$this->emitLoginValidate())
                break;
            // login validation successful
            // fall through...
        case "invalidAction":
            // user is not authorized for requested action
            // treat as unknown action and display home page
            // fall through...
        default:
            // dispatch action
            $this->dispatch($action, $subaction);
            break;
        }
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
            echo "  <TR><TD>&nbsp;</TD><TD><B><FONT CLASS=\"error\">Google login is supported only for ".htmlentities(Engine::param('station'))." accounts.</FONT></B></TD></TR>\n";
            break;
        case "ssoInvalidAssertion":
            echo "  <TR><TD>&nbsp;</TD><TD><B><FONT CLASS=\"error\">Google authentication was not successful.</FONT></B></TD></TR>\n";
            break;
        case "ssoError":
            echo "  <TR><TD>&nbsp;</TD><TD><B><FONT CLASS=\"error\">There was a problem accessing Google authentication.  Please try again later.</FONT></B></TD></TR>\n";
            break;
        case "cookiesDisabled":
            echo "  <TR><TD>&nbsp;</TD><TD><P><B><FONT CLASS=\"error\">You must enable cookies to login to Zookeeper Online.</FONT></B></P>".
            "<P>Enable cookies in your browser for the website '".
            $_SERVER['REQUEST_SCHEME']."://".$_SERVER['SERVER_NAME'].
            "/' and try again.</P>".
            "<P>For information on how we use cookies, see the ".
            "<A HREF='PRIVACY.md' TARGET='_blank'>Privacy Policy</A>.</TD></TR></TABLE>\n";
            return;
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
          <TD><INPUT TYPE=SUBMIT class="submit" VALUE="  OK  "></TD></TR>
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
        $station = htmlentities(Engine::param('station'));
    ?>
    <h2>login help</h2>
    <P>Google single sign-on provides integrated access to your existing
    Zookeeper Online account.  Select the 'Login' link
    and enter your <?php echo $station; ?> Google account credentials
    if challenged.</P>
    <P>If you do not yet have a <?php echo $station; ?> Google account, contact the
    <A HREF="mailto:<?php echo Engine::param('email')['pd']; ?>">Program Director</A>.</P>
    <h2>classic login</h2>
    <P>If you need immediate access but do not yet have a <?php echo $station; ?> Google account,
    go to the <A HREF="?action=login">classic login</A> page and enter your
    existing Zookeeper Online user name and password.
    <B>Classic login may be deprecated or restricted in future.</B></P>
<?php 
    }

    protected function emitLoginValidate() {
        $success = false;
        if($this->session->isAuth("u")) {
            if($this->session->isAuth("g"))
                echo "   <P><B>IMPORTANT:  This login can be used ONLY at the station.</B></P>\n";
            Editor::emitQueueHook($this->session);
            $success = true;
        } else
            $this->emitLogin("badCredentials");
        return $success;
    }
    
    protected function emitLogout() {
        $logoutURI = Engine::param('sso')['logout_uri'];
        $logoutURI = str_replace("{base_url}", urlencode(Engine::getBaseUrl()."?action=logout"), $logoutURI);

        $dn = $this->session->getDN()?$this->session->getDN():$this->dn;
    
        echo "<H2>$dn logged out</H2>\n";
        if($this->ssoUser) {
            echo "<SCRIPT><!--\n";
            echo "\$().ready(function(){";
            echo "window.location.replace(\"$logoutURI\");";
            echo "}); // -->\n</SCRIPT>\n";
        } else
            UI::setFocus();
    }

    protected function checkCookiesEnabled() {
        if($_REQUEST["checkCookie"]) {
            if(isset($_COOKIE["testcookie"])) {
                // the cookie test was successful!

                // clear the test cookie
                setcookie("testcookie", "", time() - 3600);
                return false;
            } else {
                // cookies are not enabled; alert user
                $rq = [ "action" => "cookiesDisabled" ];
            }
        } else {
            // send a test cookie
            setcookie("testcookie", "testcookie");
            $rq = [ "action" => "login", "checkCookie" => 1 ];
        }

        // do the redirection
        SSOCommon::zkHttpRedirect(Engine::getBaseURL(), $rq);
        return true;
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
                  <TD><INPUT TYPE=SUBMIT class="submit" VALUE="  Continue  "></TD></TR>
          </TABLE>
      </DIV>
      <P><INPUT TYPE="radio" NAME="account" VALUE="new" onClick="showNew();">I would like to create a new Zookeeper account</P>
      <DIV ID="newSect" style="display:none;margin-left:50px;">
         <INPUT TYPE=SUBMIT class="submit" VALUE="  Continue  ">
      </DIV>
      <INPUT TYPE="hidden" NAME="action" VALUE="ssoOptions">
      <INPUT TYPE="hidden" NAME="ssoOptions" VALUE="<?php echo $_REQUEST["ssoOptions"]; ?>">
    </FORM>
    </DIV>
    <SCRIPT><!--
    <?php ob_start([JSMin::class, 'minify']); ?>
    function showOld() {
        document.getElementById("oldSect").style.display = "block";
        document.getElementById("newSect").style.display = "none"; }
    function showNew() {
        document.getElementById("oldSect").style.display = "none";
        document.getElementById("newSect").style.display = "block"; }
<?php if($_REQUEST["user"]) { ?>
    $().ready(function() {
        document.getElementById("oldRadio").checked = true;
        showOld();
    });
<?php } ?>
    <?php ob_end_flush(); ?>
    // -->
    </SCRIPT>
<?php 
    }
}
