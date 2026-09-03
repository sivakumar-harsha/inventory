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

// Remaining Balance is informational only — it never blocks Save/Update.
// Project billing is allowed to exceed the original project value.
function pfSetExceeded(exceeded) {
    $('#pfRemainingWarning').toggle(exceeded);
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

    var chip = $('#pfRemainingChip');
    chip.removeClass('pf-chip-full pf-chip-exceeded');

    if (remainingAfter > 0.004) {
        $('#pfRemainingLabel').text('Remaining Balance');
        $('#pfRemainingBalance').text(pfFmt(remainingAfter));
    } else if (remainingAfter < -0.004) {
        chip.addClass('pf-chip-exceeded');
        $('#pfRemainingLabel').text('Over Billed');
        $('#pfRemainingBalance').text(pfFmt(Math.abs(remainingAfter)));
    } else {
        chip.addClass('pf-chip-full');
        $('#pfRemainingLabel').text('Fully Billed');
        $('#pfRemainingBalance').text(pfFmt(0));
    }

    pfSetExceeded(remainingAfter < -0.004);
}
