<?php
/**
 * Cash Book PDF body (landscape): the filtered rows, bracketed by the period's
 * Opening and Closing cash rows. @var array $statement @var array $rows
 */
?>
<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Opening Cash</div><div class="v"><?= pdf_currency($statement['opening']) ?></div></div>
    <div class="pdf-kpi"><div class="l">Cash Received</div><div class="v"><?= pdf_currency($statement['cash_in']) ?></div></div>
    <div class="pdf-kpi"><div class="l">Cash Paid</div><div class="v"><?= pdf_currency($statement['cash_out']) ?></div></div>
    <div class="pdf-kpi"><div class="l">Closing Cash</div><div class="v"><?= pdf_currency($statement['closing']) ?></div></div>
</div>

<table class="pdf-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Voucher No</th>
            <th>Type</th>
            <th>Particulars</th>
            <th class="pdf-right">Cash In</th>
            <th class="pdf-right">Cash Out</th>
            <th class="pdf-right">Balance</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td colspan="6"><strong>Opening Cash</strong></td>
            <td class="pdf-right"><strong><?= pdf_currency($statement['opening']) ?></strong></td>
        </tr>
        <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="pdf-center">No cash transactions match the applied filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= esc(pdf_date($r['date'])) ?></td>
            <td><?= esc(pdf_text($r['voucher'])) ?></td>
            <td><?= esc($r['ttype']) ?></td>
            <td><?= esc(pdf_text($r['particulars'])) ?></td>
            <td class="pdf-right"><?= $r['in'] > 0 ? pdf_currency($r['in']) : '-' ?></td>
            <td class="pdf-right"><?= $r['out'] > 0 ? pdf_currency($r['out']) : '-' ?></td>
            <td class="pdf-right"><?= pdf_currency($r['balance']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6" class="pdf-right">Closing Cash</td>
            <td class="pdf-right"><?= pdf_currency($statement['closing']) ?></td>
        </tr>
    </tfoot>
</table>
