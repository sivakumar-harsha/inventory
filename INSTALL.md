# ERP System Installation Guide

## Requirements
- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache or Nginx with mod_rewrite
- Composer

## Step 1: Set Up Database

1. Open phpMyAdmin or MySQL client
2. Run the SQL file:
   ```
   erp_database.sql
   ```
   This creates the database `erp_db` with all tables and sample data.

## Step 2: Configure Database Connection

Open `app/Config/Database.php` and update:

```php
public array $default = [
    'hostname' => 'localhost',   // Your DB host
    'username' => 'root',        // Your DB username
    'password' => '',            // Your DB password
    'database' => 'erp_db',     // Database name
    ...
];
```

## Step 3: Install CodeIgniter 4

Option A — With Composer (Recommended):
```bash
composer create-project codeigniter4/appstarter erp-final
```
Then copy all files from this package into that folder, overwriting where necessary.

Option B — Download CI4:
Download CodeIgniter 4 from https://codeigniter.com/download
Extract and copy this package's files into it.

## Step 4: Set Base URL

Open `app/Config/App.php` and set:
```php
public string $baseURL = 'http://localhost/erp-final/public/';
```
Adjust to match your server path.

## Step 5: Set Up Virtual Host or Access via XAMPP/WAMP

For XAMPP:
- Place the folder in `C:/xampp/htdocs/erp-final/`
- Access at: http://localhost/erp-final/public/

For Virtual Host (Recommended):
```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/erp-final/public"
    ServerName erp.local
</VirtualHost>
```

## Step 6: File Permissions (Linux/Mac)
```bash
chmod -R 755 writable/
```

## Login Credentials

| Field    | Value    |
|----------|----------|
| Username | admin    |
| Password | admin123 |

## Module List

| Module    | Features                                          |
|-----------|---------------------------------------------------|
| Dashboard | KPI overview, recent sales, recent purchases       |
| Products  | CRUD, SKU, unit, cost/sell price                  |
| Suppliers | CRUD, contact info                                |
| Customers | CRUD, contact info                                |
| Projects  | CRUD, customer link, status, P&L view             |
| Purchases | Create with items, stock IN via ledger            |
| Sales     | AJAX source selection, stock OUT via ledger       |
| Payments  | Record against sales, auto-update balance         |
| Expenses  | Project-linked expenses by category               |
| Reports   | Profit/Loss, Stock Summary, Sales, Purchases, Ledger |

## Stock Logic (Important)

Stock is NEVER stored in the products table.
ALL stock is tracked via the `stock_ledger` table:
- Purchase → stock IN entry
- Sale → stock OUT entry
- Available stock = SUM(IN) - SUM(OUT) grouped by product + source
