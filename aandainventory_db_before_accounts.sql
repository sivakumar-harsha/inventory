-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 18, 2026 at 12:19 PM
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
-- Database: `aandainventory_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `gst` varchar(100) NOT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `gst`, `contact`, `phone`, `email`, `address`, `created_at`, `updated_at`) VALUES
(1, 'Shreeya Clinic', '33AACCS1234F1Z5', 'Dr. Shreeya Menon', '9876543210', 'info@shreeyaclinic.com', 'Anna Nagar, Chennai, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(2, 'Sri Balaji Builders', '33AABCS5678G1Z2', 'S. Balaji', '9840011122', 'admin@balajibuilders.com', 'Race Course Road, Coimbatore, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(3, 'RK Interiors', '33AAFCR4321H1Z9', 'R. Kumaresan', '9894123456', 'rkinteriors@gmail.com', 'Anna Nagar, Madurai, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(4, 'Green Leaf Hospital', '33AAACG8765J1Z4', 'Dr. Green Leaf Admin', '9789012345', 'accounts@greenleaf.com', 'Fort Road, Salem, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(5, 'Anand Dental Care', '33AABCA2468K1Z7', 'Dr. Anand Raj', '9884561234', 'dental@anandcare.com', 'Thillai Nagar, Trichy, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(6, 'Harsha Medical Center', '33AACCH1357L1Z1', 'Dr. Harsha Vardhan', '9944123456', 'info@harshamedical.com', 'Perundurai Road, Erode, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(7, 'Elite Diagnostics', '33AABCE9753M1Z6', 'V. Elumalai', '9952012345', 'contact@elitediagnostics.com', 'T Nagar, Chennai, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(8, 'Vignesh Industries', '33AAFCV8642N1Z3', 'M. Vignesh', '9790011122', 'purchase@vigneshind.com', 'Avinashi Road, Tiruppur, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(9, 'Lotus Residency', '33AABCL7531P1Z8', 'K. Lotus Prakash', '9843012345', 'admin@lotusresidency.com', 'Bagalur Road, Hosur, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(10, 'Sun Tech Solutions', '29AAECS6420Q1Z0', 'R. Suryanarayan', '9870012233', 'accounts@suntech.com', 'Whitefield, Bengaluru, Karnataka', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(14, 'MARTS', '', NULL, '9675645445', '', '', '2026-09-05 11:12:06', '2026-09-05 11:12:06');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `expense_date` date NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `project_id`, `category`, `description`, `amount`, `expense_date`, `created_at`, `updated_at`) VALUES
(1, 2, 'Transport', '', 5000.00, '2026-06-29', '2026-09-03 15:47:21', '2026-09-03 15:47:21'),
(2, 2, 'Labour', '', 4500.00, '2026-06-29', '2026-09-03 15:47:47', '2026-09-03 15:47:47');

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `category_name`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Labour', 'ACTIVE', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(2, 'Transport', 'ACTIVE', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(3, 'Utilities', 'ACTIVE', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(4, 'Office Supplies', 'ACTIVE', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(5, 'Equipment Rental', 'ACTIVE', '2026-09-03 15:25:46', '2026-09-03 15:25:46');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `version` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `group` varchar(255) NOT NULL,
  `namespace` varchar(255) NOT NULL,
  `time` int(11) NOT NULL,
  `batch` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `version`, `class`, `group`, `namespace`, `time`, `batch`) VALUES
(1, '2026-08-27-000000', 'App\\Database\\Migrations\\AddProjectValueFields', 'default', 'App', 1787808775, 1),
(2, '2026-08-27-000001', 'App\\Database\\Migrations\\AddGstApplicableFields', 'default', 'App', 1787811953, 2),
(3, '2026-08-27-000002', 'App\\Database\\Migrations\\CreateStockReturns', 'default', 'App', 1787814500, 3),
(4, '2026-08-27-000003', 'App\\Database\\Migrations\\AddPurchaseAllocationFields', 'default', 'App', 1787891852, 4),
(5, '2026-08-28-000000', 'App\\Database\\Migrations\\AddSalesAdvanceApplied', 'default', 'App', 1787895763, 5),
(6, '2026-08-28-000001', 'App\\Database\\Migrations\\RemoveStockReturns', 'default', 'App', 1787908281, 6),
(7, '2026-09-01-000000', 'App\\Database\\Migrations\\AddProjectBillingStatus', 'default', 'App', 1788249097, 7),
(8, '2026-09-02-000000', 'App\\Database\\Migrations\\CreateExpenseCategories', 'default', 'App', 1788344515, 8),
(9, '2026-09-03-000000', 'App\\Database\\Migrations\\CreateProjectCashReceipts', 'default', 'App', 1788413295, 9),
(10, '2026-09-03-000001', 'App\\Database\\Migrations\\AddReceiptTypeToProjectCashReceipts', 'default', 'App', 1788435470, 10),
(11, '2026-09-04-000000', 'App\\Database\\Migrations\\AddEmailToUsers', 'default', 'App', 1788500884, 11),
(12, '2026-09-04-000001', 'App\\Database\\Migrations\\CreatePasswordResets', 'default', 'App', 1788500885, 11);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(150) NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `email`, `otp_code`, `expires_at`, `verified_at`, `created_at`) VALUES
(6, 1, 'harshamvc11@gmail.com', '403077', '2026-09-04 12:06:46', NULL, '2026-09-04 11:56:46');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `sale_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` date NOT NULL,
  `method` enum('CASH','BANK_TRANSFER','CHECK','OTHER') NOT NULL DEFAULT 'CASH',
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `sale_id`, `amount`, `payment_date`, `method`, `reference`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 107532.50, '2026-06-30', 'BANK_TRANSFER', '', '', '2026-09-03 15:44:14', '2026-09-03 15:44:14'),
(4, 4, 189262.50, '2026-07-06', 'CASH', '', '', '2026-09-03 18:59:12', '2026-09-03 18:59:23');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(50) NOT NULL DEFAULT 'pcs',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `hsn_code` varchar(50) DEFAULT NULL,
  `gst_percent` decimal(5,2) DEFAULT 0.00,
  `selling_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `unit`, `created_at`, `updated_at`, `hsn_code`, `gst_percent`, `selling_price`) VALUES
