<?php
/** Single Expense View PDF body (portrait). @var array $expense */
?>
<div class="pdf-section-title">Expense Details</div>
<table class="pdf-table">
    <tbody>
        <tr><td style="width:35%;"><strong>Expense No</strong></td><td><?= esc(pdf_text($expense['expense_no'])) ?></td></tr>
        <tr><td><strong>Date</strong></td><td><?= esc(pdf_date($expense['expense_date'])) ?></td></tr>
        <tr><td><strong>Category</strong></td><td><?= esc(pdf_text($expense['category_name'] ?? '')) ?></td></tr>
        <tr><td><strong>Project</strong></td><td><?= esc(pdf_text($expense['project_name'] ?? '')) ?></td></tr>
        <tr><td><strong>Paid To</strong></td><td><?= esc(pdf_text($expense['paid_to'])) ?></td></tr>
        <tr><td><strong>Payment Method</strong></td><td><?= esc(pdf_text(pm_label($expense['payment_method'], 'Not recorded'))) ?></td></tr>
        <tr><td><strong>Bank Account</strong></td><td><?= esc(pdf_text($bankLabel)) ?></td></tr>
        <tr><td><strong>Amount</strong></td><td><?= pdf_currency($expense['amount']) ?></td></tr>
        <tr><td><strong>Status</strong></td><td><?= esc(pdf_text($expense['status'])) ?></td></tr>
        <tr><td><strong>Remarks</strong></td><td><?= esc(pdf_text($expense['remarks'] ?? '')) ?></td></tr>
        <tr><td><strong>Recorded By</strong></td><td><?= esc(pdf_text($creatorName)) ?></td></tr>
        <tr><td><strong>Created On</strong></td><td><?= esc(pdf_datetime($expense['created_at'] ?? null)) ?></td></tr>
    </tbody>
</table>
