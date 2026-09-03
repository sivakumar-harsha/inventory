/**
 * Shared Sale Item row logic for sales/create.php and sales/edit.php.
 * Release 1.8B: extracted so both pages behave identically (Add Item, Remove Item,
 * stock-source-driven product AJAX, GST calc, footer totals) — mirrors the Release 1.8A
 * purchase-items.js pattern. No calculation changes — calcLineTotal()/calcTotal() formulas
 * and the AJAX contract (data-price/data-qty/data-gst) are unchanged from the pre-1.8B
 * create.php/edit.php implementations.
 *
 * Requires: jQuery, select2, gst-calc.js (gstCalculateLine/gstSummarize),
 * project-financial.js (updateFinancialPreview).
 * Requires globals set by the page before this file's calls are used: `ajaxBase`,
 * `excludeSaleId` (0 on create).
 */
var itemCount = 0;

// UI-only display threshold for the Low Stock badge (Release 1.8B.3). Not used anywhere in
// qty validation or stock/GST calculations — those still compare the raw available-stock
// value directly (see the qty-input handler in bindRowEvents).
var SL_LOW_STOCK_THRESHOLD = 5;

function slFormatQty(n) {
    n = parseFloat(n) || 0;
    return n.toFixed(3);
}

// Stock status badge (Release 1.8B.3): GENERAL stock rows always show a generic "GENERAL
// STOCK" badge; PROJECT stock rows show a qty-level badge since project stock is a scarce,
// per-project allocation. Purely a display label — computed from the same available-stock
// value already used (unchanged) for qty validation.
function slUpdateStatus(row) {
    var source = row.find('.item-source').val();
    var badge = row.find('.status-badge');
    badge.removeClass('badge-in-stock badge-low-stock badge-out-stock badge-general-stock');

    if (!source) {
        badge.text('').attr('title', '');
        return;
    }

    if (source === 'GENERAL') {
        badge.addClass('badge-general-stock').text('GENERAL STOCK').attr('title', 'GENERAL STOCK');
        return;
    }

    var avail = parseFloat(row.find('.available-stock').val()) || 0;
    if (avail <= 0) {
        badge.addClass('badge-out-stock').text('OUT OF STOCK').attr('title', 'OUT OF STOCK');
    } else if (avail <= SL_LOW_STOCK_THRESHOLD) {
        badge.addClass('badge-low-stock').text('LOW STOCK').attr('title', 'LOW STOCK');
    } else {
        badge.addClass('badge-in-stock').text('IN STOCK').attr('title', 'IN STOCK');
    }
}

function buildProductOptions() {
    return '<option value="">Select Source First</option>';
}

