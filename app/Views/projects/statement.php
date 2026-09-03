<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
    // Release 3.0 (UI only): every figure below is an unchanged source — same
    // $financial_summary (ProjectModel::getFinancialSummary()), $total_purchases,
    // $total_expenses, $total_cost, $net_profit, $timeline already built by
    // Projects::_buildStatementData(). No new calculation anywhere.
    $csValue     = (float) ($financial_summary['total_project_value'] ?? 0);
    $csBilled    = (float) ($financial_summary['total_billed'] ?? 0);
    $csPercent   = (float) ($financial_summary['billing_progress_percent'] ?? 0);
    $csRemaining = (float) ($financial_summary['remaining_billable_value'] ?? 0);

    $billingCompletionStatus = $billing_completion_status ?? 'ACTIVE';
    $bcsMap = ['ACTIVE' => 'active', 'PARTIAL' => 'partial', 'COMPLETED' => 'completed'];
    $bcsCls = $bcsMap[$billingCompletionStatus] ?? 'active';

    $outstanding = (float) ($financial_summary['outstanding_collection_balance'] ?? 0);
?>

<!-- Release 3.0 (Phase B): compact header, identical pattern to Project View. -->
<div class="project-header-bar mb-3">
    <div class="project-header-left">
        <div class="project-header-main">
            <span class="project-header-name"><i class="bi bi-file-earmark-text me-2"></i><?= esc($project['name']) ?></span>
            <span class="project-subheader-sep">•</span>
            <span><i class="bi bi-person me-1"></i><?= esc($project['customer_name'] ?: 'No Customer') ?></span>
            <span class="badge-status badge-<?= strtolower($project['status']) ?>" title="Work Status">Work: <?= str_replace('_', ' ', $project['status']) ?></span>
            <span class="badge-status badge-<?= $bcsCls ?>" title="Billing Status">Billing: <?= $billingCompletionStatus ?></span>
        </div>
        <div class="project-header-dates">
            <i class="bi bi-calendar3 me-1"></i><?= esc($project['start_date']) ?> &rarr; <?= $project['end_date'] ? esc($project['end_date']) : 'Ongoing' ?>
        </div>
    </div>
    <div class="project-header-actions">
        <a href="<?= base_url('projects/statements/') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
        <a href="<?= base_url('projects/statement-export/' . $project['id']) ?>" class="btn-view"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
        <a href="<?= base_url('projects/statement-pdf/' . $project['id']) ?>" class="btn-view"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
    </div>
</div>

<!-- Release 3.0 (Phase C): Project Financial Summary — exactly 8 compact KPI
     cards, 2 rows x 4 columns. -->
<div class="row g-12 mb-3 statement-kpi-row">
    <div class="col-md-3 col-6">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-file-earmark-text"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($csValue, 2) ?></div>
                <div class="kpi-label">Contract Value</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-piggy-bank"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($financial_summary['advance_amount'] ?? 0, 2) ?></div>
                <div class="kpi-label">Advance Received</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-receipt-cutoff"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($csBilled, 2) ?></div>
                <div class="kpi-label">Total Invoiced</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <?php
            if ($csRemaining > 0.004) {
                $remKpiCls = 'kpi-orange'; $remIcon = 'bi-wallet2';
                $remLabel  = 'Remaining Balance';
                $remValue  = number_format($csRemaining, 2);
            } elseif ($csRemaining < -0.004) {
                $remKpiCls = 'kpi-red'; $remIcon = 'bi-exclamation-triangle';
                $remLabel  = 'Over Billed';
                $remValue  = number_format(abs($csRemaining), 2);
            } else {
                $remKpiCls = 'kpi-green'; $remIcon = 'bi-check-circle';
                $remLabel  = 'Fully Billed';
                $remValue  = '';
            }
        ?>
        <div class="kpi-card <?= $remKpiCls ?>">
            <div class="kpi-icon"><i class="bi <?= $remIcon ?>"></i></div>
            <div>
                <?php if ($remValue !== ''): ?>
                <div class="kpi-value"><?= $remValue ?></div>
                <?php endif; ?>
                <div class="kpi-label"><?= $remLabel ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($financial_summary['total_paid'] ?? 0, 2) ?></div>
                <div class="kpi-label">Total Customer Paid</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <?php
            if ($outstanding > 0.004) {
                $outKpiCls = 'kpi-orange'; $outIcon = 'bi-hourglass-split';
                $outValue  = number_format($outstanding, 2);
            } elseif ($outstanding < -0.004) {
                $outKpiCls = 'kpi-green'; $outIcon = 'bi-award';
                $outValue  = number_format(abs($outstanding), 2) . ' Credit';
            } else {
                $outKpiCls = 'kpi-green'; $outIcon = 'bi-check-circle';
                $outValue  = 'Settled';
            }
        ?>
        <div class="kpi-card <?= $outKpiCls ?>">
            <div class="kpi-icon"><i class="bi <?= $outIcon ?>"></i></div>
            <div>
                <div class="kpi-value"><?= $outValue ?></div>
                <div class="kpi-label">Outstanding Collection</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-cart"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($total_purchases, 2) ?></div>
                <div class="kpi-label">Total Purchases</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-credit-card"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($total_expenses, 2) ?></div>
                <div class="kpi-label">Total Expenses</div>
            </div>
        </div>
    </div>
