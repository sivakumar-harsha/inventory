-- =============================================
-- Project-Based Inventory & Billing ERP
-- Database: erp_db
-- MySQL 8+
-- =============================================

CREATE DATABASE IF NOT EXISTS erp_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE erp_db;

-- =============================================
-- USERS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO users (username, password) VALUES ('admin', 'admin123');

-- =============================================
-- PRODUCTS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    sku VARCHAR(100),
    unit VARCHAR(50) NOT NULL DEFAULT 'pcs',
    cost_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    sell_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    category VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- SUPPLIERS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    contact VARCHAR(100),
    phone VARCHAR(50),
    email VARCHAR(150),
    address TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- CUSTOMERS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    contact VARCHAR(100),
    phone VARCHAR(50),
    email VARCHAR(150),
    address TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- PROJECTS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    customer_id INT UNSIGNED,
    status ENUM('ACTIVE','COMPLETED','ON_HOLD') NOT NULL DEFAULT 'ACTIVE',
    start_date DATE,
    end_date DATE,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- PURCHASES TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS purchases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT UNSIGNED NOT NULL,
    project_id INT UNSIGNED,
    stock_source ENUM('GENERAL','PROJECT') NOT NULL DEFAULT 'GENERAL',
    purchase_date DATE NOT NULL,
    invoice_no VARCHAR(100),
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- PURCHASE ITEMS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS purchase_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(15,3) NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    total DECIMAL(15,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- =============================================
-- SALES TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS sales (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED,
    sale_date DATE NOT NULL,
    invoice_no VARCHAR(100),
    stock_source ENUM('GENERAL','PROJECT') NOT NULL DEFAULT 'GENERAL',
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    balance_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('UNPAID','PARTIAL','PAID') NOT NULL DEFAULT 'UNPAID',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- SALE ITEMS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS sale_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(15,3) NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    total DECIMAL(15,2) NOT NULL,
    stock_source ENUM('GENERAL','PROJECT') NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- =============================================
-- PAYMENTS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id INT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_date DATE NOT NULL,
    method ENUM('CASH','BANK_TRANSFER','CHECK','OTHER') NOT NULL DEFAULT 'CASH',
    reference VARCHAR(100),
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id)
) ENGINE=InnoDB;

-- =============================================
-- EXPENSES TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT,
    amount DECIMAL(15,2) NOT NULL,
    expense_date DATE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id)
) ENGINE=InnoDB;

-- =============================================
-- STOCK LEDGER TABLE (CRITICAL - ONLY STOCK SOURCE)
-- =============================================
CREATE TABLE IF NOT EXISTS stock_ledger (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    transaction_type ENUM('IN','OUT') NOT NULL,
    quantity DECIMAL(15,3) NOT NULL,
    source ENUM('GENERAL','PROJECT') NOT NULL DEFAULT 'GENERAL',
    project_id INT UNSIGNED,
    reference_type ENUM('PURCHASE','SALE') NOT NULL,
    reference_id INT UNSIGNED NOT NULL,
    transaction_date DATE NOT NULL,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- SAMPLE DATA
-- =============================================

INSERT INTO suppliers (name, contact, phone, email, address) VALUES
('ABC Trading Co.', 'John Smith', '0123-456789', 'john@abctrading.com', '123 Supplier Street, City'),
('XYZ Distributors', 'Jane Doe', '0987-654321', 'jane@xyzd.com', '456 Trade Avenue, Town');

INSERT INTO customers (name, contact, phone, email, address) VALUES
('Alpha Corp', 'Mike Johnson', '0111-222333', 'mike@alphacorp.com', '789 Client Road, City'),
('Beta Solutions', 'Sarah Lee', '0444-555666', 'sarah@betasol.com', '321 Business Park, Town');

INSERT INTO products (name, sku, unit, cost_price, sell_price, category) VALUES
('Steel Pipe 2 inch', 'STL-001', 'pcs', 45.00, 65.00, 'Materials'),
('Cement Bag 50kg', 'CMT-001', 'bag', 25.00, 35.00, 'Materials'),
('PVC Pipe 1 inch', 'PVC-001', 'pcs', 12.00, 18.00, 'Materials'),
('Electrical Wire 2.5mm', 'EWR-001', 'meter', 3.50, 5.50, 'Electrical'),
('Circuit Breaker 20A', 'CBR-001', 'pcs', 85.00, 120.00, 'Electrical');

INSERT INTO projects (name, customer_id, status, start_date, description) VALUES
('Office Renovation - Phase 1', 1, 'ACTIVE', '2024-01-01', 'Complete office renovation project'),
('Warehouse Electrical Upgrade', 2, 'ACTIVE', '2024-02-01', 'Electrical system upgrade for warehouse');
