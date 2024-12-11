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

namespace ZK\Engine;

/**
 * Music review operations
 */
interface IReview {
    /*
     * MAX_REVIEW_LENGTH is the maximum length of the music review in
     * characters.  This is an administrative limit, as the underlying
     * database column can contain significantly more data.
     *
     * Note:  The review is stored in a MEDIUMTEXT, whose limit is, unlike
     * VARCHAR columns, specified in bytes as opposed to characters; thus,
     * you have to account for variable, multi-byte character encodings,
     * which can be 2, 3, or even 4 bytes per character.
     *
     * This value may be safely increased to 4194303, which is the
     * MEDIUMTEXT limit of 16,777,215 bytes divided by 4, the maximum
     * size of a utf8 encoding.
     */
    const MAX_REVIEW_LENGTH = 64000;

    function getRecentReviews($user = "", $weeks = 0, $limit = 0, $loggedIn = 0);
    function getActiveReviewers($viewAll=0, $loggedIn=0);
    function getReviews($tag, $byName=1, $user = "", $loggedIn = 0, $byId = 0);
    function getTrending(int $limit = 50);
    function insertReview($tag, $private, $airname, $review, $user);
    function updateReview($tag, $private, $airname, $review, $user);
    function deleteReview($tag, $user);
    function setExportId($tag, $user, $exportId);
}
