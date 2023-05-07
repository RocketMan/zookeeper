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
use ZK\Engine\IUser;

use ZK\UI\UICommon as UI;

class ChangePass extends MenuItem {
    public function processLocal($action, $subaction) {
        $message = "Change Password";
        $form = true;
        if(isset($_POST["validate"])) {
            $userAPI = Engine::api(IUser::class);
            if($userAPI->validatePassword($this->session->getUser(), $_REQUEST["oldPass"], 0)) {
                $newPass = $_REQUEST["newPass"];
                if($newPass != "") {
                    if($newPass == $_REQUEST["newPass2"]) {
                        // Update password
                        if($userAPI->updateUser($this->session->getUser(), $newPass)) {
                            $message = "<B>Your password has been changed.</B>\n";
                            $form = false;
                        } else
                            $message = "<B><FONT CLASS=\"error\">Password update failed.  Try again later.</FONT></B>\n";
                    } else
                        $message = "<B><FONT CLASS=\"error\">New Passwords do not match.</FONT></B>\n";
                } else
                    $message = "<B><FONT CLASS=\"error\">New Password must not be blank.</FONT></B>\n";
            } else {
                $message = "<B><FONT CLASS=\"error\">Old Password is not valid.</FONT></B>\n";
                $_REQUEST["oldPass"] = "";
            }
        }

        $this->setTemplate("changepass.html");
        $this->addVar("message", $message);
        $this->addVar("form", $form);
    }
}
