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
	
</style>

<div class="page-title">
    <span><i class="bi bi-boxes me-2"></i>Stock Summary Report</span>
    <a href="<?= base_url('reports') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card-custom mb-3">
    <div class="card-custom-header">Filters</div>
    <div class="card-custom-body">
        <form method="GET" data-auto-filter action="<?= base_url('reports/stock') ?>">
            <div class="row fb-one-row">

                <div class="col-md-4">
                    <div class="form-section">
                        <label class="form-label">Product</label>
                        <select name="product_id" class="form-control">
                            <option value="all" <?= ($product_id === 'all' || empty($product_id)) ? 'selected' : '' ?>>All Products</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $product_id == $p['id'] ? 'selected' : '' ?>>
                                    <?= esc($p['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-4" style="display:flex;align-items:flex-end;gap:10px;padding-bottom:14px">

					
					<a href="<?= base_url('reports/stock') ?>" class="btn btn-sm btn-secondary">
                       <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>

                    <a href="<?= base_url('reports/stock-export?' . http_build_query($_GET)) ?>" 
                       class="btn-save" style="background:#16a34a;">
                        <i class="bi bi-file-earmark-excel"></i> Export
                    </a>
					
					<a href="<?= base_url('reports/stock-pdf?' . http_build_query($_GET)) ?>" 
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
        <table id="stockTable" class="table-custom">
            <thead>
                <tr><th class="sno-col">S.No.</th><th>Product</th><th>Unit</th><th style="text-align:center">General Stock</th><th style="text-align:center">Project Stock</th><th style="text-align:center">Total Stock</th></tr>
            </thead>
            <tbody>
                <?php foreach ($stock as $i => $s):
                    $total = $s['general_stock'] + $s['project_stock'];
                ?>
                <tr>
                    <td class="sno-col"><?= $i + 1 ?></td>
                    <td><strong><?= esc($s['name']) ?></strong></td>
                    <td><?= esc($s['unit']) ?></td>
                    <td style="text-align:center"><?= number_format($s['general_stock']) ?></td>
                    <td style="text-align:center"><?= number_format($s['project_stock']) ?></td>
                    <td class="<?= $total > 0 ? 'text-success' : 'text-danger' ?>" style="text-align:center"><strong><?= number_format($total) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->section('scripts') ?>
<script>
		$(document).ready(function () {
			$('#stockTable').DataTable({
				paging: true,        // ✅ pagination
				searching: false,    // ❌ remove search box
				lengthChange: false, // ❌ remove "show entries"
				info: false,          // (optional) showing "1 to 10 of X"
				ordering: true, order: [],      // (optional sorting)
				pageLength: 10,      // default rows per page

				dom: 'tpi' ,// ✅ ONLY table + pagination + info
				
				language: {
					paginate: {
						previous: '<i class="bi bi-chevron-left"></i>',
						next: '<i class="bi bi-chevron-right"></i>'
					},
					emptyTable: 'No stock data found.'
				}

			});
		});
	</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
