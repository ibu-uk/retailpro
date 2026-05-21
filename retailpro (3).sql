-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 21, 2026 at 08:34 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `retailpro`
--

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `manager_name` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `name`, `address`, `phone`, `manager_name`, `is_active`, `created_at`) VALUES
(1, 'Main Branch', 'Block 4, Shop 12, Salmiya, Kuwait', '+965 2244-1100', 'Ahmed Karim', 1, '2026-05-20 07:49:57'),
(2, 'Al-Salmiya', 'Salmiya, Block 7, Shop 21, Kuwait', '+965 2211-4400', 'Sara Nasser', 1, '2026-05-20 07:49:57'),
(3, 'Hawalli', 'Hawalli, Block 8, Shop 3, Kuwait', '+965 2255-3300', 'Omar Nasser', 1, '2026-05-20 07:49:57'),
(4, 'Farwaniya', 'Farwaniya, Block 2, Shop 15, Kuwait', '+965 2299-8800', 'Nadia Saad', 1, '2026-05-20 07:49:57');

-- --------------------------------------------------------

--
-- Table structure for table `branch_categories`
--

CREATE TABLE `branch_categories` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branch_categories`
--

INSERT INTO `branch_categories` (`id`, `branch_id`, `category_id`, `created_at`) VALUES
(1, 1, 1, '2026-05-20 09:22:15'),
(2, 2, 1, '2026-05-20 09:22:15'),
(3, 3, 1, '2026-05-20 09:22:15'),
(4, 4, 1, '2026-05-20 09:22:15'),
(5, 1, 2, '2026-05-20 09:22:15'),
(6, 2, 2, '2026-05-20 09:22:15'),
(7, 3, 2, '2026-05-20 09:22:15'),
(8, 4, 2, '2026-05-20 09:22:15'),
(9, 1, 3, '2026-05-20 09:22:15'),
(10, 2, 3, '2026-05-20 09:22:15'),
(11, 3, 3, '2026-05-20 09:22:15'),
(12, 4, 3, '2026-05-20 09:22:15'),
(13, 1, 4, '2026-05-20 09:22:15'),
(14, 2, 4, '2026-05-20 09:22:15'),
(15, 3, 4, '2026-05-20 09:22:15'),
(16, 4, 4, '2026-05-20 09:22:15'),
(17, 1, 5, '2026-05-20 09:22:15'),
(18, 2, 5, '2026-05-20 09:22:15'),
(19, 3, 5, '2026-05-20 09:22:15'),
(20, 4, 5, '2026-05-20 09:22:15'),
(21, 1, 6, '2026-05-20 09:22:15'),
(22, 2, 6, '2026-05-20 09:22:15'),
(23, 3, 6, '2026-05-20 09:22:15'),
(24, 4, 6, '2026-05-20 09:22:15');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `emoji` varchar(10) DEFAULT '?',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `name_ar` varchar(80) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `emoji`, `created_at`, `name_ar`, `parent_id`, `is_active`, `description`) VALUES
(1, 'Bags', '👜', '2026-05-20 07:49:57', 'حقائب', NULL, 1, NULL),
(2, 'Watches', '⌚', '2026-05-20 07:49:57', 'ساعات', NULL, 1, NULL),
(3, 'Clothes', '👕', '2026-05-20 07:49:57', 'ملابس', NULL, 1, NULL),
(4, 'Accessories', '💍', '2026-05-20 07:49:57', 'إكسسوارات', NULL, 1, NULL),
(5, 'Shoes', '👟', '2026-05-20 07:49:57', 'أحذية', NULL, 1, NULL),
(6, 'Wallets', '👛', '2026-05-20 07:49:57', 'محافظ', NULL, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `chart_of_accounts`
--

CREATE TABLE `chart_of_accounts` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `name_ar` varchar(100) DEFAULT NULL,
  `type` enum('asset','liability','equity','revenue','expense','cogs') NOT NULL,
  `category` varchar(60) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `balance` decimal(12,3) DEFAULT 0.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chart_of_accounts`
--

