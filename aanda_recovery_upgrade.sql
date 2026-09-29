-- ============================================================================
-- A&A Inventory - recovery upgrade (restored Sep-18 backup -> Release 4.8.5C schema)
-- Target: MariaDB 10.4, database aandainventory_db.  NO "USE" line on purpose:
-- select the database in the client, so the script can never hit the wrong one.
-- Additive and re-runnable: CREATE ... IF NOT EXISTS, ADD ... IF NOT EXISTS,
-- guarded PRIMARY KEY / enum changes.  No DROP, TRUNCATE, DELETE or UPDATE.
-- Prerequisite: remove innodb_force_recovery=3 from my.ini and restart MariaDB.
-- ============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- PART 0 - Base-schema repair (the restore stopped part-way through the dump)
-- Not a numbered release: the restored DB lacks these pieces of the Sep-18 dump.
-- ============================================================================

-- 0.1 customers table + its 11 rows (values copied from the dump; INSERT IGNORE = re-runnable)
CREATE TABLE IF NOT EXISTS `customers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `gst` varchar(100) NOT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT IGNORE INTO `customers` (`id`, `name`, `gst`, `contact`, `phone`, `email`, `address`, `created_at`, `updated_at`) VALUES
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

-- 0.2 PRIMARY KEYs missing on four tables (guarded: only if the table has no PK)
SET @q = IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_items' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0, 'ALTER TABLE `sale_items` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q = IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_ledger' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0, 'ALTER TABLE `stock_ledger` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q = IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0, 'ALTER TABLE `suppliers` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q = IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0, 'ALTER TABLE `users` ADD PRIMARY KEY (`id`)', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- 0.3 secondary / unique indexes missing on those tables
ALTER TABLE `sale_items`   ADD KEY IF NOT EXISTS `sale_id` (`sale_id`), ADD KEY IF NOT EXISTS `product_id` (`product_id`);
ALTER TABLE `stock_ledger` ADD KEY IF NOT EXISTS `product_id` (`product_id`), ADD KEY IF NOT EXISTS `project_id` (`project_id`);
ALTER TABLE `users`        ADD UNIQUE KEY IF NOT EXISTS `username` (`username`), ADD UNIQUE KEY IF NOT EXISTS `users_email_unique` (`email`);

-- 0.4 AUTO_INCREMENT on every table (same column type as before; counters = values in the dump)
ALTER TABLE `customers`             MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
ALTER TABLE `expenses`              MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `expense_categories`    MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
ALTER TABLE `migrations`            MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
ALTER TABLE `password_resets`       MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
ALTER TABLE `payments`              MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `products`              MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;
ALTER TABLE `projects`              MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;
ALTER TABLE `project_cash_receipts` MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `purchases`             MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
ALTER TABLE `purchase_items`        MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
ALTER TABLE `sales`                 MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `sale_items`            MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
ALTER TABLE `stock_ledger`          MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;
ALTER TABLE `suppliers`             MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
ALTER TABLE `users`                 MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

-- 0.5 the 15 base foreign keys from the dump
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY IF NOT EXISTS (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_user_id_foreign` FOREIGN KEY IF NOT EXISTS (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY IF NOT EXISTS (`sale_id`) REFERENCES `sales` (`id`);
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY IF NOT EXISTS (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;
ALTER TABLE `project_cash_receipts`
  ADD CONSTRAINT `project_cash_receipts_project_id_foreign` FOREIGN KEY IF NOT EXISTS (`project_id`) REFERENCES `projects` (`id`);
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY IF NOT EXISTS (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchases_ibfk_2` FOREIGN KEY IF NOT EXISTS (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;
ALTER TABLE `purchase_items`
  ADD CONSTRAINT `purchase_items_ibfk_1` FOREIGN KEY IF NOT EXISTS (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_items_ibfk_2` FOREIGN KEY IF NOT EXISTS (`product_id`) REFERENCES `products` (`id`);
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY IF NOT EXISTS (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY IF NOT EXISTS (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY IF NOT EXISTS (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY IF NOT EXISTS (`product_id`) REFERENCES `products` (`id`);
ALTER TABLE `stock_ledger`
  ADD CONSTRAINT `stock_ledger_ibfk_1` FOREIGN KEY IF NOT EXISTS (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_ledger_ibfk_2` FOREIGN KEY IF NOT EXISTS (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;

-- ============================================================================
-- RELEASES 4.8.1A / 4.8.1D / 4.8.2A - General Purchase, Supplier Payments
-- (tables the application already requires; absent from the Sep-18 backup)
-- ============================================================================

-- 4.8.1A  migration 2026-09-18-000000
CREATE TABLE IF NOT EXISTS `general_purchases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_no` varchar(20) NOT NULL,
  `supplier_id` int(10) unsigned NOT NULL,
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
  `bank_account_id` int(10) unsigned DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_no` (`purchase_no`),
  KEY `supplier_id` (`supplier_id`),
  KEY `payment_status` (`payment_status`),
  CONSTRAINT `general_purchases_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `general_purchases_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.8.1A  migration 2026-09-18-000001
CREATE TABLE IF NOT EXISTS `general_purchase_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `general_purchase_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `rate` decimal(15,2) NOT NULL,
  `gst_percent` decimal(5,2) DEFAULT 0.00,
  `gst_amount` decimal(15,2) DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `general_purchase_id` (`general_purchase_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `general_purchase_items_general_purchase_id_foreign` FOREIGN KEY (`general_purchase_id`) REFERENCES `general_purchases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `general_purchase_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.8.1D  migration 2026-09-18-000002 - widen the enum only (guarded; no existing value changes)
SET @q = IF((SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_ledger' AND COLUMN_NAME = 'reference_type') LIKE '%GENERAL_PURCHASE%', 'DO 0', "ALTER TABLE `stock_ledger` MODIFY `reference_type` ENUM('PURCHASE','SALE','MANUAL','GENERAL_PURCHASE') NULL");
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- 4.8.2A  migration 2026-09-18-000003
CREATE TABLE IF NOT EXISTS `supplier_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payment_no` varchar(20) NOT NULL,
  `supplier_id` int(10) unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `advance_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_no` (`payment_no`),
  KEY `supplier_id` (`supplier_id`),
  KEY `payment_date` (`payment_date`),
  CONSTRAINT `supplier_payments_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `supplier_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.8.2A  migration 2026-09-18-000004
CREATE TABLE IF NOT EXISTS `supplier_payment_allocations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `supplier_payment_id` int(10) unsigned NOT NULL,
  `purchase_type` enum('PROJECT','GENERAL') NOT NULL,
  `purchase_id` int(10) unsigned NOT NULL,
  `bill_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_payment_id` (`supplier_payment_id`),
  KEY `purchase_type_purchase_id` (`purchase_type`,`purchase_id`),
  CONSTRAINT `supplier_payment_allocations_supplier_payment_id_foreign` FOREIGN KEY (`supplier_payment_id`) REFERENCES `supplier_payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- RELEASE 4.8.3A - Bank Accounts Foundation (prerequisite of 4.8.3D and later)
-- ============================================================================

-- migration 2026-09-18-000005
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bank_name` varchar(150) NOT NULL,
  `account_name` varchar(150) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `ifsc_code` varchar(20) DEFAULT NULL,
  `branch_name` varchar(150) DEFAULT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `bank_accounts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- migration 2026-09-18-000006  (reference_type stays VARCHAR(50): SUPPLIER_PAYMENT,
-- SERVICE_RECEIPT_PAYMENT, LOAN_PAYMENT, ... all fit - no change needed for 4.8.4F / 4.8.5C)
CREATE TABLE IF NOT EXISTS `bank_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bank_account_id` int(10) unsigned NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` enum('DEPOSIT','WITHDRAWAL','TRANSFER') NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(10) unsigned DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `transfer_bank_account_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_account_id` (`bank_account_id`),
  KEY `transaction_date` (`transaction_date`),
  KEY `reference_type_reference_id` (`reference_type`,`reference_id`),
  CONSTRAINT `bank_transactions_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `bank_transactions_transfer_bank_account_id_foreign` FOREIGN KEY (`transfer_bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `bank_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- RELEASE 4.8.3D - Bank Account Integration: project_cash_receipts.bank_account_id
-- migration 2026-09-19-000000 (nullable, so every existing receipt stays valid)
-- ============================================================================
ALTER TABLE `project_cash_receipts`
  ADD COLUMN IF NOT EXISTS `bank_account_id` int(10) unsigned DEFAULT NULL AFTER `receipt_type`;
ALTER TABLE `project_cash_receipts`
  ADD KEY IF NOT EXISTS `bank_account_id` (`bank_account_id`);
ALTER TABLE `project_cash_receipts`
  ADD CONSTRAINT `project_cash_receipts_bank_account_id_foreign` FOREIGN KEY IF NOT EXISTS (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- ============================================================================
-- RELEASE 4.8.4A - Service Received tables
-- ============================================================================

-- migration 2026-09-19-000001
CREATE TABLE IF NOT EXISTS `service_receipts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(20) NOT NULL,
  `receipt_type` enum('INVOICE','DIRECT') NOT NULL DEFAULT 'INVOICE',
  `customer_id` int(10) unsigned NOT NULL,
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
  `bank_account_id` int(10) unsigned DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_no` (`receipt_no`),
  KEY `customer_id` (`customer_id`),
  KEY `receipt_date` (`receipt_date`),
  KEY `payment_status` (`payment_status`),
  KEY `bank_account_id` (`bank_account_id`),
  CONSTRAINT `service_receipts_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `service_receipts_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  CONSTRAINT `service_receipts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- migration 2026-09-19-000002
CREATE TABLE IF NOT EXISTS `service_receipt_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `service_receipt_id` int(10) unsigned NOT NULL,
  `description` varchar(500) NOT NULL,
  `qty` decimal(15,3) NOT NULL,
  `rate` decimal(15,2) NOT NULL,
  `gst_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `gst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `service_receipt_id` (`service_receipt_id`),
  CONSTRAINT `service_receipt_items_service_receipt_id_foreign` FOREIGN KEY (`service_receipt_id`) REFERENCES `service_receipts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- RELEASE 4.8.4C - Customer Payments tables
-- ============================================================================

-- migration 2026-09-20-000001
CREATE TABLE IF NOT EXISTS `customer_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payment_no` varchar(20) NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('CASH','BANK','CHEQUE','UPI','OTHER') NOT NULL DEFAULT 'CASH',
  `bank_account_id` int(10) unsigned DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `advance_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_no` (`payment_no`),
  KEY `customer_id` (`customer_id`),
  KEY `payment_date` (`payment_date`),
  KEY `bank_account_id` (`bank_account_id`),
  CONSTRAINT `customer_payments_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `customer_payments_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  CONSTRAINT `customer_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- migration 2026-09-20-000002
CREATE TABLE IF NOT EXISTS `customer_payment_allocations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_payment_id` int(10) unsigned NOT NULL,
  `service_receipt_id` int(10) unsigned NOT NULL,
  `invoice_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_payment_id_service_receipt_id` (`customer_payment_id`,`service_receipt_id`),
  KEY `service_receipt_id` (`service_receipt_id`),
  CONSTRAINT `customer_payment_allocations_customer_payment_id_foreign` FOREIGN KEY (`customer_payment_id`) REFERENCES `customer_payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_payment_allocations_service_receipt_id_foreign` FOREIGN KEY (`service_receipt_id`) REFERENCES `service_receipts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- RELEASE 4.8.5A - Loan tables (loan_payments carries the 4.8.5C payment engine)
-- ============================================================================

-- migration 2026-09-20-000003
CREATE TABLE IF NOT EXISTS `loans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `loan_no` varchar(20) NOT NULL,
  `lender_name` varchar(150) NOT NULL,
  `loan_type` enum('BANK','PERSONAL','VEHICLE','OD','OTHER') NOT NULL DEFAULT 'BANK',
  `bank_account_id` int(10) unsigned DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `sanctioned_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_rate` decimal(7,3) NOT NULL DEFAULT 0.000,
  `tenure_months` int(10) unsigned NOT NULL DEFAULT 0,
  `emi_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `outstanding_principal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_principal_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_interest_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('ACTIVE','CLOSED') NOT NULL DEFAULT 'ACTIVE',
  `remarks` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_no` (`loan_no`),
  KEY `status` (`status`),
  KEY `start_date` (`start_date`),
  KEY `lender_name` (`lender_name`),
  KEY `bank_account_id` (`bank_account_id`),
  CONSTRAINT `loans_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  CONSTRAINT `loans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- migration 2026-09-20-000004
CREATE TABLE IF NOT EXISTS `loan_emis` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` int(10) unsigned NOT NULL,
  `emi_no` int(10) unsigned NOT NULL,
  `due_date` date NOT NULL,
  `principal_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `emi_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('PENDING','PARTIAL','PAID') NOT NULL DEFAULT 'PENDING',
  `paid_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_id_emi_no` (`loan_id`,`emi_no`),
  KEY `due_date` (`due_date`),
  KEY `payment_status` (`payment_status`),
  CONSTRAINT `loan_emis_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- migration 2026-09-20-000005
CREATE TABLE IF NOT EXISTS `loan_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` int(10) unsigned NOT NULL,
  `loan_emi_id` int(10) unsigned DEFAULT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('CASH','BANK','CHEQUE','UPI','OTHER') NOT NULL DEFAULT 'BANK',
  `bank_account_id` int(10) unsigned DEFAULT NULL,
  `principal_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reference_no` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loan_id` (`loan_id`),
  KEY `loan_emi_id` (`loan_emi_id`),
  KEY `payment_date` (`payment_date`),
  KEY `bank_account_id` (`bank_account_id`),
  CONSTRAINT `loan_payments_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`),
  CONSTRAINT `loan_payments_loan_emi_id_foreign` FOREIGN KEY (`loan_emi_id`) REFERENCES `loan_emis` (`id`),
  CONSTRAINT `loan_payments_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  CONSTRAINT `loan_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- RELEASES 4.8.4B, 4.8.4D, 4.8.4E, 4.8.4F, 4.8.5B, 4.8.5C (logic), 4.8.5D:
-- No schema changes required.
-- ============================================================================

-- ============================================================================
-- MIGRATION RECORDS - so `php spark migrate` sees these 15 as already applied.
-- Run ONLY if you used this script for the DDL. If you let `php spark migrate`
-- create the tables instead, do NOT run this section (and skip the DDL above
-- from "RELEASES 4.8.1A" down).  Each row is inserted only if not present.
-- ============================================================================
INSERT INTO `migrations` (`version`, `class`, `group`, `namespace`, `time`, `batch`)
SELECT v.version, v.class, 'default', 'App', UNIX_TIMESTAMP(),
       (SELECT COALESCE(MAX(m.batch), 0) + 1 FROM `migrations` m)
FROM (
  SELECT '2026-09-18-000000' AS version, 'App\\Database\\Migrations\\CreateGeneralPurchases' AS class
  UNION ALL SELECT '2026-09-18-000001', 'App\\Database\\Migrations\\CreateGeneralPurchaseItems'
  UNION ALL SELECT '2026-09-18-000002', 'App\\Database\\Migrations\\AddGeneralPurchaseToStockLedgerReferenceType'
  UNION ALL SELECT '2026-09-18-000003', 'App\\Database\\Migrations\\CreateSupplierPayments'
  UNION ALL SELECT '2026-09-18-000004', 'App\\Database\\Migrations\\CreateSupplierPaymentAllocations'
  UNION ALL SELECT '2026-09-18-000005', 'App\\Database\\Migrations\\CreateBankAccounts'
  UNION ALL SELECT '2026-09-18-000006', 'App\\Database\\Migrations\\CreateBankTransactions'
  UNION ALL SELECT '2026-09-19-000000', 'App\\Database\\Migrations\\AddBankAccountToProjectCashReceipts'
  UNION ALL SELECT '2026-09-19-000001', 'App\\Database\\Migrations\\CreateServiceReceipts'
  UNION ALL SELECT '2026-09-19-000002', 'App\\Database\\Migrations\\CreateServiceReceiptItems'
  UNION ALL SELECT '2026-09-20-000001', 'App\\Database\\Migrations\\CreateCustomerPayments'
  UNION ALL SELECT '2026-09-20-000002', 'App\\Database\\Migrations\\CreateCustomerPaymentAllocations'
  UNION ALL SELECT '2026-09-20-000003', 'App\\Database\\Migrations\\CreateLoans'
  UNION ALL SELECT '2026-09-20-000004', 'App\\Database\\Migrations\\CreateLoanEmis'
  UNION ALL SELECT '2026-09-20-000005', 'App\\Database\\Migrations\\CreateLoanPayments'
) v
WHERE NOT EXISTS (SELECT 1 FROM `migrations` x WHERE x.version = v.version);
