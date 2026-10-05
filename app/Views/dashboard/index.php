<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
/**
 * Dashboard (approved light design). Presentation only: every figure arrives from
 * Dashboard::index() already calculated from its authoritative source (Cash Book closing,
 * bank balances, Monthly Statement, P&L basis, ProjectModel). The only arithmetic here is
 * display proportions for the little bars.
 */
$money = static fn ($n) => number_format((float) $n, 2);

// Display proportions only (never shown as financial figures).
$pct = static fn (float $part, float $whole): float => $whole > 0.004 ? max(0, min(100, round($part / $whole * 100, 1))) : 0.0;

$moneyTotal = (float) $money_in + (float) $money_out;
$inPct      = $pct((float) $money_in, $moneyTotal);
$outPct     = $moneyTotal > 0.004 ? round(100 - $inPct, 1) : 0.0;

// Display only: greeting by server hour, and the month's net movement (Money In - Money Out as already supplied).
$hour      = (int) date('G');
$greeting  = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$netMove   = (float) $money_in - (float) $money_out;

$overdrawn = ! empty($bank_available) ? count($bank_low_balance) : 0;
$emiLate   = ! empty($loan_available) ? (int) $loan_overdue_count : 0;
$svcOpen   = ! empty($service_available) ? (int) $service_pending_invoices : 0;
$plural    = static fn (int $n, string $one, string $many) => $n . ' ' . ($n === 1 ? $one : $many);
?>
<link rel="stylesheet" href="<?= base_url('assets/css/dashboard.css') ?>">

<div class="dbx">
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="dbx-i-cash" viewBox="0 0 16 16"><rect x="1.5" y="4" width="13" height="8" rx="1.5"/><circle cx="8" cy="8" r="1.8"/></symbol>
  <symbol id="dbx-i-bank" viewBox="0 0 16 16"><path d="M2 6.5 8 3l6 3.5M3.5 7v5M6.5 7v5M9.5 7v5M12.5 7v5M2 13h12"/></symbol>
  <symbol id="dbx-i-in" viewBox="0 0 16 16"><path d="M8 3v8M4.5 8 8 11.5 11.5 8M3 13.5h10"/></symbol>
  <symbol id="dbx-i-out" viewBox="0 0 16 16"><path d="M8 13V5M4.5 8 8 4.5 11.5 8M3 2.5h10"/></symbol>
  <symbol id="dbx-i-plus" viewBox="0 0 16 16"><path d="M8 3v10M3 8h10"/></symbol>
  <symbol id="dbx-i-pay" viewBox="0 0 16 16"><path d="M2.5 5h11M2.5 8h11M2.5 11h6"/></symbol>
  <symbol id="dbx-i-cart" viewBox="0 0 16 16"><path d="M2 3h2l1.5 7h6.5l1.5-5H5"/><circle cx="6.5" cy="13" r=".8"/><circle cx="11.5" cy="13" r=".8"/></symbol>
  <symbol id="dbx-i-wallet" viewBox="0 0 16 16"><rect x="2" y="4" width="12" height="9" rx="1.5"/><path d="M2 6.5h12M10.5 9.5h1.5"/></symbol>
  <symbol id="dbx-i-proj" viewBox="0 0 16 16"><rect x="2.5" y="3" width="11" height="10" rx="1.5"/><path d="M5.5 6.5h5M5.5 9.5h3"/></symbol>
