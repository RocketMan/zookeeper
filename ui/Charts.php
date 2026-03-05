<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2026 Jim Mason <jmason@ibinx.com>
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
use ZK\Engine\IChart;
use ZK\Engine\ILibrary;
use ZK\Engine\PlaylistEntry;

use ZK\UI\UICommon as UI;

class Charts extends MenuItem {
    const DECENNIUM_CHART = false;

    const TOP_MAIN = 30;
    const TOP_GENRE = 10;

    private static $subactions = [
        [ "a", "", "Top 30", "chartTop30" ],
        [ "a", "weekly", "Weekly", "chartWeekly" ],
        [ "a", "amalgamated", "Amalgamated", "chartMonthly" ],
        [ "a", "subscribe", "Subscribe", "emitSubscribe" ],
        [ "n", "chartemail", "E-Mail", "chartEMail" ],
    ];

    public function getSubactions($action) { return self::$subactions; }

    public function processLocal($action, $subaction) {
        $extra = "<SPAN CLASS='sub'><B>Chart Feed:</B></SPAN> <A TYPE='application/rss+xml' HREF='zkrss.php?feed=charts'><IMG SRC='img/rss.png' ALT='rss'></A><BR><IMG SRC='img/blank.gif' WIDTH=1 HEIGHT=2 BORDER=0 ALT=''>";
        return $this->dispatchSubaction($action, $subaction, $extra);
    }

    private function mergeLast(&$current, $last) {
        $last = array_column($last, 'tag');
        array_unshift($last, 0);
        $lastMap = array_flip($last);

        foreach($current as $index => &$row) {
            $row['lw'] = $lastMap[$row['tag']] ?? null;
            if ($row['lw'])
                $row['change'] = $row['lw'] <=> ++$index;
        }
    }

    public function chartTop30() {
        $this->addVar('top', [
            'main' => self::TOP_MAIN,
            'genre' => self::TOP_GENRE
        ]);

        $chartAPI = Engine::api(IChart::class);
        $cats = $chartAPI->getCategories();
        $this->addVar('categories', $cats);

        $dateSpec = UI::getClientLocale() == 'en_US' ? 'l, F j, Y' : 'l, j F Y';
        $this->addVar('dateSpec', $dateSpec);

        $weeks = $chartAPI->getChartDates(2)->asArray();
        $thisWeek = $weeks[0]['week'];
        $lastWeek = $weeks[1]['week'];

        // top 30
        $chart = [];
        $chartAPI->getChart($chart, '', $thisWeek, self::TOP_MAIN, '');

        $last = [];
        $chartAPI->getChart($last, '', $lastWeek, self::TOP_MAIN, '');
        $this->mergeLast($chart, $last);

        $charts = [];
        $charts[$thisWeek][0] = $chart;
        $charts[$lastWeek][0] = $last;

        // genre charts
        $genres = [
            5, // hip-hop
            7, // reggae/world
            9, // reggae
            6, // jazz
            1, // blues
            2, // country
            4, // heavy shit
            3, // dance
            8  // C/X
        ];
        if (!$this->session->isAuth("r"))
            unset($genres[2]); // 2 is ordinal of reggae chart

        foreach ($genres as $genre) {
            $chart = [];
            $chartAPI->getChart($chart, '', $thisWeek, self::TOP_GENRE, $genre);

            $last = [];
            $chartAPI->getChart($last, '', $lastWeek, self::TOP_GENRE, $genre);
            $this->mergeLast($chart, $last);

            $charts[$thisWeek][$genre] = $chart;
            $charts[$lastWeek][$genre] = $last;
        }

        $this->addVar('allcharts', $charts);
        $this->addVar('entry', new PlaylistEntry());
        $this->setTemplate('charts/top30.html');
        $this->title = "Airplay Top 30";
    }
    
