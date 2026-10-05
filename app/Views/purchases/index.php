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
	
	/* ✅ Supplier filter fix */
	.supplier-filter:not(.select2-hidden-accessible) {
		width: 100% !important;
		min-width: 100% !important;
		max-width: 100% !important;

		display: block !important;

		white-space: nowrap !important;
		overflow: hidden !important;
		text-overflow: ellipsis !important;

		font-size: 13px !important;
		height: 30px !important;

		appearance: none !important;
		-webkit-appearance: none !important;
		-moz-appearance: none !important;
	}
	
	/* ✅ Select2 text styling */
	.select2-container--default .select2-selection--single .select2-selection__rendered {
		color: #444;
		line-height: 28px;
		font-size: 13px;
		font-weight: normal !important;
	}
	
	/* ✅ Select2 full field match */
	.select2-container .select2-selection--single {
		height: 30px !important;
		border: 1px solid #e2e8f0 !important;
		border-radius: 6px !important;
	}

	.select2-container--default .select2-selection--single .select2-selection__arrow {
		height: 28px !important;
	}

	.supplier-filter option {
		font-size: 13px;
	}
	
	.dataTables_wrapper .paginate_button i {
		font-size: 12px;
		vertical-align: middle;
	}

	
</style>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap fb-title-one-row">

    <div class="d-flex align-items-center gap-2 flex-nowrap" style="flex-shrink:0;">
        <span class="d-flex align-items-center" style="white-space:nowrap;">
			<i class="bi bi-cart-plus me-2"></i>Purchases
		</span>

        <!-- ✅ SUPPLIER FILTER -->
        <div style="width:200px; flex: 0 0 200px;">
    		<select id="supplierFilter" class="form-control supplier-filter" style="width:100%; font-size:13px;">
				<option value="">All Suppliers</option>
				<?php foreach ($suppliers as $s): ?>
					<option value="<?= esc($s['name']) ?>"><?= esc($s['name']) ?></option>
				<?php endforeach; ?>
        	</select>
		</div>
    </div>
	<div class="custom-search-box mb-1 position-relative">
		<i class="bi bi-search search-icon"></i>
		<input type="text" id="customSearch" class="form-control ps-4" placeholder="Search purchase...">
	</div>
    <a href="<?= base_url('purchases/create') ?>" class="btn-save"><i class="bi bi-plus-lg"></i> New Purchase</a>
</div>

<div class="card-custom">
    <div class="table-responsive">
        <table id="purchaseTable" class="table-custom">
            <thead>
                <tr><th class="sno-col">S.No.</th><th>Invoice</th><th>Supplier</th><th>Project</th><th>Source</th><th>Date</th><th style="text-align:right">Total</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($purchases as $i => $p): ?>
                <tr>
                    <td class="sno-col"><?= $i + 1 ?></td>
                    <td><?= esc($p['invoice_no'] ?: '-') ?></td>
                    <td class="supplier-col"><?= esc($p['supplier_name']) ?></td>
                    <td><?= esc($p['project_name'] ?: '-') ?></td>
                    <td><span class="badge-source">PROJECT</span></td>
                    <td><?= $p['purchase_date'] ?></td>
                    <td style="text-align:right"><?= number_format($p['total_amount'], 2) ?></td>
                    <td>
						<a href="<?= base_url('purchases/edit/' . $p['id']) ?>" class="btn-edit table-action-btn"><i class="bi bi-pencil"></i> </a>
                        <a href="<?= base_url('purchases/view/' . $p['id']) ?>" class="btn-view table-action-btn"><i class="bi bi-eye"></i> </a>
                        <a href="<?= base_url('purchases/delete/' . $p['id']) ?>" class="btn-delete table-action-btn" onclick="return confirm('Delete this purchase? Stock will be reversed.')"><i class="bi bi-trash"></i></a>
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
		$(document).ready(function () {
			var table = $('#purchaseTable').DataTable({
				paging: true,        // ✅ pagination
				searching: true,    // ❌ remove search box
				lengthChange: false, // ❌ remove "show entries"
				info: false,          // (optional) showing "1 to 10 of X"
				ordering: true, order: [],      // (optional sorting)
				pageLength: 10,      // default rows per page

				dom: 'tp' ,// ✅ ONLY table + pagination + info
				
				language: {
					paginate: {
						previous: '<i class="bi bi-chevron-left"></i>',
						next: '<i class="bi bi-chevron-right"></i>'
					},
					emptyTable: 'No purchases found.'
				},

				 columnDefs: [
					 { targets: 2 } // supplier column index
				 ]
			});
			
			 $('#customSearch').on('keyup', function () {
				table.search(this.value).draw();
			});
			
			// ✅ SUPPLIER FILTER
			$('#supplierFilter').on('change', function () {
				var val = $(this).val();
				table.column(2).search(val).draw();
			});
			
		});
	</script>
<?= $this->endSection() ?>
