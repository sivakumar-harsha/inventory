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
            <div class="kpi-icon"><i class="bi bi-tags"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($kpi_categories_used) ?></div>
                <div class="kpi-label">Categories Used</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div>
                <div class="kpi-value"><?= $money($kpi_highest) ?></div>
                <div class="kpi-label">Highest Category Expense</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="kpi-value"><?= $money($kpi_total) ?></div>
                <div class="kpi-label">Total Expense</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-bar-chart"></i></div>
            <div>
                <div class="kpi-value"><?= $money($kpi_average) ?></div>
                <div class="kpi-label">Average Expense Per Category</div>
            </div>
        </div>
    </div>
</div>

<div class="card-custom">
    <div class="card-custom-header exp-card-head">
        <span>Category Summary</span>
        <span class="exp-count"><?= number_format($count) ?> categor<?= $count === 1 ? 'y' : 'ies' ?></span>
    </div>
    <div class="table-responsive exp-scroll exp-sticky">
        <table id="erTable" class="table-custom">
            <thead>
                <tr>
                    <th class="sno-col">S.No.</th>
                    <th>Category</th>
                    <th style="text-align:right">Expense Count</th>
                    <th style="text-align:right">Total Amount</th>
                    <th style="text-align:right">Percentage of Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="sno-col" data-label="S.No."></td>
                    <td><span class="exp-badge exp-badge-category"><?= esc($r['category_name'] ?? 'Uncategorized') ?></span></td>
                    <td style="text-align:right"><?= number_format($r['count']) ?></td>
                    <td style="text-align:right" data-order="<?= (float) $r['total'] ?>"><?= $money($r['total']) ?></td>
                    <td style="text-align:right"><?= number_format($r['percentage'], 1) ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc;font-weight:700">
                    <td class="sno-col" data-label="S.No."></td>
                    <td>TOTAL</td>
                    <td></td>
                    <td style="text-align:right"><?= $money($kpi_total) ?></td>
                    <td style="text-align:right">100.0%</td>
                </tr>
            </tfoot>
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
        pageLength: 10,
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
