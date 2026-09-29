<?php
/** Supplier Ledger PDF body (landscape). @var array $supplier @var array $summary @var array $rows */
?>
<div class="pdf-section-title">Supplier</div>
<table class="pdf-table">
    <tbody>
        <tr><td style="width:20%;"><strong>Name</strong></td><td><?= esc(pdf_text($supplier['name'])) ?></td>
            <td style="width:20%;"><strong>GST Number</strong></td><td><?= esc(pdf_text($supplier['gst'] ?? '')) ?></td></tr>
    </tbody>
</table>

<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Outstanding Bills</div><div class="v"><?= (int) $summary['unpaid_count'] ?></div></div>
    <div class="pdf-kpi"><div class="l">Outstanding Amount</div><div class="v"><?= pdf_currency($summary['outstanding']) ?></div></div>
    <div class="pdf-kpi"><div class="l">Advance Available</div><div class="v"><?= pdf_currency($summary['advance']) ?></div></div>
    <div class="pdf-kpi"><div class="l">Net Payable</div><div class="v"><?= pdf_currency($summary['net']) ?></div></div>
</div>

<div class="pdf-section-title">Supplier Ledger — <?= esc(pdf_filter_line([
    'From' => $filters['from'] ? pdf_date($filters['from']) : '', 'To' => $filters['to'] ? pdf_date($filters['to']) : '',
    'Type' => $filters['type'], 'Search' => $filters['q'],
])) ?></div>
<table class="pdf-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Voucher No</th>
            <th>Type</th>
            <th>Particulars</th>
            <th class="pdf-right">Debit</th>
            <th class="pdf-right">Credit</th>
            <th class="pdf-right">Balance</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="pdf-center">No transactions found.</td></tr>
        <?php else: ?>
        <?php if (abs($opening) > 0.004): ?>
        <tr><td colspan="6"><em>Opening balance</em></td><td class="pdf-right"><?= pdf_currency($opening) ?></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $t): ?>
        <tr>
            <td><?= esc(pdf_date($t['date'])) ?></td>
            <td><?= esc(pdf_text($t['voucher'])) ?></td>
            <td><?= esc($t['ttype']) ?></td>
            <td><?= esc(pdf_text($t['particulars'])) ?></td>
            <td class="pdf-right"><?= $t['debit'] > 0 ? pdf_currency($t['debit']) : '-' ?></td>
            <td class="pdf-right"><?= $t['credit'] > 0 ? pdf_currency($t['credit']) : '-' ?></td>
            <td class="pdf-right"><?= pdf_currency($t['balance']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (! empty($rows)): ?>
    <tfoot>
        <tr>
            <td colspan="6" class="pdf-right">Closing Balance</td>
            <td class="pdf-right"><?= pdf_currency($closing) ?></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>
