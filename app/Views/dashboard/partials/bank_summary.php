<?php
/**
 * Release 4.8.4I: Bank summary — KPIs, overdrawn-account warning and the
 * latest-10 Transactions / Transfers widgets. Included from
 * dashboard/index.php right after Expense Summary, so it reuses that page's
 * kpi-card / card-custom / dash-table-dense styles. Read-only; renders
 * nothing until the bank tables exist (Dashboard::_bankSummary()).
 */
if (empty($bank_available)) {
    return;
}

$money = static fn ($n) => number_format((float) $n, 2);

$labelBadges = [
    'Deposit' => 'be-badge-green', 'Transfer In' => 'be-badge-green', 'Customer Payment' => 'be-badge-green', 'Service Receipt' => 'be-badge-green', 'Project Receipt' => 'be-badge-green',
    'Withdrawal' => 'be-badge-red', 'Transfer Out' => 'be-badge-blue', 'Supplier Payment' => 'be-badge-red', 'Expense' => 'be-badge-amber', 'Loan Payment' => 'be-badge-purple',
    'Manual Daybook' => 'be-badge-gray',
];
?>
<style>
	.be-dash-scroll { max-height: 300px; overflow: auto; }
	.be-dash-scroll .be-empty { padding: 26px 12px; text-align: center; color: #94a3b8; font-size: .85rem; }
	.be-dash-scroll .be-empty i { display: block; margin-bottom: 6px; font-size: 1.6rem; color: #cbd5e1; }
	.be-kpi-sub { margin-top: 1px; font-size: .68rem; color: #94a3b8; }
	.be-badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: .7rem; font-weight: 600; white-space: nowrap; border: 1px solid transparent; }
	.be-badge-gray   { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
	.be-badge-green  { background: #dcfce7; color: #166534; border-color: #86efac; }
	.be-badge-red    { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
	.be-badge-blue   { background: #dbeafe; color: #1e3a8a; border-color: #93c5fd; }
	.be-badge-amber  { background: #fef3c7; color: #92400e; border-color: #fde68a; }
	.be-badge-purple { background: #ede9fe; color: #5b21b6; border-color: #c4b5fd; }
	.be-in { color: #166534; }
	.be-out { color: #991b1b; }
	.be-sub-line { display: block; color: #94a3b8; font-size: .68rem; }
</style>

<!-- BANK (Release 4.8.4I) -->
<div class="row g-3 mb-3">
    <div class="col-lg-3 col-6">
        <div class="kpi-card <?= $bank_total_balance < 0 ? 'kpi-red' : 'kpi-green' ?>">
            <div class="kpi-icon"><i class="bi bi-bank"></i></div>
            <div>
                <div class="kpi-value"><?= $money($bank_total_balance) ?></div>
                <div class="kpi-label">Total Bank Balance</div>
                <div class="be-kpi-sub">Across all accounts</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-arrow-down-circle"></i></div>
            <div>
                <div class="kpi-value"><?= $money($bank_month_deposits) ?></div>
                <div class="kpi-label">Deposits This Month</div>
                <div class="be-kpi-sub"><?= esc(date('F Y')) ?>, excl. transfers</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-arrow-up-circle"></i></div>
            <div>
                <div class="kpi-value"><?= $money($bank_month_withdrawals) ?></div>
                <div class="kpi-label">Withdrawals This Month</div>
                <div class="be-kpi-sub"><?= esc(date('F Y')) ?>, excl. transfers</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-collection"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($bank_account_count) ?></div>
                <div class="kpi-label">Bank Accounts</div>
                <div class="be-kpi-sub"><?= number_format($bank_active_count) ?> active</div>
            </div>
        </div>
    </div>
</div>

<?php if (! empty($bank_low_balance)): ?>
<div class="alert alert-danger mb-3" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Low balance warning.</strong>
    A bank balance may not go below zero, but these accounts have:
    <ul class="mb-0 mt-1">
        <?php foreach ($bank_low_balance as $low): ?>
        <li>
            <a href="<?= base_url('bank-statement?bank_account_id=' . (int) $low['id']) ?>"><?= esc($low['bank_name']) ?> (<?= esc($low['account_number']) ?>)</a>
            — <strong><?= $money($low['current_balance']) ?></strong><?= $low['is_active'] ? '' : ' (inactive)' ?>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-custom-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-journal-text me-2"></i>Latest Bank Transactions</span>
                <a href="<?= base_url('bank-statement') ?>" class="btn-save" style="padding:3px 10px;font-size:12px;">Statement</a>
            </div>
            <div class="be-dash-scroll">
                <table class="table-custom dash-table-dense" id="bankRecent">
                    <thead style="font-size:12px; color:#888;">
                        <tr>
                            <th>Date</th>
                            <th>Bank</th>
                            <th>Transaction</th>
                            <th style="text-align:right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bank_recent)): ?>
                        <tr><td colspan="4"><div class="be-empty"><i class="bi bi-inbox"></i>No bank transactions yet</div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($bank_recent as $t): ?>
                        <tr>
                            <td style="white-space:nowrap;"><?= esc(date('d-m-Y', strtotime($t['transaction_date']))) ?></td>
                            <td>
                                <a href="<?= base_url('bank-statement?bank_account_id=' . (int) $t['bank_account_id']) ?>" style="text-decoration:none;"><?= esc($t['bank_name']) ?></a>
                                <span class="be-sub-line"><?= esc($t['account_number']) ?></span>
                            </td>
                            <td>
                                <span class="be-badge <?= esc($labelBadges[$t['label']] ?? 'be-badge-gray', 'attr') ?>"><?= esc($t['label']) ?></span>
                                <?php if (! empty($t['reference_no'])): ?><span class="be-sub-line"><?= esc($t['reference_no']) ?></span><?php endif; ?>
                            </td>
                            <td style="text-align:right; white-space:nowrap;" class="<?= $t['is_credit'] ? 'be-in' : 'be-out' ?>"><?= $t['is_credit'] ? '+' : '-' ?><?= $money($t['amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-custom-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-arrow-left-right me-2"></i>Latest Transfers</span>
                <a href="<?= base_url('bank-transfers') ?>" class="btn-save" style="padding:3px 10px;font-size:12px;">View All</a>
            </div>
            <div class="be-dash-scroll">
                <table class="table-custom dash-table-dense" id="bankTransfers">
                    <thead style="font-size:12px; color:#888;">
                        <tr>
                            <th>Date</th>
                            <th>From</th>
                            <th>To</th>
                            <th style="text-align:right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bank_transfers)): ?>
                        <tr><td colspan="4"><div class="be-empty"><i class="bi bi-inbox"></i>No transfers yet</div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($bank_transfers as $tr): ?>
                        <tr>
                            <td style="white-space:nowrap;">
                                <?= esc(date('d-m-Y', strtotime($tr['transaction_date']))) ?>
                                <?php if (! empty($tr['transfer_id'])): ?>
                                <a href="<?= base_url('bank-transfers/view/' . (int) $tr['transfer_id']) ?>" class="be-sub-line" style="text-decoration:none;"><?= esc(\App\Models\BankTransactionModel::transferNo((int) $tr['transfer_id'])) ?></a>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($tr['from_bank']) ?><span class="be-sub-line"><?= esc($tr['from_number']) ?></span></td>
                            <td><?= esc($tr['to_bank']) ?><span class="be-sub-line"><?= esc($tr['to_number']) ?></span></td>
                            <td style="text-align:right; white-space:nowrap;">
                                <?= $money($tr['amount']) ?>
                                <?php if (! empty($tr['method'])): ?><span class="be-sub-line"><?= esc($tr['method']) ?></span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
