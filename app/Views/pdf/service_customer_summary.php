<?php
/** Service Customer Summary PDF body. @var array $rows */
?>
<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Customers</div><div class="v"><?= (int) $kpi_total_customers ?></div></div>
    <div class="pdf-kpi"><div class="l">Invoice Amount</div><div class="v"><?= pdf_currency($kpi_invoice_amount) ?></div></div>
    <div class="pdf-kpi"><div class="l">Received</div><div class="v"><?= pdf_currency($kpi_received) ?></div></div>
    <div class="pdf-kpi"><div class="l">Outstanding</div><div class="v"><?= pdf_currency($kpi_outstanding) ?></div></div>
</div>

<table class="pdf-table">
    <thead>
        <tr>
            <th>Customer</th>
            <th>Phone</th>
            <th class="pdf-right">Invoice</th>
            <th class="pdf-right">Received</th>
            <th class="pdf-right">Outstanding</th>
            <th class="pdf-right">Advance</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="pdf-center">No customers match the applied filters.</td></tr>
        <?php else: ?>
        <?php $inv = $rec = $out = 0.0; foreach ($rows as $r): $inv += (float) $r['invoice']; $rec += (float) $r['received']; $out += (float) $r['outstanding']; ?>
        <tr>
            <td><?= esc(pdf_text($r['name'])) ?></td>
            <td><?= esc(pdf_text($r['phone'] ?? '')) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['invoice']) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['received']) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['outstanding']) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['advance']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (! empty($rows)): ?>
    <tfoot>
        <tr>
            <td colspan="2" class="pdf-right">Total</td>
            <td class="pdf-right"><?= pdf_currency($inv) ?></td>
            <td class="pdf-right"><?= pdf_currency($rec) ?></td>
            <td class="pdf-right"><?= pdf_currency($out) ?></td>
            <td></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>
