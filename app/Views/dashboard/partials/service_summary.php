<?php
/**
 * Release 4.8.4E: Service Received KPIs + latest-10 widgets. Included from
 * dashboard/index.php, so it reuses that page's kpi-card / card-custom /
 * dash-table-dense styles. Renders nothing until the service tables exist.
 */
if (empty($service_available)) {
    return;
}
?>
<style>
	.svc-badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; }
	.svc-badge.svc-pending { background:#ffedd5; color:#c2410c; }
	.svc-badge.svc-partial { background:#dbeafe; color:#1d4ed8; }
	.svc-badge.svc-paid    { background:#dcfce7; color:#166534; }
</style>

<!-- SERVICE RECEIVED (Release 4.8.4E) -->
<div class="row g-3 mb-3">
    <div class="col-md-3 col-6">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-calendar-day"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($service_today_collection, 2) ?></div>
                <div class="kpi-label">Today's Service Collection</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-exclamation-circle"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($service_outstanding, 2) ?></div>
                <div class="kpi-label">Service Outstanding</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($service_pending_invoices) ?></div>
                <div class="kpi-label">Pending Service Invoices</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-receipt-cutoff"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($service_total_income, 2) ?></div>
                <div class="kpi-label">Total Service Income</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-custom-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-receipt-cutoff me-2"></i>Latest Service Receipts</span>
                <a href="<?= base_url('service-receipts') ?>" style="font-size:12px;">View All</a>
            </div>
            <div class="table-responsive dash-table-scroll">
                <table class="table-custom dash-table-dense">
                    <thead style="font-size:12px; color:#888;">
                        <tr>
                            <th>Receipt</th>
                            <th>Customer</th>
                            <th style="text-align:right">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($service_recent_receipts)): ?>
                        <tr><td colspan="4" class="text-center text-muted">No service receipts yet</td></tr>
                        <?php endif; ?>
                        <?php foreach ($service_recent_receipts as $r): ?>
                        <tr>
                            <td><a href="<?= base_url('service-receipts/view/' . $r['id']) ?>" style="text-decoration:none;"><?= esc($r['receipt_no']) ?></a><br><small class="text-muted"><?= esc($r['receipt_date']) ?></small></td>
                            <td><?= esc($r['customer_name']) ?></td>
                            <td style="text-align:right"><?= number_format((float) $r['grand_total'], 2) ?></td>
                            <td><span class="svc-badge svc-<?= esc(strtolower($r['payment_status'])) ?>"><?= esc(ucfirst(strtolower($r['payment_status']))) ?></span></td>
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
                <span><i class="bi bi-wallet2 me-2"></i>Latest Customer Payments</span>
                <a href="<?= base_url('service-reports/collections') ?>" style="font-size:12px;">View All</a>
            </div>
            <div class="table-responsive dash-table-scroll">
                <table class="table-custom dash-table-dense">
                    <thead style="font-size:12px; color:#888;">
                        <tr>
                            <th>Voucher</th>
                            <th>Customer</th>
                            <th>Mode</th>
                            <th style="text-align:right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($service_recent_payments)): ?>
                        <tr><td colspan="4" class="text-center text-muted">No customer payments yet</td></tr>
                        <?php endif; ?>
                        <?php foreach ($service_recent_payments as $p): ?>
                        <tr>
                            <td><?= esc($p['payment_no']) ?><br><small class="text-muted"><?= esc($p['payment_date']) ?></small></td>
                            <td><?= esc($p['customer_name'] ?: '-') ?></td>
                            <td><?= esc(ucfirst(strtolower($p['payment_method']))) ?></td>
                            <td style="text-align:right"><?= number_format((float) $p['total_amount'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
