<?php
/**
 * Dashboard: Service figures (today's collection, outstanding, pending invoices, total income).
 * Included from dashboard/index.php; data from Dashboard::_serviceSummary(). Renders nothing
 * until the service tables exist. Receipt / payment history lives in Service Receipts and the
 * Collections report.
 */
if (empty($service_available)) {
    return;
}
?>
<section class="dbx-sheet" aria-label="Service">
    <div class="dbx-sech"><h2>Service</h2><a href="<?= base_url('service-receipts') ?>">Receipts →</a></div>
    <div class="dbx-kvl">
        <div><span>Today's collection</span><b class="dbx-num g">₹ <?= number_format($service_today_collection, 2) ?></b></div>
        <div><span>Outstanding</span><b class="dbx-num r2">₹ <?= number_format($service_outstanding, 2) ?></b></div>
        <div><span>Pending invoices</span><b class="dbx-num a"><?= number_format($service_pending_invoices) ?></b></div>
        <div><span>Total service income</span><b class="dbx-num">₹ <?= number_format($service_total_income, 2) ?></b></div>
    </div>
</section>
