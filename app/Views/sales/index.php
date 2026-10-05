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

	/* ✅ Force same font for Select2 dropdown text */
	.select2-container--default .select2-selection--single .select2-selection__rendered {
		color: #444 !important;
		line-height: 28px !important;
		font-size: 13px !important;
		font-weight: normal !important;
	}

	/* ✅ Match height + border */
	.select2-container .select2-selection--single {
		height: 30px !important;
		border: 1px solid #e2e8f0 !important;
		border-radius: 6px !important;
	}

	/* ✅ Arrow alignment */
	.select2-container--default .select2-selection--single .select2-selection__arrow {
		height: 28px !important;
	}

	/* ✅ Native dropdown match same font */
	.supplier-filter {
		font-size: 13px !important;
		line-height: 28px !important;
		font-weight: normal !important;
	}

	.dataTables_wrapper .paginate_button i {
		font-size: 12px;
		vertical-align: middle;
	}

	/* Release 4.0A (UI only): compact sales list table so both tabs fit
	   without horizontal scrolling on a 1366px viewport. */
	#salesTable.table-compact th,
	#salesTable.table-compact td {
		padding: 6px 10px;
		font-size: 13px;
		vertical-align: middle;
	}
	/* height belongs to data cells only: on a th (content-box) 38px + 12px padding + 1px border made the
	   header 51px against ~32px on the other tables */
	#salesTable.table-compact td {
		height: 38px;
	}
	#salesTable.table-compact thead th {
		font-size: 12px;
		white-space: nowrap;
	}
	#salesTable.table-compact td.truncate-col,
	#salesTable.table-compact th.truncate-col {
		max-width: 160px;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	#salesTable.table-compact td.invoice-col {
		font-weight: 700;
	}
	#salesTable.table-compact td.amount-pending {
		color: #c2410c;
		font-weight: 700;
		text-align: right;
	}
	#salesTable.table-compact td.amount-paid {
		color: #15803d;
		font-weight: 700;
		text-align: right;
	}
	#salesTable.table-compact .col-hidden {
		display: none;
	}

</style>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap fb-title-one-row">

    <div class="d-flex align-items-center gap-2 flex-nowrap" style="flex-shrink:0;">
        <span class="d-flex align-items-center" style="white-space:nowrap;">
            <i class="bi bi-receipt me-2"></i>Sales
        </span>

        <!-- ✅ CUSTOMER FILTER -->
        <div style="width:200px; flex: 0 0 200px;">
            <select id="customerFilter" class="form-control supplier-filter" style="width:100%; font-size:13px;">
                <option value="ALL">All Customers</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= esc($c['name']) ?>"><?= esc($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Release 4.0B (Phase B): Project filter — Select2 searchable,
             replaces the removed Status filter. Populated from Active
             projects already fetched by Sales::index(). -->
        <div style="width:220px; flex: 0 0 220px;">
            <select id="projectFilter" class="form-control no-search" style="width:100%; font-size:13px;">
                <option value=""></option>
                <option value="ALL">All Projects</option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"><?= esc($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

    </div>
	<div class="custom-search-box mb-1 position-relative">
		<i class="bi bi-search search-icon"></i>
		<input type="text" id="customSearch" class="form-control ps-4" placeholder="Search sales...">
	</div>
    <a href="<?= base_url('sales/create') ?>" class="btn-save"><i class="bi bi-plus-lg"></i> New Sale</a>
</div>

<?php
    $tab = $_GET['tab'] ?? 'active';

    // Release 2.0D (RC1, presentation only): friendly invoice payment-status
    // wording. sales.status enum values (PAID/PARTIAL/UNPAID) and the
    // badge CSS class (badge-paid/badge-partial/badge-unpaid) are unchanged;
    // only the visible badge text changes. A visually-hidden raw-status span
    // is added next to each badge so the existing DataTables status filter
    // (data[8], matched against the raw PARTIAL/UNPAID values) keeps working
    // unmodified.
    $statusLabels = [
        'PAID'    => 'Invoice Paid',
        'PARTIAL' => 'Partial Payment',
        'UNPAID'  => 'Payment Pending',
    ];
?>

