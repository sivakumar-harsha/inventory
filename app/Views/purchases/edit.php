<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
	.project-qty-input.is-invalid {
		border-color: #dc3545 !important;
	}
</style>

<div class="page-title">
    <span><i class="bi bi-pencil-square me-2"></i>Edit Purchase — <?= esc($purchase['invoice_no'] ?: 'PO #' . $purchase['id']) ?></span>
    <a href="<?= base_url('purchases') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<form action="<?= base_url('purchases/update/' . $purchase['id']) ?>" method="POST" id="purchaseForm">

<div class="card-custom mb-3">
    <div class="card-custom-header">Purchase Details</div>
    <div class="card-custom-body">
        <div class="row">
            <div class="col-md-4">
                <div class="form-section">
                    <label class="form-label">Supplier <span class="text-danger">*</span></label>
                    <select name="supplier_id" class="form-control" required>
                        <option value="">-- Select Supplier --</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $purchase['supplier_id'] == $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-section">
                    <label class="form-label">Project <span class="text-danger">*</span></label>
                    <select name="project_id" class="form-control" required>
                        <option value="">-- Select Project --</option>
                        <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $purchase['project_id'] == $p['id'] ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-section">
                    <label class="form-label">Purchase Date <span class="text-danger">*</span></label>
                    <input type="date" name="purchase_date" class="form-control" value="<?= esc($purchase['purchase_date']) ?>" required>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="form-section">
                    <label class="form-label">Invoice No</label>
                    <input type="text" name="invoice_no" class="form-control" value="<?= esc($purchase['invoice_no']) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-section">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="<?= esc($purchase['notes']) ?>">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card-custom mb-3">
    <div class="card-custom-header">
        Purchase Items
        <button type="button" class="btn-save" id="addItemBtn" style="float:right;margin-top:-4px"><i class="bi bi-plus"></i> Add Item</button>
    </div>
    <div class="card-custom-body">
        <div id="itemsContainer"></div>
        <div class="total-row">
            <span class="total-label">Taxable Amount:</span>
            <span class="total-value">₹<span id="taxableAmount">0.00</span></span>
        </div>
        <div class="total-row">
            <span class="total-label">GST Amount:</span>
            <span class="total-value">₹<span id="gstTotal">0.00</span></span>
        </div>
        <div class="total-row">
            <span class="total-label">Grand Total:</span>
            <span class="total-value">₹<span id="grandTotal">0.00</span></span>
        </div>
    </div>
</div>

<div class="mb-4">
    <button type="submit" class="btn-save"><i class="bi bi-save"></i> Update Purchase</button>
    <a href="<?= base_url('purchases/view/' . $purchase['id']) ?>" class="btn-cancel ms-2"><i class="bi bi-x"></i> Cancel</a>
</div>

</form>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/gst-calc.js') ?>"></script>
<script>
var products      = <?= json_encode($products) ?>;
var existingItems = <?= json_encode($items) ?>;
var itemCount = 0;

function buildProductOptions(selectedId) {
    var opts = '<option value="">-- Select Product --</option>';
    products.forEach(function (p) {
        var sel = (selectedId !== undefined && selectedId !== '' && parseInt(p.id) === parseInt(selectedId)) ? ' selected' : '';
        opts += '<option value="' + p.id + '" data-price="' + (p.selling_price || 0) + '" data-gst="' + (p.gst_percent || 0) + '"' + sel + '>'
              + p.name + '</option>';
    });
    return opts;
}

