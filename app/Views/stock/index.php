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
    <span><i class="bi bi-box-seam me-2"></i>General Stock</span>
	<div class="custom-search-box mb-1 position-relative">
		<i class="bi bi-search search-icon"></i>
		<input type="text" id="customSearch" class="form-control ps-4" placeholder="Search stock...">
	</div>
    <a href="<?= base_url('stock-entry/create') ?>" class="btn-save">+ Add Stock</a>
</div>

<div class="card-custom">
    <div class="table-responsive">
        <table id="stockTable" class="table-custom">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
					<th style="text-align:center">Current Stock</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($stocks as $i => $s): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= esc($s['product_name']) ?></td>
					<td style="text-align:center"><?= $s['current_stock'] ?></td>
                   <td>
						<a href="<?= base_url('stock-entry/edit/'.$s['id']) ?>" class="btn-edit">
							<i class="bi bi-pencil"></i>
						</a>
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
			var table = $('#stockTable').DataTable({
				paging: true,        // ✅ pagination
				searching: true,    // ❌ remove search box
				lengthChange: false, // ❌ remove "show entries"
				info: false,          // (optional) showing "1 to 10 of X"
				ordering: true,      // (optional sorting)
				pageLength: 10,      // default rows per page

				dom: 'tp' ,// ✅ ONLY table + pagination + info
				
				language: {
					paginate: {
						previous: '<i class="bi bi-chevron-left"></i>',
						next: '<i class="bi bi-chevron-right"></i>'
					}
				}
			});
			
			 $('#customSearch').on('keyup', function () {
				table.search(this.value).draw();
			});
		});
	</script>
<?= $this->endSection() ?>