<ul class="nav nav-tabs mb-2" style="border-bottom:2px solid #e2e8f0">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'active' ? 'active' : '' ?>"
           href="<?= base_url('sales?tab=active') ?>"
           style="font-weight:600;color:<?= $tab === 'active' ? '#1e40af' : '#64748b' ?>">
            <i class="bi bi-activity me-1"></i> Payment Pending Invoices
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'completed' ? 'active' : '' ?>"
           href="<?= base_url('sales?tab=completed') ?>"
           style="font-weight:600;color:<?= $tab === 'completed' ? '#1e40af' : '#64748b' ?>">
            <i class="bi bi-check2-circle me-1"></i> Paid Invoices
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'billing_completed' ? 'active' : '' ?>"
           href="<?= base_url('sales?tab=billing_completed') ?>"
           style="font-weight:600;color:<?= $tab === 'billing_completed' ? '#1e40af' : '#64748b' ?>">
            <i class="bi bi-flag-fill me-1"></i> Completed Sales
        </a>
    </li>
</ul>

<?php if ($tab === 'active'): ?>
	<!-- Release 4.0A (Phase B) / 4.0B (Phase B): compact Payment Pending
	     table — #, Invoice No, Customer, Project, Invoice Date, Pending
	     Amount, Action. A hidden project_id cell (col-hidden) drives the
	     Project filter without a duplicate visible column. -->
	<div class="card-custom">
    	<div class="table-responsive">
        <table id="salesTable" class="table-custom table-compact">
            <thead>
                <tr>
                    <th class="sno-col">S.No.</th>
                    <th>Invoice No</th>
                    <th class="truncate-col customer-filter-col">Customer</th>
                    <th class="truncate-col">Project</th>
                    <th>Invoice Date</th>
                    <th style="text-align:right">Pending Amount</th>
                    <th class="col-hidden">ProjectId</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $j = 1; foreach ($sales as $s): ?>
				<?php if ($s['status'] != 'PAID' && ($s['project_billing_status'] ?? '') !== 'COMPLETED'): ?>
				<tr>
					<td class="sno-col"><?= $j++ ?></td>
                    <td class="invoice-col"><?= esc($s['invoice_no'] ?: '-') ?></td>
                    <td class="truncate-col customer-col" title="<?= esc($s['customer_name'] ?: '-') ?>"><?= esc($s['customer_name'] ?: '-') ?></td>
                    <td class="truncate-col" title="<?= esc($s['project_name']) ?>"><?= esc($s['project_name']) ?></td>
                    <td><?= esc($s['sale_date']) ?></td>
                    <td class="amount-pending"><?= number_format($s['balance_amount'], 2) ?></td>
                    <td class="col-hidden"><?= (int) ($s['project_id'] ?? 0) ?></td>
                    <td>
						<a href="<?= base_url('sales/edit/' . $s['id']) ?>" class="btn-edit table-action-btn"><i class="bi bi-pencil"></i></a>
                        <a href="<?= base_url('sales/view/' . $s['id']) ?>" class="btn-view table-action-btn"><i class="bi bi-eye"></i> </a>
                        <a href="<?= base_url('payments/create/' . $s['id']) ?>" class="btn-pay" style="width: 65px;"><i class="bi bi-cash"></i>Pay</a>
                        <a href="<?= base_url('sales/delete/' . $s['id']) ?>" class="btn-delete table-action-btn" onclick="return confirm('Delete this sale? Stock will be reversed.')"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <?php endif; ?>
				<?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'completed'): ?>
	<!-- Release 4.0A (Phase C): compact Paid Invoice table — #, Invoice No,
	     Customer, Project, Paid Date, Paid Amount, Action. "Paid Date" uses
	     the sale's updated_at (last modification / when it was marked PAID
	     by SaleModel::recalculatePaymentState()) since sales has no
	     dedicated paid_date column and no new query is permitted. -->
    <div class="card-custom">
        <div class="table-responsive">
            <table id="salesTable" class="table-custom table-compact">
                <thead>
                    <tr>
                        <th class="sno-col">S.No.</th>
                        <th>Invoice No</th>
                        <th class="truncate-col customer-filter-col">Customer</th>
                        <th class="truncate-col">Project</th>
                        <th>Paid Date</th>
                        <th style="text-align:right">Paid Amount</th>
                        <th class="col-hidden">ProjectId</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($sales as $s): ?>
					<?php if ($s['status'] == 'PAID' && ($s['project_billing_status'] ?? '') !== 'COMPLETED'): ?>
					<tr>
						<td class="sno-col"><?= $i++ ?></td>
                        <td class="invoice-col"><?= esc($s['invoice_no'] ?: '-') ?></td>
                        <td class="truncate-col customer-col" title="<?= esc($s['customer_name'] ?: '-') ?>"><?= esc($s['customer_name'] ?: '-') ?></td>
                        <td class="truncate-col" title="<?= esc($s['project_name']) ?>"><?= esc($s['project_name']) ?></td>
                        <td><?= esc($s['updated_at'] ? substr($s['updated_at'], 0, 10) : $s['sale_date']) ?></td>
                        <td class="amount-paid"><?= number_format($s['paid_amount'], 2) ?></td>
                        <td class="col-hidden"><?= (int) ($s['project_id'] ?? 0) ?></td>
                        <td>
                            <a href="<?= base_url('sales/view/' . $s['id']) ?>" class="btn-view table-action-btn">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'billing_completed'): ?>
	<!-- Release 4.0D: Completed Sales tab — shows every invoice belonging to a
	     project whose billing_status = COMPLETED (project.billing_status, set
	     only by Projects::markBillingComplete()), regardless of the invoice's
	     own payment status (PAID/PARTIAL/UNPAID). View-only actions. -->
	<div class="card-custom">
        <div class="table-responsive">
            <table id="salesTable" class="table-custom table-compact">
                <thead>
                    <tr>
                        <th class="sno-col">S.No.</th>
                        <th>Invoice No</th>
                        <th class="truncate-col customer-filter-col">Customer</th>
                        <th class="truncate-col">Project</th>
                        <th>Invoice Date</th>
                        <th style="text-align:right">Invoice Amount</th>
                        <th style="text-align:right">Paid Amount</th>
                        <th style="text-align:right">Balance Amount</th>
                        <th class="col-hidden">ProjectId</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $k = 1; foreach ($sales as $s): ?>
                    <?php if (($s['project_billing_status'] ?? '') === 'COMPLETED'): ?>
                    <tr>
                        <td class="sno-col"><?= $k++ ?></td>
                        <td class="invoice-col"><?= esc($s['invoice_no'] ?: '-') ?></td>
                        <td class="truncate-col customer-col" title="<?= esc($s['customer_name'] ?: '-') ?>"><?= esc($s['customer_name'] ?: '-') ?></td>
                        <td class="truncate-col" title="<?= esc($s['project_name']) ?>"><?= esc($s['project_name']) ?></td>
                        <td><?= esc($s['sale_date']) ?></td>
                        <td style="text-align:right"><?= number_format($s['total_amount'], 2) ?></td>
                        <td class="amount-paid"><?= number_format($s['paid_amount'], 2) ?></td>
                        <td class="amount-pending"><?= number_format($s['balance_amount'], 2) ?></td>
                        <td class="col-hidden"><?= (int) ($s['project_id'] ?? 0) ?></td>
                        <td>
                            <a href="<?= base_url('sales/view/' . $s['id']) ?>" class="btn-view table-action-btn">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
		$(document).ready(function () {

			// ✅ force default "All" selection
			$('#customerFilter').val('ALL');
			$('#projectFilter').val('ALL');

			// Release 4.0B (Phase B/F): #projectFilter carries 'no-search' so
			// the global auto-select2 handler in main.php (layouts/main.php,
			// after the scripts section) skips it; initialized here with its
			// own "Select Project" placeholder, same pattern as
			// assets/js/payments-workflow.js's #projectFilter.
			$('#projectFilter').select2({
				width: '100%',
				placeholder: 'Select Project',
				allowClear: true
			}).val('ALL').trigger('change.select2');

			var table = $('#salesTable').DataTable({
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
					emptyTable: '<?= $tab === 'billing_completed' ? 'No completed-billing invoices found.' : ($tab === 'completed' ? 'No paid invoices found.' : 'No payment pending invoices found.') ?>'
				},

				 columnDefs: [
					{ targets: 2 }, // customer column
					{ targets: 6 }  // hidden project id column
				]
			});

			 $('#customSearch').on('keyup', function () {
				table.search(this.value).draw();
			});

			// ✅ COMBINED FILTER (PROJECT + CUSTOMER)
			// Release 4.0B: Status filter removed; Project filter added in
			// its place. Release 4.0D: Completed Sales tab has a different
			// column count than the other two tabs, so the customer and
			// hidden project-id column indexes are now looked up by class
			// (customer-filter-col / col-hidden) instead of hardcoded.
			var custColIndex = $('#salesTable thead th.customer-filter-col').index();
			var projColIndex = $('#salesTable thead th.col-hidden').index();

			$.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {

				var project  = $('#projectFilter').val();
				var customer = $('#customerFilter').val();

				var tableProject  = data[projColIndex]; // hidden project id column
				var tableCustomer = data[custColIndex]; // customer column

				if (
					(
						project === "ALL" || !project || tableProject === project
					) &&
					(
						customer === "ALL" || customer === "" || tableCustomer.includes(customer)
					)
				) {
					return true;
				}

				return false;
			});

			// trigger redraw on change
			$('#customerFilter, #projectFilter').on('change', function () {
				table.draw();
			});

		});
	</script>
<?= $this->endSection() ?>
