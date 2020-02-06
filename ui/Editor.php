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

use ZK\Controllers\API;

use ZK\Engine\Engine;
use ZK\Engine\IEditor;
use ZK\Engine\ILibrary;

use ZK\UI\UICommon as UI;

class Editor extends MenuItem {
    private static $subactions = [
        [ "m", "", "Albums", "musicEditor" ],
        [ "m", "labels", "Labels", "musicEditor" ],
    ];

    private static $subactions_tagq = [
        [ "m", "tagq", "Queued Tags", "tagQueue" ],
    ];

    // tagFont[digit][col][row]
    private static $tagFont = [
        [ [ 0xc9, 0xba, 0xc8 ],    // 0
          [ 0xcd, 0x20, 0xcd ],
          [ 0xbb, 0xba, 0xbc ]
        ],
        [ [ 0xb7, 0xba, 0xd0 ]     // 1
        ],
        [ [ 0xd5, 0xc9, 0xc8 ],    // 2
          [ 0xcd, 0xcd, 0xcd ],
          [ 0xbb, 0xbc, 0xcd ]
        ],
        [ [ 0xd5, 0x20, 0xd4 ],    // 3
          [ 0xcd, 0xcd, 0xcd ],
          [ 0xbb, 0xb9, 0xbc ]
        ],
        [ [ 0xd6, 0xc8, 0x20 ],    // 4
          [ 0x20, 0xcd, 0x20 ],
          [ 0xb7, 0xce, 0xd0 ]
        ],
        [ [ 0xc9, 0xc8, 0xd4 ],    // 5
          [ 0xcd, 0xcd, 0xcd ],
          [ 0x20, 0xbb, 0xbc ]
        ],
        [ [ 0xc9, 0xcc, 0xc8 ],    // 6
          [ 0xcd, 0xcd, 0xcd ],
          [ 0x20, 0xbb, 0xbc ]
        ],
        [ [ 0xd5, 0x20, 0x20 ],    // 7
          [ 0xcd, 0x20, 0x20 ],
          [ 0xbb, 0xba, 0xd0 ]
        ],
        [ [ 0xc9, 0xcc, 0xc8 ],    // 8
          [ 0xcd, 0xcd, 0xcd ],
          [ 0xbb, 0xb9, 0xbc ]
        ],
        [ [ 0xc9, 0xc8, 0x20 ],    // 9
          [ 0xcd, 0xcd, 0xcd ],
          [ 0xbb, 0xb9, 0xbc ]
        ]
    ];

    private static $boxToUTF8 = [
        "\xb7" => "\xe2\x95\x96", // "\u{2556}",
        "\xb9" => "\xe2\x95\xa3", // "\u{2563}",
        "\xba" => "\xe2\x95\x91", // "\u{2551}",
        "\xbb" => "\xe2\x95\x97", // "\u{2557}",
        "\xbc" => "\xe2\x95\x9d", // "\u{255d}",
        "\xc8" => "\xe2\x95\x9a", // "\u{255a}",
        "\xc9" => "\xe2\x95\x94", // "\u{2554}",
        "\xcc" => "\xe2\x95\xa0", // "\u{2560}",
        "\xcd" => "\xe2\x95\x90", // "\u{2550}",
        "\xce" => "\xe2\x95\xac", // "\u{256c}",
        "\xd0" => "\xe2\x95\xa8", // "\u{2568}",
        "\xd4" => "\xe2\x95\x98", // "\u{2558}",
        "\xd5" => "\xe2\x95\x92", // "\u{2552}",
        "\xd6" => "\xe2\x95\x93", // "\u{2553}",
    ];

    private static $charset = [
        "UTF-8" => UI::CHARSET_UTF8,
        "LATIN-1" => UI::CHARSET_LATIN1,
        "ASCII" => UI::CHARSET_ASCII,
    ];

    private static $printStatus = [
        "none" => "",
        "connecting-to-device" => "",
        "offline" => "The printer is not responding",
        "marker-supply-low" => "Low ink",
        "marker-supply-empty" => "Out of ink",
        "toner-low" => "Low toner",
        "toner-empty" => "Out of toner",
        "media-low" => "Geting low on labels!",
        "media-empty" => "Out of labels!",
        "media-needed" => "More labels needed!",
        "media-jam" => "Paper jam!",
        "timed-out" => "The printer is not responding",
    ];

    private static $printStatusSuffixes = [ "-warn", "-error", "-report" ];
    
    private $editorPanels = [
         "search"=>    ["panelSearch", "details"],
         "details"=>   ["panelDetails", "label"],
         "label"=>     ["panelLabel", "ldetails"],
         "ldetails"=>  ["panelLDetails", "tracks"],
         "tracks"=>    ["panelTracks", "search"],
         ""=>          ["panelNull", "search"]
    ];

    private $tagQPanels = [
         "select"=>    ["queueList", "form"],
         "form"=>      ["queueForm", "print"],
         "print"=>     ["queuePlace", "confirm"],
         "confirm"=>   ["queueConfirm", "select"],
         ""=>          ["panelNull", "select"]
    ];
    
    private $limit = 14;
    private $tracksPerPage = 15;

    private $subaction;
    private $emitted;
    private $albumAdded;
    private $albumUpdated;
    private $tagPrinted;
    private $printConfig;

    public static function emitQueueHook($session) {
        if(Engine::api(ILibrary::class)->getNumQueuedTags($session->getUser()))
            echo "<P>You have <A HREF=\"?session=".$session->getSessionID()."&amp;action=editor&amp;subaction=tagq\" CLASS=\"nav\">tags queued for printing</A>.</P>";
    }

    private static function isEmpty($var) {
        return !isset($var) || empty($var) && $var !== 0 && $var !== "0";
    }
    
    public function processLocal($action, $subaction) {
        $subactions = self::$subactions;
        if(Engine::api(ILibrary::class)->getNumQueuedTags($this->session->getUser()))
            $subactions = array_merge($subactions, self::$subactions_tagq);
        $this->subaction = $subaction;
        $this->printConfig = Engine::param('label_printer');
        return $this->dispatchSubaction($action, $subaction, $subactions);
    }

    public function musicEditor() {
        if($this->subaction == "labels") {
             $this->editorPanels["ldetails"][1] = "label";
             $this->editorPanels[""][1] = "label";
        }
    
        // We're always going to make two passes:
        //    Pass 1:  Call step $seq to validate
        //    Pass 2a: If $seq validates, call $next to display
        //    Pass 2b: If $seq doesn't validate, call $seq to redisplay
    
        for($i=0; $i<2; $i++) {
            if($i == 1) {
                // Emit header
                $title = $this->getTitle($_REQUEST["seq"]);
                echo "  <FORM ACTION=\"?\" METHOD=POST>\n";
                echo "    <TABLE CELLPADDING=0 CELLSPACING=0 BORDER=0 WIDTH=\"100%\">\n      <TR><TH ALIGN=LEFT>$title</TH><TH ALIGN=RIGHT CLASS=\"error\">";
                if(!$this->subaction) {
                    $printStatus = $this->getPrintStatus();
                    if($printStatus)
                        echo "Label printer alert:&nbsp;&nbsp;$printStatus";
                }
                echo "</TH></TR>\n      <TR><TD COLSPAN=2 HEIGHT=130 VALIGN=MIDDLE>\n";
    
            }
    
            // Handle default case
            if(!$this->editorPanels[$_REQUEST["seq"]])
                $_REQUEST["seq"] = "";
    
            // Dispatch to panel
            $next = $this->editorPanels[$_REQUEST["seq"]][0];
            $status = $this->$next($i==0);
            if($status)
                $_REQUEST["seq"] = $this->editorPanels[$_REQUEST["seq"]][1];
        }
    ?>
       </TD></TR>
    </TABLE>
<?php 
        $this->emitHidden("seq", $_REQUEST["seq"]);
        $this->emitVars();
        echo "  </FORM>\n";
    }

