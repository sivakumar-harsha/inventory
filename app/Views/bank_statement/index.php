<?php
$account = $statement['account'] ?? null;
$fmt     = static fn ($n) => number_format((float) $n, 2);
$dmy     = static fn ($d) => $d ? date('d/m/Y', strtotime($d)) : '-';
$qs      = http_build_query(array_filter([
    'bank_account_id' => $accountId ?: '', 'from' => $from ?? '', 'to' => $to ?? '', 'type' => $type, 'q' => $q,
], static fn ($v) => $v !== ''));
$slug    = static fn ($t) => strtolower(str_replace(' ', '-', $t));
$rowCls  = static fn ($r) => $r['ttype'] === 'Transfer' ? 'edge-xfer' : ($r['deposit'] > 0 ? 'edge-in' : 'edge-out');
$filtered = ($type !== '' || $q !== '');
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
	.blg-page .ba-crumb { margin-bottom: 6px; }
	.blg-page .ba-crumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.blg-page .ba-crumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.blg-page .ba-crumb .breadcrumb-item.active { color: #64748b; }

	.blg-filters { display: grid; grid-template-columns: minmax(0, 2.2fr) repeat(2, minmax(0, 1fr)) minmax(0, 1.1fr) minmax(0, 1.4fr) auto; gap: 8px; align-items: end; padding: 8px 12px; margin-bottom: 6px; background: #fff; border: 1px solid var(--border-color); border-radius: var(--border-radius); }
	.blg-filters label { display: block; margin: 0 0 2px; font-size: .62rem; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); }
	.blg-filters .form-control { height: 32px; padding: 3px 8px; font-size: .8rem; }
	.blg-filters .btn-cancel { height: 32px; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }

	.blg-strip { position: sticky; top: 0; z-index: 3; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); align-items: center; gap: 2px 12px; min-height: 58px; padding: 5px 12px; margin-bottom: 8px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--border-radius); }
	.blg-strip .st-item { display: flex; flex-direction: column; min-width: 0; }
	.blg-strip .st-item > span { font-size: .62rem; line-height: 1.2; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); }
	.blg-strip .st-item > b { font-size: .95rem; line-height: 1.3; font-variant-numeric: tabular-nums; white-space: nowrap; }
	.blg-strip .st-open > b { color: #1d4ed8; }
	.blg-strip .st-in > b { color: #16a34a; }
	.blg-strip .st-out > b { color: #c2410c; }
	.blg-strip .st-close > b { color: #14532d; }

	.blg-scroll { max-height: 62vh; overflow: auto; }
	.blg-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .8rem; }
	.blg-table thead th { position: sticky; top: 0; z-index: 2; background: #f1f5f9; padding: 0 10px; height: 34px; font-size: .68rem; text-transform: none; letter-spacing: .03em; color: var(--text-muted); border-bottom: 1px solid var(--border-color); white-space: nowrap; }
	.blg-table td { height: 44px; padding: 0 10px; border-bottom: 1px solid #eef2f7; vertical-align: middle; }
	.blg-table td:first-child { border-left: 3px solid transparent; }
	.blg-table tr.edge-in td:first-child { border-left-color: #16a34a; }
	.blg-table tr.edge-out td:first-child { border-left-color: #ea580c; }
	.blg-table tr.edge-xfer td:first-child, .blg-table tr.edge-open td:first-child { border-left-color: #2563eb; }
	.blg-table td.num, .blg-table th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
	.blg-table td.part { min-width: 240px; }
	.blg-table tr.open-row td { background: #fff; font-weight: 600; }
	.blg-table tfoot td { position: sticky; bottom: 0; background: #f1f5f9; font-weight: 700; height: 38px; }
	.amt-in { color: #16a34a; } .amt-out { color: #c2410c; } .amt-nil { color: #cbd5e1; }
	.bal { font-weight: 600; } .bal-neg { color: #b91c1c; }

	.tbadge { display: inline-flex; align-items: center; height: 20px; max-height: 22px; padding: 0 8px; border-radius: 10px; font-size: .66rem; font-weight: 600; white-space: nowrap; background: #e2e8f0; color: #475569; }
	.tbadge.t-customer-receipt { background: #dcfce7; color: #166534; }
	.tbadge.t-supplier-payment { background: #ffedd5; color: #9a3412; }
	.tbadge.t-expense-payment { background: #fef3c7; color: #92400e; }
	.tbadge.t-project-transaction { background: #dbeafe; color: #1e40af; }
	.tbadge.t-transfer, .tbadge.t-opening-balance { background: #e0e7ff; color: #3730a3; }
	.tbadge.t-manual-entry { background: #e2e8f0; color: #475569; }

	.blg-empty { padding: 26px 12px; text-align: center; color: #94a3b8; font-size: .85rem; }
	.blg-note { margin: 0 0 6px; font-size: .75rem; color: var(--text-muted); }

	@media (max-width: 1199.98px) {
		.blg-filters { grid-template-columns: repeat(3, minmax(0, 1fr)); }
		.blg-filters > :first-child { grid-column: 1 / -1; }
		.blg-strip { position: static; grid-template-columns: repeat(2, minmax(0, 1fr)); }
	}
	@media (max-width: 767.98px) {
		.blg-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
		.blg-strip { grid-template-columns: 1fr; }
		.blg-scroll { max-height: none; overflow: visible; }
		.blg-table thead { display: none; }
		.blg-table, .blg-table tbody, .blg-table tfoot, .blg-table tr, .blg-table td { display: block; width: 100%; }
		.blg-table tr { margin-bottom: 8px; border: 1px solid var(--border-color); border-left-width: 3px; border-radius: var(--border-radius); background: #fff; padding: 4px 0; }
		.blg-table tr.edge-in { border-left-color: #16a34a; } .blg-table tr.edge-out { border-left-color: #ea580c; } .blg-table tr.edge-xfer, .blg-table tr.edge-open { border-left-color: #2563eb; }
		.blg-table td, .blg-table td:first-child { height: auto; min-height: 28px; display: flex; justify-content: space-between; gap: 12px; padding: 4px 12px; border: 0; text-align: right; }
		.blg-table td::before { content: attr(data-label); color: var(--text-muted); font-size: .68rem; text-transform: uppercase; text-align: left; flex: none; }
		.blg-table td.part { min-width: 0; }
		.blg-table tfoot td { position: static; }
	}
</style>

<div class="blg-page">
<nav aria-label="breadcrumb" class="ba-crumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Accounts</li>
        <li class="breadcrumb-item"><a href="<?= base_url('bank-accounts') ?>">Bank Accounts</a></li>
        <li class="breadcrumb-item active" aria-current="page">Bank Ledger</li>
    </ol>
</nav>

<?php if (! $account): ?>
<div class="card-custom"><div class="blg-empty">No bank accounts yet. <a href="<?= base_url('bank-accounts/create') ?>">Create a bank account</a> to see its ledger.</div></div>
<?php else: ?>

<form method="get" action="<?= base_url('bank-statement') ?>" id="blgForm" class="blg-filters">
    <div>
        <label for="fAcc">Bank Account</label>
        <select name="bank_account_id" id="fAcc" class="form-control">
            <?php foreach ($accounts as $a): ?>
            <option value="<?= (int) $a['id'] ?>"<?= (int) $a['id'] === $accountId ? ' selected' : '' ?>><?= esc($a['bank_name']) ?> — <?= esc($a['account_name']) ?> (<?= esc($a['account_number']) ?>)<?= $a['is_active'] ? '' : ' [inactive]' ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div><label for="fFrom">From Date</label><input type="date" name="from" id="fFrom" class="form-control" value="<?= esc($from ?? '', 'attr') ?>"></div>
    <div><label for="fTo">To Date</label><input type="date" name="to" id="fTo" class="form-control" value="<?= esc($to ?? '', 'attr') ?>"></div>
    <div>
        <label for="fType">Transaction Type</label>
        <select name="type" id="fType" class="form-control">
            <option value="">All</option>
            <?php foreach ($types as $t): ?>
            <option value="<?= esc($t) ?>"<?= $type === $t ? ' selected' : '' ?>><?= esc($t) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div><label for="fQ">Search</label><input type="search" name="q" id="fQ" class="form-control" placeholder="Voucher, party, project, remarks…" value="<?= esc($q, 'attr') ?>"></div>
    <div class="dropdown">
        <button type="button" class="btn-cancel dropdown-toggle" data-toggle="dropdown" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-download"></i> Export</button>
        <div class="dropdown-menu dropdown-menu-right dropdown-menu-end">
            <a class="dropdown-item" href="<?= base_url('bank-statement') . '?' . $qs . '&export=excel' ?>"><i class="bi bi-file-earmark-excel"></i> Excel</a>
            <a class="dropdown-item" href="<?= base_url('bank-statement') . '?' . $qs . '&export=pdf' ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        </div>
    </div>
</form>

<?php if ($balanceMismatch): ?>
<div class="alert alert-warning py-2">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    The transaction history closes at <strong><?= $fmt($statement['closing']) ?></strong>, but this account's stored balance is
    <strong><?= $fmt($account['current_balance']) ?></strong>. Please review the account's postings.
</div>
<?php endif; ?>

<div class="blg-strip">
    <div class="st-item st-open"><span>Opening Balance<?= $from ? ' · ' . $dmy($from) : '' ?></span><b>₹ <?= $fmt($statement['opening']) ?></b></div>
    <div class="st-item st-in"><span>Money In</span><b>₹ <?= $fmt($statement['deposits']) ?></b></div>
    <div class="st-item st-out"><span>Money Out</span><b>₹ <?= $fmt($statement['withdrawals']) ?></b></div>
    <div class="st-item st-close"><span>Closing Balance<?= $to ? ' · ' . $dmy($to) : '' ?></span><b>₹ <?= $fmt($statement['closing']) ?></b></div>
</div>

<?php if ($filtered): ?>
<p class="blg-note">Showing <?= count($rows) ?> of <?= (int) $totalRows ?> transactions. Balances are always the true account balance.</p>
<?php endif; ?>

<div class="card-custom mb-3">
    <div class="blg-scroll">
        <table class="blg-table" id="ledgerTable">
            <thead>
                <tr>
                    <th class="sno-col">S.No.</th>
                    <th>Date</th><th>Voucher No</th><th>Transaction Type</th><th>Particulars</th><th>Method</th>
                    <th class="num">Money In</th><th class="num">Money Out</th><th class="num">Running Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr class="open-row edge-open">
                    <td class="sno-col" data-label="S.No."></td>
                    <td data-label="Date"><?= $from ? $dmy($from) : '' ?></td>
                    <td data-label="Voucher">-</td>
                    <td data-label="Type"><span class="tbadge t-opening-balance">Opening Balance</span></td>
                    <td data-label="Particulars" class="part">Opening balance<?= $from ? ' as on ' . $dmy($from) : '' ?></td>
                    <td data-label="Method"><?= pm_badge('') ?></td>
                    <td class="num amt-nil">-</td><td class="num amt-nil">-</td>
                    <td data-label="Balance" class="num bal <?= $statement['opening'] < 0 ? 'bal-neg' : '' ?>"><?= $fmt($statement['opening']) ?></td>
                </tr>
                <?php if (empty($rows)): ?>
                <tr><td colspan="9" class="blg-empty" style="display:table-cell">No transactions in this period.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                <tr class="<?= $rowCls($r) ?>">
                    <td class="sno-col sno-auto" data-label="S.No."></td>
                    <td data-label="Date"><?= $dmy($r['date']) ?></td>
                    <td data-label="Voucher"><?= esc($r['voucher'] !== '' ? $r['voucher'] : '-') ?><?php if ($r['reference_sub'] !== ''): ?> <small class="text-muted">(<?= esc($r['reference_sub']) ?>)</small><?php endif; ?></td>
                    <td data-label="Type"><span class="tbadge t-<?= $slug($r['ttype']) ?>"><?= esc($r['ttype']) ?></span></td>
                    <td data-label="Particulars" class="part"><?= esc($r['particulars']) ?></td>
                    <td data-label="Method"><?= pm_badge($r['method'] ?? '', $r['ttype'] === 'Transfer' ? '—' : 'Not recorded') ?></td>
                    <td data-label="Money In" class="num <?= $r['deposit'] > 0 ? 'amt-in' : 'amt-nil' ?>"><?= $r['deposit'] > 0 ? $fmt($r['deposit']) : '-' ?></td>
                    <td data-label="Money Out" class="num <?= $r['withdrawal'] > 0 ? 'amt-out' : 'amt-nil' ?>"><?= $r['withdrawal'] > 0 ? $fmt($r['withdrawal']) : '-' ?></td>
                    <td data-label="Balance" class="num bal <?= $r['balance'] < 0 ? 'bal-neg' : '' ?>"><?= $fmt($r['balance']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="8" class="num">Closing Balance</td><td class="num"><?= $fmt($statement['closing']) ?></td></tr></tfoot>
        </table>
    </div>
</div>

<?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(function () {
	var form = document.getElementById('blgForm'), timer;
	if (!form) { return; }
	$('#fAcc, #fFrom, #fTo, #fType').on('change', function () { form.submit(); });
	$('#fQ').on('input', function () { clearTimeout(timer); timer = setTimeout(function () { form.submit(); }, 600); });
	var q = document.getElementById('fQ');
	if (q && q.value) { q.focus(); q.setSelectionRange(q.value.length, q.value.length); }
});
</script>
<?= $this->endSection() ?>