INSERT INTO `chart_of_accounts` (`id`, `code`, `name`, `name_ar`, `type`, `category`, `is_active`, `balance`) VALUES
(1, '1000', 'Cash on Hand', 'النقدية', 'asset', 'current_asset', 1, 0.000),
(2, '1020', 'Accounts Receivable', 'الذمم المدينة', 'asset', 'current_asset', 1, 0.000),
(3, '1100', 'Inventory', 'المخزون', 'asset', 'current_asset', 1, 0.000),
(4, '2000', 'Accounts Payable', 'الذمم الدائنة', 'liability', 'current_liability', 1, 0.000),
(5, '3000', 'Owner Equity', 'حقوق الملكية', 'equity', 'equity', 1, 0.000),
(6, '3100', 'Retained Earnings', 'الأرباح المحتجزة', 'equity', 'equity', 1, 0.000),
(7, '4000', 'Sales Revenue', 'إيرادات المبيعات', 'revenue', 'revenue', 1, 0.000),
(8, '5000', 'Cost of Goods Sold', 'تكلفة البضاعة المباعة', 'cogs', 'cogs', 1, 0.000),
(9, '6000', 'Rent Expense', 'مصروف الإيجار', 'expense', 'operating', 1, 0.000),
(10, '6010', 'Salary Expense', 'مصروف الرواتب', 'expense', 'operating', 1, 0.000),
(11, '6020', 'Utilities Expense', 'مصروف الخدمات', 'expense', 'operating', 1, 0.000),
(12, '6030', 'Marketing Expense', 'مصروف التسويق', 'expense', 'operating', 1, 0.000),
(13, '6040', 'Other Operating Expense', 'مصروفات تشغيلية أخرى', 'expense', 'operating', 1, 0.000);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `type` enum('retail','wholesale') DEFAULT 'retail',
  `credit_limit` decimal(10,3) DEFAULT 0.000,
  `balance` decimal(10,3) DEFAULT 0.000,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `name_ar` varchar(150) DEFAULT NULL,
  `address_ar` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `email`, `phone`, `type`, `credit_limit`, `balance`, `address`, `is_active`, `created_at`, `name_ar`, `address_ar`) VALUES
(1, 'Walk-in Customer', '', '', 'retail', 0.000, 0.000, NULL, 1, '2026-05-20 07:49:58', NULL, NULL),
(2, 'Ahmad Al-Mutairi', 'ahmad@email.com', '+965 9988-7766', 'retail', 1000.000, -840.000, NULL, 1, '2026-05-20 07:49:58', NULL, NULL),
(3, 'Fatima Al-Rashidi', 'fatima@email.com', '+965 6677-5544', 'wholesale', 5000.000, 250.000, NULL, 1, '2026-05-20 07:49:58', NULL, NULL),
(4, 'Kuwait National Co.', 'purchasing@knc.kw', '+965 2244-1100', 'wholesale', 20000.000, -4200.000, NULL, 1, '2026-05-20 07:49:58', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `category` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,3) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `payment_mode` enum('cash','knet','transfer') DEFAULT 'cash',
  `receipt_ref` varchar(60) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `category`, `description`, `amount`, `branch_id`, `payment_mode`, `receipt_ref`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Rent', 'Monthly rent — Main Branch', 850.000, 1, 'transfer', NULL, 1, '2026-05-20 07:49:58', '2026-05-20 07:49:58'),
(2, 'Utilities', 'Electricity & Water — Jan 2025', 120.000, 1, 'cash', NULL, 1, '2026-05-20 07:49:58', '2026-05-20 07:49:58'),
(3, 'Salary', 'Staff salaries — Jan 2025', 3200.000, 1, 'transfer', NULL, 1, '2026-05-20 07:49:58', '2026-05-20 07:49:58'),
(4, 'Marketing', 'Social media ads — Jan 2025', 200.000, NULL, 'transfer', NULL, 1, '2026-05-20 07:49:58', '2026-05-20 07:49:58'),
(5, 'Rent', 'Monthly rent — Al-Salmiya', 650.000, 2, 'transfer', NULL, 1, '2026-05-20 07:49:58', '2026-05-20 07:49:58');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(30) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `sale_type` enum('retail','wholesale','credit') DEFAULT 'retail',
  `payment_mode` enum('cash','knet','wamd','transfer','credit','partial') DEFAULT 'cash',
  `subtotal` decimal(10,3) DEFAULT 0.000,
  `discount` decimal(10,3) DEFAULT 0.000,
  `vat` decimal(10,3) DEFAULT 0.000,
  `total` decimal(10,3) DEFAULT 0.000,
  `paid_amount` decimal(10,3) DEFAULT 0.000,
  `status` enum('paid','partial','credit','refunded') DEFAULT 'paid',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `customer_id`, `branch_id`, `sale_type`, `payment_mode`, `subtotal`, `discount`, `vat`, `total`, `paid_amount`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'INV-2025-0001', 2, 1, 'retail', 'cash', 25.000, 0.000, 1.250, 26.250, 26.250, 'paid', NULL, 3, '2026-05-20 07:49:58', '2026-05-20 07:49:58'),