    public function tagQueue() {
        // Remove label selection panel if there is only one template
        $labels = $this->printConfig['labels'];
        if(count($labels) == 1) {
            $key = array_keys($labels)[0];
            $nosel = $labels[$key]["rows"] * $labels[$key]["cols"] == 1;
            $this->tagQPanels["select"][1] = $nosel?"confirm":"print";
        }
    
        // We're always going to make two passes:
        //    Pass 1:  Call step $seq to validate
        //    Pass 2a: If $seq validates, call $next to display
        //    Pass 2b: If $seq doesn't validate, call $seq to redisplay
    
        for($i=0; $i<2; $i++) {
            if($i == 1) {
                // Emit header
                $title = $this->getTitle($_REQUEST["seq"]);
                echo "  <FORM ACTION=\"?\" METHOD=POST>\n";
                echo "    <TABLE CELLPADDING=0 CELLSPACING=0 BORDER=0 WIDTH=\"100%\">\n      <TR><TH ALIGN=LEFT>$title</TH></TR>\n      <TR><TD HEIGHT=130 VALIGN=MIDDLE>\n";
    
            }
    
            // Handle default case
            if(!$this->tagQPanels[$_REQUEST["seq"]])
                $_REQUEST["seq"] = "";
    
            // Dispatch to panel
            $next = $this->tagQPanels[$_REQUEST["seq"]][0];
            $status = $this->$next($i==0);
            if($status)
                $_REQUEST["seq"] = $this->tagQPanels[$_REQUEST["seq"]][1];
        }
    ?>
      </TD></TR>
    </TABLE>
<?php
        $this->emitHidden("seq", $_REQUEST["seq"]);
        $this->emitVars();
        echo "  </FORM>\n";
    }
    
    public function queueList($validate) {
         UI::emitJS('js/libed.common.js');
         if($validate) {
              if($_REQUEST["printToPDF"]) {
                  $selTags = array();
                  foreach($_POST as $key => $value) {
                      if(substr($key, 0, 3) == "tag" && $value == "on") {
                          $selTags[] = substr($key, 3);
                          $this->skipVar($key);
                      }
                  }
                  
                  $this->emitHidden("seltags", implode(",", $selTags));

                  $labels = $this->printConfig['labels'];
                  if(count($labels) == 1) {
                      $form = $labels[array_keys($labels)[0]];
                      if($form["rows"] * $form["cols"] == 1)
                          $this->emitHidden("sel", $_POST["seltags"]);
                  }

                  return true;
              }
                  
              $this->tagQPanels["select"][1] = "select";
              foreach($_POST as $key => $value) {
                 if(substr($key, 0, 3) == "tag" && $value == "on") {
                     $tag = substr($key, 3);
                     if($_REQUEST["print"])
                         $this->printTag($tag);
                     Engine::api(IEditor::class)->dequeueTag($tag, $this->session->getUser());
                     $this->skipVar("tag".$tag);
                 }
              }
              return true;
         }
         if(!Engine::api(ILibrary::class)->getNumQueuedTags($this->session->getUser())) {
              echo "  <P>There are no queued tags.</P>\n";
              return;
         }
         echo "<P><B>Tags queued for printing:</B>\n";
         echo "</P>\n";
         echo "    <TABLE BORDER=0>\n      <TR><TH><INPUT NAME=all TYPE=checkbox onClick='checkAll()'></TH><TH ALIGN=RIGHT>Tag&nbsp;&nbsp;</TH><TH>Artist</TH><TH>&nbsp;</TH><TH>Album</TH></TR>\n";
         if($result = Engine::api(IEditor::class)->getQueuedTags($this->session->getUser())) {
              while($row = $result->fetch()) {
                   echo "      <TR><TD><INPUT NAME=tag".$row["tag"]." TYPE=checkbox".($_POST["tag".$row["tag"]] == "on"?" checked":"")."></TD>";
                   echo "<TD ALIGN=RIGHT>".$row["tag"]."&nbsp;&nbsp;</TD><TD>".htmlentities($row["artist"])."</TD><TD></TD><TD>".htmlentities($row["album"])."</TD></TR>\n";
                   $this->skipVar("tag".$row["tag"]);
              }
         }
         $this->skipVar("all");
         $this->skipVar("delete");
         $this->skipVar("print");
         $this->skipVar("printToPDF");
         echo "    </TABLE>\n";
         echo "    <P><INPUT TYPE=submit CLASS=submit NAME=delete VALUE=\" Remove from Queue \">&nbsp;&nbsp;&nbsp;\n";
         if(in_array('lpr', $this->printConfig['print_methods']))
             echo "       <INPUT TYPE=submit CLASS=submit NAME=print onclick=\"return isLocal();\" VALUE=\" Print \">&nbsp;&nbsp;&nbsp;\n";
         if(in_array('pdf', $this->printConfig['print_methods']))
             echo "       <INPUT TYPE=submit CLASS=submit NAME=printToPDF onclick=\"return validateQueueList();\" VALUE=\" Print To PDF &gt; \">\n";
             echo "       <INPUT TYPE=hidden id='local' VALUE='". ($this->session->isLocal()?"1":"0") ."'>\n";
         echo "    </P>\n"; ?>
    <?php
        UI::setFocus();
    }
    
    public function queueForm($validate) {
        UI::emitJS('js/libed.common.js');
        if($validate) {
            if($_REQUEST["back"]) {
                $this->tagQPanels["form"][1] = "select";
                foreach(explode(",", $_REQUEST["seltags"]) as $tag)
                    $this->emitHidden("tag".$tag, "on");
                $this->skipVar("seltags");
                $this->skipVar("back");
                return true;
            }
            $labels = $this->printConfig['labels'];
            $formname = count($labels) == 1?
                array_keys($labels)[0]:$_REQUEST["form"];
            $form = $labels[$formname];
            if($form["rows"] * $form["cols"] == 1) {
                $this->emitHidden("sel", $_REQUEST["seltags"]);
                $this->tagQPanels["form"][1] = "confirm";
            }
            return $validate;
        }
        echo "<P><B>Choose a label printer:</B></P>\n";
        echo "<TABLE BORDER=0>\n";
        foreach($this->printConfig['labels'] as $label)
            echo "  <TR><TD><INPUT NAME=form TYPE=radio VALUE=\"".$label["code"]."\"".($_POST["form"] == $label["code"]?" checked":"").">".$label["name"]."</TD></TR>\n";
        echo "</TABLE>\n";
        echo "<P><INPUT TYPE=submit CLASS=submit NAME=back VALUE=\" &lt; Back \">&nbsp;&nbsp;&nbsp;<INPUT TYPE=SUBMIT CLASS=submit NAME=next onclick=\"return validateQueueForm();\" VALUE=\" Next &gt; \"></P>\n";
        $this->skipVar("form");
        $this->skipVar("back");
        $this->skipVar("next");
        UI::setFocus();
    }
    
