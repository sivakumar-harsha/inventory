<?php
/**
 * Release 4.8.5D: Loan KPIs + latest-10 widgets. Included from
 * dashboard/index.php, so it reuses that page's kpi-card / card-custom /
 * dash-table-dense styles. Renders nothing until the loan tables exist.
 *
 * Release 4.8.5E: a subtitle under each KPI, a payment-method badge on the
 * latest payments (row opens the Loan Ledger) and lender / EMI number / a
 * days-left-or-overdue badge on the upcoming EMIs (row opens the Loan Payments
 * page). Presentation only: the four KPI figures and the two lists' rows and
 * ordering are exactly as before.
 */
if (empty($loan_available)) {
    return;
}

helper('loan_ui');

$plural = static fn (int $n, string $one, string $many) => number_format($n) . ' ' . ($n === 1 ? $one : $many);
?>
<?= $this->include('loans/partials/ui_styles') ?>

<style>
	/* loan number with the lender under it, so five columns fit a half-width card */
	.ln-dash-scroll .ln-sub-line { max-width: 190px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #64748b; }
</style>

<!-- LOANS (Release 4.8.5D) -->
<div class="row g-3 mb-3">
    <div class="col-lg-3 col-6">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($loan_outstanding, 2) ?></div>
                <div class="kpi-label">Outstanding Loans</div>
                <div class="ln-kpi-sub"><?= $plural((int) $loan_active_count, 'active loan', 'active loans') ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-calendar-event"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($loan_emi_due_month, 2) ?></div>
                <div class="kpi-label">EMI Due This Month</div>
                <div class="ln-kpi-sub"><?= $plural((int) $loan_emi_due_count, 'due EMI', 'due EMIs') ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-percent"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($loan_interest_paid, 2) ?></div>
                <div class="kpi-label">Total Interest Paid</div>
                <div class="ln-kpi-sub">Interest collected by lenders</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-check2-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($loan_closed_count) ?></div>
                <div class="kpi-label">Closed Loans</div>
                <div class="ln-kpi-sub"><?= number_format((int) $loan_closed_count) ?> of <?= $plural((int) $loan_total_count, 'loan', 'loans') ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-custom-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-wallet2 me-2"></i>Latest Loan Payments</span>
                <a href="<?= base_url('loan-reports/payments') ?>" class="btn-save" style="padding:3px 10px;font-size:12px;">View All Payments</a>
            </div>
            <div class="ln-dash-scroll">
                <table class="table-custom dash-table-dense" id="lnRecentPayments">
                    <thead style="font-size:12px; color:#888;">
                        <tr>
                            <th>Loan / Lender</th>
                            <th>Payment Date</th>
                            <th style="text-align:right">Amount</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($loan_recent_payments)): ?>
                        <tr><td colspan="4" class="text-center text-muted">No loan payments yet</td></tr>
                        <?php endif; ?>
                        <?php foreach ($loan_recent_payments as $p): ?>
                        <?php $ledgerUrl = base_url('loans/ledger/' . $p['loan_id']); ?>
                        <tr class="ln-row-link" data-href="<?= esc($ledgerUrl, 'attr') ?>" title="Open loan ledger">
                            <td>
                                <a href="<?= esc($ledgerUrl, 'attr') ?>" style="text-decoration:none;"><?= esc($p['loan_no']) ?></a>
                                <span class="ln-sub-line" title="<?= esc($p['lender_name'], 'attr') ?>"><?= esc($p['lender_name']) ?></span>
                            </td>
                            <td><?= esc(date('d-m-Y', strtotime($p['payment_date']))) ?></td>
                            <td style="text-align:right"><?= number_format((float) $p['total_paid'], 2) ?></td>
                            <td><?= ln_method_badge((string) $p['payment_method']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-custom-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar-check me-2"></i>Upcoming EMIs</span>
                <a href="<?= base_url('loan-reports/emi-due') ?>" class="btn-save" style="padding:3px 10px;font-size:12px;">View EMI Due Report</a>
            </div>
            <div class="ln-dash-scroll">
                <table class="table-custom dash-table-dense" id="lnUpcomingEmis">
                    <thead style="font-size:12px; color:#888;">
                        <tr>
                            <th>Loan / Lender</th>
                            <th>EMI No.</th>
                            <th>Due Date</th>
                            <th style="text-align:right">Amount</th>
                            <th>Days</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($loan_upcoming_emis)): ?>
                        <tr><td colspan="5" class="text-center text-muted">No EMIs pending</td></tr>
                        <?php endif; ?>
                        <?php foreach ($loan_upcoming_emis as $e): ?>
                        <?php $paymentsUrl = base_url('loans/payments/' . $e['loan_id']); ?>
                        <tr class="ln-row-link" data-href="<?= esc($paymentsUrl, 'attr') ?>" title="Open loan payments">
                            <td>
                                <a href="<?= esc($paymentsUrl, 'attr') ?>" style="text-decoration:none;"><?= esc($e['loan_no']) ?></a>
                                <span class="ln-sub-line" title="<?= esc($e['lender_name'], 'attr') ?>"><?= esc($e['lender_name']) ?></span>
                            </td>
                            <td>#<?= (int) $e['emi_no'] ?></td>
                            <td><?= esc(date('d-m-Y', strtotime($e['due_date']))) ?></td>
                            <td style="text-align:right">
                                <?= number_format((float) $e['balance_amount'], 2) ?>
                                <?php if ((float) $e['balance_amount'] < (float) $e['emi_amount'] - 0.004): ?>
                                <span class="ln-sub-line">of <?= number_format((float) $e['emi_amount'], 2) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= ln_days_badge($e['due_date']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
	// A whole widget row opens its page; the loan number is a real link too (keyboard, middle-click).
	document.addEventListener('click', function (e) {
		var row = e.target.closest ? e.target.closest('tr.ln-row-link') : null;
		if (row && !e.target.closest('a, button')) window.location.href = row.getAttribute('data-href');
	});
</script>
