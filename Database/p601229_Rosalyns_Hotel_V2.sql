-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 01, 2026 at 09:08 PM
-- Server version: 8.0.44-cll-lve
-- PHP Version: 8.4.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `p601229_Rosalyns_Hotel_V2`
--

-- --------------------------------------------------------

--
-- Table structure for table `about_us`
--

CREATE TABLE `about_us` (
  `id` int NOT NULL,
  `section_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'main, feature, stat',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `image_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon_class` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stat_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stat_label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `about_us`
--

INSERT INTO `about_us` (`id`, `section_type`, `title`, `subtitle`, `content`, `image_url`, `icon_class`, `stat_number`, `stat_label`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'main', 'Welcome to Rosalyns Beach Hotel', 'Our Story', 'Located in the heart of Malawi, Rosalyns Beach Hotel offers premium accomodation', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(22).b14f09deaae933642113.jpeg', NULL, NULL, NULL, 1, 1, '2026-01-26 11:46:36', '2026-02-22 17:52:25'),
(2, 'feature', 'Friendly Service', NULL, 'Our staff is dedicated to making your stay comfortable and pleasant', NULL, 'fas fa-award', NULL, NULL, 1, 1, '2026-01-26 11:46:36', '2026-02-07 02:40:52'),
(3, 'feature', 'Great Location', NULL, 'Conveniently located near Liwonde National Park and local attractions', NULL, 'fas fa-leaf', NULL, NULL, 2, 1, '2026-01-26 11:46:36', '2026-02-07 02:40:52'),
(4, 'feature', 'Comfortable Rooms', NULL, 'Clean and well-maintained rooms for a good night\'s rest', NULL, 'fas fa-heart', NULL, NULL, 3, 1, '2026-01-26 11:46:36', '2026-02-07 02:40:52'),
(5, 'feature', 'Good Value', NULL, 'Affordable rates with everything you need for a comfortable stay', NULL, 'fas fa-star', NULL, NULL, 4, 1, '2026-01-26 11:46:36', '2026-02-07 02:40:52'),
(6, 'stat', NULL, NULL, NULL, NULL, NULL, '4+', 'Years Serving Guests', 1, 1, '2026-01-26 11:46:36', '2026-02-15 22:14:50'),
(7, 'stat', NULL, NULL, NULL, NULL, NULL, '95%', 'Guest Satisfaction', 2, 1, '2026-01-26 11:46:36', '2026-02-07 02:40:52'),
(8, 'stat', NULL, NULL, NULL, NULL, NULL, '50+', 'Awards Won', 3, 0, '2026-01-26 11:46:36', '2026-01-26 13:24:21'),
(9, 'stat', NULL, NULL, NULL, NULL, NULL, '10k+', 'Happy Guests', 4, 1, '2026-01-26 11:46:36', '2026-01-26 11:46:36');

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_log`
--

CREATE TABLE `admin_activity_log` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin_activity_log`
--

INSERT INTO `admin_activity_log` (`id`, `user_id`, `username`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 2, 'receptionist', 'login_success', 'Role: receptionist', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-07 13:34:41'),
(2, 2, 'receptionist', 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-07 13:35:28'),
(3, 1, 'admin', 'login_success', 'Role: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-07 13:38:34'),
(4, 2, 'receptionist', 'login_success', 'Role: receptionist', '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-02-07 13:45:29'),
(5, 2, 'receptionist', 'logout', 'User logged out', '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-02-07 13:46:02'),
(6, 1, 'admin', 'login_success', 'Role: admin', '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-02-07 13:46:23'),
(7, 2, 'receptionist', 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-07 14:07:00'),
(8, 1, 'admin', 'login_success', 'Role: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-07 14:07:10'),
(9, 1, 'admin', 'login_success', 'Role: admin', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-07 18:44:39'),
(10, 1, 'admin', 'login_success', 'Role: admin', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-07 22:35:49'),
(11, 1, 'admin', 'login_success', 'Role: admin', '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-02-08 19:10:10'),
(12, 1, 'admin', 'login_success', 'Role: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 10:30:03'),
(13, 1, 'admin', 'login_success', 'Role: admin', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 12:02:22'),
(14, 1, 'admin', 'login_success', 'Role: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 15:36:17'),
(15, 1, 'admin', 'login_success', 'Role: admin', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 16:31:02'),
(16, 1, 'admin', 'login_success', 'Role: admin', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 10:47:36'),
(17, 1, 'admin', 'login_failed', 'Wrong password (attempt 1/5)', '137.115.5.18', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 10:58:03'),
(18, 1, 'admin', 'login_success', 'Role: admin', '80.233.37.180', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-02-10 18:28:16'),
(19, 1, 'admin', 'login_success', 'Role: admin', '216.234.217.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-02-10 18:28:32'),
(20, 1, 'admin', 'login_failed', 'Wrong password (attempt 1/5)', '216.234.217.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-02-10 19:34:16'),
(21, 1, 'admin', 'login_success', 'Role: admin', '216.234.217.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-02-10 19:34:41'),
(22, 1, 'admin', 'login_success', 'Role: admin', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 09:02:45'),
(23, 1, 'admin', 'login_success', 'Role: admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 13:18:03'),
(24, 1, 'admin', 'login_success', 'Role: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 21:14:32'),
(25, 1, 'admin', 'login_success', 'Role: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-12 00:21:26'),
(26, 1, 'admin', 'login_success', 'Role: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-12 10:26:38'),
(27, 1, 'admin', 'login_success', 'Role: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-12 11:36:28'),
(28, 1, 'admin', 'login_success', 'Role: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-12 12:51:46'),
(29, 1, 'admin', 'login_success', 'Role: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-12 20:10:32'),
(30, 1, 'admin', 'login_success', 'Role: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 00:30:43'),
(31, 1, 'admin', 'login_success', 'Role: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 11:19:55'),
(32, 1, 'admin', 'login_success', 'Role: admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-02-13 16:16:03'),
(33, 1, 'admin', 'login_success', 'Role: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 19:22:26'),
(34, 1, 'admin', 'login_success', 'Role: admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-02-15 21:43:59'),
(35, 1, 'admin', 'login_success', 'Role: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-16 23:20:54'),
(36, 1, 'admin', 'login_success', 'Role: admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-17 08:58:42'),
(37, 2, 'receptionist', 'login_success', 'Role: receptionist', '192.168.2.5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-02-17 22:50:09'),
(38, 2, 'receptionist', 'logout', 'User logged out', '192.168.2.5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-02-17 22:51:41'),
(39, 1, 'admin', 'login_failed', 'Wrong password (attempt 1/5)', '192.168.2.5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-02-17 22:52:00'),
(40, 2, 'receptionist', 'login_success', 'Role: receptionist', '192.168.2.5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-02-17 22:52:20'),
(41, 1, 'admin', 'login_success', 'Role: admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-18 08:57:22'),
(42, 1, 'admin', 'login_success', 'Role: admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-18 11:44:31'),
(43, 1, 'admin', 'login_success', 'Role: admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-18 21:45:54'),
(44, 1, 'admin', 'login_success', 'Role: admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-19 22:22:25'),
(45, 2, 'receptionist', 'login_success', 'Role: receptionist', '192.168.2.5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-22 01:56:50'),
(46, 1, 'admin', 'login_success', 'Role: admin', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 16:00:06'),
(47, 1, 'admin', 'login_success', 'Role: admin', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 18:08:08'),
(48, 1, 'admin', 'login_success', 'Role: admin', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 22:34:27'),
(49, 1, 'admin', 'login_success', 'Role: admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:18:09'),
(50, 1, 'admin', 'login_success', 'Role: admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 16:13:12'),
(51, 1, 'admin', 'login_success', 'Role: admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 23:42:08'),
(52, 1, 'admin', 'login_success', 'Role: admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 15:56:26'),
(53, 1, 'admin', 'login_success', 'Role: admin', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 20:14:59'),
(54, 1, 'admin', 'login_success', 'Role: admin', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 09:01:18'),
(55, 1, 'admin', 'login_success', 'Role: admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 09:15:30'),
(56, 1, 'admin', 'login_success', 'Role: admin', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 10:04:52'),
(57, 1, 'admin', 'login_success', 'Role: admin', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 16:45:20'),
(58, 1, 'admin', 'login_success', 'Role: admin', '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-28 16:53:01'),
(59, 1, 'admin', 'logout', 'User logged out', '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-28 16:55:05'),
(60, 1, 'admin', 'login_success', 'Role: admin', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 17:15:54'),
(61, 1, 'admin', 'login_success', 'Role: admin', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 20:37:53');

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int UNSIGNED NOT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','receptionist','manager') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'receptionist',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `failed_login_attempts` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`, `failed_login_attempts`) VALUES
(1, 'admin', 'johnpaulchirwa@gmail.com', '$2y$10$OFHlFcgoqltOd7X6Z3IqVeg0961Adk9LxyfW8UBBfENSawMRZ3fF6', 'System Administrator', 'admin', 1, '2026-03-01 20:37:53', '2026-01-20 19:08:40', '2026-03-01 20:37:53', 0),
(2, 'receptionist', 'reception@liwondesunhotel.com', '$2y$10$OFHlFcgoqltOd7X6Z3IqVeg0961Adk9LxyfW8UBBfENSawMRZ3fF6', 'Front Desk', 'receptionist', 1, '2026-02-22 01:56:50', '2026-01-20 19:08:40', '2026-02-22 01:56:50', 0);

-- --------------------------------------------------------

--
-- Table structure for table `api_keys`
--

