<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// =============================================
// AUTH ROUTES
// =============================================
$routes->get('/', 'Auth::login');
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::doLogin');
$routes->get('logout', 'Auth::logout');

// =============================================
// PROTECTED ROUTES (require login)
// =============================================

// Dashboard
$routes->get('dashboard', 'Dashboard::index', ['filter' => 'auth']);

// Products
$routes->get('products', 'Products::index', ['filter' => 'auth']);
$routes->get('products/create', 'Products::create', ['filter' => 'auth']);
$routes->post('products/store', 'Products::store', ['filter' => 'auth']);
$routes->get('products/edit/(:num)', 'Products::edit/$1', ['filter' => 'auth']);
$routes->post('products/update/(:num)', 'Products::update/$1', ['filter' => 'auth']);
$routes->get('products/delete/(:num)', 'Products::delete/$1', ['filter' => 'auth']);

// Suppliers
$routes->get('suppliers', 'Suppliers::index', ['filter' => 'auth']);
$routes->get('suppliers/create', 'Suppliers::create', ['filter' => 'auth']);
$routes->post('suppliers/store', 'Suppliers::store', ['filter' => 'auth']);
$routes->get('suppliers/edit/(:num)', 'Suppliers::edit/$1', ['filter' => 'auth']);
$routes->post('suppliers/update/(:num)', 'Suppliers::update/$1', ['filter' => 'auth']);
$routes->get('suppliers/delete/(:num)', 'Suppliers::delete/$1', ['filter' => 'auth']);

// Customers
$routes->get('customers', 'Customers::index', ['filter' => 'auth']);
$routes->get('customers/create', 'Customers::create', ['filter' => 'auth']);
$routes->post('customers/store', 'Customers::store', ['filter' => 'auth']);
$routes->get('customers/edit/(:num)', 'Customers::edit/$1', ['filter' => 'auth']);
$routes->post('customers/update/(:num)', 'Customers::update/$1', ['filter' => 'auth']);
$routes->get('customers/delete/(:num)', 'Customers::delete/$1', ['filter' => 'auth']);

// Projects
$routes->get('projects', 'Projects::index', ['filter' => 'auth']);
$routes->get('projects/create', 'Projects::create', ['filter' => 'auth']);
$routes->post('projects/store', 'Projects::store', ['filter' => 'auth']);
$routes->get('projects/edit/(:num)', 'Projects::edit/$1', ['filter' => 'auth']);
$routes->post('projects/update/(:num)', 'Projects::update/$1', ['filter' => 'auth']);
$routes->post('projects/update-status/(:num)', 'Projects::updateStatus/$1', ['filter' => 'auth']);
$routes->post('projects/mark-billing-complete/(:num)', 'Projects::markBillingComplete/$1', ['filter' => 'auth']);
$routes->get('projects/delete/(:num)', 'Projects::delete/$1', ['filter' => 'auth']);
$routes->get('projects/view/(:num)', 'Projects::view/$1', ['filter' => 'auth']);
$routes->get('projects/statements', 'Projects::statementsList', ['filter' => 'auth']);
$routes->get('projects/statement/(:num)', 'Projects::statement/$1', ['filter' => 'auth']);
$routes->get('projects/statement-export/(:num)', 'Projects::statementExport/$1', ['filter' => 'auth']);
$routes->get('projects/statement-pdf/(:num)', 'Projects::statementPdf/$1', ['filter' => 'auth']);

// Purchases
$routes->get('purchases', 'Purchases::index', ['filter' => 'auth']);
$routes->get('purchases/create', 'Purchases::create', ['filter' => 'auth']);
$routes->post('purchases/store', 'Purchases::store', ['filter' => 'auth']);
$routes->get('purchases/edit/(:num)',   'Purchases::edit/$1',   ['filter' => 'auth']);
$routes->post('purchases/update/(:num)', 'Purchases::update/$1', ['filter' => 'auth']);
$routes->get('purchases/view/(:num)', 'Purchases::view/$1', ['filter' => 'auth']);
$routes->get('purchases/delete/(:num)', 'Purchases::delete/$1', ['filter' => 'auth']);

// Sales
$routes->get('sales', 'Sales::index', ['filter' => 'auth']);
$routes->get('sales/create', 'Sales::create', ['filter' => 'auth']);
$routes->post('sales/store', 'Sales::store', ['filter' => 'auth']);
$routes->get('sales/edit/(:num)',   'Sales::edit/$1',   ['filter' => 'auth']);
$routes->post('sales/update/(:num)', 'Sales::update/$1', ['filter' => 'auth']);
$routes->get('sales/view/(:num)', 'Sales::view/$1', ['filter' => 'auth']);
$routes->get('sales/delete/(:num)', 'Sales::delete/$1', ['filter' => 'auth']);

// AJAX endpoint for loading products by stock source
$routes->get('sales/get-products', 'Sales::getProducts', ['filter' => 'auth']);

// AJAX endpoint for live Project Financial Summary (Billing Tracker)
$routes->get('sales/project-financial-summary/(:num)', 'Sales::projectFinancialSummary/$1', ['filter' => 'auth']);

