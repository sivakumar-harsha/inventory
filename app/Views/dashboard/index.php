<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
/* KPI CARD (Release 4.3.2: compact executive dashboard) */
.kpi-card {
    background: #fff;
    border-radius: 12px;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 70px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
    border: 1px solid #eef0f4;
    transition: box-shadow 0.2s ease, transform 0.2s ease;
}
.kpi-card:hover {
    box-shadow: 0 8px 18px rgba(0,0,0,0.10);
    transform: translateY(-2px);
}
.kpi-icon {
    width: 40px;
    height: 40px;
    min-width: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
}
.kpi-value {
    font-size: 18px;
    font-weight: 700;
    color: #111827;
    line-height: 1.2;
}
.kpi-label {
    font-size: 12px;
    color: #6b7280;
}

.kpi-blue   .kpi-icon { background: #eff6ff; color: #2563eb; }
.kpi-green  .kpi-icon { background: #dcfce7; color: #16a34a; }
.kpi-orange .kpi-icon { background: #ffedd5; color: #d97706; }
.kpi-red    .kpi-icon { background: #fee2e2; color: #dc2626; }
.kpi-purple .kpi-icon { background: #f3e8ff; color: #7e22ce; }
.kpi-pink   .kpi-icon { background: #fdf2f8; color: #db2777; }

/* RELEASE 4.3.3: dashboard-local card padding trim (Phase G) */
.card-custom-body { padding: 12px 14px; }
.card-custom-header { padding: 10px 14px; }

/* QUICK ACTIONS (Phase E) — same pill style as projects/view.php's
   action-chip, scoped locally since global style.css is out of scope. */
.quick-actions-row { display: flex; gap: 8px; flex-wrap: wrap; }
.action-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; height: 32px; border-radius: 999px; font-size: 0.78rem; font-weight: 600; text-decoration: none; border: 1px solid transparent; }
.action-chip-blue   { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
.action-chip-blue:hover   { background: #dbeafe; }
.action-chip-purple { background: #f3e8ff; color: #7e22ce; border-color: #e9d5ff; }
.action-chip-purple:hover { background: #e9d5ff; }
.action-chip-green  { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
.action-chip-green:hover  { background: #bbf7d0; }
.action-chip-orange { background: #ffedd5; color: #c2410c; border-color: #fed7aa; }
.action-chip-orange:hover { background: #fed7aa; }
.action-chip-gray   { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
.action-chip-gray:hover   { background: #e2e8f0; }

/* PROJECT STATUS — progress-style rows (Phase D) */
.status-progress-row { display: flex; align-items: center; gap: 12px; padding: 8px 4px; }
.status-progress-icon {
    width: 34px; height: 34px; min-width: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; font-size: 15px;
}
.status-progress-body { flex: 1; min-width: 0; }
.status-progress-top { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px; }
.status-progress-label { font-size: 12.5px; font-weight: 600; color: #374151; }
.status-progress-count { font-size: 13px; font-weight: 700; color: #111827; }
.status-progress-track { width: 100%; height: 6px; border-radius: 4px; background: #eef0f4; overflow: hidden; }
.status-progress-fill { height: 100%; border-radius: 4px; }

/* REVENUE VS COST CARD (Phase B/C) */
.rvc-chart-col { display: flex; align-items: center; justify-content: center; }
.rvc-summary-card {
    display: flex; align-items: center; gap: 10px;
    min-height: 62px; padding: 8px 12px; border-radius: 10px;
    background: #f8fafc; border: 1px solid #e2e8f0;
}
.rvc-summary-icon {
    width: 34px; height: 34px; min-width: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; font-size: 15px;
}
.rvc-summary-label { font-size: 11px; color: #64748b; }
.rvc-summary-value { font-size: 15px; font-weight: 700; color: #1e293b; line-height: 1.2; }

/* TABLE */
.table-custom {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 6px;
}
.table-custom tbody tr {
    background: #fff;
    box-shadow: 0 3px 8px rgba(0,0,0,0.04);
    border-radius: 8px;
}
.table-custom td { padding: 8px 10px; border: none; }
.dash-table-dense tr { height: 36px; }
.dash-table-dense td, .dash-table-dense th {
    padding: 6px 10px;
    font-size: 0.82rem;
    vertical-align: middle;
    white-space: nowrap;
}
.card-custom:has(.dash-table-dense) .table-responsive { overflow-x: hidden; }

/* Phase F: sticky header + status badges for Top Pending Projects */
.dash-table-scroll { max-height: 260px; overflow-y: auto; }
.dash-table-dense thead th { position: sticky; top: 0; background: #fff; z-index: 1; }
.badge-pill-red   { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: #fee2e2; color: #dc2626; }
.badge-pill-green { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: #dcfce7; color: #16a34a; }

.card-custom, .kpi-card { animation: fadeUp 0.4s ease; }
@keyframes fadeUp {
    from { opacity: 0; }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

<!-- ROW 1: BUSINESS OVERVIEW -->
<div class="row g-3 mb-3">
    <div class="col-md-3 col-6">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-receipt-cutoff"></i></div>
            <div>
                <div class="kpi-value counter" data-value="<?= $total_sales ?>"><?= number_format($total_sales, 2) ?></div>
                <div class="kpi-label">Total Sales</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-cart-fill"></i></div>
            <div>
                <div class="kpi-value counter" data-value="<?= $total_purchases ?>"><?= number_format($total_purchases, 2) ?></div>
                <div class="kpi-label">Total Purchases</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-wallet2"></i></div>
            <div>
                <div class="kpi-value counter" data-value="<?= $total_expenses ?>"><?= number_format($total_expenses, 2) ?></div>
                <div class="kpi-label">Total Expenses</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card <?= $total_net_profit < 0 ? 'kpi-red' : 'kpi-green' ?>">
            <div class="kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($total_net_profit, 2) ?></div>
                <div class="kpi-label">Net Profit</div>
            </div>
        </div>
    </div>
</div>

<!-- ROW 2: COLLECTIONS & PROJECTS -->
<div class="row g-3 mb-3">
    <div class="col-md-3 col-6">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($total_outstanding, 2) ?></div>
                <div class="kpi-label">Outstanding Collection</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-calendar-check"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($month_sales, 2) ?></div>
                <div class="kpi-label">This Month Revenue</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card kpi-purple">
            <div class="kpi-icon"><i class="bi bi-kanban"></i></div>
            <div>
                <div class="kpi-value"><?= $active_projects ?></div>
                <div class="kpi-label">Active Projects</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-check2-circle"></i></div>
            <div>
                <div class="kpi-value"><?= $completed_projects ?></div>
                <div class="kpi-label">Completed Projects</div>
            </div>
        </div>
    </div>
</div>

<!-- QUICK ACTIONS (Phase E) -->
<div class="card-custom mb-3">
    <div class="card-custom-header"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</div>
    <div class="card-custom-body quick-actions-row">
        <a href="<?= base_url('sales/create') ?>" class="action-chip action-chip-blue"><i class="bi bi-receipt"></i> Add Sales Invoice</a>
        <a href="<?= base_url('payments/create') ?>" class="action-chip action-chip-purple"><i class="bi bi-cash-coin"></i> Record Payment</a>
        <a href="<?= base_url('purchases/create') ?>" class="action-chip action-chip-green"><i class="bi bi-cart"></i> Add Purchase</a>
        <a href="<?= base_url('expenses/create') ?>" class="action-chip action-chip-orange"><i class="bi bi-wallet2"></i> Add Expense</a>
        <a href="<?= base_url('projects/create') ?>" class="action-chip action-chip-gray"><i class="bi bi-kanban"></i> Add Project</a>
    </div>
</div>

<!-- PROJECT STATUS SUMMARY (Phase D: progress-style rows) -->
<?php $totalProjectsAll = max(1, $active_projects + $completed_projects + $onhold_projects); ?>
<div class="card-custom mb-3">
    <div class="card-custom-header"><i class="bi bi-pie-chart me-2"></i>Project Status Summary</div>
    <div class="card-custom-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="status-progress-row">
                    <div class="status-progress-icon" style="background:#f3e8ff;color:#7e22ce;"><i class="bi bi-kanban"></i></div>
                    <div class="status-progress-body">
                        <div class="status-progress-top">
                            <span class="status-progress-label">Active</span>
                            <span class="status-progress-count"><?= $active_projects ?></span>
                        </div>
                        <div class="status-progress-track">
                            <div class="status-progress-fill" style="width:<?= round($active_projects / $totalProjectsAll * 100, 1) ?>%;background:#7e22ce;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="status-progress-row">
                    <div class="status-progress-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-check2-circle"></i></div>
                    <div class="status-progress-body">
                        <div class="status-progress-top">
                            <span class="status-progress-label">Completed</span>
                            <span class="status-progress-count"><?= $completed_projects ?></span>
                        </div>
                        <div class="status-progress-track">
                            <div class="status-progress-fill" style="width:<?= round($completed_projects / $totalProjectsAll * 100, 1) ?>%;background:#16a34a;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="status-progress-row">
                    <div class="status-progress-icon" style="background:#ffedd5;color:#d97706;"><i class="bi bi-pause-circle"></i></div>
                    <div class="status-progress-body">
                        <div class="status-progress-top">
                            <span class="status-progress-label">On Hold</span>
                            <span class="status-progress-count"><?= $onhold_projects ?></span>
                        </div>
                        <div class="status-progress-track">
                            <div class="status-progress-fill" style="width:<?= round($onhold_projects / $totalProjectsAll * 100, 1) ?>%;background:#d97706;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- REVENUE VS COST (Phase B/C) -->
<div class="card-custom mb-3">
    <div class="card-custom-header"><i class="bi bi-bar-chart-line me-2"></i>Revenue vs Cost (This Month)</div>
    <div class="card-custom-body">
        <div class="row g-3 align-items-center">
            <div class="col-md-5 rvc-chart-col">
                <div id="donutChart" style="width:100%;max-width:200px;"></div>
            </div>
            <div class="col-md-7">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="rvc-summary-card">
                            <div class="rvc-summary-icon" style="background:#eff6ff;color:#2563eb;"><i class="bi bi-graph-up-arrow"></i></div>
                            <div>
                                <div class="rvc-summary-value"><?= number_format($month_sales, 2) ?></div>
                                <div class="rvc-summary-label">Revenue</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="rvc-summary-card">
                            <div class="rvc-summary-icon" style="background:#ffedd5;color:#d97706;"><i class="bi bi-cart-fill"></i></div>
                            <div>
                                <div class="rvc-summary-value"><?= number_format($month_purchases, 2) ?></div>
                                <div class="rvc-summary-label">Purchase Cost</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="rvc-summary-card">
                            <div class="rvc-summary-icon" style="background:#fee2e2;color:#dc2626;"><i class="bi bi-wallet2"></i></div>
                            <div>
                                <div class="rvc-summary-value"><?= number_format($month_expenses, 2) ?></div>
                                <div class="rvc-summary-label">Expenses</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="rvc-summary-card">
                            <div class="rvc-summary-icon" style="<?= $month_net_profit < 0 ? 'background:#fee2e2;color:#dc2626;' : 'background:#dcfce7;color:#16a34a;' ?>"><i class="bi bi-piggy-bank"></i></div>
                            <div>
                                <div class="rvc-summary-value" style="<?= $month_net_profit < 0 ? 'color:#dc2626;' : 'color:#16a34a;' ?>"><?= number_format($month_net_profit, 2) ?></div>
                                <div class="rvc-summary-label">Net Profit</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TOP PENDING PROJECTS (Phase F) -->
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card-custom">
            <div class="card-custom-header">
                <i class="bi bi-exclamation-diamond me-2"></i>Top Pending Projects
            </div>
            <div class="table-responsive dash-table-scroll">
                <table class="table-custom dash-table-dense">
                    <thead style="font-size:12px; color:#888;">
                        <tr>
                            <th>Project</th>
                            <th style="text-align:right;width:180px;">Remaining Balance</th>
                            <th style="text-align:right;width:180px;">Outstanding Collection</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_pending_projects)): ?>
                        <tr><td colspan="3" class="text-center text-muted">No pending collections</td></tr>
                        <?php else: ?>
                        <?php foreach ($top_pending_projects as $pp): ?>
                        <tr>
                            <td style="font-weight:500;"><?= esc($pp['project_name']) ?></td>
                            <td style="text-align:right; font-weight:600; color:#2563eb;">₹ <?= number_format($pp['remaining_balance'], 2) ?></td>
                            <td style="text-align:right;">
                                <?php if ($pp['outstanding_collection'] > 0): ?>
                                <span class="badge-pill-red">₹ <?= number_format($pp['outstanding_collection'], 2) ?></span>
                                <?php else: ?>
                                <span class="badge-pill-green">Settled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
// REVENUE VS COST DONUT
new ApexCharts(document.querySelector("#donutChart"), {
    chart: { type: 'donut', height: 190 },
    plotOptions: {
        pie: {
            donut: {
                size: '65%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total',
                        fontSize: '11px',
                        formatter: function () {
                            return '₹ <?= number_format($month_sales + $month_purchases + $month_expenses, 0) ?>';
                        }
                    }
                }
            }
        }
    },
    series: [
        <?= (float)$month_sales ?>,
        <?= (float)$month_purchases ?>,
        <?= (float)$month_expenses ?>
    ],
    labels: ['Sales Revenue', 'Purchase Cost', 'Expenses'],
    dataLabels: {
        enabled: true,
        formatter: function(val) {
            return val.toFixed(0) + "%";
        }
    },
    colors: ['#6a11cb','#28a745','#ff9800'],
    legend: { show: false }
}).render();
</script>

<script>
// COUNTER ANIMATION
document.querySelectorAll('.counter').forEach(el => {
    let value = parseFloat(el.getAttribute('data-value')) || 0;
    let count = 0;
    let step = value / 50;

    let interval = setInterval(() => {
        count += step;
        if (count >= value) {
            el.innerText = value.toLocaleString();
            clearInterval(interval);
        } else {
            el.innerText = Math.floor(count).toLocaleString();
        }
    }, 20);
});
</script>

<?= $this->endSection() ?>