    public function queuePlace($validate) {
        UI::emitJS('js/libed.common.js');
        $labels = $this->printConfig['labels'];
        $oneform = count($labels) == 1;
        if($validate) {
            if($_REQUEST["back"]) {
                if($oneform) {
                    foreach(explode(",", $_REQUEST["seltags"]) as $tag)
                        $this->emitHidden("tag".$tag, "on");
                    $this->skipVar("seltags");
                }
            
                $this->tagQPanels["print"][1] = $oneform?"select":"form";
                $this->skipVar("back");
            }
            return $validate;
        }

        $count = 0;
        foreach(explode(",", $_POST["seltags"]) as $tag)
           $count++;
        $formname = $oneform?array_keys($labels)[0]:$_REQUEST["form"];
        $form = $labels[$formname];
        $numRow = $form["rows"];
        $numCol = $form["cols"];
        $numLabels = $numRow * $numCol;
        if($count > $numLabels) $count = $numLabels;
        echo "    <P>Select up to <B><SPAN id=\"count\">$count</SPAN></B> labels:</P>\n";
        echo "    <TABLE BORDER=0 CELLPADDING=0 CELLSPACING=0>\n";
        echo "    <TR><TD CLASS=\"label-form\">\n";
        echo "    <SPAN CLASS=\"form-name\">".strtoupper($form["name"])." LABELS</SPAN><BR>\n";
        for($i=0; $i<$numRow; $i++) {
            echo "    ";
            for($j=0; $j<$numCol; $j++) {
                $index = $i*$numCol + $j;
                echo "<A HREF=\"#\" onClick=\"placeLabel($index);\" id=\"label$index\">".($i + $j*$numRow + 1)."</A>&nbsp;";
            }
            echo "<BR>\n";
        }
        echo "    </TD></TR>\n    <TR><TD>&nbsp;</TD></TR>\n";
        echo "    <TR><TD STYLE=\"text-align: right;\"><INPUT TYPE=submit CLASS=submit NAME=back VALUE=\" &lt; Back \">&nbsp;&nbsp;&nbsp;<INPUT TYPE=SUBMIT CLASS=submit NAME=next onclick=\"return validateQueuePlace();\" VALUE=\" Next &gt; \">\n";
        echo "    <INPUT TYPE=HIDDEN id='num-labels' VALUE=\"$numLabels\">\n";
        echo "    <INPUT TYPE=HIDDEN id='max-count' VALUE=\"$count\">\n";
        echo "    <INPUT TYPE=HIDDEN NAME=sel ID=sel VALUE=\"\"></TD></TR></TABLE>\n";
        $this->skipVar("sel");
        $this->skipVar("back");
        $this->skipVar("next");
        $this->skipVar("printToPDF");
        UI::setFocus();
    }

    public function queueConfirm($validate) {
        if($validate) {
            if($_REQUEST["back"]) {
                foreach(explode(",", $_REQUEST["seltags"]) as $tag)
                    $this->emitHidden("tag".$tag, "on");
            } else {
                $selCount = $_REQUEST["selcount"];
                foreach(explode(",", $_REQUEST["seltags"]) as $tag)
                    if($selCount-- > 0)
                        Engine::api(IEditor::class)->dequeueTag($tag, $this->session->getUser());
                    else
                        $this->emitHidden("tag".$tag, "on");
            }
            $this->skipVar("sel");
            $this->skipVar("seltags");
            $this->skipVar("selcount");
            $this->skipVar("back");
            $this->skipVar("done");
            return $validate;
        }

        $labels = $this->printConfig['labels'];
        $formname = count($labels) == 1?
            array_keys($labels)[0]:$_REQUEST["form"];
        $form = $labels[$formname];

        echo "        <P>A new window has been opened with a PDF for printing.</P>\n";
        echo "        <P>If the window did not open, disable pop-up blockers and try again.</P>\n";
        if(array_key_exists("message", $form))
            echo "        <P>".$form["message"]."</P>\n";
        else
            echo "        <P>Please load <B>".$form["name"]." labels</B> in your printer and print the PDF.</P>\n";
        echo "        <P>Choose <B>Done</B> after you have printed the labels successfully.</P>\n";
        echo "        <P>&nbsp;</P>\n";
        echo "        <INPUT TYPE=SUBMIT CLASS=submit NAME=back VALUE=\" &lt; Back \">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\n        <INPUT TYPE=SUBMIT CLASS=submit NAME=done VALUE=\" Done \">\n";
        
        $selLabels = explode(",", $_POST["sel"]);
        $selTags = explode(",", $_POST["seltags"]);
        $selCount = 0;
        for($i=0, $j=0; $i<sizeof($selLabels); $i++) {
            if($selLabels[$i]) {
                $selLabels[$i] = $selTags[$j++];
                $selCount++;
            }
        }
        $this->emitHidden("selcount", $selCount);
        $merged = implode(",", $selLabels);
        echo "        <SCRIPT TYPE=\"text/javascript\" LANGUAGE=\"JavaScript\"><!--\n";
        echo "        window.open('?target=print&session=".$this->session->getSessionID()."&form=".$form["code"]."&tags=$merged', '_blank', 'toolbar=no,location=no,width=800,height=800');\n";
        echo "        // -->\n";
        echo "        </SCRIPT>\n";
    }
    
    public function panelNull($validate) {
        return $validate;
    }

    public function panelSearch($validate) {
        if($validate) {
            if($_REQUEST["print"] && $_REQUEST["seltag"]) {
               $this->printTag($_REQUEST["seltag"]);
            }
            return ($_REQUEST["seltag"] || $_REQUEST["new"]) &&
                       !$_REQUEST["bup_x"] && !$_REQUEST["bdown_x"] &&
                       !$_REQUEST["go"] && !$_REQUEST["print"];
        }
        $this->emitAlbumSel();
        $this->skipVar("bup");
        $this->skipVar("bup_x");
        $this->skipVar("bup_y");
        $this->skipVar("bdown");
        $this->skipVar("bdown_x");
        $this->skipVar("bdown_y");
        $this->skipVar("go");
        $this->skipVar("search");
        $this->skipVar("coll");
        $this->skipVar("list");
        $this->skipVar("up");
        $this->skipVar("down");
        $this->skipVar("seltag");
        $this->skipVar("new");
        $this->skipVar("edit");
        $this->skipVar("next");
        $this->skipVar("done");
        $this->skipVar("print");
        $this->skipVar("selpubkey");
        $this->skipVar("tdb");
        $this->skipVar("nextTrack");
        $this->skipVar("artist");
        $this->skipVar("album");
        $this->skipVar("category");
        $this->skipVar("medium");
        $this->skipVar("format");
        $this->skipVar("location");
        $this->skipVar("bin");
        for($i=1; isset($_POST["track" . $i]); $i++) {
            $this->skipVar("track" . $i);
            $this->skipVar("artist" . $i);
        }
    }
           
    public function panelDetails($validate) {
        if($validate) {
            $success = ($_REQUEST["coll"] || $_REQUEST["artist"]) &&
                                         $_REQUEST["album"];
            if($_REQUEST["next"])
                $this->editorPanels["details"][1] = "tracks";
            else if($_REQUEST["done"] && $success && $this->insertUpdateAlbum())
                $this->editorPanels["details"][1] = "search";
            return $success;
        }
        $this->albumForm();
        $this->skipVar("bup");
        $this->skipVar("bup_x");
        $this->skipVar("bup_y");
        $this->skipVar("bdown");
        $this->skipVar("bdown_x");
        $this->skipVar("bdown_y");
        $this->skipVar("search");
        $this->skipVar("go");
        $this->skipVar("coll");
        $this->skipVar("artist");
        $this->skipVar("album");
        $this->skipVar("label");
        $this->skipVar("category");
        $this->skipVar("medium");
        $this->skipVar("format");
        $this->skipVar("location");
        $this->skipVar("bin");
        $this->skipVar("list");
        $this->skipVar("up");
        $this->skipVar("down");
        $this->skipVar("edit");
        $this->skipVar("next");
        $this->skipVar("done");
    }
    