(1, 'Asian Paint Premium White', 'Premium emulsion paint - white shade', 'Ltr', '2026-09-03 15:25:46', '2026-09-03 15:35:59', '3209', 18.00, 4200.00),
(2, 'Asian Paint Royal Blue', 'Premium emulsion paint - royal blue shade', 'Ltr', '2026-09-03 15:25:46', '2026-09-03 15:36:09', '3209', 18.00, 11450.00),
(3, 'Primer White', 'Wall primer white base coat', 'Ltr', '2026-09-03 15:25:46', '2026-09-03 15:36:21', '3208', 18.00, 22210.00),
(4, 'LED Panel Light', '18W round LED panel light', 'Pcs', '2026-09-03 15:25:46', '2026-09-03 15:36:30', '9405', 18.00, 11380.00),
(5, 'Ceiling Fan', '1200mm high speed ceiling fan', 'Pcs', '2026-09-03 15:25:46', '2026-09-03 15:25:46', '8414', 18.00, 1650.00),
(6, 'PVC Wire 1.5 SQMM', 'Copper PVC insulated wire 1.5 sq mm', 'Coil', '2026-09-03 15:25:46', '2026-09-03 15:36:42', '8544', 18.00, 11500.00),
(7, 'Switch Board 6 Module', 'Modular switch board with 6 module plate', 'Pcs', '2026-09-03 15:25:46', '2026-09-03 15:36:50', '8536', 18.00, 8320.00),
(8, 'PVC Pipe 1 Inch', 'ISI PVC pipe 1 inch diameter', 'Length', '2026-09-03 15:25:46', '2026-09-03 15:36:58', '3917', 18.00, 7265.00),
(9, 'PVC Elbow', 'PVC elbow joint 1 inch', 'Pcs', '2026-09-03 15:25:46', '2026-09-03 15:37:14', '3917', 18.00, 2325.00),
(10, 'Water Tap', 'Brass chrome finish water tap', 'Pcs', '2026-09-03 15:25:46', '2026-09-03 15:25:46', '8481', 18.00, 340.00),
(11, 'Ball Valve', 'PVC ball valve 1 inch', 'Pcs', '2026-09-03 15:25:46', '2026-09-03 15:25:46', '8481', 18.00, 195.00),
(12, 'Cement Bag', 'OPC 53 grade cement 50kg bag', 'Bag', '2026-09-03 15:25:46', '2026-09-03 15:25:46', '2523', 18.00, 400.00),
(13, 'Steel Rod 10mm', 'TMT steel rod 10mm - 12mtr length', 'Pcs', '2026-09-03 15:25:46', '2026-09-03 15:25:46', '7214', 18.00, 620.00),
(14, 'Plywood Sheet', 'Waterproof plywood sheet 19mm 8x4ft', 'Sheet', '2026-09-03 15:25:46', '2026-09-03 15:25:46', '4412', 18.00, 2450.00),
(15, 'Door Lock', 'Mortise door lock set with keys', 'Pcs', '2026-09-03 15:25:46', '2026-09-03 15:25:46', '8301', 18.00, 480.00),
(16, 'Hinges Set', 'Stainless steel door hinges - pair', 'Set', '2026-09-03 15:25:46', '2026-09-03 15:25:46', '8302', 18.00, 90.00),
(17, 'Printer Paper A4', 'A4 size copier paper - 500 sheets ream', 'Ream', '2026-09-03 15:25:46', '2026-09-03 15:25:46', '4802', 12.00, 280.00),
(18, 'Marker Pen', 'Permanent marker pen - black', 'Pcs', '2026-09-03 15:25:46', '2026-09-03 15:25:46', '9608', 12.00, 20.00),
(19, 'File Folder', 'Ring binder file folder', 'Pcs', '2026-09-03 15:25:46', '2026-09-03 15:25:46', '4820', 12.00, 65.00),
(20, 'Safety Helmet', 'ISI marked industrial safety helmet', 'Pcs', '2026-09-03 15:25:46', '2026-09-03 15:25:46', '6506', 5.00, 150.00),
(21, 'Safety Gloves', 'Cut-resistant industrial safety gloves - pair', 'Pair', '2026-09-03 15:25:46', '2026-09-03 15:25:46', '4015', 5.00, 85.00),
(22, 'Reflective Jacket', 'High visibility reflective safety jacket', 'Pcs', '2026-09-03 15:25:46', '2026-09-03 15:25:46', '6211', 5.00, 220.00),
(23, 'Cleaning Liquid', 'Multi-surface cleaning liquid - 1 ltr', 'Ltr', '2026-09-03 15:25:46', '2026-09-03 15:25:46', '3402', 18.00, 130.00),
(24, 'Drill Bit Set', 'HSS drill bit set - 13 pieces', 'Set', '2026-09-03 15:25:46', '2026-09-03 15:25:46', '8207', 12.00, 450.00),
(25, 'Extension Box', '4-socket power extension box with switch', 'Pcs', '2026-09-03 15:25:46', '2026-09-03 15:25:46', '8536', 5.00, 260.00);

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('ACTIVE','COMPLETED','ON_HOLD') NOT NULL DEFAULT 'ACTIVE',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `total_project_value` decimal(12,2) DEFAULT 0.00,
  `advance_amount` decimal(12,2) DEFAULT 0.00,
  `advance_date` date DEFAULT NULL,
  `advance_notes` varchar(255) DEFAULT NULL,
  `billing_status` enum('ACTIVE','COMPLETED') NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `customer_id`, `status`, `start_date`, `end_date`, `description`, `total_project_value`, `advance_amount`, `advance_date`, `advance_notes`, `billing_status`, `created_at`, `updated_at`) VALUES
