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
 */
$voucherBadges = [
    'Deposit' => 'be-badge-green', 'Transfer In' => 'be-badge-green',
    'Withdrawal' => 'be-badge-red', 'Transfer Out' => 'be-badge-blue',
    'Manual Entry' => 'be-badge-purple',
];

// Tab links keep every other filter and only swap the voucher.
$tabUrl = static function (string $voucherKey) use ($filters): string {
    $query = array_filter([
        'voucher'          => $voucherKey,
        'bank_account_id'  => $filters['bank_account_id'] ?: '',
        'transaction_type' => $filters['transaction_type'],
        'reference_type'   => $filters['reference_type'],
        'from'             => $filters['from'],
        'to'               => $filters['to'],
        'q'                => $filters['q'],
    ], static fn ($v) => $v !== null && $v !== '');

    return base_url('bank-accounts/transactions' . ($query ? '?' . http_build_query($query) : ''));
};
?>

<?= $this->include('bank_entries/partials/styles') ?>

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
        <a href="<?= base_url('bank-accounts/transactions/new' . ($filters['voucher'] !== '' ? '?voucher=' . $filters['voucher'] : '')) ?>" class="btn-save"><i class="bi bi-plus-lg"></i> New Transaction</a>
        <a href="<?= base_url('bank-statement') ?>" class="btn-cancel"><i class="bi bi-journal-text"></i> Statement</a>
        <a href="<?= base_url('bank-accounts') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<!-- VOUCHER TYPE TABS -->
<div class="d-flex flex-wrap gap-2 mb-3 be-noprint">
    <a href="<?= $tabUrl('') ?>" class="<?= $filters['voucher'] === '' ? 'btn-save' : 'btn-cancel' ?>"><i class="bi bi-list-ul"></i> All</a>
    <?php foreach ($vouchers as $voucherKey => $voucherLabel): ?>
    <a href="<?= $tabUrl($voucherKey) ?>" class="<?= $filters['voucher'] === $voucherKey ? 'btn-save' : 'btn-cancel' ?>"><?= esc($voucherLabel) ?></a>
    <?php endforeach; ?>
</div>

