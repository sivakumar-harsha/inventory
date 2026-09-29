<?php
/**
 * Release 4.8.6D: Expense KPIs + latest-10 widget. Included from
 * dashboard/index.php right after Loan Summary, so it reuses that page's
 * kpi-card / card-custom / dash-table-dense styles. Renders nothing until
 * the expense tables exist (Dashboard::_expenseSummary()).
 *
 * Release 4.8.6E: badge/scroll/row-link styling now comes from the shared
 * expenses/partials/ui_styles.php kit (.exp-*) instead of a local block, so
 * the dashboard widget matches the Expense CRUD/Reports screens exactly.
 */
if (empty($expense_available)) {
    return;
}

$money = static fn ($n) => number_format((float) $n, 2);
$methodClasses = ['CASH' => 'exp-badge-m-cash', 'BANK' => 'exp-badge-m-bank', 'CHEQUE' => 'exp-badge-m-cheque', 'UPI' => 'exp-badge-m-upi', 'OTHER' => 'exp-badge-m-other'];
?>

<?= $this->include('expenses/partials/ui_styles') ?>

<!-- EXPENSES (Release 4.8.6D, polished in 4.8.6E) -->
<div class="row g-3 mb-3">
    <div class="col-lg-3 col-6">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-calendar-day"></i></div>
            <div>
                <div class="kpi-value"><?= $money($expense_today) ?></div>
                <div class="kpi-label">Today Expense</div>
                <div class="exp-kpi-sub"><?= esc(date('d M Y')) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-calendar-month"></i></div>
            <div>
                <div class="kpi-value"><?= $money($expense_month) ?></div>
                <div class="kpi-label">This Month Expense</div>
                <div class="exp-kpi-sub"><?= esc(date('F Y')) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="kpi-value"><?= $money($expense_total) ?></div>
                <div class="kpi-label">Total Expense</div>
                <div class="exp-kpi-sub">All time, paid only</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-tags"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($expense_categories_used) ?></div>
                <div class="kpi-label">Active Categories</div>
                <div class="exp-kpi-sub">With at least one expense</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card-custom">
            <div class="card-custom-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-credit-card me-2"></i>Latest Expenses</span>
                <a href="<?= base_url('expense-reports') ?>" class="btn-save" style="padding:3px 10px;font-size:12px;">View All</a>
            </div>
            <div class="exp-dash-scroll">
                <table class="table-custom dash-table-dense" id="expRecent">
                    <thead style="font-size:12px; color:#888;">
                        <tr>
                            <th>Expense No</th>
                            <th>Category</th>
                            <th>Payment Method</th>
                            <th style="text-align:right">Amount</th>
                            <th>Paid To</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expense_recent)): ?>
                        <tr><td colspan="6" class="exp-empty"><i class="bi bi-inbox"></i>No expenses recorded yet</td></tr>
                        <?php endif; ?>
                        <?php foreach ($expense_recent as $e): ?>
                        <?php $viewUrl = base_url('expenses/view/' . $e['id']); ?>
                        <tr class="exp-row-link" data-href="<?= esc($viewUrl, 'attr') ?>" title="View expense" tabindex="0">
                            <td><a href="<?= esc($viewUrl, 'attr') ?>" style="text-decoration:none;"><?= esc($e['expense_no']) ?></a></td>
                            <td><span class="exp-badge exp-badge-category"><?= esc($e['category_name'] ?? 'Uncategorized') ?></span></td>
                            <td><span class="exp-badge <?= $methodClasses[$e['payment_method']] ?? 'exp-badge-m-other' ?>"><?= esc(ucfirst(strtolower($e['payment_method']))) ?></span></td>
                            <td style="text-align:right"><?= $money($e['amount']) ?></td>
                            <td><?= esc($e['paid_to']) ?></td>
                            <td><?= esc(date('d-m-Y', strtotime($e['expense_date']))) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('click', function (e) {
        var row = e.target.closest ? e.target.closest('#expRecent tr.exp-row-link') : null;
        if (row && !e.target.closest('a, button')) window.location.href = row.getAttribute('data-href');
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        var row = e.target.closest ? e.target.closest('#expRecent tr.exp-row-link') : null;
        if (row) window.location.href = row.getAttribute('data-href');
    });
</script>
