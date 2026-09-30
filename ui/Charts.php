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

use ZK\Engine\IChart;
use ZK\Engine\IConfig;
use ZK\Engine\ILibrary;
use ZK\Engine\PlaylistEntry;
use ZK\Engine\Request;
use ZK\Engine\Session;

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

    public function __construct(
        protected Request $request,
        protected Session $session,
        protected TemplateFactoryUI $templateFactory,
        protected Home $home,
        protected IConfig $config,
        protected IChart $chartDBO,
    ) {
        parent::__construct($session, $templateFactory);
    }

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

        $cats = $this->chartDBO->getCategories();
        $this->addVar('categories', $cats);

        $dateSpec = $this->request->isUsLocale() ? 'l, F j, Y' : 'l, j F Y';
        $this->addVar('dateSpec', $dateSpec);

        $weeks = $this->chartDBO->getChartDates(2)->asArray();
        if (count($weeks) < 1) {
            $this->setTemplate('charts/nocharts.html');
            return;
        }

        $thisWeek = $weeks[0]['week'];
        $lastWeek = count($weeks) > 1 ? $weeks[1]['week'] : null;

        // top 30
        $chart = [];
        $this->chartDBO->getChart($chart, '', $thisWeek, self::TOP_MAIN, '');

        $charts = [];

        if ($lastWeek) {
            $last = [];
            $this->chartDBO->getChart($last, '', $lastWeek, self::TOP_MAIN, '');
            $this->mergeLast($chart, $last);
            $charts[$thisWeek] = [];
            $charts[$lastWeek][0] = $last;
        }

        $charts[$thisWeek][0] = $chart;

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
            $this->chartDBO->getChart($chart, '', $thisWeek, self::TOP_GENRE, $genre);

            if ($lastWeek) {
                $last = [];
                $this->chartDBO->getChart($last, '', $lastWeek, self::TOP_GENRE, $genre);
                $this->mergeLast($chart, $last);
                $charts[$lastWeek][$genre] = $last;
            }

            $charts[$thisWeek][$genre] = $chart;
        }

        $this->addVar('allcharts', $charts);
        $this->addVar('entry', new PlaylistEntry());
        $this->setTemplate('charts/top30.html');
        $this->title = "Airplay Top 30";
    }
    
    public function chartWeekly() {
        $station = $this->config->get('station');
    
        $year = (int)($_REQUEST["year"] ?? 0);
        $month = (int)($_REQUEST["month"] ?? 0);
        $day = (int)($_REQUEST["day"] ?? 0);

        $check = $this->chartDBO->getChartDates(1)->asArray();
        if (count($check) != 1) {
            $this->setTemplate('charts/nocharts.html');
            return;
        }

        $dateSpec = $this->request->isUsLocale() ? 'F j, Y' : 'j F Y';
        $this->addVar('dateSpec', $dateSpec);

        if(!$month) {
            if(!$year) {
                // current year
                $today = getdate(time());
                $years = $this->chartDBO->getChartYears();
                if($years) {
                    $yearrec = $years->fetch();
                    $year = $yearrec[0];
                } else
                    $year = $today["year"];
                $month = $today["mon"];
            }
    
            $this->addVar('currentYear', $year);
            $years = $this->chartDBO->getChartYears();
            $this->addVar('years', array_column($years->asArray(), 'year'));

            $weeks = $this->chartDBO->getChartDatesByYear($year);
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
    
        $cats = $this->chartDBO->getCategories();
        $this->addVar('categories', $cats);

    // weekly = hip hop, reggae/world, jazz, heavy shit, dance, classical/exp
    //     no limits
    // monthly = hip hop, reggae/world, jazz, blues, country, heavy shit, dance
    //     limit to 60 main, 10/cat
    
        $mainLimit = $catLimit = "";
    
        $chart = [];
        $this->chartDBO->getChart($chart, '', $endDate, $mainLimit, '');

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
            $this->chartDBO->getChart($chart, '', $endDate, $catLimit, $genre);
            $charts[$genre] = $chart;
        }

        $this->addVar('charts', $charts);
        $this->addVar('entry', new PlaylistEntry());
        $this->setTemplate('charts/weekly.html');
    }
    
    public function chartMonthly() {
        $station = $this->config->get('station');
    
        $year = (int)($_REQUEST["year"] ?? 0);
        $month = (int)($_REQUEST["month"] ?? 0);
        $day = (int)($_REQUEST["day"] ?? 0);
        $cyear = (int)($_REQUEST["cyear"] ?? 0);
        $dnum = (int)($_REQUEST["dnum"] ?? 0);

        $config = $this->config->get('chart');
        $earliestYear = array_key_exists('earliest_chart_year', $config)?
            (int)$config['earliest_chart_year']:2003;

        $check = $this->chartDBO->getChartDates(1)->asArray();
        if (count($check) != 1) {
            $this->setTemplate('charts/nocharts.html');
            return;
        }

        $this->addVar('decenniumCharts', self::DECENNIUM_CHART);

        $monthly = 1;

        if(!$dnum && !$cyear && !$month) {
            if(!$year) {
                // current year
                $today = getdate(time());
                $month = $today["mon"];
    
                // Determine if we need to include the current month
                $weeks = $this->chartDBO->getChartDates(1);
                if($weeks && ($curWeek = $weeks->fetch())) {
                    list($y, $m, $d) = explode("-", $curWeek["week"]);
                    $chartEnd = $this->chartDBO->getMonthlyChartEnd($m, $y);
                    $skipCurMonth = strcmp($curWeek["week"], $chartEnd) != 0;
                }

                $weeks = $this->chartDBO->getChartMonths()->asArray();
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

            $startDate = $this->chartDBO->getMonthlyChartStart(1, $dstart);
            $endDate = $this->chartDBO->getMonthlyChartEnd(12, $dend);
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

            $startDate = $this->chartDBO->getMonthlyChartStart(1, $cyear);
            $endDate = $this->chartDBO->getMonthlyChartEnd(12, $cyear);
            $this->addVar('title', "Top 100 for the year $cyear");
            $this->title = "Chart for $cyear";
            $monthly = 0;
        } else {
            if(!checkdate($month, 1, $year)) {
                echo "<B>The requested date is invalid.</B>";
                return;
            }

            $startDate = $this->chartDBO->getMonthlyChartStart($month, $year);
            $endDate = $this->chartDBO->getMonthlyChartEnd($month, $year);
            $displayDate = date("F Y", mktime(0,0,0,$month,1,$year));
            $this->addVar('title', "chart for $displayDate");
            $this->title = "Chart for $displayDate";
        }
    
        $cats = $this->chartDBO->getCategories();
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
        $this->chartDBO->getChart($chart, $startDate, $endDate, $mainLimit, '');

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
            $this->chartDBO->getChart($chart, $startDate, $endDate, $catLimit, $genre);
            $charts[$genre] = $chart;
        }

        $this->addVar('charts', $charts);
        $this->addVar('entry', new PlaylistEntry());
        $this->setTemplate('charts/amalga.html');
    }

    public function chartEMail() {
        if(($_REQUEST["seq"] ?? '') == "update") {
            $success = true;
            for($i=1; $success && $i<=16; $i++) {
                if(isset($_POST["email".$i])) {
                    $email = $_POST["email".$i];
                    $success &= $this->chartDBO->updateChartEMail($i, $email);
                }
            }
        }

        $addresses = $this->chartDBO->getChartEMail()->asArray();
        $this->addVar('addresses', $addresses);

        if(($_REQUEST["seq"] ?? '') == "update")
            $this->addVar($success ? 'success' : 'failure', true);

        $this->setTemplate('charts/email.html');
    }
    
    public function emitSubscribe() {
        $chart = $this->config->get('chart');
        $weeklyPage = array_key_exists('weekly_subscribe', $chart)?
            $chart['weekly_subscribe']:false;
        $monthlyPage = array_key_exists('monthly_subscribe', $chart)?
            $chart['monthly_subscribe']:false;
        $this->setTemplate("charts/subscribe.html");
        $this->addVar("weeklyPage", $weeklyPage);
        $this->addVar("monthlyPage", $monthlyPage);
    }
}
