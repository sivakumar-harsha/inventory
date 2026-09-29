<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

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

	#regTable.table-custom th,
	#regTable.table-custom td { padding: 7px 10px; font-size: .75rem; }
	#regTable td:nth-child(1), #regTable td:nth-child(2), #regTable td:last-child { white-space: nowrap; }

	.badge-sr { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .72rem; font-weight: 600; }
	.badge-sr.badge-pending { background:#ffedd5; color:#c2410c; }
	.badge-sr.badge-partial { background:#dbeafe; color:#1d4ed8; }
	.badge-sr.badge-paid    { background:#dcfce7; color:#166534; }
	.badge-sr.badge-invoice { background:#e0e7ff; color:#3730a3; }
	.badge-sr.badge-direct  { background:#f3f4f6; color:#374151; }

	.sr-breadcrumb { margin-bottom: 8px; }
	.sr-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.sr-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.sr-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.sr-breadcrumb .breadcrumb-item.active { color: #64748b; }

	.text-danger-amt { color: #b91c1c; }
	.text-muted-amt { color: #94a3b8; }
</style>

<nav aria-label="breadcrumb" class="sr-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('reports') ?>">Reports</a></li>
        <li class="breadcrumb-item">Service Reports</li>
        <li class="breadcrumb-item active" aria-current="page">Service Receipt Register</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-receipt-cutoff me-2"></i>Service Receipt Register</span>
    <div class="d-flex gap-2">
        <a href="javascript:void(0)" class="btn-save" style="background:#6c757d;" title="Print (coming soon)" onclick="alert('Print will be available in a future release.')"><i class="bi bi-printer"></i> Print</a>
        <a href="<?= base_url('reports') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-receipt-cutoff"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_receipts) ?></div>
                <div class="kpi-label">Total Receipts</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-receipt"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_invoice_amount, 2) ?></div>
                <div class="kpi-label">Invoice Amount</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_received_amount, 2) ?></div>
                <div class="kpi-label">Received Amount</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-exclamation-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_outstanding, 2) ?></div>
                <div class="kpi-label">Outstanding Amount</div>
            </div>
        </div>
    </div>
</div>

<!-- FILTERS -->
<div class="card-custom mb-3">
    <div class="card-custom-header">Filters</div>
    <div class="card-custom-body">
        <form method="GET" action="<?= base_url('service-reports/register') ?>" class="filter-toolbar">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-3">
                    <div class="form-section">
                        <label class="form-label">Receipt Type</label>
                        <select name="receipt_type" class="form-control">
                            <option value="">All Types</option>
                            <?php foreach ($receiptTypes as $t): ?>
                            <option value="<?= $t ?>" <?= $f['receipt_type'] === $t ? 'selected' : '' ?>><?= esc(ucfirst(strtolower($t))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-6 col-md-3">
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
                <div class="col-6 col-md-3">
                    <div class="form-section">
                        <label class="form-label">Customer</label>
                        <select name="customer_id" class="form-control">
                            <option value="">All Customers</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $f['customer_id'] == $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="form-section">
                        <label class="form-label">Payment Mode</label>
                        <select name="payment_mode" class="form-control">
                            <option value="">All Modes</option>
                            <?php foreach ($paymentModes as $m): ?>
                            <option value="<?= $m ?>" <?= $f['payment_mode'] === $m ? 'selected' : '' ?>><?= esc(ucfirst(strtolower($m))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="form-section">
                        <label class="form-label">Attended Person</label>
                        <select name="attended_person" class="form-control">
                            <option value="">All</option>
                            <?php foreach ($attendedList as $a): ?>
                            <option value="<?= esc($a) ?>" <?= $f['attended'] === $a ? 'selected' : '' ?>><?= esc($a) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="form-section">
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= esc($f['date_from']) ?>">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="form-section">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= esc($f['date_to']) ?>">
                    </div>
                </div>
                <div class="col-12 col-md-5">
                    <div class="form-section">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Receipt no, customer, attended person, remarks..." value="<?= esc($f['search']) ?>">
                    </div>
                </div>
                <div class="col-12" style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap">
                    <button type="submit" class="btn-save"><i class="bi bi-search"></i> Filter</button>
                    <a href="<?= base_url('service-reports/register') ?>" class="btn-save" style="background:#6c757d;"><i class="bi bi-arrow-clockwise"></i> Reset</a>
                    <a href="<?= base_url('service-reports/export/pdf') . (($qs = $_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $qs : '') ?>" class="btn-save" style="background:#16a34a;"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
                    <a href="<?= base_url('service-reports/export/excel') . (($qs = $_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $qs : '') ?>" class="btn-save" style="background:#16a34a;"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (! $f['dates_valid']): ?>
<div class="alert alert-warning py-2" style="font-size:.82rem;"><i class="bi bi-exclamation-triangle me-1"></i>One of the dates is not valid (use YYYY-MM-DD), so no receipts are shown.</div>
<?php endif; ?>

<div class="card-custom">
    <div class="table-responsive">
        <table id="regTable" class="table-custom">
            <thead>
                <tr>
                    <th>Receipt No</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Receipt Type</th>
                    <th>Payment Mode</th>
                    <th>Attended Person</th>
                    <th style="text-align:right">Invoice Amount</th>
                    <th style="text-align:right">Received</th>
                    <th style="text-align:right">Outstanding</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= esc($r['receipt_no']) ?></td>
                    <td><?= esc($r['receipt_date']) ?></td>
                    <td><?= esc($r['customer_name']) ?></td>
                    <td><span class="badge-sr badge-<?= esc(strtolower($r['receipt_type'])) ?>"><?= esc(ucfirst(strtolower($r['receipt_type']))) ?></span></td>
                    <td><?= esc(ucfirst(strtolower($r['payment_mode']))) ?></td>
                    <td><?= esc($r['attended_person'] ?: '-') ?></td>
                    <td style="text-align:right"><?= number_format((float) $r['grand_total'], 2) ?></td>
                    <td style="text-align:right"><?= number_format((float) $r['received_amount'], 2) ?></td>
                    <td style="text-align:right" class="<?= (float) $r['outstanding_amount'] > 0.004 ? 'text-danger-amt' : 'text-muted-amt' ?>"><?= number_format((float) $r['outstanding_amount'], 2) ?></td>
                    <td><span class="badge-sr badge-<?= esc(strtolower($r['payment_status'])) ?>"><?= esc(ucfirst(strtolower($r['payment_status']))) ?></span></td>
                    <td><a href="<?= base_url('service-receipts/view/' . $r['id']) ?>" class="btn-view" title="View"><i class="bi bi-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc;font-weight:700">
                    <td colspan="6">TOTAL</td>
                    <td style="text-align:right"><?= number_format($kpi_invoice_amount, 2) ?></td>
                    <td style="text-align:right"><?= number_format($kpi_received_amount, 2) ?></td>
                    <td style="text-align:right"><?= number_format($kpi_outstanding, 2) ?></td>
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
				emptyTable: 'No service receipts found.'
			}
		});
	});
</script>
<?= $this->endSection() ?>
