<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-title">
    <span><i class="bi bi-cash me-2"></i>Record Payment</span>
    <a href="<?= base_url('payments') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<!-- Release 4.5.4 (Phase B): Payment Type selector — Invoice Payment (existing
     workflow, unchanged) vs Project Cash Receipt (simplified form, Phase D).
     Default = Invoice Payment. Segmented buttons, same visual language as the
     app's existing status-toggle pills. -->
<div class="card-custom mb-2">
    <div class="card-custom-body">
        <label class="form-label d-block mb-1">Payment Type</label>
        <div class="payment-type-toggle" id="paymentTypeToggle" role="group" aria-label="Payment Type">
            <button type="button" class="ptype-btn active" data-type="invoice" aria-pressed="true">
                <i class="bi bi-receipt"></i> Invoice Payment
            </button>
            <button type="button" class="ptype-btn" data-type="cash" aria-pressed="false">
                <i class="bi bi-piggy-bank"></i> Project Cash Receipt
            </button>
        </div>
    </div>
</div>

<!-- Release 4.5.4 (Phase C): Invoice Payment mode — exactly the pre-4.5.4
     workflow (Steps 1-3), just wrapped in a container the Payment Type
     toggle can show/hide. Nothing inside changed. -->
<div id="invoicePaymentMode">

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
                    data-pending="<?= $s['balance_amount'] ?>"
                    data-project-id="<?= (int) ($s['project_id'] ?? 0) ?>">
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
                <div class="pay-chip">
                    <span class="pay-chip-label">Project Cash Received</span>
                    <span class="pay-chip-value text-cash" id="detCashReceived">0.00</span>
                </div>
                <div class="pay-chip">
                    <span class="pay-chip-label">Net Outstanding After Cash</span>
                    <span class="pay-chip-value" id="detNetOutstanding">0.00</span>
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

</div>
<!-- /#invoicePaymentMode -->

<!-- Release 4.5.4/4.5.5 (Phase D/E): Project Cash Receipt mode — simplified
     form, no invoice selection, no advance allocation. This is now the only
     entry point into Project Cash Receipt — submits straight to
     ProjectCashReceipts::store(), so saving only ever writes to
     project_cash_receipts, never to payments. -->
