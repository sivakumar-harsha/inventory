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

	.badge-inactive { background: #fee2e2; color: #b91c1c; }

</style>

<div class="page-title">
    <span><i class="bi bi-tags me-2"></i>Expense Categories</span>
	<div class="custom-search-box mb-1 position-relative">
		<i class="bi bi-search search-icon"></i>
		<input type="text" id="customSearch" class="form-control ps-4" placeholder="Search category...">
	</div>
    <a href="<?= base_url('expense-categories/create') ?>" class="btn-save"><i class="bi bi-plus-lg"></i> Add Category</a>
</div>

<div class="card-custom">
    <div class="table-responsive">
        <table id="categoryTable" class="table-custom">
            <thead>
                <tr><th>#</th><th>Category Name</th><th>Status</th><th>Created Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $i => $c): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= esc($c['category_name']) ?></td>
                    <td><span class="badge-status badge-<?= strtolower($c['status']) ?>"><?= esc($c['status']) ?></span></td>
                    <td><?= $c['created_at'] ? date('d-m-Y', strtotime($c['created_at'])) : '' ?></td>
                    <td>
                        <a href="<?= base_url('expense-categories/edit/' . $c['id']) ?>" class="btn-edit"><i class="bi bi-pencil"></i> </a>
                        <a href="<?= base_url('expense-categories/delete/' . $c['id']) ?>" class="btn-delete" onclick="return confirm('Delete this expense category?')"><i class="bi bi-trash"></i> </a>
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
			var table = $('#categoryTable').DataTable({
				paging: true,
				searching: true,
				lengthChange: false,
				info: false,
				ordering: true,
				pageLength: 10,

				dom: 'tp',

				language: {
					paginate: {
						previous: '<i class="bi bi-chevron-left"></i>',
						next: '<i class="bi bi-chevron-right"></i>'
					},
					emptyTable: 'No expense categories found.'
				}
			});

			 $('#customSearch').on('keyup', function () {
				table.search(this.value).draw();
			});
		});
	</script>
<?= $this->endSection() ?>
