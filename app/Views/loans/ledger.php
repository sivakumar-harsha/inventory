<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
helper('loan_ui');
$fmtDate    = static fn ($d) => $d ? date('d-m-Y', strtotime($d)) : '—';
$money      = static fn ($n) => number_format((float) $n, 2);
$last       = end($rows);
$running    = $last ? (float) $last['balance'] : (float) $loan['sanctioned_amount'];
$drift      = abs($running - (float) $loan['outstanding_principal']) > 0.004;
$isActive   = $loan['status'] === 'ACTIVE';
$typeLabels = \App\Models\LoanTypeModel::labels();
$rate       = rtrim(rtrim(number_format((float) $loan['interest_rate'], 3, '.', ''), '0'), '.');
$rowClass   = ['DISBURSEMENT' => 'ln-row-disb', 'PAYMENT' => 'ln-row-pay'];
?>
<?= $this->include('loans/partials/ui_styles') ?>

<style>
	.ln-breadcrumb { margin-bottom: 8px; }
	.ln-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.ln-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.ln-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.ln-breadcrumb .breadcrumb-item.active { color: #64748b; }

	/* loan terms cards */
	.ln-info-card { height: 100%; padding: 6px 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; }
	.ln-info-card .l { font-size: .68rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; color: #64748b; }
	.ln-info-card .v { font-size: .95rem; font-weight: 700; color: #1e293b; }

	/* financial summary cards: equal height, compact, icon + value/label side by side */
	#ledgerTotals .kpi-card { height: 100%; padding: 8px 12px; gap: 10px; border-radius: 8px; }
	#ledgerTotals .kpi-icon { font-size: 1.3rem; line-height: 1; }
	#ledgerTotals .kpi-value { font-size: 1rem; line-height: 1.25; }
	#ledgerTotals .kpi-label { margin-top: 0; font-size: .72rem; white-space: nowrap; }
	.page-title { margin-bottom: 10px; }

	/* the statement: header stays put while the rows scroll (.ln-scroll / .ln-sticky come from ui_styles) */
	#ledgerTable { min-width: 900px; }
	#ledgerTable th, #ledgerTable td { padding: 6px 10px; font-size: .78rem; white-space: nowrap; }
	#ledgerTable .num { text-align: right; }
	#ledgerTable tfoot td { font-weight: 700; background: #f8fafc; border-top: 1px solid #cbd5e1; }

	/* row highlights: loan received, payments */
	#ledgerTable tr.ln-row-disb td   { background: #eff6ff; }
	#ledgerTable tr.ln-row-pay td    { background: #f0fdf4; }
	#ledgerTable tr.ln-row-disb td:first-child   { box-shadow: inset 3px 0 0 #3b82f6; }
	#ledgerTable tr.ln-row-pay td:first-child    { box-shadow: inset 3px 0 0 #22c55e; }

	.ln-legend { display: flex; flex-wrap: wrap; gap: 4px 14px; margin-bottom: 8px; font-size: .72rem; color: #64748b; }
	.ln-legend .sw { display: inline-block; width: 11px; height: 11px; margin-right: 5px; vertical-align: -1px; border-radius: 3px; border: 1px solid #cbd5e1; }
	.ln-legend .sw-disb   { background: #eff6ff; border-color: #3b82f6; }
	.ln-legend .sw-pay    { background: #f0fdf4; border-color: #22c55e; }

	/* Print: the loan summary, the ledger and its totals row. Same pattern as the Supplier Ledger, using the
	   selectors this layout really has (.sidebar / .topbar / .main-content). */
	@media print {
		.sidebar, .topbar { display: none !important; }
		.main-content { margin-left: 0 !important; }
		.no-print, .ln-breadcrumb, .ln-legend { display: none !important; }
		.ln-scroll { max-height: none !important; overflow: visible !important; }
		.ln-sticky thead th { position: static; box-shadow: none; }
		#ledgerTable { min-width: 0; }
		#ledgerTable th, #ledgerTable td { font-size: .7rem; padding: 3px 6px; }
		#ledgerTable tr { break-inside: avoid; }
		#ledgerTable tfoot { display: table-row-group; }   /* totals once, after the last row */
		#ledgerTable tr.ln-row-disb td, #ledgerTable tr.ln-row-pay td,
		#ledgerTable tfoot td, .ln-info-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
		.card-custom, .kpi-card, .ln-info-card { box-shadow: none; break-inside: avoid; }
		.page-content .row { margin-left: 0; margin-right: 0; }
		#loanTerms > [class*="col-"] { flex: 0 0 auto; width: 20%; }
		#ledgerTotals > [class*="col-"] { flex: 0 0 auto; width: 25%; }
		.ln-info-card { padding: 6px 8px; }
		.ln-info-card .v { font-size: .8rem; }
	}
</style>

<nav aria-label="breadcrumb" class="ln-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Accounts</li>
        <li class="breadcrumb-item"><a href="<?= base_url('loans') ?>">Loan Management</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('loans/view/' . $loan['id']) ?>"><?= esc($loan['loan_no']) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page">Ledger</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span><i class="bi bi-journal-text me-2"></i>Loan Ledger — <?= esc($loan['loan_no']) ?> — <?= esc($loan['lender_name']) ?></span>
    <div class="d-flex flex-wrap gap-2 no-print">
        <a href="<?= base_url('loans/view/' . $loan['id']) ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back to Loan</a>
        <a href="<?= base_url('loans/payments/' . $loan['id']) ?>" class="btn-save" style="background:#6c757d;"><i class="bi bi-wallet2"></i> Payments</a>
        <a href="javascript:void(0)" class="btn-save" onclick="window.print()"><i class="bi bi-printer"></i> Print</a>
        <a href="<?= base_url('loans/export/pdf/' . $loan['id']) ?>" class="btn-cancel"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
        <a href="<?= base_url('loans/ledger/export/excel/' . $loan['id']) ?>" class="btn-cancel"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
    </div>
</div>
<div class="ln-print-only" style="font-size:.75rem;color:#64748b;margin-bottom:8px;">A&amp;A Inventory &middot; Printed <?= esc(date('d-m-Y H:i')) ?></div>

<!-- LOAN TERMS -->
<div class="row g-2 mb-2" id="loanTerms">
    <div class="col-6 col-md-4 col-xl">
        <div class="ln-info-card"><div class="l">Loan Type</div><div class="v"><?= esc($typeLabels[$loan['loan_type']] ?? $loan['loan_type']) ?></div></div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="ln-info-card"><div class="l">Interest Rate</div><div class="v"><?= $rate ?>% p.a.</div></div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="ln-info-card"><div class="l">Tenure</div><div class="v"><?= (int) $loan['tenure_months'] ?> months</div></div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="ln-info-card"><div class="l">Start Date</div><div class="v"><?= $fmtDate($loan['start_date']) ?></div></div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="ln-info-card"><div class="l">End Date</div><div class="v"><?= $fmtDate($loan['end_date']) ?></div></div>
    </div>
</div>

<!-- TOTALS -->
<div class="row g-2 mb-2" id="ledgerTotals">
    <div class="col-6 col-xl-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
            <div>
                <div class="kpi-value"><?= $money($loan['sanctioned_amount']) ?></div>
                <div class="kpi-label">Sanctioned Amount</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="kpi-value"><?= $money($loan['outstanding_principal']) ?></div>
                <div class="kpi-label">Outstanding Amount</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-check2-circle"></i></div>
            <div>
                <div class="kpi-value"><?= $money($total_credit) ?></div>
                <div class="kpi-label">Total Paid</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-clock-history"></i></div>
            <div>
                <div class="kpi-value"><?= max(0, count($rows) - 1) ?></div>
                <div class="kpi-label">Payments Recorded</div>
            </div>
        </div>
    </div>
</div>

<div class="card-custom">
    <div class="card-custom-header">
        Statement
        <span style="margin-left:8px;"><?= ln_loan_status_badge($loan['status']) ?></span>
    </div>
    <div class="card-custom-body">
        <?php if ($drift): ?>
        <div class="alert alert-warning">The running balance (<?= $money($running) ?>) does not match the loan's outstanding amount (<?= $money($loan['outstanding_principal']) ?>). Please review the loan's payments.</div>
        <?php endif; ?>
        <div class="ln-legend">
            <span><span class="sw sw-disb"></span>Loan Received</span>
            <span><span class="sw sw-pay"></span>Payment</span>
        </div>
        <div class="ln-table-wrap ln-scroll ln-sticky">
            <table id="ledgerTable" class="table-custom">
                <thead>
                    <tr>
                        <th class="sno-col">S.No.</th>
                        <th>Date</th>
                        <th>Transaction</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th class="num">Debit</th>
                        <th class="num">Credit</th>
                        <th class="num">Outstanding Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr class="<?= $rowClass[$r['kind']] ?? '' ?>">
                        <td class="sno-col sno-auto" data-label="S.No."></td>
                        <td><?= $fmtDate($r['date']) ?></td>
                        <td><?= esc($r['label']) ?></td>
                        <td><?= esc($r['method'] ?: '—') ?><?php if (! empty($r['account'])): ?><br><small class="text-muted"><?= esc($r['account']) ?></small><?php endif; ?></td>
                        <td><?= esc($r['reference'] ?: '—') ?></td>
                        <td class="num"><?= $r['debit'] > 0 ? $money($r['debit']) : '' ?></td>
                        <td class="num"><?= $r['credit'] > 0 ? $money($r['credit']) : '' ?></td>
                        <td class="num"><strong><?= $money($r['balance']) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5">Total</td>
                        <td class="num"><?= $money($loan['sanctioned_amount']) ?></td>
                        <td class="num"><?= $money($total_credit) ?></td>
                        <td class="num"><?= $money($running) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- LOAN RECEIPTS (Release 4.9.0AT — kept separate from the statement above: a receipt never affects
     outstanding_principal, so it is not mixed into that Debit/Credit/Outstanding Balance accounting) -->
<div class="card-custom mt-3">
    <div class="card-custom-header">Loan Receipts</div>
    <div class="card-custom-body">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-4">
                <div class="ln-info-card"><div class="l">Sanctioned Amount</div><div class="v"><?= $money($loan['sanctioned_amount']) ?></div></div>
            </div>
            <div class="col-6 col-md-4">
                <div class="ln-info-card"><div class="l">Total Received</div><div class="v"><?= $money($total_received) ?></div></div>
            </div>
            <div class="col-6 col-md-4">
                <div class="ln-info-card"><div class="l">Remaining to Receive</div><div class="v"><?= $money($remaining_to_receive) ?></div></div>
            </div>
        </div>
        <?php if (empty($receipt_rows)): ?>
        <div class="ln-empty" style="text-align:center; color:#94a3b8; padding:12px 10px;">No receipts recorded yet.</div>
        <?php else: ?>
        <div class="ln-table-wrap">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th class="sno-col">S.No.</th>
                        <th>Date</th>
                        <th>Receipt No</th>
                        <th class="num">Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receipt_rows as $rr): ?>
                    <tr>
                        <td class="sno-col sno-auto" data-label="S.No."></td>
                        <td><?= $fmtDate($rr['date']) ?></td>
                        <td><?= esc($rr['receipt_no']) ?></td>
                        <td class="num"><?= $money($rr['amount']) ?></td>
                        <td><?= esc($rr['method']) ?><?php if (! empty($rr['account'])): ?><br><small class="text-muted"><?= esc($rr['account']) ?></small><?php endif; ?></td>
                        <td><?= esc($rr['reference'] ?: '—') ?></td>
                        <td><?= esc($rr['remarks'] ?: '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
