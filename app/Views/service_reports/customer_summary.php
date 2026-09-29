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

	#csTable.table-custom th,
	#csTable.table-custom td { padding: 7px 10px; font-size: .78rem; }

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
        <li class="breadcrumb-item active" aria-current="page">Customer Outstanding Summary</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-people me-2"></i>Customer Outstanding Summary</span>
    <div class="d-flex gap-2">
        <a href="javascript:void(0)" class="btn-save" style="background:#6c757d;" title="Print (coming soon)" onclick="alert('Print will be available in a future release.')"><i class="bi bi-printer"></i> Print</a>
        <a href="<?= base_url('reports') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-people"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_customers) ?></div>
                <div class="kpi-label">Total Customers</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-receipt"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_invoice_amount, 2) ?></div>
                <div class="kpi-label">Total Invoice Amount</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_received, 2) ?></div>
                <div class="kpi-label">Total Received</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-exclamation-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_outstanding, 2) ?></div>
                <div class="kpi-label">Total Outstanding</div>
            </div>
        </div>
    </div>
</div>

<!-- FILTERS -->
<div class="card-custom mb-3">
    <div class="card-custom-header">Filters</div>
    <div class="card-custom-body">
        <form method="GET" action="<?= base_url('service-reports/customer-summary') ?>" class="filter-toolbar">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-4">
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
                <div class="col-6 col-md-4">
                    <div class="form-section">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Customer, mobile, GST..." value="<?= esc($f['search']) ?>">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="form-check form-switch" style="padding-top:22px;">
                        <input class="form-check-input" type="checkbox" role="switch" id="outstandingOnly" name="outstanding_only" value="1" <?= $f['outstanding_only'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="outstandingOnly" style="font-size:.82rem;">Outstanding only</label>
                    </div>
                </div>
                <div class="col-12" style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap">
                    <button type="submit" class="btn-save"><i class="bi bi-search"></i> Filter</button>
                    <a href="<?= base_url('service-reports/customer-summary') ?>" class="btn-save" style="background:#6c757d;"><i class="bi bi-arrow-clockwise"></i> Reset</a>
                    <a href="<?= base_url('service-reports/export/pdf/customer-summary') . (($qs = $_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $qs : '') ?>" class="btn-save" style="background:#16a34a;"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
                    <a href="<?= base_url('service-reports/export/excel/customer-summary') . (($qs = $_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $qs : '') ?>" class="btn-save" style="background:#16a34a;"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card-custom">
    <div class="table-responsive">
        <table id="csTable" class="table-custom">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Mobile</th>
                    <th style="text-align:right">Invoice Amount</th>
                    <th style="text-align:right">Received</th>
                    <th style="text-align:right">Outstanding</th>
                    <th style="text-align:right">Advance Balance</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= esc($r['name']) ?></td>
                    <td><?= esc($r['phone'] ?: '-') ?></td>
                    <td style="text-align:right" data-order="<?= $r['invoice'] ?>"><?= number_format($r['invoice'], 2) ?></td>
                    <td style="text-align:right" data-order="<?= $r['received'] ?>"><?= number_format($r['received'], 2) ?></td>
                    <td style="text-align:right" data-order="<?= $r['outstanding'] ?>" class="<?= $r['outstanding'] > 0.004 ? 'text-danger-amt' : 'text-muted-amt' ?>"><?= number_format($r['outstanding'], 2) ?></td>
                    <td style="text-align:right" data-order="<?= $r['advance'] ?>"><?= number_format($r['advance'], 2) ?></td>
                    <td><a href="<?= base_url('customer-ledger/view/' . $r['id']) ?>" class="btn-view"><i class="bi bi-journal-text"></i> View Ledger</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc;font-weight:700">
                    <td colspan="2">TOTAL</td>
                    <td style="text-align:right"><?= number_format($kpi_invoice_amount, 2) ?></td>
                    <td style="text-align:right"><?= number_format($kpi_received, 2) ?></td>
                    <td style="text-align:right"><?= number_format($kpi_outstanding, 2) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div style="padding:8px 14px;font-size:.72rem;color:#94a3b8;">
        Same figures as each customer's ledger: Received includes any unallocated advance, and Outstanding is never negative.
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	$(document).ready(function () {
		$('#csTable').DataTable({
			paging: true,
			searching: false,
			lengthChange: false,
			info: false,
			ordering: true,
			order: [],           // keep the server's outstanding-descending order until a header is clicked
			pageLength: 10,
			dom: 'tp',
			language: {
				paginate: {
					previous: '<i class="bi bi-chevron-left"></i>',
					next: '<i class="bi bi-chevron-right"></i>'
				},
				emptyTable: 'No customers found.'
			}
		});
	});
</script>
<?= $this->endSection() ?>
