-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 10, 2026 at 12:59 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hongsing_dms`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(120) DEFAULT NULL,
  `role` enum('Super Admin','Admin','Driver','Customer') DEFAULT NULL,
  `action` varchar(120) DEFAULT NULL,
  `module` varchar(80) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`log_id`, `user_id`, `full_name`, `role`, `action`, `module`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'Super Administrator', 'Super Admin', 'CREATE', 'Admins', 'Created administrator: Jose Reyes', '::1', '2026-08-03 22:13:52'),
(2, 1, 'Super Administrator', 'Super Admin', 'UPDATE', 'Admins', 'Updated administrator: Jose Reyes', '::1', '2026-08-03 22:31:29'),
(3, 1, 'Super Administrator', 'Super Admin', 'UPDATE', 'driver', 'Updated driver: Mateo Ramos', '::1', '2026-08-03 22:54:31'),
(4, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-08-03 23:14:07'),
(5, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-08-03 23:24:04'),
(6, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-03 23:24:13'),
(7, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-04 19:48:35'),
(8, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-08-04 19:56:24'),
(9, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-08-04 19:56:39'),
(10, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-08-04 19:56:51'),
(11, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-09 21:39:20'),
(12, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-08-09 22:00:35'),
(13, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-08-09 22:00:50'),
(14, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-08-09 22:03:11'),
(15, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-09 22:03:22'),
(16, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-08-09 22:14:44'),
(17, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-08-09 22:14:54'),
(18, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-08-09 22:16:02'),
(19, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-09 22:16:09'),
(20, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-10 10:36:35'),
(21, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-08-10 10:51:17'),
(22, 1, 'Mateo Ramos', 'Driver', 'LOGIN', 'Authentication', 'Mateo Ramos logged into the system.', '::1', '2026-08-10 10:51:57'),
(23, 1, 'Mateo Ramos', 'Driver', 'LOGOUT', 'Authentication', 'Mateo Ramos logged out.', '::1', '2026-08-10 10:52:22'),
(24, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-10 10:52:31'),
(25, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-08-10 10:55:18'),
(26, 1, 'Mateo Ramos', 'Driver', 'LOGIN', 'Authentication', 'Mateo Ramos logged into the system.', '::1', '2026-08-10 10:55:29'),
(27, 1, 'Mateo Ramos', 'Driver', 'LOGIN', 'Authentication', 'Mateo Ramos logged into the system.', '::1', '2026-08-11 11:23:17'),
(28, 1, 'Mateo Ramos', 'Driver', 'LOGOUT', 'Authentication', 'Mateo Ramos logged out.', '::1', '2026-08-11 11:27:26'),
(29, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-11 11:27:47'),
(30, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-11 12:04:29'),
(31, 1, 'Mateo Ramos', 'Driver', 'LOGIN', 'Authentication', 'Mateo Ramos logged into the system.', '::1', '2026-08-11 12:04:42'),
(32, 1, 'Mateo Ramos', 'Driver', 'LOGOUT', 'Authentication', 'Mateo Ramos logged out.', '::1', '2026-08-11 12:07:39'),
(33, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-11 12:07:50'),
(34, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-11 15:40:40'),
(35, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-11 15:51:35'),
(36, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-11 15:51:44'),
(37, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-11 15:56:03'),
(38, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-11 15:56:16'),
(39, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-11 16:01:29'),
(40, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-11 16:01:47'),
(41, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-11 16:02:13'),
(42, 1, 'Mateo Ramos', 'Driver', 'LOGIN', 'Authentication', 'Mateo Ramos logged into the system.', '::1', '2026-08-11 16:02:27'),
(43, 1, 'Mateo Ramos', 'Driver', 'LOGOUT', 'Authentication', 'Mateo Ramos logged out.', '::1', '2026-08-11 16:03:31'),
(44, 1, 'Mateo Ramos', 'Driver', 'LOGIN', 'Authentication', 'Mateo Ramos logged into the system.', '::1', '2026-08-11 16:40:11'),
(45, 1, 'Mateo Ramos', 'Driver', 'LOGIN', 'Authentication', 'Mateo Ramos logged into the system.', '::1', '2026-08-11 18:40:24'),
(46, 1, 'Mateo Ramos', 'Driver', 'LOGOUT', 'Authentication', 'Mateo Ramos logged out.', '::1', '2026-08-11 19:16:34'),
(47, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-11 19:16:45'),
(48, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-11 20:13:02'),
(49, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-08-11 20:13:07'),
(50, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-08-11 20:35:27'),
(51, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-11 20:36:47'),
(52, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-08-11 20:37:59'),
(53, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-11 20:38:18'),
(54, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-11 20:38:45'),
(55, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-08-11 20:39:01'),
(56, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-08-11 20:39:51'),
(57, 1, 'Mateo Ramos', 'Driver', 'LOGIN', 'Authentication', 'Mateo Ramos logged into the system.', '::1', '2026-08-11 20:40:01'),
(58, 1, 'Mateo Ramos', 'Driver', 'LOGOUT', 'Authentication', 'Mateo Ramos logged out.', '::1', '2026-08-11 20:40:16'),
(59, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-11 20:40:27'),
(60, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-11 20:40:55'),
(61, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-08-11 20:41:08'),
(62, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-08-11 20:41:36'),
(63, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-11 20:41:47'),
(64, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-11 21:38:50'),
(65, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-08-14 12:01:16'),
(66, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-08-14 13:40:37'),
(67, 1, 'Mateo Ramos', 'Driver', 'LOGIN', 'Authentication', 'Mateo Ramos logged into the system.', '::1', '2026-08-14 13:40:48'),
(68, 1, 'Mateo Ramos', 'Driver', 'LOGOUT', 'Authentication', 'Mateo Ramos logged out.', '::1', '2026-08-14 13:50:01'),
(69, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-14 13:50:12'),
(70, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-14 13:51:04'),
(71, 1, 'Mateo Ramos', 'Driver', 'LOGIN', 'Authentication', 'Mateo Ramos logged into the system.', '::1', '2026-08-14 13:51:26'),
(72, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-14 18:36:10'),
(73, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-08-14 18:37:36'),
(74, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-14 18:37:45'),
(75, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-14 18:38:01'),
(76, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-14 18:38:09'),
(77, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-08-14 18:42:51'),
(78, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-14 18:43:01'),
(79, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-16 15:46:12'),
(80, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-18 12:52:18'),
(81, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-18 13:03:37'),
(82, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-18 13:03:50'),
(83, 1, 'Super Administrator', 'Super Admin', 'CREATE', 'driver', 'Created driver: Efren Santos', '::1', '2026-08-18 13:13:00'),
(84, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-08-18 13:13:09'),
(85, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-18 13:13:17'),
(86, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-18 13:14:10'),
(87, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-18 13:14:34'),
(88, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-18 13:26:28'),
(89, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-08-18 13:26:39'),
(90, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-08-18 13:28:13'),
(91, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-08-18 18:13:35'),
(92, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-24 17:18:01'),
(93, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-24 17:21:12'),
(94, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-24 19:01:38'),
(95, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-24 19:02:27'),
(96, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-24 19:02:36'),
(97, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-24 19:11:20'),
(98, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-24 19:12:06'),
(99, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-24 19:40:50'),
(100, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-08-24 19:41:55'),
(101, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-08-24 19:42:00'),
(102, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-24 19:42:07'),
(103, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-24 19:48:15'),
(104, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-25 16:44:54'),
(105, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-25 17:07:43'),
(106, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-25 17:07:53'),
(107, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-25 17:52:50'),
(108, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-25 17:58:56'),
(109, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-25 18:00:30'),
(110, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-25 18:00:52'),
(111, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-25 18:00:58'),
(112, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-25 18:01:12'),
(113, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-25 18:05:01'),
(114, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-25 18:05:11'),
(115, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-25 18:23:04'),
(116, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-25 18:23:29'),
(117, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-25 18:27:03'),
(118, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-25 18:28:29'),
(119, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-08-26 06:31:13'),
(120, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-08-26 06:35:02'),
(121, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-26 06:35:12'),
(122, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-26 06:38:12'),
(123, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-26 06:38:22'),
(124, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-08-26 06:40:14'),
(125, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-26 06:40:24'),
(126, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-08-26 06:40:52'),
(127, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-08-26 17:17:32'),
(128, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-08-26 17:47:16'),
(129, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-26 17:47:25'),
(130, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-26 17:48:09'),
(131, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-08-26 17:48:23'),
(132, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-08-26 17:50:10'),
(133, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-26 17:50:22'),
(134, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-26 17:50:44'),
(135, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-08-26 17:50:51'),
(136, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-08-26 17:52:51'),
(137, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-26 17:53:13'),
(138, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-26 17:53:28'),
(139, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-08-26 17:57:18'),
(140, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-26 17:57:31'),
(141, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-26 17:58:09'),
(142, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-08-26 17:58:26'),
(143, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-08-26 18:03:53'),
(144, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-26 18:04:07'),
(145, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-26 18:04:53'),
(146, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-08-26 18:05:09'),
(147, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-08-26 18:08:39'),
(148, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-26 18:08:51'),
(149, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-26 18:09:10'),
(150, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-08-26 18:09:17'),
(151, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-08-26 18:15:36'),
(152, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-26 18:15:47'),
(153, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-26 18:20:38'),
(154, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-26 18:20:48'),
(155, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-26 18:20:55'),
(156, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-26 18:21:02'),
(157, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-08-26 18:27:30'),
(158, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-26 18:28:07'),
(159, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-26 18:32:12'),
(160, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-26 18:36:05'),
(161, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-26 18:36:09'),
(162, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-08-26 18:36:17'),
(163, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-08-26 18:36:55'),
(164, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-08-26 18:40:13'),
(165, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-08-26 18:41:23'),
(166, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-26 18:41:52'),
(167, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-08-26 18:42:50'),
(168, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-08-26 18:43:17'),
(169, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-08-26 18:44:33'),
(170, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-26 18:44:44'),
(171, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-26 18:45:08'),
(172, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-26 18:45:23'),
(173, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-26 18:45:55'),
(174, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-26 18:47:16'),
(175, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-26 18:49:39'),
(176, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-08-26 18:49:46'),
(177, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-08-26 18:53:23'),
(178, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-26 18:53:51'),
(179, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-26 18:58:22'),
(180, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-26 18:58:33'),
(181, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-08-26 18:58:53'),
(182, 1, 'Mateo Ramos', 'Driver', 'LOGIN', 'Authentication', 'Mateo Ramos logged into the system.', '::1', '2026-08-26 18:59:02'),
(183, 1, 'Mateo Ramos', 'Driver', 'LOGOUT', 'Authentication', 'Mateo Ramos logged out.', '::1', '2026-08-26 18:59:26'),
(184, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-08-26 18:59:35'),
(185, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-08-26 19:03:33'),
(186, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-26 19:04:03'),
(187, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-08-26 19:08:52'),
(188, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-26 19:09:01'),
(189, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-26 19:12:05'),
(190, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-08-26 19:12:15'),
(191, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-08-26 19:17:09'),
(192, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-26 19:17:25'),
(193, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-26 19:21:07'),
(194, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-26 19:44:00'),
(195, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-08-26 19:48:31'),
(196, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-26 19:48:53'),
(197, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-26 19:50:44'),
(198, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-26 19:51:00'),
(199, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-26 19:52:26'),
(200, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-08-26 19:52:39'),
(201, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-08-26 20:00:40'),
(202, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-26 20:01:00'),
(203, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-26 20:05:05'),
(204, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-08-26 20:05:12'),
(205, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-08-26 20:05:22'),
(206, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-26 20:05:29'),
(207, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-26 20:06:53'),
(208, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-26 20:07:01'),
(209, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-26 20:07:40'),
(210, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-08-26 20:07:47'),
(211, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-08-26 20:08:02'),
(212, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-26 20:08:14'),
(213, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-08-26 20:08:34'),
(214, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-08-26 20:14:07'),
(215, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-08-26 20:15:29'),
(216, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-08-26 20:15:37'),
(217, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-09-03 22:24:23'),
(218, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-09-03 23:27:54'),
(219, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-03 23:28:04'),
(220, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-09-03 23:31:03'),
(221, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-03 23:31:12'),
(222, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-06 08:25:45'),
(223, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-07 02:16:24'),
(224, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-07 18:57:59'),
(225, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-08 01:00:11'),
(226, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-09-08 01:08:18'),
(227, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-08 01:08:35'),
(228, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-08 21:38:41'),
(229, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-09-08 21:41:48'),
(230, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-08 21:42:07'),
(231, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-09-08 21:49:05'),
(232, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-08 21:49:13'),
(233, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-09-08 22:19:07'),
(234, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-08 22:19:25'),
(235, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-09 01:14:05'),
(236, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-09 02:08:31'),
(237, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-09 03:18:26'),
(238, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-09 04:28:55'),
(239, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-09 16:17:31'),
(240, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-09-09 16:36:01'),
(241, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-09 16:36:23'),
(242, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-09-09 16:45:51'),
(243, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-09 16:46:01'),
(244, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-09-09 16:47:29'),
(245, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-09-09 16:47:38'),
(246, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-09-09 16:54:47'),
(247, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-09-09 16:54:56'),
(248, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-09-09 16:55:07'),
(249, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-09-09 17:06:33'),
(250, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-09-09 18:31:23'),
(251, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-09 18:39:18'),
(252, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-09-09 18:48:23'),
(253, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-09 18:48:40'),
(254, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-13 23:00:29'),
(255, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-09-16 15:17:19'),
(256, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-09-16 15:25:47'),
(257, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-09-16 15:27:11'),
(258, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-09-16 15:59:12'),
(259, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-09-16 16:09:29'),
(260, 1, 'Super Administrator', 'Super Admin', 'UPDATE', 'Admins', 'Administrator record reviewed/saved: Jose Reyes', '::1', '2026-09-16 16:13:45'),
(261, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-09-16 16:13:51'),
(262, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-09-16 16:19:14'),
(263, 1, 'Super Administrator', 'Super Admin', 'UPDATE', 'driver', 'Updated driver: Efren Santos | Status changed from \"Available\" to \"Absent\"', '::1', '2026-09-16 16:23:57'),
(264, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-09-16 16:24:55'),
(265, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-09-16 16:25:28'),
(266, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-09-16 17:06:17'),
(267, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-09-16 17:08:29'),
(268, 1, 'Super Administrator', 'Super Admin', 'UPDATE', 'Admins', 'Updated administrator: Jose Reyes | Status changed from \"On Leave\" to \"Active\"', '::1', '2026-09-16 17:08:45'),
(269, 1, 'Super Administrator', 'Super Admin', 'UPDATE', 'driver', 'Updated driver: Efren Santos | Status changed from \"Absent\" to \"Available\"', '::1', '2026-09-16 17:08:54'),
(270, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-09-16 17:09:02'),
(271, 2, 'Efren Santos', 'Driver', 'LOGIN', 'Authentication', 'Efren Santos logged into the system.', '::1', '2026-09-16 17:09:47'),
(272, 2, 'Efren Santos', 'Driver', 'LOGOUT', 'Authentication', 'Efren Santos logged out.', '::1', '2026-09-16 17:13:16'),
(273, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-09-16 17:14:02'),
(274, 1, 'Super Administrator', 'Super Admin', 'UPDATE', 'driver', 'Updated driver: Efren Santos | Status changed from \"Available\" to \"Absent\"', '::1', '2026-09-16 17:14:49'),
(275, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-09-16 17:14:53'),
(276, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-09-16 17:18:00'),
(277, 1, 'Super Administrator', 'Super Admin', 'UPDATE', 'driver', 'Updated driver: Efren Santos | Status changed from \"Absent\" to \"Available\"', '::1', '2026-09-16 17:18:22'),
(278, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-09-16 17:28:10'),
(279, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-16 17:28:24'),
(280, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-09-16 17:32:47'),
(281, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-20 14:43:59'),
(282, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-09-20 14:44:27'),
(283, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-20 15:02:20'),
(284, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-09-20 16:33:14'),
(285, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-09-20 16:33:25'),
(286, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-09-20 16:57:56'),
(287, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-20 16:58:04'),
(288, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-09-20 18:07:20'),
(289, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-20 18:07:31'),
(290, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-09-20 18:07:53'),
(291, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-20 18:08:01'),
(292, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-09-20 18:08:52'),
(293, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-20 18:09:00'),
(294, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-09-20 18:41:48'),
(295, 33, 'Adrian Cruz', 'Driver', 'LOGIN', 'Authentication', 'Adrian Cruz logged into the system.', '::1', '2026-09-20 18:42:00'),
(296, 33, 'Adrian Cruz', 'Driver', 'LOGOUT', 'Authentication', 'Adrian Cruz logged out.', '::1', '2026-09-20 18:44:58'),
(297, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-20 18:45:06'),
(298, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-09-20 18:55:53'),
(299, 33, 'Adrian Cruz', 'Driver', 'LOGIN', 'Authentication', 'Adrian Cruz logged into the system.', '::1', '2026-09-20 18:56:07'),
(300, 33, 'Adrian Cruz', 'Driver', 'LOGOUT', 'Authentication', 'Adrian Cruz logged out.', '::1', '2026-09-20 19:16:01'),
(301, 33, 'Adrian Cruz', 'Driver', 'LOGIN', 'Authentication', 'Adrian Cruz logged into the system.', '::1', '2026-09-20 19:19:00'),
(302, 33, 'Adrian Cruz', 'Driver', 'LOGOUT', 'Authentication', 'Adrian Cruz logged out.', '::1', '2026-09-20 19:20:41'),
(303, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-09-20 19:20:51'),
(304, 1, 'Super Administrator', 'Super Admin', 'UPDATE', 'Profile Security', 'Super Administrator changed the Super Admin account password.', '::1', '2026-09-20 19:34:38'),
(305, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-09-20 20:01:32'),
(306, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-20 20:01:41'),
(307, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-09-21 16:15:00'),
(308, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-09-21 16:18:29'),
(309, 33, 'Adrian Cruz', 'Driver', 'LOGIN', 'Authentication', 'Adrian Cruz logged into the system.', '::1', '2026-09-21 16:18:57'),
(310, 33, 'Adrian Cruz', 'Driver', 'LOGOUT', 'Authentication', 'Adrian Cruz logged out.', '::1', '2026-09-21 16:39:22'),
(311, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-21 16:45:31'),
(312, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-09-21 16:46:01'),
(313, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-09-21 16:46:20'),
(314, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-09-21 16:48:11'),
(315, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-09-21 16:48:21'),
(316, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-09-21 16:51:16'),
(317, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-21 16:51:25'),
(318, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-09-21 16:54:13'),
(319, 35, 'Carlo Mendoza', 'Driver', 'LOGIN', 'Authentication', 'Carlo Mendoza logged into the system.', '::1', '2026-09-21 16:54:35'),
(320, 35, 'Carlo Mendoza', 'Driver', 'LOGOUT', 'Authentication', 'Carlo Mendoza logged out.', '::1', '2026-09-21 16:55:25'),
(321, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-21 16:55:35'),
(322, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-09-21 17:07:15'),
(323, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-21 17:07:50'),
(324, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-09-21 17:18:47'),
(325, 3, 'Customer1', 'Customer', 'LOGOUT', 'Authentication', 'Customer1 logged out.', '::1', '2026-09-22 20:02:57'),
(326, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-22 20:03:08'),
(327, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-09-22 20:58:39'),
(328, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-22 20:59:28'),
(329, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-09-22 21:06:47'),
(330, 3, 'Customer1', 'Customer', 'LOGIN', 'Authentication', 'Customer1 logged into the system.', '::1', '2026-09-22 21:07:05'),
(331, 3, 'Customer1', 'Customer', 'LOGOUT', 'Authentication', 'Customer1 logged out.', '::1', '2026-09-22 21:08:59'),
(332, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-22 21:09:09'),
(333, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-09-22 21:11:45'),
(334, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-09-22 21:12:23'),
(335, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-09-22 21:14:26'),
(336, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-22 21:14:35'),
(337, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-09-26 00:05:47'),
(338, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-09-26 00:15:24'),
(339, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-26 00:15:34'),
(340, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-09-26 00:20:01'),
(341, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-09-26 00:20:09'),
(342, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-09-26 00:21:25'),
(343, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-26 00:21:34'),
(344, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-09-26 00:29:18'),
(345, 35, 'Carlo Mendoza', 'Driver', 'LOGIN', 'Authentication', 'Carlo Mendoza logged into the system.', '::1', '2026-09-26 00:30:02'),
(346, 35, 'Carlo Mendoza', 'Driver', 'LOGOUT', 'Authentication', 'Carlo Mendoza logged out.', '::1', '2026-09-26 00:30:09'),
(347, 1, 'Mateo Ramos', 'Driver', 'LOGIN', 'Authentication', 'Mateo Ramos logged into the system.', '::1', '2026-09-26 00:30:18'),
(348, 1, 'Mateo Ramos', 'Driver', 'LOGOUT', 'Authentication', 'Mateo Ramos logged out.', '::1', '2026-09-26 00:30:28'),
(349, 35, 'Carlo Mendoza', 'Driver', 'LOGIN', 'Authentication', 'Carlo Mendoza logged into the system.', '::1', '2026-09-26 00:31:03'),
(350, 35, 'Carlo Mendoza', 'Driver', 'LOGOUT', 'Authentication', 'Carlo Mendoza logged out.', '::1', '2026-09-26 00:32:13'),
(351, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-09-26 00:32:35'),
(352, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-09-26 00:37:07'),
(353, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-09-26 00:37:18'),
(354, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-02 12:55:19'),
(355, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-02 13:10:37'),
(356, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-10-02 13:14:09'),
(357, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-10-02 13:16:25'),
(358, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-02 13:16:40'),
(359, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-02 13:28:48'),
(360, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-02 13:30:23'),
(361, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-02 13:35:57'),
(362, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-02 13:36:21'),
(363, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-02 13:37:44'),
(364, 38, 'Francis Dela Cruz', 'Driver', 'LOGIN', 'Authentication', 'Francis Dela Cruz logged into the system.', '::1', '2026-10-02 13:38:00'),
(365, 38, 'Francis Dela Cruz', 'Driver', 'LOGOUT', 'Authentication', 'Francis Dela Cruz logged out.', '::1', '2026-10-02 13:39:20'),
(366, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-02 13:39:31'),
(367, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-02 13:40:48'),
(368, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-02 13:41:29'),
(369, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-02 13:50:12'),
(370, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-02 13:50:17'),
(371, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-02 14:19:09'),
(372, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-02 14:19:23'),
(373, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-02 15:16:38'),
(374, 4, 'Gian Funelas', 'Customer', 'LOGIN', 'Authentication', 'Gian Funelas logged into the system.', '::1', '2026-10-02 19:08:42'),
(375, 4, 'Gian Funelas', 'Customer', 'LOGOUT', 'Authentication', 'Gian Funelas logged out.', '::1', '2026-10-02 19:18:30'),
(376, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-02 19:18:51'),
(377, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-02 19:19:16'),
(378, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-02 19:19:50'),
(379, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-02 19:31:27'),
(380, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-02 19:31:44');
INSERT INTO `audit_log` (`log_id`, `user_id`, `full_name`, `role`, `action`, `module`, `description`, `ip_address`, `created_at`) VALUES
(381, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-02 19:47:07'),
(382, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-02 20:01:23'),
(383, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-02 20:02:16'),
(384, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-02 20:02:25'),
(385, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-02 20:05:12'),
(386, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-02 20:05:20'),
(387, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-02 20:09:15'),
(388, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-02 20:09:22'),
(389, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-02 20:10:45'),
(390, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-02 20:10:55'),
(391, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-03 03:14:45'),
(392, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-03 03:21:13'),
(393, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-10-03 03:21:44'),
(394, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-10-03 03:30:10'),
(395, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-03 03:30:20'),
(396, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-03 03:39:40'),
(397, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-10-03 03:39:52'),
(398, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-10-03 03:49:52'),
(399, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-03 03:50:08'),
(400, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-03 03:58:22'),
(401, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-03 03:58:33'),
(402, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-03 04:18:47'),
(403, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-03 04:18:58'),
(404, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-03 04:19:07'),
(405, 36, 'Daniel Reyes', 'Driver', 'LOGIN', 'Authentication', 'Daniel Reyes logged into the system.', '::1', '2026-10-03 04:19:27'),
(406, 36, 'Daniel Reyes', 'Driver', 'LOGOUT', 'Authentication', 'Daniel Reyes logged out.', '::1', '2026-10-03 04:19:41'),
(407, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-03 04:20:05'),
(408, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-03 04:27:12'),
(409, 36, 'Daniel Reyes', 'Driver', 'LOGIN', 'Authentication', 'Daniel Reyes logged into the system.', '::1', '2026-10-03 04:27:32'),
(410, 36, 'Daniel Reyes', 'Driver', 'LOGOUT', 'Authentication', 'Daniel Reyes logged out.', '::1', '2026-10-03 04:30:03'),
(411, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-03 04:30:10'),
(412, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-03 07:01:28'),
(413, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-03 07:04:28'),
(414, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-03 07:04:36'),
(415, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-03 07:05:59'),
(416, 1, 'Mateo Ramos', 'Driver', 'LOGIN', 'Authentication', 'Mateo Ramos logged into the system.', '::1', '2026-10-03 07:06:19'),
(417, 1, 'Mateo Ramos', 'Driver', 'LOGOUT', 'Authentication', 'Mateo Ramos logged out.', '::1', '2026-10-03 07:07:07'),
(418, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-03 07:07:15'),
(419, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-03 07:09:00'),
(420, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-03 07:09:06'),
(421, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-03 07:10:13'),
(422, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-03 07:10:21'),
(423, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-03 07:10:47'),
(424, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-03 07:10:56'),
(425, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-03 07:13:03'),
(426, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-03 07:13:10'),
(427, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-03 07:13:51'),
(428, 39, 'Gabriel Flores', 'Driver', 'LOGIN', 'Authentication', 'Gabriel Flores logged into the system.', '::1', '2026-10-03 07:14:48'),
(429, 39, 'Gabriel Flores', 'Driver', 'LOGOUT', 'Authentication', 'Gabriel Flores logged out.', '::1', '2026-10-03 07:15:19'),
(430, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-03 07:15:24'),
(431, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-03 07:16:01'),
(432, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-03 07:16:34'),
(433, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-03 08:38:33'),
(434, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-03 08:38:58'),
(435, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-03 08:40:41'),
(436, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-03 08:40:49'),
(437, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-03 08:42:07'),
(438, 37, 'Eduardo Garcia', 'Driver', 'LOGIN', 'Authentication', 'Eduardo Garcia logged into the system.', '::1', '2026-10-03 08:43:53'),
(439, 37, 'Eduardo Garcia', 'Driver', 'LOGOUT', 'Authentication', 'Eduardo Garcia logged out.', '::1', '2026-10-03 08:44:55'),
(440, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-03 08:45:05'),
(441, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-03 08:45:28'),
(442, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-03 08:45:36'),
(443, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-03 08:46:19'),
(444, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-10-03 08:53:06'),
(445, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-10-03 08:56:16'),
(446, 37, 'Eduardo Garcia', 'Driver', 'LOGIN', 'Authentication', 'Eduardo Garcia logged into the system.', '::1', '2026-10-03 08:57:14'),
(447, 37, 'Eduardo Garcia', 'Driver', 'LOGOUT', 'Authentication', 'Eduardo Garcia logged out.', '::1', '2026-10-03 08:59:39'),
(448, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-03 08:59:48'),
(449, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-03 09:00:05'),
(450, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-10-03 09:01:00'),
(451, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-10-03 09:11:42'),
(452, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-03 09:11:53'),
(453, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-03 09:12:03'),
(454, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-03 09:12:11'),
(455, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-03 09:13:17'),
(456, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-03 09:13:28'),
(457, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-03 09:17:49'),
(458, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-10-03 09:18:04'),
(459, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-10-03 09:19:14'),
(460, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-03 09:19:28'),
(461, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-03 09:20:21'),
(462, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-03 09:20:36'),
(463, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-03 09:20:48'),
(464, 2, 'Marianne Ibanez', 'Customer', 'LOGIN', 'Authentication', 'Marianne Ibanez logged into the system.', '::1', '2026-10-03 09:21:23'),
(465, 2, 'Marianne Ibanez', 'Customer', 'LOGOUT', 'Authentication', 'Marianne Ibanez logged out.', '::1', '2026-10-03 09:22:29'),
(466, 5, 'Allyssa Grace Camandona', 'Customer', 'LOGIN', 'Authentication', 'Allyssa Grace Camandona logged into the system.', '::1', '2026-10-03 09:24:38'),
(467, 5, 'Allyssa Grace Camandona', 'Customer', 'LOGOUT', 'Authentication', 'Allyssa Grace Camandona logged out.', '::1', '2026-10-03 09:26:20'),
(468, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-03 09:26:31'),
(469, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-10-04 19:08:43'),
(470, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-10-04 20:24:54'),
(471, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-04 20:25:00'),
(472, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-04 20:25:45'),
(473, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-04 20:26:03'),
(474, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-04 21:26:40'),
(475, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-04 21:26:50'),
(476, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-04 21:27:23'),
(477, 45, 'Marco Villanueva', 'Driver', 'LOGIN', 'Authentication', 'Marco Villanueva logged into the system.', '::1', '2026-10-04 21:27:34'),
(478, 45, 'Marco Villanueva', 'Driver', 'LOGOUT', 'Authentication', 'Marco Villanueva logged out.', '::1', '2026-10-04 21:38:03'),
(479, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-04 21:38:12'),
(480, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-13 23:58:49'),
(481, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-14 00:32:46'),
(482, 1, 'Danica Jane Rivera', 'Customer', 'LOGIN', 'Authentication', 'Danica Jane Rivera logged into the system.', '::1', '2026-10-14 00:33:14'),
(483, 1, 'Danica Jane Rivera', 'Customer', 'LOGOUT', 'Authentication', 'Danica Jane Rivera logged out.', '::1', '2026-10-14 01:20:17'),
(484, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-14 01:20:33'),
(485, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-14 01:49:41'),
(486, 2, 'Jose Reyes', 'Admin', 'LOGIN', 'Authentication', 'Jose Reyes logged into the system.', '::1', '2026-10-14 01:49:55'),
(487, 2, 'Jose Reyes', 'Admin', 'LOGOUT', 'Authentication', 'Jose Reyes logged out.', '::1', '2026-10-14 01:50:52'),
(488, 42, 'Jerome Navarro', 'Driver', 'LOGIN', 'Authentication', 'Jerome Navarro logged into the system.', '::1', '2026-10-14 01:51:02'),
(489, 42, 'Jerome Navarro', 'Driver', 'LOGOUT', 'Authentication', 'Jerome Navarro logged out.', '::1', '2026-10-14 01:53:06'),
(490, 42, 'Jerome Navarro', 'Driver', 'LOGIN', 'Authentication', 'Jerome Navarro logged into the system.', '::1', '2026-10-14 01:54:54'),
(491, 42, 'Jerome Navarro', 'Driver', 'LOGOUT', 'Authentication', 'Jerome Navarro logged out.', '::1', '2026-10-14 02:13:53'),
(492, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-10-14 02:14:05'),
(493, 1, 'Super Administrator', 'Super Admin', 'LOGOUT', 'Authentication', 'Super Administrator logged out.', '::1', '2026-10-14 02:14:12'),
(494, 1, 'Super Administrator', 'Super Admin', 'LOGIN', 'Authentication', 'Super Administrator logged into the system.', '::1', '2026-10-14 02:16:32');

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `customer_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_verified_at` datetime DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `phone_verified` tinyint(1) NOT NULL DEFAULT 0,
  `phone_verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`customer_id`, `full_name`, `email`, `email_verified`, `email_verified_at`, `password_hash`, `phone_number`, `address`, `city`, `province`, `created_at`, `latitude`, `longitude`, `phone_verified`, `phone_verified_at`) VALUES
