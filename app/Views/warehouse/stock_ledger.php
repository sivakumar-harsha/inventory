<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
	.dataTables_wrapper .dataTables_paginate {
		margin-top: 10px;
		text-align: right;
	}
	.dataTables_wrapper .dataTables_paginate .paginate_button {
		background: #f1f5f9 !important;
		border: 1px solid #e2e8f0 !important;
		color: #334155 !important;
		padding: 4px 10px !important;
		margin: 2px !important;
		border-radius: 6px !important;
		font-size: 12px !important;
	}
	.dataTables_wrapper .dataTables_paginate .paginate_button.current {
		background: #2F7E8A !important;
		color: #fff !important;
		border: none !important;
	}
	.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
		background: #1e293b !important;
		color: #fff !important;
	}

	.filter-toolbar .form-section { margin-bottom: 0; }
	.filter-toolbar .form-label { font-size: .72rem; margin-bottom: 3px; }
	.filter-toolbar .form-control { padding: 6px 10px; font-size: .82rem; height: auto; }

	#ledgerTable.table-custom th,
	#ledgerTable.table-custom td { padding: 7px 10px; font-size: .78rem; }

	.wh-breadcrumb { margin-bottom: 8px; }
	.wh-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.wh-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.wh-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.wh-breadcrumb .breadcrumb-item.active { color: #64748b; }

	.badge-txn { display:inline-block; padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:600; }
	.badge-txn.badge-in  { background:#dcfce7; color:#166534; }
	.badge-txn.badge-out { background:#fee2e2; color:#991b1b; }

	.balance-neg { color: #dc2626; font-weight: 600; }
</style>

<nav aria-label="breadcrumb" class="wh-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('warehouse/stock-summary') ?>">Warehouse Stock Summary</a></li>
        <li class="breadcrumb-item active" aria-current="page">Warehouse Stock Ledger</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-journal-text me-2"></i>Warehouse Stock Ledger</span>
    <a href="<?= base_url('warehouse/stock-summary') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back to Summary</a>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-list-ol"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_rows) ?></div>
                <div class="kpi-label">Total Transactions</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-arrow-down-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_in, 2) ?></div>
                <div class="kpi-label">Total Qty In</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-arrow-up-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_out, 2) ?></div>
                <div class="kpi-label">Total Qty Out</div>
            </div>
        </div>
    </div>
</div>

<!-- FILTER TOOLBAR (client-side, same DataTables column-search pattern as General Purchase list) -->
<div class="card-custom mb-3">
    <div class="card-custom-header">Filters</div>
    <div class="card-custom-body">
        <div class="row g-2 align-items-end filter-toolbar">
            <div class="col-6 col-md-3">
                <div class="form-section">
                    <label class="form-label">Product</label>
                    <select id="filterProduct" class="form-control">
                        <option value="">All Products</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= esc($p['name']) ?>"><?= esc($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">Type</label>
                    <select id="filterType" class="form-control no-search">
                        <option value="">All</option>
                        <option value="IN">IN</option>
                        <option value="OUT">OUT</option>
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">From Date</label>
                    <input type="date" id="filterFromDate" class="form-control">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">To Date</label>
                    <input type="date" id="filterToDate" class="form-control">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="form-section">
                    <label class="form-label">Reference Type</label>
                    <select id="filterRefType" class="form-control no-search">
                        <option value="">All</option>
                        <option value="GENERAL_PURCHASE">General Purchase</option>
                        <option value="PURCHASE">Purchase</option>
                        <option value="SALE">Sale</option>
                        <option value="MANUAL">Manual</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card-custom">
    <div class="table-responsive">
        <table id="ledgerTable" class="table-custom">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Type</th>
                    <th style="text-align:right">Quantity</th>
                    <th style="text-align:right">Balance</th>
                    <th>Reference</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ledger as $row): ?>
                <tr>
                    <td><?= esc($row['transaction_date']) ?></td>
                    <td><?= esc($row['product_name'] ?: '-') ?></td>
                    <td><span class="badge-txn badge-<?= strtolower($row['transaction_type']) ?>"><?= esc($row['transaction_type']) ?></span></td>
                    <td style="text-align:right"><?= number_format($row['quantity'], 2) ?> <?= esc($row['unit'] ?: '') ?></td>
                    <td style="text-align:right" class="<?= $row['balance'] < 0 ? 'balance-neg' : '' ?>"><?= number_format($row['balance'], 2) ?></td>
                    <td>
                        <?php if ($row['reference_type'] === 'GENERAL_PURCHASE'): ?>
                            <a href="<?= base_url('general-purchases/view/' . $row['reference_id']) ?>"><?= esc($row['reference_type']) ?> #<?= (int) $row['reference_id'] ?></a>
                        <?php elseif ($row['reference_type'] === 'PURCHASE'): ?>
                            <a href="<?= base_url('purchases/view/' . $row['reference_id']) ?>"><?= esc($row['reference_type']) ?> #<?= (int) $row['reference_id'] ?></a>
                        <?php else: ?>
                            <?= esc($row['reference_type'] ?: '-') ?><?= $row['reference_id'] ? ' #' . (int) $row['reference_id'] : '' ?>
                        <?php endif; ?>
                    </td>
                    <td><?= esc($row['notes'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	$(document).ready(function () {
		var table = $('#ledgerTable').DataTable({
			paging: true,
			searching: true,
			lengthChange: false,
			info: false,
			ordering: true,
			pageLength: 15,
			dom: 'tp',
			language: {
				paginate: {
					previous: '<i class="bi bi-chevron-left"></i>',
					next: '<i class="bi bi-chevron-right"></i>'
				},
				emptyTable: 'No warehouse ledger entries found.'
			}
		});

		$('#filterProduct').on('change', function () {
			table.column(1).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw();
		});
		$('#filterType').on('change', function () {
			table.column(2).search(this.value, false, false).draw();
		});
		$('#filterRefType').on('change', function () {
			table.column(5).search(this.value, false, false).draw();
		});

		$.fn.dataTable.ext.search.push(function (settings, data) {
			if (settings.nTable.id !== 'ledgerTable') return true;

			var from = $('#filterFromDate').val();
			var to   = $('#filterToDate').val();
			var rowDate = data[0];

			if (from && rowDate < from) return false;
			if (to && rowDate > to) return false;
			return true;
		});
		$('#filterFromDate, #filterToDate').on('change', function () {
			table.draw();
		});
	});
</script>
<?= $this->endSection() ?>
