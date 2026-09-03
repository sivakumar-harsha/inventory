<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
    // Release 2.2A (UI only): every figure below is an unchanged source —
    // still $financial_summary (ProjectModel::getFinancialSummary()) and
    // plain counts/sums over the same arrays Projects::view() already loads
    // ($sales, $purchases, $expenses, $total_purchases, $total_expenses,
    // $total_payments_count). This release only removes sections and moves
    // where remaining figures are displayed — no new calculation anywhere.
    $csValue     = (float) ($financial_summary['total_project_value'] ?? 0);
    $csBilled    = (float) ($financial_summary['total_billed'] ?? 0);
    $csPercent   = (float) ($financial_summary['billing_progress_percent'] ?? 0);
    $csRemaining = max(0, $csValue - $csBilled);

    // Release 2.3A: Project Billing Status is a separate, manual concept from
    // invoice collection status — it tracks whether more invoices are
    // expected, not whether existing invoices are collected.
    $billingCompletionStatus = $billing_completion_status ?? 'ACTIVE';
    $isBillingCompleted      = $billingCompletionStatus === 'COMPLETED';
    $bcsMap = ['ACTIVE' => 'active', 'PARTIAL' => 'partial', 'COMPLETED' => 'completed'];
    $bcsCls = $bcsMap[$billingCompletionStatus] ?? 'active';
    // Display-only: once billing is manually marked complete, Remaining To
    // Bill reads as 0 on this page regardless of unbilled contract value.
    // remaining_billable_value itself (financial_summary) is never changed.
    if ($isBillingCompleted) {
        $csRemaining = 0.0;
    }

    $projectCostTillDate = $total_purchases + $total_expenses;
    $customerPaid        = (float) ($financial_summary['total_paid'] ?? 0);
?>

