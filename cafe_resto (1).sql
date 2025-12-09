-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 28, 2025 at 04:06 AM
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
-- Database: `cafe_resto`
--

-- --------------------------------------------------------

--
-- Table structure for table `business_hours`
--

CREATE TABLE `business_hours` (
  `id` int(11) NOT NULL,
  `day_of_week` enum('monday','tuesday','wednesday','thursday','friday','saturday','sunday') NOT NULL,
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL,
  `is_closed` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `business_hours`
--

INSERT INTO `business_hours` (`id`, `day_of_week`, `open_time`, `close_time`, `is_closed`, `updated_at`) VALUES
(1, 'monday', '09:00:00', '21:00:00', 0, '2025-04-23 13:38:41'),
(2, 'tuesday', '09:00:00', '21:00:00', 0, '2025-04-23 13:38:41'),
(3, 'wednesday', '09:00:00', '21:00:00', 0, '2025-04-23 13:38:41'),
(4, 'thursday', '09:00:00', '21:00:00', 0, '2025-04-23 13:38:41'),
(5, 'friday', '09:00:00', '22:00:00', 0, '2025-04-23 13:38:41'),
(6, 'saturday', '10:00:00', '22:00:00', 0, '2025-04-23 13:38:41'),
(7, 'sunday', '10:00:00', '20:00:00', 0, '2025-04-23 13:38:41');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `cart_item_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart_items`
--

INSERT INTO `cart_items` (`cart_item_id`, `customer_id`, `item_id`, `quantity`, `unit_price`, `created_at`, `updated_at`) VALUES
(1, 29, 6, 1, 3.99, '2025-04-26 21:36:09', '2025-04-26 21:36:09');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`, `description`, `image_url`) VALUES
(1, 'sweets', 'matammis', '/../assets/Uploads/menu/category68088567c8ce5.png');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `loyalty_points` int(11) DEFAULT 0,
  `membership_level` enum('Regular','Bronze','Silver','Gold','Platinum') DEFAULT 'Regular',
  `birth_date` date DEFAULT NULL,
  `preferences` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `user_id`, `loyalty_points`, `membership_level`, `birth_date`, `preferences`) VALUES
(28, 48, 0, 'Regular', NULL, NULL),
(29, 49, 33, 'Bronze', NULL, '');

--
-- Triggers `customers`
--
DELIMITER $$
CREATE TRIGGER `after_customer_insert` AFTER INSERT ON `customers` FOR EACH ROW BEGIN
    INSERT INTO user_roles (user_id, role_id)
    SELECT NEW.user_id, role_id FROM roles WHERE role_name = 'customer';
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_customer_insert` BEFORE INSERT ON `customers` FOR EACH ROW BEGIN
    IF EXISTS (SELECT 1 FROM customers WHERE user_id = NEW.user_id) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'User already exists in customers table';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `status` enum('sent','delivered','failed') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_log`
--

CREATE TABLE `event_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `event_details` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `event_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_log`
--

INSERT INTO `event_log` (`log_id`, `user_id`, `event_type`, `event_details`, `ip_address`, `event_time`) VALUES
(1, NULL, 'login', 'User logged in', '::1', '2025-03-27 10:57:40'),
(2, NULL, 'login', 'User logged in', '::1', '2025-03-28 00:06:20'),
(3, NULL, 'login', 'User logged in', '::1', '2025-03-28 00:09:51'),
(4, NULL, 'login', 'User logged in', '::1', '2025-03-28 00:10:15'),
(5, NULL, 'login', 'User logged in', '::1', '2025-03-28 00:11:05'),
(6, NULL, 'login', 'User logged in', '::1', '2025-03-28 00:12:31'),
(7, NULL, 'login', 'User logged in', '::1', '2025-03-28 00:13:51'),
(8, NULL, 'login', 'User logged in', '::1', '2025-03-28 00:23:46'),
(9, NULL, 'login', 'User logged in', '::1', '2025-03-28 00:24:55'),
(10, NULL, 'login', 'User logged in', '::1', '2025-03-28 00:35:26'),
(11, NULL, 'login', 'User logged in', '::1', '2025-03-28 05:04:27'),
(12, NULL, 'login', 'User logged in', '::1', '2025-03-28 05:04:57'),
(13, NULL, 'login', 'User logged in', '::1', '2025-03-28 10:01:18'),
(14, NULL, 'login', 'User logged in', '::1', '2025-03-28 11:01:22'),
(15, NULL, 'login', 'User logged in', '::1', '2025-03-30 01:19:15'),
(16, NULL, 'login', 'User logged in', '::1', '2025-03-30 01:23:09'),
(17, NULL, 'login', 'User logged in', '::1', '2025-03-30 01:25:48'),
(18, NULL, 'login', 'User logged in', '::1', '2025-03-30 01:26:58'),
(19, NULL, 'login', 'User logged in', '::1', '2025-03-30 01:33:19'),
(20, NULL, 'login', 'User logged in', '::1', '2025-03-30 01:35:18'),
(21, NULL, 'login', 'User logged in', '::1', '2025-03-30 01:39:50'),
(22, NULL, 'login', 'User logged in', '::1', '2025-03-30 01:56:57'),
(23, NULL, 'login', 'User logged in', '::1', '2025-03-30 02:09:21'),
(24, NULL, 'login', 'User logged in', '::1', '2025-03-31 01:42:15'),
(25, NULL, 'login', 'User logged in', '::1', '2025-03-31 01:42:45'),
(26, NULL, 'login', 'User logged in', '::1', '2025-03-31 01:43:38'),
(27, NULL, 'login', 'User logged in', '::1', '2025-03-31 10:21:56'),
(28, NULL, 'login', 'User logged in', '::1', '2025-03-31 10:48:42'),
(29, NULL, 'login', 'User logged in', '::1', '2025-03-31 10:49:01'),
(30, NULL, 'login', 'User logged in', '::1', '2025-03-31 10:49:14'),
(31, NULL, 'login', 'User logged in', '::1', '2025-03-31 10:49:53'),
(32, NULL, 'login', 'User logged in', '::1', '2025-03-31 10:57:07'),
(33, NULL, 'login', 'User logged in', '::1', '2025-03-31 12:29:58'),
(34, NULL, 'login', 'User logged in', '::1', '2025-03-31 12:30:12'),
(35, NULL, 'login', 'User logged in', '::1', '2025-04-01 11:27:35'),
(36, NULL, 'login', 'User logged in', '::1', '2025-04-01 11:27:48'),
(37, NULL, 'login', 'User logged in', '::1', '2025-04-01 11:28:52'),
(38, NULL, 'login', 'User logged in', '::1', '2025-04-02 01:16:34'),
(39, NULL, 'logout', 'User logged out', '::1', '2025-04-02 01:16:34'),
(40, NULL, 'login', 'User logged in', '::1', '2025-04-02 01:44:56'),
(41, NULL, 'logout', 'User logged out', '::1', '2025-04-02 01:44:56'),
(42, NULL, 'login', 'User logged in', '::1', '2025-04-02 01:46:08'),
(43, NULL, 'logout', 'User logged out', '::1', '2025-04-02 01:46:08'),
(44, NULL, 'login', 'User logged in', '::1', '2025-04-02 01:46:17'),
(45, NULL, 'logout', 'User logged out', '::1', '2025-04-02 01:46:17'),
(46, NULL, 'login', 'User logged in', '::1', '2025-04-02 01:48:17'),
(47, NULL, 'logout', 'User logged out', '::1', '2025-04-02 01:48:17'),
(48, NULL, 'login', 'User logged in', '::1', '2025-04-02 01:48:22'),
(49, NULL, 'logout', 'User logged out', '::1', '2025-04-02 01:48:22'),
(50, NULL, 'login', 'User logged in', '::1', '2025-04-02 01:48:45'),
(51, NULL, 'logout', 'User logged out', '::1', '2025-04-02 01:48:45'),
(52, NULL, 'login', 'User logged in', '::1', '2025-04-02 01:52:16'),
(53, NULL, 'logout', 'User logged out', '::1', '2025-04-02 01:52:16'),
(54, NULL, 'login', 'User logged in', '::1', '2025-04-02 01:52:23'),
(55, NULL, 'logout', 'User logged out', '::1', '2025-04-02 01:52:23'),
(56, NULL, 'login', 'User logged in', '::1', '2025-04-02 01:57:17'),
(57, NULL, 'login', 'User logged in', '::1', '2025-04-02 01:57:42'),
(58, NULL, 'login', 'User logged in', '::1', '2025-04-02 01:59:22'),
(59, NULL, 'login', 'User logged in', '::1', '2025-04-02 02:07:39'),
(60, NULL, 'logout', 'User logged out', '::1', '2025-04-02 02:07:39'),
(61, NULL, 'login', 'User logged in', '::1', '2025-04-02 02:09:30'),
(62, NULL, 'logout', 'User logged out', '::1', '2025-04-02 02:09:30'),
(63, NULL, 'login', 'User logged in', '::1', '2025-04-02 02:17:54'),
(64, NULL, 'logout', 'User logged out', '::1', '2025-04-02 02:17:54'),
(65, NULL, 'login', 'User logged in', '::1', '2025-04-02 02:19:36'),
(66, NULL, 'logout', 'User logged out', '::1', '2025-04-02 02:19:36'),
(67, NULL, 'login', 'User logged in', '::1', '2025-04-02 02:24:49'),
(68, NULL, 'logout', 'User logged out', '::1', '2025-04-02 02:24:49'),
(69, NULL, 'login', 'User logged in', '::1', '2025-04-02 02:26:05'),
(70, NULL, 'logout', 'User logged out', '::1', '2025-04-02 02:26:05'),
(71, NULL, 'login', 'User logged in', '::1', '2025-04-02 02:47:36'),
(72, NULL, 'login', 'User logged in', '::1', '2025-04-02 03:09:51'),
(73, NULL, 'login', 'User logged in', '::1', '2025-04-02 09:54:01'),
(74, NULL, 'logout', 'User logged out', '::1', '2025-04-02 09:54:01'),
(75, NULL, 'login', 'User logged in', '::1', '2025-04-02 09:54:10'),
(76, NULL, 'logout', 'User logged out', '::1', '2025-04-02 09:54:10'),
(77, NULL, 'login', 'User logged in', '::1', '2025-04-02 09:55:19'),
(78, NULL, 'login', 'User logged in', '::1', '2025-04-02 10:22:52'),
(79, NULL, 'login', 'User logged in', '::1', '2025-04-02 10:36:55'),
(80, NULL, 'logout', 'User logged out', '::1', '2025-04-02 10:36:55'),
(81, NULL, 'login', 'User logged in', '::1', '2025-04-02 10:37:24'),
(82, NULL, 'logout', 'User logged out', '::1', '2025-04-02 10:37:24'),
(83, NULL, 'login', 'User logged in', '::1', '2025-04-02 10:51:02'),
(84, NULL, 'login', 'User logged in', '::1', '2025-04-02 10:54:13'),
(85, NULL, 'login', 'User logged in', '::1', '2025-04-02 11:03:13'),
(86, NULL, 'login', 'User logged in', '::1', '2025-04-02 11:15:15'),
(87, NULL, 'login', 'User logged in', '::1', '2025-04-02 11:42:10'),
(88, NULL, 'login', 'User logged in', '::1', '2025-04-02 11:49:00'),
(89, NULL, 'login', 'User logged in', '::1', '2025-04-02 11:50:14'),
(90, NULL, 'login', 'User logged in', '::1', '2025-04-02 12:00:00'),
(91, NULL, 'login', 'User logged in', '::1', '2025-04-03 01:27:31'),
(92, NULL, 'login', 'User logged in', '::1', '2025-04-03 01:37:48'),
(93, NULL, 'login', 'User logged in', '::1', '2025-04-03 01:38:18'),
(94, NULL, 'login', 'User logged in', '::1', '2025-04-07 01:16:53'),
(95, NULL, 'login', 'User logged in', '::1', '2025-04-07 01:17:43'),
(96, NULL, 'login', 'User logged in', '::1', '2025-04-23 00:00:48'),
(97, NULL, 'logout', 'User logged out', '::1', '2025-04-23 00:00:49'),
(98, NULL, 'login', 'User logged in', '::1', '2025-04-23 00:00:59'),
(99, NULL, 'logout', 'User logged out', '::1', '2025-04-23 00:00:59'),
(100, NULL, 'login', 'User logged in', '::1', '2025-04-23 00:01:22'),
(101, NULL, 'logout', 'User logged out', '::1', '2025-04-23 00:01:22'),
(102, 48, 'login', 'User logged in', '::1', '2025-04-23 00:04:12'),
(103, 48, 'login', 'User logged in', '::1', '2025-04-23 00:05:37'),
(104, 49, 'login', 'User logged in', '::1', '2025-04-23 00:39:15'),
(105, 48, 'login', 'User logged in', '::1', '2025-04-23 00:51:36'),
(106, 48, 'login', 'User logged in', '::1', '2025-04-23 12:43:35'),
(107, 50, 'login', 'User logged in', '::1', '2025-04-24 12:25:58'),
(108, 50, 'login', 'User logged in', '::1', '2025-04-24 12:26:33'),
(109, 48, 'login', 'User logged in', '::1', '2025-04-24 12:54:27'),
(110, 50, 'login', 'User logged in', '::1', '2025-04-24 13:10:18'),
(111, 51, 'login', 'User logged in', '::1', '2025-04-24 13:14:15'),
(112, 48, 'login', 'User logged in', '::1', '2025-04-24 13:14:44'),
(113, 51, 'login', 'User logged in', '::1', '2025-04-24 13:15:33'),
(114, 50, 'login', 'User logged in', '::1', '2025-04-24 13:38:39'),
(115, 48, 'login', 'User logged in', '::1', '2025-04-24 13:39:36'),
(116, 50, 'login', 'User logged in', '::1', '2025-04-24 13:42:39'),
(117, 49, 'login', 'User logged in', '::1', '2025-04-24 14:26:56'),
(118, 50, 'login', 'User logged in', '::1', '2025-04-24 14:36:00'),
(119, 51, 'login', 'User logged in', '::1', '2025-04-24 14:37:05'),
(120, 48, 'login', 'User logged in', '::1', '2025-04-24 14:38:07'),
(121, 51, 'login', 'User logged in', '::1', '2025-04-24 14:39:10'),
(122, 50, 'login', 'User logged in', '::1', '2025-04-24 14:39:40'),
(123, 49, 'login', 'User logged in', '::1', '2025-04-24 14:41:03'),
(124, 49, 'login', 'User logged in', '::1', '2025-04-26 01:03:09'),
(125, 50, 'login', 'User logged in', '::1', '2025-04-26 02:13:38'),
(126, 48, 'login', 'User logged in', '::1', '2025-04-26 02:16:24'),
(127, 49, 'login', 'User logged in', '::1', '2025-04-26 02:30:31'),
(128, 49, 'login', 'User logged in', '::1', '2025-04-26 02:52:20'),
(129, 49, 'login', 'User logged in', '::1', '2025-04-26 07:36:41'),
(130, 50, 'login', 'User logged in', '::1', '2025-04-26 07:52:00'),
(131, 49, 'login', 'User logged in', '::1', '2025-04-26 07:57:52'),
(132, 49, 'login', 'User logged in', '::1', '2025-04-26 07:59:22'),
(133, 50, 'login', 'User logged in', '::1', '2025-04-26 08:00:15'),
(134, 48, 'login', 'User logged in', '::1', '2025-04-26 08:01:15'),
(135, 49, 'login', 'User logged in', '::1', '2025-04-26 08:10:52'),
(136, 48, 'login', 'User logged in', '::1', '2025-04-26 08:36:59'),
(137, 50, 'login', 'User logged in', '::1', '2025-04-26 08:37:58'),
(138, 49, 'login', 'User logged in', '::1', '2025-04-26 08:39:12'),
(139, 50, 'login', 'User logged in', '::1', '2025-04-26 08:48:45'),
(140, 49, 'login', 'User logged in', '::1', '2025-04-26 08:49:20'),
(141, 50, 'login', 'User logged in', '::1', '2025-04-26 09:14:11'),
(142, 49, 'login', 'User logged in', '::1', '2025-04-26 09:15:48'),
(143, 50, 'login', 'User logged in', '::1', '2025-04-26 14:32:23'),
(144, 48, 'login', 'User logged in', '::1', '2025-04-26 14:40:54'),
(145, 51, 'login', 'User logged in', '::1', '2025-04-27 00:01:30'),
(146, 49, 'login', 'User logged in', '::1', '2025-04-27 00:06:18'),
(147, 51, 'login', 'User logged in', '::1', '2025-04-27 00:22:44'),
(148, 50, 'login', 'User logged in', '::1', '2025-04-27 10:33:27'),
(149, 51, 'login', 'User logged in', '::1', '2025-04-27 10:34:28'),
(150, 49, 'login', 'User logged in', '::1', '2025-04-27 11:01:55'),
(151, 48, 'login', 'User logged in', '::1', '2025-04-27 11:28:28'),
(152, 49, 'login', 'User logged in', '::1', '2025-04-28 01:31:36');

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `favorite_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `feedback_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `cost_per_unit` decimal(10,2) NOT NULL,
  `reorder_level` decimal(10,2) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `last_restock_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `storage_location` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `item_name`, `quantity`, `unit`, `cost_per_unit`, `reorder_level`, `supplier_id`, `last_restock_date`, `expiry_date`, `storage_location`) VALUES
(1, 'Flour', 50.00, 'kg', 1.20, 10.00, NULL, '2025-04-20', '2025-10-20', 'Dry Storage A'),
(2, 'Sugar', 30.00, 'kg', 0.80, 5.00, NULL, '2025-04-18', '2026-04-18', 'Dry Storage B'),
(3, 'Coffee Beans', 15.00, 'kg', 12.50, 3.00, NULL, '2025-04-27', '2025-10-15', 'Cool Storage'),
(4, 'Milk', 20.00, 'liters', 1.10, 5.00, NULL, '2025-04-22', '2025-04-29', 'Refrigerator'),
(5, 'Eggs', 120.00, 'pieces', 0.25, 30.00, NULL, '2025-04-21', '2025-05-05', 'Refrigerator');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_log`
--

CREATE TABLE `inventory_log` (
  `log_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('restock','update','delete') NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_log`
--

INSERT INTO `inventory_log` (`log_id`, `inventory_id`, `user_id`, `action`, `amount`, `notes`, `created_at`) VALUES
(1, 1, 48, 'restock', 25.00, 'Initial stock', '2025-04-26 02:24:51'),
(2, 2, 48, 'restock', 15.00, 'Initial stock', '2025-04-26 02:24:51'),
(3, 3, 48, 'restock', 5.00, 'Initial stock', '2025-04-26 02:24:51'),
(4, 4, 48, 'restock', 10.00, 'Initial stock', '2025-04-26 02:24:51'),
(5, 5, 48, 'restock', 60.00, 'Initial stock', '2025-04-26 02:24:51'),
(6, 1, 48, 'update', -2.50, 'Used for baking', '2025-04-26 02:24:51'),
(7, 2, 48, 'update', -1.20, 'Used for baking', '2025-04-26 02:24:51'),
(8, 5, 48, 'update', -12.00, 'Used for baking', '2025-04-26 02:24:51');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `item_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `prep_time` int(11) DEFAULT NULL COMMENT 'Preparation time in minutes',
  `calories` int(11) DEFAULT NULL,
  `allergens` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`item_id`, `category_id`, `name`, `description`, `price`, `cost`, `image_url`, `is_available`, `prep_time`, `calories`, `allergens`, `created_at`, `updated_at`) VALUES
(2, 1, 'cupcake', 'e efqnwaefhb', 12.00, 5.00, '/../assets/Uploads/menu/item680885a8d527b.png', 1, NULL, 23, '0', '2025-04-23 01:14:08', '2025-04-23 08:28:03'),
(3, 1, 'Chocolate Cake', 'Rich chocolate cake with buttercream frosting', 8.99, 3.00, '/../assets/Uploads/menu/item680c449755752.png', 1, 15, 450, '0', '2025-04-26 02:26:51', '2025-04-26 02:27:35'),
(4, 1, 'Cheesecake', 'Classic New York style cheesecake', 9.99, 4.00, '/../assets/Uploads/menu/item680c448896b6e.png', 1, 10, 520, '0', '2025-04-26 02:26:51', '2025-04-26 02:27:20'),
(5, NULL, 'Espresso', 'Single shot of premium espresso', 2.50, 0.80, '/assets/images/espresso.jpg', 1, 5, 5, NULL, '2025-04-26 02:26:51', '2025-04-26 02:26:51'),
(6, NULL, 'Cappuccino', 'Espresso with steamed milk and foam', 3.99, 1.20, '/assets/images/cappuccino.jpg', 1, 7, 120, 'dairy', '2025-04-26 02:26:51', '2025-04-26 02:26:51');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `table_id` int(11) DEFAULT NULL,
  `order_type` enum('Dine-in','Takeout','Delivery') NOT NULL,
  `status` enum('Pending','Processing','Ready','Completed','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `delivery_address` text DEFAULT NULL,
  `delivery_fee` decimal(10,2) DEFAULT 0.00,
  `estimated_delivery_time` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `discount_applied` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `customer_id`, `staff_id`, `table_id`, `order_type`, `status`, `created_at`, `updated_at`, `delivery_address`, `delivery_fee`, `estimated_delivery_time`, `notes`, `total`, `discount_applied`) VALUES
(2, 29, NULL, NULL, '', 'Completed', '2025-04-24 14:35:23', '2025-04-24 14:40:34', NULL, 0.00, NULL, NULL, 0.00, 0.00),
(3, 29, 4, 2, 'Dine-in', 'Completed', '2025-04-26 07:51:02', '2025-04-26 08:49:05', NULL, 0.00, NULL, '', 9.99, 0.00),
(4, 29, 4, 1, 'Dine-in', 'Completed', '2025-04-26 07:58:50', '2025-04-26 08:38:06', NULL, 0.00, NULL, 'add some chocolate', 8.99, 0.00),
(5, 29, NULL, 3, 'Dine-in', 'Completed', '2025-04-26 08:25:43', '2025-04-26 08:48:58', NULL, 0.00, NULL, '', 18.98, 0.00),
(6, 29, NULL, NULL, 'Delivery', 'Completed', '2025-04-26 09:12:14', '2025-04-26 09:14:21', 'jan lang', 5.00, NULL, '', 8.99, 0.00),
(7, 29, NULL, NULL, 'Takeout', 'Completed', '2025-04-26 09:13:34', '2025-04-26 09:14:17', NULL, 0.00, NULL, '', 3.99, 0.00),
(8, 29, NULL, NULL, 'Takeout', 'Cancelled', '2025-04-26 09:20:47', '2025-04-26 12:42:55', NULL, 0.00, NULL, 'less sugar', 22.97, 0.00),
(9, 29, NULL, NULL, 'Delivery', 'Completed', '2025-04-26 13:44:45', '2025-04-26 14:35:18', 'doon', 5.00, NULL, '', 14.99, 0.00),
(10, 29, NULL, 1, 'Dine-in', 'Cancelled', '2025-04-27 11:12:05', '2025-04-27 11:12:14', NULL, 0.00, NULL, '0', 12.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Preparing','Ready','Served','Cancelled') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `item_id`, `quantity`, `unit_price`, `notes`, `status`) VALUES
(1, 2, 2, 1, 12.00, NULL, 'Pending'),
(2, 3, 4, 1, 9.99, NULL, 'Pending'),
(3, 4, 3, 1, 8.99, NULL, 'Pending'),
(4, 5, 4, 1, 9.99, NULL, 'Pending'),
(5, 5, 3, 1, 8.99, NULL, 'Pending'),
(6, 6, 6, 1, 3.99, NULL, 'Pending'),
(7, 7, 6, 1, 3.99, NULL, 'Pending'),
(8, 8, 6, 1, 3.99, NULL, 'Pending'),
(9, 8, 4, 1, 9.99, NULL, 'Pending'),
(10, 8, 3, 1, 8.99, NULL, 'Pending'),
(11, 9, 4, 1, 9.99, NULL, 'Pending'),
(12, 10, 2, 1, 12.00, NULL, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','Credit Card','Debit Card','Mobile Payment','Gift Card') NOT NULL,
  `status` enum('Pending','Completed','Refunded','Failed') DEFAULT 'Pending',
  `transaction_id` varchar(100) DEFAULT NULL,
  `tip_amount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `order_id`, `payment_date`, `amount`, `payment_method`, `status`, `transaction_id`, `tip_amount`) VALUES
(1, 3, '2025-04-26 07:51:02', 9.99, 'Cash', 'Pending', NULL, 0.00),
(2, 4, '2025-04-26 07:58:50', 8.99, 'Cash', 'Pending', NULL, 0.00),
(3, 5, '2025-04-26 08:25:43', 18.98, 'Cash', 'Pending', NULL, 0.00),
(4, 6, '2025-04-26 09:14:21', 8.99, 'Cash', 'Completed', NULL, 0.00),
(5, 7, '2025-04-26 09:14:17', 3.99, 'Cash', 'Completed', NULL, 0.00),
(6, 8, '2025-04-26 09:20:47', 22.97, 'Cash', 'Pending', NULL, 0.00),
(7, 9, '2025-04-26 14:35:18', 14.99, 'Cash', 'Completed', NULL, 0.00),
(8, 10, '2025-04-27 11:12:05', 12.00, 'Cash', 'Pending', NULL, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `promotion_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_type` enum('Percentage','Fixed Amount','Buy One Get One','Free Item') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `min_purchase` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `promotions`
--

INSERT INTO `promotions` (`promotion_id`, `name`, `description`, `discount_type`, `discount_value`, `start_date`, `end_date`, `min_purchase`, `is_active`, `created_at`, `created_by`) VALUES
(1, '10% off', 'Worth it Ba', 'Percentage', 10.00, '2025-04-27', '2025-04-30', 100.00, 1, '2025-04-27 11:00:21', 5);

-- --------------------------------------------------------

--
-- Table structure for table `promotion_items`
--

CREATE TABLE `promotion_items` (
  `promotion_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `promotion_items`
--

INSERT INTO `promotion_items` (`promotion_id`, `item_id`) VALUES
(1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `reservation_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `table_id` int(11) DEFAULT NULL,
  `reservation_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `party_size` int(11) NOT NULL,
  `status` enum('Pending','Confirmed','Seated','Completed','Cancelled') DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`reservation_id`, `customer_id`, `table_id`, `reservation_date`, `start_time`, `end_time`, `party_size`, `status`, `notes`, `created_at`) VALUES
(4, 29, 2, '2025-04-30', '11:30:00', '13:30:00', 2, 'Confirmed', 'make it special', '2025-04-26 13:13:03');

-- --------------------------------------------------------

--
-- Table structure for table `restaurant_tables`
--

CREATE TABLE `restaurant_tables` (
  `table_id` int(11) NOT NULL,
  `table_number` varchar(10) NOT NULL,
  `capacity` int(11) NOT NULL,
  `location` varchar(50) DEFAULT NULL COMMENT 'e.g., Indoor, Outdoor, Upstairs',
  `status` enum('Available','Occupied','Reserved','Maintenance') DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `restaurant_tables`
--

INSERT INTO `restaurant_tables` (`table_id`, `table_number`, `capacity`, `location`, `status`) VALUES
(1, 'T1', 4, 'Indoor', 'Available'),
(2, 'T2', 6, 'Indoor', 'Reserved'),
(3, 'T3', 2, 'Outdoor', 'Reserved');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `description`) VALUES
(1, 'admin', 'System administrator with full access'),
(2, 'manager', 'Restaurant manager with high-level access'),
(3, 'staff', 'Regular staff member'),
(4, 'customer', 'Regular customer');

-- --------------------------------------------------------

--
-- Table structure for table `security_logs`
--

CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_logs`
--

INSERT INTO `security_logs` (`id`, `user_id`, `event_type`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 43, 'email_verified', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-04-02 18:51:51'),
(2, 43, 'email_verified', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-04-02 18:53:37'),
(3, 44, 'email_verified', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-04-02 18:59:51'),
(4, 46, 'email_verified', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-04-02 19:46:53'),
(5, 47, 'email_verified', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-04-03 09:37:36');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL,
  `restaurant_name` varchar(100) NOT NULL,
  `contact_email` varchar(100) NOT NULL,
  `contact_phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_id`, `restaurant_name`, `contact_email`, `contact_phone`, `address`, `tax_rate`, `updated_at`) VALUES
(1, 'Casa Baraka', 'info@myrestaurant.com', '(123) 456-7890', '123 Main St, City, State 12345', 7.50, '2025-04-24 13:40:35');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staff_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `position` varchar(100) NOT NULL,
  `hire_date` date NOT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `employment_status` enum('Full-time','Part-time','Contract','Intern') NOT NULL,
  `supervisor_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`staff_id`, `user_id`, `position`, `hire_date`, `salary`, `employment_status`, `supervisor_id`) VALUES
(4, 50, 'waiter', '2025-04-24', NULL, 'Part-time', NULL),
(5, 51, 'manager', '2025-04-24', NULL, 'Full-time', NULL);

--
-- Triggers `staff`
--
DELIMITER $$
CREATE TRIGGER `after_staff_insert` AFTER INSERT ON `staff` FOR EACH ROW BEGIN
    INSERT INTO user_roles (user_id, role_id)
    SELECT NEW.user_id, role_id FROM roles WHERE role_name = 'staff';
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_staff_insert` BEFORE INSERT ON `staff` FOR EACH ROW BEGIN
    IF EXISTS (SELECT 1 FROM staff WHERE user_id = NEW.user_id) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'User already exists in staff table';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `staff_schedules`
--

CREATE TABLE `staff_schedules` (
  `schedule_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_schedules`
--

INSERT INTO `staff_schedules` (`schedule_id`, `staff_id`, `day_of_week`, `start_time`, `end_time`) VALUES
(1, 4, 'Monday', '07:30:00', '18:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `remember_token` varchar(255) DEFAULT NULL,
  `remember_token_expiry` int(11) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `verification_token_expiry` datetime DEFAULT NULL,
  `login_token` varchar(255) DEFAULT NULL,
  `login_token_expiry` datetime DEFAULT NULL,
  `last_verified_at` datetime DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `session_timeout_enabled` tinyint(1) DEFAULT 1,
  `login_alerts_enabled` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password_hash`, `email`, `first_name`, `last_name`, `phone`, `created_at`, `updated_at`, `is_active`, `remember_token`, `remember_token_expiry`, `email_verified`, `verification_token`, `verification_token_expiry`, `login_token`, `login_token_expiry`, `last_verified_at`, `status`, `two_factor_enabled`, `session_timeout_enabled`, `login_alerts_enabled`) VALUES
(48, 'admin123', '$2y$12$M.BcMBGWp3fNrm7UM7./KOmyb6zEseg26wMLpSDiTBlInXcs1FqBK', 'test1@gmail.com', 'admin', '123', '09387768011', '2025-04-23 00:03:19', '2025-04-24 13:14:44', 1, '650cefa5f4c923ae8722c6d3b52c11c1de4843c81f6f161605c9a0657490ef45', 1748092484, 1, '946c8e289d0e1df9b50b8d95768c8c83e7715abd6de68cc2895bb0c57ad2154f', '2025-04-24 02:03:19', '$2y$10$.iW7noUYxo/wayx3B/T8p.W9QoNXZtyL9wCpB/j89Qx87xR70Yhki', '2025-04-23 02:08:20', NULL, 'active', 0, 1, 1),
(49, 'kyu', '$2y$12$/a0O4wzuJ.rppe/avjqvKOjT26SZeGXRfKWio13VHvS8PB/kIw2/K', 'mlbausa10@gmail.com', 'Kyu', '00', '09387768011', '2025-04-23 00:38:12', '2025-04-28 01:54:58', 1, '5a6992573b9a9eaecff26272a3d44a229d724b96f486ff55e01e8fdf8b63cc3f', 1748245001, 1, '937b4ae967caed3bc27d76a76334864bac508de075077b118e9cd9ee867e1d80', '2025-04-24 02:38:12', '$2y$10$velHddYt8gSxWW/HRsEHYu.QKeQcA6XCKla2CoEN9cBvsWJfKU3u.', '2025-04-23 02:43:12', NULL, 'active', 0, 1, 1),
(50, 'staff00', '$2y$12$vd.MXCBdgDYAMbUQ5rxa3exrhFQYaIxvOcG2PMc9lkl7h6YtfxiMi', 'staff00@gmail.com', 'staff', '00', '098765432123', '2025-04-24 12:24:59', '2025-04-24 13:38:39', 1, '194170c41ecb7d7f228176e9aad07f06f987e52fbde5001f693278bb74e1dbc3', 1748093919, 1, 'a32c34f30970598e96779144b0b610aa140d1a0ec42e4a0211dfbee734108fb2', '2025-04-25 14:24:59', '$2y$10$.t13bMXsJnRW/BxJE9Q66Owf9YUqKTADKHTFV5UxzznYQIFr8AIHy', '2025-04-24 14:29:59', '2025-04-24 20:25:30', 'active', 0, 1, 1),
(51, 'manager00', '$2y$12$kqZMeRQhj5VtP5Ct5bDQt.PzXtfMjVmyFswStdeodJACtgEVEl6IS', 'manager00@gmail.com', 'manager', '00', '098765432123', '2025-04-24 13:12:54', '2025-04-24 13:15:33', 1, '0b3bd4ba362f0296bb53d9743b754b4c6007c4db6ab53dbd542727d2fbd61fe2', 1748092533, 1, 'f45680acccf7cb1217eee3a2925d6ae463040e8d6c937b1368564374aca34d7b', '2025-04-25 15:12:54', '$2y$10$njMYVRwW3X8delIQrk0lkebqPLeLWkTFrh..RkfzyP1ET.Xnz80gq', '2025-04-24 15:17:54', NULL, 'active', 0, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`, `assigned_at`) VALUES
(48, 1, '2025-04-23 00:03:19'),
(49, 4, '2025-04-23 00:38:12'),
(50, 3, '2025-04-24 13:09:33'),
(51, 2, '2025-04-24 13:14:59'),
(51, 3, '2025-04-24 13:14:59');

-- --------------------------------------------------------

--
-- Table structure for table `verification_tokens`
--

CREATE TABLE `verification_tokens` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expiry` int(11) NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `purpose` enum('verification','login') NOT NULL DEFAULT 'verification',
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `verification_tokens`
--

INSERT INTO `verification_tokens` (`id`, `email`, `token`, `expiry`, `used`, `created_at`, `purpose`, `user_id`) VALUES
(8, 'mlbausa10@gmail.com', 'eaa25fcb7edac6a5c6dadacf78c4f51384c2822e6e1291763e9b0e5a4b767512', 1743592774, 0, '2025-04-01 11:19:34', 'verification', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `business_hours`
--
ALTER TABLE `business_hours`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `day_of_week` (`day_of_week`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`cart_item_id`),
  ADD UNIQUE KEY `unique_cart_item` (`customer_id`,`item_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `event_log`
--
ALTER TABLE `event_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`favorite_id`),
  ADD UNIQUE KEY `customer_id` (`customer_id`,`item_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`);

--
-- Indexes for table `inventory_log`
--
ALTER TABLE `inventory_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `table_id` (`table_id`),
  ADD KEY `idx_orders_customer` (`customer_id`),
  ADD KEY `idx_orders_staff` (`staff_id`),
  ADD KEY `idx_orders_status` (`status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `idx_order_items_item` (`item_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `idx_payments_order` (`order_id`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`promotion_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `promotion_items`
--
ALTER TABLE `promotion_items`
  ADD PRIMARY KEY (`promotion_id`,`item_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`reservation_id`),
  ADD KEY `table_id` (`table_id`),
  ADD KEY `idx_reservations_customer` (`customer_id`),
  ADD KEY `idx_reservations_date` (`reservation_date`);

--
-- Indexes for table `restaurant_tables`
--
ALTER TABLE `restaurant_tables`
  ADD PRIMARY KEY (`table_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_type` (`event_type`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `supervisor_id` (`supervisor_id`);

--
-- Indexes for table `staff_schedules`
--
ALTER TABLE `staff_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD UNIQUE KEY `unique_user_role` (`user_id`,`role_id`),
  ADD KEY `idx_user_roles_user` (`user_id`),
  ADD KEY `idx_user_roles_role` (`role_id`);

--
-- Indexes for table `verification_tokens`
--
ALTER TABLE `verification_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `business_hours`
--
ALTER TABLE `business_hours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `cart_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_log`
--
ALTER TABLE `event_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=153;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `favorite_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory_log`
--
ALTER TABLE `inventory_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `promotion_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `reservation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `restaurant_tables`
--
ALTER TABLE `restaurant_tables`
  MODIFY `table_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `staff_schedules`
--
ALTER TABLE `staff_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `verification_tokens`
--
ALTER TABLE `verification_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `event_log`
--
ALTER TABLE `event_log`
  ADD CONSTRAINT `event_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_log`
--
ALTER TABLE `inventory_log`
  ADD CONSTRAINT `inventory_log_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_log_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables` (`table_id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `promotions`
--
ALTER TABLE `promotions`
  ADD CONSTRAINT `promotions_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `staff` (`staff_id`) ON DELETE SET NULL;

--
-- Constraints for table `promotion_items`
--
ALTER TABLE `promotion_items`
  ADD CONSTRAINT `promotion_items_ibfk_1` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`promotion_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `promotion_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables` (`table_id`) ON DELETE SET NULL;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_ibfk_2` FOREIGN KEY (`supervisor_id`) REFERENCES `staff` (`staff_id`) ON DELETE SET NULL;

--
-- Constraints for table `staff_schedules`
--
ALTER TABLE `staff_schedules`
  ADD CONSTRAINT `staff_schedules_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE;

--
-- Constraints for table `verification_tokens`
--
ALTER TABLE `verification_tokens`
  ADD CONSTRAINT `verification_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
