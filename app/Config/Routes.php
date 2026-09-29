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

// Forgot Password (public, OTP-based reset)
$routes->get('forgot-password', 'Auth::forgotPassword');
$routes->post('forgot-password/send', 'Auth::sendOtp');
$routes->get('forgot-password/verify', 'Auth::verifyOtp');
$routes->post('forgot-password/verify', 'Auth::verifyOtp');
$routes->post('forgot-password/resend', 'Auth::resendOtp');
$routes->get('forgot-password/reset', 'Auth::resetPassword');
$routes->post('forgot-password/reset', 'Auth::resetPassword');

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
$routes->post('suppliers/ajax-store', 'Suppliers::ajaxStore', ['filter' => 'auth']);

// Customers
$routes->get('customers', 'Customers::index', ['filter' => 'auth']);
$routes->get('customers/create', 'Customers::create', ['filter' => 'auth']);
$routes->post('customers/store', 'Customers::store', ['filter' => 'auth']);
$routes->post('customers/ajax-store', 'Customers::ajaxStore', ['filter' => 'auth']);
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

// General Purchases (Release 4.8.1B — backend CRUD only, no views yet)
$routes->get('general-purchases', 'GeneralPurchases::index', ['filter' => 'auth']);
$routes->get('general-purchases/create', 'GeneralPurchases::create', ['filter' => 'auth']);
$routes->post('general-purchases/store', 'GeneralPurchases::store', ['filter' => 'auth']);
$routes->get('general-purchases/view/(:num)', 'GeneralPurchases::view/$1', ['filter' => 'auth']);
$routes->get('general-purchases/edit/(:num)', 'GeneralPurchases::edit/$1', ['filter' => 'auth']);
$routes->post('general-purchases/update/(:num)', 'GeneralPurchases::update/$1', ['filter' => 'auth']);
$routes->post('general-purchases/delete/(:num)', 'GeneralPurchases::delete/$1', ['filter' => 'auth']);

// Supplier Payments (Release 4.8.2B — backend CRUD + allocation engine only, no views yet)
$routes->get('supplier-payments', 'SupplierPayments::index', ['filter' => 'auth']);
$routes->get('supplier-payments/create', 'SupplierPayments::create', ['filter' => 'auth']);
$routes->post('supplier-payments/store', 'SupplierPayments::store', ['filter' => 'auth']);
$routes->get('supplier-payments/view/(:num)', 'SupplierPayments::view/$1', ['filter' => 'auth']);
$routes->get('supplier-payments/edit/(:num)', 'SupplierPayments::edit/$1', ['filter' => 'auth']);
$routes->post('supplier-payments/update/(:num)', 'SupplierPayments::update/$1', ['filter' => 'auth']);
$routes->post('supplier-payments/delete/(:num)', 'SupplierPayments::delete/$1', ['filter' => 'auth']);
$routes->post('supplier-payments/get-bills', 'SupplierPayments::ajaxBills', ['filter' => 'auth']);

$routes->get('supplier-ledger', 'SupplierLedger::index', ['filter' => 'auth']);
$routes->get('supplier-ledger/view/(:num)', 'SupplierLedger::view/$1', ['filter' => 'auth']);
$routes->get('supplier-ledger/export/pdf/(:num)', 'SupplierLedger::exportPdf/$1', ['filter' => 'auth']);
$routes->get('supplier-ledger/export/excel/(:num)', 'SupplierLedger::exportExcel/$1', ['filter' => 'auth']);

$routes->get('bank-accounts', 'BankAccounts::index', ['filter' => 'auth']);
$routes->get('bank-accounts/create', 'BankAccounts::create', ['filter' => 'auth']);
$routes->post('bank-accounts/store', 'BankAccounts::store', ['filter' => 'auth']);
$routes->get('bank-accounts/view/(:num)', 'BankAccounts::view/$1', ['filter' => 'auth']);
$routes->get('bank-accounts/edit/(:num)', 'BankAccounts::edit/$1', ['filter' => 'auth']);
$routes->post('bank-accounts/update/(:num)', 'BankAccounts::update/$1', ['filter' => 'auth']);
$routes->post('bank-accounts/delete/(:num)', 'BankAccounts::delete/$1', ['filter' => 'auth']);
$routes->get('bank-accounts/transactions/(:num)', 'BankAccounts::transactions/$1', ['filter' => 'auth']);
$routes->post('bank-accounts/store-transaction', 'BankAccounts::storeTransaction', ['filter' => 'auth']);

