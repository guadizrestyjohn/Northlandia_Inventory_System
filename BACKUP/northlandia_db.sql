  -- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 02, 2026 at 02:46 PM
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
-- Database: `northlandia_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `business_records`
--

CREATE TABLE `business_records` (
  `record_id` int(11) NOT NULL,
  `record_type` varchar(50) NOT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `record_date` date NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `customer_name`, `contact_number`, `email`, `address`, `created_at`) VALUES
(5, 'Helopew', NULL, NULL, NULL, '2026-09-24 09:25:04'),
(7, 'Pedro', NULL, NULL, NULL, '2026-09-24 13:40:54');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_categories`
--

CREATE TABLE `inventory_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_categories`
--

INSERT INTO `inventory_categories` (`category_id`, `category_name`, `description`) VALUES
(1, 'Materials', 'General materials used for production'),
(2, 'Raw Materials', 'Raw materials used in production'),
(3, 'Tools', 'Tools and equipment used in operations'),
(4, 'Finished Products', 'Completed products ready for release'),
(5, 'Modulars', 'Modular products or components');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `inventory_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `size` varchar(10) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(30) NOT NULL,
  `status` enum('Available','Low Stock','Out of Stock') NOT NULL DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`inventory_id`, `category_id`, `item_name`, `size`, `quantity`, `unit`, `status`, `created_at`) VALUES
(1, 4, 'Plywoods', 'M', 105.00, 'pcs', 'Available', '2026-09-24 08:45:12'),
(2, 4, 'rese', 'S', 20.00, 'kg', 'Available', '2026-09-24 10:05:12'),
(3, 1, 'PLYsA', 'S', 100.00, '123', 'Available', '2026-09-24 10:10:39'),
(4, 1, 'Plywood', 'S', 1.00, 'pcs', 'Available', '2026-09-24 13:04:39'),
(5, 2, 'Door Chair', 'S', 0.00, 'pcs', 'Available', '2026-09-26 11:43:55');

-- --------------------------------------------------------

--
-- Table structure for table `job_orders`
--

CREATE TABLE `job_orders` (
  `job_order_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `production_assignment` varchar(100) DEFAULT NULL,
  `production_start_date` date DEFAULT NULL,
  `production_end_date` date DEFAULT NULL,
  `customer_approval` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `status` varchar(30) NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_orders`
--

INSERT INTO `job_orders` (`job_order_id`, `order_id`, `production_assignment`, `production_start_date`, `production_end_date`, `customer_approval`, `status`, `created_at`) VALUES
(1, 8, 'Production Staff', '2026-10-01', '2026-10-05', 'Approved', 'Completed', '2026-10-01 11:15:41'),
(2, 7, 'Production Staff', '2026-10-02', '2026-10-06', 'Approved', 'Completed', '2026-10-01 11:44:28');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `specifications` text DEFAULT NULL,
  `order_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `downpayment` decimal(10,2) DEFAULT 0.00,
  `delivery_method` varchar(50) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `customer_id`, `order_date`, `product_name`, `quantity`, `specifications`, `downpayment`, `delivery_method`, `status`, `created_at`) VALUES
(5, 5, '2026-12-22', 'Iced Coffee', 0.00, 'wqd', 10.00, 'Pickup', 'Processing', '2026-09-24 10:10:24'),
(7, 5, '2026-12-06', 'Plywood', 0.00, '321', 100.00, 'Pickup', 'Ready for Stock Out', '2026-09-24 13:49:27'),
(8, 7, '2026-12-10', 'PLYsA', 0.00, 'fef', 99.99, 'Pickup', 'Ready for Stock Out', '2026-09-25 12:37:13');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(6, 'Administrative Staff'),
(1, 'Administrator'),
(5, 'Inventory Staff'),
(2, 'Manager'),
(4, 'Production Staff'),
(3, 'Sales Staff');

-- --------------------------------------------------------

--
-- Table structure for table `stock_transactions`
--

