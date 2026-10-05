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

	/* Release 4.9.0AF: one-row compact filter bar (same metrics/pattern as
	   Supplier Payments Transactions tab's .sp-f-* bar) so this reads as a
	   transaction list toolbar, not a report filter panel. */
	.sr-filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
	.sr-filters .sr-field { position: relative; min-width: 0; }
	.sr-f-customer { flex: 0 1 240px; }
	.sr-f-type { flex: 0 1 150px; }
	.sr-f-search { flex: 1 1 220px; max-width: 320px; }
	.sr-filters .form-control { padding: 6px 10px; font-size: .82rem; height: 34px; }
	.sr-filters .form-control:focus { border-color: #2F7E8A; box-shadow: 0 0 0 2px rgba(47,126,138,0.15); }
	.sr-f-search .search-icon { position: absolute; left: 10px; top: 11px; color: #94a3b8; font-size: 12px; pointer-events: none; }
	.sr-f-search .form-control { padding-left: 28px; }
	.sr-filters .btn-cancel { padding: 6px 14px; font-size: .8rem; white-space: nowrap; height: 34px; }
	@media (max-width: 575.98px) {
		.sr-actions .btn-save, .sr-actions .btn-cancel { padding: 6px 10px; font-size: .78rem; white-space: nowrap; }
		.sr-f-customer, .sr-f-type, .sr-f-search { flex: 1 1 100%; max-width: 100%; }
	}

	#srTable.table-custom th,
	#srTable.table-custom td { padding: 5px 8px; font-size: .78rem; vertical-align: middle; }
	/* keep receipt no, date and the action buttons on one line (8 columns) */
	#srTable td:nth-child(2),
	#srTable td:nth-child(3),
	#srTable td:last-child { white-space: nowrap; }

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
    <div class="d-flex gap-2 flex-wrap sr-actions">
        <a href="<?= base_url('service-receipts/create') ?>" class="btn-save"><i class="bi bi-plus-lg"></i> New Service Receipt</a>
        <a href="javascript:void(0)" class="btn-cancel" title="Export (coming soon)" onclick="alert('Export will be available in a future release.')"><i class="bi bi-file-earmark-excel"></i> Export</a>
    </div>
</div>

<!-- LIST TOOLBAR (client-side DataTable column search; one compact row, no card) -->
<div class="sr-filters mb-2">
    <div class="sr-field sr-f-customer">
        <select id="filterCustomer" class="form-control" aria-label="Customer">
            <option value="">All Customers</option>
            <?php foreach (array_unique(array_column($receipts, 'customer_name')) as $name): ?>
            <?php if ($name): ?>
            <option value="<?= esc($name) ?>"><?= esc($name) ?></option>
            <?php endif; ?>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="sr-field sr-f-type">
        <select id="filterType" class="form-control no-search" aria-label="Receipt Type">
            <option value="">All Types</option>
            <option value="Invoice">Invoice</option>
            <option value="Direct">Direct</option>
        </select>
    </div>
    <div class="sr-field sr-f-search">
        <i class="bi bi-search search-icon"></i>
        <input type="text" id="customSearch" class="form-control" placeholder="Search receipt no, person..." aria-label="Search">
    </div>
    <button type="button" class="btn-cancel sr-f-reset" id="resetFilters"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
</div>

<div class="card-custom">
    <div class="table-responsive">
        <table id="srTable" class="table-custom">
            <thead>
                <tr>
                    <th class="sno-col">S.No.</th>
                    <th>Receipt No</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Receipt Type</th>
                    <th>Attended Person</th>
                    <th style="text-align:right">Amount Received</th>
                    <th>Payment Mode</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($receipts as $r): ?>
                <tr>
                    <td class="sno-col" data-label="S.No."></td>
                    <td><?= esc($r['receipt_no']) ?></td>
                    <td><?= esc($r['receipt_date']) ?></td>
                    <td><?= esc($r['customer_name'] ?: '-') ?></td>
                    <td><?= esc(ucfirst(strtolower($r['receipt_type']))) ?></td>
                    <td><?= esc($r['attended_person'] ?: '-') ?></td>
                    <td style="text-align:right" data-order="<?= esc($r['received_amount']) ?>">₹<?= number_format((float) $r['received_amount'], 2) ?></td>
                    <td><?= esc(pm_label($r['payment_mode'], '-')) ?></td>
                    <td>
                        <a href="<?= base_url('service-receipts/view/' . $r['id']) ?>" class="btn-view table-action-btn" title="View"><i class="bi bi-eye"></i></a>
                        <a href="<?= base_url('service-receipts/edit/' . $r['id']) ?>" class="btn-edit table-action-btn" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a href="javascript:void(0)" class="btn-delete table-action-btn" title="Delete" data-id="<?= (int) $r['id'] ?>" data-no="<?= esc($r['receipt_no']) ?>" onclick="confirmDeleteReceipt(this)"><i class="bi bi-trash"></i></a>
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
			columnDefs: [{ orderable: false, targets: 8 }],
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
			table.column(3).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw();
		});
		$('#filterType').on('change', function () {
			table.column(4).search(this.value ? '^' + this.value + '$' : '', true, false).draw();
		});

		$('#resetFilters').on('click', function () {
			$('#customSearch').val('');
			$('#filterCustomer, #filterType').val('').trigger('change');
			table.search('').columns().search('').draw();
		});
	});
</script>
<?= $this->endSection() ?>
