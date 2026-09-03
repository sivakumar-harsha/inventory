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
	#purchaseTable.table-custom th,
	#purchaseTable.table-custom td { padding: 7px 10px; font-size: .75rem; }

	.badge-source.badge-mixed { background:#fef3c7; color:#92400e; }
	.badge-source.badge-general { background:#f3f4f6; color:#374151; }

</style>

<div class="page-title">
    <span><i class="bi bi-cart-fill me-2"></i>Purchases Report</span>
    <a href="<?= base_url('reports') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_purchases, 2) ?></div>
                <div class="kpi-label">Total Purchases</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-diagram-3"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_project_allocation, 2) ?></div>
                <div class="kpi-label">Project Allocation</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-boxes"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_general_allocation, 2) ?></div>
                <div class="kpi-label">General Allocation</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-truck"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_supplier_count) ?></div>
                <div class="kpi-label">Suppliers</div>
            </div>
        </div>
    </div>
</div>

<!-- FILTER TOOLBAR -->
<div class="card-custom mb-3">
    <div class="card-custom-header">Filters</div>
    <div class="card-custom-body">
        <form method="GET" action="<?= base_url('reports/purchases') ?>" class="filter-toolbar">
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
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-control">
                            <option value="">All Suppliers</option>
                            <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $supplier_id == $s['id'] ? 'selected' : '' ?>>
                                <?= esc($s['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
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
                        <label class="form-label">Stock Source</label>
                        <select name="stock_source" class="form-control">
                            <option value="">All</option>
                            <option value="PROJECT" <?= $stock_source == 'PROJECT' ? 'selected' : '' ?>>Project</option>
                            <option value="GENERAL" <?= $stock_source == 'GENERAL' ? 'selected' : '' ?>>General</option>
                            <option value="MIXED" <?= $stock_source == 'MIXED' ? 'selected' : '' ?>>Mixed</option>
                        </select>
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <div class="form-section">
                        <label class="form-label">Search Product/Invoice</label>
                        <input type="text" name="search" class="form-control" placeholder="Product or invoice..." value="<?= esc($search) ?>">
                    </div>
                </div>

                <!-- BUTTONS -->
                <div class="col-12" style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap">

                    <button type="submit" class="btn-save">
                        <i class="bi bi-search"></i> Filter
                    </button>

                    <a href="<?= base_url('reports/purchases') ?>" class="btn-save" style="background:#6c757d;">
                       <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>

					<a href="<?= base_url('reports/purchases-export?' . http_build_query($_GET)) ?>"
					   class="btn-save" style="background:#16a34a;">
						<i class="bi bi-file-earmark-excel"></i> Export
					</a>

					<a href="<?= base_url('reports/purchases-pdf?' . http_build_query($_GET)) ?>"
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
        <table id="purchaseTable" class="table-custom">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Invoice No</th>
                    <th>Date</th>
                    <th>Supplier</th>
                    <th>Project</th>
                    <th>Stock Source</th>
                    <th style="text-align:right">Purchased Qty</th>
                    <th style="text-align:right">Project Qty</th>
                    <th style="text-align:right">General Qty</th>
                    <th style="text-align:right">Project Amount</th>
                    <th style="text-align:right">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $grand          = 0;
                $grandProjAlloc = 0;
                $grandPurchQty  = 0;
                $grandProjQty   = 0;
                $grandGenQty    = 0;
                foreach ($purchases as $i => $p):
                    $grand          += $p['total_amount'];
                    $grandProjAlloc += $p['project_allocated_amount'];
                    $grandPurchQty  += $p['purchased_qty'];
                    $grandProjQty   += $p['project_qty'];
                    $grandGenQty    += $p['general_qty'];
                    $sourceClass = strtolower($p['source_label']);
                ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= esc($p['invoice_no'] ?: '-') ?></td>
                    <td><?= $p['purchase_date'] ?></td>
                    <td><?= esc($p['supplier_name']) ?></td>
                    <td><?= esc($p['project_name'] ?: '-') ?></td>
                    <td><span class="badge-source badge-<?= $sourceClass ?>"><?= $p['source_label'] ?></span></td>
                    <td style="text-align:right"><?= number_format($p['purchased_qty']) ?></td>
                    <td style="text-align:right"><?= number_format($p['project_qty']) ?></td>
                    <td style="text-align:right"><?= number_format($p['general_qty']) ?></td>
                    <td style="text-align:right"><?= number_format($p['project_allocated_amount'], 2) ?></td>
                    <td style="text-align:right"><?= number_format($p['total_amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>

			<tfoot>
				<tr style="background:#f8fafc;font-weight:700">
					 <td colspan="6">TOTAL</td>
					 <td style="text-align:right"><?= number_format($grandPurchQty) ?></td>
					 <td style="text-align:right"><?= number_format($grandProjQty) ?></td>
					 <td style="text-align:right"><?= number_format($grandGenQty) ?></td>
					 <td style="text-align:right"><?= number_format($grandProjAlloc, 2) ?></td>
                     <td style="text-align:right"><?= number_format($grand, 2) ?></td>
				</tr>
			</tfoot>
        </table>
    </div>
</div>

<?= $this->endSection() ?>


<?= $this->section('scripts') ?>
<script>
		$(document).ready(function () {
			$('#purchaseTable').DataTable({
				paging: true,        // ✅ pagination
				searching: false,    // ❌ remove search box
				lengthChange: false, // ❌ remove "show entries"
				info: false,          // (optional) showing "1 to 10 of X"
				ordering: true,      // (optional sorting)
				pageLength: 10,      // default rows per page

				dom: 'tpi', // ✅ ONLY table + pagination + info

				language: {
					paginate: {
						previous: '<i class="bi bi-chevron-left"></i>',
						next: '<i class="bi bi-chevron-right"></i>'
					},
					emptyTable: 'No purchases.'
				}

			});
		});
	</script>
<?= $this->endSection() ?>