CREATE TABLE `stock_transactions` (
  `transaction_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `transaction_type` enum('Stock In','Stock Out') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_transactions`
--

INSERT INTO `stock_transactions` (`transaction_id`, `inventory_id`, `transaction_type`, `quantity`, `reference_no`, `remarks`, `transaction_date`, `user_id`) VALUES
(1, 1, 'Stock Out', 5.00, 'SO-0001', 'May bumili', '2026-09-24 08:46:02', 1),
(2, 2, 'Stock In', 100.00, 'PO-0001', 'dwq', '2026-09-24 10:31:47', 1),
(3, 1, 'Stock In', 100.00, 'wdqw', 'dwq', '2026-09-24 10:32:02', 1),
(4, 2, 'Stock Out', 90.00, '213213', '2312', '2026-09-24 10:32:18', 1),
(5, 3, 'Stock In', 213.00, 'SO-0001', 'dwq', '2026-09-24 13:05:14', 1),
(6, 3, 'Stock Out', 123.00, '23', '321', '2026-09-24 13:05:20', 1),
(7, 4, 'Stock In', 1000.00, 'PO-0001', 'Hello', '2026-09-26 11:43:33', 1),
(8, 5, 'Stock Out', 100.00, 'PO-0001', '', '2026-09-26 11:44:38', 1),
(9, 3, 'Stock Out', 12.00, 'PO-0001', '23', '2026-09-26 11:56:28', 1),
(10, 4, 'Stock Out', 1000.00, 'PO-0001', '', '2026-09-26 12:43:28', 1),
(11, 4, 'Stock Out', 120.00, 'PO-0001', 'Pos', '2026-09-26 12:43:59', 1),
(12, 4, 'Stock Out', 2.00, '09', '', '2026-09-26 12:45:25', 1),
(13, 3, 'Stock In', 10.00, 'SI-2026-002', '', '2026-10-02 11:23:23', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tax_settings`
--

CREATE TABLE `tax_settings` (
  `tax_id` int(11) NOT NULL,
  `tax_name` varchar(50) NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role_id` int(11) NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `role_id`, `status`) VALUES
(1, 'admin', '$2y$10$cFTML0uuBAZ1dOwfjc..eOTq4yNzLw.JuFDyhhB.Ka61SHCpHWDJm', 'System Administrator', 1, 'Active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `business_records`
--
ALTER TABLE `business_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `business_records_order_id` (`order_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`);

--
-- Indexes for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`inventory_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `job_orders`
--
ALTER TABLE `job_orders`
  ADD PRIMARY KEY (`job_order_id`),
  ADD UNIQUE KEY `unique_order_id` (`order_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tax_settings`
--
ALTER TABLE `tax_settings`
  ADD PRIMARY KEY (`tax_id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `business_records`
--
ALTER TABLE `business_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `job_orders`
--
ALTER TABLE `job_orders`
  MODIFY `job_order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `tax_settings`
--
ALTER TABLE `tax_settings`
  MODIFY `tax_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `business_records`
--
ALTER TABLE `business_records`
  ADD CONSTRAINT `business_records_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `business_records_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`category_id`);

--
-- Constraints for table `job_orders`
--
ALTER TABLE `job_orders`
  ADD CONSTRAINT `job_orders_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`);

--
-- Constraints for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD CONSTRAINT `stock_transactions_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory_items` (`inventory_id`),
  ADD CONSTRAINT `stock_transactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `tax_settings`
--
ALTER TABLE `tax_settings`
  ADD CONSTRAINT `tax_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`);

-- Current production and release workflow fields/relationships.
ALTER TABLE `job_orders`
  ADD COLUMN `production_completed_at` datetime DEFAULT NULL AFTER `production_end_date`,
  ADD COLUMN `production_completed_by` int(11) DEFAULT NULL AFTER `production_completed_at`;

ALTER TABLE `stock_transactions`
  ADD COLUMN `order_id` int(11) DEFAULT NULL AFTER `inventory_id`,
  ADD COLUMN `job_order_id` int(11) DEFAULT NULL AFTER `order_id`,
  ADD KEY `stock_transactions_order_id` (`order_id`),
  ADD UNIQUE KEY `stock_transactions_job_order_unique` (`job_order_id`);

ALTER TABLE `stock_transactions`
  ADD CONSTRAINT `stock_transactions_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `stock_transactions_job_order_fk` FOREIGN KEY (`job_order_id`) REFERENCES `job_orders` (`job_order_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `job_orders`
  ADD CONSTRAINT `job_orders_production_completed_by_fk` FOREIGN KEY (`production_completed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Dedicated Records Management tables. These match records_management_migration.sql.
CREATE TABLE IF NOT EXISTS `purchase_records` (
  `purchase_record_id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(50) NOT NULL,
  `supplier_name` varchar(150) DEFAULT NULL,
  `purchase_date` date NOT NULL,
  `items_details` text NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Recorded',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`purchase_record_id`),
  UNIQUE KEY `purchase_records_reference_no` (`reference_no`),
  KEY `purchase_records_purchase_date` (`purchase_date`),
  KEY `purchase_records_created_by` (`created_by`),
  CONSTRAINT `purchase_records_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `sales_records` (
  `sales_record_id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(50) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `sales_date` date NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Recorded',
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`sales_record_id`),
  UNIQUE KEY `sales_records_reference_no` (`reference_no`),
  KEY `sales_records_order_id` (`order_id`),
  KEY `sales_records_sales_date` (`sales_date`),
  KEY `sales_records_created_by` (`created_by`),
  CONSTRAINT `sales_records_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `sales_records_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `receipt_records` (
  `receipt_record_id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(50) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `receipt_date` date NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_type` enum('Downpayment','Additional') NOT NULL DEFAULT 'Additional',
  `status` varchar(30) NOT NULL DEFAULT 'Recorded',
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`receipt_record_id`),
  UNIQUE KEY `receipt_records_reference_no` (`reference_no`),
  KEY `receipt_records_order_id` (`order_id`),
  KEY `receipt_records_receipt_date` (`receipt_date`),
  KEY `receipt_records_created_by` (`created_by`),
  CONSTRAINT `receipt_records_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `receipt_records_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `invoice_records` (
  `invoice_record_id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(50) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Recorded',
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`invoice_record_id`),
  UNIQUE KEY `invoice_records_reference_no` (`reference_no`),
  KEY `invoice_records_order_id` (`order_id`),
  KEY `invoice_records_invoice_date` (`invoice_date`),
  KEY `invoice_records_created_by` (`created_by`),
  CONSTRAINT `invoice_records_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `invoice_records_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `bidding_document_records` (
  `bidding_document_record_id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `client_name` varchar(150) DEFAULT NULL,
  `submission_date` date NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Recorded',
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`bidding_document_record_id`),
  UNIQUE KEY `bidding_document_records_reference_no` (`reference_no`),
  KEY `bidding_document_records_submission_date` (`submission_date`),
  KEY `bidding_document_records_created_by` (`created_by`),
  CONSTRAINT `bidding_document_records_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Preserve and copy legacy record data in the same idempotent way as the migration.
INSERT INTO `purchase_records` (`reference_no`, `purchase_date`, `items_details`, `amount`, `status`, `created_by`, `created_at`)
SELECT COALESCE(NULLIF(br.`reference_no`, ''), CONCAT('LEGACY-BR-', br.`record_id`)), br.`record_date`, COALESCE(br.`description`, ''), br.`amount`, 'Recorded', br.`created_by`, br.`created_at`
FROM `business_records` br WHERE br.`record_type` = 'Purchase'
AND NOT EXISTS (SELECT 1 FROM `purchase_records` pr WHERE pr.`reference_no` = COALESCE(NULLIF(br.`reference_no`, ''), CONCAT('LEGACY-BR-', br.`record_id`)));

INSERT INTO `sales_records` (`reference_no`, `order_id`, `sales_date`, `amount`, `status`, `description`, `created_by`, `created_at`)
SELECT COALESCE(NULLIF(br.`reference_no`, ''), CONCAT('LEGACY-BR-', br.`record_id`)), br.`order_id`, br.`record_date`, br.`amount`, 'Recorded', br.`description`, br.`created_by`, br.`created_at`
FROM `business_records` br WHERE br.`record_type` = 'Sales'
AND NOT EXISTS (SELECT 1 FROM `sales_records` sr WHERE sr.`reference_no` = COALESCE(NULLIF(br.`reference_no`, ''), CONCAT('LEGACY-BR-', br.`record_id`)));

INSERT INTO `sales_records` (`reference_no`, `order_id`, `sales_date`, `amount`, `status`, `description`, `created_by`, `created_at`)
SELECT CONCAT('SALE-ORD-', o.`order_id`), o.`order_id`, o.`order_date`, NULL, o.`status`, CONCAT_WS(' | ', CONCAT('Product: ', o.`product_name`), CONCAT('Quantity: ', o.`quantity`), NULLIF(o.`specifications`, ''), CONCAT('Delivery: ', COALESCE(o.`delivery_method`, 'Not specified'))), NULL, o.`created_at`
FROM `orders` o WHERE o.`status` = 'Completed'
AND NOT EXISTS (SELECT 1 FROM `sales_records` sr WHERE sr.`order_id` = o.`order_id` OR sr.`reference_no` = CONCAT('SALE-ORD-', o.`order_id`));

INSERT INTO `receipt_records` (`reference_no`, `order_id`, `receipt_date`, `amount`, `status`, `description`, `created_by`, `created_at`)
SELECT COALESCE(NULLIF(br.`reference_no`, ''), CONCAT('LEGACY-BR-', br.`record_id`)), br.`order_id`, br.`record_date`, br.`amount`, 'Recorded', br.`description`, br.`created_by`, br.`created_at`
FROM `business_records` br WHERE br.`record_type` = 'Receipt'
AND NOT EXISTS (SELECT 1 FROM `receipt_records` rr WHERE rr.`reference_no` = COALESCE(NULLIF(br.`reference_no`, ''), CONCAT('LEGACY-BR-', br.`record_id`)));

INSERT INTO `invoice_records` (`reference_no`, `order_id`, `invoice_date`, `amount`, `status`, `description`, `created_by`, `created_at`)
SELECT COALESCE(NULLIF(br.`reference_no`, ''), CONCAT('LEGACY-BR-', br.`record_id`)), br.`order_id`, br.`record_date`, br.`amount`, 'Recorded', br.`description`, br.`created_by`, br.`created_at`
FROM `business_records` br WHERE br.`record_type` = 'Invoice'
AND NOT EXISTS (SELECT 1 FROM `invoice_records` ir WHERE ir.`reference_no` = COALESCE(NULLIF(br.`reference_no`, ''), CONCAT('LEGACY-BR-', br.`record_id`)));

INSERT INTO `bidding_document_records` (`reference_no`, `title`, `submission_date`, `status`, `description`, `created_by`, `created_at`)
SELECT COALESCE(NULLIF(br.`reference_no`, ''), CONCAT('LEGACY-BR-', br.`record_id`)), LEFT(COALESCE(NULLIF(br.`description`, ''), br.`reference_no`, CONCAT('Legacy bidding document ', br.`record_id`)), 150), br.`record_date`, 'Recorded', br.`description`, br.`created_by`, br.`created_at`
FROM `business_records` br WHERE br.`record_type` = 'Bidding Document'
AND NOT EXISTS (SELECT 1 FROM `bidding_document_records` bdr WHERE bdr.`reference_no` = COALESCE(NULLIF(br.`reference_no`, ''), CONCAT('LEGACY-BR-', br.`record_id`)));
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
