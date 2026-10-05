<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
helper('loan_ui');
$fmtDate = static fn ($d) => $d ? date('d-m-Y', strtotime($d)) : '—';
$money   = static fn ($n) => number_format((float) $n, 2);
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
	.filter-toolbar .btn-save { padding: 6px 12px; font-size: .8rem; white-space: nowrap; }

	#regTable.table-custom th,
	#regTable.table-custom td { padding: 7px 10px; font-size: .75rem; }
	#regTable td:nth-child(2), #regTable td:nth-child(5) { white-space: nowrap; }

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
        <li class="breadcrumb-item active" aria-current="page">Loan Register</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-journal-check me-2"></i>Loan Register</span>
    <div class="d-flex gap-2">
        <a href="<?= base_url('loans') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Loan Management</a>
    </div>
</div>

<?= $this->include('loans/partials/report_toolbar') ?>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-journal-check"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_loans) ?></div>
                <div class="kpi-label">Total Loans</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
            <div>
                <div class="kpi-value"><?= $money($kpi_total_amount) ?></div>
                <div class="kpi-label">Total Loan Amount</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="kpi-value"><?= $money($kpi_outstanding) ?></div>
                <div class="kpi-label">Outstanding Principal</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-check2-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_closed_loans) ?></div>
                <div class="kpi-label">Closed Loans</div>
            </div>
        </div>
    </div>
</div>

<!-- FILTERS -->
<div class="card-custom mb-3 ln-noprint">
    <div class="card-custom-header">Filters</div>
    <div class="card-custom-body">
        <form method="GET" data-auto-filter action="<?= base_url('loan-reports') ?>" class="filter-toolbar" id="lnFilterForm">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-3">
                    <div class="form-section">
                        <label class="form-label">Lender</label>
                        <select name="lender" class="form-control">
                            <option value="">All Lenders</option>
                            <?php foreach ($lenders as $name): ?>
                            <option value="<?= esc($name) ?>" <?= $f['lender'] === $name ? 'selected' : '' ?>><?= esc($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="form-section">
                        <label class="form-label">Loan Type</label>
                        <select name="loan_type" class="form-control">
                            <option value="">All Types</option>
                            <?php foreach ($loanTypes as $t): ?>
                            <option value="<?= $t ?>" <?= $f['loan_type'] === $t ? 'selected' : '' ?>><?= esc(\App\Models\LoanTypeModel::labels()[$t] ?? $t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="form-section">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= esc(ucfirst(strtolower($s))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="form-section">
                        <label class="form-label">Start Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= esc($f['date_from']) ?>">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="form-section">
                        <label class="form-label">Start Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= esc($f['date_to']) ?>">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="form-section">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Loan no, lender, account no, remarks..." value="<?= esc($f['search']) ?>">
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (! empty($warning)): ?>
<div class="alert alert-warning py-2" style="font-size:.82rem;"><i class="bi bi-exclamation-triangle me-1"></i><?= esc($warning) ?></div>
<?php endif; ?>

<div class="card-custom">
    <div class="card-custom-header ln-card-head">
        <span>Loans</span>
        <span class="ln-count" id="lnCount"><?= number_format($count) ?> record<?= $count === 1 ? '' : 's' ?></span>
    </div>
    <div class="table-responsive ln-scroll ln-sticky">
        <table id="regTable" class="table-custom">
            <thead>
                <tr>
                    <th class="sno-col">S.No.</th>
                    <th>Loan Number</th>
                    <th>Lender</th>
                    <th>Loan Type</th>
                    <th>Start Date</th>
                    <th style="text-align:right">EMI Amount</th>
                    <th style="text-align:right">Outstanding Principal</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="sno-col" data-label="S.No."></td>
                    <td><?= esc($r['loan_no']) ?></td>
                    <td><?= esc($r['lender_name']) ?></td>
                    <td><?= esc(\App\Models\LoanTypeModel::labels()[$r['loan_type']] ?? $r['loan_type']) ?></td>
                    <td data-order="<?= esc($r['start_date']) ?>"><?= $fmtDate($r['start_date']) ?></td>
                    <td style="text-align:right" data-order="<?= (float) $r['emi_amount'] ?>"><?= $money($r['emi_amount']) ?></td>
                    <td style="text-align:right" data-order="<?= (float) $r['outstanding_principal'] ?>"><?= $money($r['outstanding_principal']) ?></td>
                    <td><?= ln_loan_status_badge($r['status']) ?></td>
                    <td><a href="<?= base_url('loans/view/' . $r['id']) ?>" class="btn btn-sm btn-outline-primary" style="font-size:.72rem;padding:2px 8px;"><i class="bi bi-eye"></i> View Loan</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc;font-weight:700">
                    <td colspan="6">TOTAL</td>
                    <td style="text-align:right"><?= $money($kpi_outstanding) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	$(document).ready(function () {
		$('#regTable').DataTable({
			paging: true,
			searching: false,
			lengthChange: false,
			info: false,
			ordering: true,
			order: [],           // keep the server's newest-first order until a header is clicked
			pageLength: 10,
			dom: 'tp',
			language: {
				paginate: {
					previous: '<i class="bi bi-chevron-left"></i>',
					next: '<i class="bi bi-chevron-right"></i>'
				},
				emptyTable: <?= json_encode(ln_empty_message('loans', $hasFilters), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
			}
		});
	});
</script>
<?= $this->endSection() ?>
