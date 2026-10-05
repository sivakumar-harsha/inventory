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

    // Release 4.9.0J (bug fix): this "Remaining Balance / Over Billed / Fully
    // Billed" figure is a pure Contract vs Invoiced concept — how much of
    // total_project_value has not yet been raised as an invoice — and must
    // never be reduced by money collected through a different channel.
    // remaining_balance_display (used here through 4.9.0I) also nets out
    // Project Cash Receipts (ADVANCE/DIRECT_INCOME, an independent ledger —
    // see ProjectModel::getFinancialSummary()), which is a customer
    // collection concept, not a billing one; a project with direct income
    // but no invoice for it would show a smaller "Remaining Balance" (or
    // even "Over Billed") than the contract actually justifies.
    // remaining_billable_value is exactly total_project_value - total_billed
    // (no advance, no cash receipts) — already the field Projects::index()'s
    // own Remaining Balance column and Dashboard's "Top Pending Projects"
    // widget use — so this just makes the Statement/View cards agree with
    // them instead of applying a second, different formula.
    // $csRemaining = remaining_billable_value (Contract - Advance Received - Invoiced); 4.9.0EC: card shows it clamped at 0.
    $csRemaining = (float) ($financial_summary['remaining_billable_value'] ?? 0);

    // Release 4.6.5: Total Customer Paid = same cash_received_combined figure
    // as the Cash Received card above (both now include invoice payments).
    // Release 4.8.6A-1: Total Customer Paid = Project Advance + Customer Payment vouchers
    // (getFinancialSummary()'s total_customer_paid). Advance Allocation is never added.
    $csTotalCustomerPaid = (float) ($financial_summary['total_customer_paid'] ?? $financial_summary['cash_received_combined'] ?? 0);
    $csPaidAdvance       = (float) ($financial_summary['customer_paid_advance'] ?? 0);
    $csPaidInvoice       = (float) ($financial_summary['total_paid'] ?? 0);

    $billingCompletionStatus = $billing_completion_status ?? 'ACTIVE';
    // Release 4.9.0K: OVER_BILLED (total_billed > contract value) — see
    // ProjectModel::getBillingCompletionStatus().
    $bcsMap = ['ACTIVE' => 'active', 'PARTIAL' => 'partial', 'COMPLETED' => 'completed', 'FULLY_BILLED' => 'completed', 'OVER_BILLED' => 'over-billed'];
    $bcsCls = $bcsMap[$billingCompletionStatus] ?? 'active';
    $billingCompletionStatusLabel = str_replace('_', ' ', $billingCompletionStatus);

    // Release 4.8.6A-2 Final UI Constitution Patch (presentation only): ONE dynamic KPI card, chosen
    // from two independent model figures (unused_customer_advance / invoice_outstanding) plus whether
    // any invoice exists. Nothing is recalculated here; the same figures also feed the read-only
    // Customer Advance Summary below the KPI row.
    $csAdvReceived = (float) ($financial_summary['advance_amount'] ?? 0);
    $csAdvApplied  = (float) ($financial_summary['total_advance_applied'] ?? 0);
    $csUnused      = (float) ($financial_summary['unused_customer_advance'] ?? 0);
    // Release 4.9.0U: branch decision stays on invoice_outstanding (TRUE,
    // invoice-only figure) — see app/Views/projects/view.php's identical
    // comment. Only the displayed VALUE reads the CUSTOMER_PROJECT_CASH-
    // aware project_outstanding_collection.
    $csOutstanding        = (float) ($financial_summary['invoice_outstanding'] ?? 0);
    $csOutstandingDisplay = (float) ($financial_summary['project_outstanding_collection'] ?? $csOutstanding);
    $csHasInvoices = $csBilled > 0.004;
    $dynSubtitle   = '';
    if ($csHasInvoices && $csOutstanding > 0.004) {
        $dynKpiCls = 'kpi-orange'; $dynIcon = 'bi-hourglass-split';
        $dynLabel  = 'Outstanding Collection';
        $dynValue  = '₹' . number_format($csOutstandingDisplay, 2) . ' Dr';
        if ($csUnused > 0.004) {
            $dynSubtitle = 'Unused Advance Available : ₹' . number_format($csUnused, 2);
        }
    } elseif ($csUnused > 0.004) {
        $dynKpiCls = 'kpi-green'; $dynIcon = 'bi-award';
        $dynLabel  = 'Customer Advance Balance';
        $dynValue  = '₹' . number_format($csUnused, 2) . ' Cr';
        $dynSubtitle = $csHasInvoices
            ? 'Invoice fully settled. Advance available for future invoices.'
            : 'Unused customer advance available.';
    } else {
        $dynKpiCls = 'kpi-green'; $dynIcon = 'bi-check-circle';
        $dynLabel  = 'Settled';
        $dynValue  = '₹0.00';
    }
