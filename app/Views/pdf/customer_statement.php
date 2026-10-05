<?php
/** Customer Ledger PDF body (landscape). @var array $customer @var array $summary @var array $rows */
// Release 4.9.0AI: same Cr-suffix / Customer Credit presentation as the
// on-screen ledger (see app/Views/customer_ledger/index.php) - no accounting
// value changes, summary['balance'] is the same whole-history figure already
// used for Closing Balance below.
$pdfBalance = (float) $summary['balance'];
$pdfBal     = static fn ($b) => $b < -0.004 ? pdf_currency(abs($b)) . ' Cr' : pdf_currency($b);
?>
<div class="pdf-section-title">Customer</div>
<table class="pdf-table">
    <tbody>
        <tr><td style="width:20%;"><strong>Name</strong></td><td><?= esc(pdf_text($customer['name'])) ?></td>
            <td style="width:20%;"><strong>GST Number</strong></td><td><?= esc(pdf_text($customer['gst'] ?? '')) ?></td></tr>
    </tbody>
</table>

<div class="pdf-kpi-row">
    <div class="pdf-kpi"><div class="l">Outstanding Invoices</div><div class="v"><?= (int) $summary['unpaid_count'] ?></div></div>
    <div class="pdf-kpi"><div class="l">Customer Advance</div><div class="v"><?= pdf_currency($summary['advance']) ?></div></div>
    <div class="pdf-kpi"><div class="l">Outstanding Amount</div><div class="v"><?= pdf_currency($summary['outstanding']) ?></div></div>
    <?php if ($pdfBalance < -0.004): ?>
    <div class="pdf-kpi"><div class="l">Customer Credit</div><div class="v"><?= pdf_currency(abs($pdfBalance)) ?> Cr</div></div>
    <?php else: ?>
    <div class="pdf-kpi"><div class="l">Net Receivable</div><div class="v"><?= pdf_currency($pdfBalance) ?></div></div>
    <?php endif; ?>
</div>

<div class="pdf-section-title">Customer Ledger — <?= esc(pdf_filter_line([
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
            <th>Method</th>
            <th class="pdf-right">Debit</th>
            <th class="pdf-right">Credit</th>
            <th class="pdf-right">Balance</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="8" class="pdf-center">No transactions found.</td></tr>
        <?php else: ?>
        <?php if (abs($opening) > 0.004): ?>
        <tr><td colspan="7"><em>Opening balance</em></td><td class="pdf-right"><?= $pdfBal($opening) ?></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $t): ?>
        <tr>
            <td><?= esc(pdf_date($t['date'])) ?></td>
            <td><?= esc(pdf_text($t['voucher'])) ?></td>
            <td><?= esc($t['ttype']) ?></td>
            <td><?= esc(pdf_text($t['particulars'])) ?></td>
            <td><?= esc(pm_label($t['method'], $t['ttype'] === 'Invoice' ? '—' : 'Not recorded')) ?></td>
            <td class="pdf-right"><?= $t['debit'] > 0 ? pdf_currency($t['debit']) : '-' ?></td>
            <td class="pdf-right"><?= $t['credit'] > 0 ? pdf_currency($t['credit']) : '-' ?></td>
            <td class="pdf-right"><?= $pdfBal($t['balance']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (! empty($rows)): ?>
    <tfoot>
        <tr>
            <td colspan="7" class="pdf-right">Closing Balance</td>
            <td class="pdf-right"><?= $pdfBal($closing) ?></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>
