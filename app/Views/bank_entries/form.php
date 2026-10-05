<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
/**
 * Release 4.8.4I: one create/edit form for Bank Deposit / Withdrawal /
 * Transfer / Daybook, driven by the controller's $cfg['fields']. Submits by
 * AJAX to {slug}/store or {slug}/update/{id} exactly like bank_accounts/create.
 * The balance preview below is a convenience only — the server re-checks the
 * balance inside the posting transaction and answers 422 if it would go below
 * zero.
 */
$slug     = $cfg['slug'];
$isEdit   = $entry !== null;
$saveUrl  = $isEdit ? base_url($slug . '/update/' . $entry['id']) : base_url($slug . '/store');
$listUrl  = base_url($slug);
$title    = ($isEdit ? 'Edit ' : 'New ') . $cfg['singular'];
$hasPreview = ! empty($cfg['preview']);

$accountMap = [];
foreach ($accounts as $account) {
    $accountMap[$account['id']] = ['label' => $account['label'], 'balance' => $account['balance']];
}
?>

<?= $this->include('bank_entries/partials/styles') ?>

<nav aria-label="breadcrumb" class="ba-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Accounts</li>
        <li class="breadcrumb-item"><a href="<?= base_url('bank-accounts') ?>">Bank Accounts</a></li>
        <li class="breadcrumb-item"><a href="<?= $listUrl ?>"><?= esc($cfg['title']) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= esc($title) ?></li>
    </ol>
</nav>

<div class="page-title">
    <span><i class="bi <?= esc($cfg['icon'], 'attr') ?> me-2"></i><?= esc($title) ?><?= $isEdit && isset($entry['transfer_no']) ? ' — ' . esc($entry['transfer_no']) : '' ?></span>
</div>

<div class="card-custom">
    <div class="card-custom-body">
        <form id="entryForm" autocomplete="off">
            <div class="row g-3">
                <?php $idPrefix = 'f_'; include APPPATH . 'Views/bank_entries/partials/fields.php'; ?>
            </div>

            <?php if ($hasPreview): ?>
            <div class="be-preview mt-3" id="balancePreview" style="display:none;">
                <div id="previewRows"></div>
                <div class="be-preview-warning" id="previewWarning" style="display:none;"></div>
            </div>
            <?php endif; ?>

            <div id="formErrors" class="alert alert-danger mt-3" style="display:none;"></div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn-save" id="saveEntryBtn"><i class="bi bi-check-lg"></i> <?= $isEdit ? 'Update' : 'Save' ?></button>
                <a href="<?= $listUrl ?>" class="btn-cancel"><i class="bi bi-x-lg"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	var BE = {
		accounts: <?= json_encode((object) $accountMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
		effects: <?= json_encode((object) ($entry['effects'] ?? []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
		preview: <?= json_encode($cfg['preview'] ?? null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
	};

	function fmtMoney(n) {
		return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
	}

	// Balance of an account with THIS entry's own effect taken back out (so an edit is judged as a fresh posting).
	function baseBalance(accountId) {
		var account = BE.accounts[accountId];
		if (!account) return null;
		return account.balance - (BE.effects[accountId] || 0);
	}

	function previewRow(label, value, isAfter) {
		var negative = isAfter && value < 0;
		return '<div class="be-preview-row"><span>' + label + '</span><span class="' + (isAfter ? 'be-preview-after' : '') + (negative ? ' negative' : '') + '">' + fmtMoney(value) + '</span></div>';
	}

	function refreshPreview() {
		var p = BE.preview;
		if (!p) return;

		var amount = parseFloat($('#f_amount').val()) || 0;
		var rows = '', warning = '';

		if (p.mode === 'single') {
			var id = $('#f_' + p.account).val();
			var base = baseBalance(id);
			if (base === null) { $('#balancePreview').hide(); return; }

			var credit = p.direction ? p.direction === 'credit' : $('#f_' + p.direction_field).val() === 'CREDIT';
			var after  = credit ? base + amount : base - amount;

			rows = previewRow('Current balance' + (Object.keys(BE.effects).length ? ' (without this entry)' : ''), base, false) + previewRow('Balance after', after, true);
			if (!credit && after < 0) warning = 'Insufficient balance: this would leave the account at ' + fmtMoney(after) + '.';
		} else {
			var from = $('#f_' + p.from).val(), to = $('#f_' + p.to).val();
			var fromBase = baseBalance(from), toBase = baseBalance(to);
			if (fromBase === null && toBase === null) { $('#balancePreview').hide(); return; }

			if (fromBase !== null) rows += previewRow('Source balance after', fromBase - amount, true);
			if (toBase !== null)   rows += previewRow('Destination balance after', toBase + amount, true);
			if (fromBase !== null && fromBase - amount < 0) warning = 'Insufficient balance in the source account.';
			if (from && from === to) warning = 'Source and destination must be different accounts.';
		}

		$('#previewRows').html(rows);
		$('#previewWarning').text(warning).toggle(!!warning);
		$('#balancePreview').show();
	}

	// Messages come from the server as plain text; render them as text, never as HTML.
	function showErrors(errors) {
		var $box = $('#formErrors').empty();
		$.each(errors, function (i, message) {
			$box.append($('<div>').text(message));
		});
		$box.show();
		$('#saveEntryBtn').prop('disabled', false);
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

	function submitEntry(confirmDuplicate) {
		var data = $('#entryForm').serialize();
		if (confirmDuplicate) {
			data += '&confirm_duplicate=1';
		}

		$.ajax({
			url: "<?= $saveUrl ?>",
			type: 'POST',
			dataType: 'json',
			data: data
		}).done(function (resp) {
			if (resp.status) {
				window.location.href = "<?= $listUrl ?>";
			} else if (resp.duplicate) {
				if (confirm(duplicateMessage(resp.warning))) {
					submitEntry(true);
				} else {
					$('#saveEntryBtn').prop('disabled', false);
				}
			} else {
				showErrors(resp.errors || ['Failed to save.']);
			}
		}).fail(function (xhr) {
			var data = xhr.responseJSON;
			showErrors((data && data.errors) || ['A network error occurred.']);
		});
	}

	$('#entryForm').on('submit', function (e) {
		e.preventDefault();

		// Disabled while the request is in flight so a double click cannot post the entry twice.
		$('#saveEntryBtn').prop('disabled', true);
		$('#formErrors').hide();

		submitEntry(false);
	});

	$(document).ready(function () {
		if (BE.preview) {
			$('#entryForm').on('input change', 'input, select', refreshPreview);
			refreshPreview();
		}
	});
</script>
<?= $this->endSection() ?>