function addItem(prefill) {
    itemCount++;
    var selectedId   = prefill ? prefill.product_id  : '';
    var prefillQty   = prefill ? prefill.quantity     : '';
    var prefillPrice = prefill ? prefill.unit_price   : '';

    var gst = 0;
    if (prefill) {
        var prod = products.find(function (p) { return p.id == prefill.product_id; });
        gst = prod ? (prod.gst_percent || 0) : 0;
    }
    var gstApp = (prefill && prefill.gst_applicable !== undefined) ? parseInt(prefill.gst_applicable) : 1;
    var gstAppWith    = (gstApp === 1) ? ' selected' : '';
    var gstAppWithout = (gstApp === 0) ? ' selected' : '';

    var lineResult = gstCalculateLine(
        prefill ? prefill.quantity : 0,
        prefill ? prefill.unit_price : 0,
        gst,
        gstApp === 1
    );
    var lineVal = lineResult.total.toFixed(2);

    var prefillProjectQty = (prefill && prefill.project_qty !== undefined && prefill.project_qty !== null)
        ? prefill.project_qty : prefillQty;
    var prefillGeneralQty = Math.max(0, (parseInt(prefillQty) || 0) - (parseInt(prefillProjectQty) || 0));

    var html = '<div class="item-row" id="item-' + itemCount + '">' +
        '<span class="row-num">Item #' + itemCount + '</span>' +
        '<button type="button" class="remove-item" onclick="removeItem(' + itemCount + ')" title="Remove item"><i class="bi bi-x-circle-fill"></i></button>' +
        '<div class="row">' +
            '<div class="col-md-3">' +
                '<div class="form-section">' +
                    '<label class="form-label">Product</label>' +
                    '<select name="items[' + itemCount + '][product_id]" class="form-control product-select" required>' + buildProductOptions(selectedId) + '</select>' +
                '</div>' +
            '</div>' +
            '<div class="col-md-2">' +
                '<div class="form-section">' +
                    '<label class="form-label">Purchased Qty</label>' +
                    '<input type="number" name="items[' + itemCount + '][quantity]" class="form-control qty-input" min="1" value="' + prefillQty + '" required>' +
                '</div>' +
            '</div>' +
            '<div class="col-md-2">' +
                '<div class="form-section">' +
                    '<label class="form-label">Unit Price</label>' +
                    '<input type="number" name="items[' + itemCount + '][unit_price]" class="form-control price-input" step="0.01" min="0" value="' + prefillPrice + '" required>' +
                '</div>' +
            '</div>' +
            '<div class="col-md-1">' +
                '<div class="form-section">' +
                    '<label class="form-label">GST %</label>' +
                    '<input type="text" class="form-control gst-percent" readonly>' +
                '</div>' +
            '</div>' +
            '<div class="col-md-2">' +
                '<div class="form-section">' +
                    '<label class="form-label">GST</label>' +
                    '<select name="items[' + itemCount + '][gst_applicable]" class="form-control gst-applicable">' +
                        '<option value="1"' + gstAppWith + '>With GST</option>' +
                        '<option value="0"' + gstAppWithout + '>Without GST</option>' +
                    '</select>' +
                '</div>' +
            '</div>' +
            '<div class="col-md-2">' +
                '<div class="form-section">' +
                    '<label class="form-label">Line Total</label>' +
                    '<input type="text" class="form-control line-total" readonly value="' + lineVal + '">' +
                '</div>' +
            '</div>' +
        '</div>' +
        '<div class="row">' +
            '<div class="col-md-3">' +
                '<div class="form-section">' +
                    '<label class="form-label">Project Qty</label>' +
                    '<input type="number" name="items[' + itemCount + '][project_qty]" class="form-control project-qty-input" min="0" value="' + prefillProjectQty + '" required>' +
                '</div>' +
            '</div>' +
            '<div class="col-md-3">' +
                '<div class="form-section">' +
                    '<label class="form-label">General Qty</label>' +
                    '<input type="number" class="form-control general-qty-display" readonly value="' + prefillGeneralQty + '">' +
                '</div>' +
            '</div>' +
            '<div class="col-md-6">' +
                '<div class="form-section">' +
                    '<span class="allocation-hint text-muted"></span>' +
                    '<span class="allocation-error text-danger" style="display:none">Project Qty cannot exceed Purchased Qty.</span>' +
                '</div>' +
            '</div>' +
        '</div>' +
    '</div>';

    $('#itemsContainer').append(html);
    bindRowEvents(itemCount);

    var row = $('#item-' + itemCount);
    var selected = row.find('.product-select').find(':selected');
    var selGst = parseFloat(selected.attr('data-gst')) || 0;
    row.find('.gst-percent').val(selGst + '%');

    if (prefill) {
        row.data('allocationTouched', true);
        row.data('prefilled', true);
    }
    calcAllocation(row);
    calcTotal();
}