?>

<!-- Release 3.0 (Phase B): compact header, identical pattern to Project View. -->
<div class="project-header-bar mb-3">
    <div class="project-header-left">
        <div class="project-header-main">
            <span class="project-header-name"><i class="bi bi-file-earmark-text me-2"></i><?= esc($project['name']) ?></span>
            <span class="project-subheader-sep">•</span>
            <span><i class="bi bi-person me-1"></i><?= esc($project['customer_name'] ?: 'No Customer') ?></span>
            <span class="badge-status badge-<?= strtolower($project['status']) ?>" title="Work Status">Work: <?= str_replace('_', ' ', $project['status']) ?></span>
            <span class="badge-status badge-<?= $bcsCls ?>" title="Billing Status">Billing: <?= $billingCompletionStatusLabel ?></span>
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

<!-- Release 4.6.5.7 (Phase A/B): Project Financial Summary — exactly 8 compact
     KPI cards, 2 rows x 4 columns (Cash Received card removed — the same
     figure is already inside Total Customer Paid; getFinancialSummary()
     itself is unchanged, only this card was removed from the UI). -->
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
            // Release 4.9.0EB: one state - Over Billed or Remaining Balance.
            if ($csRemaining < -0.004) {
                $remKpiCls = 'kpi-red'; $remIcon = 'bi-exclamation-triangle';
                $remValue  = number_format(abs($csRemaining), 2);
                $remLabel  = 'Over Billed';
            } elseif ($csRemaining > 0.004) {
                $remKpiCls = 'kpi-orange'; $remIcon = 'bi-wallet2';
                $remValue  = number_format($csRemaining, 2);
                $remLabel  = 'Remaining Balance';
            } else {
                $remKpiCls = 'kpi-green'; $remIcon = 'bi-check-circle';
                $remValue  = '0.00';
                $remLabel  = 'Remaining Balance';
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
                <div class="kpi-value"><?= number_format($csTotalCustomerPaid, 2) ?></div>
                <div class="kpi-label">Total Customer Paid</div>
                <div class="kpi-breakdown">Advance: ₹<?= number_format($csPaidAdvance, 2) ?> · Invoice Payments: ₹<?= number_format($csPaidInvoice, 2) ?></div>
            </div>
        </div>
    </div>
    <!-- Single dynamic card: Customer Advance Balance / Outstanding Collection / Settled. -->
    <div class="col-md-3 col-6">
        <div class="kpi-card <?= $dynKpiCls ?>"<?= $dynSubtitle !== '' ? ' title="' . esc($dynSubtitle) . '"' : '' ?>>
            <div class="kpi-icon"><i class="bi <?= $dynIcon ?>"></i></div>
            <div>
                <div class="kpi-value kpi-value-fit"><?= $dynValue ?></div>
                <div class="kpi-label kpi-label-tight"><?= esc($dynLabel) ?></div>
                <?php if ($dynSubtitle !== ''): ?>
                <div class="kpi-breakdown kpi-subtitle"><?= esc($dynSubtitle) ?></div>
                <?php endif; ?>
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

<!-- Release 4.8.6A-2 Final UI Patch: read-only Customer Advance Summary (not a KPI card). Same
     figures as the model's advance_amount / total_advance_applied / unused_customer_advance. -->
<div class="card-custom mb-3">
    <div class="card-custom-header"><i class="bi bi-piggy-bank me-2"></i>Customer Advance Summary</div>
    <div class="card-custom-body adv-summary-body">
        <div class="adv-summary-row"><span class="adv-summary-label">Advance Received</span><span class="adv-summary-value">₹<?= number_format($csAdvReceived, 2) ?></span></div>
        <div class="adv-summary-row"><span class="adv-summary-label">Advance Applied to Invoices</span><span class="adv-summary-value">₹<?= number_format($csAdvApplied, 2) ?></span></div>
        <div class="adv-summary-row adv-summary-total"><span class="adv-summary-label">Unused Advance Available</span><span class="adv-summary-value">₹<?= number_format($csUnused, 2) ?></span></div>
    </div>
</div>

<?php
    // Release 4.6.5.7 (UI only): Direct Project Income data, unchanged from
    // Release 4.6.5.5 — $directIncomeReceipts is still the same ASC-ordered
    // read from Projects::_buildStatementData(). Latest Receipt Date is
    // simply the last element of that already-ordered list — a display
    // pick, not a new calculation.
    $directIncomeReceipts = $direct_income_receipts ?? [];
    $directIncomeCount    = count($directIncomeReceipts);
    $latestReceiptDate    = $directIncomeCount > 0 ? end($directIncomeReceipts)['receipt_date'] : null;

    // Release 4.9.0Y (2nd): this card's "Total Direct Income" must equal the
    // sum of the receipts actually shown in THIS section's history — which,
    // since 4.9.0X, includes CUSTOMER_PROJECT_CASH rows alongside genuine
    // DIRECT_INCOME rows. That is deliberately NOT the same figure as
    // financial_summary['total_direct_income'] (still DIRECT_INCOME-only),
    // which stays untouched because it also feeds Net Profit / P&L
    // (Projects::_buildStatementData()'s $projectRevenue, Reports.php,
    // Dashboard.php) — CUSTOMER_PROJECT_CASH is customer cash already
    // counted in Total Customer Paid, not accounting revenue, so it must
    // never enter those calculations. This is a section-local display sum
    // only, scoped to this card and its table footer below.
    $totalDirectIncome = array_sum(array_column($directIncomeReceipts, 'amount'));

    // Release 4.9.0X: the history list above now also carries
    // CUSTOMER_PROJECT_CASH rows (Projects::_buildStatementData()) purely so
    // the user can see customer cash collected through this screen.
    // Release 4.9.0Y (2nd): $totalDirectIncome above is now this section's
    // own sum of that list (see comment further up), not financial_summary's
    // total_direct_income — see that comment for why the two are allowed to
    // differ.
    $receiptTypeLabels = [
        'DIRECT_INCOME'          => 'Direct Income',
        'CUSTOMER_PROJECT_CASH'  => 'Unallocated Project Receipt',
    ];
?>

<!-- Release 4.6.5.7 (Phase C): compact Direct Project Income summary card —
     replaces the old full-width table in this position. The table itself
     moves into the collapsible history section below (Phase D), unchanged. -->
<div class="card-custom mb-3">
    <div class="card-custom-header"><i class="bi bi-cash-coin me-2"></i>Direct Project Income</div>
    <div class="card-custom-body di-compact-body">
        <div class="di-mini-chip">
            <span class="di-mini-label">Total Direct Income</span>
            <span class="di-mini-value">₹<?= number_format($totalDirectIncome, 2) ?></span>
        </div>
        <div class="di-mini-chip">
            <span class="di-mini-label">Number of Receipts</span>
            <span class="di-mini-value"><?= $directIncomeCount ?></span>
        </div>
        <div class="di-mini-chip">
            <span class="di-mini-label">Latest Receipt</span>
            <span class="di-mini-value"><?= $latestReceiptDate ? date('d M Y', strtotime($latestReceiptDate)) : '—' ?></span>
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
            <?php if ($csRemaining < -0.004): ?>
            <span class="billing-strip-overbilled"><span class="billing-strip-label">Over Billed</span> ₹<?= number_format(abs($csRemaining), 2) ?></span>
            <?php else: ?>
            <span><span class="billing-strip-label">Remaining Balance</span> ₹<?= number_format(max(0, $csRemaining), 2) ?></span>
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

<!-- Release 4.6.5.7 (Phase D): Direct Project Income History — the same
     read-only table + total row from Release 4.6.5.5, now tucked into a
     collapsed-by-default section so it doesn't take first-screen space.
     Bootstrap's native collapse (bootstrap.bundle.min.js, already loaded in
     layouts/main.php) — no new JS. -->
