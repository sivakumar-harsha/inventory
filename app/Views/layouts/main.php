<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'A&A Inventory' ?></title>
	<link rel="icon" type="image/png" href="<?= base_url('assets/images/aainv_logo.png') ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
	<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
	<style>
		/* Release 4.8.5A: expandable Bank Accounts sidebar menu */
		.sidebar-nav .nav-parent { cursor: pointer; }
		.sidebar-nav .nav-parent.nav-parent-open { color: var(--white); }
		.sidebar-nav .nav-parent .nav-caret { margin-left: auto; width: auto; font-size: .7rem; transition: transform .15s ease; }
		.sidebar-nav .nav-parent[aria-expanded="true"] .nav-caret { transform: rotate(180deg); }
		.sidebar-nav .nav-sub { padding-left: 42px; font-size: .8rem; }
	</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-box-seam-fill"></i>
        <span>A&A System</span>
    </div>
    <nav class="sidebar-nav">
        <a href="<?= base_url('dashboard') ?>" class="nav-link <?= (current_url() === base_url('dashboard')) ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <div class="nav-section">INVENTORY</div>
        <a href="<?= base_url('products') ?>" class="nav-link <?= (strpos(current_url(), '/products') !== false) ? 'active' : '' ?>">
            <i class="bi bi-box"></i> Products
        </a>
        <a href="<?= base_url('suppliers') ?>" class="nav-link <?= (strpos(current_url(), '/suppliers') !== false) ? 'active' : '' ?>">
            <i class="bi bi-truck"></i> Suppliers
        </a>
        <a href="<?= base_url('warehouse/stock-summary') ?>" class="nav-link <?= (strpos(current_url(), '/warehouse/stock-summary') !== false || strpos(current_url(), '/warehouse/product-ledger') !== false) ? 'active' : '' ?>">
            <i class="bi bi-boxes"></i> Warehouse Stock Summary
        </a>
        <a href="<?= base_url('warehouse/stock-ledger') ?>" class="nav-link <?= (strpos(current_url(), '/warehouse/stock-ledger') !== false) ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Warehouse Stock Ledger
        </a>
        <div class="nav-section">MASTERS</div>
        <a href="<?= base_url('expense-categories') ?>" class="nav-link <?= (strpos(current_url(), '/expense-categories') !== false) ? 'active' : '' ?>">
            <i class="bi bi-tags"></i> Expense Categories
        </a>
        <div class="nav-section">CRM</div>
        <a href="<?= base_url('customers') ?>" class="nav-link <?= (strpos(current_url(), '/customers') !== false) ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Customers
        </a>
        <a href="<?= base_url('projects') ?>" class="nav-link <?= (strpos(current_url(), '/projects') !== false && strpos(current_url(), '/projects/statement') === false) ? 'active' : '' ?>">
            <i class="bi bi-kanban"></i> Projects
        </a>
        <a href="<?= base_url('projects/statements') ?>" class="nav-link <?= (strpos(current_url(), '/projects/statement') !== false) ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-text"></i> Project Statements
        </a>
        <div class="nav-section">TRANSACTIONS</div>
        <a href="<?= base_url('purchases') ?>" class="nav-link <?= (strpos(current_url(), '/purchases') !== false) ? 'active' : '' ?>">
            <i class="bi bi-cart-plus"></i> Purchases
        </a>
        <a href="<?= base_url('general-purchases') ?>" class="nav-link <?= (strpos(current_url(), '/general-purchases') !== false) ? 'active' : '' ?>">
            <i class="bi bi-boxes"></i> General Purchase
        </a>
		<a href="<?= base_url('stock-entry') ?>" class="nav-link <?= (strpos(current_url(), '/stock-entry') !== false) ? 'active' : '' ?>">
			<i class="bi bi-box-seam"></i> Stock Entry
		</a>
        <a href="<?= base_url('sales') ?>" class="nav-link <?= (strpos(current_url(), '/sales') !== false) ? 'active' : '' ?>">
            <i class="bi bi-receipt"></i> Sales
        </a>
        <a href="<?= base_url('payments') ?>" class="nav-link <?= (strpos(current_url(), '/payments') !== false) ? 'active' : '' ?>">
            <i class="bi bi-cash-stack"></i> Payments
        </a>
        <a href="<?= base_url('supplier-payments') ?>" class="nav-link <?= (strpos(current_url(), '/supplier-payments') !== false) ? 'active' : '' ?>">
            <i class="bi bi-wallet2"></i> Supplier Payments
        </a>
        <a href="<?= base_url('supplier-ledger') ?>" class="nav-link <?= (strpos(current_url(), '/supplier-ledger') !== false) ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Supplier Ledger
        </a>
        <a href="<?= base_url('service-receipts') ?>" class="nav-link <?= (strpos(current_url(), '/service-receipts') !== false) ? 'active' : '' ?>">
            <i class="bi bi-receipt-cutoff"></i> Service Received
        </a>
        <a href="<?= base_url('customer-ledger') ?>" class="nav-link <?= (strpos(current_url(), '/customer-ledger') !== false) ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Customer Ledger
        </a>
        <a href="<?= base_url('expenses') ?>" class="nav-link <?= (strpos(current_url(), '/expenses') !== false) ? 'active' : '' ?>">
            <i class="bi bi-credit-card"></i> Expense Register
        </a>
        <a href="<?= base_url('expense-reports') ?>" class="nav-link <?= (trim(uri_string(), '/') === 'expense-reports') ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Expense Ledger
        </a>
        <div class="nav-section">ACCOUNTS</div>
        <?php
        // Release 4.8.5A: Bank Accounts is one expandable menu (Accounts / Transactions / Statement).
        // Deposit, Withdrawal, Transfer and Manual Entry are voucher types inside Transactions, so their pages highlight Transactions.
        $bankPath         = trim(uri_string(), '/');
        $bankInStatement  = (bool) preg_match('#^bank-statement(/|$)#', $bankPath);
        $bankInTxns       = (bool) preg_match('#^(bank-accounts/(transactions|manual-entry|deposit|withdrawal|transfer)|bank-deposits|bank-withdrawals|bank-transfers)(/|$)#', $bankPath);
        $bankInAccounts   = ! $bankInStatement && ! $bankInTxns && (bool) preg_match('#^bank-accounts(/|$)#', $bankPath);
        $bankMenuOpen     = $bankInStatement || $bankInTxns || $bankInAccounts;
        ?>
        <a href="#bankMenu" class="nav-link nav-parent <?= $bankMenuOpen ? 'nav-parent-open' : 'collapsed' ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?= $bankMenuOpen ? 'true' : 'false' ?>" aria-controls="bankMenu">
            <i class="bi bi-bank"></i> Bank Accounts <i class="bi bi-chevron-down nav-caret"></i>
        </a>
        <div class="collapse nav-submenu <?= $bankMenuOpen ? 'show' : '' ?>" id="bankMenu">
            <a href="<?= base_url('bank-accounts') ?>" class="nav-link nav-sub <?= $bankInAccounts ? 'active' : '' ?>">
                <i class="bi bi-card-list"></i> Accounts
            </a>
            <a href="<?= base_url('bank-accounts/transactions') ?>" class="nav-link nav-sub <?= $bankInTxns ? 'active' : '' ?>">
                <i class="bi bi-list-ul"></i> Transactions
            </a>
            <a href="<?= base_url('bank-statement') ?>" class="nav-link nav-sub <?= $bankInStatement ? 'active' : '' ?>">
                <i class="bi bi-journal-text"></i> Statement
            </a>
        </div>
        <a href="<?= base_url('cash-book') ?>" class="nav-link <?= (trim(uri_string(), '/') === 'cash-book') ? 'active' : '' ?>">
            <i class="bi bi-wallet2"></i> Cash Book
        </a>
        <a href="<?= base_url('loans') ?>" class="nav-link <?= (strpos(current_url(), '/loans') !== false) ? 'active' : '' ?>">
            <i class="bi bi-cash-coin"></i> Loan Management
        </a>
        <div class="nav-section">REPORTS</div>
        <a href="<?= base_url('reports') ?>" class="nav-link <?= (strpos(current_url(), '/reports') !== false) ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line"></i> Reports
        </a>
        <a href="<?= base_url('service-reports/register') ?>" class="nav-link <?= (strpos(current_url(), '/service-reports/register') !== false) ? 'active' : '' ?>">
            <i class="bi bi-receipt-cutoff"></i> Service Register
        </a>
        <a href="<?= base_url('service-reports/outstanding') ?>" class="nav-link <?= (strpos(current_url(), '/service-reports/outstanding') !== false) ? 'active' : '' ?>">
            <i class="bi bi-exclamation-circle"></i> Service Outstanding
        </a>
        <a href="<?= base_url('service-reports/collections') ?>" class="nav-link <?= (strpos(current_url(), '/service-reports/collections') !== false) ? 'active' : '' ?>">
            <i class="bi bi-cash-stack"></i> Service Collections
        </a>
        <a href="<?= base_url('service-reports/customer-summary') ?>" class="nav-link <?= (strpos(current_url(), '/service-reports/customer-summary') !== false) ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Customer Outstanding
        </a>
        <?php $lrPath = trim(uri_string(), '/'); ?>
        <a href="<?= base_url('loan-reports') ?>" class="nav-link <?= ($lrPath === 'loan-reports') ? 'active' : '' ?>">
            <i class="bi bi-journal-check"></i> Loan Register
        </a>
        <a href="<?= base_url('loan-reports/emi-due') ?>" class="nav-link <?= ($lrPath === 'loan-reports/emi-due') ? 'active' : '' ?>">
            <i class="bi bi-calendar-event"></i> Loan EMI Due
        </a>
        <a href="<?= base_url('loan-reports/payments') ?>" class="nav-link <?= ($lrPath === 'loan-reports/payments') ? 'active' : '' ?>">
            <i class="bi bi-wallet2"></i> Loan Payments
        </a>
        <a href="<?= base_url('loan-reports/outstanding') ?>" class="nav-link <?= ($lrPath === 'loan-reports/outstanding') ? 'active' : '' ?>">
            <i class="bi bi-hourglass-split"></i> Loan Outstanding
        </a>
        <a href="<?= base_url('loan-reports/lender-summary') ?>" class="nav-link <?= ($lrPath === 'loan-reports/lender-summary') ? 'active' : '' ?>">
            <i class="bi bi-buildings"></i> Lender Summary
        </a>
        <?php $erPath = trim(uri_string(), '/'); ?>
        <a href="<?= base_url('expense-reports/category-summary') ?>" class="nav-link <?= ($erPath === 'expense-reports/category-summary') ? 'active' : '' ?>">
            <i class="bi bi-tags"></i> Expense Category Summary
        </a>
        <a href="<?= base_url('expense-reports/project-summary') ?>" class="nav-link <?= ($erPath === 'expense-reports/project-summary') ? 'active' : '' ?>">
            <i class="bi bi-kanban"></i> Expense Project Summary
        </a>
        <a href="<?= base_url('expense-reports/payment-summary') ?>" class="nav-link <?= ($erPath === 'expense-reports/payment-summary') ? 'active' : '' ?>">
            <i class="bi bi-credit-card-2-front"></i> Expense Payment Summary
        </a>
        <a href="<?= base_url('expense-reports/monthly-summary') ?>" class="nav-link <?= ($erPath === 'expense-reports/monthly-summary') ? 'active' : '' ?>">
            <i class="bi bi-calendar3"></i> Expense Monthly Summary
        </a>
        <a href="<?= base_url('reports/export-center') ?>" class="nav-link <?= (strpos(current_url(), '/reports/export-center') !== false) ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-arrow-down"></i> Print/Export Center
        </a>
        <a href="<?= base_url('audit-logs') ?>" class="nav-link <?= (strpos(current_url(), '/audit-logs') !== false) ? 'active' : '' ?>">
            <i class="bi bi-clock-history"></i> Audit Logs
        </a>
        <a href="<?= base_url('database-backup') ?>" class="nav-link <?= (strpos(current_url(), '/database-backup') !== false) ? 'active' : '' ?>">
            <i class="bi bi-hdd-network"></i> Backup &amp; Restore
        </a>
    </nav>
