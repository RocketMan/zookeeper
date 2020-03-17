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

namespace ZK\Controllers;

use ZK\Engine\Engine;
use ZK\Engine\IChart;
use ZK\Engine\IPlaylist;
use ZK\Engine\IUser;

use ZK\UI\UICommon as UI;

class RunDaily implements IController {
    private $catCodes;
    
    public function processRequest($dispatcher) {
        header("Content-type: text/plain");

        echo "Starting ".date("Y-m-d H:i:s")."\n";
        
        $this->runCharts();
        $this->purgeDeletedPlaylists();
        $this->purgeOldSessions();

        echo "Done ".date("Y-m-d H:i:s")."\n";
    }
    
    private function runCharts() {
        echo "Running charts: ";
        
        $today = date("Y-m-d");
        $config = Engine::param('chart');
        if(array_key_exists('suspend_until', $config) &&
                strtotime($config['suspend_until']) > strtotime($today)) {
            echo "No (charting suspended until ".$config['suspend_until'].")\n";
            return;
        }

        $date = self::weeklyChartDate($today);
        if($today == $date) {
            // don't run charts on charting day, so as to give
            // a grace period for late DJs
            echo "No (charting day; come back tomorrow)\n";
            return;
        }

        // check whether this chart has already been generated
        $weeks = Engine::api(IChart::class)->getChartDates(6);
        while($weeks && $week = $weeks->fetch()) {
            if($week["week"] == $date) {
                // chart already exists; exit
                echo "No (already run)\n";
                return;
            }
        }

        // run the chart
        $ok = Engine::api(IChart::class)->doChart($date,
                                            $config['max_spins'],
                                            $config['apply_limit_per_dj']);
        echo $ok?"OK\n":"FAILED!\n";

        if($ok) {
            // send the e-mails
            $addresses = $this->getAddresses();
            if($addresses['weekly'])
                $this->chartWeekly($date, $addresses['weekly']);
            if($addresses['trades'])
                $this->chartWeekly($date, $addresses['trades'], 1);  // CMJ
            if($addresses['monthly'])
                $this->chartMonthly($date, $addresses['monthly']);
            if($addresses['crossroads'])
                $this->chartMonthly($date, $addresses['crossroads'], 1);
        }
    }

    private function purgeDeletedPlaylists() {
        $ok = Engine::api(IPlaylist::class)->purgeDeletedPlaylists();
        echo "Purging deleted playlists: ".($ok?"OK":"FAILED!")."\n";
    }

    private function purgeOldSessions() {
        $ok = Engine::session()->purgeOldSessions();
        echo "Purging old sessions: ".($ok?"OK":"FAILED!")."\n";
    }

    private static function weeklyChartDate($date) {
        $dt = \DateTime::createFromFormat("Y-m-d", $date);
        $w = $dt->format("w");    // day of week (0 (Sunday)..6 (Saturday))
        $dt->modify("-$w day");   // adjust to previous Sunday
        return $dt->format("Y-m-d");
    }

    private static function eMailify($arg, $size) {
        return substr(UI::deLatin1ify($arg), 0, $size);
    }

    /**
     * compose text into a rule
     *
     * @param text text to align
     * @param align text alignment -1=left, 0=centre, 1=right
     * @param l additional text for left side (optional)
     * @param r additional text for right side (optional)
     * @param size line length (default 80)
     *
     * Note the 'l' parameter is ignored for align=-1 and
     * the 'r' parameter is ignored for align=1, as the 'text'
     * parameter is left- or right-aligned in those cases.
     *
     * If you want to align both left and right with no centre, both
     * ('left', -1, '', 'right') and ('right', 1, 'left') are equivalent.
     */
    private static function rule($text, $align, $l="", $r="", $size=80) {
        switch($align) {
        case -1: // left
            $result = $text;
            break;
        case 0: // middle
            $result = $l;
            $result .= sprintf("%".floor($size/2+
                                  strlen($text)/2-
                                  strlen($result)-1)."s", $text);
            break;            
        case 1: // right
            $result = $l;
            $r = $text;
            break;
        }
        
        if($r)
            $result .= sprintf("%".($size-strlen($result)-1)."s", $r);
            
        return $result."\n";
    }

