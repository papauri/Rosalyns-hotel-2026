-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 22, 2026 at 06:09 PM
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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `about_us`
--
ALTER TABLE `about_us`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
