<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
helper('loan_ui');
$typeLabels = \App\Models\LoanTypeModel::labels();
$today      = date('Y-m-d');
$rate       = rtrim(rtrim(number_format((float) $loan['interest_rate'], 3, '.', ''), '0'), '.');
$fmtDate    = static fn ($d) => $d ? date('d-m-Y', strtotime($d)) : '—';

// Release 4.9.0AY: every figure on this page comes from the loan's recorded payments.
$payCount   = count($payments ?? []);
// Release 4.9.0BA: Total Paid = SUM(loan_payments.total_paid); Outstanding = Sanctioned - Total Paid.
$totalPaid  = round((float) $loan['total_paid'], 2);
$percent    = (float) $loan['sanctioned_amount'] > 0 ? (int) round(min(100, $totalPaid / (float) $loan['sanctioned_amount'] * 100)) : 0;
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
	#payTable { min-width: 720px; }
	#payTable th, #payTable td { padding: 6px 10px; font-size: .78rem; white-space: nowrap; }
	.ln-schedule-wrap .num { text-align: right; }

	@media print {
		.sidebar, .topbar { display: none !important; }
		.main-content { margin-left: 0 !important; }
		.no-print, .ln-breadcrumb, #loanFlash { display: none !important; }
		.ln-schedule-wrap { overflow: visible; }
		#payTable { min-width: 0; }
		#payTable th, #payTable td { font-size: .7rem; padding: 3px 6px; }
		.card-custom { box-shadow: none; break-inside: avoid; }
		#payTable tr { break-inside: avoid; }
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

<?php
// Receive Loan is offered only while something remains to be received (remaining_to_receive comes from the controller).
$canReceive = $receipt_available && $remaining_to_receive > 0;
?>
<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span><i class="bi bi-cash-coin me-2"></i>Loan <?= esc($loan['loan_no']) ?> — <?= esc($loan['lender_name']) ?></span>
    <div class="d-flex flex-wrap gap-2 no-print">
        <a href="<?= base_url('loans') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
        <a href="<?= base_url('loans/edit/' . $loan['id']) ?>" class="btn-save" style="background:#6c757d;"><i class="bi bi-pencil"></i> Edit</a>
        <?php if ($canReceive): ?>
        <a href="<?= base_url('loans/receive/' . $loan['id']) ?>" class="btn-save" style="background:#0d9488;"><i class="bi bi-piggy-bank"></i> Receive Loan</a>
        <?php endif; ?>
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
                    <tr><td><strong>Account Number</strong></td><td><?= esc($loan['account_number'] ?: '—') ?></td></tr>
                    <tr><td><strong>Loan Date / Start Date</strong></td><td><?= $fmtDate($loan['start_date']) ?></td></tr>
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
                    <tr><td>Total Paid</td><td style="text-align:right">₹<?= number_format($totalPaid, 2) ?></td></tr>
                    <tr><td><strong>Outstanding Amount</strong></td><td style="text-align:right"><strong>₹<?= number_format((float) $loan['outstanding_principal'], 2) ?></strong></td></tr>
                    <tr><td>Payments Recorded</td><td style="text-align:right"><?= (int) $payCount ?></td></tr>
                    <tr><td>Interest Rate</td><td style="text-align:right"><?= (float) $loan['interest_rate'] > 0 ? $rate . '% per year' : '—' ?></td></tr>
                    <tr><td>Tenure</td><td style="text-align:right"><?= (int) $loan['tenure_months'] > 0 ? (int) $loan['tenure_months'] . ' months' : '—' ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- LOAN RECEIPTS -->
<div class="card-custom mb-3">
    <div class="card-custom-header d-flex align-items-center justify-content-between flex-wrap">
        <span>Loan Receipts</span>
    </div>
    <div class="card-custom-body">
        <div class="row g-2 mb-3">
            <div class="col-6 col-md-3"><div class="ln-stat"><div class="v">₹<?= number_format((float) $loan['sanctioned_amount'], 2) ?></div><div class="l">Sanctioned Amount</div></div></div>
            <div class="col-6 col-md-3"><div class="ln-stat"><div class="v">₹<?= number_format($total_received, 2) ?></div><div class="l">Total Received</div></div></div>
            <div class="col-6 col-md-3"><div class="ln-stat"><div class="v">₹<?= number_format($remaining_to_receive, 2) ?></div><div class="l">Remaining to Receive</div></div></div>
        </div>
        <?php if (empty($receipts)): ?>
        <div class="ln-empty" style="text-align:center; color:#94a3b8; padding:18px 10px;">
            <i class="bi bi-inbox"></i> No receipts recorded yet.<?= $canReceive ? ' Use "Receive Loan" once money actually arrives.' : '' ?>
        </div>
        <?php else: ?>
        <div class="ln-schedule-wrap">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th class="sno-col">S.No.</th>
                        <th>Receipt No</th>
                        <th>Date</th>
                        <th class="num">Amount</th>
                        <th>Payment Mode</th>
                        <th>Bank Account</th>
                        <th>Reference No</th>
                        <th>Remarks</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receipts as $r): ?>
                    <tr>
                        <td class="sno-col sno-auto" data-label="S.No."></td>
                        <td><?= esc($r['receipt_no']) ?></td>
                        <td><?= $fmtDate($r['receipt_date']) ?></td>
                        <td class="num"><?= number_format((float) $r['amount'], 2) ?></td>
                        <td><?= ln_method_badge($r['payment_method']) ?></td>
                        <td><?= $r['bank_name'] ? esc($r['bank_name'] . ' - ' . $r['account_name']) : '—' ?></td>
                        <td><?= esc($r['reference_no'] ?: '—') ?></td>
                        <td><?= esc($r['remarks'] ?: '—') ?></td>
                        <td class="no-print">
                            <div class="d-flex flex-wrap gap-2">
                                <a href="<?= base_url('loans/receipts/edit/' . $r['id']) ?>" class="btn-edit"><i class="bi bi-pencil"></i> Edit</a>
                                <button type="button" class="btn-delete js-del-receipt" data-id="<?= (int) $r['id'] ?>"><i class="bi bi-trash"></i> Delete</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ACTUAL PAYMENTS (from recorded loan payments) -->