</svg>

    <div class="dbx-head">
        <div>
            <h1>Dashboard</h1>
            <div class="dbx-sub"><b><?= $greeting ?></b> · Here's your business position for <?= esc(date('F Y')) ?>.</div>
        </div>
        <?php if ($overdrawn || $emiLate || $svcOpen): ?>
        <div class="dbx-pills" aria-label="Needs attention">
            <?php if ($overdrawn): ?>
            <a class="dbx-pill neg" href="<?= base_url('bank-accounts') ?>"><span class="dbx-dot"></span><b><?= $overdrawn ?></b> bank <?= $overdrawn === 1 ? 'account' : 'accounts' ?> overdrawn</a>
            <?php endif; ?>
            <?php if ($emiLate): ?>
            <a class="dbx-pill neg" href="<?= base_url('loan-reports/emi-due') ?>"><span class="dbx-dot"></span><b><?= $emiLate ?></b> <?= $emiLate === 1 ? 'EMI' : 'EMIs' ?> overdue</a>
            <?php endif; ?>
            <?php if ($svcOpen): ?>
            <a class="dbx-pill amb" href="<?= base_url('service-reports/outstanding') ?>"><span class="dbx-dot"></span><b><?= $svcOpen ?></b> service <?= $svcOpen === 1 ? 'invoice' : 'invoices' ?> pending</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- FINANCIAL POSITION: Cash = Cash Book closing, Bank = total bank balance, Money In/Out = this month's Monthly Statement (transfers excluded) -->
    <section class="dbx-sheet dbx-pos" aria-label="Financial position">
        <div class="dbx-pi cash">
            <div class="dbx-eyebrow"><span class="dbx-ico"><svg class="dbx-i"><use href="#dbx-i-cash"/></svg></span>Cash</div>
            <div class="dbx-val dbx-num<?= $cash_balance < 0 ? ' is-neg' : '' ?>"><s>₹</s><?= $money($cash_balance) ?></div>
            <div class="dbx-note">Cash Book closing balance</div>
        </div>
        <div class="dbx-pi bank">
            <div class="dbx-eyebrow"><span class="dbx-ico"><svg class="dbx-i"><use href="#dbx-i-bank"/></svg></span>Bank</div>
            <?php if (! empty($bank_available)): ?>
            <div class="dbx-val dbx-num<?= $bank_total_balance < 0 ? ' is-neg' : '' ?>"><s>₹</s><?= $money($bank_total_balance) ?></div>
            <div class="dbx-note">Total across all accounts</div>
            <?php else: ?>
            <div class="dbx-val">—</div>
            <div class="dbx-note">Bank data not available</div>
            <?php endif; ?>
        </div>
        <div class="dbx-pi in">
            <div class="dbx-eyebrow"><span class="dbx-ico"><svg class="dbx-i"><use href="#dbx-i-in"/></svg></span>Money In · <?= esc(date('M Y')) ?></div>
            <?php if (! empty($money_available)): ?>
            <div class="dbx-val dbx-num"><s>₹</s><?= $money($money_in) ?></div>
            <div class="dbx-note">Bank + cash receipts</div>
            <?php else: ?>
            <div class="dbx-val">—</div>
            <div class="dbx-note">Not available</div>
            <?php endif; ?>
        </div>
        <div class="dbx-pi out">
            <div class="dbx-eyebrow"><span class="dbx-ico"><svg class="dbx-i"><use href="#dbx-i-out"/></svg></span>Money Out · <?= esc(date('M Y')) ?></div>
            <?php if (! empty($money_available)): ?>
            <div class="dbx-val dbx-num"><s>₹</s><?= $money($money_out) ?></div>
            <div class="dbx-note">Bank + cash payments</div>
            <?php else: ?>
            <div class="dbx-val">—</div>
            <div class="dbx-note">Not available</div>
            <?php endif; ?>
        </div>
        <?php if (! empty($money_available) && $moneyTotal > 0.004): ?>
        <div class="dbx-ratio">
            <span class="dbx-net">Net movement <b class="dbx-num <?= $netMove < 0 ? 'neg' : 'pos' ?>"><?= $netMove < 0 ? '−' : '+' ?>₹ <?= $money(abs($netMove)) ?></b></span>
            <div class="dbx-meter" role="img" aria-label="Money in <?= $inPct ?> percent, money out <?= $outPct ?> percent"><i style="width:<?= $inPct ?>%;background:#7fc8a0"></i><i style="width:<?= $outPct ?>%;background:#eec77d"></i></div>
            <span>In <b><?= $inPct ?>%</b> · Out <b><?= $outPct ?>%</b> · transfers excluded</span>
        </div>
        <?php endif; ?>
    </section>

    <div class="dbx-cols">

        <!-- ================= main column ================= -->
        <div class="dbx-stack dbx-group">

            <!-- BUSINESS PERFORMANCE -->
            <?php $shareBase = max((float) $total_sales, (float) $total_purchases, (float) $total_expenses); ?>
            <section class="dbx-sheet" aria-label="Business performance">
                <div class="dbx-sech"><h2>Business performance</h2><a href="<?= base_url('reports/profit-loss') ?>">Profit &amp; Loss →</a></div>
                <div class="dbx-perf">
                    <div class="np<?= $total_net_profit < 0 ? ' is-neg' : '' ?>">
                        <div class="dbx-lbl">Net Profit</div>
                        <div class="dbx-val dbx-num">₹ <?= $money($total_net_profit) ?></div>
                        <div class="dbx-note">Same basis as the P&amp;L report</div>
                    </div>
                    <div class="m">
                        <div class="dbx-lbl">Total Sales</div>
                        <div class="dbx-val dbx-num">₹ <?= $money($total_sales) ?></div>
                        <div class="dbx-share"><i style="width:<?= $pct((float) $total_sales, $shareBase) ?>%"></i></div>
                    </div>
                    <div class="m">
                        <div class="dbx-lbl">Total Purchases</div>
                        <div class="dbx-val dbx-num">₹ <?= $money($total_purchases) ?></div>
                        <div class="dbx-share"><i style="width:<?= $pct((float) $total_purchases, $shareBase) ?>%"></i></div>
                    </div>
                    <div class="m">
                        <div class="dbx-lbl">Total Expenses</div>
                        <div class="dbx-val dbx-num">₹ <?= $money($total_expenses) ?></div>
                        <div class="dbx-note">Paid expenses</div>
                        <div class="dbx-share"><i style="width:<?= $pct((float) $total_expenses, $shareBase) ?>%"></i></div>
                    </div>
                </div>
            </section>

            <!-- COLLECTIONS & PROJECTS -->
            <?php $maxOutstanding = $top_pending_projects ? max(array_column($top_pending_projects, 'outstanding_collection')) : 0; ?>
            <section class="dbx-sheet" aria-label="Collections and projects">
                <div class="dbx-sech"><h2>Collections &amp; projects</h2><a href="<?= base_url('projects') ?>">All projects →</a></div>
                <div class="dbx-coll">
                    <div>
                        <div class="dbx-eyebrow">Outstanding Collection</div>
                        <div class="dbx-big dbx-num<?= $total_outstanding <= 0.004 ? ' is-zero' : '' ?>">₹ <?= $money($total_outstanding) ?></div>
                    </div>
                    <div class="dbx-status" aria-label="Project status">
                        <div class="dbx-st"><div class="n"><?= (int) $active_projects ?></div><div class="t"><span class="dbx-dot" style="color:#4f74e0"></span>Active</div></div>
                        <div class="dbx-st"><div class="n"><?= (int) $completed_projects ?></div><div class="t"><span class="dbx-dot" style="color:#4fae7f"></span>Completed</div></div>
                        <div class="dbx-st"><div class="n"><?= (int) $onhold_projects ?></div><div class="t"><span class="dbx-dot" style="color:#e0a23b"></span>On Hold</div></div>
                    </div>
                </div>
                <table class="dbx-table">
                    <colgroup><col><col style="width:25%"><col style="width:27%"></colgroup>
                    <thead><tr><th>Top pending projects</th><th class="dbx-r">Remaining Balance</th><th class="dbx-r">Outstanding Collection</th></tr></thead>
                    <tbody>
                        <?php if (empty($top_pending_projects)): ?>
                        <tr><td colspan="3" class="dbx-empty">No pending collections</td></tr>
                        <?php endif; ?>
                        <?php foreach ($top_pending_projects as $pp): ?>
                        <?php
                            // remaining_balance = MAX(0, Contract - Advance Received - Invoiced) and over_billed = the excess,
                            // both from ProjectModel::getFinancialSummary()['remaining_billable_value'] (one state, shown in one column).
                            $rbOver = (float) ($pp['over_billed'] ?? 0) > 0.004;
                            $rbText = $money($rbOver ? $pp['over_billed'] : $pp['remaining_balance']);
                        ?>
                        <tr>
                            <td><div class="dbx-pname"><?= esc($pp['project_name']) ?></div></td>
                            <td class="dbx-r dbx-num<?= $rbOver ? ' dbx-over' : '' ?>">₹ <?= $rbText ?><span class="dbx-lab<?= $rbOver ? ' over' : '' ?>"><?= $rbOver ? 'Over Billed' : 'Remaining Balance' ?></span></td>
                            <td class="dbx-r dbx-num"><span class="dbx-outv">₹ <?= $money($pp['outstanding_collection']) ?></span><div class="dbx-outbar"><i style="width:<?= $pct((float) $pp['outstanding_collection'], (float) $maxOutstanding) ?>%"></i></div></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>

        <!-- ================= right column ================= -->
        <div class="dbx-stack dbx-rail dbx-group">

            <?php $calm = ! $overdrawn && ! $emiLate && ! $svcOpen; ?>
            <section class="dbx-sheet full<?= $calm ? ' dbx-calm' : '' ?>" aria-label="Needs attention">
                <?php if ($calm): ?>
                <div class="dbx-sech"><h2>Needs attention</h2><span class="dbx-clear"><span class="dbx-dot"></span>All clear</span></div>
                <?php else: ?>
                <div class="dbx-sech"><h2>Needs attention</h2></div>
                <ul class="dbx-att">
                    <?php if ($overdrawn): ?>
                    <li><span class="dbx-dot k-neg"></span><?= $overdrawn === 1 ? 'Bank account overdrawn' : $overdrawn . ' bank accounts overdrawn' ?> <span style="color:var(--d-faint)">(<?= esc(implode(', ', array_column($bank_low_balance, 'bank_name'))) ?>)</span><a href="<?= base_url('bank-accounts') ?>">Review</a></li>
                    <?php endif; ?>
                    <?php if ($emiLate): ?>
                    <li><span class="dbx-dot k-neg"></span><?= $plural($emiLate, 'loan EMI overdue', 'loan EMIs overdue') ?><a href="<?= base_url('loan-reports/emi-due') ?>">View</a></li>
                    <?php endif; ?>
                    <?php if ($svcOpen): ?>
                    <li><span class="dbx-dot k-amb"></span><?= $plural($svcOpen, 'service invoice pending', 'service invoices pending') ?><a href="<?= base_url('service-reports/outstanding') ?>">View</a></li>
                    <?php endif; ?>
                </ul>
                <?php endif; ?>
            </section>

            <section class="dbx-sheet full" aria-label="Quick actions">
                <div class="dbx-sech"><h2>Quick actions</h2></div>
                <div class="dbx-acts">
                    <a class="dbx-btn primary" href="<?= base_url('sales/create') ?>"><svg class="dbx-i"><use href="#dbx-i-plus"/></svg>Add Sales Invoice</a>
                    <a class="dbx-btn" href="<?= base_url('payments/create') ?>"><svg class="dbx-i"><use href="#dbx-i-pay"/></svg>Record Payment</a>
                    <a class="dbx-btn" href="<?= base_url('purchases/create') ?>"><svg class="dbx-i"><use href="#dbx-i-cart"/></svg>Add Purchase</a>
                    <a class="dbx-btn" href="<?= base_url('expenses/create') ?>"><svg class="dbx-i"><use href="#dbx-i-wallet"/></svg>Add Expense</a>
                    <a class="dbx-btn" href="<?= base_url('projects/create') ?>"><svg class="dbx-i"><use href="#dbx-i-proj"/></svg>Add Project</a>
                </div>
            </section>

            <?= $this->include('dashboard/partials/loan_summary') ?>
            <?= $this->include('dashboard/partials/service_summary') ?>
            <?= $this->include('dashboard/partials/expense_summary') ?>
        </div>
    </div>

    <?= $this->include('dashboard/partials/maintenance_summary') ?>