(2, 'INV-2025-0002', 3, 1, 'wholesale', 'knet', 60.000, 0.000, 3.000, 63.000, 63.000, 'paid', NULL, 3, '2026-05-20 07:49:58', '2026-05-20 07:49:58'),
(3, 'INV-2025-0003', 4, 1, 'credit', 'credit', 200.000, 0.000, 10.000, 210.000, 0.000, 'credit', NULL, 3, '2026-05-20 07:49:58', '2026-05-20 07:49:58');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) DEFAULT 1,
  `unit_price` decimal(10,3) DEFAULT 0.000,
  `disc_pct` decimal(5,2) DEFAULT 0.00,
  `discount` decimal(10,3) DEFAULT 0.000,
  `total` decimal(10,3) DEFAULT 0.000,
  `batch_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_items`
--

INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `qty`, `unit_price`, `disc_pct`, `discount`, `total`, `batch_id`, `supplier_id`) VALUES
(1, 1, 1, 1, 25.000, 0.00, 0.000, 25.000, NULL, NULL),
(2, 2, 3, 5, 12.000, 0.00, 0.000, 60.000, NULL, NULL),
(3, 3, 9, 4, 15.000, 0.00, 0.000, 60.000, NULL, NULL),
(4, 3, 1, 2, 25.000, 0.00, 0.000, 50.000, NULL, NULL),
(5, 3, 3, 5, 12.000, 0.00, 0.000, 60.000, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `journal_entries`
--

CREATE TABLE `journal_entries` (
  `id` int(11) NOT NULL,
  `entry_date` date NOT NULL,
  `reference` varchar(60) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `type` enum('sale','purchase','expense','payment_in','payment_out','adjustment') NOT NULL,
  `debit_account` varchar(60) NOT NULL,
  `credit_account` varchar(60) NOT NULL,
  `amount` decimal(12,3) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `offers`
--

CREATE TABLE `offers` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('percent','bogo','promo_code','fixed') DEFAULT 'percent',
  `discount_value` decimal(10,3) DEFAULT 0.000,
  `promo_code` varchar(30) DEFAULT NULL,
  `applies_to` varchar(60) DEFAULT 'all',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `usage_limit` int(11) DEFAULT 0,
  `usage_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `min_purchase` decimal(10,3) DEFAULT 0.000,
  `max_discount` decimal(10,3) DEFAULT 0.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `offers`
--

INSERT INTO `offers` (`id`, `title`, `description`, `type`, `discount_value`, `promo_code`, `applies_to`, `start_date`, `end_date`, `usage_limit`, `usage_count`, `is_active`, `created_at`, `min_purchase`, `max_discount`) VALUES
(1, 'Summer Sale 2025', '20% off all Bags', 'percent', 20.000, NULL, '1', '2025-01-15', '2025-01-31', 100, 65, 1, '2026-05-20 07:49:58', 0.000, 0.000),
(2, 'Buy 1 Get 1 Free', 'T-Shirts & Caps', 'bogo', 0.000, NULL, '3', '2025-01-10', '2025-01-20', 100, 40, 1, '2026-05-20 07:49:58', 0.000, 0.000),
(3, 'Eid Special Bundle', '15% off — Code EID2025', 'promo_code', 15.000, NULL, 'all', '2025-02-01', '2025-02-28', 200, 0, 1, '2026-05-20 07:49:58', 0.000, 0.000);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `type` enum('customer','supplier') NOT NULL,
  `reference_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `amount` decimal(10,3) NOT NULL,
  `payment_mode` enum('cash','knet','wamd','transfer') DEFAULT 'cash',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `sku` varchar(60) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `brand` varchar(80) DEFAULT NULL,
  `cost_price` decimal(10,3) DEFAULT 0.000,
  `retail_price` decimal(10,3) DEFAULT 0.000,
  `wholesale_price` decimal(10,3) DEFAULT 0.000,
  `color` varchar(80) DEFAULT NULL,
  `size` varchar(80) DEFAULT NULL,
  `unit_type` enum('piece','box','unit') DEFAULT 'piece',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `name_ar` varchar(150) DEFAULT NULL,
  `barcode` varchar(60) DEFAULT NULL,
  `emoji` varchar(10) DEFAULT '?',
  `sub_category_id` int(11) DEFAULT NULL,
  `origin_country` varchar(80) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `has_expiry` tinyint(1) DEFAULT 0,
  `expiry_alert_days` int(11) DEFAULT 90,
  `last_supplier_id` int(11) DEFAULT NULL,
  `last_purchase_price` decimal(10,3) DEFAULT NULL,
  `last_purchase_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `sku`, `category_id`, `brand`, `cost_price`, `retail_price`, `wholesale_price`, `color`, `size`, `unit_type`, `description`, `is_active`, `created_at`, `name_ar`, `barcode`, `emoji`, `sub_category_id`, `origin_country`, `updated_at`, `has_expiry`, `expiry_alert_days`, `last_supplier_id`, `last_purchase_price`, `last_purchase_date`) VALUES
(1, 'Chanel Mini Bag', 'BAG-2841', 1, 'Chanel', 12.000, 25.000, 20.000, 'Black', 'Mini', 'piece', NULL, 1, '2026-05-20 07:49:58', NULL, NULL, '📦', NULL, NULL, '2026-05-20 07:49:58', 0, 90, NULL, NULL, NULL),
(2, 'Fossil Watch', 'WTC-0291', 2, 'Fossil', 15.000, 30.000, 24.000, 'Silver', 'One Size', 'piece', NULL, 1, '2026-05-20 07:49:58', NULL, NULL, '📦', NULL, NULL, '2026-05-20 07:49:58', 0, 90, NULL, NULL, NULL),
(3, 'Premium T-Shirt', 'CLT-8821', 3, 'Generic', 5.000, 12.000, 9.000, 'White', 'M', 'piece', NULL, 1, '2026-05-20 07:49:58', NULL, NULL, '📦', NULL, NULL, '2026-05-20 07:49:58', 0, 90, NULL, NULL, NULL),
(4, 'Leather Wallet', 'WLT-4421', 6, 'Generic', 4.000, 10.000, 8.000, 'Brown', 'Standard', 'piece', NULL, 1, '2026-05-20 07:49:58', NULL, NULL, '📦', NULL, NULL, '2026-05-20 07:49:58', 0, 90, NULL, NULL, NULL),
(5, 'Summer Cap', 'CAP-1102', 4, 'Generic', 2.000, 5.000, 3.500, 'Beige', 'Free Size', 'piece', NULL, 1, '2026-05-20 07:49:58', NULL, NULL, '📦', NULL, NULL, '2026-05-20 07:49:58', 0, 90, NULL, NULL, NULL),
(6, 'Nike Air Max', 'SHO-0812', 5, 'Nike', 18.000, 28.000, 22.000, 'White', '42', 'piece', NULL, 1, '2026-05-20 07:49:58', NULL, NULL, '📦', NULL, NULL, '2026-05-20 07:49:58', 0, 90, NULL, NULL, NULL),
(7, 'Black Denim Jeans', 'CLT-0441', 3, 'Generic', 8.000, 18.000, 14.000, 'Black', 'M', 'piece', NULL, 1, '2026-05-20 07:49:58', NULL, NULL, '📦', NULL, NULL, '2026-05-20 13:20:16', 0, 90, 2, 12.000, '2026-05-20'),
(8, 'Silver Bracelet', 'ACC-1201', 4, 'Generic', 3.000, 8.500, 6.000, 'Silver', 'One Size', 'piece', NULL, 1, '2026-05-20 07:49:58', NULL, NULL, '📦', NULL, NULL, '2026-05-20 07:49:58', 0, 90, NULL, NULL, NULL),
(9, 'Tote Bag (L)', 'BAG-3312', 1, 'Generic', 6.000, 15.000, 12.000, 'Tan', 'L', 'piece', NULL, 1, '2026-05-20 07:49:58', NULL, NULL, '📦', NULL, NULL, '2026-05-20 07:49:58', 0, 90, NULL, NULL, NULL),
(10, 'Casio F91W', 'WTC-0080', 2, 'Casio', 2.000, 6.000, 4.500, 'Black', 'One Size', 'piece', NULL, 1, '2026-05-20 07:49:58', NULL, NULL, '📦', NULL, NULL, '2026-05-20 07:49:58', 0, 90, NULL, NULL, NULL),
(11, 'Polo Shirt', 'CLT-9901', 3, 'Generic', 4.000, 9.000, 7.000, 'Navy', 'M', 'piece', NULL, 1, '2026-05-20 07:49:58', NULL, NULL, '📦', NULL, NULL, '2026-05-20 07:49:58', 0, 90, NULL, NULL, NULL),
(12, 'Sunglasses', 'ACC-4401', 4, 'Generic', 5.000, 12.000, 9.000, 'Black', 'One Size', 'piece', NULL, 1, '2026-05-20 07:49:58', NULL, NULL, '📦', NULL, NULL, '2026-05-20 07:49:58', 0, 90, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_suppliers`
--

CREATE TABLE `product_suppliers` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `cost_price` decimal(10,3) NOT NULL DEFAULT 0.000,
  `min_order_qty` int(11) DEFAULT 1,
  `lead_days` int(11) DEFAULT 7,
  `is_preferred` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_suppliers`
--

INSERT INTO `product_suppliers` (`id`, `product_id`, `supplier_id`, `cost_price`, `min_order_qty`, `lead_days`, `is_preferred`, `notes`, `updated_at`) VALUES
(1, 7, 2, 12.000, 150, 7, 1, '', '2026-05-20 13:20:16');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `po_number` varchar(30) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `status` enum('pending','partial','completed','cancelled') DEFAULT 'pending',
  `total_amount` decimal(10,3) DEFAULT 0.000,
  `paid_amount` decimal(10,3) DEFAULT 0.000,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `po_number`, `supplier_id`, `branch_id`, `status`, `total_amount`, `paid_amount`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'PO-2025-0041', 1, 1, 'completed', 4800.000, 4800.000, NULL, 2, '2026-05-20 07:49:58', '2026-05-20 07:49:58'),
(2, 'PO-2025-0040', 2, 1, 'partial', 8200.000, 0.000, NULL, 2, '2026-05-20 07:49:58', '2026-05-20 07:49:58'),
(3, 'PO-2025-0039', 3, 1, 'pending', 12400.000, 0.000, NULL, 2, '2026-05-20 07:49:58', '2026-05-20 07:49:58');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty_ordered` int(11) DEFAULT 0,
  `qty_received` int(11) DEFAULT 0,
  `unit_cost` decimal(10,3) DEFAULT 0.000,
  `expiry_date` date DEFAULT NULL,
  `lot_number` varchar(80) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(80) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'company_name', 'RetailPro Kuwait LLC', '2026-05-20 07:49:58'),
(2, 'vat_number', 'KWT-30082024-00841', '2026-05-20 07:49:58'),
(3, 'address', 'Block 4, Shop 12, Salmiya, Kuwait', '2026-05-20 07:49:58'),
(4, 'phone', '+965 2244-1100', '2026-05-20 07:49:58'),
(5, 'currency', 'KWD', '2026-05-20 07:49:58'),
(6, 'vat_rate', '5', '2026-05-20 07:49:58'),
(7, 'invoice_prefix', 'INV-', '2026-05-20 07:49:58'),
(8, 'invoice_footer', 'Thank you for shopping with RetailPro. Returns accepted within 7 days with receipt.', '2026-05-20 07:49:58'),
(9, 'tax_type', 'exclusive', '2026-05-20 07:49:58'),
(10, 'app_version', '2.4.0', '2026-05-20 07:49:58');

-- --------------------------------------------------------

--
-- Table structure for table `stock`
--

CREATE TABLE `stock` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `qty` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock`
--

INSERT INTO `stock` (`id`, `product_id`, `branch_id`, `qty`) VALUES
(1, 1, 1, 18),
(2, 1, 2, 5),
(3, 1, 3, 2),
(4, 1, 4, 1),
(5, 2, 1, 12),
(6, 2, 2, 8),
(7, 2, 3, 3),
(8, 2, 4, 2),
(9, 3, 1, 85),
(10, 3, 2, 40),
(11, 3, 3, 20),
(12, 3, 4, 15),
(13, 4, 1, 34),
(14, 4, 2, 20),
(15, 4, 3, 10),
(16, 4, 4, 8),
(17, 5, 1, 62),
(18, 5, 2, 30),
(19, 5, 3, 15),
(20, 5, 4, 10),
(21, 6, 1, 2),
(22, 6, 2, 5),
(23, 6, 3, 1),
(24, 6, 4, 0),
(25, 7, 1, 53),
(26, 7, 2, 8),
(27, 7, 3, 4),
(28, 7, 4, 2),
(29, 8, 1, 24),
(30, 8, 2, 12),
(31, 8, 3, 6),
(32, 8, 4, 4),
(33, 9, 1, 20),
(34, 9, 2, 10),
(35, 9, 3, 5),
(36, 9, 4, 3),
(37, 10, 1, 40),
(38, 10, 2, 20),
(39, 10, 3, 10),
(40, 10, 4, 8),
(41, 11, 1, 55),
(42, 11, 2, 25),
(43, 11, 3, 12),
(44, 11, 4, 8),
(45, 12, 1, 30),
(46, 12, 2, 15),
(47, 12, 3, 8),
(48, 12, 4, 5);

-- --------------------------------------------------------

--
-- Table structure for table `stock_batches`
--

CREATE TABLE `stock_batches` (
  `id` int(11) NOT NULL,
  `batch_number` varchar(60) NOT NULL,
  `product_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `po_id` int(11) DEFAULT NULL,
  `qty_received` int(11) NOT NULL DEFAULT 0,
  `qty_remaining` int(11) NOT NULL DEFAULT 0,
  `cost_price` decimal(10,3) DEFAULT 0.000,
  `expiry_date` date DEFAULT NULL,
  `manufacture_date` date DEFAULT NULL,
  `lot_number` varchar(80) DEFAULT NULL,
  `received_date` date NOT NULL,
  `received_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','low','depleted','expired') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_batches`
--

INSERT INTO `stock_batches` (`id`, `batch_number`, `product_id`, `supplier_id`, `branch_id`, `po_id`, `qty_received`, `qty_remaining`, `cost_price`, `expiry_date`, `manufacture_date`, `lot_number`, `received_date`, `received_by`, `notes`, `status`, `created_at`) VALUES
(1, 'BTCH-20260520-44CE1', 7, 2, 1, NULL, 50, 50, 12.000, '2027-05-20', '2026-05-20', 'BLKJ-0044', '2026-05-20', 1, '', 'active', '2026-05-20 13:20:16');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `type` enum('in','out','transfer','damage','return','adjustment') NOT NULL,
  `qty` int(11) NOT NULL,
  `reference` varchar(60) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `batch_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `type`, `qty`, `reference`, `notes`, `user_id`, `created_at`, `batch_id`, `supplier_id`, `expiry_date`) VALUES
(1, 1, 1, 'in', 24, 'PO-2841', NULL, 2, '2026-05-20 07:49:58', NULL, NULL, NULL),
(2, 2, 1, 'out', -3, 'INV-1044', NULL, 3, '2026-05-20 07:49:58', NULL, NULL, NULL),
(3, 6, 2, 'transfer', 10, 'TRF-0081', NULL, 2, '2026-05-20 07:49:58', NULL, NULL, NULL),
(4, 3, 3, 'damage', -2, 'DAM-0012', NULL, 4, '2026-05-20 07:49:58', NULL, NULL, NULL),
(5, 4, 1, 'return', 1, 'RET-0041', NULL, 3, '2026-05-20 07:49:58', NULL, NULL, NULL),
(6, 7, 1, 'in', 50, 'BTCH-20260520-44CE1', 'Received from supplier — Batch BTCH-20260520-44CE1', 1, '2026-05-20 13:20:16', 1, 2, '2027-05-20'),
(7, 7, 1, 'out', -1, 'INV-2026-0004', NULL, 1, '2026-05-20 13:22:45', NULL, NULL, NULL),
(8, 7, 1, 'return', 1, 'INV-2026-0004', 'Invoice deleted', 1, '2026-05-20 13:29:50', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `company` varchar(150) NOT NULL,
  `contact_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `vat_number` varchar(60) DEFAULT NULL,
  `payment_terms` varchar(30) DEFAULT 'Net 30',
  `balance` decimal(10,3) DEFAULT 0.000,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `company_ar` varchar(150) DEFAULT NULL,
  `contact_name_ar` varchar(100) DEFAULT NULL,
  `address_ar` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `company`, `contact_name`, `email`, `phone`, `vat_number`, `payment_terms`, `balance`, `address`, `is_active`, `created_at`, `company_ar`, `contact_name_ar`, `address_ar`) VALUES
