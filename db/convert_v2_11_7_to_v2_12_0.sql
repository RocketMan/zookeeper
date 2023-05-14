/*
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2023 Jim Mason <jmason@ibinx.com>
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
 */

/**
 * IMPORTANT NOTE:
 *
 * Run this script ONLY if you are converting an existing Zookeeper Online
 * v2_11_7 database for use with the current codebase.
 *
 * If you are creating a new database, run zkdbSchema.sql and then populate
 * the resulting db using the various bootstrap scripts as appropriate.
 */

--
-- Database: `zkdb`
--

SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT;
SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS;
SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION;
SET NAMES utf8mb4;

ALTER TABLE `airnames` MODIFY `dj` varchar(8) NOT NULL;
ALTER TABLE `lists` MODIFY `dj` varchar(8) NOT NULL;
ALTER TABLE `reviews` MODIFY `user` varchar(8) NOT NULL;
ALTER TABLE `sessions` MODIFY `user` varchar(8) NOT NULL;
ALTER TABLE `tagqueue` MODIFY `user` varchar(8) NOT NULL;
ALTER TABLE `tagqueue` MODIFY `tag` int(11) NOT NULL;
ALTER TABLE `tagqueue` MODIFY `keyed` datetime NOT NULL;
ALTER TABLE `users` MODIFY `name` varchar(8) NOT NULL;

ALTER TABLE `albumvol` ADD INDEX `location` (`location`);
UPDATE albumvol SET location='L' WHERE location='C';
UPDATE albumvol a INNER JOIN currents c ON a.tag = c.tag AND adddate <= CURDATE() AND pulldate > CURDATE() SET location='C';

SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT;
SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS;
SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION;
