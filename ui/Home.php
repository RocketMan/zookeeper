<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2024 Jim Mason <jmason@ibinx.com>
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
        $result = [];

        $now = new \DateTime();
        $result[] = clone $now;

        for($i=0; $i<6; $i++) {
            $now->modify("-1 days");
            $result[] = clone $now;
        }

        $this->addVar("dates", $result);
    }

    protected function makeTimePicker($date=null) {
        $result = [];

        $now = new \DateTime();
        if(!$date || $now->format("Y-m-d") == $date) {
            // today
            $hour = (int)$now->format("H");
            $result[] = -1;
        } else {
            $hour = 23;
            $result[] = $hour;
        }

        do {
            if($hour % 3) continue;
            $result[] = $hour;
        } while(--$hour > 0);

        $this->addVar("times", $result);
    }

    public function getTimes() {
        $this->setTemplate('onnow.html');
        $this->makeTimePicker($_REQUEST["date"] ?? null);
        echo json_encode($this->render('time'));
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
        $this->makeDatePicker();
        $this->makeTimePicker();
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
            $this->addVar('onnow', $row);
        }

        if(Engine::param('push_enabled', true)) {
            $push = preg_replace("/^(http)/", "ws", Engine::getBaseUrl()) . "push/onair";
            $this->addVar('push', $push);
        }
    }
}
