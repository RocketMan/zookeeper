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
-- Generation Time: May 16, 2018 at 03:14 PM
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
-- Dumping data for table `users`
--

-- CREATE USER 'zookeeper'@'localhost' IDENTIFIED BY 'zookeeper';

drop database if exists zkdb;
-- create database zkdb;
GRANT ALL PRIVILEGES ON zkdb.* TO 'zookeeper'@'localhost';
use zkdb;

create table users;

INSERT INTO `users` (`id`, `name`, `password`, `groups`, `realname`, `expires`, `legacypass`, `lastlogin`, `ssoaccount`) VALUES
(1, 'root', 'a68bbd37621a42a19259a18f227dc9dbc3', 'mxncp', 'Zookeeper Superuser', NULL, NULL, NULL, NULL);
