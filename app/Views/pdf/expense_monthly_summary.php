<?php
/** Monthly Summary PDF body. @var array $rows */
?>
<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Months</div><div class="v"><?= (int) $kpi_total_months ?></div></div>
    <div class="pdf-kpi"><div class="l">Highest Month</div><div class="v"><?= pdf_currency($kpi_highest) ?></div></div>
    <div class="pdf-kpi"><div class="l">Lowest Month</div><div class="v"><?= pdf_currency($kpi_lowest) ?></div></div>
    <div class="pdf-kpi"><div class="l">Average</div><div class="v"><?= pdf_currency($kpi_average) ?></div></div>
</div>

<table class="pdf-table">
    <thead>
        <tr>
            <th>Month</th>
            <th class="pdf-right">Count</th>
            <th class="pdf-right">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="3" class="pdf-center">No PAID expenses recorded.</td></tr>
        <?php else: ?>
        <?php $total = 0.0; $count = 0; foreach ($rows as $r): $total += (float) $r['total']; $count += (int) $r['count']; ?>
        <tr>
            <td><?= esc($r['month_label']) ?></td>
            <td class="pdf-right"><?= (int) $r['count'] ?></td>
            <td class="pdf-right"><?= pdf_currency($r['total']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (! empty($rows)): ?>
    <tfoot>
        <tr>
            <td class="pdf-right">Total</td>
            <td class="pdf-right"><?= $count ?></td>
            <td class="pdf-right"><?= pdf_currency($total) ?></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>
