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
-- Table structure for table `chartemail`
--

CREATE TABLE IF NOT EXISTS `chartemail` (
  `id` tinyint(11) NOT NULL DEFAULT '0',
  `chart` varchar(80) NOT NULL DEFAULT '',
  `address` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `chart` (`chart`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `chartemail`
--

INSERT INTO `chartemail` (`id`, `chart`, `address`) VALUES
(1, 'Weekly', ''),
(2, 'Trades', ''),
(3, 'Monthly', ''),
(4, 'Crossroads', '');

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
