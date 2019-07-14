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
 * Music review operations
 */
interface IReview {
    function getRecentReviews($user = "", $weeks = 0, $limit = 0, $loggedIn = 0);
    function getReviews($tag, $byName=1, $user = "", $loggedIn = 0);
    function insertReview($tag, $private, $airname, $review, $user);
    function updateReview($tag, $private, $airname, $review, $user);
    function deleteReview($tag, $user);
    function getRecentReviewsByAirname(&$result, $airname, $count, $loggedIn = 0);
}
