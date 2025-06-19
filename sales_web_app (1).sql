-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 15, 2025 at 05:33 PM
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
(1, 'Coca-Cola 50cl', 'Soft drink', 250.00, 500, '2025-06-14 03:04:40', '2025-06-14 03:04:40'),
(2, 'Peak Milk (Small)', 'Powdered milk sachet', 150.00, 300, '2025-06-14 03:04:40', '2025-06-14 03:04:40'),
(3, 'Indomie Noodles (Hungry Man)', 'Instant noodles', 400.00, 200, '2025-06-14 03:04:40', '2025-06-14 03:04:40'),
(4, 'Detergent Powder (1kg)', 'Washing powder', 1200.00, 100, '2025-06-14 03:04:40', '2025-06-14 03:04:40');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `product` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `sale_date` date DEFAULT NULL,
  `cashier_name` varchar(255) DEFAULT NULL,
  `role` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `transaction_id`, `product`, `price`, `quantity`, `subtotal`, `sale_date`, `cashier_name`, `role`) VALUES
(1, 'sale_684bfe350fce5', 'sugar', 400.00, 2, 800.00, '2025-06-13', NULL, 'admin'),
(2, 'sale_684bfe350fce5', 'salt', 500.00, 5, 2500.00, '2025-06-13', NULL, 'admin'),
(3, 'sale_684bff1c1c871', 'sugar', 400.00, 2, 800.00, '2025-06-13', NULL, 'admin'),
(4, 'sale_684bff1c1c871', 'salt', 500.00, 5, 2500.00, '2025-06-13', NULL, 'admin'),
(5, 'sale_684bff2e0978e', 'sugar', 400.00, 3, 1200.00, '2025-06-13', NULL, 'admin'),
(6, 'sale_684c00bfa8807', 'detergent', 1000.00, 2, 2000.00, '2025-06-13', NULL, 'admin'),
(7, 'sale_684c00bfa8807', 'key soap', 300.00, 3, 900.00, '2025-06-13', NULL, 'admin'),
(8, 'sale_684c027bcdedd', 'biscuit', 200.00, 1, 200.00, '2025-06-13', NULL, 'manager'),
(9, 'sale_684dcbe17ba04', 'soap', 400.00, 4, 1600.00, '2025-06-14', NULL, 'admin'),
(10, 'sale_684dcbe17ba04', 'spaghetti', 1000.00, 2, 2000.00, '2025-06-14', NULL, 'admin');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `cashier_name` varchar(100) NOT NULL,
  `transaction_date` datetime NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
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
(4, NULL, 'Felix A', '$2y$10$GnNsyhMfXimfWuHMuJKE1.1pJHBLYIodX/FNGPI4nb7KXTBSeoiVq', 'cashier', '2025-06-15 14:58:24');

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
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
