<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
/**
 * Release 4.8.4I Patch: Bank Transactions. Read-only; filters are GET
 * parameters. Every line and its running balance come from
 * BankTransactionModel::statementFor() (see the BankTransactions controller).
 *
 * Release 4.8.5A: the single bank transaction module - voucher tabs
 * (Deposit / Withdrawal / Transfer / Manual Entry) filter the list and
 * "+ New Transaction" opens the unified entry screen.
 *
 * Release 4.9.1: compact Day-Book-style layout. The list + filter toolbar
 * move into a left column; a right-side "Bank Transaction Entry" panel embeds
 * the same four voucher forms as the standalone New Transaction screen
 * (bank_transactions/entry.php) so a transaction can be posted without
 * leaving the list. No controller/model logic changed — this view still only
 * reads $lines/$filters and posts through each entry controller's own
 * unchanged {slug}/store endpoint.
 */
$voucherBadges = [
    'Deposit' => 'be-badge-green', 'Transfer In' => 'be-badge-green',
    'Withdrawal' => 'be-badge-red', 'Transfer Out' => 'be-badge-blue',
    'Manual Entry' => 'be-badge-purple',
];

// Release 4.9.2: Month options for the toolbar dropdown — 12 months back to 2 months
// ahead of the current month, always including whatever month is currently selected
// (so a bookmarked/old URL for a month outside that window still shows correctly).
$monthOptions = [];
for ($i = 12; $i >= -2; $i--) {
    $key = date('Y-m', strtotime("first day of -{$i} month"));
    $monthOptions[$key] = date('F Y', strtotime($key . '-01'));
}
if (! isset($monthOptions[$filters['month']])) {
    $monthOptions[$filters['month']] = date('F Y', strtotime($filters['month'] . '-01'));
}
krsort($monthOptions);

// Release 4.9.5: footer totals, computed from the same $lines the table renders —
// no new balance math. Credit/Debit are a plain sum of the displayed rows; Balance
// is NOT summed (it's a running balance) — it's just the last displayed row's own
// balance, since $lines is already in ascending date order. With no rows, fall back
// to the selected account's own stored current_balance (existing field, not a new calc).
$totalCredit    = 0.0;
$totalDebit     = 0.0;
foreach ($lines as $line) {
    $totalCredit += $line['credit'];
    $totalDebit  += $line['debit'];
}
if (! empty($lines)) {
    $closingBalance = end($lines)['balance'];
} else {
    $closingBalance = 0.0;
    foreach ($accounts as $a) {
        if ((int) $a['id'] === $filters['bank_account_id']) {
            $closingBalance = (float) $a['current_balance'];
            break;
        }
    }
}

// Filter form action keeps every filter as a GET field; no separate "tab" links needed any more.
$accountMap = [];
$jsForms    = [];
foreach ($forms as $key => $form) {
    foreach ($form['accounts'] as $account) {
        $accountMap[$account['id']] = ['label' => $account['label'], 'balance' => $account['balance']];
    }
    $jsForms[$key] = [
        'saveUrl' => base_url($form['cfg']['slug'] . '/store'),
        'preview' => $form['cfg']['preview'] ?? null,
        'singular' => $form['cfg']['singular'],
    ];
}
?>

<?= $this->include('bank_entries/partials/styles') ?>