</div>

<!-- Release 3.0 (Phase D): Billing Overview — one thin progress bar, a
     billing percentage badge, and one inline figures strip. -->
<div class="card-custom mb-3">
    <div class="card-custom-header"><i class="bi bi-graph-up-arrow me-2"></i>Billing Overview</div>
    <div class="card-custom-body billing-overview-body">
        <div class="progress" style="height:6px;background:#e2e8f0;border-radius:999px;overflow:hidden">
            <div class="progress-bar" role="progressbar"
                 style="width:<?= max(0, min(100, $csPercent)) ?>%;background:#2563eb"></div>
        </div>
        <div class="billing-strip-figures">
            <span><span class="billing-strip-label">Contract Value</span> ₹<?= number_format($csValue, 2) ?></span>
            <span class="billing-strip-sep">•</span>
            <span><span class="billing-strip-label">Total Invoiced</span> ₹<?= number_format($csBilled, 2) ?></span>
            <span class="billing-strip-sep">•</span>
            <?php if ($csRemaining > 0.004): ?>
            <span><span class="billing-strip-label">Remaining Balance</span> ₹<?= number_format($csRemaining, 2) ?></span>
            <?php elseif ($csRemaining < -0.004): ?>
            <span class="billing-strip-overbilled"><span class="billing-strip-label">Over Billed</span> ₹<?= number_format(abs($csRemaining), 2) ?></span>
            <?php else: ?>
            <span><span class="billing-strip-label">Remaining Balance</span> ₹0.00</span>
            <?php endif; ?>
            <span class="billing-strip-pct ms-auto"><?= number_format($csPercent, 0) ?>% Billed</span>
        </div>
    </div>
</div>

<!-- Release 3.0 (Phase E): Cost & Profit Summary — separate compact section. -->
<div class="card-custom mb-3">
    <div class="card-custom-header"><i class="bi bi-layers me-2"></i>Cost &amp; Profit Summary</div>
    <div class="card-custom-body">
        <div class="row g-12">
            <div class="col-md-3 col-6">
                <div class="kpi-card kpi-orange">
                    <div class="kpi-icon"><i class="bi bi-cart"></i></div>
                    <div>
                        <div class="kpi-value"><?= number_format($total_purchases, 2) ?></div>
                        <div class="kpi-label">Total Purchases</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="kpi-card kpi-orange">
                    <div class="kpi-icon"><i class="bi bi-credit-card"></i></div>
                    <div>
                        <div class="kpi-value"><?= number_format($total_expenses, 2) ?></div>
                        <div class="kpi-label">Total Expenses</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="kpi-card kpi-red">
                    <div class="kpi-icon"><i class="bi bi-layers"></i></div>
                    <div>
                        <div class="kpi-value"><?= number_format($total_cost, 2) ?></div>
                        <div class="kpi-label">Total Project Cost</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="kpi-card <?= $net_profit >= 0 ? 'kpi-green' : 'kpi-red' ?>">
                    <div class="kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
                    <div>
                        <div class="kpi-value"><?= number_format($net_profit, 2) ?></div>
                        <div class="kpi-label">Net Profit</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Release 3.0 (Phase F): Timeline redesign — Date, Event, Reference, Amount,
     Running Collection Balance. Description and Category columns removed.
     Ordering and calculations (FIFO running balance) are unchanged. -->
