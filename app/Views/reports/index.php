<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
/*
 * Release 4.9.0F: Reports Center. Only analytical reports that exist and
 * work are listed — "how much / how many". Transaction lists (ledgers,
 * registers, statements, cash book) stay under Transactions / Accounts.
 * Each entry: [title, path, one-line description]; every one has PDF + Excel export.
 */
$groups = [
    ['Financial', 'bi-graph-up-arrow', [
        ['Profit & Loss', 'reports/profit-loss', 'Revenue, cost of goods and expenses by project for a period'],
        ['Monthly Statement', 'monthly-statement', 'Monthly money received and paid with opening and closing balance'],
        ['Cash Book', 'cash-book', 'Physical cash received, paid and current cash balance'],
    ]],
    ['Transaction Analysis', 'bi-bar-chart-line', [
        ['Sales Report', 'reports/sales', 'Invoiced, received and pending sales by period, project or customer'],
        ['Purchase Report', 'reports/purchases', 'Stock purchase value and project / general split by period or supplier'],
        ['Expense Reports', 'expense-reports/category-summary', 'Expense analysis by category, project, payment method and month'],
    ]],
    ['Loans', 'bi-cash-coin', [
        ['Loan Outstanding', 'loan-reports/outstanding', 'Principal still owed per loan, with next EMI'],
        ['EMI Due', 'loan-reports/emi-due', 'EMIs due in a period: amount, paid and balance'],
        ['Lender Summary', 'loan-reports/lender-summary', 'Borrowed, repaid and outstanding per lender'],
    ]],
    ['Inventory', 'bi-boxes', [
        ['Stock Summary', 'reports/stock', 'Current general and project stock per product'],
        ['Warehouse Stock Summary', 'warehouse/stock-summary', 'Warehouse stock in, out and available balance per product', false],
        ['Warehouse Stock Ledger', 'warehouse/stock-ledger', 'Warehouse stock transactions with running balance, filtered by product, type and date', false],
        ['Stock Movements (All)', 'reports/ledger', 'Stock movements across general and project stock, filtered by project and product'],
    ]],
];
?>

<style>
.rc-desc { font-size: .78rem; color: var(--text-muted); margin: 0 0 12px; }
.rc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(320px, 100%), 1fr)); gap: 12px; align-items: start; }
.rc-grid .card-custom { margin-bottom: 0; }
.rc-head { display: flex; align-items: center; gap: 6px; padding: 6px 12px; }
.rc-head .rc-n { margin-left: auto; font-weight: 500; font-size: .7rem; color: var(--text-muted); }
.rc-list { list-style: none; margin: 0; padding: 0; }
.rc-list li + li { border-top: 1px solid var(--border-color); }
.rc-list a { display: flex; align-items: center; gap: 10px; padding: 7px 12px; color: var(--text-dark); text-decoration: none; }
.rc-list a:hover, .rc-list a:focus-visible { background: #f8fafc; }
.rc-list a:focus-visible { outline: 2px solid var(--primary); outline-offset: -2px; }
.rc-list a > div { flex: 1 1 auto; min-width: 0; }
.rc-t { font-size: .8rem; font-weight: 600; line-height: 1.3; }
.rc-s { font-size: .72rem; color: var(--text-muted); line-height: 1.3; }
.rc-x { flex: none; font-size: .66rem; color: #94a3b8; white-space: nowrap; }
.rc-go { flex: none; color: #cbd5e1; font-size: .75rem; }
.rc-foot { margin-top: 12px; font-size: .74rem; color: var(--text-muted); }
.rc-foot a { color: var(--primary); text-decoration: none; }
@media (max-width: 575.98px) {
    .rc-grid { grid-template-columns: 1fr; }
    .rc-x { display: none; }
}
</style>

<div class="page-title mb-0">
    <span><i class="bi bi-bar-chart-line me-2"></i>Reports</span>
</div>
<p class="rc-desc">Totals and summaries for a period. To check individual transactions use the ledgers under Transactions and Accounts.</p>

<div class="rc-grid">
    <?php foreach ($groups as [$gTitle, $gIcon, $items]): ?>
    <div class="card-custom">
        <div class="card-custom-header rc-head">
            <i class="bi <?= esc($gIcon, 'attr') ?>"></i><?= esc($gTitle) ?>
            <span class="rc-n"><?= count($items) ?></span>
        </div>
        <ul class="rc-list">
            <?php foreach ($items as $item): [$title, $path, $desc] = $item; $hasExport = $item[3] ?? true; ?>
            <li>
                <a href="<?= base_url($path) ?>">
                    <div>
                        <div class="rc-t"><?= esc($title) ?></div>
                        <div class="rc-s"><?= esc($desc) ?></div>
                    </div>
                    <?php if ($hasExport): ?><span class="rc-x">PDF &middot; Excel</span><?php endif; ?>
                    <i class="bi bi-chevron-right rc-go"></i>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endforeach; ?>
</div>

<div class="rc-foot">
    Transaction verification pages:
    <a href="<?= base_url('supplier-ledger') ?>">Supplier Ledger</a> &middot;
    <a href="<?= base_url('customer-ledger') ?>">Customer Ledger</a> &middot;
    <a href="<?= base_url('expense-reports') ?>">Expense Ledger</a> &middot;
    <a href="<?= base_url('bank-statement') ?>">Bank Statement</a></div>

<?= $this->endSection() ?>