<div class="card-custom mb-3 be-filter">
    <div class="card-custom-body">
        <form method="get" action="<?= base_url('bank-accounts/transactions') ?>" class="row g-2 align-items-end">
            <input type="hidden" name="voucher" value="<?= esc($filters['voucher'], 'attr') ?>">
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label">Bank Account</label>
                <select name="bank_account_id" class="form-control">
                    <option value="">All Accounts</option>
                    <?php foreach ($accounts as $a): ?>
                    <option value="<?= (int) $a['id'] ?>"<?= (int) $a['id'] === $filters['bank_account_id'] ? ' selected' : '' ?>><?= esc($a['bank_name']) ?> — <?= esc($a['account_name']) ?> (<?= esc($a['account_number']) ?>)<?= $a['is_active'] ? '' : ' [inactive]' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label">Transaction Type</label>
                <select name="transaction_type" class="form-control">
                    <option value="">All Types</option>
                    <?php foreach ($typeOptions as $key => $label): ?>
                    <option value="<?= esc($key, 'attr') ?>"<?= $key === $filters['transaction_type'] ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-3">
                <label class="form-label">Reference Type</label>
                <select name="reference_type" class="form-control">
                    <option value="">All Reference Types</option>
                    <option value="<?= esc($noReference, 'attr') ?>"<?= $filters['reference_type'] === $noReference ? ' selected' : '' ?>>None (no reference)</option>
                    <?php foreach ($referenceTypes as $referenceType): ?>
                    <option value="<?= esc($referenceType, 'attr') ?>"<?= $referenceType === $filters['reference_type'] ? ' selected' : '' ?>><?= esc($referenceLabels[$referenceType] ?? $referenceType) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label">Date From</label>
                <input type="date" name="from" class="form-control" value="<?= esc($filters['from'] ?? '', 'attr') ?>">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label">Date To</label>
                <input type="date" name="to" class="form-control" value="<?= esc($filters['to'] ?? '', 'attr') ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-5">
                <label class="form-label">Search</label>
                <input type="text" name="q" class="form-control" maxlength="100" placeholder="Reference no, remarks, bank, amount..." value="<?= esc($filters['q'], 'attr') ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-3 d-flex flex-wrap gap-2">
                <button type="submit" class="btn-save"><i class="bi bi-funnel"></i> Show</button>
                <a href="<?= base_url('bank-accounts/transactions') ?>" class="btn-cancel"><i class="bi bi-x-lg"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card-custom">
    <div class="be-scroll">
        <table id="transactionsTable" class="table-custom be-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Voucher Type</th>
                    <th>Bank Account</th>
                    <th>Reference Type</th>
                    <th>Reference No</th>
                    <th class="be-num">Credit</th>
                    <th class="be-num">Debit</th>
                    <th class="be-num">Balance</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $line): ?>
                <tr>
                    <td style="white-space:nowrap;"><?= esc(date('d-m-Y', strtotime($line['date']))) ?></td>
                    <td>
                        <span class="be-badge <?= esc($voucherBadges[$line['voucher_label']] ?? 'be-badge-gray', 'attr') ?>"><?= esc($line['voucher_label']) ?></span>
                        <?php if ($line['view_url'] !== ''): ?><a href="<?= base_url($line['view_url']) ?>" class="be-noprint" title="Open entry (view / edit / delete)" style="color:#2F7E8A;"><i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?>
                        <?php if ($line['category'] !== ''): ?><span class="be-sub-line"><?= esc(ucwords(strtolower($line['category']))) ?></span><?php endif; ?>
                        <?php if ($line['counterparty'] !== ''): ?><span class="be-sub-line"><?= $line['type_key'] === 'TRANSFER_OUT' ? 'to' : 'from' ?> <?= esc($line['counterparty']) ?></span><?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <?= esc($line['bank_name']) ?>
                        <span class="be-sub-line"><?= esc($line['account_number']) ?></span>
                    </td>
                    <?php /* readable label (Supplier Payment, Manual Entry, ...); the badge wraps at spaces so a long one does not force the table wider than the card */ ?>
                    <td><?= $line['reference_label'] !== '' ? '<span class="be-badge be-badge-gray" style="white-space:normal;">' . esc($line['reference_label']) . '</span>' : '<span class="be-muted">-</span>' ?></td>
                    <td>
                        <?= $line['reference'] !== '' ? esc($line['reference']) : '<span class="be-muted">-</span>' ?>
                        <?php if ($line['reference_sub'] !== ''): ?><span class="be-sub-line"><?= esc($line['reference_sub']) ?></span><?php endif; ?>
                    </td>
                    <td class="be-num be-num-in"><?= $line['credit'] > 0 ? number_format($line['credit'], 2) : '<span class="be-muted">-</span>' ?></td>
                    <td class="be-num be-num-out"><?= $line['debit'] > 0 ? number_format($line['debit'], 2) : '<span class="be-muted">-</span>' ?></td>
                    <td class="be-num"><strong><?= number_format($line['balance'], 2) ?></strong></td>
                    <td class="be-wrap"><?= $line['remarks'] !== '' ? esc($line['remarks']) : '<span class="be-muted">-</span>' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($lines)): ?>
        <div class="be-muted" style="text-align:center; padding:26px 12px;">
            <?= $isFiltered ? 'No transactions match the selected filters.' : 'No bank transactions recorded yet.' ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	$(document).ready(function () {
		<?php if (! empty($lines)): ?>
		// The filters are server-side (the form above); DataTables only pages. Sorting is off: a running balance only reads correctly in posting order.
		$('#transactionsTable').DataTable({
			paging: true,
			searching: false,
			lengthChange: false,
			info: true,
			ordering: false,
			pageLength: 25,
			dom: 'tip',
			language: {
				info: 'Showing _START_ to _END_ of _TOTAL_ transactions',
				paginate: {
					previous: '<i class="bi bi-chevron-left"></i>',
					next: '<i class="bi bi-chevron-right"></i>'
				}
			}
		});
		<?php endif; ?>
	});
</script>
<?= $this->endSection() ?>
