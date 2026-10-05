<?php
/** Service Receipt Register PDF body. @var array $rows */
?>
<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Total Receipts</div><div class="v"><?= (int) $kpi_total_receipts ?></div></div>
    <div class="pdf-kpi"><div class="l">Invoice Amount</div><div class="v"><?= pdf_currency($kpi_invoice_amount) ?></div></div>
    <div class="pdf-kpi"><div class="l">Received Amount</div><div class="v"><?= pdf_currency($kpi_received_amount) ?></div></div>
    <div class="pdf-kpi"><div class="l">Outstanding</div><div class="v"><?= pdf_currency($kpi_outstanding) ?></div></div>
</div>

<table class="pdf-table">
    <thead>
        <tr>
            <th>Receipt No</th>
            <th>Date</th>
            <th>Type</th>
            <th>Customer</th>
            <th>Attended By</th>
            <th>Mode</th>
            <th>Status</th>
            <th class="pdf-right">Grand Total</th>
            <th class="pdf-right">Received</th>
            <th class="pdf-right">Outstanding</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="10" class="pdf-center">No service receipts match the applied filters.</td></tr>
        <?php else: ?>
        <?php $g = $rc = $o = 0.0; foreach ($rows as $r): $g += (float) $r['grand_total']; $rc += (float) $r['received_amount']; $o += (float) $r['outstanding_amount']; ?>
        <tr>
            <td><?= esc(pdf_text($r['receipt_no'])) ?></td>
            <td><?= esc(pdf_date($r['receipt_date'])) ?></td>
            <td><?= esc(pdf_text($r['receipt_type'])) ?></td>
            <td><?= esc(pdf_text($r['customer_name'])) ?></td>
            <td><?= esc(pdf_text($r['attended_person'] ?? '')) ?></td>
            <td><?= esc(pdf_text(pm_label($r['payment_mode'] ?? '', '-'))) ?></td>
            <td><?= esc(pdf_text($r['payment_status'])) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['grand_total']) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['received_amount']) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['outstanding_amount']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (! empty($rows)): ?>
    <tfoot>
        <tr>
            <td colspan="7" class="pdf-right">Total</td>
            <td class="pdf-right"><?= pdf_currency($g) ?></td>
            <td class="pdf-right"><?= pdf_currency($rc) ?></td>
            <td class="pdf-right"><?= pdf_currency($o) ?></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>