<style>
	/* Release 4.9.1: compact Day-Book-style layout for Bank Transactions only. */
	.bt-toolbar { padding: 10px 14px; }
	.bt-toolbar .form-label { font-size: .7rem; margin-bottom: 2px; }
	.bt-toolbar .form-control { padding: 5px 9px; font-size: .8rem; height: auto; }
	.bt-toolbar .btn-save, .bt-toolbar .btn-cancel { padding: 6px 12px; font-size: .8rem; }

	.bt-entry-panel { position: sticky; top: 12px; }
	@media (max-width: 991px) { .bt-entry-panel { position: static !important; } }

	.bt-voucher-select { margin-bottom: 12px; }

	.bt-auto-row { background: #fafbfc; }

	/* Release 4.9.0EF: Voucher Type is a compact category column (badge only); long Remarks wrap in their own column so they never widen the table. */
	.bt-vt-col { width: 1%; white-space: nowrap; }
	#transactionsTable td.be-wrap { overflow-wrap: anywhere; }

	/* Release 4.9.5: size select2 to match this page's compact controls (same pattern
	   as general_purchases' .gp-page), and force full width since some of these selects
	   sit inside forms hidden with display:none at page load (select2 can't measure a
	   hidden element's width, so a fixed 100% avoids the classic 0-width dropdown bug). */
	.bt-toolbar .select2-container,
	.bt-entry-panel .select2-container { width: 100% !important; }
	.bt-toolbar .select2-container--default .select2-selection--single,
	.bt-entry-panel .select2-container--default .select2-selection--single {
		height: 32px;
		border: 1px solid #d1d5db;
		border-radius: 4px;
	}
	.bt-toolbar .select2-container--default .select2-selection--single .select2-selection__rendered,
	.bt-entry-panel .select2-container--default .select2-selection--single .select2-selection__rendered {
		line-height: 30px;
		font-size: .8rem;
		padding-left: 9px;
	}
	.bt-toolbar .select2-container--default .select2-selection--single .select2-selection__arrow,
	.bt-entry-panel .select2-container--default .select2-selection--single .select2-selection__arrow { height: 30px; }

	/* Release 4.9.5: totals footer — compact, no extra card, just a stronger table row. */
	.bt-totals-row td { border-top: 2px solid #cbd5e1; font-weight: 600; background: #f8fafc; }
	.bt-totals-row .be-muted { font-weight: 600; }

	@media print {
		.bt-entry-panel, .bt-toolbar, .page-title a { display: none !important; }
	}
</style>

<nav aria-label="breadcrumb" class="ba-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Accounts</li>
        <li class="breadcrumb-item"><a href="<?= base_url('bank-accounts') ?>">Bank Accounts</a></li>
        <li class="breadcrumb-item active" aria-current="page">Transactions<?= $filters['voucher'] !== '' ? ' - ' . esc($vouchers[$filters['voucher']]) : '' ?></li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-list-ul me-2"></i>Bank Transactions</span>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= base_url('bank-statement') ?>" class="btn-cancel"><i class="bi bi-journal-text"></i> Statement</a>
        <a href="<?= base_url('bank-accounts') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <!-- COMPACT TOOLBAR: Voucher Type | Bank Account | Month | Reset, one row on desktop. Every select auto-submits on change. -->
        <div class="card-custom mb-3 be-filter bt-toolbar be-noprint">
            <form method="get" action="<?= base_url('bank-accounts/transactions') ?>" class="row g-2 align-items-end bt-toolbar-row" id="btFilterForm">
                <div class="col-6 col-md-3">
                    <label class="form-label">Voucher Type</label>
                    <select name="voucher" class="form-control bt-auto-submit">
                        <option value="">All</option>
                        <?php foreach ($vouchers as $voucherKey => $voucherLabel): ?>
                        <option value="<?= esc($voucherKey, 'attr') ?>"<?= $filters['voucher'] === $voucherKey ? ' selected' : '' ?>><?= esc($voucherLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Bank Account</label>
                    <select name="bank_account_id" class="form-control bt-auto-submit">
                        <?php foreach ($accounts as $a): ?>
                        <option value="<?= (int) $a['id'] ?>"<?= (int) $a['id'] === $filters['bank_account_id'] ? ' selected' : '' ?>><?= esc($a['bank_name']) ?> — <?= esc($a['account_name']) ?><?= $a['is_active'] ? '' : ' [inactive]' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Month</label>
                    <select name="month" class="form-control bt-auto-submit">
                        <?php foreach ($monthOptions as $monthKey => $monthLabel): ?>
                        <option value="<?= esc($monthKey, 'attr') ?>"<?= $filters['month'] === $monthKey ? ' selected' : '' ?>><?= esc($monthLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 d-flex gap-2">
                    <a href="<?= base_url('bank-accounts/transactions') ?>" class="btn-cancel flex-fill"><i class="bi bi-x-lg"></i> Reset</a>
                </div>
            </form>
        </div>

        <div class="card-custom">
            <div class="be-scroll">
                <table id="transactionsTable" class="table-custom be-table">
                    <thead>
                        <tr>
                            <th class="sno-col">S.No.</th>
                            <th>Date</th>
                            <th class="bt-vt-col">Voucher Type</th>
                            <th>Remarks</th>
                            <th>Method</th>
                            <th class="be-num">Credit</th>
                            <th class="be-num">Debit</th>
                            <th class="be-num">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lines as $line): ?>
                        <tr<?= $line['voucher_key'] !== 'manual' && ! in_array($line['reference_type'], ['MANUAL_DEPOSIT', 'MANUAL_WITHDRAWAL', 'BANK_TRANSFER', ''], true) ? ' class="bt-auto-row"' : '' ?>>
                            <td class="sno-col" data-label="S.No."></td>
                            <td style="white-space:nowrap;"><?= esc(date('d-m-Y', strtotime($line['date']))) ?></td>
                            <td style="white-space:nowrap;">
                                <span class="be-badge <?= esc($voucherBadges[$line['voucher_label']] ?? 'be-badge-gray', 'attr') ?>"><?= esc($line['voucher_label']) ?></span>
                                <?php if ($line['view_url'] !== ''): ?><a href="<?= base_url($line['view_url']) ?>" class="be-noprint" title="Open entry (view / edit / delete)" style="color:#2F7E8A;"><i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?>
                            </td>
                            <?php /* Release 4.9.3: Reference Type + Reference No folded into this one Remarks string (display only; reference_type/reference_id are unchanged). */ ?>
                            <?php /* Release 4.9.0EF: the entry category moved here from under the Voucher Type badge (nothing dropped); the transfer counterparty is not repeated - it is on the entry's detail page. */ ?>
                            <?php $remarksCell = trim($line['remarks_display'] . ($line['category'] !== '' ? ' - ' . ucwords(strtolower($line['category'])) : ''), ' -'); ?>
                            <td class="be-wrap"><?= $remarksCell !== '' ? esc($remarksCell) : '<span class="be-muted">-</span>' ?></td>
                            <td style="white-space:nowrap;"><?= pm_badge($line['method'] ?? '', strpos($line['type_key'], 'TRANSFER') === 0 ? '—' : 'Not recorded') ?></td>
                            <td class="be-num be-num-in"><?= $line['credit'] > 0 ? number_format($line['credit'], 2) : '<span class="be-muted">-</span>' ?></td>
                            <td class="be-num be-num-out"><?= $line['debit'] > 0 ? number_format($line['debit'], 2) : '<span class="be-muted">-</span>' ?></td>
                            <td class="be-num"><strong><?= number_format($line['balance'], 2) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bt-totals-row">
                            <td colspan="3"></td>
                            <td colspan="2">TOTAL</td>
                            <td class="be-num be-num-in"><?= number_format($totalCredit, 2) ?></td>
                            <td class="be-num be-num-out"><?= number_format($totalDebit, 2) ?></td>
                            <td class="be-num"><?= number_format($closingBalance, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
                <?php if (empty($lines)): ?>
                <div class="be-muted" style="text-align:center; padding:26px 12px;">
                    <?= $isFiltered ? 'No transactions match the selected filters.' : 'No bank transactions recorded yet.' ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-custom bt-entry-panel">
            <div class="card-custom-header">Bank Transaction Entry</div>
            <div class="card-custom-body">
                <div class="bt-voucher-select">
                    <label class="form-label" for="voucherType">Voucher Type *</label>
                    <select id="voucherType" class="form-control">
                        <?php foreach ($vouchers as $key => $label): ?>
                        <option value="<?= esc($key, 'attr') ?>"<?= $key === $entryVoucher ? ' selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php foreach ($forms as $key => $form): ?>
                <form class="entry-form" id="entryForm_<?= esc($key, 'attr') ?>" data-voucher="<?= esc($key, 'attr') ?>" autocomplete="off"<?= $key === $entryVoucher ? '' : ' style="display:none;"' ?>>
                    <div class="row g-2">
                        <?php
                            // Same field markup as bank_entries/form.php / bank_transactions/entry.php; the partial reads these locals.
                            $cfg = $form['cfg']; $entry = null; $accounts = $form['accounts']; $isEdit = false; $idPrefix = 'p_' . $key . '_';
                            include APPPATH . 'Views/bank_entries/partials/fields.php';
                        ?>
                    </div>

                    <?php if (! empty($form['cfg']['preview'])): ?>
                    <div class="be-preview mt-3" id="balancePreview_<?= esc($key, 'attr') ?>" style="display:none;">
                        <div id="previewRows_<?= esc($key, 'attr') ?>"></div>
                        <div class="be-preview-warning" id="previewWarning_<?= esc($key, 'attr') ?>" style="display:none;"></div>
                    </div>
                    <?php endif; ?>

                    <div id="formErrors_<?= esc($key, 'attr') ?>" class="alert alert-danger mt-3" style="display:none;"></div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn-save flex-fill" id="saveBtn_<?= esc($key, 'attr') ?>"><i class="bi bi-check-lg"></i> Save</button>
                    </div>
                </form>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	$(document).ready(function () {
		<?php if (! empty($lines)): ?>
		// Release 4.9.3: no pagination — the Month filter already bounds how many rows
		// show at once, so every matching row is shown in one table. Sorting stays off:
		// a running balance only reads correctly in posting order.
		$('#transactionsTable').DataTable({
			paging: false,
			searching: false,
			lengthChange: false,
			info: false,
			ordering: false,
			dom: 't'
		});
		<?php endif; ?>

		// Release 4.9.3: the toolbar has no Show button any more — each filter reloads
		// the page (via the existing GET form) as soon as it changes.
		$('#btFilterForm .bt-auto-submit').on('change', function () {
			$('#btFilterForm').trigger('submit');
		});
	});

	// Release 4.9.1: the same voucher-switching / balance-preview / duplicate-confirm
	// entry logic as bank_transactions/entry.php, embedded inline. Field ids use the
	// "p_" prefix (vs "f_" there) so both pages' markup never collides if ever combined.
	var BE = {
		accounts: <?= json_encode((object) $accountMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
		forms: <?= json_encode($jsForms, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
	};

	function fmtMoney(n) {
		return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
	}

	function balanceOf(accountId) {
		var account = BE.accounts[accountId];
		return account ? account.balance : null;
	}

	function previewRow(label, value, isAfter) {
		var negative = isAfter && value < 0;
		return '<div class="be-preview-row"><span>' + label + '</span><span class="' + (isAfter ? 'be-preview-after' : '') + (negative ? ' negative' : '') + '">' + fmtMoney(value) + '</span></div>';
	}

	function refreshPreview(key) {
		var p = BE.forms[key].preview;
		if (!p) return;

		var prefix = '#p_' + key + '_';
		var $box = $('#balancePreview_' + key);
		var amount = parseFloat($(prefix + 'amount').val()) || 0;
		var rows = '', warning = '';

		if (p.mode === 'single') {
			var base = balanceOf($(prefix + p.account).val());
			if (base === null) { $box.hide(); return; }

			var credit = p.direction ? p.direction === 'credit' : $(prefix + p.direction_field).val() === 'CREDIT';
			var after = credit ? base + amount : base - amount;

			rows = previewRow('Current balance', base, false) + previewRow('Balance after', after, true);
			if (!credit && after < 0) warning = 'Insufficient balance: this would leave the account at ' + fmtMoney(after) + '.';
		} else {
			var from = $(prefix + p.from).val(), to = $(prefix + p.to).val();
			var fromBase = balanceOf(from), toBase = balanceOf(to);
			if (fromBase === null && toBase === null) { $box.hide(); return; }

			if (fromBase !== null) rows += previewRow('Source balance after', fromBase - amount, true);
			if (toBase !== null)   rows += previewRow('Destination balance after', toBase + amount, true);
			if (fromBase !== null && fromBase - amount < 0) warning = 'Insufficient balance in the source account.';
			if (from && from === to) warning = 'Source and destination must be different accounts.';
		}

		$('#previewRows_' + key).html(rows);
		$('#previewWarning_' + key).text(warning).toggle(!!warning);
		$box.show();
	}

	// Messages come from the server as plain text; render them as text, never as HTML.
	function showErrors(key, errors) {
		var $box = $('#formErrors_' + key).empty();
		$.each(errors, function (i, message) {
			$box.append($('<div>').text(message));
		});
		$box.show();
		$('#saveBtn_' + key).prop('disabled', false);
	}

	function showVoucher(key) {
		$('.entry-form').hide();
		$('#entryForm_' + key).show();
		refreshPreview(key);
	}

	// Release 4.9.0AO: duplicate warning text, built from the server's plain-data summary.
	function duplicateMessage(w) {
		w = w || {};
		return (w.message || 'Possible duplicate bank transaction found.') + '\n\n'
			+ (w.date || '') + '   ' + (w.amount != null ? fmtMoney(w.amount) : '') + '\n'
			+ (w.bank_account || '') + '\n'
			+ (w.transaction_type || '') + (w.reference_type ? ' — ' + w.reference_type : '') + (w.reference_no ? ' ' + w.reference_no : '') + '\n\n'
			+ 'Click OK only if this is a separate, genuine transaction.';
	}

	function submitEntry(key, serialized, confirmDuplicate) {
		var data = serialized + (confirmDuplicate ? '&confirm_duplicate=1' : '');

		$.ajax({
			url: BE.forms[key].saveUrl,
			type: 'POST',
			dataType: 'json',
			data: data
		}).done(function (resp) {
			if (resp.status) {
				// Reload keeps the current filters/query string and shows the new row.
				window.location.reload();
			} else if (resp.duplicate) {
				if (confirm(duplicateMessage(resp.warning))) {
					submitEntry(key, serialized, true);
				} else {
					$('#saveBtn_' + key).prop('disabled', false);
				}
			} else {
				showErrors(key, resp.errors || ['Failed to save.']);
			}
		}).fail(function (xhr) {
			var data2 = xhr.responseJSON;
			showErrors(key, (data2 && data2.errors) || ['A network error occurred.']);
		});
	}

	$('.entry-form').on('submit', function (e) {
		e.preventDefault();

		var key = $(this).data('voucher');

		// Disabled while the request is in flight so a double click cannot post the entry twice.
		$('#saveBtn_' + key).prop('disabled', true);
		$('#formErrors_' + key).hide();

		submitEntry(key, $(this).serialize(), false);
	});

	$(document).ready(function () {
		$('#voucherType').on('change', function () {
			showVoucher(this.value);
		});

		$('.entry-form').on('input change', 'input, select', function () {
			refreshPreview($(this).closest('form').data('voucher'));
		});

		refreshPreview($('#voucherType').val());
	});
</script>
<?= $this->endSection() ?>
