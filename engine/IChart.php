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

namespace ZK\Engine;

/**
 * Chart operations
 */
interface IChart {
    function getCategories();
    function updateCategory($i, $name, $code, $dir);
    function getNextAID();
    function getAddDates($limit="");
    function getAdd($date);
    function getAdd2($date);
    function getCurrents($date);
    function getCurrents2($date);
    function getCurrentsWithPlays($date);
    function getCurrentsWithPlays2($date=0);
    function addAlbum($aid, $tag, $adddate, $pulldate, $cats);
    function updateAlbum($id, $aid, $tag, $adddate, $pulldate, $cats);
    function deleteAlbum($id);
    function getAlbum($id);
    function getAlbumByTag($tag);
    function getAlbumPlays($tag, $startDate="", $endDate="", $limit="");
    function getChartDates($limit=0);
    function getChartDatesByYear($year, $limit=0);
    function getChartYears($limit=0);
    function getChartMonths($limit=0);
    function getChartSortFn($a, $b);
    function getChart(&$result, $startDate, $endDate, $limit="", $category="");
    function getChart2(&$result, $startDate, $endDate, $limit="", $category="");
    function getBottom(&$result, $startDate, $endDate, $limit="", $category="");
    function getChartEMail();
    function updateChartEMail($i, $address);
    function getWeeklyActivity($date);
    function doChart($chartDate, $maxSpins, $limitPerDJ=0);
    function getMonthlyChartStart($month, $year);
    function getMonthlyChartEnd($month, $year);
}
