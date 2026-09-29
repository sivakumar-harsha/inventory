<?php
/** Loan Register PDF body. @var array $rows */
?>
<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Total Loans</div><div class="v"><?= (int) $kpi_total_loans ?></div></div>
    <div class="pdf-kpi"><div class="l">Total Amount</div><div class="v"><?= pdf_currency($kpi_total_amount) ?></div></div>
    <div class="pdf-kpi"><div class="l">Outstanding</div><div class="v"><?= pdf_currency($kpi_outstanding) ?></div></div>
    <div class="pdf-kpi"><div class="l">Closed Loans</div><div class="v"><?= (int) $kpi_closed_loans ?></div></div>
</div>

<table class="pdf-table">
    <thead>
        <tr>
            <th>Loan No</th>
            <th>Lender</th>
            <th>Type</th>
            <th>Start Date</th>
            <th class="pdf-right">Sanctioned</th>
            <th class="pdf-right">Outstanding</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="pdf-center">No loans match the applied filters.</td></tr>
        <?php else: ?>
        <?php $totalSanctioned = 0.0; $totalOutstanding = 0.0; foreach ($rows as $r): $totalSanctioned += (float) $r['sanctioned_amount']; $totalOutstanding += (float) $r['outstanding_principal']; ?>
        <tr>
            <td><?= esc(pdf_text($r['loan_no'])) ?></td>
            <td><?= esc(pdf_text($r['lender_name'])) ?></td>
            <td><?= esc(pdf_text($r['loan_type'])) ?></td>
            <td><?= esc(pdf_date($r['start_date'])) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['sanctioned_amount']) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['outstanding_principal']) ?></td>
            <td><?= esc(pdf_text($r['status'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (! empty($rows)): ?>
    <tfoot>
        <tr>
            <td colspan="4" class="pdf-right">Total</td>
            <td class="pdf-right"><?= pdf_currency($totalSanctioned) ?></td>
            <td class="pdf-right"><?= pdf_currency($totalOutstanding) ?></td>
            <td></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>
