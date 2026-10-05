<?php
/** Outstanding Loan Report PDF body. @var array $rows */
?>
<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Total Outstanding</div><div class="v"><?= pdf_currency($kpi_outstanding) ?></div></div>
    <div class="pdf-kpi"><div class="l">Active Loans</div><div class="v"><?= (int) $kpi_active_loans ?></div></div>
    <div class="pdf-kpi"><div class="l">Closed Loans</div><div class="v"><?= (int) $kpi_closed_loans ?></div></div>
    <div class="pdf-kpi"><div class="l">Average (Active)</div><div class="v"><?= pdf_currency($kpi_average) ?></div></div>
</div>

<table class="pdf-table">
    <thead>
        <tr>
            <th>Loan No</th>
            <th>Lender</th>
            <th>Type</th>
            <th class="pdf-right">Outstanding Amount</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="5" class="pdf-center">No loans match the applied filters.</td></tr>
        <?php else: ?>
        <?php $total = 0.0; foreach ($rows as $r): $total += (float) $r['outstanding_principal']; ?>
        <tr>
            <td><?= esc(pdf_text($r['loan_no'])) ?></td>
            <td><?= esc(pdf_text($r['lender_name'])) ?></td>
            <td><?= esc(pdf_text($r['loan_type'])) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['outstanding_principal']) ?></td>
            <td><?= esc(pdf_text($r['status'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (! empty($rows)): ?>
    <tfoot>
        <tr>
            <td colspan="3" class="pdf-right">Total</td>
            <td class="pdf-right"><?= pdf_currency($total) ?></td>
            <td></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>
