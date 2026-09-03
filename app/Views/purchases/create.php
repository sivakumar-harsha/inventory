<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-title">
    <span><i class="bi bi-cart-plus me-2"></i>New Purchase</span>
    <a href="<?= base_url('purchases') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<form action="<?= base_url('purchases/store') ?>" method="POST" id="purchaseForm">

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
                        <option value="<?= $s['id'] ?>"><?= esc($s['name']) ?></option>
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
                        <option value="<?= $p['id'] ?>"><?= esc($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-section">
                    <label class="form-label">Purchase Date <span class="text-danger">*</span></label>
                    <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
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
    <button type="submit" class="btn-save"><i class="bi bi-save"></i> Save Purchase</button>
    <a href="<?= base_url('purchases') ?>" class="btn-cancel ms-2"><i class="bi bi-x"></i> Cancel</a>
</div>

</form>

<!-- PRODUCT MODAL -->
<div class="modal fade" id="productModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Add Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="productForm">
                    <div class="form-section">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="form-section">
                        <label>Description</label>
                        <textarea name="description" class="form-control"></textarea>
                    </div>

                    <div class="form-section">
                        <label>Unit</label>
                        <input type="text" name="unit" class="form-control" required>
                    </div>

                    <div class="form-section">
                        <label>HSN</label>
                        <input type="text" name="hsn_code" class="form-control">
                    </div>

                    <div class="form-section">
                        <label>GST %</label>
                        <input type="number" name="gst_percent" class="form-control">
                    </div>

                    <div class="form-section">
                        <label>Selling Price</label>
                        <input type="number" name="selling_price" class="form-control" step="0.01">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="saveProduct()">Save</button>
            </div>
        </div>
    </div>
</div>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/gst-calc.js') ?>"></script>
<script>
var products = <?= json_encode($products) ?>;
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

function openProductModal() {
    $('#productModal').modal('show');
}

function saveProduct() {
    $.ajax({
        url: "<?= base_url('products/store') ?>",
        type: "POST",
        data: $('#productForm').serialize(),
        success: function(res) {

            products.push({
                id: res.id,
                name: res.name,
                selling_price: res.selling_price ? res.selling_price : 0,
                gst_percent: res.gst_percent ? res.gst_percent : 0,
                unit: res.unit ? res.unit : ''
            });

            $('.product-select').each(function() {
                $(this).append(
                    '<option value="' + res.id + '" data-price="' + (res.selling_price ? res.selling_price : 0) + '" data-gst="' + (res.gst_percent ? res.gst_percent : 0) + '">' + res.name + '</option>'
                );
            });

            var lastSelect = $('.product-select').last();
            lastSelect.val(res.id).trigger('change');

            $('#productModal').modal('hide');
            $('#productForm')[0].reset();
        }
    });
}

function addItem() {
    itemCount++;

    var html = '<div class="item-row" id="item-' + itemCount + '">' +
        '<span class="row-num">Item #' + itemCount + '</span>' +
        '<button type="button" class="remove-item" onclick="removeItem(' + itemCount + ')" title="Remove item"><i class="bi bi-x-circle-fill"></i></button>' +
        '<div class="row">' +
            '<div class="col-md-3">' +
                '<div class="form-section">' +
                    '<label class="form-label">Product</label>' +
                    '<div style="display:flex;gap:6px;align-items:center;">' +
                    '<select name="items[' + itemCount + '][product_id]" class="form-control product-select" required>' + buildProductOptions() + '</select>' +
                    '<button type="button" class="btn btn-sm btn-primary" onclick="openProductModal()">+Add</button>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="col-md-2">' +
                '<div class="form-section">' +
                    '<label class="form-label">Purchased Qty</label>' +
                    '<input type="number" name="items[' + itemCount + '][quantity]" class="form-control qty-input" min="1" required>' +
                '</div>' +
            '</div>' +
            '<div class="col-md-2">' +
                '<div class="form-section">' +
                    '<label class="form-label">Unit Price</label>' +
                    '<input type="number" name="items[' + itemCount + '][unit_price]" class="form-control price-input" step="0.01" min="0" required>' +
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
                        '<option value="1">With GST</option>' +
                        '<option value="0">Without GST</option>' +
                    '</select>' +
                '</div>' +
            '</div>' +
            '<div class="col-md-2">' +
                '<div class="form-section">' +
                    '<label class="form-label">Line Total</label>' +
                    '<input type="text" class="form-control line-total" readonly value="0.00">' +
                '</div>' +
            '</div>' +
        '</div>' +
        '<div class="row">' +
            '<div class="col-md-3">' +
                '<div class="form-section">' +
                    '<label class="form-label">Project Qty</label>' +
                    '<input type="number" name="items[' + itemCount + '][project_qty]" class="form-control project-qty-input" min="0" value="0" required>' +
                '</div>' +
            '</div>' +
            '<div class="col-md-3">' +
                '<div class="form-section">' +
                    '<label class="form-label">General Qty</label>' +
                    '<input type="number" class="form-control general-qty-display" readonly value="0">' +
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
    calcAllocation(row);
    calcTotal();
}

function removeItem(num) {
    $('#item-' + num).remove();
    calcTotal();
}

function bindRowEvents(num) {
    var row = $('#item-' + num);

    row.find('.product-select').select2({
        width: 'resolve',
        placeholder: 'Select Product'
    });

    row.find('.product-select').on('change', function () {
        var selected = $(this).find(':selected');
        var price = parseFloat(selected.attr('data-price')) || 0;
        var gst = parseFloat(selected.attr('data-gst')) || 0;
        row.find('.gst-percent').val(gst + '%');
        row.find('.price-input').val(price.toFixed(2));
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

$(document).on('click', '#addItemBtn', function() {
    addItem();
});
addItem();
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