(1, 'Shreeya Clinic Chennai', 1, 'ACTIVE', '2026-07-01', NULL, 'Interior renovation and painting works', 2586869.00, 50000.00, '2026-07-01', NULL, 'ACTIVE', '2026-09-03 15:25:47', '2026-09-03 15:25:47'),
(2, 'Balaji Builders Phase 1', 2, 'ACTIVE', '2026-06-15', NULL, 'Residential complex construction - phase 1', 1850000.00, 100000.00, '2026-06-15', NULL, 'ACTIVE', '2026-09-03 15:25:47', '2026-09-03 15:25:47'),
(3, 'RK Interiors Office Renovation', 3, 'ACTIVE', '2026-07-10', NULL, 'Office interior renovation works', 1275000.00, 75000.00, '2026-07-10', NULL, 'ACTIVE', '2026-09-03 15:25:47', '2026-09-03 15:25:47'),
(4, 'Green Leaf Hospital ICU', 4, 'ACTIVE', '2026-05-20', NULL, 'ICU expansion and electrical works', 3500000.00, 200000.00, '2026-05-20', NULL, 'ACTIVE', '2026-09-03 15:25:47', '2026-09-03 15:25:47'),
(5, 'Anand Dental Expansion', 5, 'COMPLETED', '2026-02-01', '2026-06-30', 'Dental clinic expansion works', 925000.00, 25000.00, '2026-02-01', NULL, 'COMPLETED', '2026-09-03 15:25:47', '2026-09-03 15:25:47'),
(6, 'Harsha Medical Lab', 6, 'COMPLETED', '2026-01-15', '2026-05-10', 'Medical lab setup and fit-out', 1580000.00, 80000.00, '2026-01-15', NULL, 'COMPLETED', '2026-09-03 15:25:47', '2026-09-03 15:25:47'),
(7, 'Elite Diagnostics Branch', 7, 'ACTIVE', '2026-07-25', NULL, 'New diagnostics branch fit-out', 2240000.00, 120000.00, '2026-07-25', NULL, 'ACTIVE', '2026-09-03 15:25:47', '2026-09-03 15:25:47'),
(8, 'Lotus Residency Painting', 9, 'ON_HOLD', '2026-08-01', NULL, 'Residency exterior and interior painting', 1160000.00, 40000.00, '2026-08-01', NULL, 'ACTIVE', '2026-09-03 15:25:47', '2026-09-03 15:25:47');

