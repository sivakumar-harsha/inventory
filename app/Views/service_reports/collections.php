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
	.filter-toolbar .btn-save { padding: 6px 12px; font-size: .8rem; white-space: nowrap; }

	#colTable.table-custom th,
	#colTable.table-custom td { padding: 7px 10px; font-size: .75rem; }
	#colTable td:nth-child(2), #colTable td:nth-child(3) { white-space: nowrap; }

	.badge-src { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: .7rem; font-weight: 700; white-space: nowrap; }
	.badge-src.src-invoice-receipt  { background:#e0e7ff; color:#3730a3; }
	.badge-src.src-customer-payment { background:#dcfce7; color:#166534; }
	.badge-src.src-customer-advance { background:#fef9c3; color:#854d0e; }

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
        <li class="breadcrumb-item">Service Reports</li>
        <li class="breadcrumb-item active" aria-current="page">Service Collection Report</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-cash-stack me-2"></i>Service Collection Report</span>
    <div class="d-flex gap-2">
        <a href="javascript:void(0)" class="btn-save" style="background:#6c757d;" title="Print (coming soon)" onclick="alert('Print will be available in a future release.')"><i class="bi bi-printer"></i> Print</a>
        <a href="<?= base_url('reports') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-calendar-day"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_today, 2) ?></div>
                <div class="kpi-label">Today's Collection</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-calendar-month"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_month, 2) ?></div>
                <div class="kpi-label">This Month Collection</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-funnel"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_filtered, 2) ?></div>
                <div class="kpi-label">Collection in Filter</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-piggy-bank"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_advance, 2) ?></div>
                <div class="kpi-label">Advance Received (in filter)</div>
            </div>
        </div>
    </div>
</div>

<!-- FILTERS -->
<div class="card-custom mb-3">
    <div class="card-custom-header">Filters</div>
    <div class="card-custom-body">
        <form method="GET" data-auto-filter action="<?= base_url('service-reports/collections') ?>" class="filter-toolbar">
            <div class="row g-2 align-items-end">
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
                <div class="col-6 col-md-2">
                    <div class="form-section">
                        <label class="form-label">Payment Mode</label>
                        <select name="payment_mode" class="form-control">
                            <option value="">All Modes</option>
                            <?php foreach ($paymentModes as $m): ?>
                            <option value="<?= $m ?>" <?= $f['payment_mode'] === $m ? 'selected' : '' ?>><?= esc(pm_label($m)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="form-section">
                        <label class="form-label">Bank Account</label>
                        <select name="bank_account_id" class="form-control">
                            <option value="">All Accounts</option>
                            <?php foreach ($banks as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $f['bank_id'] == $b['id'] ? 'selected' : '' ?>><?= esc($b['bank_name'] . ' - ' . $b['account_name']) ?></option>
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
                <div class="col-12 col-md-4">
                    <div class="form-section">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Reference, customer, remarks..." value="<?= esc($f['search']) ?>">
                    </div>
                </div>
                <div class="col-12" style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap">
                    <a href="<?= base_url('service-reports/collections') ?>" class="btn-save" style="background:#6c757d;"><i class="bi bi-arrow-clockwise"></i> Reset</a>
                    <a href="<?= base_url('service-reports/export/pdf/collections') . (($qs = $_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $qs : '') ?>" class="btn-save" style="background:#16a34a;"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
                    <a href="<?= base_url('service-reports/export/excel/collections') . (($qs = $_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $qs : '') ?>" class="btn-save" style="background:#16a34a;"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (! $f['dates_valid']): ?>
<div class="alert alert-warning py-2" style="font-size:.82rem;"><i class="bi bi-exclamation-triangle me-1"></i>One of the dates is not valid (use YYYY-MM-DD), so no collections are shown.</div>
<?php endif; ?>

<div class="card-custom">
    <div class="table-responsive">
        <table id="colTable" class="table-custom">
            <thead>
                <tr>
                    <th class="sno-col">S.No.</th>
                    <th>Date</th>
                    <th>Reference No</th>
                    <th>Collection Source</th>
                    <th>Customer</th>
                    <th>Payment Mode</th>
                    <th>Bank Account</th>
                    <th style="text-align:right">Amount</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="sno-col" data-label="S.No."></td>
                    <td><?= esc($r['date']) ?></td>
                    <td><?= esc($r['reference']) ?></td>
                    <td><span class="badge-src src-<?= esc(strtolower(str_replace(' ', '-', $r['source']))) ?>"><?= esc($r['source']) ?></span></td>
                    <td><?= esc($r['customer'] ?: '-') ?></td>
                    <td><?= esc(pm_label($r['mode'], '-')) ?></td>
                    <td><?= esc($r['bank'] ?: '-') ?></td>
                    <td style="text-align:right"><?= number_format($r['amount'], 2) ?></td>
                    <td><?= esc($r['remarks'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc;font-weight:700">
                    <td colspan="7">TOTAL</td>
                    <td style="text-align:right"><?= number_format($kpi_filtered, 2) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	$(document).ready(function () {
		$('#colTable').DataTable({
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
				emptyTable: 'No collections found.'
			}
		});
	});
</script>
<?= $this->endSection() ?>
