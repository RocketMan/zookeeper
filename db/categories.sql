-- phpMyAdmin SQL Dump
-- version 4.0.10.19
-- https://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: May 16, 2018 at 08:12 PM
-- Server version: 10.0.29-MariaDB-cll-lve
-- PHP Version: 5.6.36

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `deb7412_zkdb`
--

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

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `code`, `director`) VALUES
(1, 'Blues', 'B', 'Blues Director: '),
(2, 'Country/Bluegrass', 'C', 'Country/Bluegrass Director: '),
(3, 'RPM/Electronica', 'D', 'RPM/Electronica Director: '),
(4, 'Loud', 'M', 'Metal Director: '),
(5, 'Hip-Hop', 'H', 'Hip-Hop Director: '),
(6, 'Jazz', 'J', 'Jazz Director: '),
(7, 'Reggae/World', 'W', 'World Director: '),
(8, 'Classical/Experimental', 'X', 'Classical/Experimental Director: '),
(9, '(Reggae)', 'R', 'Reggae Director: '),
(10, '', '', ''),
(11, '', '', ''),
(12, '', '', ''),
(14, '', '', ''),
(13, '', '', ''),
(15, '', '', ''),
(16, '', '', '');

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