    public function panelLabel($validate) {
        if($validate) {
            $success = ($_REQUEST["selpubkey"] || $_REQUEST["lnew"] ||
                       (string)($_REQUEST["selpubkey"]) == '0') &&
                       !$_REQUEST["go"] && !$_REQUEST["bup_x"] &&
                       !$_REQUEST["bdown_x"];
            if($success && $_REQUEST["next"])
                if($_REQUEST["seltag"])
                   $this->editorPanels["label"][1] = "details";
                else
                   $this->editorPanels["label"][1] = "tracks";
            return $success;
        }
        $this->emitLabelSel();
        $this->skipVar("bup");
        $this->skipVar("bup_x");
        $this->skipVar("bup_y");
        $this->skipVar("bdown");
        $this->skipVar("bdown_x");
        $this->skipVar("bdown_y");
        $this->skipVar("go");
        $this->skipVar("search");
        $this->skipVar("list");
        $this->skipVar("up");
        $this->skipVar("down");
        $this->skipVar("lnew");
        $this->skipVar("edit");
        $this->skipVar("next");
        $this->skipVar("selpubkey");
        $this->skipVar("name");
        $this->skipVar("address");
        $this->skipVar("city");
        $this->skipVar("state");
        $this->skipVar("zip");
    }
    
    public function panelLDetails($validate) {
        if($validate) {
            $success = $_REQUEST["name"] != "";
            if($success && $this->subaction == "labels")
                 $this->insertUpdateLabel();
            else if($_REQUEST["seltag"])
                 $this->editorPanels["ldetails"][1] = "details";
            return $success;
        }
        $this->labelForm();
        $this->skipVar("name");
        $this->skipVar("address");
        $this->skipVar("city");
        $this->skipVar("state");
        $this->skipVar("zip");
        $this->skipVar("foreign");
        $this->skipVar("phone");
        $this->skipVar("fax");
        $this->skipVar("email");
        $this->skipVar("url");
        $this->skipVar("attention");
        $this->skipVar("maillist");
        $this->skipVar("mailcount");
        $this->skipVar("list");
        $this->skipVar("up");
        $this->skipVar("down");
        $this->skipVar("edit");
        $this->skipVar("next");
    }
    
    public function panelTracks($validate) {
        if($validate)
            return $_REQUEST["next"] && $this->validateTracks() && $this->insertUpdateAlbum();
        $this->trackForm();
        $this->skipVar("nextTrack");
        $this->skipVar("more");
        $this->skipVar("list");
        $this->skipVar("up");
        $this->skipVar("down");
        $this->skipVar("edit");
        $this->skipVar("next");
    }
    
    private function skipVar($name) {
        $this->emitted[$name] = "X";
    }
    
    private function emitHidden($name, $value) {
        $post = $_SERVER["REQUEST_METHOD"] == "POST";
    
        if($post)
            $_POST[$name] = $value;
        else
            $_GET[$name] = $value;
    }
    
    private function emitVars() {
        $post = $_SERVER["REQUEST_METHOD"] == "POST";
    
        foreach($post?$_POST:$_GET as $key => $value)
            if(!$this->emitted[$key])
                 echo "    <INPUT TYPE=HIDDEN NAME=$key VALUE=\"" . htmlentities(stripslashes($value)) . "\">\n";
    }

    private function insertUpdateAlbum() {
        $album = $this->getAlbum();
        $result = Engine::api(IEditor::class)->insertUpdateAlbum($album, $this->getTracks(), $this->getLabel());

        if($result) {
            if($_REQUEST["new"]) {
                $_REQUEST["seltag"] = $album["tag"];
                $this->printTag($_REQUEST["seltag"]);
            }

            $this->albumAdded = $_REQUEST["new"];
            $this->albumUpdated = !$_REQUEST["new"];
            $this->skipVar("name");
        }

        return $result;
    }

    private function insertUpdateLabel() {
        $label = $this->getLabel();
        $result = Engine::api(IEditor::class)->insertUpdateLabel($label);
        if($result) {
            $this->albumAdded = $_REQUEST["lnew"];
            $this->albumUpdated = !$_REQUEST["lnew"];
            if($this->albumAdded)
                $_REQUEST["selpubkey"] = $label["pubkey"];
            $_REQUEST["search"] = $_REQUEST["name"];
        }

        return $result;
    }

    private function getAlbum() {
         $album = $_REQUEST;
         $album["tag"] = $album["new"]?0:$album["seltag"];
         if(array_key_exists("selpubkey", $album))
              $album["pubkey"] = $album["selpubkey"];
         return $album;
    }
    
    private function getLabel() {
         $label = $_REQUEST;
         $label["pubkey"] = $label["lnew"]?0:$label["selpubkey"];
         return $label;
    }
    
    private function getTracks() {
         $tracks = array();
         $isColl = array_key_exists("coll", $_REQUEST) && $_REQUEST["coll"];
         for($i=1;
                   array_key_exists("track".$i, $_POST) &&
                       !self::isEmpty($_POST["track". $i]); $i++)
              $tracks[$i] = $isColl?[ "track" => $_POST["track".$i],
                                            "artist" => $_POST["artist".$i] ]:
                                    $_POST["track".$i];
         return $tracks;
    }
    
    private function getTitle($seq) {
        $albumLabel = htmlentities(stripslashes(($_REQUEST["coll"]?"":$_REQUEST["artist"] . " / ") . $_REQUEST["album"]));
        switch($seq) {
        case "search":
            $title = "Album Editor";
            if($this->albumAdded)
                $title = "Album Added!";
            else if($this->albumUpdated)
                $title = "Album Updated!";
            if($this->tagPrinted) {
                $printed = $this->canPrintLocal()?"Printed":"Queued";
                $title .= "&nbsp;&nbsp;<FONT CLASS=\"success\">Tag $printed</FONT>";
            }
            break;
        case "details":
            $title = $_REQUEST["new"]?"New Album":"Edit Album";
            break;
        case "label":
            if($this->subaction == "labels") {
                $title = "Label Editor";
                if($this->albumAdded)
                    $title = "Label Added!";
                else if($this->albumUpdated)
                    $title = "Label Updated!";
            } else
                $title = "Select label for $albumLabel";
            break;
        case "ldetails":
            $title = "Label Details";
            break;
        case "tracks":
            $title = "Tracks for $albumLabel";
            break;
        case "select":
            $title = "Select tags to print";
            if($this->tagPrinted)
                $title .= "&nbsp;&nbsp;<FONT CLASS=\"success\">Tag(s) Printed</FONT>";
            break;
        case "form":
            $title = "Select the label format";
            break;
        case "print":
            $title = "Place album tags on the label form";
            break;
        case "confirm":
            $title = "Tags are ready for printing";
            break;
        default:
            $title = "Album Editor";
            break;
        }
        return $title;
    }
    
