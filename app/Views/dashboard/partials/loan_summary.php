<?php
/**
 * Dashboard: Loans (Outstanding Loans, Scheduled EMI This Month, next 5 upcoming / overdue
 * EMIs as a timeline). Included from dashboard/index.php; data from Dashboard::_loanSummary().
 * Renders nothing until the loan tables exist. Total Paid, Closed Loans and the payment
 * history live in Loan Reports.
 */
if (empty($loan_available)) {
    return;
}

helper('loan_ui');

$plural = static fn (int $n, string $one, string $many) => number_format($n) . ' ' . ($n === 1 ? $one : $many);
?>
<section class="dbx-sheet full" aria-label="Loans">
    <div class="dbx-sech"><h2>Loans</h2><a href="<?= base_url('loan-reports') ?>">Loan reports →</a></div>
    <div class="dbx-pair">
        <div class="dbx-fig"><div class="l">Outstanding Loans</div><div class="v dbx-num">₹ <?= number_format($loan_outstanding, 2) ?></div><div class="s"><?= $plural((int) $loan_active_count, 'active loan', 'active loans') ?></div></div>
        <div class="dbx-fig"><div class="l">Scheduled EMI this month</div><div class="v dbx-num">₹ <?= number_format($loan_emi_due_month, 2) ?></div><div class="s"><?= $plural((int) $loan_emi_due_count, 'scheduled EMI', 'scheduled EMIs') ?></div></div>
    </div>
    <hr class="dbx-hr">
    <div class="dbx-subh">Upcoming / overdue EMIs</div>
    <?php if (empty($loan_upcoming_emis)): ?>
    <div class="dbx-empty">No EMIs pending</div>
    <?php else: ?>
    <ul class="dbx-tl">
        <?php foreach ($loan_upcoming_emis as $e): ?>
        <?php
            // ln_days_diff(): positive = days overdue, 0 = due today, negative = days left.
            $days = ln_days_diff($e['due_date']);
            if ($days > 0) {
                $cls  = 'od';
                $when = $days . ($days === 1 ? ' day overdue' : ' days overdue');
            } elseif ($days === 0) {
                $cls  = 'up';
                $when = 'Due today';
            } else {
                $left = abs($days);
                $cls  = $left <= 14 ? 'up' : '';
                $when = 'in ' . $left . ($left === 1 ? ' day' : ' days');
            }
        ?>
        <li class="<?= $cls ?>">
            <span class="nd"></span>
            <div>
                <div class="t1"><a href="<?= esc(base_url('loans/payments/' . (int) $e['loan_id']), 'attr') ?>" style="color:inherit"><?= esc($e['loan_no']) ?></a> · EMI #<?= (int) $e['emi_no'] ?> · ₹ <?= number_format((float) $e['balance_amount'], 2) ?></div>
                <div class="t2"><?= esc($e['lender_name']) ?> · due <?= esc(date('d-m-Y', strtotime($e['due_date']))) ?><?php if ((float) $e['balance_amount'] < (float) $e['emi_amount'] - 0.004): ?> · of ₹ <?= number_format((float) $e['emi_amount'], 2) ?><?php endif; ?></div>
            </div>
            <span class="when"><?= esc($when) ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
