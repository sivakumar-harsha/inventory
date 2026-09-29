<?php
/** Outstanding Service Report PDF body. @var array $rows */
?>
<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Pending Count</div><div class="v"><?= (int) $kpi_pending_count ?></div></div>
    <div class="pdf-kpi"><div class="l">Pending Amount</div><div class="v"><?= pdf_currency($kpi_pending_amount) ?></div></div>
    <div class="pdf-kpi"><div class="l">Partial Count</div><div class="v"><?= (int) $kpi_partial_count ?></div></div>
    <div class="pdf-kpi"><div class="l">Total Outstanding</div><div class="v"><?= pdf_currency($kpi_total_outstanding) ?></div></div>
</div>

<table class="pdf-table">
    <thead>
        <tr>
            <th>Receipt No</th>
            <th>Date</th>
            <th>Customer</th>
            <th class="pdf-right">Grand Total</th>
            <th class="pdf-right">Outstanding</th>
            <th>Status</th>
            <th class="pdf-right">Age (Days)</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="pdf-center">No outstanding invoices match the applied filters.</td></tr>
        <?php else: ?>
        <?php $total = 0.0; foreach ($rows as $r): $total += (float) $r['outstanding_amount']; ?>
        <tr>
            <td><?= esc(pdf_text($r['receipt_no'])) ?></td>
            <td><?= esc(pdf_date($r['receipt_date'])) ?></td>
            <td><?= esc(pdf_text($r['customer_name'])) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['grand_total']) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['outstanding_amount']) ?></td>
            <td><?= esc(pdf_text($r['payment_status'])) ?></td>
            <td class="pdf-right"><?= (int) $r['age_days'] ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (! empty($rows)): ?>
    <tfoot>
        <tr>
            <td colspan="4" class="pdf-right">Total</td>
            <td class="pdf-right"><?= pdf_currency($total) ?></td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>