    private function getAddresses() {
        $addresses = array();
        $results = Engine::api(IChart::class)->getChartEMail();
        while($results && ($row = $results->fetch()))
            $addresses[strtolower($row["chart"])] = $row["address"];
        return $addresses;
    }

    private function buildChart($start, $end, $limit="", $category="", $cmj=0, $crd=0) {
        $result = "";
        $chart = [];
        $chartApi = Engine::api(IChart::class);
        $chartApi->getChart($chart, $start, $end, $limit, $category);
        if(sizeof($chart)) {
            if($category) {
                if($crd)
                    $result .= sprintf("\n\n%s\n%s\n",
                                strtoupper($this->catCodes[$category-1]["name"]),
                                $this->catCodes[$category-1]["director"]);
                else
                    $result .= sprintf("\n\n%-22s %56s\n",
                                strtoupper($this->catCodes[$category-1]["name"]),
                                $this->catCodes[$category-1]["director"]);
            }

            if($cmj)
                $line = sprintf("%4s %-23s %-23s %20s %5s\n",
                                        "Rank", "Artist", "Album", "Label", "Plays");
            else if(!$crd)
                $line = sprintf("%4s %-23s %-26s %23s\n",
                                        "Rank", "Artist", "Album", "Label");
            else
                $line = "";

            if($line)
                $result .= $line;
                
            for($i=0; $i < sizeof($chart); $i++) {
                // Fixup the artist, album, and label names
                $artist = $chart[$i]["artist"];
                $label = str_replace(" Records", "", $chart[$i]["label"]);
                $label = str_replace(" Recordings", "", $label);
    
                // Setup medium
                $album = $chart[$i]["album"];
                switch($chart[$i]["medium"]) {
                case "S":
                    $medium = " 7\"";
                    break;
                case "T":
                    $medium = " 10\"";
                    break;
                case "V":
                    $medium = " 12\"";
                    break;
                default:
                    $medium = "";
                    break;
                }
    
                if($cmj)
                    $line = sprintf("%3d  %-23s %-23s %20s %4d\n",
                                             $i+1,
                                             self::eMailify($artist, 23),
                                             self::eMailify($album . $medium, 23),
                                             self::eMailify($label, 20),
                                             $chart[$i]["PLAYS"]);
                else if($crd) {
                    if($artist == "COLL")
                        $line = sprintf("%d. %s - %s\n",
                                                 $i+1,
                                                 $album . $medium,
                                                 $label);
                    else
                        $line = sprintf("%d. %s - %s - %s\n",
                                                 $i+1,
                                                 $artist,
                                                 $album . $medium,
                                                 $label);
                } else
                    $line = sprintf("%3d  %-23s %-26s %23s\n",
                                             $i+1,
                                             self::eMailify($artist, 23),
                                             self::eMailify($album . $medium, 26),
                                             self::eMailify($label, 23));
    
                $result .= $line;
            }
            return $result;
        }
    }

    private function sendChartGenreEMail($start, $date, $month) {
        list($y,$m,$d) = explode("-", $date);
    
        foreach($this->catCodes as $index => $genre) {
            // skip undefined categories
            if(!$genre["name"] || !$genre["code"])
                continue;

            $address = $genre["email"];

            // skip genre e-mail if no valid address
            if(!strpos($address, '@')) {
                echo "Skipping ".$genre["name"]." monthly e-mail due to invalid or missing address: $address\n";
                continue;
            }
    
            // Build the chart
            $chart = $this->buildChart($start, $date, 0, $genre["id"], 1);
    
            // Setup the headers
            $subject = Engine::param('station').": ".
                         $genre["name"] . " monthly totals, " .
                         date("m/Y", mktime(0,0,0,$month,$d,$y));
                           
            $headers = "From: ".Engine::param('station')." ".
                           Engine::param('application')." <".
                           Engine::param('email')['chartman'].">\r\n";

            // send the mail
            $stat = mail($address, $subject, $chart, $headers);
            echo "Sending ".$genre["name"]." monthly e-mail: ".
                 ($stat?"OK":"FAILED!")."\n";
        }
    }
    
