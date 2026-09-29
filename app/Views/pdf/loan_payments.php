<?php
/** Loan Payment Register PDF body. @var array $rows */
?>
<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Total Payments</div><div class="v"><?= (int) $kpi_total_payments ?></div></div>
    <div class="pdf-kpi"><div class="l">Principal</div><div class="v"><?= pdf_currency($kpi_principal) ?></div></div>
    <div class="pdf-kpi"><div class="l">Interest</div><div class="v"><?= pdf_currency($kpi_interest) ?></div></div>
    <div class="pdf-kpi"><div class="l">Collection</div><div class="v"><?= pdf_currency($kpi_collection) ?></div></div>
</div>

<table class="pdf-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Loan No</th>
            <th>Lender</th>
            <th>EMI #</th>
            <th>Method</th>
            <th>Bank</th>
            <th>Reference</th>
            <th class="pdf-right">Principal</th>
            <th class="pdf-right">Interest</th>
            <th class="pdf-right">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="10" class="pdf-center">No loan payments match the applied filters.</td></tr>
        <?php else: ?>
        <?php $p = $i = $t = 0.0; foreach ($rows as $r): $p += (float) $r['principal_paid']; $i += (float) $r['interest_paid']; $t += (float) $r['total_paid']; ?>
        <tr>
            <td><?= esc(pdf_date($r['payment_date'])) ?></td>
            <td><?= esc(pdf_text($r['loan_no'])) ?></td>
            <td><?= esc(pdf_text($r['lender_name'])) ?></td>
            <td><?= $r['emi_no'] !== null ? (int) $r['emi_no'] : '-' ?></td>
            <td><?= esc(pdf_text($r['payment_method'])) ?></td>
            <td><?= esc(pdf_text($r['bank'] ?? '')) ?></td>
            <td><?= esc(pdf_text($r['reference_no'] ?? '')) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['principal_paid']) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['interest_paid']) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['total_paid']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (! empty($rows)): ?>
    <tfoot>
        <tr>
            <td colspan="7" class="pdf-right">Total</td>
            <td class="pdf-right"><?= pdf_currency($p) ?></td>
            <td class="pdf-right"><?= pdf_currency($i) ?></td>
            <td class="pdf-right"><?= pdf_currency($t) ?></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>