-- --------------------------------------------------------

--
-- Table structure for table `project_cash_receipts`
--

CREATE TABLE `project_cash_receipts` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `receipt_no` varchar(50) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `receipt_date` date NOT NULL,
  `payment_method` enum('CASH','BANK_TRANSFER','CHECK','OTHER') NOT NULL DEFAULT 'CASH',
  `receipt_type` enum('ADVANCE','DIRECT_INCOME') NOT NULL DEFAULT 'ADVANCE',
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_cash_receipts`
--

INSERT INTO `project_cash_receipts` (`id`, `project_id`, `customer_id`, `receipt_no`, `amount`, `receipt_date`, `payment_method`, `receipt_type`, `reference`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2, 2, 'CASH-0001', 20000.00, '2026-07-03', 'CASH', 'DIRECT_INCOME', '', '', '2026-09-03 18:34:43', '2026-09-03 18:34:43'),
(2, 2, 2, 'CASH-0002', 50000.00, '2026-07-04', 'CASH', 'DIRECT_INCOME', 'CQU2452GHSH', '', '2026-09-03 18:45:42', '2026-09-03 18:45:42');

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int(10) UNSIGNED NOT NULL,
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED DEFAULT NULL,
  `purchase_date` date NOT NULL,
  `invoice_no` varchar(100) DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `supplier_id`, `project_id`, `purchase_date`, `invoice_no`, `total_amount`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 2, '2026-06-20', 'INV/BBP/2006', 69472.50, '', '2026-09-03 15:38:30', '2026-09-03 15:38:30'),
(2, 2, 2, '2026-07-02', 'INV/BBP2/2006', 195740.90, '', '2026-09-03 15:56:07', '2026-09-03 15:56:07');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `purchase_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `hsn_code` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `project_qty` int(11) NOT NULL DEFAULT 0,
  `general_qty` int(11) NOT NULL DEFAULT 0,
  `unit_price` decimal(15,2) NOT NULL,
  `gst_percent` decimal(5,2) DEFAULT 0.00,
  `gst_applicable` tinyint(1) NOT NULL DEFAULT 1,
  `gst_amount` decimal(10,2) DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL,
  `total_with_gst` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `purchase_items`
