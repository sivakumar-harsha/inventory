<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?= $this->include('sales/_item_row_style') ?>

<style>
	#projectFinancialCard.pf-compact .card-custom-header { padding: 5px 14px; font-size: 0.82rem; }
	#projectFinancialCard.pf-compact .card-custom-body { padding: 6px 14px; }
	#projectFinancialCard.pf-compact .pf-chip {
		display: flex;
		align-items: center;
		gap: 8px;
		height: 52px;
		background: #f8fafc;
		border: 1px solid var(--border-color);
		border-radius: 8px;
		padding: 0 10px;
	}
	#projectFinancialCard.pf-compact .pf-chip-icon { font-size: 1rem; color: var(--primary); flex: 0 0 auto; line-height: 1; }
	#projectFinancialCard.pf-compact .pf-chip-body { display: flex; flex-direction: column; justify-content: center; line-height: 1.15; overflow: hidden; min-width: 0; flex: 1 1 auto; }
	#projectFinancialCard.pf-compact .pf-chip-label { font-size: 0.63rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .03em; }
	#projectFinancialCard.pf-compact .pf-chip-value { font-size: 1rem; font-weight: 700; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-variant-numeric: tabular-nums; }
	#projectFinancialCard.pf-compact .pf-chip-sub { font-size: 0.7rem; font-weight: 600; color: var(--text-muted); white-space: nowrap; }
	/* Remaining Balance state (default, remaining > 0): blue */
	#projectFinancialCard.pf-compact .pf-chip-remaining { background: #eff6ff; border-color: #bfdbfe; }
	#projectFinancialCard.pf-compact .pf-icon-remaining { color: #2563eb; font-size: 1.1rem; }
	#projectFinancialCard.pf-compact .pf-chip-value-remaining { font-size: 1.15rem; font-weight: 800; color: #1d4ed8; }
	/* Fully Billed state (remaining == 0): green */
	#projectFinancialCard.pf-compact .pf-chip-remaining.pf-chip-full { background: #f0fdf4; border-color: #bbf7d0; }
	#projectFinancialCard.pf-compact .pf-chip-remaining.pf-chip-full .pf-icon-remaining { color: #16a34a; }
	#projectFinancialCard.pf-compact .pf-chip-remaining.pf-chip-full .pf-chip-value-remaining { color: #15803d; }
	/* Over Billed state (remaining < 0): red */
	#projectFinancialCard.pf-compact .pf-chip-remaining.pf-chip-exceeded { background: #fef2f2; border-color: #fecaca; }
	#projectFinancialCard.pf-compact .pf-chip-remaining.pf-chip-exceeded .pf-icon-remaining { color: #b91c1c; }
	#projectFinancialCard.pf-compact .pf-chip-remaining.pf-chip-exceeded .pf-chip-value-remaining { color: #b91c1c; }
	#projectFinancialCard.pf-compact .pf-chip-sub-info { font-size: 0.7rem; font-weight: 600; color: #b91c1c; }
</style>

<div class="page-title">
    <span><i class="bi bi-receipt me-2"></i>New Sale</span>
    <a href="<?= base_url('sales') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<form action="<?= base_url('sales/store') ?>" method="POST" id="saleForm">