</div>

<!-- MAIN CONTENT -->
<div class="main-content" id="mainContent">
    <!-- TOPBAR -->
    <div class="topbar">
		<button id="sidebarToggle" class="btn btn-sm btn-light me-2">
			<i class="bi bi-list"></i>
		</button>
        <div class="topbar-title"><?= $title ?? '' ?></div>
        <div class="topbar-right">
            <span class="topbar-user"><i class="bi bi-person-circle"></i> <?= session('username') ?></span>
            <a href="<?= base_url('logout') ?>" class="btn-logout">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <!-- PAGE CONTENT -->
    <div class="page-content">

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= session()->getFlashdata('success') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= session()->getFlashdata('error') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?= $this->renderSection('content') ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
	
	<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
	
<?= $this->renderSection('scripts') ?>
	<script>
		$(document).ready(function () {
			$('select').not('.no-search').each(function () {
				if (!$(this).hasClass("select2-hidden-accessible")) {
					$(this).select2({
						width: '100%',
						placeholder: 'Search...',
						allowClear: true
					});
				}
			});
		});
	</script>
	<script>
	$('#sidebarToggle').on('click', function () {
		$('#sidebar').toggleClass('collapsed');
		$('#mainContent').toggleClass('expanded');
	});
	</script>
</body>
</html>
