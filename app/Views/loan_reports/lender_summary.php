<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
helper('loan_ui');
$money = static fn ($n) => number_format((float) $n, 2);
$sum   = static fn (string $k) => array_sum(array_column($rows, $k));
$hasFilters = ln_has_filters($f);
$count      = count($rows);
?>
<?= $this->include('loans/partials/ui_styles') ?>

<style>
	.dataTables_wrapper .dataTables_paginate { margin-top: 10px; text-align: right; }
	.dataTables_wrapper .dataTables_paginate .paginate_button {
		background: #f1f5f9 !important; border: 1px solid #e2e8f0 !important; color: #334155 !important;
		padding: 4px 10px !important; margin: 2px !important; border-radius: 6px !important; font-size: 12px !important;
	}
	.dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #2F7E8A !important; color: #fff !important; border: none !important; }
	.dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: #1e293b !important; color: #fff !important; }

	.filter-toolbar .form-section { margin-bottom: 0; }
	.filter-toolbar .form-label { font-size: .72rem; margin-bottom: 3px; }
	.filter-toolbar .form-control { padding: 6px 10px; font-size: .82rem; height: auto; }
	.filter-toolbar .btn-save { padding: 6px 12px; font-size: .8rem; white-space: nowrap; }

	#lenderTable.table-custom th,
	#lenderTable.table-custom td { padding: 7px 10px; font-size: .75rem; }

	.sr-breadcrumb { margin-bottom: 8px; }
	.sr-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.sr-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.sr-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.sr-breadcrumb .breadcrumb-item.active { color: #64748b; }
</style>

<nav aria-label="breadcrumb" class="sr-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('reports') ?>">Reports</a></li>
        <li class="breadcrumb-item">Loan Reports</li>
        <li class="breadcrumb-item active" aria-current="page">Lender Summary</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-buildings me-2"></i>Lender Summary</span>
    <div class="d-flex gap-2">
        <a href="<?= base_url('loans') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Loan Management</a>
    </div>
</div>

<?= $this->include('loans/partials/report_toolbar') ?>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-buildings"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_lenders) ?></div>
                <div class="kpi-label">Total Lenders</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
            <div>
                <div class="kpi-value"><?= $money($kpi_borrowed) ?></div>
                <div class="kpi-label">Total Borrowed</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="kpi-value"><?= $money($kpi_outstanding) ?></div>
                <div class="kpi-label">Total Outstanding</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-percent"></i></div>
            <div>
                <div class="kpi-value"><?= $money($kpi_interest_paid) ?></div>
                <div class="kpi-label">Total Interest Paid</div>
            </div>
        </div>
    </div>
</div>

<!-- FILTERS -->
<div class="card-custom mb-3 ln-noprint">
    <div class="card-custom-header">Filters</div>
    <div class="card-custom-body">
        <form method="GET" action="<?= base_url('loan-reports/lender-summary') ?>" class="filter-toolbar" id="lnFilterForm">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <div class="form-section">
                        <label class="form-label">Search Lender</label>
                        <input type="text" name="search" class="form-control" placeholder="Lender name..." value="<?= esc($f['search']) ?>">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" role="switch" id="activeOnly" name="active_only" value="1" <?= $f['active_only'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activeOnly" style="font-size:.82rem;">Active lenders only <span class="text-muted">(with at least one active loan)</span></label>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card-custom">
    <div class="card-custom-header ln-card-head">
        <span>Lenders</span>
        <span class="ln-count" id="lnCount"><?= number_format($count) ?> record<?= $count === 1 ? '' : 's' ?></span>
    </div>
    <div class="table-responsive ln-scroll ln-sticky">
        <table id="lenderTable" class="table-custom">
            <thead>
                <tr>
                    <th>Lender</th>
                    <th style="text-align:right">Loan Count</th>
                    <th style="text-align:right">Borrowed Amount</th>
                    <th style="text-align:right">Principal Paid</th>
                    <th style="text-align:right">Interest Paid</th>
                    <th style="text-align:right">Outstanding Principal</th>
                    <th style="text-align:right">Active Loans</th>
                    <th style="text-align:right">Closed Loans</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?= base_url('loan-reports?lender=' . rawurlencode($r['lender_name'])) ?>" style="text-decoration:none;" title="Loans of this lender"><?= esc($r['lender_name']) ?></a></td>
                    <td style="text-align:right"><?= (int) $r['loan_count'] ?></td>
                    <td style="text-align:right" data-order="<?= (float) $r['borrowed'] ?>"><?= $money($r['borrowed']) ?></td>
                    <td style="text-align:right" data-order="<?= (float) $r['principal_paid'] ?>"><?= $money($r['principal_paid']) ?></td>
                    <td style="text-align:right" data-order="<?= (float) $r['interest_paid'] ?>"><?= $money($r['interest_paid']) ?></td>
                    <td style="text-align:right" data-order="<?= (float) $r['outstanding'] ?>"><?= $money($r['outstanding']) ?></td>
                    <td style="text-align:right"><?= (int) $r['active_loans'] ?></td>
                    <td style="text-align:right"><?= (int) $r['closed_loans'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc;font-weight:700">
                    <td>TOTAL</td>
                    <td style="text-align:right"><?= (int) $sum('loan_count') ?></td>
                    <td style="text-align:right"><?= $money($kpi_borrowed) ?></td>
                    <td style="text-align:right"><?= $money($sum('principal_paid')) ?></td>
                    <td style="text-align:right"><?= $money($kpi_interest_paid) ?></td>
                    <td style="text-align:right"><?= $money($kpi_outstanding) ?></td>
                    <td style="text-align:right"><?= (int) $sum('active_loans') ?></td>
                    <td style="text-align:right"><?= (int) $sum('closed_loans') ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	$(document).ready(function () {
		$('#lenderTable').DataTable({
			paging: true,
			searching: false,
			lengthChange: false,
			info: false,
			ordering: true,
			order: [],           // keep the server's highest-outstanding-first order until a header is clicked
			pageLength: 10,
			dom: 'tp',
			language: {
				paginate: {
					previous: '<i class="bi bi-chevron-left"></i>',
					next: '<i class="bi bi-chevron-right"></i>'
				},
				emptyTable: <?= json_encode(ln_empty_message('lenders', $hasFilters), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
			}
		});
	});
</script>
<?= $this->endSection() ?>
