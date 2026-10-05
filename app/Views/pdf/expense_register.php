<?php
/**
 * Expense Ledger PDF body (landscape). Built from ExpenseReports::_ledger(), so
 * it shows exactly what the screen shows.
 * @var array $ledger_rows @var array $summary
 */
?>
<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Total Expense Paid</div><div class="v"><?= pdf_currency($summary['paid_total']) ?></div></div>
</div>

<table class="pdf-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Voucher No</th>
            <th>Category</th>
            <th>Project</th>
            <th>Particulars</th>
            <th>Payment Method</th>
            <th class="pdf-right">Expense Amount</th>
            <th>Status</th>
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
            <td><?= esc(pdf_text(pm_label($r['payment_method'], 'Not recorded'))) ?></td>
            <td class="pdf-right"><?= $r['expense_amount'] > 0 ? pdf_currency($r['expense_amount']) : '-' ?></td>
            <td><?= esc(ucfirst(strtolower($r['status']))) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
