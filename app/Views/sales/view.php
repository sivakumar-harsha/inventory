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

        <div class="card-custom mt-3">
            <div class="card-custom-header">Payments Received</div>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead><tr><th>Date</th><th>Amount</th><th>Method</th></tr></thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                        <tr><td colspan="3" class="text-center text-muted">No payments</td></tr>
                        <?php else: ?>
                        <?php foreach ($payments as $pay): ?>
                        <tr>
                            <td><?= $pay['payment_date'] ?></td>
                            <td><?= number_format($pay['amount'], 2) ?></td>
                            <td><?= $pay['method'] ?></td>
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
                    <thead><tr><th>#</th><th>Product</th><th>Unit</th><th>Qty</th><th>Unit Price</th><th>GST %</th><th>GST</th><th style="text-align:right">GST Amt</th><th style="text-align:right">Total</th><th style="text-align:right">Total + GST</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $i => $item): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
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