<div class="card-custom mb-3">
    <div class="card-custom-header di-history-header" data-bs-toggle="collapse" data-bs-target="#directIncomeHistoryCollapse" role="button" aria-expanded="false" aria-controls="directIncomeHistoryCollapse">
        <i class="bi bi-clock-history me-2"></i>Direct Project Income History (<?= $directIncomeCount ?> Receipt<?= $directIncomeCount === 1 ? '' : 's' ?>)
        <i class="bi bi-chevron-down di-history-caret ms-2"></i>
    </div>
    <div class="collapse" id="directIncomeHistoryCollapse">
        <div class="card-custom-body">
            <?php if (empty($directIncomeReceipts)): ?>
            <div class="text-center text-muted" style="padding:14px 0">No Direct Project Income recorded.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table-custom" id="directIncomeTable">
                    <thead>
                        <tr>
                            <th class="sno-col">S.No.</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Receipt No</th>
                            <th>Reference Number</th>
                            <th>Payment Method</th>
                            <th>Notes</th>
                            <th style="text-align:right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($directIncomeReceipts as $r): ?>
                        <?php $rType = $receiptTypeLabels[$r['receipt_type'] ?? 'DIRECT_INCOME'] ?? 'Direct Income'; ?>
                        <tr>
                            <td class="sno-col sno-auto" data-label="S.No."></td>
                            <td><?= esc($r['receipt_date']) ?></td>
                            <td>
                                <?php if (($r['receipt_type'] ?? '') === 'CUSTOMER_PROJECT_CASH'): ?>
                                <span class="event-badge event-badge-payment"><?= esc($rType) ?></span>
                                <?php else: ?>
                                <span class="event-badge event-badge-income"><?= esc($rType) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($r['receipt_no']) ?></td>
                            <td><?= esc($r['reference'] ?: '—') ?></td>
                            <td><?= pm_badge($r["payment_method"] ?? "", "Not recorded") ?></td>
                            <td><?= esc($r['notes'] ?: '—') ?></td>
                            <td style="text-align:right"><?= number_format($r['amount'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7" style="text-align:right;font-weight:700">Total Direct Income</td>
                            <td style="text-align:right;font-weight:700"><?= number_format($totalDirectIncome, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
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
                        <th class="sno-col">S.No.</th>
                        <th>Date</th>
                        <th>Event</th>
                        <th>Reference</th>
                        <th>Method</th>
                        <th style="text-align:right">Amount</th>
                        <th style="text-align:right">Running Collection Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($timeline)): ?>
                    <tr><td colspan="7" class="text-center text-muted">No timeline events for this project</td></tr>
                    <?php else: ?>
                    <?php
                        $typeLabels = [
                            'Project Advance' => 'Customer Advance Received',
                            'Sales Invoice'    => 'Billing Raised to Customer',
                            'Invoice Payment'  => 'Payment Collected from Customer',
                            'Advance Receipt' => 'Advance Received',
                            'Direct Project Income' => 'Direct Project Income',
                            // Release 4.9.0X: explicit entry (was relying on
                            // the $ev['type'] fallback, which already read
                            // "Unallocated Project Receipt" (was "Customer Project Cash") — this just adds the
                            // matching icon/badge/tooltip below).
                            'Unallocated Project Receipt' => 'Unallocated Project Receipt',
                            'Purchase'         => 'Purchased Materials for Project',
                            'Expense'          => 'Project Expense Recorded',
                        ];
                        $typeIcons = [
                            'Project Advance' => 'bi-piggy-bank',
                            'Sales Invoice'    => 'bi-receipt-cutoff',
                            'Invoice Payment'  => 'bi-cash-coin',
                            'Advance Receipt' => 'bi-piggy-bank',
                            'Direct Project Income' => 'bi-cash-coin',
                            'Unallocated Project Receipt' => 'bi-cash-coin',
                            'Purchase'         => 'bi-cart',
                            'Expense'          => 'bi-credit-card',
                        ];
                        $typeBadgeCls = [
                            'Project Advance' => 'event-badge-advance',
                            'Sales Invoice'    => 'event-badge-billing',
                            'Invoice Payment'  => 'event-badge-payment',
                            // Release 4.6.5: Advance Receipt keeps the same
                            // badge as before (green, same as Invoice Payment)
                            // — running balance math for it is unchanged.
                            // Direct Project Income gets its own distinct
                            // badge so it reads as revenue, not a collection.
                            'Advance Receipt' => 'event-badge-payment',
                            'Direct Project Income' => 'event-badge-income',
                            // Unallocated Project Receipt is a genuine customer
                            // collection (reduces running balance like
                            // Invoice Payment/Advance Receipt), so it shares
                            // their badge — never the Direct Income green.
                            'Unallocated Project Receipt' => 'event-badge-payment',
                            'Purchase'         => 'event-badge-purchase',
                            'Expense'          => 'event-badge-expense',
                        ];
                        $typeTooltips = [
                            'Advance Receipt' => 'Money received without invoice allocation — held against a future invoice (see Method for how it was paid).',
                            'Direct Project Income' => 'Cash received with no invoice ever raised — recognized as revenue immediately.',
                            'Unallocated Project Receipt' => 'Customer money received for this project without selecting an invoice (any payment method — see Method). Reduces Outstanding Collection; not counted as Direct Income.',
                        ];
                    ?>
                    <?php foreach ($timeline as $ev): ?>
                    <?php
                        $typeIcon    = $typeIcons[$ev['type']] ?? 'bi-dot';
                        $typeLabel   = $typeLabels[$ev['type']] ?? $ev['type'];
                        $badgeCls    = $typeBadgeCls[$ev['type']] ?? 'event-badge-payment';
                        $typeTooltip = $typeTooltips[$ev['type']] ?? '';
                    ?>
                    <tr data-date="<?= esc($ev['date']) ?>" data-category="<?= esc($ev['category']) ?>" data-type="<?= esc($ev['type']) ?>">
                        <td class="sno-col sno-auto" data-label="S.No."></td>
                        <td><?= esc($ev['date']) ?></td>
                        <td><span class="event-badge <?= $badgeCls ?>" <?= $typeTooltip ? 'title="' . esc($typeTooltip) . '"' : '' ?>><i class="bi <?= $typeIcon ?>"></i><?= esc($typeLabel) ?></span></td>
                        <td><?= esc($ev['reference']) ?></td>
                        <td><?= pm_badge($ev["method"] ?? "", in_array($ev["category"], ["Payment", "Income"], true) || $ev["type"] === "Expense" ? "Not recorded" : "—") ?></td>
                        <td style="text-align:right">
                            <?= $ev['amount'] !== null ? number_format($ev['amount'], 2) : '—' ?>
                        </td>
                        <td style="text-align:right" class="running-balance-col">
                            <?php if ($ev['category'] === 'Cost'): ?>
                                <span class="balance-pill balance-costonly">Project Cost Only</span>
                            <?php elseif ($ev['category'] === 'Income'): ?>
                                <span class="balance-pill balance-income">Revenue Entry</span>
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
.statement-kpi-row .kpi-breakdown { font-size: 10px; line-height: 1.15; color: #64748b; text-align: center; }
/* "Customer Advance Balance" is longer than the old label and wraps to two lines on mid-width screens; tighter leading keeps it inside the fixed-height card. */
.statement-kpi-row .kpi-label-tight { line-height: 1.1; }
/* The "₹… Cr/Dr" value is wider than the old plain number; keep it on one line on mid-width screens. */
.statement-kpi-row .kpi-value-fit { white-space: nowrap; }
@media (max-width: 1199.98px) { .statement-kpi-row .kpi-value-fit { font-size: 15px; } }
/* 992-1199px: four cards per row, so the fixed-height card only has room for a tighter breakdown; the dynamic card's subtitle moves to its tooltip. */
@media (min-width: 992px) and (max-width: 1199.98px) { .statement-kpi-row .kpi-card { padding-top: 2px; padding-bottom: 2px; } .statement-kpi-row .kpi-breakdown { font-size: 9px; line-height: 1.05; } .statement-kpi-row .kpi-value { line-height: 1.15; } }
@media (max-width: 1199.98px) { .statement-kpi-row .kpi-breakdown.kpi-subtitle { display: none; } }
/* Below 992px the cards are too narrow for the extra breakdown line (the original cards already overflow there), so it is left to the value + label only. */
@media (max-width: 991.98px) { .statement-kpi-row .kpi-breakdown:not(.kpi-subtitle) { display: none; } }
.statement-kpi-row .kpi-label { font-size: 11px; color: #64748b; text-align: center; }

/* Customer Advance Summary: three compact read-only rows. */
.adv-summary-body { padding: 6px 14px; }
.adv-summary-row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 4px 0; border-bottom: 1px solid #f1f5f9; font-size: 0.8rem; max-width: 460px; }
.adv-summary-row:last-child { border-bottom: 0; }
.adv-summary-label { color: #64748b; }
.adv-summary-value { color: #1e293b; font-weight: 600; font-variant-numeric: tabular-nums; }
.adv-summary-total .adv-summary-label { color: #1e293b; font-weight: 600; }
.adv-summary-total .adv-summary-value { color: #15803d; font-weight: 700; }

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
/* Release 4.6.5.5: Direct Project Income badge switched to green (was
   cyan) per this release's requirement. */
.event-badge-income   { background: #dcfce7; color: #15803d; }

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
.balance-income      { background: #dcfce7; color: #15803d; }

/* Release 4.6.5.7 (Phase C): compact Direct Project Income summary — same
   mini-card language as Billing Overview's figures strip, just as chips
   instead of an inline sentence. */
.di-compact-body { display: flex; gap: 10px; flex-wrap: wrap; padding: 10px; }
.di-mini-chip {
    flex: 1;
    min-width: 150px;
    display: flex;
    flex-direction: column;
    gap: 2px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    padding: 8px 12px;
}
.di-mini-label { font-size: 0.66rem; color: #64748b; text-transform: uppercase; letter-spacing: .02em; }
.di-mini-value { font-size: 1.05rem; font-weight: 700; color: #15803d; }

/* Release 4.6.5.7 (Phase D): collapsible Direct Project Income History —
   header toggles Bootstrap's native .collapse, caret rotates on expand. */
.di-history-header { cursor: pointer; display: flex; align-items: center; user-select: none; }
.di-history-caret { transition: transform 0.2s ease; margin-left: auto; }
.di-history-header[aria-expanded="true"] .di-history-caret { transform: rotate(180deg); }
#directIncomeTable tfoot td { background: #f0fdf4; border-top: 2px solid #bbf7d0; }

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
