<?php
/**
 * Release 4.9.0BT: Monthly Statement PDF body (landscape). Same figures as the page.
 * @var array $s statement from App\Libraries\MonthlyStatement::build()
 */
$showBank = $s['include_bank'];
$showCash = $s['include_cash'];
$t        = $s['transfers'];
// 4.9.0BW: amount beside its label; negatives keep their minus sign, zero shows '-'. No recalculation.
// $plus adds an explicit + to positive amounts (movement lines).
$line = static function (string $label, $amount, bool $bold = false, bool $plus = false): string {
    $v  = round((float) $amount, 2);
    $tx = $v == 0.0 ? '-' : ($v < 0 ? '-' : ($plus ? '+' : '')) . pdf_currency(abs($v));
    $st = $bold ? ' style="font-weight:700;background:#eef2f7;"' : '';

    if (! $bold && preg_match('/^\s+/', $label)) {
        // sub-line (breakdown of the line above): indented and muted, so it is not read as a separate amount
        return '<tr><td style="padding-left:20px;color:#64748b;">' . esc(trim($label)) . '</td><td class="pdf-right" style="color:#64748b;">' . $tx . '</td></tr>';
    }

    return '<tr' . $st . '><td>' . esc($label) . '</td><td class="pdf-right">' . $tx . '</td></tr>';
};
?>
<table class="pdf-table" style="margin-bottom:6px;">
    <tr>
        <td><strong>Month:</strong> <?= esc($s['label']) ?></td>
        <td><strong>Period:</strong> <?= esc(pdf_date($s['from'])) ?> to <?= esc(pdf_date($s['to'])) ?></td>
        <td><strong>Bank Account:</strong> <?= esc($s['account'] ? $s['account']['bank_name'] . ' — ' . $s['account']['account_name'] : 'All Accounts') ?></td>
        <td><strong>Mode:</strong> <?= esc(\App\Libraries\MonthlyStatement::MODES[$s['mode']]) ?></td>
    </tr>
</table>

<?php foreach ($s['notes'] as $note): ?>
<div style="font-size:8.5px;color:#64748b;margin-bottom:2px;"><?= esc($note) ?></div>
<?php endforeach; ?>

<?php if ($s['gaps']): ?>
<div style="border:1px solid #fcd34d;background:#fffbeb;padding:5px 8px;margin:4px 0 8px;font-size:8.5px;color:#78350f;">
    <strong>Data gaps — excluded from all totals; they may prevent complete reconciliation.</strong>
    <?php foreach ($s['gaps'] as $g): ?>
    <div>• <strong><?= esc($g['title']) ?></strong>: <?= (int) $g['count'] ?> record(s), <?= pdf_currency($g['amount']) ?>
        <?php foreach (array_slice($g['items'], 0, 4) as $i => $it): ?><?= $i ? '; ' : ' — ' ?><?= esc($it['ref']) ?> <?= esc(pdf_date($it['date'])) ?> <?= pdf_currency($it['amount']) ?><?php endforeach; ?><?= count($g['items']) > 4 ? '; … +' . (count($g['items']) - 4) . ' more' : '' ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$in  = $s['total_receipts']['total'];
$out = $s['total_payments']['total'];
$net = $s['net_movement']['total'];
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
<div class="pdf-section-title">Monthly Summary</div>
<table class="pdf-table" style="width:60%;">
    <tbody>
        <?= $line('Money In — Credit (money received)', $in) ?>
        <?= $line('Money Out — Debit (money spent)', $out) ?>
        <?= $line('Net Movement (Money In − Money Out)', $net, true) ?>
    </tbody>
</table>

<?php
// 4.9.0BZ: ONE combined table. Money In fills Credit, Money Out fills Debit; the other side shows '-'.
$amt = static function ($v): string {
    $v = round((float) $v, 2);

    return $v == 0.0 ? '-' : ($v < 0 ? '-' : '') . pdf_currency(abs($v));
};
?>
<div style="page-break-inside:avoid;"><div class="pdf-section-title">Monthly Transactions</div>
<table class="pdf-table" style="width:66%;">
    <thead>
        <tr>
            <th>Particulars</th>
            <th class="pdf-right" style="width:20%;">Debit<br><span style="font-weight:400;">Money Out</span></th>
            <th class="pdf-right" style="width:20%;">Credit<br><span style="font-weight:400;">Money In</span></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ([['in', $s['receipts']], ['out', $s['payments']]] as [$grp, $lines]): foreach ($lines as $k => $l): ?>
        <tr>
            <td><?= esc($l['label']) ?></td>
            <td class="pdf-right"><?= $grp === 'out' ? $amt($l['total']) : '-' ?></td>
            <td class="pdf-right"><?= $grp === 'in' ? $amt($l['total']) : '-' ?></td>
        </tr>
        <?php foreach ($l['detail'] as $label => $d): $dv = ($d['bank'] ?? 0) + ($d['cash'] ?? 0); ?>
        <tr style="color:#64748b;">
            <td style="padding-left:20px;"><?= esc($label) ?></td>
            <td class="pdf-right"><?= $grp === 'out' ? $amt($dv) : '-' ?></td>
            <td class="pdf-right"><?= $grp === 'in' ? $amt($dv) : '-' ?></td>
        </tr>
        <?php endforeach; endforeach; endforeach; ?>
        <tr style="font-weight:700;background:#eef2f7;">
            <td>MONTHLY TOTAL</td>
            <td class="pdf-right"><?= $amt($out) ?></td>
            <td class="pdf-right"><?= $amt($in) ?></td>
        </tr>
    </tbody>