<!-- Release 2.2C (Task 1): Project Header — one compact single-line summary
     bar (reduced from 2.2B's two-line version) with exactly three actions:
     Back, Edit Project, Project Statement. -->
<div class="project-header-bar mb-3">
    <div class="project-header-left">
        <div class="project-header-main">
            <span class="project-header-name"><i class="bi bi-kanban me-2"></i><?= esc($project['name']) ?></span>
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
        <a href="<?= base_url('projects') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
        <a href="<?= base_url('projects/edit/' . $project['id']) ?>" class="btn-edit"><i class="bi bi-pencil"></i> Edit Project</a>
        <a href="<?= base_url('projects/statement/' . $project['id']) ?>" class="btn-cancel"><i class="bi bi-file-earmark-bar-graph"></i> Project Statement</a>
        <?php if (!$isBillingCompleted): ?>
        <form method="post" action="<?= base_url('projects/mark-billing-complete/' . $project['id']) ?>"
              onsubmit="return confirm('This project will be marked as Billing Completed. No more sales invoices should be created for this project.');" style="display:inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn-cancel"><i class="bi bi-check2-circle"></i> Mark Billing Complete</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Release 2.2B (Section 2): Project Health Dashboard — exactly 6 KPI cards,
     2 rows x 3 columns. -->
<div class="row g-12 mb-3 project-kpi-row">
    <div class="col-md-4 col-6">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-file-earmark-text"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($csValue, 2) ?></div>
                <div class="kpi-label">Contract Value</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-layers"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($projectCostTillDate, 2) ?></div>
                <div class="kpi-label">Project Cost Till Date</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-wallet2"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($csRemaining, 2) ?></div>
                <div class="kpi-label">Remaining Balance</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-cart"></i></div>
            <div>
                <div class="kpi-value"><?= count($purchases) ?></div>
                <div class="kpi-label">Purchase Count</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-receipt"></i></div>
            <div>
                <div class="kpi-value"><?= count($sales) ?></div>
                <div class="kpi-label">Sales Invoice Count</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="kpi-value"><?= $total_payments_count ?></div>
                <div class="kpi-label">Payment Count</div>
            </div>
        </div>
    </div>
</div>

<!-- Release 2.2B (Section 3): Billing Overview — one progress bar only, plus
     4 compact billing metrics and 2 compact payment cards. Invoice Collection
     Progress bar, Invoice Count Summary, old Billing Progress strip and
     Project Progress Summary strip are all removed. -->
<div class="card-custom mb-3">
    <div class="card-custom-header"><i class="bi bi-graph-up-arrow me-2"></i>Billing Overview</div>
    <div class="card-custom-body billing-overview-body">

        <div class="mb-2">
            <div class="progress" style="height:6px;background:#e2e8f0;border-radius:999px;overflow:hidden">
                <div class="progress-bar" role="progressbar"
                     style="width:<?= max(0, min(100, $csPercent)) ?>%;background:#2563eb"></div>
            </div>
            <div class="billing-strip-figures">
                <span><span class="billing-strip-label">Contract Value</span> ₹<?= number_format($csValue, 2) ?></span>
                <span class="billing-strip-sep">•</span>
                <span><span class="billing-strip-label">Total Invoiced</span> ₹<?= number_format($csBilled, 2) ?></span>
                <span class="billing-strip-sep">•</span>
                <span><span class="billing-strip-label">Remaining To Bill</span> ₹<?= number_format($csRemaining, 2) ?></span>
                <span class="billing-strip-pct ms-auto"><?= number_format($csPercent, 0) ?>% Billed</span>
            </div>
        </div>

        <div class="row g-12">
            <div class="col-md-6">
                <div class="billing-mini-card billing-mini-green">
                    <div class="billing-mini-icon"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="billing-mini-value"><?= number_format($customerPaid, 2) ?></div>
                        <div class="billing-mini-label">Customer Payments Received</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="billing-mini-card billing-mini-blue">
                    <div class="billing-mini-icon"><i class="bi bi-wallet2"></i></div>
                    <div>
                        <div class="billing-mini-value"><?= number_format($csRemaining, 2) ?></div>
                        <div class="billing-mini-label">Remaining Balance</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Release 2.2A (Phase 4): Project Information — compact identity card,
     kept per the final approved spec (not part of the "historical financial
     detail" being removed). Same $project/$payment_status already loaded by
     Projects::view(); no new query. -->
<div class="card-custom mb-3">
    <div class="card-custom-header"><i class="bi bi-info-circle me-2"></i>Project Information</div>
    <div class="card-custom-body project-info-body">
        <div class="project-info-grid">
            <div class="project-info-cell">
                <div class="project-info-label">Customer</div>
                <div class="project-info-value"><?= esc($project['customer_name'] ?: '-') ?></div>
            </div>
            <div class="project-info-cell">
                <div class="project-info-label">Start Date</div>
                <div class="project-info-value"><?= esc($project['start_date']) ?></div>
            </div>
            <div class="project-info-cell">
                <div class="project-info-label">Expected End Date</div>
                <div class="project-info-value"><?= $project['end_date'] ? esc($project['end_date']) : '-' ?></div>
            </div>
            <div class="project-info-cell">
                <div class="project-info-label">Work Status</div>
                <div><span class="badge-status badge-<?= strtolower($project['status']) ?>"><?= str_replace('_', ' ', $project['status']) ?></span></div>
            </div>
            <div class="project-info-cell">
                <div class="project-info-label">Billing Status</div>
                <div><span class="badge-status badge-<?= $bcsCls ?>"><?= $billingCompletionStatus ?></span></div>
            </div>
        </div>
        <?php if (!empty($project['description'])): ?>
        <div class="project-info-notes"><?= esc($project['description']) ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- Release 2.2A (Phase 5): Recent Sales Invoices — top 5 only, from the same
     $sales array already loaded (ORDER BY sale_date DESC), no new query. -->
<div class="card-custom mb-3">
    <div class="card-custom-header"><i class="bi bi-receipt me-2"></i>Recent Sales Invoices</div>
    <div class="table-responsive">
        <table class="table-custom recent-sales-table">
            <thead>
                <tr><th>Invoice</th><th>Date</th><th class="text-end">Amount</th><th class="text-end">Pending</th><th>Status</th><th class="text-center">Action</th></tr>
            </thead>
            <tbody>
                <?php $recentSales = array_slice($sales, 0, 5); ?>
                <?php if (empty($recentSales)): ?>
                <tr><td colspan="6" class="text-center text-muted">No sales invoices yet</td></tr>
                <?php else: ?>
                <?php foreach ($recentSales as $s): ?>
                <tr>
                    <td><a href="<?= base_url('sales/view/' . $s['id']) ?>"><?= esc($s['invoice_no'] ?: '#' . $s['id']) ?></a></td>
                    <td><?= esc($s['sale_date']) ?></td>
                    <td class="text-end"><?= number_format($s['total_amount'], 2) ?></td>
                    <td class="text-end recent-sales-pending"><?= number_format((float) $s['balance_amount'], 2) ?></td>
                    <td><span class="badge-status badge-<?= strtolower($s['status']) ?>"><?= esc($s['status']) ?></span></td>
                    <td class="text-center"><a href="<?= base_url('sales/view/' . $s['id']) ?>" class="recent-sales-view-link">View Invoice</a></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($sales) > 5): ?>
    <div class="recent-sales-footer">
        <a href="<?= base_url('projects/statement/' . $project['id']) ?>">View Complete Billing History &rarr; Project Statement</a>
    </div>
    <?php endif; ?>
</div>

<!-- Release 2.2B (Section 6): Quick Actions — exactly 5 compact chips, links
     only, no tables. Edit is dropped here since it already lives in the
     header. -->
<div class="card-custom mb-3">
    <div class="card-custom-header"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</div>
    <div class="card-custom-body quick-actions-row">
        <?php if ($isBillingCompleted): ?>
        <span class="action-chip action-chip-gray" style="opacity:.55;cursor:not-allowed" title="Billing marked complete — no more sales invoices for this project"><i class="bi bi-receipt"></i> Add Sales Invoice</span>
        <?php else: ?>
        <a href="<?= base_url('sales/create') ?>" class="action-chip action-chip-blue"><i class="bi bi-receipt"></i> Add Sales Invoice</a>
        <?php endif; ?>
        <a href="<?= base_url('purchases/create') ?>" class="action-chip action-chip-green"><i class="bi bi-cart"></i> Add Purchase</a>
        <a href="<?= base_url('expenses/create') ?>" class="action-chip action-chip-orange"><i class="bi bi-wallet2"></i> Add Expense</a>
        <a href="<?= base_url('payments/create') ?>" class="action-chip action-chip-purple"><i class="bi bi-cash-coin"></i> Record Payment</a>
        <a href="<?= base_url('projects/statement/' . $project['id']) ?>" class="action-chip action-chip-gray"><i class="bi bi-file-earmark-bar-graph"></i> Project Statement</a>
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
.project-kpi-row .kpi-card { padding: 8px 10px; min-height: 68px; border-width: 1px; gap: 6px; text-align: center; justify-content: center; }
.project-kpi-row .kpi-icon { width: 28px; height: 28px; font-size: 14px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.project-kpi-row .kpi-value { font-size: 20px; font-weight: 700; text-align: center; }
.project-kpi-row .kpi-label { font-size: 11px; color: #64748b; text-align: center; }
.billing-overview-body { padding: 8px 12px; }
.billing-strip-pct { font-size: 0.78rem; font-weight: 700; color: #2563eb; white-space: nowrap; text-align: right; }
.billing-strip-figures { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 0.76rem; color: #1e293b; margin-top: 4px; }
.billing-strip-label { color: #64748b; text-transform: uppercase; letter-spacing: .02em; font-size: 0.66rem; margin-right: 3px; }
.billing-strip-sep { color: #cbd5e1; }
.billing-mini-card { display: flex; align-items: center; gap: 10px; padding: 7px 12px; min-height: 52px; border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0; }
.billing-mini-icon { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; }
.billing-mini-green .billing-mini-icon { background: #dcfce7; color: #15803d; }
.billing-mini-orange .billing-mini-icon { background: #ffedd5; color: #c2410c; }
.billing-mini-blue .billing-mini-icon { background: #eff6ff; color: #2563eb; }
.billing-mini-value { font-size: 1rem; font-weight: 700; color: #1e293b; }
.billing-mini-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; letter-spacing: .02em; }
.recent-sales-table th, .recent-sales-table td { padding: 5px 10px; font-size: 0.78rem; }
.recent-sales-pending { color: #c2410c; font-weight: 600; }
.recent-sales-view-link { color: #2563eb; font-weight: 600; font-size: 0.74rem; text-decoration: none; white-space: nowrap; }
.recent-sales-view-link:hover { text-decoration: underline; }
.quick-actions-row { display: flex; gap: 8px; flex-wrap: wrap; }
.action-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; height: 30px; border-radius: 999px; font-size: 0.76rem; font-weight: 600; text-decoration: none; border: 1px solid transparent; }
.action-chip-blue { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
.action-chip-blue:hover { background: #dbeafe; }
.action-chip-green { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
.action-chip-green:hover { background: #bbf7d0; }
.action-chip-orange { background: #ffedd5; color: #c2410c; border-color: #fed7aa; }
.action-chip-orange:hover { background: #fed7aa; }
.action-chip-purple { background: #f3e8ff; color: #7e22ce; border-color: #e9d5ff; }
.action-chip-purple:hover { background: #e9d5ff; }
.action-chip-gray { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
.action-chip-gray:hover { background: #e2e8f0; }
.project-info-body { padding: 6px 12px; }
.project-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; }
.project-info-cell { padding: 4px 0; border-bottom: 1px solid #f1f5f9; }
.project-info-label { font-size: 0.68rem; color: #64748b; text-transform: uppercase; letter-spacing: .02em; margin-bottom: 2px; }
.project-info-value { font-size: 0.85rem; font-weight: 600; color: #1e293b; }
.project-info-notes { margin-top: 6px; padding-top: 6px; border-top: 1px solid #f1f5f9; font-size: 0.8rem; color: #64748b; }
@media (max-width: 576px) { .project-info-grid { grid-template-columns: 1fr; } }
.recent-sales-footer { padding: 8px 14px; border-top: 1px solid #f1f5f9; text-align: right; font-size: 0.78rem; }
.recent-sales-footer a { color: #2563eb; font-weight: 600; text-decoration: none; }
.recent-sales-footer a:hover { text-decoration: underline; }
</style>

<?= $this->endSection() ?>
