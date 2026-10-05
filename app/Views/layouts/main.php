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

		/* Release 4.9.0F: report pages use a compact summary strip, not dashboard-size KPI cards */
		.rpt-page .row.g-3 { --bs-gutter-x: .6rem; --bs-gutter-y: .6rem; }
		.rpt-page .kpi-card { padding: 7px 12px; gap: 10px; border-radius: 6px; }
		.rpt-page .kpi-icon { font-size: 1rem; }
		.rpt-page .kpi-value { font-size: .92rem; line-height: 1.25; }
		.rpt-page .kpi-label { font-size: .68rem; margin-top: 0; }
		.rpt-page .rpt-desc { font-size: .76rem; color: var(--text-muted); margin: 0 0 8px; }
		.rpt-page .rpt-filter .rpt-field { display: flex; flex-direction: column; gap: 2px; }
		.rpt-page .rpt-filter .rpt-field label { font-size: .68rem; font-weight: 600; color: var(--text-muted); margin: 0; }
		.rpt-page .rpt-filter .rpt-field input { width: 150px; height: var(--btn-height); }
		@media (max-width: 575.98px) {
			.rpt-page .rpt-filter .rpt-field { flex: 1 1 40%; }
			.rpt-page .rpt-filter .rpt-field input { width: 100%; }
		}
	</style>
</head>
<?php
// Release 4.9.0F: every analytical report page highlights the single Reports entry
// (Export Center, the Expense Ledger at bare expense-reports and the stock movement
// log have their own sidebar entries).
$rptPath   = trim(uri_string(), '/');
$rptActive = (bool) preg_match('#^(reports(/(?!export-center$|ledger$).*)?|expense-reports/.+|service-reports/.+|loan-reports(/.*)?)$#', $rptPath);
// Release 4.9.0CJ: Monthly Statement and Cash Book are reached only through Reports -> Financial,
// so their pages keep the Reports sidebar item highlighted. Kept separate from $rptActive, which also
// drives the rpt-page body class (that class must not apply to these pages).
$rptNavActive = $rptActive || (bool) preg_match('#^(monthly-statement|cash-book|warehouse|reports/ledger)(/|$)#', $rptPath);
?>
<body class="<?= $rptActive ? 'rpt-page' : '' ?>">

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
        <a href="<?= base_url('purchases') ?>" class="nav-link <?= (! $rptActive && strpos(current_url(), '/purchases') !== false) ? 'active' : '' ?>">
            <i class="bi bi-cart-plus"></i> Purchases
        </a>
        <a href="<?= base_url('general-purchases') ?>" class="nav-link <?= (strpos(current_url(), '/general-purchases') !== false) ? 'active' : '' ?>">
            <i class="bi bi-boxes"></i> General Purchase
        </a>
		<a href="<?= base_url('stock-entry') ?>" class="nav-link <?= (strpos(current_url(), '/stock-entry') !== false) ? 'active' : '' ?>">
			<i class="bi bi-box-seam"></i> Stock Entry
		</a>
        <a href="<?= base_url('sales') ?>" class="nav-link <?= (! $rptActive && strpos(current_url(), '/sales') !== false) ? 'active' : '' ?>">
            <i class="bi bi-receipt"></i> Sales
        </a>
        <a href="<?= base_url('payments') ?>" class="nav-link <?= (! $rptActive && strpos(current_url(), '/payments') !== false) ? 'active' : '' ?>">
            <i class="bi bi-cash-stack"></i> Payments
        </a>
        <a href="<?= base_url('supplier-payments') ?>" class="nav-link <?= (strpos(current_url(), '/supplier-payments') !== false) ? 'active' : '' ?>">
            <i class="bi bi-wallet2"></i> Supplier Payments
        </a>
        <a href="<?= base_url('service-receipts') ?>" class="nav-link <?= (strpos(current_url(), '/service-receipts') !== false) ? 'active' : '' ?>">
            <i class="bi bi-receipt-cutoff"></i> Service Received
        </a>
        <a href="<?= base_url('expenses') ?>" class="nav-link <?= (strpos(current_url(), '/expenses') !== false) ? 'active' : '' ?>">
            <i class="bi bi-credit-card"></i> Expense Register
        </a>
        <div class="nav-section">LEDGERS</div>
        <a href="<?= base_url('customer-ledger') ?>" class="nav-link <?= (strpos(current_url(), '/customer-ledger') !== false) ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Customer Ledger
        </a>
        <a href="<?= base_url('supplier-ledger') ?>" class="nav-link <?= (strpos(current_url(), '/supplier-ledger') !== false) ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Supplier Ledger
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
        <a href="<?= base_url('loans') ?>" class="nav-link <?= (strpos(current_url(), '/loans') !== false) ? 'active' : '' ?>">
            <i class="bi bi-cash-coin"></i> Loan Management
        </a>
        <div class="nav-section">REPORTS</div>
        <a href="<?= base_url('reports') ?>" class="nav-link <?= $rptNavActive ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line"></i> Reports
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
        <?php
        // Release 4.9.0DG: the page's breadcrumb lives in the global top header, once. Render the page first, lift its own
        // breadcrumb (or the optional 'topbar_breadcrumb' section) out of the content, else derive one from the route.
        helper('breadcrumb');
        ob_start();
        $pageRet  = $this->renderSection('content');
        $pageHtml = ob_get_clean();
        if ($pageHtml === '' && is_string($pageRet)) { $pageHtml = $pageRet; }
        [$topbarCrumb, $pageHtml] = topbar_breadcrumb_split($pageHtml);
        ob_start();
        $crumbRet = $this->renderSection('topbar_breadcrumb');
        $crumbSec = trim((string) ob_get_clean());
        if ($crumbSec === '' && is_string($crumbRet)) { $crumbSec = trim($crumbRet); }
        if ($crumbSec !== '') { [$crumbSec] = topbar_breadcrumb_split($crumbSec); $topbarCrumb = $crumbSec !== '' ? $crumbSec : $topbarCrumb; }
        if ($topbarCrumb === '') { $topbarCrumb = topbar_breadcrumb_fallback(uri_string()); }
        ?>
        <?php if ($topbarCrumb !== ''): ?>
        <div class="topbar-title topbar-crumb"><div class="topbar-breadcrumb"><?= $topbarCrumb ?></div></div>
        <?php else: ?>
        <div class="topbar-title"><?= $title ?? '' ?></div>
        <?php endif; ?>
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

        <?= $pageHtml ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* Release 4.9.0DI: the one-row filter bars scroll internally (overflow-x:auto forces overflow-y to clip), which hid
   the Export menus that live inside them. Bootstrap menus use fixed positioning so no overflow ancestor can clip them. */
