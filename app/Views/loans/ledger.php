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
$typeLabels = ['BANK' => 'Bank Loan', 'PERSONAL' => 'Personal', 'VEHICLE' => 'Vehicle', 'OD' => 'Overdraft', 'OTHER' => 'Other'];
$rate       = rtrim(rtrim(number_format((float) $loan['interest_rate'], 3, '.', ''), '0'), '.');
$rowClass   = ['DISBURSEMENT' => 'ln-row-disb', 'EMI' => 'ln-row-pay', 'PREPAYMENT' => 'ln-row-prepay'];
?>
<?= $this->include('loans/partials/ui_styles') ?>

<style>
	.ln-breadcrumb { margin-bottom: 8px; }
	.ln-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.ln-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.ln-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.ln-breadcrumb .breadcrumb-item.active { color: #64748b; }

	/* loan terms cards */
	.ln-info-card { height: 100%; padding: 9px 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; }
	.ln-info-card .l { font-size: .68rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; color: #64748b; }
	.ln-info-card .v { font-size: .95rem; font-weight: 700; color: #1e293b; }

	/* the statement: header stays put while the rows scroll (.ln-scroll / .ln-sticky come from ui_styles) */
	#ledgerTable { min-width: 900px; }
	#ledgerTable th, #ledgerTable td { padding: 6px 10px; font-size: .78rem; white-space: nowrap; }
	#ledgerTable .num { text-align: right; }
	#ledgerTable tfoot td { font-weight: 700; background: #f8fafc; border-top: 1px solid #cbd5e1; }

	/* row highlights: disbursement, EMI payments, prepayments */
	#ledgerTable tr.ln-row-disb td   { background: #eff6ff; }
	#ledgerTable tr.ln-row-pay td    { background: #f0fdf4; }
	#ledgerTable tr.ln-row-prepay td { background: #fffbeb; }
	#ledgerTable tr.ln-row-disb td:first-child   { box-shadow: inset 3px 0 0 #3b82f6; }
	#ledgerTable tr.ln-row-pay td:first-child    { box-shadow: inset 3px 0 0 #22c55e; }
	#ledgerTable tr.ln-row-prepay td:first-child { box-shadow: inset 3px 0 0 #f59e0b; }

	.ln-legend { display: flex; flex-wrap: wrap; gap: 4px 14px; margin-bottom: 8px; font-size: .72rem; color: #64748b; }
	.ln-legend .sw { display: inline-block; width: 11px; height: 11px; margin-right: 5px; vertical-align: -1px; border-radius: 3px; border: 1px solid #cbd5e1; }
	.ln-legend .sw-disb   { background: #eff6ff; border-color: #3b82f6; }
	.ln-legend .sw-pay    { background: #f0fdf4; border-color: #22c55e; }
	.ln-legend .sw-prepay { background: #fffbeb; border-color: #f59e0b; }

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
		#ledgerTable tr.ln-row-disb td, #ledgerTable tr.ln-row-pay td, #ledgerTable tr.ln-row-prepay td,
		#ledgerTable tfoot td, .ln-info-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
		.card-custom, .kpi-card, .ln-info-card { box-shadow: none; break-inside: avoid; }
		.page-content .row { margin-left: 0; margin-right: 0; }
		#loanTerms > [class*="col-"] { flex: 0 0 auto; width: 20%; }
		#ledgerTotals > [class*="col-"] { flex: 0 0 auto; width: 33.3333%; }
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
<div class="row g-3 mb-3" id="loanTerms">
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
<div class="row g-3 mb-3" id="ledgerTotals">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
            <div>
                <div class="kpi-value"><?= $money($loan['sanctioned_amount']) ?></div>
                <div class="kpi-label">Sanctioned Amount</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-arrow-down-circle"></i></div>
            <div>
                <div class="kpi-value"><?= $money($loan['total_principal_paid']) ?></div>
                <div class="kpi-label">Principal Paid</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-percent"></i></div>
            <div>
                <div class="kpi-value"><?= $money($loan['total_interest_paid']) ?></div>
                <div class="kpi-label">Interest Paid</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="kpi-value"><?= $money($loan['outstanding_principal']) ?></div>
                <div class="kpi-label">Outstanding Principal</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-check2-circle"></i></div>
            <div>
                <div class="kpi-value"><?= (int) $emis_paid ?> <span style="font-size:.75rem;font-weight:400;">of <?= (int) $emis_total ?></span></div>
                <div class="kpi-label">EMIs Paid</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-clock-history"></i></div>
            <div>
                <div class="kpi-value"><?= (int) $emis_pending ?></div>
                <div class="kpi-label">EMIs Pending</div>
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
        <div class="alert alert-warning">The running balance (<?= $money($running) ?>) does not match the loan's outstanding principal (<?= $money($loan['outstanding_principal']) ?>). Please review the loan's payments.</div>
        <?php endif; ?>
        <div class="ln-legend">
            <span><span class="sw sw-disb"></span>Disbursement</span>
            <span><span class="sw sw-pay"></span>EMI payment</span>
            <span><span class="sw sw-prepay"></span>Prepayment</span>
        </div>
        <div class="ln-table-wrap ln-scroll ln-sticky">
            <table id="ledgerTable" class="table-custom">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Transaction</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th class="num">Debit</th>
                        <th class="num">Credit</th>
                        <th class="num">Principal</th>
                        <th class="num">Interest</th>
                        <th class="num">Outstanding Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr class="<?= $rowClass[$r['kind']] ?? '' ?>">
                        <td><?= $fmtDate($r['date']) ?></td>
                        <td><?= esc($r['label']) ?></td>
                        <td><?= esc($r['method'] ?: '—') ?></td>
                        <td><?= esc($r['reference'] ?: '—') ?></td>
                        <td class="num"><?= $r['debit'] > 0 ? $money($r['debit']) : '' ?></td>
                        <td class="num"><?= $r['credit'] > 0 ? $money($r['credit']) : '' ?></td>
                        <td class="num"><?= $r['kind'] === 'DISBURSEMENT' ? '' : $money($r['principal']) ?></td>
                        <td class="num"><?= $r['kind'] === 'DISBURSEMENT' ? '' : $money($r['interest']) ?></td>
                        <td class="num"><strong><?= $money($r['balance']) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">Total</td>
                        <td class="num"><?= $money($loan['sanctioned_amount']) ?></td>
                        <td class="num"><?= $money($total_credit) ?></td>
                        <td class="num"><?= $money($total_principal) ?></td>
                        <td class="num"><?= $money($total_interest) ?></td>
                        <td class="num"><?= $money($running) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
