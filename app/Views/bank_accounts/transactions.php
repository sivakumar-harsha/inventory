<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
	.ba-breadcrumb { margin-bottom: 8px; }
	.ba-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.ba-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.ba-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.ba-breadcrumb .breadcrumb-item.active { color: #64748b; }

	.balance-card {
		position: sticky;
		top: 12px;
	}
	@media (max-width: 991px) {
		.balance-card { position: static !important; }
	}

	.type-toggle { display:flex; gap:8px; margin-bottom: 12px; }
	.type-btn {
		flex: 1;
		padding: 10px;
		border-radius: 8px;
		border: 1px solid #e2e8f0;
		background: #f8fafc;
		color: #334155;
		text-align: center;
		font-size: .82rem;
		font-weight: 600;
		cursor: pointer;
	}
	.type-btn.active.type-deposit { background: #dcfce7; border-color: #16a34a; color: #166534; }
	.type-btn.active.type-withdrawal { background: #fee2e2; border-color: #dc2626; color: #991b1b; }
	.type-btn.active.type-transfer { background: #dbeafe; border-color: #2563eb; color: #1e3a8a; }

	.preview-box {
		border: 1px dashed #cbd5e1;
		border-radius: 8px;
		padding: 12px;
		margin-top: 12px;
		background: #f8fafc;
	}
	.preview-box .preview-balance { font-size: 1.2rem; font-weight: 700; }
	.preview-box .preview-balance.negative { color: #b91c1c; }
	.preview-warning { color: #b91c1c; font-size: .78rem; margin-top: 6px; }

	.badge-type {
		display: inline-block;
		padding: 2px 8px;
		border-radius: 6px;
		font-size: .7rem;
		font-weight: 700;
	}
	.badge-type.type-deposit { background:#dcfce7; color:#166534; }
	.badge-type.type-withdrawal { background:#fee2e2; color:#991b1b; }
	.badge-type.type-transfer { background:#dbeafe; color:#1e3a8a; }

	.ledger-table-wrap { overflow-x: auto; }
	#statementTable.table-custom th,
	#statementTable.table-custom td { padding: 7px 10px; font-size: .78rem; white-space: nowrap; }
	#statementTable.table-custom td.desc-cell { white-space: normal; min-width: 180px; }

	.filter-toolbar .form-label { font-size: .72rem; margin-bottom: 3px; }
	.filter-toolbar .form-control { padding: 6px 10px; font-size: .82rem; height: auto; }

	@media print {
	    .page-title a, .btn-cancel, .btn-save, .ba-breadcrumb, .filter-toolbar, .transaction-entry-card, .main-sidebar, .main-header { display: none !important; }
	}
</style>

<nav aria-label="breadcrumb" class="ba-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Accounts</li>
        <li class="breadcrumb-item"><a href="<?= base_url('bank-accounts') ?>">Bank Accounts</a></li>
        <li class="breadcrumb-item active" aria-current="page">Transactions — <?= esc($account['bank_name']) ?></li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span><i class="bi bi-journal-text me-2"></i><?= esc($account['bank_name']) ?> — <?= esc($account['account_name']) ?></span>
    <div class="d-flex gap-2">
        <a href="javascript:window.print()" class="btn-save" style="background:#6c757d;"><i class="bi bi-printer"></i> Print</a>
        <a href="javascript:void(0)" class="btn-cancel" title="Export (coming soon)" onclick="alert('Export will be available in a future release.')"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
        <a href="<?= base_url('bank-accounts') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-custom balance-card mb-3">
            <div class="card-custom-header">Current Balance</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td>Account Number</td><td style="text-align:right"><?= esc($account['account_number']) ?></td></tr>
                    <tr><td>Opening Balance</td><td style="text-align:right"><?= number_format((float) $account['opening_balance'], 2) ?></td></tr>
                    <tr><td><strong>Current Balance</strong></td><td style="text-align:right"><strong id="currentBalanceDisplay"><?= number_format((float) $account['current_balance'], 2) ?></strong></td></tr>
                </table>
            </div>
        </div>

        <div class="card-custom transaction-entry-card">
            <div class="card-custom-header">New Transaction</div>
            <div class="card-custom-body">
                <form id="transactionForm">
                    <input type="hidden" name="bank_account_id" value="<?= (int) $account['id'] ?>">
                    <input type="hidden" name="transaction_type" id="transactionType" value="DEPOSIT">

                    <div class="type-toggle">
                        <div class="type-btn active type-deposit" data-type="DEPOSIT">Deposit</div>
                        <div class="type-btn type-withdrawal" data-type="WITHDRAWAL">Withdrawal</div>
                        <div class="type-btn type-transfer" data-type="TRANSFER">Transfer</div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Date *</label>
                        <input type="date" name="transaction_date" id="txnDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Amount *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="txnAmount" class="form-control" required>
                    </div>

                    <div class="mb-2" id="destinationBankGroup" style="display:none;">
                        <label class="form-label">Destination Bank *</label>
                        <select name="transfer_bank_account_id" id="destinationBank" class="form-control">
                            <option value="">Select destination account</option>
                            <?php foreach ($otherAccounts as $oa): ?>
                            <option value="<?= (int) $oa['id'] ?>"><?= esc($oa['bank_name']) ?> — <?= esc($oa['account_name']) ?> (<?= esc($oa['account_number']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Reference Number</label>
                        <input type="text" name="reference_no" id="txnReference" class="form-control">
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" id="txnRemarks" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="preview-box">
                        <div>Balance After Transaction</div>
                        <div class="preview-balance" id="previewBalance"><?= number_format((float) $account['current_balance'], 2) ?></div>
                        <div class="preview-warning" id="previewWarning" style="display:none;"></div>
                    </div>

                    <div id="formErrors" class="alert alert-danger mt-3" style="display:none;"></div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn-save" id="saveTxnBtn"><i class="bi bi-check-lg"></i> Save Transaction</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card-custom mb-3">
            <div class="card-custom-header">Filters</div>
            <div class="card-custom-body">
                <div class="row g-2 align-items-end filter-toolbar">
                    <div class="col-6 col-md-4">
                        <div class="form-section">
                            <label class="form-label">From Date</label>
                            <input type="date" id="filterFromDate" class="form-control">
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="form-section">
                            <label class="form-label">To Date</label>
                            <input type="date" id="filterToDate" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom">
            <div class="card-custom-header">Bank Statement</div>
            <div class="ledger-table-wrap">
                <table id="statementTable" class="table-custom">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Reference</th>
                            <th>Remarks</th>
                            <th style="text-align:right">Deposit</th>
                            <th style="text-align:right">Withdrawal</th>
                            <th style="text-align:right">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= esc($account['created_at'] ? date('Y-m-d', strtotime($account['created_at'])) : '-') ?></td>
                            <td><span class="badge-type type-deposit">Opening</span></td>
                            <td>-</td>
                            <td class="desc-cell">Opening Balance</td>
                            <td style="text-align:right">-</td>
                            <td style="text-align:right">-</td>
                            <td style="text-align:right"><?= number_format((float) $account['opening_balance'], 2) ?></td>
                        </tr>
                        <?php if (empty($transactions)): ?>
                        <tr><td colspan="7" style="text-align:center; color:#94a3b8;">No transactions posted yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($transactions as $t): ?>
                        <tr data-date="<?= esc($t['date']) ?>">
                            <td><?= esc($t['date']) ?></td>
                            <td><span class="badge-type type-<?= strpos($t['type'], 'TRANSFER') === 0 ? 'transfer' : strtolower($t['type']) ?>"><?= esc(ucwords(strtolower(str_replace('_', ' ', $t['type'])))) ?></span></td>
                            <td><?= esc($t['reference_no'] ?: '-') ?></td>
                            <td class="desc-cell"><?= esc($t['remarks'] ?: '-') ?></td>
                            <td style="text-align:right"><?= $t['credit'] > 0 ? number_format($t['credit'], 2) : '-' ?></td>
                            <td style="text-align:right"><?= $t['debit'] > 0 ? number_format($t['debit'], 2) : '-' ?></td>
                            <td style="text-align:right"><?= number_format($t['balance'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" style="text-align:right"><strong>Opening Balance</strong></td>
                            <td style="text-align:right"><strong><?= number_format((float) $account['opening_balance'], 2) ?></strong></td>
                        </tr>
                        <tr>
                            <td colspan="6" style="text-align:right"><strong>Closing Balance</strong></td>
                            <td style="text-align:right"><strong><?= number_format((float) $account['current_balance'], 2) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	var currentBalance = <?= (float) $account['current_balance'] ?>;

	function updatePreview() {
		var type   = $('#transactionType').val();
		var amount = parseFloat($('#txnAmount').val() || 0);
		var preview = currentBalance;

		if (type === 'DEPOSIT') {
			preview = currentBalance + amount;
		} else {
			preview = currentBalance - amount;
		}

		$('#previewBalance').text(preview.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
		$('#previewBalance').toggleClass('negative', preview < 0);

		var warning = '';
		if ((type === 'WITHDRAWAL' || type === 'TRANSFER') && amount > currentBalance) {
			warning = 'This amount exceeds the current balance of ' + currentBalance.toFixed(2) + '.';
		}
		if (type === 'TRANSFER') {
			var dest = $('#destinationBank').val();
			var source = $('input[name="bank_account_id"]').val();
			if (dest && dest === source) {
				warning = 'Transfer source and destination must differ.';
			}
		}
		$('#previewWarning').text(warning).toggle(!!warning);
		$('#saveTxnBtn').prop('disabled', !!warning);
	}

	$('.type-btn').on('click', function () {
		$('.type-btn').removeClass('active');
		$(this).addClass('active');
		var type = $(this).data('type');
		$('#transactionType').val(type);
		$('#destinationBankGroup').toggle(type === 'TRANSFER');
		updatePreview();
	});

	$('#txnAmount, #destinationBank').on('input change', updatePreview);

	$('#transactionForm').on('submit', function (e) {
		e.preventDefault();

		var type = $('#transactionType').val();
		if (type === 'TRANSFER' && ! $('#destinationBank').val()) {
			$('#formErrors').text('A destination account is required for a transfer.').show();
			return;
		}

		$.ajax({
			url: "<?= base_url('bank-accounts/store-transaction') ?>",
			type: 'POST',
			dataType: 'json',
			data: $('#transactionForm').serialize()
		}).done(function (resp) {
			if (resp.status) {
				window.location.reload();
			} else {
				$('#formErrors').html((resp.errors || ['Failed to save transaction.']).join('<br>')).show();
			}
		}).fail(function (xhr) {
			var data = xhr.responseJSON;
			$('#formErrors').html(((data && data.errors) || ['A network error occurred.']).join('<br>')).show();
		});
	});

	$(document).ready(function () {
		updatePreview();

		function applyDateFilter() {
			var from = $('#filterFromDate').val();
			var to   = $('#filterToDate').val();

			$('#statementTable tbody tr[data-date]').each(function () {
				var row  = $(this);
				var date = row.data('date');
				var visible = true;
				if (from && date < from) visible = false;
				if (to && date > to) visible = false;
				row.toggle(visible);
			});
		}

		$('#filterFromDate, #filterToDate').on('change', applyDateFilter);
	});
</script>
<?= $this->endSection() ?>
