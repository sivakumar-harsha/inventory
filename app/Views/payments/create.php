<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-title">
    <span><i class="bi bi-cash me-2"></i>Record Payment</span>
    <a href="<?= base_url('payments') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<!-- Release 2.1E: Step 1 — Select Project. Filters the invoice table rows
     below client-side; all invoices were already loaded in one pass, no new
     route/query. -->
<div class="card-custom mb-2">
    <div class="card-custom-header">Step 1 — Select Project</div>
    <div class="card-custom-body">
        <select id="projectFilter" class="form-control no-search" style="max-width:420px">
            <option value="">-- Select Project --</option>
            <?php foreach ($projects as $p): ?>
            <option value="<?= $p['id'] ?>"><?= esc($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- Release 2.1E: Step 2 — Invoice List. Table is fully hidden behind a
     standalone centered placeholder until a project is chosen; the hidden
     #saleSelect below is unchanged in behavior (existing detail/validation
     JS still runs off its 'change' event). -->
<div class="card-custom mb-2">
    <div class="card-custom-header">Step 2 — Invoice List</div>

    <div id="invoicePlaceholder" class="invoice-placeholder">
        <div class="invoice-placeholder-icon"><i class="bi bi-file-earmark-text"></i></div>
        <div class="invoice-placeholder-title">Select a project to view invoices.</div>
        <div class="invoice-placeholder-sub">Choose an active project above.</div>
    </div>

    <div class="table-responsive" id="invoiceTableWrap" style="display:none">
        <table class="table-custom invoice-table">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Advance</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Pending</th>
                    <th>Status</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody id="invoiceTableBody">
                <?php if (empty($sales)): ?>
                <tr><td colspan="7" class="text-center text-muted">No invoices found.</td></tr>
                <?php else: ?>
                <?php foreach ($sales as $s):
                    $pending    = (float) $s['balance_amount'];
                    $canPay     = $pending > 0.004;
                    $statusMap  = ['PAID' => 'Invoice Paid', 'PARTIAL' => 'Partial Payment', 'UNPAID' => 'Payment Pending'];
                    $statusText = $statusMap[$s['status']] ?? $s['status'];
                ?>
                <tr class="invoice-row" data-project-id="<?= (int) ($s['project_id'] ?? 0) ?>">
                    <td><strong><?= esc($s['invoice_no'] ?: 'Sale #' . $s['id']) ?></strong><div class="invoice-row-sub"><?= esc($s['project_name'] ?: '-') ?></div></td>
                    <td class="text-end"><?= number_format($s['total_amount'], 2) ?></td>
                    <td class="text-end text-advance"><?= number_format($s['advance_applied'], 2) ?></td>
                    <td class="text-end text-paid"><?= number_format($s['paid_amount'], 2) ?></td>
                    <td class="text-end text-pending"><?= number_format($pending, 2) ?></td>
                    <td><span class="badge-status badge-<?= strtolower($s['status']) ?>"><?= esc($statusText) ?></span></td>
                    <td class="text-center">
                        <button type="button" class="btn-save btn-sm record-payment-btn"
                            data-sale-id="<?= $s['id'] ?>"
                            <?= $canPay ? '' : 'disabled' ?>>
                            <i class="bi bi-cash-coin"></i> Record Payment
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                <tr id="noRowsMsg" style="display:none"><td colspan="7" class="text-center text-muted">No invoices available for this project.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Release 2.1E: Step 3 — Payment Entry. Card stays hidden until an invoice
     is selected (Bug 3); fields/IDs/validation unchanged. -->
