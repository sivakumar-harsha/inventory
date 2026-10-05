<?php
/** Collections Report PDF body. @var array $rows */
?>
<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Today</div><div class="v"><?= pdf_currency($kpi_today) ?></div></div>
    <div class="pdf-kpi"><div class="l">This Month</div><div class="v"><?= pdf_currency($kpi_month) ?></div></div>
    <div class="pdf-kpi"><div class="l">Filtered Total</div><div class="v"><?= pdf_currency($kpi_filtered) ?></div></div>
    <div class="pdf-kpi"><div class="l">Advance</div><div class="v"><?= pdf_currency($kpi_advance) ?></div></div>
</div>

<table class="pdf-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Reference</th>
            <th>Source</th>
            <th>Customer</th>
            <th>Mode</th>
            <th>Bank</th>
            <th class="pdf-right">Amount</th>
            <th>Remarks</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="8" class="pdf-center">No collections match the applied filters.</td></tr>
        <?php else: ?>
        <?php $total = 0.0; foreach ($rows as $r): $total += (float) $r['amount']; ?>
        <tr>
            <td><?= esc(pdf_date($r['date'])) ?></td>
            <td><?= esc(pdf_text($r['reference'])) ?></td>
            <td><?= esc(pdf_text($r['source'])) ?></td>
            <td><?= esc(pdf_text($r['customer'])) ?></td>
            <td><?= esc(pdf_text(pm_label($r['mode'] ?? '', '-'))) ?></td>
            <td><?= esc(pdf_text($r['bank'] ?? '')) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['amount']) ?></td>
            <td><?= esc(pdf_text($r['remarks'] ?? '')) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (! empty($rows)): ?>
    <tfoot>
        <tr>
            <td colspan="6" class="pdf-right">Total</td>
            <td class="pdf-right"><?= pdf_currency($total) ?></td>
            <td></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>