CREATE TABLE `api_keys` (
  `id` int UNSIGNED NOT NULL,
  `api_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Hashed API key',
  `api_key_plain` text COLLATE utf8mb4_unicode_ci COMMENT 'AES-256 encrypted retrievable API key for admin view',
  `client_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of the client/website using the API',
  `client_website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Website URL of the client',
  `client_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Contact email for the client',
  `permissions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON array of permissions: ["rooms.read", "availability.check", "bookings.create", "bookings.read"]',
  `rate_limit_per_hour` int NOT NULL DEFAULT '100' COMMENT 'Maximum API calls per hour',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether the API key is active',
  `last_used_at` timestamp NULL DEFAULT NULL COMMENT 'Last time the API key was used',
  `usage_count` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Total number of API calls made',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API keys for external booking system access';

--
-- Dumping data for table `api_keys`
--

INSERT INTO `api_keys` (`id`, `api_key`, `api_key_plain`, `client_name`, `client_website`, `client_email`, `permissions`, `rate_limit_per_hour`, `is_active`, `last_used_at`, `usage_count`, `created_at`, `updated_at`) VALUES
(1, '$2y$10$3SV7ph3x7/ttZKUx3rvf8.tVLy6.OaifO3tcYfCeTRV7eSPa3PPX6', NULL, 'Test Client', 'https://promanaged-it.com', 'test@example.com', '[\"rooms.read\", \"availability.check\", \"bookings.create\", \"bookings.read\"]', 1000, 1, '2026-01-28 23:42:38', 3, '2026-01-27 13:30:53', '2026-01-28 23:48:54'),
(2, '$2y$10$ss6GhWWehnq4.hGD2FniqOcms1WQ2Clfl0d9heGWBL0yLoV/wWOgS', NULL, 'Test', 'http://localhost:8000/index.php', 'johnpaulchirwa@gmail.com', '[\"rooms.read\",\"availability.check\",\"bookings.create\",\"bookings.read\",\"bookings.update\",\"bookings.delete\"]', 100, 1, NULL, 0, '2026-02-18 11:45:47', '2026-02-18 11:45:47'),
(3, '$2y$10$Ke3GT9eI5PloBQkxL35VVeuwVy8Aiaq8Xdxhic6U2xdo5ku770y8y', NULL, 'Local Test Client', 'http://localhost:8080', 'test@localhost', '[\"availability.check\",\"bookings.create\",\"rooms.read\"]', 1000, 1, '2026-02-18 14:31:31', 6, '2026-02-18 14:07:09', '2026-02-18 18:38:36');

-- --------------------------------------------------------

--
-- Table structure for table `api_usage_logs`
--

CREATE TABLE `api_usage_logs` (
  `id` int UNSIGNED NOT NULL,
  `api_key_id` int UNSIGNED NOT NULL,
  `endpoint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'API endpoint called',
  `method` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'HTTP method',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Client IP address',
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Client user agent',
  `response_code` int NOT NULL COMMENT 'HTTP response code',
  `response_time` decimal(10,4) NOT NULL COMMENT 'Response time in seconds',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log of API usage for monitoring and analytics';

-- --------------------------------------------------------

--
-- Table structure for table `blocked_dates`
--

CREATE TABLE `blocked_dates` (
  `id` int UNSIGNED NOT NULL,
  `room_id` int UNSIGNED DEFAULT NULL,
  `block_date` date NOT NULL,
  `block_type` enum('manual','maintenance','event','full') COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blocked_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int UNSIGNED NOT NULL,
  `booking_reference` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `room_id` int UNSIGNED NOT NULL,
  `individual_room_id` int UNSIGNED DEFAULT NULL COMMENT 'Specific room assigned to this booking',
  `guest_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guest_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guest_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guest_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guest_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `number_of_guests` int NOT NULL DEFAULT '1',
  `adult_guests` int NOT NULL DEFAULT '1',
  `child_guests` int NOT NULL DEFAULT '0',
  `child_price_multiplier` decimal(5,2) NOT NULL DEFAULT '50.00',
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `number_of_nights` int NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `child_supplement_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `amount_paid` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Total amount paid so far',
  `amount_due` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Remaining amount to be paid',
  `vat_rate` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'VAT rate applied',
  `vat_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'VAT amount',
  `total_with_vat` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Total amount including VAT',
  `last_payment_date` date DEFAULT NULL COMMENT 'Date of last payment',
  `special_requests` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','tentative','confirmed','checked-in','checked-out','cancelled','expired','no-show') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `is_tentative` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether this is a tentative booking',
  `tentative_expires_at` datetime DEFAULT NULL COMMENT 'When tentative booking expires',
  `deposit_required` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether deposit is required',
  `deposit_amount` decimal(10,2) DEFAULT NULL COMMENT 'Required deposit amount',
  `deposit_paid` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether deposit has been paid',
  `deposit_paid_at` datetime DEFAULT NULL COMMENT 'When deposit was paid',
  `reminder_sent` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Expiration reminder sent',
  `reminder_sent_at` datetime DEFAULT NULL COMMENT 'When reminder was sent',
  `converted_to_confirmed_at` datetime DEFAULT NULL COMMENT 'When converted to confirmed',
  `expired_at` datetime DEFAULT NULL COMMENT 'When booking expired',
  `tentative_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Notes about tentative booking',
  `payment_status` enum('unpaid','partial','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `payment_amount` decimal(10,2) DEFAULT '0.00',
  `payment_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL COMMENT 'When tentative booking expires (NULL for non-tentative bookings)',
  `converted_from_tentative` tinyint(1) DEFAULT '0' COMMENT 'Whether this booking was converted from tentative status (1=yes, 0=no)',
  `occupancy_type` enum('single','double','triple') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'double' COMMENT 'Occupancy type for pricing',
  `final_invoice_generated` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether final invoice has been generated at checkout',
  `final_invoice_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path to final invoice file',
  `final_invoice_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Final invoice number',
  `final_invoice_sent_at` datetime DEFAULT NULL COMMENT 'When final invoice email was sent',
  `checkout_completed_at` datetime DEFAULT NULL COMMENT 'When checkout was completed',
  `folio_charges_total` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Total of all folio charge lines (including VAT)',
  `tourism_levy_amount` decimal(10,2) DEFAULT '0.00' COMMENT 'Tourism levy amount charged',
  `tourism_levy_percent` decimal(5,2) DEFAULT '0.00' COMMENT 'Tourism levy percentage applied'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `booking_reference`, `room_id`, `individual_room_id`, `guest_name`, `guest_email`, `guest_phone`, `guest_country`, `guest_address`, `number_of_guests`, `adult_guests`, `child_guests`, `child_price_multiplier`, `check_in_date`, `check_out_date`, `number_of_nights`, `total_amount`, `child_supplement_total`, `amount_paid`, `amount_due`, `vat_rate`, `vat_amount`, `total_with_vat`, `last_payment_date`, `special_requests`, `status`, `is_tentative`, `tentative_expires_at`, `deposit_required`, `deposit_amount`, `deposit_paid`, `deposit_paid_at`, `reminder_sent`, `reminder_sent_at`, `converted_to_confirmed_at`, `expired_at`, `tentative_notes`, `payment_status`, `payment_amount`, `payment_date`, `created_at`, `updated_at`, `expires_at`, `converted_from_tentative`, `occupancy_type`, `final_invoice_generated`, `final_invoice_path`, `final_invoice_number`, `final_invoice_sent_at`, `checkout_completed_at`, `folio_charges_total`, `tourism_levy_amount`, `tourism_levy_percent`) VALUES
(41, 'LSH20266943', 2, NULL, 'JOHN-PAUL CHIRWA', 'johnpaulchirwa@gmail.com', '0860081635', 'Ireland', '10 Lois na Coille\r\nBallykilmurray, Tullamore', 2, 2, 0, 0.00, '2026-03-14', '2026-03-15', 1, 250000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '', 'pending', 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 'unpaid', 0.00, NULL, '2026-02-28 16:49:42', '2026-02-28 16:49:42', NULL, 0, 'double', 0, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `booking_charges`
--

CREATE TABLE `booking_charges` (
  `id` int UNSIGNED NOT NULL,
  `booking_id` int UNSIGNED NOT NULL,
  `charge_type` enum('room','food','drink','service','minibar','custom','breakfast','room_service','laundry','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `source_item_id` int UNSIGNED DEFAULT NULL COMMENT 'FK to menu item ID if applicable (food_menu.id or drink_menu.id)',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Snapshot of charge description at time of creation',
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Snapshot of unit price at time of creation',
  `line_subtotal` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'quantity * unit_price',
  `vat_rate` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'VAT rate percentage for this line',
  `vat_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'VAT amount for this line',
  `line_total` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'line_subtotal + vat_amount',
  `posted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When charge was posted to folio',
  `added_by` int UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who added the charge',
  `voided` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether charge is voided/reversed',
  `voided_at` datetime DEFAULT NULL COMMENT 'When charge was voided',
  `void_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Reason for voiding',
  `voided_by` int UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who voided the charge',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Booking folio charges - tracks all extras and room charges with audit trail';

-- --------------------------------------------------------

--
-- Table structure for table `booking_date_adjustments`
--

CREATE TABLE `booking_date_adjustments` (
  `id` int UNSIGNED NOT NULL,
  `booking_id` int UNSIGNED NOT NULL,
  `booking_reference` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_check_in_date` date NOT NULL COMMENT 'Previous check-in date',
  `new_check_in_date` date NOT NULL COMMENT 'New check-in date',
  `old_check_out_date` date NOT NULL COMMENT 'Previous check-out date',
  `new_check_out_date` date NOT NULL COMMENT 'New check-out date',
  `old_number_of_nights` int NOT NULL COMMENT 'Previous number of nights',
  `new_number_of_nights` int NOT NULL COMMENT 'New number of nights',
  `old_total_amount` decimal(10,2) NOT NULL COMMENT 'Previous booking total amount',
  `new_total_amount` decimal(10,2) NOT NULL COMMENT 'New booking total amount',
  `amount_delta` decimal(10,2) NOT NULL COMMENT 'Difference in amount (positive = additional charge, negative = refund)',
  `adjustment_reason` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Reason for the adjustment',
  `adjusted_by` int UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who made the adjustment',
  `adjusted_by_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Admin user name who made the adjustment',
  `adjustment_timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the adjustment was made',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP address of the admin making the adjustment',
  `metadata` json DEFAULT NULL COMMENT 'Additional metadata (e.g., room rate at time of adjustment)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Booking date adjustments audit trail with financial impact tracking';

-- --------------------------------------------------------

--
-- Table structure for table `booking_email_templates`
--

CREATE TABLE `booking_email_templates` (
  `id` int NOT NULL,
  `template_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `html_body` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `text_body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_email_templates`
--

INSERT INTO `booking_email_templates` (`id`, `template_key`, `template_name`, `subject`, `html_body`, `text_body`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'booking_received', 'Booking Received (Customer)', 'Booking Received - {{site_name}} [{{booking_reference}}]', '<h1 style=\"color:#1A1A1A;text-align:center;\">Booking Received - Awaiting Confirmation</h1><p>Dear {{guest_name}},</p><p>Thank you for your booking request with <strong>{{site_name}}</strong>.</p><p><strong>Reference:</strong> {{booking_reference}}<br><strong>Room:</strong> {{room_name}}<br><strong>Check-in:</strong> {{check_in_date_formatted}}<br><strong>Check-out:</strong> {{check_out_date_formatted}}<br><strong>Nights:</strong> {{number_of_nights}}<br><strong>Guests:</strong> {{number_of_guests}}<br><strong>Total:</strong> {{currency_symbol}} {{total_amount_formatted}}</p><p>{{payment_policy}}</p><p>Contact: <a href=\"mailto:{{contact_email}}\">{{contact_email}}</a> | {{phone_main}}</p>', '', 1, '2026-02-15 21:56:52', '2026-02-15 21:56:52'),
(2, 'booking_confirmed', 'Booking Confirmed (Customer)', 'Booking Confirmed - {{site_name}} [{{booking_reference}}]', '<h1 style=\"color:#1A1A1A;text-align:center;\">Booking Confirmed!</h1><p>Dear {{guest_name}},</p><p>Your booking with <strong>{{site_name}}</strong> is confirmed.</p><p><strong>Reference:</strong> {{booking_reference}}<br><strong>Room:</strong> {{room_name}}<br><strong>Check-in:</strong> {{check_in_date_formatted}}<br><strong>Check-out:</strong> {{check_out_date_formatted}}<br><strong>Nights:</strong> {{number_of_nights}}<br><strong>Guests:</strong> {{number_of_guests}}<br><strong>Total:</strong> {{currency_symbol}} {{total_amount_formatted}}</p><p>{{payment_policy}}</p><p>Check-in: {{check_in_time}} | Check-out: {{check_out_time}}</p><p>Contact: <a href=\"mailto:{{contact_email}}\">{{contact_email}}</a> | {{phone_main}}</p>', '', 1, '2026-02-15 21:56:52', '2026-02-15 21:56:52'),
(3, 'booking_cancelled', 'Booking Cancelled (Customer)', 'Booking Cancelled - {{site_name}} [{{booking_reference}}]', '<h1 style=\"color:#dc3545;text-align:center;\">Booking Cancelled</h1><p>Dear {{guest_name}},</p><p>Your booking with <strong>{{site_name}}</strong> has been cancelled.</p><p><strong>Reference:</strong> {{booking_reference}}<br><strong>Room:</strong> {{room_name}}<br><strong>Check-in:</strong> {{check_in_date_formatted}}<br><strong>Check-out:</strong> {{check_out_date_formatted}}<br><strong>Total:</strong> {{currency_symbol}} {{total_amount_formatted}}</p><p><strong>Reason:</strong> {{cancellation_reason}}</p><p>Contact: <a href=\"mailto:{{contact_email}}\">{{contact_email}}</a> | {{phone_main}}</p>', '', 1, '2026-02-15 21:56:52', '2026-02-15 21:56:52'),
(4, 'payment_invoice', 'Payment Invoice (Customer)', 'Payment Invoice - {{site_name}} [{{booking_reference}}]', '<h1 style=\"color:#1A1A1A;text-align:center;\">Payment Confirmed</h1><p>Dear {{guest_name}},</p><p>Thank you. Your payment has been received for booking <strong>{{booking_reference}}</strong>.</p><p><strong>Room:</strong> {{room_name}}<br><strong>Check-in:</strong> {{check_in_date_formatted}}<br><strong>Check-out:</strong> {{check_out_date_formatted}}<br><strong>Total Paid:</strong> {{currency_symbol}} {{total_amount_formatted}}</p><p>Your invoice is attached to this email.</p><p>Contact: <a href=\"mailto:{{contact_email}}\">{{contact_email}}</a> | {{phone_main}}</p>', '', 1, '2026-02-15 21:56:52', '2026-02-15 21:56:52');

-- --------------------------------------------------------

--
-- Table structure for table `booking_notes`
--

CREATE TABLE `booking_notes` (
  `id` int UNSIGNED NOT NULL,
  `booking_id` int UNSIGNED NOT NULL,
  `note_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_payments`
--

CREATE TABLE `booking_payments` (
  `id` int UNSIGNED NOT NULL,
  `booking_id` int UNSIGNED NOT NULL,
  `booking_reference` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_type` enum('deposit','full','partial','refund') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'MWK',
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_status` enum('pending','processing','completed','failed','cancelled','refunded') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `payment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_by` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_timeline_logs`
--

CREATE TABLE `booking_timeline_logs` (
  `id` int UNSIGNED NOT NULL,
  `booking_id` int UNSIGNED NOT NULL,
  `booking_reference` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_type` enum('create','update','status_change','payment','cancellation','email','check_in','check_out','conversion','reminder','expiry','note') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `old_value` text COLLATE utf8mb4_unicode_ci,
  `new_value` text COLLATE utf8mb4_unicode_ci,
  `performed_by_type` enum('guest','admin','system','api') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `performed_by_id` int DEFAULT NULL,
  `performed_by_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_timeline_logs`
--

INSERT INTO `booking_timeline_logs` (`id`, `booking_id`, `booking_reference`, `action`, `action_type`, `description`, `old_value`, `new_value`, `performed_by_type`, `performed_by_id`, `performed_by_name`, `ip_address`, `user_agent`, `metadata`, `created_at`) VALUES
(9, 41, 'LSH20266943', 'Booking created', 'create', 'New booking created for 1 night(s) - Total: 250000', NULL, 'pending', 'guest', NULL, 'JOHN-PAUL CHIRWA', '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '{\"total\": 250000, \"guests\": 2, \"room_id\": \"2\", \"check_in\": \"2026-03-14\", \"check_out\": \"2026-03-15\", \"is_tentative\": 0}', '2026-02-28 16:49:42');

-- --------------------------------------------------------

--
-- Table structure for table `cancellation_log`
--

CREATE TABLE `cancellation_log` (
  `id` int UNSIGNED NOT NULL,
  `booking_id` int UNSIGNED NOT NULL,
  `booking_reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `booking_type` enum('room','conference') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'room',
  `guest_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancellation_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cancelled_by` int NOT NULL,
  `cancellation_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `email_sent` tinyint(1) DEFAULT '0',
  `email_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for all booking cancellations with email tracking';

-- --------------------------------------------------------

--
-- Table structure for table `conference_bookings`
--

CREATE TABLE `conference_bookings` (
  `id` int UNSIGNED NOT NULL,
  `booking_reference` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `conference_room_id` int UNSIGNED DEFAULT NULL,
  `organization_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_phone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `number_of_attendees` int NOT NULL DEFAULT '1',
  `setup_requirements` text COLLATE utf8mb4_unicode_ci,
  `catering_required` tinyint(1) DEFAULT '0',
  `catering_details` text COLLATE utf8mb4_unicode_ci,
  `av_requirements` text COLLATE utf8mb4_unicode_ci,
  `special_requests` text COLLATE utf8mb4_unicode_ci,
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `amount_paid` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_status` enum('unpaid','partial','paid') COLLATE utf8mb4_unicode_ci DEFAULT 'unpaid',
  `status` enum('pending','tentative','confirmed','cancelled','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `is_tentative` tinyint(1) DEFAULT '0',
  `tentative_expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conference_inquiries`
--

CREATE TABLE `conference_inquiries` (
  `id` int NOT NULL,
  `inquiry_reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `conference_room_id` int NOT NULL,
  `company_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `number_of_attendees` int NOT NULL,
  `event_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `special_requirements` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `catering_required` tinyint(1) DEFAULT '0',
  `av_equipment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','confirmed','cancelled','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `total_amount` decimal(10,2) DEFAULT NULL,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Total amount paid so far',
  `amount_due` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Remaining amount to be paid',
  `vat_rate` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'VAT rate applied',
  `vat_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'VAT amount',
  `total_with_vat` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Total amount including VAT',
  `last_payment_date` date DEFAULT NULL COMMENT 'Date of last payment',
  `deposit_required` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether deposit is required',
  `deposit_amount` decimal(10,2) DEFAULT NULL COMMENT 'Required deposit amount',
  `deposit_paid` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether deposit has been paid',
  `payment_status` enum('pending','deposit_paid','full_paid','refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `total_paid` decimal(10,2) DEFAULT '0.00',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conference_rooms`
--

CREATE TABLE `conference_rooms` (
  `id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacity` int NOT NULL,
  `size_sqm` decimal(10,2) DEFAULT NULL,
  `daily_rate` decimal(10,2) NOT NULL,
  `amenities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `conference_rooms`
--

INSERT INTO `conference_rooms` (`id`, `name`, `description`, `capacity`, `size_sqm`, `daily_rate`, `amenities`, `image_path`, `is_active`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'Conference Room 1', 'Large conference space for seminars, workshops, and corporate events. Can be divided for smaller groups.', 250, 35.00, 400000.00, 'Video Conferencing, Smart TV, Whiteboard, High-Speed WiFi, Coffee Service, Sweets, Projector hire', 'images/conference/conference_1771583184_4949.jpeg', 1, 1, '2026-01-20 22:35:58', '2026-02-20 10:26:49');

-- --------------------------------------------------------

--
-- Table structure for table `contact_inquiries`
--

CREATE TABLE `contact_inquiries` (
  `id` int UNSIGNED NOT NULL,
  `reference_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `consent` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('new','read','replied','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cookie_consent_log`
--

CREATE TABLE `cookie_consent_log` (
  `id` int UNSIGNED NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consent_level` enum('all','essential','declined') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cookie_consent_log`
--

INSERT INTO `cookie_consent_log` (`id`, `ip_address`, `user_agent`, `consent_level`, `created_at`) VALUES
(85, '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'all', '2026-02-28 16:47:32'),
(86, '102.70.92.212', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.3 Mobile/15E148 Safari/604.1', 'all', '2026-02-28 19:44:50'),
(87, '216.234.217.124', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', 'declined', '2026-02-28 20:16:19'),
(88, '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'all', '2026-03-01 17:15:40'),
(89, '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'all', '2026-03-01 17:17:08');

-- --------------------------------------------------------

--
-- Table structure for table `drink_menu`
--

CREATE TABLE `drink_menu` (
  `id` int NOT NULL,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Coffee, Wine, Cocktails, Beer, Non-Alcoholic, etc.',
  `item_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,2) NOT NULL,
  `currency_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'MWK',
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT '1',
  `is_featured` tinyint(1) DEFAULT '0' COMMENT 'Featured items shown prominently',
  `tags` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Comma-separated tags',
  `display_order` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `drink_menu`
--

INSERT INTO `drink_menu` (`id`, `category`, `item_name`, `description`, `price`, `currency_code`, `image_path`, `is_available`, `is_featured`, `tags`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'Coffee', 'Mzuzu Coffee', 'Premium Malawian coffee', 6000.00, 'MWK', NULL, 1, 1, NULL, 1, '2026-02-11 22:36:14', '2026-02-11 22:36:14'),
(2, 'Coffee', 'Espresso Single Shot', 'Rich single shot espresso', 3000.00, 'MWK', NULL, 1, 0, NULL, 2, '2026-02-11 22:36:14', '2026-02-11 22:36:14'),
(3, 'Coffee', 'Cappuccino', 'Creamy cappuccino with foam', 8000.00, 'MWK', NULL, 1, 1, NULL, 3, '2026-02-11 22:36:14', '2026-02-11 22:36:14'),
(4, 'Coffee', 'Hot Chocolate', 'Rich hot chocolate', 10000.00, 'MWK', NULL, 1, 1, NULL, 4, '2026-02-11 22:36:14', '2026-02-11 22:36:14'),
(5, 'Non-Alcoholic', 'Bottled Water', 'Pure bottled water', 2000.00, 'MWK', NULL, 1, 1, NULL, 5, '2026-02-11 22:36:14', '2026-02-11 22:36:14'),
(6, 'Non-Alcoholic', 'Coke/Fanta Can', 'Soft drink can', 6000.00, 'MWK', NULL, 1, 0, NULL, 6, '2026-02-11 22:36:14', '2026-02-11 22:36:14'),
(7, 'Non-Alcoholic', 'Appletiser', 'Sparkling apple juice', 12000.00, 'MWK', NULL, 1, 1, NULL, 7, '2026-02-11 22:36:14', '2026-02-11 22:36:14'),
(8, 'Beer', 'Carlsberg Green', 'Premium Danish lager', 4000.00, 'MWK', NULL, 1, 0, NULL, 8, '2026-02-11 22:36:14', '2026-02-11 22:36:14'),
(9, 'Beer', 'Kuche-Kuche', 'Malawian beer', 4000.00, 'MWK', NULL, 1, 1, NULL, 9, '2026-02-11 22:36:14', '2026-02-11 22:36:14'),
(10, 'Beer', 'Windhoek Lager/Draft', 'Namibian beer', 10000.00, 'MWK', NULL, 1, 1, NULL, 10, '2026-02-11 22:36:14', '2026-02-11 22:36:14'),
(11, 'Beer', 'Heineken Beer', 'Dutch lager', 10000.00, 'MWK', NULL, 1, 1, NULL, 11, '2026-02-11 22:36:14', '2026-02-11 22:36:14'),
(12, 'Wine', 'Nederburg Red Wines', 'Premium South African red wine', 58000.00, 'MWK', NULL, 1, 1, NULL, 12, '2026-02-11 22:36:14', '2026-02-11 22:36:14'),
(13, 'Cocktails', 'Chapman', 'Chapman cocktail blend', 6500.00, 'MWK', NULL, 1, 1, NULL, 13, '2026-02-11 22:36:14', '2026-02-11 22:36:14');

-- --------------------------------------------------------

--
-- Table structure for table `email_settings`
--

CREATE TABLE `email_settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `setting_group` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'email',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_encrypted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_settings`
--

INSERT INTO `email_settings` (`id`, `setting_key`, `setting_value`, `setting_group`, `description`, `is_encrypted`, `created_at`, `updated_at`) VALUES
(1, 'smtp_password', '2:p2WpmX[0YTs7', 'smtp', '', 0, '2026-01-27 09:51:05', '2026-02-17 10:38:48'),
(2, 'email_development_mode', '0', 'general', '', 0, '2026-01-27 09:51:06', '2026-02-17 10:38:48'),
(3, 'smtp_host', 'mail.promanaged-it.com', 'smtp', '', 0, '2026-01-27 09:51:06', '2026-02-17 10:38:48'),
(4, 'smtp_port', '465', 'smtp', '', 0, '2026-01-27 09:51:06', '2026-02-17 10:38:48'),
(5, 'smtp_username', 'info@promanaged-it.com', 'smtp', '', 0, '2026-01-27 09:51:07', '2026-02-17 10:38:48'),
(6, 'smtp_secure', 'ssl', 'smtp', '', 0, '2026-01-27 09:51:07', '2026-02-17 10:38:48'),
(7, 'smtp_timeout', '30', 'smtp', 'SMTP connection timeout in seconds', 0, '2026-01-27 09:51:08', '2026-01-27 09:51:08'),
(8, 'smtp_debug', '0', 'smtp', 'SMTP debug level (0-4)', 0, '2026-01-27 09:51:08', '2026-01-27 09:51:08'),
(9, 'email_from_name', 'Rosalyn\'s Beach Hotel', 'general', '', 0, '2026-01-27 09:51:09', '2026-02-17 10:38:48'),
(10, 'email_from_email', 'johnpaulchirwa@gmail.com', 'general', '', 0, '2026-01-27 09:51:09', '2026-02-17 10:38:48'),
(11, 'email_admin_email', 'johnpaulchirwa@gmail.com', 'general', '', 0, '2026-01-27 09:51:10', '2026-02-17 10:38:48'),
(12, 'email_bcc_admin', '1', 'general', '', 0, '2026-01-27 09:51:10', '2026-02-17 10:38:48'),
(13, 'email_log_enabled', '1', 'general', '', 0, '2026-01-27 09:51:11', '2026-02-17 10:38:49'),
(14, 'email_preview_enabled', '1', 'general', '', 0, '2026-01-27 09:51:11', '2026-02-17 10:38:49'),
(147, 'invoice_recipients', 'accounts@promanaged-it.com', 'invoicing', 'accounts@promanaged-it.com', 0, '2026-01-27 16:00:35', '2026-02-05 14:01:26'),
(148, 'send_invoice_emails', '1', 'invoicing', 'Send invoice emails when payment is marked as paid (1=yes, 0=no)', 0, '2026-01-27 16:00:35', '2026-01-27 16:00:35');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int NOT NULL,
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `location` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ticket_price` decimal(10,2) DEFAULT '0.00',
  `capacity` int DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT '0',
  `show_in_upcoming` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `video_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path to event video file',
  `video_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Video MIME type (video/mp4, video/webm, etc.)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon_class` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `page_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`id`, `name`, `slug`, `description`, `short_description`, `icon_class`, `page_url`, `image_url`, `is_featured`, `is_active`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'Restaurant', 'fine-dining', 'Our restaurant serves tasty local and international dishes. Open for breakfast, lunch, and dinner. Enjoy good food at affordable prices.', 'Good food at reasonable prices', 'fas fa-utensils', 'restaurant.php', NULL, 1, 1, 1, '2026-01-19 20:22:49', '2026-02-07 02:40:52'),
(3, 'Swimming Pool', 'swimming-pool', 'Outdoor swimming pool perfect for cooling off and relaxing. Pool area with seating available.', 'Refreshing outdoor pool', 'fas fa-swimming-pool', NULL, NULL, 1, 1, 3, '2026-01-19 20:22:49', '2026-02-07 02:40:52'),
(5, 'WiFi Internet', 'wifi', 'Complimentary WiFi available throughout the hotel for all guests.', 'Free internet access', 'fas fa-wifi', NULL, NULL, 1, 1, 5, '2026-01-19 20:22:49', '2026-02-07 02:40:52'),
(6, 'Front Desk Service', 'concierge', 'Our front desk is available to help with check-in, information, and assistance during your stay.', 'Helpful front desk staff', 'fas fa-concierge-bell', NULL, NULL, 1, 1, 6, '2026-01-19 20:22:49', '2026-02-07 02:40:52');

-- --------------------------------------------------------

--
-- Table structure for table `food_menu`
--

CREATE TABLE `food_menu` (
  `id` int NOT NULL,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Breakfast, Lunch, Dinner, Desserts, etc.',
  `item_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,2) NOT NULL,
  `currency_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'MWK',
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT '1',
  `is_featured` tinyint(1) DEFAULT '0' COMMENT 'Featured items shown prominently',
  `is_vegetarian` tinyint(1) DEFAULT '0',
  `is_vegan` tinyint(1) DEFAULT '0',
  `allergens` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Comma-separated allergen list',
  `display_order` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `food_menu`
--

INSERT INTO `food_menu` (`id`, `category`, `item_name`, `description`, `price`, `currency_code`, `image_path`, `is_available`, `is_featured`, `is_vegetarian`, `is_vegan`, `allergens`, `display_order`, `created_at`, `updated_at`) VALUES
(34, 'Starters', 'Cream of Mushroom Soup', 'Savor the rich, velvety goodness of our cream of mushroom soup, crafted with tender mushrooms, caramelized onions, and a touch of cream. Perfectly paired with a warm, crusty bread roll.', 15275.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 1, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(35, 'Starters', 'Vegetable Soup', 'Enjoy a hearty blend of seasonal vegetables simmered to perfection in a savory broth. This nourishing soup is served piping hot with a side of freshly baked bread roll.', 15275.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 2, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(36, 'Starters', 'Chicken Soup', 'Indulge in a rich and flavorful chicken soup, brimming with tender fillet pieces and fresh garden vegetables. Served with a warm bread roll to complete this comforting classic.', 18800.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 3, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(37, 'Starters', 'Garden Salad', 'A vibrant medley of crisp lettuce, juicy tomatoes, crunchy cucumbers, and sweet carrots, all tossed in a refreshing lemon herb dressing.', 16450.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 4, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(38, 'Mains', 'Grilled Pork Loin Chop', 'Experience the succulent flavour of our thick and juicy pork loin chop, marinated in a signature blend of spices and herbs, grilled to perfection, and served alongside a medley of grilled vegetables.', 47000.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 5, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(39, 'Mains', 'Get Your Rib Fix', 'Indulge in tender, juicy pork ribs, generously smothered in our signature barbecue sauce, and paired with creamy coleslaw for the ultimate comfort food experience.', 58750.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 6, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(40, 'Mains', 'Grilled Quarter Chicken', 'Enjoy a flavourful quarter chicken, marinated in our special blend of herbs and spices, then grilled to juicy perfection.', 35250.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 7, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(41, 'Mains', 'Lemon Creamy Chicken', 'Treat yourself to tender chicken fillet enveloped in a luscious, creamy lemon butter sauce.', 41125.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 8, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(42, 'Mains', 'Savory Pot-Fried Goat', 'Savor the authentic flavors of tender goat meat, marinated in a blend of traditional spices and fried to perfection in a traditional pot. This dish offers a rich, succulent taste that is sure to delight your palate.', 41125.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 9, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(43, 'Mains', 'T-Bone Steak with Mushroom Sauce', 'Indulge in a tender, juicy T-bone steak, served with a rich and savoury mushroom gravy. All meals served with a choice of rice, nsima, chips, mashed potatoes, and seasonal vegetables.', 52875.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 10, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(44, 'Local Corner', 'Road Runner Grilled', 'Delight in the bold flavors of free-range chicken marinated in fiery Kambuzi chili and grilled to perfection.', 41000.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 11, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(45, 'Local Corner', 'Grilled Chambo', 'Savor the taste of whole or open chambo, expertly grilled and served with our homemade tartar sauce.', 41125.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 12, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(46, 'Local Corner', 'Grilled Mcheni', 'Enjoy the exquisite flavour of open Mcheni, grilled to perfection and paired with our homemade tartar sauce.', 41125.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 13, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(47, 'Local Corner', 'Chambo Fillet', 'Treat yourself to a pan-seared Chambo fillet, finished with a zesty lemon butter sauce.', 47000.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 14, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(48, 'Local Corner', 'Catch of the Day', 'Ask your waiter for the freshest catch of the day, prepared with our special Makawa twist.', 41125.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 15, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(49, 'Pasta', 'Creamy Pasta Alfredo', 'Delight in fettuccine pasta smothered in a rich, creamy Alfredo sauce, topped with a sprinkle of parmesan cheese.', 35250.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 16, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(50, 'Pasta', 'Spaghetti Bolognese', 'Enjoy spaghetti pasta generously coated in a hearty, homemade Bolognese sauce, crafted with ground beef and a blend of aromatic herbs and spices.', 35250.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 17, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(51, 'Vegetables', 'Vegetable Curry', 'Aromatic and flavorful vegetable curry, simmered with a blend of spices and served with a bun.', 23500.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 18, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(52, 'Vegetables', 'Local Vegetables in Peanut Sauce', 'Local vegetables cooked to perfection and smothered in a rich, creamy peanut sauce.', 5875.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 19, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(53, 'Vegetables', 'Mixed Vegetables', 'A colorful assortment of seasonal vegetables, steamed to perfection.', 5875.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 20, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(54, 'Quick & Easy', 'Beef Burger', 'Juicy beef patty, grilled to perfection and served with fresh toppings.', 25850.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 21, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(55, 'Quick & Easy', 'Chicken Burger', 'Crispy chicken fillet, topped with fresh lettuce and tomato, served on a toasted bun.', 28200.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 22, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(56, 'Quick & Easy', 'Chicken Wrap', 'Tender chicken, wrapped with fresh vegetables and a tangy sauce.', 28200.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 23, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(57, 'Quick & Easy', 'Chicken Pizza', 'Crispy pizza crust topped with succulent chicken and melted cheese.', 37600.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 24, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(58, 'Quick & Easy', 'Chicken Mayo Sandwich', 'Savory chicken mixed with creamy mayonnaise, served on fresh bread.', 28200.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 25, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(59, 'Quick & Easy', 'Cheese and Tomato Sandwich', 'A classic combination of melted cheese and fresh tomato slices.', 25850.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 26, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(60, 'Quick & Easy', 'Gizzards', 'Deliciously seasoned and cooked to perfection.', 23500.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 27, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(61, 'Quick & Easy', 'Meatballs', 'Savory meatballs, seasoned with a blend of herbs and spices.', 23500.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 28, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(62, 'Quick & Easy', 'Samosas', 'Crispy pastry pockets filled with a savory mixture of vegetables or meat.', 23500.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 29, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(63, 'Sweet Corner', 'Chocolate Gateaux', 'Indulge in a velvety-smooth chocolate cake, served with a dollop of creamy whipped cream.', 14500.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 30, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(64, 'Sweet Corner', 'Ice Cream', 'Ask your waiter for the available flavors and enjoy a scoop of creamy, delicious ice cream.', 14100.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 31, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(65, 'Sweet Corner', 'Seasonal Fruit Salad', 'Enjoy a refreshing mix of the freshest seasonal fruits, bursting with natural sweetness.', 11750.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 32, '2026-02-11 22:57:37', '2026-02-11 22:57:37'),
(66, 'Sweet Corner', 'Classic Waffle and Ice Cream', 'Warm, golden waffle served with a scoop of vanilla ice cream and a drizzle of rich chocolate sauce.', 16450.00, 'MWK', NULL, 1, 0, 0, 0, NULL, 33, '2026-02-11 22:57:37', '2026-02-11 22:57:37');

-- --------------------------------------------------------

--
-- Table structure for table `footer_links`
--

CREATE TABLE `footer_links` (
  `id` int NOT NULL,
  `column_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `link_text` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `link_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `secondary_link_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `footer_links`
--

INSERT INTO `footer_links` (`id`, `column_name`, `link_text`, `link_url`, `secondary_link_url`, `display_order`, `is_active`) VALUES
(1, 'About Hotel', 'About Us', 'editorial-about-container', 'index#editorial-about-container', 1, 1),
(2, 'About Hotel', 'Sustainability', 'editorial-about-container', 'index#editorial-about-container', 2, 1),
(3, 'About Hotel', 'Awards', 'editorial-about-container', 'index#editorial-about-container', 3, 1),
(4, 'About Hotel', 'History', 'editorial-about-container', 'index#editorial-about-container', 4, 1),
(5, 'Guest Services', 'Rooms & Suites', '#rooms', NULL, 1, 1),
(6, 'Guest Services', 'Facilities', '#facilities', NULL, 2, 1),
(17, 'Guest Services', 'Events', '#events', NULL, 3, 1),
(18, 'Guest Services', 'All Services', 'guest-services.php', NULL, 4, 1),
(26, 'Dining & Entertainment', 'Restaurant', 'restaurant.php#menu', NULL, 1, 1),
(28, 'Guest Services', 'Conference Rooms', 'conference.php', NULL, 5, 1),
(31, 'Quick Links', 'Contact Us', 'contact-us.php', 'contact-us.php', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `gallery`
--

CREATE TABLE `gallery` (
  `id` int NOT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `room_id` int DEFAULT NULL,
  `display_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gallery`
--

INSERT INTO `gallery` (`id`, `title`, `description`, `image_url`, `category`, `room_id`, `display_order`, `is_active`, `created_at`) VALUES
(2, 'Fine Dining Restaurant', 'World-class cuisine', 'images/hotel-exterior-1024x572.jpg', 'dining', NULL, 2, 1, '2026-01-19 20:22:49'),
(3, 'Olympic Pool', 'Heated swimming pool', 'images/hotel-exterior-1024x572.jpg', 'facilities', NULL, 3, 1, '2026-01-19 20:22:49'),
(4, 'Hotel Exterior', 'Main entrance', 'images/hotel-exterior-1024x572.jpg', 'exterior', NULL, 4, 1, '2026-01-19 20:22:49'),
(5, 'Luxury Spa', 'Wellness center', 'images/hotel-exterior-1024x572.jpg', 'facilities', NULL, 5, 1, '2026-01-19 20:22:49'),
(6, 'Sunset View', 'Evening beauty', 'images/hotel-exterior-1024x572.jpg', 'exterior', NULL, 6, 1, '2026-01-19 20:22:49'),
(7, 'Presidential Suite Living Area', 'Spacious living area with premium furnishings', '', 'rooms', NULL, NULL, 1, '2026-01-20 07:57:13'),
(8, 'Hotel Exterior View', 'Stunning hotel facade during golden hour', 'images/gallery/hotel-exterior_1-1024x572.jpg', 'exterior', NULL, 8, 1, '2026-01-20 07:57:13'),
(9, 'Pool Area Relaxation', 'Olympic pool with panoramic views', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(2).da074be04e999b56828a.jpeg', 'facilities', NULL, 9, 1, '2026-01-20 07:57:13'),
(10, 'Fitness Excellence', 'State-of-the-art fitness facilities', 'images/gallery/fitness-center-1024x683.jpg', 'facilities', NULL, 10, 1, '2026-01-20 07:57:13'),
(11, 'Presidential Suite Living Area', 'Spacious living area with premium furnishings', 'images/gallery/hotel-lobby.jpg', 'rooms', NULL, NULL, 1, '2026-01-20 08:03:22'),
(12, 'Hotel Exterior View', 'Stunning hotel facade during golden hour', 'images/gallery/hotel-exterior_1-1024x572.jpg', 'exterior', NULL, 8, 1, '2026-01-20 08:03:22'),
(13, 'Pool Area Relaxation', 'Olympic pool with panoramic views', 'images/gallery/pool-area-1024x683.jpg', 'facilities', NULL, 9, 1, '2026-01-20 08:03:22'),
(14, 'Fitness Excellence', 'State-of-the-art fitness facilities', 'images/gallery/fitness-center-1024x683.jpg', 'facilities', NULL, 10, 1, '2026-01-20 08:03:22'),
(15, 'Presidential Suite Living Area', 'Spacious living area with premium furnishings', 'images/gallery/hotel-lobby.jpg', 'rooms', NULL, NULL, 1, '2026-01-20 08:07:18'),
(16, 'Hotel Exterior View', 'Stunning hotel facade during golden hour', 'images/gallery/hotel-exterior_1-1024x572.jpg', 'exterior', NULL, 8, 1, '2026-01-20 08:07:18'),
(17, 'Pool Area Relaxation', 'Olympic pool with panoramic views', 'images/gallery/pool-area-1024x683.jpg', 'facilities', NULL, 9, 1, '2026-01-20 08:07:18'),
(18, 'Fitness Excellence', 'State-of-the-art fitness facilities', 'images/gallery/fitness-center-1024x683.jpg', 'facilities', NULL, 10, 1, '2026-01-20 08:07:18'),
(23, 'Executive Suite - Bedroom', 'Premium bedroom with king bed', 'images/rooms/executive-bedroom.jpg', 'rooms', 2, 1, 1, '2026-01-20 16:07:07'),
(24, 'Executive Suite - Work Area', 'Dedicated workspace with desk and business amenities', 'images/rooms/executive-work.jpg', 'rooms', 2, 2, 1, '2026-01-20 16:07:07'),
(25, 'Executive Suite - Lounge', 'Comfortable lounge area', 'images/rooms/executive-lounge.jpg', 'rooms', 2, 3, 1, '2026-01-20 16:07:07'),
(26, 'Executive Suite - Bathroom', 'Modern bathroom with premium toiletries', 'images/rooms/executive-bathroom.jpg', 'rooms', 2, 4, 1, '2026-01-20 16:07:07'),
(35, 'Executive Suite - Bedroom', 'Premium bedroom with king bed', '', 'rooms', 2, 1, 1, '2026-01-20 16:31:10'),
(36, 'Executive Suite - Work Area', 'Dedicated workspace with desk and business amenities', 'https://source.unsplash.com/1200x1200/?hotel,workspace,desk', 'rooms', 2, 2, 1, '2026-01-20 16:31:10'),
(37, 'Executive Suite - Lounge', 'Comfortable lounge area', 'https://source.unsplash.com/1200x1200/?hotel,lounge,sofa', 'rooms', 2, 3, 1, '2026-01-20 16:31:10'),
(38, 'Executive Suite - Bathroom', 'Modern bathroom with premium toiletries', 'https://source.unsplash.com/1200x1200/?hotel,bathroom,modern', 'rooms', 2, 4, 1, '2026-01-20 16:31:10'),
(43, 'Bedroom', 'New View', '', 'rooms', 4, 0, 1, '2026-01-22 14:45:32');

-- --------------------------------------------------------

--
-- Table structure for table `guest_services`
--

CREATE TABLE `guest_services` (
  `id` int UNSIGNED NOT NULL,
  `service_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icon_class` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'fas fa-concierge-bell',
  `image_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_text` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Learn More',
  `display_order` int UNSIGNED DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `guest_services`
--

INSERT INTO `guest_services` (`id`, `service_key`, `title`, `description`, `icon_class`, `image_path`, `link_url`, `link_text`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'restaurant', 'Fine Dining Restaurant', 'Experience exquisite cuisine crafted by our talented chefs. From local delicacies to international dishes, our restaurant offers a culinary journey like no other.', 'fas fa-utensils', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(12).0842da12c539e2c09eab.jpeg', 'restaurant.php', 'View Restaurant', 1, 1, '2026-02-23 13:03:48', '2026-02-23 23:35:14'),
(3, 'conference', 'Conference & Meeting Rooms', 'Host your corporate events, meetings, and conferences in our state-of-the-art venues. Full AV equipment, catering, and dedicated event coordination.', 'fas fa-briefcase', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2010.01.01.d1946c3fcfdca0980d58.jpeg', 'conference.php', 'Book Conference', 3, 1, '2026-02-23 13:03:48', '2026-02-23 23:35:22'),
(4, 'events', 'Events & Entertainment', 'Discover upcoming events, live entertainment, and special occasions at our hotel. From business breakfasts to gala dinners, there is always something happening.', 'fas fa-calendar-alt', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56.88dce9e3acf192dac8d6.jpeg', 'events.php', 'View Events', 4, 1, '2026-02-23 13:03:48', '2026-02-23 23:35:33'),
(5, 'rooms', 'Rooms & Accommodation', 'Discover our luxurious rooms and suites, each designed for comfort and elegance. Book your perfect stay with us today.', 'fas fa-bed', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(18).35e879243a9ccdd6669e.jpeg', 'booking.php', 'Book a Room', 5, 1, '2026-02-23 13:03:48', '2026-02-23 23:34:04'),
(6, 'concierge', 'Concierge Services', 'Our dedicated concierge team is available 24/7 to assist with transportation, tours, restaurant reservations, and any special requests to make your stay memorable.', 'fas fa-concierge-bell', NULL, 'contact-us.php', 'Contact Concierge', 6, 1, '2026-02-23 13:03:48', '2026-02-23 13:03:48');

-- --------------------------------------------------------

--
-- Table structure for table `gym_classes`
--

CREATE TABLE `gym_classes` (
  `id` int NOT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `day_label` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `time_label` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `level_label` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'All Levels',
  `display_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gym_classes`
--

INSERT INTO `gym_classes` (`id`, `title`, `description`, `day_label`, `time_label`, `level_label`, `display_order`, `is_active`) VALUES
(13, 'Morning Yoga Flow', 'Start your day with energizing yoga sequences', 'Monday - Friday', '6:30 AM', 'All Levels', 1, 1),
(14, 'HIIT Bootcamp', 'High-intensity interval training for maximum results', 'Tuesday & Thursday', '7:00 AM', 'Intermediate', 2, 1),
(15, 'Pilates Core', 'Strengthen your core with controlled movements', 'Wednesday & Saturday', '8:00 AM', 'All Levels', 3, 1),
(16, 'Evening Meditation', 'Wind down with guided meditation and breathing', 'Daily', '6:00 PM', 'All Levels', 4, 1);

-- --------------------------------------------------------

--
-- Table structure for table `gym_content`
--

CREATE TABLE `gym_content` (
  `id` int NOT NULL,
  `hero_title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Fitness & Wellness Center',
  `hero_subtitle` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Health & Vitality',
  `hero_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `hero_image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'images/gym/hero-bg.jpg',
  `wellness_title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Transform Your Body & Mind',
  `wellness_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `wellness_image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'images/gym/fitness-center.jpg',
  `badge_text` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Award-Winning Facilities',
  `personal_training_image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'images/gym/personal-training.jpg',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gym_content`
--

INSERT INTO `gym_content` (`id`, `hero_title`, `hero_subtitle`, `hero_description`, `hero_image_path`, `wellness_title`, `wellness_description`, `wellness_image_path`, `badge_text`, `personal_training_image_path`, `is_active`, `created_at`, `updated_at`) VALUES
(4, 'Fitness Center', 'Stay Active', 'Our fitness center has the equipment you need to maintain your workout routine while traveling.', 'images/gym/hero-bg.jpg', 'Exercise Facilities', 'We offer basic gym equipment for cardio and strength training. Available to all hotel guests.', 'images/gym/fitness-center.jpg', 'Fitness Facilities Available', 'https://media.gettyimages.com/id/1773192171/photo/smiling-young-woman-leaning-on-barbell-at-health-club.jpg?s=1024x1024&w=gi&k=20&c=pzLyu0hPJmPgKV4TTs1sOld-TSvZ-uCt18LCsR4vsYU=', 1, '2026-01-20 15:26:43', '2026-02-07 02:40:53');

-- --------------------------------------------------------

--
-- Table structure for table `gym_facilities`
--

CREATE TABLE `gym_facilities` (
  `id` int NOT NULL,
  `icon_class` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fas fa-check',
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `display_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gym_facilities`
--

INSERT INTO `gym_facilities` (`id`, `icon_class`, `title`, `description`, `display_order`, `is_active`) VALUES
(19, 'fas fa-running', 'Cardio Zone', 'Treadmills, ellipticals, bikes, and rowers with entertainment screens and HR monitoring', 1, 1),
(20, 'fas fa-dumbbell', 'Strength Training', 'Full range of free weights, barbells, and functional rigs', 2, 1),
(21, 'fas fa-child', 'Yoga & Pilates Studio', 'Dedicated studio for yoga, pilates, and meditation with daily classes', 3, 1),
(22, 'fas fa-swimming-pool', 'Lap Pool', '25-meter heated pool ideal for swim workouts and aqua aerobics', 4, 1),
(23, 'fas fa-hot-tub', 'Spa & Sauna', 'Traditional sauna, steam room, and jacuzzi for recovery', 5, 1),
(24, 'fas fa-apple-alt', 'Nutrition Bar', 'Smoothies, protein shakes, and healthy snacks to fuel your workout', 6, 1);

-- --------------------------------------------------------

--
-- Table structure for table `gym_features`
--

CREATE TABLE `gym_features` (
  `id` int NOT NULL,
  `icon_class` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fas fa-dumbbell',
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `display_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gym_features`
--

INSERT INTO `gym_features` (`id`, `icon_class`, `title`, `description`, `display_order`, `is_active`) VALUES
(13, 'fas fa-dumbbell', 'Modern Equipment', 'Latest cardio machines, free weights, and resistance training equipment', 1, 1),
(14, 'fas fa-user-md', 'Personal Training', 'Certified trainers available for one-on-one sessions and customized programs', 2, 1),
(15, 'fas fa-spa', 'Spa & Recovery', 'Massage therapy, sauna, and steam rooms for post-workout relaxation', 3, 1),
(16, 'fas fa-clock', 'Flexible Hours', 'Open daily from 5:30 AM to 10:00 PM for your convenience', 4, 1);

-- --------------------------------------------------------

--
-- Table structure for table `gym_inquiries`
--

CREATE TABLE `gym_inquiries` (
  `id` int NOT NULL,
  `reference_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `membership_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preferred_date` date DEFAULT NULL,
  `preferred_time` time DEFAULT NULL,
  `guests` int DEFAULT '1',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `consent` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gym_packages`
--

CREATE TABLE `gym_packages` (
  `id` int NOT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_class` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'fas fa-leaf',
  `includes_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Line-separated bullet points',
  `duration_label` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `currency_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'MWK',
  `cta_text` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Book Package',
  `cta_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#book',
  `is_featured` tinyint(1) DEFAULT '0',
  `display_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gym_packages`
--

INSERT INTO `gym_packages` (`id`, `name`, `icon_class`, `includes_text`, `duration_label`, `price`, `currency_code`, `cta_text`, `cta_link`, `is_featured`, `display_order`, `is_active`) VALUES
(7, 'Rejuvenation Retreat', 'fas fa-leaf', '3 personal training sessions\nDaily yoga classes\n2 spa massages\nNutrition consultation\nComplimentary smoothie bar access', '5 Days', 45000.00, 'MWK', 'Book Package', '#book', 0, 1, 1),
(8, 'Ultimate Wellness', 'fas fa-star', '5 personal training sessions\nUnlimited group classes\n4 spa treatments\nFull nutrition program\nFitness assessment & tracking\nComplimentary wellness amenities', '7 Days', 8500.00, 'MWK', 'Book Package', '#book', 1, 2, 1),
(9, 'Fitness Kickstart', 'fas fa-dumbbell', '2 personal training sessions\nGroup class pass (5 classes)\n1 spa massage\nFitness assessment\nWorkout plan to take home', '3 Days', 28000.00, 'MWK', 'Book Package', '#book', 0, 3, 1);

-- --------------------------------------------------------

--
-- Table structure for table `hotel_gallery`
--

CREATE TABLE `hotel_gallery` (
  `id` int UNSIGNED NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `image_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `video_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path to video file or URL (e.g., Getty Images, YouTube, Vimeo)',
  `video_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Video MIME type (video/mp4, video/webm, etc.) or platform (youtube, vimeo, getty)',
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'general' COMMENT 'e.g., exterior, interior, rooms, facilities, dining, events',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `display_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hotel_gallery`
--

INSERT INTO `hotel_gallery` (`id`, `title`, `description`, `image_url`, `video_path`, `video_type`, `category`, `is_active`, `display_order`, `created_at`, `updated_at`) VALUES
(9, 'Rosalyn\'s Beach Hotel', '', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(18).35e879243a9ccdd6669e.jpeg', NULL, NULL, 'exterior', 1, 1, '2026-02-15 22:05:37', '2026-02-17 11:24:52'),
(10, 'Rosalyn\'s Beach Hotel', '', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.36.41%20(1).9dcda0cf8d98e649c98a.jpeg', NULL, NULL, 'facilities', 1, 2, '2026-02-15 22:05:37', '2026-02-17 11:25:03'),
(11, 'Pool', 'Luxury pool', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(2).da074be04e999b56828a.jpeg', NULL, NULL, 'exterior', 1, 3, '2026-02-22 11:11:37', '2026-02-22 11:11:37'),
(12, 'Outdoor', 'Outdoor chilling', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(14).e3534199c47be9e4b39f.jpeg', NULL, NULL, 'exterior', 1, 4, '2026-02-22 11:12:23', '2026-02-22 11:12:33'),
(13, 'Coffee Bar', 'Coffee Bar', 'https://www.rosalynsbeachhotel.com/static/media/DSC06307%20(1).1885c56188a87f218187.jpg', NULL, NULL, 'general', 1, 5, '2026-02-22 11:13:05', '2026-02-22 11:14:32'),
(14, 'Special Occasions', 'Special Occasions', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(24).97b0f95811c598fa167d.jpeg', NULL, NULL, 'general', 1, 6, '2026-02-22 11:14:59', '2026-02-22 11:14:59');

-- --------------------------------------------------------

--
-- Table structure for table `housekeeping_assignments`
--

CREATE TABLE `housekeeping_assignments` (
  `id` int UNSIGNED NOT NULL,
  `individual_room_id` int UNSIGNED NOT NULL,
  `status` enum('pending','in_progress','completed','blocked') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `assigned_to` int UNSIGNED DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `priority` enum('high','medium','low') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `assignment_type` enum('checkout_cleanup','regular_cleaning','maintenance','deep_clean','turn_down') COLLATE utf8mb4_unicode_ci DEFAULT 'regular_cleaning',
  `is_recurring` tinyint(1) DEFAULT '0',
  `recurring_pattern` enum('daily','weekly','monthly') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recurring_end_date` date DEFAULT NULL,
  `verified_by` int UNSIGNED DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `estimated_duration` int DEFAULT '30' COMMENT 'Estimated duration in minutes',
  `actual_duration` int DEFAULT NULL COMMENT 'Actual duration in minutes',
  `auto_created` tinyint(1) DEFAULT '0',
  `linked_booking_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `housekeeping_assignments`
--

INSERT INTO `housekeeping_assignments` (`id`, `individual_room_id`, `status`, `due_date`, `assigned_to`, `created_by`, `notes`, `completed_at`, `created_at`, `updated_at`, `priority`, `assignment_type`, `is_recurring`, `recurring_pattern`, `recurring_end_date`, `verified_by`, `verified_at`, `estimated_duration`, `actual_duration`, `auto_created`, `linked_booking_id`) VALUES
(1, 6, 'in_progress', '2026-02-22', 2, 1, '', NULL, '2026-02-22 10:52:08', '2026-02-22 10:52:08', 'high', 'deep_clean', 0, NULL, NULL, NULL, NULL, 30, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `housekeeping_audit_log`
--

CREATE TABLE `housekeeping_audit_log` (
  `id` int UNSIGNED NOT NULL,
  `assignment_id` int UNSIGNED NOT NULL COMMENT 'FK to housekeeping_assignments.id',
  `action` enum('created','updated','deleted','verified','status_changed','assigned','unassigned','priority_changed','notes_updated','recurring_created') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type of action performed',
  `old_values` json DEFAULT NULL COMMENT 'Snapshot of data before change',
  `new_values` json DEFAULT NULL COMMENT 'Snapshot of data after change',
  `changed_fields` json DEFAULT NULL COMMENT 'Array of field names that changed',
  `performed_by` int UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who performed the action',
  `performed_by_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Username for historical accuracy',
  `performed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the action was performed',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP address of the user (optional, for security)',
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Browser user agent (optional, for context)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for housekeeping assignments';

--
-- Dumping data for table `housekeeping_audit_log`
--

INSERT INTO `housekeeping_audit_log` (`id`, `assignment_id`, `action`, `old_values`, `new_values`, `changed_fields`, `performed_by`, `performed_by_name`, `performed_at`, `ip_address`, `user_agent`) VALUES
(1, 1, 'created', NULL, '{\"notes\": \"\", \"status\": \"in_progress\", \"due_date\": \"2026-02-22\", \"priority\": \"high\", \"assigned_to\": 2, \"verified_at\": null, \"completed_at\": null, \"is_recurring\": 0, \"assignment_type\": \"deep_clean\", \"recurring_pattern\": null, \"estimated_duration\": 30, \"individual_room_id\": 6, \"recurring_end_date\": null}', NULL, 1, 'admin', '2026-02-22 10:52:08', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Table structure for table `individual_rooms`
--

CREATE TABLE `individual_rooms` (
  `id` int UNSIGNED NOT NULL,
  `room_type_id` int NOT NULL COMMENT 'Links to rooms table (room type)',
  `room_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique identifier like EXEC-101, VVIP-01',
  `room_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Descriptive name like Executive Room 1',
  `floor` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Floor number or designation',
  `view_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('available','occupied','maintenance','cleaning','out_of_order') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `child_price_multiplier` decimal(5,2) DEFAULT NULL,
  `single_occupancy_enabled_override` tinyint(1) DEFAULT NULL,
  `double_occupancy_enabled_override` tinyint(1) DEFAULT NULL,
  `triple_occupancy_enabled_override` tinyint(1) DEFAULT NULL,
  `children_allowed_override` tinyint(1) DEFAULT NULL,
  `housekeeping_status` enum('pending','in_progress','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `housekeeping_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_cleaned_at` datetime DEFAULT NULL,
  `next_maintenance_date` datetime DEFAULT NULL,
  `specific_amenities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON array of room-specific amenities',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Internal notes about the room',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether room can be booked',
  `display_order` int NOT NULL DEFAULT '0' COMMENT 'Sort order within room type',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Individual rooms within each room type';

--
-- Dumping data for table `individual_rooms`
--

INSERT INTO `individual_rooms` (`id`, `room_type_id`, `room_number`, `room_name`, `floor`, `view_type`, `status`, `child_price_multiplier`, `single_occupancy_enabled_override`, `double_occupancy_enabled_override`, `triple_occupancy_enabled_override`, `children_allowed_override`, `housekeeping_status`, `housekeeping_notes`, `last_cleaned_at`, `next_maintenance_date`, `specific_amenities`, `notes`, `is_active`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 2, '1A', 'Superior Suite', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-02-11 12:06:30', '2026-02-20 15:26:00'),
(2, 2, '1B', 'Superior Suite', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 1, 2, '2026-02-11 12:06:30', '2026-02-20 15:25:57'),
(3, 2, '2A', 'Superior Suite', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 1, 3, '2026-02-11 12:06:30', '2026-02-20 15:25:53'),
(4, 2, '2B', 'Superior Suite', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 1, 4, '2026-02-11 12:06:30', '2026-02-20 15:26:03'),
(5, 2, '3A', 'Superior Suite', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 1, 5, '2026-02-11 12:06:30', '2026-02-20 15:26:07'),
(6, 2, '3B', 'Superior Suite', NULL, NULL, 'cleaning', NULL, NULL, NULL, NULL, NULL, 'in_progress', NULL, NULL, NULL, NULL, NULL, 1, 6, '2026-02-11 12:06:30', '2026-02-22 10:52:08'),
(7, 2, '4A', 'Superior Suite', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 1, 7, '2026-02-11 12:06:30', '2026-02-20 15:26:13'),
(8, 2, '4B', 'Superior Suite', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 1, 8, '2026-02-11 12:06:30', '2026-02-20 15:26:16'),
(9, 2, '5A', 'Superior Suite', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 1, 9, '2026-02-11 12:06:30', '2026-02-20 15:26:18'),
(10, 2, '5B', 'Superior Suite', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 1, 10, '2026-02-11 12:06:30', '2026-02-20 15:26:21'),
(11, 1, 'Villa', 'Villa', NULL, NULL, 'occupied', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 1, 11, '2026-02-11 12:06:30', '2026-02-21 12:15:48'),
(12, 2, '6A', 'Superior Suite', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 1, 12, '2026-02-11 12:06:30', '2026-02-20 15:26:28'),
(13, 2, '6B', 'Superior Suite', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 1, 13, '2026-02-11 12:06:30', '2026-02-20 15:26:31'),
(14, 2, '7A', 'Superior Suite', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 1, 14, '2026-02-11 12:06:30', '2026-02-20 15:26:34'),
(15, 2, '7B', 'Superior Suite', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 1, 15, '2026-02-11 12:06:30', '2026-02-20 15:26:37');

-- --------------------------------------------------------

--
-- Table structure for table `individual_room_amenities`
--

CREATE TABLE `individual_room_amenities` (
  `id` int UNSIGNED NOT NULL,
  `individual_room_id` int UNSIGNED NOT NULL,
  `amenity_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amenity_label` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amenity_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_included` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `individual_room_blocked_dates`
--

CREATE TABLE `individual_room_blocked_dates` (
  `id` int UNSIGNED NOT NULL,
  `individual_room_id` int UNSIGNED NOT NULL,
  `block_date` date NOT NULL,
  `block_type` enum('manual','maintenance','event','full') COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `reason` text COLLATE utf8mb4_unicode_ci,
  `blocked_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `individual_room_photos`
--

CREATE TABLE `individual_room_photos` (
  `id` int UNSIGNED NOT NULL,
  `individual_room_id` int UNSIGNED NOT NULL,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` int DEFAULT '0',
  `is_primary` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `individual_room_pictures_archive`
--

CREATE TABLE `individual_room_pictures_archive` (
  `id` int UNSIGNED NOT NULL,
  `individual_room_id` int UNSIGNED NOT NULL COMMENT 'FK to individual_rooms',
  `image_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `picture_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'general' COMMENT 'e.g., bedroom, bathroom, view, amenities',
  `display_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Photos for individual rooms';

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_audit_log`
--

CREATE TABLE `maintenance_audit_log` (
  `id` int UNSIGNED NOT NULL,
  `maintenance_id` int UNSIGNED NOT NULL COMMENT 'FK to room_maintenance_schedules.id',
  `action` enum('created','updated','deleted','verified','status_changed','assigned','unassigned','priority_changed','notes_updated','recurring_created','type_changed') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type of action performed',
  `old_values` json DEFAULT NULL COMMENT 'Snapshot of data before change',
  `new_values` json DEFAULT NULL COMMENT 'Snapshot of data after change',
  `changed_fields` json DEFAULT NULL COMMENT 'Array of field names that changed',
  `performed_by` int UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who performed the action',
  `performed_by_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Username for historical accuracy',
  `performed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the action was performed',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP address of the user (optional, for security)',
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Browser user agent (optional, for context)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for maintenance schedules';

--
-- Dumping data for table `maintenance_audit_log`
--

INSERT INTO `maintenance_audit_log` (`id`, `maintenance_id`, `action`, `old_values`, `new_values`, `changed_fields`, `performed_by`, `performed_by_name`, `performed_at`, `ip_address`, `user_agent`) VALUES
(1, 2, 'created', NULL, '{\"title\": \"Plumbing\", \"status\": \"pending\", \"due_date\": \"2026-02-23\", \"end_date\": \"2026-02-23T00:17\", \"priority\": \"urgent\", \"block_room\": 1, \"created_by\": 1, \"start_date\": \"2026-02-22T00:17\", \"assigned_to\": 2, \"description\": \"\", \"verified_at\": null, \"verified_by\": null, \"auto_created\": 0, \"completed_at\": null, \"is_recurring\": 0, \"maintenance_type\": \"repair\", \"linked_booking_id\": null, \"recurring_pattern\": null, \"estimated_duration\": 60, \"individual_room_id\": 11, \"recurring_end_date\": null}', NULL, 1, 'admin', '2026-02-22 01:27:37', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(2, 3, 'created', NULL, '{\"title\": \"Plumbing\", \"status\": \"pending\", \"due_date\": \"2026-02-23\", \"end_date\": \"2026-02-23T00:17\", \"priority\": \"urgent\", \"block_room\": 1, \"created_by\": 1, \"start_date\": \"2026-02-22T00:17\", \"assigned_to\": 2, \"description\": \"\", \"verified_at\": null, \"verified_by\": null, \"auto_created\": 0, \"completed_at\": null, \"is_recurring\": 0, \"maintenance_type\": \"repair\", \"linked_booking_id\": null, \"recurring_pattern\": null, \"estimated_duration\": 60, \"individual_room_id\": 11, \"recurring_end_date\": null}', NULL, 1, 'admin', '2026-02-22 01:28:54', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Table structure for table `managed_media_catalog`
--

CREATE TABLE `managed_media_catalog` (
  `id` int UNSIGNED NOT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_type` enum('image','video') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'image',
  `source_type` enum('upload','url') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'upload',
  `media_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alt_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `placement_key` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional retrieval key (legacy group replacement)',
  `page_slug` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `section_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `display_order` int NOT NULL DEFAULT '0',
  `legacy_source` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legacy_id` int UNSIGNED DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `managed_media_catalog`
--

INSERT INTO `managed_media_catalog` (`id`, `title`, `description`, `media_type`, `source_type`, `media_url`, `mime_type`, `alt_text`, `caption`, `placement_key`, `page_slug`, `section_key`, `entity_type`, `entity_id`, `is_active`, `display_order`, `legacy_source`, `legacy_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Rosalyn\'s Beach Hotel – Image 1', '', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(18).35e879243a9ccdd6669e.jpeg', NULL, 'Rosalyn\'s Beach Hotel – Image 1', '', 'index_hotel_gallery', 'index', 'hotel_gallery', NULL, NULL, 1, 1, 'hotel_gallery', 9, NULL, '2026-02-16 12:24:27', '2026-02-16 12:24:27'),
(2, 'Rosalyn\'s Beach Hotel – Image 2', '', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.36.41%20(1).9dcda0cf8d98e649c98a.jpeg', NULL, 'Rosalyn\'s Beach Hotel – Image 2', '', 'index_hotel_gallery', 'index', 'hotel_gallery', NULL, NULL, 1, 2, 'hotel_gallery', 10, NULL, '2026-02-16 12:24:27', '2026-02-16 12:24:27'),
(3, 'Welcome to Rosalyns Beach Hotel (About Us Image)', '', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(22).b14f09deaae933642113.jpeg', NULL, NULL, NULL, 'about_us.main.image', NULL, 'about', 'about_us', 1, 1, 0, 'about_us.image_url', 1, NULL, '2026-02-16 13:10:31', '2026-02-22 18:23:04'),
(4, 'Conference Room 1 (Image)', 'Large conference space for seminars, workshops, and corporate events. Can be divided for smaller groups.', 'image', 'upload', 'images/conference/conference_1771583184_4949.jpeg', NULL, 'Conference Room 1', 'Large conference space for seminars, workshops, and corporate events. Can be divided for smaller groups.', 'conference_rooms.image_path', 'conference', 'conference_rooms', 'conference_room', 1, 1, 1, 'conference_rooms.image_path', 1, NULL, '2026-02-16 13:10:31', '2026-02-20 10:26:51'),
(8, 'Superior Suite (Image)', 'Comfortable room with private bathroom', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-10%20at%2019.27.57%20(13).60ab649ff39cf3dd7a51.jpeg', NULL, 'Superior Suite', 'Comfortable room with private bathroom', 'rooms.image_url', 'rooms-gallery', 'rooms_collection', 'room', 2, 1, 2, 'rooms.image_url', 2, NULL, '2026-02-16 13:10:31', '2026-02-22 11:07:08'),
(9, 'Superior Suite (Image)', 'Comfortable room with private bathroom', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-10%20at%2019.27.57%20(13).60ab649ff39cf3dd7a51.jpeg', NULL, 'Superior Suite', 'Comfortable room with private bathroom', 'rooms.image_url', 'rooms-gallery', 'rooms_collection', 'room', 4, 1, 2, 'rooms.image_url', 4, NULL, '2026-02-16 13:10:31', '2026-02-17 14:07:33'),
(10, 'Family Room (Image)', 'Simple, affordable accommodation', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-10%20at%2019.27.57%20(4).281ec13c11228d49a15e.jpeg', NULL, 'Family Room', 'Simple, affordable accommodation', 'rooms.image_url', 'rooms-gallery', 'rooms_collection', 'room', 5, 1, 3, 'rooms.image_url', 5, NULL, '2026-02-16 13:10:31', '2026-02-17 14:07:18'),
(11, 'Fine Dining Restaurant', 'World-class cuisine', 'image', 'upload', 'images/hotel-exterior-1024x572.jpg', NULL, 'Fine Dining Restaurant', 'World-class cuisine', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 1, 2, 'gallery.image_url', 2, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(12, 'Olympic Pool', 'Heated swimming pool', 'image', 'upload', 'images/hotel-exterior-1024x572.jpg', NULL, 'Olympic Pool', 'Heated swimming pool', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 1, 3, 'gallery.image_url', 3, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(13, 'Hotel Exterior', 'Main entrance', 'image', 'upload', 'images/hotel-exterior-1024x572.jpg', NULL, 'Hotel Exterior', 'Main entrance', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 1, 4, 'gallery.image_url', 4, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(14, 'Luxury Spa', 'Wellness center', 'image', 'upload', 'images/hotel-exterior-1024x572.jpg', NULL, 'Luxury Spa', 'Wellness center', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 1, 5, 'gallery.image_url', 5, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(15, 'Sunset View', 'Evening beauty', 'image', 'upload', 'images/hotel-exterior-1024x572.jpg', NULL, 'Sunset View', 'Evening beauty', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 1, 6, 'gallery.image_url', 6, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(17, 'Hotel Exterior View', 'Stunning hotel facade during golden hour', 'image', 'upload', 'images/gallery/hotel-exterior_1-1024x572.jpg', NULL, 'Hotel Exterior View', 'Stunning hotel facade during golden hour', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 1, 8, 'gallery.image_url', 8, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(18, 'Pool Area Relaxation', 'Olympic pool with panoramic views', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(2).da074be04e999b56828a.jpeg', NULL, 'Pool Area Relaxation', 'Olympic pool with panoramic views', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 1, 9, 'gallery.image_url', 9, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(19, 'Fitness Excellence', 'State-of-the-art fitness facilities', 'image', 'upload', 'images/gallery/fitness-center-1024x683.jpg', NULL, 'Fitness Excellence', 'State-of-the-art fitness facilities', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 1, 10, 'gallery.image_url', 10, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(20, 'Presidential Suite Living Area', 'Spacious living area with premium furnishings', 'image', 'upload', 'images/gallery/hotel-lobby.jpg', NULL, 'Presidential Suite Living Area', 'Spacious living area with premium furnishings', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 1, 0, 'gallery.image_url', 11, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(21, 'Hotel Exterior View', 'Stunning hotel facade during golden hour', 'image', 'upload', 'images/gallery/hotel-exterior_1-1024x572.jpg', NULL, 'Hotel Exterior View', 'Stunning hotel facade during golden hour', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 1, 8, 'gallery.image_url', 12, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(22, 'Pool Area Relaxation', 'Olympic pool with panoramic views', 'image', 'upload', 'images/gallery/pool-area-1024x683.jpg', NULL, 'Pool Area Relaxation', 'Olympic pool with panoramic views', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 1, 9, 'gallery.image_url', 13, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(23, 'Fitness Excellence', 'State-of-the-art fitness facilities', 'image', 'upload', 'images/gallery/fitness-center-1024x683.jpg', NULL, 'Fitness Excellence', 'State-of-the-art fitness facilities', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 1, 10, 'gallery.image_url', 14, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(24, 'Presidential Suite Living Area', 'Spacious living area with premium furnishings', 'image', 'upload', 'images/gallery/hotel-lobby.jpg', NULL, 'Presidential Suite Living Area', 'Spacious living area with premium furnishings', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 1, 0, 'gallery.image_url', 15, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(25, 'Hotel Exterior View', 'Stunning hotel facade during golden hour', 'image', 'upload', 'images/gallery/hotel-exterior_1-1024x572.jpg', NULL, 'Hotel Exterior View', 'Stunning hotel facade during golden hour', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 1, 8, 'gallery.image_url', 16, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(26, 'Pool Area Relaxation', 'Olympic pool with panoramic views', 'image', 'upload', 'images/gallery/pool-area-1024x683.jpg', NULL, 'Pool Area Relaxation', 'Olympic pool with panoramic views', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 1, 9, 'gallery.image_url', 17, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(27, 'Fitness Excellence', 'State-of-the-art fitness facilities', 'image', 'upload', 'images/gallery/fitness-center-1024x683.jpg', NULL, 'Fitness Excellence', 'State-of-the-art fitness facilities', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 1, 10, 'gallery.image_url', 18, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(28, 'Executive Suite - Bedroom', 'Premium bedroom with king bed', 'image', 'upload', 'images/rooms/executive-bedroom.jpg', NULL, 'Executive Suite - Bedroom', 'Premium bedroom with king bed', 'gallery.image_url', 'room', 'room_gallery', 'room', 2, 1, 1, 'gallery.image_url', 23, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(29, 'Executive Suite - Work Area', 'Dedicated workspace with desk and business amenities', 'image', 'upload', 'images/rooms/executive-work.jpg', NULL, 'Executive Suite - Work Area', 'Dedicated workspace with desk and business amenities', 'gallery.image_url', 'room', 'room_gallery', 'room', 2, 1, 2, 'gallery.image_url', 24, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(30, 'Executive Suite - Lounge', 'Comfortable lounge area', 'image', 'upload', 'images/rooms/executive-lounge.jpg', NULL, 'Executive Suite - Lounge', 'Comfortable lounge area', 'gallery.image_url', 'room', 'room_gallery', 'room', 2, 1, 3, 'gallery.image_url', 25, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(31, 'Executive Suite - Bathroom', 'Modern bathroom with premium toiletries', 'image', 'upload', 'images/rooms/executive-bathroom.jpg', NULL, 'Executive Suite - Bathroom', 'Modern bathroom with premium toiletries', 'gallery.image_url', 'room', 'room_gallery', 'room', 2, 1, 4, 'gallery.image_url', 26, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(33, 'Executive Suite - Work Area', 'Dedicated workspace with desk and business amenities', 'image', 'url', 'https://source.unsplash.com/1200x1200/?hotel,workspace,desk', NULL, 'Executive Suite - Work Area', 'Dedicated workspace with desk and business amenities', 'gallery.image_url', 'room', 'room_gallery', 'room', 2, 1, 2, 'gallery.image_url', 36, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(34, 'Executive Suite - Lounge', 'Comfortable lounge area', 'image', 'url', 'https://source.unsplash.com/1200x1200/?hotel,lounge,sofa', NULL, 'Executive Suite - Lounge', 'Comfortable lounge area', 'gallery.image_url', 'room', 'room_gallery', 'room', 2, 1, 3, 'gallery.image_url', 37, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(35, 'Executive Suite - Bathroom', 'Modern bathroom with premium toiletries', 'image', 'url', 'https://source.unsplash.com/1200x1200/?hotel,bathroom,modern', NULL, 'Executive Suite - Bathroom', 'Modern bathroom with premium toiletries', 'gallery.image_url', 'room', 'room_gallery', 'room', 2, 1, 4, 'gallery.image_url', 38, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(42, 'Restaurant & Bar (Hero Image)', '', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(23).d8f7b3a34a1674bc3a8d.jpeg', NULL, NULL, NULL, 'page_hero.restaurant.image', 'restaurant', 'hero', 'page_hero', 1, 1, 0, 'page_heroes.hero_image_path', 1, NULL, '2026-02-16 13:10:31', '2026-02-22 18:23:04'),
(43, 'Conference & Meeting Room (Hero Image)', '', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(23).d8f7b3a34a1674bc3a8d.jpeg', NULL, NULL, NULL, 'page_hero.conference.image', 'conference', 'hero', 'page_hero', 2, 1, 0, 'page_heroes.hero_image_path', 2, NULL, '2026-02-16 13:10:31', '2026-02-22 18:23:04'),
(44, 'Events (Hero Image)', '', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(14).e3534199c47be9e4b39f.jpeg', NULL, NULL, NULL, 'page_hero.events.image', 'events', 'hero', 'page_hero', 3, 1, 0, 'page_heroes.hero_image_path', 3, NULL, '2026-02-16 13:10:31', '2026-02-22 18:23:04'),
(45, 'Our Rooms (Hero Image)', '', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(23).d8f7b3a34a1674bc3a8d.jpeg', NULL, NULL, NULL, 'page_hero.rooms-showcase.image', 'rooms-showcase', 'hero', 'page_hero', 4, 1, 0, 'page_heroes.hero_image_path', 4, NULL, '2026-02-16 13:10:31', '2026-02-22 18:23:04'),
(46, 'Our Rooms (Hero Image)', '', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(23).d8f7b3a34a1674bc3a8d.jpeg', NULL, NULL, NULL, 'page_hero.rooms-gallery.image', 'rooms-gallery', 'hero', 'page_hero', 6, 1, 0, 'page_heroes.hero_image_path', 6, NULL, '2026-02-16 13:10:31', '2026-02-22 18:23:04'),
(47, 'Fitness Center (Hero Image)', '', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(23).d8f7b3a34a1674bc3a8d.jpeg', NULL, NULL, NULL, 'page_hero.gym.image', 'gym', 'hero', 'page_hero', 7, 1, 0, 'page_heroes.hero_image_path', 7, NULL, '2026-02-16 13:10:31', '2026-02-22 18:23:04'),
(49, 'Restaurant & Bar (Hero Video)', '', 'video', 'url', 'https://media.gettyimages.com/id/2219019953/video/group-of-mature-adult-friends-quickly-booking-a-hotel-on-smartphone-while-exploring-the-temple.mp4?s=mp4-640x640-gi&k=20&c=AklHmBkwIrOueUSYHFDoVY2nN7tD4xkV3QAFYCzG4ts=', 'video/mp4', NULL, NULL, 'page_hero.restaurant.video', 'restaurant', 'hero', 'page_hero', 1, 1, 0, 'page_heroes.hero_video_path', 1, NULL, '2026-02-16 13:10:31', '2026-02-17 11:10:23'),
(50, 'Hero Video: index', 'Your unforgettable escape awaits in the heart of Malawi\'s Lake side. Discover the perfect blend of comfort, nature, and luxury at Rosalyns Beach Hotel.', 'video', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(2).da074be04e999b56828a.jpeg', NULL, 'Experience Luxury at Rosalyns Beach Hotel', 'Your unforgettable escape awaits', 'page_heroes.hero_video_path', 'index', 'hero', 'page_hero', 8, 1, 0, 'page_heroes.hero_video_path', 8, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(52, 'Rosalyn\'s Beach Hotel – Image 1', '', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(18).35e879243a9ccdd6669e.jpeg', NULL, 'Rosalyn\'s Beach Hotel – Image 1', '', 'hotel_gallery.image_url', 'index', 'hotel_gallery', 'hotel_gallery', 9, 1, 1, 'hotel_gallery.image_url', 9, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(53, 'Rosalyn\'s Beach Hotel – Image 2', '', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.36.41%20(1).9dcda0cf8d98e649c98a.jpeg', NULL, 'Rosalyn\'s Beach Hotel – Image 2', '', 'hotel_gallery.image_url', 'index', 'hotel_gallery', 'hotel_gallery', 10, 1, 2, 'hotel_gallery.image_url', 10, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(55, 'Intimate indoor seating', 'Intimate indoor seating', 'image', 'url', 'https://media.gettyimages.com/id/2076075171/photo/abstract-defocused-background-of-restaurant.jpg?s=612x612&w=0&k=20&c=_KsEUAChBiOQDEMP6bumoJPoHkD5WTFmPBh1R1oeTz8=', NULL, 'Intimate indoor seating', 'Intimate indoor seating', 'restaurant_gallery.image_path', 'restaurant', 'restaurant_gallery', 'restaurant_gallery', 2, 1, 2, 'restaurant_gallery.image_path', 2, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(56, 'Premium bar with signature cocktails', 'Premium bar with signature cocktails', 'image', 'url', 'https://media.gettyimages.com/id/1758301432/photo/luxury-cocktails-dark-mood-dark-delicious-cocktails-for-brunch-delight.jpg?s=612x612&w=0&k=20&c=UO2273jUYp1WvoWFbJklxEZDjtHKQwVDcKe8ziDqo5A=', NULL, 'Premium bar with signature cocktails', 'Premium bar with signature cocktails', 'restaurant_gallery.image_path', 'restaurant', 'restaurant_gallery', 'restaurant_gallery', 3, 1, 3, 'restaurant_gallery.image_path', 3, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(57, 'Fresh seafood platter', 'Fresh seafood platter', 'image', 'url', 'https://art.whisk.com/image/upload/fl_progressive,h_560,w_560,c_fill,dpr_2/v1650641489/v3/user-recipes/zurh6pbpesx0f3nzbil7.jpg', NULL, 'Fresh seafood platter', 'Fresh seafood platter', 'restaurant_gallery.image_path', 'restaurant', 'restaurant_gallery', 'restaurant_gallery', 4, 1, 4, 'restaurant_gallery.image_path', 4, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(58, 'Elegant dining area with panoramic views', 'Elegant dining area with panoramic views', 'image', 'url', 'https://media.gettyimages.com/id/1400584557/photo/happy-woman-toasting-with-a-glass-of-wine-during-a-dinner-celebration.jpg?s=612x612&w=0&k=20&c=FXRZHwaTK0iIj3sntl0v5GokMf57dB1jVOn9h7zkUR8=', NULL, 'Elegant dining area with panoramic views', 'Elegant dining area with panoramic views', 'restaurant_gallery.image_path', 'restaurant', 'restaurant_gallery', 'restaurant_gallery', 13, 1, 1, 'restaurant_gallery.image_path', 13, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(59, 'Fine dining experience', 'Fine dining experience', 'image', 'url', 'https://media.gettyimages.com/id/1494508942/photo/chef.jpg?s=612x612&w=0&k=20&c=bQGrV0fE-q-mynbVI1DOunZdwte9cyQ0dBf4_m8TUmQ=', NULL, 'Fine dining experience', 'Fine dining experience', 'restaurant_gallery.image_path', 'restaurant', 'restaurant_gallery', 'restaurant_gallery', 17, 1, 5, 'restaurant_gallery.image_path', 17, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(60, 'Alfresco dining terrace', 'Alfresco dining terrace', 'image', 'url', 'https://media.gettyimages.com/id/1272158224/photo/using-a-bbq-blower-to-stoke-coal-on-a-simple-barbecue-grill.jpg?s=612x612&w=0&k=20&c=BugTQ1FTnUH7nAdJc4PKNM0YJcgVF8a3Y44Zqv50kqs=', NULL, 'Alfresco dining terrace', 'Alfresco dining terrace', 'restaurant_gallery.image_path', 'restaurant', 'restaurant_gallery', 'restaurant_gallery', 18, 1, 6, 'restaurant_gallery.image_path', 18, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(62, 'Gym Hero #4', 'Our fitness center has the equipment you need to maintain your workout routine while traveling.', 'image', 'upload', 'images/gym/hero-bg.jpg', NULL, 'Fitness Center', 'Stay Active', 'gym_content.hero_image_path', 'gym', 'gym_wellness', 'gym_content', 4, 1, 0, 'gym_content.hero_image_path', 4, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(63, 'Gym Wellness #4', 'We offer basic gym equipment for cardio and strength training. Available to all hotel guests.', 'image', 'upload', 'images/gym/fitness-center.jpg', NULL, 'Exercise Facilities', 'Fitness Facilities Available', 'gym_content.wellness_image_path', 'gym', 'gym_wellness', 'gym_content', 4, 1, 1, 'gym_content.wellness_image_path', 4, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(64, 'Gym Personal Training #4', 'We offer basic gym equipment for cardio and strength training. Available to all hotel guests.', 'image', 'url', 'https://media.gettyimages.com/id/1773192171/photo/smiling-young-woman-leaning-on-barbell-at-health-club.jpg?s=1024x1024&w=gi&k=20&c=pzLyu0hPJmPgKV4TTs1sOld-TSvZ-uCt18LCsR4vsYU=', NULL, 'Personal training image', 'Fitness Facilities Available', 'gym_content.personal_training_image_path', 'gym', 'gym_training', 'gym_content', 4, 1, 2, 'gym_content.personal_training_image_path', 4, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(65, 'Welcome #1', NULL, 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(18).35e879243a9ccdd6669e.jpeg', NULL, 'Rosalyn\'s Beach Hotel', NULL, 'welcome.image_path', 'index', 'hero', 'welcome', 1, 1, 1, 'welcome.image_path', 1, NULL, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(66, 'Site Logo', 'Logo from site settings', 'image', 'url', 'https://picsart.onelink.me/VgrZ/h7qtg7vx', NULL, 'Site logo', 'Site logo', 'site_settings.site_logo', 'global', 'branding', 'site_setting', 20, 1, 0, 'site_settings.site_logo', 20, NULL, '2026-02-16 13:10:31', '2026-02-16 13:51:17'),
(67, 'Experience Luxury at Rosalyn\'s Beach Hotel (Hero Image)', '', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(23).d8f7b3a34a1674bc3a8d.jpeg', NULL, NULL, NULL, 'page_hero.index.image', 'index', 'hero', 'page_hero', 8, 1, 0, 'page_heroes.hero_image_path', 8, NULL, '2026-02-16 14:41:33', '2026-02-17 11:10:23'),
(68, 'Pool (Image)', 'Luxury pool', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(2).da074be04e999b56828a.jpeg', NULL, 'Pool', 'Luxury pool', 'index_hotel_gallery', 'index', 'hotel_gallery', 'hotel_gallery', 11, 1, 3, 'hotel_gallery.image_url', 11, NULL, '2026-02-22 11:11:37', '2026-02-22 11:11:37'),
(69, 'Outdoor (Image)', 'Outdoor chilling', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(14).e3534199c47be9e4b39f.jpeg', NULL, 'Outdoor', 'Outdoor chilling', 'index_hotel_gallery', 'index', 'hotel_gallery', 'hotel_gallery', 12, 1, 4, 'hotel_gallery.image_url', 12, NULL, '2026-02-22 11:12:23', '2026-02-22 11:12:33'),
(70, 'Coffee Bar (Image)', 'Coffee Bar', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/DSC06307%20(1).1885c56188a87f218187.jpg', NULL, 'Coffee Bar', 'Coffee Bar', 'index_hotel_gallery', 'index', 'hotel_gallery', 'hotel_gallery', 13, 1, 5, 'hotel_gallery.image_url', 13, NULL, '2026-02-22 11:13:05', '2026-02-22 11:14:32'),
(71, 'Special Occasions (Image)', 'Special Occasions', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(24).97b0f95811c598fa167d.jpeg', NULL, 'Special Occasions', 'Special Occasions', 'index_hotel_gallery', 'index', 'hotel_gallery', 'hotel_gallery', 14, 1, 6, 'hotel_gallery.image_url', 14, NULL, '2026-02-22 11:15:00', '2026-02-22 11:15:00'),
(72, 'Front View (Image)', 'Front View', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(22).b14f09deaae933642113.jpeg', NULL, 'Front View', 'Front View', 'index_hotel_gallery', 'index', 'hotel_gallery', 'hotel_gallery', 15, 1, 7, 'hotel_gallery.image_url', 15, NULL, '2026-02-22 17:54:23', '2026-02-22 17:54:23'),
(74, 'Front View (Image)', 'Front View', 'image', 'url', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(24).97b0f95811c598fa167d.jpeg', NULL, 'Front View', 'Front View', 'index_hotel_gallery', 'index', 'hotel_gallery', 'hotel_gallery', 16, 1, 7, 'hotel_gallery.image_url', 16, NULL, '2026-02-22 18:47:03', '2026-02-22 18:47:03');

-- --------------------------------------------------------

--
-- Table structure for table `managed_media_groups_archive`
--

CREATE TABLE `managed_media_groups_archive` (
  `id` int UNSIGNED NOT NULL,
  `group_key` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `page_slug` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `section_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `display_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `managed_media_groups_archive`
--

INSERT INTO `managed_media_groups_archive` (`id`, `group_key`, `group_name`, `description`, `page_slug`, `section_key`, `entity_type`, `entity_id`, `is_active`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'index_hero_media', 'Home Hero Media', 'Image/video used in home hero section', 'index', 'hero', NULL, NULL, 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(2, 'index_hotel_gallery', 'Home Hotel Gallery', 'Media used in home gallery section', 'index', 'hotel_gallery', NULL, NULL, 1, 2, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(3, 'restaurant_gallery_media', 'Restaurant Gallery', 'Media used in restaurant gallery section', 'restaurant', 'restaurant_gallery', NULL, NULL, 1, 3, '2026-02-16 10:03:13', '2026-02-16 10:03:13');

-- --------------------------------------------------------

--
-- Table structure for table `managed_media_items_archive`
--

CREATE TABLE `managed_media_items_archive` (
  `id` int UNSIGNED NOT NULL,
  `group_id` int UNSIGNED NOT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_type` enum('image','video') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'image',
  `source_type` enum('upload','url') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'upload',
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alt_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `display_order` int NOT NULL DEFAULT '0',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `managed_media_links`
--

CREATE TABLE `managed_media_links` (
  `id` int UNSIGNED NOT NULL,
  `media_catalog_id` int UNSIGNED NOT NULL,
  `source_table` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_record_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_column` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_context` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `media_type` enum('image','video') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'image',
  `placement_key` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `page_slug` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `section_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int UNSIGNED DEFAULT NULL,
  `use_case` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `managed_media_links`
--

INSERT INTO `managed_media_links` (`id`, `media_catalog_id`, `source_table`, `source_record_id`, `source_column`, `source_context`, `media_type`, `placement_key`, `page_slug`, `section_key`, `entity_type`, `entity_id`, `use_case`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 3, 'about_us', '1', 'image_url', '', 'image', 'about_us.image_url', 'index', 'about', 'about_us', 1, 'section_main', 1, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(2, 4, 'conference_rooms', '1', 'image_path', '', 'image', 'conference_rooms.image_path', 'conference', 'conference_rooms', 'conference_room', 1, 'card_image', 1, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(6, 8, 'rooms', '2', 'image_url', '', 'image', 'rooms.image_url', 'rooms-gallery', 'rooms_collection', 'room', 2, 'featured_image', 2, 1, '2026-02-16 13:10:31', '2026-02-22 11:07:08'),
(7, 9, 'rooms', '4', 'image_url', '', 'image', 'rooms.image_url', 'rooms-gallery', 'rooms_collection', 'room', 4, 'featured_image', 2, 1, '2026-02-16 13:10:31', '2026-02-17 14:07:33'),
(8, 10, 'rooms', '5', 'image_url', '', 'image', 'rooms.image_url', 'rooms-gallery', 'rooms_collection', 'room', 5, 'featured_image', 3, 1, '2026-02-16 13:10:31', '2026-02-17 14:07:18'),
(9, 11, 'gallery', '2', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 'room_gallery_image', 2, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(10, 12, 'gallery', '3', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 'room_gallery_image', 3, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(11, 13, 'gallery', '4', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 'room_gallery_image', 4, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(12, 14, 'gallery', '5', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 'room_gallery_image', 5, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(13, 15, 'gallery', '6', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 'room_gallery_image', 6, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(15, 17, 'gallery', '8', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 'room_gallery_image', 8, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(16, 18, 'gallery', '9', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 'room_gallery_image', 9, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(17, 19, 'gallery', '10', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 'room_gallery_image', 10, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(18, 20, 'gallery', '11', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 'room_gallery_image', 0, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(19, 21, 'gallery', '12', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 'room_gallery_image', 8, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(20, 22, 'gallery', '13', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 'room_gallery_image', 9, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(21, 23, 'gallery', '14', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 'room_gallery_image', 10, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(22, 24, 'gallery', '15', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 'room_gallery_image', 0, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(23, 25, 'gallery', '16', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 'room_gallery_image', 8, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(24, 26, 'gallery', '17', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 'room_gallery_image', 9, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(25, 27, 'gallery', '18', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', NULL, 'room_gallery_image', 10, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(26, 28, 'gallery', '23', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', 2, 'room_gallery_image', 1, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(27, 29, 'gallery', '24', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', 2, 'room_gallery_image', 2, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(28, 30, 'gallery', '25', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', 2, 'room_gallery_image', 3, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(29, 31, 'gallery', '26', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', 2, 'room_gallery_image', 4, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(31, 33, 'gallery', '36', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', 2, 'room_gallery_image', 2, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(32, 34, 'gallery', '37', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', 2, 'room_gallery_image', 3, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(33, 35, 'gallery', '38', 'image_url', '', 'image', 'gallery.image_url', 'room', 'room_gallery', 'room', 2, 'room_gallery_image', 4, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(40, 42, 'page_heroes', '1', 'hero_image_path', '', 'image', 'page_heroes.hero_image_path', 'restaurant', 'hero', 'page_hero', 1, 'hero_image', 1, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(41, 43, 'page_heroes', '2', 'hero_image_path', '', 'image', 'page_heroes.hero_image_path', 'conference', 'hero', 'page_hero', 2, 'hero_image', 2, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(42, 44, 'page_heroes', '3', 'hero_image_path', '', 'image', 'page_heroes.hero_image_path', 'events', 'hero', 'page_hero', 3, 'hero_image', 3, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(43, 45, 'page_heroes', '4', 'hero_image_path', '', 'image', 'page_heroes.hero_image_path', 'rooms-showcase', 'hero', 'page_hero', 4, 'hero_image', 4, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(44, 46, 'page_heroes', '6', 'hero_image_path', '', 'image', 'page_heroes.hero_image_path', 'rooms-gallery', 'hero', 'page_hero', 6, 'hero_image', 5, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(45, 47, 'page_heroes', '7', 'hero_image_path', '', 'image', 'page_heroes.hero_image_path', 'gym', 'hero', 'page_hero', 7, 'hero_image', 6, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(47, 49, 'page_heroes', '1', 'hero_video_path', '', 'video', 'page_heroes.hero_video_path', 'restaurant', 'hero', 'page_hero', 1, 'hero_video', 1, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(48, 50, 'page_heroes', '8', 'hero_video_path', '', 'video', 'page_heroes.hero_video_path', 'index', 'hero', 'page_hero', 8, 'hero_video', 0, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(50, 52, 'hotel_gallery', '9', 'image_url', '', 'image', 'index_hotel_gallery', 'index', 'hotel_gallery', 'hotel_gallery', 9, 'gallery_image', 1, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(51, 53, 'hotel_gallery', '10', 'image_url', '', 'image', 'index_hotel_gallery', 'index', 'hotel_gallery', 'hotel_gallery', 10, 'gallery_image', 2, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(53, 55, 'restaurant_gallery', '2', 'image_path', '', 'image', 'restaurant_gallery_media', 'restaurant', 'restaurant_gallery', 'restaurant_gallery', 2, 'gallery_image', 2, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(54, 56, 'restaurant_gallery', '3', 'image_path', '', 'image', 'restaurant_gallery_media', 'restaurant', 'restaurant_gallery', 'restaurant_gallery', 3, 'gallery_image', 3, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(55, 57, 'restaurant_gallery', '4', 'image_path', '', 'image', 'restaurant_gallery_media', 'restaurant', 'restaurant_gallery', 'restaurant_gallery', 4, 'gallery_image', 4, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(56, 58, 'restaurant_gallery', '13', 'image_path', '', 'image', 'restaurant_gallery_media', 'restaurant', 'restaurant_gallery', 'restaurant_gallery', 13, 'gallery_image', 1, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(57, 59, 'restaurant_gallery', '17', 'image_path', '', 'image', 'restaurant_gallery_media', 'restaurant', 'restaurant_gallery', 'restaurant_gallery', 17, 'gallery_image', 5, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(58, 60, 'restaurant_gallery', '18', 'image_path', '', 'image', 'restaurant_gallery_media', 'restaurant', 'restaurant_gallery', 'restaurant_gallery', 18, 'gallery_image', 6, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(60, 62, 'gym_content', '4', 'hero_image_path', '', 'image', 'gym_content.hero_image_path', 'gym', 'gym_wellness', 'gym_content', 4, 'hero_image', 0, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(61, 63, 'gym_content', '4', 'wellness_image_path', '', 'image', 'gym_content.wellness_image_path', 'gym', 'gym_wellness', 'gym_content', 4, 'wellness_image', 1, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(62, 64, 'gym_content', '4', 'personal_training_image_path', '', 'image', 'gym_content.personal_training_image_path', 'gym', 'gym_training', 'gym_content', 4, 'training_image', 2, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(63, 65, 'welcome', '1', 'image_path', '', 'image', 'welcome.image_path', 'index', 'hero', 'welcome', 1, 'section_image', 1, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(64, 66, 'site_settings', '20', 'setting_value', 'site_logo', 'image', 'site_settings.site_logo', 'global', 'branding', 'site_setting', 20, 'site_logo', 0, 1, '2026-02-16 13:10:31', '2026-02-16 13:10:31'),
(65, 67, 'page_heroes', '8', 'hero_image_path', '', 'image', NULL, 'index', 'hero', NULL, NULL, NULL, 0, 1, '2026-02-16 14:46:57', '2026-02-16 14:46:57'),
(66, 67, 'page_heroes', '8', 'hero_image_path', 'page_hero_media', 'image', 'page_hero.index.image', 'index', 'hero', 'page_hero', 8, NULL, 0, 1, '2026-02-17 11:10:23', '2026-02-17 11:10:23'),
(67, 42, 'page_heroes', '1', 'hero_image_path', 'page_hero_media', 'image', 'page_hero.restaurant.image', 'restaurant', 'hero', 'page_hero', 1, NULL, 0, 1, '2026-02-17 11:10:23', '2026-02-17 11:10:23'),
(68, 49, 'page_heroes', '1', 'hero_video_path', 'page_hero_media', 'video', 'page_hero.restaurant.video', 'restaurant', 'hero', 'page_hero', 1, NULL, 0, 1, '2026-02-17 11:10:23', '2026-02-17 11:10:23'),
(69, 43, 'page_heroes', '2', 'hero_image_path', 'page_hero_media', 'image', 'page_hero.conference.image', 'conference', 'hero', 'page_hero', 2, NULL, 0, 1, '2026-02-17 11:10:23', '2026-02-17 11:10:23'),
(70, 44, 'page_heroes', '3', 'hero_image_path', 'page_hero_media', 'image', 'page_hero.events.image', 'events', 'hero', 'page_hero', 3, NULL, 0, 1, '2026-02-17 11:10:23', '2026-02-17 11:10:23'),
(71, 45, 'page_heroes', '4', 'hero_image_path', 'page_hero_media', 'image', 'page_hero.rooms-showcase.image', 'rooms-showcase', 'hero', 'page_hero', 4, NULL, 0, 1, '2026-02-17 11:10:23', '2026-02-17 11:10:23'),
(72, 46, 'page_heroes', '6', 'hero_image_path', 'page_hero_media', 'image', 'page_hero.rooms-gallery.image', 'rooms-gallery', 'hero', 'page_hero', 6, NULL, 0, 1, '2026-02-17 11:10:24', '2026-02-17 11:10:24'),
(73, 47, 'page_heroes', '7', 'hero_image_path', 'page_hero_media', 'image', 'page_hero.gym.image', 'gym', 'hero', 'page_hero', 7, NULL, 0, 1, '2026-02-17 11:10:24', '2026-02-17 11:10:24'),
(74, 68, 'hotel_gallery', '11', 'image_url', '', 'image', 'index_hotel_gallery', 'index', 'hotel_gallery', 'hotel_gallery', 11, 'gallery_image', 3, 1, '2026-02-22 11:11:37', '2026-02-22 11:11:37'),
(75, 69, 'hotel_gallery', '12', 'image_url', '', 'image', 'index_hotel_gallery', 'index', 'hotel_gallery', 'hotel_gallery', 12, 'gallery_image', 4, 1, '2026-02-22 11:12:23', '2026-02-22 11:12:33'),
(76, 70, 'hotel_gallery', '13', 'image_url', '', 'image', 'index_hotel_gallery', 'index', 'hotel_gallery', 'hotel_gallery', 13, 'gallery_image', 5, 1, '2026-02-22 11:13:05', '2026-02-22 11:14:32'),
(77, 71, 'hotel_gallery', '14', 'image_url', '', 'image', 'index_hotel_gallery', 'index', 'hotel_gallery', 'hotel_gallery', 14, 'gallery_image', 6, 1, '2026-02-22 11:15:00', '2026-02-22 11:15:00'),
(78, 72, 'hotel_gallery', '15', 'image_url', '', 'image', 'index_hotel_gallery', 'index', 'hotel_gallery', 'hotel_gallery', 15, 'gallery_image', 7, 0, '2026-02-22 17:54:23', '2026-02-22 18:10:06'),
(80, 3, 'about_us', '1', 'image_url', 'about_us_media', 'image', 'about_us.main.image', NULL, 'about', 'about_us', 1, NULL, 0, 1, '2026-02-22 18:23:04', '2026-02-22 18:23:04'),
(81, 74, 'hotel_gallery', '16', 'image_url', '', 'image', 'index_hotel_gallery', 'index', 'hotel_gallery', 'hotel_gallery', 16, 'gallery_image', 7, 0, '2026-02-22 18:47:03', '2026-02-22 18:47:13');

-- --------------------------------------------------------

--
-- Table structure for table `menu_categories`
--

CREATE TABLE `menu_categories` (
  `id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `display_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_categories`
--

INSERT INTO `menu_categories` (`id`, `name`, `slug`, `description`, `display_order`, `is_active`) VALUES
(6, 'Breakfast', 'breakfast', 'Start your day with our delicious breakfast options', 1, 1),
(7, 'Starter', 'starter', 'Appetizing starters to begin your meal', 2, 1),
(8, 'Chicken Corner', 'chicken-corner', 'Delicious chicken dishes prepared to perfection', 3, 1),
(9, 'Meat Corner', 'meat-corner', 'Premium meat dishes for carnivores', 4, 1),
(10, 'Fish Corner', 'fish-corner', 'Fresh fish and seafood from Lake Malawi', 5, 1),
(11, 'Pasta Corner', 'pasta-corner', 'Italian pasta classics and favorites', 6, 1),
(12, 'Burger Corner', 'burger-corner', 'Juicy burgers made with premium Malawian beef', 7, 1),
(13, 'Pizza Corner', 'pizza-corner', 'Authentic pizzas with various toppings', 8, 1),
(14, 'Snack Corner', 'snack-corner', 'Quick bites and light snacks', 9, 1),
(15, 'Indian Corner', 'indian-corner', 'Authentic Indian cuisine with aromatic spices', 10, 1),
(16, 'Liwonde Sun Specialities', 'liwonde-sun-specialities', 'Our signature special dishes', 11, 1),
(17, 'Extras', 'extras', 'Additional sides and extras', 12, 1),
(18, 'Desserts', 'desserts', 'Sweet treats to end your meal', 13, 1);

-- --------------------------------------------------------

--
-- Table structure for table `migration_log`
--

CREATE TABLE `migration_log` (
  `migration_id` int UNSIGNED NOT NULL,
  `migration_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique name of the migration',
  `migration_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the migration was run',
  `status` enum('pending','in_progress','completed','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'Migration status',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log of database migrations';

--
-- Dumping data for table `migration_log`
--

INSERT INTO `migration_log` (`migration_id`, `migration_name`, `migration_date`, `status`, `created_at`) VALUES
(1, 'payments_accounting_system', '2026-01-30 00:12:22', 'completed', '2026-01-30 00:12:22'),
(7, 'occupancy_pricing_system', '2026-02-05 12:48:15', 'completed', '2026-02-05 12:48:15'),
(9, 'add_individual_rooms', '2026-02-11 20:54:08', 'completed', '2026-02-11 20:54:08'),
(10, 'admin_media_and_permissions_upgrade', '2026-02-16 10:03:13', 'completed', '2026-02-16 10:03:13'),
(11, 'unified_media_catalog_portal', '2026-02-16 12:24:27', 'completed', '2026-02-16 12:24:27'),
(12, 'media_links_full_normalization', '2026-02-16 13:10:31', 'completed', '2026-02-16 13:10:31'),
(13, 'page_heroes_home_migration', '2026-02-16 14:46:57', 'completed', '2026-02-16 14:46:57'),
(14, '014_canonical_media_and_room_image_consolidation', '2026-02-16 20:04:38', 'completed', '2026-02-16 20:04:38');

-- --------------------------------------------------------

--
-- Table structure for table `newsletter_subscribers`
--

CREATE TABLE `newsletter_subscribers` (
  `id` int NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subscription_status` enum('active','unsubscribed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `subscribed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `unsubscribed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `page_heroes`
--

CREATE TABLE `page_heroes` (
  `id` int NOT NULL,
  `page_slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique page identifier e.g., restaurant, conference',
  `page_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL path e.g., /restaurant.php',
  `hero_title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hero_subtitle` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hero_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `primary_cta_text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_cta_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hero_image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hero_video_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path to video file or URL (e.g., Getty Images, YouTube, Vimeo)',
  `hero_video_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Video MIME type (video/mp4, video/webm, etc.) or platform (youtube, vimeo, getty)',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `display_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `page_heroes`
--

INSERT INTO `page_heroes` (`id`, `page_slug`, `page_url`, `hero_title`, `hero_subtitle`, `hero_description`, `primary_cta_text`, `primary_cta_link`, `hero_image_path`, `hero_video_path`, `hero_video_type`, `is_active`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'restaurant', '/restaurant.php', 'Restaurant & Bar', 'Good Food & Drinks', 'Enjoy tasty meals and refreshing drinks at our restaurant. We serve local and international dishes at reasonable prices.', NULL, NULL, 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(23).d8f7b3a34a1674bc3a8d.jpeg', 'https://media.gettyimages.com/id/2219019953/video/group-of-mature-adult-friends-quickly-booking-a-hotel-on-smartphone-while-exploring-the-temple.mp4?s=mp4-640x640-gi&k=20&c=AklHmBkwIrOueUSYHFDoVY2nN7tD4xkV3QAFYCzG4ts=', 'video/mp4', 1, 1, '2026-01-25 18:03:42', '2026-02-22 16:02:45'),
(2, 'conference', '/conference.php', 'Conference & Meeting Room', 'Event Space Available', 'We have meeting rooms available for conferences, workshops, and events. Basic amenities included.', NULL, NULL, 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(23).d8f7b3a34a1674bc3a8d.jpeg', NULL, NULL, 1, 2, '2026-01-25 18:03:42', '2026-02-22 16:02:42'),
(3, 'events', '/events.php', 'Events', 'What\'s Happening', 'Join us for special events and activities at the hotel.', NULL, NULL, 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(14).e3534199c47be9e4b39f.jpeg', NULL, NULL, 1, 3, '2026-01-25 18:03:42', '2026-02-22 16:03:50'),
(4, 'rooms-showcase', '/rooms-showcase.php', 'Our Rooms', 'Comfortable Accommodation', 'Choose from our range of clean, comfortable rooms at affordable prices.', NULL, NULL, 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(23).d8f7b3a34a1674bc3a8d.jpeg', NULL, NULL, 1, 4, '2026-01-25 19:08:32', '2026-02-22 16:02:37'),
(6, 'rooms-gallery', '/rooms-gallery.php', 'Our Rooms', 'Comfortable Accommodation', 'Choose from our range of clean, comfortable rooms at affordable prices.', NULL, NULL, 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(23).d8f7b3a34a1674bc3a8d.jpeg', NULL, NULL, 1, 5, '2026-01-25 19:08:32', '2026-02-22 16:02:35'),
(7, 'gym', '/gym.php', 'Fitness Center', 'Stay Active', 'Our gym has basic equipment for your workout needs.', NULL, NULL, 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(23).d8f7b3a34a1674bc3a8d.jpeg', NULL, NULL, 1, 6, '2026-01-25 19:08:32', '2026-02-22 16:02:32'),
(8, 'index', '/index.php', 'Experience Luxury at Rosalyn\'s Beach Hotel', 'Your unforgettable escape awaits', 'Your unforgettable escape awaits in the heart of Malawi\'s Lake side. Discover the perfect blend of comfort, nature, and luxury at Rosalyns Beach Hotel.', 'Explore Our Rooms', 'rooms-showcase.php', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(23).d8f7b3a34a1674bc3a8d.jpeg', NULL, NULL, 1, 0, '2026-02-11 18:54:36', '2026-02-17 10:52:55'),
(9, 'contact-us', '', 'Contact Us', 'We Would Love to Hear From You', 'Reach out for reservations, inquiries, or any questions about our hotel and services.', 'Call Us Now', '#contact-info', NULL, NULL, NULL, 1, 0, '2026-02-23 13:05:25', '2026-02-23 13:05:25');

-- --------------------------------------------------------

--
-- Table structure for table `page_loaders`
--

CREATE TABLE `page_loaders` (
  `id` int NOT NULL,
  `page_slug` varchar(255) NOT NULL,
  `subtext` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `page_loaders`
--

INSERT INTO `page_loaders` (`id`, `page_slug`, `subtext`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'index', 'Loading Excellence...', 1, '2026-01-26 08:37:51', '2026-01-26 08:37:51'),
(2, 'restaurant', 'Preparing Culinary Delights...', 1, '2026-01-26 08:37:51', '2026-01-26 08:37:51'),
(3, 'gym', 'Getting Fit...', 1, '2026-01-26 08:37:51', '2026-01-26 08:37:51'),
(4, 'conference', 'Setting Up Your Event...', 1, '2026-01-26 08:37:51', '2026-01-26 08:37:51'),
(5, 'events', 'Loading Exciting Events...', 1, '2026-01-26 08:37:51', '2026-01-26 08:37:51'),
(6, 'room', 'Finding Your Perfect Room...', 1, '2026-01-26 08:37:51', '2026-01-26 08:37:51'),
(7, 'booking', 'Processing Your Reservation...', 1, '2026-01-26 08:37:51', '2026-01-26 08:37:51'),
(8, 'rooms-gallery', 'Finding Your Perfect Room...', 1, '2026-01-26 08:37:51', '2026-01-26 08:37:51');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 1, 'c0e470c230362e27ee9d32dc6ef45c9c57237d8c6d6feafcf7fe77c111b73147', '2026-02-07 14:24:54', NULL, '2026-02-07 13:24:52');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int UNSIGNED NOT NULL,
  `payment_reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique payment reference like PAY-2026-000001',
  `booking_type` enum('room','conference') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type of booking',
  `booking_id` int UNSIGNED NOT NULL COMMENT 'ID from bookings or conference_inquiries table',
  `conference_id` int UNSIGNED DEFAULT NULL COMMENT 'Optional link to conference_inquiries table for conference-specific payments',
  `booking_reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Reference from booking (LSH2026xxxx or CONF-2026-xxxx)',
  `payment_date` date NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL COMMENT 'Amount paid before VAT',
  `vat_rate` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'VAT percentage (e.g., 16.50 for 16.5%)',
  `vat_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Calculated VAT amount',
  `total_amount` decimal(10,2) NOT NULL COMMENT 'Total including VAT',
  `payment_method` enum('cash','bank_transfer','mobile_money','credit_card','debit_card','cheque','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `payment_type` enum('deposit','full_payment','partial_payment','refund','adjustment') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Type of payment transaction',
  `payment_reference_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Transaction ID, receipt number, or cheque number',
  `payment_status` enum('pending','partial','paid','completed','refunded','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `invoice_generated` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether invoice has been generated',
  `invoice_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Invoice number (e.g., INV-2026-000001)',
  `amount` decimal(10,2) DEFAULT '0.00' COMMENT 'Additional payment amount field - coexists with payment_amount',
  `status` enum('pending','completed','failed','refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'Additional payment status field - coexists with payment_status',
  `transaction_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Additional transaction reference field - coexists with payment_reference_number',
  `invoice_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path to generated invoice file',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Additional payment notes',
  `recorded_by` int UNSIGNED DEFAULT NULL COMMENT 'Admin user who recorded the payment',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cc_emails` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Additional CC email addresses for payment receipt',
  `receipt_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Sequential receipt number for payments',
  `processed_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Admin user who processed the payment',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
  `original_payment_id` int UNSIGNED DEFAULT NULL COMMENT 'Reference to original payment being refunded',
  `refund_reason` enum('early_checkout','late_checkout_charge','cancellation','service_issue','overpayment','other') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Reason for refund or adjustment',
  `refund_status` enum('pending','processing','completed','failed') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Status of refund processing',
  `refund_amount` decimal(10,2) DEFAULT '0.00' COMMENT 'Amount being refunded (for refund type payments)',
  `refund_date_processed` timestamp NULL DEFAULT NULL COMMENT 'When refund was processed',
  `refund_notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Additional notes about refund'
) ;

-- --------------------------------------------------------

--
-- Table structure for table `policies`
--

CREATE TABLE `policies` (
  `id` int NOT NULL,
  `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `policies`
--

INSERT INTO `policies` (`id`, `slug`, `title`, `summary`, `content`, `display_order`, `is_active`, `updated_at`) VALUES
(1, 'booking-policy', 'Booking Policy', 'Simple booking terms', 'Bookings can be made by phone or email. A deposit may be required to confirm your reservation. Please contact us for changes to your booking.', 1, 1, '2026-02-07 02:40:53'),
(2, 'cancellation-policy', 'Cancellation Policy', 'Fair cancellation terms', 'Cancellations made at least 48 hours before arrival will receive a full refund. Cancellations within 48 hours may be charged one night.', 2, 1, '2026-02-07 02:40:53'),
(3, 'dining-policy', 'Dining Policy', 'Elegant dining etiquette', 'Smart casual dress code applies after 6pm. Outside food and beverages are not permitted in dining venues. Allergy and dietary requests are accommodated with advance notice.', 3, 1, '2026-01-20 10:54:23'),
(4, 'faqs', 'FAQs', 'Quick answers to common questions', 'Check-in: 14:00, Check-out: 11:00. Airport transfers can be arranged. Children are welcome; extra beds available on request. High-speed WiFi is complimentary throughout the property.', 4, 1, '2026-01-20 10:54:23');

-- --------------------------------------------------------

--
-- Table structure for table `restaurant_gallery`
--

CREATE TABLE `restaurant_gallery` (
  `id` int NOT NULL,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'restaurant' COMMENT 'restaurant, bar, dining-area, food',
  `display_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `restaurant_gallery`
--

INSERT INTO `restaurant_gallery` (`id`, `image_path`, `caption`, `category`, `display_order`, `is_active`, `created_at`) VALUES
(2, 'https://media.gettyimages.com/id/2076075171/photo/abstract-defocused-background-of-restaurant.jpg?s=612x612&w=0&k=20&c=_KsEUAChBiOQDEMP6bumoJPoHkD5WTFmPBh1R1oeTz8=', 'Intimate indoor seating', 'dining-area', 2, 1, '2026-01-20 14:17:17'),
(3, 'https://media.gettyimages.com/id/1758301432/photo/luxury-cocktails-dark-mood-dark-delicious-cocktails-for-brunch-delight.jpg?s=612x612&w=0&k=20&c=UO2273jUYp1WvoWFbJklxEZDjtHKQwVDcKe8ziDqo5A=', 'Premium bar with signature cocktails', 'bar', 3, 1, '2026-01-20 14:17:17'),
(4, 'https://art.whisk.com/image/upload/fl_progressive,h_560,w_560,c_fill,dpr_2/v1650641489/v3/user-recipes/zurh6pbpesx0f3nzbil7.jpg', 'Fresh seafood platter', 'food', 4, 1, '2026-01-20 14:17:17'),
(13, 'https://media.gettyimages.com/id/1400584557/photo/happy-woman-toasting-with-a-glass-of-wine-during-a-dinner-celebration.jpg?s=612x612&w=0&k=20&c=FXRZHwaTK0iIj3sntl0v5GokMf57dB1jVOn9h7zkUR8=', 'Elegant dining area with panoramic views', 'dining-area', 1, 1, '2026-01-20 15:22:41'),
(17, 'https://media.gettyimages.com/id/1494508942/photo/chef.jpg?s=612x612&w=0&k=20&c=bQGrV0fE-q-mynbVI1DOunZdwte9cyQ0dBf4_m8TUmQ=', 'Fine dining experience', 'restaurant', 5, 1, '2026-01-20 15:22:41'),
(18, 'https://media.gettyimages.com/id/1272158224/photo/using-a-bbq-blower-to-stoke-coal-on-a-simple-barbecue-grill.jpg?s=612x612&w=0&k=20&c=BugTQ1FTnUH7nAdJc4PKNM0YJcgVF8a3Y44Zqv50kqs=', 'Alfresco dining terrace', 'dining-area', 6, 1, '2026-01-20 15:22:41');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int NOT NULL,
  `booking_id` int UNSIGNED DEFAULT NULL COMMENT 'Link to bookings table if guest stayed',
  `room_id` int DEFAULT NULL COMMENT 'Link to rooms table',
  `review_type` enum('general','room','restaurant','spa','conference','gym','service') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'general',
  `guest_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of reviewer',
  `guest_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Email of reviewer',
  `rating` int NOT NULL COMMENT 'Overall rating from 1 to 5 stars',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Review title',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Review content',
  `service_rating` int DEFAULT NULL COMMENT 'Service rating from 1 to 5',
  `cleanliness_rating` int DEFAULT NULL COMMENT 'Cleanliness rating from 1 to 5',
  `location_rating` int DEFAULT NULL COMMENT 'Location rating from 1 to 5',
  `value_rating` int DEFAULT NULL COMMENT 'Value rating from 1 to 5',
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'Moderation status',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Review submission date',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update date'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Guest reviews with detailed ratings';

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `booking_id`, `room_id`, `review_type`, `guest_name`, `guest_email`, `rating`, `title`, `comment`, `service_rating`, `cleanliness_rating`, `location_rating`, `value_rating`, `status`, `created_at`, `updated_at`) VALUES
(1, NULL, NULL, 'general', 'Test User', 'test@example.com', 5, 'Excellent Stay', 'This was a wonderful experience at the hotel. The service was outstanding and the room was very clean.', NULL, NULL, NULL, NULL, 'approved', '2026-01-27 14:47:18', '2026-01-27 22:45:26'),
(2, NULL, NULL, 'general', 'John Doe', 'john@example.com', 4, 'Great Room', 'The room was spacious and comfortable. Staff was very helpful.', 5, 5, 4, 4, 'approved', '2026-01-27 14:48:28', '2026-01-27 15:29:21'),
(6, NULL, NULL, 'room', 'JOHN-PAUL CHIRWA', 'johnpaulchirwa@gmail.com', 4, 'tessssssssssssssssssssssssss', 'tessssssssssssssssssssssssss', 1, 3, 3, 2, 'approved', '2026-02-04 02:20:53', '2026-02-04 02:22:16');

-- --------------------------------------------------------

--
-- Table structure for table `review_responses`
--

CREATE TABLE `review_responses` (
  `id` int NOT NULL,
  `review_id` int NOT NULL COMMENT 'Foreign key to reviews table',
  `admin_id` int UNSIGNED DEFAULT NULL COMMENT 'Link to admin_users table',
  `response` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Admin response content',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Response date'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin responses to guest reviews';

--
-- Dumping data for table `review_responses`
--

INSERT INTO `review_responses` (`id`, `review_id`, `admin_id`, `response`, `created_at`) VALUES
(2, 6, 2, 'Okay thank yo very much', '2026-02-04 02:21:37'),
(3, 2, 1, 'Thank you very much', '2026-02-24 18:13:55');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_per_night` decimal(10,2) NOT NULL,
  `size_sqm` int DEFAULT NULL,
  `max_guests` int DEFAULT '2',
  `single_occupancy_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `double_occupancy_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `triple_occupancy_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `children_allowed` tinyint(1) NOT NULL DEFAULT '1',
  `rooms_available` int DEFAULT '5' COMMENT 'Number of rooms currently available for booking',
  `total_rooms` int DEFAULT '5' COMMENT 'Total number of rooms of this type',
  `bed_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `badge` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amenities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_featured` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `video_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path to room video file',
  `video_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Video MIME type',
  `price_single_occupancy` decimal(10,2) DEFAULT NULL COMMENT 'Price for single occupancy (1 guest)',
  `price_double_occupancy` decimal(10,2) DEFAULT NULL COMMENT 'Price for double occupancy (2 guests)',
  `price_triple_occupancy` decimal(10,2) DEFAULT NULL COMMENT 'Price for triple occupancy (3 guests)',
  `child_price_multiplier` decimal(5,2) NOT NULL DEFAULT '50.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `name`, `slug`, `description`, `short_description`, `price_per_night`, `size_sqm`, `max_guests`, `single_occupancy_enabled`, `double_occupancy_enabled`, `triple_occupancy_enabled`, `children_allowed`, `rooms_available`, `total_rooms`, `bed_type`, `image_url`, `badge`, `amenities`, `is_featured`, `is_active`, `display_order`, `created_at`, `updated_at`, `video_path`, `video_type`, `price_single_occupancy`, `price_double_occupancy`, `price_triple_occupancy`, `child_price_multiplier`) VALUES
(1, 'VIP Beach Front Villa', 'executive-villa', 'Experience luxury beachfront living in our spacious two-room villa. Perfect for groups of up to 4, featuring a private lounge and fully-equipped kitchen. Enjoy breathtaking lake views and direct beach access.\r\n\r\n', 'Spacious room with work area', 460000.00, 60, 5, 1, 1, 1, 2, 0, 1, 'King Bed', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-06-30%20at%2018.38.03.f4a12059d4216a3061ab.jpeg', NULL, 'King Bed,Work Desk,Butler Service,Living Area,Smart TV,High-Speed WiFi,Coffee Machine,Mini Bar,Safe', 1, 1, 1, '2026-01-19 20:22:49', '2026-02-21 12:15:48', NULL, NULL, 460000.00, 460000.00, 460000.00, 0.00),
(2, 'Superior Suite', 'deluxe-room', 'Indulge in our luxurious Executive Suite, offering panoramic views and exclusive amenities. Perfect for couples or business travelers, this suite includes a sumptuous bed and breakfast for two, ensuring a memorable stay.', 'Comfortable room with private bathroom', 250000.00, 45, 2, 1, 1, 0, 0, 16, 17, 'King Bed', 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-10%20at%2019.27.57%20(13).60ab649ff39cf3dd7a51.jpeg', 'Popular', 'King Bed,Jacuzzi Tub,Living Area,Marble Bathroom,Premium Bedding,Smart TV,Mini Bar,Free WiFi', 1, 1, 2, '2026-01-19 20:22:49', '2026-02-21 16:14:01', NULL, NULL, 250000.00, 250000.00, NULL, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `room_blocked_dates`
--

CREATE TABLE `room_blocked_dates` (
  `id` int NOT NULL,
  `room_id` int DEFAULT NULL COMMENT 'Room ID (NULL means block all rooms)',
  `block_date` date NOT NULL COMMENT 'Date to block from bookings',
  `block_type` enum('maintenance','event','manual','full') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual' COMMENT 'Reason for blocking the date',
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional explanation for blocking',
  `created_by` int UNSIGNED DEFAULT NULL COMMENT 'Admin user who created this block',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the block was created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Manually blocked dates for rooms - prevents bookings on specified dates';

-- --------------------------------------------------------

--
-- Table structure for table `room_maintenance_blocks`
--

CREATE TABLE `room_maintenance_blocks` (
  `id` int UNSIGNED NOT NULL,
  `individual_room_id` int UNSIGNED NOT NULL COMMENT 'FK to individual_rooms',
  `block_start_date` date NOT NULL,
  `block_end_date` date NOT NULL,
  `block_type` enum('maintenance','renovation','cleaning','inspection','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'maintenance',
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Maintenance/renovation blocks for individual rooms';

-- --------------------------------------------------------

--
-- Table structure for table `room_maintenance_log`
--

CREATE TABLE `room_maintenance_log` (
  `id` int UNSIGNED NOT NULL,
  `individual_room_id` int UNSIGNED NOT NULL,
  `status_from` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_to` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `performed_by` int UNSIGNED DEFAULT NULL COMMENT 'Admin user who made the change',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log of room status changes for housekeeping';

--
-- Dumping data for table `room_maintenance_log`
--

INSERT INTO `room_maintenance_log` (`id`, `individual_room_id`, `status_from`, `status_to`, `reason`, `performed_by`, `created_at`) VALUES
(1, 6, 'available', 'cleaning', 'Housekeeping assignment active', 1, '2026-02-22 10:52:08');

-- --------------------------------------------------------

--
-- Table structure for table `room_maintenance_schedules`
--

CREATE TABLE `room_maintenance_schedules` (
  `id` int UNSIGNED NOT NULL,
  `individual_room_id` int UNSIGNED NOT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('planned','in_progress','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'planned',
  `priority` enum('low','medium','high','urgent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `maintenance_type` enum('repair','replacement','inspection','upgrade','emergency') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'repair' COMMENT 'Type of maintenance work',
  `is_recurring` tinyint(1) DEFAULT '0' COMMENT 'Whether this is a recurring maintenance task',
  `recurring_pattern` enum('daily','weekly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Pattern for recurring tasks',
  `recurring_end_date` date DEFAULT NULL COMMENT 'End date for recurring tasks (NULL = no end)',
  `estimated_duration` int DEFAULT '60' COMMENT 'Estimated duration in minutes',
  `actual_duration` int DEFAULT NULL COMMENT 'Actual duration in minutes (filled when completed)',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'When maintenance was marked completed',
  `verified_by` int UNSIGNED DEFAULT NULL COMMENT 'Admin user who verified the work',
  `verified_at` timestamp NULL DEFAULT NULL COMMENT 'When maintenance was verified',
  `linked_booking_id` int UNSIGNED DEFAULT NULL COMMENT 'Booking ID affected by this maintenance',
  `auto_created` tinyint(1) DEFAULT '0' COMMENT 'Whether this was auto-created by the system',
  `block_room` tinyint(1) DEFAULT '1',
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `due_date` date DEFAULT NULL COMMENT 'Due date for maintenance completion (cannot be in the past)',
  `assigned_to` int UNSIGNED DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_maintenance_schedules`
--

INSERT INTO `room_maintenance_schedules` (`id`, `individual_room_id`, `title`, `description`, `status`, `priority`, `maintenance_type`, `is_recurring`, `recurring_pattern`, `recurring_end_date`, `estimated_duration`, `actual_duration`, `completed_at`, `verified_by`, `verified_at`, `linked_booking_id`, `auto_created`, `block_room`, `start_date`, `end_date`, `due_date`, `assigned_to`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 11, 'Plumbing', '', '', 'urgent', 'repair', 0, NULL, NULL, 60, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-02-22 00:17:00', '2026-02-23 00:17:00', '2026-02-23', 2, 1, '2026-02-22 01:27:37', '2026-02-22 01:27:37'),
(3, 11, 'Plumbing', '', '', 'urgent', 'repair', 0, NULL, NULL, 60, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-02-22 00:17:00', '2026-02-23 00:17:00', '2026-02-23', 2, 1, '2026-02-22 01:28:54', '2026-02-22 01:28:54');

-- --------------------------------------------------------

--
-- Table structure for table `section_headers`
--

CREATE TABLE `section_headers` (
  `id` int NOT NULL,
  `section_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique identifier for the section (e.g., home_rooms, home_facilities)',
  `page` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Page where section appears (e.g., index, restaurant, gym)',
  `section_label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Small label above title (optional)',
  `section_subtitle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Subtitle text between label and title (optional)',
  `section_title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Main section heading',
  `section_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Description text below title',
  `display_order` int DEFAULT '0' COMMENT 'Order of section on page',
  `is_active` tinyint(1) DEFAULT '1' COMMENT 'Whether section header is active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `section_headers`
--

INSERT INTO `section_headers` (`id`, `section_key`, `page`, `section_label`, `section_subtitle`, `section_title`, `section_description`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(19, 'home_rooms', 'index', 'Accommodations', 'Where Comfort Meets Reality', 'Luxurious Rooms & Suites', 'Experience unmatched comfort in our meticulously designed rooms and suites', 1, 1, '2026-02-07 11:34:58', '2026-02-07 11:46:29'),
(20, 'home_facilities', 'index', 'Amenities', NULL, 'World-Class Facilities', 'Indulge in our premium facilities designed for your ultimate comfort', 2, 1, '2026-02-07 11:34:58', '2026-02-07 11:34:58'),
(21, 'home_testimonials', 'index', 'Reviews', NULL, 'What Our Guests Say', 'Hear from those who have experienced our exceptional hospitality', 3, 1, '2026-02-07 11:34:58', '2026-02-07 11:34:58'),
(22, 'hotel_gallery', 'index', 'Visual Journey', 'Discover Our Story', 'Explore Our Hotel', 'Immerse yourself in the beauty and luxury of our hotel', 4, 1, '2026-02-07 11:34:58', '2026-02-07 11:34:58'),
(23, 'hotel_reviews', 'global', 'Guest Reviews', NULL, 'What Our Guests Say', 'Read authentic reviews from guests who have experienced our hospitality', 1, 1, '2026-02-07 11:34:58', '2026-02-07 11:34:58'),
(24, 'restaurant_gallery', 'restaurant', 'Visual Journey', NULL, 'Our Dining Spaces', 'From elegant interiors to breathtaking views, every detail creates the perfect ambiance', 1, 1, '2026-02-07 11:34:58', '2026-02-07 11:34:58'),
(25, 'restaurant_menu', 'restaurant', 'Culinary Delights', 'A Symphony of Flavors', 'Our Menu', 'Discover our carefully curated selection of dishes and beverages', 2, 1, '2026-02-07 11:34:58', '2026-02-07 11:34:58'),
(26, 'gym_wellness', 'gym', 'Your Wellness Journey', 'Transform Your Life', 'Start Your Fitness Journey', 'Transform your body and mind with our state-of-the-art facilities', 1, 1, '2026-02-07 11:34:58', '2026-02-07 11:34:58'),
(27, 'gym_facilities', 'gym', 'What We Offer', NULL, 'Comprehensive Fitness Facilities', 'Everything you need for a complete wellness experience', 2, 1, '2026-02-07 11:34:58', '2026-02-07 11:34:58'),
(28, 'gym_classes', 'gym', 'Stay Active', NULL, 'Group Fitness Classes', 'Join our expert-led classes designed for all fitness levels', 3, 1, '2026-02-07 11:34:58', '2026-02-07 11:34:58'),
(29, 'gym_training', 'gym', 'One-on-One Coaching', NULL, 'Personal Training Programs', 'Achieve your fitness goals faster with personalized guidance from our certified trainers', 4, 1, '2026-02-07 11:34:58', '2026-02-07 11:34:58'),
(30, 'gym_packages', 'gym', 'Exclusive Offers', NULL, 'Wellness Packages', 'Comprehensive packages designed for optimal health and relaxation', 5, 1, '2026-02-07 11:34:58', '2026-02-07 11:34:58'),
(31, 'rooms_collection', 'rooms-showcase', 'Stay Collection', NULL, 'Pick Your Perfect Space', 'Suites and rooms crafted for business, romance, and family stays with direct booking flows', 1, 1, '2026-02-07 11:34:58', '2026-02-07 11:34:58'),
(32, 'conference_overview', 'conference', 'Professional Events', 'Where Business Meets Excellence', 'Conference & Meeting Facilities', 'State-of-the-art venues for your business needs', 1, 1, '2026-02-07 11:34:58', '2026-02-07 11:34:58'),
(33, 'events_overview', 'events', 'Celebrations & Gatherings', NULL, 'Upcoming Events', 'Discover our curated experiences and special occasions', 1, 1, '2026-02-07 11:34:58', '2026-02-07 11:34:58'),
(34, 'matuwi_welcome', 'restaurant', 'Welcome to', 'Nestled along Lake Malawi', 'Matuwi Kitchen', 'Nestled along the picturesque shores of Lake Malawi in the heart of Mangochi, our restaurant offers a unique culinary journey that blends the rich flavors of local traditions with the exquisite tastes of international cuisine. Our menu is a testament to the vibrant culture and bountiful resources of our beautiful country.\n\nFrom fresh, locally-sourced ingredients to creative culinary innovations, each meal is crafted with passion and care. Whether you\'re in the mood for a comforting Malawian classic or an adventurous global delight, our chefs are dedicated to bringing you an unforgettable dining experience.\n\nSit back, relax, and let us take you on a gastronomic adventure that mirrors the stunning landscapes and warm hospitality of Malawi.\n\nEnjoy your meal!', 1, 1, '2026-02-11 23:08:45', '2026-02-11 23:08:45');

-- --------------------------------------------------------

--
-- Table structure for table `session_logs`
--

CREATE TABLE `session_logs` (
  `id` int UNSIGNED NOT NULL,
  `session_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'unknown',
  `browser` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `os` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `page_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `referrer_domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_start` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `page_count` int DEFAULT '1',
  `consent_level` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `session_logs`
--

INSERT INTO `session_logs` (`id`, `session_id`, `ip_address`, `device_type`, `browser`, `os`, `page_url`, `referrer_domain`, `country`, `session_start`, `last_activity`, `page_count`, `consent_level`) VALUES
(1, 'l56joh70ku2j1qb27jpfsnf9hd', '::1', 'desktop', 'Chrome', 'Windows 10/11', '/conference.php', 'localhost', 'Local', '2026-02-09 11:42:50', '2026-02-09 11:45:47', 5, 'all'),
(6, 'p7nj9em2l885n6mfkfgf6ea72m', '::1', 'desktop', 'Chrome', 'Windows 10/11', '/conference.php', 'localhost', 'Local', '2026-02-09 11:53:31', '2026-02-09 11:53:31', 1, 'all'),
(7, 'bt1vih1r78h284ar922skn7fcq', '51.37.179.253', 'desktop', 'Chrome', 'Windows 10/11', '/hotelsmw/conference.php', 'promanaged-it.com', 'Kilcock, Leinster, Ireland', '2026-02-09 12:03:26', '2026-02-09 12:14:58', 5, 'all'),
(12, '07jdpjogrkj36jv6tc14bj1rp5', '137.115.5.18', 'desktop', 'Chrome', 'Windows 10/11', '/hotelsmw/booking.php?room_id=2', 'promanaged-it.com', 'Lilongwe, Central Region, Malawi', '2026-02-10 10:53:15', '2026-02-10 10:54:11', 4, 'all'),
(16, 'g16vk42uadk0k2inji8qb5rch4', '51.37.179.253', 'mobile', 'Chrome', 'Android', '/hotelsmw/booking.php?room_id=2', 'promanaged-it.com', 'Kilcock, Leinster, Ireland', '2026-02-10 11:41:50', '2026-02-10 11:41:50', 1, 'all'),
(17, 'creg0oud31dh6l122jd5b97ahi', '74.125.208.33', 'mobile', 'Chrome', 'Android', '/hotelsmw/booking.php?room_id=2', '', 'Mountain View, California, United States', '2026-02-10 11:41:52', '2026-02-10 11:41:52', 1, 'pending'),
(18, 'beprqfnf15dug0bv43p4gio3im', '66.102.9.105', 'mobile', 'Chrome', 'Android', '/hotelsmw/booking.php?room_id=2', '', 'Mountain View, California, United States', '2026-02-10 11:41:52', '2026-02-10 11:41:52', 1, 'pending'),
(19, 'haf5ckq4u83mkkqnudkccnhvh7', '66.249.83.2', 'mobile', 'Chrome', 'Android', '/hotelsmw/booking.php?room_id=2', '', 'Mountain View, California, United States', '2026-02-10 11:41:53', '2026-02-10 11:41:53', 1, 'pending'),
(20, 'u6b1a48kmaoajnj1kvrt00i4sc', '216.234.217.230', 'mobile', 'Chrome', 'Android', '/hotelsmw/booking.php?room_id=2', 'promanaged-it.com', 'Lilongwe, Central Region, Malawi', '2026-02-10 11:41:57', '2026-02-10 13:03:31', 2, 'all'),
(22, '37q9vfut3duagv5pcdr7jgqh03', '127.0.0.1', 'desktop', 'Chrome', 'Windows 10/11', '/gym.php', 'localhost', 'Local', '2026-02-11 13:20:09', '2026-02-11 23:08:08', 5, 'all'),
(24, 'oi1nru119hk5dph6vvnlbmpc7u', '::1', 'desktop', 'Unknown', 'Unknown', '/gym.php', '', 'Local', '2026-02-11 16:02:39', '2026-02-11 16:02:39', 1, 'pending'),
(25, 'osp2frvgabpj2i0j5d42ccfp0a', '::1', 'desktop', 'Unknown', 'Unknown', '/conference.php', '', 'Local', '2026-02-11 16:02:41', '2026-02-11 16:02:41', 1, 'pending'),
(26, 'unija69kv0vs120d6mqjbsudor', '::1', 'desktop', 'Unknown', 'Unknown', '/booking.php', '', 'Local', '2026-02-11 16:02:44', '2026-02-11 16:02:44', 1, 'pending'),
(27, '3uekstv282tplgdirbsb8jittf', '::1', 'desktop', 'Unknown', 'Unknown', '/gym.php', '', 'Local', '2026-02-11 16:03:27', '2026-02-11 16:03:27', 1, 'pending'),
(28, '1h297hemcct1v4ht0q4no8ci8n', '::1', 'desktop', 'Unknown', 'Unknown', '/conference.php', '', 'Local', '2026-02-11 16:03:30', '2026-02-11 16:03:30', 1, 'pending'),
(29, 'kts3rukk9d26dica7sviah9v94', '::1', 'desktop', 'Unknown', 'Unknown', '/booking.php', '', 'Local', '2026-02-11 16:03:32', '2026-02-11 16:03:32', 1, 'pending'),
(30, 'eic6lschcbtli2qsqdnv4865ag', '::1', 'desktop', 'Unknown', 'Unknown', '/gym.php', '', 'Local', '2026-02-11 16:05:47', '2026-02-11 16:05:47', 1, 'pending'),
(31, 'rnktsvanphc1i7r26vvefd98o2', '::1', 'desktop', 'Unknown', 'Unknown', '/conference.php', '', 'Local', '2026-02-11 16:05:49', '2026-02-11 16:05:49', 1, 'pending'),
(32, 'tv52nkashstosslr4p0tti3kq5', '::1', 'desktop', 'Unknown', 'Unknown', '/booking.php', '', 'Local', '2026-02-11 16:05:52', '2026-02-11 16:05:52', 1, 'pending'),
(34, '76e1666348af858dd42192b7d8b97cbc', '::1', 'desktop', 'Chrome', 'Windows 10/11', '/gym.php', 'localhost', 'Local', '2026-02-11 22:54:46', '2026-02-11 22:54:46', 1, 'all'),
(37, 'mlbvh0l8tt342ce5nabtee1ute', '192.168.2.5', 'mobile', 'Chrome', 'Android', '/gym.php', '192.168.2.13', 'Local', '2026-02-12 00:18:03', '2026-02-12 00:18:03', 1, 'pending'),
(38, 'boigkp6qcf5b7hpoacd2sh0cns', '::1', 'desktop', 'Chrome', 'Windows 10/11', '/gym.php', 'localhost', 'Local', '2026-02-12 07:47:56', '2026-02-12 09:16:20', 8, 'all'),
(46, '11p51g550ut55d9k3c6r62f7cr', '::1', 'desktop', 'Unknown', 'Unknown', '/gym.php', '', 'Local', '2026-02-12 09:37:32', '2026-02-12 09:37:32', 1, 'pending'),
(47, 'u0edd2prbcnveb545k1dgtmqkp', '::1', 'desktop', 'Unknown', 'Unknown', '/booking.php', '', 'Local', '2026-02-12 09:39:39', '2026-02-12 09:39:39', 1, 'pending'),
(48, 'mqfkl5n4ir5amvtc8o9pqq490l', '::1', 'desktop', 'Unknown', 'Unknown', '/conference.php', '', 'Local', '2026-02-12 09:39:41', '2026-02-12 09:39:41', 1, 'pending'),
(49, 'pssjlfk2a09pqfh8l9m6rnos2n', '::1', 'desktop', 'Unknown', 'Unknown', '/booking-lookup.php', '', 'Local', '2026-02-12 09:39:42', '2026-02-12 09:39:42', 1, 'pending'),
(50, 'ug6fr58jukg7q51og4u4a5vi62', '::1', 'desktop', 'Unknown', 'Unknown', '/submit-review.php', '', 'Local', '2026-02-12 09:39:43', '2026-02-12 09:39:43', 1, 'pending'),
(51, '3i7o7oiebkhhu8cemk3lb27mm7', '::1', 'desktop', 'Chrome', 'Windows 10/11', '/gym.php', 'localhost', 'Local', '2026-02-12 11:50:20', '2026-02-12 14:40:21', 6, 'all'),
(57, 'ainu40jb7g8jpipm98p7q64v6d', '::1', 'desktop', 'Chrome', 'Windows 10/11', '/booking.php', 'localhost', 'Local', '2026-02-13 11:20:55', '2026-02-13 11:20:55', 1, 'pending'),
(58, '7c44707919b95977281b1459656e5525', '127.0.0.1', 'desktop', 'Chrome', 'Windows 10/11', '/gym.php', 'localhost', 'Local', '2026-02-13 17:26:05', '2026-02-13 19:02:36', 7, 'all'),
(62, 'e4bll24n4815p6c4evu0vngg77', '::1', 'desktop', 'Chrome', 'Windows 10/11', '/gym.php', 'localhost', 'Local', '2026-02-13 18:01:55', '2026-02-13 18:21:57', 2, 'all'),
(67, 'ef8be1a55adcbb7a94a4d61f8995f58c', '192.168.2.5', 'mobile', 'Chrome', 'Android', '/conference.php', '192.168.2.18', 'Local', '2026-02-13 19:15:31', '2026-02-13 19:15:31', 1, '{\"version\":\"1.0\",\"ti'),
(68, 'e094f9e4e454fc5da1373bd64d70347d', '192.168.2.5', 'mobile', 'Chrome', 'Android', '/booking.php?check_in=2026-02-15&check_out=2026-02-17&guests=1', '192.168.2.18', 'Local', '2026-02-15 23:21:21', '2026-02-15 23:21:21', 1, '{\"version\":\"1.0\",\"ti'),
(69, 'drqqccrjkafr08nhsufqg460td', '127.0.0.1', 'desktop', 'Chrome', 'Windows 10/11', '/booking.php', 'localhost', 'Local', '2026-02-17 10:42:35', '2026-02-17 21:03:32', 9, 'all'),
(78, 's27pekk2s9np7i6t3c2mrr24p9', '127.0.0.1', 'desktop', 'Chrome', 'Windows 10/11', '/gym.php', 'localhost', 'Local', '2026-02-18 10:29:20', '2026-02-18 11:48:31', 3, 'all'),
(81, 'jdp02hfip1clvm2mtgtt6vv63d', '127.0.0.1', 'desktop', 'Chrome', 'Windows 10/11', '/booking.php', 'localhost', 'Local', '2026-02-18 22:17:29', '2026-02-18 22:17:29', 1, 'all'),
(82, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'desktop', 'Chrome', 'Windows 10/11', '/booking.php?room_id=1', 'localhost', 'Local', '2026-02-19 11:44:15', '2026-02-22 21:41:37', 62, 'all'),
(86, 'da2ggsdcrjishlole11qh7g60m', '192.168.2.5', 'mobile', 'Chrome', 'Android', '/submit-review.php', '192.168.2.13', 'Local', '2026-02-19 18:50:14', '2026-02-19 18:50:14', 1, 'all'),
(121, '9foud31tejj0q4kekpgfu0aj9e', '51.37.179.253', 'mobile', 'Chrome', 'Android', '/rosalyns/submit-review.php/index.php', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '2026-02-22 18:28:19', '2026-02-22 18:28:40', 2, 'all'),
(147, '73v6u0p31n9i8vji7p054jngmu', '51.37.179.253', 'desktop', 'Chrome', 'Windows 10/11', '/rosalyns/contact-us.php', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '2026-02-22 22:38:03', '2026-02-23 09:59:01', 6, 'all'),
(151, '7vd27dri4cm4dqjaa2b1fik6gt', '51.37.179.253', 'mobile', 'Chrome', 'Android', '/rosalyns/submit-review.php', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '2026-02-23 01:14:33', '2026-02-23 01:14:33', 1, 'all'),
(154, '7ac14nsd90a9j71g8ks9cecbp5', '127.0.0.1', 'desktop', 'Chrome', 'Windows 10/11', '/contact-us.php', '', 'Local', '2026-02-23 22:48:33', '2026-02-23 23:49:30', 5, 'all'),
(159, 'tpvp8bd9h51rf9eoajjukren5j', '192.168.2.5', 'mobile', 'Chrome', 'Android', '/booking.php', '192.168.2.13', 'Local', '2026-02-24 00:10:59', '2026-02-24 00:12:27', 2, 'all'),
(161, '7hogg49ajqk5s0d062vh7nk1v7', '127.0.0.1', 'desktop', 'Chrome', 'Windows 10/11', '/submit-review.php', 'localhost', 'Local', '2026-02-24 18:41:12', '2026-02-24 19:06:08', 6, 'all'),
(167, '8e7l51h4bcug7u1de0ci7f0p5v', '127.0.0.1', 'desktop', 'Chrome', 'Windows 10/11', '/conference.php', 'localhost', 'Local', '2026-02-25 13:47:44', '2026-02-25 15:16:40', 3, 'all'),
(170, 'tp6sgdl8v97roq377gt79ltkli', '127.0.0.1', 'desktop', 'Chrome', 'Windows 10/11', '/conference.php', 'localhost', 'Local', '2026-02-26 17:25:26', '2026-02-26 23:56:27', 4, 'all'),
(174, 's2l6js4f7d9kejlbqi2a3sdn6c', '127.0.0.1', 'desktop', 'Chrome', 'Windows 10/11', '/conference.php', '', 'Local', '2026-02-27 00:18:13', '2026-02-27 00:18:13', 1, 'all'),
(175, '3rsbq26n4unf1v2u4v8hpiti8m', '127.0.0.1', 'desktop', 'Chrome', 'Windows 10/11', '/contact-us.php', 'localhost', 'Local', '2026-02-27 20:10:12', '2026-02-28 16:41:55', 3, 'all'),
(176, 'ctkrmts05gu1ct53q59bions2q', '51.37.179.253', 'mobile', 'Chrome', 'Android', '/rosalyns/booking-confirmation.php?ref=LSH20264588', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '2026-02-27 20:16:13', '2026-02-28 10:03:23', 4, 'all'),
(178, 'vbj425n30pt7pe4eejrqaro66q', '142.250.32.34', 'mobile', 'Chrome', 'Android', '/rosalyns/booking.php', '', 'Mountain View, California, United States', '2026-02-27 20:16:36', '2026-02-27 20:16:36', 1, 'pending'),
(181, '7t7a025cikg3ql8t4cnck5at3g', '142.250.32.34', 'mobile', 'Chrome', 'Android', '/rosalyns/booking-confirmation.php?ref=LSH20264588', '', 'Mountain View, California, United States', '2026-02-28 10:03:25', '2026-02-28 10:03:25', 1, 'pending'),
(184, '9v2kqg1lghia5jd7nnme5sst9v', '51.37.179.253', 'mobile', 'Chrome', 'Android', '/rosalyns/conference.php', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '2026-02-28 16:49:16', '2026-02-28 16:51:27', 3, 'all'),
(187, 'u8vijpmr4fcvuhtde8j68p33dr', '51.37.179.253', 'desktop', 'Chrome', 'Windows 10/11', '/rosalyns/conference.php', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '2026-03-01 20:55:36', '2026-03-01 20:55:54', 2, 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `site_pages`
--

CREATE TABLE `site_pages` (
  `id` int NOT NULL,
  `page_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique slug, e.g. home, rooms, restaurant',
  `title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Display name in navigation',
  `file_path` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PHP file, e.g. rooms-gallery.php',
  `icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'fa-file' COMMENT 'Font Awesome icon class',
  `nav_position` int DEFAULT '0' COMMENT 'Order in navigation (lower = first)',
  `show_in_nav` tinyint(1) DEFAULT '1' COMMENT '1 = visible in nav, 0 = hidden from nav but page still accessible',
  `is_enabled` tinyint(1) DEFAULT '1' COMMENT '1 = page accessible, 0 = returns 404 / redirect',
  `requires_auth` tinyint(1) DEFAULT '0' COMMENT 'Future: require login to view',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Short description for admin reference',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_pages`
--

INSERT INTO `site_pages` (`id`, `page_key`, `title`, `file_path`, `icon`, `nav_position`, `show_in_nav`, `is_enabled`, `requires_auth`, `description`, `created_at`, `updated_at`) VALUES
(1, 'home', 'Home', 'index.php', 'fa-home', 10, 1, 1, 0, 'Main landing page', '2026-02-07 21:36:55', '2026-02-07 21:36:55'),
(2, 'rooms', 'Rooms', 'rooms-gallery.php', 'fa-bed', 20, 1, 1, 0, 'Room gallery & listings', '2026-02-07 21:36:55', '2026-02-07 21:40:35'),
(3, 'restaurant', 'Restaurant', 'restaurant.php', 'fa-utensils', 30, 1, 1, 0, 'Restaurant & menu', '2026-02-07 21:36:55', '2026-02-07 21:36:55'),
(4, 'gym', 'Gym', 'gym.php', 'fa-dumbbell', 40, 1, 0, 0, 'Gym & fitness centre', '2026-02-07 21:36:55', '2026-02-20 09:42:08'),
(5, 'conference', 'Conference', 'conference.php', 'fa-briefcase', 50, 1, 1, 0, 'Conference facilities', '2026-02-07 21:36:55', '2026-02-07 21:36:55'),
(6, 'events', 'Events', 'events.php', 'fa-calendar-alt', 60, 1, 1, 0, 'Hotel events', '2026-02-07 21:36:55', '2026-02-07 21:36:55'),
(7, 'booking', 'Book Now', 'booking.php', 'fa-calendar-check', 100, 1, 1, 0, 'Booking page (CTA button, not regular nav)', '2026-02-07 21:36:55', '2026-02-07 21:40:55'),
(9, 'contact-us', 'Contact Us', 'contact-us.php', 'fa-envelope', 99, 0, 1, 0, NULL, '2026-02-23 13:03:48', '2026-02-23 13:03:48'),
(10, 'guest-services', 'Guest Services', 'guest-services.php', 'fa-concierge-bell', 98, 0, 1, 0, NULL, '2026-02-23 13:03:48', '2026-02-23 13:03:48');

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_group` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `hero_video_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path to hero section video',
  `hero_video_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Video MIME type'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `setting_key`, `setting_value`, `setting_group`, `updated_at`, `hero_video_path`, `hero_video_type`) VALUES
(1, 'site_name', 'Rosalyns Beach Hotel', 'general', '2026-02-24 16:03:58', NULL, NULL),
(2, 'site_tagline', 'Experience the difference.', 'general', '2026-02-17 11:12:27', NULL, NULL),
(3, 'hero_title', 'Your Comfortable Stay in Malawi', 'hero', '2026-02-07 02:40:53', NULL, NULL),
(4, 'hero_subtitle', 'Enjoy a pleasant and affordable stay with clean rooms, friendly service, and good value for money.', 'hero', '2026-02-07 02:40:53', NULL, NULL),
(5, 'phone_main', '+265 888 226 665', 'contact', '2026-02-17 19:48:26', NULL, NULL),
(6, 'phone_reservations', '+265 888 226 665', 'contact', '2026-02-17 19:48:12', NULL, NULL),
(9, 'address_line1', ' Matuwi Village', 'contact', '2026-02-17 19:45:06', NULL, NULL),
(10, 'address_line2', 'Mangochi', 'contact', '2026-02-17 19:45:14', NULL, NULL),
(11, 'address_country', 'Malawi', 'contact', '2026-01-19 20:22:49', NULL, NULL),
(12, 'facebook_url', 'https://www.facebook.com/100089610594010/', 'social', '2026-02-19 11:55:57', NULL, NULL),
(13, 'instagram_url', 'https://www.facebook.com/100089610594010/', 'social', '2026-02-19 11:56:02', NULL, NULL),
(14, 'twitter_url', 'https://www.facebook.com/100089610594010/', 'social', '2026-02-19 11:56:05', NULL, NULL),
(15, 'linkedin_url', 'https://www.facebook.com/100089610594010/', 'social', '2026-02-19 11:56:09', NULL, NULL),
(16, 'working_hours', '24/7 Available', 'contact', '2026-01-19 20:22:49', NULL, NULL),
(17, 'copyright_text', '2026 Rosalyns Beach Hotel. All rights reserved.', 'general', '2026-02-11 11:57:46', NULL, NULL),
(18, 'currency_symbol', 'MWK', 'general', '2026-01-20 10:16:28', NULL, NULL),
(19, 'currency_code', 'MWK', 'general', '2026-01-20 10:16:13', NULL, NULL),
(20, 'site_logo', 'https://www.rosalynsbeachhotel.com/static/media/logo.068798eab68c159cf5e9.png', 'general', '2026-02-21 16:13:35', NULL, NULL),
(23, 'site_url', 'https://promanaged-it.com/rosalyns', 'general', '2026-02-22 11:55:29', NULL, NULL),
(27, 'check_in_time', '2:00 PM', 'booking', '2026-01-27 12:02:11', NULL, NULL),
(28, 'check_out_time', '11:00 AM', 'booking', '2026-01-27 12:02:11', NULL, NULL),
(29, 'booking_change_policy', 'If you need to make any changes, please contact us at least 48 hours before your arrival.', 'booking', '2026-01-27 12:02:11', NULL, NULL),
(30, 'email_main', 'johnpaulchirwa@gmail.com', 'contact', '2026-02-17 19:47:46', NULL, NULL),
(32, 'vat_enabled', '1', 'accounting', '2026-01-30 00:09:59', NULL, NULL),
(33, 'vat_rate', '16.5', 'accounting', '2026-01-30 00:09:59', NULL, NULL),
(34, 'vat_number', 'MW123456789', 'accounting', '2026-01-30 00:09:59', NULL, NULL),
(35, 'payment_terms', 'Payment due upon check-in', 'accounting', '2026-01-30 00:09:59', NULL, NULL),
(36, 'invoice_prefix', 'INV', 'accounting', '2026-01-30 00:09:59', NULL, NULL),
(37, 'invoice_start_number', '1001', 'accounting', '2026-01-30 00:09:59', NULL, NULL),
(44, 'max_advance_booking_days', '22', 'booking', '2026-01-30 00:40:21', NULL, NULL),
(45, 'payment_policy', 'Full payment is required upon check-in. We accept cash, credit cards, and bank transfers.', 'booking', '2026-01-30 00:36:10', NULL, NULL),
(76, 'tentative_enabled', '1', 'bookings', '2026-02-01 16:32:10', NULL, NULL),
(77, 'tentative_duration_hours', '48', 'bookings', '2026-02-01 16:32:10', NULL, NULL),
(78, 'tentative_reminder_hours', '24', 'bookings', '2026-02-01 16:32:10', NULL, NULL),
(79, 'tentative_max_extensions', '2', 'bookings', '2026-02-01 16:32:10', NULL, NULL),
(80, 'tentative_deposit_percent', '20', 'bookings', '2026-02-01 16:32:10', NULL, NULL),
(81, 'tentative_deposit_required', '0', 'bookings', '2026-02-01 16:32:10', NULL, NULL),
(82, 'tentative_block_availability', '1', 'bookings', '2026-02-01 16:32:10', NULL, NULL),
(90, 'whatsapp_number', '+353860081635', 'contact', '2026-02-18 21:43:20', NULL, NULL),
(102, 'footer_credits', '© 2026 Rosalyns Beach Hotel.', 'general', '2026-02-24 16:04:18', NULL, NULL),
(103, 'footer_design_credit', 'Powered by ProManaged IT', 'general', '2026-02-02 00:33:08', NULL, NULL),
(104, 'footer_share_title', 'Share', 'general', '2026-02-02 00:32:07', NULL, NULL),
(105, 'footer_connect_title', 'Connect With Us', 'general', '2026-02-02 00:32:07', NULL, NULL),
(106, 'footer_contact_title', 'Contact Information', 'general', '2026-02-02 00:32:07', NULL, NULL),
(107, 'footer_policies_title', 'Policies', 'general', '2026-02-02 00:32:07', NULL, NULL),
(108, 'conference_email', 'johnpaulchira@gmail.com', 'contact', '2026-02-03 00:06:33', NULL, NULL),
(109, 'gym_email', 'johnpaulchira@gmail.com', 'contact', '2026-02-03 00:06:38', NULL, NULL),
(112, 'pending_duration_hours', '24', 'booking', '2026-02-03 00:29:35', NULL, NULL),
(113, 'tentative_grace_period_hours', '0', 'booking', '2026-02-03 00:29:35', NULL, NULL),
(114, 'admin_notification_email', '', 'email', '2026-02-03 00:29:35', NULL, NULL),
(115, 'booking_time_buffer_minutes', '60', 'booking', '2026-02-03 17:49:20', NULL, NULL),
(118, 'default_keywords', 'hotel malawi, liwonde accommodation, budget hotel, affordable stay, malawi lodging', 'general', '2026-02-07 02:40:53', NULL, NULL),
(120, 'phone_reception', '0883 500 304', 'contact', '2026-02-04 21:24:01', NULL, NULL),
(121, 'phone_cell1', '0998 864 377', 'contact', '2026-02-04 21:24:01', NULL, NULL),
(122, 'phone_cell2', '0882 363 765', 'contact', '2026-02-04 21:24:01', NULL, NULL),
(123, 'phone_alternate1', '0983 825 196', 'contact', '2026-02-04 21:24:01', NULL, NULL),
(124, 'phone_alternate2', '0999 877 796', 'contact', '2026-02-04 21:24:01', NULL, NULL),
(125, 'phone_alternate3', '0888 353 540', 'contact', '2026-02-04 21:24:01', NULL, NULL),
(126, 'email_restaurant', 'johnpaulchirwa@gmail.com', 'contact', '2026-02-19 11:56:37', NULL, NULL),
(127, 'cache_email_enabled', '1', NULL, '2026-02-05 17:24:35', NULL, NULL),
(128, 'cache_settings_enabled', '1', NULL, '2026-02-05 17:24:38', NULL, NULL),
(129, 'cache_rooms_enabled', '1', NULL, '2026-02-07 12:49:46', NULL, NULL),
(140, 'cache_tables_enabled', '1', NULL, '2026-02-05 18:31:48', NULL, NULL),
(263, 'cache_schedule_enabled', '1', NULL, '2026-02-07 13:03:46', NULL, NULL),
(264, 'cache_schedule_interval', 'daily', NULL, '2026-02-07 13:03:46', NULL, NULL),
(265, 'cache_schedule_time', '00:00', NULL, '2026-02-07 13:03:46', NULL, NULL),
(266, 'cache_global_enabled', '1', NULL, '2026-02-07 12:49:14', NULL, NULL),
(269, 'cache_last_run', '1770469224', NULL, '2026-02-07 13:00:24', NULL, NULL),
(273, 'cache_custom_seconds', '60', NULL, '2026-02-07 13:03:46', NULL, NULL),
(283, 'upcoming_events_enabled', '1', 'upcoming_events', '2026-02-09 15:34:41', NULL, NULL),
(284, 'upcoming_events_pages', '[\"index\"]', 'upcoming_events', '2026-02-09 15:34:41', NULL, NULL),
(285, 'upcoming_events_max_display', '4', 'upcoming_events', '2026-02-09 15:34:41', NULL, NULL),
(286, 'booking_system_enabled', '1', 'booking', '2026-02-11 22:43:54', NULL, NULL),
(287, 'booking_disabled_message', 'For booking inquiries, please contact us directly at: [contact info]', 'booking', '2026-02-11 22:43:54', NULL, NULL),
(288, 'booking_disabled_action', 'message', 'booking', '2026-02-11 22:43:55', NULL, NULL),
(290, 'booking_notification_email', 'johnpaulchirwa@gmail.com', 'email', '2026-02-17 09:12:53', NULL, NULL),
(293, 'booking_notification_cc_emails', 'johnpaulchirwa@gmail.com', NULL, '2026-02-17 09:12:53', NULL, NULL),
(295, 'whatsapp_enabled', '0', NULL, '2026-02-18 21:43:20', NULL, NULL),
(296, 'whatsapp_api_token', '', NULL, '2026-02-18 21:43:20', NULL, NULL),
(297, 'whatsapp_phone_id', '', NULL, '2026-02-18 21:43:20', NULL, NULL),
(298, 'whatsapp_business_id', '', NULL, '2026-02-18 21:43:20', NULL, NULL),
(300, 'whatsapp_notify_on_booking', '1', NULL, '2026-02-18 21:43:20', NULL, NULL),
(301, 'whatsapp_notify_on_confirmation', '1', NULL, '2026-02-18 21:43:20', NULL, NULL),
(302, 'whatsapp_notify_on_cancellation', '1', NULL, '2026-02-18 21:43:20', NULL, NULL),
(303, 'whatsapp_notify_on_checkin', '1', NULL, '2026-02-18 21:43:20', NULL, NULL),
(304, 'whatsapp_notify_on_checkout', '1', NULL, '2026-02-18 21:43:20', NULL, NULL),
(305, 'whatsapp_guest_notifications', '1', NULL, '2026-02-18 21:43:20', NULL, NULL),
(306, 'whatsapp_hotel_notifications', '1', NULL, '2026-02-18 21:43:20', NULL, NULL),
(307, 'whatsapp_admin_numbers', '', NULL, '2026-02-18 21:43:20', NULL, NULL),
(308, 'whatsapp_confirmed_template', 'booking_confirmed', NULL, '2026-02-18 21:43:20', NULL, NULL),
(309, 'whatsapp_cancelled_template', 'booking_cancelled', NULL, '2026-02-18 21:43:20', NULL, NULL),
(341, 'logo_url', 'https://promanaged-it.com/rosalyns/images/logo/logo.png', NULL, '2026-02-28 11:32:16', NULL, NULL),
(342, 'tourism_levy_enabled', '0', 'booking', '2026-02-23 01:03:06', NULL, NULL),
(343, 'tourism_levy_percent', '1.00', 'booking', '2026-02-23 01:03:06', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `site_visitors`
--

CREATE TABLE `site_visitors` (
  `id` int UNSIGNED NOT NULL,
  `session_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `device_type` enum('desktop','tablet','mobile','bot','unknown') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'unknown',
  `browser` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `os` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referrer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `referrer_domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `page_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_first_visit` tinyint(1) DEFAULT '0',
  `visit_duration` int DEFAULT NULL COMMENT 'Seconds on page',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_visitors`
--

INSERT INTO `site_visitors` (`id`, `session_id`, `ip_address`, `user_agent`, `device_type`, `browser`, `os`, `referrer`, `referrer_domain`, `country`, `page_url`, `page_title`, `is_first_visit`, `visit_duration`, `created_at`) VALUES
(1, 'l56joh70ku2j1qb27jpfsnf9hd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/conference.php', NULL, 1, NULL, '2026-02-09 11:42:50'),
(2, 'l56joh70ku2j1qb27jpfsnf9hd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-09 11:43:54'),
(3, 'l56joh70ku2j1qb27jpfsnf9hd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-09 11:44:44'),
(4, 'l56joh70ku2j1qb27jpfsnf9hd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-09 11:45:32'),
(5, 'l56joh70ku2j1qb27jpfsnf9hd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-09 11:45:47'),
(6, 'p7nj9em2l885n6mfkfgf6ea72m', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/rooms-gallery.php', 'localhost', 'Local', '/conference.php', NULL, 1, NULL, '2026-02-09 11:53:31'),
(7, 'bt1vih1r78h284ar922skn7fcq', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'https://promanaged-it.com/hotelsmw/restaurant.php', 'promanaged-it.com', 'Kilcock, Leinster, Ireland', '/hotelsmw/gym.php', NULL, 1, NULL, '2026-02-09 12:03:26'),
(8, 'bt1vih1r78h284ar922skn7fcq', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'https://promanaged-it.com/hotelsmw/gym.php', 'promanaged-it.com', 'Kilcock, Leinster, Ireland', '/hotelsmw/conference.php', NULL, 0, NULL, '2026-02-09 12:03:59'),
(9, 'bt1vih1r78h284ar922skn7fcq', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'https://promanaged-it.com/hotelsmw/conference.php', 'promanaged-it.com', 'Kilcock, Leinster, Ireland', '/hotelsmw/conference.php', NULL, 0, NULL, '2026-02-09 12:04:45'),
(10, 'bt1vih1r78h284ar922skn7fcq', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'https://promanaged-it.com/hotelsmw/conference.php', 'promanaged-it.com', 'Kilcock, Leinster, Ireland', '/hotelsmw/conference.php', NULL, 0, NULL, '2026-02-09 12:12:19'),
(11, 'bt1vih1r78h284ar922skn7fcq', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'https://promanaged-it.com/hotelsmw/conference.php', 'promanaged-it.com', 'Kilcock, Leinster, Ireland', '/hotelsmw/conference.php', NULL, 0, NULL, '2026-02-09 12:14:58'),
(12, '07jdpjogrkj36jv6tc14bj1rp5', '137.115.5.18', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'https://promanaged-it.com/hotelsmw/rooms-gallery.php', 'promanaged-it.com', 'Lilongwe, Central Region, Malawi', '/hotelsmw/gym.php', NULL, 1, NULL, '2026-02-10 10:53:15'),
(13, '07jdpjogrkj36jv6tc14bj1rp5', '137.115.5.18', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'https://promanaged-it.com/hotelsmw/rooms-gallery.php', 'promanaged-it.com', 'Lilongwe, Central Region, Malawi', '/hotelsmw/conference.php', NULL, 0, NULL, '2026-02-10 10:53:18'),
(14, '07jdpjogrkj36jv6tc14bj1rp5', '137.115.5.18', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'https://promanaged-it.com/hotelsmw/rooms-gallery.php', 'promanaged-it.com', 'Lilongwe, Central Region, Malawi', '/hotelsmw/booking.php', NULL, 0, NULL, '2026-02-10 10:53:20'),
(15, '07jdpjogrkj36jv6tc14bj1rp5', '137.115.5.18', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'https://promanaged-it.com/hotelsmw/room.php?room=executive-suite', 'promanaged-it.com', 'Lilongwe, Central Region, Malawi', '/hotelsmw/booking.php?room_id=2', NULL, 0, NULL, '2026-02-10 10:54:11'),
(16, 'g16vk42uadk0k2inji8qb5rch4', '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'https://promanaged-it.com/hotelsmw/room.php?room=executive-suite', 'promanaged-it.com', 'Kilcock, Leinster, Ireland', '/hotelsmw/booking.php?room_id=2', NULL, 1, NULL, '2026-02-10 11:41:50'),
(17, 'creg0oud31dh6l122jd5b97ahi', '74.125.208.33', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', 'mobile', 'Chrome', 'Android', '', '', 'Mountain View, California, United States', '/hotelsmw/booking.php?room_id=2', NULL, 1, NULL, '2026-02-10 11:41:52'),
(18, 'beprqfnf15dug0bv43p4gio3im', '66.102.9.105', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', 'mobile', 'Chrome', 'Android', '', '', 'Mountain View, California, United States', '/hotelsmw/booking.php?room_id=2', NULL, 1, NULL, '2026-02-10 11:41:52'),
(19, 'haf5ckq4u83mkkqnudkccnhvh7', '66.249.83.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', 'mobile', 'Chrome', 'Android', '', '', 'Mountain View, California, United States', '/hotelsmw/booking.php?room_id=2', NULL, 1, NULL, '2026-02-10 11:41:53'),
(20, 'u6b1a48kmaoajnj1kvrt00i4sc', '216.234.217.230', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'https://promanaged-it.com/hotelsmw/room.php?room=executive-suite', 'promanaged-it.com', 'Lilongwe, Central Region, Malawi', '/hotelsmw/booking.php?room_id=2', NULL, 1, NULL, '2026-02-10 11:41:57'),
(21, 'u6b1a48kmaoajnj1kvrt00i4sc', '216.234.217.230', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'https://promanaged-it.com/hotelsmw/room.php?room=executive-suite', 'promanaged-it.com', NULL, '/hotelsmw/booking.php?room_id=2', NULL, 1, NULL, '2026-02-10 13:03:31'),
(22, '37q9vfut3duagv5pcdr7jgqh03', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/gym.php', NULL, 1, NULL, '2026-02-11 13:20:09'),
(23, '37q9vfut3duagv5pcdr7jgqh03', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/gym.php', NULL, 0, NULL, '2026-02-11 13:30:41'),
(24, 'oi1nru119hk5dph6vvnlbmpc7u', '::1', 'curl/8.13.0', 'desktop', 'Unknown', 'Unknown', '', '', 'Local', '/gym.php', NULL, 1, NULL, '2026-02-11 16:02:39'),
(25, 'osp2frvgabpj2i0j5d42ccfp0a', '::1', 'curl/8.13.0', 'desktop', 'Unknown', 'Unknown', '', '', 'Local', '/conference.php', NULL, 1, NULL, '2026-02-11 16:02:41'),
(26, 'unija69kv0vs120d6mqjbsudor', '::1', 'curl/8.13.0', 'desktop', 'Unknown', 'Unknown', '', '', 'Local', '/booking.php', NULL, 1, NULL, '2026-02-11 16:02:44'),
(27, '3uekstv282tplgdirbsb8jittf', '::1', 'curl/8.13.0', 'desktop', 'Unknown', 'Unknown', '', '', 'Local', '/gym.php', NULL, 1, NULL, '2026-02-11 16:03:27'),
(28, '1h297hemcct1v4ht0q4no8ci8n', '::1', 'curl/8.13.0', 'desktop', 'Unknown', 'Unknown', '', '', 'Local', '/conference.php', NULL, 1, NULL, '2026-02-11 16:03:30'),
(29, 'kts3rukk9d26dica7sviah9v94', '::1', 'curl/8.13.0', 'desktop', 'Unknown', 'Unknown', '', '', 'Local', '/booking.php', NULL, 1, NULL, '2026-02-11 16:03:32'),
(30, 'eic6lschcbtli2qsqdnv4865ag', '::1', 'curl/8.13.0', 'desktop', 'Unknown', 'Unknown', '', '', 'Local', '/gym.php', NULL, 1, NULL, '2026-02-11 16:05:47'),
(31, 'rnktsvanphc1i7r26vvefd98o2', '::1', 'curl/8.13.0', 'desktop', 'Unknown', 'Unknown', '', '', 'Local', '/conference.php', NULL, 1, NULL, '2026-02-11 16:05:49'),
(32, 'tv52nkashstosslr4p0tti3kq5', '::1', 'curl/8.13.0', 'desktop', 'Unknown', 'Unknown', '', '', 'Local', '/booking.php', NULL, 1, NULL, '2026-02-11 16:05:52'),
(33, '37q9vfut3duagv5pcdr7jgqh03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-11 21:05:30'),
(34, '76e1666348af858dd42192b7d8b97cbc', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/gym.php', NULL, 1, NULL, '2026-02-11 22:54:46'),
(35, '37q9vfut3duagv5pcdr7jgqh03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/conference.php', 'localhost', 'Local', '/gym.php', NULL, 0, NULL, '2026-02-11 22:55:10'),
(36, '37q9vfut3duagv5pcdr7jgqh03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/gym.php', NULL, 0, NULL, '2026-02-11 23:08:08'),
(37, 'mlbvh0l8tt342ce5nabtee1ute', '192.168.2.5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'http://192.168.2.13:8000/rooms-gallery.php', '192.168.2.13', 'Local', '/gym.php', NULL, 1, NULL, '2026-02-12 00:18:03'),
(38, 'boigkp6qcf5b7hpoacd2sh0cns', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/gym.php', NULL, 1, NULL, '2026-02-12 07:47:56'),
(39, 'boigkp6qcf5b7hpoacd2sh0cns', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/gym.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-12 07:48:00'),
(40, 'boigkp6qcf5b7hpoacd2sh0cns', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/gym.php', NULL, 0, NULL, '2026-02-12 08:28:15'),
(41, 'boigkp6qcf5b7hpoacd2sh0cns', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/gym.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-12 08:28:26'),
(42, 'boigkp6qcf5b7hpoacd2sh0cns', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/gym.php', NULL, 0, NULL, '2026-02-12 08:47:24'),
(43, 'boigkp6qcf5b7hpoacd2sh0cns', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/gym.php', NULL, 0, NULL, '2026-02-12 09:03:04'),
(44, 'boigkp6qcf5b7hpoacd2sh0cns', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/gym.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-12 09:03:08'),
(45, 'boigkp6qcf5b7hpoacd2sh0cns', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/gym.php', NULL, 0, NULL, '2026-02-12 09:16:20'),
(46, '11p51g550ut55d9k3c6r62f7cr', '::1', 'curl/8.13.0', 'desktop', 'Unknown', 'Unknown', '', '', 'Local', '/gym.php', NULL, 1, NULL, '2026-02-12 09:37:32'),
(47, 'u0edd2prbcnveb545k1dgtmqkp', '::1', 'curl/8.13.0', 'desktop', 'Unknown', 'Unknown', '', '', 'Local', '/booking.php', NULL, 1, NULL, '2026-02-12 09:39:39'),
(48, 'mqfkl5n4ir5amvtc8o9pqq490l', '::1', 'curl/8.13.0', 'desktop', 'Unknown', 'Unknown', '', '', 'Local', '/conference.php', NULL, 1, NULL, '2026-02-12 09:39:41'),
(49, 'pssjlfk2a09pqfh8l9m6rnos2n', '::1', 'curl/8.13.0', 'desktop', 'Unknown', 'Unknown', '', '', 'Local', '/booking-lookup.php', NULL, 1, NULL, '2026-02-12 09:39:42'),
(50, 'ug6fr58jukg7q51og4u4a5vi62', '::1', 'curl/8.13.0', 'desktop', 'Unknown', 'Unknown', '', '', 'Local', '/submit-review.php', NULL, 1, NULL, '2026-02-12 09:39:42'),
(51, '3i7o7oiebkhhu8cemk3lb27mm7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/gym.php', NULL, 1, NULL, '2026-02-12 11:50:20'),
(52, '3i7o7oiebkhhu8cemk3lb27mm7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/gym.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-12 11:50:25'),
(53, '3i7o7oiebkhhu8cemk3lb27mm7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/gym.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-12 11:56:42'),
(54, '3i7o7oiebkhhu8cemk3lb27mm7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/gym.php', NULL, 0, NULL, '2026-02-12 12:19:39'),
(55, '3i7o7oiebkhhu8cemk3lb27mm7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/gym.php', NULL, 0, NULL, '2026-02-12 14:22:14'),
(56, '3i7o7oiebkhhu8cemk3lb27mm7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/gym.php', NULL, 0, NULL, '2026-02-12 14:40:21'),
(57, 'ainu40jb7g8jpipm98p7q64v6d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/booking.php', NULL, 1, NULL, '2026-02-13 11:20:55'),
(58, '7c44707919b95977281b1459656e5525', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/gym.php', NULL, 1, NULL, '2026-02-13 17:26:05'),
(59, '7c44707919b95977281b1459656e5525', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/gym.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-13 17:26:37'),
(60, '7c44707919b95977281b1459656e5525', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/gym.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-13 17:33:04'),
(61, '7c44707919b95977281b1459656e5525', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-13 17:57:21'),
(62, 'e4bll24n4815p6c4evu0vngg77', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/gym.php', NULL, 1, NULL, '2026-02-13 18:01:55'),
(63, 'e4bll24n4815p6c4evu0vngg77', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/gym.php', NULL, 0, NULL, '2026-02-13 18:21:57'),
(64, '7c44707919b95977281b1459656e5525', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/events.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-13 18:30:37'),
(65, '7c44707919b95977281b1459656e5525', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/events.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-13 19:02:22'),
(66, '7c44707919b95977281b1459656e5525', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/conference.php', 'localhost', 'Local', '/gym.php', NULL, 0, NULL, '2026-02-13 19:02:36'),
(67, 'ef8be1a55adcbb7a94a4d61f8995f58c', '192.168.2.5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'http://192.168.2.18:8000/', '192.168.2.18', 'Local', '/conference.php', NULL, 1, NULL, '2026-02-13 19:15:31'),
(68, 'e094f9e4e454fc5da1373bd64d70347d', '192.168.2.5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'http://192.168.2.18:8000/', '192.168.2.18', 'Local', '/booking.php?check_in=2026-02-15&check_out=2026-02-17&guests=1', NULL, 1, NULL, '2026-02-15 23:21:21'),
(69, 'drqqccrjkafr08nhsufqg460td', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/booking.php', NULL, 1, NULL, '2026-02-17 10:42:35'),
(70, 'drqqccrjkafr08nhsufqg460td', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/gym.php', NULL, 0, NULL, '2026-02-17 11:14:33'),
(71, 'drqqccrjkafr08nhsufqg460td', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/gym.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-17 11:14:41'),
(72, 'drqqccrjkafr08nhsufqg460td', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/booking.php?check_in=2026-02-17&check_out=2026-02-19&guests=2&room_type=', NULL, 0, NULL, '2026-02-17 12:41:19'),
(73, 'drqqccrjkafr08nhsufqg460td', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php?check_in=2026-02-17&check_out=2026-02-19&guests=2&room_type=', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-17 12:43:48'),
(74, 'drqqccrjkafr08nhsufqg460td', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-17 13:35:44'),
(75, 'drqqccrjkafr08nhsufqg460td', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-17 14:07:53'),
(76, 'drqqccrjkafr08nhsufqg460td', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-17 21:03:17'),
(77, 'drqqccrjkafr08nhsufqg460td', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-17 21:03:32'),
(78, 's27pekk2s9np7i6t3c2mrr24p9', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/gym.php', 'localhost', 'Local', '/gym.php', NULL, 1, NULL, '2026-02-18 10:29:20'),
(79, 's27pekk2s9np7i6t3c2mrr24p9', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/conference.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-18 10:29:31'),
(80, 's27pekk2s9np7i6t3c2mrr24p9', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/gym.php', 'localhost', 'Local', '/gym.php', NULL, 0, NULL, '2026-02-18 11:48:31'),
(81, 'jdp02hfip1clvm2mtgtt6vv63d', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/booking.php', NULL, 1, NULL, '2026-02-18 22:17:29'),
(82, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/booking.php', NULL, 1, NULL, '2026-02-19 11:44:15'),
(83, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-19 11:50:49'),
(84, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-19 12:01:00'),
(85, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-19 13:27:21'),
(86, 'da2ggsdcrjishlole11qh7g60m', '192.168.2.5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'http://192.168.2.13:8000/', '192.168.2.13', 'Local', '/submit-review.php', NULL, 1, NULL, '2026-02-19 18:50:14'),
(87, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-19 22:24:30'),
(88, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php', 'localhost', 'Local', '/booking-confirmation.php?ref=LSH20264072', NULL, 0, NULL, '2026-02-19 22:25:48'),
(89, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/conference.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-20 10:27:23'),
(90, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/conference.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-20 10:28:35'),
(91, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/conference.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-20 10:52:49'),
(92, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/conference.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-20 11:29:53'),
(93, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/booking.php?check_in=2026-02-21&check_out=2026-02-22&guests=2&children=1&room_type=Superior+Suite', NULL, 0, NULL, '2026-02-20 14:23:15'),
(94, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/booking.php?check_in=2026-02-21&check_out=2026-02-22&guests=2&children=1&room_type=Superior+Suite', NULL, 0, NULL, '2026-02-20 14:34:58'),
(95, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php?check_in=2026-02-21&check_out=2026-02-22&guests=2&children=1&room_type=Superior+Suite', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-20 14:37:27'),
(96, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/booking.php?check_in=2026-02-22&check_out=2026-02-24&guests=5&children=1&room_type=VIP+Beach+Front+Villa', NULL, 0, NULL, '2026-02-20 14:40:29'),
(97, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php?check_in=2026-02-22&check_out=2026-02-24&guests=5&children=1&room_type=VIP+Beach+Front+Villa', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-20 14:41:51'),
(98, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php?check_in=2026-02-22&check_out=2026-02-24&guests=5&children=1&room_type=VIP+Beach+Front+Villa', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-20 14:44:39'),
(99, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-20 14:45:11'),
(100, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-20 15:08:07'),
(101, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-20 15:10:06'),
(102, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-20 15:10:33'),
(103, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-20 15:38:24'),
(104, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-20 15:40:47'),
(105, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php', 'localhost', 'Local', '/booking-confirmation.php?ref=LSH20262821', NULL, 0, NULL, '2026-02-20 15:41:58'),
(106, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-20 17:19:30'),
(107, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-20 17:25:27'),
(108, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-20 17:32:30'),
(109, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-20 17:41:11'),
(110, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-20 17:41:55'),
(111, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-20 17:51:36'),
(112, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-20 17:53:37'),
(113, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-20 18:02:57'),
(114, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php', 'localhost', 'Local', '/booking-confirmation.php?ref=LSH20261303', NULL, 0, NULL, '2026-02-20 18:05:55'),
(115, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/booking.php?check_in=2026-02-23&check_out=2026-02-25&guests=2&children=0&room_type=VIP+Beach+Front+Villa', NULL, 0, NULL, '2026-02-21 11:50:01'),
(116, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php?check_in=2026-02-23&check_out=2026-02-25&guests=2&children=0&room_type=VIP+Beach+Front+Villa', 'localhost', 'Local', '/booking-confirmation.php?ref=LSH20263047', NULL, 0, NULL, '2026-02-21 11:50:43'),
(117, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/booking.php?check_in=2026-02-22&check_out=2026-02-23&guests=5&children=0&room_type=VIP+Beach+Front+Villa', NULL, 0, NULL, '2026-02-21 15:53:44'),
(118, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/booking.php?check_in=2026-02-23&check_out=2026-02-24&guests=5&children=0&room_type=VIP+Beach+Front+Villa', NULL, 0, NULL, '2026-02-21 16:06:29'),
(119, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php?check_in=2026-02-23&check_out=2026-02-24&guests=5&children=0&room_type=VIP+Beach+Front+Villa', 'localhost', 'Local', '/booking-confirmation.php?ref=LSH20268184', NULL, 0, NULL, '2026-02-21 16:11:54'),
(120, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', '', '', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-22 16:04:09'),
(121, '9foud31tejj0q4kekpgfu0aj9e', '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'https://promanaged-it.com/rosalyns/', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '/rosalyns/submit-review.php', NULL, 1, NULL, '2026-02-22 18:28:19'),
(122, '9foud31tejj0q4kekpgfu0aj9e', '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'https://promanaged-it.com/rosalyns/', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '/rosalyns/submit-review.php/index.php', NULL, 0, NULL, '2026-02-22 18:28:40'),
(123, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/room.php?room=executive-villa', 'localhost', 'Local', '/booking.php?room_id=1', NULL, 0, NULL, '2026-02-22 18:30:59'),
(124, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/room.php?room=executive-villa', 'localhost', 'Local', '/booking.php?room_id=1', NULL, 0, NULL, '2026-02-22 18:43:50'),
(125, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php?room_id=1', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-22 18:44:29'),
(126, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-22 18:45:11'),
(127, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/room.php?room=executive-villa', 'localhost', 'Local', '/booking.php?room_id=1', NULL, 0, NULL, '2026-02-22 18:48:21'),
(128, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/room.php?room=executive-villa', 'localhost', 'Local', '/booking.php?room_id=1', NULL, 0, NULL, '2026-02-22 18:53:37'),
(129, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/room.php?room=executive-villa', 'localhost', 'Local', '/booking.php?room_id=1', NULL, 0, NULL, '2026-02-22 19:05:21'),
(130, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/room.php?room=executive-villa', 'localhost', 'Local', '/booking.php?room_id=1', NULL, 0, NULL, '2026-02-22 19:11:35'),
(131, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php?room_id=1', 'localhost', 'Local', '/booking.php/booking.php', NULL, 0, NULL, '2026-02-22 19:12:53'),
(132, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php/booking.php', 'localhost', 'Local', '/booking.php/css/base/critical.css', NULL, 0, NULL, '2026-02-22 19:12:56'),
(133, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php/booking.php', 'localhost', 'Local', '/booking.php/css/main.css', NULL, 0, NULL, '2026-02-22 19:12:59'),
(134, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php/booking.php', 'localhost', 'Local', '/booking.php/js/modal.js', NULL, 0, NULL, '2026-02-22 19:13:03'),
(135, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php/booking.php', 'localhost', 'Local', '/booking.php/js/navigation-unified.js', NULL, 0, NULL, '2026-02-22 19:13:06'),
(136, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php/booking.php', 'localhost', 'Local', '/booking.php/js/scroll-lazy-animations.js', NULL, 0, NULL, '2026-02-22 19:13:09'),
(137, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php/booking.php', 'localhost', 'Local', '/booking.php/js/main.js', NULL, 0, NULL, '2026-02-22 19:13:12'),
(138, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php/booking.php', 'localhost', 'Local', '/booking.php/js/page-transitions.js', NULL, 0, NULL, '2026-02-22 19:13:15'),
(139, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/booking.php/booking.php', 'localhost', 'Local', '/booking.php/css/main.css', NULL, 0, NULL, '2026-02-22 19:13:17'),
(140, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-22 19:19:11'),
(141, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-22 19:33:20'),
(142, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-22 19:41:20'),
(143, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-22 19:47:13'),
(144, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-22 19:53:46'),
(145, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/room.php?room=executive-villa', 'localhost', 'Local', '/booking.php?room_id=1', NULL, 0, NULL, '2026-02-22 19:54:50'),
(146, '8jfsok15e608t6hj54fj46qr74', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/room.php?room=executive-villa', 'localhost', 'Local', '/booking.php?room_id=1', NULL, 0, NULL, '2026-02-22 21:41:37'),
(147, '73v6u0p31n9i8vji7p054jngmu', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'https://promanaged-it.com/rosalyns/rooms-gallery.php', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '/rosalyns/booking.php', NULL, 1, NULL, '2026-02-22 22:38:03'),
(148, '73v6u0p31n9i8vji7p054jngmu', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'https://promanaged-it.com/rosalyns/', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '/rosalyns//booking.php?check_in=2026-02-24&check_out=2026-02-26&guests=2&room_type=Superior+Suite', NULL, 0, NULL, '2026-02-22 22:41:06'),
(149, '73v6u0p31n9i8vji7p054jngmu', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'https://promanaged-it.com/rosalyns//booking.php?check_in=2026-02-24&check_out=2026-02-26&guests=2&room_type=Superior+Suite', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '/rosalyns/booking.php', NULL, 0, NULL, '2026-02-22 22:44:03'),
(150, '73v6u0p31n9i8vji7p054jngmu', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'https://promanaged-it.com/rosalyns/booking.php', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '/rosalyns/booking-confirmation.php?ref=LSH20261303', NULL, 0, NULL, '2026-02-22 22:44:20'),
(151, '7vd27dri4cm4dqjaa2b1fik6gt', '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'https://promanaged-it.com/rosalyns/', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '/rosalyns/submit-review.php', NULL, 1, NULL, '2026-02-23 01:14:33'),
(152, '73v6u0p31n9i8vji7p054jngmu', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'https://promanaged-it.com/rosalyns/booking.php', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '/rosalyns/booking-confirmation.php?ref=LSH20261303', NULL, 1, NULL, '2026-02-23 09:54:46'),
(153, '73v6u0p31n9i8vji7p054jngmu', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'https://promanaged-it.com/rosalyns/', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '/rosalyns/contact-us.php', NULL, 0, NULL, '2026-02-23 09:59:01'),
(154, '7ac14nsd90a9j71g8ks9cecbp5', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', '', '', 'Local', '/contact-us.php', NULL, 1, NULL, '2026-02-23 22:48:33'),
(155, '7ac14nsd90a9j71g8ks9cecbp5', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', '', '', 'Local', '/contact-us.php', NULL, 0, NULL, '2026-02-23 22:48:42');
INSERT INTO `site_visitors` (`id`, `session_id`, `ip_address`, `user_agent`, `device_type`, `browser`, `os`, `referrer`, `referrer_domain`, `country`, `page_url`, `page_title`, `is_first_visit`, `visit_duration`, `created_at`) VALUES
(156, '7ac14nsd90a9j71g8ks9cecbp5', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', '', '', 'Local', '/contact-us.php', NULL, 0, NULL, '2026-02-23 22:54:42'),
(157, '7ac14nsd90a9j71g8ks9cecbp5', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/guest-services.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-23 23:46:40'),
(158, '7ac14nsd90a9j71g8ks9cecbp5', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/guest-services.php', 'localhost', 'Local', '/contact-us.php', NULL, 0, NULL, '2026-02-23 23:49:30'),
(159, 'tpvp8bd9h51rf9eoajjukren5j', '192.168.2.5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'http://192.168.2.13:8000/guest-services.php', '192.168.2.13', 'Local', '/booking.php', NULL, 1, NULL, '2026-02-24 00:10:58'),
(160, 'tpvp8bd9h51rf9eoajjukren5j', '192.168.2.5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'http://192.168.2.13:8000/booking.php', '192.168.2.13', 'Local', '/booking.php', NULL, 0, NULL, '2026-02-24 00:12:27'),
(161, '7hogg49ajqk5s0d062vh7nk1v7', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/submit-review.php', NULL, 1, NULL, '2026-02-24 18:41:11'),
(162, '7hogg49ajqk5s0d062vh7nk1v7', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/submit-review.php', NULL, 0, NULL, '2026-02-24 18:44:52'),
(163, '7hogg49ajqk5s0d062vh7nk1v7', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/submit-review.php', NULL, 0, NULL, '2026-02-24 18:47:10'),
(164, '7hogg49ajqk5s0d062vh7nk1v7', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/submit-review.php', NULL, 0, NULL, '2026-02-24 18:50:20'),
(165, '7hogg49ajqk5s0d062vh7nk1v7', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/submit-review.php', NULL, 0, NULL, '2026-02-24 18:51:43'),
(166, '7hogg49ajqk5s0d062vh7nk1v7', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/submit-review.php', NULL, 0, NULL, '2026-02-24 19:06:08'),
(167, '8e7l51h4bcug7u1de0ci7f0p5v', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/conference.php', NULL, 1, NULL, '2026-02-25 13:47:44'),
(168, '8e7l51h4bcug7u1de0ci7f0p5v', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-25 15:13:06'),
(169, '8e7l51h4bcug7u1de0ci7f0p5v', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-25 15:16:40'),
(170, 'tp6sgdl8v97roq377gt79ltkli', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/contact-us.php', NULL, 1, NULL, '2026-02-26 17:25:26'),
(171, 'tp6sgdl8v97roq377gt79ltkli', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/contact-us.php', NULL, 0, NULL, '2026-02-26 17:35:51'),
(172, 'tp6sgdl8v97roq377gt79ltkli', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/contact-us.php', NULL, 0, NULL, '2026-02-26 17:39:01'),
(173, 'tp6sgdl8v97roq377gt79ltkli', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/rooms-gallery.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-26 23:56:27'),
(174, 's2l6js4f7d9kejlbqi2a3sdn6c', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', '', '', 'Local', '/conference.php', NULL, 1, NULL, '2026-02-27 00:18:13'),
(175, '3rsbq26n4unf1v2u4v8hpiti8m', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/', 'localhost', 'Local', '/contact-us.php', NULL, 1, NULL, '2026-02-27 20:10:12'),
(176, 'ctkrmts05gu1ct53q59bions2q', '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'https://promanaged-it.com/rosalyns/', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '/rosalyns//booking.php?check_in=2026-02-28&check_out=2026-03-03&guests=2&children=0&room_type=VIP+Beach+Front+Villa', NULL, 1, NULL, '2026-02-27 20:16:13'),
(177, 'ctkrmts05gu1ct53q59bions2q', '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'https://promanaged-it.com/rosalyns//booking.php?check_in=2026-02-28&check_out=2026-03-03&guests=2&children=0&room_type=VIP+Beach+Front+Villa', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '/rosalyns/booking.php', NULL, 0, NULL, '2026-02-27 20:16:34'),
(178, 'vbj425n30pt7pe4eejrqaro66q', '142.250.32.34', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', 'mobile', 'Chrome', 'Android', '', '', 'Mountain View, California, United States', '/rosalyns/booking.php', NULL, 1, NULL, '2026-02-27 20:16:36'),
(179, 'ctkrmts05gu1ct53q59bions2q', '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'https://promanaged-it.com/rosalyns/', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '/rosalyns//booking.php?check_in=2026-03-12&check_out=2026-03-20&guests=2&children=0&room_type=VIP+Beach+Front+Villa', NULL, 1, NULL, '2026-02-28 10:03:02'),
(180, 'ctkrmts05gu1ct53q59bions2q', '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'https://promanaged-it.com/rosalyns//booking.php?check_in=2026-03-12&check_out=2026-03-20&guests=2&children=0&room_type=VIP+Beach+Front+Villa', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '/rosalyns/booking-confirmation.php?ref=LSH20264588', NULL, 0, NULL, '2026-02-28 10:03:23'),
(181, '7t7a025cikg3ql8t4cnck5at3g', '142.250.32.34', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', 'mobile', 'Chrome', 'Android', '', '', 'Mountain View, California, United States', '/rosalyns/booking-confirmation.php?ref=LSH20264588', NULL, 1, NULL, '2026-02-28 10:03:25'),
(182, '3rsbq26n4unf1v2u4v8hpiti8m', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/restaurant.php', 'localhost', 'Local', '/conference.php', NULL, 0, NULL, '2026-02-28 16:41:08'),
(183, '3rsbq26n4unf1v2u4v8hpiti8m', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'http://localhost:8000/index.php', 'localhost', 'Local', '/contact-us.php', NULL, 0, NULL, '2026-02-28 16:41:55'),
(184, '9v2kqg1lghia5jd7nnme5sst9v', '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'https://promanaged-it.com/rosalyns/', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '/rosalyns//booking.php?check_in=2026-03-14&check_out=2026-03-15&guests=2&room_type=Superior+Suite', NULL, 1, NULL, '2026-02-28 16:49:16'),
(185, '9v2kqg1lghia5jd7nnme5sst9v', '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'https://promanaged-it.com/rosalyns//booking.php?check_in=2026-03-14&check_out=2026-03-15&guests=2&room_type=Superior+Suite', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '/rosalyns/booking-confirmation.php?ref=LSH20266943', NULL, 0, NULL, '2026-02-28 16:49:43'),
(186, '9v2kqg1lghia5jd7nnme5sst9v', '51.37.179.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android', 'https://promanaged-it.com/rosalyns/events.php', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '/rosalyns/conference.php', NULL, 0, NULL, '2026-02-28 16:51:27'),
(187, 'u8vijpmr4fcvuhtde8j68p33dr', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'https://promanaged-it.com/rosalyns/restaurant.php', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '/rosalyns/conference.php', NULL, 1, NULL, '2026-03-01 20:55:36'),
(188, 'u8vijpmr4fcvuhtde8j68p33dr', '51.37.179.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows 10/11', 'https://promanaged-it.com/rosalyns/events.php', 'promanaged-it.com', 'Dublin, Leinster, Ireland', '/rosalyns/conference.php', NULL, 0, NULL, '2026-03-01 20:55:54');

-- --------------------------------------------------------

--
-- Table structure for table `tentative_booking_log`
--

CREATE TABLE `tentative_booking_log` (
  `id` int UNSIGNED NOT NULL,
  `booking_id` int UNSIGNED NOT NULL,
  `action` enum('created','extended','reminder_sent','converted','expired','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `previous_expires_at` datetime DEFAULT NULL,
  `new_expires_at` datetime DEFAULT NULL,
  `action_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `performed_by` int UNSIGNED DEFAULT NULL COMMENT 'Admin user ID, or NULL for system',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for tentative booking actions';

--
-- Dumping data for table `tentative_booking_log`
--

INSERT INTO `tentative_booking_log` (`id`, `booking_id`, `action`, `previous_expires_at`, `new_expires_at`, `action_reason`, `performed_by`, `created_at`) VALUES
(1, 26, '', NULL, '2026-02-07 14:06:06', NULL, 2, '2026-02-05 14:06:06'),
(2, 26, '', NULL, '2026-02-07 17:00:37', NULL, 2, '2026-02-05 17:00:37'),
(3, 26, '', NULL, '2026-02-07 17:01:46', NULL, 2, '2026-02-05 17:01:46'),
(4, 26, '', NULL, '2026-02-07 17:02:29', NULL, 2, '2026-02-05 17:02:29'),
(5, 26, 'converted', NULL, NULL, 'Converted from tentative to confirmed by admin', 2, '2026-02-06 15:28:30'),
(6, 29, 'converted', NULL, NULL, 'Converted from tentative to confirmed by admin', 1, '2026-02-10 11:45:36');

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` int NOT NULL,
  `guest_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guest_location` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` int DEFAULT '5',
  `testimonial_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `stay_date` date DEFAULT NULL,
  `guest_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT '0',
  `is_approved` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `testimonials`
--

INSERT INTO `testimonials` (`id`, `guest_name`, `guest_location`, `rating`, `testimonial_text`, `stay_date`, `guest_image`, `is_featured`, `is_approved`, `display_order`, `created_at`) VALUES
(1, 'Sarah Johnson', 'London, UK', 4, 'Nice hotel with friendly staff. Rooms were clean and comfortable. Good value for money. Would stay again.', '2025-12-15', NULL, 1, 1, 1, '2026-01-19 20:22:49'),
(2, 'Michael Chen', 'Singapore', 5, 'Pleasant stay in a good location. Staff was helpful and the rooms were tidy. Simple but comfortable.', '2025-11-20', NULL, 1, 1, 2, '2026-01-19 20:22:49'),
(3, 'Emma Williams', 'New York, USA', 5, 'Good budget hotel option. Clean rooms, decent food, and friendly service. Met our expectations for a 2-star hotel.', '2026-01-05', NULL, 1, 1, 3, '2026-01-19 20:22:49');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `permission_key` varchar(50) NOT NULL,
  `is_granted` tinyint(1) DEFAULT '1',
  `granted_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_permissions`
--

INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`, `is_granted`, `granted_by`, `created_at`, `updated_at`) VALUES
(1, 2, 'dashboard', 1, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(2, 2, 'bookings', 0, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(3, 2, 'calendar', 0, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(4, 2, 'blocked_dates', 0, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(5, 2, 'rooms', 0, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(6, 2, 'gallery', 0, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(7, 2, 'conference', 0, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(8, 2, 'gym', 0, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(9, 2, 'menu', 0, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(10, 2, 'events', 0, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(11, 2, 'reviews', 0, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(12, 2, 'accounting', 0, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(13, 2, 'payments', 0, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(14, 2, 'invoices', 0, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(15, 2, 'payment_add', 0, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(16, 2, 'reports', 0, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(18, 2, 'section_headers', 0, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(19, 2, 'booking_settings', 0, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(20, 2, 'cache', 0, 1, '2026-02-07 13:34:34', '2026-02-07 13:34:34'),
(21, 1, 'dashboard', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(22, 1, 'bookings', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(23, 1, 'calendar', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(24, 1, 'blocked_dates', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(25, 1, 'rooms', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(26, 1, 'gallery', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(27, 1, 'conference', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(28, 1, 'gym', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(29, 1, 'menu', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(30, 1, 'events', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(31, 1, 'reviews', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(32, 1, 'accounting', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(33, 1, 'payments', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(34, 1, 'invoices', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(35, 1, 'payment_add', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(36, 1, 'reports', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(37, 1, 'section_headers', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(38, 1, 'booking_settings', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(39, 2, 'pages', 0, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(40, 1, 'pages', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(41, 1, 'cache', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(42, 2, 'user_management', 0, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(43, 1, 'user_management', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(44, 2, 'media_management', 0, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(45, 1, 'media_management', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(46, 2, 'media_create', 0, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(47, 1, 'media_create', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(48, 2, 'media_edit', 0, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(49, 1, 'media_edit', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(50, 2, 'media_delete', 0, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(51, 1, 'media_delete', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(52, 2, 'user_create', 0, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(53, 1, 'user_create', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(54, 2, 'user_edit', 0, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(55, 1, 'user_edit', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(56, 2, 'user_delete', 0, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(57, 1, 'user_delete', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(58, 2, 'user_permissions', 0, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13'),
(59, 1, 'user_permissions', 1, 1, '2026-02-16 10:03:13', '2026-02-16 10:03:13');

-- --------------------------------------------------------

--
-- Table structure for table `v_active_tentative_bookings`
--

CREATE TABLE `v_active_tentative_bookings` (
  `id` int UNSIGNED DEFAULT NULL,
  `booking_reference` varchar(20) DEFAULT NULL,
  `room_id` int UNSIGNED DEFAULT NULL,
  `room_name` varchar(100) DEFAULT NULL,
  `room_slug` varchar(100) DEFAULT NULL,
  `price_per_night` decimal(10,2) DEFAULT NULL,
  `guest_name` varchar(255) DEFAULT NULL,
  `guest_email` varchar(255) DEFAULT NULL,
  `guest_phone` varchar(50) DEFAULT NULL,
  `check_in_date` date DEFAULT NULL,
  `check_out_date` date DEFAULT NULL,
  `number_of_nights` int DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','tentative','confirmed','checked-in','checked-out','cancelled','expired','no-show') DEFAULT NULL,
  `is_tentative` tinyint(1) DEFAULT NULL,
  `tentative_expires_at` datetime DEFAULT NULL,
  `deposit_required` tinyint(1) DEFAULT NULL,
  `deposit_amount` decimal(10,2) DEFAULT NULL,
  `deposit_paid` tinyint(1) DEFAULT NULL,
  `reminder_sent` tinyint(1) DEFAULT NULL,
  `reminder_sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `tentative_notes` text,
  `hours_until_expiration` bigint DEFAULT NULL,
  `expiration_status` varchar(8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_media_by_page`
-- (See below for the actual view)
--
CREATE TABLE `v_media_by_page` (
`id` int unsigned
,`title` varchar(180)
,`description` varchar(255)
,`media_type` enum('image','video')
,`source_type` enum('upload','url')
,`media_url` varchar(500)
,`mime_type` varchar(120)
,`alt_text` varchar(255)
,`caption` varchar(255)
,`placement_key` varchar(120)
,`page_slug` varchar(100)
,`section_key` varchar(100)
,`entity_type` varchar(50)
,`entity_id` int unsigned
,`is_active` tinyint(1)
,`display_order` int
,`legacy_source` varchar(50)
,`legacy_id` int unsigned
,`created_by` int unsigned
,`created_at` timestamp
,`updated_at` timestamp
,`source_columns` text
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_room_media`
-- (See below for the actual view)
--
CREATE TABLE `v_room_media` (
`id` int unsigned
,`title` varchar(180)
,`description` varchar(255)
,`media_type` enum('image','video')
,`source_type` enum('upload','url')
,`media_url` varchar(500)
,`mime_type` varchar(120)
,`alt_text` varchar(255)
,`caption` varchar(255)
,`placement_key` varchar(120)
,`page_slug` varchar(100)
,`section_key` varchar(100)
,`entity_type` varchar(50)
,`entity_id` int unsigned
,`is_active` tinyint(1)
,`display_order` int
,`legacy_source` varchar(50)
,`legacy_id` int unsigned
,`created_by` int unsigned
,`created_at` timestamp
,`updated_at` timestamp
,`room_id` varchar(64)
);

-- --------------------------------------------------------

--
-- Table structure for table `v_tentative_booking_stats`
--

CREATE TABLE `v_tentative_booking_stats` (
  `total_tentative_bookings` bigint DEFAULT NULL,
  `active_count` decimal(23,0) DEFAULT NULL,
  `warning_count` decimal(23,0) DEFAULT NULL,
  `critical_count` decimal(23,0) DEFAULT NULL,
  `expired_count` decimal(23,0) DEFAULT NULL,
  `deposits_required_count` decimal(23,0) DEFAULT NULL,
  `deposits_paid_count` decimal(23,0) DEFAULT NULL,
  `total_deposits_amount` decimal(32,2) DEFAULT NULL,
  `reminders_sent_count` decimal(23,0) DEFAULT NULL,
  `total_value` decimal(32,2) DEFAULT NULL,
  `average_booking_value` decimal(14,6) DEFAULT NULL,
  `unique_rooms_booked` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `welcome`
--

CREATE TABLE `welcome` (
  `id` int UNSIGNED NOT NULL,
  `section_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'hero, scroll, welcome, concept, rooms_line, discover, facility, cta, footer, progress',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `image_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_text` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `welcome`
--

INSERT INTO `welcome` (`id`, `section_key`, `title`, `subtitle`, `body_text`, `image_path`, `link_url`, `link_text`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'hero', 'Rosalyn\'s Beach Hotel', NULL, NULL, 'https://www.rosalynsbeachhotel.com/static/media/WhatsApp%20Image%202024-10-02%20at%2009.32.56%20(18).35e879243a9ccdd6669e.jpeg', NULL, NULL, 1, 1, '2026-02-13 19:01:46', '2026-02-15 22:05:37'),
(2, 'scroll', NULL, 'Scroll', NULL, NULL, NULL, NULL, 2, 1, '2026-02-13 19:01:46', '2026-02-13 19:01:46'),
(3, 'welcome', 'Welcome', NULL, 'Design\nDevelopment\nDestination', NULL, NULL, NULL, 3, 1, '2026-02-13 19:01:46', '2026-02-13 19:01:46'),
(4, 'concept', 'Concept', NULL, NULL, NULL, 'index.php', 'Concept', 4, 1, '2026-02-13 19:01:46', '2026-02-13 19:01:46'),
(5, 'rooms_line', NULL, NULL, 'Superior Twin Room / Minimal Double Room / Standard Triple Room', NULL, 'rooms-gallery.php', NULL, 5, 1, '2026-02-13 19:01:46', '2026-02-13 19:01:46'),
(6, 'discover', 'Discover', NULL, NULL, NULL, NULL, NULL, 6, 1, '2026-02-13 19:01:46', '2026-02-13 19:01:46'),
(7, 'rooms', 'Rooms', NULL, NULL, NULL, 'rooms-gallery.php', 'Rooms', 7, 1, '2026-02-13 19:01:46', '2026-02-13 19:01:46'),
(8, 'facility', 'Cafe & Bar', NULL, NULL, NULL, 'restaurant.php', NULL, 8, 1, '2026-02-13 19:01:46', '2026-02-13 19:01:46'),
(9, 'facility', 'Kitchen', NULL, NULL, NULL, 'restaurant.php', NULL, 9, 1, '2026-02-13 19:01:46', '2026-02-13 19:01:46'),
(10, 'facility', 'Gallery', NULL, NULL, NULL, 'index.php#gallery', NULL, 10, 1, '2026-02-13 19:01:46', '2026-02-13 19:01:46'),
(11, 'cta', NULL, NULL, 'Join the collective', NULL, 'booking.php', 'Book Now', 11, 1, '2026-02-13 19:01:46', '2026-02-13 19:01:46'),
(12, 'footer', NULL, NULL, NULL, NULL, NULL, NULL, 12, 1, '2026-02-13 19:01:46', '2026-02-13 19:01:46'),
(13, 'progress', NULL, NULL, '0\n66\n100\nHold', NULL, NULL, NULL, 13, 1, '2026-02-13 19:01:46', '2026-02-13 19:01:46');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `about_us`
--
ALTER TABLE `about_us`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_section_type` (`section_type`),
  ADD KEY `idx_display_order` (`display_order`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `api_keys`
--
ALTER TABLE `api_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `api_key` (`api_key`),
  ADD KEY `idx_client_name` (`client_name`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_last_used` (`last_used_at`);

--
-- Indexes for table `api_usage_logs`
--
ALTER TABLE `api_usage_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_api_key_id` (`api_key_id`),
  ADD KEY `idx_endpoint` (`endpoint`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `blocked_dates`
--
ALTER TABLE `blocked_dates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_id` (`room_id`),
  ADD KEY `idx_block_date` (`block_date`),
  ADD KEY `idx_room_date` (`room_id`,`block_date`),
  ADD KEY `idx_blocked_dates_block_type` (`block_type`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_reference` (`booking_reference`),
  ADD KEY `idx_booking_ref` (`booking_reference`),
  ADD KEY `idx_room_id` (`room_id`),
  ADD KEY `idx_guest_email` (`guest_email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dates` (`check_in_date`,`check_out_date`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_tentative_bookings` (`status`,`expires_at`),
  ADD KEY `idx_tentative_expires` (`tentative_expires_at`,`status`),
  ADD KEY `idx_is_tentative` (`is_tentative`,`status`),
  ADD KEY `idx_individual_room_id` (`individual_room_id`),
  ADD KEY `idx_bookings_final_invoice` (`final_invoice_generated`,`final_invoice_sent_at`);

--
-- Indexes for table `booking_charges`
--
ALTER TABLE `booking_charges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_charges_booking_id` (`booking_id`),
  ADD KEY `idx_booking_charges_type` (`charge_type`),
  ADD KEY `idx_booking_charges_source` (`source_item_id`),
  ADD KEY `idx_booking_charges_voided` (`voided`),
  ADD KEY `idx_booking_charges_posted_at` (`posted_at`);

--
-- Indexes for table `booking_date_adjustments`
--
ALTER TABLE `booking_date_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_date_adjustments_booking_id` (`booking_id`),
  ADD KEY `idx_booking_date_adjustments_reference` (`booking_reference`),
  ADD KEY `idx_booking_date_adjustments_timestamp` (`adjustment_timestamp`),
  ADD KEY `idx_booking_date_adjustments_adjusted_by` (`adjusted_by`);

--
-- Indexes for table `booking_email_templates`
--
ALTER TABLE `booking_email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_template_key` (`template_key`);

--
-- Indexes for table `booking_notes`
--
ALTER TABLE `booking_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `booking_payments`
--
ALTER TABLE `booking_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_booking_reference` (`booking_reference`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_payment_date` (`payment_date`);

--
-- Indexes for table `booking_timeline_logs`
--
ALTER TABLE `booking_timeline_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_booking_reference` (`booking_reference`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_performed_by` (`performed_by_type`,`performed_by_id`);

--
-- Indexes for table `cancellation_log`
--
ALTER TABLE `cancellation_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_booking_reference` (`booking_reference`),
  ADD KEY `idx_cancellation_date` (`cancellation_date`),
  ADD KEY `idx_booking_type` (`booking_type`);

--
-- Indexes for table `conference_bookings`
--
ALTER TABLE `conference_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_reference` (`booking_reference`),
  ADD KEY `idx_event_date` (`event_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_contact_email` (`contact_email`);

--
-- Indexes for table `conference_inquiries`
--
ALTER TABLE `conference_inquiries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `inquiry_reference` (`inquiry_reference`),
  ADD KEY `idx_conference_inquiry_date` (`event_date`,`status`);

--
-- Indexes for table `conference_rooms`
--
ALTER TABLE `conference_rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conference_room_active` (`is_active`,`display_order`);

--
-- Indexes for table `contact_inquiries`
--
ALTER TABLE `contact_inquiries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_reference` (`reference_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `cookie_consent_log`
--
ALTER TABLE `cookie_consent_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `drink_menu`
--
ALTER TABLE `drink_menu`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category` (`category`),
  ADD KEY `is_available` (`is_available`),
  ADD KEY `is_featured` (`is_featured`);

--
-- Indexes for table `email_settings`
--
ALTER TABLE `email_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `setting_group` (`setting_group`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_events_date` (`event_date`,`is_active`),
  ADD KEY `idx_events_featured` (`is_featured`,`is_active`);

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_facilities_featured` (`is_featured`,`is_active`);

--
-- Indexes for table `food_menu`
--
ALTER TABLE `food_menu`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category` (`category`),
  ADD KEY `is_available` (`is_available`),
  ADD KEY `is_featured` (`is_featured`);

--
-- Indexes for table `footer_links`
--
ALTER TABLE `footer_links`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gallery`
--
ALTER TABLE `gallery`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `guest_services`
--
ALTER TABLE `guest_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_service_key` (`service_key`),
  ADD KEY `idx_active_order` (`is_active`,`display_order`);

--
-- Indexes for table `gym_classes`
--
ALTER TABLE `gym_classes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gym_content`
--
ALTER TABLE `gym_content`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gym_facilities`
--
ALTER TABLE `gym_facilities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gym_features`
--
ALTER TABLE `gym_features`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gym_inquiries`
--
ALTER TABLE `gym_inquiries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ref_number` (`reference_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `gym_packages`
--
ALTER TABLE `gym_packages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hotel_gallery`
--
ALTER TABLE `hotel_gallery`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active_order` (`is_active`,`display_order`);

--
-- Indexes for table `housekeeping_assignments`
--
ALTER TABLE `housekeeping_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room` (`individual_room_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_due` (`due_date`),
  ADD KEY `idx_housekeeping_priority` (`priority`),
  ADD KEY `idx_housekeeping_status_priority` (`status`,`priority`),
  ADD KEY `idx_housekeeping_assigned_to` (`assigned_to`),
  ADD KEY `idx_housekeeping_due_date` (`due_date`),
  ADD KEY `idx_housekeeping_type` (`assignment_type`),
  ADD KEY `idx_housekeeping_linked_booking` (`linked_booking_id`),
  ADD KEY `fk_housekeeping_verified_by` (`verified_by`);

--
-- Indexes for table `housekeeping_audit_log`
--
ALTER TABLE `housekeeping_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_housekeeping_audit_assignment` (`assignment_id`),
  ADD KEY `idx_housekeeping_audit_action` (`action`),
  ADD KEY `idx_housekeeping_audit_performed_by` (`performed_by`),
  ADD KEY `idx_housekeeping_audit_performed_at` (`performed_at`);

--
-- Indexes for table `individual_rooms`
--
ALTER TABLE `individual_rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_room_number` (`room_number`),
  ADD KEY `idx_room_type_id` (`room_type_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `individual_room_amenities`
--
ALTER TABLE `individual_room_amenities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room` (`individual_room_id`),
  ADD KEY `idx_key` (`amenity_key`);

--
-- Indexes for table `individual_room_blocked_dates`
--
ALTER TABLE `individual_room_blocked_dates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_individual_room_date` (`individual_room_id`,`block_date`),
  ADD KEY `idx_individual_room_block_date` (`block_date`),
  ADD KEY `idx_individual_room_block_type` (`block_type`),
  ADD KEY `idx_individual_room_blocked_by` (`blocked_by`);

--
-- Indexes for table `individual_room_photos`
--
ALTER TABLE `individual_room_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room` (`individual_room_id`),
  ADD KEY `idx_primary` (`is_primary`);

--
-- Indexes for table `individual_room_pictures_archive`
--
ALTER TABLE `individual_room_pictures_archive`
  ADD PRIMARY KEY (`id`),
  ADD KEY `individual_room_id` (`individual_room_id`),
  ADD KEY `picture_type` (`picture_type`),
  ADD KEY `display_order` (`display_order`);

--
-- Indexes for table `maintenance_audit_log`
--
ALTER TABLE `maintenance_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_maintenance_audit_maintenance` (`maintenance_id`),
  ADD KEY `idx_maintenance_audit_action` (`action`),
  ADD KEY `idx_maintenance_audit_performed_by` (`performed_by`),
  ADD KEY `idx_maintenance_audit_performed_at` (`performed_at`);

--
-- Indexes for table `managed_media_catalog`
--
ALTER TABLE `managed_media_catalog`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_media_catalog_legacy` (`legacy_source`,`legacy_id`),
  ADD KEY `idx_media_catalog_active_order` (`is_active`,`display_order`,`id`),
  ADD KEY `idx_media_catalog_placement` (`placement_key`),
  ADD KEY `idx_media_catalog_page_section` (`page_slug`,`section_key`);

--
-- Indexes for table `managed_media_groups_archive`
--
ALTER TABLE `managed_media_groups_archive`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_managed_media_group_key` (`group_key`),
  ADD KEY `idx_group_context` (`page_slug`,`section_key`,`entity_type`,`entity_id`),
  ADD KEY `idx_group_active_order` (`is_active`,`display_order`);

--
-- Indexes for table `managed_media_items_archive`
--
ALTER TABLE `managed_media_items_archive`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_media_group_order` (`group_id`,`is_active`,`display_order`),
  ADD KEY `idx_media_type` (`media_type`),
  ADD KEY `fk_managed_media_created_by` (`created_by`);

--
-- Indexes for table `managed_media_links`
--
ALTER TABLE `managed_media_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_media_link_source` (`source_table`,`source_record_id`,`source_column`,`source_context`),
  ADD KEY `idx_media_link_catalog` (`media_catalog_id`),
  ADD KEY `idx_media_link_lookup` (`source_table`,`source_record_id`,`is_active`),
  ADD KEY `idx_media_link_context` (`page_slug`,`section_key`,`entity_type`,`entity_id`);

--
-- Indexes for table `menu_categories`
--
ALTER TABLE `menu_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `migration_log`
--
ALTER TABLE `migration_log`
  ADD PRIMARY KEY (`migration_id`),
  ADD UNIQUE KEY `idx_migration_name` (`migration_name`),
  ADD KEY `idx_migration_date` (`migration_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `page_heroes`
--
ALTER TABLE `page_heroes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_slug` (`page_slug`),
  ADD UNIQUE KEY `page_url` (`page_url`),
  ADD KEY `idx_page_heroes_active_order` (`is_active`,`display_order`);

--
-- Indexes for table `page_loaders`
--
ALTER TABLE `page_loaders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_slug` (`page_slug`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_reference` (`payment_reference`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `idx_booking_type_id` (`booking_type`,`booking_id`),
  ADD KEY `idx_payment_date` (`payment_date`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_recorded_by` (`recorded_by`),
  ADD KEY `idx_conference_id` (`conference_id`),
  ADD KEY `idx_refund_original_payment` (`original_payment_id`),
  ADD KEY `idx_refund_status` (`refund_status`),
  ADD KEY `idx_refund_reason` (`refund_reason`);

--
-- Indexes for table `policies`
--
ALTER TABLE `policies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `restaurant_gallery`
--
ALTER TABLE `restaurant_gallery`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_id` (`room_id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_guest_email` (`guest_email`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `review_responses`
--
ALTER TABLE `review_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_review_id` (`review_id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_rooms_featured` (`is_featured`,`is_active`),
  ADD KEY `idx_rooms_price` (`price_per_night`);

--
-- Indexes for table `room_blocked_dates`
--
ALTER TABLE `room_blocked_dates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_date` (`room_id`,`block_date`),
  ADD KEY `idx_block_date` (`block_date`),
  ADD KEY `idx_block_type` (`block_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_blocked_admin` (`created_by`);

--
-- Indexes for table `room_maintenance_blocks`
--
ALTER TABLE `room_maintenance_blocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `individual_room_id` (`individual_room_id`),
  ADD KEY `block_dates` (`block_start_date`,`block_end_date`),
  ADD KEY `block_type` (`block_type`),
  ADD KEY `fk_maintenance_block_creator` (`created_by`);

--
-- Indexes for table `room_maintenance_log`
--
ALTER TABLE `room_maintenance_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_individual_room_id` (`individual_room_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_maintenance_log_performed_by` (`performed_by`);

--
-- Indexes for table `room_maintenance_schedules`
--
ALTER TABLE `room_maintenance_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room` (`individual_room_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dates` (`start_date`,`end_date`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_recurring` (`is_recurring`,`recurring_pattern`),
  ADD KEY `idx_assigned_status` (`assigned_to`,`status`),
  ADD KEY `fk_maintenance_verified_by` (`verified_by`),
  ADD KEY `fk_maintenance_linked_booking` (`linked_booking_id`);

--
-- Indexes for table `section_headers`
--
ALTER TABLE `section_headers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_section` (`section_key`,`page`),
  ADD KEY `idx_page` (`page`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `session_logs`
--
ALTER TABLE `session_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_session_id` (`session_id`),
  ADD KEY `idx_sl_session` (`session_id`),
  ADD KEY `idx_sl_ip` (`ip_address`),
  ADD KEY `idx_sl_start` (`session_start`),
  ADD KEY `idx_sl_device` (`device_type`);

--
-- Indexes for table `site_pages`
--
ALTER TABLE `site_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_key` (`page_key`),
  ADD KEY `idx_enabled_nav` (`is_enabled`,`show_in_nav`,`nav_position`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `site_visitors`
--
ALTER TABLE `site_visitors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_page` (`page_url`(191)),
  ADD KEY `idx_device` (`device_type`);

--
-- Indexes for table `tentative_booking_log`
--
ALTER TABLE `tentative_booking_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_testimonials_featured` (`is_featured`,`is_approved`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_perm` (`user_id`,`permission_key`);

--
-- Indexes for table `welcome`
--
ALTER TABLE `welcome`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_welcome_section_order` (`section_key`,`display_order`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `about_us`
--
ALTER TABLE `about_us`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `api_keys`
--
ALTER TABLE `api_keys`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `api_usage_logs`
--
ALTER TABLE `api_usage_logs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blocked_dates`
--
ALTER TABLE `blocked_dates`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `booking_charges`
--
ALTER TABLE `booking_charges`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `booking_date_adjustments`
--
ALTER TABLE `booking_date_adjustments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booking_email_templates`
--
ALTER TABLE `booking_email_templates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `booking_notes`
--
ALTER TABLE `booking_notes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `booking_payments`
--
ALTER TABLE `booking_payments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booking_timeline_logs`
--
ALTER TABLE `booking_timeline_logs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `cancellation_log`
--
ALTER TABLE `cancellation_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `conference_bookings`
--
ALTER TABLE `conference_bookings`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `conference_inquiries`
--
ALTER TABLE `conference_inquiries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `conference_rooms`
--
ALTER TABLE `conference_rooms`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `contact_inquiries`
--
ALTER TABLE `contact_inquiries`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cookie_consent_log`
--
ALTER TABLE `cookie_consent_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `drink_menu`
--
ALTER TABLE `drink_menu`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `email_settings`
--
ALTER TABLE `email_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=247;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `food_menu`
--
ALTER TABLE `food_menu`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `footer_links`
--
ALTER TABLE `footer_links`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `gallery`
--
ALTER TABLE `gallery`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `guest_services`
--
ALTER TABLE `guest_services`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `gym_classes`
--
ALTER TABLE `gym_classes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `gym_content`
--
ALTER TABLE `gym_content`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `gym_facilities`
--
ALTER TABLE `gym_facilities`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `gym_features`
--
ALTER TABLE `gym_features`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `gym_inquiries`
--
ALTER TABLE `gym_inquiries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `gym_packages`
--
ALTER TABLE `gym_packages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `hotel_gallery`
--
ALTER TABLE `hotel_gallery`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `housekeeping_assignments`
--
ALTER TABLE `housekeeping_assignments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `housekeeping_audit_log`
--
ALTER TABLE `housekeeping_audit_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `individual_rooms`
--
ALTER TABLE `individual_rooms`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `individual_room_amenities`
--
ALTER TABLE `individual_room_amenities`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `individual_room_blocked_dates`
--
ALTER TABLE `individual_room_blocked_dates`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `individual_room_photos`
--
ALTER TABLE `individual_room_photos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `individual_room_pictures_archive`
--
ALTER TABLE `individual_room_pictures_archive`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maintenance_audit_log`
--
ALTER TABLE `maintenance_audit_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `managed_media_catalog`
--
ALTER TABLE `managed_media_catalog`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `managed_media_groups_archive`
--
ALTER TABLE `managed_media_groups_archive`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `managed_media_items_archive`
--
ALTER TABLE `managed_media_items_archive`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `managed_media_links`
--
ALTER TABLE `managed_media_links`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `menu_categories`
--
ALTER TABLE `menu_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `migration_log`
--
ALTER TABLE `migration_log`
  MODIFY `migration_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `page_heroes`
--
ALTER TABLE `page_heroes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `page_loaders`
--
ALTER TABLE `page_loaders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `policies`
--
ALTER TABLE `policies`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `restaurant_gallery`
--
ALTER TABLE `restaurant_gallery`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `review_responses`
--
ALTER TABLE `review_responses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `room_blocked_dates`
--
ALTER TABLE `room_blocked_dates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `room_maintenance_blocks`
--
ALTER TABLE `room_maintenance_blocks`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `room_maintenance_log`
--
ALTER TABLE `room_maintenance_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `room_maintenance_schedules`
--
ALTER TABLE `room_maintenance_schedules`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `section_headers`
--
ALTER TABLE `section_headers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `session_logs`
--
ALTER TABLE `session_logs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=189;

--
-- AUTO_INCREMENT for table `site_pages`
--
ALTER TABLE `site_pages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=344;

--
-- AUTO_INCREMENT for table `site_visitors`
--
ALTER TABLE `site_visitors`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=189;

--
-- AUTO_INCREMENT for table `tentative_booking_log`
--
ALTER TABLE `tentative_booking_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `welcome`
--
ALTER TABLE `welcome`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

-- --------------------------------------------------------

--
-- Structure for view `v_media_by_page`
--
DROP TABLE IF EXISTS `v_media_by_page`;

CREATE ALGORITHM=UNDEFINED DEFINER=`p601229`@`localhost` SQL SECURITY DEFINER VIEW `v_media_by_page`  AS SELECT `c`.`id` AS `id`, `c`.`title` AS `title`, `c`.`description` AS `description`, `c`.`media_type` AS `media_type`, `c`.`source_type` AS `source_type`, `c`.`media_url` AS `media_url`, `c`.`mime_type` AS `mime_type`, `c`.`alt_text` AS `alt_text`, `c`.`caption` AS `caption`, `c`.`placement_key` AS `placement_key`, `c`.`page_slug` AS `page_slug`, `c`.`section_key` AS `section_key`, `c`.`entity_type` AS `entity_type`, `c`.`entity_id` AS `entity_id`, `c`.`is_active` AS `is_active`, `c`.`display_order` AS `display_order`, `c`.`legacy_source` AS `legacy_source`, `c`.`legacy_id` AS `legacy_id`, `c`.`created_by` AS `created_by`, `c`.`created_at` AS `created_at`, `c`.`updated_at` AS `updated_at`, group_concat(concat(`l`.`source_table`,'.',`l`.`source_column`) separator ', ') AS `source_columns` FROM (`managed_media_catalog` `c` left join `managed_media_links` `l` on((`l`.`media_catalog_id` = `c`.`id`))) WHERE (`c`.`is_active` = 1) GROUP BY `c`.`id` ORDER BY `c`.`page_slug` ASC, `c`.`section_key` ASC, `c`.`display_order` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_room_media`
--
DROP TABLE IF EXISTS `v_room_media`;

CREATE ALGORITHM=UNDEFINED DEFINER=`p601229`@`localhost` SQL SECURITY DEFINER VIEW `v_room_media`  AS SELECT `c`.`id` AS `id`, `c`.`title` AS `title`, `c`.`description` AS `description`, `c`.`media_type` AS `media_type`, `c`.`source_type` AS `source_type`, `c`.`media_url` AS `media_url`, `c`.`mime_type` AS `mime_type`, `c`.`alt_text` AS `alt_text`, `c`.`caption` AS `caption`, `c`.`placement_key` AS `placement_key`, `c`.`page_slug` AS `page_slug`, `c`.`section_key` AS `section_key`, `c`.`entity_type` AS `entity_type`, `c`.`entity_id` AS `entity_id`, `c`.`is_active` AS `is_active`, `c`.`display_order` AS `display_order`, `c`.`legacy_source` AS `legacy_source`, `c`.`legacy_id` AS `legacy_id`, `c`.`created_by` AS `created_by`, `c`.`created_at` AS `created_at`, `c`.`updated_at` AS `updated_at`, `l`.`source_record_id` AS `room_id` FROM (`managed_media_catalog` `c` join `managed_media_links` `l` on((`l`.`media_catalog_id` = `c`.`id`))) WHERE ((`l`.`source_table` = 'rooms') AND (`c`.`is_active` = 1)) ORDER BY `c`.`display_order` ASC ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking_charges`
--
ALTER TABLE `booking_charges`
  ADD CONSTRAINT `fk_booking_charges_booking_id` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `booking_date_adjustments`
--
ALTER TABLE `booking_date_adjustments`
  ADD CONSTRAINT `fk_booking_date_adjustments_adjusted_by` FOREIGN KEY (`adjusted_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_booking_date_adjustments_booking_id` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `booking_payments`
--
ALTER TABLE `booking_payments`
  ADD CONSTRAINT `booking_payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `booking_timeline_logs`
--
ALTER TABLE `booking_timeline_logs`
  ADD CONSTRAINT `booking_timeline_logs_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cancellation_log`
--
ALTER TABLE `cancellation_log`
  ADD CONSTRAINT `fk_cancellation_log_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `housekeeping_assignments`
--
ALTER TABLE `housekeeping_assignments`
  ADD CONSTRAINT `fk_housekeeping_booking` FOREIGN KEY (`linked_booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_housekeeping_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `housekeeping_audit_log`
--
ALTER TABLE `housekeeping_audit_log`
  ADD CONSTRAINT `fk_housekeeping_audit_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `housekeeping_assignments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_housekeeping_audit_performed_by` FOREIGN KEY (`performed_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `individual_room_blocked_dates`
--
ALTER TABLE `individual_room_blocked_dates`
  ADD CONSTRAINT `fk_irbd_blocked_by` FOREIGN KEY (`blocked_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_irbd_individual_room` FOREIGN KEY (`individual_room_id`) REFERENCES `individual_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `maintenance_audit_log`
--
ALTER TABLE `maintenance_audit_log`
  ADD CONSTRAINT `fk_maintenance_audit_maintenance` FOREIGN KEY (`maintenance_id`) REFERENCES `room_maintenance_schedules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_maintenance_audit_performed_by` FOREIGN KEY (`performed_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `managed_media_links`
--
ALTER TABLE `managed_media_links`
  ADD CONSTRAINT `fk_media_link_catalog` FOREIGN KEY (`media_catalog_id`) REFERENCES `managed_media_catalog` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_refund_original_payment` FOREIGN KEY (`original_payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `room_maintenance_schedules`
--
ALTER TABLE `room_maintenance_schedules`
  ADD CONSTRAINT `fk_maintenance_linked_booking` FOREIGN KEY (`linked_booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_maintenance_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