// Manual bank ledger (Release 4.8.4I — Deposit / Withdrawal / Transfer / Manual Entry CRUD + Bank Statement)
$routes->get('bank-deposits', 'BankTransactions::legacy/deposit', ['filter' => 'auth']);
$routes->get('bank-deposits/create', 'BankDeposits::create', ['filter' => 'auth']);
$routes->post('bank-deposits/store', 'BankDeposits::store', ['filter' => 'auth']);
$routes->get('bank-deposits/view/(:num)', 'BankDeposits::view/$1', ['filter' => 'auth']);
$routes->get('bank-deposits/edit/(:num)', 'BankDeposits::edit/$1', ['filter' => 'auth']);
$routes->post('bank-deposits/update/(:num)', 'BankDeposits::update/$1', ['filter' => 'auth']);
$routes->post('bank-deposits/delete/(:num)', 'BankDeposits::delete/$1', ['filter' => 'auth']);

$routes->get('bank-withdrawals', 'BankTransactions::legacy/withdrawal', ['filter' => 'auth']);
$routes->get('bank-withdrawals/create', 'BankWithdrawals::create', ['filter' => 'auth']);
$routes->post('bank-withdrawals/store', 'BankWithdrawals::store', ['filter' => 'auth']);
$routes->get('bank-withdrawals/view/(:num)', 'BankWithdrawals::view/$1', ['filter' => 'auth']);
$routes->get('bank-withdrawals/edit/(:num)', 'BankWithdrawals::edit/$1', ['filter' => 'auth']);
$routes->post('bank-withdrawals/update/(:num)', 'BankWithdrawals::update/$1', ['filter' => 'auth']);
$routes->post('bank-withdrawals/delete/(:num)', 'BankWithdrawals::delete/$1', ['filter' => 'auth']);

$routes->get('bank-transfers', 'BankTransactions::legacy/transfer', ['filter' => 'auth']);
$routes->get('bank-transfers/create', 'BankTransfers::create', ['filter' => 'auth']);
$routes->post('bank-transfers/store', 'BankTransfers::store', ['filter' => 'auth']);
$routes->get('bank-transfers/view/(:num)', 'BankTransfers::view/$1', ['filter' => 'auth']);
$routes->get('bank-transfers/edit/(:num)', 'BankTransfers::edit/$1', ['filter' => 'auth']);
$routes->post('bank-transfers/update/(:num)', 'BankTransfers::update/$1', ['filter' => 'auth']);
$routes->post('bank-transfers/delete/(:num)', 'BankTransfers::delete/$1', ['filter' => 'auth']);

// Release 4.8.4I Patch: the former Bank Daybook screen is "Manual Entry" under Bank Accounts (same BankDaybook controller).
$routes->get('bank-accounts/manual-entry', 'BankTransactions::legacy/manual', ['filter' => 'auth']);
$routes->get('bank-accounts/manual-entry/create', 'BankDaybook::create', ['filter' => 'auth']);
$routes->post('bank-accounts/manual-entry/store', 'BankDaybook::store', ['filter' => 'auth']);
$routes->get('bank-accounts/manual-entry/view/(:num)', 'BankDaybook::view/$1', ['filter' => 'auth']);
$routes->get('bank-accounts/manual-entry/edit/(:num)', 'BankDaybook::edit/$1', ['filter' => 'auth']);
$routes->post('bank-accounts/manual-entry/update/(:num)', 'BankDaybook::update/$1', ['filter' => 'auth']);
$routes->post('bank-accounts/manual-entry/delete/(:num)', 'BankDaybook::delete/$1', ['filter' => 'auth']);

