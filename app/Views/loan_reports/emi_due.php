<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
helper('loan_ui');
$fmtDate = static fn ($d) => $d ? date('d-m-Y', strtotime($d)) : '—';
$money   = static fn ($n) => number_format((float) $n, 2);
$totalBalance = round(array_sum(array_column($rows, 'balance_amount')), 2);
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

	#dueTable.table-custom th,
	#dueTable.table-custom td { padding: 7px 10px; font-size: .75rem; }
	#dueTable td:nth-child(1), #dueTable td:nth-child(2) { white-space: nowrap; }

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
        <li class="breadcrumb-item active" aria-current="page">EMI Due Report</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-calendar-event me-2"></i>EMI Due Report</span>
    <div class="d-flex gap-2">
        <a href="<?= base_url('loans') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Loan Management</a>
    </div>
</div>

<?= $this->include('loans/partials/report_toolbar') ?>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-calendar-month"></i></div>
            <div>
                <div class="kpi-value"><?= $money($kpi_due_month) ?></div>
                <div class="kpi-label">EMI Due This Month (<?= (int) $kpi_due_month_count ?>)</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_overdue_count) ?></div>
                <div class="kpi-label">Overdue EMI Count</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-calendar-check"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_upcoming_count) ?></div>
                <div class="kpi-label">Upcoming EMI Count</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="kpi-value"><?= $money($kpi_overdue_amount) ?></div>
                <div class="kpi-label">Overdue Amount</div>
            </div>
        </div>
    </div>
</div>

<!-- FILTERS -->
<div class="card-custom mb-3 ln-noprint">
    <div class="card-custom-header">Filters</div>
    <div class="card-custom-body">
        <form method="GET" action="<?= base_url('loan-reports/emi-due') ?>" class="filter-toolbar" id="lnFilterForm">
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
                        <label class="form-label">Due Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= esc($f['date_from']) ?>">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="form-section">
                        <label class="form-label">Due Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= esc($f['date_to']) ?>">
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
        <span>Unpaid EMIs</span>
        <span class="ln-count" id="lnCount"><?= number_format($count) ?> record<?= $count === 1 ? '' : 's' ?></span>
    </div>
    <div class="table-responsive ln-scroll ln-sticky">
        <table id="dueTable" class="table-custom">
            <thead>
                <tr>
                    <th>Due Date</th>
                    <th>Loan Number</th>
                    <th>Lender</th>
                    <th>EMI Number</th>
                    <th style="text-align:right">EMI Amount</th>
                    <th style="text-align:right">Paid Amount</th>
                    <th style="text-align:right">Balance Amount</th>
                    <th style="text-align:right" title="Today minus due date: positive = overdue, negative = not yet due">Days Due</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td data-order="<?= esc($r['due_date']) ?>"><?= $fmtDate($r['due_date']) ?></td>
                    <td><a href="<?= base_url('loans/view/' . $r['loan_id']) ?>" style="text-decoration:none;"><?= esc($r['loan_no']) ?></a></td>
                    <td><?= esc($r['lender_name']) ?></td>
                    <td>#<?= (int) $r['emi_no'] ?></td>
                    <td style="text-align:right" data-order="<?= (float) $r['emi_amount'] ?>"><?= $money($r['emi_amount']) ?></td>
                    <td style="text-align:right" data-order="<?= (float) $r['paid_amount'] ?>"><?= $money($r['paid_amount']) ?></td>
                    <td style="text-align:right" data-order="<?= (float) $r['balance_amount'] ?>"><?= $money($r['balance_amount']) ?></td>
                    <td style="text-align:right" data-order="<?= (int) $r['days_due'] ?>"><?= (int) $r['days_due'] ?></td>
                    <td><?= ln_due_badge($r['band']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc;font-weight:700">
                    <td colspan="6">TOTAL BALANCE</td>
                    <td style="text-align:right"><?= $money($totalBalance) ?></td>
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
		$('#dueTable').DataTable({
			paging: true,
			searching: false,
			lengthChange: false,
			info: false,
			ordering: true,
			order: [],           // keep the server's earliest-due-first order until a header is clicked
			pageLength: 10,
			dom: 'tp',
			language: {
				paginate: {
					previous: '<i class="bi bi-chevron-left"></i>',
					next: '<i class="bi bi-chevron-right"></i>'
				},
				emptyTable: <?= json_encode(ln_empty_message('EMIs due', $hasFilters), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
			}
		});
	});
</script>
<?= $this->endSection() ?>
