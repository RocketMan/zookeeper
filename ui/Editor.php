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
                echo "    <TABLE CELLPADDING=0 CELLSPACING=0 BORDER=0 WIDTH=\"100%\">\n      <TR><TH ALIGN=LEFT>$title</TH></TR>\n      <TR><TD HEIGHT=130 VALIGN=MIDDLE>\n";
    
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
                     if($_REQUEST["print"] && $this->session->isLocal())
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
             echo "       <INPUT TYPE=submit CLASS=submit NAME=printToPDF onclick=\"return validate();\" VALUE=\" Print To PDF &gt; \">\n";
         echo "    </P>\n"; ?>
    <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript"><!--
    function checkAll() {
      form = document.forms[0];
      all = form.all.checked;
      for(var i=0; i<form.length; i++)
         if(form[i].type == 'checkbox')
             form[i].checked = all;
    }
    function isLocal() {
<?php
    if($this->session->isLocal())
        echo "      return true;\n";
    else
        echo "      alert('tags can be printed to the label printer only at the station');\n      return false;\n";
?>
    }
    function validate() {
      form = document.forms[0];
      selected = 0;
      for(var i=0; i<form.length; i++)
         if(form[i].type == 'checkbox')
             selected |= form[i].checked;
      if(selected)
        return true;
      else {
        alert("Select at least one tag to proceed");
        return false;
      }
    }
    // -->
    </SCRIPT>
    <?php 
        UI::setFocus();
    }
    
    public function queueForm($validate) {
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
        echo "<P><INPUT TYPE=submit CLASS=submit NAME=back VALUE=\" &lt; Back \">&nbsp;&nbsp;&nbsp;<INPUT TYPE=SUBMIT CLASS=submit NAME=next onclick=\"return validate();\" VALUE=\" Next &gt; \"></P>\n";
?>
    <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript"><!--
    function validate() {
        var selected = false;
        var forms = document.getElementsByName('form');
        for(var i=0; i<forms.length; i++)
            if(forms[i].checked)
                return true;
        alert("Select a label format");
        return false;
    }
    // -->
    </SCRIPT>
    <?php
        $this->skipVar("form");
        $this->skipVar("back");
        $this->skipVar("next");
        UI::setFocus();
    }
    
    public function queuePlace($validate) {
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
                echo "<A HREF=\"#\" onClick=\"c($index);\" id=\"label$index\">".($i + $j*$numRow + 1)."</A>&nbsp;";
            }
            echo "<BR>\n";
        }
        echo "    </TD></TR>\n    <TR><TD>&nbsp;</TD></TR>\n";
        echo "    <TR><TD STYLE=\"text-align: right;\"><INPUT TYPE=submit CLASS=submit NAME=back VALUE=\" &lt; Back \">&nbsp;&nbsp;&nbsp;<INPUT TYPE=SUBMIT CLASS=submit NAME=next onclick=\"return validate();\" VALUE=\" Next &gt; \">\n";
        echo "    <INPUT TYPE=HIDDEN NAME=sel ID=sel VALUE=\"\"></TD></TR></TABLE>\n";
        echo "<SCRIPT LANGUAGE=\"JavaScript\" TYPE=\"text/javascript\"><!--\n";
        // 2018-06-14 this does not work in some browsers;
        // for now, we'll manually create an empty array
        //echo "    var sel = [...Array($numLabels)].map(x=>0);\n";
        // BEGIN WORKAROUND
        echo "    var sel = [ ";
        for($i=0; $i<$numLabels; $i++)
            echo "0, ";
        echo "];\n";
        // END WORKAROUND