<div id="cashReceiptMode" class="pcr-hidden">
    <div class="card-custom mb-2">
        <div class="card-custom-header">Project Cash Receipt</div>
        <div class="card-custom-body">
            <form action="<?= base_url('project-cash-receipts/store') ?>" method="POST">
                <div class="form-section">
                    <label class="form-label">Project <span class="text-danger">*</span></label>
                    <select name="project_id" id="cashProjectSelect" class="form-control" required>
                        <option value=""></option>
                        <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>" data-customer-name="<?= esc($p['customer_name'] ?? '') ?: 'No Customer' ?>">
                            <?= esc($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-section">
                    <label class="form-label">Customer</label>
                    <input type="text" id="cashCustomerDisplay" class="form-control" value="" readonly placeholder="Auto-filled from Project">
                </div>

                <!-- Release 4.6.5: Receipt Type — Advance (held against a future
                     invoice, excluded from revenue until invoiced) vs Direct
                     Income (no invoice will ever be raised, recognized as
                     revenue immediately). Defaults to Advance to match the
                     column's DB default and preserve pre-4.6.5 behavior unless
                     explicitly chosen otherwise. -->
                <div class="form-section">
                    <label class="form-label">Receipt Type <span class="text-danger">*</span></label>
                    <div class="payment-type-toggle" role="group" aria-label="Receipt Type">
                        <button type="button" class="ptype-btn rtype-btn" data-rtype="ADVANCE" aria-pressed="false">
                            <i class="bi bi-piggy-bank"></i> Advance
                        </button>
                        <button type="button" class="ptype-btn rtype-btn active" data-rtype="DIRECT_INCOME" aria-pressed="true">
                            <i class="bi bi-cash-coin"></i> Direct Income
                        </button>
                    </div>
                    <input type="hidden" name="receipt_type" id="cashReceiptType" value="DIRECT_INCOME">
                    <div class="pf-chip-sub-info" style="display:block;margin-top:4px;color:#64748b;font-weight:400">
                        Advance: held against a future invoice, not yet revenue. Direct Income: no invoice will ever be raised for this — recognized as revenue immediately.
                    </div>
                </div>

                <div id="cashSummary" class="pay-summary-row">
                    <div class="pay-chip">
                        <span class="pay-chip-label">Project Cash Received</span>
                        <span class="pay-chip-value text-cash" id="cashReceivedTotal">0.00</span>
                    </div>
                    <div class="pay-chip">
                        <span class="pay-chip-label">Remaining Balance After Receipt</span>
                        <span class="pay-chip-value" id="cashRemainingAfter">0.00</span>
                    </div>
                    <div class="pay-chip">
                        <span class="pay-chip-label">Customer Paid Total After Receipt</span>
                        <span class="pay-chip-value text-paid" id="cashCustomerPaidAfter">0.00</span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-section">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" name="amount" id="cashAmountInput" class="form-control" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-section">
                            <label class="form-label">Cash Receipt Date <span class="text-danger">*</span></label>
                            <input type="date" name="receipt_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-section">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" id="cashPaymentMethod" class="form-control" required>
                                <option value="CASH">Cash</option>
                                <option value="BANK_TRANSFER">Bank Transfer</option>
                                <option value="CHECK">Check</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-section">
                            <label class="form-label">Reference Number</label>
                            <input type="text" name="reference" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- Release 4.8.3D: shown only for Bank Transfer / Check; the chosen
                     account receives the automatic DEPOSIT. Cash and Other never
                     touch a bank account. Accounts are loaded here because the
                     Payments controller (which renders this page) isn't touched
                     by this release. -->
                <?php $cashBankAccounts = $bankAccounts ?? (new \App\Models\BankAccountModel())->where('is_active', 1)->orderBy('bank_name', 'ASC')->findAll(); ?>
                <div class="form-section pcr-hidden" id="cashBankAccountSection">
                    <label class="form-label">Bank Account <span class="text-danger">*</span></label>
                    <select name="bank_account_id" id="cashBankAccount" class="form-control">
                        <option value="">-- Select Bank Account --</option>
                        <?php foreach ($cashBankAccounts as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= esc($b['bank_name'] . ' - ' . $b['account_name']) ?> (<?= esc($b['account_number']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-section">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control pay-notes"></textarea>
                </div>

                <div class="mt-2 text-end">
                    <button type="submit" class="btn-save"><i class="bi bi-save"></i> Save Receipt</button>
                    <a href="<?= base_url('payments') ?>" class="btn-cancel ms-2"><i class="bi bi-x"></i> Cancel</a>
                </div>
            </form>
        </div>
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
.text-cash { color: #16a34a; }
.text-net-pending { color: #ea580c; font-weight: 600; }
.text-net-settled { color: #16a34a; font-weight: 600; }
.badge-status { padding: 2px 8px; font-size: 0.68rem; border-radius: 10px; }
.invoice-table .btn-sm { padding: 3px 10px; font-size: 0.72rem; }
.step3-invoice-label { float: right; font-weight: 500; font-size: 0.78rem; color: #2563eb; }
.pay-summary-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
.pay-chip { flex: 1; min-width: 130px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 10px; }
.pay-chip-label { display: block; font-size: 0.65rem; text-transform: uppercase; letter-spacing: .03em; color: #64748b; }
.pay-chip-value { display: block; font-size: 0.95rem; font-weight: 700; color: #1e293b; }
.pay-notes { min-height: 38px !important; }

/* Release 4.5.4 (Phase B): Payment Type segmented control. */
.payment-type-toggle { display: inline-flex; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.ptype-btn { border: none; background: #f8fafc; color: #475569; padding: 8px 18px; font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: 0.15s; }
.ptype-btn + .ptype-btn { border-left: 1px solid #e2e8f0; }
.ptype-btn.active { background: #2F7E8A; color: #fff; }
.ptype-btn:hover:not(.active) { background: #eef2f5; }

/* Release 4.5.7: !important guards the Invoice Payment / Cash Receipt mode
   toggle against any other rule's display value ever winning by specificity. */
.pcr-hidden { display: none !important; }
</style>

<?= $this->section('scripts') ?>
<!-- Release 2.1F (Phase A root cause): loaded here, in the 'scripts' section,
     which layouts/main.php renders AFTER jQuery/Select2 <script src> tags.
     The previous inline block lived in the 'content' section instead, which
     renders BEFORE jQuery/Select2 load — every $() call there threw
     "$ is not defined" and silently aborted the whole block, so project
     select, invoice filtering and Step 3 never worked no matter how the
     logic itself was written. -->

<script>
// Release 4.5.3/4.5.4: { project_id: { cash_received, net_outstanding } } —
// built server-side from ProjectModel::getFinancialSummary()
// (Payments::buildProjectFinancialsMap()), so both the Invoice Payment mode's
// Step 3 cards and the Project Cash Receipt mode's summary cards read the
// same figures as Dashboard/Statement/Balance Sheet.
var PROJECT_FINANCIALS_MAP = <?= json_encode($project_financials) ?>;
</script>
<script src="<?= base_url('assets/js/payments-workflow.js') ?>"></script>
<script>
$(document).ready(function() {
    // Release 2.1F (Phase 4): Create and Edit now share one implementation
    // (assets/js/payments-workflow.js) instead of two near-duplicate blocks.
    initPaymentsWorkflow();
    // Release 4.5.4 (Phase B/D): Payment Type toggle + Cash Receipt mode
    // preview — no-ops on payments/edit.php, which has neither element.
    initPaymentTypeToggle();

    // Release 4.6.5: Receipt Type toggle (Advance / Direct Income) for the
    // Project Cash Receipt form — mirrors the Payment Type toggle's own
    // active/hidden-input pattern, kept separate since it posts a different
    // field (receipt_type) to a different endpoint.
    $('.rtype-btn').on('click', function () {
        $('.rtype-btn').removeClass('active').attr('aria-pressed', 'false');
        $(this).addClass('active').attr('aria-pressed', 'true');
        $('#cashReceiptType').val($(this).data('rtype'));
    });

    // Release 4.6.5.4: explicit initial state on page load, matching the
    // view's own default active button/hidden input value — Direct Income,
    // not Advance. Idempotent against the HTML default above; kept here so
    // the default is asserted in one place rather than relying solely on
    // markup.
    $('.rtype-btn').removeClass('active').attr('aria-pressed', 'false');
    $('.rtype-btn[data-rtype="DIRECT_INCOME"]').addClass('active').attr('aria-pressed', 'true');
    $('#cashReceiptType').val('DIRECT_INCOME');

    // Release 4.8.3D: Bank Account applies to Bank Transfer / Check only. The
    // hidden select isn't required (and is cleared) so Cash/Other still save.
    function toggleCashBankAccount() {
        var bank = ['BANK_TRANSFER', 'CHECK'].indexOf($('#cashPaymentMethod').val()) !== -1;
        $('#cashBankAccountSection').toggleClass('pcr-hidden', !bank);
        $('#cashBankAccount').prop('required', bank);
        if (!bank) { $('#cashBankAccount').val(''); }
    }
    $('#cashPaymentMethod').on('change', toggleCashBankAccount);
    toggleCashBankAccount();
});
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
