<?php
/** EMI Due Report PDF body. @var array $rows */
?>
<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Due This Month</div><div class="v"><?= pdf_currency($kpi_due_month) ?></div></div>
    <div class="pdf-kpi"><div class="l">Due Month Count</div><div class="v"><?= (int) $kpi_due_month_count ?></div></div>
    <div class="pdf-kpi"><div class="l">Overdue Count</div><div class="v"><?= (int) $kpi_overdue_count ?></div></div>
    <div class="pdf-kpi"><div class="l">Overdue Amount</div><div class="v"><?= pdf_currency($kpi_overdue_amount) ?></div></div>
</div>

<table class="pdf-table">
    <thead>
        <tr>
            <th>Loan No</th>
            <th>Lender</th>
            <th>EMI #</th>
            <th>Due Date</th>
            <th class="pdf-right">EMI Amount</th>
            <th class="pdf-right">Paid</th>
            <th class="pdf-right">Balance</th>
            <th>Status</th>
            <th class="pdf-right">Days Due</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="9" class="pdf-center">No unpaid EMIs match the applied filters.</td></tr>
        <?php else: ?>
        <?php $total = 0.0; foreach ($rows as $r): $total += (float) $r['balance_amount']; ?>
        <tr>
            <td><?= esc(pdf_text($r['loan_no'])) ?></td>
            <td><?= esc(pdf_text($r['lender_name'])) ?></td>
            <td><?= (int) $r['emi_no'] ?></td>
            <td><?= esc(pdf_date($r['due_date'])) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['emi_amount']) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['paid_amount']) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['balance_amount']) ?></td>
            <td><?= esc(pdf_text($r['payment_status'])) ?></td>
            <td class="pdf-right"><?= (int) $r['days_due'] ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (! empty($rows)): ?>
    <tfoot>
        <tr>
            <td colspan="6" class="pdf-right">Total Balance</td>
            <td class="pdf-right"><?= pdf_currency($total) ?></td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>