?>
    var count = 0, max=<?php echo $count; ?>;
    function c(idx) {
      if(sel[idx]) {
        elt = document.getElementById('label'+idx);
        elt.style.background="white";
        elt.style.border="solid #696969 2px";
        sel[idx] = 0;
        if(count == max) {
          for(i=0; i<<?php echo $numLabels; ?>; i++) {
            if(!sel[i]) {
              elt = document.getElementById('label'+i);
              elt.style.background = "white";
            }
          }
        }
        count--;
      } else {
        if(count == max) return;
        elt = document.getElementById('label'+idx);
        elt.style.background="beige";
        elt.style.border="solid green 2px";
        sel[idx] = 1;
        count++;
        if(count == max) {
          for(i=0; i<<?php echo $numLabels; ?>; i++) {
            if(!sel[i]) {
              elt = document.getElementById('label'+i);
              elt.style.background = "#c3c3c3";
            }
          }
        }
      }
    }
    function validate() {
      if(count == 0) {
        alert("Select at least one label to print");
        return false;
      } else
        document.getElementById('sel').value = sel.join();

      return true;
    }
    // -->
    </SCRIPT>
    <?php
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
               $this->enqueueTag($_REQUEST["seltag"]);
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
                $this->enqueueTag($_REQUEST["seltag"]);
            }

            $this->albumAdded = $_REQUEST["new"];
            $this->albumUpdated = !$_REQUEST["new"];
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
                $title .= "&nbsp;&nbsp;<FONT CLASS=\"success\">Tag Queued</FONT>";
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

    private function emitZkAlpha($moveThe = 0) {
    ?>
<SCRIPT TYPE="text/javascript" LANGUAGE="JavaScript"><!--
nonalnum='.,!?&~ -+={[(|';
function zkAlpha(control<?php echo !$moveThe?", track":"";?>) {
  val=control.value;
  newVal='';
  for(i=0; i<val.length; i++)
  newVal += (i==0 || nonalnum.indexOf(val.charAt(i-1),0) >= 0)?val.charAt(i).toUpperCase():val.charAt(i).toLowerCase();
  if(<?php echo !$moveThe?"!track && ":"";?>newVal.substr(0, 4) == 'The ') newVal=newVal.substr(4)+', The';
  control.value=newVal;
}
// -->
</SCRIPT>
    <?php 
    }
    
    private function emitCache($fields) {
         echo "<SCRIPT TYPE=\"text/javascript\" LANGUAGE=\"JavaScript\" SRC=\"js/zooscript.js\"></SCRIPT>\n";
         echo "<SCRIPT TYPE=\"text/javascript\" LANGUAGE=\"JavaScript\">\n";
         echo "   fields = [";
         for($i=0; $i<sizeof($fields); $i++)
              echo " '" . $fields[$i] . "',";
         echo " ];\n";
         // 2018-06-14 this does not work in some browsers;
         // for now, we'll manually create an empty array
         //echo "   data = [...Array($this->limit)].map(e => Array(".sizeof($fields).").fill(''));\n\n";
         // BEGIN WORKAROUND
         echo "   data = [\n";
         for($i=0; $i<$this->limit; $i++) {
             echo "      [ ";
             for($j=0; $j<sizeof($fields); $j++)
                 echo "'', ";
             echo "],\n";
         }
         echo "];\n\n";
         // END WORKAROUND
    ?>
    function changeList() {
        i = document.forms[0].list.selectedIndex;
        document.forms[0].sel<?php echo $fields[0];?>.value = data[i][0];
        if (document.getElementById && !window.opera) {
            for(j=0; j<fields.length; j++) {
                if(document.getElementById(fields[j]))
                    if(fields[j] == 'email' || fields[j] == 'url') {
                        var html = '<A HREF="';
                        if(fields[j] == 'email' && data[i][j].indexOf('mailto:') != 0)
                            html += 'mailto:';
                        else if(fields[j] == 'url' && data[i][j].indexOf('http://') != 0)
                            html += 'http://';
                        html += data[i][j] + '"';
                        if(fields[j] == 'url')
                            html += ' TARGET="_blank"';
                        html += '>' + data[i][j] + '</A>';
                        document.getElementById(fields[j]).innerHTML = html;
                    } else
                        document.getElementById(fields[j]).innerHTML = data[i][j] + "&nbsp;";
            }
        } else if (document.all) {
            for(j=0; j<fields.length; j++) {
                if(eval("document.all." + fields[j]))
                    if(fields[j] == 'email' || fields[j] == 'url') {
                        var html = '<A HREF="';
                        if(fields[j] == 'email' && data[i][j].indexOf('mailto:') != 0)
                            html += 'mailto:';
                        else if(fields[j] == 'url' && data[i][j].indexOf('http://') != 0)
                            html += 'http://';
                        html += data[i][j] + '"';
                        if(fields[j] == 'url')
                            html += ' TARGET="_blank"';
                        html += '>' + data[i][j] + '</A>';
                        eval("document.all." + fields[j] + ".innerHTML = " + html);
                    } else
                        eval("document.all." + fields[j] + ".innerHTML = " + data[i][j]);
            }
        }
    }
   
    function onSearch(sync,e) {
        if(e.type == 'keyup' && (e.keyCode == 33 ||
               e.keyCode == 34 || e.keyCode == 38 || e.keyCode == 40))
        {
            switch(e.keyCode) {
            case 33:
              // page up
              if(sync.list.selectedIndex == 0) {
                upDown(e);
                return;
              }
              sync.list.selectedIndex = 0;
              break;
            case 38:
              // line up
              if(sync.list.selectedIndex == 0) {
                upDown(e);
                return;
              }
              sync.list.selectedIndex--;
              break;
            case 34:
              // page down
              if(sync.list.selectedIndex == sync.list.length-1) {
                upDown(e);
                return;
              }
              sync.list.selectedIndex = sync.list.length-1;
              break;
            case 40:
              // line down
              if(sync.list.selectedIndex == sync.list.length-1) {
                upDown(e);
                return;
              }
              sync.list.selectedIndex++;
              break;
            }
            changeList();
            return;
        }
    
        if(sync.Timer) {
            clearTimeout(sync.Timer);
            sync.Timer = null;
        }
        sync.Timer = setTimeout('onSearchNow()', 250);
    }
    // -->
</SCRIPT>
<?php 
    }
    
    private function emitAlbumSel() {
         $this->emitCache(API::ALBUM_FIELDS);
    
         echo "<TABLE CELLPADDING=5 CELLSPACING=5 WIDTH=\"100%\"><TR><TD VALIGN=TOP WIDTH=220>\n";
         echo "  <INPUT TYPE=HIDDEN NAME=seltag VALUE=\"\">\n";
         echo "<TABLE BORDER=0 CELLPADDING=4 CELLSPACING=0 WIDTH=\"100%\">";
         echo "<TR><TD COLSPAN=2 ALIGN=LEFT><B>Search:</B><BR><INPUT TYPE=TEXT CLASS=text STYLE=\"width:214px;\" NAME=search VALUE=\"$osearch\" autocomplete=off onkeyup=\"onSearch(document.forms[0],event);\" onkeypress=\"return event.keyCode != 13;\"><BR>\n";
         echo "<SPAN CLASS=\"sub\">compilation?</SPAN><INPUT TYPE=CHECKBOX NAME=coll" . ($osearch&&$_REQUEST["coll"]?" CHECKED":"") . " onclick=\"onSearch(document.forms[0],event);\"></TD><TD></TD></TR>\n";
         echo "  <TR><TD COLSPAN=2 ALIGN=LEFT><INPUT NAME=\"bup\" VALUE=\"1\" TYPE=\"image\" SRC=\"img/zk_list_up_beta.gif\" onclick=\"return scrollUp();\"><BR><SELECT style=\"width:220px;\" class=\"select\" NAME=list SIZE=$this->limit onChange='changeList()' onKeyDown='return upDown(event);'>\n";
         for($i=0; $i<$this->limit; $i++)
              echo "  <OPTION VALUE=\"\">\n";
         echo "</SELECT><BR><INPUT NAME=\"bdown\" VALUE=\"1\" TYPE=\"image\" SRC=\"img/zk_list_dn_beta.gif\" onclick=\"return scrollDown(-1);\"></TD>\n";
         echo "</TR></TABLE>\n";
         echo "  <INPUT TYPE=HIDDEN NAME=up VALUE=\"\">\n";
         echo "  <INPUT TYPE=HIDDEN NAME=down VALUE=\"\">\n";
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
    <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript"><!--
    function setFocus() {
    <?php
        if($_REQUEST["seltag"]) {
              echo "   loadXMLDoc(\"zkapi.php?method=getAlbumsRq&operation=searchByTag&size=$this->limit&key=".$_REQUEST["seltag"]."\",0);\n";
              echo "   document.forms[0].list.focus();\n";
        } else {
              echo "   scrollUp();\n";
              echo "   document.forms[0].search.focus();\n";
        }
    ?>
    }
    // -->
    </SCRIPT>
    <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript"><!--
    function onSearchNow() {
      var search = document.forms[0].search.value;
      if(search.lastIndexOf('.') == search.length-1 && search.length > 3)
         loadXMLDoc("zkapi.php?method=getAlbumsRq&operation=searchByTag&size=<?php echo $this->limit; ?>&key=" + urlEncode(search),0);
      else
         loadXMLDoc("zkapi.php?method=getAlbumsRq&operation=searchByName&size=<?php echo $this->limit; ?>&key=" + (document.forms[0].coll.checked?"[coll]: ":"") + urlEncode(search),0);
    }
    
    function scrollUp() {
      return loadXMLDoc("zkapi.php?method=getAlbumsRq&operation=prevPage&size=<?php echo $this->limit; ?>&key=" + urlEncode(document.forms[0].up.value),-1);
    }
    
    function scrollDown(selected) {
      return loadXMLDoc("zkapi.php?method=getAlbumsRq&operation=nextPage&size=<?php echo $this->limit; ?>&key=" + urlEncode(document.forms[0].down.value),selected);
    }
    
    function upDown(e) {
      select = document.forms[0].list;
      if(e.keyCode == 33 && select.selectedIndex == 0) {
         // page up
         scrollUp();
      } else if(e.keyCode == 34 && select.selectedIndex == select.length-1) {
         // page down
         scrollDown(1);
      } else if(e.keyCode == 38 && select.selectedIndex == 0) {
         // line up
         loadXMLDoc("zkapi.php?method=getAlbumsRq&operation=prevLine&size=<?php echo $this->limit; ?>&key=" + urlEncode(document.forms[0].up.value),-1);
      } else if(e.keyCode == 40 && select.selectedIndex == select.length-1) {
         // line down
         loadXMLDoc("zkapi.php?method=getAlbumsRq&operation=nextLine&size=<?php echo $this->limit; ?>&key=" + urlEncode(document.forms[0].up.value),1);
      }
      return true;
    }
    
    function processReqChange(req,selected) {
      if(req.readyState == 4) {
         // document loaded
         if (req.status == 200) {
            // success!
            list = document.forms[0].list;
            while(list.length > 0) list.remove(0);
            items = req.responseXML.getElementsByTagName("albumrec");
            for(var i=0; i<items.length; i++) {
              var opt = document.createElement("option");
              opt.value = getNodeValue(items[i].getElementsByTagName("tag"));
              opt.appendChild(document.createTextNode(getNodeValue(items[i].getElementsByTagName("artist"))));
              list.appendChild(opt);
              for(var j=0; j<fields.length; j++)
                 data[i][j] = getNodeValue(items[i].getElementsByTagName(fields[j]));
            }
            switch(selected) {
            case -1:
              list.selectedIndex = 0;
              break;
            case 1:
              list.selectedIndex = list.length-1;
              break;
            default:
              list.selectedIndex = list.length/2;
              break;
            }
            changeList();
            document.forms[0].up.value = getNodeValue(items[0].getElementsByTagName("artist")) + "|" + getNodeValue(items[0].getElementsByTagName("album")) + "|" + getNodeValue(items[0].getElementsByTagName("tag"));
            document.forms[0].down.value = getNodeValue(items[items.length-1].getElementsByTagName("artist")) + "|" + getNodeValue(items[items.length-1].getElementsByTagName("album")) + "|" + getNodeValue(items[items.length-1].getElementsByTagName("tag"));
         } else {
            alert("There was a problem retrieving the XML data:\n" + req.statusText);
         }
      }
    }
    
    // -->
    </SCRIPT>
    <?php 
    }
    
    private function albumForm() {
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
    <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript"><!--
    function setComp() {
    disabled = document.forms[0].coll.checked;
    document.forms[0].artist.style.visibility = disabled?'hidden':'visible';
    document.getElementById("lartist").style.visibility = disabled?'hidden':'visible';
    disabled?document.forms[0].album.focus():document.forms[0].artist.focus();
    }
    function setLocation() {
    storage = document.forms[0].location.value == 'G';
    document.forms[0].bin.style.visibility = storage?'visible':'hidden';
    document.getElementById("lbin").style.visibility = storage?'visible':'hidden';
    if(storage) document.forms[0].bin.focus();
    }
    // -->
    </SCRIPT>
    <?php 
        $this->emitZkAlpha(1);
        echo "  <INPUT TYPE=HIDDEN NAME=new VALUE=\"".$_REQUEST["new"]."\">\n";
        UI::setFocus($coll?"album":"artist");
    }
    
    private function emitLabelSel() {
        $this->emitCache(API::LABEL_FIELDS);
    
        echo "<TABLE CELLPADDING=5 CELLSPACING=5 WIDTH=\"100%\"><TR><TD VALIGN=TOP WIDTH=230>\n";
        echo "  <INPUT TYPE=HIDDEN NAME=selpubkey VALUE=\"\">\n";
        echo "<TABLE BORDER=0 CELLPADDING=4 CELLSPACING=0 WIDTH=\"100%\">";
        echo "<TR><TD COLSPAN=2 ALIGN=LEFT><B>Search:</B><BR><INPUT TYPE=TEXT CLASS=text STYLE=\"width:214px;\" NAME=search VALUE=\"$osearch\" autocomplete=off onkeyup=\"onSearch(document.forms[0],event);\" onkeypress=\"return event.keyCode != 13;\"></TD></TR>\n";
        echo "  <TR><TD COLSPAN=2 ALIGN=LEFT><INPUT NAME=\"bup\" VALUE=\"1\" TYPE=\"image\" SRC=\"img/zk_list_up_beta.gif\" onclick=\"return scrollUp();\"><BR><SELECT style=\"width:220px;\" class=\"select\" NAME=list SIZE=$this->limit onChange='changeList()' onKeyDown='return upDown(event);'>\n";
        for($i=0; $i<$this->limit; $i++)
            echo "  <OPTION VALUE=\"\">\n";
        echo "</SELECT><BR><INPUT NAME=\"bdown\" VALUE=\"1\" TYPE=\"image\" SRC=\"img/zk_list_dn_beta.gif\" onclick=\"return scrollDown(-1);\"></TD>\n";
        echo "</TR></TABLE>\n";
        echo "  <INPUT TYPE=HIDDEN NAME=up VALUE=\"\">\n";
        echo "  <INPUT TYPE=HIDDEN NAME=down VALUE=\"\">\n";
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
    <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript"><!--
    function setFocus() {
    <?php
         if($_REQUEST["seltag"]) {
              echo "   loadXMLDoc(\"zkapi.php?method=getLabelsRq&operation=searchByTag&size=$this->limit&key=".$_REQUEST["seltag"]."\");\n";
              echo "   document.forms[0].list.focus();\n";
         } else if($_REQUEST["name"]) {
              echo "   loadXMLDoc(\"zkapi.php?method=getLabelsRq&operation=searchByName&size=$this->limit&key=".UI::URLify($_REQUEST["name"])."\",0);\n";
              echo "   document.forms[0].list.focus();\n";
         } else {
              echo "   scrollUp();\n";
              echo "   document.forms[0].search.focus();\n";
         }
    ?> 
    }
    // -->
    </SCRIPT>
    <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript"><!--
    function onSearchNow() {
      loadXMLDoc("zkapi.php?method=getLabelsRq&operation=searchByName&size=<?php echo $this->limit; ?>&key=" + urlEncode(document.forms[0].search.value),0);
    }
    
    function scrollUp() {
      return loadXMLDoc("zkapi.php?method=getLabelsRq&operation=prevPage&size=<?php echo $this->limit; ?>&key=" + urlEncode(document.forms[0].up.value),-1);
    }
    
    function scrollDown(selected) {
      return loadXMLDoc("zkapi.php?method=getLabelsRq&operation=nextPage&size=<?php echo $this->limit; ?>&key=" + urlEncode(document.forms[0].down.value),selected);
    }
    
    function upDown(e) { 
      select = document.forms[0].list;
      if(e.keyCode == 33 && select.selectedIndex == 0) {
         // page up
         scrollUp();
      } else if(e.keyCode == 34 && select.selectedIndex == select.length-1) {
         // page down
         scrollDown(1);
      } else if(e.keyCode == 38 && select.selectedIndex == 0) {
         // line up
         loadXMLDoc("zkapi.php?method=getLabelsRq&operation=prevLine&size=<?php echo $this->limit; ?>&key=" + urlEncode(document.forms[0].up.value),-1);
      } else if(e.keyCode == 40 && select.selectedIndex == select.length-1) {
         // line down
         loadXMLDoc("zkapi.php?method=getLabelsRq&operation=nextLine&size=<?php echo $this->limit; ?>&key=" + urlEncode(document.forms[0].up.value),1);
      }
      return true;
    }
    
    function processReqChange(req,selected) {
      if(req.readyState == 4) {
         // document loaded
         if (req.status == 200) {
            // success!
            list = document.forms[0].list;
            while(list.length > 0) list.remove(0);
            items = req.responseXML.getElementsByTagName("labelrec");
            for(var i=0; i<items.length; i++) {
              var opt = document.createElement("option");
              opt.value = getNodeValue(items[i].getElementsByTagName("pubkey"));
              opt.appendChild(document.createTextNode(getNodeValue(items[i].getElementsByTagName("name"))));
              list.appendChild(opt);
              for(var j=0; j<fields.length; j++)
                 data[i][j] = getNodeValue(items[i].getElementsByTagName(fields[j]));
            }
            switch(selected) {
            case -1:
              list.selectedIndex = 0;
              break;
            case 1:
              list.selectedIndex = list.length-1;
              break;
            default:
              list.selectedIndex = list.length/2;
              break;
            }
            changeList();
            document.forms[0].up.value = getNodeValue(items[0].getElementsByTagName("name")) + "|" + getNodeValue(items[0].getElementsByTagName("pubkey"));
            document.forms[0].down.value = getNodeValue(items[items.length-1].getElementsByTagName("name")) + "|" + getNodeValue(items[items.length-1].getElementsByTagName("pubkey"));
         } else {
            alert("There was a problem retrieving the XML data:\n" + req.statusText);
         }
      }
    }
    // -->
    </SCRIPT>
    <?php 
    }
    
    private function labelForm() {
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
    <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript"><!--
    function setForeign() {
    foreign = document.forms[0].foreign.checked;
    document.getElementById("lstate").style.visibility = foreign?'hidden':'visible';
    document.getElementById("lzip").innerHTML = foreign?'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Country:':'Postal Code:';
    }
    // -->
    </SCRIPT>
    <?php 
        $this->emitZkAlpha();
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
        echo "<TR><TD></TD><TD".($_REQUEST["coll"]?" COLSPAN=3":"")." ALIGN=RIGHT>Insert/Delete&nbsp;Track:&nbsp;<INPUT TYPE=BUTTON NAME=insert CLASS=submit onClick='insertTrack();' VALUE='+'>&nbsp;<INPUT TYPE=BUTTON NAME=delete CLASS=submit onClick='deleteTrack();' VALUE='&minus;'></TD></TR>\n";
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
    <SCRIPT TYPE="text/javascript" LANGUAGE="JavaScript" SRC="js/zooscript.js"></SCRIPT>
    <SCRIPT TYPE="text/javascript" LANGUAGE="JavaScript"><!--
    var focus;
    function cf(f) { focus = f; }
    function nextTrack() {
        var form = document.forms[0];
        for(var i=1; typeof(eval('form.track'+i)) != 'undefined'; i++);
        return i;
    }
    function insertTrack() {
        var form = document.forms[0];
        var next = nextTrack();
        var track = createNamedElement('INPUT', 'track'+next);
        track.type = 'hidden';
        form.appendChild(track);
    <?php if($_REQUEST["coll"]){?>
        var artist = createNamedElement('INPUT', 'artist'+next);
        artist.type = 'hidden';
        form.appendChild(artist);
    <?php }?>
        for(var j=next; j>focus; j--) {
            eval('form.track' + j + '.value=form.track' + (j-1) + '.value;');
    <?php if($_REQUEST["coll"]){?>
            eval('form.artist' + j + '.value=form.artist' + (j-1) + '.value;');
    <?php }?>
        }
        eval('form.track' + focus + '.value="";');
    <?php if($_REQUEST["coll"]){?>
        eval('form.artist' + focus + '.value="";');
    <?php }?>
        eval('form.track' + focus + '.focus();');
    }
    function deleteTrack() {
        if(confirm('Delete track ' + focus + '?')) {
            var form = document.forms[0];
            var last = nextTrack()-1;
            for(var j=focus; j<last; j++) {
                eval('form.track' + j + '.value=form.track' + (j+1) + '.value;');
    <?php if($_REQUEST["coll"]){?>
                eval('form.artist' + j + '.value=form.artist' + (j+1) + '.value;');
    <?php }?>
            }
            eval('form.track' + last + '.value="";');
    <?php if($_REQUEST["coll"]){?>
            eval('form.artist' + last + '.value="";');
    <?php }?>
            eval('form.track' + focus + '.focus();');
        }
    }
    // -->
    </SCRIPT>
    <?php 
        $this->emitZkAlpha();
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
    
    private function printTag($tag) {
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
}