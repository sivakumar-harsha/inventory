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
	.select2-container--default .select2-selection--single .select2-selection__rendered {
		color: #444 !important;
		line-height: 28px !important;
		font-size: 13px !important;
		font-weight: normal !important;
	}
	.select2-container .select2-selection--single {
		height: 32px !important;
		border: 1px solid #e2e8f0 !important;
		border-radius: 6px !important;
	}
	.select2-container--default .select2-selection--single .select2-selection__arrow {
		height: 30px !important;
	}
</style>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <div class="d-flex align-items-center gap-2 flex-nowrap" style="flex-shrink:0;">
        <span class="d-flex align-items-center" style="white-space:nowrap;">
            <i class="bi bi-piggy-bank me-2"></i>Project Cash Receipts
        </span>

        <div style="width:220px; flex: 0 0 220px;">
            <select id="customerFilter" class="form-control no-search" style="width:100%; font-size:13px;">
                <option value=""></option>
                <option value="ALL">All Customers</option>
            </select>
        </div>

        <div style="width:220px; flex: 0 0 220px;">
            <select id="projectFilter" class="form-control no-search" style="width:100%; font-size:13px;">
                <option value=""></option>
                <option value="ALL">All Projects</option>
                <?php
                    $seenProjects = [];
                    foreach ($receipts as $r) {
                        if (!empty($r['project_name'])) { $seenProjects[$r['project_name']] = true; }
                    }
                    $projectNames = array_keys($seenProjects);
                    sort($projectNames);
                ?>
                <?php foreach ($projectNames as $projectName): ?>
                    <option value="<?= esc($projectName) ?>"><?= esc($projectName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
	<div class="custom-search-box mb-1 position-relative">
		<i class="bi bi-search search-icon"></i>
		<input type="text" id="customSearch" class="form-control ps-4" placeholder="Search receipt...">
	</div>
    <a href="<?= base_url('project-cash-receipts/create') ?>" class="btn-save"><i class="bi bi-plus-lg"></i> Receive Cash</a>
</div>

<div class="card-custom">
    <div class="table-responsive">
        <table id="cashReceiptsTable" class="table-custom">
            <thead>
                <tr>
                    <th>#</th><th>Receipt No</th><th>Project</th><th>Customer</th>
                    <th>Date</th><th>Method</th><th style="text-align:right">Amount</th>
                    <th>Reference</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($receipts as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= esc($r['receipt_no']) ?></td>
                    <td><?= esc($r['project_name'] ?: '-') ?></td>
                    <td><?= esc($r['customer_name'] ?: '-') ?></td>
                    <td><?= $r['receipt_date'] ?></td>
                    <td><?= $r['payment_method'] ?></td>
                    <td style="text-align:right"><strong><?= number_format($r['amount'], 2) ?></strong></td>
                    <td><?= esc($r['reference'] ?: '-') ?></td>
                    <td>
						<a href="<?= base_url('project-cash-receipts/edit/' . $r['id']) ?>" class="btn-edit">
							<i class="bi bi-pencil"></i>
						</a>
                        <a href="<?= base_url('project-cash-receipts/delete/' . $r['id']) ?>" class="btn-delete" onclick="return confirm('Delete this cash receipt?')"><i class="bi bi-trash"></i> </a>
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
		var seenCustomers = {};
		$('#cashReceiptsTable tbody tr').each(function () {
			var name = $.trim($(this).find('td').eq(3).text());
			if (name && name !== '-') { seenCustomers[name] = true; }
		});
		var customerNames = Object.keys(seenCustomers).sort();
		customerNames.forEach(function (name) {
			$('#customerFilter').append($('<option>', { value: name, text: name }));
		});

		$('#customerFilter').select2({
			width: '100%',
			placeholder: 'Select Customer',
			allowClear: true
		}).val('ALL').trigger('change.select2');

		$('#projectFilter').select2({
			width: '100%',
			placeholder: 'Select Project',
			allowClear: true
		}).val('ALL').trigger('change.select2');

		var table = $('#cashReceiptsTable').DataTable({
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
				emptyTable: 'No project cash receipts found.'
			}
		});

		$('#customSearch').on('keyup', function () {
			table.search(this.value).draw();
		});

		$.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
			if (settings.nTable.id !== 'cashReceiptsTable') {
				return true;
			}
			var customer      = $('#customerFilter').val();
			var tableCustomer = data[3];
			var customerOk = (customer === "ALL" || !customer || tableCustomer === customer);

			var project      = $('#projectFilter').val();
			var tableProject = data[2];
			var projectOk = (project === "ALL" || !project || tableProject === project);

			return customerOk && projectOk;
		});

		$('#customerFilter, #projectFilter').on('change', function () {
			table.draw();
		});
	});
</script>
<?= $this->endSection() ?>
