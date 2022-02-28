/*
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
 */

/**
 * IMPORTANT NOTE:
 *
 * Run this script ONLY if you are converting an existing Zookeeper Online
 * v2_11_1 database for use with the current codebase.
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

CREATE TABLE `albummap` (
  `tag` int(11) NOT NULL,
  `artwork` int(11) DEFAULT NULL,
  `cached` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tag`),
  KEY `artwork` (`artwork`),
  KEY `cached` (`cached`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE `artistmap` (
  `name` varchar(80) NOT NULL,
  `artwork` int(11) DEFAULT NULL,
  `cached` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`name`),
  KEY `artwork` (`artwork`),
  KEY `cached` (`cached`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE `artwork` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image_url` varchar(2083) DEFAULT NULL,
  `info_url` varchar(2083) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT;
SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS;
SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION;
