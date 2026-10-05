<?php
/**
 * Dashboard: Expenses today / this month (PAID only, as the Expense Register and the P&L).
 * Included from dashboard/index.php; data from Dashboard::_expenseSummary(). The all-time total
 * is the headline Total Expenses figure, so it is not repeated here.
 */
if (empty($expense_available)) {
    return;
}
?>
<section class="dbx-sheet" aria-label="Expenses">
    <div class="dbx-sech"><h2>Expenses</h2><a href="<?= base_url('expense-reports') ?>">Reports →</a></div>
    <div class="dbx-pair">
        <div class="dbx-fig"><div class="l">Today</div><div class="v dbx-num">₹ <?= number_format($expense_today, 2) ?></div><div class="s"><?= esc(date('d M Y')) ?></div></div>
        <div class="dbx-fig"><div class="l">This month</div><div class="v dbx-num">₹ <?= number_format($expense_month, 2) ?></div><div class="s"><?= esc(date('M Y')) ?> · paid</div></div>
    </div>
</section>
