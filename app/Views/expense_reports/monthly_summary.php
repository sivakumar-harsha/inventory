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

<?= $this->include('expense_reports/partials/summary_head') ?>

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
                    <th class="sno-col">S.No.</th>
                    <th>Month</th>
                    <th style="text-align:right">Expense Count</th>
                    <th style="text-align:right">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="sno-col" data-label="S.No."></td>
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
            emptyTable: '<div class="exp-empty"><i class="bi bi-inbox"></i>No paid expenses recorded yet.</div>'
        }
    });
});
</script>
<?= $this->endSection() ?>
