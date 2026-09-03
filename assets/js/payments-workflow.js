// Release 2.1F (Phase 4): single shared initializer for both payments/create.php
// and payments/edit.php, so the Step 1/2/3 workflow only has one implementation
// to keep in sync instead of two near-duplicate inline blocks.
//
// Must be loaded (via <script src>) inside the layout's `scripts` section, i.e.
// after jQuery/Select2 — never from the `content` section (see Release 2.1F
// Phase A root cause: content renders before jQuery/Select2 load, so any
// jQuery call placed there silently throws and never binds).
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
        var total   = parseFloat(opt.attr('data-total')) || 0;
        var advance = parseFloat(opt.attr('data-advance')) || 0;
        var paid    = parseFloat(opt.attr('data-paid')) || 0;
        var pending = parseFloat(opt.attr('data-pending')) || 0;

        $('#detTotal').text(total.toFixed(2));
        $('#detAdvance').text(advance.toFixed(2));
        $('#detPaid').text(paid.toFixed(2));
        $('#detPending').text(pending.toFixed(2));
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

    // Step 1 project filter, client-side only (all invoices already fetched
    // in one pass). Reused both on user-driven change and once on load, so
    // the edit screen can open already scoped to its own invoice's project
    // without a duplicate implementation.
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
