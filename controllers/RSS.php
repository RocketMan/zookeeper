<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2025 Jim Mason <jmason@ibinx.com>
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
use ZK\Engine\IArtwork;
use ZK\Engine\IChart;
use ZK\Engine\ILibrary;
use ZK\Engine\IReview;

use ZK\UI\UICommon as UI;

class RSS extends CommandTarget implements IController {
    private static $actions = [
        [ "", "emitError" ],
        [ "reviews", "recentReviews" ],
        [ "charts", "recentCharts" ],
        [ "adds", "recentAdds" ],
    ];

    private $params = [];

    public function processRequest() {
        header("Content-type: text/xml; charset=UTF-8");
        ob_start("ob_gzhandler");
        $templateFactory = new TemplateFactoryXML();
        $template = $templateFactory->load('rss.xml');
        $this->params['feeds'] = [];

        $feeds = explode(',', $_REQUEST['feed'] ?? '');
        foreach($feeds as $feed)
            $this->processLocal($feed, null);

        echo $template->render($this->params);
        ob_end_flush(); // ob_gzhandler
    }

    public function processLocal($action, $subaction) {
        $this->dispatchAction($action, self::$actions);
    }

    public function composeChartRSS($endDate, $limit="", $category="") {
        $chart = [];
        Engine::api(IChart::class)->getChart($chart, "", $endDate, $limit, $category);
        $albums = [];
        if(sizeof($chart)) {
            for($i=0; $i < sizeof($chart); $i++) {
                $album = $chart[$i];
                $label = str_replace(" Records", "", $chart[$i]["label"]);
                $label = str_replace(" Recordings", "", $label);
                $album['label'] = $label;
                $album['rank'] = $i + 1;
                $albums[] = $album;
            }
        }
        return $albums;
    }
    
    public function recentCharts() {
        $top = $_REQUEST["top"] ?? 30;
        $weeks = $_REQUEST["weeks"] ?? 10;

        $this->params['limit'] = $top;
        $this->params['dateSpec'] = UI::isUsLocale() ? 'l, F j, Y' : 'l, j F Y';
        $this->params['MEDIA'] = ILibrary::MEDIA;
        $weeks = Engine::api(IChart::class)->getChartDates($weeks)->asArray();
        $charts = array_map(function($week) use ($top) {
            return [
                'endDate' => $week['week'],
                'albums' => $this->composeChartRSS($week['week'], $top)
            ];
        }, $weeks);
        $this->params['charts'] = $charts;
        $this->params['feeds'][] = 'charts';
    }
    
    public function recentReviews() {
        $dateSpec = UI::isUsLocale() ? 'F j, Y' : 'j F Y';
        $this->params['dateSpec'] = $dateSpec;
        $this->params['GENRES'] = ILibrary::GENRES;
        $this->params['feeds'][] = 'reviews';

        $limit = $_REQUEST['limit'] ?? 50;
        $results = Engine::api(IReview::class)->getRecentReviews('', 0, $limit);
        // coalesce albums into one array for artwork injection
        // use foreach, as reference passing does not work with array_map
        $albums = [];
        foreach($results as &$review)
            $albums[] = &$review["album"];
        Engine::api(IArtwork::class)->injectAlbumArt($albums, Engine::getBaseUrl());
        foreach($results as &$row) {
            $reviews = Engine::api(IReview::class)->getReviews($row['album']['tag'], 0, $row['user']);
            if(count($reviews)) {
                $row['review'] = $row['body'] = $reviews[0]['review'];
                $row['tracks'] = '';
                if(preg_match('/(.+?)(?=(\r?\n)[\p{P}\p{S}\s]*\d+[\p{P}\p{S}\d]*\s)/su',
                        $row['review'], $matches) && $matches[1]) {
                    $row['tracks'] = trim(mb_substr($row['review'], mb_strlen($matches[1])));
                    $row['body'] = $matches[1];
                }
            }
        }

        $this->params['GENRES'] = ILibrary::GENRES;
        $this->params['reviews'] = $results;
    }
    
    public function composeAddRSS($addDate, $cats) {
        $albums = [];
        $results = Engine::api(IChart::class)->getAdd($addDate);
        if($results) {
            while($row = $results->fetch()) {
                // Categories
                $acats = explode(",", $row["afile_category"]);
                $codes = implode("", array_map(function($cat) use ($cats) {
                    return $cat ? $cats[$cat - 1]["code"] : "";
                }, $acats));
    
                if($codes == "")
                    $codes = "G";

                $row['codes'] = $codes;
                $albums[] = $row;
            }
        }
        return $albums;
    }
    
    public function recentAdds() {
        $weeks = $_REQUEST["weeks"] ?? 4;

        $this->params['dateSpec'] = UI::isUsLocale() ? 'l, F j, Y' : 'l, j F Y';
        $this->params['MEDIA'] = ILibrary::MEDIA;
        $weeks = Engine::api(IChart::class)->getAddDates($weeks)->asArray();
        $cats = Engine::api(IChart::class)->getCategories();
        $adds = array_map(function($week) use ($cats) {
            return [
                'addDate' => $week["adddate"],
                'albums' => $this->composeAddRSS($week["adddate"], $cats)
            ];
        }, $weeks);
        $this->params['adds'] = $adds;
        $this->params['feeds'][] = 'adds';
    }
    
    public function emitError() {
        $this->params['feeds'][] = 'invalid';
    }
}