    private function emitAlbumSel() {
         UI::emitJS('js/libed.album.js');
         UI::emitJS('js/libed.common.js');
    
         echo "<TABLE CELLPADDING=5 CELLSPACING=5 WIDTH=\"100%\"><TR><TD VALIGN=TOP WIDTH=220>\n";
         echo "  <INPUT TYPE=HIDDEN NAME=seltag id='seltag' VALUE=\"".$_REQUEST["seltag"]."\">\n";
         echo "<TABLE BORDER=0 CELLPADDING=4 CELLSPACING=0 WIDTH=\"100%\">";
         echo "<TR><TD COLSPAN=2 ALIGN=LEFT><B>Search:</B><BR><INPUT TYPE=TEXT CLASS=text STYLE=\"width:214px;\" NAME=search id='search' VALUE=\"$osearch\" autocomplete=off><BR>\n";
         echo "<SPAN CLASS=\"sub\">compilation?</SPAN><INPUT TYPE=CHECKBOX NAME=coll" . ($osearch&&$_REQUEST["coll"]?" CHECKED":"") . " id='coll'></TD><TD></TD></TR>\n";
         echo "  <TR><TD COLSPAN=2 ALIGN=LEFT><INPUT NAME=\"bup\" id='bup' VALUE=\"&nbsp;\" TYPE=\"submit\" CLASS=\"editorUp\"><BR><SELECT style=\"width:220px;\" class=\"select\" NAME=list id='list' SIZE=$this->limit>\n";
         for($i=0; $i<$this->limit; $i++)
              echo "  <OPTION VALUE=\"\">\n";
         echo "</SELECT><BR><INPUT NAME=\"bdown\" id='bdown' VALUE=\"&nbsp;\" TYPE=\"submit\" CLASS=\"editorDown\" ></TD>\n";
         echo "</TR></TABLE>\n";
         echo "  <INPUT TYPE=HIDDEN NAME=up id='up' VALUE=\"\">\n";
         echo "  <INPUT TYPE=HIDDEN NAME=down id='down' VALUE=\"\">\n";
         echo "  <INPUT TYPE=HIDDEN id='list-size' VALUE=\"$this->limit\">\n";
    ?>
    </TD><TD>
    <TABLE>
      <TR><TD ALIGN=RIGHT>Album&nbsp;Tag:</TD><TD ID="tag"></TD></TR>
      <TR><TD ALIGN=RIGHT>Artist:</TD><TD ID="artist" CLASS="header"></TD></TR>
      <TR><TD ALIGN=RIGHT>Album:</TD><TD ID="album" CLASS="header"></TD></TR>
      <TR><TD ALIGN=RIGHT>Category:</TD><TD ID="category"></TD></TR>
      <TR><TD ALIGN=RIGHT>Media:</TD><TD ID="medium"></TD></TR>
      <TR><TD ALIGN=RIGHT>Format:</TD><TD ID="size"></TD></TR>
      <TR><TD ALIGN=RIGHT>Location:</TD><TD><SPAN ID="location"></SPAN><SPAN ID="bin"></SPAN></TD></TR>
      <TR><TD ALIGN=RIGHT>Date In:</TD><TD ID="created"></TD></TR>
      <TR><TD ALIGN=RIGHT>Date Mod:</TD><TD ID="updated"></TD></TR>
      <TR><TD ALIGN=RIGHT>Label:</TD><TD ID="name" CLASS="header"></TD></TR>
      <TR><TD ALIGN=RIGHT></TD><TD ID="address"></TD></TR>
      <TR><TD ALIGN=RIGHT></TD><TD><SPAN ID="city"></SPAN>&nbsp;<SPAN ID="state"></SPAN>&nbsp;<SPAN ID="zip"></SPAN></TD></TR>
      <TR><TD ALIGN=RIGHT></TD><TD ID="label3">&nbsp;</TD></TR>
    </TABLE>
    </TD></TR>
    <TR><TD ALIGN=CENTER>
    <!--P ALIGN=CENTER-->
      <INPUT TYPE=SUBMIT NAME=new CLASS=submit VALUE="  New  ">&nbsp;
      <INPUT TYPE=SUBMIT NAME=edit CLASS=submit VALUE="  Edit  ">&nbsp; <?php
         if(!empty($this->printConfig['print_methods']))

             echo "      <INPUT TYPE=SUBMIT NAME=print CLASS=submit VALUE=\"  Print  \">&nbsp;\n"; ?>
    <!--/P-->
    </TD><TD></TD></TR>
    </TABLE>
    <?php
    }