<div class="card-custom mb-3">
    <div class="card-custom-header">Sale Details</div>
    <div class="card-custom-body">
        <div class="row">
            <div class="col-md-4">
                <div class="form-section">
                    <label class="form-label">Project <span class="text-danger">*</span></label>
                    <select name="project_id" id="projectSelect" class="form-control" required>
                        <option value="">-- Select Project --</option>
                        <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= esc($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-section">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-control">
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-section">
                    <label class="form-label">Sale Date <span class="text-danger">*</span></label>
                    <input type="date" name="sale_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="form-section">
                    <label class="form-label">Invoice No</label>
                    <input type="text" name="invoice_no" class="form-control">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-section">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control">
                </div>
            </div>
        </div>

        <div id="stockNotice" style="display:none" class="alert alert-info">
            <i class="bi bi-info-circle-fill me-2"></i>
            <span id="stockNoticeText"></span>
        </div>

        <!-- Release 4.6.5: informational only — never blocks invoice creation.
             Shown when the selected project already has Direct Project Income
             recorded, so the double-counting risk (raising an invoice for
             cash already recognized as revenue) is visible up front. -->
        <div id="directIncomeNotice" style="display:none" class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <span id="directIncomeNoticeText"></span>
        </div>
    </div>
</div>

<div class="card-custom mb-3 pf-compact" id="projectFinancialCard" style="display:none">
    <div class="card-custom-header"><i class="bi bi-graph-up me-2"></i>Project Financial Summary</div>
    <div class="card-custom-body">
        <div class="row row-cols-2 row-cols-lg-4 g-2">
            <div class="col">
                <div class="pf-chip">
                    <span class="pf-chip-icon"><i class="bi bi-folder-fill"></i></span>
                    <span class="pf-chip-body">
                        <span class="pf-chip-label">Project Value</span>
                        <span class="pf-chip-value" id="pfTotalValue">-</span>
                    </span>
                </div>
            </div>
            <div class="col">
                <div class="pf-chip">
                    <span class="pf-chip-icon"><i class="bi bi-journal-check"></i></span>
                    <span class="pf-chip-body">
                        <span class="pf-chip-label">Already Invoiced</span>
                        <span class="pf-chip-value" id="pfAlreadyInvoiced">0.00</span>
                    </span>
                </div>
            </div>
            <div class="col">
                <div class="pf-chip pf-chip-remaining" id="pfRemainingChip">
                    <span class="pf-chip-icon pf-icon-remaining"><i class="bi bi-wallet2"></i></span>
                    <span class="pf-chip-body">
                        <span class="pf-chip-label" id="pfRemainingLabel">Remaining Balance</span>
                        <span class="pf-chip-value pf-chip-value-remaining" id="pfRemainingBalance">0.00</span>
                        <span class="pf-chip-sub-info" id="pfRemainingWarning" style="display:none">Project billing exceeds the original project value.</span>
                    </span>
                </div>
            </div>
            <div class="col">
                <div class="pf-chip">
                    <span class="pf-chip-icon"><i class="bi bi-receipt"></i></span>
                    <span class="pf-chip-body">
                        <span class="pf-chip-label">This Invoice</span>
                        <span class="pf-chip-value" id="pfInvoiceTotal">0.00</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card-custom mb-3">
    <div class="card-custom-header">
        Sale Items
        <button type="button" class="btn-save" id="addItemBtn" style="float:right;margin-top:-4px">
            <i class="bi bi-plus"></i> Add Item
        </button>
    </div>
    <div class="card-custom-body">
        <div id="loadingMsg" style="display:none;color:#64748b;font-size:0.85rem"><i class="bi bi-arrow-repeat"></i> Loading available products...</div>
        <div id="itemsContainer"></div>
        <div class="pr-totals-strip">
            <span class="pr-total-item"><span class="total-label">Taxable</span> <span class="total-value">₹<span id="taxableAmount">0.00</span></span></span>
            <span class="pr-total-sep">+</span>
            <span class="pr-total-item"><span class="total-label">GST</span> <span class="total-value">₹<span id="gstTotal">0.00</span></span></span>
            <span class="pr-total-divider"></span>
            <span class="pr-grand-total"><span class="total-label">Grand Total</span> <span class="total-value">₹<span id="grandTotal">0.00</span></span></span>
        </div>
    </div>
</div>

<div class="mb-4">
    <button type="submit" id="saveSaleBtn" class="btn-save"><i class="bi bi-save"></i> Save Sale</button>
    <a href="<?= base_url('sales') ?>" class="btn-cancel ms-2"><i class="bi bi-x"></i> Cancel</a>
</div>

<!-- Release 4.8.6A-2: advance the accountant chose to apply (modal). 0 = none. -->
<input type="hidden" name="advance_to_apply" id="advanceToApply" value="0">

</form>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/gst-calc.js') ?>"></script>
<?= $this->include('sales/_project_financial_script') ?>
<script>
var ajaxBase      = '<?= base_url('sales/get-products') ?>';
var excludeSaleId = 0;

initProjectFinancialTracker('<?= base_url('sales/project-financial-summary') ?>', null);
</script>
<script src="<?= base_url('assets/js/sales-items.js') ?>"></script>
<script>
$('#projectSelect').on('change', function() {
    loadProjectFinancialSummary($(this).val());
});

$('#addItemBtn').on('click', function() {
    addItem();
});
</script>
<?= $this->setVar('advanceEditing', false)->setVar('advanceExcludeSaleId', 0)->setVar('advancePaidAmount', 0)->include('sales/_advance_modal') ?>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