<div class="card-custom mb-3">
    <div class="card-custom-header d-flex align-items-center justify-content-between flex-wrap">
        <span>Actual Payments</span>
        <a href="<?= base_url('loans/payments/' . $loan['id']) ?>" class="btn-view no-print"><i class="bi bi-wallet2"></i> Payment History</a>
    </div>
    <div class="card-custom-body">
        <div class="d-flex justify-content-between mb-1" style="font-size:.8rem;">
            <span>Paid (₹<?= number_format($totalPaid, 2) ?> of ₹<?= number_format((float) $loan['sanctioned_amount'], 2) ?>)</span>
            <span><?= $percent ?>%</span>
        </div>
        <div class="ln-progress mb-3" role="progressbar" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="ln-progress-bar" style="width:<?= $percent ?>%"></div>
        </div>
        <div class="row g-2">
            <div class="col-6 col-md-3"><div class="ln-stat"><div class="v"><?= $payCount ?></div><div class="l">Payments Recorded</div></div></div>
            <div class="col-6 col-md-3"><div class="ln-stat"><div class="v">₹<?= number_format($totalPaid, 2) ?></div><div class="l">Total Paid</div></div></div>
            <div class="col-6 col-md-3"><div class="ln-stat"><div class="v">₹<?= number_format((float) $loan['outstanding_principal'], 2) ?></div><div class="l">Outstanding Amount</div></div></div>
        </div>
    </div>
</div>

