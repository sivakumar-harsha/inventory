<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
/**
 * Release 4.8.6F — Supplier Payments Index, redesigned as a pure accounting
 * transaction list. View layer only: every figure below is read from what
 * SupplierPayments::index() derived read-only; nothing here posts, allocates,
 * settles advance or touches a ledger.
 */
// Release 4.8.6F-1: exactly three payment-type badges, no generic "Advance".
$sp_status_badge = ['PAID' => 'PAID', 'PARTIAL' => 'PARTIAL', 'ADVANCE' => 'ADVANCE'];
// Payment Type column: one compact line — business meaning plus the payment method.
$sp_method = static function ($m): string {
    $label = ucwords(strtolower(str_replace('_', ' ', trim((string) $m))));
    return $label === 'Bank Transfer' ? 'Bank' : $label;
};
$sp_type_line = static function (array $v) use ($sp_method): string {
    $method = $sp_method($v['payment_method']);
    if ($v['payment_type'] === 'ADVANCE') {
        return 'Advance Payment';
    }
    if ($v['payment_type'] === 'MIXED') {
        return 'Mixed • ' . ($method !== '' ? $method . ' + ' : '') . 'Advance';
    }
    return 'Bill Payment' . ($method !== '' ? ' • ' . $method : '');
};
$sp_money = static fn ($amount) => '₹' . number_format((float) $amount, 2);

