<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$modeLabels = ['CASH' => 'Cash', 'BANK' => 'Bank', 'CHEQUE' => 'Cheque', 'UPI' => 'UPI', 'OTHER' => 'Other'];
$typeLabels = ['INVOICE' => 'Invoice', 'DIRECT' => 'Direct Receipt'];

$grand    = (float) $receipt['grand_total'];
$roundOff = round($grand) - $grand;
$status = ucfirst(strtolower($receipt['payment_status']));

// Prefer the customer's live contact details; fall back to the receipt's own snapshot.
$custName    = $customer['name']    ?? $receipt['customer_name'];
$custAddress = $customer['address'] ?? $receipt['customer_address'];
?>

<style>
.badge-sr {
	display: inline-block;
	padding: 4px 12px;
	border-radius: 20px;
	font-size: .78rem;
	font-weight: 600;
	-webkit-print-color-adjust: exact;
	print-color-adjust: exact;
}
.badge-sr.badge-pending { background:#ffedd5; color:#c2410c; }
.badge-sr.badge-partial { background:#dbeafe; color:#1d4ed8; }
.badge-sr.badge-paid    { background:#dcfce7; color:#166534; }

.sr-breadcrumb { margin-bottom: 8px; }
.sr-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
.sr-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
.sr-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
.sr-breadcrumb .breadcrumb-item.active { color: #64748b; }

.sr-items-table { min-width: 620px; }
.sr-items-table tfoot td { border-top: 1px solid #e2e8f0; font-size: .85rem; }
.sr-items-table tfoot tr.grand td { font-weight: 700; border-top: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1; }

/* Same print rules as Supplier Payment view (sidebar/topbar are hidden by the shared stylesheet) */
@media print {
    .page-title a, .btn-cancel, .btn-save, .sr-breadcrumb { display: none !important; }
}
</style>

<nav aria-label="breadcrumb" class="sr-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Transactions</li>
        <li class="breadcrumb-item"><a href="<?= base_url('service-receipts') ?>">Service Received</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= esc($receipt['receipt_no']) ?></li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-receipt-cutoff me-2"></i>Service Receipt — <?= esc($receipt['receipt_no']) ?></span>
    <div class="d-flex gap-2">
        <a href="<?= base_url('service-receipts') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
        <a href="<?= base_url('service-receipts/edit/' . $receipt['id']) ?>" class="btn-save"><i class="bi bi-pencil"></i> Edit</a>
        <a href="javascript:window.print()" class="btn-save" style="background:#6c757d;"><i class="bi bi-printer"></i> Print</a>
        <a href="javascript:void(0)" class="btn-cancel" title="Export PDF (coming soon)" onclick="alert('PDF export will be available in a future release.')"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card-custom mb-3">
            <div class="card-custom-header">Receipt Information</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td><strong>Receipt Number</strong></td><td><?= esc($receipt['receipt_no']) ?></td></tr>
                    <tr><td><strong>Receipt Date</strong></td><td><?= esc($receipt['receipt_date']) ?></td></tr>
                    <tr><td><strong>Receipt Type</strong></td><td><?= esc($typeLabels[$receipt['receipt_type']] ?? $receipt['receipt_type']) ?></td></tr>
                    <tr><td><strong>Payment Mode</strong></td><td><?= esc($modeLabels[$receipt['payment_mode']] ?? $receipt['payment_mode']) ?></td></tr>
                    <?php if (! empty($receipt['bank_account_id'])): ?>
                    <tr><td><strong>Bank Account</strong></td><td><?= esc(trim(($receipt['bank_name'] ?? '') . ' - ' . ($receipt['account_name'] ?? '') . ' (' . ($receipt['account_number'] ?? '') . ')')) ?></td></tr>
                    <?php endif; ?>
                    <tr><td><strong>Attended Person</strong></td><td><?= esc($receipt['attended_person'] ?: '-') ?></td></tr>
                    <?php if (! empty($receipt['remarks'])): ?>
                    <tr><td><strong>Remarks</strong></td><td><?= esc($receipt['remarks']) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="card-custom mb-3">
            <div class="card-custom-header">Customer Information</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td><strong>Name</strong></td><td><?= esc($custName ?: '-') ?></td></tr>
                    <tr><td><strong>Mobile</strong></td><td><?= esc($customer['phone'] ?? '' ?: '-') ?></td></tr>
                    <tr><td><strong>Email</strong></td><td><?= esc($customer['email'] ?? '' ?: '-') ?></td></tr>
                    <tr><td><strong>GST Number</strong></td><td><?= esc($customer['gst'] ?? '' ?: '-') ?></td></tr>
                    <tr><td><strong>Address</strong></td><td><?= esc($custAddress ?: '-') ?></td></tr>
                </table>
            </div>
        </div>

        <div class="card-custom">
            <div class="card-custom-header">Payment Summary</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td>Subtotal</td><td style="text-align:right"><?= number_format((float) $receipt['subtotal'], 2) ?></td></tr>
                    <tr><td>GST</td><td style="text-align:right"><?= number_format((float) $receipt['gst_total'], 2) ?></td></tr>
                    <tr><td>Round Off</td><td style="text-align:right"><?= ($roundOff >= 0 ? '+' : '') . number_format($roundOff, 2) ?></td></tr>
                    <tr><td><strong>Grand Total</strong></td><td style="text-align:right"><strong><?= number_format($grand, 2) ?></strong></td></tr>
                    <tr><td>Received</td><td style="text-align:right"><?= number_format((float) $receipt['received_amount'], 2) ?></td></tr>
                    <tr><td>Outstanding</td><td style="text-align:right"><?= number_format((float) $receipt['outstanding_amount'], 2) ?></td></tr>
                </table>
                <div class="mt-2">
                    <span class="badge-sr badge-<?= esc(strtolower($receipt['payment_status'])) ?>"><?= esc($status) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card-custom">
            <div class="card-custom-header">Service Items</div>
            <div class="table-responsive">
                <table class="table-custom sr-items-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Description</th>
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
                            <td><?= $i + 1 ?></td>
                            <td><?= esc($item['description']) ?></td>
                            <td style="text-align:right"><?= number_format((float) $item['qty'], 3) ?></td>
                            <td style="text-align:right"><?= number_format((float) $item['rate'], 2) ?></td>
                            <td style="text-align:right"><?= number_format((float) $item['gst_percent'], 2) ?></td>
                            <td style="text-align:right"><?= number_format((float) $item['gst_amount'], 2) ?></td>
                            <td style="text-align:right"><?= number_format((float) $item['line_total'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="6" style="text-align:right">Subtotal</td><td style="text-align:right"><?= number_format((float) $receipt['subtotal'], 2) ?></td></tr>
                        <tr><td colspan="6" style="text-align:right">GST</td><td style="text-align:right"><?= number_format((float) $receipt['gst_total'], 2) ?></td></tr>
                        <tr><td colspan="6" style="text-align:right">Round Off</td><td style="text-align:right"><?= ($roundOff >= 0 ? '+' : '') . number_format($roundOff, 2) ?></td></tr>
                        <tr class="grand"><td colspan="6" style="text-align:right">Grand Total</td><td style="text-align:right"><?= number_format($grand, 2) ?></td></tr>
                        <tr><td colspan="6" style="text-align:right">Received</td><td style="text-align:right"><?= number_format((float) $receipt['received_amount'], 2) ?></td></tr>
                        <tr><td colspan="6" style="text-align:right">Outstanding</td><td style="text-align:right"><?= number_format((float) $receipt['outstanding_amount'], 2) ?></td></tr>
                        <tr><td colspan="6" style="text-align:right">Status</td><td style="text-align:right"><span class="badge-sr badge-<?= esc(strtolower($receipt['payment_status'])) ?>"><?= esc($status) ?></span></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
