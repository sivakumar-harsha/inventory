<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-title">
    <span><i class="bi bi-cart me-2"></i>Purchase Details</span>
    <div class="d-flex gap-2"><a href="<?= base_url('purchases/edit/' . $purchase['id']) ?>" class="btn-save"><i class="bi bi-pencil"></i> 	Edit</a><a href="<?= base_url('purchases') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a></div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card-custom">
            <div class="card-custom-header">Purchase Information</div>
            <div class="card-custom-body">
                <?php
                    $totalPurchasedQty = array_sum(array_column($items, 'quantity'));
                    $totalProjectQty   = array_sum(array_column($items, 'project_qty'));
                    $totalGeneralQty   = array_sum(array_column($items, 'general_qty'));
                    $isMixedAllocation = $totalGeneralQty > 0;
                    $sourceLabel       = $totalGeneralQty == 0 ? 'PROJECT' : ($totalProjectQty == 0 ? 'GENERAL' : 'MIXED');
                ?>
                <table class="table-custom">
                    <tr><td><strong>Invoice No</strong></td><td><?= esc($purchase['invoice_no'] ?: '-') ?></td></tr>
                    <tr><td><strong>Supplier</strong></td><td><?= esc($purchase['supplier_name']) ?></td></tr>
                    <tr><td><strong>Project</strong></td><td><?= esc($purchase['project_name'] ?: '-') ?></td></tr>
                    <tr><td><strong>Stock Source</strong></td><td><span class="badge-source"><?= $sourceLabel ?></span></td></tr>
                    <tr><td><strong>Purchased Qty</strong></td><td><?= number_format($totalPurchasedQty) ?></td></tr>
                    <tr><td><strong>Project Qty</strong></td><td><?= number_format($totalProjectQty) ?></td></tr>
                    <tr><td><strong>General Qty</strong></td><td><?= number_format($totalGeneralQty) ?></td></tr>
                    <tr><td><strong>Date</strong></td><td><?= $purchase['purchase_date'] ?></td></tr>
                    <tr><td><strong>Total Amount</strong></td><td><strong><?= number_format($purchase['total_amount'], 2) ?></strong></td></tr>
                    <?php if ($isMixedAllocation): ?>
                    <tr><td><strong>Project Allocated Amount</strong></td><td><strong><?= number_format($allocated_project_amount, 2) ?></strong></td></tr>
                    <?php endif; ?>
                </table>
                <?php if ($purchase['notes']): ?>
                <p style="margin-top:10px;font-size:0.8rem;color:#64748b"><strong>Notes:</strong> <?= esc($purchase['notes']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card-custom">
            <div class="card-custom-header">Items Purchased</div>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
						<tr>
						<th>#</th>
						<th>Product</th>
						<th>Unit</th>
						<th>Qty</th>
						<th>Project Qty</th>
						<th>General Qty</th>
						<th>HSN</th>
						<th>GST %</th>
						<th>GST</th>
						<th style="text-align: right;">Price</th>
						<th style="text-align: right;">GST Amt</th>
						<th style="text-align: right;">Total</th>
						<th style="text-align: right;">Total + GST</th>
						</tr>
					</thead>
                    <tbody>
                        <?php foreach ($items as $i => $item): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= esc($item['product_name']) ?></td>
                            <td><?= esc($item['unit']) ?></td>
                            <td><?= number_format($item['quantity']) ?></td>
							<td><?= number_format($item['project_qty']) ?></td>
							<td><?= number_format($item['general_qty']) ?></td>
							<td><?= esc($item['product_hsn'] ?? $item['hsn_code'] ?? '-') ?></td>
							<td><?= number_format($item['gst_percent'], 2) ?></td>
							<td><?= !empty($item['gst_applicable']) ? 'Yes' : 'No' ?></td>
							<td class="text-right"><?= number_format($item['unit_price'], 2) ?></td>
							<td class="text-right"><?= number_format($item['gst_amount'], 2) ?></td>
							<td class="text-right"><?= number_format($item['total'], 2) ?></td>
							<td class="text-right"><?= number_format($item['total_with_gst'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-custom-body" style="border-top:1px solid #e2e8f0">
                <table class="table-custom" style="max-width:320px;margin-left:auto">
                    <tr><td><strong>Taxable Amount</strong></td><td style="text-align:right"><?= number_format($gst_summary['taxable_amount'], 2) ?></td></tr>
                    <tr><td><strong>GST Amount</strong></td><td style="text-align:right"><?= number_format($gst_summary['gst_amount'], 2) ?></td></tr>
                    <tr><td><strong>Grand Total</strong></td><td style="text-align:right"><strong><?= number_format($purchase['total_amount'], 2) ?></strong></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
