/**
 * Live Project Financial Summary for Sales Create/Edit.
 * Consumes GET sales/project-financial-summary/{projectId}[?exclude_sale_id=]
 * and the grandTotal already computed by the page's own calcTotal().
 */
var pfSummary       = null;
var pfBaseUrl       = null;
var pfExcludeSaleId = null;

function initProjectFinancialTracker(baseUrl, excludeSaleId) {
    pfBaseUrl       = baseUrl;
    pfExcludeSaleId = excludeSaleId || null;
}

function loadProjectFinancialSummary(projectId) {
    if (!projectId) {
        pfSummary = null;
        $('#projectFinancialCard').hide();
        pfSetExceeded(false);
        return;
    }

    var params = {};
    if (pfExcludeSaleId) params.exclude_sale_id = pfExcludeSaleId;

    $.get(pfBaseUrl + '/' + projectId, params, function (data) {
        pfSummary = data;
        renderCurrentStatus(data);
        $('#projectFinancialCard').show();
        updateFinancialPreview(parseFloat($('#grandTotal').text()) || 0);
    }).fail(function () {
        pfSummary = null;
        $('#projectFinancialCard').hide();
        pfSetExceeded(false);
    });
}

function pfFmt(n) {
    return parseFloat(n || 0).toFixed(2);
}

function pfSetExceeded(exceeded) {
    $('#pfRemainingChip').toggleClass('pf-chip-exceeded', exceeded);
    $('#pfRemainingWarning').toggle(exceeded);
    $('#saveSaleBtn').prop('disabled', exceeded);
}

function renderCurrentStatus(s) {
    $('#pfTotalValue').text(pfFmt(s.total_project_value));
    $('#pfAlreadyInvoiced').text(pfFmt(s.total_billed));
}

// Called from calcTotal() with the grandTotal it already computed — no re-derivation here.
function updateFinancialPreview(currentInvoiceTotal) {
    currentInvoiceTotal = parseFloat(currentInvoiceTotal) || 0;
    $('#pfInvoiceTotal').text(pfFmt(currentInvoiceTotal));

    if (!pfSummary) return;

    // Project Remaining Balance = Project Value - Total Billed (financial_summary's
    // remaining_billable_value, unchanged calculation — this file only displays it).
    var remaining = parseFloat(pfSummary.remaining_billable_value) || 0;

    // Remaining Balance After This Invoice = Project Remaining Balance - Current Invoice Grand Total
    var remainingAfter = remaining - currentInvoiceTotal;
    $('#pfRemainingBalance').text(pfFmt(remainingAfter));

    pfSetExceeded(remainingAfter < -0.004);
}