// Payments
$routes->get('payments', 'Payments::index', ['filter' => 'auth']);
$routes->get('payments/create', 'Payments::create', ['filter' => 'auth']);
$routes->get('payments/create/(:num)', 'Payments::create/$1', ['filter' => 'auth']);
$routes->post('payments/store', 'Payments::store', ['filter' => 'auth']);
$routes->get('payments/edit/(:num)', 'Payments::edit/$1', ['filter' => 'auth']);
$routes->post('payments/update/(:num)', 'Payments::update/$1', ['filter' => 'auth']);
$routes->get('payments/delete/(:num)', 'Payments::delete/$1', ['filter' => 'auth']);

// Release 4.5: Project Cash Receipts — separate module, independent of Payments
$routes->get('project-cash-receipts', 'ProjectCashReceipts::index', ['filter' => 'auth']);
$routes->get('project-cash-receipts/create', 'ProjectCashReceipts::create', ['filter' => 'auth']);
$routes->post('project-cash-receipts/store', 'ProjectCashReceipts::store', ['filter' => 'auth']);
$routes->get('project-cash-receipts/edit/(:num)', 'ProjectCashReceipts::edit/$1', ['filter' => 'auth']);
$routes->post('project-cash-receipts/update/(:num)', 'ProjectCashReceipts::update/$1', ['filter' => 'auth']);
$routes->get('project-cash-receipts/delete/(:num)', 'ProjectCashReceipts::delete/$1', ['filter' => 'auth']);

// Expense Categories (Masters)
$routes->get('expense-categories', 'ExpenseCategories::index', ['filter' => 'auth']);
$routes->get('expense-categories/create', 'ExpenseCategories::create', ['filter' => 'auth']);
$routes->post('expense-categories/store', 'ExpenseCategories::store', ['filter' => 'auth']);
$routes->get('expense-categories/edit/(:num)', 'ExpenseCategories::edit/$1', ['filter' => 'auth']);
$routes->post('expense-categories/update/(:num)', 'ExpenseCategories::update/$1', ['filter' => 'auth']);
$routes->get('expense-categories/delete/(:num)', 'ExpenseCategories::delete/$1', ['filter' => 'auth']);

// Expenses
$routes->get('expenses', 'Expenses::index', ['filter' => 'auth']);
$routes->get('expenses/create', 'Expenses::create', ['filter' => 'auth']);
$routes->post('expenses/store', 'Expenses::store', ['filter' => 'auth']);
$routes->get('expenses/edit/(:num)', 'Expenses::edit/$1', ['filter' => 'auth']);
$routes->post('expenses/update/(:num)', 'Expenses::update/$1', ['filter' => 'auth']);
$routes->get('expenses/delete/(:num)', 'Expenses::delete/$1', ['filter' => 'auth']);

// Reports
$routes->get('reports', 'Reports::index', ['filter' => 'auth']);
$routes->get('reports/profit-loss', 'Reports::profitLoss', ['filter' => 'auth']);
$routes->get('reports/profit-loss-export', 'Reports::profitLossExport', ['filter' => 'auth']);
$routes->get('reports/profit-loss-pdf', 'Reports::profitLossPdf', ['filter' => 'auth']);
$routes->get('reports/stock',              'Reports::stock',            ['filter' => 'auth']);
$routes->get('reports/stock-export', 'Reports::stockExport', ['filter' => 'auth']);
$routes->get('reports/stock-pdf', 'Reports::stockPdf', ['filter' => 'auth']);
$routes->get('reports/sales', 'Reports::sales', ['filter' => 'auth']);
$routes->get('reports/sales-export', 'Reports::salesExport', ['filter' => 'auth']);
$routes->get('reports/sales-pdf', 'Reports::salesPdf', ['filter' => 'auth']);
$routes->get('reports/purchases', 'Reports::purchases', ['filter' => 'auth']);
$routes->get('reports/purchases-export', 'Reports::purchasesExport', ['filter' => 'auth']);
$routes->get('reports/purchases-pdf', 'Reports::purchasesPdf', ['filter' => 'auth']);
$routes->get('reports/ledger', 'Reports::ledger', ['filter' => 'auth']);
$routes->get('reports/ledger-export', 'Reports::ledgerExport', ['filter' => 'auth']);
$routes->get('reports/ledger-pdf', 'Reports::ledgerPdf', ['filter' => 'auth']);
$routes->get('reports/get-products-by-project', 'Reports::getProductsByProject', ['filter' => 'auth']);
$routes->get('reports/balance-sheet', 'Reports::balanceSheet', ['filter' => 'auth']);
$routes->get('reports/balance-sheet-export', 'Reports::balanceSheetExport', ['filter' => 'auth']);
$routes->get('reports/balance-sheet-pdf', 'Reports::balanceSheetPdf', ['filter' => 'auth']);

// Stock
$routes->get('stock-entry', 'Stock::index', ['filter' => 'auth']);
$routes->get('stock-entry/create', 'Stock::create', ['filter' => 'auth']);
$routes->get('stock-entry/edit/(:num)', 'Stock::edit/$1', ['filter' => 'auth']);
$routes->post('stock-entry/update/(:num)', 'Stock::update/$1', ['filter' => 'auth']);
$routes->get('stock-entry/view/(:num)', 'Stock::view/$1', ['filter' => 'auth']);
$routes->post('stock-entry/store', 'Stock::store', ['filter' => 'auth']);