    private function chartWeekly($date, $address, $cmj=0) {
        list($y,$m,$d) = explode("-", $date);
    
        if($address) {
            // Allow only alphanumeric and {@,-,.} in address
            for($i=0; $i<strlen($address); $i++) {
                $c = strtolower(substr($address, $i, 1));
                if(!($c == "@" || $c == "-" || $c == "." ||
                     $c == "," || $c == " " ||
                     ($c >= "0" && $c <= "9") ||
                     ($c >= "a" && $c <= "z")))
                    break;
            }
    
            if($i != strlen($address)) {
                echo "Skipping ".($cmj?"cmj":"weekly")." e-mail due to invalid address: $address\n";
            } else {
                // get the chart categories
                $this->catCodes = Engine::api(IChart::class)->getCategories();
    
                // Build the charts
                $charts = $this->buildChart("", $date, 100, "", $cmj);
                $charts .= $this->buildChart("", $date, 20, 5, $cmj); // hip-hop
                $charts .= $this->buildChart("", $date, 20, 7, $cmj); // reggae/world
                $charts .= $this->buildChart("", $date, 20, 6, $cmj); // jazz
                if(!$cmj) {
                    $charts .= $this->buildChart("", $date, 20, 1, $cmj); // blues
                    $charts .= $this->buildChart("", $date, 20, 2, $cmj); // country
                    $charts .= $this->buildChart("", $date, 20, 8, $cmj); // c/x
                }
                $charts .= $this->buildChart("", $date, 20, 4, $cmj); // heavy shit
                $charts .= $this->buildChart("", $date, 20, 3, $cmj); // dance
    
                // Compose the message body
                $contact = Engine::param('contact');
                $fancyDate = date("j F Y", mktime(0,0,0,$m,$d,$y));
                $body = self::rule("Chart for the Week ending $fancyDate",
                                   0,
                                   Engine::param('station_medium'));
                $body .= self::rule("Music Director: ".Engine::param('md_name'),
                                   0)."\n";
                $body .= self::rule($contact['addr'], -1, "",
                                   "Vox: ".$contact['phone']);
                $body .= self::rule($contact['city'], -1, "",
                                   "Fax: ".$contact['fax']);
                $body .= self::rule(Engine::param('email')['md'], -1, "",
                                   Engine::param('urls')['home'])."\n\n";

                $body .= $charts;

                if(!$cmj)
                    $body .= Engine::param('chart')['weekly_footer'];
    
                // Setup the headers
                $subject = Engine::param('station').": ".
                           date("Y-m-d", mktime(0,0,0,$m,$d,$y)) . " chart";
                $headers = "From: ".Engine::param('station')." ".
                                    Engine::param('application')." <".
                                    Engine::param('email')['chartman'].">\r\n";
    
                // send the mail
                $stat = mail($address, $subject, $body, $headers);
                echo "Sending ".($cmj?"cmj":"weekly").
                     " e-mail: ".($stat?"OK":"FAILED!")."\n";
            }
        } else
            echo "Skipping ".($cmj?"cmj":"weekly")." e-mail: no address configured\n";
    }