</table>
</div>

<div style="page-break-inside:avoid;">
<div class="pdf-section-title">Monthly Movement (<?= esc(pdf_date($s['from'])) ?> to <?= esc(pdf_date($s['to'])) ?>)</div>
<table class="pdf-table" style="width:60%;">
    <tbody>
        <?= $line('Opening Balance', $s['opening']['total'], true) ?>
        <?php if ($both): ?>
        <?= $line('      Bank', $s['opening']['bank']) ?>
        <?= $line('      Cash (derived)', $s['opening']['cash']) ?>
        <?php endif; ?>
        <?= $line('Money In — Credit', $in, false, true) ?>
        <?= $line('Money Out — Debit', -$out, false, true) ?>
        <?= $line('Net Movement', $net, true) ?>
        <?php if (abs($t['total']) >= 0.005): ?><?= $line('Net Transfers (own accounts, see below)', $t['total'], false, true) ?><?php endif; ?>
        <?= $line('Closing Position — ' . pdf_date($s['to']), $s['closing']['total'], true) ?>
        <?php if ($both): ?>
        <?= $line('      Bank', $s['closing']['bank']) ?>
        <?= $line('      Cash (derived)', $s['closing']['cash']) ?>
        <?php endif; ?>
    </tbody>
</table>
<div style="font-size:8px;color:#64748b;">Net Movement = Money In − Money Out. Closing Position is the position at month end, not the account's current balance.</div>
</div>

<div style="page-break-inside:avoid;">
<div class="pdf-section-title">Transfers — internal movements, not income or expense</div>
<table class="pdf-table" style="width:60%;">
    <tbody>
        <?php foreach ($tLines as $label => $v): if (abs($v) < 0.005) { continue; } ?>
        <?= $line($label, $v, false, true) ?>
        <?php endforeach; ?>
        <?= $line('NET TRANSFERS' . (abs($t['total']) < 0.005 ? ' (eliminated)' : ''), $t['total'], true, true) ?>
    </tbody>
</table>
<div style="font-size:8px;color:#64748b;">Transfer is not Money Received and not Money Spent.</div>
</div>

<?php if ($showBank && $s['bank_detail']): ?>
<div style="page-break-inside:avoid;"><div class="pdf-section-title">Bank Account Detail</div>
<table class="pdf-table">
    <thead>
        <tr>
            <th>Account</th><th class="pdf-right">Opening</th><th class="pdf-right">Deposits</th><th class="pdf-right">Withdrawals</th>
            <th class="pdf-right">Transfers In</th><th class="pdf-right">Transfers Out</th><th class="pdf-right">Closing</th><th class="pdf-right">Current Balance</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($s['bank_detail'] as $d): ?>
        <tr>
            <td><?= esc($d['name']) ?> (<?= esc($d['number']) ?>)</td>
            <td class="pdf-right"><?= pdf_currency($d['opening']) ?></td>
            <td class="pdf-right"><?= pdf_currency($d['deposits']) ?></td>
            <td class="pdf-right"><?= pdf_currency($d['withdrawals']) ?></td>
            <td class="pdf-right"><?= pdf_currency($d['transfers_in']) ?></td>
            <td class="pdf-right"><?= pdf_currency($d['transfers_out']) ?></td>
            <td class="pdf-right"><strong><?= pdf_currency($d['closing']) ?></strong></td>
            <td class="pdf-right"><?= pdf_currency($d['current_balance']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
<div style="font-size:8px;color:#64748b;">Deposits/Withdrawals include cash deposited to / withdrawn from the account. Closing + later months' movement = current balance.</div>
<?php endif; ?>

<?php if ($showCash): $c = $s['cash_detail']; ?>
<div style="page-break-inside:avoid;"><div class="pdf-section-title">Cash Detail (derived from source records)</div>
<table class="pdf-table" style="width:50%;">
    <tbody>
        <tr><td>Opening Cash</td><td class="pdf-right"><?= pdf_currency($c['opening']) ?></td></tr>
        <tr><td>Cash Receipts</td><td class="pdf-right"><?= pdf_currency($c['receipts']) ?></td></tr>
        <tr><td>Cash Payments</td><td class="pdf-right"><?= pdf_currency($c['payments']) ?></td></tr>
        <tr><td>Cash Transfers</td><td class="pdf-right"><?= pdf_currency($c['transfers']) ?></td></tr>
        <tr style="font-weight:700;background:#eef2f7;"><td>Closing Cash</td><td class="pdf-right"><?= pdf_currency($c['closing']) ?></td></tr>
    </tbody>
</table>
</div>
<?php endif; ?>