// Release 4.8.4I Patch: read-only list of every bank_transactions row (the per-account page above keeps its /(:num) route).
$routes->get('bank-accounts/transactions', 'BankTransactions::index', ['filter' => 'auth']);

// Release 4.8.5A: Transactions is the single bank transaction module. The unified entry screen posts to the existing
// bank-deposits / bank-withdrawals / bank-transfers / bank-accounts/manual-entry store endpoints (unchanged).
$routes->get('bank-accounts/transactions/new', 'BankTransactions::create', ['filter' => 'auth']);
// The former list screens redirect to Transactions (voucher tab preselected); their create/view/edit/update/delete routes above stay as they were.
$routes->get('bank-accounts/deposit', 'BankTransactions::legacy/deposit', ['filter' => 'auth']);
$routes->get('bank-accounts/withdrawal', 'BankTransactions::legacy/withdrawal', ['filter' => 'auth']);
$routes->get('bank-accounts/transfer', 'BankTransactions::legacy/transfer', ['filter' => 'auth']);

$routes->get('bank-statement', 'BankStatement::index', ['filter' => 'auth']);

// Cash Book (Release 4.9.0E — read-only cash ledger derived from cash-method records; exports via ?export=excel|pdf)
$routes->get('cash-book', 'CashBook::index', ['filter' => 'auth']);

// Warehouse (Release 4.8.1E — read-only reporting on existing stock_ledger)
$routes->get('warehouse/stock-summary', 'Warehouse::stockSummary', ['filter' => 'auth']);
$routes->get('warehouse/stock-ledger', 'Warehouse::stockLedger', ['filter' => 'auth']);
$routes->get('warehouse/product-ledger/(:num)', 'Warehouse::productLedger/$1', ['filter' => 'auth']);

// Service Receipts (Release 4.8.4A — backend CRUD only, no views yet)
$routes->get('service-receipts', 'ServiceReceipts::index', ['filter' => 'auth']);
$routes->get('service-receipts/create', 'ServiceReceipts::create', ['filter' => 'auth']);
$routes->post('service-receipts/store', 'ServiceReceipts::store', ['filter' => 'auth']);
$routes->get('service-receipts/view/(:num)', 'ServiceReceipts::view/$1', ['filter' => 'auth']);
$routes->get('service-receipts/edit/(:num)', 'ServiceReceipts::edit/$1', ['filter' => 'auth']);
$routes->post('service-receipts/update/(:num)', 'ServiceReceipts::update/$1', ['filter' => 'auth']);
$routes->post('service-receipts/delete/(:num)', 'ServiceReceipts::delete/$1', ['filter' => 'auth']);

// Customer Payments (Release 4.8.4C — allocation engine, backend only, no views yet)
$routes->get('customer-payments', 'CustomerPayments::index', ['filter' => 'auth']);
$routes->get('customer-payments/create', 'CustomerPayments::create', ['filter' => 'auth']);
$routes->post('customer-payments/store', 'CustomerPayments::store', ['filter' => 'auth']);
$routes->get('customer-payments/view/(:num)', 'CustomerPayments::view/$1', ['filter' => 'auth']);
$routes->get('customer-payments/edit/(:num)', 'CustomerPayments::edit/$1', ['filter' => 'auth']);
$routes->post('customer-payments/update/(:num)', 'CustomerPayments::update/$1', ['filter' => 'auth']);
$routes->post('customer-payments/delete/(:num)', 'CustomerPayments::delete/$1', ['filter' => 'auth']);
$routes->post('customer-payments/get-invoices', 'CustomerPayments::ajaxInvoices', ['filter' => 'auth']);

// Customer Ledger (Release 4.8.4D — read-only statement over service receipts + customer payments)
$routes->get('customer-ledger', 'CustomerLedger::index', ['filter' => 'auth']);
$routes->get('customer-ledger/view/(:num)', 'CustomerLedger::view/$1', ['filter' => 'auth']);
$routes->get('customer-ledger/export/pdf/(:num)', 'CustomerLedger::exportPdf/$1', ['filter' => 'auth']);
$routes->get('customer-ledger/export/excel/(:num)', 'CustomerLedger::exportExcel/$1', ['filter' => 'auth']);

