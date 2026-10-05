<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
.badge-pay {
	display: inline-block;
	padding: 4px 12px;
	border-radius: 20px;
	font-size: .78rem;
	font-weight: 600;
}
.badge-pay.badge-pending { background:#f3f4f6; color:#374151; }
.badge-pay.badge-partial { background:#fef3c7; color:#92400e; }
.badge-pay.badge-paid    { background:#dcfce7; color:#166534; }

.gp-breadcrumb { margin-bottom: 8px; }
.gp-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
.gp-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
.gp-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
.gp-breadcrumb .breadcrumb-item.active { color: #64748b; }

@media print {
    .page-title a, .btn-cancel, .btn-save, .gp-breadcrumb { display: none !important; }
}
</style>

<nav aria-label="breadcrumb" class="gp-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('purchases') ?>">Purchases</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('general-purchases') ?>">General Purchase</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= esc($purchase['purchase_no']) ?></li>
    </ol>
</nav>

<div class="page-title">
    <span><i class="bi bi-boxes me-2"></i>General Purchase — <?= esc($purchase['purchase_no']) ?></span>
    <div class="d-flex gap-2">
        <a href="<?= base_url('general-purchases/edit/' . $purchase['id']) ?>" class="btn-save"><i class="bi bi-pencil"></i> Edit</a>
        <a href="javascript:window.print()" class="btn-save" style="background:#6c757d;"><i class="bi bi-printer"></i> Print</a>
        <a href="<?= base_url('general-purchases') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card-custom mb-3">
            <div class="card-custom-header">Supplier Details</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td><strong>Name</strong></td><td><?= esc($purchase['supplier_name'] ?: '-') ?></td></tr>
                    <tr><td><strong>Mobile</strong></td><td><?= esc($purchase['supplier_phone'] ?: '-') ?></td></tr>
                    <tr><td><strong>Email</strong></td><td><?= esc($purchase['supplier_email'] ?: '-') ?></td></tr>
                    <tr><td><strong>GST Number</strong></td><td><?= esc($purchase['supplier_gst'] ?: '-') ?></td></tr>
                    <tr><td><strong>Address</strong></td><td><?= esc($purchase['supplier_address'] ?: '-') ?></td></tr>
                </table>
            </div>
        </div>

        <div class="card-custom mb-3">
            <div class="card-custom-header">Purchase Details</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td><strong>GP Number</strong></td><td><?= esc($purchase['purchase_no']) ?></td></tr>
                    <tr><td><strong>Purchase Date</strong></td><td><?= esc($purchase['purchase_date']) ?></td></tr>
                    <tr><td><strong>Bill Number</strong></td><td><?= esc($purchase['bill_no'] ?: '-') ?></td></tr>
                    <tr><td><strong>Bill Date</strong></td><td><?= esc($purchase['bill_date'] ?: '-') ?></td></tr>
                    <tr><td><strong>Payment Method</strong></td><td><?= esc(pm_label($purchase['payment_method'], '-')) ?></td></tr>
                    <?php if ($purchase['remarks']): ?>
                    <tr><td><strong>Remarks</strong></td><td><?= esc($purchase['remarks']) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="card-custom">
            <div class="card-custom-header">Payment Summary</div>
            <div class="card-custom-body">
                <?php $roundOff = round($totals['grand_total']) - $totals['grand_total']; ?>
                <table class="table-custom">
                    <tr><td>Subtotal</td><td style="text-align:right"><?= number_format($totals['subtotal'], 2) ?></td></tr>
                    <tr><td>GST</td><td style="text-align:right"><?= number_format($totals['gst_total'], 2) ?></td></tr>
                    <tr><td>Round Off</td><td style="text-align:right"><?= ($roundOff >= 0 ? '+' : '') . number_format($roundOff, 2) ?></td></tr>
                    <tr><td><strong>Grand Total</strong></td><td style="text-align:right"><strong><?= number_format($totals['grand_total'], 2) ?></strong></td></tr>
                    <tr><td>Advance Paid</td><td style="text-align:right"><?= number_format($totals['advance_paid'], 2) ?></td></tr>
                    <tr><td>Outstanding</td><td style="text-align:right"><?= number_format($totals['outstanding_amount'], 2) ?></td></tr>
                </table>
                <div class="mt-2">
                    <span class="badge-pay badge-<?= strtolower($totals['payment_status']) ?>"><?= esc($totals['payment_status']) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card-custom">
            <div class="card-custom-header">Product Items</div>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th class="sno-col">S.No.</th>
                            <th>Product</th>
                            <th>HSN</th>
                            <th>Unit</th>
                            <th style="text-align:right">Qty</th>
                            <th style="text-align:right">Rate</th>
                            <th style="text-align:right">GST %</th>
                            <th style="text-align:right">GST Amount</th>
                            <th style="text-align:right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $i => $item): ?>
                        <tr>
                            <td class="sno-col"><?= $i + 1 ?></td>
                            <td><?= esc($item['product_name']) ?></td>
                            <td><?= esc($item['product_hsn'] ?: '-') ?></td>
                            <td><?= esc($item['unit']) ?></td>
                            <td style="text-align:right"><?= number_format($item['quantity'], 3) ?></td>
                            <td style="text-align:right"><?= number_format($item['rate'], 2) ?></td>
                            <td style="text-align:right"><?= number_format($item['gst_percent'], 2) ?></td>
                            <td style="text-align:right"><?= number_format($item['gst_amount'], 2) ?></td>
                            <td style="text-align:right"><?= number_format($item['line_total'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
