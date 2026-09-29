<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
	.sl-breadcrumb { margin-bottom: 8px; }
	.sl-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.sl-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.sl-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.sl-breadcrumb .breadcrumb-item.active { color: #64748b; }

	.badge-type {
		display: inline-block;
		padding: 2px 8px;
		border-radius: 6px;
		font-size: .7rem;
		font-weight: 700;
		white-space: nowrap;
	}
	.badge-type.type-invoice { background:#e0e7ff; color:#3730a3; }
	.badge-type.type-payment { background:#dcfce7; color:#166534; }
	.badge-type.type-advance { background:#fef9c3; color:#854d0e; }

	.ledger-table-wrap { overflow-x: auto; }
	#ledgerTable.table-custom th,
	#ledgerTable.table-custom td { padding: 7px 10px; font-size: .78rem; white-space: nowrap; }

	.text-danger-amt { color: #b91c1c; }
	.text-credit-amt { color: #166534; }

	.filter-toolbar .form-label { font-size: .72rem; margin-bottom: 3px; }
	.filter-toolbar .form-control { padding: 6px 10px; font-size: .82rem; height: auto; }

	@media print {
	    .page-title a, .btn-cancel, .btn-save, .sl-breadcrumb, .filter-toolbar, .main-sidebar, .main-header { display: none !important; }
	}
</style>

<?php
    // A negative running balance means the customer is in credit (advance
    // exceeds what is owed); show it as an absolute amount marked Cr.
    $fmtBalance = static fn (float $b): string => $b < -0.004 ? number_format(abs($b), 2) . ' Cr' : number_format($b, 2);
?>

<nav aria-label="breadcrumb" class="sl-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Transactions</li>
        <li class="breadcrumb-item"><a href="<?= base_url('customer-ledger') ?>">Customer Ledger</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= esc($customer['name']) ?></li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-journal-text me-2"></i>Customer Statement — <?= esc($customer['name']) ?></span>
    <div class="d-flex gap-2">
        <a href="javascript:window.print()" class="btn-save" style="background:#6c757d;"><i class="bi bi-printer"></i> Print</a>
        <a href="<?= base_url('customer-ledger/export/pdf/' . $customer['id']) ?>" class="btn-cancel"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
        <a href="<?= base_url('customer-ledger/export/excel/' . $customer['id']) ?>" class="btn-cancel"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
        <a href="<?= base_url('customer-ledger') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card-custom mb-3">
            <div class="card-custom-header">Customer Information</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td><strong>Name</strong></td><td><?= esc($customer['name']) ?></td></tr>
                    <tr><td><strong>GST Number</strong></td><td><?= esc($customer['gst'] ?: '-') ?></td></tr>
                    <tr><td><strong>Contact</strong></td><td><?= esc($customer['contact'] ?: '-') ?></td></tr>
                    <tr><td><strong>Mobile</strong></td><td><?= esc($customer['phone'] ?: '-') ?></td></tr>
                    <tr><td><strong>Email</strong></td><td><?= esc($customer['email'] ?: '-') ?></td></tr>
                    <tr><td><strong>Address</strong></td><td><?= esc($customer['address'] ?: '-') ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-4">
                <div class="kpi-card kpi-blue">
                    <div class="kpi-icon"><i class="bi bi-receipt"></i></div>
                    <div>
                        <div class="kpi-value"><?= number_format($summary['total_invoice'], 2) ?></div>
                        <div class="kpi-label">Total Invoice Amount</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="kpi-card kpi-green">
                    <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="kpi-value"><?= number_format($summary['total_received'], 2) ?></div>
                        <div class="kpi-label">Total Received Amount</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="kpi-card kpi-orange">
                    <div class="kpi-icon"><i class="bi bi-exclamation-circle"></i></div>
                    <div>
                        <div class="kpi-value"><?= number_format($summary['outstanding'], 2) ?></div>
                        <div class="kpi-label">Outstanding Balance</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="kpi-card kpi-red">
                    <div class="kpi-icon"><i class="bi bi-piggy-bank"></i></div>
                    <div>
                        <div class="kpi-value"><?= number_format($summary['advance'], 2) ?></div>
                        <div class="kpi-label">Advance Balance</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="kpi-card kpi-blue">
                    <div class="kpi-icon"><i class="bi bi-receipt-cutoff"></i></div>
                    <div>
                        <div class="kpi-value"><?= number_format($summary['invoice_count']) ?></div>
                        <div class="kpi-label">Invoice Count</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="kpi-card kpi-green">
                    <div class="kpi-icon"><i class="bi bi-wallet2"></i></div>
                    <div>
                        <div class="kpi-value"><?= number_format($summary['payment_count']) ?></div>
                        <div class="kpi-label">Payment Count</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FILTERS -->
<div class="card-custom mb-3">
    <div class="card-custom-header">Filters</div>
    <div class="card-custom-body">
        <div class="row g-2 align-items-end filter-toolbar">
            <div class="col-6 col-md-3">
                <div class="form-section">
                    <label class="form-label">From Date</label>
                    <input type="date" id="filterFromDate" class="form-control">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="form-section">
                    <label class="form-label">To Date</label>
                    <input type="date" id="filterToDate" class="form-control">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="form-section">
                    <label class="form-label">Transaction Type</label>
                    <select id="filterType" class="form-control">
                        <option value="">All Types</option>
                        <option value="Invoice">Invoice</option>
                        <option value="Payment">Payment</option>
                        <option value="Advance">Advance</option>
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="form-section">
                    <label class="form-label">Search</label>
                    <input type="text" id="filterSearch" class="form-control" placeholder="Reference, description...">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card-custom">
    <div class="card-custom-header">Transaction Ledger</div>
    <div class="ledger-table-wrap">
        <table id="ledgerTable" class="table-custom">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Transaction Type</th>
                    <th style="text-align:right">Debit</th>
                    <th style="text-align:right">Credit</th>
                    <th style="text-align:right">Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                <tr><td colspan="6" style="text-align:center; color:#94a3b8;">No transactions found for this customer.</td></tr>
                <?php endif; ?>
                <?php foreach ($transactions as $t): ?>
                <tr data-type="<?= esc($t['type']) ?>" data-date="<?= esc($t['date']) ?>" data-search="<?= esc(strtolower($t['reference'] . ' ' . $t['description'])) ?>">
                    <td><?= esc($t['date']) ?></td>
                    <td title="<?= esc($t['description']) ?>"><?= esc($t['reference'] ?: '-') ?></td>
                    <td><span class="badge-type type-<?= esc(strtolower($t['type'])) ?>"><?= esc($t['type']) ?></span> <small class="text-muted"><?= esc($t['description']) ?></small></td>
                    <td style="text-align:right"><?= $t['debit'] > 0 ? number_format($t['debit'], 2) : '-' ?></td>
                    <td style="text-align:right"><?= $t['credit'] > 0 ? number_format($t['credit'], 2) : '-' ?></td>
                    <td style="text-align:right" class="<?= $t['balance'] < -0.004 ? 'text-credit-amt' : '' ?>"><?= $fmtBalance($t['balance']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if (! empty($transactions)): ?>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align:right"><strong>Closing Balance</strong></td>
                    <td style="text-align:right" class="<?= $closingBalance > 0.004 ? 'text-danger-amt' : ($closingBalance < -0.004 ? 'text-credit-amt' : '') ?>"><strong><?= $fmtBalance($closingBalance) ?></strong></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	$(document).ready(function () {
		// Filtering only hides rows; the running balance and closing balance
		// stay the full-ledger figures, never a sum of the visible rows.
		function applyFilters() {
			var from   = $('#filterFromDate').val();
			var to     = $('#filterToDate').val();
			var type   = $('#filterType').val();
			var search = $.trim($('#filterSearch').val()).toLowerCase();

			$('#ledgerTable tbody tr[data-date]').each(function () {
				var row  = $(this);
				var date = String(row.data('date'));

				var visible = true;
				if (from && date < from) visible = false;
				if (to && date > to) visible = false;
				if (type && row.data('type') !== type) visible = false;
				if (search && String(row.data('search')).indexOf(search) === -1) visible = false;

				row.toggle(visible);
			});
		}

		$('#filterFromDate, #filterToDate, #filterType').on('change', applyFilters);
		$('#filterSearch').on('keyup input', applyFilters);
	});
</script>
<?= $this->endSection() ?>
