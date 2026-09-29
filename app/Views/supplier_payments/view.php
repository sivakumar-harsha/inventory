<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
.badge-bill-type {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 6px;
	font-size: .7rem;
	font-weight: 700;
}
.badge-bill-type.type-project { background:#e0e7ff; color:#3730a3; }
.badge-bill-type.type-general { background:#e0f2fe; color:#075985; }

.sp-breadcrumb { margin-bottom: 8px; }
.sp-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
.sp-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
.sp-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
.sp-breadcrumb .breadcrumb-item.active { color: #64748b; }

@media print {
    .page-title a, .btn-cancel, .btn-save, .sp-breadcrumb { display: none !important; }
}
</style>

<nav aria-label="breadcrumb" class="sp-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Transactions</li>
        <li class="breadcrumb-item"><a href="<?= base_url('supplier-payments') ?>">Supplier Payments</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= esc($payment['payment_no']) ?></li>
    </ol>
</nav>

<div class="page-title">
    <span><i class="bi bi-wallet2 me-2"></i>Supplier Payment — <?= esc($payment['payment_no']) ?></span>
    <div class="d-flex gap-2">
        <?php if ($gpAdvanceId): ?>
        <a href="<?= base_url('general-purchases/edit/' . (int) $gpAdvanceId) ?>" class="btn-save"><i class="bi bi-box-arrow-up-right"></i> Edit on General Purchase</a>
        <?php else: ?>
        <a href="<?= base_url('supplier-payments/edit/' . $payment['id']) ?>" class="btn-save"><i class="bi bi-pencil"></i> Edit</a>
        <?php endif; ?>
        <a href="javascript:window.print()" class="btn-save" style="background:#6c757d;"><i class="bi bi-printer"></i> Print</a>
        <a href="<?= base_url('supplier-payments') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card-custom mb-3">
            <div class="card-custom-header">Supplier Information</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td><strong>Name</strong></td><td><?= esc($supplier['name'] ?? '-') ?></td></tr>
                    <tr><td><strong>Mobile</strong></td><td><?= esc($supplier['phone'] ?? '-') ?></td></tr>
                    <tr><td><strong>Email</strong></td><td><?= esc($supplier['email'] ?? '-') ?></td></tr>
                    <tr><td><strong>GST Number</strong></td><td><?= esc($supplier['gst'] ?? '-') ?></td></tr>
                    <tr><td><strong>Address</strong></td><td><?= esc($supplier['address'] ?? '-') ?></td></tr>
                </table>
            </div>
        </div>

        <div class="card-custom mb-3">
            <div class="card-custom-header">Voucher Information</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td><strong>Payment No</strong></td><td><?= esc($payment['payment_no']) ?></td></tr>
                    <tr><td><strong>Payment Type</strong></td><td><?= $gpAdvanceId || (float) $payment['advance_amount'] > 0 ? (empty($allocations) ? 'Advance Payment' : 'Mixed Payment') : 'Bill Payment' ?></td></tr>
                    <?php if ($gpAdvanceId): ?>
                    <tr><td><strong>Source</strong></td><td><a href="<?= base_url('general-purchases/view/' . (int) $gpAdvanceId) ?>">General Purchase <?= esc($payment['reference_no'] ?: '#' . (int) $gpAdvanceId) ?></a></td></tr>
                    <?php endif; ?>
                    <tr><td><strong>Payment Date</strong></td><td><?= esc($payment['payment_date']) ?></td></tr>
                    <tr><td><strong>Payment Method</strong></td><td><?= esc($payment['payment_method'] ?: '-') ?></td></tr>
                    <tr><td><strong>Reference No</strong></td><td><?= esc($payment['reference_no'] ?: '-') ?></td></tr>
                    <?php if ($payment['remarks']): ?>
                    <tr><td><strong>Remarks</strong></td><td><?= esc($payment['remarks']) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="card-custom">
            <div class="card-custom-header">Payment Summary</div>
            <div class="card-custom-body">
                <!-- Release 4.8.6E: cash paid and advance used are shown separately; advance used moves no money -->
                <table class="table-custom">
                    <tr><td>Payment Amount (Cash / Bank)</td><td style="text-align:right"><?= number_format((float) $cashPaid, 2) ?></td></tr>
                    <tr><td>Supplier Advance Used</td><td style="text-align:right"><?= number_format((float) $advanceUsed, 2) ?></td></tr>
                    <tr><td><strong>Total Bill Settled</strong></td><td style="text-align:right"><strong><?= number_format((float) $billsSettled, 2) ?></strong></td></tr>
                    <tr><td>Outstanding After Payment</td><td style="text-align:right"><?= number_format((float) $outstandingAfter, 2) ?></td></tr>
                    <?php if ((float) $payment['advance_amount'] > 0): ?>
                    <tr><td>Supplier Advance Payment</td><td style="text-align:right"><?= number_format((float) $payment['advance_amount'], 2) ?></td></tr>
                    <?php endif; ?>
                    <?php if (! $gpAdvanceId): ?>
                    <tr><td>Remaining Supplier Advance</td><td style="text-align:right"><?= number_format((float) $remainingAdvance, 2) ?></td></tr>
                    <?php endif; ?>
                    <tr><td><strong>Total Payment</strong></td><td style="text-align:right"><strong><?= number_format((float) $payment['total_amount'], 2) ?></strong></td></tr>
                </table>
                <?php if ($gpAdvanceId): ?>
                <div style="font-size:.75rem; color:#64748b; margin-top:6px;">This advance was paid on General Purchase <?= esc($payment['reference_no'] ?: '#' . (int) $gpAdvanceId) ?> and is already applied to that purchase&rsquo;s own bill. It is shown for history only and is not available to settle other bills.</div>
                <?php endif; ?>
                <?php if ((float) $advanceUsed > 0): ?>
                <div style="font-size:.75rem; color:#64748b; margin-top:6px;">Supplier advance used settles bills without any bank transaction and is not part of the Total Payment.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card-custom">
            <div class="card-custom-header">Allocation Table</div>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Bill Type</th>
                            <th>Purchase No</th>
                            <th>Bill No</th>
                            <th>Purchase Date</th>
                            <th style="text-align:right">Bill Amount</th>
                            <th style="text-align:right">Paid Amount</th>
                            <th style="text-align:right">Balance After</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allocations)): ?>
                        <tr><td colspan="8" style="text-align:center; color:#94a3b8;">This voucher is an unallocated advance — no bills were paid.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($allocations as $i => $a): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><span class="badge-bill-type type-<?= strtolower($a['purchase_type']) ?>"><?= esc($a['purchase_type']) ?></span></td>
                            <td><?= esc($a['purchase_no'] ?: '-') ?></td>
                            <td><?= esc($a['bill_no'] ?: '-') ?></td>
                            <td><?= esc($a['purchase_date'] ?: '-') ?></td>
                            <td style="text-align:right"><?= number_format((float) $a['bill_amount'], 2) ?></td>
                            <td style="text-align:right"><?= number_format((float) $a['paid_amount'], 2) ?></td>
                            <td style="text-align:right"><?= number_format((float) $a['balance_amount'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
