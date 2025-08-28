-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 07, 2025 at 07:08 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `learning`
--

-- --------------------------------------------------------

--
-- Table structure for table `blog_ratings`
--

CREATE TABLE `blog_ratings` (
  `user_id` int(11) NOT NULL,
  `content_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `content_ratings`
--

CREATE TABLE `content_ratings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_blogs`
--

CREATE TABLE `staff_blogs` (
  `blog_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `post_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_content`
--

CREATE TABLE `staff_content` (
  `content_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `type` enum('video','document','blog') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `content_text` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_content`
--

INSERT INTO `staff_content` (`content_id`, `staff_id`, `title`, `type`, `file_path`, `content_text`, `image_path`, `upload_date`) VALUES
(14, 9, 'PROJECT DOCUMENT', 'document', 'content/RUSO/tj.docx', NULL, NULL, '2025-03-18 09:04:50'),
(15, 9, 'GOOD EVENING', 'blog', '', 'FINALY THIS YEAR IS WILL BE DONE! WITH LOT OF JOY ANDCSAD.', 'uploads/1742288817_images (1).png', '2025-03-18 09:06:57'),
(16, 13, 'doc', 'document', 'content/SANJAY/DOCUMENT TEMPLATE.pdf', NULL, NULL, '2025-03-19 08:48:43'),
(17, 13, 'i am sanjai i am gay', 'blog', '', 'yes i am gay', 'uploads/1742374177_estonished-black-long-nighty-with-robe.jpg', '2025-03-19 08:49:37'),
(18, 16, 'project documentation', 'document', 'content/FRANK/PART A1_merge.pdf', NULL, NULL, '2025-04-03 16:14:06');

-- --------------------------------------------------------

--
-- Table structure for table `staff_feedback`
--

CREATE TABLE `staff_feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `feedback_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_feedback`
--

INSERT INTO `staff_feedback` (`id`, `user_id`, `staff_id`, `feedback_text`, `created_at`) VALUES
(1, 10, 16, 'good page and content', '2025-04-07 16:57:00'),
(2, 10, 16, 'good content', '2025-04-07 16:57:13');

-- --------------------------------------------------------

--
-- Table structure for table `staff_ratings`
--

CREATE TABLE `staff_ratings` (
  `user_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_ratings`
--

INSERT INTO `staff_ratings` (`user_id`, `staff_id`, `rating`) VALUES
(10, 9, 5),
(10, 13, 4),
(10, 16, 5),
(12, 9, 1);

-- --------------------------------------------------------

--
-- Stand-in structure for view `staff_stats`
-- (See below for the actual view)
--
CREATE TABLE `staff_stats` (
`staff_id` int(11)
,`staff_name` varchar(100)
,`total_content` bigint(21)
,`total_blogs` bigint(21)
,`total_admin_messages` bigint(21)
,`total_ratings` bigint(21)
,`avg_rating` decimal(14,4)
);

-- --------------------------------------------------------

--
-- Table structure for table `staff_to_admin_messages`
--

CREATE TABLE `staff_to_admin_messages` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `admin_reply` text DEFAULT NULL,
  `staff_reply` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_replied_at` timestamp NULL DEFAULT NULL,
  `staff_replied_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `role` enum('user','staff','admin') NOT NULL DEFAULT 'user',
  `pending_approval` tinyint(1) DEFAULT 0,
  `profile_photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `pending_approval`, `profile_photo`, `created_at`, `updated_at`) VALUES
(1, 'PRAVEEN', 'jaga03038@gmail.com', 'Tebi1328', 'admin', 0, NULL, '2025-03-08 17:57:37', '2025-03-11 13:09:34'),
(9, 'RUSO', 'ruso@gmail.com', '12345678', 'staff', 0, 'uploads/staff_9_1743049352.png', '2025-03-18 09:00:18', '2025-03-27 04:22:32'),
(10, 'PRASANNA', 'pricy@gmail.com', '12345678', 'user', 0, 'uploads/user_10_1743050044.png', '2025-03-18 09:00:44', '2025-03-27 04:34:04'),
(11, 'RONALD', 'r@gmail.com', '12345678', 'staff', 0, NULL, '2025-03-18 09:17:58', '2025-03-18 09:17:58'),
(12, 'PRAVEEN RAJ D', 'praveenrajjohnbosco@gmail.com', 'JDAX', 'user', 0, NULL, '2025-03-18 09:20:40', '2025-03-18 09:20:40'),
(13, 'SANJAY', 'sanjay@gmail.com', '12345678', 'staff', 0, 'profile_photos/13_images (2).png', '2025-03-19 08:47:52', '2025-03-19 08:48:22'),
(16, 'FRANK', 'frank@gmail.com', '12345678', 'staff', 0, 'uploads/staff_16_1744044946.png', '2025-04-03 16:10:42', '2025-04-07 16:55:46');

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `user_id` int(11) NOT NULL,
  `favorite_staff_id` int(11) DEFAULT NULL,
  `theme` varchar(50) DEFAULT 'light'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_ratings`
--

CREATE TABLE `user_ratings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `rating` int(1) NOT NULL CHECK (`rating` between 1 and 5),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_to_staff_messages`
--

CREATE TABLE `user_to_staff_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `staff_reply` text DEFAULT NULL,
  `replied_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_to_staff_messages`
--

INSERT INTO `user_to_staff_messages` (`id`, `user_id`, `staff_id`, `message`, `is_read`, `created_at`, `staff_reply`, `replied_at`) VALUES
(9, 10, 9, 'please uploade this document in pdf', 1, '2025-03-18 09:12:58', 'yes i will make that', '2025-03-18 09:13:51'),
(10, 10, 13, 'one doubt realy yoou are a gay', 1, '2025-03-19 08:52:29', 'yes', '2025-03-19 08:53:22'),
(11, 10, 16, 'good document', 1, '2025-04-03 16:17:58', 'ok', '2025-04-03 16:19:04');

-- --------------------------------------------------------

--
-- Structure for view `staff_stats`
--
DROP TABLE IF EXISTS `staff_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `staff_stats`  AS SELECT `u`.`id` AS `staff_id`, `u`.`name` AS `staff_name`, count(distinct `sc`.`content_id`) AS `total_content`, count(distinct `sb`.`blog_id`) AS `total_blogs`, count(distinct `sa`.`id`) AS `total_admin_messages`, count(distinct `ur`.`id`) AS `total_ratings`, avg(`ur`.`rating`) AS `avg_rating` FROM ((((`users` `u` left join `staff_content` `sc` on(`u`.`id` = `sc`.`staff_id`)) left join `staff_blogs` `sb` on(`u`.`id` = `sb`.`staff_id`)) left join `staff_to_admin_messages` `sa` on(`u`.`id` = `sa`.`staff_id`)) left join `user_ratings` `ur` on(`u`.`id` = `ur`.`staff_id`)) WHERE `u`.`role` = 'staff' GROUP BY `u`.`id`, `u`.`name` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `blog_ratings`
--
ALTER TABLE `blog_ratings`
  ADD PRIMARY KEY (`user_id`,`content_id`),
  ADD KEY `content_id` (`content_id`);

--
-- Indexes for table `content_ratings`
--
ALTER TABLE `content_ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_content_unique` (`user_id`,`content_id`),
  ADD KEY `content_id` (`content_id`);

--
-- Indexes for table `staff_blogs`
--
ALTER TABLE `staff_blogs`
  ADD PRIMARY KEY (`blog_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `staff_content`
--
ALTER TABLE `staff_content`
  ADD PRIMARY KEY (`content_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `staff_feedback`
--
ALTER TABLE `staff_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `staff_ratings`
--
ALTER TABLE `staff_ratings`
  ADD PRIMARY KEY (`user_id`,`staff_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `staff_to_admin_messages`
--
ALTER TABLE `staff_to_admin_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `favorite_staff_id` (`favorite_staff_id`);

--
-- Indexes for table `user_ratings`
--
ALTER TABLE `user_ratings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `user_to_staff_messages`
--
ALTER TABLE `user_to_staff_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `content_ratings`
--
ALTER TABLE `content_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff_blogs`
--
ALTER TABLE `staff_blogs`
  MODIFY `blog_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_content`
--
ALTER TABLE `staff_content`
  MODIFY `content_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `staff_feedback`
--
ALTER TABLE `staff_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff_to_admin_messages`
--
ALTER TABLE `staff_to_admin_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `user_ratings`
--
ALTER TABLE `user_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_to_staff_messages`
--
ALTER TABLE `user_to_staff_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `blog_ratings`
--
ALTER TABLE `blog_ratings`
  ADD CONSTRAINT `blog_ratings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `blog_ratings_ibfk_2` FOREIGN KEY (`content_id`) REFERENCES `staff_content` (`content_id`);

--
-- Constraints for table `content_ratings`
--
ALTER TABLE `content_ratings`
  ADD CONSTRAINT `content_ratings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `content_ratings_ibfk_2` FOREIGN KEY (`content_id`) REFERENCES `staff_content` (`content_id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_blogs`
--
ALTER TABLE `staff_blogs`
  ADD CONSTRAINT `staff_blogs_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_content`
--
ALTER TABLE `staff_content`
  ADD CONSTRAINT `staff_content_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_feedback`
--
ALTER TABLE `staff_feedback`
  ADD CONSTRAINT `staff_feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_feedback_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_ratings`
--
ALTER TABLE `staff_ratings`
  ADD CONSTRAINT `staff_ratings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `staff_ratings_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `staff_to_admin_messages`
--
ALTER TABLE `staff_to_admin_messages`
  ADD CONSTRAINT `staff_to_admin_messages_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_to_admin_messages_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_preferences_ibfk_2` FOREIGN KEY (`favorite_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_ratings`
--
ALTER TABLE `user_ratings`
  ADD CONSTRAINT `user_ratings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_ratings_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_to_staff_messages`
--
ALTER TABLE `user_to_staff_messages`
  ADD CONSTRAINT `user_to_staff_messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_to_staff_messages_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