    private function albumForm() {
        UI::emitJS('js/libed.common.js');
    ?>
    <TABLE>
    <?php 
        $coll = $_REQUEST["coll"];
        if($_REQUEST["new"]) {
            echo "  <TR><TD></TD><TD>&nbsp;</TD></TR>\n";
            $agenre = $category?$category:"G";
            $amedium = $medium?$medium:"C";
            $aformat = $format?$format:"F";
            $alocation = $location?$location:"L";
            $this->skipVar("seltag");
            $this->skipVar("selpubkey");
        } else {
            $row = Engine::api(IEditor::class)->getAlbum($_REQUEST["seltag"]);
            $artist = stripslashes($row["artist"]);
            $album = stripslashes($row["album"]);
            $agenre = $row["category"];
            $amedium = $row["medium"];
            $aformat = $row["size"];
            $alocation = $row["location"];
            $bin = $row["bin"];
            $coll = substr($artist, 0, 8) == "[coll]: ";
            $name = $_REQUEST["name"];
            if(!$name) {
                if($_REQUEST["selpubkey"]) {
                    $row = Engine::api(IEditor::class)->getLabel($_REQUEST["selpubkey"]);
                    $name = $row["name"];
                    $address = $row["address"];
                    $city = $row["city"];
                    $state = $row["state"];
                    $zip = $row["zip"];
                } else {
                    $name = $row["name"];
                    $address = $row["address"];
                    $city = $row["city"];
                    $state = $row["state"];
                    $zip = $row["zip"];
                }
            } else {
                $address = $_REQUEST["address"];
                $city = $_REQUEST["city"];
                $state = $_REQUEST["state"];
                $zip = $_REQUEST["zip"];
            }
            echo "  <TR><TD></TD><TD>&nbsp;</TD></TR>\n";
            echo "  <TR><TD ALIGN=RIGHT>Album&nbsp;Tag:</TD><TH ALIGN=LEFT ID=\"tag\">".$_REQUEST["seltag"]."</TH></TR>\n";
        }
    ?>
      <TR><TD ALIGN=RIGHT>Compilation:</TD><TD CLASS="header"><INPUT TYPE=CHECKBOX onClick="return setComp();" NAME=coll<?php echo $coll?" CHECKED":"";?>></TD></TR>
      <TR><TD ID="lartist" ALIGN=RIGHT STYLE="visibility:<?php echo $coll?"hidden":"visible";?>">Artist:</TD><TD CLASS="header"><INPUT NAME=artist TYPE=TEXT CLASS=text SIZE=60 VALUE="<?php echo htmlentities(stripslashes($artist));?>" STYLE="visibility:<?php echo $coll?"hidden":"visible";?>" onChange="zkAlpha(this)"></TD></TR>
      <TR><TD ALIGN=RIGHT>Album:</TD><TD CLASS="header"><INPUT NAME=album TYPE=TEXT CLASS=text SIZE=60 VALUE="<?php echo htmlentities(stripslashes($album));?>" onChange="zkAlpha(this)"></TD></TR>
      <TR><TD ALIGN=RIGHT>Category:</TD><TD><SELECT NAME=category CLASS=textsp>
    <?php 
        foreach(Search::GENRES as $code => $genre) {
            $selected = ($agenre == $code)?" SELECTED":"";
            echo "             <OPTION VALUE=\"$code\"$selected>$genre\n";
        }
    ?>
                    </SELECT></TD></TR>
      <TR><TD ALIGN=RIGHT>Media:</TD><TD><SELECT NAME=medium CLASS=textsp>
    <?php 
        foreach(Search::MEDIA as $code => $medium) {
            $selected = ($amedium == $code)?" SELECTED":"";
            echo "             <OPTION VALUE=\"$code\"$selected>$medium\n";
        }
    ?>
                    </SELECT></TD></TR>
      <TR><TD ALIGN=RIGHT>Format:</TD><TD><SELECT NAME=format CLASS=textsp>
    <?php 
        foreach(Search::LENGTHS as $code => $format) {
            $selected = ($aformat == $code)?" SELECTED":"";
            echo "             <OPTION VALUE=\"$code\"$selected>$format\n";
        }
    ?>
                    </SELECT></TD></TR>
    <?php 
        echo "  <TR><TD ALIGN=RIGHT>Location:</TD><TD><SELECT NAME=location CLASS=textsp onChange=\"return setLocation();\">\n";
        foreach(Search::LOCATIONS as $code => $location) {
            $selected = ($alocation == $code)?" SELECTED":"";
            echo "             <OPTION VALUE=\"$code\"$selected>$location\n";
        }
    ?>
                    </SELECT>&nbsp;&nbsp;<SPAN ID=lbin STYLE="visibility:<?php echo ($alocation == 'G')?"visible":"hidden";?>">Bin:&nbsp;</SPAN><INPUT NAME=bin TYPE=text CLASS=text SIZE=10 VALUE="<?php echo $bin;?>" STYLE="visibility:<?php echo ($alocation == 'G')?"visible":"hidden";?>"></TD></TR>
    <?php 
        if(!$_REQUEST["new"]) {
    ?>
      <TR><TD COLSPAN=2></TD></TR>
      <TR><TD ALIGN=RIGHT>Label:</TD><TD CLASS="header"><?php echo $name;?></TD></TR>
      <TR><TD></TD><TD><?php echo $address;?></TD></TR>
      <TR><TD></TD><TD><?php echo "$city $state $zip";?></TD></TR>
      <!--TR><TD ALIGN=RIGHT>Date In:</TD><TD></TD></TR>
      <TR><TD ALIGN=RIGHT>Date Mod:</TD><TD></TD></TR>
      <TR><TD ALIGN=RIGHT>Label:</TD><TD CLASS="header"></TD></TR-->
    <?php 
        }
    ?>
      <!--TR><TD ALIGN=RIGHT></TD><TD></TD></TR>
      <TR><TD ALIGN=RIGHT></TD><TD></TD></TR-->
      <TR><TD ALIGN=RIGHT></TD><TD>&nbsp;</TD></TR>
      <TR><TD></TD><TD><?php if(!$_REQUEST["new"]){?><INPUT TYPE=SUBMIT NAME=edit CLASS=submit VALUE="  Change Label...  ">&nbsp;&nbsp;<?php }?><INPUT TYPE=SUBMIT NAME=<?php echo $_REQUEST["new"]?"edit":"next";?> CLASS=submit VALUE="  <?php echo $_REQUEST["new"]?"Next &gt;&gt;":"Tracks...";?>  ">&nbsp;&nbsp;<?php if(!$_REQUEST["new"]){?><INPUT TYPE=SUBMIT NAME=done CLASS=submit VALUE="  Done!  "><?php }?></TD></TR>
    </TABLE>
    <?php 
        echo "  <INPUT TYPE=HIDDEN NAME=new VALUE=\"".$_REQUEST["new"]."\">\n";
        UI::setFocus($coll?"album":"artist");
    }
    
    private function emitLabelSel() {
         UI::emitJS('js/libed.label.js');
         UI::emitJS('js/libed.common.js');
    
        echo "<TABLE CELLPADDING=5 CELLSPACING=5 WIDTH=\"100%\"><TR><TD VALIGN=TOP WIDTH=230>\n";
        echo "  <INPUT TYPE=HIDDEN NAME=selpubkey id='selpubkey' VALUE=\"".$_REQUEST["selpubkey"]."\">\n";
        echo "<TABLE BORDER=0 CELLPADDING=4 CELLSPACING=0 WIDTH=\"100%\">";
        echo "<TR><TD COLSPAN=2 ALIGN=LEFT><B>Search:</B><BR><INPUT TYPE=TEXT CLASS=text STYLE=\"width:214px;\" NAME=search id='search' VALUE=\"$osearch\" autocomplete=off></TD></TR>\n";
        echo "  <TR><TD COLSPAN=2 ALIGN=LEFT><INPUT NAME=\"bup\" id=\"bup\" VALUE=\"&nbsp;\" TYPE=\"submit\" CLASS=\"editorUp\"><BR><SELECT style=\"width:220px;\" class=\"select\" NAME=list id='list' SIZE=$this->limit>\n";
        for($i=0; $i<$this->limit; $i++)
            echo "  <OPTION VALUE=\"\">\n";
        echo "</SELECT><BR><INPUT NAME=\"bdown\" id=\"bdown\" VALUE=\"&nbsp;\" TYPE=\"submit\" CLASS=\"editorDown\"></TD>\n";
        echo "</TR></TABLE>\n";
        echo "  <INPUT TYPE=HIDDEN NAME=up id='up' VALUE=\"\">\n";
        echo "  <INPUT TYPE=HIDDEN NAME=down id='down' VALUE=\"\">\n";
        echo "  <INPUT TYPE=HIDDEN id='list-size' VALUE=\"$this->limit\">\n";
        echo "  <INPUT TYPE=HIDDEN id='seltag' VALUE=\"".$_REQUEST["seltag"]."\">\n";
        echo "  <INPUT TYPE=HIDDEN id='req-name' VALUE=\"".$_REQUEST["name"]."\">\n";
    ?>
    </TD><TD>
    <TABLE>
      <TR><TD ALIGN=RIGHT>Label&nbsp;ID:</TD><TD ID="pubkey"></TD></TR>
      <TR><TD ALIGN=RIGHT>Name:</TD><TD ID="name" CLASS="header"></TD></TR>
      <TR><TD ALIGN=RIGHT></TD><TD ID="address"></TD></TR>
      <TR><TD ALIGN=RIGHT></TD><TD><SPAN ID="city"></SPAN>&nbsp;<SPAN ID="state"></SPAN>&nbsp;<SPAN ID="zip"></SPAN></TD></TR>
      <TR><TD ALIGN=RIGHT>Attn:</TD><TD ID="attention"></TD></TR>
      <TR><TD ALIGN=RIGHT>E-mail:</TD><TD ID="email"></TD></TR>
      <TR><TD ALIGN=RIGHT>URL:</TD><TD ID="url"></TD></TR>
      <TR><TD ALIGN=RIGHT>Mail List:</TD><TD ID="maillist"></TD></TR>
      <TR><TD ALIGN=RIGHT>Mail Count:</TD><TD ID="mailcount"></TD></TR>
      <TR><TD ALIGN=RIGHT>Date In:</TD><TD ID="pcreated"></TD></TR>
      <TR><TD ALIGN=RIGHT>Date Mod:</TD><TD ID="modified"></TD></TR>
      <TR><TD ALIGN=RIGHT></TD><TD ID="label3">&nbsp;</TD></TR>
    </TABLE>
    </TD></TR>
    <TR><TD ALIGN=CENTER>
    <!--P ALIGN=CENTER-->
      <INPUT TYPE=SUBMIT NAME=lnew CLASS=submit VALUE="  New  ">&nbsp;
      <INPUT TYPE=SUBMIT NAME=edit CLASS=submit VALUE="  Edit  ">&nbsp;
    <?php  if($_REQUEST["seltag"]) { ?>
      <INPUT TYPE=SUBMIT NAME=next CLASS=submit VALUE="   OK   ">&nbsp;
    <?php  } else if($this->subaction != "labels") { ?>
      <INPUT TYPE=SUBMIT NAME=next CLASS=submit VALUE=" Tracks &gt;&gt; ">&nbsp;
    <?php  } ?>
    <!--/P-->
    </TD><TD></TD></TR>
    </TABLE>
    <?php
    }
    