function removeItem(num) {
    $('#item-' + num).remove();
    calcTotal();
}

function bindRowEvents(num) {
    var row = $('#item-' + num);

    row.find('.product-select').on('change', function () {
        var selected = $(this).find(':selected');
        var price = parseFloat(selected.attr('data-price')) || 0;
        var gst = parseFloat(selected.attr('data-gst')) || 0;
        row.find('.gst-percent').val(gst + '%');
        if (!row.data('prefilled')) {
            row.find('.price-input').val(price.toFixed(2));
        }
        calcLineTotal(row);
    });

    row.find('.qty-input, .price-input').on('input', function () {
        calcLineTotal(row);
        calcAllocation(row);
    });

    row.find('.gst-applicable').on('change', function () {
        calcLineTotal(row);
    });

    row.find('.project-qty-input').on('input', function () {
        row.data('allocationTouched', true);
        calcAllocation(row);
    });

    calcAllocation(row);
}

function calcLineTotal(row) {
    var qty   = parseFloat(row.find('.qty-input').val()) || 0;
    var price = parseFloat(row.find('.price-input').val()) || 0;
    var selected = row.find('.product-select').find(':selected');
    var gst = parseFloat(selected.attr('data-gst')) || 0;
    var gstApplicable = row.find('.gst-applicable').val() === '1';

    var result = gstCalculateLine(qty, price, gst, gstApplicable);
    row.find('.line-total').val(result.total.toFixed(2));
    calcTotal();
}

function calcAllocation(row) {
    var qty = parseInt(row.find('.qty-input').val()) || 0;
    var projectInput = row.find('.project-qty-input');
    var projectQty = parseInt(projectInput.val()) || 0;
    var errorEl = row.find('.allocation-error');
    var exceeded = row.data('allocationTouched') && projectQty > qty;

    if (!row.data('allocationTouched')) {
        projectQty = qty;
        projectInput.val(qty);
    } else if (projectQty > qty) {
        projectQty = qty;
        projectInput.val(qty);
    } else if (projectQty < 0) {
        projectQty = 0;
        projectInput.val(0);
    }

    if (exceeded) {
        errorEl.show();
        projectInput.addClass('is-invalid');
    } else {
        errorEl.hide();
        projectInput.removeClass('is-invalid');
    }

    projectInput.attr('max', qty);
    var generalQty = Math.max(0, qty - projectQty);
    row.find('.general-qty-display').val(generalQty);

    var hint = row.find('.allocation-hint');
    if (qty === 0) {
        hint.text('');
    } else if (generalQty === 0) {
        hint.text('100% allocated to this project.');
    } else {
        hint.text('Project: ' + projectQty + ' · General (warehouse): ' + generalQty);
    }
}

function calcTotal() {
    var lines = [];
    $('.item-row').each(function () {
        var row = $(this);
        var qty   = parseFloat(row.find('.qty-input').val()) || 0;
        var price = parseFloat(row.find('.price-input').val()) || 0;
        var gst   = parseFloat(row.find('.product-select').find(':selected').attr('data-gst')) || 0;
        var gstApplicable = row.find('.gst-applicable').val() === '1';
        lines.push(gstCalculateLine(qty, price, gst, gstApplicable));
    });
    var summary = gstSummarize(lines);
    $('#taxableAmount').text(summary.taxable.toFixed(2));
    $('#gstTotal').text(summary.gst.toFixed(2));
    $('#grandTotal').text(summary.grandTotal.toFixed(2));
}

// Pre-load existing items
$(document).ready(function() {
    if (existingItems.length > 0) {
        existingItems.forEach(function(item) {
            addItem(item);
            // Mark the just-added row as prefilled so price is not overwritten by change event
            $('#item-' + itemCount).data('prefilled', true);
        });
    } else {
        addItem();
    }
    calcTotal();
});

$(document).on('click', '#addItemBtn', function() {
    addItem();
});
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
