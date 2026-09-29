<?php
/** Payment Method Summary PDF body. @var array $rows */
?>
<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Total</div><div class="v"><?= pdf_currency($kpi_total) ?></div></div>
    <div class="pdf-kpi"><div class="l">Cash</div><div class="v"><?= pdf_currency($kpi_cash) ?></div></div>
    <div class="pdf-kpi"><div class="l">Bank</div><div class="v"><?= pdf_currency($kpi_bank) ?></div></div>
    <div class="pdf-kpi"><div class="l">Digital (Bank+Cheque+UPI)</div><div class="v"><?= pdf_currency($kpi_digital) ?></div></div>
</div>

<table class="pdf-table">
    <thead>
        <tr>
            <th>Payment Method</th>
            <th class="pdf-right">Count</th>
            <th class="pdf-right">Total</th>
            <th class="pdf-right">% of Total</th>
        </tr>
    </thead>
    <tbody>
        <?php $total = 0.0; $count = 0; foreach ($rows as $r): $total += (float) $r['total']; $count += (int) $r['count']; ?>
        <tr>
            <td><?= esc(pdf_text($r['payment_method'])) ?></td>
            <td class="pdf-right"><?= (int) $r['count'] ?></td>
            <td class="pdf-right"><?= pdf_currency($r['total']) ?></td>
            <td class="pdf-right"><?= number_format((float) $r['percentage'], 1) ?>%</td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td class="pdf-right">Total</td>
            <td class="pdf-right"><?= $count ?></td>
            <td class="pdf-right"><?= pdf_currency($total) ?></td>
            <td class="pdf-right">100.0%</td>
        </tr>
    </tfoot>
</table>