<div class="card-custom" id="step3Card" style="display:none">
    <div class="card-custom-header">
        Step 3 — Payment Information
        <span id="selectedInvoiceLabel" class="step3-invoice-label"></span>
    </div>
    <div class="card-custom-body">
        <form action="<?= base_url('payments/store') ?>" method="POST">
            <select name="sale_id" id="saleSelect" class="form-control no-search" style="display:none" required>
                <option value="">-- Select Sale --</option>
                <?php foreach ($sales as $s): ?>
                <option value="<?= $s['id'] ?>" <?= ($selected_sale_id == $s['id']) ? 'selected' : '' ?>
                    data-total="<?= $s['total_amount'] ?>"
                    data-advance="<?= $s['advance_applied'] ?>"
                    data-paid="<?= $s['paid_amount'] ?>"
                    data-pending="<?= $s['balance_amount'] ?>">
                    <?= esc($s['invoice_no'] ?: 'Sale #' . $s['id']) ?> — <?= esc($s['project_name']) ?> — Invoice Pending: <?= number_format($s['balance_amount'], 2) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <div id="saleDetail" style="display:none" class="pay-summary-row">
                <div class="pay-chip">
                    <span class="pay-chip-label">Invoice Total</span>
                    <span class="pay-chip-value" id="detTotal">0.00</span>
                </div>
                <div class="pay-chip">
                    <span class="pay-chip-label">Advance Applied</span>
                    <span class="pay-chip-value text-advance" id="detAdvance">0.00</span>
                </div>
                <div class="pay-chip">
                    <span class="pay-chip-label">Paid Amount</span>
                    <span class="pay-chip-value text-paid" id="detPaid">0.00</span>
                </div>
                <div class="pay-chip">
                    <span class="pay-chip-label">Invoice Pending</span>
                    <span class="pay-chip-value text-pending" id="detPending">0.00</span>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-section">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" name="amount" id="amountInput" class="form-control" step="0.01" min="0.01" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-section">
                        <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-section">
                        <label class="form-label">Method <span class="text-danger">*</span></label>
                        <select name="method" class="form-control" required>
                            <option value="CASH">Cash</option>
                            <option value="BANK_TRANSFER">Bank Transfer</option>
                            <option value="CHECK">Check</option>
                            <option value="OTHER">Other</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Reference No</label>
                        <input type="text" name="reference" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control pay-notes"></textarea>
                    </div>
                </div>
            </div>
            <div class="mt-2 text-end">
                <button type="submit" class="btn-save"><i class="bi bi-save"></i> Record Payment</button>
                <a href="<?= base_url('payments') ?>" class="btn-cancel ms-2"><i class="bi bi-x"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>

<style>
.invoice-table th, .invoice-table td { vertical-align: middle; font-size: 0.78rem; padding: 6px 10px; }
.invoice-row-sub { font-size: 0.7rem; color: #64748b; }
.invoice-row.is-selected { background: #eff6ff; box-shadow: inset 3px 0 0 #2563eb, inset 0 0 0 1px #2563eb; outline: 1px solid #93c5fd; outline-offset: -1px; }
.invoice-table .badge-partial { background: #ffedd5; color: #c2410c; }
.invoice-placeholder { text-align: center; padding: 24px 20px; color: #64748b; }
.invoice-placeholder-icon { font-size: 1.6rem; color: #94a3b8; margin-bottom: 6px; }
.invoice-placeholder-title { font-size: 0.9rem; font-weight: 600; color: #334155; }
.invoice-placeholder-sub { font-size: 0.78rem; margin-top: 2px; }
.text-advance { color: #2563eb; }
.text-paid { color: #16a34a; }
.text-pending { color: #ea580c; font-weight: 600; }
.badge-status { padding: 2px 8px; font-size: 0.68rem; border-radius: 10px; }
.invoice-table .btn-sm { padding: 3px 10px; font-size: 0.72rem; }
.step3-invoice-label { float: right; font-weight: 500; font-size: 0.78rem; color: #2563eb; }
.pay-summary-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
.pay-chip { flex: 1; min-width: 130px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 10px; }
.pay-chip-label { display: block; font-size: 0.65rem; text-transform: uppercase; letter-spacing: .03em; color: #64748b; }
.pay-chip-value { display: block; font-size: 0.95rem; font-weight: 700; color: #1e293b; }
.pay-notes { min-height: 38px !important; }
</style>

<?= $this->section('scripts') ?>
<!-- Release 2.1F (Phase A root cause): loaded here, in the 'scripts' section,
     which layouts/main.php renders AFTER jQuery/Select2 <script src> tags.
     The previous inline block lived in the 'content' section instead, which
     renders BEFORE jQuery/Select2 load — every $() call there threw
     "$ is not defined" and silently aborted the whole block, so project
     select, invoice filtering and Step 3 never worked no matter how the
     logic itself was written. -->
<script src="<?= base_url('assets/js/payments-workflow.js') ?>"></script>
<script>
$(document).ready(function() {
    // Release 2.1F (Phase 4): Create and Edit now share one implementation
    // (assets/js/payments-workflow.js) instead of two near-duplicate blocks.
    initPaymentsWorkflow();
});
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
