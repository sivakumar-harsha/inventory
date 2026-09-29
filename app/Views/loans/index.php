<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
helper('loan_ui');
$typeLabels = ['BANK' => 'Bank Loan', 'PERSONAL' => 'Personal', 'VEHICLE' => 'Vehicle', 'OD' => 'Overdraft', 'OTHER' => 'Other'];
?>
<?= $this->include('loans/partials/ui_styles') ?>

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
	.filter-toolbar .btn-cancel { padding: 6px 12px; font-size: .8rem; white-space: nowrap; }

	#loanTable.table-custom th,
	#loanTable.table-custom td { padding: 7px 10px; font-size: .78rem; }
	/* five row actions sit on one line (they used to stack, one per row of the cell); the loan number never
	   breaks at its hyphen, and the long money headings may wrap so the table fits a laptop screen */
	#loanTable.table-custom thead th { white-space: normal; }
	#loanTable.table-custom td:first-child { white-space: nowrap; }
	#loanTable td.ln-actions { white-space: nowrap; }
	#loanTable td.ln-actions a { margin-right: 2px; padding: 0 9px; }
	#loanTable td.ln-actions a:last-child { margin-right: 0; }

	.ln-breadcrumb { margin-bottom: 8px; }
	.ln-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.ln-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.ln-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.ln-breadcrumb .breadcrumb-item.active { color: #64748b; }

	.ln-sub { display: block; color: #94a3b8; font-size: .68rem; }
</style>

<nav aria-label="breadcrumb" class="ln-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Accounts</li>
        <li class="breadcrumb-item active" aria-current="page">Loan Management</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-cash-coin me-2"></i>Loan Management</span>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= base_url('loans/create') ?>" class="btn-save"><i class="bi bi-plus-lg"></i> New Loan</a>
        <a href="javascript:void(0)" class="btn-cancel" title="Export (coming soon)" onclick="alert('Export will be available in a future release.')"><i class="bi bi-file-earmark-excel"></i> Export</a>
    </div>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_active_loans) ?></div>
                <div class="kpi-label">Total Active Loans</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_outstanding, 2) ?></div>
                <div class="kpi-label">Outstanding Principal</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-bank"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_sanctioned, 2) ?></div>
                <div class="kpi-label">Total Loan Amount</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-calendar-event"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_emi_due_month, 2) ?></div>
                <div class="kpi-label">EMI Due This Month<?= $kpi_emi_due_count > 0 ? ' (' . $kpi_emi_due_count . ')' : '' ?></div>
            </div>
        </div>
    </div>
</div>