    public function chartWeekly() {
        $station = Engine::param('station');
    
        $chartAPI = Engine::api(IChart::class);
        $year = (int)($_REQUEST["year"] ?? 0);
        $month = (int)($_REQUEST["month"] ?? 0);
        $day = (int)($_REQUEST["day"] ?? 0);

        $dateSpec = UI::getClientLocale() == 'en_US' ? 'F j, Y' : 'j F Y';
        $this->addVar('dateSpec', $dateSpec);

        if(!$month) {
            if(!$year) {
                // current year
                $today = getdate(time());
                $years = $chartAPI->getChartYears();
                if($years) {
                    $yearrec = $years->fetch();
                    $year = $yearrec[0];
                } else
                    $year = $today["year"];
                $month = $today["mon"];
            }
    
            $this->addVar('currentYear', $year);
            $years = $chartAPI->getChartYears();
            $this->addVar('years', array_column($years->asArray(), 'year'));

            $weeks = $chartAPI->getChartDatesByYear($year);
            $this->addVar('weeks', array_column($weeks->asArray(), 'week'));

            $this->title = "Weekly charts for $year";
            $this->setTemplate('charts/weekly.html');
            return;
        }

        if(!checkdate($month, $day, $year)) {
            echo "<B>The requested date is invalid.</B>";
            return;
        }

        $endDate = "$year-$month-$day";
        $this->addVar('endDate', $endDate);

        $displayDate = date($dateSpec, mktime(0,0,0,$month,$day,$year));
        $this->title = "Chart for $displayDate";
    
        $cats = $chartAPI->getCategories();
        $this->addVar('categories', $cats);

    // weekly = hip hop, reggae/world, jazz, heavy shit, dance, classical/exp
    //     no limits
    // monthly = hip hop, reggae/world, jazz, blues, country, heavy shit, dance
    //     limit to 60 main, 10/cat
    
        $mainLimit = $catLimit = "";
    
        $chart = [];
        $chartAPI->getChart($chart, '', $endDate, $mainLimit, '');

        $charts = [];
        $charts[0] = $chart;

        // genre charts
        $genres = [
            5, // hip-hop
            7, // reggae/world
            9, // reggae
            6, // jazz
            1, // blues
            2, // country
            4, // heavy shit
            3, // dance
            8  // C/X
        ];
        if (!$this->session->isAuth("r"))
            unset($genres[2]); // 2 is ordinal of reggae chart

        foreach ($genres as $genre) {
            $chart = [];
            $chartAPI->getChart($chart, '', $endDate, $catLimit, $genre);
            $charts[$genre] = $chart;
        }

        $this->addVar('charts', $charts);
        $this->addVar('entry', new PlaylistEntry());
        $this->setTemplate('charts/weekly.html');
    }
    
