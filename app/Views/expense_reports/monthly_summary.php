<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$money  = static fn ($n) => number_format((float) $n, 2);
$erPath = trim(uri_string(), '/');
$count  = count($rows);
?>

<?= $this->include('expenses/partials/ui_styles') ?>

<style>
#erTable.table-custom th, #erTable.table-custom td { padding: 7px 10px; font-size: .75rem; }
</style>

<nav aria-label="breadcrumb" class="exp-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('reports') ?>">Reports</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('expense-reports') ?>">Expense Reports</a></li>
        <li class="breadcrumb-item active" aria-current="page">Monthly Summary</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-calendar3 me-2"></i>Monthly Expense Summary</span>
    <div class="d-flex gap-2">
        <a href="<?= base_url('expenses') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Expense Management</a>
    </div>
</div>

<div class="exp-subnav exp-noprint">
    <a href="<?= base_url('expense-reports') ?>" class="<?= $erPath === 'expense-reports' ? 'active' : '' ?>">Expense Register</a>
    <a href="<?= base_url('expense-reports/category-summary') ?>" class="<?= $erPath === 'expense-reports/category-summary' ? 'active' : '' ?>">Category Summary</a>
    <a href="<?= base_url('expense-reports/project-summary') ?>" class="<?= $erPath === 'expense-reports/project-summary' ? 'active' : '' ?>">Project Summary</a>
    <a href="<?= base_url('expense-reports/payment-summary') ?>" class="<?= $erPath === 'expense-reports/payment-summary' ? 'active' : '' ?>">Payment Method Summary</a>
    <a href="<?= base_url('expense-reports/monthly-summary') ?>" class="<?= $erPath === 'expense-reports/monthly-summary' ? 'active' : '' ?>">Monthly Summary</a>
</div>

<?= $this->include('expenses/partials/report_toolbar') ?>

<div class="exp-noprint mb-2">
    <span class="exp-filter-chip"><i class="bi bi-info-circle" style="font-size:.68rem;"></i>All paid expenses, grouped by month</span>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-calendar3"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_total_months) ?></div>
                <div class="kpi-label">Total Months</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-arrow-up-circle"></i></div>
            <div>
                <div class="kpi-value"><?= $money($kpi_highest) ?></div>
                <div class="kpi-label">Highest Month</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-arrow-down-circle"></i></div>
            <div>
                <div class="kpi-value"><?= $money($kpi_lowest) ?></div>
                <div class="kpi-label">Lowest Month</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-bar-chart"></i></div>
            <div>
                <div class="kpi-value"><?= $money($kpi_average) ?></div>
                <div class="kpi-label">Average Monthly Expense</div>
            </div>
        </div>
    </div>
</div>

<div class="card-custom">
    <div class="card-custom-header exp-card-head">
        <span>Monthly Summary</span>
        <span class="exp-count"><?= number_format($count) ?> month<?= $count === 1 ? '' : 's' ?></span>
    </div>
    <div class="table-responsive exp-scroll exp-sticky">
        <table id="erTable" class="table-custom">
            <thead>
                <tr>
                    <th>Month</th>
                    <th style="text-align:right">Expense Count</th>
                    <th style="text-align:right">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($count === 0): ?>
                <tr><td colspan="3" class="exp-empty"><i class="bi bi-inbox"></i>No paid expenses recorded yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td data-order="<?= esc($r['month_key']) ?>"><?= esc($r['month_label']) ?></td>
                    <td style="text-align:right"><?= number_format($r['count']) ?></td>
                    <td style="text-align:right" data-order="<?= (float) $r['total'] ?>"><?= $money($r['total']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function () {
    $('#erTable').DataTable({
        paging: true,
        searching: false,
        lengthChange: false,
        info: false,
        ordering: true,
        order: [],
        pageLength: 12,
        dom: 'tp',
        language: {
            paginate: {
                previous: '<i class="bi bi-chevron-left"></i>',
                next: '<i class="bi bi-chevron-right"></i>'
            },
            emptyTable: 'No paid expenses recorded yet.'
        }
    });
});
</script>
<?= $this->endSection() ?>