--

INSERT INTO `purchase_items` (`id`, `purchase_id`, `product_id`, `hsn_code`, `quantity`, `project_qty`, `general_qty`, `unit_price`, `gst_percent`, `gst_applicable`, `gst_amount`, `total`, `total_with_gst`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '3209', 2, 2, 0, 4200.00, 18.00, 1, 1512.00, 8400.00, 9912.00, '2026-09-03 15:38:30', '2026-09-03 15:38:30'),
(2, 1, 2, '3209', 3, 3, 0, 11450.00, 18.00, 1, 6183.00, 34350.00, 40533.00, '2026-09-03 15:38:30', '2026-09-03 15:38:30'),
(3, 1, 11, '8481', 15, 15, 0, 195.00, 18.00, 1, 526.50, 2925.00, 3451.50, '2026-09-03 15:38:30', '2026-09-03 15:38:30'),
(4, 1, 5, '8414', 8, 8, 0, 1650.00, 18.00, 1, 2376.00, 13200.00, 15576.00, '2026-09-03 15:38:30', '2026-09-03 15:38:30'),
(5, 2, 7, '8536', 9, 9, 0, 8320.00, 18.00, 1, 13478.40, 74880.00, 88358.40, '2026-09-03 15:56:07', '2026-09-03 15:56:07'),
(6, 2, 20, '6506', 15, 10, 5, 150.00, 5.00, 1, 112.50, 2250.00, 2362.50, '2026-09-03 15:56:07', '2026-09-03 15:56:07'),
(7, 2, 12, '2523', 50, 50, 0, 400.00, 18.00, 1, 3600.00, 20000.00, 23600.00, '2026-09-03 15:56:07', '2026-09-03 15:56:07'),
(8, 2, 6, '8544', 6, 6, 0, 11500.00, 18.00, 1, 12420.00, 69000.00, 81420.00, '2026-09-03 15:56:07', '2026-09-03 15:56:07');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `sale_date` date NOT NULL,
  `invoice_no` varchar(100) DEFAULT NULL,
  `stock_source` enum('GENERAL','PROJECT','MIXED') DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `advance_applied` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('UNPAID','PARTIAL','PAID') NOT NULL DEFAULT 'UNPAID',
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `project_id`, `customer_id`, `sale_date`, `invoice_no`, `stock_source`, `total_amount`, `advance_applied`, `paid_amount`, `balance_amount`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2, 2, '2026-06-25', 'INV/S/BBP/2006', 'PROJECT', 207532.50, 100000.00, 107532.50, 0.00, 'PAID', '', '2026-09-03 15:41:13', '2026-09-03 15:44:14'),
(4, 2, 2, '2026-07-05', 'INV/BBPS/0507', 'GENERAL', 189262.50, 0.00, 189262.50, 0.00, 'PAID', '', '2026-09-03 18:55:14', '2026-09-03 18:59:12');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `sale_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `gst_percent` decimal(5,2) DEFAULT 0.00,
  `gst_applicable` tinyint(1) NOT NULL DEFAULT 1,
  `gst_amount` decimal(10,2) DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL,
  `total_with_gst` decimal(10,2) DEFAULT 0.00,
  `stock_source` enum('GENERAL','PROJECT') NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `unit_price`, `gst_percent`, `gst_applicable`, `gst_amount`, `total`, `total_with_gst`, `stock_source`, `project_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 2.000, 8200.00, 18.00, 1, 2952.00, 16400.00, 19352.00, 'PROJECT', 2, '2026-09-03 15:41:13', '2026-09-03 15:41:13'),