    public function chartMonthly() {
        $station = Engine::param('station');
    
        $chartAPI = Engine::api(IChart::class);
        $year = (int)($_REQUEST["year"] ?? 0);
        $month = (int)($_REQUEST["month"] ?? 0);
        $day = (int)($_REQUEST["day"] ?? 0);
        $cyear = (int)($_REQUEST["cyear"] ?? 0);
        $dnum = (int)($_REQUEST["dnum"] ?? 0);

        $config = Engine::param('chart');
        $earliestYear = array_key_exists('earliest_chart_year', $config)?
            (int)$config['earliest_chart_year']:2003;

        $this->addVar('decenniumCharts', self::DECENNIUM_CHART);

        $monthly = 1;

        if(!$dnum && !$cyear && !$month) {
            if(!$year) {
                // current year
                $today = getdate(time());
                $month = $today["mon"];
    
                // Determine if we need to include the current month
                $weeks = $chartAPI->getChartDates(1);
                if($weeks && ($curWeek = $weeks->fetch())) {
                    list($y, $m, $d) = explode("-", $curWeek["week"]);
                    $chartEnd = $chartAPI->getMonthlyChartEnd($m, $y);
                    $skipCurMonth = strcmp($curWeek["week"], $chartEnd) != 0;
                }

                $weeks = $chartAPI->getChartMonths()->asArray();
                if ($skipCurMonth)
                    array_shift($weeks);

                $this->addVar('weeks', array_column($weeks, 'week'));
                $this->addVar('dateSpec', 'F Y');
            }

            $this->setTemplate('charts/amalga.html');
            $this->title = "Amalgamated charts";
            return;
        }

        if(self::DECENNIUM_CHART && $dnum) {
            $dstart = $dnum * 10;
            $dend = $dstart + 9;
            if(!checkdate(1, 1, $dstart) || !checkdate(1, 1, $dend)) {
                echo "<B>The requested date is invalid.</B>";
                return;
            }

            $startDate = $chartAPI->getMonthlyChartStart(1, $dstart);
            $endDate = $chartAPI->getMonthlyChartEnd(12, $dend);
            $name = "$dstart - $dend";
            if($dend - $earliestYear < 10)
                $name .= " (based on available data)";
            $this->addVar('title', "Top 100 for the decennium $name");
            $this->title = "Chart for $name";
            $monthly = 0;
        } else if($cyear) {
            if(!checkdate(1, 1, $cyear)) {
                echo "<B>The requested date is invalid.</B>";
                return;
            }

            $startDate = $chartAPI->getMonthlyChartStart(1, $cyear);
            $endDate = $chartAPI->getMonthlyChartEnd(12, $cyear);
            $this->addVar('title', "Top 100 for the year $cyear");
            $this->title = "Chart for $cyear";
            $monthly = 0;
        } else {
            if(!checkdate($month, 1, $year)) {
                echo "<B>The requested date is invalid.</B>";
                return;
            }

            $startDate = $chartAPI->getMonthlyChartStart($month, $year);
            $endDate = $chartAPI->getMonthlyChartEnd($month, $year);
            $displayDate = date("F Y", mktime(0,0,0,$month,1,$year));
            $this->addVar('title', "chart for $displayDate");
            $this->title = "Chart for $displayDate";
        }
    
        $cats = $chartAPI->getCategories();
        $this->addVar('categories', $cats);

    // weekly = hip hop, reggae/world, jazz, heavy shit, dance, classical/exp
    //     no limits
    // monthly = hip hop, reggae/world, jazz, blues, country, heavy shit, dance
    //     limit to 60 main, 10/cat
    
        if($dnum || $cyear) {
            $mainLimit = "100";
            $catLimit = "30";
        } else {
            $mainLimit = $monthly?($this->session->isAuth("f")?"100":"60"):"";
            $catLimit = $monthly?($this->session->isAuth("f")?"40":"10"):"";
        }

        $chart = [];
        $chartAPI->getChart($chart, $startDate, $endDate, $mainLimit, '');

        $charts = [];
        $charts[0] = $chart;

        // genre charts
        $genres = [
            5, // hip-hop
            7, // reggae/world
            9, // reggae
            6, // jazz
            1, // blues
            2, // country
            4, // heavy shit
            3, // dance
            8  // C/X
        ];

        if ($monthly)
            unset($genres[8]); // 8 is ordinal of C/X chart

        if (!$this->session->isAuth("r"))
            unset($genres[2]); // 2 is ordinal of reggae chart

        foreach ($genres as $genre) {
            $chart = [];
            $chartAPI->getChart($chart, $startDate, $endDate, $catLimit, $genre);
            $charts[$genre] = $chart;
        }

        $this->addVar('charts', $charts);
        $this->addVar('entry', new PlaylistEntry());
        $this->setTemplate('charts/amalga.html');
    }

    public function chartEMail() {
        $chartAPI = Engine::api(IChart::class);
        if(($_REQUEST["seq"] ?? '') == "update") {
            $success = true;
            for($i=1; $success && $i<=16; $i++) {
                if(isset($_POST["email".$i])) {
                    $email = $_POST["email".$i];
                    $success &= $chartAPI->updateChartEMail($i, $email);
                }
            }
        }

        $addresses = $chartAPI->getChartEMail()->asArray();
        $this->addVar('addresses', $addresses);

        if(($_REQUEST["seq"] ?? '') == "update")
            $this->addVar($success ? 'success' : 'failure', true);

        $this->setTemplate('charts/email.html');
    }
    
    public function emitSubscribe() {
        $chart = Engine::param('chart');
        $weeklyPage = array_key_exists('weekly_subscribe', $chart)?
            $chart['weekly_subscribe']:false;
        $monthlyPage = array_key_exists('monthly_subscribe', $chart)?
            $chart['monthly_subscribe']:false;
        $this->setTemplate("charts/subscribe.html");
        $this->addVar("weeklyPage", $weeklyPage);
        $this->addVar("monthlyPage", $monthlyPage);
    }
}