// Supplier dropdown: one entry per supplier that actually has a voucher.
$sp_suppliers = [];
foreach ($vouchers as $sp_v) {
    if ((int) $sp_v['supplier_id'] > 0 && $sp_v['supplier_name']) {
        $sp_suppliers[(int) $sp_v['supplier_id']] = $sp_v['supplier_name'];
    }
}
asort($sp_suppliers);
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

	.sp-breadcrumb { margin-bottom: 8px; }
	.sp-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.sp-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.sp-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.sp-breadcrumb .breadcrumb-item.active { color: #64748b; }

	@media (max-width: 479.98px) {
		.sp-page-head .btn-save, .sp-page-head .btn-cancel { white-space: normal; }
	}

	/* ---------- one-row filter bar (same metrics as General Purchase) ---------- */
	.sp-filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; }
	.sp-filters .sp-field { display: flex; flex-direction: column; min-width: 0; position: relative; }
	.sp-f-supplier { flex: 1 1 calc(24% - 7px); max-width: calc(24% - 7px); }
	.sp-f-from, .sp-f-to { flex: 1 1 calc(14% - 7px); max-width: calc(14% - 7px); }
	.sp-f-type { flex: 1 1 calc(16% - 7px); max-width: calc(16% - 7px); }
	.sp-f-status { flex: 1 1 calc(14% - 7px); max-width: calc(14% - 7px); }
	.sp-f-search { flex: 1 1 calc(18% - 7px); max-width: calc(18% - 7px); }
	.sp-filters .form-label { font-size: .72rem; margin-bottom: 3px; color: #64748b; }
	.sp-filters .form-control { padding: 6px 10px; font-size: .82rem; height: 34px; }
	.sp-filters .form-control:focus { border-color: #2F7E8A; box-shadow: 0 0 0 2px rgba(47,126,138,0.15); }
	.sp-f-search .sp-search-icon { position: absolute; left: 10px; bottom: 9px; color: #94a3b8; font-size: 12px; pointer-events: none; }
	.sp-f-search .form-control { padding-left: 28px; }
	.sp-page-card .card-custom-body { padding-top: 10px; padding-bottom: 10px; }
	.sp-filter-card { position: sticky; top: 52px; z-index: 20; }

	/* ---------- transaction table ---------- */
	#spTable.table-custom th,
	#spTable.table-custom td { padding: 4px 10px; font-size: .78rem; vertical-align: middle; }
	#spTable .sp-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
	.sp-voucher-no { font-weight: 600; color: #1e293b; white-space: nowrap; }
	.sp-supplier-name { font-weight: 600; color: #1e293b; }
	.sp-supplier-sub { font-size: .7rem; color: #94a3b8; margin-top: 1px; font-weight: 500; }
	.sp-out-due  { color: #c2410c; font-weight: 700; }
	.sp-out-paid { color: #15803d; font-weight: 700; }
	#spTable td.sp-amount { color: #15803d; font-weight: 700; }

	/* status badge: same size as General Purchase */
	.sp-badge { display: inline-block; padding: 3px 12px; font-size: .68rem; font-weight: 800; border: 1px solid transparent; border-radius: 20px; letter-spacing: .05em; white-space: nowrap; }
	.sp-status-paid      { background: #bbf7d0; color: #14532d; border-color: #4ade80; }
	.sp-status-partial   { background: #fde68a; color: #78350f; border-color: #f59e0b; }
	.sp-status-advance   { background: #bfdbfe; color: #1e3a8a; border-color: #60a5fa; }
	/* payment type: one muted text line, no badge */
	.sp-type-line { display: block; max-width: 190px; font-size: .72rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
	.sp-out-adv { color: #3b82f6; font-weight: 500; }
	#spTable td.sp-amount.sp-amt-adv  { color: #2563eb; }
	#spTable td.sp-amount.sp-amt-zero { color: #94a3b8; }

	/* row indicator: left border only, no fills */
	#spTable tbody tr.sp-row td:first-child { border-left: 3px solid #cbd5e1; }
	#spTable tbody tr.sp-row-paid      td:first-child { border-left-color: #22c55e; }
	#spTable tbody tr.sp-row-partial   td:first-child { border-left-color: #f59e0b; }
	#spTable tbody tr.sp-row-advance   td:first-child { border-left-color: #3b82f6; }

	.sp-empty { padding: 26px 10px; text-align: center; color: #94a3b8; }
	.sp-empty i { font-size: 1.8rem; display: block; margin-bottom: 6px; color: #cbd5e1; }
	#spTable td.dataTables_empty { text-align: center; color: #94a3b8; }

	@media (max-width: 1199.98px) {
		.sp-f-supplier { flex: 1 1 100%; max-width: 100%; }
		.sp-f-from, .sp-f-to, .sp-f-type, .sp-f-status, .sp-f-search { flex: 1 1 calc(20% - 7px); max-width: calc(20% - 7px); }
	}
	@media (max-width: 1279.98px) {
		#spTable.table-custom th, #spTable.table-custom td { padding: 7px 4px; font-size: .74rem; }
		#spTable.table-custom thead th { padding-right: 16px; white-space: normal; }
		#spTable .sp-badge { padding: 3px 8px; letter-spacing: .02em; }
		#spTable .sp-type-line { max-width: 120px; }
	}
	@media (max-width: 767.98px) {
		.sp-filters .sp-field, .sp-f-supplier, .sp-f-from, .sp-f-to, .sp-f-type, .sp-f-status, .sp-f-search { flex: 1 1 100%; max-width: 100%; }
	}

	/* tablet / mobile: one card per voucher, no horizontal scroll */
	@media (max-width: 991.98px) {
		.sp-table-wrap { overflow-x: visible !important; }
		#spTable, #spTable tbody, #spTable td { display: block; width: 100%; box-sizing: border-box; }
		#spTable tbody tr { display: flex; flex-direction: column; width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 10px; background: #fff !important; padding: 4px 0; }
		#spTable thead { display: none; }
		#spTable tbody tr.sp-row-paid      { border-left: 3px solid #22c55e; }
		#spTable tbody tr.sp-row-partial   { border-left: 3px solid #f59e0b; }
		#spTable tbody tr.sp-row-advance   { border-left: 3px solid #3b82f6; }
		#spTable tbody tr td:first-child { border-left: none !important; }
		/* Supplier first, then voucher / date / type / amount / status / actions */
		#spTable tbody tr td:nth-child(3) { order: -1; }
		#spTable tbody tr td {
			display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start;
			gap: 4px 10px; text-align: right; border: none; border-bottom: 1px dashed #f1f5f9;
		}
		#spTable tbody tr td > * { min-width: 0; }
		#spTable tbody tr td > .d-inline-flex { flex-wrap: wrap; justify-content: flex-end; }
		#spTable tbody tr td:last-child { border-bottom: none; }
		#spTable tbody tr td::before {
			content: attr(data-label); flex: 0 0 36%; text-align: left; font-size: .66rem;
			text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; font-weight: 600;
		}
		#spTable tbody tr td.sp-num { text-align: right; white-space: normal; }
		#spTable .sp-type-line, #spTable .sp-voucher-no { white-space: normal; max-width: none; }
		#spTable td.dataTables_empty::before { content: none; }
		#spTable td.dataTables_empty { display: block; text-align: center; }
	}
</style>

<nav aria-label="breadcrumb" class="sp-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Transactions</li>
        <li class="breadcrumb-item active" aria-current="page">Supplier Payments</li>
    </ol>
</nav>

<div class="page-title sp-page-head d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-wallet2 me-2"></i>Supplier Payments</span>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= base_url('supplier-payments/create') ?>" class="btn-save"><i class="bi bi-plus-lg"></i> New Supplier Payment</a>
        <a href="javascript:void(0)" class="btn-cancel" title="Export (coming soon)" onclick="alert('Export will be available in a future release.')"><i class="bi bi-file-earmark-excel"></i> Export</a>
    </div>
</div>

<!-- FILTERS (client-side) -->
<div class="card-custom mb-3 sp-page-card sp-filter-card">
    <div class="card-custom-body">
        <div class="sp-filters">
            <div class="sp-field sp-f-supplier">
                <label class="form-label" for="filterSupplier">Supplier</label>
                <select id="filterSupplier" class="form-control">
                    <option value="">All Suppliers</option>
                    <?php foreach ($sp_suppliers as $sp_id => $sp_name): ?>
                    <option value="<?= (int) $sp_id ?>"><?= esc($sp_name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sp-field sp-f-from">
                <label class="form-label" for="filterFromDate">From Date</label>
                <input type="date" id="filterFromDate" class="form-control">
            </div>
            <div class="sp-field sp-f-to">
                <label class="form-label" for="filterToDate">To Date</label>
                <input type="date" id="filterToDate" class="form-control">
            </div>
            <div class="sp-field sp-f-type">
                <label class="form-label" for="filterType">Payment Type</label>
                <select id="filterType" class="form-control">
                    <option value="">All Payments</option>
                    <option value="BILL">Bill Payment</option>
                    <option value="ADVANCE">Advance Payment</option>
                    <option value="MIXED">Mixed Payment</option>
                </select>
            </div>
            <div class="sp-field sp-f-status">
                <label class="form-label" for="filterStatus">Payment Status</label>
                <select id="filterStatus" class="form-control">
                    <option value="">All Status</option>
                    <option value="PAID">Paid</option>
                    <option value="PARTIAL">Partial</option>
                    <option value="ADVANCE">Advance</option>
                </select>
            </div>
            <div class="sp-field sp-f-search">
                <label class="form-label" for="customSearch">Search</label>
                <i class="bi bi-search sp-search-icon"></i>
                <input type="text" id="customSearch" class="form-control" placeholder="Voucher no, supplier…">
            </div>
        </div>
    </div>
</div>

<!-- PAYMENT TABLE -->
<div class="card-custom">
    <div class="table-responsive sp-table-wrap">
        <table id="spTable" class="table-custom">
            <thead>
                <tr>
                    <th>Voucher No</th>
                    <th>Payment Date</th>
                    <th>Supplier</th>
                    <th>Payment Type</th>
                    <th class="sp-num">Payment Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vouchers as $v): ?>
                <?php
                $statusKey = strtolower($v['status']);
                $isDue     = $v['supplier_outstanding'] > 0.004;
                $isAdvance = $v['payment_type'] === 'ADVANCE';
                $amtClass  = (float) $v['total_amount'] <= 0.004 ? 'sp-amt-zero' : ($isAdvance ? 'sp-amt-adv' : '');
                // Hidden, search-only text: bill numbers and the GP number never get a column.
                $searchText = [];
                foreach ($v['bills'] as $b) {
                    $searchText[] = $b['purchase_no'];
                    if (! empty($b['bill_no'])) {
                        $searchText[] = $b['bill_no'];
                    }
                }
                if ($v['gp_advance_id']) {
                    $searchText[] = $v['reference_no'] ?: 'GP #' . (int) $v['gp_advance_id'];
                }
                ?>
                <tr class="sp-row sp-row-<?= $statusKey ?>"
                    data-supplier="<?= (int) $v['supplier_id'] ?>"
                    data-type="<?= esc($v['payment_type'], 'attr') ?>"
                    data-status="<?= esc($v['status'], 'attr') ?>"
                    data-date="<?= esc($v['payment_date'], 'attr') ?>">

                    <td data-label="Voucher No" data-order="<?= esc($v['payment_no'], 'attr') ?>"><span class="sp-voucher-no"><?= esc($v['payment_no']) ?></span><span class="d-none"><?= esc(implode(' ', $searchText)) ?></span></td>

                    <td data-label="Payment Date" data-order="<?= esc($v['payment_date'], 'attr') ?>"><?= esc($v['payment_date']) ?></td>

                    <td data-label="Supplier">
                        <div class="sp-supplier-name"><?= esc($v['supplier_name'] ?: '—') ?></div>
                        <?php if ($isAdvance): ?>
                        <div class="sp-supplier-sub sp-out-adv">Advance Voucher</div>
                        <?php else: ?>
                        <div class="sp-supplier-sub <?= $isDue ? 'sp-out-due' : 'sp-out-paid' ?>"><?= $isDue ? 'Outstanding ' . $sp_money($v['supplier_outstanding']) : 'Paid in Full' ?></div>
                        <?php endif; ?>
                    </td>

                    <td data-label="Payment Type"><span class="sp-type-line" title="<?= esc($sp_type_line($v), 'attr') ?>"><?= esc($sp_type_line($v)) ?></span></td>

                    <td data-label="Payment Amount" class="sp-num sp-amount <?= $amtClass ?>" data-order="<?= (float) $v['total_amount'] ?>"><?= $sp_money($v['total_amount']) ?></td>

                    <td data-label="Status"><span class="sp-badge sp-status-<?= $statusKey ?>"><?= $sp_status_badge[$v['status']] ?? esc($v['status']) ?></span></td>

                    <td data-label="Actions">
                        <span class="d-inline-flex gap-1">
                            <a href="<?= base_url('supplier-payments/view/' . $v['id']) ?>" class="btn-view" title="View"><i class="bi bi-eye"></i></a>
                            <?php if ($v['gp_advance_id']): ?>
                            <!-- Owned by its General Purchase: that is the only screen
                                 that can change the advance, so edit/delete point there. -->
                            <a href="<?= base_url('general-purchases/edit/' . (int) $v['gp_advance_id']) ?>" class="btn-edit" title="Edit the advance on its General Purchase"><i class="bi bi-box-arrow-up-right"></i></a>
                            <?php else: ?>
                            <a href="<?= base_url('supplier-payments/edit/' . $v['id']) ?>" class="btn-edit" title="Edit"><i class="bi bi-pencil"></i></a>
                            <a href="javascript:void(0)" class="btn-delete" title="Delete" onclick="confirmDeleteVoucher(<?= (int) $v['id'] ?>)"><i class="bi bi-trash"></i></a>
                            <?php endif; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	function confirmDeleteVoucher(id) {
		if (!confirm('Delete this supplier payment? Outstanding balances on its bills will be restored. This cannot be undone.')) return;

		$.ajax({
			url: "<?= base_url('supplier-payments/delete/') ?>" + id,
			type: 'POST',
			dataType: 'json'
		}).done(function (resp) {
			if (resp.status) {
				window.location.reload();
			} else {
				alert((resp.errors && resp.errors.join(' ')) || 'Failed to delete payment.');
			}
		}).fail(function (xhr) {
			var data = xhr.responseJSON;
			alert((data && data.errors && data.errors.join(' ')) || 'A network error occurred.');
		});
	}

	$(document).ready(function () {
		var table = $('#spTable').DataTable({
			paging: true,
			searching: true,
			lengthChange: false,
			info: false,
			ordering: true,
			order: [[1, 'desc'], [0, 'desc']],
			pageLength: 10,
			dom: 'tp',
			// Invoice/Bills, Status and Actions are composed cells — sorting
			// them by their rendered text would be meaningless.
			columnDefs: [
				{ orderable: false, targets: [5, 6] }
			],
			language: {
				paginate: {
					previous: '<i class="bi bi-chevron-left"></i>',
					next: '<i class="bi bi-chevron-right"></i>'
				},
				// K. empty states
				emptyTable:  '<div class="sp-empty"><i class="bi bi-inbox"></i>No supplier payments found.</div>',
				zeroRecords: '<div class="sp-empty"><i class="bi bi-funnel"></i>No payments match your filters.</div>'
			}
		});

		// ---- C. filters ----
		$('#customSearch').on('keyup', function () {
			table.search(this.value).draw();
		});
		$('#filterSupplier, #filterType, #filterStatus, #filterFromDate, #filterToDate').on('change', function () {
			table.draw();
		});

		// Supplier / payment type / date range are matched against the row's
		// own data attributes, so the composed cell markup never affects them.
		$.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
			if (settings.nTable.id !== 'spTable') return true;

			var node = settings.aoData[dataIndex] && settings.aoData[dataIndex].nTr;
			if (!node) return true;

			var supplier = $('#filterSupplier').val();
			var type     = $('#filterType').val();
			var status   = $('#filterStatus').val();
			var from     = $('#filterFromDate').val();
			var to       = $('#filterToDate').val();
			var rowDate  = node.getAttribute('data-date') || '';

			if (supplier && node.getAttribute('data-supplier') !== supplier) return false;
			if (type && node.getAttribute('data-type') !== type) return false;
			if (status && node.getAttribute('data-status') !== status) return false;
			if (from && rowDate < from) return false;
			if (to && rowDate > to) return false;
			return true;
		});
	});
</script>
<?= $this->endSection() ?>