// Service Reports (Release 4.8.4E — read-only reports over service receipts + customer payments)
$routes->get('service-reports/register', 'ServiceReports::register', ['filter' => 'auth']);
$routes->get('service-reports/outstanding', 'ServiceReports::outstanding', ['filter' => 'auth']);
$routes->get('service-reports/collections', 'ServiceReports::collections', ['filter' => 'auth']);
$routes->get('service-reports/customer-summary', 'ServiceReports::customerSummary', ['filter' => 'auth']);

// Service Reports PDF exports (Release 4.8.7A)
$routes->get('service-reports/export/pdf', 'ServiceReports::exportPdf', ['filter' => 'auth']);
$routes->get('service-reports/export/pdf/outstanding', 'ServiceReports::exportOutstandingPdf', ['filter' => 'auth']);
$routes->get('service-reports/export/pdf/collections', 'ServiceReports::exportCollectionsPdf', ['filter' => 'auth']);
$routes->get('service-reports/export/pdf/customer-summary', 'ServiceReports::exportCustomerSummaryPdf', ['filter' => 'auth']);

$routes->get('service-reports/export/excel', 'ServiceReports::exportExcel', ['filter' => 'auth']);
$routes->get('service-reports/export/excel/outstanding', 'ServiceReports::exportOutstandingExcel', ['filter' => 'auth']);
$routes->get('service-reports/export/excel/collections', 'ServiceReports::exportCollectionsExcel', ['filter' => 'auth']);
$routes->get('service-reports/export/excel/customer-summary', 'ServiceReports::exportCustomerSummaryExcel', ['filter' => 'auth']);

// Loans (Release 4.8.5A — loan master + EMI schedule engine, backend only, no views yet)
$routes->get('loans', 'Loans::index', ['filter' => 'auth']);
$routes->get('loans/create', 'Loans::create', ['filter' => 'auth']);
$routes->post('loans/store', 'Loans::store', ['filter' => 'auth']);
$routes->get('loans/view/(:num)', 'Loans::view/$1', ['filter' => 'auth']);
$routes->get('loans/edit/(:num)', 'Loans::edit/$1', ['filter' => 'auth']);
$routes->post('loans/update/(:num)', 'Loans::update/$1', ['filter' => 'auth']);
$routes->post('loans/delete/(:num)', 'Loans::delete/$1', ['filter' => 'auth']);
$routes->post('loans/generate-schedule', 'Loans::ajaxGenerateSchedule', ['filter' => 'auth']);

// Loan payments + ledger (Release 4.8.5C — EMI payment engine, bank posting, loan ledger)
$routes->get('loans/payments/(:num)', 'Loans::payments/$1', ['filter' => 'auth']);
$routes->post('loans/payments/store', 'Loans::storePayment', ['filter' => 'auth']);
$routes->post('loans/payments/update/(:num)', 'Loans::updatePayment/$1', ['filter' => 'auth']);
$routes->post('loans/payments/delete/(:num)', 'Loans::deletePayment/$1', ['filter' => 'auth']);
$routes->get('loans/ledger/(:num)', 'Loans::ledger/$1', ['filter' => 'auth']);
$routes->get('loans/export/pdf/(:num)', 'Loans::exportLedgerPdf/$1', ['filter' => 'auth']);
$routes->get('loans/ledger/export/excel/(:num)', 'Loans::exportLedgerExcel/$1', ['filter' => 'auth']);

// Loan Reports (Release 4.8.5D — read-only reports over loans, EMIs and loan payments)
$routes->get('loan-reports', 'LoanReports::index', ['filter' => 'auth']);
$routes->get('loan-reports/emi-due', 'LoanReports::emiDue', ['filter' => 'auth']);
$routes->get('loan-reports/payments', 'LoanReports::payments', ['filter' => 'auth']);
$routes->get('loan-reports/outstanding', 'LoanReports::outstanding', ['filter' => 'auth']);
$routes->get('loan-reports/lender-summary', 'LoanReports::lenderSummary', ['filter' => 'auth']);

