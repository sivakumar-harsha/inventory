<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
/**
 * Release 4.8.5A: unified New Transaction screen. A Voucher Type dropdown shows
 * one of the four existing entry forms (Deposit / Withdrawal / Transfer / Manual
 * Entry). The fields come from each controller's own cfg(), and every form posts
 * by AJAX to that controller's unchanged {slug}/store endpoint, so validation,
 * posting and the overdraft rule are exactly what the standalone screens use.
 * The balance preview is a convenience only; the server re-checks the balance.
 */
$listUrl = base_url('bank-accounts/transactions');

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
	/* Release 4.9.5: force full width — some of these selects sit inside a
	   voucher form hidden with display:none at page load, and select2 can't
	   measure a hidden element's width otherwise. */
	.entry-form .select2-container { width: 100% !important; }
</style>

<nav aria-label="breadcrumb" class="ba-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Accounts</li>
        <li class="breadcrumb-item"><a href="<?= base_url('bank-accounts') ?>">Bank Accounts</a></li>
        <li class="breadcrumb-item"><a href="<?= $listUrl ?>">Transactions</a></li>
        <li class="breadcrumb-item active" aria-current="page">New Transaction</li>
    </ol>
</nav>

<div class="page-title">
    <span><i class="bi bi-plus-circle me-2"></i>New Transaction</span>
</div>

<div class="card-custom">
    <div class="card-custom-body">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="voucherType">Voucher Type *</label>
                <select id="voucherType" class="form-control">
                    <?php foreach ($vouchers as $key => $label): ?>
                    <option value="<?= esc($key, 'attr') ?>"<?= $key === $voucher ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php foreach ($forms as $key => $form): ?>
        <form class="entry-form" id="entryForm_<?= esc($key, 'attr') ?>" data-voucher="<?= esc($key, 'attr') ?>" autocomplete="off"<?= $key === $voucher ? '' : ' style="display:none;"' ?>>
            <div class="row g-3">
                <?php
                    // Same field markup as bank_entries/form.php; the partial reads these locals.
                    $cfg = $form['cfg']; $entry = null; $accounts = $form['accounts']; $isEdit = false; $idPrefix = 'f_' . $key . '_';
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

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn-save" id="saveBtn_<?= esc($key, 'attr') ?>"><i class="bi bi-check-lg"></i> Save</button>
                <a href="<?= $listUrl ?>" class="btn-cancel"><i class="bi bi-x-lg"></i> Cancel</a>
            </div>
        </form>
        <?php endforeach; ?>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	var BE = {
		accounts: <?= json_encode((object) $accountMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
		forms: <?= json_encode($jsForms, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
		listUrl: <?= json_encode($listUrl) ?>
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

		var prefix = '#f_' + key + '_';
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

		if (window.history && history.replaceState) {
			history.replaceState(null, '', '?voucher=' + encodeURIComponent(key));
		}
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
				window.location.href = BE.listUrl + '?voucher=' + encodeURIComponent(key);
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
