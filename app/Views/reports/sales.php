<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

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

	.dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
		opacity: 0.5;
		cursor: not-allowed;
	}

	.dataTables_wrapper .dataTables_info {
		font-size: 12px;
		color: #64748b;
	}

	.dataTables_wrapper .dataTables_paginate {
		  float: right;
		  text-align: right;
		  padding-top: .25em;
		  padding-bottom: 7px;
		  padding-right: 10px;
		}

	.custom-search-box {
		max-width: 300px;
	}

	.custom-search-box input {
		border-radius: 8px;
		border: 1px solid #e2e8f0;
		padding: 6px 12px;
		font-size: 13px;
		transition: 0.2s;
	}

	.custom-search-box input:focus {
		border-color: #2F7E8A;
		box-shadow: 0 0 0 2px rgba(47,126,138,0.15);
	}
	.search-icon {
		position: absolute;
		top: 8px;
		left: 9px;
		color: #94a3b8;
		font-size: 12px;
	}

	.dataTables_wrapper .paginate_button i {
		font-size: 12px;
		vertical-align: middle;
	}

	/* compact filter toolbar */
	.filter-toolbar .form-section { margin-bottom: 0; }
	.filter-toolbar .form-label { font-size: .72rem; margin-bottom: 3px; }
	.filter-toolbar .form-control { padding: 6px 10px; font-size: .82rem; height: auto; }
	.filter-toolbar .btn-save,
	.filter-toolbar .btn.btn-secondary { padding: 6px 12px; font-size: .8rem; white-space: nowrap; }

	/* compact table spacing */
	#salesTable.table-custom th,
	#salesTable.table-custom td { padding: 7px 10px; font-size: .75rem; }

</style>

<div class="page-title">
    <span><i class="bi bi-receipt-cutoff me-2"></i>Sales Report</span>
    <a href="<?= base_url('reports') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_sales, 2) ?></div>
                <div class="kpi-label">Total Sales</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-wallet2"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_customer_received, 2) ?></div>
                <div class="kpi-label">Customer Received</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_customer_pending, 2) ?></div>
                <div class="kpi-label">Customer Pending</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-receipt"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_invoices) ?></div>
                <div class="kpi-label">Total Invoices</div>
            </div>
        </div>
    </div>
</div>