// Loan Reports PDF exports (Release 4.8.7A)
$routes->get('loan-reports/export/pdf', 'LoanReports::exportPdf', ['filter' => 'auth']);
$routes->get('loan-reports/export/pdf/emi-due', 'LoanReports::exportEmiDuePdf', ['filter' => 'auth']);
$routes->get('loan-reports/export/pdf/payments', 'LoanReports::exportPaymentsPdf', ['filter' => 'auth']);
$routes->get('loan-reports/export/pdf/outstanding', 'LoanReports::exportOutstandingPdf', ['filter' => 'auth']);
$routes->get('loan-reports/export/pdf/lender-summary', 'LoanReports::exportLenderSummaryPdf', ['filter' => 'auth']);

$routes->get('loan-reports/export/excel', 'LoanReports::exportExcel', ['filter' => 'auth']);
$routes->get('loan-reports/export/excel/emi-due', 'LoanReports::exportEmiDueExcel', ['filter' => 'auth']);
$routes->get('loan-reports/export/excel/payments', 'LoanReports::exportPaymentsExcel', ['filter' => 'auth']);
$routes->get('loan-reports/export/excel/outstanding', 'LoanReports::exportOutstandingExcel', ['filter' => 'auth']);
$routes->get('loan-reports/export/excel/lender-summary', 'LoanReports::exportLenderSummaryExcel', ['filter' => 'auth']);

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

// Release 4.5.5 (Phase E): Project Cash Receipt now has exactly one entry
// point — the Payment Type toggle on payments/create.php — so only the
// storage endpoint remains routed. index/create/edit/update/delete (the
// standalone list + form pages) are removed.
$routes->post('project-cash-receipts/store', 'ProjectCashReceipts::store', ['filter' => 'auth']);

// Expense Categories (Masters)
$routes->get('expense-categories', 'ExpenseCategories::index', ['filter' => 'auth']);
$routes->get('expense-categories/create', 'ExpenseCategories::create', ['filter' => 'auth']);
$routes->post('expense-categories/store', 'ExpenseCategories::store', ['filter' => 'auth']);
$routes->get('expense-categories/edit/(:num)', 'ExpenseCategories::edit/$1', ['filter' => 'auth']);
$routes->post('expense-categories/update/(:num)', 'ExpenseCategories::update/$1', ['filter' => 'auth']);
$routes->get('expense-categories/delete/(:num)', 'ExpenseCategories::delete/$1', ['filter' => 'auth']);

// Expenses (Release 4.8.6A: Expense Management Foundation — JSON-only backend)
$routes->get('expenses', 'Expenses::index', ['filter' => 'auth']);
$routes->get('expenses/create', 'Expenses::create', ['filter' => 'auth']);
$routes->post('expenses/store', 'Expenses::store', ['filter' => 'auth']);
$routes->get('expenses/view/(:num)', 'Expenses::view/$1', ['filter' => 'auth']);
$routes->get('expenses/edit/(:num)', 'Expenses::edit/$1', ['filter' => 'auth']);
$routes->post('expenses/update/(:num)', 'Expenses::update/$1', ['filter' => 'auth']);
$routes->post('expenses/delete/(:num)', 'Expenses::delete/$1', ['filter' => 'auth']);
$routes->get('expenses/export/pdf/(:num)', 'Expenses::exportPdf/$1', ['filter' => 'auth']);
$routes->get('expenses/export/excel/(:num)', 'Expenses::exportExcel/$1', ['filter' => 'auth']);

