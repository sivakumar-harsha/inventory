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

	#outTable.table-custom th,
	#outTable.table-custom td { padding: 7px 10px; font-size: .75rem; }
	#outTable td:nth-child(2), #outTable td:nth-child(3), #outTable td:last-child { white-space: nowrap; }

	.badge-sr { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .72rem; font-weight: 600; }
	.badge-sr.badge-pending { background:#ffedd5; color:#c2410c; }
	.badge-sr.badge-partial { background:#dbeafe; color:#1d4ed8; }
	.badge-sr.badge-paid    { background:#dcfce7; color:#166534; }

	/* age bands: colour only, no business rule attached */
	.badge-age { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: .7rem; font-weight: 700; white-space: nowrap; }
	.badge-age.age-0-30  { background:#dcfce7; color:#166534; }
	.badge-age.age-31-60 { background:#fef9c3; color:#854d0e; }
	.badge-age.age-61-90 { background:#ffedd5; color:#c2410c; }
	.badge-age.age-90    { background:#fee2e2; color:#b91c1c; }

	.sr-breadcrumb { margin-bottom: 8px; }
	.sr-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.sr-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.sr-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.sr-breadcrumb .breadcrumb-item.active { color: #64748b; }

	.text-danger-amt { color: #b91c1c; }
</style>

<nav aria-label="breadcrumb" class="sr-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('reports') ?>">Reports</a></li>
        <li class="breadcrumb-item">Service Reports</li>
        <li class="breadcrumb-item active" aria-current="page">Outstanding Service Report</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-exclamation-circle me-2"></i>Outstanding Service Report</span>
    <div class="d-flex gap-2">
        <a href="javascript:void(0)" class="btn-save" style="background:#6c757d;" title="Print (coming soon)" onclick="alert('Print will be available in a future release.')"><i class="bi bi-printer"></i> Print</a>
        <a href="<?= base_url('reports') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_pending_count) ?></div>
                <div class="kpi-label">Pending Invoice Count</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-cash"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_pending_amount, 2) ?></div>
                <div class="kpi-label">Pending Amount</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-pie-chart"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_partial_count) ?></div>
                <div class="kpi-label">Partial Invoice Count</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-exclamation-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_outstanding, 2) ?></div>
                <div class="kpi-label">Total Outstanding</div>
            </div>
        </div>
    </div>
</div>

<!-- FILTERS -->
<div class="card-custom mb-3">
    <div class="card-custom-header">Filters</div>
    <div class="card-custom-body">
        <form method="GET" data-auto-filter action="<?= base_url('service-reports/outstanding') ?>" class="filter-toolbar">
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
                        <label class="form-label">Outstanding From</label>
                        <input type="number" step="0.01" min="0" name="min_amount" class="form-control" placeholder="Min" value="<?= $f['min_amount'] !== null ? esc((string) $f['min_amount']) : '' ?>">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="form-section">
                        <label class="form-label">Outstanding To</label>
                        <input type="number" step="0.01" min="0" name="max_amount" class="form-control" placeholder="Max" value="<?= $f['max_amount'] !== null ? esc((string) $f['max_amount']) : '' ?>">
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
                        <input type="text" name="search" class="form-control" placeholder="Receipt no, customer..." value="<?= esc($f['search']) ?>">
                    </div>
                </div>
                <div class="col-12" style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap">
                    <a href="<?= base_url('service-reports/outstanding') ?>" class="btn-save" style="background:#6c757d;"><i class="bi bi-arrow-clockwise"></i> Reset</a>
                    <a href="<?= base_url('service-reports/export/pdf/outstanding') . (($qs = $_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $qs : '') ?>" class="btn-save" style="background:#16a34a;"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
                    <a href="<?= base_url('service-reports/export/excel/outstanding') . (($qs = $_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $qs : '') ?>" class="btn-save" style="background:#16a34a;"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (! $f['dates_valid']): ?>
<div class="alert alert-warning py-2" style="font-size:.82rem;"><i class="bi bi-exclamation-triangle me-1"></i>One of the dates is not valid (use YYYY-MM-DD), so no invoices are shown.</div>
<?php endif; ?>

<div class="card-custom">
    <div class="table-responsive">
        <table id="outTable" class="table-custom">
            <thead>
                <tr>
                    <th class="sno-col">S.No.</th>
                    <th>Receipt No</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th style="text-align:right">Invoice Amount</th>
                    <th style="text-align:right">Received</th>
                    <th style="text-align:right">Outstanding</th>
                    <th style="text-align:right">Age (Days)</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="sno-col" data-label="S.No."></td>
                    <td><?= esc($r['receipt_no']) ?></td>
                    <td><?= esc($r['receipt_date']) ?></td>
                    <td><?= esc($r['customer_name']) ?></td>
                    <td style="text-align:right"><?= number_format((float) $r['grand_total'], 2) ?></td>
                    <td style="text-align:right"><?= number_format((float) $r['received_amount'], 2) ?></td>
                    <td style="text-align:right" class="text-danger-amt"><?= number_format((float) $r['outstanding_amount'], 2) ?></td>
                    <td style="text-align:right" data-order="<?= (int) $r['age_days'] ?>">
                        <?= (int) $r['age_days'] ?>
                        <span class="badge-age age-<?= esc(str_replace(['-', '+'], ['-', ''], $r['age_band'])) ?>"><?= esc($r['age_band']) ?></span>
                    </td>
                    <td><span class="badge-sr badge-<?= esc(strtolower($r['payment_status'])) ?>"><?= esc(ucfirst(strtolower($r['payment_status']))) ?></span></td>
                    <td><a href="<?= base_url('service-receipts/view/' . $r['id']) ?>" class="btn-view"><i class="bi bi-eye"></i> View Receipt</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc;font-weight:700">
                    <td colspan="6">TOTAL OUTSTANDING</td>
                    <td style="text-align:right"><?= number_format($kpi_total_outstanding, 2) ?></td>
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
			order: [],           // keep the server's oldest-first order until a header is clicked
			pageLength: 10,
			dom: 'tp',
			language: {
				paginate: {
					previous: '<i class="bi bi-chevron-left"></i>',
					next: '<i class="bi bi-chevron-right"></i>'
				},
				emptyTable: 'No outstanding service invoices.'
			}
		});
	});
</script>
<?= $this->endSection() ?>