    private function labelForm() {
        UI::emitJS('js/libed.common.js');
        echo "<TABLE>\n";
    
        if($_REQUEST["lnew"]) {
            echo "  <TR><TD></TD><TD>&nbsp;</TD></TR>\n";
            $this->skipVar("selpubkey");
        } else {
            $row = Engine::api(IEditor::class)->getLabel($_REQUEST["selpubkey"]);
            $foreign = $row["international"] == "T";
            echo "  <TR><TD></TD><TD>&nbsp;</TD></TR>\n";
            echo "  <TR><TD ALIGN=RIGHT>Label&nbsp;ID:</TD><TH ALIGN=LEFT ID=\"pubkey\">".$row["pubkey"]."</TH></TR>\n";
        }
    ?>
      <TR><TD ALIGN=RIGHT>Name:</TD><TD CLASS="header"><INPUT NAME=name TYPE=TEXT CLASS=text SIZE=60 VALUE="<?php echo htmlentities(stripslashes($row["name"]));?>" onChange="zkAlpha(this,true)"></TD></TR>
      <TR><TD ALIGN=RIGHT>Attn:</TD><TD><INPUT NAME=attention TYPE=TEXT CLASS=text SIZE=60 VALUE="<?php echo htmlentities(stripslashes($row["attention"]));?>" onChange="zkAlpha(this,true)"></TD></TR>
      <TR><TD ALIGN=RIGHT>Address:</TD><TD><INPUT NAME=address TYPE=TEXT CLASS=text SIZE=60 VALUE="<?php echo htmlentities(stripslashes($row["address"]));?>" onChange="zkAlpha(this,true)"></TD></TR>
      <TR><TD ALIGN=RIGHT>City:</TD><TD><INPUT NAME=city TYPE=TEXT CLASS=text SIZE=60 VALUE="<?php echo htmlentities(stripslashes($row["city"]));?>" onChange="zkAlpha(this,true)"></TD></TR>
      <TR><TD ALIGN=RIGHT ID=lstate STYLE="visibility:<?php echo $foreign?"hidden":"visible";?>">State:</TD><TD><INPUT NAME=state TYPE=TEXT CLASS=text SIZE=20 VALUE="<?php echo htmlentities(stripslashes($row["state"]));?>" onChange="this.value=this.value.toUpperCase();"></TD></TR>
      <TR><TD ALIGN=RIGHT ID=lzip><?php echo $foreign?"&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Country":"Postal Code";?>:</TD><TD><INPUT NAME=zip TYPE=TEXT CLASS=text SIZE=20 VALUE="<?php echo htmlentities(stripslashes($row["zip"]));?>" onChange="this.value=this.value.toUpperCase();"><INPUT NAME=foreign TYPE=CHECKBOX onClick="return setForeign();"<?php echo $foreign?" CHECKED":"";?>><SPAN CLASS="sub">Foreign?</SPAN></TD></TR>
      <TR><TD ALIGN=RIGHT>Phone:</TD><TD><INPUT NAME=phone TYPE=TEXT CLASS=text SIZE=20 VALUE="<?php echo htmlentities(stripslashes($row["phone"]));?>"></TD></TR>
      <TR><TD ALIGN=RIGHT>Fax:</TD><TD><INPUT NAME=fax TYPE=TEXT CLASS=text SIZE=20 VALUE="<?php echo htmlentities(stripslashes($row["fax"]));?>"></TD></TR>
      <TR><TD ALIGN=RIGHT>E-Mail:</TD><TD><INPUT NAME=email TYPE=TEXT CLASS=text SIZE=60 VALUE="<?php echo htmlentities(stripslashes($row["email"]));?>"></TD></TR>
      <TR><TD ALIGN=RIGHT>URL:</TD><TD><INPUT NAME=url TYPE=TEXT CLASS=text SIZE=60 VALUE="<?php echo htmlentities(stripslashes($row["url"]));?>"></TD></TR>
      <TR><TD ALIGN=RIGHT>Mail List:</TD><TD><INPUT NAME=maillist TYPE=TEXT CLASS=text SIZE=5 VALUE="<?php echo $row["maillist"];?>" onChange="zkAlpha(this,true)"></TD></TR>
      <TR><TD ALIGN=RIGHT>Mail Count:</TD><TD><INPUT NAME=mailcount TYPE=TEXT CLASS=text SIZE=5 VALUE="<?php echo $row["mailcount"];?>"></TD></TR>
    <?php 
        if(!$_REQUEST["lnew"]) {
    ?>
      <TR><TD ALIGN=RIGHT>Date In:</TD><TD><?php echo $row["pcreated"];?></TD></TR>
      <TR><TD ALIGN=RIGHT>Date Mod:</TD><TD><?php echo $row["modified"];?></TD></TR>
    <?php 
        }
    ?>
      <TR><TD ALIGN=RIGHT></TD><TD>&nbsp;</TD></TR>
      <TR><TD></TD><TD><INPUT TYPE=SUBMIT NAME=edit CLASS=submit VALUE="  <?php echo ($this->subaction=="labels")?"Done!":($_REQUEST["seltag"]?"  OK  ":"Next &gt;&gt;");?>  ">&nbsp;</TD></TR>
    </TABLE>
    <?php 
        UI::setFocus("name");
    }
    
    private function validateTracks() {
        $lowestBlank = $highestTrack = 0;
        foreach($_POST as $key => $value) {
            if(substr($key, 0, 5) == "track") {
                $i = substr($key, 5) * 1;
                if(!self::isEmpty($value) && $i > $highestTrack)
                    $highestTrack = $i;
                if(self::isEmpty($value) && (!$lowestBlank || $i < $lowestBlank))
                    $lowestBlank = $i;
            }
        }
        return !$lowestBlank || $lowestBlank >= $highestTrack;
    }
    