if (window.bootstrap && bootstrap.Dropdown) {
	bootstrap.Dropdown.Default.popperConfig = function (d) { return Object.assign({}, d, { strategy: 'fixed' }); };
}
</script>
<script src="<?= base_url('assets/js/cash-opening-guard.js') ?>"></script>
<script src="<?= base_url('assets/js/auto-filter.js') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
	
	<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
/* Release 4.9.0DK: display-only S.No. for DataTables. A table opts in by giving its S.No. header the .sno-col class
   (and an empty <td class="sno-col"> per row). The cells are renumbered 1..n on every draw in the order currently
   displayed, across all pages, so sorting/filtering/paging never leave stale numbers. Nothing is stored. */
if (window.jQuery && $.fn.dataTable) {
	$(document).on('preInit.dt', function (e, settings) {
		settings.aoColumns.forEach(function (c) {
			if ($(c.nTh).hasClass('sno-col')) { c.bSortable = false; c.bSearchable = false; }
		});
	});
	$(document).on('init.dt', function (e, settings) {
		settings.aoColumns.forEach(function (c) {
			if ($(c.nTh).hasClass('sno-col')) { $(c.nTh).off('.DT').removeClass('sorting sorting_asc sorting_desc').addClass('sorting_disabled').removeAttr('tabindex aria-controls'); }
		});
	});
	$(document).on('draw.dt', function (e, settings) {
		var api = new $.fn.dataTable.Api(settings);
		settings.aoColumns.forEach(function (c, i) {
			if (!$(c.nTh).hasClass('sno-col')) return;
			api.column(i, { search: 'applied', order: 'applied', page: 'all' }).nodes().each(function (cell, n) { cell.textContent = n + 1; });
		});
	});
}
</script>

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