(1, 'Gulf Bags Trading', 'Ali Hassan', 'ali@gulfbags.kw', '+965 2200-1100', '30082841KWD1', 'Net 30', -12400.000, NULL, 1, '2026-05-20 07:49:58', NULL, NULL, NULL),
(2, 'Dubai Fashion Hub', 'Sara Karim', 'sara@dubaifashion.ae', '+971 4-422-8800', 'TRN-29481UAE', 'Net 15', -8200.000, NULL, 1, '2026-05-20 07:49:58', NULL, NULL, NULL),
(3, 'Istanbul Textile Co.', 'Mehmet Oz', 'mehmet@istextile.tr', '+90 212-440-2200', 'TUR-1820-2024', 'Net 45', 0.000, NULL, 1, '2026-05-20 07:49:58', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` int(11) NOT NULL,
  `name` varchar(40) NOT NULL,
  `name_ar` varchar(40) DEFAULT NULL,
  `abbreviation` varchar(10) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `name`, `name_ar`, `abbreviation`, `is_active`, `created_at`) VALUES
(1, 'Piece', 'قطعة', 'pc', 1, '2026-05-20 07:49:58'),
(2, 'Box', 'صندوق', 'box', 1, '2026-05-20 07:49:58'),
(3, 'Kilogram', 'كيلوغرام', 'kg', 1, '2026-05-20 07:49:58'),
(4, 'Gram', 'غرام', 'g', 1, '2026-05-20 07:49:58'),
(5, 'Meter', 'متر', 'm', 1, '2026-05-20 07:49:58'),
(6, 'Dozen', 'دزينة', 'doz', 1, '2026-05-20 07:49:58'),
(7, 'Pair', 'زوج', 'pr', 1, '2026-05-20 07:49:58');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','manager','cashier','inventory') DEFAULT 'cashier',
  `branch_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `branch_id`, `is_active`, `last_login`, `created_at`) VALUES
