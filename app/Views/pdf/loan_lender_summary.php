<?php
/** Lender Summary PDF body. @var array $rows */
?>
<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Lenders</div><div class="v"><?= (int) $kpi_total_lenders ?></div></div>
    <div class="pdf-kpi"><div class="l">Borrowed</div><div class="v"><?= pdf_currency($kpi_borrowed) ?></div></div>
    <div class="pdf-kpi"><div class="l">Outstanding</div><div class="v"><?= pdf_currency($kpi_outstanding) ?></div></div>
    <div class="pdf-kpi"><div class="l">Interest Paid</div><div class="v"><?= pdf_currency($kpi_interest_paid) ?></div></div>
</div>

<table class="pdf-table">
    <thead>
        <tr>
            <th>Lender</th>
            <th class="pdf-right">Loans</th>
            <th class="pdf-right">Borrowed</th>
            <th class="pdf-right">Principal Paid</th>
            <th class="pdf-right">Interest Paid</th>
            <th class="pdf-right">Outstanding</th>
            <th class="pdf-right">Active</th>
            <th class="pdf-right">Closed</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="8" class="pdf-center">No lenders match the applied filters.</td></tr>
        <?php else: ?>
        <?php $borrowed = $principal = $interest = $outstanding = 0.0; foreach ($rows as $r):
            $borrowed += (float) $r['borrowed']; $principal += (float) $r['principal_paid'];
            $interest += (float) $r['interest_paid']; $outstanding += (float) $r['outstanding'];
        ?>
        <tr>
            <td><?= esc(pdf_text($r['lender_name'])) ?></td>
            <td class="pdf-right"><?= (int) $r['loan_count'] ?></td>
            <td class="pdf-right"><?= pdf_currency($r['borrowed']) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['principal_paid']) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['interest_paid']) ?></td>
            <td class="pdf-right"><?= pdf_currency($r['outstanding']) ?></td>
            <td class="pdf-right"><?= (int) $r['active_loans'] ?></td>
            <td class="pdf-right"><?= (int) $r['closed_loans'] ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (! empty($rows)): ?>
    <tfoot>
        <tr>
            <td class="pdf-right">Total</td>
            <td></td>
            <td class="pdf-right"><?= pdf_currency($borrowed) ?></td>
            <td class="pdf-right"><?= pdf_currency($principal) ?></td>
            <td class="pdf-right"><?= pdf_currency($interest) ?></td>
            <td class="pdf-right"><?= pdf_currency($outstanding) ?></td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>
