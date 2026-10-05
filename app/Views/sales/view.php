<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-title">
    <span><i class="bi bi-receipt me-2"></i>Sale Details</span>
	<div class="d-flex gap-2"><a href="<?= base_url('sales/edit/' . $sale['id']) ?>" class="btn-save"><i class="bi bi-pencil"></i> Edit</a><a 		href="<?= base_url('sales') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a></div>
    <?php if ($sale['status'] !== 'PAID'): ?>
    <a href="<?= base_url('payments/create/' . $sale['id']) ?>" class="btn-pay"><i class="bi bi-cash"></i> Record Payment</a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card-custom">
            <div class="card-custom-header">Sale Information</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td><strong>Invoice No</strong></td><td><?= esc($sale['invoice_no'] ?: '-') ?></td></tr>
                    <tr><td><strong>Project</strong></td><td><?= esc($sale['project_name']) ?></td></tr>
                    <tr><td><strong>Customer</strong></td><td><?= esc($sale['customer_name'] ?: '-') ?></td></tr>
                    <tr><td><strong>Stock Source</strong></td><td><span class="badge-source"><?= $sale['stock_source'] ?></span></td></tr>
                    <tr><td><strong>Date</strong></td><td><?= $sale['sale_date'] ?></td></tr>
                    <tr><td><strong>Total</strong></td><td><strong><?= number_format($sale['total_amount'], 2) ?></strong></td></tr>
                    <tr><td><strong>Paid</strong></td><td class="text-success"><?= number_format($sale['paid_amount'], 2) ?></td></tr>
                    <tr><td><strong>Balance</strong></td><td class="text-danger"><?= number_format($sale['balance_amount'], 2) ?></td></tr>
                    <tr><td><strong>Status</strong></td><td><span class="badge-status badge-<?= strtolower($sale['status']) ?>"><?= $sale['status'] ?></span></td></tr>
                </table>
            </div>
        </div>

        <?php
            // Release 4.8.6A-2 Final UI Constitution Patch: ONE advance-settlement card. The old separate
            // Settlement Summary is merged into it, so the invoice's settlement (advance, payments, what
            // is still owed = total - advance applied - payments) always shows, with or without an
            // advance on the project. Presentation only — same values as before.
            $ssTotal       = (float) $sale['total_amount'];
            $ssApplied     = (float) ($sale['advance_applied'] ?? 0);
            $ssPaid        = (float) ($sale['paid_amount'] ?? 0);
            $ssOutstanding = max(0.0, round($ssTotal - $ssApplied - $ssPaid, 2));
            $ssStatus      = $allocation_status ?? ($ssApplied <= 0.004 ? 'Not Applied' : ($ssApplied >= $ssTotal - 0.004 ? 'Fully Applied' : 'Partially Applied'));
        ?>
        <!-- Release 4.8.6A-2: advance applied to this invoice (a matching record — not a payment). -->
        <div class="card-custom mt-3">
            <div class="card-custom-header">Advance Applied</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td><strong>Available Customer Advance</strong></td><td>₹<?= number_format($advance_available ?? 0, 2) ?></td></tr>
                    <tr><td><strong>Advance Applied</strong></td><td class="text-success">₹<?= number_format($ssApplied, 2) ?></td></tr>
                    <tr><td><strong>Customer Payments</strong></td><td class="text-success">₹<?= number_format($ssPaid, 2) ?></td></tr>
                    <tr><td><strong>Invoice Outstanding</strong></td><td class="text-danger">₹<?= number_format($ssOutstanding, 2) ?></td></tr>
                    <tr><td><strong>Allocation Status</strong></td><td><?= esc($ssStatus) ?></td></tr>
                </table>
                <?php if (! empty($legacy_advance)): ?>
                <!-- Release 4.8.6A-2 Patch: old automatic (FIFO) advance — converted only when the accountant clicks. -->
                <div class="mt-2 d-flex flex-wrap align-items-center gap-2">
                    <span class="badge bg-secondary">Legacy Advance Adjustment</span>
                    <form action="<?= base_url('sales/update/' . $sale['id']) ?>" method="POST" class="ms-auto" onsubmit="return confirm('Convert this legacy advance into a new allocation? The amount, balance and status stay the same.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="convert_legacy_advance" value="1">
                        <button type="submit" class="btn-save"><i class="bi bi-arrow-repeat"></i> Convert to New Allocation</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php if (count($allocations) > 1): ?>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead><tr><th class="sno-col">S.No.</th><th>Date</th><th style="text-align:right">Allocated</th><th>By</th></tr></thead>
                    <tbody>
                        <?php foreach ($allocations as $al): ?>
                        <tr><td class="sno-col sno-auto" data-label="S.No."></td><td><?= esc($al['allocation_date']) ?></td><td style="text-align:right"><?= number_format($al['allocated_amount'], 2) ?></td><td><?= esc($al['created_by_name'] ?? '-') ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="card-custom mt-3">
            <div class="card-custom-header">Payments Received</div>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead><tr><th class="sno-col">S.No.</th><th>Date</th><th>Amount</th><th>Method</th></tr></thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                        <tr><td colspan="4" class="text-center text-muted">No payments</td></tr>
                        <?php else: ?>
                        <?php foreach ($payments as $pay): ?>
                        <tr>
                            <td class="sno-col sno-auto" data-label="S.No."></td>
                            <td><?= $pay['payment_date'] ?></td>
                            <td><?= number_format($pay['amount'], 2) ?></td>
                            <td><?= pm_badge($pay['method'] ?? '', 'Not recorded') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card-custom">
            <div class="card-custom-header">Items Sold</div>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead><tr><th class="sno-col">S.No.</th><th>Product</th><th>Unit</th><th>Qty</th><th>Unit Price</th><th>GST %</th><th>GST</th><th style="text-align:right">GST Amt</th><th style="text-align:right">Total</th><th style="text-align:right">Total + GST</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $i => $item): ?>
                        <tr>
                            <td class="sno-col"><?= $i + 1 ?></td>
                            <td><?= esc($item['product_name']) ?></td>
                            <td><?= esc($item['unit']) ?></td>
                            <td><?= number_format($item['quantity'], 3) ?></td>
                            <td><?= number_format($item['unit_price'], 2) ?></td>
							<td><?= number_format($item['gst_percent'], 2) ?></td>
							<td><?= !empty($item['gst_applicable']) ? 'Yes' : 'No' ?></td>
							<td style="text-align:right"><?= number_format($item['gst_amount'], 2) ?></td>
							<td style="text-align:right"><?= number_format($item['total'], 2) ?></td>
							<td style="text-align:right"><?= number_format($item['total_with_gst'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-custom-body" style="border-top:1px solid #e2e8f0">
                <table class="table-custom" style="max-width:320px;margin-left:auto">
                    <tr><td><strong>Taxable Amount</strong></td><td style="text-align:right"><?= number_format($gst_summary['taxable_amount'], 2) ?></td></tr>
                    <tr><td><strong>GST Amount</strong></td><td style="text-align:right"><?= number_format($gst_summary['gst_amount'], 2) ?></td></tr>
                    <tr><td><strong>Grand Total</strong></td><td style="text-align:right"><strong><?= number_format($sale['total_amount'], 2) ?></strong></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
