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

	/* compact filter toolbar */
	.filter-toolbar .form-section { margin-bottom: 0; }
	.filter-toolbar .form-label { font-size: .72rem; margin-bottom: 3px; }
	.filter-toolbar .form-control { padding: 6px 10px; font-size: .82rem; height: auto; }
	.filter-toolbar .btn-save,
	.filter-toolbar .btn-cancel { padding: 6px 12px; font-size: .8rem; white-space: nowrap; }

	#srTable.table-custom th,
	#srTable.table-custom td { padding: 7px 8px; font-size: .78rem; }
	/* icon-only action buttons: tighter than the shared 14px side padding so all three fit in one row */
	#srTable .btn-view, #srTable .btn-edit, #srTable .btn-delete { padding: 0 9px; }
	/* keep receipt no, date and the action buttons on one line (10 columns is tight) */
	#srTable td:nth-child(1),
	#srTable td:nth-child(2),
	#srTable td:last-child { white-space: nowrap; }

	/* Payment status badge — Pending orange / Partial blue / Paid green */
	.badge-sr {
		display: inline-block;
		padding: 3px 10px;
		border-radius: 20px;
		font-size: .72rem;
		font-weight: 600;
	}
	.badge-sr.badge-pending { background:#ffedd5; color:#c2410c; }
	.badge-sr.badge-partial { background:#dbeafe; color:#1d4ed8; }
	.badge-sr.badge-paid    { background:#dcfce7; color:#166534; }

	.sr-breadcrumb { margin-bottom: 8px; }
	.sr-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.sr-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.sr-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.sr-breadcrumb .breadcrumb-item.active { color: #64748b; }

</style>

<nav aria-label="breadcrumb" class="sr-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Transactions</li>
        <li class="breadcrumb-item active" aria-current="page">Service Received</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-receipt-cutoff me-2"></i>Service Received</span>
    <div class="d-flex gap-2">
        <a href="<?= base_url('service-receipts/create') ?>" class="btn-save"><i class="bi bi-plus-lg"></i> New Service Receipt</a>
        <a href="javascript:void(0)" class="btn-cancel" title="Export (coming soon)" onclick="alert('Export will be available in a future release.')"><i class="bi bi-file-earmark-excel"></i> Export</a>
    </div>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-receipt"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_receipts) ?></div>
                <div class="kpi-label">Total Service Receipts</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_invoice_amount, 2) ?></div>
                <div class="kpi-label">Total Invoice Amount</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_received_amount, 2) ?></div>
                <div class="kpi-label">Total Received Amount</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-exclamation-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_outstanding, 2) ?></div>
                <div class="kpi-label">Outstanding Amount</div>
            </div>
        </div>
    </div>
</div>

<!-- FILTER TOOLBAR (client-side, same DataTable-column-search pattern as General Purchase / Supplier Payments) -->
<div class="card-custom mb-3">
    <div class="card-custom-header">Filters</div>
    <div class="card-custom-body">
        <div class="row g-2 align-items-end filter-toolbar">
            <div class="col-6 col-md-3">
                <div class="form-section">
                    <label class="form-label">Customer</label>
                    <select id="filterCustomer" class="form-control">
                        <option value="">All Customers</option>
                        <?php foreach (array_unique(array_column($receipts, 'customer_name')) as $name): ?>
                        <?php if ($name): ?>
                        <option value="<?= esc($name) ?>"><?= esc($name) ?></option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">Receipt Type</label>
                    <select id="filterType" class="form-control no-search">
                        <option value="">All</option>
                        <option value="Invoice">Invoice</option>
                        <option value="Direct">Direct</option>
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">Payment Status</label>
                    <select id="filterPaymentStatus" class="form-control no-search">
                        <option value="">All</option>
                        <option value="Pending">Pending</option>
                        <option value="Partial">Partial</option>
                        <option value="Paid">Paid</option>
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">From Date</label>
                    <input type="date" id="filterFromDate" class="form-control">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">To Date</label>
                    <input type="date" id="filterToDate" class="form-control">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="custom-search-box position-relative">
                    <label class="form-label">Search</label>
                    <i class="bi bi-search search-icon" style="top:34px;"></i>
                    <input type="text" id="customSearch" class="form-control ps-4" placeholder="Search receipt no, person...">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <button type="button" class="btn-cancel w-100" id="resetFilters"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
            </div>
        </div>
    </div>
</div>

<div class="card-custom">
    <div class="table-responsive">
        <table id="srTable" class="table-custom">
            <thead>
                <tr>
                    <th>Receipt No</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Receipt Type</th>
                    <th>Attended Person</th>
                    <th style="text-align:right">Grand Total</th>
                    <th style="text-align:right">Received Amount</th>
                    <th style="text-align:right">Outstanding</th>
                    <th>Payment Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($receipts as $r): ?>
                <tr>
                    <td><?= esc($r['receipt_no']) ?></td>
                    <td><?= esc($r['receipt_date']) ?></td>
                    <td><?= esc($r['customer_name'] ?: '-') ?></td>
                    <td><?= esc(ucfirst(strtolower($r['receipt_type']))) ?></td>
                    <td><?= esc($r['attended_person'] ?: '-') ?></td>
                    <td style="text-align:right" data-order="<?= esc($r['grand_total']) ?>"><?= number_format((float) $r['grand_total'], 2) ?></td>
                    <td style="text-align:right" data-order="<?= esc($r['received_amount']) ?>"><?= number_format((float) $r['received_amount'], 2) ?></td>
                    <td style="text-align:right" data-order="<?= esc($r['outstanding_amount']) ?>"><?= number_format((float) $r['outstanding_amount'], 2) ?></td>
                    <td><span class="badge-sr badge-<?= esc(strtolower($r['payment_status'])) ?>"><?= esc(ucfirst(strtolower($r['payment_status']))) ?></span></td>
                    <td>
                        <a href="<?= base_url('service-receipts/view/' . $r['id']) ?>" class="btn-view" title="View"><i class="bi bi-eye"></i></a>
                        <a href="<?= base_url('service-receipts/edit/' . $r['id']) ?>" class="btn-edit" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a href="javascript:void(0)" class="btn-delete" title="Delete" data-id="<?= (int) $r['id'] ?>" data-no="<?= esc($r['receipt_no']) ?>" onclick="confirmDeleteReceipt(this)"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Delete confirmation modal (delete is a POST route) -->
<div class="modal fade" id="deleteReceiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Delete Service Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="deleteReceiptError" class="alert alert-danger d-none" role="alert"></div>
                <p class="mb-1">Delete service receipt <strong id="deleteReceiptNo"></strong>?</p>
                <p class="mb-0 text-muted" style="font-size:.85rem;">Its service items will be removed too. This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal"><i class="bi bi-x"></i> Cancel</button>
                <button type="button" class="btn-save" id="deleteReceiptConfirmBtn" style="background:#dc2626;">
                    <span id="deleteReceiptSpinner" class="spinner-border spinner-border-sm d-none"></span>
                    <i class="bi bi-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
var deleteReceiptId = null;

	function confirmDeleteReceipt(el) {
		deleteReceiptId = $(el).data('id');
		$('#deleteReceiptNo').text($(el).data('no'));
		$('#deleteReceiptError').addClass('d-none').text('');
		bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteReceiptModal')).show();
	}

	$('#deleteReceiptConfirmBtn').on('click', function () {
		var btn = $(this);
		var spinner = $('#deleteReceiptSpinner');
		var errorBanner = $('#deleteReceiptError');

		errorBanner.addClass('d-none').text('');
		btn.prop('disabled', true);
		spinner.removeClass('d-none');

		$.ajax({
			url: "<?= base_url('service-receipts/delete/') ?>" + deleteReceiptId,
			type: 'POST',
			dataType: 'json'
		}).done(function (resp) {
			if (resp.status) {
				window.location.reload();
			} else {
				errorBanner.text((resp.errors && resp.errors.join(' ')) || 'Failed to delete service receipt.').removeClass('d-none');
				btn.prop('disabled', false);
				spinner.addClass('d-none');
			}
		}).fail(function (xhr) {
			var data = xhr.responseJSON;
			errorBanner.text((data && data.errors && data.errors.join(' ')) || 'A network error occurred. Please try again.').removeClass('d-none');
			btn.prop('disabled', false);
			spinner.addClass('d-none');
		});
	});

	$(document).ready(function () {
		var table = $('#srTable').DataTable({
			paging: true,
			searching: true,
			lengthChange: false,
			info: false,
			ordering: true,
			order: [],
			pageLength: 10,
			dom: 'tp',
			columnDefs: [{ orderable: false, targets: 9 }],
			language: {
				paginate: {
					previous: '<i class="bi bi-chevron-left"></i>',
					next: '<i class="bi bi-chevron-right"></i>'
				},
				emptyTable: 'No service receipts found.'
			}
		});

		$('#customSearch').on('keyup', function () {
			table.search(this.value).draw();
		});
		$('#filterCustomer').on('change', function () {
			table.column(2).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw();
		});
		$('#filterType').on('change', function () {
			table.column(3).search(this.value ? '^' + this.value + '$' : '', true, false).draw();
		});
		$('#filterPaymentStatus').on('change', function () {
			table.column(8).search(this.value ? '^' + this.value + '$' : '', true, false).draw();
		});

		// Date range — custom DataTables search plugin scoped to this table only
		$.fn.dataTable.ext.search.push(function (settings, data) {
			if (settings.nTable.id !== 'srTable') return true;

			var from = $('#filterFromDate').val();
			var to   = $('#filterToDate').val();
			var rowDate = data[1]; // Date column

			if (from && rowDate < from) return false;
			if (to && rowDate > to) return false;
			return true;
		});
		$('#filterFromDate, #filterToDate').on('change', function () {
			table.draw();
		});

		$('#resetFilters').on('click', function () {
			$('#filterFromDate, #filterToDate, #customSearch').val('');
			$('#filterCustomer, #filterType, #filterPaymentStatus').val('').trigger('change');
			table.search('').columns().search('').draw();
		});
	});
</script>
<?= $this->endSection() ?>