(1, 'Danica Jane Rivera', 'danicajanerivera02@gmail.com', 0, NULL, '$2y$10$IKVa2/xQWcbfWYHl1aSeTejFqMMHYjzZf0Z1Oz1a5LuA0n6LDMDUe', '0912345679', '17 Bautista Street', 'Malabon', 'Metro Manila', '2026-08-02 20:49:36', NULL, NULL, 0, NULL),
(2, 'Marianne Ibanez', 'marianne@gmail.com', 0, NULL, '$2y$10$9MVGGwF2g/uLk49Oq.UbxOKS6BsN7l8sExhHlJVrIfGsqloL/UpUa', '09943957889', '454 GRC Building, Rizal Avenue Extension, corner 9th Avenue, East Grace Park, Caloocan City', 'Caloocan City', 'Metro Manila', '2026-08-26 17:55:00', NULL, NULL, 1, '2026-09-20 20:04:03'),
(3, 'Customer1', 'nicspark02@gmail.com', 0, NULL, '$2y$10$UK3QDr20vYeIWa64EeUhk.QVi72dn4THAYjqAVMMf9.QnRAxEMETS', '09944673432', '17 Macanas', 'Malabon', 'Panghulo', '2026-09-22 20:01:03', NULL, NULL, 0, NULL),
(4, 'Gian Funelas', 'gianfunelas26@gmail.com', 0, NULL, '$2y$10$35J0LIp/tAYmdBOdOypdAOjiTaFahLNPfhmEiWskl0lQNP/vtro46', '09944673432', '14 Macanas St. Panghulo Malabon City', 'Malabon', 'Metro Manila', '2026-10-02 19:08:28', NULL, NULL, 1, '2026-10-02 19:13:31'),
(5, 'Allyssa Grace Camandona', 'allyssagracecamandona@gmail.com', 0, NULL, '$2y$10$7M5.65I.cud/voKho3XUneSptPTc3BO25/UYANK0c7IWs3UDJKYEy', '09266937896', '11 F linum street Del Nacia IV Novaliches Quezon City', 'Quezon City', 'Metro Manila', '2026-10-03 09:24:21', NULL, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customer_address`
--

CREATE TABLE `customer_address` (
  `address_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `label` varchar(50) NOT NULL DEFAULT 'Home',
  `full_address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `contact_person` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_incident`
--

CREATE TABLE `delivery_incident` (
  `incident_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `truck_id` int(11) DEFAULT NULL,
  `incident_type` enum('Vehicle Breakdown','Traffic/Road Issue','Customer Unavailable','Weather','Wrong/Incomplete Address','Loading/Warehouse Delay','Driver-related Issue','Accident/Emergency','Delivery Interrupted','Others') NOT NULL,
  `incident_category` enum('Operational','External','Customer-related','Driver-related') NOT NULL,
  `description` text NOT NULL,
  `estimated_delay_minutes` int(11) NOT NULL DEFAULT 0,
  `evidence_path` varchar(255) DEFAULT NULL,
  `reported_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `violation_status` enum('No Violation','For Review','Verified Violation') NOT NULL DEFAULT 'For Review',
  `violation_type` varchar(120) DEFAULT NULL,
  `review_status` enum('Pending Review','Reviewed','Resolved') NOT NULL DEFAULT 'Pending Review',
  `reviewed_by` int(11) DEFAULT NULL,
  `admin_remarks` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_incident`
--

INSERT INTO `delivery_incident` (`incident_id`, `order_id`, `driver_id`, `truck_id`, `incident_type`, `incident_category`, `description`, `estimated_delay_minutes`, `evidence_path`, `reported_at`, `resolved_at`, `latitude`, `longitude`, `violation_status`, `violation_type`, `review_status`, `reviewed_by`, `admin_remarks`, `reviewed_at`) VALUES
(1, 147, 45, 43, 'Vehicle Breakdown', 'Operational', 'Nabangga sa poste', 120, 'assets/uploads/incidents/incident_147_6e877601f4b0.png', '2026-10-04 21:37:53', '2026-10-04 21:40:40', 14.68719471, 120.95472565, 'No Violation', 'No Violation', 'Resolved', 2, NULL, '2026-10-04 21:40:40');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_location`
--

CREATE TABLE `delivery_location` (
  `location_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `full_address` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `latitude` decimal(10,6) DEFAULT NULL,
  `longitude` decimal(10,6) DEFAULT NULL,
  `contact_person` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_location`
--

INSERT INTO `delivery_location` (`location_id`, `order_id`, `full_address`, `city`, `province`, `latitude`, `longitude`, `contact_person`, `contact_number`) VALUES
(1, 1, '17 Bautista Street', 'Malabon', 'Metro Manila', NULL, NULL, 'Danica Jane Rivera', '0912345679'),
(2, 3, '17 Macanas St. Panghulo Malabon City', 'Malabon', 'Metro Manila', 14.687451, 120.954487, 'Danica Jane Rivera', '0912345679'),
(3, 4, '14 Macanas St. Panghulo Malabon City', 'Malabon', 'Metro Manila', 14.687178, 120.954765, 'Danica Jane Rivera', '0912345679'),
(4, 5, 'SM City Grand Central, Rizal Avenue Extension, Barangay 88, Zone 8, Grace Park East, District 2, Caloocan, Northern Manila District, Metro Manila, 1403, Philippines', 'Caloocan', 'Metro Manila', 14.654967, 120.984745, 'Marianne Ibanez', '09943957889'),
(5, 6, 'Panghulo, Malabon, Northern Manila District, Metro Manila, 1478, Philippines', 'Malabon', 'Metro Manila', 14.687587, 120.953838, 'Marianne Ibanez', '09943957889'),
(6, 7, 'E. Espiritu Street, Karisma Village, Panghulo, Malabon, Northern Manila District, Metro Manila, 1478, Philippines', 'Malabon', 'Metro Manila', 14.687484, 120.953797, 'Marianne Ibanez', '09943957889'),
(7, 8, 'SM Center Sangandaan, Samson Road, Barangay 3, Zone 1, Sangandaan, District 1, Caloocan, Northern Manila District, Metro Manila, 1400, Philippines', 'Caloocan', 'Metro Manila', 14.658315, 120.971918, 'Marianne Ibanez', '09943957889'),
(8, 9, 'Macanas Street, Karisma Village', 'Malabon', 'Metro Manila', 14.687452, 120.954487, 'Marianne Ibanez', '09943957889'),
(9, 130, '106, M. H. del Pilar Street, Grace Park East', 'Caloocan', 'Metro Manila', 14.649995, 120.984460, 'Marianne Ibanez', '09943957889'),
(10, 131, 'Samson Road, Sangandaan', 'Caloocan', 'Metro Manila', 14.657840, 120.972263, 'Marianne Ibanez', '09943957889'),
(11, 132, 'SM Center Sangandaan, Samson Road, Barangay 3, Zone 1, Sangandaan, District 1, Caloocan, Northern Manila District, Metro Manila, 1400, Philippines', 'Caloocan', 'Metro Manila', 14.658315, 120.971918, 'Marianne Ibanez', '09943957889'),
(12, 133, 'SM Center Sangandaan, Samson Road, Barangay 3, Zone 1, Sangandaan, District 1, Caloocan, Northern Manila District, Metro Manila, 1400, Philippines', 'Caloocan', 'Metro Manila', 14.658315, 120.971918, 'Marianne Ibanez', '09943957889'),
(13, 134, 'Macanas Street, Karisma Village, Panghulo, Malabon, Northern Manila District, Metro Manila, 1478, Philippines', 'Malabon', 'Metro Manila', 14.687452, 120.954487, 'Customer1', '09944673432'),
(14, 135, 'Macanas Street, Karisma Village, Panghulo, Malabon, Northern Manila District, Metro Manila, 1478, Philippines', 'Malabon', 'Metro Manila', 14.687452, 120.954487, 'Danica Jane Rivera', '0912345679'),
(15, 136, 'Linum Street, Del Nacia Ville Ⅳ, Sauyo, 6th District, Quezon City, Eastern Manila District, Metro Manila, 1116, Philippines', 'Quezon City', 'Metro Manila', 14.692229, 121.043865, 'Danica Jane Rivera', '0912345679'),
(16, 137, 'SM Center Sangandaan, Samson Road, Barangay 3, Zone 1, Sangandaan, District 1, Caloocan, Northern Manila District, Metro Manila, 1400, Philippines', 'Caloocan', 'Metro Manila', 14.658315, 120.971918, 'Danica Jane Rivera', '0912345679'),
(17, 138, 'SM Center Sangandaan, Samson Road, Barangay 3, Zone 1, Sangandaan, District 1, Caloocan, Northern Manila District, Metro Manila, 1400, Philippines', 'Caloocan', 'Metro Manila', 14.658315, 120.971918, 'Danica Jane Rivera', '0912345679'),
(18, 139, 'SM Center Sangandaan, Samson Road, Barangay 3, Zone 1, Sangandaan, District 1, Caloocan, Northern Manila District, Metro Manila, 1400, Philippines', 'Caloocan', 'Metro Manila', 14.658315, 120.971918, 'Danica Jane Rivera', '0912345679'),
(19, 140, 'P. Aquino Avenue, Balut, Tonsuya, Malabon, Northern Manila District, Metro Manila, 1479, Philippines', 'Malabon', 'Metro Manila', 14.660142, 120.957935, 'Danica Jane Rivera', '0912345679'),
(20, 141, 'Cavite City, Cavite, Calabarzon, 4100, Philippines', 'Cavite City', 'Cavite', 14.482092, 120.908919, 'Danica Jane Rivera', '0912345679'),
(21, 142, 'SM Center Sangandaan, Samson Road, Barangay 3, Zone 1, Sangandaan, District 1, Caloocan, Northern Manila District, Metro Manila, 1400, Philippines', 'Caloocan', 'Metro Manila', 14.658315, 120.971918, 'Danica Jane Rivera', '0912345679'),
(22, 143, 'SM Center Sangandaan, Samson Road, Barangay 3, Zone 1, Sangandaan, District 1, Caloocan, Northern Manila District, Metro Manila, 1400, Philippines', 'Caloocan', 'Metro Manila', 14.658315, 120.971918, 'Danica Jane Rivera', '0912345679'),
(23, 144, 'SM Center Sangandaan, Samson Road, Barangay 3, Zone 1, Sangandaan, District 1, Caloocan, Northern Manila District, Metro Manila, 1400, Philippines', 'Caloocan', 'Metro Manila', 14.658315, 120.971918, 'Danica Jane Rivera', '0912345679'),
(24, 145, 'P. Aquino Avenue, Paradise Village, Tonsuya, Malabon, Northern Manila District, Metro Manila, 1411, Philippines', 'Malabon', 'Metro Manila', 14.659600, 120.961251, 'Marianne Ibanez', '09943957889'),
(25, 146, 'Macanas Street, Karisma Village, Panghulo, Malabon, Northern Manila District, Metro Manila, 1478, Philippines', 'Malabon', 'Metro Manila', 14.687452, 120.954487, 'Allyssa Grace Camandona', '09266937896'),
(26, 147, 'Macanas Street, Karisma Village, Panghulo, Malabon, Northern Manila District, Metro Manila, 1478, Philippines', 'Malabon', 'Metro Manila', 14.687452, 120.954487, 'Danica Jane Rivera', '0912345679'),
(27, 148, 'Ignacio Street, Karisma Village, Panghulo, Malabon, Northern Manila District, Metro Manila, 1478, Philippines', 'Malabon', 'Metro Manila', 14.686394, 120.955267, 'Danica Jane Rivera', '0912345679');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_status`
--

CREATE TABLE `delivery_status` (
  `status_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` enum('Pending','Assigned','In Transit','Arrived','Delivered','Failed','Cancelled') NOT NULL,
  `latitude` decimal(10,6) DEFAULT NULL,
  `longitude` decimal(10,6) DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_status`
--

INSERT INTO `delivery_status` (`status_id`, `order_id`, `status`, `latitude`, `longitude`, `timestamp`, `notes`) VALUES
(1, 1, 'Pending', NULL, NULL, '2026-08-09 22:15:58', 'Order placed by customer, awaiting admin approval.'),
(2, 3, 'Pending', NULL, NULL, '2026-08-14 13:12:22', 'Order placed by customer, awaiting admin approval.'),
(3, 3, 'Assigned', NULL, NULL, '2026-08-18 13:14:04', 'Delivery assigned by admin.'),
(4, 3, 'Assigned', NULL, NULL, '2026-08-18 13:24:34', 'Driver accepted the assigned delivery.'),
(5, 3, 'In Transit', 14.687192, 120.954708, '2026-08-18 13:24:45', 'Delivery started by driver.'),
(6, 3, 'Delivered', 14.687189, 120.954728, '2026-08-18 13:25:57', 'Delivery completed by driver.'),
(7, 4, 'Pending', NULL, NULL, '2026-08-26 17:24:34', 'Order placed by customer, awaiting admin approval.'),
(8, 4, 'Assigned', NULL, NULL, '2026-08-26 17:48:06', 'Delivery assigned by admin.'),
(9, 4, 'Assigned', NULL, NULL, '2026-08-26 17:50:39', 'Driver accepted the assigned delivery.'),
(10, 5, 'Pending', NULL, NULL, '2026-08-26 17:57:10', 'Order placed by customer, awaiting admin approval.'),
(11, 5, 'Assigned', NULL, NULL, '2026-08-26 17:58:05', 'Delivery assigned by admin.'),
(12, 5, 'Assigned', NULL, NULL, '2026-08-26 18:04:14', 'Driver accepted the assigned delivery.'),
(13, 5, 'In Transit', 14.687385, 120.954915, '2026-08-26 18:04:46', 'Delivery started by driver.'),
(14, 5, 'Delivered', 14.687341, 120.954810, '2026-08-26 18:09:05', 'Delivery completed by driver.'),
(15, 1, 'Assigned', NULL, NULL, '2026-08-26 18:59:11', 'Driver accepted the assigned delivery.'),
(16, 1, 'In Transit', 14.687328, 120.954776, '2026-08-26 18:59:14', 'Delivery started by driver.'),
(17, 1, 'Delivered', 14.687328, 120.954776, '2026-08-26 18:59:16', 'Delivery completed by driver.'),
(18, 6, 'Pending', NULL, NULL, '2026-08-26 19:14:46', 'Order placed by customer, awaiting admin approval.'),
(19, 4, 'In Transit', 14.687282, 120.954657, '2026-08-26 19:20:03', 'Delivery started by driver.'),
(20, 4, 'Delivered', 14.687282, 120.954657, '2026-08-26 19:20:42', 'Delivery completed by driver.'),
(21, 2, 'Assigned', NULL, NULL, '2026-08-26 19:49:47', 'Delivery assigned by admin.'),
(22, 7, 'Pending', NULL, NULL, '2026-08-26 19:58:47', 'Order placed by customer, awaiting admin approval.'),
(23, 8, 'Pending', NULL, NULL, '2026-09-09 04:37:29', 'Order placed by customer, awaiting admin approval.'),
(24, 9, 'Pending', NULL, NULL, '2026-09-09 16:45:46', 'Order placed by customer. Waiting for admin approval.'),
(25, 9, 'Assigned', NULL, NULL, '2026-09-09 16:47:26', 'Delivery assigned by admin.'),
(26, 10, 'Delivered', NULL, NULL, '2025-11-27 00:00:00', 'Demo historical delivery used for ML training.'),
(27, 11, 'Delivered', NULL, NULL, '2026-01-14 00:00:00', 'Demo historical delivery used for ML training.'),
(28, 12, 'Delivered', NULL, NULL, '2025-03-15 00:00:00', 'Demo historical delivery used for ML training.'),
(29, 13, 'Delivered', NULL, NULL, '2025-11-27 00:00:00', 'Demo historical delivery used for ML training.'),
(30, 14, 'Delivered', NULL, NULL, '2025-04-02 00:00:00', 'Demo historical delivery used for ML training.'),
(31, 15, 'Delivered', NULL, NULL, '2025-02-21 00:00:00', 'Demo historical delivery used for ML training.'),
(32, 16, 'Delivered', NULL, NULL, '2024-10-13 00:00:00', 'Demo historical delivery used for ML training.'),
(33, 17, 'Delivered', NULL, NULL, '2024-11-20 00:00:00', 'Demo historical delivery used for ML training.'),
(34, 18, 'Delivered', NULL, NULL, '2024-10-11 00:00:00', 'Demo historical delivery used for ML training.'),
(35, 19, 'Delivered', NULL, NULL, '2025-08-25 00:00:00', 'Demo historical delivery used for ML training.'),
(36, 20, 'Delivered', NULL, NULL, '2025-10-15 00:00:00', 'Demo historical delivery used for ML training.'),
(37, 21, 'Delivered', NULL, NULL, '2025-12-10 00:00:00', 'Demo historical delivery used for ML training.'),
(38, 22, 'Delivered', NULL, NULL, '2026-08-10 00:00:00', 'Demo historical delivery used for ML training.'),
(39, 23, 'Delivered', NULL, NULL, '2026-08-03 00:00:00', 'Demo historical delivery used for ML training.'),
(40, 24, 'Delivered', NULL, NULL, '2024-11-27 00:00:00', 'Demo historical delivery used for ML training.'),
(41, 25, 'Delivered', NULL, NULL, '2026-01-25 00:00:00', 'Demo historical delivery used for ML training.'),
(42, 26, 'Delivered', NULL, NULL, '2025-03-30 00:00:00', 'Demo historical delivery used for ML training.'),
(43, 27, 'Delivered', NULL, NULL, '2026-06-22 00:00:00', 'Demo historical delivery used for ML training.'),
(44, 28, 'Delivered', NULL, NULL, '2025-09-18 00:00:00', 'Demo historical delivery used for ML training.'),
(45, 29, 'Delivered', NULL, NULL, '2025-12-14 00:00:00', 'Demo historical delivery used for ML training.'),
(46, 30, 'Delivered', NULL, NULL, '2025-12-02 00:00:00', 'Demo historical delivery used for ML training.'),
(47, 31, 'Delivered', NULL, NULL, '2025-07-23 00:00:00', 'Demo historical delivery used for ML training.'),
(48, 32, 'Delivered', NULL, NULL, '2025-10-30 00:00:00', 'Demo historical delivery used for ML training.'),
(49, 33, 'Delivered', NULL, NULL, '2025-01-10 00:00:00', 'Demo historical delivery used for ML training.'),
(50, 34, 'Delivered', NULL, NULL, '2025-05-22 00:00:00', 'Demo historical delivery used for ML training.'),
(51, 35, 'Delivered', NULL, NULL, '2025-11-02 00:00:00', 'Demo historical delivery used for ML training.'),
(52, 36, 'Delivered', NULL, NULL, '2026-04-05 00:00:00', 'Demo historical delivery used for ML training.'),
(53, 37, 'Delivered', NULL, NULL, '2026-07-08 00:00:00', 'Demo historical delivery used for ML training.'),
(54, 38, 'Delivered', NULL, NULL, '2026-03-31 00:00:00', 'Demo historical delivery used for ML training.'),
(55, 39, 'Delivered', NULL, NULL, '2025-12-31 00:00:00', 'Demo historical delivery used for ML training.'),
(56, 40, 'Delivered', NULL, NULL, '2025-01-27 00:00:00', 'Demo historical delivery used for ML training.'),
(57, 41, 'Delivered', NULL, NULL, '2025-07-18 00:00:00', 'Demo historical delivery used for ML training.'),
(58, 42, 'Delivered', NULL, NULL, '2025-07-03 00:00:00', 'Demo historical delivery used for ML training.'),
(59, 43, 'Delivered', NULL, NULL, '2026-03-01 00:00:00', 'Demo historical delivery used for ML training.'),
(60, 44, 'Delivered', NULL, NULL, '2025-04-25 00:00:00', 'Demo historical delivery used for ML training.'),
(61, 45, 'Delivered', NULL, NULL, '2026-01-09 00:00:00', 'Demo historical delivery used for ML training.'),
(62, 46, 'Delivered', NULL, NULL, '2025-06-24 00:00:00', 'Demo historical delivery used for ML training.'),
(63, 47, 'Delivered', NULL, NULL, '2026-06-15 00:00:00', 'Demo historical delivery used for ML training.'),
(64, 48, 'Delivered', NULL, NULL, '2025-07-17 00:00:00', 'Demo historical delivery used for ML training.'),
(65, 49, 'Delivered', NULL, NULL, '2026-04-11 00:00:00', 'Demo historical delivery used for ML training.'),
(66, 50, 'Delivered', NULL, NULL, '2024-11-15 00:00:00', 'Demo historical delivery used for ML training.'),
(67, 51, 'Delivered', NULL, NULL, '2025-01-15 00:00:00', 'Demo historical delivery used for ML training.'),
(68, 52, 'Delivered', NULL, NULL, '2025-01-28 00:00:00', 'Demo historical delivery used for ML training.'),
(69, 53, 'Delivered', NULL, NULL, '2026-06-17 00:00:00', 'Demo historical delivery used for ML training.'),
(70, 54, 'Delivered', NULL, NULL, '2025-12-13 00:00:00', 'Demo historical delivery used for ML training.'),
(71, 55, 'Delivered', NULL, NULL, '2025-09-17 00:00:00', 'Demo historical delivery used for ML training.'),
(72, 56, 'Delivered', NULL, NULL, '2025-02-01 00:00:00', 'Demo historical delivery used for ML training.'),
(73, 57, 'Delivered', NULL, NULL, '2025-06-12 00:00:00', 'Demo historical delivery used for ML training.'),
(74, 58, 'Delivered', NULL, NULL, '2026-02-04 00:00:00', 'Demo historical delivery used for ML training.'),
(75, 59, 'Delivered', NULL, NULL, '2026-01-03 00:00:00', 'Demo historical delivery used for ML training.'),
(76, 60, 'Delivered', NULL, NULL, '2025-04-05 00:00:00', 'Demo historical delivery used for ML training.'),
(77, 61, 'Delivered', NULL, NULL, '2026-03-26 00:00:00', 'Demo historical delivery used for ML training.'),
(78, 62, 'Delivered', NULL, NULL, '2025-12-12 00:00:00', 'Demo historical delivery used for ML training.'),
(79, 63, 'Delivered', NULL, NULL, '2025-09-04 00:00:00', 'Demo historical delivery used for ML training.'),
(80, 64, 'Delivered', NULL, NULL, '2025-02-12 00:00:00', 'Demo historical delivery used for ML training.'),
(81, 65, 'Delivered', NULL, NULL, '2024-12-28 00:00:00', 'Demo historical delivery used for ML training.'),
(82, 66, 'Delivered', NULL, NULL, '2026-04-06 00:00:00', 'Demo historical delivery used for ML training.'),
(83, 67, 'Delivered', NULL, NULL, '2024-11-21 00:00:00', 'Demo historical delivery used for ML training.'),
(84, 68, 'Delivered', NULL, NULL, '2025-08-21 00:00:00', 'Demo historical delivery used for ML training.'),
(85, 69, 'Delivered', NULL, NULL, '2024-10-17 00:00:00', 'Demo historical delivery used for ML training.'),
(86, 70, 'Delivered', NULL, NULL, '2026-04-09 00:00:00', 'Demo historical delivery used for ML training.'),
(87, 71, 'Delivered', NULL, NULL, '2024-12-07 00:00:00', 'Demo historical delivery used for ML training.'),
(88, 72, 'Delivered', NULL, NULL, '2024-11-01 00:00:00', 'Demo historical delivery used for ML training.'),
(89, 73, 'Delivered', NULL, NULL, '2025-04-18 00:00:00', 'Demo historical delivery used for ML training.'),
(90, 74, 'Delivered', NULL, NULL, '2026-01-07 00:00:00', 'Demo historical delivery used for ML training.'),
(91, 75, 'Delivered', NULL, NULL, '2024-11-09 00:00:00', 'Demo historical delivery used for ML training.'),
(92, 76, 'Delivered', NULL, NULL, '2026-01-02 00:00:00', 'Demo historical delivery used for ML training.'),
(93, 77, 'Delivered', NULL, NULL, '2024-10-27 00:00:00', 'Demo historical delivery used for ML training.'),
(94, 78, 'Delivered', NULL, NULL, '2025-03-23 00:00:00', 'Demo historical delivery used for ML training.'),
(95, 79, 'Delivered', NULL, NULL, '2024-11-12 00:00:00', 'Demo historical delivery used for ML training.'),
(96, 80, 'Delivered', NULL, NULL, '2025-01-08 00:00:00', 'Demo historical delivery used for ML training.'),
(97, 81, 'Delivered', NULL, NULL, '2026-01-31 00:00:00', 'Demo historical delivery used for ML training.'),
(98, 82, 'Delivered', NULL, NULL, '2026-07-16 00:00:00', 'Demo historical delivery used for ML training.'),
(99, 83, 'Delivered', NULL, NULL, '2025-03-10 00:00:00', 'Demo historical delivery used for ML training.'),
(100, 84, 'Delivered', NULL, NULL, '2026-01-04 00:00:00', 'Demo historical delivery used for ML training.'),
(101, 85, 'Delivered', NULL, NULL, '2026-05-03 00:00:00', 'Demo historical delivery used for ML training.'),
(102, 86, 'Delivered', NULL, NULL, '2026-01-08 00:00:00', 'Demo historical delivery used for ML training.'),
(103, 87, 'Delivered', NULL, NULL, '2025-08-27 00:00:00', 'Demo historical delivery used for ML training.'),
(104, 88, 'Delivered', NULL, NULL, '2026-03-31 00:00:00', 'Demo historical delivery used for ML training.'),
(105, 89, 'Delivered', NULL, NULL, '2026-07-28 00:00:00', 'Demo historical delivery used for ML training.'),
(106, 90, 'Delivered', NULL, NULL, '2025-01-03 00:00:00', 'Demo historical delivery used for ML training.'),
(107, 91, 'Delivered', NULL, NULL, '2025-05-28 00:00:00', 'Demo historical delivery used for ML training.'),
(108, 92, 'Delivered', NULL, NULL, '2026-07-03 00:00:00', 'Demo historical delivery used for ML training.'),
(109, 93, 'Delivered', NULL, NULL, '2024-10-20 00:00:00', 'Demo historical delivery used for ML training.'),
(110, 94, 'Delivered', NULL, NULL, '2026-05-31 00:00:00', 'Demo historical delivery used for ML training.'),
(111, 95, 'Delivered', NULL, NULL, '2026-06-21 00:00:00', 'Demo historical delivery used for ML training.'),
(112, 96, 'Delivered', NULL, NULL, '2025-12-16 00:00:00', 'Demo historical delivery used for ML training.'),
(113, 97, 'Delivered', NULL, NULL, '2025-02-11 00:00:00', 'Demo historical delivery used for ML training.'),
(114, 98, 'Delivered', NULL, NULL, '2024-12-29 00:00:00', 'Demo historical delivery used for ML training.'),
(115, 99, 'Delivered', NULL, NULL, '2024-11-06 00:00:00', 'Demo historical delivery used for ML training.'),
(116, 100, 'Delivered', NULL, NULL, '2025-08-19 00:00:00', 'Demo historical delivery used for ML training.'),
(117, 101, 'Delivered', NULL, NULL, '2026-07-31 00:00:00', 'Demo historical delivery used for ML training.'),
(118, 102, 'Delivered', NULL, NULL, '2026-07-23 00:00:00', 'Demo historical delivery used for ML training.'),
(119, 103, 'Delivered', NULL, NULL, '2025-09-12 00:00:00', 'Demo historical delivery used for ML training.'),
(120, 104, 'Delivered', NULL, NULL, '2026-06-22 00:00:00', 'Demo historical delivery used for ML training.'),
(121, 105, 'Delivered', NULL, NULL, '2026-03-15 00:00:00', 'Demo historical delivery used for ML training.'),
(122, 106, 'Delivered', NULL, NULL, '2025-09-17 00:00:00', 'Demo historical delivery used for ML training.'),
(123, 107, 'Delivered', NULL, NULL, '2025-12-12 00:00:00', 'Demo historical delivery used for ML training.'),
(124, 108, 'Delivered', NULL, NULL, '2026-01-22 00:00:00', 'Demo historical delivery used for ML training.'),
(125, 109, 'Delivered', NULL, NULL, '2025-03-30 00:00:00', 'Demo historical delivery used for ML training.'),
(126, 110, 'Delivered', NULL, NULL, '2026-07-21 00:00:00', 'Demo historical delivery used for ML training.'),
(127, 111, 'Delivered', NULL, NULL, '2025-03-30 00:00:00', 'Demo historical delivery used for ML training.'),
(128, 112, 'Delivered', NULL, NULL, '2025-12-03 00:00:00', 'Demo historical delivery used for ML training.'),
(129, 113, 'Delivered', NULL, NULL, '2025-09-19 00:00:00', 'Demo historical delivery used for ML training.'),
(130, 114, 'Delivered', NULL, NULL, '2025-02-01 00:00:00', 'Demo historical delivery used for ML training.'),
(131, 115, 'Delivered', NULL, NULL, '2025-05-28 00:00:00', 'Demo historical delivery used for ML training.'),
(132, 116, 'Delivered', NULL, NULL, '2026-02-10 00:00:00', 'Demo historical delivery used for ML training.'),
(133, 117, 'Delivered', NULL, NULL, '2025-10-08 00:00:00', 'Demo historical delivery used for ML training.'),
(134, 118, 'Delivered', NULL, NULL, '2026-06-08 00:00:00', 'Demo historical delivery used for ML training.'),
(135, 119, 'Delivered', NULL, NULL, '2024-11-01 00:00:00', 'Demo historical delivery used for ML training.'),
(136, 120, 'Delivered', NULL, NULL, '2025-08-13 00:00:00', 'Demo historical delivery used for ML training.'),
(137, 121, 'Delivered', NULL, NULL, '2026-07-06 00:00:00', 'Demo historical delivery used for ML training.'),
(138, 122, 'Delivered', NULL, NULL, '2026-07-09 00:00:00', 'Demo historical delivery used for ML training.'),
(139, 123, 'Delivered', NULL, NULL, '2025-04-09 00:00:00', 'Demo historical delivery used for ML training.'),
(140, 124, 'Delivered', NULL, NULL, '2026-02-09 00:00:00', 'Demo historical delivery used for ML training.'),
(141, 125, 'Delivered', NULL, NULL, '2025-10-26 00:00:00', 'Demo historical delivery used for ML training.'),
(142, 126, 'Delivered', NULL, NULL, '2026-05-14 00:00:00', 'Demo historical delivery used for ML training.'),
(143, 127, 'Delivered', NULL, NULL, '2025-07-12 00:00:00', 'Demo historical delivery used for ML training.'),
(144, 128, 'Delivered', NULL, NULL, '2025-03-15 00:00:00', 'Demo historical delivery used for ML training.'),
(145, 129, 'Delivered', NULL, NULL, '2024-12-17 00:00:00', 'Demo historical delivery used for ML training.'),
(146, 130, 'Pending', NULL, NULL, '2026-09-09 18:56:51', 'Order placed by customer. Waiting for admin approval.'),
(147, 131, 'Pending', NULL, NULL, '2026-09-20 15:04:10', 'Order placed by customer. Waiting for admin approval.'),
(148, 132, 'Pending', NULL, NULL, '2026-09-20 18:08:40', 'Urgent order placed by customer. Waiting for admin approval.'),
(149, 132, 'Assigned', NULL, NULL, '2026-09-20 18:41:04', 'Delivery assigned by admin. ML predicted TAT: 27 minutes.'),
(150, 132, 'Assigned', NULL, NULL, '2026-09-21 16:29:22', 'Driver accepted the assigned delivery.'),
(151, 132, 'In Transit', 14.687195, 120.954727, '2026-09-21 16:29:30', 'Delivery started by driver.'),
(152, 133, 'Pending', NULL, NULL, '2026-09-21 16:51:10', 'Urgent order placed by customer. Waiting for admin approval.'),
(153, 133, 'Assigned', NULL, NULL, '2026-09-21 16:53:01', 'Delivery assigned by admin. ML predicted TAT: 27 minutes.'),
(154, 133, 'Assigned', NULL, NULL, '2026-09-21 16:54:53', 'Driver accepted the assigned delivery.'),
(155, 133, 'In Transit', 14.687175, 120.954729, '2026-09-21 16:54:57', 'Delivery started by driver.'),
(156, 133, 'Delivered', 14.687223, 120.954777, '2026-09-21 16:55:22', 'Delivery completed by driver with proof of delivery photo.'),
(157, 134, 'Pending', NULL, NULL, '2026-09-22 20:02:40', 'Order placed by customer. Waiting for admin approval.'),
(158, 135, 'Pending', NULL, NULL, '2026-09-26 00:21:21', 'Urgent order placed by customer. Waiting for admin approval.'),
(159, 136, 'Pending', NULL, NULL, '2026-10-02 13:35:13', 'Order placed by customer. Waiting for admin approval.'),
(160, 136, 'Assigned', NULL, NULL, '2026-10-02 13:37:13', 'Delivery assigned by admin. ML predicted TAT: 46 minutes.'),
(161, 136, 'Assigned', NULL, NULL, '2026-10-02 13:38:20', 'Driver accepted the assigned delivery.'),
(162, 136, 'In Transit', 14.666159, 120.996963, '2026-10-02 13:38:24', 'Delivery started by driver.'),
(163, 136, 'Delivered', 14.666199, 120.996988, '2026-10-02 13:39:08', 'Delivery completed by driver with proof of delivery photo.'),
(164, 137, 'Pending', NULL, NULL, '2026-10-02 20:05:09', 'Urgent order placed by customer. Waiting for admin approval.'),
(165, 138, 'Pending', NULL, NULL, '2026-10-02 20:10:43', 'Order placed by customer. Waiting for admin approval.'),
(166, 139, 'Pending', NULL, NULL, '2026-10-03 03:58:13', 'Urgent order placed by customer. Waiting for admin approval.'),
(167, 139, 'Assigned', NULL, NULL, '2026-10-03 04:01:28', 'Delivery assigned by admin. ML predicted TAT: 30 minutes.'),
(168, 139, 'Assigned', NULL, NULL, '2026-10-03 04:19:39', 'Driver accepted the assigned delivery.'),
(169, 139, 'In Transit', 14.687195, 120.954735, '2026-10-03 04:27:46', 'Delivery started by driver.'),
(170, 139, 'Delivered', 14.687268, 120.954870, '2026-10-03 04:29:51', 'Delivery completed by driver with proof of delivery photo.'),
(171, 140, 'Pending', NULL, NULL, '2026-10-03 07:04:24', 'Order placed by customer. Waiting for admin approval.'),
(172, 140, 'Assigned', NULL, NULL, '2026-10-03 07:05:55', 'Delivery assigned by admin. ML predicted TAT: 71 minutes.'),
(173, 140, 'Assigned', NULL, NULL, '2026-10-03 07:06:41', 'Driver accepted the assigned delivery.'),
(174, 140, 'In Transit', 14.649853, 120.983912, '2026-10-03 07:06:47', 'Delivery started by driver.'),
(175, 140, 'Delivered', 14.649886, 120.983886, '2026-10-03 07:07:03', 'Delivery completed by driver with proof of delivery photo.'),
(176, 141, 'Pending', NULL, NULL, '2026-10-03 07:12:11', 'Order placed by customer. Waiting for admin approval.'),
(177, 141, 'Assigned', NULL, NULL, '2026-10-03 07:13:49', 'Delivery assigned by admin. ML predicted TAT: 131 minutes.'),
(178, 141, 'Assigned', NULL, NULL, '2026-10-03 07:15:00', 'Driver accepted the assigned delivery.'),
(179, 141, 'In Transit', 14.649869, 120.983886, '2026-10-03 07:15:03', 'Delivery started by driver.'),
(180, 141, 'Delivered', 14.649873, 120.983919, '2026-10-03 07:15:15', 'Delivery completed by driver with proof of delivery photo.'),
(181, 142, 'Pending', NULL, NULL, '2026-10-03 08:40:38', 'Order placed by customer. Waiting for admin approval.'),
(182, 142, 'Assigned', NULL, NULL, '2026-10-03 08:41:56', 'Delivery assigned by admin. ML predicted TAT: 50 minutes.'),
(183, 142, 'Assigned', NULL, NULL, '2026-10-03 08:44:22', 'Driver accepted the assigned delivery.'),
(184, 142, 'In Transit', 14.649868, 120.983903, '2026-10-03 08:44:31', 'Delivery started by driver.'),
(185, 142, 'Delivered', 14.649868, 120.983903, '2026-10-03 08:44:50', 'Delivery completed by driver with proof of delivery photo.'),
(186, 143, 'Pending', NULL, NULL, '2026-10-03 09:12:39', 'Order placed by customer. Waiting for admin approval.'),
(187, 144, 'Pending', NULL, NULL, '2026-10-03 09:13:14', 'Order placed by customer. Waiting for admin approval.'),
(188, 145, 'Pending', NULL, NULL, '2026-10-03 09:22:27', 'Order placed by customer. Waiting for admin approval.'),
(189, 146, 'Pending', NULL, NULL, '2026-10-03 09:26:18', 'Order placed by customer. Waiting for admin approval.'),
(190, 146, 'Assigned', NULL, NULL, '2026-10-03 09:27:14', 'Delivery assigned by admin. ML predicted TAT: 42 minutes.'),
(191, 145, 'Assigned', NULL, NULL, '2026-10-03 09:28:10', 'Delivery assigned by admin. ML predicted TAT: 56 minutes.'),
(192, 144, 'Assigned', NULL, NULL, '2026-10-03 09:29:07', 'Delivery assigned by admin. ML predicted TAT: 30 minutes.'),
(193, 147, 'Pending', NULL, NULL, '2026-10-04 20:25:41', 'Urgent order placed by customer. Waiting for admin approval.'),
(194, 147, 'Assigned', NULL, NULL, '2026-10-04 21:27:10', 'Delivery assigned by admin. ML predicted TAT: 32 minutes.'),
(195, 147, 'Assigned', NULL, NULL, '2026-10-04 21:27:44', 'Driver accepted the assigned delivery.'),
(196, 147, 'In Transit', 14.687225, 120.954772, '2026-10-04 21:27:48', 'Delivery started by driver.'),
(197, 148, 'Pending', NULL, NULL, '2026-10-14 01:17:10', 'Order placed by customer. Waiting for admin approval.'),
(198, 148, 'Assigned', NULL, NULL, '2026-10-14 01:46:35', 'Delivery assigned by admin. ML predicted TAT: 36 minutes.'),
(199, 148, 'Assigned', NULL, NULL, '2026-10-14 02:03:45', 'Driver accepted the assigned delivery.'),
(200, 148, 'In Transit', 14.687218, 120.954756, '2026-10-14 02:05:36', 'Delivery started by driver.');

-- --------------------------------------------------------

--
-- Table structure for table `driver`
--

CREATE TABLE `driver` (
  `driver_id` int(11) NOT NULL,
  `license_number` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('Available','On Delivery','On Leave','Absent','Inactive','Resigned','Terminated') NOT NULL DEFAULT 'Available',
  `current_truck_id` int(11) DEFAULT NULL,
  `total_deliveries` int(11) NOT NULL DEFAULT 0,
  `rating` decimal(3,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_location` varchar(150) DEFAULT NULL,
  `current_latitude` decimal(10,7) DEFAULT NULL,
  `current_longitude` decimal(10,7) DEFAULT NULL,
  `location_updated_at` datetime DEFAULT NULL,
  `phone_verified` tinyint(1) NOT NULL DEFAULT 0,
  `phone_verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `driver`
--

INSERT INTO `driver` (`driver_id`, `license_number`, `full_name`, `phone_number`, `email`, `password_hash`, `status`, `current_truck_id`, `total_deliveries`, `rating`, `created_at`, `last_location`, `current_latitude`, `current_longitude`, `location_updated_at`, `phone_verified`, `phone_verified_at`) VALUES
(1, 'N01-23-456789', 'Mateo Ramos', '09517849042', 'mateoramos_driver@hongsing.com', '$2y$10$Ru9WV8RE/yXcRV3tHSmt1uwZ339tLfOoeNWzI8mjCwpjmfbcaMDWa', 'Available', 46, 2, NULL, '2026-08-03 22:36:50', NULL, 14.6498856, 120.9838863, '2026-10-03 07:07:03', 0, NULL),
(2, 'B02-19-112049', 'Efren Santos', '09944673432', 'efrensantos_driver@hongsing.com', '$2y$10$9KR/oc1JgJFmcl9bWU0beefLcr9BWW.kzv5i6pr65ibka7FWWiwka', 'Available', 2, 3, NULL, '2026-08-18 13:13:00', NULL, 14.6622184, 120.9637081, '2026-09-16 17:09:50', 0, NULL),
(33, 'B03-26-344628', 'Adrian Cruz', '09170000003', 'adrian.cruz@hongsing.test', '$2y$10$5upy6jpGicV4KOD.xL1riuPZLPRv0Cekq0zY8inj762apeV2vhdJq', 'On Delivery', 1, 0, 4.26, '2026-09-09 16:58:33', NULL, 14.6872222, 120.9547693, '2026-09-21 16:39:19', 0, NULL),
(34, 'B04-26-558751', 'Benjamin Santos', '09170000004', 'benjamin.santos@hongsing.test', '$2y$10$wO66yDlaGDHL2ItNgjJqWOLcS38pxAdGlFOswSEWtUt/1PHeVShm.', 'Available', NULL, 0, 4.19, '2026-09-09 16:58:33', NULL, NULL, NULL, NULL, 0, NULL),
(35, 'B05-26-785800', 'Carlo Mendoza', '09170000005', 'carlo.mendoza@hongsing.test', '$2y$10$EO/L.cI.RdW9KAEr4erBi.qzdG5y.fdaDkMS6xyXM0.HTDB17ycyS', 'Available', 33, 1, 4.29, '2026-09-09 16:58:34', NULL, 14.6874156, 120.9544861, '2026-09-26 00:30:02', 0, NULL),
(36, 'B06-26-369010', 'Daniel Reyes', '09170000006', 'daniel.reyes@hongsing.test', '$2y$10$7ANey2jUcgmd8D7U90NV8u6PA9F0LkHnh3b3BU8DwxhAu01r12TuG', 'Available', 34, 1, 4.94, '2026-09-09 16:58:34', NULL, 14.6872679, 120.9548698, '2026-10-03 04:30:02', 0, NULL),
(37, 'B07-26-419555', 'Eduardo Garcia', '09170000007', 'eduardo.garcia@hongsing.test', '$2y$10$ZxjabwYdD/AxdtiPSVHdXORWKmNnCAK04/o1aAWyvVbzwztM1NNz2', 'Available', 35, 1, 4.95, '2026-09-09 16:58:34', NULL, 14.6498287, 120.9839353, '2026-10-03 08:59:19', 0, NULL),
(38, 'B08-26-331477', 'Francis Dela Cruz', '09170000008', 'francis.delacruz@hongsing.test', '$2y$10$khilMr8kpvD/Xl7amp2TgOZNW8FIHratFsDAs1Bh.VAe7m35kWGve', 'Available', 36, 1, 4.30, '2026-09-09 16:58:34', NULL, 14.6661994, 120.9969879, '2026-10-02 13:39:08', 0, NULL),
(39, 'B09-26-555090', 'Gabriel Flores', '09170000009', 'gabriel.flores@hongsing.test', '$2y$10$tOQhwZyLAGqdzrk6xPf0lO4UF4EmDboEqcT7H1p/JqCnzdpRG9SSe', 'Available', 37, 1, 4.53, '2026-09-09 16:58:34', NULL, 14.6498727, 120.9839186, '2026-10-03 07:15:16', 0, NULL),
(40, 'B10-26-251151', 'Harold Aquino', '09170000010', 'harold.aquino@hongsing.test', '$2y$10$YOTw29ln00IuPOyJDSaDfevxfbvzDNqoGOjKfQ//GQ0sWRAzr3k92', 'Available', 38, 0, 4.13, '2026-09-09 16:58:34', NULL, NULL, NULL, NULL, 0, NULL),
(41, 'B11-26-164179', 'Ivan Bautista', '09170000011', 'ivan.bautista@hongsing.test', '$2y$10$0bBLrab70O.fuLHgAyW5x.wrqRCM1EdMfwmO2RdHdSfw1h30bhd0q', 'Available', 39, 0, 4.47, '2026-09-09 16:58:34', NULL, NULL, NULL, NULL, 0, NULL),
(42, 'B12-26-763114', 'Jerome Navarro', '09170000012', 'jerome.navarro@hongsing.test', '$2y$10$bLiFtUhG28Uhd85FIllWbe4rl7HrRSeFhbpYoenuIqzB.4EDLoZrC', 'On Delivery', 40, 0, 4.55, '2026-09-09 16:58:35', NULL, 14.6871933, 120.9547285, '2026-10-14 02:13:51', 0, NULL),
(43, 'B13-26-140822', 'Kevin Ramos', '09170000013', 'kevin.ramos@hongsing.test', '$2y$10$dkyMwT85Gut6aUk3mqGnKuSGpwKGpw/26gno1UGnEucmSKPqUa57.', 'Available', 41, 0, 4.67, '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, 0, NULL),
(44, 'B14-26-911123', 'Luis Mercado', '09170000014', 'luis.mercado@hongsing.test', '$2y$10$XvDQ55gXe.jf163TXxrLYefLtQrK8dvL6EqJkVCHgVrYahELVrk66', 'Available', 42, 0, 4.20, '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, 0, NULL),
(45, 'B15-26-134544', 'Marco Villanueva', '09170000015', 'marco.villanueva@hongsing.test', '$2y$10$0po/zy83y7wVMtXtLgUPguReBmx3/OKC5Ae2cLJyJgJIqLi26YDcq', 'On Delivery', 43, 0, 3.85, '2026-09-09 16:58:35', NULL, 14.6872202, 120.9547671, '2026-10-04 21:38:00', 0, NULL),
(46, 'B16-26-755022', 'Nathan Torres', '09170000016', 'nathan.torres@hongsing.test', '$2y$10$/E1t0QrigTGz5AJXqJs39eYkz/JW.obUv27YTBnQ/n1PskKBnqZtS', 'Available', 44, 0, 3.88, '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, 0, NULL),
(47, 'B17-26-330143', 'Oscar Fernandez', '09170000017', 'oscar.fernandez@hongsing.test', '$2y$10$kZNvnfTGc7v5CJhERsAffOw1ki6EZ6YQwUM9n7fjTiN3OMrtyydJa', 'Available', 45, 0, 4.63, '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `driver_truck_assignment`
--

CREATE TABLE `driver_truck_assignment` (
  `assignment_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `truck_id` int(11) NOT NULL,
  `assigned_date` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `driver_truck_assignment`
--

INSERT INTO `driver_truck_assignment` (`assignment_id`, `driver_id`, `truck_id`, `assigned_date`, `is_active`) VALUES
(1, 2, 2, '2026-08-18', 0),
(2, 2, 2, '2026-08-26', 0),
(3, 2, 2, '2026-08-26', 0),
(4, 2, 2, '2026-08-26', 0),
(5, 2, 2, '2026-09-09', 1),
(36, 33, 1, '2026-09-09', 0),
(37, 34, 2, '2026-09-09', 0),
(38, 35, 33, '2026-09-09', 0),
(39, 36, 34, '2026-09-09', 0),
(40, 37, 35, '2026-09-09', 0),
(41, 38, 36, '2026-09-09', 0),
(42, 39, 37, '2026-09-09', 0),
(43, 40, 38, '2026-09-09', 1),
(44, 41, 39, '2026-09-09', 1),
(45, 42, 40, '2026-09-09', 0),
(46, 43, 41, '2026-09-09', 1),
(47, 44, 42, '2026-09-09', 1),
(48, 45, 43, '2026-09-09', 0),
(49, 46, 44, '2026-09-09', 1),
(50, 47, 45, '2026-09-09', 1),
(51, 33, 1, '2026-09-20', 1),
(52, 35, 33, '2026-09-21', 0),
(53, 38, 36, '2026-10-02', 1),
(54, 36, 34, '2026-10-03', 0),
(55, 1, 46, '2026-10-03', 1),
(56, 39, 37, '2026-10-03', 1),
(57, 37, 35, '2026-10-03', 1),
(58, 35, 33, '2026-10-03', 0),
(59, 36, 34, '2026-10-03', 1),
(60, 35, 33, '2026-10-03', 1),
(61, 45, 43, '2026-10-04', 1),
(62, 42, 40, '2026-10-14', 1);

-- --------------------------------------------------------

--
-- Table structure for table `email_verification_otp`
--

CREATE TABLE `email_verification_otp` (
  `otp_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempt_count` int(11) NOT NULL DEFAULT 0,
  `last_sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL,
  `comments` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `order_id`, `customer_id`, `driver_id`, `rating`, `comments`, `created_at`) VALUES
(1, 5, 2, 2, 5, NULL, '2026-08-26 18:15:30'),
(2, 1, 1, 1, 3, 'Sofer late na ng delivery', '2026-08-26 19:00:11'),
(3, 136, 1, 38, 5, 'FIVE STARS, COMMENDABLE DELIVERY bilis', '2026-10-02 13:40:21'),
(4, 139, 1, 36, 4, 'Goods', '2026-10-03 04:31:05'),
(5, 140, 1, 1, 5, 'GRABE SOBRANG BILIS, WALA PA SA KALINGKINGAN NIYO', '2026-10-03 07:09:53'),
(6, 141, 1, 39, 1, 'GRABE, SOFER TAGAL DUMATING SABI KO URGENT UMABOT NG 2 DAYS, HAYS!!!', '2026-10-03 07:15:58'),
(7, 142, 1, 37, 5, 'Goods', '2026-10-03 08:46:09');

-- --------------------------------------------------------

--
-- Table structure for table `ml_assignment_log`
--

CREATE TABLE `ml_assignment_log` (
  `log_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `truck_id` int(11) DEFAULT NULL,
  `delivery_location` varchar(255) DEFAULT NULL,
  `order_volume` decimal(10,2) DEFAULT NULL,
  `distance_km` decimal(10,2) DEFAULT NULL,
  `order_weight_kg` decimal(10,2) DEFAULT NULL,
  `day_of_week` tinyint(4) DEFAULT NULL,
  `hour_of_day` tinyint(4) DEFAULT NULL,
  `priority_level` enum('Normal','Urgent') DEFAULT NULL,
  `predicted_tat_minutes` int(11) DEFAULT NULL,
  `predicted_delay_probability` decimal(5,4) DEFAULT NULL,
  `predicted_delay_risk` enum('Low','Medium','High') DEFAULT NULL,
  `actual_tat_minutes` int(11) DEFAULT NULL,
  `actual_delay_minutes` int(11) DEFAULT NULL,
  `was_delayed` tinyint(1) DEFAULT NULL,
  `model_version` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ml_assignment_log`
--

INSERT INTO `ml_assignment_log` (`log_id`, `order_id`, `driver_id`, `truck_id`, `delivery_location`, `order_volume`, `distance_km`, `order_weight_kg`, `day_of_week`, `hour_of_day`, `priority_level`, `predicted_tat_minutes`, `predicted_delay_probability`, `predicted_delay_risk`, `actual_tat_minutes`, `actual_delay_minutes`, `was_delayed`, `model_version`, `created_at`) VALUES
(1, 10, 1, 43, 'Demo delivery location - Pasig Route', 1208.70, NULL, NULL, NULL, NULL, NULL, 98, NULL, NULL, 92, NULL, NULL, 'demo-v1', '2025-11-27 00:00:00'),
(2, 11, 39, 37, 'Demo delivery location - Makati Route', 329.01, NULL, NULL, NULL, NULL, NULL, 78, NULL, NULL, 85, NULL, NULL, 'demo-v1', '2026-01-14 00:00:00'),
(3, 12, 38, 36, 'Demo delivery location - Malolos Route', 341.69, NULL, NULL, NULL, NULL, NULL, 126, NULL, NULL, 115, NULL, NULL, 'demo-v1', '2025-03-15 00:00:00'),
(4, 13, 40, 38, 'Demo delivery location - Malabon Route', 1342.59, NULL, NULL, NULL, NULL, NULL, 23, NULL, NULL, 32, NULL, NULL, 'demo-v1', '2025-11-27 00:00:00'),
(5, 14, 45, 43, 'Demo delivery location - Caloocan Route', 2141.86, NULL, NULL, NULL, NULL, NULL, 75, NULL, NULL, 80, NULL, NULL, 'demo-v1', '2025-04-02 00:00:00'),
(6, 15, 42, 40, 'Demo delivery location - Caloocan Route', 456.24, NULL, NULL, NULL, NULL, NULL, 61, NULL, NULL, 67, NULL, NULL, 'demo-v1', '2025-02-21 00:00:00'),
(7, 16, 2, 2, 'Demo delivery location - Malabon Route', 1480.88, NULL, NULL, NULL, NULL, NULL, 52, NULL, NULL, 54, NULL, NULL, 'demo-v1', '2024-10-13 00:00:00'),
(8, 17, 40, 38, 'Demo delivery location - Bulacan Route', 590.85, NULL, NULL, NULL, NULL, NULL, 76, NULL, NULL, 66, NULL, NULL, 'demo-v1', '2024-11-20 00:00:00'),
(9, 18, 33, 1, 'Demo delivery location - Malolos Route', 2386.39, NULL, NULL, NULL, NULL, NULL, 149, NULL, NULL, 144, NULL, NULL, 'demo-v1', '2024-10-11 00:00:00'),
(10, 19, 37, 35, 'Demo delivery location - Las Piñas Route', 1918.27, NULL, NULL, NULL, NULL, NULL, 109, NULL, NULL, 119, NULL, NULL, 'demo-v1', '2025-08-25 00:00:00'),
(11, 20, 37, 35, 'Demo delivery location - Malolos Route', 1051.10, NULL, NULL, NULL, NULL, NULL, 160, NULL, NULL, 145, NULL, NULL, 'demo-v1', '2025-10-15 00:00:00'),
(12, 21, 43, 41, 'Demo delivery location - Parañaque Route', 674.25, NULL, NULL, NULL, NULL, NULL, 108, NULL, NULL, 103, NULL, NULL, 'demo-v1', '2025-12-10 00:00:00'),
(13, 22, 46, 44, 'Demo delivery location - Bocaue Route', 519.29, NULL, NULL, NULL, NULL, NULL, 86, NULL, NULL, 90, NULL, NULL, 'demo-v1', '2026-08-10 00:00:00'),
(14, 23, 1, 45, 'Demo delivery location - Manila Route', 1614.49, NULL, NULL, NULL, NULL, NULL, 88, NULL, NULL, 75, NULL, NULL, 'demo-v1', '2026-08-03 00:00:00'),
(15, 24, 43, 41, 'Demo delivery location - Las Piñas Route', 755.21, NULL, NULL, NULL, NULL, NULL, 120, NULL, NULL, 123, NULL, NULL, 'demo-v1', '2024-11-27 00:00:00'),
(16, 25, 45, 43, 'Demo delivery location - Quezon City Route', 1835.93, NULL, NULL, NULL, NULL, NULL, 58, NULL, NULL, 73, NULL, NULL, 'demo-v1', '2026-01-25 00:00:00'),
(17, 26, 34, 2, 'Demo delivery location - Manila Route', 2096.43, NULL, NULL, NULL, NULL, NULL, 101, NULL, NULL, 87, NULL, NULL, 'demo-v1', '2025-03-30 00:00:00'),
(18, 27, 44, 42, 'Demo delivery location - Caloocan Route', 1132.98, NULL, NULL, NULL, NULL, NULL, 39, NULL, NULL, 48, NULL, NULL, 'demo-v1', '2026-06-22 00:00:00'),
(19, 28, 42, 40, 'Demo delivery location - Makati Route', 546.51, NULL, NULL, NULL, NULL, NULL, 91, NULL, NULL, 77, NULL, NULL, 'demo-v1', '2025-09-18 00:00:00'),
(20, 29, 42, 40, 'Demo delivery location - Marikina Route', 767.90, NULL, NULL, NULL, NULL, NULL, 96, NULL, NULL, 91, NULL, NULL, 'demo-v1', '2025-12-14 00:00:00'),
(21, 30, 39, 37, 'Demo delivery location - Malolos Route', 1302.72, NULL, NULL, NULL, NULL, NULL, 126, NULL, NULL, 129, NULL, NULL, 'demo-v1', '2025-12-02 00:00:00'),
(22, 31, 47, 45, 'Demo delivery location - Caloocan Route', 1524.74, NULL, NULL, NULL, NULL, NULL, 45, NULL, NULL, 41, NULL, NULL, 'demo-v1', '2025-07-23 00:00:00'),
(23, 32, 36, 34, 'Demo delivery location - Malabon Route', 1280.18, NULL, NULL, NULL, NULL, NULL, 53, NULL, NULL, 47, NULL, NULL, 'demo-v1', '2025-10-30 00:00:00'),
(24, 33, 42, 40, 'Demo delivery location - Manila Route', 631.01, NULL, NULL, NULL, NULL, NULL, 74, NULL, NULL, 77, NULL, NULL, 'demo-v1', '2025-01-10 00:00:00'),
(25, 34, 37, 35, 'Demo delivery location - North Valenzuela Route', 477.28, NULL, NULL, NULL, NULL, NULL, 32, NULL, NULL, 25, NULL, NULL, 'demo-v1', '2025-05-22 00:00:00'),
(26, 35, 46, 44, 'Demo delivery location - Malabon Route', 1700.83, NULL, NULL, NULL, NULL, NULL, 59, NULL, NULL, 56, NULL, NULL, 'demo-v1', '2025-11-02 00:00:00'),
(27, 36, 43, 41, 'Demo delivery location - Manila Route', 1046.06, NULL, NULL, NULL, NULL, NULL, 56, NULL, NULL, 69, NULL, NULL, 'demo-v1', '2026-04-05 00:00:00'),
(28, 37, 1, 37, 'Demo delivery location - Makati Route', 1984.79, NULL, NULL, NULL, NULL, NULL, 89, NULL, NULL, 75, NULL, NULL, 'demo-v1', '2026-07-08 00:00:00'),
(29, 38, 2, 2, 'Demo delivery location - Manila Route', 2269.66, NULL, NULL, NULL, NULL, NULL, 63, NULL, NULL, 65, NULL, NULL, 'demo-v1', '2026-03-31 00:00:00'),
(30, 39, 45, 43, 'Demo delivery location - Quezon City Route', 2076.27, NULL, NULL, NULL, NULL, NULL, 92, NULL, NULL, 91, NULL, NULL, 'demo-v1', '2025-12-31 00:00:00'),
(31, 40, 45, 43, 'Demo delivery location - Parañaque Route', 1836.65, NULL, NULL, NULL, NULL, NULL, 125, NULL, NULL, 116, NULL, NULL, 'demo-v1', '2025-01-27 00:00:00'),
(32, 41, 41, 39, 'Demo delivery location - Marikina Route', 708.42, NULL, NULL, NULL, NULL, NULL, 111, NULL, NULL, 98, NULL, NULL, 'demo-v1', '2025-07-18 00:00:00'),
(33, 42, 40, 38, 'Demo delivery location - Makati Route', 1328.39, NULL, NULL, NULL, NULL, NULL, 81, NULL, NULL, 69, NULL, NULL, 'demo-v1', '2025-07-03 00:00:00'),
(34, 43, 41, 39, 'Demo delivery location - Bulacan Route', 1822.25, NULL, NULL, NULL, NULL, NULL, 80, NULL, NULL, 76, NULL, NULL, 'demo-v1', '2026-03-01 00:00:00'),
(35, 44, 35, 33, 'Demo delivery location - Bulacan Route', 167.70, NULL, NULL, NULL, NULL, NULL, 82, NULL, NULL, 82, NULL, NULL, 'demo-v1', '2025-04-25 00:00:00'),
(36, 45, 42, 40, 'Demo delivery location - Malabon Route', 807.44, NULL, NULL, NULL, NULL, NULL, 57, NULL, NULL, 49, NULL, NULL, 'demo-v1', '2026-01-09 00:00:00'),
(37, 46, 1, 42, 'Demo delivery location - Bulacan Route', 1119.52, NULL, NULL, NULL, NULL, NULL, 73, NULL, NULL, 88, NULL, NULL, 'demo-v1', '2025-06-24 00:00:00'),
(38, 47, 36, 34, 'Demo delivery location - Las Piñas Route', 844.86, NULL, NULL, NULL, NULL, NULL, 130, NULL, NULL, 124, NULL, NULL, 'demo-v1', '2026-06-15 00:00:00'),
(39, 48, 1, 41, 'Demo delivery location - Navotas Route', 937.69, NULL, NULL, NULL, NULL, NULL, 85, NULL, NULL, 70, NULL, NULL, 'demo-v1', '2025-07-17 00:00:00'),
(40, 49, 33, 1, 'Demo delivery location - Marikina Route', 2376.00, NULL, NULL, NULL, NULL, NULL, 88, NULL, NULL, 88, NULL, NULL, 'demo-v1', '2026-04-11 00:00:00'),
(41, 50, 42, 40, 'Demo delivery location - Parañaque Route', 623.23, NULL, NULL, NULL, NULL, NULL, 79, NULL, NULL, 94, NULL, NULL, 'demo-v1', '2024-11-15 00:00:00'),
(42, 51, 39, 37, 'Demo delivery location - Bocaue Route', 2469.47, NULL, NULL, NULL, NULL, NULL, 109, NULL, NULL, 118, NULL, NULL, 'demo-v1', '2025-01-15 00:00:00'),
(43, 52, 33, 1, 'Demo delivery location - Quezon City Route', 513.74, NULL, NULL, NULL, NULL, NULL, 70, NULL, NULL, 74, NULL, NULL, 'demo-v1', '2025-01-28 00:00:00'),
(44, 53, 43, 41, 'Demo delivery location - Bulacan Route', 152.58, NULL, NULL, NULL, NULL, NULL, 85, NULL, NULL, 76, NULL, NULL, 'demo-v1', '2026-06-17 00:00:00'),
(45, 54, 43, 41, 'Demo delivery location - Manila Route', 471.04, NULL, NULL, NULL, NULL, NULL, 55, NULL, NULL, 63, NULL, NULL, 'demo-v1', '2025-12-13 00:00:00'),
(46, 55, 37, 35, 'Demo delivery location - Malabon Route', 1432.40, NULL, NULL, NULL, NULL, NULL, 40, NULL, NULL, 45, NULL, NULL, 'demo-v1', '2025-09-17 00:00:00'),
(47, 56, 47, 45, 'Demo delivery location - Las Piñas Route', 857.82, NULL, NULL, NULL, NULL, NULL, 130, NULL, NULL, 119, NULL, NULL, 'demo-v1', '2025-02-01 00:00:00'),
(48, 57, 35, 33, 'Demo delivery location - Navotas Route', 836.92, NULL, NULL, NULL, NULL, NULL, 40, NULL, NULL, 47, NULL, NULL, 'demo-v1', '2025-06-12 00:00:00'),
(49, 58, 40, 38, 'Demo delivery location - Bocaue Route', 959.53, NULL, NULL, NULL, NULL, NULL, 105, NULL, NULL, 114, NULL, NULL, 'demo-v1', '2026-02-04 00:00:00'),
(50, 59, 44, 42, 'Demo delivery location - Bulacan Route', 522.00, NULL, NULL, NULL, NULL, NULL, 89, NULL, NULL, 90, NULL, NULL, 'demo-v1', '2026-01-03 00:00:00'),
(51, 60, 42, 40, 'Demo delivery location - Malolos Route', 598.34, NULL, NULL, NULL, NULL, NULL, 152, NULL, NULL, 137, NULL, NULL, 'demo-v1', '2025-04-05 00:00:00'),
(52, 61, 39, 37, 'Demo delivery location - Caloocan Route', 811.91, NULL, NULL, NULL, NULL, NULL, 47, NULL, NULL, 54, NULL, NULL, 'demo-v1', '2026-03-26 00:00:00'),
(53, 62, 45, 43, 'Demo delivery location - Caloocan Route', 418.56, NULL, NULL, NULL, NULL, NULL, 55, NULL, NULL, 52, NULL, NULL, 'demo-v1', '2025-12-12 00:00:00'),
(54, 63, 42, 40, 'Demo delivery location - Quezon City Route', 186.31, NULL, NULL, NULL, NULL, NULL, 70, NULL, NULL, 78, NULL, NULL, 'demo-v1', '2025-09-04 00:00:00'),
(55, 64, 35, 33, 'Demo delivery location - Bocaue Route', 786.11, NULL, NULL, NULL, NULL, NULL, 90, NULL, NULL, 88, NULL, NULL, 'demo-v1', '2025-02-12 00:00:00'),
(56, 65, 37, 35, 'Demo delivery location - Muntinlupa Route', 638.47, NULL, NULL, NULL, NULL, NULL, 130, NULL, NULL, 126, NULL, NULL, 'demo-v1', '2024-12-28 00:00:00'),
(57, 66, 43, 41, 'Demo delivery location - Quezon City Route', 852.99, NULL, NULL, NULL, NULL, NULL, 59, NULL, NULL, 55, NULL, NULL, 'demo-v1', '2026-04-06 00:00:00'),
(58, 67, 47, 45, 'Demo delivery location - Malolos Route', 1733.48, NULL, NULL, NULL, NULL, NULL, 168, NULL, NULL, 159, NULL, NULL, 'demo-v1', '2024-11-21 00:00:00'),
(59, 68, 2, 2, 'Demo delivery location - Caloocan Route', 932.73, NULL, NULL, NULL, NULL, NULL, 49, NULL, NULL, 46, NULL, NULL, 'demo-v1', '2025-08-21 00:00:00'),
(60, 69, 46, 44, 'Demo delivery location - Malolos Route', 1169.93, NULL, NULL, NULL, NULL, NULL, 147, NULL, NULL, 142, NULL, NULL, 'demo-v1', '2024-10-17 00:00:00'),
(61, 70, 38, 36, 'Demo delivery location - Malabon Route', 1875.18, NULL, NULL, NULL, NULL, NULL, 71, NULL, NULL, 60, NULL, NULL, 'demo-v1', '2026-04-09 00:00:00'),
(62, 71, 44, 42, 'Demo delivery location - Parañaque Route', 693.14, NULL, NULL, NULL, NULL, NULL, 95, NULL, NULL, 103, NULL, NULL, 'demo-v1', '2024-12-07 00:00:00'),
(63, 72, 38, 36, 'Demo delivery location - North Valenzuela Route', 1431.76, NULL, NULL, NULL, NULL, NULL, 27, NULL, NULL, 33, NULL, NULL, 'demo-v1', '2024-11-01 00:00:00'),
(64, 73, 35, 33, 'Demo delivery location - Quezon City Route', 708.04, NULL, NULL, NULL, NULL, NULL, 78, NULL, NULL, 68, NULL, NULL, 'demo-v1', '2025-04-18 00:00:00'),
(65, 74, 33, 1, 'Demo delivery location - Pasig Route', 287.43, NULL, NULL, NULL, NULL, NULL, 54, NULL, NULL, 63, NULL, NULL, 'demo-v1', '2026-01-07 00:00:00'),
(66, 75, 41, 39, 'Demo delivery location - Bulacan Route', 2672.43, NULL, NULL, NULL, NULL, NULL, 75, NULL, NULL, 89, NULL, NULL, 'demo-v1', '2024-11-09 00:00:00'),
(67, 76, 34, 2, 'Demo delivery location - Caloocan Route', 511.28, NULL, NULL, NULL, NULL, NULL, 27, NULL, NULL, 40, NULL, NULL, 'demo-v1', '2026-01-02 00:00:00'),
(68, 77, 44, 42, 'Demo delivery location - Caloocan Route', 1645.11, NULL, NULL, NULL, NULL, NULL, 50, NULL, NULL, 59, NULL, NULL, 'demo-v1', '2024-10-27 00:00:00'),
(69, 78, 34, 2, 'Demo delivery location - Makati Route', 215.88, NULL, NULL, NULL, NULL, NULL, 69, NULL, NULL, 74, NULL, NULL, 'demo-v1', '2025-03-23 00:00:00'),
(70, 79, 40, 38, 'Demo delivery location - Bocaue Route', 2499.89, NULL, NULL, NULL, NULL, NULL, 82, NULL, NULL, 94, NULL, NULL, 'demo-v1', '2024-11-12 00:00:00'),
(71, 80, 47, 45, 'Demo delivery location - Manila Route', 1898.26, NULL, NULL, NULL, NULL, NULL, 78, NULL, NULL, 90, NULL, NULL, 'demo-v1', '2025-01-08 00:00:00'),
(72, 81, 41, 39, 'Demo delivery location - Bulacan Route', 2725.03, NULL, NULL, NULL, NULL, NULL, 86, NULL, NULL, 101, NULL, NULL, 'demo-v1', '2026-01-31 00:00:00'),
(73, 82, 2, 2, 'Demo delivery location - North Valenzuela Route', 1265.62, NULL, NULL, NULL, NULL, NULL, 57, NULL, NULL, 52, NULL, NULL, 'demo-v1', '2026-07-16 00:00:00'),
(74, 83, 46, 44, 'Demo delivery location - Malabon Route', 2118.42, NULL, NULL, NULL, NULL, NULL, 47, NULL, NULL, 48, NULL, NULL, 'demo-v1', '2025-03-10 00:00:00'),
(75, 84, 44, 42, 'Demo delivery location - North Valenzuela Route', 841.64, NULL, NULL, NULL, NULL, NULL, 29, NULL, NULL, 42, NULL, NULL, 'demo-v1', '2026-01-04 00:00:00'),
(76, 85, 42, 40, 'Demo delivery location - Muntinlupa Route', 742.89, NULL, NULL, NULL, NULL, NULL, 137, NULL, NULL, 134, NULL, NULL, 'demo-v1', '2026-05-03 00:00:00'),
(77, 86, 45, 43, 'Demo delivery location - Marikina Route', 597.97, NULL, NULL, NULL, NULL, NULL, 104, NULL, NULL, 102, NULL, NULL, 'demo-v1', '2026-01-08 00:00:00'),
(78, 87, 35, 33, 'Demo delivery location - Malolos Route', 421.30, NULL, NULL, NULL, NULL, NULL, 121, NULL, NULL, 126, NULL, NULL, 'demo-v1', '2025-08-27 00:00:00'),
(79, 88, 39, 37, 'Demo delivery location - North Valenzuela Route', 1487.02, NULL, NULL, NULL, NULL, NULL, 44, NULL, NULL, 47, NULL, NULL, 'demo-v1', '2026-03-31 00:00:00'),
(80, 89, 1, 35, 'Demo delivery location - Pasig Route', 1555.12, NULL, NULL, NULL, NULL, NULL, 100, NULL, NULL, 100, NULL, NULL, 'demo-v1', '2026-07-28 00:00:00'),
(81, 90, 42, 40, 'Demo delivery location - Muntinlupa Route', 949.00, NULL, NULL, NULL, NULL, NULL, 133, NULL, NULL, 131, NULL, NULL, 'demo-v1', '2025-01-03 00:00:00'),
(82, 91, 44, 42, 'Demo delivery location - Malabon Route', 229.00, NULL, NULL, NULL, NULL, NULL, 43, NULL, NULL, 43, NULL, NULL, 'demo-v1', '2025-05-28 00:00:00'),
(83, 92, 40, 38, 'Demo delivery location - Navotas Route', 1400.10, NULL, NULL, NULL, NULL, NULL, 72, NULL, NULL, 60, NULL, NULL, 'demo-v1', '2026-07-03 00:00:00'),
(84, 93, 47, 45, 'Demo delivery location - Caloocan Route', 1242.77, NULL, NULL, NULL, NULL, NULL, 57, NULL, NULL, 58, NULL, NULL, 'demo-v1', '2024-10-20 00:00:00'),
(85, 94, 44, 42, 'Demo delivery location - Navotas Route', 926.32, NULL, NULL, NULL, NULL, NULL, 56, NULL, NULL, 56, NULL, NULL, 'demo-v1', '2026-05-31 00:00:00'),
(86, 95, 43, 41, 'Demo delivery location - Quezon City Route', 664.29, NULL, NULL, NULL, NULL, NULL, 73, NULL, NULL, 80, NULL, NULL, 'demo-v1', '2026-06-21 00:00:00'),
(87, 96, 37, 35, 'Demo delivery location - Malabon Route', 1031.30, NULL, NULL, NULL, NULL, NULL, 46, NULL, NULL, 42, NULL, NULL, 'demo-v1', '2025-12-16 00:00:00'),
(88, 97, 40, 38, 'Demo delivery location - Caloocan Route', 796.46, NULL, NULL, NULL, NULL, NULL, 65, NULL, NULL, 53, NULL, NULL, 'demo-v1', '2025-02-11 00:00:00'),
(89, 98, 33, 1, 'Demo delivery location - North Valenzuela Route', 1237.33, NULL, NULL, NULL, NULL, NULL, 30, NULL, NULL, 26, NULL, NULL, 'demo-v1', '2024-12-29 00:00:00'),
(90, 99, 40, 38, 'Demo delivery location - Las Piñas Route', 2162.72, NULL, NULL, NULL, NULL, NULL, 130, NULL, NULL, 138, NULL, NULL, 'demo-v1', '2024-11-06 00:00:00'),
(91, 100, 35, 33, 'Demo delivery location - Malolos Route', 287.34, NULL, NULL, NULL, NULL, NULL, 107, NULL, NULL, 114, NULL, NULL, 'demo-v1', '2025-08-19 00:00:00'),
(92, 101, 34, 2, 'Demo delivery location - Navotas Route', 1684.56, NULL, NULL, NULL, NULL, NULL, 52, NULL, NULL, 45, NULL, NULL, 'demo-v1', '2026-07-31 00:00:00'),
(93, 102, 2, 2, 'Demo delivery location - Parañaque Route', 1995.82, NULL, NULL, NULL, NULL, NULL, 126, NULL, NULL, 112, NULL, NULL, 'demo-v1', '2026-07-23 00:00:00'),
(94, 103, 33, 1, 'Demo delivery location - Las Piñas Route', 1323.38, NULL, NULL, NULL, NULL, NULL, 129, NULL, NULL, 122, NULL, NULL, 'demo-v1', '2025-09-12 00:00:00'),
(95, 104, 2, 2, 'Demo delivery location - Pasig Route', 1351.48, NULL, NULL, NULL, NULL, NULL, 94, NULL, NULL, 104, NULL, NULL, 'demo-v1', '2026-06-22 00:00:00'),
(96, 105, 35, 33, 'Demo delivery location - Manila Route', 919.32, NULL, NULL, NULL, NULL, NULL, 49, NULL, NULL, 44, NULL, NULL, 'demo-v1', '2026-03-15 00:00:00'),
(97, 106, 33, 1, 'Demo delivery location - Bocaue Route', 556.24, NULL, NULL, NULL, NULL, NULL, 96, NULL, NULL, 96, NULL, NULL, 'demo-v1', '2025-09-17 00:00:00'),
(98, 107, 35, 33, 'Demo delivery location - Parañaque Route', 597.91, NULL, NULL, NULL, NULL, NULL, 124, NULL, NULL, 123, NULL, NULL, 'demo-v1', '2025-12-12 00:00:00'),
(99, 108, 35, 33, 'Demo delivery location - Bocaue Route', 875.83, NULL, NULL, NULL, NULL, NULL, 85, NULL, NULL, 88, NULL, NULL, 'demo-v1', '2026-01-22 00:00:00'),
(100, 109, 47, 45, 'Demo delivery location - Bulacan Route', 1283.70, NULL, NULL, NULL, NULL, NULL, 89, NULL, NULL, 86, NULL, NULL, 'demo-v1', '2025-03-30 00:00:00'),
(101, 110, 44, 42, 'Demo delivery location - Manila Route', 1149.56, NULL, NULL, NULL, NULL, NULL, 46, NULL, NULL, 60, NULL, NULL, 'demo-v1', '2026-07-21 00:00:00'),
(102, 111, 43, 41, 'Demo delivery location - Malabon Route', 420.64, NULL, NULL, NULL, NULL, NULL, 45, NULL, NULL, 53, NULL, NULL, 'demo-v1', '2025-03-30 00:00:00'),
(103, 112, 45, 43, 'Demo delivery location - Bocaue Route', 316.28, NULL, NULL, NULL, NULL, NULL, 88, NULL, NULL, 103, NULL, NULL, 'demo-v1', '2025-12-03 00:00:00'),
(104, 113, 41, 39, 'Demo delivery location - Pasig Route', 186.50, NULL, NULL, NULL, NULL, NULL, 79, NULL, NULL, 85, NULL, NULL, 'demo-v1', '2025-09-19 00:00:00'),
(105, 114, 45, 43, 'Demo delivery location - Bocaue Route', 1348.48, NULL, NULL, NULL, NULL, NULL, 89, NULL, NULL, 102, NULL, NULL, 'demo-v1', '2025-02-01 00:00:00'),
(106, 115, 45, 43, 'Demo delivery location - North Valenzuela Route', 306.33, NULL, NULL, NULL, NULL, NULL, 32, NULL, NULL, 44, NULL, NULL, 'demo-v1', '2025-05-28 00:00:00'),
(107, 116, 33, 1, 'Demo delivery location - Manila Route', 240.23, NULL, NULL, NULL, NULL, NULL, 83, NULL, NULL, 68, NULL, NULL, 'demo-v1', '2026-02-10 00:00:00'),
(108, 117, 37, 35, 'Demo delivery location - Marikina Route', 761.32, NULL, NULL, NULL, NULL, NULL, 107, NULL, NULL, 107, NULL, NULL, 'demo-v1', '2025-10-08 00:00:00'),
(109, 118, 2, 2, 'Demo delivery location - Bocaue Route', 2158.04, NULL, NULL, NULL, NULL, NULL, 117, NULL, NULL, 127, NULL, NULL, 'demo-v1', '2026-06-08 00:00:00'),
(110, 119, 42, 40, 'Demo delivery location - Quezon City Route', 416.34, NULL, NULL, NULL, NULL, NULL, 74, NULL, NULL, 63, NULL, NULL, 'demo-v1', '2024-11-01 00:00:00'),
(111, 120, 44, 42, 'Demo delivery location - Malabon Route', 1062.96, NULL, NULL, NULL, NULL, NULL, 47, NULL, NULL, 49, NULL, NULL, 'demo-v1', '2025-08-13 00:00:00'),
(112, 121, 38, 36, 'Demo delivery location - Caloocan Route', 1412.92, NULL, NULL, NULL, NULL, NULL, 30, NULL, NULL, 42, NULL, NULL, 'demo-v1', '2026-07-06 00:00:00'),
(113, 122, 2, 2, 'Demo delivery location - Makati Route', 1210.77, NULL, NULL, NULL, NULL, NULL, 109, NULL, NULL, 101, NULL, NULL, 'demo-v1', '2026-07-09 00:00:00'),
(114, 123, 2, 2, 'Demo delivery location - Bocaue Route', 630.16, NULL, NULL, NULL, NULL, NULL, 122, NULL, NULL, 107, NULL, NULL, 'demo-v1', '2025-04-09 00:00:00'),
(115, 124, 47, 45, 'Demo delivery location - Bocaue Route', 376.94, NULL, NULL, NULL, NULL, NULL, 108, NULL, NULL, 96, NULL, NULL, 'demo-v1', '2026-02-09 00:00:00'),
(116, 125, 36, 34, 'Demo delivery location - Pasig Route', 195.47, NULL, NULL, NULL, NULL, NULL, 82, NULL, NULL, 68, NULL, NULL, 'demo-v1', '2025-10-26 00:00:00'),
(117, 126, 37, 35, 'Demo delivery location - Las Piñas Route', 1285.48, NULL, NULL, NULL, NULL, NULL, 138, NULL, NULL, 127, NULL, NULL, 'demo-v1', '2026-05-14 00:00:00'),
(118, 127, 34, 2, 'Demo delivery location - Makati Route', 1556.23, NULL, NULL, NULL, NULL, NULL, 86, NULL, NULL, 79, NULL, NULL, 'demo-v1', '2025-07-12 00:00:00'),
(119, 128, 36, 34, 'Demo delivery location - Manila Route', 153.92, NULL, NULL, NULL, NULL, NULL, 59, NULL, NULL, 67, NULL, NULL, 'demo-v1', '2025-03-15 00:00:00'),
(120, 129, 35, 33, 'Demo delivery location - Navotas Route', 675.88, NULL, NULL, NULL, NULL, NULL, 49, NULL, NULL, 59, NULL, NULL, 'demo-v1', '2024-12-17 00:00:00'),
(121, 132, 33, 1, '454 GRC Building, Rizal Avenue Extension, corner 9th Avenue, East Grace Park, Caloocan City, Caloocan City, Metro Manila', 8.50, NULL, NULL, NULL, NULL, NULL, 27, NULL, NULL, NULL, NULL, NULL, 'RF-TAT-v1', '2026-09-20 18:41:04'),
(122, 133, 35, 33, '454 GRC Building, Rizal Avenue Extension, corner 9th Avenue, East Grace Park, Caloocan City, Caloocan City, Metro Manila', 9.00, NULL, NULL, NULL, NULL, NULL, 27, NULL, NULL, NULL, NULL, NULL, 'RF-TAT-v1', '2026-09-21 16:53:01'),
(123, 136, 38, 36, '17 Bautista Street, Malabon, Metro Manila', 10.60, NULL, NULL, NULL, NULL, NULL, 46, NULL, NULL, NULL, NULL, NULL, 'RF-TAT-v1', '2026-10-02 13:37:13'),
(124, 139, 36, 34, '17 Bautista Street, Malabon, Metro Manila', 365.00, NULL, NULL, NULL, NULL, NULL, 30, NULL, NULL, NULL, NULL, NULL, 'RF-TAT-v1', '2026-10-03 04:01:28'),
(125, 140, 1, 46, '17 Bautista Street, Malabon, Metro Manila', 1375.00, NULL, NULL, NULL, NULL, NULL, 71, NULL, NULL, NULL, NULL, NULL, 'RF-TAT-v1', '2026-10-03 07:05:55'),
(126, 141, 39, 37, '17 Bautista Street, Malabon, Metro Manila', 1984.00, NULL, NULL, NULL, NULL, NULL, 131, NULL, NULL, NULL, NULL, NULL, 'RF-TAT-v1', '2026-10-03 07:13:49'),
(127, 142, 37, 35, '17 Bautista Street, Malabon, Metro Manila', 830.00, NULL, NULL, NULL, NULL, NULL, 50, NULL, NULL, NULL, NULL, NULL, 'RF-TAT-v1', '2026-10-03 08:41:56'),
(128, 146, 35, 33, '11 F linum street Del Nacia IV Novaliches Quezon City, Quezon City, Metro Manila', 300.00, NULL, NULL, NULL, NULL, NULL, 42, NULL, NULL, NULL, NULL, NULL, 'RF-TAT-v1', '2026-10-03 09:27:14'),
(129, 145, 36, 34, '454 GRC Building, Rizal Avenue Extension, corner 9th Avenue, East Grace Park, Caloocan City, Caloocan City, Metro Manila', 918.00, NULL, NULL, NULL, NULL, NULL, 56, NULL, NULL, NULL, NULL, NULL, 'RF-TAT-v1', '2026-10-03 09:28:10'),
(130, 144, 35, 33, '17 Bautista Street, Malabon, Metro Manila', 8.50, NULL, NULL, NULL, NULL, NULL, 30, NULL, NULL, NULL, NULL, NULL, 'RF-TAT-v1', '2026-10-03 09:29:07'),
(131, 147, 45, 43, '17 Bautista Street, Malabon, Metro Manila', 9.60, NULL, NULL, NULL, NULL, NULL, 32, NULL, NULL, NULL, NULL, NULL, 'RF-TAT-v2-incidents', '2026-10-04 21:27:10'),
(132, 148, 42, 40, '17 Bautista Street, Malabon, Metro Manila', 16.80, NULL, NULL, NULL, NULL, NULL, 36, NULL, NULL, NULL, NULL, NULL, 'RF-TAT-v2-incidents', '2026-10-14 01:46:35');

-- --------------------------------------------------------

--
-- Table structure for table `ml_delivery_history`
--

CREATE TABLE `ml_delivery_history` (
  `history_id` int(11) NOT NULL,
  `source_order_id` int(11) DEFAULT NULL,
  `delivery_area` varchar(150) NOT NULL,
  `origin_latitude` decimal(10,7) DEFAULT NULL,
  `origin_longitude` decimal(10,7) DEFAULT NULL,
  `destination_latitude` decimal(10,7) DEFAULT NULL,
  `destination_longitude` decimal(10,7) DEFAULT NULL,
  `distance_km` decimal(10,2) NOT NULL,
  `order_weight_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `priority_level` enum('Normal','Urgent') NOT NULL DEFAULT 'Normal',
  `day_of_week` tinyint(4) NOT NULL,
  `hour_of_day` tinyint(4) NOT NULL,
  `estimated_route_minutes` int(11) DEFAULT NULL,
  `actual_delivery_minutes` int(11) NOT NULL,
  `delay_minutes` int(11) NOT NULL DEFAULT 0,
  `was_delayed` tinyint(1) NOT NULL DEFAULT 0,
  `driver_id` int(11) DEFAULT NULL,
  `truck_id` int(11) DEFAULT NULL,
  `delivery_date` date NOT NULL,
  `data_source` enum('Historical','System') NOT NULL DEFAULT 'System',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ml_driver_history`
--

CREATE TABLE `ml_driver_history` (
  `history_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `completed_deliveries` int(11) NOT NULL DEFAULT 0,
  `delayed_deliveries` int(11) NOT NULL DEFAULT 0,
  `verified_violations` int(11) NOT NULL DEFAULT 0,
  `is_synthetic` tinyint(1) NOT NULL DEFAULT 1,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ml_driver_history`
--

INSERT INTO `ml_driver_history` (`history_id`, `driver_id`, `completed_deliveries`, `delayed_deliveries`, `verified_violations`, `is_synthetic`, `generated_at`) VALUES
(1, 1, 27, 4, 2, 1, '2026-10-04 20:53:45'),
(2, 2, 34, 2, 1, 1, '2026-10-04 20:53:45'),
(3, 34, 41, 3, 2, 1, '2026-10-04 20:53:45'),
(4, 35, 48, 1, 1, 1, '2026-10-04 20:53:45'),
(5, 36, 24, 4, 0, 1, '2026-10-04 20:53:45'),
(6, 37, 31, 2, 2, 1, '2026-10-04 20:53:45'),
(7, 38, 38, 5, 1, 1, '2026-10-04 20:53:45'),
(8, 39, 45, 3, 0, 1, '2026-10-04 20:53:45'),
(9, 40, 21, 1, 2, 1, '2026-10-04 20:53:45'),
(10, 41, 28, 4, 1, 1, '2026-10-04 20:53:45'),
(11, 42, 35, 2, 0, 1, '2026-10-04 20:53:45'),
(12, 43, 42, 5, 2, 1, '2026-10-04 20:53:45'),
(13, 44, 49, 3, 1, 1, '2026-10-04 20:53:45'),
(14, 45, 25, 1, 0, 1, '2026-10-04 20:53:45'),
(15, 46, 32, 4, 2, 1, '2026-10-04 20:53:45'),
(16, 47, 39, 2, 1, 1, '2026-10-04 20:53:45'),
(17, 33, 34, 5, 0, 1, '2026-10-04 20:53:45');

-- --------------------------------------------------------

--
-- Table structure for table `ml_truck_history`
--

CREATE TABLE `ml_truck_history` (
  `history_id` int(11) NOT NULL,
  `truck_id` int(11) NOT NULL,
  `completed_deliveries` int(11) NOT NULL DEFAULT 0,
  `breakdown_deliveries` int(11) NOT NULL DEFAULT 0,
  `is_synthetic` tinyint(1) NOT NULL DEFAULT 1,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ml_truck_history`
--

INSERT INTO `ml_truck_history` (`history_id`, `truck_id`, `completed_deliveries`, `breakdown_deliveries`, `is_synthetic`, `generated_at`) VALUES
(1, 35, 56, 1, 1, '2026-10-04 20:53:58'),
(2, 36, 25, 0, 1, '2026-10-04 20:53:58'),
(3, 37, 30, 3, 1, '2026-10-04 20:53:58'),
(4, 38, 35, 2, 1, '2026-10-04 20:53:58'),
(5, 39, 40, 1, 1, '2026-10-04 20:53:58'),
(6, 40, 45, 0, 1, '2026-10-04 20:53:58'),
(7, 41, 50, 3, 1, '2026-10-04 20:53:58'),
(8, 42, 55, 2, 1, '2026-10-04 20:53:58'),
(9, 43, 60, 1, 1, '2026-10-04 20:53:58'),
(10, 44, 29, 0, 1, '2026-10-04 20:53:58'),
(11, 45, 34, 3, 1, '2026-10-04 20:53:58'),
(12, 46, 39, 2, 1, '2026-10-04 20:53:58'),
(13, 47, 44, 1, 1, '2026-10-04 20:53:58'),
(14, 1, 30, 3, 1, '2026-10-04 20:53:58'),
(15, 2, 35, 2, 1, '2026-10-04 20:53:58'),
(16, 33, 46, 3, 1, '2026-10-04 20:53:58'),
(17, 34, 51, 2, 1, '2026-10-04 20:53:58');

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

CREATE TABLE `notification` (
  `notification_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status_id` int(11) DEFAULT NULL,
  `notification_type` enum('SMS','In-App') NOT NULL,
  `recipient_role` enum('Customer','Driver','Admin','Super Admin') NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `recipient_phone` varchar(20) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  `delivery_status` enum('Sent','Failed','Pending') NOT NULL DEFAULT 'Pending',
  `message` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification`
--

INSERT INTO `notification` (`notification_id`, `order_id`, `status_id`, `notification_type`, `recipient_role`, `recipient_id`, `recipient_phone`, `is_read`, `read_at`, `sent_at`, `delivery_status`, `message`) VALUES
(1, 3, NULL, 'In-App', 'Driver', 2, NULL, 1, NULL, '2026-08-18 13:14:04', 'Sent', 'You have been assigned delivery HS-20260814-891EE1. Order weight: 6.00 kg. Truck remaining capacity: 2,494.00 kg.'),
(2, 3, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-08-18 13:14:04', 'Sent', 'Order HS-20260814-891EE1 has been assigned to Efren Santos with truck NCB 5678. Truck load: 6.00 kg / 2,500.00 kg.'),
(3, 3, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-08-18 13:24:34', 'Sent', 'Driver Efren Santos accepted delivery HS-20260814-891EE1.'),
(4, 4, NULL, 'In-App', 'Driver', 2, NULL, 0, NULL, '2026-08-26 17:48:06', 'Sent', 'You have been assigned delivery HS-20260826-9C9EAF. Order weight: 6.00 kg. Truck remaining capacity: 2,494.00 kg.'),
(5, 4, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-08-26 17:48:06', 'Sent', 'Order HS-20260826-9C9EAF has been assigned to Efren Santos with truck NCB 5678. Truck load: 6.00 kg / 2,500.00 kg.'),
(6, 4, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-08-26 17:50:39', 'Sent', 'Driver Efren Santos accepted delivery HS-20260826-9C9EAF.'),
(7, 5, NULL, 'In-App', 'Driver', 2, NULL, 0, NULL, '2026-08-26 17:58:05', 'Sent', 'You have been assigned delivery HS-20260826-FE793F. Order weight: 6.00 kg. Truck remaining capacity: 2,488.00 kg.'),
(8, 5, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-08-26 17:58:05', 'Sent', 'Order HS-20260826-FE793F has been assigned to Efren Santos with truck NCB 5678. Truck load: 12.00 kg / 2,500.00 kg.'),
(9, 5, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-08-26 18:04:15', 'Sent', 'Driver Efren Santos accepted delivery HS-20260826-FE793F.'),
(10, 5, NULL, 'In-App', 'Customer', 2, NULL, 1, NULL, '2026-08-26 18:09:05', 'Sent', 'Your order HS-20260826-FE793F has been successfully delivered.'),
(11, 1, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-08-26 18:59:11', 'Sent', 'Driver Mateo Ramos accepted delivery ORD-20260809-614A5.'),
(12, 1, NULL, 'In-App', 'Customer', 1, NULL, 0, NULL, '2026-08-26 18:59:16', 'Sent', 'Your order ORD-20260809-614A5 has been successfully delivered.'),
(13, 4, NULL, 'In-App', 'Customer', 1, NULL, 0, NULL, '2026-08-26 19:20:42', 'Sent', 'Your order HS-20260826-9C9EAF has been successfully delivered.'),
(14, 2, NULL, 'In-App', 'Driver', 2, NULL, 0, NULL, '2026-08-26 19:49:47', 'Sent', 'You have been assigned delivery ORD-20260811124132-CC3454. Order weight: 6.00 kg. Truck remaining capacity: 2,494.00 kg.'),
(15, 2, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-08-26 19:49:47', 'Sent', 'Order ORD-20260811124132-CC3454 has been assigned to Efren Santos with truck NCB 5678. Truck load: 6.00 kg / 2,500.00 kg.'),
(16, 9, NULL, 'In-App', 'Driver', 2, NULL, 0, NULL, '2026-09-09 16:47:26', 'Sent', 'You have been assigned delivery HS-20260909-BBB7E5. Order weight: 7.30 kg. Truck remaining capacity: 2,486.70 kg.'),
(17, 9, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-09-09 16:47:26', 'Sent', 'Order HS-20260909-BBB7E5 has been assigned to Efren Santos with truck NCB 5678. Truck load: 13.30 kg / 2,500.00 kg.'),
(18, 132, NULL, 'In-App', 'Driver', 33, NULL, 0, NULL, '2026-09-20 18:41:04', 'Sent', 'You have been assigned delivery HS-20260920-20EE0C. Order weight: 8.50 kg. Truck remaining capacity: 4,481.50 kg. ML estimated delivery time: 27 min.'),
(19, 132, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-09-20 18:41:04', 'Sent', 'Order HS-20260920-20EE0C has been assigned to Adrian Cruz with truck NAA 1234. Truck load: 8.50 kg / 4,490.00 kg. ML predicted TAT: 27 min.'),
(20, 132, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-09-21 16:29:22', 'Sent', 'Driver Adrian Cruz accepted delivery HS-20260920-20EE0C.'),
(21, 133, NULL, 'In-App', 'Driver', 35, NULL, 0, NULL, '2026-09-21 16:53:01', 'Sent', 'You have been assigned delivery HS-20260921-8B164C. Order weight: 9.00 kg. Truck remaining capacity: 991.00 kg. ML estimated delivery time: 27 min.'),
(22, 133, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-09-21 16:53:01', 'Sent', 'Order HS-20260921-8B164C has been assigned to Carlo Mendoza with truck HS03-727. Truck load: 9.00 kg / 1,000.00 kg. ML predicted TAT: 27 min.'),
(23, 133, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-09-21 16:54:53', 'Sent', 'Driver Carlo Mendoza accepted delivery HS-20260921-8B164C.'),
(24, 133, NULL, 'In-App', 'Customer', 2, NULL, 0, NULL, '2026-09-21 16:55:22', 'Sent', 'Your order HS-20260921-8B164C has been successfully delivered.'),
(25, 136, NULL, 'In-App', 'Driver', 38, NULL, 0, NULL, '2026-10-02 13:37:13', 'Sent', 'You have been assigned delivery HS-20261002-97CD95. Order weight: 10.60 kg. Truck remaining capacity: 2,489.40 kg. ML estimated delivery time: 46 min.'),
(26, 136, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-10-02 13:37:13', 'Sent', 'Order HS-20261002-97CD95 has been assigned to Francis Dela Cruz with truck HS06-513. Truck load: 10.60 kg / 2,500.00 kg. ML predicted TAT: 46 min.'),
(27, 136, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-10-02 13:38:20', 'Sent', 'Driver Francis Dela Cruz accepted delivery HS-20261002-97CD95.'),
(28, 136, NULL, 'In-App', 'Customer', 1, NULL, 0, NULL, '2026-10-02 13:39:08', 'Sent', 'Your order HS-20261002-97CD95 has been successfully delivered.'),
(29, 139, NULL, 'In-App', 'Customer', 1, '0912345679', 0, NULL, '2026-10-03 03:58:44', 'Sent', 'Your order HS-20261002-14972B has been approved. It is now being prepared for delivery assignment.'),
(30, 139, NULL, 'In-App', 'Super Admin', 1, NULL, 0, NULL, '2026-10-03 03:58:44', 'Sent', 'Order HS-20261002-14972B has been approved and is ready for delivery assignment.'),
(31, 130, NULL, 'In-App', 'Customer', 2, '09943957889', 0, NULL, '2026-10-03 04:00:06', 'Sent', 'Your order HS-20260909-5468A9 has been cancelled.'),
(32, 130, NULL, 'In-App', 'Super Admin', 1, NULL, 0, NULL, '2026-10-03 04:00:06', 'Sent', 'Order HS-20260909-5468A9 has been cancelled.'),
(33, 139, NULL, 'In-App', 'Driver', 36, NULL, 0, NULL, '2026-10-03 04:01:28', 'Sent', 'You have been assigned delivery HS-20261002-14972B. Order weight: 365.00 kg. Truck remaining capacity: 1,135.00 kg. ML estimated delivery time: 30 min.'),
(34, 139, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-10-03 04:01:28', 'Sent', 'Order HS-20261002-14972B has been assigned to Daniel Reyes with truck HS04-292. Truck load: 365.00 kg / 1,500.00 kg. ML predicted TAT: 30 min.'),
(35, 139, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-10-03 04:19:39', 'Sent', 'Driver Daniel Reyes accepted delivery HS-20261002-14972B.'),
(36, 139, NULL, 'In-App', 'Customer', 1, NULL, 0, NULL, '2026-10-03 04:29:51', 'Sent', 'Your order HS-20261002-14972B has been successfully delivered.'),
(37, 140, NULL, 'In-App', 'Customer', 1, '0912345679', 0, NULL, '2026-10-03 07:04:49', 'Sent', 'Your order HS-20261002-B7C0B4 has been approved. It is now being prepared for delivery assignment.'),
(38, 140, NULL, 'In-App', 'Super Admin', 1, NULL, 0, NULL, '2026-10-03 07:04:49', 'Sent', 'Order HS-20261002-B7C0B4 has been approved and is ready for delivery assignment.'),
(39, 140, NULL, 'In-App', 'Driver', 1, NULL, 0, NULL, '2026-10-03 07:05:55', 'Sent', 'You have been assigned delivery HS-20261002-B7C0B4. Order weight: 1,375.00 kg. Truck remaining capacity: 2,625.00 kg. ML estimated delivery time: 1h 11m.'),
(40, 140, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-10-03 07:05:55', 'Sent', 'Order HS-20261002-B7C0B4 has been assigned to Mateo Ramos with truck HS16-121. Truck load: 1,375.00 kg / 4,000.00 kg. ML predicted TAT: 1h 11m.'),
(41, 140, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-10-03 07:06:41', 'Sent', 'Driver Mateo Ramos accepted delivery HS-20261002-B7C0B4.'),
(42, 140, NULL, 'In-App', 'Customer', 1, NULL, 0, NULL, '2026-10-03 07:07:03', 'Sent', 'Your order HS-20261002-B7C0B4 has been successfully delivered.'),
(43, 141, NULL, 'In-App', 'Customer', 1, '0912345679', 0, NULL, '2026-10-03 07:13:24', 'Sent', 'Your order HS-20261002-94EB5A has been approved. It is now being prepared for delivery assignment.'),
(44, 141, NULL, 'In-App', 'Super Admin', 1, NULL, 0, NULL, '2026-10-03 07:13:24', 'Sent', 'Order HS-20261002-94EB5A has been approved and is ready for delivery assignment.'),
(45, 141, NULL, 'In-App', 'Driver', 39, NULL, 0, NULL, '2026-10-03 07:13:49', 'Sent', 'You have been assigned delivery HS-20261002-94EB5A. Order weight: 1,984.00 kg. Truck remaining capacity: 1,016.00 kg. ML estimated delivery time: 2h 11m.'),
(46, 141, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-10-03 07:13:49', 'Sent', 'Order HS-20261002-94EB5A has been assigned to Gabriel Flores with truck HS07-832. Truck load: 1,984.00 kg / 3,000.00 kg. ML predicted TAT: 2h 11m.'),
(47, 141, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-10-03 07:15:00', 'Sent', 'Driver Gabriel Flores accepted delivery HS-20261002-94EB5A.'),
(48, 141, NULL, 'In-App', 'Customer', 1, NULL, 0, NULL, '2026-10-03 07:15:15', 'Sent', 'Your order HS-20261002-94EB5A has been successfully delivered.'),
(49, 142, NULL, 'In-App', 'Customer', 1, '0912345679', 0, NULL, '2026-10-03 08:40:58', 'Sent', 'Your order HS-20261003-6A6BAE has been approved. It is now being prepared for delivery assignment.'),
(50, 142, NULL, 'In-App', 'Super Admin', 1, NULL, 0, NULL, '2026-10-03 08:40:58', 'Sent', 'Order HS-20261003-6A6BAE has been approved and is ready for delivery assignment.'),
(51, 142, NULL, 'In-App', 'Driver', 37, NULL, 0, NULL, '2026-10-03 08:41:56', 'Sent', 'You have been assigned delivery HS-20261003-6A6BAE. Order weight: 830.00 kg. Truck remaining capacity: 1,170.00 kg. ML estimated delivery time: 50 min.'),
(52, 142, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-10-03 08:41:56', 'Sent', 'Order HS-20261003-6A6BAE has been assigned to Eduardo Garcia with truck HS05-527. Truck load: 830.00 kg / 2,000.00 kg. ML predicted TAT: 50 min.'),
(53, 142, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-10-03 08:44:22', 'Sent', 'Driver Eduardo Garcia accepted delivery HS-20261003-6A6BAE.'),
(54, 142, NULL, 'In-App', 'Customer', 1, NULL, 0, NULL, '2026-10-03 08:44:50', 'Sent', 'Your order HS-20261003-6A6BAE has been successfully delivered.'),
(55, 144, NULL, 'In-App', 'Customer', 1, '0912345679', 0, NULL, '2026-10-03 09:13:47', 'Sent', 'Your order HS-20261003-DA7F99 has been approved. It is now being prepared for delivery assignment.'),
(56, 144, NULL, 'In-App', 'Super Admin', 1, NULL, 0, NULL, '2026-10-03 09:13:47', 'Sent', 'Order HS-20261003-DA7F99 has been approved and is ready for delivery assignment.'),
(57, 143, NULL, 'In-App', 'Customer', 1, '0912345679', 0, NULL, '2026-10-03 09:26:39', 'Sent', 'Your order HS-20261003-400FF6 has been approved. It is now being prepared for delivery assignment.'),
(58, 143, NULL, 'In-App', 'Super Admin', 1, NULL, 0, NULL, '2026-10-03 09:26:39', 'Sent', 'Order HS-20261003-400FF6 has been approved and is ready for delivery assignment.'),
(59, 145, NULL, 'In-App', 'Customer', 2, '09943957889', 0, NULL, '2026-10-03 09:26:42', 'Sent', 'Your order HS-20261003-AFE97C has been approved. It is now being prepared for delivery assignment.'),
(60, 145, NULL, 'In-App', 'Super Admin', 1, NULL, 0, NULL, '2026-10-03 09:26:42', 'Sent', 'Order HS-20261003-AFE97C has been approved and is ready for delivery assignment.'),
(61, 146, NULL, 'In-App', 'Customer', 5, '09266937896', 0, NULL, '2026-10-03 09:26:45', 'Sent', 'Your order HS-20261003-C3A89C has been approved. It is now being prepared for delivery assignment.'),
(62, 146, NULL, 'In-App', 'Super Admin', 1, NULL, 0, NULL, '2026-10-03 09:26:45', 'Sent', 'Order HS-20261003-C3A89C has been approved and is ready for delivery assignment.'),
(63, 146, NULL, 'In-App', 'Driver', 35, NULL, 0, NULL, '2026-10-03 09:27:14', 'Sent', 'You have been assigned delivery HS-20261003-C3A89C. Order weight: 300.00 kg. Truck remaining capacity: 700.00 kg. ML estimated delivery time: 42 min.'),
(64, 146, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-10-03 09:27:14', 'Sent', 'Order HS-20261003-C3A89C has been assigned to Carlo Mendoza with truck HS03-727. Truck load: 300.00 kg / 1,000.00 kg. ML predicted TAT: 42 min.'),
(65, 145, NULL, 'In-App', 'Driver', 36, NULL, 0, NULL, '2026-10-03 09:28:10', 'Sent', 'You have been assigned delivery HS-20261003-AFE97C. Order weight: 918.00 kg. Truck remaining capacity: 582.00 kg. ML estimated delivery time: 56 min.'),
(66, 145, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-10-03 09:28:10', 'Sent', 'Order HS-20261003-AFE97C has been assigned to Daniel Reyes with truck HS04-292. Truck load: 918.00 kg / 1,500.00 kg. ML predicted TAT: 56 min.'),
(67, 144, NULL, 'In-App', 'Driver', 35, NULL, 0, NULL, '2026-10-03 09:29:07', 'Sent', 'You have been assigned delivery HS-20261003-DA7F99. Order weight: 8.50 kg. Truck remaining capacity: 691.50 kg. ML estimated delivery time: 30 min.'),
(68, 144, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-10-03 09:29:07', 'Sent', 'Order HS-20261003-DA7F99 has been assigned to Carlo Mendoza with truck HS03-727. Truck load: 308.50 kg / 1,000.00 kg. ML predicted TAT: 30 min.'),
(69, 147, NULL, 'In-App', 'Customer', 1, '0912345679', 0, NULL, '2026-10-04 20:26:14', 'Sent', 'Your order HS-20261004-188D8E has been approved. It is now being prepared for delivery assignment.'),
(70, 147, NULL, 'In-App', 'Super Admin', 1, NULL, 0, NULL, '2026-10-04 20:26:14', 'Sent', 'Order HS-20261004-188D8E has been approved and is ready for delivery assignment.'),
(71, 147, NULL, 'In-App', 'Driver', 45, NULL, 0, NULL, '2026-10-04 21:27:10', 'Sent', 'You have been assigned delivery HS-20261004-188D8E. Order weight: 9.60 kg. Truck remaining capacity: 2,490.40 kg. ML estimated delivery time: 32 min.'),
(72, 147, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-10-04 21:27:10', 'Sent', 'Order HS-20261004-188D8E has been assigned to Marco Villanueva with truck HS13-937. Truck load: 9.60 kg / 2,500.00 kg. ML predicted TAT: 32 min.'),
(73, 147, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-10-04 21:27:44', 'Sent', 'Driver Marco Villanueva accepted delivery HS-20261004-188D8E.'),
(74, 147, NULL, 'In-App', 'Super Admin', 1, NULL, 0, NULL, '2026-10-04 21:37:53', 'Sent', 'Delay/incident reported for order HS-20261004-188D8E by Marco Villanueva: Vehicle Breakdown (estimated 120 min delay).'),
(75, 147, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-10-04 21:37:53', 'Sent', 'Delay/incident reported for order HS-20261004-188D8E by Marco Villanueva: Vehicle Breakdown (estimated 120 min delay).'),
(76, 148, NULL, 'In-App', 'Customer', 1, '0912345679', 0, NULL, '2026-10-14 01:29:11', 'Sent', 'Your order HS-20261013-FC5A7D has been approved. It is now being prepared for delivery assignment.'),
(77, 148, NULL, 'In-App', 'Super Admin', 1, NULL, 0, NULL, '2026-10-14 01:29:11', 'Sent', 'Order HS-20261013-FC5A7D has been approved and is ready for delivery assignment.'),
(78, 148, NULL, 'In-App', 'Driver', 42, NULL, 0, NULL, '2026-10-14 01:46:35', 'Sent', 'You have been assigned delivery HS-20261013-FC5A7D. Order weight: 16.80 kg. Truck remaining capacity: 983.20 kg. ML estimated delivery time: 36 min.'),
(79, 148, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-10-14 01:46:35', 'Sent', 'Order HS-20261013-FC5A7D has been assigned to Jerome Navarro with truck HS10-517. Truck load: 16.80 kg / 1,000.00 kg. ML predicted TAT: 36 min.'),
(80, 148, NULL, 'In-App', 'Admin', 2, NULL, 0, NULL, '2026-10-14 02:03:45', 'Sent', 'Driver Jerome Navarro accepted delivery HS-20261013-FC5A7D.');

-- --------------------------------------------------------

--
-- Table structure for table `order`
--

CREATE TABLE `order` (
  `order_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `truck_id` int(11) DEFAULT NULL,
  `route_id` int(11) DEFAULT NULL,
  `item_description` text NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `urgent_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `total_weight` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_weight_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `order_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `priority_level` enum('Normal','Urgent') NOT NULL DEFAULT 'Normal',
  `completion_status` enum('Pending','Approved','Assigned','Accepted','In Transit','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order`
--

INSERT INTO `order` (`order_id`, `order_number`, `customer_id`, `driver_id`, `truck_id`, `route_id`, `item_description`, `subtotal`, `urgent_fee`, `total_amount`, `total_weight`, `total_weight_kg`, `order_date`, `expected_delivery_date`, `priority_level`, `completion_status`, `created_at`, `approved_at`, `assigned_at`, `accepted_at`, `started_at`, `completed_at`, `cancelled_at`) VALUES
(1, 'ORD-20260809-614A5', 1, 1, 1, NULL, '50 plastic egg trays', 0.00, 0.00, 250.00, 0.00, 0.00, '2026-08-09', '2026-08-09', 'Urgent', 'Completed', '2026-08-09 22:15:58', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'ORD-20260811124132-CC3454', 1, 2, 2, NULL, 'Plastic Fruit Tray 250g (500 pcs) x 1', 0.00, 0.00, 1000.00, 6.00, 6.00, '2026-08-11', '2026-08-12', 'Normal', 'Assigned', '2026-08-11 20:41:32', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'HS-20260814-891EE1', 1, 2, 2, NULL, 'Plastic Fruit Tray 250g (500 pcs) x 1', 0.00, 0.00, 1000.00, 6.00, 6.00, '2026-08-14', '2026-08-18', 'Normal', 'Completed', '2026-08-14 13:12:22', NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'HS-20260826-9C9EAF', 1, 2, 2, NULL, 'Plastic Fruit Tray 250g (500 pcs) x 1', 0.00, 0.00, 1000.00, 6.00, 6.00, '2026-08-26', '2026-08-31', 'Normal', 'Completed', '2026-08-26 17:24:34', NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'HS-20260826-FE793F', 2, 2, 2, NULL, 'Plastic Fruit Tray 250g (500 pcs) x 1', 0.00, 0.00, 1000.00, 6.00, 6.00, '2026-08-26', '2026-08-31', 'Normal', 'Completed', '2026-08-26 17:57:10', NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'HS-20260826-C9FC21', 2, NULL, NULL, NULL, 'Plastic Fruit Tray 250g (500 pcs) x 1', 0.00, 0.00, 1000.00, 6.00, 6.00, '2026-08-26', '2026-09-01', 'Normal', 'Approved', '2026-08-26 19:14:46', NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'HS-20260826-513E32', 2, NULL, NULL, NULL, 'Plastic Fruit Tray 250g (500 pcs) x 1', 0.00, 0.00, 1000.00, 6.00, 6.00, '2026-08-26', '2026-08-27', 'Urgent', 'Approved', '2026-08-26 19:58:47', NULL, NULL, NULL, NULL, NULL, NULL),
(8, 'HS-20260908-3F4E1B', 2, NULL, NULL, NULL, 'Bubble Wrap Roll 40\" x 100m x 1, Clear Plastic Drinking Cups 16oz (Carton of 1000) x 1', 0.00, 0.00, 2000.00, 11.50, 11.50, '2026-09-08', '2026-09-10', 'Normal', 'Approved', '2026-09-09 04:37:29', NULL, NULL, NULL, NULL, NULL, NULL),
(9, 'HS-20260909-BBB7E5', 2, 2, 2, 1, 'Clear PVC Shrink Film Roll 15-inch x 1, HDPE Squeeze Bottles 250ml (Box of 100) x 1', 0.00, 0.00, 1370.00, 7.30, 7.30, '2026-09-09', '2026-09-10', 'Normal', 'Assigned', '2026-09-09 16:45:46', NULL, NULL, NULL, NULL, NULL, NULL),
(10, 'DEMO-20251126-00001', 1, 1, 43, 8, 'Historical ML Training Delivery #1', 0.00, 0.00, 25948.80, 1208.70, 1208.70, '2025-11-26', '2025-11-28', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'DEMO-20260113-00002', 1, 39, 37, 9, 'Historical ML Training Delivery #2', 0.00, 0.00, 9410.06, 329.01, 329.01, '2026-01-13', '2026-01-16', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(12, 'DEMO-20250314-00003', 1, 38, 36, 16, 'Historical ML Training Delivery #3', 0.00, 0.00, 19091.18, 341.69, 341.69, '2025-03-14', '2025-03-17', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(13, 'DEMO-20251126-00004', 1, 40, 38, 3, 'Historical ML Training Delivery #4', 0.00, 0.00, 28053.29, 1342.59, 1342.59, '2025-11-26', '2025-11-30', 'Urgent', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(14, 'DEMO-20250401-00005', 1, 45, 43, 5, 'Historical ML Training Delivery #5', 0.00, 0.00, 27779.91, 2141.86, 2141.86, '2025-04-01', '2025-04-06', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(15, 'DEMO-20250220-00006', 1, 42, 40, 5, 'Historical ML Training Delivery #6', 0.00, 0.00, 8707.01, 456.24, 456.24, '2025-02-20', '2025-02-23', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(16, 'DEMO-20241012-00007', 1, 2, 2, 3, 'Historical ML Training Delivery #7', 0.00, 0.00, 23246.14, 1480.88, 1480.88, '2024-10-12', '2024-10-17', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(17, 'DEMO-20241119-00008', 1, 40, 38, 14, 'Historical ML Training Delivery #8', 0.00, 0.00, 32595.39, 590.85, 590.85, '2024-11-19', '2024-11-24', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(18, 'DEMO-20241010-00009', 1, 33, 1, 16, 'Historical ML Training Delivery #9', 0.00, 0.00, 1416.92, 2386.39, 2386.39, '2024-10-10', '2024-10-11', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(19, 'DEMO-20250824-00010', 1, 37, 35, 12, 'Historical ML Training Delivery #10', 0.00, 0.00, 30664.19, 1918.27, 1918.27, '2025-08-24', '2025-08-28', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(20, 'DEMO-20251014-00011', 1, 37, 35, 16, 'Historical ML Training Delivery #11', 0.00, 0.00, 18290.19, 1051.10, 1051.10, '2025-10-14', '2025-10-18', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(21, 'DEMO-20251209-00012', 1, 43, 41, 11, 'Historical ML Training Delivery #12', 0.00, 0.00, 40208.76, 674.25, 674.25, '2025-12-09', '2025-12-11', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(22, 'DEMO-20260809-00013', 1, 46, 44, 15, 'Historical ML Training Delivery #13', 0.00, 0.00, 5995.31, 519.29, 519.29, '2026-08-09', '2026-08-13', 'Urgent', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(23, 'DEMO-20260802-00014', 1, 1, 45, 7, 'Historical ML Training Delivery #14', 0.00, 0.00, 37139.55, 1614.49, 1614.49, '2026-08-02', '2026-08-06', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(24, 'DEMO-20241126-00015', 1, 43, 41, 12, 'Historical ML Training Delivery #15', 0.00, 0.00, 4982.98, 755.21, 755.21, '2024-11-26', '2024-11-30', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(25, 'DEMO-20260124-00016', 1, 45, 43, 6, 'Historical ML Training Delivery #16', 0.00, 0.00, 3050.45, 1835.93, 1835.93, '2026-01-24', '2026-01-28', 'Urgent', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(26, 'DEMO-20250329-00017', 1, 34, 2, 7, 'Historical ML Training Delivery #17', 0.00, 0.00, 11313.77, 2096.43, 2096.43, '2025-03-29', '2025-04-02', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(27, 'DEMO-20260621-00018', 1, 44, 42, 5, 'Historical ML Training Delivery #18', 0.00, 0.00, 38476.41, 1132.98, 1132.98, '2026-06-21', '2026-06-23', 'Urgent', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(28, 'DEMO-20250917-00019', 1, 42, 40, 9, 'Historical ML Training Delivery #19', 0.00, 0.00, 41366.62, 546.51, 546.51, '2025-09-17', '2025-09-20', 'Urgent', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(29, 'DEMO-20251213-00020', 1, 42, 40, 10, 'Historical ML Training Delivery #20', 0.00, 0.00, 17439.86, 767.90, 767.90, '2025-12-13', '2025-12-16', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(30, 'DEMO-20251201-00021', 1, 39, 37, 16, 'Historical ML Training Delivery #21', 0.00, 0.00, 6748.47, 1302.72, 1302.72, '2025-12-01', '2025-12-06', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(31, 'DEMO-20250722-00022', 1, 47, 45, 5, 'Historical ML Training Delivery #22', 0.00, 0.00, 36284.04, 1524.74, 1524.74, '2025-07-22', '2025-07-23', 'Urgent', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(32, 'DEMO-20251029-00023', 1, 36, 34, 3, 'Historical ML Training Delivery #23', 0.00, 0.00, 9006.50, 1280.18, 1280.18, '2025-10-29', '2025-10-31', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(33, 'DEMO-20250109-00024', 1, 42, 40, 7, 'Historical ML Training Delivery #24', 0.00, 0.00, 9461.49, 631.01, 631.01, '2025-01-09', '2025-01-10', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(34, 'DEMO-20250521-00025', 1, 37, 35, 2, 'Historical ML Training Delivery #25', 0.00, 0.00, 33554.15, 477.28, 477.28, '2025-05-21', '2025-05-25', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(35, 'DEMO-20251101-00026', 1, 46, 44, 3, 'Historical ML Training Delivery #26', 0.00, 0.00, 7037.48, 1700.83, 1700.83, '2025-11-01', '2025-11-02', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(36, 'DEMO-20260404-00027', 1, 43, 41, 7, 'Historical ML Training Delivery #27', 0.00, 0.00, 42991.75, 1046.06, 1046.06, '2026-04-04', '2026-04-06', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(37, 'DEMO-20260707-00028', 1, 1, 37, 9, 'Historical ML Training Delivery #28', 0.00, 0.00, 46140.47, 1984.79, 1984.79, '2026-07-07', '2026-07-08', 'Urgent', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(38, 'DEMO-20260330-00029', 1, 2, 2, 7, 'Historical ML Training Delivery #29', 0.00, 0.00, 15555.69, 2269.66, 2269.66, '2026-03-30', '2026-04-02', 'Urgent', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(39, 'DEMO-20251230-00030', 1, 45, 43, 6, 'Historical ML Training Delivery #30', 0.00, 0.00, 38707.35, 2076.27, 2076.27, '2025-12-30', '2025-12-31', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(40, 'DEMO-20250126-00031', 1, 45, 43, 11, 'Historical ML Training Delivery #31', 0.00, 0.00, 12156.15, 1836.65, 1836.65, '2025-01-26', '2025-01-27', 'Urgent', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(41, 'DEMO-20250717-00032', 1, 41, 39, 10, 'Historical ML Training Delivery #32', 0.00, 0.00, 839.03, 708.42, 708.42, '2025-07-17', '2025-07-20', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(42, 'DEMO-20250702-00033', 1, 40, 38, 9, 'Historical ML Training Delivery #33', 0.00, 0.00, 39670.37, 1328.39, 1328.39, '2025-07-02', '2025-07-03', 'Urgent', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(43, 'DEMO-20260228-00034', 1, 41, 39, 14, 'Historical ML Training Delivery #34', 0.00, 0.00, 40583.04, 1822.25, 1822.25, '2026-02-28', '2026-03-04', 'Urgent', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(44, 'DEMO-20250424-00035', 1, 35, 33, 14, 'Historical ML Training Delivery #35', 0.00, 0.00, 5641.39, 167.70, 167.70, '2025-04-24', '2025-04-28', 'Normal', 'Completed', '2026-09-09 16:58:35', NULL, NULL, NULL, NULL, NULL, NULL),
(45, 'DEMO-20260108-00036', 1, 42, 40, 3, 'Historical ML Training Delivery #36', 0.00, 0.00, 18889.17, 807.44, 807.44, '2026-01-08', '2026-01-11', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(46, 'DEMO-20250623-00037', 1, 1, 42, 14, 'Historical ML Training Delivery #37', 0.00, 0.00, 12980.83, 1119.52, 1119.52, '2025-06-23', '2025-06-24', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(47, 'DEMO-20260614-00038', 1, 36, 34, 12, 'Historical ML Training Delivery #38', 0.00, 0.00, 36570.91, 844.86, 844.86, '2026-06-14', '2026-06-16', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(48, 'DEMO-20250716-00039', 1, 1, 41, 4, 'Historical ML Training Delivery #39', 0.00, 0.00, 38111.23, 937.69, 937.69, '2025-07-16', '2025-07-19', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(49, 'DEMO-20260410-00040', 1, 33, 1, 10, 'Historical ML Training Delivery #40', 0.00, 0.00, 9243.05, 2376.00, 2376.00, '2026-04-10', '2026-04-11', 'Urgent', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(50, 'DEMO-20241114-00041', 1, 42, 40, 11, 'Historical ML Training Delivery #41', 0.00, 0.00, 28512.43, 623.23, 623.23, '2024-11-14', '2024-11-15', 'Urgent', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(51, 'DEMO-20250114-00042', 1, 39, 37, 15, 'Historical ML Training Delivery #42', 0.00, 0.00, 44014.67, 2469.47, 2469.47, '2025-01-14', '2025-01-18', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(52, 'DEMO-20250127-00043', 1, 33, 1, 6, 'Historical ML Training Delivery #43', 0.00, 0.00, 47994.45, 513.74, 513.74, '2025-01-27', '2025-01-30', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(53, 'DEMO-20260616-00044', 1, 43, 41, 14, 'Historical ML Training Delivery #44', 0.00, 0.00, 9358.23, 152.58, 152.58, '2026-06-16', '2026-06-17', 'Urgent', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(54, 'DEMO-20251212-00045', 1, 43, 41, 7, 'Historical ML Training Delivery #45', 0.00, 0.00, 20698.19, 471.04, 471.04, '2025-12-12', '2025-12-16', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(55, 'DEMO-20250916-00046', 1, 37, 35, 3, 'Historical ML Training Delivery #46', 0.00, 0.00, 20593.99, 1432.40, 1432.40, '2025-09-16', '2025-09-20', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(56, 'DEMO-20250131-00047', 1, 47, 45, 12, 'Historical ML Training Delivery #47', 0.00, 0.00, 24618.83, 857.82, 857.82, '2025-01-31', '2025-02-01', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(57, 'DEMO-20250611-00048', 1, 35, 33, 4, 'Historical ML Training Delivery #48', 0.00, 0.00, 48722.48, 836.92, 836.92, '2025-06-11', '2025-06-15', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(58, 'DEMO-20260203-00049', 1, 40, 38, 15, 'Historical ML Training Delivery #49', 0.00, 0.00, 49998.84, 959.53, 959.53, '2026-02-03', '2026-02-04', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(59, 'DEMO-20260102-00050', 1, 44, 42, 14, 'Historical ML Training Delivery #50', 0.00, 0.00, 1096.13, 522.00, 522.00, '2026-01-02', '2026-01-04', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(60, 'DEMO-20250404-00051', 1, 42, 40, 16, 'Historical ML Training Delivery #51', 0.00, 0.00, 19962.37, 598.34, 598.34, '2025-04-04', '2025-04-06', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(61, 'DEMO-20260325-00052', 1, 39, 37, 5, 'Historical ML Training Delivery #52', 0.00, 0.00, 1779.75, 811.91, 811.91, '2026-03-25', '2026-03-28', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(62, 'DEMO-20251211-00053', 1, 45, 43, 5, 'Historical ML Training Delivery #53', 0.00, 0.00, 39994.53, 418.56, 418.56, '2025-12-11', '2025-12-13', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(63, 'DEMO-20250903-00054', 1, 42, 40, 6, 'Historical ML Training Delivery #54', 0.00, 0.00, 45929.44, 186.31, 186.31, '2025-09-03', '2025-09-08', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(64, 'DEMO-20250211-00055', 1, 35, 33, 15, 'Historical ML Training Delivery #55', 0.00, 0.00, 48642.06, 786.11, 786.11, '2025-02-11', '2025-02-13', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(65, 'DEMO-20241227-00056', 1, 37, 35, 13, 'Historical ML Training Delivery #56', 0.00, 0.00, 1453.24, 638.47, 638.47, '2024-12-27', '2024-12-29', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(66, 'DEMO-20260405-00057', 1, 43, 41, 6, 'Historical ML Training Delivery #57', 0.00, 0.00, 11467.68, 852.99, 852.99, '2026-04-05', '2026-04-07', 'Urgent', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(67, 'DEMO-20241120-00058', 1, 47, 45, 16, 'Historical ML Training Delivery #58', 0.00, 0.00, 30408.44, 1733.48, 1733.48, '2024-11-20', '2024-11-23', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(68, 'DEMO-20250820-00059', 1, 2, 2, 5, 'Historical ML Training Delivery #59', 0.00, 0.00, 37972.12, 932.73, 932.73, '2025-08-20', '2025-08-22', 'Urgent', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(69, 'DEMO-20241016-00060', 1, 46, 44, 16, 'Historical ML Training Delivery #60', 0.00, 0.00, 40086.97, 1169.93, 1169.93, '2024-10-16', '2024-10-20', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(70, 'DEMO-20260408-00061', 1, 38, 36, 3, 'Historical ML Training Delivery #61', 0.00, 0.00, 10128.65, 1875.18, 1875.18, '2026-04-08', '2026-04-10', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(71, 'DEMO-20241206-00062', 1, 44, 42, 11, 'Historical ML Training Delivery #62', 0.00, 0.00, 2450.27, 693.14, 693.14, '2024-12-06', '2024-12-08', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(72, 'DEMO-20241031-00063', 1, 38, 36, 2, 'Historical ML Training Delivery #63', 0.00, 0.00, 15284.98, 1431.76, 1431.76, '2024-10-31', '2024-11-03', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(73, 'DEMO-20250417-00064', 1, 35, 33, 6, 'Historical ML Training Delivery #64', 0.00, 0.00, 32521.12, 708.04, 708.04, '2025-04-17', '2025-04-22', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(74, 'DEMO-20260106-00065', 1, 33, 1, 8, 'Historical ML Training Delivery #65', 0.00, 0.00, 27046.80, 287.43, 287.43, '2026-01-06', '2026-01-09', 'Urgent', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(75, 'DEMO-20241108-00066', 1, 41, 39, 14, 'Historical ML Training Delivery #66', 0.00, 0.00, 15096.24, 2672.43, 2672.43, '2024-11-08', '2024-11-11', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(76, 'DEMO-20260101-00067', 1, 34, 2, 5, 'Historical ML Training Delivery #67', 0.00, 0.00, 35279.82, 511.28, 511.28, '2026-01-01', '2026-01-05', 'Urgent', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(77, 'DEMO-20241026-00068', 1, 44, 42, 5, 'Historical ML Training Delivery #68', 0.00, 0.00, 7556.66, 1645.11, 1645.11, '2024-10-26', '2024-10-31', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(78, 'DEMO-20250322-00069', 1, 34, 2, 9, 'Historical ML Training Delivery #69', 0.00, 0.00, 24303.90, 215.88, 215.88, '2025-03-22', '2025-03-27', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(79, 'DEMO-20241111-00070', 1, 40, 38, 15, 'Historical ML Training Delivery #70', 0.00, 0.00, 8946.08, 2499.89, 2499.89, '2024-11-11', '2024-11-16', 'Urgent', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(80, 'DEMO-20250107-00071', 1, 47, 45, 7, 'Historical ML Training Delivery #71', 0.00, 0.00, 34435.06, 1898.26, 1898.26, '2025-01-07', '2025-01-11', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(81, 'DEMO-20260130-00072', 1, 41, 39, 14, 'Historical ML Training Delivery #72', 0.00, 0.00, 29503.79, 2725.03, 2725.03, '2026-01-30', '2026-01-31', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(82, 'DEMO-20260715-00073', 1, 2, 2, 2, 'Historical ML Training Delivery #73', 0.00, 0.00, 32674.44, 1265.62, 1265.62, '2026-07-15', '2026-07-19', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(83, 'DEMO-20250309-00074', 1, 46, 44, 3, 'Historical ML Training Delivery #74', 0.00, 0.00, 17472.35, 2118.42, 2118.42, '2025-03-09', '2025-03-13', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(84, 'DEMO-20260103-00075', 1, 44, 42, 2, 'Historical ML Training Delivery #75', 0.00, 0.00, 27660.77, 841.64, 841.64, '2026-01-03', '2026-01-06', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(85, 'DEMO-20260502-00076', 1, 42, 40, 13, 'Historical ML Training Delivery #76', 0.00, 0.00, 38195.92, 742.89, 742.89, '2026-05-02', '2026-05-03', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(86, 'DEMO-20260107-00077', 1, 45, 43, 10, 'Historical ML Training Delivery #77', 0.00, 0.00, 38872.54, 597.97, 597.97, '2026-01-07', '2026-01-11', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(87, 'DEMO-20250826-00078', 1, 35, 33, 16, 'Historical ML Training Delivery #78', 0.00, 0.00, 15787.79, 421.30, 421.30, '2025-08-26', '2025-08-28', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(88, 'DEMO-20260330-00079', 1, 39, 37, 2, 'Historical ML Training Delivery #79', 0.00, 0.00, 33971.02, 1487.02, 1487.02, '2026-03-30', '2026-04-02', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(89, 'DEMO-20260727-00080', 1, 1, 35, 8, 'Historical ML Training Delivery #80', 0.00, 0.00, 29545.71, 1555.12, 1555.12, '2026-07-27', '2026-07-29', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(90, 'DEMO-20250102-00081', 1, 42, 40, 13, 'Historical ML Training Delivery #81', 0.00, 0.00, 24293.38, 949.00, 949.00, '2025-01-02', '2025-01-06', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(91, 'DEMO-20250527-00082', 1, 44, 42, 3, 'Historical ML Training Delivery #82', 0.00, 0.00, 28206.68, 229.00, 229.00, '2025-05-27', '2025-05-30', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(92, 'DEMO-20260702-00083', 1, 40, 38, 4, 'Historical ML Training Delivery #83', 0.00, 0.00, 696.60, 1400.10, 1400.10, '2026-07-02', '2026-07-04', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(93, 'DEMO-20241019-00084', 1, 47, 45, 5, 'Historical ML Training Delivery #84', 0.00, 0.00, 37117.44, 1242.77, 1242.77, '2024-10-19', '2024-10-20', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(94, 'DEMO-20260530-00085', 1, 44, 42, 4, 'Historical ML Training Delivery #85', 0.00, 0.00, 19439.88, 926.32, 926.32, '2026-05-30', '2026-06-02', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(95, 'DEMO-20260620-00086', 1, 43, 41, 6, 'Historical ML Training Delivery #86', 0.00, 0.00, 3852.72, 664.29, 664.29, '2026-06-20', '2026-06-24', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(96, 'DEMO-20251215-00087', 1, 37, 35, 3, 'Historical ML Training Delivery #87', 0.00, 0.00, 25075.26, 1031.30, 1031.30, '2025-12-15', '2025-12-16', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(97, 'DEMO-20250210-00088', 1, 40, 38, 5, 'Historical ML Training Delivery #88', 0.00, 0.00, 17599.71, 796.46, 796.46, '2025-02-10', '2025-02-12', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(98, 'DEMO-20241228-00089', 1, 33, 1, 2, 'Historical ML Training Delivery #89', 0.00, 0.00, 32728.91, 1237.33, 1237.33, '2024-12-28', '2024-12-30', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(99, 'DEMO-20241105-00090', 1, 40, 38, 12, 'Historical ML Training Delivery #90', 0.00, 0.00, 22695.76, 2162.72, 2162.72, '2024-11-05', '2024-11-09', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(100, 'DEMO-20250818-00091', 1, 35, 33, 16, 'Historical ML Training Delivery #91', 0.00, 0.00, 16539.64, 287.34, 287.34, '2025-08-18', '2025-08-20', 'Urgent', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(101, 'DEMO-20260730-00092', 1, 34, 2, 4, 'Historical ML Training Delivery #92', 0.00, 0.00, 22877.83, 1684.56, 1684.56, '2026-07-30', '2026-08-01', 'Urgent', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(102, 'DEMO-20260722-00093', 1, 2, 2, 11, 'Historical ML Training Delivery #93', 0.00, 0.00, 47971.42, 1995.82, 1995.82, '2026-07-22', '2026-07-23', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(103, 'DEMO-20250911-00094', 1, 33, 1, 12, 'Historical ML Training Delivery #94', 0.00, 0.00, 36818.54, 1323.38, 1323.38, '2025-09-11', '2025-09-15', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(104, 'DEMO-20260621-00095', 1, 2, 2, 8, 'Historical ML Training Delivery #95', 0.00, 0.00, 5992.94, 1351.48, 1351.48, '2026-06-21', '2026-06-25', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(105, 'DEMO-20260314-00096', 1, 35, 33, 7, 'Historical ML Training Delivery #96', 0.00, 0.00, 8298.08, 919.32, 919.32, '2026-03-14', '2026-03-16', 'Urgent', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(106, 'DEMO-20250916-00097', 1, 33, 1, 15, 'Historical ML Training Delivery #97', 0.00, 0.00, 28432.46, 556.24, 556.24, '2025-09-16', '2025-09-19', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(107, 'DEMO-20251211-00098', 1, 35, 33, 11, 'Historical ML Training Delivery #98', 0.00, 0.00, 47267.56, 597.91, 597.91, '2025-12-11', '2025-12-16', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(108, 'DEMO-20260121-00099', 1, 35, 33, 15, 'Historical ML Training Delivery #99', 0.00, 0.00, 12474.83, 875.83, 875.83, '2026-01-21', '2026-01-25', 'Urgent', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(109, 'DEMO-20250329-00100', 1, 47, 45, 14, 'Historical ML Training Delivery #100', 0.00, 0.00, 42515.94, 1283.70, 1283.70, '2025-03-29', '2025-04-02', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(110, 'DEMO-20260720-00101', 1, 44, 42, 7, 'Historical ML Training Delivery #101', 0.00, 0.00, 15990.55, 1149.56, 1149.56, '2026-07-20', '2026-07-22', 'Urgent', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(111, 'DEMO-20250329-00102', 1, 43, 41, 3, 'Historical ML Training Delivery #102', 0.00, 0.00, 16161.09, 420.64, 420.64, '2025-03-29', '2025-04-01', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(112, 'DEMO-20251202-00103', 1, 45, 43, 15, 'Historical ML Training Delivery #103', 0.00, 0.00, 12151.65, 316.28, 316.28, '2025-12-02', '2025-12-05', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(113, 'DEMO-20250918-00104', 1, 41, 39, 8, 'Historical ML Training Delivery #104', 0.00, 0.00, 20619.07, 186.50, 186.50, '2025-09-18', '2025-09-19', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(114, 'DEMO-20250131-00105', 1, 45, 43, 15, 'Historical ML Training Delivery #105', 0.00, 0.00, 21794.05, 1348.48, 1348.48, '2025-01-31', '2025-02-02', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(115, 'DEMO-20250527-00106', 1, 45, 43, 2, 'Historical ML Training Delivery #106', 0.00, 0.00, 26419.11, 306.33, 306.33, '2025-05-27', '2025-05-29', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(116, 'DEMO-20260209-00107', 1, 33, 1, 7, 'Historical ML Training Delivery #107', 0.00, 0.00, 40404.54, 240.23, 240.23, '2026-02-09', '2026-02-13', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(117, 'DEMO-20251007-00108', 1, 37, 35, 10, 'Historical ML Training Delivery #108', 0.00, 0.00, 2310.18, 761.32, 761.32, '2025-10-07', '2025-10-11', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(118, 'DEMO-20260607-00109', 1, 2, 2, 15, 'Historical ML Training Delivery #109', 0.00, 0.00, 31044.53, 2158.04, 2158.04, '2026-06-07', '2026-06-09', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(119, 'DEMO-20241031-00110', 1, 42, 40, 6, 'Historical ML Training Delivery #110', 0.00, 0.00, 38255.81, 416.34, 416.34, '2024-10-31', '2024-11-01', 'Urgent', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(120, 'DEMO-20250812-00111', 1, 44, 42, 3, 'Historical ML Training Delivery #111', 0.00, 0.00, 8061.66, 1062.96, 1062.96, '2025-08-12', '2025-08-14', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(121, 'DEMO-20260705-00112', 1, 38, 36, 5, 'Historical ML Training Delivery #112', 0.00, 0.00, 25326.95, 1412.92, 1412.92, '2026-07-05', '2026-07-07', 'Urgent', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(122, 'DEMO-20260708-00113', 1, 2, 2, 9, 'Historical ML Training Delivery #113', 0.00, 0.00, 25851.66, 1210.77, 1210.77, '2026-07-08', '2026-07-12', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(123, 'DEMO-20250408-00114', 1, 2, 2, 15, 'Historical ML Training Delivery #114', 0.00, 0.00, 9531.06, 630.16, 630.16, '2025-04-08', '2025-04-11', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(124, 'DEMO-20260208-00115', 1, 47, 45, 15, 'Historical ML Training Delivery #115', 0.00, 0.00, 44006.65, 376.94, 376.94, '2026-02-08', '2026-02-11', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(125, 'DEMO-20251025-00116', 1, 36, 34, 8, 'Historical ML Training Delivery #116', 0.00, 0.00, 37898.83, 195.47, 195.47, '2025-10-25', '2025-10-28', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(126, 'DEMO-20260513-00117', 1, 37, 35, 12, 'Historical ML Training Delivery #117', 0.00, 0.00, 26132.67, 1285.48, 1285.48, '2026-05-13', '2026-05-14', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(127, 'DEMO-20250711-00118', 1, 34, 2, 9, 'Historical ML Training Delivery #118', 0.00, 0.00, 46981.94, 1556.23, 1556.23, '2025-07-11', '2025-07-16', 'Urgent', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(128, 'DEMO-20250314-00119', 1, 36, 34, 7, 'Historical ML Training Delivery #119', 0.00, 0.00, 2453.61, 153.92, 153.92, '2025-03-14', '2025-03-19', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(129, 'DEMO-20241216-00120', 1, 35, 33, 4, 'Historical ML Training Delivery #120', 0.00, 0.00, 45696.97, 675.88, 675.88, '2024-12-16', '2024-12-20', 'Normal', 'Completed', '2026-09-09 16:58:36', NULL, NULL, NULL, NULL, NULL, NULL),
(130, 'HS-20260909-5468A9', 2, NULL, NULL, 17, 'HDPE Squeeze Bottles 250ml (Box of 100) x 1, Hinged Clamshell Container 8x8 (Pack of 150) x 1', 0.00, 0.00, 1440.00, 8.00, 8.00, '2026-09-09', '2026-09-20', 'Normal', 'Cancelled', '2026-09-09 18:56:51', NULL, NULL, NULL, NULL, NULL, '2026-10-03 04:00:06'),
(131, 'HS-20260920-DBC063', 2, NULL, NULL, 18, 'Mesh Paper Tube Protective Cover for Fragile Items - 200mm 10packs x 1, Plastic Cutlery Sets - Spoon & Fork (Box of 500) x 1', 0.00, 0.00, 719.00, 4.00, 4.00, '2026-09-20', '2026-09-21', 'Normal', 'Approved', '2026-09-20 15:04:10', NULL, NULL, NULL, NULL, NULL, NULL),
(132, 'HS-20260920-20EE0C', 2, 33, 1, 19, 'Clear LDPE Poly Bags 12x18 (Pack of 1000) x 1', 950.00, 50.00, 1000.00, 8.50, 8.50, '2026-09-20', NULL, 'Urgent', 'In Transit', '2026-09-20 18:08:40', NULL, NULL, NULL, NULL, NULL, NULL),
(133, 'HS-20260921-8B164C', 2, 35, 33, 20, 'Polypropylene Strapping Band Roll 15mm x 1', 1150.00, 50.00, 1200.00, 9.00, 9.00, '2026-09-21', NULL, 'Urgent', 'Completed', '2026-09-21 16:51:10', NULL, NULL, NULL, NULL, NULL, NULL),
(134, 'HS-20260922-3F365B', 3, NULL, NULL, 21, 'Plastic Zip Lock Bags 8x12 (Pack of 1000) x 1', 880.00, 0.00, 880.00, 5.40, 5.40, '2026-09-22', NULL, 'Normal', 'Approved', '2026-09-22 20:02:40', NULL, NULL, NULL, NULL, NULL, NULL),
(135, 'HS-20260925-3FC645', 1, NULL, NULL, 22, 'Heavy Duty Garbage Bags 36x48 (Pack of 100) x 1', 750.00, 50.00, 800.00, 9.00, 9.00, '2026-09-26', NULL, 'Urgent', 'Approved', '2026-09-26 00:21:21', NULL, NULL, NULL, NULL, NULL, NULL),
(136, 'HS-20261002-97CD95', 1, 38, 36, 23, 'Plastic Hangers Heavy Duty (Box of 100) x 1, Plastic Zip Lock Bags 8x12 (Pack of 1000) x 1', 1530.00, 0.00, 1530.00, 10.60, 10.60, '2026-10-02', NULL, 'Normal', 'Completed', '2026-10-02 13:35:13', NULL, NULL, NULL, NULL, NULL, NULL),
(137, 'HS-20261002-0CEF18', 1, NULL, NULL, 24, 'Clear Plastic Drinking Cups 16oz (Carton of 1000) x 1', 1350.00, 50.00, 1400.00, 8.00, 8.00, '2026-10-02', NULL, 'Urgent', 'Approved', '2026-10-02 20:05:09', NULL, NULL, NULL, NULL, NULL, NULL),
(138, 'HS-20261002-52E759', 1, NULL, NULL, 25, 'Bubble Wrap Roll 40\" x 100m x 1, Heavy Duty Garbage Bags 36x48 (Pack of 100) x 1, Heavy Duty Plastic Pallet Standard x 1, LLDPE Industrial Stretch Film Roll 500mm x 1', 4180.00, 0.00, 4180.00, 32.70, 32.70, '2026-10-02', NULL, 'Normal', 'Approved', '2026-10-02 20:10:43', NULL, NULL, NULL, NULL, NULL, NULL),
(139, 'HS-20261002-14972B', 1, 36, 34, 26, 'Bubble Wrap Roll 40\" x 100m x 50, HDPE Chemical Drum 200L Lid Type x 20', 69500.00, 50.00, 69550.00, 365.00, 365.00, '2026-10-03', NULL, 'Urgent', 'Completed', '2026-10-03 03:58:13', '2026-10-03 03:58:44', NULL, NULL, NULL, NULL, NULL),
(140, 'HS-20261002-B7C0B4', 1, 1, 46, 27, 'Clear Plastic Drinking Cups 16oz (Carton of 1000) x 50, Correx Fluted Plastic Sheets 4x8ft (Pack of 10) x 50, Polycarbonate Sheet Clear 2mm 4x8ft x 50', 332500.00, 0.00, 332500.00, 1375.00, 1375.00, '2026-10-03', NULL, 'Normal', 'Completed', '2026-10-03 07:04:24', '2026-10-03 07:04:49', NULL, NULL, NULL, NULL, NULL),
(141, 'HS-20261002-94EB5A', 1, 39, 37, 28, 'Clear Plastic Drinking Cups 16oz (Carton of 1000) x 248', 334800.00, 0.00, 334800.00, 1984.00, 1984.00, '2026-10-03', NULL, 'Normal', 'Completed', '2026-10-03 07:12:11', '2026-10-03 07:13:24', NULL, NULL, NULL, NULL, NULL),
(142, 'HS-20261003-6A6BAE', 1, 37, 35, 29, 'HDPE Chemical Drum 200L Lid Type x 40, Heavy Duty Garbage Bags 36x48 (Pack of 100) x 50', 111500.00, 0.00, 111500.00, 830.00, 830.00, '2026-10-03', NULL, 'Normal', 'Completed', '2026-10-03 08:40:38', '2026-10-03 08:40:58', NULL, NULL, NULL, NULL, NULL),
(143, 'HS-20261003-400FF6', 1, NULL, NULL, 30, 'Correx Fluted Plastic Sheets 4x8ft (Pack of 10) x 1', 2100.00, 0.00, 2100.00, 12.00, 12.00, '2026-10-03', NULL, 'Normal', 'Approved', '2026-10-03 09:12:39', '2026-10-03 09:26:39', NULL, NULL, NULL, NULL, NULL),
(144, 'HS-20261003-DA7F99', 1, 35, 33, 31, 'Clear LDPE Poly Bags 12x18 (Pack of 1000) x 1', 950.00, 0.00, 950.00, 8.50, 8.50, '2026-10-03', NULL, 'Normal', 'Assigned', '2026-10-03 09:13:14', '2026-10-03 09:13:47', NULL, NULL, NULL, NULL, NULL),
(145, 'HS-20261003-AFE97C', 2, 36, 34, 32, 'Correx Fluted Plastic Sheets 4x8ft (Pack of 10) x 39, Heavy Duty Garbage Bags 36x48 (Pack of 100) x 50', 119400.00, 0.00, 119400.00, 918.00, 918.00, '2026-10-03', NULL, 'Normal', 'Assigned', '2026-10-03 09:22:27', '2026-10-03 09:26:42', NULL, NULL, NULL, NULL, NULL),
(146, 'HS-20261003-C3A89C', 5, 35, 33, 33, 'Bubble Wrap Roll 40\" x 100m x 50, HDPE Squeeze Bottles 250ml (Box of 100) x 50', 60000.00, 0.00, 60000.00, 300.00, 300.00, '2026-10-03', NULL, 'Normal', 'Assigned', '2026-10-03 09:26:18', '2026-10-03 09:26:45', NULL, NULL, NULL, NULL, NULL),
(147, 'HS-20261004-188D8E', 1, 45, 43, 34, 'Clear PVC Shrink Film Roll 15-inch x 2', 1640.00, 50.00, 1690.00, 9.60, 9.60, '2026-10-04', NULL, 'Urgent', 'In Transit', '2026-10-04 20:25:41', '2026-10-04 20:26:14', NULL, NULL, NULL, NULL, NULL),
(148, 'HS-20261013-FC5A7D', 1, 42, 40, 35, 'Bubble Wrap Roll 40\" x 100m x 1, Clear LDPE Poly Bags 12x18 (Pack of 1000) x 1, Clear PVC Shrink Film Roll 15-inch x 1', 2420.00, 0.00, 2420.00, 16.80, 16.80, '2026-10-14', NULL, 'Normal', 'In Transit', '2026-10-14 01:17:10', '2026-10-14 01:29:11', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_item`
--

CREATE TABLE `order_item` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_weight_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_weight_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_item`
--

INSERT INTO `order_item` (`order_item_id`, `order_id`, `product_id`, `quantity`, `unit_price`, `unit_weight_kg`, `total_weight_kg`, `subtotal`, `created_at`) VALUES
(1, 2, 1, 1, 1000.00, 6.00, 6.00, 1000.00, '2026-08-11 20:41:32'),
(2, 3, 1, 1, 1000.00, 6.00, 6.00, 1000.00, '2026-08-14 13:12:22'),
(3, 4, 1, 1, 1000.00, 6.00, 6.00, 1000.00, '2026-08-26 17:24:34'),
(4, 5, 1, 1, 1000.00, 6.00, 6.00, 1000.00, '2026-08-26 17:57:10'),
(5, 6, 1, 1, 1000.00, 6.00, 6.00, 1000.00, '2026-08-26 19:14:46'),
(6, 7, 1, 1, 1000.00, 6.00, 6.00, 1000.00, '2026-08-26 19:58:47'),
(7, 8, 81, 1, 650.00, 3.50, 3.50, 650.00, '2026-09-09 04:37:29'),
(8, 8, 95, 1, 1350.00, 8.00, 8.00, 1350.00, '2026-09-09 04:37:29'),
(9, 9, 89, 1, 820.00, 0.00, 0.00, 820.00, '2026-09-09 16:45:46'),
(10, 9, 97, 1, 550.00, 0.00, 0.00, 550.00, '2026-09-09 16:45:46'),
(11, 130, 97, 1, 550.00, 0.00, 0.00, 550.00, '2026-09-09 18:56:51'),
(12, 130, 85, 1, 890.00, 0.00, 0.00, 890.00, '2026-09-09 18:56:51'),
(13, 131, 2, 1, 239.00, 0.00, 0.00, 239.00, '2026-09-20 15:04:10'),
(14, 131, 84, 1, 480.00, 0.00, 0.00, 480.00, '2026-09-20 15:04:10'),
(15, 132, 4, 1, 950.00, 0.00, 0.00, 950.00, '2026-09-20 18:08:40'),
(16, 133, 100, 1, 1150.00, 0.00, 0.00, 1150.00, '2026-09-21 16:51:10'),
(17, 134, 99, 1, 880.00, 0.00, 0.00, 880.00, '2026-09-22 20:02:40'),
(18, 135, 87, 1, 750.00, 0.00, 0.00, 750.00, '2026-09-26 00:21:21'),
(19, 136, 92, 1, 650.00, 0.00, 0.00, 650.00, '2026-10-02 13:35:13'),
(20, 136, 99, 1, 880.00, 0.00, 0.00, 880.00, '2026-10-02 13:35:13'),
(21, 137, 95, 1, 1350.00, 0.00, 0.00, 1350.00, '2026-10-02 20:05:09'),
(22, 138, 81, 1, 650.00, 0.00, 0.00, 650.00, '2026-10-02 20:10:43'),
(23, 138, 87, 1, 750.00, 0.00, 0.00, 750.00, '2026-10-02 20:10:43'),
(24, 138, 3, 1, 2400.00, 0.00, 0.00, 2400.00, '2026-10-02 20:10:43'),
(25, 138, 83, 1, 380.00, 0.00, 0.00, 380.00, '2026-10-02 20:10:43'),
(26, 139, 81, 50, 650.00, 0.00, 0.00, 32500.00, '2026-10-03 03:58:13'),
(27, 139, 82, 20, 1850.00, 0.00, 0.00, 37000.00, '2026-10-03 03:58:13'),
(28, 140, 95, 50, 1350.00, 0.00, 0.00, 67500.00, '2026-10-03 07:04:24'),
(29, 140, 93, 50, 2100.00, 0.00, 0.00, 105000.00, '2026-10-03 07:04:24'),
(30, 140, 98, 50, 3200.00, 0.00, 0.00, 160000.00, '2026-10-03 07:04:24'),
(31, 141, 95, 248, 1350.00, 0.00, 0.00, 334800.00, '2026-10-03 07:12:11'),
(32, 142, 82, 40, 1850.00, 0.00, 0.00, 74000.00, '2026-10-03 08:40:38'),
(33, 142, 87, 50, 750.00, 0.00, 0.00, 37500.00, '2026-10-03 08:40:38'),
(34, 143, 93, 1, 2100.00, 0.00, 0.00, 2100.00, '2026-10-03 09:12:39'),
(35, 144, 4, 1, 950.00, 0.00, 0.00, 950.00, '2026-10-03 09:13:14'),
(36, 145, 93, 39, 2100.00, 0.00, 0.00, 81900.00, '2026-10-03 09:22:27'),
(37, 145, 87, 50, 750.00, 0.00, 0.00, 37500.00, '2026-10-03 09:22:27'),
(38, 146, 81, 50, 650.00, 0.00, 0.00, 32500.00, '2026-10-03 09:26:18'),
(39, 146, 97, 50, 550.00, 0.00, 0.00, 27500.00, '2026-10-03 09:26:18'),
(40, 147, 89, 2, 820.00, 0.00, 0.00, 1640.00, '2026-10-04 20:25:41'),
(41, 148, 81, 1, 650.00, 0.00, 0.00, 650.00, '2026-10-14 01:17:10'),
(42, 148, 4, 1, 950.00, 0.00, 0.00, 950.00, '2026-10-14 01:17:10'),
(43, 148, 89, 1, 820.00, 0.00, 0.00, 820.00, '2026-10-14 01:17:10');

-- --------------------------------------------------------

--
-- Table structure for table `phone_verification_otp`
--

CREATE TABLE `phone_verification_otp` (
  `otp_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempt_count` int(11) NOT NULL DEFAULT 0,
  `last_sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `product_id` int(11) NOT NULL,
  `product_code` varchar(50) DEFAULT NULL,
  `product_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `product_image` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `weight_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `status` enum('Available','Unavailable') NOT NULL DEFAULT 'Available',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`product_id`, `product_code`, `product_name`, `description`, `product_image`, `price`, `weight_kg`, `stock_quantity`, `status`, `created_at`, `updated_at`) VALUES
(1, 'PLA-001', 'Plastic Fruit Tray 250g (500 pcs)', 'Fresh-View 250g Clear PET Berry & Produce Clamshell  Show off the vibrant color and natural freshness of your produce with our 250g clear PET fruit tray. Made from food-grade, crystal-clear plastic, this lightweight yet durable clamshell features secure snap-lock closures to keep delicate fruits like strawberries, blueberries, and cherry tomatoes safe during storage and transport. Built-in side vents allow airflow to extend shelf life and prevent moisture buildup.', 'product_6a7af05717fa97.97535632.webp', 1000.00, 6.00, 44, 'Available', '2026-08-11 19:49:27', '2026-08-26 19:58:47'),
(2, 'PLA-002', 'Mesh Paper Tube Protective Cover for Fragile Items - 200mm 10packs', 'Honeycomb sleeves are an innovative and eco-friendly packaging solution designed to provide exceptional protection for fragile items. Made from high-quality kraft paper, these sleeves feature a unique honeycomb structure that expands to securely wrap around products like glass bottles, ceramics, jars, and other breakable goods. The expandable design ensures a snug fit, offering excellent cushioning and shock absorption during storage or transit. Lightweight, durable, and biodegradable, honeycomb sleeves are an ideal sustainable alternative to plastic-based packaging materials. They are easy to use, requiring no additional tape or adhesives, and their compact form saves storage space. Perfect for businesses and individuals seeking a practical yet environmentally conscious packaging option, honeycomb sleeves deliver both functionality and sustainability.', 'product_b49600788198e0ce.png', 239.00, 1.00, 149, 'Available', '2026-09-03 23:30:57', '2026-09-20 15:04:10'),
(3, 'PLA-003', 'Heavy Duty Plastic Pallet Standard', 'Industrial-grade rackable plastic pallet designed for warehouse storage and forklift transport.', 'product_809aa186d5ea511e.jpg', 2400.00, 18.00, 79, 'Available', '2026-09-09 02:13:29', '2026-10-02 20:10:43'),
(4, 'PLA-004', 'Clear LDPE Poly Bags 12x18 (Pack of 1000)', 'Commercial clear low-density polyethylene bags for general packaging and manufacturing storage.', 'product_7d3a2230c73eb4da.webp', 950.00, 8.50, 497, 'Available', '2026-09-09 02:16:07', '2026-10-14 01:17:10'),
(5, 'PLA-005', 'Plastic Nursery Pots 6-inch (Box of 200)', 'Injection-molded polypropylene pots with drainage holes for commercial plant nurseries.', 'product_45d6331c8d2363f4.jpg', 720.00, 6.00, 300, 'Available', '2026-09-09 02:18:26', '2026-09-09 02:18:26'),
(78, 'PLA-006', 'PP Microwavable Container 500ml (Case of 250)', 'Heat-resistant clear food containers with snap-on lids for meal prep and food service.', 'product_8f7e9f2fc562e147.jpg', 1100.00, 7.20, 450, 'Available', '2026-09-09 02:48:55', '2026-09-09 03:35:35'),
(79, 'PLA-007', 'PET Preforms 28mm Neck (Box of 1000)', 'Raw bottle preforms used for stretch blow molding beverage containers.', 'product_81f352afa698e9fe.jpg', 3100.00, 22.00, 120, 'Available', '2026-09-09 02:48:55', '2026-09-09 03:26:47'),
(80, 'PLA-008', 'Stackable Plastic Storage Crate Large', 'Rigid ventilated polypropylene crate for agriculture harvesting and logistics.', 'product_8ccfc0e02b3f11bb.jpg', 450.00, 2.80, 250, 'Available', '2026-09-09 02:48:55', '2026-09-09 03:36:40'),
(81, 'PLA-009', 'Bubble Wrap Roll 40\" x 100m', 'Protective cushioning air bubble film roll for shipping and fragile items packaging.', 'product_8aec619cd37964aa.webp', 650.00, 3.50, 77, 'Available', '2026-09-09 02:48:55', '2026-10-14 01:17:10'),
(82, 'PLA-010', 'HDPE Chemical Drum 200L Lid Type', 'High-density blue plastic drum designed for bulk liquid chemicals and oil storage.', 'product_cbdabb24a269b059.png', 1850.00, 9.50, 50, 'Available', '2026-09-09 02:48:55', '2026-10-04 21:19:45'),
(83, 'PLA-011', 'LLDPE Industrial Stretch Film Roll 500mm', 'High-clarity pallet wrapping stretch film for securing cargo loads during transport.', 'product_74589fc7179030a8.jpg', 380.00, 2.20, 599, 'Available', '2026-09-09 02:48:55', '2026-10-02 20:10:43'),
(84, 'PLA-012', 'Plastic Cutlery Sets - Spoon & Fork (Box of 500)', 'Bulk polystyrene (PS) disposable spoon and fork sets for fast-food operations.', 'product_9d32bc067a339959.jpg', 480.00, 3.00, 399, 'Available', '2026-09-09 02:48:55', '2026-09-20 15:04:10'),
(85, 'PLA-013', 'Hinged Clamshell Container 8x8 (Pack of 150)', 'Eco-friendly clear PET clamshell boxes for bakery products and takeout meals.', 'product_9c65a2df92ee0199.jpg', 890.00, 5.50, 349, 'Available', '2026-09-09 02:48:55', '2026-09-09 18:56:51'),
(86, 'PLA-014', 'Plastic Shopping T-Shirt Bags (Carton of 2000)', 'Durable HDPE grocery shopping carry bags with handles for retail outlets.', 'product_b3c9649a0e8c3a9e.jpg', 620.00, 6.80, 800, 'Available', '2026-09-09 02:48:55', '2026-09-09 03:32:11'),
(87, 'PLA-015', 'Heavy Duty Garbage Bags 36x48 (Pack of 100)', 'Thick black recycled LDPE trash liners for institutional and commercial waste disposal.', 'product_1ad0cb81be65d325.jpg', 750.00, 9.00, 198, 'Available', '2026-09-09 02:48:55', '2026-10-03 09:22:27'),
(88, 'PLA-016', 'Plastic Jerry Can 20L with Cap', 'Leak-proof HDPE container for water, fuel, or liquid chemical transport.', 'product_4a0273a1fefd91a4.jpg', 220.00, 1.10, 500, 'Available', '2026-09-09 02:48:55', '2026-09-09 03:30:21'),
(89, 'PLA-017', 'Clear PVC Shrink Film Roll 15-inch', 'High-clarity heat shrink film for secondary packaging and product bundling.', 'product_fa8218c574d77dca.jpg', 820.00, 4.80, 146, 'Available', '2026-09-09 02:48:55', '2026-10-14 01:17:10'),
(90, 'PLA-018', 'PP Woven Sacks 50kg Capacity (Bundle of 100)', 'Heavy-duty polypropylene woven bags for grain, fertilizer, and agricultural feed storage.', 'product_5ee2492bb2591c21.jpg', 900.00, 7.00, 1000, 'Available', '2026-09-09 02:48:55', '2026-09-09 03:36:04'),
(91, 'PLA-019', 'PET Water Bottles 500ml with Caps (Box of 200)', 'Food-safe clear PET plastic bottles complete with screw-top tamper-evident caps.', 'product_0975cb819ed0f4bc.webp', 580.00, 3.80, 600, 'Available', '2026-09-09 02:48:55', '2026-09-09 03:27:36'),
(92, 'PLA-020', 'Plastic Hangers Heavy Duty (Box of 100)', 'Molded plastic clothes hangers with notch hooks for garment retail and industrial laundries.', 'product_106ad10a6ea4f870.jpg', 650.00, 5.20, 219, 'Available', '2026-09-09 02:48:55', '2026-10-02 13:35:13'),
(93, 'PLA-021', 'Correx Fluted Plastic Sheets 4x8ft (Pack of 10)', 'Lightweight polypropylene twinwall fluted sheets for signage, construction, and packing.', 'product_6f4a680c65b8be4e.jpg', 2100.00, 12.00, 150, 'Available', '2026-09-09 02:48:55', '2026-10-04 21:18:59'),
(94, 'PLA-022', 'Plastic Water Bucket 20L Heavy Duty', 'Rigid molded plastic utility bucket with reinforced metal bail handle.', 'product_bc12232ebb11c607.jpg', 160.00, 0.95, 400, 'Available', '2026-09-09 02:48:55', '2026-09-09 03:32:48'),
(95, 'PLA-023', 'Clear Plastic Drinking Cups 16oz (Carton of 1000)', 'Disposable cold drink PET cups for beverages, milk tea, and food stalls.', 'product_9b274096e1fc9a0d.jpg', 1350.00, 8.00, 0, 'Available', '2026-09-09 02:48:55', '2026-10-03 07:12:11'),
(96, 'PLA-024', 'Plastic Industrial Jerry Can 5L (Case of 24)', 'Compact HDPE liquid storage containers equipped with tamper-evident caps.', 'product_5d1b8084f53c57ba.jpg', 780.00, 4.80, 250, 'Available', '2026-09-09 02:48:55', '2026-09-09 03:29:27'),
(97, 'PLA-025', 'HDPE Squeeze Bottles 250ml (Box of 100)', 'Flexible squeeze bottles designed for sauces, condiments, or industrial lubricants.', 'product_9aaebe6ddf273586.jpg', 550.00, 2.50, 128, 'Available', '2026-09-09 02:48:55', '2026-10-03 09:26:18'),
(98, 'PLA-026', 'Polycarbonate Sheet Clear 2mm 4x8ft', 'High-impact resistant transparent PC sheet for safety guards and roofing panels.', 'product_d99deb7feb25ec35.jpg', 3200.00, 7.50, 0, 'Available', '2026-09-09 02:48:55', '2026-10-03 07:04:24'),
(99, 'PLA-027', 'Plastic Zip Lock Bags 8x12 (Pack of 1000)', 'Reclosable re-sealable polyethylene bags for electronic components and small parts packaging.', 'product_1be6794fdeccba18.jpg', 880.00, 5.40, 398, 'Available', '2026-09-09 02:48:55', '2026-10-02 13:35:13'),
(100, 'PLA-028', 'Polypropylene Strapping Band Roll 15mm', 'High-tensile strength PP strap roll for manual and machine box banding/bundling.', 'product_437d63d6f021c358.jpg', 1150.00, 9.00, 139, 'Available', '2026-09-09 02:48:55', '2026-09-21 16:51:10'),
(101, 'PLA-029', 'Plastic Pill/Medicine Bottles 100cc (Box of 200)', 'Pharmaceutical-grade HDPE amber/white bottles with child-resistant caps.', 'product_9a517567cd3423be.jpg', 690.00, 3.10, 320, 'Available', '2026-09-09 02:48:55', '2026-09-09 03:31:05');

-- --------------------------------------------------------

--
-- Table structure for table `receipt`
--

CREATE TABLE `receipt` (
  `receipt_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `delivery_time` datetime NOT NULL,
  `received_by` varchar(150) DEFAULT NULL,
  `signature` varchar(100) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `photo_proof_path` varchar(255) DEFAULT NULL,
  `delivery_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `receipt`
--

INSERT INTO `receipt` (`receipt_id`, `order_id`, `receipt_number`, `delivery_time`, `received_by`, `signature`, `signature_path`, `photo_proof_path`, `delivery_notes`, `created_at`) VALUES
(1, 133, 'POD-20260921085522-133', '2026-09-21 16:55:22', NULL, '', NULL, 'pod_order_133_8083e6e1dbebbac3e7620deab7263ad7.jpg', 'Proof of delivery uploaded by driver.', '2026-09-22 20:23:04'),
(2, 136, 'POD-20261002053908-136', '2026-10-02 13:39:08', NULL, NULL, NULL, 'pod_order_136_fe254af4e38cb85ff657b20724f70279.jpg', 'Proof of delivery uploaded by driver.', '2026-10-02 13:39:08'),
(3, 139, 'POD-20261002202951-139', '2026-10-03 04:29:51', NULL, NULL, NULL, 'pod_order_139_e36559323cfe4a64c078306102c54a07.png', 'Proof of delivery uploaded by driver.', '2026-10-03 04:29:51'),
(4, 140, 'POD-20261002230703-140', '2026-10-03 07:07:03', NULL, NULL, NULL, 'pod_order_140_ffaf0bb000d5e4a6e258863b0b3f75e8.png', 'Proof of delivery uploaded by driver.', '2026-10-03 07:07:03'),
(5, 141, 'POD-20261002231515-141', '2026-10-03 07:15:15', NULL, NULL, NULL, 'pod_order_141_ee485b89ecbcf1f613760dc3888e6db0.png', 'Proof of delivery uploaded by driver.', '2026-10-03 07:15:15'),
(6, 142, 'POD-20261003004450-142', '2026-10-03 08:44:50', NULL, NULL, NULL, 'pod_order_142_1e29fdcd411d4aecd553986bab598e33.png', 'Proof of delivery uploaded by driver.', '2026-10-03 08:44:50');

-- --------------------------------------------------------

--
-- Table structure for table `route`
--

CREATE TABLE `route` (
  `route_id` int(11) NOT NULL,
  `route_code` varchar(50) NOT NULL,
  `route_type` enum('Direct','Optimized') NOT NULL DEFAULT 'Direct',
  `path_details` text NOT NULL,
  `estimated_distance` decimal(10,2) NOT NULL,
  `estimated_time` varchar(50) NOT NULL,
  `estimated_time_minutes` int(11) DEFAULT NULL,
  `predicted_time_minutes` int(11) DEFAULT NULL,
  `delay_risk` enum('Low','Medium','High') DEFAULT NULL,
  `optimization_score` decimal(10,4) DEFAULT NULL,
  `waypoints` int(11) DEFAULT NULL,
  `stop_sequence` longtext DEFAULT NULL,
  `routing_provider` varchar(50) DEFAULT NULL,
  `model_version` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `route`
--

INSERT INTO `route` (`route_id`, `route_code`, `route_type`, `path_details`, `estimated_distance`, `estimated_time`, `estimated_time_minutes`, `predicted_time_minutes`, `delay_risk`, `optimization_score`, `waypoints`, `stop_sequence`, `routing_provider`, `model_version`, `created_at`) VALUES
(1, 'RT-20260909-8211CD', 'Direct', 'Hong Sing LKRR Commercial → Macanas Street, Karisma Village, Malabon, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6874515, 120.954487\r\nRoad distance: 5.44 km\r\nEstimated travel time: 10 min\r\nRouting provider: OSRM', 5.44, '10 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(2, 'DEMO-RT-001', 'Direct', 'Hong Sing LKRR Commercial → North Valenzuela Route | Demo ML training route', 6.50, '18 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(3, 'DEMO-RT-002', 'Direct', 'Hong Sing LKRR Commercial → Malabon Route | Demo ML training route', 9.80, '27 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(4, 'DEMO-RT-003', 'Direct', 'Hong Sing LKRR Commercial → Navotas Route | Demo ML training route', 12.40, '35 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(5, 'DEMO-RT-004', 'Direct', 'Hong Sing LKRR Commercial → Caloocan Route | Demo ML training route', 15.20, '42 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(6, 'DEMO-RT-005', 'Direct', 'Hong Sing LKRR Commercial → Quezon City Route | Demo ML training route', 21.50, '58 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(7, 'DEMO-RT-006', 'Direct', 'Hong Sing LKRR Commercial → Manila Route | Demo ML training route', 18.30, '51 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(8, 'DEMO-RT-007', 'Direct', 'Hong Sing LKRR Commercial → Pasig Route | Demo ML training route', 29.40, '75 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(9, 'DEMO-RT-008', 'Direct', 'Hong Sing LKRR Commercial → Makati Route | Demo ML training route', 25.80, '68 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(10, 'DEMO-RT-009', 'Direct', 'Hong Sing LKRR Commercial → Marikina Route | Demo ML training route', 31.70, '82 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(11, 'DEMO-RT-010', 'Direct', 'Hong Sing LKRR Commercial → Parañaque Route | Demo ML training route', 34.50, '91 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(12, 'DEMO-RT-011', 'Direct', 'Hong Sing LKRR Commercial → Las Piñas Route | Demo ML training route', 38.20, '101 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(13, 'DEMO-RT-012', 'Direct', 'Hong Sing LKRR Commercial → Muntinlupa Route | Demo ML training route', 43.60, '112 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(14, 'DEMO-RT-013', 'Direct', 'Hong Sing LKRR Commercial → Bulacan Route | Demo ML training route', 27.80, '70 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(15, 'DEMO-RT-014', 'Direct', 'Hong Sing LKRR Commercial → Bocaue Route | Demo ML training route', 34.20, '86 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(16, 'DEMO-RT-015', 'Direct', 'Hong Sing LKRR Commercial → Malolos Route | Demo ML training route', 47.50, '118 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(17, 'RT-20260909-98A9B6', 'Direct', 'Hong Sing LKRR Commercial → 106, M. H. del Pilar Street, Grace Park East, Caloocan, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6499945, 120.9844601\r\nRoad distance: 3.77 km\r\nEstimated travel time: 6 min\r\nRouting provider: OSRM', 3.77, '6 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(18, 'RT-20260920-C7EBD3', 'Direct', 'Hong Sing LKRR Commercial → Samson Road, Sangandaan, Caloocan, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6578404, 120.9722628\r\nRoad distance: 3.97 km\r\nEstimated travel time: 5 min\r\nRouting provider: OSRM', 3.97, '5 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(19, 'RT-20260920-DE7F14', 'Direct', 'Hong Sing LKRR Commercial → SM Center Sangandaan, Samson Road, Barangay 3, Zone 1, Sangandaan, District 1, Caloocan, Northern Manila District, Metro Manila, 1400, Philippines, Caloocan, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6583153, 120.9719176\r\nRoad distance: 4.01 km\r\nEstimated travel time: 5 min\r\nRouting provider: OSRM', 4.01, '5 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(20, 'RT-20260921-D125BE', 'Direct', 'Hong Sing LKRR Commercial → SM Center Sangandaan, Samson Road, Barangay 3, Zone 1, Sangandaan, District 1, Caloocan, Northern Manila District, Metro Manila, 1400, Philippines, Caloocan, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6583153, 120.9719176\r\nRoad distance: 4.01 km\r\nEstimated travel time: 5 min\r\nRouting provider: OSRM', 4.01, '5 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(21, 'RT-20260922-3620F4', 'Direct', 'Hong Sing LKRR Commercial → Macanas Street, Karisma Village, Panghulo, Malabon, Northern Manila District, Metro Manila, 1478, Philippines, Malabon, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6874515, 120.954487\r\nRoad distance: 5.44 km\r\nEstimated travel time: 10 min\r\nRouting provider: OSRM', 5.44, '10 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-22 20:23:33'),
(22, 'RT-20260925-4296D7', 'Direct', 'Hong Sing LKRR Commercial → Macanas Street, Karisma Village, Panghulo, Malabon, Northern Manila District, Metro Manila, 1478, Philippines, Malabon, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6874515, 120.954487\r\nRoad distance: 5.44 km\r\nEstimated travel time: 10 min\r\nRouting provider: OSRM', 5.44, '10 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-09-26 00:21:21'),
(23, 'RT-20261002-3CA9A1', 'Direct', 'Hong Sing LKRR Commercial → Linum Street, Del Nacia Ville Ⅳ, Sauyo, 6th District, Quezon City, Eastern Manila District, Metro Manila, 1116, Philippines, Quezon City, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6922285, 121.0438651\r\nRoad distance: 11.78 km\r\nEstimated travel time: 17 min\r\nRouting provider: OSRM', 11.78, '17 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-10-02 13:35:13'),
(24, 'RT-20261002-1E8404', 'Direct', 'Hong Sing LKRR Commercial → SM Center Sangandaan, Samson Road, Barangay 3, Zone 1, Sangandaan, District 1, Caloocan, Northern Manila District, Metro Manila, 1400, Philippines, Caloocan, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6583153, 120.9719176\r\nRoad distance: 4.01 km\r\nEstimated travel time: 5 min\r\nRouting provider: OSRM', 4.01, '5 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-10-02 20:05:09'),
(25, 'RT-20261002-35CF53', 'Direct', 'Hong Sing LKRR Commercial → SM Center Sangandaan, Samson Road, Barangay 3, Zone 1, Sangandaan, District 1, Caloocan, Northern Manila District, Metro Manila, 1400, Philippines, Caloocan, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6583153, 120.9719176\r\nRoad distance: 4.01 km\r\nEstimated travel time: 5 min\r\nRouting provider: OSRM', 4.01, '5 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-10-02 20:10:43'),
(26, 'RT-20261002-11021F', 'Direct', 'Hong Sing LKRR Commercial → SM Center Sangandaan, Samson Road, Barangay 3, Zone 1, Sangandaan, District 1, Caloocan, Northern Manila District, Metro Manila, 1400, Philippines, Caloocan, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6583153, 120.9719176\r\nRoad distance: 4.01 km\r\nEstimated travel time: 5 min\r\nRouting provider: OSRM', 4.01, '5 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-10-03 03:58:13'),
(27, 'RT-20261002-F01D5F', 'Direct', 'Hong Sing LKRR Commercial → P. Aquino Avenue, Balut, Tonsuya, Malabon, Northern Manila District, Metro Manila, 1479, Philippines, Malabon, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6601423, 120.9579353\r\nRoad distance: 5.53 km\r\nEstimated travel time: 8 min\r\nRouting provider: OSRM', 5.53, '8 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-10-03 07:04:24'),
(28, 'RT-20261002-ED0315', 'Direct', 'Hong Sing LKRR Commercial → Cavite City, Cavite, Calabarzon, 4100, Philippines, Cavite City, Cavite\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.4820919, 120.908919\r\nRoad distance: 44.28 km\r\nEstimated travel time: 51 min\r\nRouting provider: OSRM', 44.28, '51 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-10-03 07:12:11'),
(29, 'RT-20261003-CD931D', 'Direct', 'Hong Sing LKRR Commercial → SM Center Sangandaan, Samson Road, Barangay 3, Zone 1, Sangandaan, District 1, Caloocan, Northern Manila District, Metro Manila, 1400, Philippines, Caloocan, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6583153, 120.9719176\r\nRoad distance: 4.01 km\r\nEstimated travel time: 5 min\r\nRouting provider: OSRM', 4.01, '5 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-10-03 08:40:38'),
(30, 'RT-20261003-D20C06', 'Direct', 'Hong Sing LKRR Commercial → SM Center Sangandaan, Samson Road, Barangay 3, Zone 1, Sangandaan, District 1, Caloocan, Northern Manila District, Metro Manila, 1400, Philippines, Caloocan, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6583153, 120.9719176\r\nRoad distance: 4.01 km\r\nEstimated travel time: 5 min\r\nRouting provider: OSRM', 4.01, '5 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-10-03 09:12:39'),
(31, 'RT-20261003-FE5C9B', 'Direct', 'Hong Sing LKRR Commercial → SM Center Sangandaan, Samson Road, Barangay 3, Zone 1, Sangandaan, District 1, Caloocan, Northern Manila District, Metro Manila, 1400, Philippines, Caloocan, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6583153, 120.9719176\r\nRoad distance: 4.01 km\r\nEstimated travel time: 5 min\r\nRouting provider: OSRM', 4.01, '5 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-10-03 09:13:14'),
(32, 'RT-20261003-47EFE2', 'Direct', 'Hong Sing LKRR Commercial → P. Aquino Avenue, Paradise Village, Tonsuya, Malabon, Northern Manila District, Metro Manila, 1411, Philippines, Malabon, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6595998, 120.9612514\r\nRoad distance: 5.16 km\r\nEstimated travel time: 7 min\r\nRouting provider: OSRM', 5.16, '7 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-10-03 09:22:27'),
(33, 'RT-20261003-20951E', 'Direct', 'Hong Sing LKRR Commercial → Macanas Street, Karisma Village, Panghulo, Malabon, Northern Manila District, Metro Manila, 1478, Philippines, Malabon, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6874515, 120.954487\r\nRoad distance: 5.44 km\r\nEstimated travel time: 10 min\r\nRouting provider: OSRM', 5.44, '10 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-10-03 09:26:18'),
(34, 'RT-20261004-5B1B6E', 'Direct', 'Hong Sing LKRR Commercial → Macanas Street, Karisma Village, Panghulo, Malabon, Northern Manila District, Metro Manila, 1478, Philippines, Malabon, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6874515, 120.954487\r\nRoad distance: 5.44 km\r\nEstimated travel time: 10 min\r\nRouting provider: OSRM', 5.44, '10 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-10-04 20:25:41'),
(35, 'RT-20261013-EFED05', 'Direct', 'Hong Sing LKRR Commercial → Ignacio Street, Karisma Village, Panghulo, Malabon, Northern Manila District, Metro Manila, 1478, Philippines, Malabon, Metro Manila\r\nOrigin coordinates: 14.6749623, 120.9874442\r\nDestination coordinates: 14.6863943, 120.9552665\r\nRoad distance: 5.53 km\r\nEstimated travel time: 11 min\r\nRouting provider: OSRM', 5.53, '11 min', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-10-14 01:17:10');

-- --------------------------------------------------------

--
-- Table structure for table `system_user`
--

CREATE TABLE `system_user` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `role` enum('Admin','Super Admin') NOT NULL,
  `status` enum('Active','On Leave','Absent','Resigned','Terminated') NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_user`
--

INSERT INTO `system_user` (`user_id`, `full_name`, `email`, `phone_number`, `password_hash`, `password_changed_at`, `role`, `status`, `created_at`) VALUES
(1, 'Super Administrator', 'superadmin@hongsing.com', NULL, '$2y$10$gDklLmK7vEcdAjDId0bPY..66CqM/bh11lVM7LPU9e0761PVONVem', '2026-09-20 19:34:38', 'Super Admin', 'Active', '2026-08-03 21:31:25'),
(2, 'Jose Reyes', 'josereyes_admin@hongsing.com', NULL, '$2y$10$Kto.uQ37X.BaZkMU7tKcDO4TozFBHC/aVEsqVH9jh5Xs4Yq62OyB6', NULL, 'Admin', 'Active', '2026-08-03 22:13:52');

-- --------------------------------------------------------

--
-- Table structure for table `truck`
--

CREATE TABLE `truck` (
  `truck_id` int(11) NOT NULL,
  `plate_number` varchar(20) NOT NULL,
  `model` varchar(100) NOT NULL,
  `capacity` decimal(10,2) NOT NULL,
  `status` enum('Available','In Use','Maintenance') NOT NULL DEFAULT 'Available',
  `last_maintenance` date DEFAULT NULL,
  `current_load` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `truck`
--

INSERT INTO `truck` (`truck_id`, `plate_number`, `model`, `capacity`, `status`, `last_maintenance`, `current_load`) VALUES
(1, 'NAA 1234', 'Isuzu NMR 85 truck', 4490.00, 'In Use', '2026-12-07', 8.50),
(2, 'NCB 5678', 'Isuzu NMR 85 truck', 2500.00, 'In Use', '2026-08-14', 13.30),
(33, 'HS03-727', 'Isuzu Elf', 1000.00, 'In Use', '2026-09-09', 308.50),
(34, 'HS04-292', 'Mitsubishi Canter', 1500.00, 'In Use', '2026-09-09', 918.00),
(35, 'HS05-527', 'Hino Dutro', 2000.00, 'Available', '2026-09-09', 0.00),
(36, 'HS06-513', 'Fuso Canter', 2500.00, 'Available', '2026-09-09', 0.00),
(37, 'HS07-832', 'Isuzu N-Series', 3000.00, 'Available', '2026-09-09', 0.00),
(38, 'HS08-741', 'Isuzu Elf', 3500.00, 'Available', '2026-09-09', 0.00),
(39, 'HS09-861', 'Mitsubishi Canter', 4000.00, 'Available', '2026-09-09', 0.00),
(40, 'HS10-517', 'Hino Dutro', 1000.00, 'In Use', '2026-09-09', 16.80),
(41, 'HS11-849', 'Fuso Canter', 1500.00, 'Available', '2026-09-09', 0.00),
(42, 'HS12-570', 'Isuzu N-Series', 2000.00, 'Available', '2026-09-09', 0.00),
(43, 'HS13-937', 'Isuzu Elf', 2500.00, 'In Use', '2026-09-09', 9.60),
(44, 'HS14-311', 'Mitsubishi Canter', 3000.00, 'Available', '2026-09-09', 0.00),
(45, 'HS15-814', 'Hino Dutro', 3500.00, 'Available', '2026-09-09', 0.00),
(46, 'HS16-121', 'Fuso Canter', 4000.00, 'Available', '2026-09-09', 0.00),
(47, 'HS17-782', 'Isuzu N-Series', 1000.00, 'Available', '2026-09-09', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `truck_location`
--

CREATE TABLE `truck_location` (
  `location_id` int(11) NOT NULL,
  `truck_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `urgent_delivery_rate`
--

CREATE TABLE `urgent_delivery_rate` (
  `rate_id` int(11) NOT NULL,
  `min_distance_km` decimal(10,2) NOT NULL,
  `max_distance_km` decimal(10,2) DEFAULT NULL,
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `urgent_delivery_rate`
--

INSERT INTO `urgent_delivery_rate` (`rate_id`, `min_distance_km`, `max_distance_km`, `fee`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 0.00, 10.00, 50.00, 1, '2026-09-20 16:13:56', '2026-09-20 16:43:10'),
(2, 10.01, 20.00, 100.00, 1, '2026-09-20 16:13:56', '2026-09-20 16:13:56'),
(3, 20.01, 30.00, 150.00, 1, '2026-09-20 16:13:56', '2026-09-20 16:13:56'),
(4, 30.01, NULL, 200.00, 1, '2026-09-20 16:13:56', '2026-09-20 16:13:56');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `role` (`role`),
  ADD KEY `module` (`module`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `customer_address`
--
ALTER TABLE `customer_address`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `idx_customer_address_customer` (`customer_id`);

--
-- Indexes for table `delivery_incident`
--
ALTER TABLE `delivery_incident`
  ADD PRIMARY KEY (`incident_id`),
  ADD KEY `idx_incident_order` (`order_id`),
  ADD KEY `idx_incident_driver` (`driver_id`),
  ADD KEY `idx_incident_truck` (`truck_id`),
  ADD KEY `idx_incident_reported` (`reported_at`),
  ADD KEY `idx_incident_review` (`review_status`,`violation_status`),
  ADD KEY `fk_incident_reviewer` (`reviewed_by`);

--
-- Indexes for table `delivery_location`
--
ALTER TABLE `delivery_location`
  ADD PRIMARY KEY (`location_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `delivery_status`
--
ALTER TABLE `delivery_status`
  ADD PRIMARY KEY (`status_id`),
  ADD KEY `idx_delivery_status_order` (`order_id`),
  ADD KEY `idx_delivery_status_order_time` (`order_id`,`timestamp`);

--
-- Indexes for table `driver`
--
ALTER TABLE `driver`
  ADD PRIMARY KEY (`driver_id`),
  ADD UNIQUE KEY `license_number` (`license_number`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_driver_status` (`status`),
  ADD KEY `fk_driver_truck` (`current_truck_id`);

--
-- Indexes for table `driver_truck_assignment`
--
ALTER TABLE `driver_truck_assignment`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `truck_id` (`truck_id`);

--
-- Indexes for table `email_verification_otp`
--
ALTER TABLE `email_verification_otp`
  ADD PRIMARY KEY (`otp_id`),
  ADD KEY `idx_email_otp_customer` (`customer_id`),
  ADD KEY `idx_email_otp_expires` (`expires_at`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD UNIQUE KEY `order_id` (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indexes for table `ml_assignment_log`
--
ALTER TABLE `ml_assignment_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `idx_ml_log_created` (`created_at`),
  ADD KEY `idx_ml_log_model` (`model_version`);

--
-- Indexes for table `ml_delivery_history`
--
ALTER TABLE `ml_delivery_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `idx_ml_history_date` (`delivery_date`),
  ADD KEY `idx_ml_history_area` (`delivery_area`),
  ADD KEY `idx_ml_history_delayed` (`was_delayed`),
  ADD KEY `idx_ml_history_source_order` (`source_order_id`),
  ADD KEY `fk_ml_history_driver` (`driver_id`),
  ADD KEY `fk_ml_history_truck` (`truck_id`);

--
-- Indexes for table `ml_driver_history`
--
ALTER TABLE `ml_driver_history`
  ADD PRIMARY KEY (`history_id`),
  ADD UNIQUE KEY `uq_ml_driver_history_driver` (`driver_id`);

--
-- Indexes for table `ml_truck_history`
--
ALTER TABLE `ml_truck_history`
  ADD PRIMARY KEY (`history_id`),
  ADD UNIQUE KEY `uq_ml_truck_history_truck` (`truck_id`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `status_id` (`status_id`),
  ADD KEY `idx_notification_recipient` (`recipient_role`,`recipient_id`,`is_read`),
  ADD KEY `idx_notification_sent_at` (`sent_at`);

--
-- Indexes for table `order`
--
ALTER TABLE `order`
  ADD PRIMARY KEY (`order_id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `truck_id` (`truck_id`),
  ADD KEY `route_id` (`route_id`),
  ADD KEY `idx_order_status` (`completion_status`),
  ADD KEY `idx_order_date` (`order_date`),
  ADD KEY `idx_order_expected_delivery` (`expected_delivery_date`),
  ADD KEY `idx_order_driver_status` (`driver_id`,`completion_status`),
  ADD KEY `idx_order_truck_status` (`truck_id`,`completion_status`);

--
-- Indexes for table `order_item`
--
ALTER TABLE `order_item`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `fk_order_item_order` (`order_id`),
  ADD KEY `fk_order_item_product` (`product_id`);

--
-- Indexes for table `phone_verification_otp`
--
ALTER TABLE `phone_verification_otp`
  ADD PRIMARY KEY (`otp_id`),
  ADD KEY `idx_phone_otp_customer` (`customer_id`),
  ADD KEY `idx_phone_otp_expires` (`expires_at`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `unique_product_code` (`product_code`);

--
-- Indexes for table `receipt`
--
ALTER TABLE `receipt`
  ADD PRIMARY KEY (`receipt_id`),
  ADD UNIQUE KEY `order_id` (`order_id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`);

--
-- Indexes for table `route`
--
ALTER TABLE `route`
  ADD PRIMARY KEY (`route_id`),
  ADD UNIQUE KEY `route_code` (`route_code`);

--
-- Indexes for table `system_user`
--
ALTER TABLE `system_user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `truck`
--
ALTER TABLE `truck`
  ADD PRIMARY KEY (`truck_id`),
  ADD UNIQUE KEY `plate_number` (`plate_number`),
  ADD KEY `idx_truck_status` (`status`);

--
-- Indexes for table `truck_location`
--
ALTER TABLE `truck_location`
  ADD PRIMARY KEY (`location_id`),
  ADD KEY `idx_truck_id` (`truck_id`),
  ADD KEY `idx_driver_id` (`driver_id`),
  ADD KEY `idx_recorded_at` (`recorded_at`);

--
-- Indexes for table `urgent_delivery_rate`
--
ALTER TABLE `urgent_delivery_rate`
  ADD PRIMARY KEY (`rate_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=495;

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `customer_address`
--
ALTER TABLE `customer_address`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_incident`
--
ALTER TABLE `delivery_incident`
  MODIFY `incident_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `delivery_location`
--
ALTER TABLE `delivery_location`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `delivery_status`
--
ALTER TABLE `delivery_status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

--
-- AUTO_INCREMENT for table `driver`
--
ALTER TABLE `driver`
  MODIFY `driver_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `driver_truck_assignment`
--
ALTER TABLE `driver_truck_assignment`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `email_verification_otp`
--
ALTER TABLE `email_verification_otp`
  MODIFY `otp_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `ml_assignment_log`
--
ALTER TABLE `ml_assignment_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=133;

--
-- AUTO_INCREMENT for table `ml_delivery_history`
--
ALTER TABLE `ml_delivery_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ml_driver_history`
--
ALTER TABLE `ml_driver_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `ml_truck_history`
--
ALTER TABLE `ml_truck_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `notification`
--
ALTER TABLE `notification`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `order`
--
ALTER TABLE `order`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=149;

--
-- AUTO_INCREMENT for table `order_item`
--
ALTER TABLE `order_item`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `phone_verification_otp`
--
ALTER TABLE `phone_verification_otp`
  MODIFY `otp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `product`
--
ALTER TABLE `product`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT for table `receipt`
--
ALTER TABLE `receipt`
  MODIFY `receipt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `route`
--
ALTER TABLE `route`
  MODIFY `route_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `system_user`
--
ALTER TABLE `system_user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `truck`
--
ALTER TABLE `truck`
  MODIFY `truck_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `truck_location`
--
ALTER TABLE `truck_location`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `urgent_delivery_rate`
--
ALTER TABLE `urgent_delivery_rate`
  MODIFY `rate_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customer_address`
--
ALTER TABLE `customer_address`
  ADD CONSTRAINT `fk_customer_address_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `delivery_incident`
--
ALTER TABLE `delivery_incident`
  ADD CONSTRAINT `fk_incident_driver` FOREIGN KEY (`driver_id`) REFERENCES `driver` (`driver_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_incident_order` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_incident_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `system_user` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_incident_truck` FOREIGN KEY (`truck_id`) REFERENCES `truck` (`truck_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `delivery_location`
--
ALTER TABLE `delivery_location`
  ADD CONSTRAINT `delivery_location_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `delivery_status`
--
ALTER TABLE `delivery_status`
  ADD CONSTRAINT `delivery_status_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `driver`
--
ALTER TABLE `driver`
  ADD CONSTRAINT `fk_driver_truck` FOREIGN KEY (`current_truck_id`) REFERENCES `truck` (`truck_id`) ON DELETE SET NULL;

--
-- Constraints for table `driver_truck_assignment`
--
ALTER TABLE `driver_truck_assignment`
  ADD CONSTRAINT `driver_truck_assignment_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `driver` (`driver_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `driver_truck_assignment_ibfk_2` FOREIGN KEY (`truck_id`) REFERENCES `truck` (`truck_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `email_verification_otp`
--
ALTER TABLE `email_verification_otp`
  ADD CONSTRAINT `fk_email_otp_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_3` FOREIGN KEY (`driver_id`) REFERENCES `driver` (`driver_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `ml_assignment_log`
--
ALTER TABLE `ml_assignment_log`
  ADD CONSTRAINT `ml_assignment_log_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ml_delivery_history`
--
ALTER TABLE `ml_delivery_history`
  ADD CONSTRAINT `fk_ml_history_driver` FOREIGN KEY (`driver_id`) REFERENCES `driver` (`driver_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ml_history_order` FOREIGN KEY (`source_order_id`) REFERENCES `order` (`order_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ml_history_truck` FOREIGN KEY (`truck_id`) REFERENCES `truck` (`truck_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `ml_driver_history`
--
ALTER TABLE `ml_driver_history`
  ADD CONSTRAINT `fk_ml_driver_history_driver` FOREIGN KEY (`driver_id`) REFERENCES `driver` (`driver_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ml_truck_history`
--
ALTER TABLE `ml_truck_history`
  ADD CONSTRAINT `fk_ml_truck_history_truck` FOREIGN KEY (`truck_id`) REFERENCES `truck` (`truck_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notification`
--
ALTER TABLE `notification`
  ADD CONSTRAINT `notification_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `notification_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `delivery_status` (`status_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `order`
--
ALTER TABLE `order`
  ADD CONSTRAINT `order_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `order_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `driver` (`driver_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `order_ibfk_3` FOREIGN KEY (`truck_id`) REFERENCES `truck` (`truck_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `order_ibfk_4` FOREIGN KEY (`route_id`) REFERENCES `route` (`route_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `order_item`
--
ALTER TABLE `order_item`
  ADD CONSTRAINT `fk_order_item_order` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_item_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON UPDATE CASCADE;

--
-- Constraints for table `phone_verification_otp`
--
ALTER TABLE `phone_verification_otp`
  ADD CONSTRAINT `fk_phone_otp_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE;

--
-- Constraints for table `receipt`
--
ALTER TABLE `receipt`
  ADD CONSTRAINT `receipt_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `truck_location`
--
ALTER TABLE `truck_location`
  ADD CONSTRAINT `fk_truck_location_driver` FOREIGN KEY (`driver_id`) REFERENCES `driver` (`driver_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_truck_location_truck` FOREIGN KEY (`truck_id`) REFERENCES `truck` (`truck_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