</div>

<script>
/* Display-only count-up (runs once). The server-rendered text is stored and restored exactly at the end. */
(function () {
    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
    var root = document.querySelector('.dbx');
    if (!root || !window.requestAnimationFrame) { return; }
    var items = [
        ['.dbx-pos .dbx-val.dbx-num', 80], ['.dbx-perf .np .dbx-val', 160], ['.dbx-perf .m .dbx-val', 200], ['.dbx-big', 240]
    ];
    var re = /^(-?)([\d,]+)\.(\d{2})$/;
    function fmt(c, neg) {
        var whole = Math.floor(c / 100), frac = c % 100;
        return (neg ? '-' : '') + String(whole).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '.' + (frac < 10 ? '0' : '') + frac;
    }
    items.forEach(function (it) {
        root.querySelectorAll(it[0]).forEach(function (el) {
            var node = el.lastChild;
            if (!node || node.nodeType !== 3) { return; }
            var raw = node.nodeValue, m = re.exec(raw.trim());
            if (!m) { return; }
            var lead = raw.match(/^\s*/)[0], tail = raw.match(/\s*$/)[0];
            var target = parseInt(m[2].replace(/,/g, ''), 10) * 100 + parseInt(m[3], 10), neg = m[1] === '-';
            if (target === 0) { return; }
            node.nodeValue = lead + fmt(0, false) + tail;
            setTimeout(function () {
                var t0 = null, dur = 800;
                (function step(ts) {
                    if (t0 === null) { t0 = ts; }
                    var p = Math.min(1, (ts - t0) / dur), e = 1 - Math.pow(1 - p, 3);
                    node.nodeValue = p >= 1 ? raw : lead + fmt(Math.round(target * e), neg) + tail;
                    if (p < 1) { requestAnimationFrame(step); }
                })(performance.now());
            }, it[1]);
        });
    });
})();
</script>
<?= $this->endSection() ?>