    private function trackForm() {
        UI::emitJS('js/libed.common.js');
        if($_REQUEST["seltag"] && !$_REQUEST["tdb"]) {
            $tracks = Engine::api(IEditor::class)->getTracks($_REQUEST["seltag"], $_REQUEST["coll"]);
            while($row = $tracks->fetch()) {
                $this->emitHidden("track".$row["seq"], $row["track"]);
                $_POST["track".$row["seq"]] = $row["track"];
                if($_REQUEST["coll"]) {
                    $this->emitHidden("artist" . $row["seq"], $row["artist"]);
                    $_POST["artist".$row["seq"]] = $row["artist"];
                }
            }
            $this->emitHidden("tdb", "true");
        }
        if($_REQUEST["nextTrack"]) {
            // validate previous batch of tracks were entered
            $lastBatch = $_REQUEST["nextTrack"] - $this->tracksPerPage;
            for($i=0; $i<$this->tracksPerPage; $i++) {
                if(self::isEmpty($_POST["track".(int)($lastBatch+$i)]) ||
                        $_REQUEST["coll"] && self::isEmpty($_POST["artist".(int)($lastBatch+$i)])) {
                    $_REQUEST["nextTrack"] -= $this->tracksPerPage;
                    $focusTrack = $lastBatch+$i;
                    break;
                }
            }
        } else $_REQUEST["nextTrack"] = 1;
    
        echo "<TABLE>\n";
        echo "<TR><TD></TD><TD".($_REQUEST["coll"]?" COLSPAN=3":"")." ALIGN=RIGHT>Insert/Delete&nbsp;Track:&nbsp;<INPUT TYPE=BUTTON NAME=insert CLASS=submit onClick='insertTrack(".$_REQUEST["coll"].");' VALUE='+'>&nbsp;<INPUT TYPE=BUTTON NAME=delete CLASS=submit onClick='deleteTrack(".$_REQUEST["coll"].");' VALUE='&minus;'></TD></TR>\n";
        $size = $_REQUEST["coll"]?30:60;
        for($i=0; $i<$this->tracksPerPage; $i++) {
            $trackNum = $_REQUEST["nextTrack"] + $i;
            echo "  <TR><TD ALIGN=RIGHT>Track $trackNum:</TD><TD><INPUT NAME=track$trackNum VALUE=\"".htmlentities(stripslashes($_POST["track".$trackNum]))."\" TYPE=text CLASS=text SIZE=$size onChange=\"zkAlpha(this,true)\" onFocus=\"cf($trackNum);\">";
            $this->skipVar("track".$trackNum);
            if($_REQUEST["coll"]) {
                echo "</TD><TD ALIGN=RIGHT>Artist:</TD><TD><INPUT NAME=artist$trackNum VALUE=\"".htmlentities(stripslashes($_POST["artist".$trackNum]))."\" TYPE=text CLASS=text SIZE=$size onChange=\"zkAlpha(this,false)\" onFocus=\"cf($trackNum);\">";
                $this->skipVar("artist".$trackNum);
            }
            echo "</TD></TR>\n";
        }
        echo "  <TR><TD></TD><TD".($_REQUEST["coll"]?" COLSPAN=3":"")."><INPUT TYPE=SUBMIT NAME=more CLASS=submit VALUE='  More Tracks...  '>&nbsp;&nbsp;&nbsp;<INPUT TYPE=SUBMIT NAME=next CLASS=submit VALUE='  Done!  '></TD></TR>\n";
    ?>
    </TABLE>
    <INPUT TYPE=HIDDEN NAME=nextTrack VALUE=<?php echo (int)($_REQUEST["nextTrack"]+$this->tracksPerPage);?>>
    <?php
        UI::setFocus("track" . ($focusTrack?$focusTrack:$_REQUEST["nextTrack"]));
    }

    public static function makeLabel($tag, $charset, $dark=1,
                                        $boxEscape="", $textEscape="") {
        $al = Engine::api(IEditor::class)->getAlbum($tag);
      
        while($tag) {
           $digits[] = $tag % 10;
           $tag = floor($tag / 10);
        }

        $output = $boxEscape."\r";
        for($row=0; $row < 3; $row++) {
            for($darken=0; $darken < $dark; $darken++) {
                for($i=sizeof($digits)-1; $i >=0; $i--) {
                    for($col=0; $col < sizeof(self::$tagFont[$digits[$i]]); $col++)
                        $output .= chr(self::$tagFont[$digits[$i]][$col][$row]);
                    $output .= " ";
                    if($i && !($i%3))
                        $output .= "  ";
                }
                $output .= "\r";
            }
            $output .= "\n";
        }

        if($charset == UI::CHARSET_UTF8)
            $output = strtr($output, self::$boxToUTF8);

        $output .= $textEscape;
    
        $artist = UI::deLatin1ify($al["artist"], $charset);
        ////if(mb_strlen($artist) > 25)
        ////  $artist = mb_substr($artist, 0, 25) . "...";
        if(mb_strlen($artist) > 30)
            $artist = mb_substr($artist, 0, 30);
      
        for($darken=0; $darken < $dark; $darken++)
            $output .= $artist . "\r";
        $output .= "\n";
    
        $album = UI::deLatin1ify($al["album"], $charset);
        $cat = " (" . Search::GENRES[$al["category"]] . ")";
        $maxAlbumLen = 23 - mb_strlen($cat);
        if(mb_strlen($album) > $maxAlbumLen + 3)
            $album = mb_substr($album, 0, $maxAlbumLen) . "...";
    
        $alcat = sprintf("  %s%".(26-mb_strlen($album))."s\r", $album, $cat);
        for($darken=0; $darken < $dark; $darken++)
            $output .= $alcat;
        $output .= "\n\n";

        return $output;
    }

    private function enqueueTag($tag) {
        if(!empty($this->printConfig['print_methods'])) {
            // Enqueue tag for later printing
            Engine::api(IEditor::class)->enqueueTag($tag, $this->session->getUser());
            $this->tagPrinted = 1;
        }
    }

    private function canPrintLocal() {
        return $this->session->isLocal() &&
                    in_array('lpr', $this->printConfig['print_methods']);
    }
    
    private function printTag($tag) {
        if(!$this->canPrintLocal()) {
            // Enqueue tag for later printing
            $this->enqueueTag($tag);
            return;
        }

        $charset = self::$charset[$this->printConfig['charset']];

        $template = $this->printConfig['use_template'];
        if($template) {
            $pdf = popen(__DIR__."/../".
                                  "zk print form=$template tags=$tag", "r");
            $output = stream_get_contents($pdf);
            pclose($pdf);
        } else
            $output = self::makeLabel($tag, $charset,
                                  $this->printConfig['darkness'],
                                  $this->printConfig['box_mode'],
                                  $this->printConfig['text_mode']);
    
        $printer = popen("lpr -P".$this->printConfig['print_queue'], "w");
        fwrite($printer, $output);
        pclose($printer);
    
        $this->tagPrinted = 1;
    }

    private function getPrintStatus() {
        $status = "";
        if($this->canPrintLocal()) {
            $printer = popen("lpoptions -d ".$this->printConfig['print_queue'], "r");
            $output = stream_get_contents($printer);
            pclose($printer);
            $options = explode(' ', $output);
            foreach($options as $option) {
                // we are interested only in "printer-state-reasons"
                $tuple = explode('=', $option);
                if($tuple[0] == "printer-state-reasons") {
                    // there can be 1 or more comma-separated reasons
                    $reasons = explode(',', $tuple[1]);
                    foreach($reasons as $reason) {
                        // remove suffix, if any, from the reason code
                        foreach(self::$printStatusSuffixes as $suffix) {
                            $suffixLen = strlen($suffix);
                            if(substr($reason, -$suffixLen) == $suffix) {
                                $reason = substr($reason, 0,
                                                 strlen($reason)-$suffixLen);
                                break;
                            }
                        }

                        // map reason code to user-friendly message
                        if(array_key_exists($reason, self::$printStatus))
                            $reason = self::$printStatus[$reason];

                        if($reason) {
                            if($status)
                                $status .= ", ";
                            $status .= $reason;
                        }
                    }
                    break;
                }
            }
        }
        return $status;
    }
}
