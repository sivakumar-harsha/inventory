<?php
/** Project Summary PDF body. @var array $rows */
?>
<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Projects</div><div class="v"><?= (int) $kpi_project_count ?></div></div>
    <div class="pdf-kpi"><div class="l">Highest Project</div><div class="v"><?= pdf_currency($kpi_highest) ?></div></div>
    <div class="pdf-kpi"><div class="l">Total</div><div class="v"><?= pdf_currency($kpi_total) ?></div></div>
    <div class="pdf-kpi"><div class="l">Average</div><div class="v"><?= pdf_currency($kpi_average) ?></div></div>
</div>

<table class="pdf-table">
    <thead>
        <tr>
            <th>Project</th>
            <th class="pdf-right">Count</th>
            <th class="pdf-right">Total</th>
            <th class="pdf-right">% of Total</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="4" class="pdf-center">No PAID expenses recorded.</td></tr>
        <?php else: ?>
        <?php $total = 0.0; $count = 0; foreach ($rows as $r): $total += (float) $r['total']; $count += (int) ($r['count'] ?? 0); ?>
        <tr>
            <td><?= esc(pdf_text($r['project_name'] ?? $r['project'] ?? 'No Project')) ?></td>
            <td class="pdf-right"><?= (int) ($r['count'] ?? 0) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['total']) ?></td>
            <td class="pdf-right"><?= number_format((float) $r['percentage'], 1) ?>%</td>
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
            <td class="pdf-right">100.0%</td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>
