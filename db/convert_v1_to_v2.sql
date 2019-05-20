/*
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
 */

/**
 * IMPORTANT NOTE:
 *
 * Run this script ONLY if you are converting an existing Zookeeper Online v1
 * database for use with the current codebase.
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
SET NAMES utf8;

ALTER DATABASE `zkdb` CHARACTER SET utf8 COLLATE utf8_general_ci;

ALTER TABLE `airnames` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `albumvol` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `categories` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `chartemail` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `colltracknames` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `currents` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `lists` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `plays` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `publist` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `reviews` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `sessions` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `ssoredirect` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `ssosetup` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `tagqueue` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `tracknames` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `tracks` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `users` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;

ALTER TABLE `categories` ADD COLUMN `email` varchar(80) DEFAULT NULL;

UPDATE `categories` SET email='reggae@kzsu.stanford.edu' WHERE id = 9;
UPDATE `categories` SET email='classical@kzsu.stanford.edu' WHERE id = 8;
UPDATE `categories` SET email='world@kzsu.stanford.edu' WHERE id = 7;
UPDATE `categories` SET email='jazz@kzsu.stanford.edu' WHERE id = 6;
UPDATE `categories` SET email='hiphop@kzsu.stanford.edu' WHERE id = 5;
UPDATE `categories` SET email='metal@kzsu.stanford.edu' WHERE id = 4;
UPDATE `categories` SET email='rpm@kzsu.stanford.edu' WHERE id = 3;
UPDATE `categories` SET email='country@kzsu.stanford.edu' WHERE id = 2;
UPDATE `categories` SET email='blues@kzsu.stanford.edu' WHERE id = 1;

SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT;
SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS;
SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION;
