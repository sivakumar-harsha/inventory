<?php
/**
 * Release 4.9.0BT: Overall Monthly Statement page (read-only).
 * @var array|null $s  statement from App\Libraries\MonthlyStatement::build(), null on a rejected request
 * @var string|null $error
 */
$fmt  = static fn ($n) => number_format((float) $n, 2);
$dmy  = static fn ($d) => $d ? date('d-m-Y', strtotime($d)) : '-';
$qs   = http_build_query(['month' => $month, 'account' => $accountId ?: '', 'mode' => $mode]);
$neg  = static fn ($n) => ((float) $n) < 0 ? ' ms-neg' : '';
$showBank = $s ? $s['include_bank'] : true;
$showCash = $s ? $s['include_cash'] : true;
// 4.9.0BW: presentation only. Every figure below is read straight from the engine's $s — nothing is recalculated.
// ₹ amount; zero shows '-'; a negative keeps its minus sign (never converted to positive).
$money = static function ($n, bool $plus = false): string {
    $v = round((float) $n, 2);
    if ($v == 0.0) {
        return '<span class="ms-nil">-</span>';
    }

    return '<span' . ($v < 0 ? ' class="ms-neg"' : '') . '>' . ($v < 0 ? '-' : ($plus ? '+' : '')) . '₹' . number_format(abs($v), 2) . '</span>';
};
$line = static fn (string $labelHtml, string $amountHtml, string $cls = ''): string => '<div class="ms-r' . ($cls !== '' ? ' ' . $cls : '') . '"><span class="ms-l">' . $labelHtml . '</span><span class="ms-a">' . $amountHtml . '</span></div>';
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
	.ms-page .ba-crumb { margin-bottom: 6px; }
	.ms-page .ba-crumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.ms-page .ba-crumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.ms-page .ba-crumb .breadcrumb-item.active { color: #64748b; }

	.ms-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 4px 16px; margin-bottom: 6px; }
	.ms-head h1 { margin: 0; font-size: 1.15rem; font-weight: 700; }
	.ms-head .ms-meta { font-size: .76rem; color: var(--text-muted); }

	.ms-filters { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr) minmax(0, 1fr) auto; gap: 8px; align-items: end; padding: 8px 12px; margin-bottom: 8px; background: #fff; border: 1px solid var(--border-color); border-radius: var(--border-radius); }
	.ms-filters label { display: block; margin: 0 0 2px; font-size: .62rem; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); }
	.ms-filters .form-control { height: 32px; padding: 3px 8px; font-size: .8rem; }
	.ms-filters .btn-cancel { height: 32px; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }

	.ms-card { background: #fff; border: 1px solid var(--border-color); border-radius: var(--border-radius); margin-bottom: 8px; overflow: hidden; }
	.ms-scroll { overflow-x: auto; max-width: 100%; }
	.ms-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
	.ms-table th { background: #f1f5f9; padding: 0 10px; height: 30px; font-size: .66rem; text-transform: none; letter-spacing: .03em; color: var(--text-muted); border-bottom: 1px solid var(--border-color); white-space: nowrap; text-align: left; }
	.ms-table td { padding: 0 10px; height: 30px; border-bottom: 1px solid #eef2f7; vertical-align: middle; }
	.ms-table th.num, .ms-table td.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
	.ms-table td.ms-in { padding-left: 24px; color: #475569; font-size: .75rem; height: 24px; }
	.ms-table tr.ms-sec td { background: #2F7E8A; color: #fff; font-size: .68rem; font-weight: 700; letter-spacing: .05em; height: 26px; }
	.ms-table tr.ms-total td { background: #eef2f7; font-weight: 700; border-top: 1px solid #cbd5e1; }
	.ms-table tr.ms-grand td { background: #e2f0f2; font-weight: 700; border-top: 2px solid #2F7E8A; height: 34px; }
	.ms-table tr.ms-sub td { color: #475569; font-size: .75rem; height: 24px; }
	.ms-nil { color: #cbd5e1; }
	.ms-neg { color: #b91c1c; }
	.ms-hint { font-size: .68rem; color: var(--text-muted); font-weight: 400; text-transform: none; letter-spacing: 0; }

	.ms-warn { border: 1px solid #fcd34d; background: #fffbeb; border-radius: var(--border-radius); padding: 8px 12px; margin-bottom: 8px; font-size: .78rem; color: #78350f; }
	.ms-warn summary { cursor: pointer; font-weight: 700; }
	.ms-warn ul { margin: 6px 0 0; padding-left: 18px; }
	.ms-warn li { margin-bottom: 4px; }
	.ms-warn small { color: #92400e; }
	.ms-note { margin: 0 0 6px; font-size: .74rem; color: var(--text-muted); }
	.ms-err { border: 1px solid #fecaca; background: #fef2f2; color: #991b1b; border-radius: var(--border-radius); padding: 10px 12px; font-size: .85rem; }
	.ms-check { border: 1px solid #fecaca; background: #fef2f2; color: #991b1b; border-radius: var(--border-radius); padding: 6px 12px; font-size: .78rem; margin-bottom: 8px; }

	/* 4.9.0BW: Money In / Money Out statement — label and amount sit together in a constrained width */
	.ms-doc { max-width: 680px; }
	/* 4.9.0BZ: combined Particulars | Debit | Credit table — compact, restrained, scrolls inside its card when narrow */
	.ms-tx { min-width: 440px; }
	.ms-tx th { height: auto; padding: 6px 12px; line-height: 1.25; white-space: nowrap; }
	.ms-tx th small { display: block; font-size: .64rem; font-weight: 400; text-transform: none; letter-spacing: 0; color: var(--text-muted); }
	.ms-tx th:not(:first-child), .ms-tx td.num { width: 140px; }
	.ms-tx td { padding: 0 12px; height: 32px; font-size: .82rem; }
	.ms-tx td.ms-in { padding-left: 32px; }
	.ms-tx td.ms-subv { font-size: .75rem; color: #475569; }
	.ms-tx tr.ms-sub td { height: 24px; }
	.ms-tx tr.ms-split td { border-top: 1px solid #cbd5e1; }
	.ms-tx tfoot tr.ms-grand td { background: #e8eef4; border-top: 2px solid #64748b; height: 38px; font-size: .88rem; }
	.ms-sum { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(190px, 100%), 1fr)); gap: 8px; margin-bottom: 8px; }
	.ms-sum > div { background: #fff; border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 8px 12px; min-width: 0; }
	.ms-sum .ms-st { font-size: .66rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--text-muted); }
	.ms-sum .ms-sv { font-size: 1.15rem; font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1.5; overflow-wrap: anywhere; }
	.ms-sum .ms-sc { font-size: .7rem; color: var(--text-muted); }
	.ms-sum .ms-net { border-color: #94a3b8; background: #f8fafc; }
	.ms-h { padding: 7px 12px; background: #f1f5f9; border-bottom: 1px solid var(--border-color); font-size: .72rem; font-weight: 700; letter-spacing: .05em; color: #334155; }
	.ms-h small { display: block; font-size: .7rem; font-weight: 400; letter-spacing: 0; color: var(--text-muted); }
	.ms-r { display: grid; grid-template-columns: minmax(0, 1fr) auto; column-gap: 24px; align-items: baseline; padding: 6px 12px; border-bottom: 1px solid #eef2f7; font-size: .82rem; }
	.ms-r:last-child { border-bottom: 0; }
	.ms-a { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
	.ms-r.ms-sub { padding-top: 3px; padding-bottom: 3px; font-size: .75rem; color: #475569; }
	.ms-r.ms-sub .ms-l { padding-left: 20px; }
	.ms-r.ms-tot { background: #eef2f7; font-weight: 700; border-top: 1px solid #cbd5e1; }
	.ms-r.ms-big { background: #e8eef4; font-weight: 700; border-top: 2px solid #64748b; padding-top: 9px; padding-bottom: 9px; font-size: .9rem; }
	.ms-expl { padding: 6px 12px; font-size: .74rem; color: var(--text-muted); border-bottom: 1px solid #eef2f7; }
	.ms-expl b { color: #334155; font-variant-numeric: tabular-nums; }
	.ms-foot { padding: 6px 12px; font-size: .72rem; color: var(--text-muted); }
	.ms-h2 { margin: 14px 0 6px; font-size: .72rem; font-weight: 700; letter-spacing: .05em; color: var(--text-muted); text-transform: uppercase; }
	@media (max-width: 575.98px) {
		.ms-r { grid-template-columns: 1fr; row-gap: 1px; }
		.ms-a { justify-self: end; }
		.ms-r.ms-sub .ms-l { padding-left: 14px; }
	}

	.ms-page { min-width: 0; max-width: 100%; }
	.ms-filters > div { min-width: 0; }
	.ms-filters input.form-control { width: 100%; max-width: 100%; min-width: 0; }
	.ms-filters .select2-container { width: 100% !important; max-width: 100%; min-width: 0; }
	.ms-filters input[type=month] { min-width: 0; }
	@media (max-width: 991.98px) { .ms-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
	@media (max-width: 575.98px) { .ms-page { overflow-x: auto; } .ms-filters { grid-template-columns: 1fr; } .ms-table th, .ms-table td { padding: 0 6px; } .ms-table { font-size: .74rem; } }
</style>

<div class="ms-page">
<nav aria-label="breadcrumb" class="ba-crumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Reports</li>
        <li class="breadcrumb-item active" aria-current="page">Monthly Statement</li>
    </ol>
</nav>

<div class="ms-head">
    <h1>Monthly Statement<?= $s ? ' — ' . esc($s['label']) : '' ?></h1>
    <div class="ms-meta">A&amp;A Inventory ERP · Generated <?= date('d-m-Y') ?> · Cash &amp; bank movement (read-only)</div>
</div>

<form method="get" action="<?= base_url('monthly-statement') ?>" id="msForm" class="ms-filters">
    <div><label for="fMonth">Month</label><input type="month" name="month" id="fMonth" class="form-control" value="<?= esc($month, 'attr') ?>" pattern="[0-9]{4}-[0-9]{2}" required></div>
    <div>
        <label for="fAccount">Bank Account</label>
        <select name="account" id="fAccount" class="form-control">
            <option value="">All Accounts</option>
            <?php foreach ($accounts as $a): ?>
            <option value="<?= (int) $a['id'] ?>"<?= (int) $accountId === (int) $a['id'] ? ' selected' : '' ?>><?= esc($a['bank_name'] . ' — ' . $a['account_name'] . ' (' . $a['account_number'] . ')') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="fMode">Mode</label>
        <select name="mode" id="fMode" class="form-control">
            <?php foreach ($modes as $k => $l): ?>
            <option value="<?= esc($k, 'attr') ?>"<?= $mode === $k ? ' selected' : '' ?>><?= esc($l) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="dropdown">
        <button type="button" class="btn-cancel dropdown-toggle" data-toggle="dropdown" data-bs-toggle="dropdown" aria-expanded="false"<?= $s ? '' : ' disabled' ?>><i class="bi bi-download"></i> Export</button>
        <div class="dropdown-menu dropdown-menu-right dropdown-menu-end">
            <a class="dropdown-item" href="<?= base_url('monthly-statement') . '?' . $qs . '&export=excel' ?>"><i class="bi bi-file-earmark-excel"></i> Excel</a>
            <a class="dropdown-item" href="<?= base_url('monthly-statement') . '?' . $qs . '&export=pdf' ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        </div>
    </div>
</form>

<?php if ($error): ?>
<div class="ms-err" role="alert"><?= esc($error) ?></div>
<?php else: ?>

<?php if (! $s['checks']['bank_ok'] || ! $s['checks']['cash_ok']): ?>
<div class="ms-check" role="alert">Internal check failed: the statement closing does not agree with the account/cash walk. Do not rely on this page and report it.</div>
<?php endif; ?>

<?php foreach ($s['notes'] as $n): ?>
<p class="ms-note"><?= esc($n) ?></p>
<?php endforeach; ?>

<?php if ($s['gaps']): ?>
<details class="ms-warn" open>
    <summary>Data gaps — <?= count($s['gaps']) ?> kind<?= count($s['gaps']) === 1 ? '' : 's' ?> of record excluded from the totals below</summary>
    <div style="margin-top:4px">These records have no reliable cash/bank posting. They are not counted as cash or bank, nothing was invented for them, and they may prevent complete reconciliation.</div>
    <ul>
        <?php foreach ($s['gaps'] as $g): ?>
        <li><b><?= esc($g['title']) ?></b> — <?= (int) $g['count'] ?> record<?= $g['count'] === 1 ? '' : 's' ?>, ₹ <?= $fmt($g['amount']) ?><?= $g['in_month'] ? ' (' . (int) $g['in_month'] . ' in this month)' : ' (all earlier)' ?><br>
            <small><?= esc($g['why']) ?></small><br>
            <small>
                <?php foreach (array_slice($g['items'], 0, 6) as $i => $it): ?><?= $i ? '; ' : '' ?><?= esc($it['ref']) ?> · <?= $dmy($it['date']) ?> · ₹ <?= $fmt($it['amount']) ?><?php endforeach; ?><?= count($g['items']) > 6 ? '; … +' . (count($g['items']) - 6) . ' more' : '' ?>
            </small>
        </li>
        <?php endforeach; ?>
    </ul>
</details>
<?php endif; ?>

<?php
$in   = $s['total_receipts']['total'];
$out  = $s['total_payments']['total'];
$net  = $s['net_movement']['total'];
$t    = $s['transfers'];
$both = $showBank && $showCash;
$tLines = [
    'Bank to bank — transfer in'  => $t['bank_in'],
    'Bank to bank — transfer out' => -$t['bank_out'],
    'Cash deposited to bank'      => $t['cash_to_bank'] * (($showBank ? 1 : 0) + ($showCash ? -1 : 0)),
    'Cash withdrawn from bank'    => $t['bank_to_cash'] * (($showCash ? 1 : 0) + ($showBank ? -1 : 0)),
];
if (! array_filter($tLines, static fn ($v) => abs($v) >= 0.005) && abs($t['total']) >= 0.005) {
    // the engine gives only a net figure for this scope (e.g. Cash mode) — show it as one explicit line
    $tLines['Transfers to / from outside this view (net)'] = $t['total'];
}
?>
<div class="ms-doc" id="msMain">

<div class="ms-sum" id="msSummary">
    <div><div class="ms-st">Money In — Credit</div><div class="ms-sv"><?= $money($in) ?></div><div class="ms-sc">Money received during the month</div></div>
    <div><div class="ms-st">Money Out — Debit</div><div class="ms-sv"><?= $money($out) ?></div><div class="ms-sc">Money spent during the month</div></div>
    <div class="ms-net"><div class="ms-st">Net Movement</div><div class="ms-sv"><?= $money($net) ?></div><div class="ms-sc">Money In − Money Out</div></div>
</div>

<?php
// 4.9.0BZ: ONE combined table. A Money In line fills Credit, a Money Out line fills Debit; the other side shows '-'.
$cell = static fn (string $side, string $want, $v, bool $sub = false): string => '<td class="num' . ($sub ? ' ms-subv' : '') . '">' . ($side === $want ? $money($v) : '<span class="ms-nil">-</span>') . '</td>';
?>
<h2 class="ms-h2" style="margin-top:0">Monthly transactions</h2>
<div class="ms-card" id="msTx"><div class="ms-scroll">
<table class="ms-table ms-tx">
    <thead>
        <tr>
            <th>Particulars</th>
            <th class="num">Debit<small>Money Out</small></th>
            <th class="num">Credit<small>Money In</small></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ([['in', $s['receipts']], ['out', $s['payments']]] as [$grp, $lines]): foreach ($lines as $k => $l): $dc = $grp === 'in' ? 'C' : 'D'; ?>
        <tr<?= $grp === 'out' && $k === array_key_first($lines) ? ' class="ms-split"' : '' ?>>
            <td><?= esc($l['label']) ?></td>
            <?= $cell($dc, 'D', $l['total']) ?>
            <?= $cell($dc, 'C', $l['total']) ?>
        </tr>
        <?php foreach ($l['detail'] as $label => $d): $dv = ($d['bank'] ?? 0) + ($d['cash'] ?? 0); ?>
        <tr class="ms-sub">
            <td class="ms-in"><?= esc($label) ?></td>
            <?= $cell($dc, 'D', $dv, true) ?>
            <?= $cell($dc, 'C', $dv, true) ?>
        </tr>
        <?php endforeach; endforeach; endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="ms-grand">
            <td>MONTHLY TOTAL</td>
            <td class="num"><?= $money($out) ?></td>
            <td class="num"><?= $money($in) ?></td>
        </tr>
    </tfoot>
</table>
</div></div>

<div class="ms-card" id="msMove">
    <div class="ms-h">MONTHLY MOVEMENT<small><?= $dmy($s['from']) ?> to <?= $dmy($s['to']) ?></small></div>
    <?= $line('Opening Balance', $money($s['opening']['total']), 'ms-tot') ?>
    <?php if ($both): ?>
    <?= $line('Bank', $money($s['opening']['bank']), 'ms-sub') ?>
    <?= $line('Cash <span class="ms-hint">(derived from records' . (! empty($s['cash_opening_set']) ? '; from stored opening cash' : '; starts at zero') . ')</span>', $money($s['opening']['cash']), 'ms-sub') ?>
    <?php endif; ?>
    <?= $line('Money In — Credit', $money($in, true)) ?>
    <?= $line('Money Out — Debit', $money(-$out, true)) ?>
    <?= $line('Net Movement', $money($net), 'ms-tot') ?>
    <div class="ms-expl">Net Movement = Money In − Money Out: <b><?= $money($in) ?> − <?= $money($out) ?> = <?= $money($net) ?></b></div>
    <?php if (abs($t['total']) >= 0.005): ?>
    <?= $line('Net Transfers <span class="ms-hint">(own accounts — see Transfers below)</span>', $money($t['total'], true)) ?>
    <?php endif; ?>
    <?= $line('Closing Position <span class="ms-hint">· ' . $dmy($s['to']) . '</span>', $money($s['closing']['total']), 'ms-big') ?>
    <?php if ($both): ?>
    <?= $line('Bank', $money($s['closing']['bank']), 'ms-sub') ?>
    <?= $line('Cash <span class="ms-hint">(derived)</span>', $money($s['closing']['cash']), 'ms-sub') ?>
    <?php endif; ?>
    <div class="ms-foot">Closing Position is the position at the end of this month. It is not the account's current balance — see Account Detail below.</div>
</div>

<div class="ms-card" id="msTransfers">
    <div class="ms-h">TRANSFERS<small>Internal movements — not income or expense</small></div>
    <?php $shown = 0; foreach ($tLines as $label => $v): if (abs($v) < 0.005) { continue; } $shown++; ?>
    <?= $line(esc($label), $money($v, true)) ?>
    <?php endforeach; ?>
    <?php if (! $shown): ?><div class="ms-expl">No transfers between your own accounts or cash in this view.</div><?php endif; ?>
    <?= $line('Net Transfers <span class="ms-hint">' . (abs($t['total']) < 0.005 ? '(eliminated — both sides are inside this view)' : '(one side is outside this view)') . '</span>', $money($t['total'], true), 'ms-tot') ?>
    <div class="ms-foot">Transfer ≠ Money Received. Transfer ≠ Money Spent. A transfer only moves money between your own accounts / cash.</div>
</div>
</div>

<h2 class="ms-h2">Account detail</h2>

<?php if ($showBank): ?>
<div class="ms-card"><div class="ms-scroll">
<table class="ms-table" id="msBank">
    <thead>
        <tr><th colspan="8">Bank Account Detail</th></tr>
        <tr>
            <th>Account</th><th class="num">Opening</th><th class="num">Deposits</th><th class="num">Withdrawals</th>
            <th class="num">Transfers In</th><th class="num">Transfers Out</th><th class="num">Closing</th><th class="num">Current Balance</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($s['bank_detail'] as $d): ?>
        <tr>
            <td><?= esc($d['name']) ?><br><span class="ms-hint"><?= esc($d['number']) ?></span></td>
            <td class="num<?= $neg($d['opening']) ?>"><?= $fmt($d['opening']) ?></td>
            <td class="num"><?= $fmt($d['deposits']) ?></td>
            <td class="num"><?= $fmt($d['withdrawals']) ?></td>
            <td class="num"><?= $fmt($d['transfers_in']) ?></td>
            <td class="num"><?= $fmt($d['transfers_out']) ?></td>
            <td class="num ms-strong<?= $neg($d['closing']) ?>"><b><?= $fmt($d['closing']) ?></b></td>
            <td class="num<?= $neg($d['current_balance']) ?>"><?= $fmt($d['current_balance']) ?><?= abs($d['later']) > 0.004 ? '<br><span class="ms-hint">later months ' . ($d['later'] >= 0 ? '+' : '') . $fmt($d['later']) . '</span>' : '' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (! $s['bank_detail']): ?><tr><td colspan="8" class="ms-nil" style="text-align:center">No bank accounts.</td></tr><?php endif; ?>
    </tbody>
</table>
</div></div>
<p class="ms-note">Deposits and Withdrawals include cash deposited to / withdrawn from the account (they are internal transfers in the statement above). Closing + movement in later months = the account's current balance.</p>
<?php endif; ?>

<?php if ($showCash): $c = $s['cash_detail']; ?>
<div class="ms-card"><div class="ms-scroll">
<table class="ms-table" id="msCash">
    <thead><tr><th colspan="2">Cash Detail <span class="ms-hint">(derived from source records — no cash table exists)</span></th></tr></thead>
    <tbody>
        <tr><td>Opening Cash</td><td class="num<?= $neg($c['opening']) ?>"><?= $fmt($c['opening']) ?></td></tr>
        <tr><td>Cash Receipts</td><td class="num"><?= $fmt($c['receipts']) ?></td></tr>
        <tr><td>Cash Payments</td><td class="num"><?= $fmt($c['payments']) ?></td></tr>
        <tr><td>Cash Transfers <span class="ms-hint">(+ withdrawn from bank, − deposited to bank)</span></td><td class="num<?= $neg($c['transfers']) ?>"><?= $fmt($c['transfers']) ?></td></tr>
        <tr class="ms-total"><td>Closing Cash</td><td class="num<?= $neg($c['closing']) ?>"><?= $fmt($c['closing']) ?></td></tr>
    </tbody>
</table>
</div></div>
<?php endif; ?>

<?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(function () {
	var form = document.getElementById('msForm');
	if (!form) { return; }
	$('#fMonth, #fAccount, #fMode').on('change', function () {
		var m = document.getElementById('fMonth');
		if (m && m.value && /^\d{4}-(0[1-9]|1[0-2])$/.test(m.value)) { form.submit(); }
	});
});
</script>
<?= $this->endSection() ?>
