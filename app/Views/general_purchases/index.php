<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
/**
 * Release 4.8.6G — General Purchase index, redesigned as a transaction list.
 * View layer only: every figure is read from what GeneralPurchases::index()
 * already supplies; nothing here posts, allocates or recalculates a balance.
 */
$gp_money  = static fn ($amount) => '₹' . number_format((float) $amount, 2);
$gp_status = static function (array $p): string {
	// The stored payment_status is authoritative; anything unexpected reads as PENDING.
	$s = strtoupper((string) ($p['payment_status'] ?? ''));
	return in_array($s, ['PAID', 'PARTIAL', 'PENDING'], true) ? $s : 'PENDING';
};

?>

<style>
	.dataTables_wrapper .dataTables_paginate { margin-top: 10px; text-align: right; }
	.dataTables_wrapper .dataTables_paginate .paginate_button {
		background: #f1f5f9 !important;
		border: 1px solid #e2e8f0 !important;
		color: #334155 !important;
		padding: 4px 10px !important;
		margin: 2px !important;
		border-radius: 6px !important;
		font-size: 12px !important;
	}
	.dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #2F7E8A !important; color: #fff !important; border: none !important; }
	.dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: #1e293b !important; color: #fff !important; }
	.dataTables_wrapper .dataTables_paginate .paginate_button.disabled { opacity: 0.5; cursor: not-allowed; }

	.gp-breadcrumb { margin-bottom: 8px; }
	.gp-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.gp-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.gp-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.gp-breadcrumb .breadcrumb-item.active { color: #64748b; }

	@media (max-width: 479.98px) {
					.gp-page-head .btn-save, .gp-page-head .btn-cancel { white-space: normal; }
	}

	/* ---------- one-row filter bar ---------- */
	.gp-filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
	.gp-filters .gp-field { display: flex; flex-direction: column; min-width: 0; position: relative; }
	.gp-f-supplier { flex: 1 1 calc(28% - 8px); max-width: calc(28% - 8px); }
	.gp-f-from, .gp-f-to { flex: 1 1 calc(17% - 8px); max-width: calc(17% - 8px); }
	.gp-f-status { flex: 1 1 calc(15% - 8px); max-width: calc(15% - 8px); }
	.gp-f-search { flex: 1 1 calc(23% - 8px); max-width: calc(23% - 8px); }
	.gp-filters .form-label { font-size: .72rem; margin-bottom: 3px; color: #64748b; }
	.gp-filters .form-control { padding: 6px 10px; font-size: .82rem; height: 34px; }
	.gp-filters .form-control:focus { border-color: #2F7E8A; box-shadow: 0 0 0 2px rgba(47,126,138,0.15); }
	.gp-f-search .gp-search-icon { position: absolute; left: 10px; bottom: 9px; color: #94a3b8; font-size: 12px; pointer-events: none; }
	.gp-f-search .form-control { padding-left: 28px; }
	.gp-page-card .card-custom-body { padding-top: 10px; padding-bottom: 10px; }
	.gp-filter-card { position: sticky; top: 52px; z-index: 20; }

	/* ---------- transaction table ---------- */
	#gpTable.table-custom th,
	#gpTable.table-custom td { padding: 4px 11px; font-size: .78rem; vertical-align: middle; }
	#gpTable .gp-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
	.gp-no { font-weight: 600; color: #1e293b; white-space: nowrap; }
	.gp-supplier-name { font-weight: 600; color: #1e293b; }
	.gp-supplier-sub { font-size: .7rem; color: #94a3b8; margin-top: 1px; }
	.gp-out-due  { color: #c2410c; font-weight: 700; }
	.gp-out-paid { color: #15803d; font-weight: 700; }

	/* payment status badge: PAID / PARTIAL / PENDING only */
	.gp-badge { display: inline-block; padding: 3px 12px; font-size: .68rem; font-weight: 800; border: 1px solid transparent; border-radius: 20px; letter-spacing: .05em; white-space: nowrap; }
	.gp-status-paid    { background: #bbf7d0; color: #14532d; border-color: #4ade80; }
	.gp-status-partial { background: #fde68a; color: #78350f; border-color: #f59e0b; }
	.gp-status-pending { background: #e2e8f0; color: #1e293b; border-color: #94a3b8; }

	/* row indicator: left border only, no fills */
	#gpTable tbody tr.gp-row td:first-child { border-left: 3px solid #cbd5e1; }
	#gpTable tbody tr.gp-row-paid    td:first-child { border-left-color: #86efac; }
	#gpTable tbody tr.gp-row-partial td:first-child { border-left-color: #fcd34d; }
	#gpTable tbody tr.gp-row-pending td:first-child { border-left-color: #cbd5e1; }


	.gp-empty { padding: 26px 10px; text-align: center; color: #94a3b8; }
	.gp-empty i { font-size: 1.8rem; display: block; margin-bottom: 6px; color: #cbd5e1; }
	#gpTable td.dataTables_empty { text-align: center; color: #94a3b8; }

	@media (max-width: 1199.98px) {
		.gp-f-supplier { flex: 1 1 100%; max-width: 100%; }
		.gp-f-from, .gp-f-to, .gp-f-status, .gp-f-search { flex: 1 1 calc(25% - 8px); max-width: calc(25% - 8px); }
	}
	@media (max-width: 1279.98px) {
		#gpTable.table-custom th, #gpTable.table-custom td { padding: 7px 4px; font-size: .74rem; }
		#gpTable.table-custom thead th { padding-right: 16px; white-space: normal; }
		#gpTable .gp-badge { padding: 3px 8px; letter-spacing: .02em; }
	}
	@media (max-width: 767.98px) {
		.gp-filters .gp-field, .gp-f-supplier, .gp-f-from, .gp-f-to, .gp-f-status, .gp-f-search { flex: 1 1 100%; max-width: 100%; }
	}

	/* tablet / mobile (the fixed sidebar leaves little room): one card per purchase, no horizontal scroll */
	@media (max-width: 991.98px) {
		.gp-table-wrap { overflow-x: visible !important; }
		#gpTable, #gpTable tbody, #gpTable tr, #gpTable td { display: block; width: 100%; box-sizing: border-box; }
		#gpTable thead { display: none; }
		#gpTable tbody tr { border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 10px; background: #fff !important; padding: 4px 0; }
		#gpTable tbody tr.gp-row-paid    { border-left: 3px solid #86efac; }
		#gpTable tbody tr.gp-row-partial { border-left: 3px solid #fcd34d; }
		#gpTable tbody tr.gp-row-pending { border-left: 3px solid #cbd5e1; }
		#gpTable tbody tr td:first-child { border-left: none !important; }
		#gpTable tbody tr td {
			display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start;
			gap: 4px 10px; text-align: right; border: none; border-bottom: 1px dashed #f1f5f9;
		}
		#gpTable tbody tr td > * { min-width: 0; }
		#gpTable tbody tr td > .d-inline-flex { flex-wrap: wrap; justify-content: flex-end; }
		#gpTable tbody tr td:last-child { border-bottom: none; }
		#gpTable tbody tr td::before {
			content: attr(data-label); flex: 0 0 36%; text-align: left; font-size: .66rem;
			text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; font-weight: 600;
		}
		#gpTable tbody tr td.gp-num { text-align: right; }
			#gpTable td.dataTables_empty::before { content: none; }
		#gpTable td.dataTables_empty { display: block; text-align: center; }
	}
</style>

<nav aria-label="breadcrumb" class="gp-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('purchases') ?>">Purchases</a></li>
        <li class="breadcrumb-item active" aria-current="page">General Purchase</li>
    </ol>
</nav>

<div class="page-title gp-page-head d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-boxes me-2"></i>General Purchase</span>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= base_url('general-purchases/create') ?>" class="btn-save"><i class="bi bi-plus-lg"></i> New General Purchase</a>
        <a href="javascript:void(0)" class="btn-cancel" title="Export (coming soon)" onclick="alert('Export will be available in a future release.')"><i class="bi bi-file-earmark-excel"></i> Export</a>
    </div>
</div>

<!-- FILTERS (client-side) -->
<div class="card-custom mb-3 gp-page-card gp-filter-card">
    <div class="card-custom-body">
        <div class="gp-filters">
            <div class="gp-field gp-f-supplier">
                <label class="form-label" for="filterSupplier">Supplier</label>
                <select id="filterSupplier" class="form-control">
                    <option value="">All Suppliers</option>
                    <?php foreach ($suppliers as $s): ?>
                    <option value="<?= (int) $s['id'] ?>"><?= esc($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="gp-field gp-f-from">
                <label class="form-label" for="filterFromDate">From Date</label>
                <input type="date" id="filterFromDate" class="form-control">
            </div>
            <div class="gp-field gp-f-to">
                <label class="form-label" for="filterToDate">To Date</label>
                <input type="date" id="filterToDate" class="form-control">
            </div>
            <div class="gp-field gp-f-status">
                <label class="form-label" for="filterPaymentStatus">Payment Status</label>
                <select id="filterPaymentStatus" class="form-control">
                    <option value="">All</option>
                    <option value="PENDING">Pending</option>
                    <option value="PARTIAL">Partial</option>
                    <option value="PAID">Paid</option>
                </select>
            </div>
            <div class="gp-field gp-f-search">
                <label class="form-label" for="customSearch">Search</label>
                <i class="bi bi-search gp-search-icon"></i>
                <input type="text" id="customSearch" class="form-control" placeholder="GP no, bill no, supplier…">
            </div>
        </div>
    </div>
</div>

<!-- PURCHASE TABLE -->
<div class="card-custom">
    <div class="table-responsive gp-table-wrap">
        <table id="gpTable" class="table-custom">
            <thead>
                <tr>
                    <th>GP No</th>
                    <th>Purchase Date</th>
                    <th>Supplier</th>
                    <th>Bill No</th>
                    <th class="gp-num">Grand Total</th>
                    <th class="gp-num">Paid Amount</th>
                    <th class="gp-num">Outstanding</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($purchases as $p): ?>
                <?php
                $status      = $gp_status($p);
                $outstanding = max(0.0, (float) $p['outstanding_amount']);
                $isDue       = $outstanding > 0.004;
                $paidTotal   = max(0.0, (float) $p['grand_total'] - $outstanding);
                ?>
                <tr class="gp-row gp-row-<?= strtolower($status) ?>"
                    data-supplier="<?= (int) $p['supplier_id'] ?>"
                    data-status="<?= $status ?>"
                    data-date="<?= esc($p['purchase_date'], 'attr') ?>">

                    <td data-label="GP No">
                        <span class="gp-no"><?= esc($p['purchase_no']) ?></span>
                    </td>

                    <td data-label="Purchase Date" data-order="<?= esc($p['purchase_date'], 'attr') ?>"><?= esc($p['purchase_date']) ?></td>

                    <td data-label="Supplier">
                        <div class="gp-supplier-name"><?= esc($p['supplier_name'] ?: '-') ?></div>
                        <div class="gp-supplier-sub <?= $isDue ? 'gp-out-due' : 'gp-out-paid' ?>" style="font-weight:500"><?= $isDue ? 'Outstanding ' . $gp_money($outstanding) : 'Paid in Full' ?></div>
                    </td>

                    <td data-label="Bill No"><?= esc($p['bill_no'] ?: '-') ?></td>

                    <td data-label="Grand Total" class="gp-num" data-order="<?= (float) $p['grand_total'] ?>"><?= number_format((float) $p['grand_total'], 2) ?></td>

                    <td data-label="Paid Amount" class="gp-num" data-order="<?= $paidTotal ?>"><?= number_format($paidTotal, 2) ?></td>

                    <td data-label="Outstanding" class="gp-num <?= $isDue ? 'gp-out-due' : 'gp-out-paid' ?>" data-order="<?= $outstanding ?>"><?= $isDue ? number_format($outstanding, 2) : '₹0.00' ?></td>

                    <td data-label="Status"><span class="gp-badge gp-status-<?= strtolower($status) ?>"><?= $status ?></span></td>

                    <td data-label="Actions">
                        <span class="d-inline-flex gap-1">
                            <a href="<?= base_url('general-purchases/view/' . $p['id']) ?>" class="btn-view" title="View"><i class="bi bi-eye"></i></a>
                            <a href="<?= base_url('general-purchases/edit/' . $p['id']) ?>" class="btn-edit" title="Edit"><i class="bi bi-pencil"></i></a>
                            <a href="javascript:void(0)" class="btn-delete" title="Delete" onclick="confirmDeleteGp(<?= (int) $p['id'] ?>)"><i class="bi bi-trash"></i></a>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- delete is a POST route, so it needs a real form submit rather than a GET link -->
<form id="deleteGpForm" method="POST" style="display:none"></form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	function confirmDeleteGp(id) {
		if (!confirm('Delete this general purchase? This cannot be undone.')) return;
		var form = document.getElementById('deleteGpForm');
		form.action = "<?= base_url('general-purchases/delete/') ?>" + id;
		form.submit();
	}

	$(document).ready(function () {
		var table = $('#gpTable').DataTable({
			paging: true,
			searching: true,
			lengthChange: false,
			info: false,
			ordering: true,
			order: [],
			pageLength: 10,
			dom: 'tp',
			columnDefs: [
				{ orderable: false, targets: [7, 8] }
			],
			language: {
				paginate: {
					previous: '<i class="bi bi-chevron-left"></i>',
					next: '<i class="bi bi-chevron-right"></i>'
				},
				emptyTable:  '<div class="gp-empty"><i class="bi bi-inbox"></i>No general purchases found.</div>',
				zeroRecords: '<div class="gp-empty"><i class="bi bi-funnel"></i>No purchases match your filters.</div>'
			}
		});

		$('#customSearch').on('keyup', function () {
			table.search(this.value).draw();
		});
		$('#filterSupplier, #filterPaymentStatus, #filterFromDate, #filterToDate').on('change', function () {
			table.draw();
		});

		// Supplier / status / date range are matched against the row's own data
		// attributes, so the composed cell markup never affects them.
		$.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
			if (settings.nTable.id !== 'gpTable') return true;

			var node = settings.aoData[dataIndex] && settings.aoData[dataIndex].nTr;
			if (!node) return true;

			var supplier = $('#filterSupplier').val();
			var status   = $('#filterPaymentStatus').val();
			var from     = $('#filterFromDate').val();
			var to       = $('#filterToDate').val();
			var rowDate  = node.getAttribute('data-date') || '';

			if (supplier && node.getAttribute('data-supplier') !== supplier) return false;
			if (status && node.getAttribute('data-status') !== status) return false;
			if (from && rowDate < from) return false;
			if (to && rowDate > to) return false;
			return true;
		});

	});
</script>
<?= $this->endSection() ?>
