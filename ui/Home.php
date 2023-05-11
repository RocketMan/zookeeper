<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2022 Jim Mason <jmason@ibinx.com>
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
use ZK\Engine\IPlaylist;
use ZK\Engine\PlaylistEntry;

use ZK\UI\UICommon as UI;

class Home extends MenuItem {
    private static $subactions = [
        [ "", "emitHome" ],
        [ "recent", "recentSpins" ],
        [ "times", "getTimes" ],
    ];

    public function processLocal($action, $subaction) {
        return $this->dispatchAction($subaction, self::$subactions);
    }

    public function recentSpins() {
        $plays = Engine::api(IPlaylist::class)->getPlaysBefore($_REQUEST["before"] ?? null, $_REQUEST["count"] ?? 10);
        echo json_encode($plays);
    }

    protected function makeDatePicker() {
        $now = new \DateTime();
        $result = "<option value='" . $now->format("Y-m-d") . "'>Today</option>";
        $now->modify("-1 days");
        $result .= "<option value='" . $now->format("Y-m-d") . "'>Yesterday</option>";
        for($i=0; $i<5; $i++) {
            $now->modify("-1 days");
            $result .= "<option value='" . $now->format("Y-m-d") . "'>" .
                $now->format("D M j") . "</option>";
        }

        return $result;
    }

    protected function makeTimePicker($date=null) {
        $now = new \DateTime();
        if(!$date || $now->format("Y-m-d") == $date) {
            // today
            $result = "<option value='now'>Recently Played</option>";
            $hour = (int)$now->format("H");
        } else {
            $result = "<option value='23:59:59'>Before midnight</option>";
            $hour = 23;
        }

        do {
            if($hour % 3) continue;
            $h = sprintf("%02d", $hour);
            $ampm = $h >= 12?"pm":"am";
            $hx = $h > 12?$hour-12:$hour;
            $dh = $h == 12?"noon":($hx.$ampm);
            $result .= "<option value='$h:00:00'>Before $dh</option>";
        } while(--$hour > 0);

        return $result;
    }

    public function getTimes() {
        $retVal = [];
        $retVal['times'] = $this->makeTimePicker($_REQUEST["date"] ?? null);
        echo json_encode($retVal);
    }

    public function emitHome() {
        $this->setTemplate("onnow.html");
        $this->emitWhatsOnNow();
        if(($config = Engine::param('discogs')) &&
                ($config['apikey'] || $config['client_id']) &&
                Engine::param('push_enabled', true))
            $this->emitRecentlyPlayed();
        else
            $this->emitTopPlays();
    }

    private function emitRecentlyPlayed() {
        $this->addVar('discogs', true);
        $this->addVar('datepicker', $this->makeDatePicker());
        $this->addVar('timepicker', $this->makeTimePicker());
    }

    private function emitTopPlays($numweeks=1, $limit=10) {
       // Determine last chart date
       $weeks = Engine::api(IChart::class)->getChartDates(1);
       if($weeks && ($lastWeek = $weeks->fetch()))
          list($y,$m,$d) = explode("-", $lastWeek["week"]);
    
       if(! isset($y) || !$y)
          return;    // No charts!  bail.

       $topPlays = [];
       if(!$numweeks || $numweeks == 1)
          Engine::api(IChart::class)->getChart($topPlays, "", $lastWeek["week"], $limit);
       else {
          // Determine start chart date that will yield $numweeks worth of charts
          $startDate = date("Y-m-d", mktime(0,0,0,
                                          $m,
                                          $d-(($numweeks-1)*7),
                                          $y));
    
          Engine::api(IChart::class)->getChart($topPlays, $startDate, "", $limit);
       }
    
       Engine::api(ILibrary::class)->markAlbumsReviewed($topPlays);
       if(sizeof($topPlays)) {
          $formatEndDate = date("l, j F Y", mktime(0,0,0,$m,$d,$y));
          for($i=0; $i < sizeof($topPlays); $i++) {
             $topPlays[$i]['index'] = $i + 1;

             // Setup artist correctly for collections & swap names if from library
             $artist = $topPlays[$i]['artist'];
             if (preg_match("/^COLL$/i", $artist))
                 $artist = "Various Artists";
             else if ($topPlays[$i]['tag'])
                 $artist = PlaylistEntry::swapNames($artist);
             $topPlays[$i]['artist'] = $artist;
          }

          $this->addVar('topplays', $topPlays);
          $this->addVar('numweeks', $numweeks);
          $this->addVar('limit', $limit);
          $this->addVar('enddate', $formatEndDate);
       }
    }
    
    private function emitWhatsOnNow() {
        $tz = date("T");
        $this->addVar('tz', $tz);
        $record = Engine::api(IPlaylist::class)->getWhatsOnNow();
        if($record && ($row = $record->fetch())) {
            $row['showtime'] = Playlists::makeShowTime($row);
            $airId = $row["airid"];
            $row['djref'] =  "?subaction=viewDJ&seq=selUser&viewuser=$airId";
            $row['href'] = "?subaction=viewListById&playlist=$row[0]";
            $this->addVar('onnow', $row);
        }

        if(Engine::param('push_enabled', true)) {
            $push = preg_replace("/^(http)/", "ws", UI::getBaseUrl()) . "push/onair";
            $this->addVar('push', $push);
        }
    }
}