<div class="card-custom">
    <div class="card-custom-header"><i class="bi bi-clock-history me-2"></i>Chronological Timeline</div>
    <div class="card-custom-body">
        <div class="table-responsive statement-timeline-wrap">
            <table class="table-custom" id="timelineTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Event</th>
                        <th>Reference</th>
                        <th style="text-align:right">Amount</th>
                        <th style="text-align:right">Running Collection Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($timeline)): ?>
                    <tr><td colspan="5" class="text-center text-muted">No timeline events for this project</td></tr>
                    <?php else: ?>
                    <?php
                        $typeLabels = [
                            'Project Advance' => 'Customer Advance Received',
                            'Sales Invoice'    => 'Billing Raised to Customer',
                            'Invoice Payment'  => 'Payment Collected from Customer',
                            'Purchase'         => 'Purchased Materials for Project',
                            'Expense'          => 'Project Expense Recorded',
                        ];
                        $typeIcons = [
                            'Project Advance' => 'bi-piggy-bank',
                            'Sales Invoice'    => 'bi-receipt-cutoff',
                            'Invoice Payment'  => 'bi-cash-coin',
                            'Purchase'         => 'bi-cart',
                            'Expense'          => 'bi-credit-card',
                        ];
                        $typeBadgeCls = [
                            'Project Advance' => 'event-badge-advance',
                            'Sales Invoice'    => 'event-badge-billing',
                            'Invoice Payment'  => 'event-badge-payment',
                            'Purchase'         => 'event-badge-purchase',
                            'Expense'          => 'event-badge-expense',
                        ];
                    ?>
                    <?php foreach ($timeline as $ev): ?>
                    <?php
                        $typeIcon  = $typeIcons[$ev['type']] ?? 'bi-dot';
                        $typeLabel = $typeLabels[$ev['type']] ?? $ev['type'];
                        $badgeCls  = $typeBadgeCls[$ev['type']] ?? 'event-badge-payment';
                    ?>
                    <tr data-date="<?= esc($ev['date']) ?>" data-category="<?= esc($ev['category']) ?>" data-type="<?= esc($ev['type']) ?>">
                        <td><?= esc($ev['date']) ?></td>
                        <td><span class="event-badge <?= $badgeCls ?>"><i class="bi <?= $typeIcon ?>"></i><?= esc($typeLabel) ?></span></td>
                        <td><?= esc($ev['reference']) ?></td>
                        <td style="text-align:right">
                            <?= $ev['amount'] !== null ? number_format($ev['amount'], 2) : '—' ?>
                        </td>
                        <td style="text-align:right" class="running-balance-col">
                            <?php if ($ev['category'] === 'Cost'): ?>
                                <span class="balance-pill balance-costonly">Project Cost Only</span>
                            <?php elseif ($ev['running_balance'] === null): ?>
                                —
                            <?php elseif ($ev['running_balance'] < -0.004): ?>
                                <span class="balance-pill balance-credit">Advance Credit <?= number_format(abs($ev['running_balance']), 2) ?></span>
                            <?php elseif ($ev['running_balance'] <= 0.004): ?>
                                <span class="balance-pill balance-settled">Settled</span>
                            <?php else: ?>
                                <span class="balance-pill balance-outstanding">Outstanding <?= number_format($ev['running_balance'], 2) ?></span>
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

<style>
.g-12 { --bs-gutter-x: 12px; --bs-gutter-y: 12px; }
.card-custom-header { padding: 10px 14px; }
.card-custom-body { padding: 12px 14px; }

.project-header-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; padding: 8px 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; }
.project-header-left { display: flex; flex-direction: column; gap: 2px; }
.project-header-main { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 0.82rem; color: #475569; }
.project-header-name { font-size: 1rem; font-weight: 700; color: #1e293b; }
.project-header-dates { font-size: 0.74rem; color: #64748b; }
.project-header-actions { display: flex; gap: 8px; flex-wrap: wrap; align-self: center; }
.project-subheader-sep { color: #cbd5e1; }

.statement-kpi-row .kpi-card { padding: 8px 10px; height: 72px; border-width: 1px; gap: 6px; text-align: center; justify-content: center; }
.statement-kpi-row .kpi-icon { width: 28px; height: 28px; font-size: 14px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.statement-kpi-row .kpi-value { font-size: 18px; font-weight: 700; text-align: center; }
.statement-kpi-row .kpi-label { font-size: 11px; color: #64748b; text-align: center; }

.billing-overview-body { padding: 10px; }
.billing-strip-pct { font-size: 0.78rem; font-weight: 700; color: #2563eb; white-space: nowrap; text-align: right; }
.billing-strip-figures { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 0.76rem; color: #1e293b; margin-top: 6px; }
.billing-strip-label { color: #64748b; text-transform: uppercase; letter-spacing: .02em; font-size: 0.66rem; margin-right: 3px; }
.billing-strip-sep { color: #cbd5e1; }
.billing-strip-overbilled { color: #b91c1c; font-weight: 700; }
.billing-strip-overbilled .billing-strip-label { color: #b91c1c; }

.event-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 9px;
    font-size: 0.72rem;
    font-weight: 600;
    border-radius: 20px;
    white-space: nowrap;
}
.event-badge-billing  { background: #dbeafe; color: #1d4ed8; }
.event-badge-payment  { background: #dcfce7; color: #15803d; }
.event-badge-purchase { background: #ffedd5; color: #c2410c; }
.event-badge-expense  { background: #fee2e2; color: #b91c1c; }
.event-badge-advance  { background: #f3e8ff; color: #7e22ce; }

.balance-pill {
    display: inline-block;
    padding: 2px 9px;
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1.5;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    white-space: nowrap;
}
.balance-outstanding { background: #fef3c7; color: #92400e; }
.balance-credit      { background: #dcfce7; color: #15803d; }
.balance-settled      { background: transparent; color: #15803d; border: 1px solid #15803d; }
.balance-costonly    { background: #f1f5f9; color: #64748b; }

.running-balance-col { font-variant-numeric: tabular-nums; }

.statement-timeline-wrap { max-height: 560px; overflow-y: auto; }
#timelineTable thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f8fafc;
}
#timelineTable td, #timelineTable th {
    height: 36px;
    padding: 4px 10px;
    font-size: 0.82rem;
    vertical-align: middle;
}
</style>

<?= $this->endSection() ?>
