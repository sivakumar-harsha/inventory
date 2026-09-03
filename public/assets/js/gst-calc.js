/**
 * Shared GST calculation for Sales/Purchases create & edit pages.
 * Mirrors app/Helpers/gst_helper.php::gst_calculate_line() exactly.
 */
function gstCalculateLine(qty, price, gstPercent, gstApplicable) {
    qty        = parseFloat(qty) || 0;
    price      = parseFloat(price) || 0;
    gstPercent = parseFloat(gstPercent) || 0;

    var taxable   = qty * price;
    var gstAmount = gstApplicable ? (taxable * gstPercent) / 100 : 0;
    var total     = taxable + gstAmount;

    return { taxable: taxable, gstAmount: gstAmount, total: total };
}

/**
 * lines: array of { taxable, gstAmount } (or objects with those keys)
 */
function gstSummarize(lines) {
    var taxable = 0, gst = 0;
    lines.forEach(function (l) {
        taxable += l.taxable || 0;
        gst     += l.gstAmount || 0;
    });
    return { taxable: taxable, gst: gst, grandTotal: taxable + gst };
}
