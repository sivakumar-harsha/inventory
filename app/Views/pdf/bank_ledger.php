<?php
/**
 * Bank Ledger PDF body (landscape): the filtered rows, bracketed by the period's
 * Opening and Closing balance rows. @var array $account @var array $statement @var array $rows
 */
?>
<div class="pdf-section-title">Account</div>
<table class="pdf-table">
    <tbody>
        <tr>
            <td style="width:14%;"><strong>Bank</strong></td><td><?= esc(pdf_text($account['bank_name'])) ?></td>
            <td style="width:14%;"><strong>Account</strong></td><td><?= esc(pdf_text($account['account_name'])) ?> (<?= esc(pdf_text($account['account_number'])) ?>)</td>
        </tr>
    </tbody>
</table>

<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Opening Balance</div><div class="v"><?= pdf_currency($statement['opening']) ?></div></div>
    <div class="pdf-kpi"><div class="l">Money In</div><div class="v"><?= pdf_currency($statement['deposits']) ?></div></div>
    <div class="pdf-kpi"><div class="l">Money Out</div><div class="v"><?= pdf_currency($statement['withdrawals']) ?></div></div>
    <div class="pdf-kpi"><div class="l">Closing Balance</div><div class="v"><?= pdf_currency($statement['closing']) ?></div></div>
</div>

<table class="pdf-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Voucher No</th>
            <th>Type</th>
            <th>Particulars</th>
            <th>Method</th>
            <th class="pdf-right">Money In</th>
            <th class="pdf-right">Money Out</th>
            <th class="pdf-right">Balance</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td colspan="7"><strong>Opening Balance</strong></td>
            <td class="pdf-right"><strong><?= pdf_currency($statement['opening']) ?></strong></td>
        </tr>
        <?php if (empty($rows)): ?>
        <tr><td colspan="8" class="pdf-center">No transactions match the applied filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= esc(pdf_date($r['date'])) ?></td>
            <td><?= esc(pdf_text($r['voucher'])) ?></td>
            <td><?= esc($r['ttype']) ?></td>
            <td><?= esc(pdf_text($r['particulars'])) ?></td>
            <td><?= esc(pm_label($r['method'] ?? '', $r['ttype'] === 'Transfer' ? '—' : 'Not recorded')) ?></td>
            <td class="pdf-right"><?= $r['deposit'] > 0 ? pdf_currency($r['deposit']) : '-' ?></td>
            <td class="pdf-right"><?= $r['withdrawal'] > 0 ? pdf_currency($r['withdrawal']) : '-' ?></td>
            <td class="pdf-right"><?= pdf_currency($r['balance']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="7" class="pdf-right">Closing Balance</td>
            <td class="pdf-right"><?= pdf_currency($statement['closing']) ?></td>
        </tr>
    </tfoot>
</table>
