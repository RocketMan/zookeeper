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

-- phpMyAdmin SQL Dump
-- version 4.0.10.19
-- https://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: May 15, 2018 at 08:25 PM
-- Server version: 10.0.29-MariaDB-cll-lve
-- PHP Version: 5.6.36

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `zkdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `airnames`
--

CREATE TABLE IF NOT EXISTS `airnames` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dj` varchar(8) DEFAULT NULL,
  `airname` varchar(30) NOT NULL DEFAULT '',
  `url` varchar(80) DEFAULT NULL,
  `email` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `airname_2` (`airname`),
  KEY `airname` (`airname`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;

-- --------------------------------------------------------

--
-- Table structure for table `albumvol`
--

CREATE TABLE IF NOT EXISTS `albumvol` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `artist` varchar(80) NOT NULL DEFAULT '',
  `album` varchar(80) NOT NULL DEFAULT '',
  `category` char(1) DEFAULT NULL,
  `medium` char(1) DEFAULT NULL,
  `size` char(1) DEFAULT NULL,
  `created` date DEFAULT NULL,
  `updated` date DEFAULT NULL,
  `pubkey` int(11) DEFAULT NULL,
  `location` char(1) DEFAULT NULL,
  `bin` varchar(8) DEFAULT NULL,
  `tag` int(11) DEFAULT NULL,
  `iscoll` char(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag` (`tag`),
  KEY `artist` (`artist`),
  KEY `album` (`album`),
  KEY `pubkey` (`pubkey`),
  KEY `aat` (`artist`,`album`,`tag`),
  FULLTEXT KEY `artist_2` (`artist`,`album`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE IF NOT EXISTS `categories` (
  `id` tinyint(11) NOT NULL DEFAULT '0',
  `name` varchar(80) NOT NULL DEFAULT '',
  `code` char(1) DEFAULT NULL,
  `director` varchar(80) DEFAULT NULL,
  `email` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 PACK_KEYS=1;

-- --------------------------------------------------------

--
-- Table structure for table `chartemail`
--

CREATE TABLE IF NOT EXISTS `chartemail` (
  `id` tinyint(11) NOT NULL DEFAULT '0',
  `chart` varchar(80) NOT NULL DEFAULT '',
  `address` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `chart` (`chart`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `colltracknames`
--

CREATE TABLE IF NOT EXISTS `colltracknames` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tag` int(11) DEFAULT NULL,
  `track` varchar(80) DEFAULT NULL,
  `artist` varchar(80) DEFAULT NULL,
  `seq` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tag` (`tag`),
  KEY `track` (`track`),
  KEY `artist` (`artist`),
  KEY `tag_2` (`tag`,`seq`),
  FULLTEXT KEY `artist_2` (`artist`,`track`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;

-- --------------------------------------------------------

--
-- Table structure for table `currents`
--

CREATE TABLE IF NOT EXISTS `currents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `afile_number` int(11) NOT NULL DEFAULT '0',
  `tag` int(11) NOT NULL DEFAULT '0',
  `adddate` date NOT NULL DEFAULT '0000-00-00',
  `pulldate` date NOT NULL DEFAULT '0000-00-00',
  `category` set('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16') NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `addkey` (`afile_number`,`adddate`),
  KEY `afile_number` (`afile_number`),
  KEY `tag` (`tag`),
  KEY `adddate` (`adddate`),
  KEY `pulldate` (`pulldate`),
  KEY `category` (`category`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 PACK_KEYS=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `lists`
--

CREATE TABLE IF NOT EXISTS `lists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dj` varchar(8) DEFAULT NULL,
  `showdate` date NOT NULL DEFAULT '0000-00-00',
  `showtime` varchar(20) DEFAULT NULL,
  `description` varchar(80) DEFAULT NULL,
  `airname` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dj` (`dj`),
  KEY `showdate` (`showdate`),
  KEY `airname` (`airname`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;

-- --------------------------------------------------------

--
-- Table structure for table `lists_del`
--

CREATE TABLE IF NOT EXISTS `lists_del` (
  `listid` int(11) NOT NULL DEFAULT '0',
  `airname` int(11) DEFAULT NULL,
  `deleted` date DEFAULT NULL,
  PRIMARY KEY (`listid`),
  KEY `deleted` (`deleted`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `plays`
--

CREATE TABLE IF NOT EXISTS `plays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tag` int(11) NOT NULL DEFAULT '0',
  `week` date NOT NULL DEFAULT '0000-00-00',
  `plays` int(11) DEFAULT '0',
  `delta` int(11) DEFAULT '0',
  `category` set('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16') NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `tag` (`tag`),
  KEY `week` (`week`),
  KEY `category` (`category`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 PACK_KEYS=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `publist`
--

CREATE TABLE IF NOT EXISTS `publist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pubkey` int(11) DEFAULT NULL,
  `name` varchar(80) NOT NULL DEFAULT '',
  `attention` varchar(80) DEFAULT NULL,
  `address` varchar(80) DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `state` varchar(80) DEFAULT NULL,
  `zip` varchar(10) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `fax` varchar(20) DEFAULT NULL,
  `mailcount` tinyint(4) DEFAULT NULL,
  `maillist` char(1) DEFAULT NULL,
  `international` char(1) DEFAULT NULL,
  `pcreated` date DEFAULT NULL,
  `modified` date DEFAULT NULL,
  `url` varchar(80) DEFAULT NULL,
  `email` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pubkey` (`pubkey`),
  KEY `name` (`name`),
  KEY `np` (`name`,`pubkey`),
  FULLTEXT KEY `name_2` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE IF NOT EXISTS `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tag` int(11) NOT NULL DEFAULT '0',
  `user` varchar(8) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `private` int(11) DEFAULT NULL,
  `airname` int(11) DEFAULT NULL,
  `review` mediumtext,
  PRIMARY KEY (`id`),
  KEY `user` (`user`),
  KEY `tag` (`tag`),
  KEY `created` (`created`),
  FULLTEXT KEY `review` (`review`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sessionkey` varchar(32) NOT NULL DEFAULT '',
  `user` varchar(8) DEFAULT NULL,
  `access` varchar(12) DEFAULT NULL,
  `logon` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `realname` varchar(128) DEFAULT NULL,
  `portid` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sessionkey` (`sessionkey`),
  KEY `logon` (`logon`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;

-- --------------------------------------------------------

--
-- Table structure for table `ssoredirect`
--

CREATE TABLE IF NOT EXISTS `ssoredirect` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sessionkey` varchar(32) DEFAULT NULL,
  `url` mediumtext,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sessionkey` (`sessionkey`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;

-- --------------------------------------------------------

--
-- Table structure for table `ssosetup`
--

CREATE TABLE IF NOT EXISTS `ssosetup` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sessionkey` varchar(32) DEFAULT NULL,
  `fullname` varchar(128) DEFAULT NULL,
  `account` varchar(128) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `url` mediumtext,
  PRIMARY KEY (`id`),
  KEY `sessionkey` (`sessionkey`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;

-- --------------------------------------------------------

--
-- Table structure for table `tagqueue`
--

CREATE TABLE IF NOT EXISTS `tagqueue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` varchar(8) NOT NULL DEFAULT '',
  `tag` int(11) NOT NULL DEFAULT '0',
  `keyed` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ut` (`user`,`tag`),
  KEY `user` (`user`),
  KEY `keyed` (`keyed`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;

-- --------------------------------------------------------

--
-- Table structure for table `tracknames`
--

CREATE TABLE IF NOT EXISTS `tracknames` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tag` int(11) DEFAULT NULL,
  `track` varchar(80) DEFAULT NULL,
  `seq` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tag` (`tag`),
  KEY `track` (`track`),
  KEY `tag_2` (`tag`,`seq`),
  FULLTEXT KEY `track_2` (`track`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;

-- --------------------------------------------------------

--
-- Table structure for table `tracks`
--

CREATE TABLE IF NOT EXISTS `tracks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `list` int(11) NOT NULL DEFAULT '0',
  `tag` int(11) DEFAULT NULL,
  `artist` varchar(80) DEFAULT NULL,
  `track` varchar(80) DEFAULT NULL,
  `album` varchar(80) DEFAULT NULL,
  `label` varchar(80) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `list` (`list`),
  KEY `tag` (`tag`),
  KEY `aal` (`artist`,`album`,`label`),
  FULLTEXT KEY `artist` (`artist`,`album`,`track`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(8) DEFAULT NULL,
  `password` varchar(34) DEFAULT NULL,
  `groups` varchar(12) DEFAULT NULL,
  `realname` varchar(128) DEFAULT NULL,
  `expires` date DEFAULT NULL,
  `legacypass` varchar(8) DEFAULT NULL,
  `lastlogin` date DEFAULT NULL,
  `ssoaccount` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `ssoaccount` (`ssoaccount`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
