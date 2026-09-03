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
		<a href="<?= base_url('stock-entry') ?>" class="nav-link <?= (strpos(current_url(), '/stock-entry') !== false) ? 'active' : '' ?>">
			<i class="bi bi-box-seam"></i> Stock Entry
		</a>
        <a href="<?= base_url('sales') ?>" class="nav-link <?= (strpos(current_url(), '/sales') !== false) ? 'active' : '' ?>">
            <i class="bi bi-receipt"></i> Sales
        </a>
        <a href="<?= base_url('payments') ?>" class="nav-link <?= (strpos(current_url(), '/payments') !== false) ? 'active' : '' ?>">
            <i class="bi bi-cash-stack"></i> Payments
        </a>
        <a href="<?= base_url('expenses') ?>" class="nav-link <?= (strpos(current_url(), '/expenses') !== false) ? 'active' : '' ?>">
            <i class="bi bi-credit-card"></i> Expenses
        </a>
        <div class="nav-section">FINANCE</div>
        <a href="<?= base_url('project-cash-receipts') ?>" class="nav-link <?= (strpos(current_url(), '/project-cash-receipts') !== false) ? 'active' : '' ?>">
            <i class="bi bi-piggy-bank"></i> Project Cash Receipts
        </a>
        <div class="nav-section">REPORTS</div>
        <a href="<?= base_url('reports') ?>" class="nav-link <?= (strpos(current_url(), '/reports') !== false) ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line"></i> Reports
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