function addItem(prefill) {
    itemCount++;
    var num = itemCount;

    var srcGen = (prefill && prefill.stock_source === 'GENERAL') ? ' selected' : '';
    var srcPrj = (prefill && prefill.stock_source === 'PROJECT') ? ' selected' : '';

    var productOpt = prefill
        ? '<option value="' + prefill.product_id + '" selected>' +
          prefill.product_name + ' (' + (prefill.unit || '') + ')</option>'
        : buildProductOptions();

    var prefillQty   = prefill ? parseFloat(prefill.quantity).toFixed(3)   : '';
    var prefillPrice = prefill ? parseFloat(prefill.unit_price).toFixed(2) : '';
    var lineVal       = (prefill && prefill.quantity && prefill.unit_price)
                          ? (parseFloat(prefill.quantity) * parseFloat(prefill.unit_price)).toFixed(2)
                          : '0.00';

    var gstApp        = (prefill && prefill.gst_applicable !== undefined) ? parseInt(prefill.gst_applicable) : 1;
    var gstAppWith     = (gstApp === 1) ? ' selected' : '';
    var gstAppWithout  = (gstApp === 0) ? ' selected' : '';

    var html = '<div class="item-row pr-item-row" id="item-' + num + '">' +
        '<div class="pr-row1">' +
            '<div class="pr-field pr-field-source"><div class="form-section">' +
                '<label class="form-label">Stock Source</label>' +
                '<select name="items[' + num + '][stock_source]" class="form-control item-source" required>' +
                    '<option value="">Select</option>' +
                    '<option value="GENERAL"' + srcGen + '>GENERAL</option>' +
                    '<option value="PROJECT"' + srcPrj + '>PROJECT</option>' +
                '</select>' +
            '</div></div>' +
            '<div class="pr-field pr-field-product"><div class="form-section">' +
                '<label class="form-label">Product</label>' +
                '<select name="items[' + num + '][product_id]" class="form-control product-select" required>' + productOpt + '</select>' +
            '</div></div>' +
            '<div class="pr-field pr-field-avail"><div class="form-section">' +
                '<label class="form-label">Available Qty</label>' +
                '<div class="pr-avail-inline">' +
                    '<input type="text" class="form-control available-stock" readonly>' +
                    '<span class="allocation-badge status-badge"></span>' +
                '</div>' +
            '</div></div>' +
            '<div class="pr-field pr-field-qty"><div class="form-section">' +
                '<label class="form-label">Qty</label>' +
                '<input type="number" name="items[' + num + '][quantity]" class="form-control qty-input" value="' + prefillQty + '" required>' +
            '</div></div>' +
            '<div class="pr-field pr-field-price"><div class="form-section">' +
                '<label class="form-label">Unit Price</label>' +
                '<input type="number" name="items[' + num + '][unit_price]" class="form-control price-input" step="0.01" min="0" value="' + prefillPrice + '" required>' +
            '</div></div>' +
            '<div class="pr-field pr-field-gstpct"><div class="form-section">' +
                '<label class="form-label">GST %</label>' +
                '<input type="text" class="form-control gst-percent" readonly>' +
            '</div></div>' +
            '<div class="pr-field pr-field-gstapp"><div class="form-section">' +
                '<label class="form-label">GST</label>' +
                '<select name="items[' + num + '][gst_applicable]" class="form-control gst-applicable">' +
                    '<option value="1"' + gstAppWith + '>With GST</option>' +
                    '<option value="0"' + gstAppWithout + '>Without GST</option>' +
                '</select>' +
            '</div></div>' +
            '<div class="pr-field pr-field-total"><div class="form-section">' +
                '<label class="form-label">Line Total</label>' +
                '<input type="text" class="form-control line-total" readonly value="' + lineVal + '">' +
            '</div></div>' +
            '<div class="pr-field pr-field-remove"><div class="form-section">' +
                '<label class="form-label">&nbsp;</label>' +
                '<button type="button" class="remove-item" onclick="removeItem(' + num + ')" title="Remove item"><i class="bi bi-x-circle-fill"></i></button>' +
            '</div></div>' +
        '</div>' +
    '</div>';

    $('#itemsContainer').append(html);
    bindRowEvents(num);

    var row = $('#item-' + num);
    slUpdateStatus(row);

    if (prefill) {
        loadRowProducts(row, prefill.stock_source, prefill);
    }

    calcTotal();
}

function removeItem(num) {
    $('#item-' + num).remove();
    calcTotal();
}