(1, 'Super Admin', 'admin@retailpro.kw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', NULL, 1, '2026-05-21 06:32:22', '2026-05-20 07:49:57'),
(2, 'Ahmed Karim', 'a.karim@retailpro.kw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 1, 1, NULL, '2026-05-20 07:49:57'),
(3, 'Cashier POS', 'cashier@retailpro.kw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier', 1, 1, NULL, '2026-05-20 07:49:57'),
(4, 'Inventory Staff', 'inventory@retailpro.kw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'inventory', NULL, 1, NULL, '2026-05-20 07:49:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `branch_categories`
--
ALTER TABLE `branch_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bc_unique` (`branch_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chart_of_accounts`
--
ALTER TABLE `chart_of_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_ii_batch` (`batch_id`);

--
-- Indexes for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `offers`
--
ALTER TABLE `offers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `product_suppliers`
--
ALTER TABLE `product_suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ps_unique` (`product_id`,`supplier_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `stock`
--
ALTER TABLE `stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_branch` (`product_id`,`branch_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `stock_batches`
--
ALTER TABLE `stock_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `batch_number` (`batch_number`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `received_by` (`received_by`),
  ADD KEY `idx_batch_product` (`product_id`),
  ADD KEY `idx_batch_supplier` (`supplier_id`),
  ADD KEY `idx_batch_expiry` (`expiry_date`),
  ADD KEY `idx_batch_status` (`status`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `branch_id` (`branch_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `branch_categories`
--
ALTER TABLE `branch_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `chart_of_accounts`
--
ALTER TABLE `chart_of_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `journal_entries`
--
ALTER TABLE `journal_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offers`
--
ALTER TABLE `offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `product_suppliers`
--
ALTER TABLE `product_suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `stock`
--
ALTER TABLE `stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `stock_batches`
--
ALTER TABLE `stock_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `branch_categories`
--
ALTER TABLE `branch_categories`
  ADD CONSTRAINT `branch_categories_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `branch_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `invoices_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`),
  ADD CONSTRAINT `invoice_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD CONSTRAINT `journal_entries_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `journal_entries_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `product_suppliers`
--
ALTER TABLE `product_suppliers`
  ADD CONSTRAINT `product_suppliers_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_suppliers_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`),
  ADD CONSTRAINT `purchase_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `stock`
--
ALTER TABLE `stock`
  ADD CONSTRAINT `stock_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

--
-- Constraints for table `stock_batches`
--
ALTER TABLE `stock_batches`
  ADD CONSTRAINT `stock_batches_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_batches_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `stock_batches_ibfk_3` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `stock_batches_ibfk_4` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_batches_ibfk_5` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
