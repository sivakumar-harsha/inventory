<?php
$fmt      = static fn ($n) => number_format((float) $n, 2);
$dmy      = static fn ($d) => $d ? date('d/m/Y', strtotime($d)) : '-';
$qs       = http_build_query(array_filter(['from' => $from ?? '', 'to' => $to ?? '', 'type' => $type, 'q' => $q], static fn ($v) => $v !== ''));
$slug     = static fn ($t) => strtolower(str_replace(' ', '-', $t));
$rowCls   = static fn ($r) => $r['ttype'] === 'Cash Transfer' ? 'edge-xfer' : ($r['in'] > 0 ? 'edge-in' : 'edge-out');
$filtered = ($type !== '' || $q !== '');
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
	.cbk-page .ba-crumb { margin-bottom: 6px; }
	.cbk-page .ba-crumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.cbk-page .ba-crumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.cbk-page .ba-crumb .breadcrumb-item.active { color: #64748b; }

	.cbk-filters { display: grid; grid-template-columns: minmax(0, 1.4fr) repeat(2, minmax(0, 1fr)) minmax(0, 1.2fr) minmax(0, 1.6fr) auto; gap: 8px; align-items: end; padding: 8px 12px; margin-bottom: 6px; background: #fff; border: 1px solid var(--border-color); border-radius: var(--border-radius); }
	.cbk-filters label { display: block; margin: 0 0 2px; font-size: .62rem; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); }
	.cbk-filters .form-control { height: 32px; padding: 3px 8px; font-size: .8rem; }
	.cbk-filters .cbk-acct { height: 32px; display: flex; align-items: center; padding: 0 8px; font-size: .8rem; font-weight: 600; background: #f1f5f9; border: 1px solid var(--border-color); border-radius: var(--border-radius); }
	.cbk-filters .btn-cancel { height: 32px; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }

	.cbk-strip { position: sticky; top: 0; z-index: 3; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); align-items: center; gap: 2px 12px; min-height: 58px; padding: 5px 12px; margin-bottom: 8px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--border-radius); }
	.cbk-strip .st-item { display: flex; flex-direction: column; min-width: 0; }
	.cbk-strip .st-item > span { font-size: .62rem; line-height: 1.2; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); }
	.cbk-strip .st-item > b { font-size: .95rem; line-height: 1.3; font-variant-numeric: tabular-nums; white-space: nowrap; }
	.cbk-strip .st-open > b { color: #1d4ed8; }
	.cbk-strip .st-in > b { color: #16a34a; }
	.cbk-strip .st-out > b { color: #c2410c; }
	.cbk-strip .st-close > b { color: #14532d; }

	.cbk-scroll { max-height: 62vh; overflow: auto; }
	.cbk-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .8rem; }
	.cbk-table thead th { position: sticky; top: 0; z-index: 2; background: #f1f5f9; padding: 0 10px; height: 34px; font-size: .68rem; text-transform: none; letter-spacing: .03em; color: var(--text-muted); border-bottom: 1px solid var(--border-color); white-space: nowrap; }
	.cbk-table td { height: 44px; padding: 0 10px; border-bottom: 1px solid #eef2f7; vertical-align: middle; }
	.cbk-table td:first-child { border-left: 3px solid transparent; }
	.cbk-table tr.edge-in td:first-child { border-left-color: #16a34a; }
	.cbk-table tr.edge-out td:first-child { border-left-color: #ea580c; }
	.cbk-table tr.edge-xfer td:first-child, .cbk-table tr.edge-open td:first-child { border-left-color: #2563eb; }
	.cbk-table td.num, .cbk-table th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
	.cbk-table td.part { min-width: 240px; }
	.cbk-table tr.open-row td { background: #fff; font-weight: 600; }
	.cbk-table tfoot td { position: sticky; bottom: 0; background: #f1f5f9; font-weight: 700; height: 38px; }
	.amt-in { color: #16a34a; } .amt-out { color: #c2410c; } .amt-nil { color: #cbd5e1; }
	.bal { font-weight: 600; } .bal-neg { color: #b91c1c; }

	.tbadge { display: inline-flex; align-items: center; height: 20px; max-height: 22px; padding: 0 8px; border-radius: 10px; font-size: .66rem; font-weight: 600; white-space: nowrap; background: #e2e8f0; color: #475569; }
	.tbadge.t-customer-receipt { background: #dcfce7; color: #166534; }
	.tbadge.t-supplier-payment { background: #ffedd5; color: #9a3412; }
	.tbadge.t-expense-payment { background: #fef3c7; color: #92400e; }
	.tbadge.t-project-transaction { background: #dbeafe; color: #1e40af; }
	.tbadge.t-cash-transfer, .tbadge.t-opening-cash { background: #e0e7ff; color: #3730a3; }
	.tbadge.t-manual-entry { background: #e2e8f0; color: #475569; }

	.cbk-empty { padding: 26px 12px; text-align: center; color: #94a3b8; font-size: .85rem; }
	.cbk-note { margin: 0 0 6px; font-size: .75rem; color: var(--text-muted); }

	@media (max-width: 1199.98px) {
		.cbk-filters { grid-template-columns: repeat(3, minmax(0, 1fr)); }
		.cbk-strip { position: static; grid-template-columns: repeat(2, minmax(0, 1fr)); }
	}
	@media (max-width: 767.98px) {
		.cbk-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
		.cbk-strip { grid-template-columns: 1fr; }
		.cbk-scroll { max-height: none; overflow: visible; }
		.cbk-table thead { display: none; }
		.cbk-table, .cbk-table tbody, .cbk-table tfoot, .cbk-table tr, .cbk-table td { display: block; width: 100%; }
		.cbk-table tr { margin-bottom: 8px; border: 1px solid var(--border-color); border-left-width: 3px; border-radius: var(--border-radius); background: #fff; padding: 4px 0; }
		.cbk-table tr.edge-in { border-left-color: #16a34a; } .cbk-table tr.edge-out { border-left-color: #ea580c; } .cbk-table tr.edge-xfer, .cbk-table tr.edge-open { border-left-color: #2563eb; }
		.cbk-table td, .cbk-table td:first-child { height: auto; min-height: 28px; display: flex; justify-content: space-between; gap: 12px; padding: 4px 12px; border: 0; text-align: right; }
		.cbk-table td::before { content: attr(data-label); color: var(--text-muted); font-size: .68rem; text-transform: uppercase; text-align: left; flex: none; }
		.cbk-table td.part { min-width: 0; }
		.cbk-table tfoot td { position: static; }
	}
</style>

<div class="cbk-page">
<nav aria-label="breadcrumb" class="ba-crumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Accounts</li>
        <li class="breadcrumb-item active" aria-current="page">Cash Book</li>
    </ol>
</nav>

<form method="get" action="<?= base_url('cash-book') ?>" id="cbkForm" class="cbk-filters">
    <div><label>Cash Account</label><div class="cbk-acct">Cash Book</div></div>
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
    <div><label for="fQ">Search</label><input type="search" name="q" id="fQ" class="form-control" placeholder="Voucher, party, project, invoice, remarks…" value="<?= esc($q, 'attr') ?>"></div>
    <div class="dropdown">
        <button type="button" class="btn-cancel dropdown-toggle" data-toggle="dropdown" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-download"></i> Export</button>
        <div class="dropdown-menu dropdown-menu-right dropdown-menu-end">
            <a class="dropdown-item" href="<?= base_url('cash-book') . '?' . $qs . '&export=excel' ?>"><i class="bi bi-file-earmark-excel"></i> Excel</a>
            <a class="dropdown-item" href="<?= base_url('cash-book') . '?' . $qs . '&export=pdf' ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        </div>
    </div>
</form>

<div class="d-flex justify-content-end mb-2"><a href="<?= base_url('cash-book/opening-balance') ?>" class="btn-cancel"><i class="bi bi-cash-stack"></i> Opening Balance</a></div>
<?php if (! empty($statement['locked_opening'])): $lo = $statement['locked_opening']; ?>
<div class="cbk-note mb-2" id="cbkOpeningLock" style="font-size:.8rem">
    <i class="bi bi-lock-fill"></i> Opening Cash: <strong>₹ <?= $fmt($lo['amount']) ?></strong> · Opening Date: <strong><?= $dmy($lo['opening_date']) ?></strong> · Status: <strong>Locked</strong>
    <?php if (! empty($statement['excluded_before_opening'])): ?>
    <div class="mt-1 text-muted" id="cbkOpeningExcluded"><strong>Note:</strong> Some cash transactions are dated before the Cash Opening Date and are excluded from the current cash calculation.</div>
    <?php endif; ?>
</div>
<?php endif; ?>
<div class="cbk-strip">
    <div class="st-item st-open"><span>Opening Cash<?= ($statement['opening_date'] ?? $from) ? ' · ' . $dmy($statement['opening_date'] ?? $from) : '' ?></span><b>₹ <?= $fmt($statement['opening']) ?></b></div>
    <div class="st-item st-in"><span>Cash Received</span><b>₹ <?= $fmt($statement['cash_in']) ?></b></div>
    <div class="st-item st-out"><span>Cash Paid</span><b>₹ <?= $fmt($statement['cash_out']) ?></b></div>
    <div class="st-item st-close"><span>Closing Cash<?= $to ? ' · ' . $dmy($to) : '' ?></span><b>₹ <?= $fmt($statement['closing']) ?></b></div>
</div>

<?php if ($filtered): ?>
<p class="cbk-note">Showing <?= count($rows) ?> of <?= (int) $totalRows ?> transactions. Balances are always the true cash balance.</p>
<?php endif; ?>

<div class="card-custom mb-3">
    <div class="cbk-scroll">
        <table class="cbk-table" id="ledgerTable">
            <thead>
                <tr>
                    <th class="sno-col">S.No.</th>
                    <th>Date</th><th>Voucher No</th><th>Transaction Type</th><th>Particulars</th><th>Method</th>
                    <th class="num">Cash In</th><th class="num">Cash Out</th><th class="num">Running Cash Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr class="open-row edge-open">
                    <td class="sno-col" data-label="S.No."></td>
                    <td data-label="Date"><?= ($statement['opening_date'] ?? $from) ? $dmy($statement['opening_date'] ?? $from) : '' ?></td>
                    <td data-label="Voucher">-</td>
                    <td data-label="Type"><span class="tbadge t-opening-cash">Opening Cash</span></td>
                    <td data-label="Particulars" class="part"><?php if (! empty($statement['opening_is_setting'])): ?>Opening cash balance<?= ! empty($statement['opening_remarks']) ? ' — ' . esc($statement['opening_remarks']) : '' ?><?php else: ?>Opening cash<?= $from ? ' as on ' . $dmy($from) : '' ?><?php endif; ?></td>
                    <td data-label="Method"><?= pm_badge('') ?></td>
                    <td class="num amt-nil">-</td><td class="num amt-nil">-</td>
                    <td data-label="Balance" class="num bal <?= $statement['opening'] < 0 ? 'bal-neg' : '' ?>"><?= $fmt($statement['opening']) ?></td>
                </tr>
                <?php if (empty($rows)): ?>
                <tr><td colspan="9" class="cbk-empty" style="display:table-cell">No cash transactions in this period.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                <tr class="<?= $rowCls($r) ?>">
                    <td class="sno-col sno-auto" data-label="S.No."></td>
                    <td data-label="Date"><?= $dmy($r['date']) ?></td>
                    <td data-label="Voucher"><?= esc($r['voucher'] !== '' ? $r['voucher'] : '-') ?></td>
                    <td data-label="Type"><span class="tbadge t-<?= $slug($r['ttype']) ?>"><?= esc($r['ttype']) ?></span></td>
                    <td data-label="Particulars" class="part"><?= esc($r['particulars']) ?></td>
                    <td data-label="Method"><?= pm_badge($r['method'] ?? '', 'Not recorded') ?></td>
                    <td data-label="Cash In" class="num <?= $r['in'] > 0 ? 'amt-in' : 'amt-nil' ?>"><?= $r['in'] > 0 ? $fmt($r['in']) : '-' ?></td>
                    <td data-label="Cash Out" class="num <?= $r['out'] > 0 ? 'amt-out' : 'amt-nil' ?>"><?= $r['out'] > 0 ? $fmt($r['out']) : '-' ?></td>
                    <td data-label="Balance" class="num bal <?= $r['balance'] < 0 ? 'bal-neg' : '' ?>"><?= $fmt($r['balance']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php /* Release 4.9.0EF: footer totals are the same $statement cash_in / cash_out / closing as the summary cards above - nothing recalculated here. */ ?>
            <tfoot><tr>
                <td colspan="6" class="num">TOTAL</td>
                <td class="num amt-in" data-label="Total Cash In"><?= $fmt($statement['cash_in']) ?></td>
                <td class="num amt-out" data-label="Total Cash Out"><?= $fmt($statement['cash_out']) ?></td>
                <td class="num" data-label="Closing Cash"><span style="font-weight:600;color:#64748b;font-size:.68rem;">Closing Cash</span> <?= $fmt($statement['closing']) ?></td>
            </tr></tfoot>
        </table>
    </div>
</div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(function () {
	var form = document.getElementById('cbkForm'), timer;
	if (!form) { return; }
	$('#fFrom, #fTo, #fType').on('change', function () { form.submit(); });
	$('#fQ').on('input', function () { clearTimeout(timer); timer = setTimeout(function () { form.submit(); }, 600); });
	var q = document.getElementById('fQ');
	if (q && q.value) { q.focus(); q.setSelectionRange(q.value.length, q.value.length); }
});
</script>
<?= $this->endSection() ?>
