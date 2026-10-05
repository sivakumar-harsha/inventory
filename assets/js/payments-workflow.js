// Release 2.1F (Phase 4): single shared initializer for both payments/create.php
// and payments/edit.php, so the Step 1/2/3 workflow only has one implementation
// to keep in sync instead of two near-duplicate inline blocks.
//
// Must be loaded (via <script src>) inside the layout's `scripts` section, i.e.
// after jQuery/Select2 — never from the `content` section (see Release 2.1F
// Phase A root cause: content renders before jQuery/Select2 load, so any
// jQuery call placed there silently throws and never binds).
// Release 4.5.7: Step 1 project filter, client-side only (all invoices
// already fetched in one pass). Moved out of initPaymentsWorkflow() so
// initPaymentTypeToggle() can also call it directly when switching back to
// Invoice Payment mode ("reload invoice list for that project").
function applyProjectFilter() {
    var pid = $('#projectFilter').val();

    if (!pid) {
        $('#invoiceTableWrap').hide();
        $('#invoicePlaceholder').show();
        $('.invoice-row').hide();
        $('#noRowsMsg').hide();
        return;
    }

    $('#invoicePlaceholder').hide();
    $('#invoiceTableWrap').show();
    var visible = 0;
    $('.invoice-row').each(function () {
        var match = $(this).data('project-id') == pid;
        $(this).toggle(match);
        if (match) visible++;
    });
    $('#noRowsMsg').toggle(visible === 0);
}

function initPaymentsWorkflow() {
    // #projectFilter carries the 'no-search' class so the global auto-select2
    // handler in main.php skips it; initialized here explicitly with its own
    // placeholder text instead of the generic "Search...".
    $('#projectFilter').select2({
        width: '100%',
        placeholder: 'Search project...',
        allowClear: true
    });

    // Single source of truth for the detail chips, the amount max, and
    // whether Step 3 is expanded. #saleSelect carries 'no-search' so the
    // global auto-select2 handler never wraps it in a second, visible
    // dropdown widget — it must stay a plain hidden <select> for JS only.
    $('#saleSelect').on('change', function () {
        var opt = $(this).find(':selected');
        if (!opt.val()) {
            $('#saleDetail').hide();
            $('#amountInput').removeAttr('max');
            $('#selectedInvoiceLabel').text('');
            $('#step3Card').hide();
            return;
        }
        var total     = parseFloat(opt.attr('data-total')) || 0;
        var advance   = parseFloat(opt.attr('data-advance')) || 0;
        var paid      = parseFloat(opt.attr('data-paid')) || 0;
        var pending   = parseFloat(opt.attr('data-pending')) || 0;
        var projectId = opt.attr('data-project-id');

        $('#detTotal').text(total.toFixed(2));
        $('#detAdvance').text(advance.toFixed(2));
        $('#detPaid').text(paid.toFixed(2));
        $('#detPending').text(pending.toFixed(2));

        // Release 4.5.3/4.5.4: Project Cash Received (server-built
        // PROJECT_FINANCIALS_MAP, ProjectModel::getFinancialSummary() —
        // no new calculation) and Net Outstanding After Cash = Invoice
        // Pending - Project Cash Received. Invoice Pending itself (above)
        // is untouched.
        var financialsMap = (typeof PROJECT_FINANCIALS_MAP !== 'undefined') ? PROJECT_FINANCIALS_MAP : {};
        var projectFin    = financialsMap[projectId] || {};
        var cashReceived  = parseFloat(projectFin.cash_received) || 0;
        var netOutstanding = pending - cashReceived;

        $('#detCashReceived').text(cashReceived.toFixed(2));

        var $netEl = $('#detNetOutstanding');
        $netEl.removeClass('text-net-pending text-net-settled');
        if (netOutstanding > 0.004) {
            $netEl.addClass('text-net-pending').text(netOutstanding.toFixed(2));
        } else if (netOutstanding < -0.004) {
            $netEl.addClass('text-net-settled').text('Advance Credit ₹' + Math.abs(netOutstanding).toFixed(2));
        } else {
            $netEl.addClass('text-net-settled').text('Settled');
        }

        $('#saleDetail').show();
        $('#amountInput').attr('max', pending.toFixed(2));
        $('#selectedInvoiceLabel').text(opt.text());
        $('#step3Card').show();
    });

    // Clicking a row's Record Payment button drives the hidden #saleSelect
    // and moves the row highlight (only one row highlighted at a time).
    $('.record-payment-btn').on('click', function () {
        var saleId = $(this).data('sale-id');
        $('#saleSelect').val(saleId).trigger('change');
        $('.invoice-row').removeClass('is-selected');
        $(this).closest('.invoice-row').addClass('is-selected');
        $('html, body').animate({ scrollTop: $('#step3Card').offset().top - 20 }, 300);
    });

    // A user-driven project change always drops any invoice already selected
    // from a different project (Step 3 + row highlight + summary chips all
    // reset). The initial call to applyProjectFilter() below runs once on
    // page load, outside this handler, so the edit screen's own preselected
    // invoice survives — only a later, user-driven change clears state.
    $('#projectFilter').on('change', function () {
        $('#saleSelect').val('').trigger('change');
        $('.invoice-row').removeClass('is-selected');
        applyProjectFilter();
    });

    // Release 4.0B (Phase E): when the page was opened with a sale already
    // preselected (server-rendered <option selected> on #saleSelect — see
    // Payments::create($saleId) / payments/create.php's selected_sale_id),
    // scope #projectFilter to that invoice's project and highlight its row
    // before the existing filter/detail logic runs, so the invoice table
    // isn't left hidden behind the Step 1 placeholder. 'change.select2' is
    // namespaced so it refreshes the Select2 widget's displayed text without
    // firing the plain #projectFilter 'change' handler above (which would
    // otherwise reset the just-restored #saleSelect back to empty).
    var preselectedSaleId = $('#saleSelect').val();
    var $preselectedBtn = preselectedSaleId
        ? $('.record-payment-btn[data-sale-id="' + preselectedSaleId + '"]')
        : $();
    var preselectedProjectId = $preselectedBtn.length
        ? $preselectedBtn.closest('.invoice-row').data('project-id')
        : null;

    if (preselectedProjectId) {
        $('#projectFilter').val(preselectedProjectId).trigger('change.select2');
    }

    applyProjectFilter();
    $('#saleSelect').trigger('change');

    if ($preselectedBtn.length) {
        $('.invoice-row').removeClass('is-selected');
        $preselectedBtn.closest('.invoice-row').addClass('is-selected');
    }
}