<!-- FILTERS (client-side) -->
<div class="card-custom mb-3">
    <div class="card-custom-header">Filters</div>
    <div class="card-custom-body">
        <div class="row g-2 align-items-end filter-toolbar">
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">Lender</label>
                    <select id="filterLender" class="form-control">
                        <option value="">All Lenders</option>
                        <?php foreach ($lenders as $lender): ?>
                        <option value="<?= esc($lender) ?>"><?= esc($lender) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">Loan Type</label>
                    <select id="filterType" class="form-control">
                        <option value="">All Types</option>
                        <?php foreach ($loan_types as $t): ?>
                        <option value="<?= esc($t) ?>"><?= esc($typeLabels[$t] ?? $t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">Status</label>
                    <select id="filterStatus" class="form-control no-search">
                        <option value="">All</option>
                        <option value="ACTIVE">Active</option>
                        <option value="CLOSED">Closed</option>
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">Start From</label>
                    <input type="date" id="filterFromDate" class="form-control">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">Start To</label>
                    <input type="date" id="filterToDate" class="form-control">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">Search</label>
                    <input type="text" id="customSearch" class="form-control" placeholder="Loan no, lender...">
                </div>
            </div>
            <div class="col-12 col-md-auto">
                <a href="javascript:void(0)" id="resetFilters" class="btn-cancel"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>
        </div>
    </div>
</div>

<div class="card-custom">
    <div class="table-responsive">
        <table id="loanTable" class="table-custom">
            <thead>
                <tr>
                    <th>Loan No</th>
                    <th>Lender</th>
                    <th>Loan Type</th>
                    <th style="text-align:right">Sanctioned Amount</th>
                    <th style="text-align:right">Outstanding Principal</th>
                    <th style="text-align:right">EMI Amount</th>
                    <th style="text-align:right">Interest %</th>
                    <th style="text-align:right">Tenure</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($loans as $l): ?>
                <tr data-lender="<?= esc($l['lender_name']) ?>"
                    data-type="<?= esc($l['loan_type']) ?>"
                    data-status="<?= esc($l['status']) ?>"
                    data-start="<?= esc($l['start_date']) ?>">
                    <td data-order="<?= (int) $l['id'] ?>">
                        <a href="<?= base_url('loans/view/' . $l['id']) ?>"><strong><?= esc($l['loan_no']) ?></strong></a>
                        <span class="ln-sub">Start <?= date('d-m-Y', strtotime($l['start_date'])) ?></span>
                    </td>
                    <td><?= esc($l['lender_name']) ?></td>
                    <td><?= esc($typeLabels[$l['loan_type']] ?? $l['loan_type']) ?></td>
                    <td style="text-align:right" data-order="<?= (float) $l['sanctioned_amount'] ?>"><?= number_format((float) $l['sanctioned_amount'], 2) ?></td>
                    <td style="text-align:right" data-order="<?= (float) $l['outstanding_principal'] ?>"><?= number_format((float) $l['outstanding_principal'], 2) ?></td>
                    <td style="text-align:right" data-order="<?= (float) $l['emi_amount'] ?>"><?= number_format((float) $l['emi_amount'], 2) ?></td>
                    <td style="text-align:right" data-order="<?= (float) $l['interest_rate'] ?>"><?= rtrim(rtrim(number_format((float) $l['interest_rate'], 3), '0'), '.') ?>%</td>
                    <td style="text-align:right" data-order="<?= (int) $l['tenure_months'] ?>"><?= (int) $l['tenure_months'] ?> mo</td>
                    <td><?= ln_loan_status_badge($l['status']) ?></td>
                    <td class="ln-actions">
                        <a href="<?= base_url('loans/view/' . $l['id']) ?>" class="btn-view" title="View"><i class="bi bi-eye"></i></a>
                        <a href="<?= base_url('loans/edit/' . $l['id']) ?>" class="btn-edit" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a href="javascript:void(0)" class="btn-delete" title="Delete" onclick="confirmDeleteLoan(<?= (int) $l['id'] ?>)"><i class="bi bi-trash"></i></a>
                        <a href="<?= base_url('loans/payments/' . $l['id']) ?>" class="btn-pay" title="Payments"><i class="bi bi-wallet2"></i></a>
                        <a href="<?= base_url('loans/ledger/' . $l['id']) ?>" class="btn-save" title="Ledger"><i class="bi bi-journal-text"></i></a>
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
	function confirmDeleteLoan(id) {
		if (!confirm('Delete this loan and its EMI schedule? Loans with payments recorded cannot be deleted.')) return;

		$.ajax({
			url: "<?= base_url('loans/delete/') ?>" + id,
			type: 'POST',
			dataType: 'json'
		}).done(function (resp) {
			if (resp.status) {
				window.location.reload();
			} else {
				alert((resp.errors && resp.errors.join(' ')) || 'Failed to delete loan.');
			}
		}).fail(function (xhr) {
			var data = xhr.responseJSON;
			alert((data && data.errors && data.errors.join(' ')) || 'A network error occurred.');
		});
	}

	$(document).ready(function () {
		var table = $('#loanTable').DataTable({
			paging: true,
			searching: true,
			lengthChange: false,
			info: false,
			ordering: true,
			order: [[0, 'desc']],
			pageLength: 10,
			dom: 'tp',
			columnDefs: [{ orderable: false, targets: 9 }],
			language: {
				paginate: {
					previous: '<i class="bi bi-chevron-left"></i>',
					next: '<i class="bi bi-chevron-right"></i>'
				},
				emptyTable: 'No loans found.'
			}
		});

		// Lender / type / status / start-date range, read from the row's data-* attributes.
		$.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
			if (settings.nTable.id !== 'loanTable') return true;

			var row = settings.aoData[dataIndex].nTr;
			var lender = $('#filterLender').val();
			var type   = $('#filterType').val();
			var status = $('#filterStatus').val();
			var from   = $('#filterFromDate').val();
			var to     = $('#filterToDate').val();
			var start  = row.getAttribute('data-start');

			if (lender && row.getAttribute('data-lender') !== lender) return false;
			if (type && row.getAttribute('data-type') !== type) return false;
			if (status && row.getAttribute('data-status') !== status) return false;
			if (from && start < from) return false;
			if (to && start > to) return false;
			return true;
		});

		$('#filterLender, #filterType, #filterStatus, #filterFromDate, #filterToDate').on('change', function () {
			table.draw();
		});

		$('#customSearch').on('keyup', function () {
			table.search(this.value).draw();
		});

		$('#resetFilters').on('click', function () {
			$('#filterLender, #filterType, #filterStatus').val('').trigger('change');
			$('#filterFromDate, #filterToDate, #customSearch').val('');
			table.search('').draw();
		});
	});
</script>
<?= $this->endSection() ?>