<!-- FILTER TOOLBAR -->
<div class="card-custom mb-3">
    <div class="card-custom-header">Filters</div>
    <div class="card-custom-body">
        <form method="GET" action="<?= base_url('reports/sales') ?>" class="filter-toolbar">
            <div class="row g-2 align-items-end">

                <div class="col-6 col-md-2">
                    <div class="form-section">
                        <label class="form-label">From Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= esc($start_date) ?>">
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <div class="form-section">
                        <label class="form-label">To Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= esc($end_date) ?>">
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <div class="form-section">
                        <label class="form-label">Project</label>
                        <select name="project_id" class="form-control">
                            <option value="">All Projects</option>
                            <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $project_id == $p['id'] ? 'selected' : '' ?>>
                                <?= esc($p['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <div class="form-section">
                        <label class="form-label">Customer</label>
                        <select name="customer_id" class="form-control">
                            <option value="">All Customers</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $customer_id == $c['id'] ? 'selected' : '' ?>>
                                <?= esc($c['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <div class="form-section">
                        <label class="form-label">Invoice Status</label>
                        <select name="status" class="form-control">
                            <option value="">All</option>
                            <option value="PAID" <?= $status == 'PAID' ? 'selected' : '' ?>>Paid</option>
                            <option value="PARTIAL" <?= $status == 'PARTIAL' ? 'selected' : '' ?>>Partial</option>
                            <option value="UNPAID" <?= $status == 'UNPAID' ? 'selected' : '' ?>>Unpaid</option>
                        </select>
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <div class="form-section">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-control">
                            <option value="">All</option>
                            <option value="CASH" <?= $payment_method == 'CASH' ? 'selected' : '' ?>>Cash</option>
                            <option value="BANK_TRANSFER" <?= $payment_method == 'BANK_TRANSFER' ? 'selected' : '' ?>>Bank Transfer</option>
                            <option value="CHECK" <?= $payment_method == 'CHECK' ? 'selected' : '' ?>>Check</option>
                            <option value="OTHER" <?= $payment_method == 'OTHER' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="form-section">
                        <label class="form-label">Search Invoice No</label>
                        <input type="text" name="search" class="form-control" placeholder="Invoice no..." value="<?= esc($search) ?>">
                    </div>
                </div>

                <!-- BUTTONS -->
                <div class="col-12 col-md-9" style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap">

                    <button type="submit" class="btn-save">
                        <i class="bi bi-search"></i> Filter
                    </button>

                    <a href="<?= base_url('reports/sales') ?>" class="btn btn-sm btn-secondary">
                       <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>

					<a href="<?= base_url('reports/sales-export?' . http_build_query($_GET)) ?>"
					   class="btn-save" style="background:#16a34a;">
						<i class="bi bi-file-earmark-excel"></i> Export
					</a>

					<a href="<?= base_url('reports/sales-pdf?' . http_build_query($_GET)) ?>"
					   class="btn-save" style="background:#dc2626;">
						<i class="bi bi-file-pdf"></i> PDF
					</a>

                </div>

            </div>
        </form>
    </div>
</div>

<div class="card-custom">
    <div class="table-responsive">
        <table id="salesTable" class="table-custom">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Invoice No</th>
                    <th>Date</th>
                    <th>Project</th>
                    <th>Customer</th>
                    <th style="text-align:right">Invoice Amount</th>
                    <th style="text-align:right">Advance Applied</th>
                    <th style="text-align:right">Paid</th>
                    <th style="text-align:right">Pending</th>
                    <th>Status</th>
                    <th>View</th>
                </tr>
            </thead>
            <tbody>
				<?php
				$grandTotal   = 0;
				$grandAdvance = 0;
				$grandPaid    = 0;
				$grandBalance = 0;
				foreach ($sales as $i => $s):
					$grandTotal   += $s['total_amount'];
					$grandAdvance += $s['advance_applied'];
					$grandPaid    += $s['paid_amount'];
					$grandBalance += $s['balance_amount'];
				?>
				<tr>
					<td><?= $i + 1 ?></td>
					<td><?= esc($s['invoice_no'] ?: '-') ?></td>
					<td><?= $s['sale_date'] ?></td>
					<td><?= esc($s['project_name']) ?></td>
					<td><?= esc($s['customer_name'] ?: '-') ?></td>
					<td style="text-align:right"><?= number_format($s['total_amount'], 2) ?></td>
					<td style="text-align:right"><?= number_format($s['advance_applied'], 2) ?></td>
					<td style="text-align:right"><?= number_format($s['paid_amount'], 2) ?></td>
					<td style="text-align:right"><?= number_format($s['balance_amount'], 2) ?></td>
					<td><span class="badge-status badge-<?= strtolower($s['status']) ?>"><?= $s['status'] ?></span></td>
					<td>
						<a href="<?= base_url('sales/view/' . $s['id']) ?>" class="btn-view"><i class="bi bi-eye"></i></a>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr style="background:#f8fafc;font-weight:700">
						<td colspan="5">TOTAL</td>
						<td style="text-align:right"><?= number_format($grandTotal, 2) ?></td>
						<td style="text-align:right"><?= number_format($grandAdvance, 2) ?></td>
						<td style="text-align:right"><?= number_format($grandPaid, 2) ?></td>
						<td style="text-align:right"><?= number_format($grandBalance, 2) ?></td>
						<td></td>
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
			$('#salesTable').DataTable({
				paging: true,        // ✅ pagination
				searching: false,    // ❌ remove search box
				lengthChange: false, // ❌ remove "show entries"
				info: false,          // (optional) showing "1 to 10 of X"
				ordering: true,      // (optional sorting)
				pageLength: 10,      // default rows per page

				dom: 'tpi' , // ✅ ONLY table + pagination + info

				 language: {
					 paginate: {
						 previous: '<i class="bi bi-chevron-left"></i>',
						 next: '<i class="bi bi-chevron-right"></i>'
					 },
					 emptyTable: 'No sales.'
				 }
			});
		});
	</script>
<?= $this->endSection() ?>
