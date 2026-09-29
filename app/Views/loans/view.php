<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
helper('loan_ui');
$typeLabels = ['BANK' => 'Bank Loan', 'PERSONAL' => 'Personal', 'VEHICLE' => 'Vehicle', 'OD' => 'Overdraft', 'OTHER' => 'Other'];
$today      = date('Y-m-d');
$rate       = rtrim(rtrim(number_format((float) $loan['interest_rate'], 3, '.', ''), '0'), '.');
$fmtDate    = static fn ($d) => $d ? date('d-m-Y', strtotime($d)) : '—';

$totalEmis = count($emis);
$paidEmis  = count(array_filter($emis, static fn ($e) => $e['payment_status'] === 'PAID'));
$partEmis  = count(array_filter($emis, static fn ($e) => $e['payment_status'] === 'PARTIAL'));
$overdue   = count(array_filter($emis, static fn ($e) => $e['payment_status'] !== 'PAID' && $e['due_date'] < $today));
$pendEmis  = $totalEmis - $paidEmis;
$percent   = $totalEmis > 0 ? (int) round($paidEmis / $totalEmis * 100) : 0;
$next      = $outstanding['next_emi'] ?? null;
?>
<?= $this->include('loans/partials/ui_styles') ?>

<style>
	.ln-breadcrumb { margin-bottom: 8px; }
	.ln-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.ln-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.ln-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.ln-breadcrumb .breadcrumb-item.active { color: #64748b; }

	.ln-progress { height: 14px; background: #e2e8f0; border-radius: 8px; overflow: hidden; }
	.ln-progress-bar { height: 100%; background: #16a34a; transition: width .3s; }
	.ln-stat { text-align: center; padding: 6px 4px; }
	.ln-stat .v { font-size: 1.15rem; font-weight: 700; color: #1e293b; }
	.ln-stat .l { font-size: .72rem; color: #64748b; text-transform: uppercase; letter-spacing: .03em; }

	.ln-schedule-wrap { overflow-x: auto; }
	#schedTable { min-width: 860px; }
	#schedTable th, #schedTable td { padding: 6px 10px; font-size: .78rem; white-space: nowrap; }
	#schedTable .num { text-align: right; }
	#schedTable tr.next-due td { background: #fffbeb; }
	#schedTable .ln-badge + .ln-badge { margin-left: 4px; }

	@media print {
		.sidebar, .topbar { display: none !important; }
		.main-content { margin-left: 0 !important; }
		.no-print, .ln-breadcrumb, #loanFlash { display: none !important; }
		.ln-schedule-wrap { overflow: visible; }
		#schedTable { min-width: 0; }
		#schedTable th, #schedTable td { font-size: .7rem; padding: 3px 6px; }
		.card-custom { box-shadow: none; break-inside: avoid; }
		#schedCard { break-inside: auto; }
		#schedTable tr { break-inside: avoid; }
	}
</style>

<nav aria-label="breadcrumb" class="ln-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Accounts</li>
        <li class="breadcrumb-item"><a href="<?= base_url('loans') ?>">Loan Management</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= esc($loan['loan_no']) ?></li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span><i class="bi bi-cash-coin me-2"></i>Loan <?= esc($loan['loan_no']) ?> — <?= esc($loan['lender_name']) ?></span>
    <div class="d-flex flex-wrap gap-2 no-print">
        <a href="<?= base_url('loans') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
        <a href="<?= base_url('loans/edit/' . $loan['id']) ?>" class="btn-save" style="background:#6c757d;"><i class="bi bi-pencil"></i> Edit</a>
        <a href="<?= base_url('loans/payments/' . $loan['id']) ?>" class="btn-pay"><i class="bi bi-wallet2"></i> Payments</a>
        <a href="<?= base_url('loans/ledger/' . $loan['id']) ?>" class="btn-save"><i class="bi bi-journal-text"></i> Ledger</a>
        <a href="javascript:void(0)" class="btn-save" onclick="window.print()"><i class="bi bi-printer"></i> Print</a>
        <a href="<?= base_url('loans/export/pdf/' . $loan['id']) ?>" class="btn-cancel"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
        <a href="<?= base_url('loans/ledger/export/excel/' . $loan['id']) ?>" class="btn-cancel"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
    </div>
</div>

<div id="loanFlash" class="alert alert-success alert-dismissible fade show" role="alert" style="display:none;">
    <i class="bi bi-check-circle-fill me-2"></i><span id="loanFlashText"></span>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<div class="row g-3 mb-3">
    <!-- LOAN SUMMARY -->
    <div class="col-md-6">
        <div class="card-custom h-100">
            <div class="card-custom-header">Loan Summary</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td><strong>Loan Number</strong></td><td><?= esc($loan['loan_no']) ?></td></tr>
                    <tr><td><strong>Lender</strong></td><td><?= esc($loan['lender_name']) ?></td></tr>
                    <tr><td><strong>Type</strong></td><td><?= esc($typeLabels[$loan['loan_type']] ?? $loan['loan_type']) ?></td></tr>
                    <tr><td><strong>Bank</strong></td><td><?= $bank_account ? esc($bank_account['bank_name'] . ' - ' . $bank_account['account_name']) : '—' ?></td></tr>
                    <tr><td><strong>Account Number</strong></td><td><?= esc($loan['account_number'] ?: '—') ?></td></tr>
                    <tr><td><strong>Start</strong></td><td><?= $fmtDate($loan['start_date']) ?></td></tr>
                    <tr><td><strong>End</strong></td><td><?= $fmtDate($loan['end_date']) ?></td></tr>
                    <tr><td><strong>Status</strong></td><td><?= ln_loan_status_badge($loan['status']) ?></td></tr>
                    <?php if (! empty($loan['remarks'])): ?>
                    <tr><td><strong>Remarks</strong></td><td><?= esc($loan['remarks']) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- FINANCIAL SUMMARY -->
    <div class="col-md-6">
        <div class="card-custom h-100">
            <div class="card-custom-header">Financial Summary</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td>Sanctioned Amount</td><td style="text-align:right">₹<?= number_format((float) $loan['sanctioned_amount'], 2) ?></td></tr>
                    <tr><td><strong>Outstanding Principal</strong></td><td style="text-align:right"><strong>₹<?= number_format((float) $loan['outstanding_principal'], 2) ?></strong></td></tr>
                    <tr><td>Principal Paid</td><td style="text-align:right">₹<?= number_format((float) ($outstanding['principal_paid'] ?? $loan['total_principal_paid']), 2) ?></td></tr>
                    <tr><td>Interest Paid</td><td style="text-align:right">₹<?= number_format((float) ($outstanding['interest_paid'] ?? $loan['total_interest_paid']), 2) ?></td></tr>
                    <tr><td>EMI Amount</td><td style="text-align:right">₹<?= number_format((float) $loan['emi_amount'], 2) ?></td></tr>
                    <tr><td>Interest Rate</td><td style="text-align:right"><?= $rate ?>% per year</td></tr>
                    <tr><td>Tenure</td><td style="text-align:right"><?= (int) $loan['tenure_months'] ?> months</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- EMI PROGRESS -->
<div class="card-custom mb-3">
    <div class="card-custom-header">EMI Progress</div>
    <div class="card-custom-body">
        <div class="d-flex justify-content-between mb-1" style="font-size:.8rem;">
            <span><?= $paidEmis ?> of <?= $totalEmis ?> EMIs paid</span>
            <span><?= $percent ?>%</span>
        </div>
        <div class="ln-progress mb-3" role="progressbar" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="ln-progress-bar" style="width:<?= $percent ?>%"></div>
        </div>
        <div class="row g-2">
            <div class="col-6 col-md-3"><div class="ln-stat"><div class="v"><?= $paidEmis ?></div><div class="l">Paid EMIs</div></div></div>
            <div class="col-6 col-md-3">
                <div class="ln-stat"><div class="v"><?= $pendEmis ?></div><div class="l">Pending EMIs<?= $partEmis > 0 ? " ({$partEmis} partly paid)" : '' ?><?= $overdue > 0 ? " — {$overdue} overdue" : '' ?></div></div>
            </div>
            <div class="col-6 col-md-3"><div class="ln-stat"><div class="v"><?= $next ? $fmtDate($next['due_date']) : '—' ?></div><div class="l">Next EMI Due Date</div></div></div>
            <div class="col-6 col-md-3"><div class="ln-stat"><div class="v"><?= $next ? '₹' . number_format((float) $next['balance_amount'], 2) : '—' ?></div><div class="l">Next EMI Amount</div></div></div>
        </div>
    </div>
</div>

<!-- EMI SCHEDULE -->
<div class="card-custom" id="schedCard">
    <div class="card-custom-header">EMI Schedule</div>
    <div class="card-custom-body">
        <div class="ln-schedule-wrap">
            <table id="schedTable" class="table-custom">
                <thead>
                    <tr>
                        <th>EMI</th>
                        <th>Due Date</th>
                        <th class="num">Principal</th>
                        <th class="num">Interest</th>
                        <th class="num">EMI Amount</th>
                        <th class="num">Paid</th>
                        <th class="num">Balance Due</th>
                        <th class="num">Closing Balance</th>
                        <th>Status</th>
                        <th>Paid Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($emis)): ?>
                    <tr><td colspan="10" style="text-align:center; color:#94a3b8;">No EMI schedule found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($emis as $e): ?>
                    <?php $isOverdue = $e['payment_status'] !== 'PAID' && $e['due_date'] < $today; ?>
                    <tr class="<?= ($next && (int) $next['emi_id'] === (int) $e['id']) ? 'next-due' : '' ?>">
                        <td><?= (int) $e['emi_no'] ?></td>
                        <td><?= $fmtDate($e['due_date']) ?></td>
                        <td class="num"><?= number_format((float) $e['principal_amount'], 2) ?></td>
                        <td class="num"><?= number_format((float) $e['interest_amount'], 2) ?></td>
                        <td class="num"><?= number_format((float) $e['emi_amount'], 2) ?></td>
                        <td class="num"><?= number_format((float) $e['paid_amount'], 2) ?></td>
                        <td class="num"><?= number_format((float) $e['balance_amount'], 2) ?></td>
                        <td class="num"><?= number_format((float) $e['closing_balance'], 2) ?></td>
                        <td>
                            <?= ln_emi_status_badge($e['payment_status']) ?>
                            <?php if ($isOverdue): ?><?= ln_badge('Overdue', 'red') ?><?php endif; ?>
                        </td>
                        <td><?= $fmtDate($e['paid_date']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	// One-time "saved" message left by the create / edit page.
	try {
		var msg = sessionStorage.getItem('loanFlash');
		if (msg) {
			sessionStorage.removeItem('loanFlash');
			$('#loanFlashText').text(msg);
			$('#loanFlash').show();
		}
	} catch (x) {}
</script>
<?= $this->endSection() ?>
