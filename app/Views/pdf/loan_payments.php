<?php
/** Loan Payment Register PDF body. @var array $rows */
?>
<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Total Payments</div><div class="v"><?= (int) $kpi_total_payments ?></div></div>
    <div class="pdf-kpi"><div class="l">Total Paid</div><div class="v"><?= pdf_currency($kpi_collection) ?></div></div>
</div>

<table class="pdf-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Loan No</th>
            <th>Lender</th>
            <th>Method</th>
            <th>Bank Account</th>
            <th>Reference</th>
            <th>Remarks</th>
            <th class="pdf-right">Payment Amount</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="8" class="pdf-center">No loan payments match the applied filters.</td></tr>
        <?php else: ?>
        <?php $t = 0.0; foreach ($rows as $r): $t += (float) $r['total_paid']; ?>
        <tr>
            <td><?= esc(pdf_date($r['payment_date'])) ?></td>
            <td><?= esc(pdf_text($r['loan_no'])) ?></td>
            <td><?= esc(pdf_text($r['lender_name'])) ?></td>
            <td><?= esc(pdf_text(pm_label($r['payment_method'], 'Not recorded'))) ?></td>
            <td><?= esc(pdf_text($r['bank'] ?? '')) ?></td>
            <td><?= esc(pdf_text($r['reference_no'] ?? '')) ?></td>
            <td><?= esc(pdf_text($r['remarks'] ?? '')) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['total_paid']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (! empty($rows)): ?>
    <tfoot>
        <tr>
            <td colspan="6" class="pdf-right">Total</td>
            <td class="pdf-right"><?= pdf_currency($t) ?></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>