// Release 4.9.0I: Bank Account show/hide for the Invoice Payment form's
// Method select — same pattern as the Cash Receipt form's own
// toggleCashBankAccount() (payments/create.php inline script), scoped to
// #invoicePayMethod/#invoiceBankAccount so the two forms never interfere.
// Shared by both payments/create.php and payments/edit.php, which each call
// it once from their own $(document).ready().
function initInvoicePayBankToggle() {
    var $method = $('#invoicePayMethod');
    if (!$method.length) {
        return;
    }

    function toggle() {
        var bank = ['BANK_TRANSFER', 'CHECK'].indexOf($method.val()) !== -1;
        $('#invoiceBankAccountRow').toggleClass('pcr-hidden', !bank);
        $('#invoiceBankAccount').prop('required', bank);
        if (!bank) { $('#invoiceBankAccount').val(''); }
    }

    $method.on('change', toggle);
    toggle();
}

// Release 4.5.4 (Phase B/D): Payment Type selector on payments/create.php —
// toggles between the unchanged Invoice Payment workflow (#invoicePaymentMode)
// and the new, simplified Project Cash Receipt form (#cashReceiptMode), and
// drives the Cash Receipt mode's live "Outstanding After This Receipt"
// preview. A no-op wherever these elements don't exist (payments/edit.php),
// so it's safe to call unconditionally from both pages.
function initPaymentTypeToggle() {
    // Release 4.6.5.3 (bug fix): scoped to the #paymentTypeToggle id, not the
    // shared '.payment-type-toggle' class — the Receipt Type toggle inside
    // #cashReceiptMode reuses that same class (and '.ptype-btn') purely for
    // styling. Selecting by class here matched BOTH toggles, so this
    // delegated handler also fired on Advance/Direct Income clicks with
    // data-type === undefined, which setType() treated as "not cash" and
    // switched back to Invoice Payment mode.
    var $toggle = $('#paymentTypeToggle');
    if (!$toggle.length) {
        return;
    }

    // Release 4.5.7: containers are switched via the 'pcr-hidden' CSS class
    // (display:none !important) instead of jQuery .show()/.hide(), so the
    // toggle can never be silently overridden by another display rule.
    function setType(type) {
        $toggle.find('.ptype-btn').removeClass('active').attr('aria-pressed', 'false');
        $toggle.find('.ptype-btn[data-type="' + type + '"]').addClass('active').attr('aria-pressed', 'true');

        if (type === 'cash') {
            $('#invoicePaymentMode').addClass('pcr-hidden');
            $('#cashReceiptMode').removeClass('pcr-hidden');

            // Entering Cash Receipt mode always drops any invoice already
            // selected in Invoice Payment mode (Step 3 + row highlight).
            $('#saleSelect').val('').trigger('change');
            $('.invoice-row').removeClass('is-selected');

            // Release 4.6.5.5 / 4.9.0W: every entry into Cash Receipt mode
            // resets Receipt Type to the "Direct Income" button (superseding
            // 4.6.5.4's "preserve current selection" behavior) — switching to
            // Invoice Payment and back, or opening the form fresh, must never
            // leave Advance selected from a previous visit. That button now
            // submits CUSTOMER_PROJECT_CASH, not DIRECT_INCOME — see
            // payments/create.php's own comment on the button.
            $('.rtype-btn').removeClass('active').attr('aria-pressed', 'false');
            $('.rtype-btn[data-rtype="CUSTOMER_PROJECT_CASH"]').addClass('active').attr('aria-pressed', 'true');
            $('#cashReceiptType').val('CUSTOMER_PROJECT_CASH');

            updateCashPreview();
        } else {
            $('#cashReceiptMode').addClass('pcr-hidden');
            $('#invoicePaymentMode').removeClass('pcr-hidden');

            // Switching back to Invoice Payment: keep the project selection
            // (untouched above) and reload/re-filter the invoice list for it.
            if (typeof applyProjectFilter === 'function') {
                applyProjectFilter();
            }
        }
    }

    $toggle.on('click', '.ptype-btn:not(.rtype-btn)', function () {
        setType($(this).data('type'));
    });

    // Release 4.5.7 (Cash Receipt Form): three summary cards — Project Cash
    // Received (existing total), Remaining Balance After Receipt and
    // Customer Paid Total After Receipt (both live, unsaved previews) — all
    // read from the same server-built PROJECT_FINANCIALS_MAP used elsewhere
    // (no new calculation). Nothing is saved until the form is submitted
    // (which only ever posts to project-cash-receipts/store).
    var financialsMap = (typeof PROJECT_FINANCIALS_MAP !== 'undefined') ? PROJECT_FINANCIALS_MAP : {};

    function updateCashPreview() {
        var pid = $('#cashProjectSelect').val();
        var fin = financialsMap[pid] || {};
        var cashReceived = parseFloat(fin.cash_received) || 0;
        var remainingBal = parseFloat(fin.remaining_balance) || 0;
        var customerPaid = parseFloat(fin.total_customer_paid) || 0;
        var entered       = parseFloat($('#cashAmountInput').val()) || 0;
        var rtype = $('#cashReceiptType').val();
        // Release 4.9.0Q: total_customer_paid excludes Direct Income by
        // definition (it's "not a customer collection" — Release 4.8.6A-1),
        // so a receipt being entered here only belongs in this preview's
        // "after receipt" total when it's an Advance receipt. A Direct
        // Income entry still raises Cash Received and reduces Remaining
        // Balance above (both already include Direct Income), just not this
        // figure.
        // Release 4.9.0T: Customer Project Cash is also a customer
        // collection (Total Customer Paid) but, unlike Advance, it does NOT
        // reduce Remaining Balance — it pays down what's already invoiced,
        // it doesn't shrink how much of the contract remains to be billed
        // (ProjectModel::getFinancialSummary()'s remaining_balance_display
        // formula only ever subtracts Advance/Direct Income).
        var addsToCustomerPaid = rtype === 'ADVANCE' || rtype === 'CUSTOMER_PROJECT_CASH';
        // Release 4.9.0EC: Remaining Balance is the one authoritative project
        // figure (Contract - Advance Received - Total Invoiced) and a receipt
        // entered here does not change it, so the preview shows that same figure
        // (never negative) — identical to Project View/Statement/List/Dashboard.
        var customerPaidAfter = customerPaid + (addsToCustomerPaid ? entered : 0);

        $('#cashReceivedTotal').text(cashReceived.toFixed(2));
        $('#cashRemainingAfter').text(Math.max(0, remainingBal).toFixed(2));
        $('#cashCustomerPaidAfter').text(customerPaidAfter.toFixed(2));
    }

    $('#cashProjectSelect').on('change', function () {
        var customerName = $(this).find(':selected').data('customer-name') || '';
        $('#cashCustomerDisplay').val(customerName);
        updateCashPreview();
    });
    $('#cashAmountInput').on('input', updateCashPreview);
    // Release 4.9.0Q: Advance ⇄ Direct Income affects the Customer Paid Total
    // preview above, so switching it must refresh the preview too. Deferred
    // with setTimeout(0) so this always runs after create.php's own
    // rtype-btn handler (bound later, in the page's inline script) has
    // already written the new value into #cashReceiptType — otherwise this
    // could read the stale type depending on jQuery's handler-firing order.
    $(document).on('click', '.rtype-btn', function () {
        setTimeout(updateCashPreview, 0);
    });

    updateCashPreview();
}
