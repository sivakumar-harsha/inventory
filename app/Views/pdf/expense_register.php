<?php
/**
 * Expense Ledger PDF body (landscape). Built from ExpenseReports::_ledger(), so
 * it shows exactly what the screen shows.
 * @var array $ledger_rows @var array $summary @var float $closing
 */
?>
<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Outstanding Expense Bills</div><div class="v"><?= (int) $summary['pending_count'] ?></div></div>
    <div class="pdf-kpi"><div class="l">Outstanding Amount</div><div class="v"><?= pdf_currency($summary['outstanding']) ?></div></div>
    <div class="pdf-kpi"><div class="l">Total Expense Paid</div><div class="v"><?= pdf_currency($summary['paid_total']) ?></div></div>
    <div class="pdf-kpi"><div class="l">Net Expense</div><div class="v"><?= pdf_currency($summary['net']) ?></div></div>
</div>

<table class="pdf-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Voucher No</th>
            <th>Category</th>
            <th>Project</th>
            <th>Particulars</th>
            <th class="pdf-right">Debit</th>
            <th class="pdf-right">Credit</th>
            <th class="pdf-right">Balance</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($ledger_rows)): ?>
        <tr><td colspan="8" class="pdf-center">No expenses match the applied filters.</td></tr>
        <?php else: ?>
        <?php foreach ($ledger_rows as $r): ?>
        <tr>
            <td><?= esc(pdf_date($r['date'])) ?></td>
            <td><?= esc(pdf_text($r['voucher'])) ?></td>
            <td><?= esc(pdf_text($r['category'])) ?></td>
            <td><?= esc(pdf_text($r['project'])) ?></td>
            <td><?= esc(pdf_text($r['particulars'])) ?></td>
            <td class="pdf-right"><?= $r['debit'] > 0 ? pdf_currency($r['debit']) : '-' ?></td>
            <td class="pdf-right"><?= $r['credit'] > 0 ? pdf_currency($r['credit']) : '-' ?></td>
            <td class="pdf-right"><?= pdf_currency($r['balance']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (! empty($ledger_rows)): ?>
    <tfoot>
        <tr>
            <td colspan="7" class="pdf-right">Closing Balance</td>
            <td class="pdf-right"><?= pdf_currency($closing) ?></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>