// Expense Reports (Release 4.8.6D: read-only reports over 4.8.6A-C data)
$routes->get('expense-reports', 'ExpenseReports::index', ['filter' => 'auth']);
$routes->get('expense-reports/category-summary', 'ExpenseReports::categorySummary', ['filter' => 'auth']);
$routes->get('expense-reports/project-summary', 'ExpenseReports::projectSummary', ['filter' => 'auth']);
$routes->get('expense-reports/payment-summary', 'ExpenseReports::paymentSummary', ['filter' => 'auth']);
$routes->get('expense-reports/monthly-summary', 'ExpenseReports::monthlySummary', ['filter' => 'auth']);

// Expense Reports PDF exports (Release 4.8.7A)
$routes->get('expense-reports/export/pdf', 'ExpenseReports::exportPdf', ['filter' => 'auth']);
$routes->get('expense-reports/export/pdf/category-summary', 'ExpenseReports::exportCategorySummaryPdf', ['filter' => 'auth']);
$routes->get('expense-reports/export/pdf/project-summary', 'ExpenseReports::exportProjectSummaryPdf', ['filter' => 'auth']);
$routes->get('expense-reports/export/pdf/payment-summary', 'ExpenseReports::exportPaymentSummaryPdf', ['filter' => 'auth']);
$routes->get('expense-reports/export/pdf/monthly-summary', 'ExpenseReports::exportMonthlySummaryPdf', ['filter' => 'auth']);

$routes->get('expense-reports/export/excel', 'ExpenseReports::exportExcel', ['filter' => 'auth']);
$routes->get('expense-reports/export/excel/category-summary', 'ExpenseReports::exportCategorySummaryExcel', ['filter' => 'auth']);
$routes->get('expense-reports/export/excel/project-summary', 'ExpenseReports::exportProjectSummaryExcel', ['filter' => 'auth']);
$routes->get('expense-reports/export/excel/payment-summary', 'ExpenseReports::exportPaymentSummaryExcel', ['filter' => 'auth']);
$routes->get('expense-reports/export/excel/monthly-summary', 'ExpenseReports::exportMonthlySummaryExcel', ['filter' => 'auth']);

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

// Print/Export Center (Release 4.8.7C — view-only page; links to PDF/Excel
// export routes already registered above from 4.8.7A/4.8.7B; no new export
// generation logic anywhere in this controller/route)
$routes->get('reports/export-center', 'DashboardExportController::index', ['filter' => 'auth']);

// Audit Logs (Release 4.8.8A — read-only viewer over audit_logs; no delete/edit routes)
$routes->get('audit-logs', 'AuditLogs::index', ['filter' => 'auth']);
$routes->get('audit-logs/(:num)', 'AuditLogs::view/$1', ['filter' => 'auth']);

// Backup & Restore Center (Release 4.8.8C). No role/permission system exists
// in this codebase (see app/Filters/AuthFilter.php) so, same as every other
// module, this is gated only by 'auth' — any logged-in user can create,
// download, delete a backup, or restore the database. See Known Limitations
// in the Release 4.8.8C report.
$routes->get('database-backup', 'DatabaseBackup::index', ['filter' => 'auth']);
$routes->post('database-backup/create', 'DatabaseBackup::create', ['filter' => 'auth']);
$routes->get('database-backup/download/(:num)', 'DatabaseBackup::download/$1', ['filter' => 'auth']);
$routes->post('database-backup/delete/(:num)', 'DatabaseBackup::deleteRecord/$1', ['filter' => 'auth']);
$routes->get('database-backup/restore', 'DatabaseBackup::restoreForm', ['filter' => 'auth']);
$routes->post('database-backup/restore', 'DatabaseBackup::restore', ['filter' => 'auth']);

// Stock
$routes->get('stock-entry', 'Stock::index', ['filter' => 'auth']);
$routes->get('stock-entry/create', 'Stock::create', ['filter' => 'auth']);
$routes->get('stock-entry/edit/(:num)', 'Stock::edit/$1', ['filter' => 'auth']);
$routes->post('stock-entry/update/(:num)', 'Stock::update/$1', ['filter' => 'auth']);
$routes->get('stock-entry/view/(:num)', 'Stock::view/$1', ['filter' => 'auth']);
$routes->post('stock-entry/store', 'Stock::store', ['filter' => 'auth']);
