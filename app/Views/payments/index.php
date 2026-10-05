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

	/* Release 4.0B (Phase F): match Sales List filter row height/spacing. */
	.select2-container--default .select2-selection--single .select2-selection__rendered {
		color: #444 !important;
		line-height: 28px !important;
		font-size: 13px !important;
		font-weight: normal !important;
	}
	.select2-container .select2-selection--single {
		height: 30px !important;
		border: 1px solid #e2e8f0 !important;
		border-radius: 6px !important;
	}
	.select2-container--default .select2-selection--single .select2-selection__arrow {
		height: 28px !important;
	}

</style>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap fb-title-one-row">
    <div class="d-flex align-items-center gap-2 flex-nowrap" style="flex-shrink:0;">
        <span class="d-flex align-items-center" style="white-space:nowrap;">
            <i class="bi bi-cash-stack me-2"></i>Payments
        </span>

        <!-- Release 4.0B.1: Customer filter — Select2 searchable, options
             collected client-side from the Customer column already rendered
             in the table below (view-only, no new query). Placed before the
             Project filter per spec's required layout order. -->
        <div style="width:220px; flex: 0 0 220px;">
            <select id="customerFilter" class="form-control no-search" style="width:100%; font-size:13px;">
                <option value=""></option>
                <option value="ALL">All Customers</option>
            </select>
        </div>

        <!-- Release 4.0B (Phase C): Project filter — Select2 searchable,
             options built from project_name already present on each fetched
             payment row (Payments::index()), no new query. -->
        <div style="width:220px; flex: 0 0 220px;">
            <select id="projectFilter" class="form-control no-search" style="width:100%; font-size:13px;">
                <option value=""></option>
                <option value="ALL">All Projects</option>
                <?php foreach ($projects as $projectName): ?>
                    <option value="<?= esc($projectName) ?>"><?= esc($projectName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
	<div class="custom-search-box mb-1 position-relative">
		<i class="bi bi-search search-icon"></i>
		<input type="text" id="customSearch" class="form-control ps-4" placeholder="Search payment...">
	</div>
    <a href="<?= base_url('payments/create') ?>" class="btn-save"><i class="bi bi-plus-lg"></i> Record Payment</a>
</div>

<div class="card-custom">
    <div class="table-responsive">
        <table id="paymentsTable" class="table-custom">
            <thead>
                <tr><th class="sno-col">S.No.</th><th>Invoice</th><th>Project</th><th>Customer</th><th style="text-align:right">Amount</th><th>Date</th><th>Method</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $i => $p): ?>
                <tr>
                    <td class="sno-col"><?= $i + 1 ?></td>
                    <td><?= esc($p['invoice_no'] ?: 'Sale #' . $p['sale_id']) ?></td>
                    <td><?= esc($p['project_name'] ?: '-') ?></td>
                    <td><?= esc($p['customer_name'] ?: '-') ?></td>
                    <td style="text-align:right"><strong><?= number_format($p['amount'], 2) ?></strong></td>
                    <td><?= $p['payment_date'] ?></td>
                    <td><?= pm_badge($p['method'] ?? '', 'Not recorded') ?><?php if (! empty($p['bank_name'])): ?><br><small class="text-muted"><?= esc($p['bank_name']) ?></small><?php endif; ?></td>
                    <td>
						<a href="<?= base_url('payments/edit/' . $p['id']) ?>" class="btn-edit table-action-btn">
							<i class="bi bi-pencil"></i>
						</a>
                        <a href="<?= base_url('payments/delete/' . $p['id']) ?>" class="btn-delete table-action-btn" onclick="return confirm('Delete this payment?')"><i class="bi bi-trash"></i> </a>
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

			// Release 4.0B.1: Customer filter options collected from the
			// Customer column (index 3) already rendered in the table —
			// no new query, view-only, same pattern used for the Project
			// filter's dropdown source.
			var seenCustomers = {};
			$('#paymentsTable tbody tr').each(function () {
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

			// Release 4.0B (Phase C/F): #projectFilter carries 'no-search'
			// so the global auto-select2 handler in main.php skips it —
			// initialized here with its own "Select Project" placeholder,
			// same pattern as sales/index.php and payments-workflow.js.
			$('#projectFilter').select2({
				width: '100%',
				placeholder: 'Select Project',
				allowClear: true
			}).val('ALL').trigger('change.select2');

			var table = $('#paymentsTable').DataTable({
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
					emptyTable: 'No payments found.'
				}
			});

			 $('#customSearch').on('keyup', function () {
				table.search(this.value).draw();
			});

			// Release 4.0B.1 / 4.0B (Phase C): Customer + Project filters,
			// combined with AND, matched against the visible Customer
			// (index 3) and Project (index 2) columns — no hidden columns
			// needed since Payments::index() already returns both per row.
			$.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
				if (settings.nTable.id !== 'paymentsTable') {
					return true;
				}
				var customer      = $('#customerFilter').val();
				var tableCustomer = data[3]; // customer column
				var customerOk = (customer === "ALL" || !customer || tableCustomer === customer);

				var project      = $('#projectFilter').val();
				var tableProject = data[2]; // project column
				var projectOk = (project === "ALL" || !project || tableProject === project);

				return customerOk && projectOk;
			});

			$('#customerFilter, #projectFilter').on('change', function () {
				table.draw();
			});
		});
	</script>
<?= $this->endSection() ?>