(2, 1, 2, 3.000, 21450.00, 18.00, 1, 11583.00, 64350.00, 75933.00, 'PROJECT', 2, '2026-09-03 15:41:13', '2026-09-03 15:41:13'),
(3, 1, 11, 15.000, 1195.00, 18.00, 1, 3226.50, 17925.00, 21151.50, 'PROJECT', 2, '2026-09-03 15:41:13', '2026-09-03 15:41:13'),
(4, 1, 5, 8.000, 9650.00, 18.00, 1, 13896.00, 77200.00, 91096.00, 'PROJECT', 2, '2026-09-03 15:41:13', '2026-09-03 15:41:13'),
(8, 4, 20, 5.000, 36050.00, 5.00, 1, 9012.50, 180250.00, 189262.50, 'GENERAL', NULL, '2026-09-03 18:56:19', '2026-09-03 18:56:19');

-- --------------------------------------------------------

--
-- Table structure for table `stock_ledger`
--

CREATE TABLE `stock_ledger` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `transaction_type` enum('IN','OUT') NOT NULL,
  `quantity` int(11) NOT NULL,
  `source` enum('GENERAL','PROJECT') NOT NULL DEFAULT 'GENERAL',
  `project_id` int(10) UNSIGNED DEFAULT NULL,
  `reference_type` enum('PURCHASE','SALE','MANUAL') DEFAULT NULL,
  `reference_id` int(10) UNSIGNED NOT NULL,
  `transaction_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `stock_ledger`
--

INSERT INTO `stock_ledger` (`id`, `product_id`, `transaction_type`, `quantity`, `source`, `project_id`, `reference_type`, `reference_id`, `transaction_date`, `notes`, `created_at`) VALUES
(1, 1, 'IN', 2, 'PROJECT', 2, 'PURCHASE', 1, '2026-06-20', 'Purchase #1', '2026-09-03 15:38:30'),
(2, 2, 'IN', 3, 'PROJECT', 2, 'PURCHASE', 1, '2026-06-20', 'Purchase #1', '2026-09-03 15:38:30'),
(3, 11, 'IN', 15, 'PROJECT', 2, 'PURCHASE', 1, '2026-06-20', 'Purchase #1', '2026-09-03 15:38:30'),
(4, 5, 'IN', 8, 'PROJECT', 2, 'PURCHASE', 1, '2026-06-20', 'Purchase #1', '2026-09-03 15:38:30'),
(5, 1, 'OUT', 2, 'PROJECT', 2, 'SALE', 1, '2026-06-25', 'Sale #1', '2026-09-03 15:41:13'),
(6, 2, 'OUT', 3, 'PROJECT', 2, 'SALE', 1, '2026-06-25', 'Sale #1', '2026-09-03 15:41:13'),
(7, 11, 'OUT', 15, 'PROJECT', 2, 'SALE', 1, '2026-06-25', 'Sale #1', '2026-09-03 15:41:13'),
(8, 5, 'OUT', 8, 'PROJECT', 2, 'SALE', 1, '2026-06-25', 'Sale #1', '2026-09-03 15:41:13'),
(9, 7, 'IN', 9, 'PROJECT', 2, 'PURCHASE', 2, '2026-07-02', 'Purchase #2', '2026-09-03 15:56:07'),
(10, 20, 'IN', 10, 'PROJECT', 2, 'PURCHASE', 2, '2026-07-02', 'Purchase #2', '2026-09-03 15:56:07'),
(11, 20, 'IN', 5, 'GENERAL', NULL, 'PURCHASE', 2, '2026-07-02', 'Purchase #2', '2026-09-03 15:56:07'),
(12, 12, 'IN', 50, 'PROJECT', 2, 'PURCHASE', 2, '2026-07-02', 'Purchase #2', '2026-09-03 15:56:07'),
(13, 6, 'IN', 6, 'PROJECT', 2, 'PURCHASE', 2, '2026-07-02', 'Purchase #2', '2026-09-03 15:56:07'),
(19, 20, 'OUT', 5, 'GENERAL', NULL, 'SALE', 4, '2026-07-05', 'Sale #4', '2026-09-03 18:56:19');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `gst` varchar(100) NOT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `gst`, `contact`, `phone`, `email`, `address`, `created_at`, `updated_at`) VALUES
(1, 'Asian Paints Distributor', '33AABCA1122R1Z4', 'N. Aravind', '9840022334', 'sales@asianpaintsdist.com', 'Guindy Industrial Estate, Chennai, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(2, 'ABC Electricals', '33AACCA2233S1Z9', 'P. Baskar', '9894022345', 'orders@abcelectricals.com', 'Ondipudur, Coimbatore, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(3, 'Chennai Steel Traders', '33AABCC3344T1Z2', 'S. Chandrasekar', '9789022356', 'sales@chennaisteel.com', 'Red Hills, Chennai, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(4, 'Sri Lakshmi Hardware', '33AAFCS4455U1Z7', 'L. Lakshmanan', '9884022367', 'info@srilakshmihw.com', 'East Veli Street, Madurai, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(5, 'Vinayaga Cement Depot', '33AABCV5566V1Z0', 'G. Vinayagam', '9944022378', 'vinayagacement@gmail.com', 'Bharathi Nagar, Erode, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(6, 'Supreme Tiles & Granites', '33AACCS6677W1Z5', 'R. Suresh Kumar', '9952022389', 'sales@supremetiles.com', 'Trichy Road, Coimbatore, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(7, 'JK Pipes & Fittings', '33AABCJ7788X1Z8', 'J. Karthikeyan', '9790022390', 'orders@jkpipes.com', 'Perumalpuram, Tirunelveli, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(8, 'National Plywood Centre', '33AAFCN8899Y1Z3', 'N. Natarajan', '9843022301', 'sales@nationalplywood.com', 'Kallukuzhi, Trichy, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(9, 'Modern Sanitary World', '33AABCM9900Z1Z6', 'M. Manikandan', '9870022312', 'info@modernsanitary.com', 'Salem Main Road, Salem, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(10, 'Harsha Industrial Supplies', '33AACCH0011A1Z1', 'H. Harish', '9994022323', 'sales@harshaindustrial.com', 'SIDCO Industrial Estate, Chennai, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(16, 'GASA', '', NULL, '674363622', '', '', '2026-09-05 11:50:55', '2026-09-05 11:50:55');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `created_at`, `updated_at`) VALUES
(1, 'aainv', 'harshamvc11@gmail.com', 'aainv#123', '2026-04-11 10:04:23', '2026-09-04 15:25:49');

--
-- Indexes for dumped tables
--

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
  ADD KEY `expenses_ibfk_1` (`project_id`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `project_cash_receipts`
--
ALTER TABLE `project_cash_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_no` (`receipt_no`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `project_cash_receipts`
--
ALTER TABLE `project_cash_receipts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`);

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `project_cash_receipts`
--
ALTER TABLE `project_cash_receipts`
  ADD CONSTRAINT `project_cash_receipts_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchases_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD CONSTRAINT `purchase_items_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  ADD CONSTRAINT `stock_ledger_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_ledger_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
