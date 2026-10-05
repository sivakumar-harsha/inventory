-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 02, 2026 at 03:00 PM
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
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `module` varchar(100) NOT NULL,
  `action` enum('CREATE','UPDATE','DELETE','STATUS_CHANGE','LOGIN','LOGOUT') NOT NULL,
  `reference_type` varchar(100) DEFAULT NULL,
  `reference_id` int(10) UNSIGNED DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `old_values` longtext DEFAULT NULL,
  `new_values` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `module`, `action`, `reference_type`, `reference_id`, `reference_no`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 5, 'SPV-000001', 'Automatic withdrawal posted from SUPPLIER_PAYMENT.', NULL, '{\"bank_account_id\":1,\"transaction_date\":\"2026-09-25\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":8500.1,\"reference_type\":\"SUPPLIER_PAYMENT\",\"reference_id\":5}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-25 10:43:27'),
(2, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 5, 'SPV-000001', 'Supplier payment SPV-000001 created.', NULL, '{\"supplier_id\":1,\"payment_date\":\"2026-09-25\",\"payment_method\":\"Bank Transfer\",\"reference_no\":\"\",\"remarks\":\"\",\"total_amount\":8500.1,\"advance_amount\":0}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-25 10:43:27'),
(3, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 6, NULL, 'Automatic deposit posted from BANK_DAYBOOK.', NULL, '{\"bank_account_id\":1,\"transaction_date\":\"2026-09-25\",\"transaction_type\":\"DEPOSIT\",\"amount\":2000,\"reference_type\":\"BANK_DAYBOOK\",\"reference_id\":null}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-25 10:50:47'),
(4, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-25 15:16:33'),
(5, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 7, 'PRJ-000023', 'Automatic deposit posted from PROJECT_ADVANCE_DEPOSIT.', NULL, '{\"bank_account_id\":1,\"transaction_date\":\"2026-09-20\",\"transaction_type\":\"DEPOSIT\",\"amount\":100000,\"reference_type\":\"PROJECT_ADVANCE_DEPOSIT\",\"reference_id\":23}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-25 15:28:53'),
(6, 1, 'Bank', 'CREATE', 'BANK_ACCOUNT', 7, 'IBS2352525', 'Bank account IBS2352525 created.', NULL, '{\"bank_name\":\"Indian Bank\",\"account_name\":\"JAAN\",\"account_number\":\"IBS2352525\",\"ifsc_code\":\"SBW2532535\",\"branch_name\":\"MNallur\",\"opening_balance\":35000,\"is_active\":1}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-25 16:06:44'),
(7, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 8, 'SPV-000006', 'Automatic withdrawal posted from SUPPLIER_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-25\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":20461.2,\"reference_type\":\"SUPPLIER_PAYMENT\",\"reference_id\":6}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-25 16:12:13'),
(8, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 6, 'SPV-000006', 'Supplier payment SPV-000006 created.', NULL, '{\"supplier_id\":7,\"payment_date\":\"2026-09-25\",\"payment_method\":\"Bank Transfer\",\"reference_no\":\"\",\"remarks\":\"\",\"total_amount\":20461.2,\"advance_amount\":0}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-25 16:12:13'),
(9, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 9, 'PRJ-000024', 'Automatic deposit posted from PROJECT_ADVANCE_DEPOSIT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-10\",\"transaction_type\":\"DEPOSIT\",\"amount\":50000,\"reference_type\":\"PROJECT_ADVANCE_DEPOSIT\",\"reference_id\":24}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-25 17:53:03'),
(10, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 10, 'SPV-000007', 'Automatic withdrawal posted from SUPPLIER_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-11\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":3000,\"reference_type\":\"SUPPLIER_PAYMENT\",\"reference_id\":7}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-25 17:55:49'),
(11, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 7, 'SPV-000007', 'Supplier payment SPV-000007 created.', NULL, '{\"supplier_id\":10,\"payment_date\":\"2026-09-11\",\"payment_method\":\"Bank Transfer\",\"reference_no\":\"\",\"remarks\":\"\",\"total_amount\":3000,\"advance_amount\":3000}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-25 17:55:49'),
(12, 1, 'Loan', 'CREATE', 'LOAN', 1, 'LN-000001', 'Loan LN-000001 created.', NULL, '{\"lender_name\":\"TEs\",\"loan_type\":\"PERSONAL\",\"bank_account_id\":null,\"account_number\":null,\"sanctioned_amount\":100000,\"interest_rate\":2,\"tenure_months\":12,\"emi_amount\":8423.89,\"start_date\":\"2026-09-16\",\"end_date\":\"2027-09-16\",\"remarks\":null,\"loan_no\":\"LN-000001\",\"outstanding_principal\":100000,\"total_principal_paid\":0,\"total_interest_paid\":0,\"status\":\"ACTIVE\",\"created_by\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-25 18:40:42'),
(13, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 11, 'LN-000001', 'Automatic withdrawal posted from LOAN_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-10-16\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":8423.89,\"reference_type\":\"LOAN_PAYMENT\",\"reference_id\":1}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-25 18:41:52'),
(14, 1, 'Loan', 'CREATE', 'LOAN_PAYMENT', 1, 'LN-000001', 'Payment recorded against loan LN-000001.', NULL, '{\"loan_id\":1,\"loan_emi_id\":1,\"payment_date\":\"2026-10-16\",\"payment_method\":\"BANK\",\"bank_account_id\":7,\"principal_paid\":8257.22,\"interest_paid\":166.67,\"total_paid\":8423.89,\"reference_no\":null,\"remarks\":null,\"created_by\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-25 18:41:52'),
(15, 1, 'Authentication', 'LOGOUT', 'USER', 1, 'aainv', 'User \'aainv\' logged out.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-25 18:56:04'),
(16, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-26 10:13:29'),
(17, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 12, 'PRJ-000025', 'Automatic deposit posted from PROJECT_ADVANCE_DEPOSIT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-01\",\"transaction_type\":\"DEPOSIT\",\"amount\":50000,\"reference_type\":\"PROJECT_ADVANCE_DEPOSIT\",\"reference_id\":25}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-26 10:16:34'),
(18, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 13, 'CASH-0005', 'Automatic deposit posted from PROJECT_ADVANCE.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-04\",\"transaction_type\":\"DEPOSIT\",\"amount\":9048,\"reference_type\":\"PROJECT_ADVANCE\",\"reference_id\":5}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-26 12:18:09'),
(19, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-26 18:14:58'),
(20, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 14, 'EXP-000003', 'Automatic withdrawal posted from EXPENSE.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-26\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":2500,\"reference_type\":\"EXPENSE\",\"reference_id\":4}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-26 18:17:08'),
(21, 1, 'Expense', 'CREATE', 'EXPENSE', 4, 'EXP-000003', 'Expense EXP-000003 created.', NULL, '{\"expense_no\":\"EXP-000003\",\"expense_date\":\"2026-09-26\",\"category_id\":1,\"project_id\":25,\"paid_to\":\"Vendor\",\"payment_method\":\"BANK\",\"bank_account_id\":7,\"amount\":\"2500.00\",\"remarks\":null,\"status\":\"PAID\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-26 18:17:08'),
(22, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 15, 'EXP-000005', 'Automatic withdrawal posted from EXPENSE.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-26\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":6300,\"reference_type\":\"EXPENSE\",\"reference_id\":5}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-26 18:18:14'),
(23, 1, 'Expense', 'CREATE', 'EXPENSE', 5, 'EXP-000005', 'Expense EXP-000005 created.', NULL, '{\"expense_no\":\"EXP-000005\",\"expense_date\":\"2026-09-26\",\"category_id\":5,\"project_id\":null,\"paid_to\":\"Paid for euipment rent\",\"payment_method\":\"UPI\",\"bank_account_id\":7,\"amount\":\"6300.00\",\"remarks\":null,\"status\":\"PAID\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-26 18:18:14'),
(24, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 8, 'SPV-000008', 'Supplier payment SPV-000008 created.', NULL, '{\"supplier_id\":1,\"payment_date\":\"2026-09-26\",\"payment_method\":\"\",\"reference_no\":\"\",\"remarks\":\"\",\"total_amount\":12100,\"advance_amount\":12100}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-26 18:22:30'),
(25, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 10:59:28'),
(26, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 16, 'GP-000002', 'Automatic withdrawal posted from SUPPLIER_ADVANCE.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-08\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":177,\"reference_type\":\"SUPPLIER_ADVANCE\",\"reference_id\":2}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 11:04:21'),
(27, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 17, 'SPV-000009', 'Automatic withdrawal posted from SUPPLIER_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-09\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":1000.1,\"reference_type\":\"SUPPLIER_PAYMENT\",\"reference_id\":9}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 11:11:39'),
(28, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 9, 'SPV-000009', 'Supplier payment SPV-000009 created.', NULL, '{\"supplier_id\":3,\"payment_date\":\"2026-09-09\",\"payment_method\":\"Cheque\",\"reference_no\":\"\",\"remarks\":\"\",\"total_amount\":1000.1,\"advance_amount\":1000.1}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 11:11:40'),
(29, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 18, 'SPV-000010', 'Automatic withdrawal posted from SUPPLIER_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-09\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":1000,\"reference_type\":\"SUPPLIER_PAYMENT\",\"reference_id\":10}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 11:13:42'),
(30, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 10, 'SPV-000010', 'Supplier payment SPV-000010 created.', NULL, '{\"supplier_id\":3,\"payment_date\":\"2026-09-09\",\"payment_method\":\"Cheque\",\"reference_no\":\"\",\"remarks\":\"\",\"total_amount\":1000,\"advance_amount\":0,\"advance_used\":1000.1}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 11:13:42'),
(31, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 19, 'GP-000003', 'Automatic withdrawal posted from SUPPLIER_ADVANCE.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-28\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":437.6,\"reference_type\":\"SUPPLIER_ADVANCE\",\"reference_id\":3}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 11:56:22'),
(32, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 15:28:25'),
(33, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 20, 'SPV-000011', 'Automatic withdrawal posted from SUPPLIER_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-28\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":10000,\"reference_type\":\"SUPPLIER_PAYMENT\",\"reference_id\":11}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 16:02:04'),
(34, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 11, 'SPV-000011', 'Supplier payment SPV-000011 created.', NULL, '{\"supplier_id\":6,\"payment_date\":\"2026-09-28\",\"payment_method\":\"Bank Transfer\",\"reference_no\":\"\",\"remarks\":\"\",\"total_amount\":10000,\"advance_amount\":0}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 16:02:04'),
(35, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 21, 'GP-000004', 'Automatic withdrawal posted from SUPPLIER_ADVANCE.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-20\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":8556,\"reference_type\":\"SUPPLIER_ADVANCE\",\"reference_id\":4}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 16:05:46'),
(36, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 12, 'GPA-000004', 'Supplier advance voucher GPA-000004 created from General Purchase advance.', NULL, '{\"supplier_id\":10,\"payment_date\":\"2026-09-20\",\"payment_method\":\"UPI\",\"reference_no\":\"GP-000004\",\"remarks\":\"Advance paid while creating General Purchase GP-000004\",\"total_amount\":8556,\"advance_amount\":8556}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 16:05:46'),
(37, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 22, 'SPV-000013', 'Automatic withdrawal posted from SUPPLIER_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-28\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":100792.8,\"reference_type\":\"SUPPLIER_PAYMENT\",\"reference_id\":13}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 16:08:25'),
(38, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 13, 'SPV-000013', 'Supplier payment SPV-000013 created.', NULL, '{\"supplier_id\":10,\"payment_date\":\"2026-09-28\",\"payment_method\":\"Bank Transfer\",\"reference_no\":\"\",\"remarks\":\"\",\"total_amount\":100792.8,\"advance_amount\":0,\"advance_used\":3000}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 16:08:25'),
(39, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 23, 'GP-000005', 'Automatic withdrawal posted from SUPPLIER_ADVANCE.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-28\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":1000,\"reference_type\":\"SUPPLIER_ADVANCE\",\"reference_id\":5}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 17:01:03'),
(40, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 14, 'GPA-000005', 'Supplier advance voucher GPA-000005 created from General Purchase advance.', NULL, '{\"supplier_id\":1,\"payment_date\":\"2026-09-28\",\"payment_method\":\"BANK_TRANSFER\",\"reference_no\":\"GP-000005\",\"remarks\":\"Advance paid while creating General Purchase GP-000005\",\"total_amount\":1000,\"advance_amount\":1000}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 17:01:03'),
(41, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 24, 'GP-000006', 'Automatic withdrawal posted from SUPPLIER_ADVANCE.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-28\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":416.2,\"reference_type\":\"SUPPLIER_ADVANCE\",\"reference_id\":6}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 17:52:15'),
(42, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 15, 'GPA-000006', 'Supplier advance voucher GPA-000006 created from General Purchase advance.', NULL, '{\"supplier_id\":7,\"payment_date\":\"2026-09-28\",\"payment_method\":\"CHEQUE\",\"reference_no\":\"GP-000006\",\"remarks\":\"Advance paid while creating General Purchase GP-000006\",\"total_amount\":416.2,\"advance_amount\":416.2}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 17:52:15'),
(43, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 25, 'SPV-000016', 'Automatic withdrawal posted from SUPPLIER_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-28\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":4000,\"reference_type\":\"SUPPLIER_PAYMENT\",\"reference_id\":16}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 17:53:59'),
(44, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 16, 'SPV-000016', 'Supplier payment SPV-000016 created.', NULL, '{\"supplier_id\":7,\"payment_date\":\"2026-09-28\",\"payment_method\":\"Cheque\",\"reference_no\":\"\",\"remarks\":\"\",\"total_amount\":4000,\"advance_amount\":0}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 17:53:59'),
(45, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 26, 'SPV-000017', 'Automatic withdrawal posted from SUPPLIER_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-29\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":1000,\"reference_type\":\"SUPPLIER_PAYMENT\",\"reference_id\":17}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 17:54:23'),
(46, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 17, 'SPV-000017', 'Supplier payment SPV-000017 created.', NULL, '{\"supplier_id\":7,\"payment_date\":\"2026-09-29\",\"payment_method\":\"Cheque\",\"reference_no\":\"\",\"remarks\":\"\",\"total_amount\":1000,\"advance_amount\":0}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-28 17:54:23'),
(47, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 10:19:06'),
(48, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 27, 'EXP-000006', 'Automatic withdrawal posted from EXPENSE.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-29\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":21311,\"reference_type\":\"EXPENSE\",\"reference_id\":6}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 10:30:05'),
(49, 1, 'Expense', 'CREATE', 'EXPENSE', 6, 'EXP-000006', 'Expense EXP-000006 created.', NULL, '{\"expense_no\":\"EXP-000006\",\"expense_date\":\"2026-09-29\",\"category_id\":3,\"project_id\":null,\"paid_to\":\"pppp\",\"payment_method\":\"UPI\",\"bank_account_id\":7,\"amount\":\"21311.00\",\"remarks\":null,\"status\":\"PAID\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 10:30:05'),
(50, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 18, 'GPA-000007', 'Supplier advance voucher GPA-000007 created from General Purchase advance.', NULL, '{\"supplier_id\":9,\"payment_date\":\"2026-09-21\",\"payment_method\":null,\"reference_no\":\"GP-000007\",\"remarks\":\"Advance paid while creating General Purchase GP-000007\",\"total_amount\":7324.4,\"advance_amount\":7324.4}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 10:45:30'),
(51, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 28, 'GP-000007', 'Automatic withdrawal posted from SUPPLIER_ADVANCE.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-21\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":7324.4,\"reference_type\":\"SUPPLIER_ADVANCE\",\"reference_id\":7}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 10:45:51'),
(52, 1, 'Supplier Payment', 'UPDATE', 'SUPPLIER_PAYMENT', 18, 'GPA-000007', 'Supplier advance voucher GPA-000007 updated from General Purchase advance.', '{\"payment_method\":null,\"total_amount\":\"7324.40\",\"advance_amount\":\"7324.40\"}', '{\"payment_method\":\"BANK_TRANSFER\",\"total_amount\":7324.4,\"advance_amount\":7324.4}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 10:45:51'),
(53, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 29, 'SPV-000019', 'Automatic withdrawal posted from SUPPLIER_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-22\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":40000,\"reference_type\":\"SUPPLIER_PAYMENT\",\"reference_id\":19}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 11:52:16'),
(54, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 19, 'SPV-000019', 'Supplier payment SPV-000019 created.', NULL, '{\"supplier_id\":9,\"payment_date\":\"2026-09-22\",\"payment_method\":\"Bank Transfer\",\"reference_no\":\"\",\"remarks\":\"\",\"total_amount\":40000,\"advance_amount\":0}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 11:52:16'),
(55, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 30, 'SPV-000020', 'Automatic withdrawal posted from SUPPLIER_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-29\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":5000,\"reference_type\":\"SUPPLIER_PAYMENT\",\"reference_id\":20}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 11:54:00'),
(56, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 20, 'SPV-000020', 'Supplier payment SPV-000020 created.', NULL, '{\"supplier_id\":9,\"payment_date\":\"2026-09-29\",\"payment_method\":\"Bank Transfer\",\"reference_no\":\"\",\"remarks\":\"\",\"total_amount\":5000,\"advance_amount\":5000}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 11:54:00'),
(57, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 31, 'SPV-000020', 'Automatic withdrawal posted from SUPPLIER_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-23\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":5000,\"reference_type\":\"SUPPLIER_PAYMENT\",\"reference_id\":20}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 11:54:35'),
(58, 1, 'Supplier Payment', 'UPDATE', 'SUPPLIER_PAYMENT', 20, 'SPV-000020', 'Supplier payment SPV-000020 updated.', '{\"payment_date\":\"2026-09-29\",\"total_amount\":\"5000.00\",\"advance_amount\":\"5000.00\"}', '{\"payment_date\":\"2026-09-23\",\"total_amount\":5000,\"advance_amount\":5000}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 11:54:35'),
(59, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 32, 'SPV-000021', 'Automatic withdrawal posted from SUPPLIER_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-24\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":5000,\"reference_type\":\"SUPPLIER_PAYMENT\",\"reference_id\":21}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 11:54:59'),
(60, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 21, 'SPV-000021', 'Supplier payment SPV-000021 created.', NULL, '{\"supplier_id\":9,\"payment_date\":\"2026-09-24\",\"payment_method\":\"Bank Transfer\",\"reference_no\":\"\",\"remarks\":\"\",\"total_amount\":5000,\"advance_amount\":0,\"advance_used\":5000}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 11:54:59'),
(61, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 33, 'PRJ-000026', 'Automatic deposit posted from PROJECT_ADVANCE_DEPOSIT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-25\",\"transaction_type\":\"DEPOSIT\",\"amount\":75000,\"reference_type\":\"PROJECT_ADVANCE_DEPOSIT\",\"reference_id\":26}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 11:57:51'),
(62, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 34, 'SPV-000022', 'Automatic withdrawal posted from SUPPLIER_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-26\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":7085,\"reference_type\":\"SUPPLIER_PAYMENT\",\"reference_id\":22}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 12:01:19'),
(63, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 22, 'SPV-000022', 'Supplier payment SPV-000022 created.', NULL, '{\"supplier_id\":5,\"payment_date\":\"2026-09-26\",\"payment_method\":\"Bank Transfer\",\"reference_no\":\"\",\"remarks\":\"\",\"total_amount\":7085,\"advance_amount\":7085}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 12:01:19'),
(64, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 35, 'SPV-000023', 'Automatic withdrawal posted from SUPPLIER_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-27\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":100000,\"reference_type\":\"SUPPLIER_PAYMENT\",\"reference_id\":23}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 12:02:18'),
(65, 1, 'Supplier Payment', 'CREATE', 'SUPPLIER_PAYMENT', 23, 'SPV-000023', 'Supplier payment SPV-000023 created.', NULL, '{\"supplier_id\":5,\"payment_date\":\"2026-09-27\",\"payment_method\":\"Bank Transfer\",\"reference_no\":\"\",\"remarks\":\"\",\"total_amount\":100000,\"advance_amount\":0,\"advance_used\":7085}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 12:02:18'),
(66, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 12:41:18'),
(67, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 36, 'EXP-000007', 'Automatic withdrawal posted from EXPENSE.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-27\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":2500,\"reference_type\":\"EXPENSE\",\"reference_id\":7}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 12:52:32'),
(68, 1, 'Expense', 'CREATE', 'EXPENSE', 7, 'EXP-000007', 'Expense EXP-000007 created.', NULL, '{\"expense_no\":\"EXP-000007\",\"expense_date\":\"2026-09-27\",\"category_id\":2,\"project_id\":26,\"paid_to\":\"Transport Charge\",\"payment_method\":\"CHEQUE\",\"bank_account_id\":7,\"amount\":\"2500.00\",\"remarks\":null,\"status\":\"PAID\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 12:52:32'),
(69, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 15:12:45'),
(72, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 39, 'VBP/SI/9822', 'Automatic deposit posted from SALE_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-28\",\"transaction_type\":\"DEPOSIT\",\"amount\":76925,\"reference_type\":\"SALE_PAYMENT\",\"reference_id\":5}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 15:33:52'),
(73, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 40, 'VBP/SI/9823', 'Automatic deposit posted from SALE_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-29\",\"transaction_type\":\"DEPOSIT\",\"amount\":10832,\"reference_type\":\"SALE_PAYMENT\",\"reference_id\":6}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:156.0) Gecko/20100101 Firefox/156.0', '2026-09-29 18:20:22'),
(74, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '::1', 'curl/8.16.0', '2026-09-29 18:48:25'),
(75, 1, 'Authentication', 'LOGOUT', 'USER', 1, 'aainv', 'User \'aainv\' logged out.', NULL, NULL, '::1', 'curl/8.16.0', '2026-09-29 18:50:41'),
(76, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '::1', 'curl/8.16.0', '2026-09-29 18:52:39'),
(77, 1, 'Authentication', 'LOGOUT', 'USER', 1, 'aainv', 'User \'aainv\' logged out.', NULL, NULL, '::1', 'curl/8.16.0', '2026-09-29 18:53:17'),
(78, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '::1', 'curl/8.16.0', '2026-09-29 18:59:56'),
(79, 1, 'Authentication', 'LOGOUT', 'USER', 1, 'aainv', 'User \'aainv\' logged out.', NULL, NULL, '::1', 'curl/8.16.0', '2026-09-29 19:00:10'),
(80, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '::1', 'curl/8.16.0', '2026-09-29 19:02:53'),
(81, 1, 'Authentication', 'LOGOUT', 'USER', 1, 'aainv', 'User \'aainv\' logged out.', NULL, NULL, '::1', 'curl/8.16.0', '2026-09-29 19:02:54'),
(82, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-09-30 09:57:06'),
(83, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 41, 'SRV-000001', 'Automatic deposit posted from SERVICE_RECEIPT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-09-29\",\"transaction_type\":\"DEPOSIT\",\"amount\":5900,\"reference_type\":\"SERVICE_RECEIPT\",\"reference_id\":1}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-09-30 10:56:04'),
(84, 1, 'Service Receipt', 'CREATE', 'SERVICE_RECEIPT', 1, 'SRV-000001', 'Service receipt SRV-000001 created.', NULL, '{\"subtotal\":5900,\"gst_total\":0,\"grand_total\":5900,\"received_amount\":5900,\"outstanding_amount\":0,\"payment_status\":\"PAID\",\"receipt_type\":\"DIRECT\",\"customer_id\":15,\"receipt_date\":\"2026-09-29\",\"attended_person\":\"SAM\",\"payment_mode\":\"BANK\",\"bank_account_id\":7,\"remarks\":null}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-09-30 10:56:04'),
(85, 1, 'Authentication', 'LOGOUT', 'USER', 1, 'aainv', 'User \'aainv\' logged out.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-09-30 12:20:06'),
(86, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-09-30 12:20:19'),
(89, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 44, NULL, 'Automatic withdrawal posted from MANUAL_WITHDRAWAL.', NULL, '{\"bank_account_id\":1,\"transaction_date\":\"2026-09-30\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":2500,\"reference_type\":\"MANUAL_WITHDRAWAL\",\"reference_id\":null}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-09-30 17:58:02'),
(90, 1, 'Loan', 'CREATE', 'LOAN', 2, 'LN-000002', 'Loan LN-000002 created.', NULL, '{\"lender_name\":\"Gold Kumar\",\"loan_type\":\"BANK\",\"bank_account_id\":7,\"account_number\":\"SGDSGsg\",\"sanctioned_amount\":200000,\"interest_rate\":5,\"tenure_months\":12,\"emi_amount\":17121.5,\"start_date\":\"2026-08-01\",\"end_date\":\"2027-08-01\",\"remarks\":null,\"loan_no\":\"LN-000002\",\"outstanding_principal\":200000,\"total_principal_paid\":0,\"total_interest_paid\":0,\"status\":\"ACTIVE\",\"created_by\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-09-30 18:06:07'),
(91, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 10:15:38'),
(92, 1, 'Loan', 'DELETE', 'LOAN', 2, 'LN-000002', 'Loan LN-000002 deleted.', '{\"id\":\"2\",\"loan_no\":\"LN-000002\",\"lender_name\":\"Gold Kumar\",\"loan_type\":\"BANK\",\"bank_account_id\":\"7\",\"account_number\":\"SGDSGsg\",\"sanctioned_amount\":\"200000.00\",\"interest_rate\":\"5.000\",\"tenure_months\":\"12\",\"emi_amount\":\"17121.50\",\"start_date\":\"2026-08-01\",\"end_date\":\"2027-08-01\",\"outstanding_principal\":\"200000.00\",\"total_principal_paid\":\"0.00\",\"total_interest_paid\":\"0.00\",\"status\":\"ACTIVE\",\"remarks\":null,\"created_by\":\"1\",\"created_at\":\"2026-09-30 18:06:07\",\"updated_at\":\"2026-09-30 18:06:07\"}', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 10:16:39'),
(93, 1, 'Loan', 'CREATE', 'LOAN', 3, 'LN-000002', 'Loan LN-000002 created.', NULL, '{\"lender_name\":\"Gold Kumar\",\"loan_type\":\"BANK\",\"bank_account_id\":7,\"account_number\":\"33223323\",\"sanctioned_amount\":200000,\"interest_rate\":7,\"tenure_months\":12,\"emi_amount\":17305.35,\"start_date\":\"2026-10-01\",\"end_date\":\"2027-10-01\",\"remarks\":null,\"loan_no\":\"LN-000002\",\"outstanding_principal\":200000,\"total_principal_paid\":0,\"total_interest_paid\":0,\"status\":\"ACTIVE\",\"created_by\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 10:22:47'),
(94, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 45, 'LR-000001', 'Automatic deposit posted from LOAN_DISBURSEMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-10-01\",\"transaction_type\":\"DEPOSIT\",\"amount\":200000,\"reference_type\":\"LOAN_DISBURSEMENT\",\"reference_id\":1}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 10:24:14'),
(95, 1, 'Loan', 'CREATE', 'LOAN_DISBURSEMENT', 1, 'LR-000001', 'Receipt LR-000001 recorded against loan LN-000002.', NULL, '{\"loan_id\":3,\"receipt_date\":\"2026-10-01\",\"amount\":200000,\"payment_method\":\"BANK\",\"bank_account_id\":7,\"reference_no\":null,\"remarks\":null,\"receipt_no\":\"LR-000001\",\"created_by\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 10:24:14'),
(96, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 46, 'LN-000002', 'Automatic withdrawal posted from LOAN_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-10-01\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":17305.35,\"reference_type\":\"LOAN_PAYMENT\",\"reference_id\":2}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 10:27:58'),
(97, 1, 'Loan', 'CREATE', 'LOAN_PAYMENT', 2, 'LN-000002', 'Payment recorded against loan LN-000002.', NULL, '{\"loan_id\":3,\"loan_emi_id\":25,\"payment_date\":\"2026-10-01\",\"payment_method\":\"BANK\",\"bank_account_id\":7,\"principal_paid\":16138.68,\"interest_paid\":1166.67,\"total_paid\":17305.35,\"reference_no\":null,\"remarks\":null,\"created_by\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 10:27:58'),
(98, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 47, 'LN-000002', 'Automatic withdrawal posted from LOAN_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-10-02\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":10100,\"reference_type\":\"LOAN_PAYMENT\",\"reference_id\":3}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 10:28:55'),
(99, 1, 'Loan', 'CREATE', 'LOAN_PAYMENT', 3, 'LN-000002', 'Payment recorded against loan LN-000002.', NULL, '{\"loan_id\":3,\"loan_emi_id\":null,\"payment_date\":\"2026-10-02\",\"payment_method\":\"BANK\",\"bank_account_id\":7,\"principal_paid\":10000,\"interest_paid\":100,\"total_paid\":10100,\"reference_no\":null,\"remarks\":null,\"created_by\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 10:28:55'),
(100, 1, 'Loan', 'DELETE', 'LOAN_PAYMENT', 3, 'LN-000002', 'Payment deleted from loan LN-000002.', '{\"id\":\"3\",\"loan_id\":\"3\",\"loan_emi_id\":null,\"payment_date\":\"2026-10-02\",\"payment_method\":\"BANK\",\"bank_account_id\":\"7\",\"principal_paid\":\"10000.00\",\"interest_paid\":\"100.00\",\"total_paid\":\"10100.00\",\"reference_no\":null,\"remarks\":null,\"created_by\":\"1\",\"created_at\":\"2026-10-01 10:28:55\",\"updated_at\":\"2026-10-01 10:28:55\"}', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 10:31:33'),
(101, 1, 'Loan', 'DELETE', 'LOAN_PAYMENT', 2, 'LN-000002', 'Payment deleted from loan LN-000002.', '{\"id\":\"2\",\"loan_id\":\"3\",\"loan_emi_id\":\"25\",\"payment_date\":\"2026-10-01\",\"payment_method\":\"BANK\",\"bank_account_id\":\"7\",\"principal_paid\":\"16138.68\",\"interest_paid\":\"1166.67\",\"total_paid\":\"17305.35\",\"reference_no\":null,\"remarks\":null,\"created_by\":\"1\",\"created_at\":\"2026-10-01 10:27:58\",\"updated_at\":\"2026-10-01 10:27:58\"}', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 10:46:46'),
(102, 1, 'Loan', 'CREATE', 'LOAN', 4, 'LN-000004', 'Loan LN-000004 created.', NULL, '{\"lender_name\":\"sgg\",\"loan_type\":\"BANK\",\"bank_account_id\":7,\"account_number\":null,\"sanctioned_amount\":50000,\"interest_rate\":0,\"tenure_months\":12,\"emi_amount\":4166.67,\"start_date\":\"2026-10-01\",\"end_date\":\"2027-10-01\",\"remarks\":null,\"loan_no\":\"LN-000004\",\"outstanding_principal\":50000,\"total_principal_paid\":0,\"total_interest_paid\":0,\"status\":\"ACTIVE\",\"created_by\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 11:41:12'),
(103, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 48, 'LR-000002', 'Automatic deposit posted from LOAN_DISBURSEMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-10-01\",\"transaction_type\":\"DEPOSIT\",\"amount\":50000,\"reference_type\":\"LOAN_DISBURSEMENT\",\"reference_id\":2}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 11:41:25'),
(104, 1, 'Loan', 'CREATE', 'LOAN_DISBURSEMENT', 2, 'LR-000002', 'Receipt LR-000002 recorded against loan LN-000004.', NULL, '{\"loan_id\":4,\"receipt_date\":\"2026-10-01\",\"amount\":50000,\"payment_method\":\"BANK\",\"bank_account_id\":7,\"reference_no\":null,\"remarks\":null,\"receipt_no\":\"LR-000002\",\"created_by\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 11:41:25'),
(105, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 49, 'LN-000004', 'Automatic withdrawal posted from LOAN_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-10-01\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":12110,\"reference_type\":\"LOAN_PAYMENT\",\"reference_id\":4}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 11:42:35'),
(106, 1, 'Loan', 'CREATE', 'LOAN_PAYMENT', 4, 'LN-000004', 'Payment recorded against loan LN-000004.', NULL, '{\"loan_id\":4,\"loan_emi_id\":37,\"payment_date\":\"2026-10-01\",\"payment_method\":\"BANK\",\"bank_account_id\":7,\"principal_paid\":12000,\"interest_paid\":110,\"total_paid\":12110,\"reference_no\":null,\"remarks\":null,\"created_by\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 11:42:35'),
(107, 1, 'Loan', 'DELETE', 'LOAN_DISBURSEMENT', 2, 'LR-000002', 'Receipt LR-000002 deleted from loan LN-000004.', '{\"id\":\"2\",\"loan_id\":\"4\",\"receipt_no\":\"LR-000002\",\"receipt_date\":\"2026-10-01\",\"amount\":\"50000.00\",\"payment_method\":\"BANK\",\"bank_account_id\":\"7\",\"reference_no\":null,\"remarks\":null,\"created_by\":\"1\",\"created_at\":\"2026-10-01 11:41:24\",\"updated_at\":\"2026-10-01 11:41:24\"}', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 12:08:36'),
(108, 1, 'Loan', 'DELETE', 'LOAN_PAYMENT', 4, 'LN-000004', 'Payment deleted from loan LN-000004.', '{\"id\":\"4\",\"loan_id\":\"4\",\"loan_emi_id\":\"37\",\"payment_date\":\"2026-10-01\",\"payment_method\":\"BANK\",\"bank_account_id\":\"7\",\"principal_paid\":\"12000.00\",\"interest_paid\":\"110.00\",\"total_paid\":\"12110.00\",\"reference_no\":null,\"remarks\":null,\"created_by\":\"1\",\"created_at\":\"2026-10-01 11:42:35\",\"updated_at\":\"2026-10-01 11:42:35\"}', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 12:08:59'),
(109, 1, 'Loan', 'DELETE', 'LOAN', 4, 'LN-000004', 'Loan LN-000004 deleted.', '{\"id\":\"4\",\"loan_no\":\"LN-000004\",\"lender_name\":\"sgg\",\"loan_type\":\"BANK\",\"bank_account_id\":\"7\",\"account_number\":null,\"sanctioned_amount\":\"50000.00\",\"interest_rate\":\"0.000\",\"tenure_months\":\"12\",\"emi_amount\":\"4166.67\",\"start_date\":\"2026-10-01\",\"end_date\":\"2027-10-01\",\"outstanding_principal\":\"50000.00\",\"total_principal_paid\":\"0.00\",\"total_interest_paid\":\"0.00\",\"status\":\"ACTIVE\",\"remarks\":null,\"created_by\":\"1\",\"created_at\":\"2026-10-01 11:41:12\",\"updated_at\":\"2026-10-01 12:08:59\"}', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 12:09:07'),
(110, 1, 'Loan', 'DELETE', 'LOAN_DISBURSEMENT', 1, 'LR-000001', 'Receipt LR-000001 deleted from loan LN-000002.', '{\"id\":\"1\",\"loan_id\":\"3\",\"receipt_no\":\"LR-000001\",\"receipt_date\":\"2026-10-01\",\"amount\":\"200000.00\",\"payment_method\":\"BANK\",\"bank_account_id\":\"7\",\"reference_no\":null,\"remarks\":null,\"created_by\":\"1\",\"created_at\":\"2026-10-01 10:24:14\",\"updated_at\":\"2026-10-01 10:24:14\"}', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 12:09:19'),
(111, 1, 'Loan', 'DELETE', 'LOAN', 3, 'LN-000002', 'Loan LN-000002 deleted.', '{\"id\":\"3\",\"loan_no\":\"LN-000002\",\"lender_name\":\"Gold Kumar\",\"loan_type\":\"BANK\",\"bank_account_id\":\"7\",\"account_number\":\"33223323\",\"sanctioned_amount\":\"200000.00\",\"interest_rate\":\"7.000\",\"tenure_months\":\"12\",\"emi_amount\":\"17305.35\",\"start_date\":\"2026-10-01\",\"end_date\":\"2027-10-01\",\"outstanding_principal\":\"200000.00\",\"total_principal_paid\":\"0.00\",\"total_interest_paid\":\"0.00\",\"status\":\"ACTIVE\",\"remarks\":null,\"created_by\":\"1\",\"created_at\":\"2026-10-01 10:22:47\",\"updated_at\":\"2026-10-01 10:46:46\"}', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 12:09:37'),
(112, 1, 'Loan', 'CREATE', 'LOAN', 5, 'LN-000002', 'Loan LN-000002 created.', NULL, '{\"lender_name\":\"John\",\"loan_type\":\"PERSONAL\",\"account_number\":\"987461331\",\"sanctioned_amount\":100000,\"interest_rate\":0,\"tenure_months\":0,\"start_date\":\"2026-10-01\",\"remarks\":null,\"emi_amount\":0,\"loan_no\":\"LN-000002\",\"outstanding_principal\":100000,\"total_principal_paid\":0,\"total_interest_paid\":0,\"status\":\"ACTIVE\",\"created_by\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 12:10:22'),
(113, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 50, 'LR-000001', 'Automatic deposit posted from LOAN_DISBURSEMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-10-01\",\"transaction_type\":\"DEPOSIT\",\"amount\":100000,\"reference_type\":\"LOAN_DISBURSEMENT\",\"reference_id\":3}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 12:10:53'),
(114, 1, 'Loan', 'CREATE', 'LOAN_DISBURSEMENT', 3, 'LR-000001', 'Receipt LR-000001 recorded against loan LN-000002.', NULL, '{\"loan_id\":5,\"receipt_date\":\"2026-10-01\",\"amount\":100000,\"payment_method\":\"CHEQUE\",\"bank_account_id\":7,\"reference_no\":null,\"remarks\":null,\"receipt_no\":\"LR-000001\",\"created_by\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 12:10:53'),
(115, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 51, 'LN-000002', 'Automatic withdrawal posted from LOAN_PAYMENT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-10-01\",\"transaction_type\":\"WITHDRAWAL\",\"amount\":3000,\"reference_type\":\"LOAN_PAYMENT\",\"reference_id\":5}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 12:11:33'),
(116, 1, 'Loan', 'CREATE', 'LOAN_PAYMENT', 5, 'LN-000002', 'Payment recorded against loan LN-000002.', NULL, '{\"loan_id\":5,\"loan_emi_id\":null,\"payment_date\":\"2026-10-01\",\"payment_method\":\"BANK\",\"bank_account_id\":7,\"principal_paid\":2500,\"interest_paid\":500,\"total_paid\":3000,\"reference_no\":null,\"remarks\":null,\"created_by\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 12:11:33'),
(117, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 15:05:26'),
(118, 1, 'Loan', 'CREATE', 'LOAN', 6, 'LN-000006', 'Loan LN-000006 created.', NULL, '{\"lender_name\":\"shsh\",\"loan_type\":\"BANK\",\"account_number\":\"5352\",\"sanctioned_amount\":23333,\"interest_rate\":10,\"tenure_months\":12,\"start_date\":\"2026-10-15\",\"remarks\":null,\"emi_amount\":0,\"loan_no\":\"LN-000006\",\"outstanding_principal\":23333,\"total_principal_paid\":0,\"total_interest_paid\":0,\"status\":\"ACTIVE\",\"created_by\":\"1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 15:14:23'),
(119, 1, 'Loan', 'DELETE', 'LOAN', 6, 'LN-000006', 'Loan LN-000006 deleted.', '{\"id\":\"6\",\"loan_no\":\"LN-000006\",\"lender_name\":\"shsh\",\"loan_type\":\"BANK\",\"bank_account_id\":null,\"account_number\":\"5352\",\"sanctioned_amount\":\"23333.00\",\"interest_rate\":\"10.000\",\"tenure_months\":\"12\",\"emi_amount\":\"0.00\",\"start_date\":\"2026-10-15\",\"end_date\":null,\"outstanding_principal\":\"23333.00\",\"total_principal_paid\":\"0.00\",\"total_interest_paid\":\"0.00\",\"status\":\"ACTIVE\",\"remarks\":null,\"created_by\":\"1\",\"created_at\":\"2026-10-01 15:14:23\",\"updated_at\":\"2026-10-01 15:14:23\"}', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 15:17:31'),
(120, 1, 'Loan', 'DELETE', 'LOAN_PAYMENT', 1, 'LN-000001', 'Payment deleted from loan LN-000001.', '{\"id\":\"1\",\"loan_id\":\"1\",\"loan_emi_id\":\"1\",\"payment_date\":\"2026-10-16\",\"payment_method\":\"BANK\",\"bank_account_id\":\"7\",\"principal_paid\":\"8257.22\",\"interest_paid\":\"166.67\",\"total_paid\":\"8423.89\",\"reference_no\":null,\"remarks\":null,\"created_by\":\"1\",\"created_at\":\"2026-09-25 18:41:52\",\"updated_at\":\"2026-09-25 18:41:52\"}', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 15:18:49'),
(121, 1, 'Loan', 'DELETE', 'LOAN', 1, 'LN-000001', 'Loan LN-000001 deleted.', '{\"id\":\"1\",\"loan_no\":\"LN-000001\",\"lender_name\":\"TEs\",\"loan_type\":\"PERSONAL\",\"bank_account_id\":null,\"account_number\":null,\"sanctioned_amount\":\"100000.00\",\"interest_rate\":\"2.000\",\"tenure_months\":\"12\",\"emi_amount\":\"8423.89\",\"start_date\":\"2026-09-16\",\"end_date\":\"2027-09-16\",\"outstanding_principal\":\"100000.00\",\"total_principal_paid\":\"0.00\",\"total_interest_paid\":\"0.00\",\"status\":\"ACTIVE\",\"remarks\":null,\"created_by\":\"1\",\"created_at\":\"2026-09-25 18:40:42\",\"updated_at\":\"2026-10-01 15:18:49\"}', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 15:18:55'),
(122, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 16:51:26'),
(123, 1, 'Bank', 'CREATE', 'BANK_TRANSACTION', 52, 'SRV-000002', 'Automatic deposit posted from SERVICE_RECEIPT.', NULL, '{\"bank_account_id\":7,\"transaction_date\":\"2026-10-01\",\"transaction_type\":\"DEPOSIT\",\"amount\":5000,\"reference_type\":\"SERVICE_RECEIPT\",\"reference_id\":2}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 18:45:07');
INSERT INTO `audit_logs` (`id`, `user_id`, `module`, `action`, `reference_type`, `reference_id`, `reference_no`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(124, 1, 'Service Receipt', 'CREATE', 'SERVICE_RECEIPT', 2, 'SRV-000002', 'Service receipt SRV-000002 created.', NULL, '{\"subtotal\":5000,\"gst_total\":0,\"grand_total\":5000,\"received_amount\":5000,\"outstanding_amount\":0,\"payment_status\":\"PAID\",\"receipt_type\":\"DIRECT\",\"customer_id\":4,\"receipt_date\":\"2026-10-01\",\"attended_person\":\"RAW\",\"payment_mode\":\"UPI\",\"bank_account_id\":7,\"remarks\":null}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-01 18:45:07'),
(125, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-02 10:31:36'),
(126, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-02 15:22:04'),
(127, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-02 15:22:05'),
(128, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-02 15:22:05'),
(129, 1, 'Authentication', 'LOGIN', 'USER', 1, 'aainv', 'User \'aainv\' logged in.', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-02 15:22:05'),
(130, 1, 'Cash Book', 'CREATE', 'CASH_OPENING_BALANCE', 1, NULL, 'Cash opening balance set (permanent)', NULL, '{\"opening_date\":\"2026-04-01\",\"amount\":\"250000.00\",\"remarks\":\"Opening Cash Balance\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0', '2026-10-02 18:12:00');

-- --------------------------------------------------------

--
-- Table structure for table `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` int(10) UNSIGNED NOT NULL,
  `bank_name` varchar(150) NOT NULL,
  `account_name` varchar(150) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `ifsc_code` varchar(20) DEFAULT NULL,
  `branch_name` varchar(150) DEFAULT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bank_accounts`
--

INSERT INTO `bank_accounts` (`id`, `bank_name`, `account_name`, `account_number`, `ifsc_code`, `branch_name`, `opening_balance`, `current_balance`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'State Bank Of India', 'HASA', 'SGA52352525', 'GSG525232', 'ThillaiNagar', 150000.00, 238999.90, 1, 1, '2026-09-24 15:20:51', '2026-09-24 15:20:51'),
(7, 'Indian Bank', 'JAAN', 'IBS2352525', 'SBW2532535', 'MNallur', 35000.00, 65843.70, 1, 1, '2026-09-25 16:06:43', '2026-09-25 16:06:43');

-- --------------------------------------------------------

--
-- Table structure for table `bank_transactions`
--

CREATE TABLE `bank_transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `bank_account_id` int(10) UNSIGNED NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` enum('DEPOSIT','WITHDRAWAL','TRANSFER','TRANSFER_OUT','TRANSFER_IN') NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(10) UNSIGNED DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `payment_mode` varchar(20) DEFAULT NULL,
  `party_name` varchar(150) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `transfer_bank_account_id` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bank_transactions`
--

INSERT INTO `bank_transactions` (`id`, `bank_account_id`, `transaction_date`, `transaction_type`, `amount`, `reference_type`, `reference_id`, `reference_no`, `payment_mode`, `party_name`, `category`, `remarks`, `transfer_bank_account_id`, `created_by`, `created_at`, `updated_at`) VALUES
(5, 1, '2026-09-25', 'WITHDRAWAL', 8500.10, 'SUPPLIER_PAYMENT', 5, 'SPV-000001', NULL, NULL, NULL, 'Supplier Payment - Asian Paints Distributor', NULL, 1, '2026-09-25 10:43:27', '2026-09-25 10:43:27'),
(7, 1, '2026-09-20', 'DEPOSIT', 100000.00, 'PROJECT_ADVANCE_DEPOSIT', 23, 'PRJ-000023', NULL, NULL, NULL, 'Project Advance Received - RK Interiors', NULL, 1, '2026-09-25 15:28:53', '2026-09-25 15:28:53'),
(8, 7, '2026-09-25', 'WITHDRAWAL', 20461.20, 'SUPPLIER_PAYMENT', 6, 'SPV-000006', NULL, NULL, NULL, 'Supplier Payment - JK Pipes & Fittings', NULL, 1, '2026-09-25 16:12:13', '2026-09-25 16:12:13'),
(9, 7, '2026-09-10', 'DEPOSIT', 50000.00, 'PROJECT_ADVANCE_DEPOSIT', 24, 'PRJ-000024', NULL, NULL, NULL, 'Project Advance Received - Elite Diagnostics', NULL, 1, '2026-09-25 17:53:03', '2026-09-25 17:53:03'),
(10, 7, '2026-09-11', 'WITHDRAWAL', 3000.00, 'SUPPLIER_PAYMENT', 7, 'SPV-000007', NULL, NULL, NULL, 'Supplier Payment - Harsha Industrial Supplies', NULL, 1, '2026-09-25 17:55:49', '2026-09-25 17:55:49'),
(12, 7, '2026-09-01', 'DEPOSIT', 50000.00, 'PROJECT_ADVANCE_DEPOSIT', 25, 'PRJ-000025', NULL, NULL, NULL, 'Project Advance Received - Green Leaf Hospital', NULL, 1, '2026-09-26 10:16:33', '2026-09-26 10:16:33'),
(13, 7, '2026-09-04', 'DEPOSIT', 9048.00, 'PROJECT_ADVANCE', 5, 'CASH-0005', NULL, NULL, NULL, 'Project Advance - Green Leaf Project', NULL, 1, '2026-09-26 12:18:09', '2026-09-26 12:18:09'),
(14, 7, '2026-09-26', 'WITHDRAWAL', 2500.00, 'EXPENSE', 4, 'EXP-000003', NULL, NULL, NULL, 'Expense - Labour', NULL, 1, '2026-09-26 18:17:08', '2026-09-26 18:17:08'),
(15, 7, '2026-09-26', 'WITHDRAWAL', 6300.00, 'EXPENSE', 5, 'EXP-000005', NULL, NULL, NULL, 'Expense - Equipment Rental', NULL, 1, '2026-09-26 18:18:14', '2026-09-26 18:18:14'),
(16, 7, '2026-09-08', 'WITHDRAWAL', 177.00, 'SUPPLIER_ADVANCE', 2, 'GP-000002', NULL, NULL, NULL, 'Supplier Advance - Chennai Steel Traders', NULL, 1, '2026-09-28 11:04:21', '2026-09-28 11:04:21'),
(17, 7, '2026-09-09', 'WITHDRAWAL', 1000.10, 'SUPPLIER_PAYMENT', 9, 'SPV-000009', NULL, NULL, NULL, 'Supplier Payment - Chennai Steel Traders', NULL, 1, '2026-09-28 11:11:39', '2026-09-28 11:11:39'),
(18, 7, '2026-09-09', 'WITHDRAWAL', 1000.00, 'SUPPLIER_PAYMENT', 10, 'SPV-000010', NULL, NULL, NULL, 'Supplier Payment - Chennai Steel Traders', NULL, 1, '2026-09-28 11:13:42', '2026-09-28 11:13:42'),
(19, 7, '2026-09-28', 'WITHDRAWAL', 437.60, 'SUPPLIER_ADVANCE', 3, 'GP-000003', NULL, NULL, NULL, 'Supplier Advance - Supreme Tiles & Granites', NULL, 1, '2026-09-28 11:56:22', '2026-09-28 11:56:22'),
(20, 7, '2026-09-28', 'WITHDRAWAL', 10000.00, 'SUPPLIER_PAYMENT', 11, 'SPV-000011', NULL, NULL, NULL, 'Supplier Payment - Supreme Tiles & Granites', NULL, 1, '2026-09-28 16:02:04', '2026-09-28 16:02:04'),
(21, 7, '2026-09-20', 'WITHDRAWAL', 8556.00, 'SUPPLIER_ADVANCE', 4, 'GP-000004', NULL, NULL, NULL, 'Supplier Advance - Harsha Industrial Supplies', NULL, 1, '2026-09-28 16:05:46', '2026-09-28 16:05:46'),
(22, 7, '2026-09-28', 'WITHDRAWAL', 100792.80, 'SUPPLIER_PAYMENT', 13, 'SPV-000013', NULL, NULL, NULL, 'Supplier Payment - Harsha Industrial Supplies', NULL, 1, '2026-09-28 16:08:25', '2026-09-28 16:08:25'),
(23, 7, '2026-09-28', 'WITHDRAWAL', 1000.00, 'SUPPLIER_ADVANCE', 5, 'GP-000005', NULL, NULL, NULL, 'Supplier Advance - Asian Paints Distributor', NULL, 1, '2026-09-28 17:01:03', '2026-09-28 17:01:03'),
(24, 7, '2026-09-28', 'WITHDRAWAL', 416.20, 'SUPPLIER_ADVANCE', 6, 'GP-000006', NULL, NULL, NULL, 'Supplier Advance - JK Pipes & Fittings', NULL, 1, '2026-09-28 17:52:15', '2026-09-28 17:52:15'),
(25, 7, '2026-09-28', 'WITHDRAWAL', 4000.00, 'SUPPLIER_PAYMENT', 16, 'SPV-000016', NULL, NULL, NULL, 'Supplier Payment - JK Pipes & Fittings', NULL, 1, '2026-09-28 17:53:58', '2026-09-28 17:53:58'),
(26, 7, '2026-09-29', 'WITHDRAWAL', 1000.00, 'SUPPLIER_PAYMENT', 17, 'SPV-000017', NULL, NULL, NULL, 'Supplier Payment - JK Pipes & Fittings', NULL, 1, '2026-09-28 17:54:23', '2026-09-28 17:54:23'),
(27, 7, '2026-09-29', 'WITHDRAWAL', 21311.00, 'EXPENSE', 6, 'EXP-000006', NULL, NULL, NULL, 'Expense - Utilities', NULL, 1, '2026-09-29 10:30:05', '2026-09-29 10:30:05'),
(28, 7, '2026-09-21', 'WITHDRAWAL', 7324.40, 'SUPPLIER_ADVANCE', 7, 'GP-000007', NULL, NULL, NULL, 'Supplier Advance - Modern Sanitary World', NULL, 1, '2026-09-29 10:45:50', '2026-09-29 10:45:50'),
(29, 7, '2026-09-22', 'WITHDRAWAL', 40000.00, 'SUPPLIER_PAYMENT', 19, 'SPV-000019', NULL, NULL, NULL, 'Supplier Payment - Modern Sanitary World', NULL, 1, '2026-09-29 11:52:16', '2026-09-29 11:52:16'),
(31, 7, '2026-09-23', 'WITHDRAWAL', 5000.00, 'SUPPLIER_PAYMENT', 20, 'SPV-000020', NULL, NULL, NULL, 'Supplier Payment - Modern Sanitary World', NULL, 1, '2026-09-29 11:54:35', '2026-09-29 11:54:35'),
(32, 7, '2026-09-24', 'WITHDRAWAL', 5000.00, 'SUPPLIER_PAYMENT', 21, 'SPV-000021', NULL, NULL, NULL, 'Supplier Payment - Modern Sanitary World', NULL, 1, '2026-09-29 11:54:59', '2026-09-29 11:54:59'),
(33, 7, '2026-09-25', 'DEPOSIT', 75000.00, 'PROJECT_ADVANCE_DEPOSIT', 26, 'PRJ-000026', NULL, NULL, NULL, 'Project Advance Received - Vinayaga', NULL, 1, '2026-09-29 11:57:51', '2026-09-29 11:57:51'),
(34, 7, '2026-09-26', 'WITHDRAWAL', 7085.00, 'SUPPLIER_PAYMENT', 22, 'SPV-000022', NULL, NULL, NULL, 'Supplier Payment - Vinayaga Cement Depot', NULL, 1, '2026-09-29 12:01:19', '2026-09-29 12:01:19'),
(35, 7, '2026-09-27', 'WITHDRAWAL', 100000.00, 'SUPPLIER_PAYMENT', 23, 'SPV-000023', NULL, NULL, NULL, 'Supplier Payment - Vinayaga Cement Depot', NULL, 1, '2026-09-29 12:02:18', '2026-09-29 12:02:18'),
(36, 7, '2026-09-27', 'WITHDRAWAL', 2500.00, 'EXPENSE', 7, 'EXP-000007', NULL, NULL, NULL, 'Expense - Transport', NULL, 1, '2026-09-29 12:52:32', '2026-09-29 12:52:32'),
(39, 7, '2026-09-28', 'DEPOSIT', 76925.00, 'SALE_PAYMENT', 5, 'VBP/SI/9822', NULL, NULL, NULL, 'Invoice Payment - VBP/SI/9822', NULL, 1, '2026-09-29 15:33:52', '2026-09-29 15:33:52'),
(40, 7, '2026-09-29', 'DEPOSIT', 10832.00, 'SALE_PAYMENT', 6, 'VBP/SI/9823', NULL, NULL, NULL, 'Invoice Payment - VBP/SI/9823', NULL, 1, '2026-09-29 18:20:22', '2026-09-29 18:20:22'),
(41, 7, '2026-09-29', 'DEPOSIT', 5900.00, 'SERVICE_RECEIPT', 1, 'SRV-000001', NULL, NULL, NULL, 'Service Receipt - Vinayaga', NULL, 1, '2026-09-30 10:56:03', '2026-09-30 10:56:03'),
(44, 1, '2026-09-30', 'WITHDRAWAL', 2500.00, 'MANUAL_WITHDRAWAL', NULL, NULL, 'CASH', 'oiio', NULL, NULL, NULL, 1, '2026-09-30 17:58:02', '2026-09-30 17:58:02'),
(50, 7, '2026-10-01', 'DEPOSIT', 100000.00, 'LOAN_DISBURSEMENT', 3, 'LR-000001', NULL, NULL, NULL, 'Loan Disbursement – John', NULL, 1, '2026-10-01 12:10:53', '2026-10-01 12:10:53'),
(51, 7, '2026-10-01', 'WITHDRAWAL', 3000.00, 'LOAN_PAYMENT', 5, 'LN-000002', NULL, NULL, NULL, 'Loan EMI Payment – John', NULL, 1, '2026-10-01 12:11:33', '2026-10-01 12:11:33'),
(52, 7, '2026-10-01', 'DEPOSIT', 5000.00, 'SERVICE_RECEIPT', 2, 'SRV-000002', NULL, NULL, NULL, 'Service Receipt - Green Leaf Hospital', NULL, 1, '2026-10-01 18:45:07', '2026-10-01 18:45:07');

-- --------------------------------------------------------

--
-- Table structure for table `cash_opening_balance`
--

CREATE TABLE `cash_opening_balance` (
  `id` int(10) UNSIGNED NOT NULL,
  `opening_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remarks` varchar(255) DEFAULT NULL,
  `lock_key` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cash_opening_balance`
--

INSERT INTO `cash_opening_balance` (`id`, `opening_date`, `amount`, `remarks`, `lock_key`, `created_at`, `updated_at`) VALUES
(1, '2026-04-01', 250000.00, 'Opening Cash Balance', 1, '2026-10-02 18:12:00', '2026-10-02 18:12:00');

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
(14, 'MARTS', '', NULL, '9675645445', '', '', '2026-09-05 11:12:06', '2026-09-05 11:12:06'),
(15, 'Vinayaga', '', NULL, '987456235', '', '', '2026-09-29 11:57:13', '2026-09-29 11:57:13');

-- --------------------------------------------------------

--
-- Table structure for table `customer_payments`
--

CREATE TABLE `customer_payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `payment_no` varchar(20) NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('CASH','BANK','CHEQUE','UPI','OTHER') NOT NULL DEFAULT 'CASH',
  `bank_account_id` int(10) UNSIGNED DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `advance_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_payment_allocations`
--

CREATE TABLE `customer_payment_allocations` (
  `id` int(10) UNSIGNED NOT NULL,
  `customer_payment_id` int(10) UNSIGNED NOT NULL,
  `service_receipt_id` int(10) UNSIGNED NOT NULL,
  `invoice_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `database_backups`
--

CREATE TABLE `database_backups` (
  `id` int(10) UNSIGNED NOT NULL,
  `backup_name` varchar(150) NOT NULL,
  `backup_type` enum('MANUAL','AUTO','RESTORE') NOT NULL DEFAULT 'MANUAL',
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `total_tables` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('SUCCESS','FAILED') DEFAULT 'SUCCESS',
  `restore_of` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(10) UNSIGNED NOT NULL,
  `expense_no` varchar(20) NOT NULL,
  `project_id` int(10) UNSIGNED DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `description` text DEFAULT NULL,
  `paid_to` varchar(150) NOT NULL DEFAULT '',
  `payment_method` enum('CASH','BANK','CHEQUE','UPI','OTHER') NOT NULL DEFAULT 'CASH',
  `bank_account_id` int(10) UNSIGNED DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('PAID','CANCELLED') NOT NULL DEFAULT 'PAID',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `expense_date` date NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `expense_no`, `project_id`, `category`, `category_id`, `description`, `paid_to`, `payment_method`, `bank_account_id`, `amount`, `remarks`, `status`, `created_by`, `expense_date`, `created_at`, `updated_at`) VALUES
(1, 'EXP-000001', 2, 'Transport', 2, '', 'Legacy Entry', 'CASH', NULL, 5000.00, NULL, 'PAID', NULL, '2026-06-29', '2026-09-03 15:47:21', '2026-09-22 10:53:25'),
(2, 'EXP-000002', 2, 'Labour', 1, '', 'Legacy Entry', 'CASH', NULL, 4500.00, NULL, 'PAID', NULL, '2026-06-29', '2026-09-03 15:47:47', '2026-09-22 10:53:25'),
(4, 'EXP-000003', 25, '', 1, NULL, 'Vendor', 'BANK', 7, 2500.00, NULL, 'PAID', 1, '2026-09-26', '2026-09-26 18:17:08', '2026-09-26 18:17:08'),
(5, 'EXP-000005', NULL, '', 5, NULL, 'Paid for euipment rent', 'UPI', 7, 6300.00, NULL, 'PAID', 1, '2026-09-26', '2026-09-26 18:18:14', '2026-09-26 18:18:14'),
(6, 'EXP-000006', NULL, '', 3, NULL, 'pppp', 'UPI', 7, 21311.00, NULL, 'PAID', 1, '2026-09-29', '2026-09-29 10:30:05', '2026-09-29 10:30:05'),
(7, 'EXP-000007', 26, '', 2, NULL, 'Transport Charge', 'CHEQUE', 7, 2500.00, NULL, 'PAID', 1, '2026-09-27', '2026-09-29 12:52:32', '2026-09-29 12:52:32');

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `category_name`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Labour', NULL, 'ACTIVE', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(2, 'Transport', NULL, 'ACTIVE', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(3, 'Utilities', NULL, 'ACTIVE', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(4, 'Office Supplies', NULL, 'ACTIVE', '2026-09-03 15:25:46', '2026-09-03 15:25:46'),
(5, 'Equipment Rental', NULL, 'ACTIVE', '2026-09-03 15:25:46', '2026-09-03 15:25:46');

-- --------------------------------------------------------

--
-- Table structure for table `general_purchases`
--

CREATE TABLE `general_purchases` (
  `id` int(10) UNSIGNED NOT NULL,
  `purchase_no` varchar(20) NOT NULL,
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `purchase_date` date NOT NULL,
  `bill_no` varchar(100) DEFAULT NULL,
  `bill_date` date DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gst_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `advance_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `outstanding_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('Pending','Partial','Paid') NOT NULL DEFAULT 'Pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `bank_account_id` int(10) UNSIGNED DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `general_purchases`
--

INSERT INTO `general_purchases` (`id`, `purchase_no`, `supplier_id`, `purchase_date`, `bill_no`, `bill_date`, `subtotal`, `gst_total`, `grand_total`, `advance_paid`, `outstanding_amount`, `payment_status`, `payment_method`, `bank_account_id`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'GP-000001', 1, '2026-09-24', 'DSA242353', '2026-09-24', 7695.00, 1385.10, 9080.10, 580.00, 0.00, 'Paid', 'CHEQUE', NULL, '', 1, '2026-09-24 11:05:38', '2026-09-24 11:05:38'),
(2, 'GP-000002', 3, '2026-09-08', '31212', '2026-09-09', 1845.00, 332.10, 2177.10, 177.00, 0.00, 'Paid', 'BANK_TRANSFER', 7, '', 1, '2026-09-28 11:04:21', '2026-09-28 11:04:21'),
(3, 'GP-000003', 6, '2026-09-28', 'STGPB123', '2026-09-28', 17320.00, 3117.60, 20437.60, 437.60, 10000.00, 'Partial', 'BANK_TRANSFER', 7, '', 1, '2026-09-28 11:56:21', '2026-09-28 11:56:21'),
(4, 'GP-000004', 10, '2026-09-20', 'HIS23598', '2026-09-20', 24200.00, 4356.00, 28556.00, 8556.00, 20000.00, 'Partial', 'UPI', 7, '', 1, '2026-09-28 16:05:46', '2026-09-28 16:05:46'),
(5, 'GP-000005', 1, '2026-09-28', 'SF775', '2026-09-28', 2050.00, 369.00, 2419.00, 1000.00, 1419.00, 'Partial', 'BANK_TRANSFER', 7, '', 1, '2026-09-28 17:01:03', '2026-09-28 17:01:03'),
(6, 'GP-000006', 7, '2026-09-28', 'JKPB3493', '2026-09-28', 4590.00, 826.20, 5416.20, 416.20, 0.00, 'Paid', 'CHEQUE', 7, '', 1, '2026-09-28 17:52:14', '2026-09-28 17:52:14'),
(7, 'GP-000007', 9, '2026-09-21', 'MSW/GP/00998', '2026-09-21', 48580.00, 8744.40, 57324.40, 7324.40, 0.00, 'Paid', 'BANK_TRANSFER', 7, '', 1, '2026-09-29 10:45:30', '2026-09-29 10:45:50'),
(8, 'GP-000008', 5, '2026-09-29', 'HDSGSD', '2026-09-29', 47395.00, 8144.35, 55539.35, 0.00, 55539.35, 'Pending', NULL, NULL, '', 1, '2026-09-29 18:30:29', '2026-09-29 18:30:29');

-- --------------------------------------------------------

--
-- Table structure for table `general_purchase_items`
--

CREATE TABLE `general_purchase_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `general_purchase_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `rate` decimal(15,2) NOT NULL,
  `gst_percent` decimal(5,2) DEFAULT 0.00,
  `gst_amount` decimal(15,2) DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `general_purchase_items`
--

INSERT INTO `general_purchase_items` (`id`, `general_purchase_id`, `product_id`, `quantity`, `unit`, `rate`, `gst_percent`, `gst_amount`, `line_total`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1.000, 'Ltr', 4200.00, 18.00, 756.00, 4956.00, '2026-09-24 11:05:39', '2026-09-24 11:05:39'),
(2, 1, 5, 2.000, 'Pcs', 1650.00, 18.00, 594.00, 3894.00, '2026-09-24 11:05:39', '2026-09-24 11:05:39'),
(3, 1, 11, 1.000, 'Pcs', 195.00, 18.00, 35.10, 230.10, '2026-09-24 11:05:39', '2026-09-24 11:05:39'),
(4, 2, 11, 1.000, 'Pcs', 195.00, 18.00, 35.10, 230.10, '2026-09-28 11:04:21', '2026-09-28 11:04:21'),
(5, 2, 5, 1.000, 'Pcs', 1650.00, 18.00, 297.00, 1947.00, '2026-09-28 11:04:21', '2026-09-28 11:04:21'),
(6, 3, 7, 2.000, 'Pcs', 8320.00, 18.00, 2995.20, 19635.20, '2026-09-28 11:56:21', '2026-09-28 11:56:21'),
(7, 3, 10, 2.000, 'Pcs', 340.00, 18.00, 122.40, 802.40, '2026-09-28 11:56:21', '2026-09-28 11:56:21'),
(8, 4, 6, 2.000, 'Coil', 11500.00, 18.00, 4140.00, 27140.00, '2026-09-28 16:05:46', '2026-09-28 16:05:46'),
(9, 4, 12, 3.000, 'Bag', 400.00, 18.00, 216.00, 1416.00, '2026-09-28 16:05:46', '2026-09-28 16:05:46'),
(10, 5, 5, 1.000, 'Pcs', 1650.00, 18.00, 297.00, 1947.00, '2026-09-28 17:01:03', '2026-09-28 17:01:03'),
(11, 5, 12, 1.000, 'Bag', 400.00, 18.00, 72.00, 472.00, '2026-09-28 17:01:03', '2026-09-28 17:01:03'),
(12, 6, 1, 1.000, 'Ltr', 4200.00, 18.00, 756.00, 4956.00, '2026-09-28 17:52:14', '2026-09-28 17:52:14'),
(13, 6, 11, 2.000, 'Pcs', 195.00, 18.00, 70.20, 460.20, '2026-09-28 17:52:15', '2026-09-28 17:52:15'),
(17, 7, 6, 2.000, 'Coil', 11500.00, 18.00, 4140.00, 27140.00, '2026-09-29 10:45:50', '2026-09-29 10:45:50'),
(18, 7, 7, 3.000, 'Pcs', 8320.00, 18.00, 4492.80, 29452.80, '2026-09-29 10:45:50', '2026-09-29 10:45:50'),
(19, 7, 13, 1.000, 'Pcs', 620.00, 18.00, 111.60, 731.60, '2026-09-29 10:45:50', '2026-09-29 10:45:50'),
(20, 8, 3, 2.000, 'Ltr', 22210.00, 18.00, 7995.60, 52415.60, '2026-09-29 18:30:29', '2026-09-29 18:30:29'),
(21, 8, 21, 35.000, 'Pair', 85.00, 5.00, 148.75, 3123.75, '2026-09-29 18:30:29', '2026-09-29 18:30:29');

-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

CREATE TABLE `loans` (
  `id` int(10) UNSIGNED NOT NULL,
  `loan_no` varchar(20) NOT NULL,
  `lender_name` varchar(150) NOT NULL,
  `loan_type` enum('BANK','PERSONAL','VEHICLE','OD','OTHER') NOT NULL DEFAULT 'BANK',
  `bank_account_id` int(10) UNSIGNED DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `sanctioned_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_rate` decimal(7,3) NOT NULL DEFAULT 0.000,
  `tenure_months` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `emi_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `outstanding_principal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_principal_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_interest_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('ACTIVE','CLOSED') NOT NULL DEFAULT 'ACTIVE',
  `remarks` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loans`
--

INSERT INTO `loans` (`id`, `loan_no`, `lender_name`, `loan_type`, `bank_account_id`, `account_number`, `sanctioned_amount`, `interest_rate`, `tenure_months`, `emi_amount`, `start_date`, `end_date`, `outstanding_principal`, `total_principal_paid`, `total_interest_paid`, `status`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES
(5, 'LN-000002', 'John', 'PERSONAL', NULL, '987461331', 100000.00, 0.000, 0, 0.00, '2026-10-01', NULL, 97500.00, 2500.00, 500.00, 'ACTIVE', NULL, 1, '2026-10-01 12:10:22', '2026-10-01 12:11:33');

-- --------------------------------------------------------

--
-- Table structure for table `loan_emis`
--

CREATE TABLE `loan_emis` (
  `id` int(10) UNSIGNED NOT NULL,
  `loan_id` int(10) UNSIGNED NOT NULL,
  `emi_no` int(10) UNSIGNED NOT NULL,
  `due_date` date NOT NULL,
  `principal_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `emi_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('PENDING','PARTIAL','PAID') NOT NULL DEFAULT 'PENDING',
  `paid_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_payments`
--

CREATE TABLE `loan_payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `loan_id` int(10) UNSIGNED NOT NULL,
  `loan_emi_id` int(10) UNSIGNED DEFAULT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('CASH','BANK','CHEQUE','UPI','OTHER') NOT NULL DEFAULT 'BANK',
  `bank_account_id` int(10) UNSIGNED DEFAULT NULL,
  `principal_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reference_no` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loan_payments`
--

INSERT INTO `loan_payments` (`id`, `loan_id`, `loan_emi_id`, `payment_date`, `payment_method`, `bank_account_id`, `principal_paid`, `interest_paid`, `total_paid`, `reference_no`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES
(5, 5, NULL, '2026-10-01', 'BANK', 7, 2500.00, 500.00, 3000.00, NULL, NULL, 1, '2026-10-01 12:11:33', '2026-10-01 12:11:33');

-- --------------------------------------------------------

--
-- Table structure for table `loan_receipts`
--

CREATE TABLE `loan_receipts` (
  `id` int(10) UNSIGNED NOT NULL,
  `loan_id` int(10) UNSIGNED NOT NULL,
  `receipt_no` varchar(20) NOT NULL,
  `receipt_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('CASH','BANK','CHEQUE','UPI','OTHER') NOT NULL DEFAULT 'BANK',
  `bank_account_id` int(10) UNSIGNED DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loan_receipts`
--

INSERT INTO `loan_receipts` (`id`, `loan_id`, `receipt_no`, `receipt_date`, `amount`, `payment_method`, `bank_account_id`, `reference_no`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES
(3, 5, 'LR-000001', '2026-10-01', 100000.00, 'CHEQUE', 7, NULL, NULL, 1, '2026-10-01 12:10:53', '2026-10-01 12:10:53');

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
(12, '2026-09-04-000001', 'App\\Database\\Migrations\\CreatePasswordResets', 'default', 'App', 1788500885, 11),
(13, '2026-09-04-000002', 'App\\Database\\Migrations\\RestoreBasePrimaryKeysAndAutoIncrement', 'default', 'App', 1789988575, 12),
(14, '2026-09-04-000003', 'App\\Database\\Migrations\\RestoreBaseCustomersTable', 'default', 'App', 1789988577, 12),
(15, '2026-09-04-000004', 'App\\Database\\Migrations\\RestoreBaseIndexes', 'default', 'App', 1789988580, 12),
(16, '2026-09-04-000005', 'App\\Database\\Migrations\\RestoreBaseForeignKeys', 'default', 'App', 1789988611, 12),
(17, '2026-09-18-000000', 'App\\Database\\Migrations\\CreateGeneralPurchases', 'default', 'App', 1789988613, 12),
(18, '2026-09-18-000001', 'App\\Database\\Migrations\\CreateGeneralPurchaseItems', 'default', 'App', 1789988614, 12),
(19, '2026-09-18-000002', 'App\\Database\\Migrations\\AddGeneralPurchaseToStockLedgerReferenceType', 'default', 'App', 1789988614, 12),
(20, '2026-09-18-000003', 'App\\Database\\Migrations\\CreateSupplierPayments', 'default', 'App', 1789988615, 12),
(21, '2026-09-18-000004', 'App\\Database\\Migrations\\CreateSupplierPaymentAllocations', 'default', 'App', 1789988616, 12),
(22, '2026-09-18-000005', 'App\\Database\\Migrations\\CreateBankAccounts', 'default', 'App', 1789988617, 12),
(23, '2026-09-18-000006', 'App\\Database\\Migrations\\CreateBankTransactions', 'default', 'App', 1789988619, 12),
(24, '2026-09-19-000000', 'App\\Database\\Migrations\\AddBankAccountToProjectCashReceipts', 'default', 'App', 1789988622, 12),
(25, '2026-09-19-000001', 'App\\Database\\Migrations\\CreateServiceReceipts', 'default', 'App', 1789988623, 12),
(26, '2026-09-19-000002', 'App\\Database\\Migrations\\CreateServiceReceiptItems', 'default', 'App', 1789988623, 12),
(27, '2026-09-20-000001', 'App\\Database\\Migrations\\CreateCustomerPayments', 'default', 'App', 1789988624, 12),
(28, '2026-09-20-000002', 'App\\Database\\Migrations\\CreateCustomerPaymentAllocations', 'default', 'App', 1789988625, 12),
(29, '2026-09-20-000003', 'App\\Database\\Migrations\\CreateLoans', 'default', 'App', 1789988626, 12),
(30, '2026-09-20-000004', 'App\\Database\\Migrations\\CreateLoanEmis', 'default', 'App', 1789988627, 12),
(31, '2026-09-20-000005', 'App\\Database\\Migrations\\CreateLoanPayments', 'default', 'App', 1789988628, 12),
(32, '2026-09-22-000000', 'App\\Database\\Migrations\\AddExpenseManagementFieldsToExpenses', 'default', 'App', 1790054613, 13),
(33, '2026-09-22-000001', 'App\\Database\\Migrations\\AddDescriptionToExpenseCategories', 'default', 'App', 1790054614, 13),
(34, '2026-09-22-000002', 'App\\Database\\Migrations\\CreateAuditLogs', 'default', 'App', 1790313129, 14),
(35, '2026-09-22-000003', 'App\\Database\\Migrations\\CreateDatabaseBackups', 'default', 'App', 1790313130, 14),
(36, '2026-09-24-000001', 'App\\Database\\Migrations\\ExtendBankTransactionsForManualLedger', 'default', 'App', 1790313131, 14),
(37, '2026-09-25-000001', 'App\\Database\\Migrations\\AddAdvancePaymentToProjects', 'default', 'App', 1790330328, 15),
(38, '2026-09-25-000002', 'App\\Database\\Migrations\\CreateProjectAdvanceAllocations', 'default', 'App', 1790403248, 16),
(39, '2026-09-29-000001', 'App\\Database\\Migrations\\AddBankAccountToPayments', 'default', 'App', 1790676195, 17),
(40, '2026-09-29-000002', 'App\\Database\\Migrations\\AddCustomerProjectCashReceiptType', 'default', 'App', 1790684429, 18),
(41, '2026-09-30-000001', 'App\\Database\\Migrations\\CreateLoanReceipts', 'default', 'App', 1790830269, 19),
(42, '2026-10-02-000001', 'App\\Database\\Migrations\\CreateCashOpeningBalance', 'default', 'App', 1790944845, 20);

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
  `bank_account_id` int(10) UNSIGNED DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `sale_id`, `amount`, `payment_date`, `method`, `bank_account_id`, `reference`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 107532.50, '2026-06-30', 'BANK_TRANSFER', NULL, '', '', '2026-09-03 15:44:14', '2026-09-03 15:44:14'),
(4, 4, 189262.50, '2026-07-06', 'CASH', NULL, '', '', '2026-09-03 18:59:12', '2026-09-03 18:59:23'),
(5, 9, 76925.00, '2026-09-28', 'BANK_TRANSFER', 7, '', '', '2026-09-29 15:33:52', '2026-09-29 15:33:52'),
(6, 10, 10832.00, '2026-09-29', 'BANK_TRANSFER', 7, '', '', '2026-09-29 18:20:22', '2026-09-29 18:20:22'),
(7, 10, 0.40, '2026-09-29', 'CASH', NULL, '', '', '2026-09-29 18:37:30', '2026-09-29 18:37:30'),
(8, 6, 32100.80, '2026-10-02', 'CASH', NULL, '', '', '2026-10-02 12:09:21', '2026-10-02 12:09:21');

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
  `advance_payment_method` varchar(20) DEFAULT NULL,
  `advance_bank_account_id` int(10) UNSIGNED DEFAULT NULL,
  `billing_status` enum('ACTIVE','COMPLETED') NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `customer_id`, `status`, `start_date`, `end_date`, `description`, `total_project_value`, `advance_amount`, `advance_date`, `advance_notes`, `advance_payment_method`, `advance_bank_account_id`, `billing_status`, `created_at`, `updated_at`) VALUES
(1, 'Shreeya Clinic Chennai', 1, 'ACTIVE', '2026-07-01', NULL, 'Interior renovation and painting works', 2586869.00, 50000.00, '2026-07-01', NULL, NULL, NULL, 'ACTIVE', '2026-09-03 15:25:47', '2026-09-03 15:25:47'),
(2, 'Balaji Builders Phase 1', 2, 'ACTIVE', '2026-06-15', NULL, 'Residential complex construction - phase 1', 1850000.00, 100000.00, '2026-06-15', NULL, NULL, NULL, 'ACTIVE', '2026-09-03 15:25:47', '2026-09-03 15:25:47'),
(3, 'RK Interiors Office Renovation', 3, 'ACTIVE', '2026-07-10', NULL, 'Office interior renovation works', 1275000.00, 75000.00, '2026-07-10', NULL, NULL, NULL, 'ACTIVE', '2026-09-03 15:25:47', '2026-09-03 15:25:47'),
(4, 'Green Leaf Hospital ICU', 4, 'ACTIVE', '2026-05-20', NULL, 'ICU expansion and electrical works', 3500000.00, 200000.00, '2026-05-20', NULL, NULL, NULL, 'ACTIVE', '2026-09-03 15:25:47', '2026-09-03 15:25:47'),
(5, 'Anand Dental Expansion', 5, 'COMPLETED', '2026-02-01', '2026-06-30', 'Dental clinic expansion works', 925000.00, 25000.00, '2026-02-01', NULL, NULL, NULL, 'COMPLETED', '2026-09-03 15:25:47', '2026-09-03 15:25:47'),
(6, 'Harsha Medical Lab', 6, 'COMPLETED', '2026-01-15', '2026-05-10', 'Medical lab setup and fit-out', 1580000.00, 80000.00, '2026-01-15', NULL, NULL, NULL, 'COMPLETED', '2026-09-03 15:25:47', '2026-09-03 15:25:47'),
(7, 'Elite Diagnostics Branch', 7, 'ACTIVE', '2026-07-25', NULL, 'New diagnostics branch fit-out', 2240000.00, 120000.00, '2026-07-25', NULL, NULL, NULL, 'ACTIVE', '2026-09-03 15:25:47', '2026-09-03 15:25:47'),
(8, 'Lotus Residency Painting', 9, 'ON_HOLD', '2026-08-01', NULL, 'Residency exterior and interior painting', 1160000.00, 40000.00, '2026-08-01', NULL, NULL, NULL, 'ACTIVE', '2026-09-03 15:25:47', '2026-09-03 15:25:47'),
(23, 'RK Project', 3, 'ACTIVE', '2026-09-20', NULL, '', 600000.00, 100000.00, '2026-09-20', '', 'CHEQUE', 1, 'ACTIVE', '2026-09-25 15:28:53', '2026-09-25 15:28:53'),
(24, 'Elite Project', 7, 'ACTIVE', '2026-09-10', NULL, '', 200000.00, 50000.00, '2026-09-10', '', 'CHEQUE', 7, 'ACTIVE', '2026-09-25 17:53:03', '2026-09-25 17:53:03'),
(25, 'Green Leaf Project', 4, 'ACTIVE', '2026-09-01', NULL, '', 200000.00, 50000.00, '2026-09-01', '', 'UPI', 7, 'ACTIVE', '2026-09-26 10:16:33', '2026-09-26 10:16:33'),
(26, 'Vinayaga Builders Project', 15, 'ACTIVE', '2026-09-25', NULL, '', 200000.00, 75000.00, '2026-09-25', '', 'CHEQUE', 7, 'ACTIVE', '2026-09-29 11:57:51', '2026-09-29 11:57:51');

-- --------------------------------------------------------

--
-- Table structure for table `project_advance_allocations`
--

CREATE TABLE `project_advance_allocations` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `sales_invoice_id` int(10) UNSIGNED NOT NULL,
  `allocated_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `allocation_date` date NOT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_advance_allocations`
--

INSERT INTO `project_advance_allocations` (`id`, `project_id`, `customer_id`, `sales_invoice_id`, `allocated_amount`, `allocation_date`, `created_by`, `created_at`) VALUES
(1, 25, 4, 8, 40951.90, '2026-09-03', 1, '2026-09-26 12:11:11'),
(2, 26, 15, 9, 75000.00, '2026-09-27', 1, '2026-09-29 12:07:47'),
(3, 2, 2, 1, 100000.00, '2026-06-25', 1, '2026-10-02 11:08:14');

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
  `receipt_type` enum('ADVANCE','DIRECT_INCOME','CUSTOMER_PROJECT_CASH') NOT NULL DEFAULT 'ADVANCE',
  `bank_account_id` int(10) UNSIGNED DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_cash_receipts`
--

INSERT INTO `project_cash_receipts` (`id`, `project_id`, `customer_id`, `receipt_no`, `amount`, `receipt_date`, `payment_method`, `receipt_type`, `bank_account_id`, `reference`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2, 2, 'CASH-0001', 20000.00, '2026-07-03', 'CASH', 'CUSTOMER_PROJECT_CASH', NULL, '', '', '2026-09-03 18:34:43', '2026-09-03 18:34:43'),
(2, 2, 2, 'CASH-0002', 50000.00, '2026-07-04', 'CASH', 'CUSTOMER_PROJECT_CASH', NULL, 'CQU2452GHSH', '', '2026-09-03 18:45:42', '2026-09-03 18:45:42'),
(4, 23, 3, 'CASH-0003', 62074.80, '2026-09-23', 'CASH', 'CUSTOMER_PROJECT_CASH', NULL, '', '', '2026-09-25 16:56:33', '2026-09-25 16:56:33'),
(5, 25, 4, 'CASH-0005', 9048.00, '2026-09-04', 'CHECK', 'CUSTOMER_PROJECT_CASH', 7, '', '', '2026-09-26 12:18:08', '2026-09-26 12:18:08'),
(7, 26, 15, 'CASH-0006', 10000.00, '2026-09-29', 'CASH', 'CUSTOMER_PROJECT_CASH', NULL, '', '', '2026-09-29 17:39:56', '2026-09-29 17:39:56'),
(8, 26, 15, 'CASH-0008', 25000.00, '2026-09-29', 'CASH', 'CUSTOMER_PROJECT_CASH', NULL, '', '', '2026-09-29 18:22:35', '2026-09-29 18:22:35');

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
(2, 2, 2, '2026-07-02', 'INV/BBP2/2006', 195740.90, '', '2026-09-03 15:56:07', '2026-09-03 15:56:07'),
(9, 7, 23, '2026-09-21', 'PIS2352', 20461.20, '', '2026-09-25 16:10:43', '2026-09-25 16:10:43'),
(10, 10, 24, '2026-09-11', 'PE85325', 103792.80, '', '2026-09-25 17:54:47', '2026-09-25 17:54:47'),
(11, 4, 25, '2026-09-02', 'GLPI2141', 40007.90, '', '2026-09-26 10:18:14', '2026-09-26 10:18:14'),
(12, 5, 26, '2026-09-26', 'VBP/PI/8772', 107085.00, '', '2026-09-29 11:59:52', '2026-09-29 11:59:52'),
(13, 5, 26, '2026-09-29', 'IND2342', 306.80, '', '2026-09-29 18:31:22', '2026-09-29 18:31:22');

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
(8, 2, 6, '8544', 6, 6, 0, 11500.00, 18.00, 1, 12420.00, 69000.00, 81420.00, '2026-09-03 15:56:07', '2026-09-03 15:56:07'),
(17, 9, 11, '8481', 2, 2, 0, 195.00, 18.00, 1, 70.20, 390.00, 460.20, '2026-09-25 16:10:43', '2026-09-25 16:10:43'),
(18, 9, 5, '8414', 3, 3, 0, 1650.00, 18.00, 1, 891.00, 4950.00, 5841.00, '2026-09-25 16:10:44', '2026-09-25 16:10:44'),
(19, 9, 12, '2523', 30, 30, 0, 400.00, 18.00, 1, 2160.00, 12000.00, 14160.00, '2026-09-25 16:10:44', '2026-09-25 16:10:44'),
(20, 10, 13, '7214', 2, 2, 0, 620.00, 18.00, 1, 223.20, 1240.00, 1463.20, '2026-09-25 17:54:47', '2026-09-25 17:54:47'),
(21, 10, 3, '3208', 3, 3, 0, 22210.00, 18.00, 1, 11993.40, 66630.00, 78623.40, '2026-09-25 17:54:47', '2026-09-25 17:54:47'),
(22, 10, 7, '8536', 2, 2, 0, 8320.00, 18.00, 1, 2995.20, 16640.00, 19635.20, '2026-09-25 17:54:47', '2026-09-25 17:54:47'),
(23, 10, 10, '8481', 9, 9, 0, 340.00, 18.00, 1, 550.80, 3060.00, 3610.80, '2026-09-25 17:54:47', '2026-09-25 17:54:47'),
(24, 10, 23, '3402', 3, 3, 0, 130.00, 18.00, 1, 70.20, 390.00, 460.20, '2026-09-25 17:54:47', '2026-09-25 17:54:47'),
(25, 11, 3, '3208', 1, 1, 0, 22210.00, 18.00, 1, 3997.80, 22210.00, 26207.80, '2026-09-26 10:18:14', '2026-09-26 10:18:14'),
(26, 11, 11, '8481', 1, 1, 0, 195.00, 18.00, 1, 35.10, 195.00, 230.10, '2026-09-26 10:18:14', '2026-09-26 10:18:14'),
(27, 11, 6, '8544', 1, 1, 0, 11500.00, 18.00, 1, 2070.00, 11500.00, 13570.00, '2026-09-26 10:18:14', '2026-09-26 10:18:14'),
(28, 12, 5, '8414', 3, 3, 0, 1650.00, 18.00, 1, 891.00, 4950.00, 5841.00, '2026-09-29 11:59:52', '2026-09-29 11:59:52'),
(29, 12, 1, '3209', 4, 4, 0, 4200.00, 18.00, 1, 3024.00, 16800.00, 19824.00, '2026-09-29 11:59:52', '2026-09-29 11:59:52'),
(30, 12, 6, '8544', 6, 6, 0, 11500.00, 18.00, 1, 12420.00, 69000.00, 81420.00, '2026-09-29 11:59:52', '2026-09-29 11:59:52'),
(31, 13, 23, '3402', 2, 2, 0, 130.00, 18.00, 1, 46.80, 260.00, 306.80, '2026-09-29 18:31:22', '2026-09-29 18:31:22');

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
(4, 2, 2, '2026-07-05', 'INV/BBPS/0507', 'GENERAL', 189262.50, 0.00, 189262.50, 0.00, 'PAID', '', '2026-09-03 18:55:14', '2026-09-03 18:59:12'),
(5, 23, 3, '2026-09-22', 'SIV12414', 'PROJECT', 37925.20, 37925.20, 0.00, 0.00, 'PAID', '', '2026-09-25 16:15:20', '2026-09-25 16:15:20'),
(6, 24, 7, '2026-09-12', 'IEV258925', 'PROJECT', 112052.80, 50000.00, 32100.80, 29952.00, 'PARTIAL', '', '2026-09-25 17:58:25', '2026-10-02 12:09:21'),
(8, 25, 4, '2026-09-03', 'GLSI3453', 'PROJECT', 40951.90, 40951.90, 0.00, 0.00, 'PAID', '', '2026-09-26 12:11:11', '2026-09-26 12:11:11'),
(9, 26, 15, '2026-09-27', 'VBP/SI/9822', 'PROJECT', 151925.00, 75000.00, 76925.00, 0.00, 'PAID', '', '2026-09-29 12:07:47', '2026-09-29 15:33:52'),
(10, 26, 15, '2026-09-29', 'VBP/SI/9823', 'GENERAL', 10832.40, 0.00, 10832.40, 0.00, 'PAID', '', '2026-09-29 16:24:55', '2026-09-29 18:37:30');

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
(8, 4, 20, 5.000, 36050.00, 5.00, 1, 9012.50, 180250.00, 189262.50, 'GENERAL', NULL, '2026-09-03 18:56:19', '2026-09-03 18:56:19'),
(9, 5, 11, 2.000, 595.00, 18.00, 1, 214.20, 1190.00, 1404.20, 'PROJECT', 23, '2026-09-25 16:15:20', '2026-09-25 16:15:20'),
(10, 5, 5, 3.000, 3650.00, 18.00, 1, 1971.00, 10950.00, 12921.00, 'PROJECT', 23, '2026-09-25 16:15:20', '2026-09-25 16:15:20'),
(11, 5, 12, 25.000, 800.00, 18.00, 1, 3600.00, 20000.00, 23600.00, 'PROJECT', 23, '2026-09-25 16:15:20', '2026-09-25 16:15:20'),
(17, 6, 23, 3.000, 330.00, 18.00, 1, 178.20, 990.00, 1168.20, 'PROJECT', 24, '2026-09-25 17:58:44', '2026-09-25 17:58:44'),
(18, 6, 3, 3.000, 22510.00, 18.00, 1, 12155.40, 67530.00, 79685.40, 'PROJECT', 24, '2026-09-25 17:58:44', '2026-09-25 17:58:44'),
(19, 6, 13, 2.000, 820.00, 18.00, 1, 295.20, 1640.00, 1935.20, 'PROJECT', 24, '2026-09-25 17:58:44', '2026-09-25 17:58:44'),
(20, 6, 7, 2.000, 8620.00, 18.00, 1, 3103.20, 17240.00, 20343.20, 'PROJECT', 24, '2026-09-25 17:58:44', '2026-09-25 17:58:44'),
(21, 6, 10, 9.000, 840.00, 18.00, 1, 1360.80, 7560.00, 8920.80, 'PROJECT', 24, '2026-09-25 17:58:44', '2026-09-25 17:58:44'),
(25, 8, 11, 1.000, 395.00, 18.00, 1, 71.10, 395.00, 466.10, 'PROJECT', 25, '2026-09-26 12:11:11', '2026-09-26 12:11:11'),
(26, 8, 3, 1.000, 22510.00, 18.00, 1, 4051.80, 22510.00, 26561.80, 'PROJECT', 25, '2026-09-26 12:11:11', '2026-09-26 12:11:11'),
(27, 8, 6, 1.000, 11800.00, 18.00, 1, 2124.00, 11800.00, 13924.00, 'PROJECT', 25, '2026-09-26 12:11:11', '2026-09-26 12:11:11'),
(28, 9, 1, 4.000, 6200.00, 18.00, 1, 4464.00, 24800.00, 29264.00, 'PROJECT', 26, '2026-09-29 12:07:47', '2026-09-29 12:07:47'),
(29, 9, 5, 3.000, 3650.00, 18.00, 1, 1971.00, 10950.00, 12921.00, 'PROJECT', 26, '2026-09-29 12:07:47', '2026-09-29 12:07:47'),
(30, 9, 6, 6.000, 15500.00, 18.00, 1, 16740.00, 93000.00, 109740.00, 'PROJECT', 26, '2026-09-29 12:07:47', '2026-09-29 12:07:47'),
(31, 10, 1, 2.000, 4200.00, 18.00, 1, 1512.00, 8400.00, 9912.00, 'GENERAL', NULL, '2026-09-29 16:24:55', '2026-09-29 16:24:55'),
(32, 10, 11, 4.000, 195.00, 18.00, 1, 140.40, 780.00, 920.40, 'GENERAL', NULL, '2026-09-29 16:24:55', '2026-09-29 16:24:55');

-- --------------------------------------------------------

--
-- Table structure for table `service_receipts`
--

CREATE TABLE `service_receipts` (
  `id` int(10) UNSIGNED NOT NULL,
  `receipt_no` varchar(20) NOT NULL,
  `receipt_type` enum('INVOICE','DIRECT') NOT NULL DEFAULT 'INVOICE',
  `customer_id` int(10) UNSIGNED NOT NULL,
  `customer_name` varchar(200) NOT NULL,
  `customer_address` text DEFAULT NULL,
  `receipt_date` date NOT NULL,
  `attended_person` varchar(150) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gst_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `received_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `outstanding_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('PENDING','PARTIAL','PAID') NOT NULL DEFAULT 'PENDING',
  `payment_mode` enum('CASH','BANK','CHEQUE','UPI','OTHER') NOT NULL DEFAULT 'CASH',
  `bank_account_id` int(10) UNSIGNED DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_receipts`
--

INSERT INTO `service_receipts` (`id`, `receipt_no`, `receipt_type`, `customer_id`, `customer_name`, `customer_address`, `receipt_date`, `attended_person`, `subtotal`, `gst_total`, `grand_total`, `received_amount`, `outstanding_amount`, `payment_status`, `payment_mode`, `bank_account_id`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'SRV-000001', 'DIRECT', 15, 'Vinayaga', '', '2026-09-29', 'SAM', 5900.00, 0.00, 5900.00, 5900.00, 0.00, 'PAID', 'BANK', 7, NULL, 1, '2026-09-30 10:56:03', '2026-09-30 10:56:03'),
(2, 'SRV-000002', 'DIRECT', 4, 'Green Leaf Hospital', 'Fort Road, Salem, Tamil Nadu', '2026-10-01', 'RAW', 5000.00, 0.00, 5000.00, 5000.00, 0.00, 'PAID', 'UPI', 7, NULL, 1, '2026-10-01 18:45:07', '2026-10-01 18:45:07');

-- --------------------------------------------------------

--
-- Table structure for table `service_receipt_items`
--

CREATE TABLE `service_receipt_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `service_receipt_id` int(10) UNSIGNED NOT NULL,
  `description` varchar(500) NOT NULL,
  `qty` decimal(15,3) NOT NULL,
  `rate` decimal(15,2) NOT NULL,
  `gst_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `gst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_receipt_items`
--

INSERT INTO `service_receipt_items` (`id`, `service_receipt_id`, `description`, `qty`, `rate`, `gst_percent`, `gst_amount`, `line_total`, `created_at`, `updated_at`) VALUES
(1, 1, 'PAAA', 1.000, 3500.00, 0.00, 0.00, 3500.00, '2026-09-30 10:56:03', '2026-09-30 10:56:03'),
(2, 1, 'SAS', 1.000, 2400.00, 0.00, 0.00, 2400.00, '2026-09-30 10:56:03', '2026-09-30 10:56:03'),
(3, 2, 'PAINTING', 1.000, 5000.00, 0.00, 0.00, 5000.00, '2026-10-01 18:45:07', '2026-10-01 18:45:07');

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
  `reference_type` enum('PURCHASE','SALE','MANUAL','GENERAL_PURCHASE') DEFAULT NULL,
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
(19, 20, 'OUT', 5, 'GENERAL', NULL, 'SALE', 4, '2026-07-05', 'Sale #4', '2026-09-03 18:56:19'),
(26, 1, 'IN', 1, 'GENERAL', NULL, 'GENERAL_PURCHASE', 1, '2026-09-24', 'General Purchase GP-000001', '2026-09-24 11:05:39'),
(27, 5, 'IN', 2, 'GENERAL', NULL, 'GENERAL_PURCHASE', 1, '2026-09-24', 'General Purchase GP-000001', '2026-09-24 11:05:39'),
(28, 11, 'IN', 1, 'GENERAL', NULL, 'GENERAL_PURCHASE', 1, '2026-09-24', 'General Purchase GP-000001', '2026-09-24 11:05:39'),
(29, 11, 'IN', 2, 'PROJECT', 23, 'PURCHASE', 9, '2026-09-21', 'Purchase #9', '2026-09-25 16:10:43'),
(30, 5, 'IN', 3, 'PROJECT', 23, 'PURCHASE', 9, '2026-09-21', 'Purchase #9', '2026-09-25 16:10:44'),
(31, 12, 'IN', 30, 'PROJECT', 23, 'PURCHASE', 9, '2026-09-21', 'Purchase #9', '2026-09-25 16:10:44'),
(32, 11, 'OUT', 2, 'PROJECT', 23, 'SALE', 5, '2026-09-22', 'Sale #5', '2026-09-25 16:15:20'),
(33, 5, 'OUT', 3, 'PROJECT', 23, 'SALE', 5, '2026-09-22', 'Sale #5', '2026-09-25 16:15:20'),
(34, 12, 'OUT', 25, 'PROJECT', 23, 'SALE', 5, '2026-09-22', 'Sale #5', '2026-09-25 16:15:20'),
(35, 13, 'IN', 2, 'PROJECT', 24, 'PURCHASE', 10, '2026-09-11', 'Purchase #10', '2026-09-25 17:54:47'),
(36, 3, 'IN', 3, 'PROJECT', 24, 'PURCHASE', 10, '2026-09-11', 'Purchase #10', '2026-09-25 17:54:47'),
(37, 7, 'IN', 2, 'PROJECT', 24, 'PURCHASE', 10, '2026-09-11', 'Purchase #10', '2026-09-25 17:54:47'),
(38, 10, 'IN', 9, 'PROJECT', 24, 'PURCHASE', 10, '2026-09-11', 'Purchase #10', '2026-09-25 17:54:47'),
(39, 23, 'IN', 3, 'PROJECT', 24, 'PURCHASE', 10, '2026-09-11', 'Purchase #10', '2026-09-25 17:54:47'),
(45, 23, 'OUT', 3, 'PROJECT', 24, 'SALE', 6, '2026-09-12', 'Sale #6', '2026-09-25 17:58:44'),
(46, 3, 'OUT', 3, 'PROJECT', 24, 'SALE', 6, '2026-09-12', 'Sale #6', '2026-09-25 17:58:44'),
(47, 13, 'OUT', 2, 'PROJECT', 24, 'SALE', 6, '2026-09-12', 'Sale #6', '2026-09-25 17:58:44'),
(48, 7, 'OUT', 2, 'PROJECT', 24, 'SALE', 6, '2026-09-12', 'Sale #6', '2026-09-25 17:58:44'),
(49, 10, 'OUT', 9, 'PROJECT', 24, 'SALE', 6, '2026-09-12', 'Sale #6', '2026-09-25 17:58:44'),
(50, 3, 'IN', 1, 'PROJECT', 25, 'PURCHASE', 11, '2026-09-02', 'Purchase #11', '2026-09-26 10:18:14'),
(51, 11, 'IN', 1, 'PROJECT', 25, 'PURCHASE', 11, '2026-09-02', 'Purchase #11', '2026-09-26 10:18:14'),
(52, 6, 'IN', 1, 'PROJECT', 25, 'PURCHASE', 11, '2026-09-02', 'Purchase #11', '2026-09-26 10:18:14'),
(56, 11, 'OUT', 1, 'PROJECT', 25, 'SALE', 8, '2026-09-03', 'Sale #8', '2026-09-26 12:11:11'),
(57, 3, 'OUT', 1, 'PROJECT', 25, 'SALE', 8, '2026-09-03', 'Sale #8', '2026-09-26 12:11:11'),
(58, 6, 'OUT', 1, 'PROJECT', 25, 'SALE', 8, '2026-09-03', 'Sale #8', '2026-09-26 12:11:11'),
(59, 11, 'IN', 1, 'GENERAL', NULL, 'GENERAL_PURCHASE', 2, '2026-09-08', 'General Purchase GP-000002', '2026-09-28 11:04:21'),
(60, 5, 'IN', 1, 'GENERAL', NULL, 'GENERAL_PURCHASE', 2, '2026-09-08', 'General Purchase GP-000002', '2026-09-28 11:04:21'),
(61, 7, 'IN', 2, 'GENERAL', NULL, 'GENERAL_PURCHASE', 3, '2026-09-28', 'General Purchase GP-000003', '2026-09-28 11:56:21'),
(62, 10, 'IN', 2, 'GENERAL', NULL, 'GENERAL_PURCHASE', 3, '2026-09-28', 'General Purchase GP-000003', '2026-09-28 11:56:22'),
(63, 6, 'IN', 2, 'GENERAL', NULL, 'GENERAL_PURCHASE', 4, '2026-09-20', 'General Purchase GP-000004', '2026-09-28 16:05:46'),
(64, 12, 'IN', 3, 'GENERAL', NULL, 'GENERAL_PURCHASE', 4, '2026-09-20', 'General Purchase GP-000004', '2026-09-28 16:05:46'),
(65, 5, 'IN', 1, 'GENERAL', NULL, 'GENERAL_PURCHASE', 5, '2026-09-28', 'General Purchase GP-000005', '2026-09-28 17:01:03'),
(66, 12, 'IN', 1, 'GENERAL', NULL, 'GENERAL_PURCHASE', 5, '2026-09-28', 'General Purchase GP-000005', '2026-09-28 17:01:03'),
(67, 1, 'IN', 1, 'GENERAL', NULL, 'GENERAL_PURCHASE', 6, '2026-09-28', 'General Purchase GP-000006', '2026-09-28 17:52:15'),
(68, 11, 'IN', 2, 'GENERAL', NULL, 'GENERAL_PURCHASE', 6, '2026-09-28', 'General Purchase GP-000006', '2026-09-28 17:52:15'),
(72, 6, 'IN', 2, 'GENERAL', NULL, 'GENERAL_PURCHASE', 7, '2026-09-21', 'General Purchase GP-000007', '2026-09-29 10:45:50'),
(73, 7, 'IN', 3, 'GENERAL', NULL, 'GENERAL_PURCHASE', 7, '2026-09-21', 'General Purchase GP-000007', '2026-09-29 10:45:50'),
(74, 13, 'IN', 1, 'GENERAL', NULL, 'GENERAL_PURCHASE', 7, '2026-09-21', 'General Purchase GP-000007', '2026-09-29 10:45:50'),
(75, 5, 'IN', 3, 'PROJECT', 26, 'PURCHASE', 12, '2026-09-26', 'Purchase #12', '2026-09-29 11:59:52'),
(76, 1, 'IN', 4, 'PROJECT', 26, 'PURCHASE', 12, '2026-09-26', 'Purchase #12', '2026-09-29 11:59:52'),
(77, 6, 'IN', 6, 'PROJECT', 26, 'PURCHASE', 12, '2026-09-26', 'Purchase #12', '2026-09-29 11:59:52'),
(78, 1, 'OUT', 4, 'PROJECT', 26, 'SALE', 9, '2026-09-27', 'Sale #9', '2026-09-29 12:07:47'),
(79, 5, 'OUT', 3, 'PROJECT', 26, 'SALE', 9, '2026-09-27', 'Sale #9', '2026-09-29 12:07:47'),
(80, 6, 'OUT', 6, 'PROJECT', 26, 'SALE', 9, '2026-09-27', 'Sale #9', '2026-09-29 12:07:47'),
(81, 1, 'OUT', 2, 'GENERAL', NULL, 'SALE', 10, '2026-09-29', 'Sale #10', '2026-09-29 16:24:55'),
(82, 11, 'OUT', 4, 'GENERAL', NULL, 'SALE', 10, '2026-09-29', 'Sale #10', '2026-09-29 16:24:55'),
(83, 3, 'IN', 2, 'GENERAL', NULL, 'GENERAL_PURCHASE', 8, '2026-09-29', 'General Purchase GP-000008', '2026-09-29 18:30:29'),
(84, 21, 'IN', 35, 'GENERAL', NULL, 'GENERAL_PURCHASE', 8, '2026-09-29', 'General Purchase GP-000008', '2026-09-29 18:30:29'),
(85, 23, 'IN', 2, 'PROJECT', 26, 'PURCHASE', 13, '2026-09-29', 'Purchase #13', '2026-09-29 18:31:22');

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
(10, 'Harsha Industrial Supplies', '33AACCH0011A1Z1', 'H. Harish', '9994022323', 'sales@harshaindustrial.com', 'SIDCO Industrial Estate, Chennai, Tamil Nadu', '2026-09-03 15:25:46', '2026-09-03 15:25:46');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_payments`
--

CREATE TABLE `supplier_payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `payment_no` varchar(20) NOT NULL,
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `advance_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_payments`
--

INSERT INTO `supplier_payments` (`id`, `payment_no`, `supplier_id`, `payment_date`, `payment_method`, `reference_no`, `remarks`, `total_amount`, `advance_amount`, `created_by`, `created_at`, `updated_at`) VALUES
(5, 'SPV-000001', 1, '2026-09-25', 'Bank Transfer', '', '', 8500.10, 0.00, 1, '2026-09-25 10:43:26', '2026-09-25 10:43:26'),
(6, 'SPV-000006', 7, '2026-09-25', 'Bank Transfer', '', '', 20461.20, 0.00, 1, '2026-09-25 16:12:13', '2026-09-25 16:12:13'),
(7, 'SPV-000007', 10, '2026-09-11', 'Bank Transfer', '', '', 3000.00, 3000.00, 1, '2026-09-25 17:55:49', '2026-09-25 17:55:49'),
(8, 'SPV-000008', 1, '2026-09-26', '', '', '', 12100.00, 12100.00, 1, '2026-09-26 18:22:30', '2026-09-26 18:22:30'),
(9, 'SPV-000009', 3, '2026-09-09', 'Cheque', '', '', 1000.10, 1000.10, 1, '2026-09-28 11:11:39', '2026-09-28 11:11:39'),
(10, 'SPV-000010', 3, '2026-09-09', 'Cheque', '', '', 1000.00, 0.00, 1, '2026-09-28 11:13:42', '2026-09-28 11:13:42'),
(11, 'SPV-000011', 6, '2026-09-28', 'Bank Transfer', '', '', 10000.00, 0.00, 1, '2026-09-28 16:02:04', '2026-09-28 16:02:04'),
(12, 'GPA-000004', 10, '2026-09-20', 'UPI', 'GP-000004', 'Advance paid while creating General Purchase GP-000004', 8556.00, 8556.00, 1, '2026-09-28 16:05:46', '2026-09-28 16:05:46'),
(13, 'SPV-000013', 10, '2026-09-28', 'Bank Transfer', '', '', 100792.80, 0.00, 1, '2026-09-28 16:08:24', '2026-09-28 16:08:24'),
(14, 'GPA-000005', 1, '2026-09-28', 'BANK_TRANSFER', 'GP-000005', 'Advance paid while creating General Purchase GP-000005', 1000.00, 1000.00, 1, '2026-09-28 17:01:03', '2026-09-28 17:01:03'),
(15, 'GPA-000006', 7, '2026-09-28', 'CHEQUE', 'GP-000006', 'Advance paid while creating General Purchase GP-000006', 416.20, 416.20, 1, '2026-09-28 17:52:15', '2026-09-28 17:52:15'),
(16, 'SPV-000016', 7, '2026-09-28', 'Cheque', '', '', 4000.00, 0.00, 1, '2026-09-28 17:53:58', '2026-09-28 17:53:58'),
(17, 'SPV-000017', 7, '2026-09-29', 'Cheque', '', '', 1000.00, 0.00, 1, '2026-09-28 17:54:23', '2026-09-28 17:54:23'),
(18, 'GPA-000007', 9, '2026-09-21', 'BANK_TRANSFER', 'GP-000007', 'Advance paid while creating General Purchase GP-000007', 7324.40, 7324.40, 1, '2026-09-29 10:45:30', '2026-09-29 10:45:51'),
(19, 'SPV-000019', 9, '2026-09-22', 'Bank Transfer', '', '', 40000.00, 0.00, 1, '2026-09-29 11:52:15', '2026-09-29 11:52:15'),
(20, 'SPV-000020', 9, '2026-09-23', 'Bank Transfer', '', '', 5000.00, 5000.00, 1, '2026-09-29 11:54:00', '2026-09-29 11:54:35'),
(21, 'SPV-000021', 9, '2026-09-24', 'Bank Transfer', '', '', 5000.00, 0.00, 1, '2026-09-29 11:54:58', '2026-09-29 11:54:58'),
(22, 'SPV-000022', 5, '2026-09-26', 'Bank Transfer', '', '', 7085.00, 7085.00, 1, '2026-09-29 12:01:19', '2026-09-29 12:01:19'),
(23, 'SPV-000023', 5, '2026-09-27', 'Bank Transfer', '', '', 100000.00, 0.00, 1, '2026-09-29 12:02:18', '2026-09-29 12:02:18');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_payment_allocations`
--

CREATE TABLE `supplier_payment_allocations` (
  `id` int(10) UNSIGNED NOT NULL,
  `supplier_payment_id` int(10) UNSIGNED NOT NULL,
  `purchase_type` enum('PROJECT','GENERAL') NOT NULL,
  `purchase_id` int(10) UNSIGNED NOT NULL,
  `bill_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_payment_allocations`
--

INSERT INTO `supplier_payment_allocations` (`id`, `supplier_payment_id`, `purchase_type`, `purchase_id`, `bill_amount`, `paid_amount`, `balance_amount`, `created_at`, `updated_at`) VALUES
(5, 5, 'GENERAL', 1, 9080.10, 8500.10, 0.00, '2026-09-25 10:43:26', '2026-09-25 10:43:26'),
(6, 6, 'PROJECT', 9, 20461.20, 20461.20, 0.00, '2026-09-25 16:12:13', '2026-09-25 16:12:13'),
(7, 10, 'GENERAL', 2, 2177.10, 2000.10, 0.00, '2026-09-28 11:13:42', '2026-09-28 11:13:42'),
(8, 11, 'GENERAL', 3, 20437.60, 10000.00, 10000.00, '2026-09-28 16:02:04', '2026-09-28 16:02:04'),
(9, 13, 'PROJECT', 10, 103792.80, 103792.80, 0.00, '2026-09-28 16:08:25', '2026-09-28 16:08:25'),
(10, 16, 'GENERAL', 6, 5416.20, 4000.00, 1000.00, '2026-09-28 17:53:58', '2026-09-28 17:53:58'),
(11, 17, 'GENERAL', 6, 5416.20, 1000.00, 0.00, '2026-09-28 17:54:23', '2026-09-28 17:54:23'),
(12, 19, 'GENERAL', 7, 57324.40, 40000.00, 10000.00, '2026-09-29 11:52:16', '2026-09-29 11:52:16'),
(13, 21, 'GENERAL', 7, 57324.40, 10000.00, 0.00, '2026-09-29 11:54:58', '2026-09-29 11:54:58'),
(14, 23, 'PROJECT', 12, 107085.00, 107085.00, 0.00, '2026-09-29 12:02:18', '2026-09-29 12:02:18');

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
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `module` (`module`),
  ADD KEY `action` (`action`),
  ADD KEY `reference_type` (`reference_type`),
  ADD KEY `reference_id` (`reference_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bank_accounts_created_by_foreign` (`created_by`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bank_transactions_transfer_bank_account_id_foreign` (`transfer_bank_account_id`),
  ADD KEY `bank_transactions_created_by_foreign` (`created_by`),
  ADD KEY `bank_account_id` (`bank_account_id`),
  ADD KEY `transaction_date` (`transaction_date`),
  ADD KEY `reference_type_reference_id` (`reference_type`,`reference_id`);

--
-- Indexes for table `cash_opening_balance`
--
ALTER TABLE `cash_opening_balance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lock_key` (`lock_key`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_payments`
--
ALTER TABLE `customer_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_no` (`payment_no`),
  ADD KEY `customer_payments_created_by_foreign` (`created_by`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `payment_date` (`payment_date`),
  ADD KEY `bank_account_id` (`bank_account_id`);

--
-- Indexes for table `customer_payment_allocations`
--
ALTER TABLE `customer_payment_allocations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_payment_id_service_receipt_id` (`customer_payment_id`,`service_receipt_id`),
  ADD KEY `service_receipt_id` (`service_receipt_id`);

--
-- Indexes for table `database_backups`
--
ALTER TABLE `database_backups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `backup_type` (`backup_type`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `restore_of` (`restore_of`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `expenses_expense_no_unique` (`expense_no`),
  ADD KEY `expenses_ibfk_1` (`project_id`),
  ADD KEY `expenses_expense_date_index` (`expense_date`),
  ADD KEY `expenses_status_index` (`status`),
  ADD KEY `expenses_category_id_index` (`category_id`),
  ADD KEY `expenses_bank_account_id_index` (`bank_account_id`),
  ADD KEY `expenses_created_by_index` (`created_by`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `general_purchases`
--
ALTER TABLE `general_purchases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `purchase_no` (`purchase_no`),
  ADD KEY `general_purchases_created_by_foreign` (`created_by`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `payment_status` (`payment_status`);

--
-- Indexes for table `general_purchase_items`
--
ALTER TABLE `general_purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `general_purchase_id` (`general_purchase_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `loan_no` (`loan_no`),
  ADD KEY `loans_created_by_foreign` (`created_by`),
  ADD KEY `status` (`status`),
  ADD KEY `start_date` (`start_date`),
  ADD KEY `lender_name` (`lender_name`),
  ADD KEY `bank_account_id` (`bank_account_id`);

--
-- Indexes for table `loan_emis`
--
ALTER TABLE `loan_emis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `loan_id_emi_no` (`loan_id`,`emi_no`),
  ADD KEY `due_date` (`due_date`),
  ADD KEY `payment_status` (`payment_status`);

--
-- Indexes for table `loan_payments`
--
ALTER TABLE `loan_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_payments_created_by_foreign` (`created_by`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `loan_emi_id` (`loan_emi_id`),
  ADD KEY `payment_date` (`payment_date`),
  ADD KEY `bank_account_id` (`bank_account_id`);

--
-- Indexes for table `loan_receipts`
--
ALTER TABLE `loan_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_no` (`receipt_no`),
  ADD KEY `loan_receipts_created_by_foreign` (`created_by`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `receipt_date` (`receipt_date`),
  ADD KEY `bank_account_id` (`bank_account_id`);

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
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `bank_account_id` (`bank_account_id`);

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
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `advance_bank_account_id` (`advance_bank_account_id`);

--
-- Indexes for table `project_advance_allocations`
--
ALTER TABLE `project_advance_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `sales_invoice_id` (`sales_invoice_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `project_cash_receipts`
--
ALTER TABLE `project_cash_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_no` (`receipt_no`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `bank_account_id` (`bank_account_id`);

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
-- Indexes for table `service_receipts`
--
ALTER TABLE `service_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_no` (`receipt_no`),
  ADD KEY `service_receipts_created_by_foreign` (`created_by`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `receipt_date` (`receipt_date`),
  ADD KEY `payment_status` (`payment_status`),
  ADD KEY `bank_account_id` (`bank_account_id`);

--
-- Indexes for table `service_receipt_items`
--
ALTER TABLE `service_receipt_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_receipt_id` (`service_receipt_id`);

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
-- Indexes for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_no` (`payment_no`),
  ADD KEY `supplier_payments_created_by_foreign` (`created_by`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `payment_date` (`payment_date`);

--
-- Indexes for table `supplier_payment_allocations`
--
ALTER TABLE `supplier_payment_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_payment_id` (`supplier_payment_id`),
  ADD KEY `purchase_type_purchase_id` (`purchase_type`,`purchase_id`);

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
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- AUTO_INCREMENT for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `cash_opening_balance`
--
ALTER TABLE `cash_opening_balance`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `customer_payments`
--
ALTER TABLE `customer_payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_payment_allocations`
--
ALTER TABLE `customer_payment_allocations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `database_backups`
--
ALTER TABLE `database_backups`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `general_purchases`
--
ALTER TABLE `general_purchases`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `general_purchase_items`
--
ALTER TABLE `general_purchase_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `loan_emis`
--
ALTER TABLE `loan_emis`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `loan_payments`
--
ALTER TABLE `loan_payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `loan_receipts`
--
ALTER TABLE `loan_receipts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `project_advance_allocations`
--
ALTER TABLE `project_advance_allocations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `project_cash_receipts`
--
ALTER TABLE `project_cash_receipts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `service_receipts`
--
ALTER TABLE `service_receipts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `service_receipt_items`
--
ALTER TABLE `service_receipt_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `supplier_payment_allocations`
--
ALTER TABLE `supplier_payment_allocations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD CONSTRAINT `bank_accounts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  ADD CONSTRAINT `bank_transactions_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  ADD CONSTRAINT `bank_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bank_transactions_transfer_bank_account_id_foreign` FOREIGN KEY (`transfer_bank_account_id`) REFERENCES `bank_accounts` (`id`);

--
-- Constraints for table `customer_payments`
--
ALTER TABLE `customer_payments`
  ADD CONSTRAINT `customer_payments_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  ADD CONSTRAINT `customer_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_payments_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `customer_payment_allocations`
--
ALTER TABLE `customer_payment_allocations`
  ADD CONSTRAINT `customer_payment_allocations_customer_payment_id_foreign` FOREIGN KEY (`customer_payment_id`) REFERENCES `customer_payments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_payment_allocations_service_receipt_id_foreign` FOREIGN KEY (`service_receipt_id`) REFERENCES `service_receipts` (`id`);

--
-- Constraints for table `database_backups`
--
ALTER TABLE `database_backups`
  ADD CONSTRAINT `database_backups_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  ADD CONSTRAINT `expenses_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`),
  ADD CONSTRAINT `expenses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `general_purchases`
--
ALTER TABLE `general_purchases`
  ADD CONSTRAINT `general_purchases_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `general_purchases_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `general_purchase_items`
--
ALTER TABLE `general_purchase_items`
  ADD CONSTRAINT `general_purchase_items_general_purchase_id_foreign` FOREIGN KEY (`general_purchase_id`) REFERENCES `general_purchases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `general_purchase_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `loans`
--
ALTER TABLE `loans`
  ADD CONSTRAINT `loans_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  ADD CONSTRAINT `loans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `loan_emis`
--
ALTER TABLE `loan_emis`
  ADD CONSTRAINT `loan_emis_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_payments`
--
ALTER TABLE `loan_payments`
  ADD CONSTRAINT `loan_payments_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  ADD CONSTRAINT `loan_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `loan_payments_loan_emi_id_foreign` FOREIGN KEY (`loan_emi_id`) REFERENCES `loan_emis` (`id`),
  ADD CONSTRAINT `loan_payments_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`);

--
-- Constraints for table `loan_receipts`
--
ALTER TABLE `loan_receipts`
  ADD CONSTRAINT `loan_receipts_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  ADD CONSTRAINT `loan_receipts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `loan_receipts_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`);

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`);

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_advance_bank_account_id_foreign` FOREIGN KEY (`advance_bank_account_id`) REFERENCES `bank_accounts` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `project_advance_allocations`
--
ALTER TABLE `project_advance_allocations`
  ADD CONSTRAINT `project_advance_allocations_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_advance_allocations_sales_invoice_id_foreign` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_cash_receipts`
--
ALTER TABLE `project_cash_receipts`
  ADD CONSTRAINT `project_cash_receipts_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON UPDATE CASCADE,
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
-- Constraints for table `service_receipts`
--
ALTER TABLE `service_receipts`
  ADD CONSTRAINT `service_receipts_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  ADD CONSTRAINT `service_receipts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `service_receipts_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `service_receipt_items`
--
ALTER TABLE `service_receipt_items`
  ADD CONSTRAINT `service_receipt_items_service_receipt_id_foreign` FOREIGN KEY (`service_receipt_id`) REFERENCES `service_receipts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  ADD CONSTRAINT `stock_ledger_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_ledger_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  ADD CONSTRAINT `supplier_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `supplier_payments_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `supplier_payment_allocations`
--
ALTER TABLE `supplier_payment_allocations`
  ADD CONSTRAINT `supplier_payment_allocations_supplier_payment_id_foreign` FOREIGN KEY (`supplier_payment_id`) REFERENCES `supplier_payments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
