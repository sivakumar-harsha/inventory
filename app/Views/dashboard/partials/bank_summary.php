<?php
/**
 * Release 4.8.4I: Bank attention item for the Dashboard — the overdrawn-account
 * warning only. Included from dashboard/index.php. Read-only; renders nothing
 * until the bank tables exist and no account is below zero
 * (Dashboard::_bankSummary()).
 *
 * Dashboard cleanup: the Bank KPIs moved into the Financial Overview row of
 * index.php (Bank = total current balance), and the Latest Transactions /
 * Latest Transfers tables and the deposit / withdrawal / account-count cards
 * were removed — Bank Transactions and Bank Statement hold that detail.
 */
if (empty($bank_available) || empty($bank_low_balance)) {
    return;
}

$money = static fn ($n) => number_format((float) $n, 2);
?>
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