<!-- PAYMENT HISTORY (loan_payments) -->
<div class="card-custom mb-3">
    <div class="card-custom-header d-flex align-items-center justify-content-between flex-wrap">
        <span>Payment History</span>
        <?php if ($loan['status'] === 'ACTIVE'): ?>
        <a href="<?= base_url('loans/payments/' . $loan['id']) ?>" class="btn-view no-print"><i class="bi bi-plus-circle"></i> Record Payment</a>
        <?php endif; ?>
    </div>
    <div class="card-custom-body">
        <div class="ln-schedule-wrap">
            <table id="payTable" class="table-custom">
                <thead>
                    <tr>
                        <th class="sno-col">S.No.</th>
                        <th>Payment Date</th>
                        <th class="num">Payment Amount</th>
                        <th>Payment Method</th>
                        <th>Bank Account</th>
                        <th>Reference</th>
                        <th>Remarks</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                    <tr><td colspan="8" style="text-align:center; color:#94a3b8;">No payments recorded yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td class="sno-col sno-auto" data-label="S.No."></td>
                        <td><?= $fmtDate($p['payment_date']) ?><?php if (! empty($p['loan_emi_id'])): ?> <small class="text-muted">(old EMI ref. #<?= (int) $p['emi_no'] ?>)</small><?php endif; ?></td>
                        <td class="num"><strong><?= number_format((float) $p['total_paid'], 2) ?></strong></td>
                        <td><?= ln_method_badge($p['payment_method']) ?></td>
                        <td><?= $p['bank_name'] ? esc($p['bank_name'] . ' - ' . $p['account_name']) : '—' ?></td>
                        <td><?= esc($p['reference_no'] ?: '—') ?></td>
                        <td><?= esc($p['remarks'] ?: '—') ?></td>
                        <td class="no-print">
                            <div class="d-flex flex-wrap gap-2">
                                <a href="<?= base_url('loans/payments/' . $loan['id'] . '?edit=' . (int) $p['id']) ?>" class="btn-edit"><i class="bi bi-pencil"></i> Edit</a>
                                <button type="button" class="btn-delete js-del-pay" data-id="<?= (int) $p['id'] ?>"><i class="bi bi-trash"></i> Delete</button>
                            </div>
                        </td>
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
	// One-time "saved" message left by the create / edit / receive page.
	try {
		var msg = sessionStorage.getItem('loanFlash');
		if (msg) {
			sessionStorage.removeItem('loanFlash');
			$('#loanFlashText').text(msg);
			$('#loanFlash').show();
		}
	} catch (x) {}

	$(document).on('click', '.js-del-pay', function () {
		var id = $(this).data('id');
		if (!confirm('Delete this payment? The total paid and outstanding amount will be restored and any bank withdrawal reversed.')) return;
		$.ajax({ url: "<?= base_url('loans/payments/delete/') ?>" + id, method: 'POST', dataType: 'json' })
			.done(function (r) {
				try { sessionStorage.setItem('loanFlash', r.message || 'Payment deleted.'); } catch (x) {}
				location.reload();
			})
			.fail(function (xhr) {
				var r = xhr.responseJSON;
				alert((r && r.errors && r.errors.join(' ')) || 'The payment could not be deleted.');
			});
	});

	$(document).on('click', '.js-del-receipt', function () {
		var id = $(this).data('id');
		if (!confirm('Delete this receipt? Any bank deposit it posted will be reversed.')) return;
		$.ajax({ url: "<?= base_url('loans/receipts/delete/') ?>" + id, method: 'POST', dataType: 'json' })
			.done(function (r) {
				try { sessionStorage.setItem('loanFlash', r.message || 'Receipt deleted.'); } catch (x) {}
				location.reload();
			})
			.fail(function (xhr) {
				var r = xhr.responseJSON;
				alert((r && r.errors && r.errors.join(' ')) || 'The receipt could not be deleted.');
			});
	});
</script>
<?= $this->endSection() ?>
