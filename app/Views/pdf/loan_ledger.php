<?php
/** Loan Ledger PDF body (portrait). @var array $loan @var array $rows */
?>
<div class="pdf-section-title">Loan Summary</div>
<table class="pdf-table">
    <tbody>
        <tr><td style="width:35%;"><strong>Loan No</strong></td><td><?= esc(pdf_text($loan['loan_no'])) ?></td></tr>
        <tr><td><strong>Lender</strong></td><td><?= esc(pdf_text($loan['lender_name'])) ?></td></tr>
        <tr><td><strong>Type</strong></td><td><?= esc(pdf_text($loan['loan_type'])) ?></td></tr>
        <tr><td><strong>Sanctioned Amount</strong></td><td><?= pdf_currency($loan['sanctioned_amount']) ?></td></tr>
        <tr><td><strong>Outstanding Principal</strong></td><td><?= pdf_currency($loan['outstanding_principal']) ?></td></tr>
        <tr><td><strong>Status</strong></td><td><?= esc(pdf_text($loan['status'])) ?></td></tr>
        <tr><td><strong>EMIs Paid / Total</strong></td><td><?= (int) $emis_paid ?> / <?= (int) $emis_total ?> (Pending: <?= (int) $emis_pending ?>)</td></tr>
    </tbody>
</table>

<div class="pdf-section-title">Ledger</div>
<table class="pdf-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Particulars</th>
            <th>Reference</th>
            <th>Method</th>
            <th class="pdf-right">Debit</th>
            <th class="pdf-right">Credit</th>
            <th class="pdf-right">Principal</th>
            <th class="pdf-right">Interest</th>
            <th class="pdf-right">Balance</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= esc(pdf_date($r['date'])) ?></td>
            <td><?= esc(pdf_text($r['label'])) ?></td>
            <td><?= esc(pdf_text($r['reference'])) ?></td>
            <td><?= esc(pdf_text($r['method'])) ?></td>
            <td class="pdf-right"><?= $r['debit'] > 0 ? pdf_currency($r['debit']) : '-' ?></td>
            <td class="pdf-right"><?= $r['credit'] > 0 ? pdf_currency($r['credit']) : '-' ?></td>
            <td class="pdf-right"><?= $r['principal'] > 0 ? pdf_currency($r['principal']) : '-' ?></td>
            <td class="pdf-right"><?= $r['interest'] > 0 ? pdf_currency($r['interest']) : '-' ?></td>
            <td class="pdf-right"><?= pdf_currency($r['balance']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="pdf-right">Total Collected</td>
            <td class="pdf-right"><?= pdf_currency($total_credit) ?></td>
            <td class="pdf-right"><?= pdf_currency($total_principal) ?></td>
            <td class="pdf-right"><?= pdf_currency($total_interest) ?></td>
            <td></td>
        </tr>
    </tfoot>
</table>
