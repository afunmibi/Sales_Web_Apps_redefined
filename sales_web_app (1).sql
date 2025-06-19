-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 19, 2025 at 03:54 AM
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
-- Database: `sales_web_app`
--

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `stock_quantity`, `created_at`, `updated_at`) VALUES
(1, 'Coca-Cola 50cl', 'Soft drink', 250.00, 498, '2025-06-14 03:04:40', '2025-06-19 01:28:25'),
(2, 'Peak Milk (Small)', 'Powdered milk sachet', 150.00, 300, '2025-06-14 03:04:40', '2025-06-14 03:04:40'),
(3, 'Indomie Noodles (Hungry Man)', 'Instant noodles', 400.00, 199, '2025-06-14 03:04:40', '2025-06-19 00:07:08'),
(4, 'Detergent Powder (1kg)', 'Washing powder', 1200.00, 99, '2025-06-14 03:04:40', '2025-06-19 01:11:03'),
(5, 'Coke', 'hfhdjddhdjdk', 400.00, 49, '2025-06-16 12:00:46', '2025-06-19 00:07:08');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL,
  `transaction_code` varchar(50) NOT NULL COMMENT 'Unique code for the transaction, e.g., SALE-YYMMDD-XXXX',
  `cashier_id` int(11) NOT NULL COMMENT 'Foreign key to the users table',
  `customer_name` varchar(255) DEFAULT NULL COMMENT 'Optional customer name',
  `sale_date` datetime DEFAULT current_timestamp() COMMENT 'Date and time the sale occurred',
  `subtotal` decimal(10,2) NOT NULL COMMENT 'Sum of all item prices * quantities before discount',
  `discount_percentage` decimal(5,2) DEFAULT 0.00 COMMENT 'Percentage discount applied to the sale',
  `discount_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Calculated discount amount',
  `grand_total` decimal(10,2) NOT NULL COMMENT 'Final amount after discount',
  `amount_paid` decimal(10,2) NOT NULL COMMENT 'Amount received from the customer',
  `change_amount` decimal(10,2) NOT NULL COMMENT 'Change given back to the customer',
  `payment_method` varchar(50) DEFAULT 'Cash' COMMENT 'e.g., Cash, Card, Mobile Money',
  `status` varchar(50) DEFAULT 'Completed' COMMENT 'e.g., Completed, Refunded, Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`sale_id`, `transaction_code`, `cashier_id`, `customer_name`, `sale_date`, `subtotal`, `discount_percentage`, `discount_amount`, `grand_total`, `amount_paid`, `change_amount`, `payment_method`, `status`, `created_at`, `updated_at`) VALUES
(4, 'SALE-20250619-020708-91637', 1, '0', '2025-06-19 01:07:08', 1050.00, 1.00, 10.50, 1039.50, 1039.50, 0.00, 'Cash', 'Completed', '2025-06-19 00:07:08', '2025-06-19 00:07:08'),
(5, 'SALE-20250619-031103-49204', 2, '0', '2025-06-19 02:11:03', 1200.00, 10.00, 120.00, 1080.00, 1080.00, 0.00, 'Cash', 'Completed', '2025-06-19 01:11:03', '2025-06-19 01:11:03'),
(6, 'SALE-20250619-032825-91e6b', 4, '0', '2025-06-19 02:28:25', 250.00, 0.00, 0.00, 250.00, 250.00, 0.00, 'Cash', 'Completed', '2025-06-19 01:28:25', '2025-06-19 01:28:25');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `line_total` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `unit_price`, `line_total`, `created_at`, `updated_at`) VALUES
(1, 4, 1, 1, 250.00, 250.00, '2025-06-19 00:07:08', '2025-06-19 00:07:08'),
(2, 4, 5, 1, 400.00, 400.00, '2025-06-19 00:07:08', '2025-06-19 00:07:08'),
(3, 4, 3, 1, 400.00, 400.00, '2025-06-19 00:07:08', '2025-06-19 00:07:08'),
(4, 5, 4, 1, 1200.00, 1200.00, '2025-06-19 01:11:03', '2025-06-19 01:11:03'),
(5, 6, 1, 1, 250.00, 250.00, '2025-06-19 01:28:25', '2025-06-19 01:28:25');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `cashier_name` varchar(100) NOT NULL,
  `customer_name` varchar(255) DEFAULT 'Walk-in Customer',
  `transaction_date` datetime NOT NULL,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `change_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','manager','cashier') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password_hash`, `role`, `created_at`) VALUES
(1, 'Admin1', 'Admin1', '$2y$10$fZJ4SpgHClQjMqvJesIo/u1SIPOeu7t9kLM04Uvvu3O2qS1535jQ2', 'admin', '2025-06-13 02:22:52'),
(2, 'Manager1', 'Manager1', '$2y$10$fZJ4SpgHClQjMqvJesIo/u1SIPOeu7t9kLM04Uvvu3O2qS1535jQ2', 'manager', '2025-06-13 02:30:49'),
(4, NULL, 'Cashier1', '$2y$10$nlglc/kR4D.DCi3GWi09kuRbRd05QiNsN8g/dl2VDM2kdrVUdNTD.', 'cashier', '2025-06-15 14:58:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`sale_id`),
  ADD UNIQUE KEY `transaction_code` (`transaction_code`),
  ADD KEY `cashier_id` (`cashier_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cashier_id_new` (`cashier_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_cashier_id_new` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