    private function chartMonthly($date, $address, $crd=0) {
        list($y,$m,$d) = explode("-", $date);

        $chartAPI = Engine::api(IChart::class);
        $chartEnd1 = $chartAPI->getMonthlyChartEnd($m, $y);
        $chartEnd2 = $chartAPI->getMonthlyChartEnd((int)$m - 1, $y);
    
        if($date == $chartEnd1 || $date == $chartEnd2) {
            // This week is the end of a monthly chart; send it!
            $month = ($date == $chartEnd1)?$m:(int)$m - 1;
            $start = $chartAPI->getMonthlyChartStart($month, $y);
        } else
            return;
    
        if($address) {
            // Allow only alphanumeric and {@,-,.} in address
            for($i=0; $i<strlen($address); $i++) {
                $c = strtolower(substr($address, $i, 1));
                if(!($c == "@" || $c == "-" || $c == "." ||
                     $c == "," || $c == " " ||
                     ($c >= "0" && $c <= "9") ||
                     ($c >= "a" && $c <= "z")))
                    break;
            }
    
            if($i != strlen($address)) {
                echo "Skipping ".($crd?"crossroads":"monthly")." e-mail due to invalid address: $address\n";
            } else {
                // get the chart categories
                $this->catCodes = Engine::api(IChart::class)->getCategories();
    
                // Build the charts
                $charts = "";
                if(!$crd) {
                    $charts .= $this->buildChart($start, $date, 100, "");
                    $charts .= $this->buildChart($start, $date, 20, 5); // hip-hop
                    $charts .= $this->buildChart($start, $date, 20, 7); // reggae/world
                    $charts .= $this->buildChart($start, $date, 20, 6); // jazz
                }
                $charts .= $this->buildChart($start, $date, $crd?100:20, 1, 0, $crd); // blues
                $charts .= $this->buildChart($start, $date, $crd?100:20, 2, 0, $crd); // country
                if(!$crd) {
                    $charts .= $this->buildChart($start, $date, 20, 4); // heavy shit
                    $charts .= $this->buildChart($start, $date, 20, 3); // dance
                    $charts .= $this->buildChart($start, $date, 20, 8); // C/X
                }
                
                // Compose the message body
                $contact = Engine::param('contact');
                if($crd) {
                    $body = Engine::param('station_medium')."\n";
                    $body .= $contact['city']."\n";
                    $body .= Engine::param('md_name').", Music Director\n";
                    $body .= "P: ".$contact['phone']."\n";
                    $body .= "F: ".$contact['fax']."\n";
                    $body .= "E: ".Engine::param('email')['md']."\n\n";
                }
                $fancyDate = date("F Y", mktime(0,0,0,$month,$d,$y));
                $body = self::rule("Chart for the Month of $fancyDate",
                                   0,
                                   Engine::param('station_medium'));
                $body .= self::rule("Music Director: ".Engine::param('md_name'),
                                   0)."\n";
                $body .= self::rule($contact['addr'], -1, "",
                                   "Vox: ".$contact['phone']);
                $body .= self::rule($contact['city'], -1, "",
                                   "Fax: ".$contact['fax']);
                $body .= self::rule(Engine::param('email')['md'], -1, "",
                                   Engine::param('urls')['home'])."\n\n";
                
                $body .= $charts;

                if(!$crd)
                    $body .= Engine::param('chart')['monthly_footer'];
    
                // Setup the headers
                $subject = Engine::param('station').": ".
                           date("Y-m", mktime(0,0,0,$month,$d,$y)) . " chart";
                $headers = "From: ".Engine::param('station')." ".
                                    Engine::param('application')." <".
                                    Engine::param('email')['chartman'].">\r\n";
    
                // send the mail
                $stat = mail($address, $subject, $body, $headers);
                echo "Sending ".($crd?"crossroads":"monthly").
                     " e-mail: ".($stat?"OK":"FAILED!")."\n";
            }
        } else
            echo "Skipping ".($crd?"crossroads":"monthly")." e-mail: no address configured\n";

        if(!$crd)
            $this->sendChartGenreEMail($start, $date, $month);
    }
}
