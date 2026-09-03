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
    <span><i class="bi bi-journal-text me-2"></i>Stock Ledger</span>
    <a href="<?= base_url('reports') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card-custom mb-3">
    <div class="card-custom-body">
        <form method="GET" action="<?= base_url('reports/ledger') ?>">
            <div class="row">
				<div class="col-md-4">
					<div class="form-section">
						<label class="form-label">Filter by Project</label>
						<select name="project_id" class="form-control">
							<option value="">All Projects</option>
							<option value="general" <?= $project_id === 'general' ? 'selected' : '' ?>>GENERAL PRODUCTS</option>
							<?php foreach ($projects as $pr): ?>
							<option value="<?= $pr['id'] ?>" <?= $project_id == $pr['id'] ? 'selected' : '' ?>>
								<?= esc($pr['name']) ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				
				<div class="col-md-4">
					<div class="form-section">
						<label class="form-label">Filter by Product</label>
						<select name="product_id" class="form-control">
							<option value="">All Products</option>
							<?php foreach ($products as $p): ?>
							<option value="<?= $p['id'] ?>" <?= $product_id == $p['id'] ? 'selected' : '' ?>>
								<?= esc($p['name']) ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				 <div class="col-md-4" style="display:flex;align-items:flex-end;gap:10px;padding-bottom:14px">
					<button type="submit" class="btn-save">
						<i class="bi bi-search"></i> Filter
					</button>
					 
					 <a href="<?= base_url('reports/ledger') ?>" class="btn btn-sm btn-secondary">
                       <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>
					
					<a href="<?= base_url('reports/ledger-export?' . http_build_query($_GET)) ?>" 
					   class="btn-save" style="background:#16a34a;">
						<i class="bi bi-file-earmark-excel"></i> Export
					</a>
					 
					 <a href="<?= base_url('reports/ledger-pdf?' . http_build_query($_GET)) ?>" 
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
        <table id="ledgerTable" class="table-custom">
            <thead>
                <tr><th>#</th><th>Date</th><th>Product</th><th>Type</th><th>Qty</th><th>Source</th><th>Project</th><th>Reference</th><th>Notes</th></tr>
            </thead>
            <tbody>
                <?php foreach ($ledger as $i => $l): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= $l['transaction_date'] ?></td>
                    <td><?= esc($l['product_name']) ?></td>
                    <td><span class="badge-status badge-<?= strtolower($l['transaction_type']) ?>"><?= $l['transaction_type'] ?></span></td>
                    <td><?= number_format($l['quantity']) ?></td>
                    <td><span class="badge-source"><?= $l['source'] ?></span></td>
                    <td><?= esc($l['project_name'] ?: '-') ?></td>
                    <td><?= esc($l['reference_type'] . ' #' . $l['reference_id']) ?></td>
                    <td><?= esc($l['notes']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	
$(document).ready(function () {
	
	$('#ledgerTable').DataTable({
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
			 emptyTable: 'No ledger entries found.'
		 }
	});

    $('select[name="project_id"]').on('change select2:select', function () {

        var projectId = $(this).val();
        var productDropdown = $('select[name="product_id"]');

        $.ajax({
            url: "<?= base_url('reports/get-products-by-project') ?>",
            type: "GET",
            data: { project_id: projectId },

            success: function (res) {

                productDropdown.empty();
                productDropdown.append('<option value="">All Products</option>');

                res.forEach(function (p) {
                    productDropdown.append(
                        '<option value="'+p.id+'">'+p.name+'</option>'
                    );
                });

                // refresh select2 safely
                if (productDropdown.hasClass("select2-hidden-accessible")) {
                    productDropdown.select2('destroy');
                }

                productDropdown.select2({
                    width: '100%',
                    placeholder: 'Search...'
                });

            }
        });

    });

});
</script>
<?= $this->endSection() ?>

