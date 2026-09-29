<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
helper('loan_ui');
$fmtDate = static fn ($d) => $d ? date('d-m-Y', strtotime($d)) : '—';
$money   = static fn ($n) => number_format((float) $n, 2);
$sum     = static fn (string $k) => array_sum(array_column($rows, $k));
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

	#outTable.table-custom th,
	#outTable.table-custom td { padding: 7px 10px; font-size: .75rem; }
	#outTable td:nth-child(1), #outTable td:nth-child(6) { white-space: nowrap; }

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
        <li class="breadcrumb-item active" aria-current="page">Outstanding Loan Report</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-hourglass-split me-2"></i>Outstanding Loan Report</span>
    <div class="d-flex gap-2">
        <a href="<?= base_url('loans') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Loan Management</a>
    </div>
</div>

<?= $this->include('loans/partials/report_toolbar') ?>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
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
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-journal-check"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_active_loans) ?></div>
                <div class="kpi-label">Active Loans</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-check2-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_closed_loans) ?></div>
                <div class="kpi-label">Closed Loans</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-calculator"></i></div>
            <div>
                <div class="kpi-value"><?= $money($kpi_average) ?></div>
                <div class="kpi-label">Average Outstanding</div>
            </div>
        </div>
    </div>
</div>

<!-- FILTERS -->
<div class="card-custom mb-3 ln-noprint">
    <div class="card-custom-header">Filters</div>
    <div class="card-custom-body">
        <form method="GET" action="<?= base_url('loan-reports/outstanding') ?>" class="filter-toolbar" id="lnFilterForm">
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
                            <option value="<?= $t ?>" <?= $f['loan_type'] === $t ? 'selected' : '' ?>><?= esc(ucfirst(strtolower($t))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="form-section">
                        <label class="form-label">Outstanding From</label>
                        <input type="number" step="0.01" min="0" name="min_amount" class="form-control" value="<?= $f['min_amount'] !== null ? esc((string) $f['min_amount']) : '' ?>">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="form-section">
                        <label class="form-label">Outstanding To</label>
                        <input type="number" step="0.01" min="0" name="max_amount" class="form-control" value="<?= $f['max_amount'] !== null ? esc((string) $f['max_amount']) : '' ?>">
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="form-section">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Loan no, lender..." value="<?= esc($f['search']) ?>">
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
        <table id="outTable" class="table-custom">
            <thead>
                <tr>
                    <th>Loan Number</th>
                    <th>Lender</th>
                    <th style="text-align:right">Loan Amount</th>
                    <th style="text-align:right">Principal Paid</th>
                    <th style="text-align:right">Outstanding Principal</th>
                    <th>Next EMI Date</th>
                    <th style="text-align:right">Next EMI Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?= base_url('loans/view/' . $r['id']) ?>" style="text-decoration:none;"><?= esc($r['loan_no']) ?></a></td>
                    <td><?= esc($r['lender_name']) ?></td>
                    <td style="text-align:right" data-order="<?= (float) $r['sanctioned_amount'] ?>"><?= $money($r['sanctioned_amount']) ?></td>
                    <td style="text-align:right" data-order="<?= (float) $r['total_principal_paid'] ?>"><?= $money($r['total_principal_paid']) ?></td>
                    <td style="text-align:right" data-order="<?= (float) $r['outstanding_principal'] ?>"><?= $money($r['outstanding_principal']) ?></td>
                    <td data-order="<?= esc((string) $r['next_emi_date']) ?>"><?= $fmtDate($r['next_emi_date']) ?></td>
                    <td style="text-align:right" data-order="<?= (float) $r['next_emi_amount'] ?>"><?= $r['next_emi_date'] ? $money($r['next_emi_amount']) : '—' ?></td>
                    <td><?= ln_loan_status_badge($r['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc;font-weight:700">
                    <td colspan="2">TOTAL</td>
                    <td style="text-align:right"><?= $money($sum('sanctioned_amount')) ?></td>
                    <td style="text-align:right"><?= $money($sum('total_principal_paid')) ?></td>
                    <td style="text-align:right"><?= $money($kpi_outstanding) ?></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	$(document).ready(function () {
		$('#outTable').DataTable({
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
				emptyTable: <?= json_encode(ln_empty_message('loans', $hasFilters), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
			}
		});
	});
</script>
<?= $this->endSection() ?>