// Repopulates a row's Product options for the given stock source via AJAX. When `prefill`
// is supplied, re-selects its product_id and restores its saved qty/price afterward (the
// AJAX response would otherwise reset them via the product-select change handler) — same
// behavior as the pre-1.8B sales/edit.php addPrefillItem().
function loadRowProducts(row, source, prefill) {
    if (!source) return;

    var projectId = $('#projectSelect').val();
    var params = { source: source };
    if (typeof excludeSaleId !== 'undefined' && excludeSaleId) params.exclude_sale_id = excludeSaleId;
    if (source === 'PROJECT') params.project_id = projectId;

    $.get(ajaxBase, params, function (data) {
        var select = row.find('.product-select');
        select.empty().append('<option value="">-- Select Product --</option>');

        var foundAvail = null;
        data.forEach(function (p) {
            var sel = (prefill && parseInt(p.id) === parseInt(prefill.product_id)) ? ' selected' : '';
            select.append(
                '<option value="' + p.id + '" data-price="' + p.selling_price +
                '" data-qty="' + p.available_qty + '" data-gst="' + (p.gst_percent || 0) + '"' + sel + '>' +
                p.name + ' (' + p.unit + ') — Stock: ' + parseFloat(p.available_qty).toFixed(2) +
                '</option>'
            );
            if (prefill && parseInt(p.id) === parseInt(prefill.product_id)) {
                foundAvail = p.available_qty;
            }
        });

        if (data.length === 0) {
            select.append('<option value="" disabled>No stock available for this source</option>');
        }

        if (prefill && select.val() != prefill.product_id) {
            select.prepend(
                '<option value="' + prefill.product_id + '" data-gst="' + (prefill.gst_percent || 0) + '" selected>' +
                prefill.product_name + ' (' + (prefill.unit || '') + ')</option>'
            );
            select.val(prefill.product_id);
            foundAvail = 0;
        }

        // Refresh Select2's rendering and let the product-select change handler set
        // baseline price/available/gst from the now-current option's data attributes.
        select.trigger('change');

        if (prefill) {
            // Saved historical values win over the AJAX-derived baseline the trigger just set.
            row.find('.available-stock').val(foundAvail !== null ? slFormatQty(foundAvail) : '0.000');
            row.find('.qty-input').val(parseFloat(prefill.quantity).toFixed(3));
            row.find('.price-input').val(parseFloat(prefill.unit_price).toFixed(2));
            var selected = select.find(':selected');
            row.find('.gst-percent').val((parseFloat(selected.attr('data-gst')) || 0) + '%');
        }

        slUpdateStatus(row);
        calcLineTotal(row);
    });
}

function bindRowEvents(num) {
    var row = $('#item-' + num);

    row.find('.product-select').select2({
        width: 'resolve',
        placeholder: 'Select Product'
    });

    row.find('.item-source').on('change', function () {
        var source = $(this).val();
        row.find('.product-select').empty().append('<option value="">Select Source First</option>');
        row.find('.available-stock').val('');
        slUpdateStatus(row);
        if (source) {
            loadRowProducts(row, source, null);
        } else {
            row.find('.product-select').trigger('change');
        }
    });

    row.find('.product-select').on('change', function () {
        var selected = $(this).find(':selected');
        var price = parseFloat(selected.attr('data-price')) || 0;
        var avail = parseFloat(selected.attr('data-qty')) || 0;
        var gst   = parseFloat(selected.attr('data-gst')) || 0;

        row.find('.price-input').val(price.toFixed(2));
        row.find('.available-stock').val(slFormatQty(avail));
        row.find('.gst-percent').val(gst + '%');

        slUpdateStatus(row);
        calcLineTotal(row);
    });

    row.find('.qty-input').on('input', function () {
        var qty   = parseFloat($(this).val()) || 0;
        var avail = parseFloat(row.find('.available-stock').val()) || 0;
        if (avail > 0 && qty > avail) {
            $(this).val(avail.toFixed(3));
            alert('Quantity exceeds available stock (' + avail.toFixed(3) + ').');
        }
        calcLineTotal(row);
    });

    row.find('.price-input').on('input', function () {
        calcLineTotal(row);
    });

    row.find('.gst-applicable').on('change', function () {
        calcLineTotal(row);
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

    updateFinancialPreview(summary.grandTotal);
}

// Re-evaluates each PROJECT-source row's stock-level badge when the form-level project
// changes, since availability is scoped per project.
$(document).on('change', '#projectSelect', function () {
    $('.item-row').each(function () {
        slUpdateStatus($(this));
    });
});
