<?php
/**
 * Release 4.8.6A-2 / 4.8.6B / 4.8.6B-1 / 4.8.6B-2 / 4.8.6C-1 (figures from the server) / 4.8.6C-3 (compact UI): "Customer Advance Available" modal, shared by Sales Create and
 * Sales Edit.
 *
 * On Save it looks up the project's unused advance. With none (or the project has no customer) the
 * form submits exactly as before. Otherwise the invoice is NOT submitted until the accountant
 * answers, and the modal cannot be bypassed: it has no close (X) button, Esc and a click outside are
 * ignored, and only the three "Continue with ..." choices submit the form. Cancel closes it and
 * saves nothing (the page stays as it is).
 *
 * Three selectable cards: Apply Full Advance, Apply Partial Advance, Save Without Applying Advance (default;
 * on Edit the invoice's existing allocation is preselected). The answer goes to the server in the
 * hidden advance_action ('apply' | 'none') and advance_to_apply fields, and the server re-validates
 * everything and refuses to save without an answer.
 *
 * Allocation table guard (4.8.6B-1): when project_advance_allocations does not exist (migration
 * 2026-09-25-000002 pending) the modal still opens, but Full/Partial are disabled with an info
 * message, the partial input is hidden and only Save Without Applying Advance / Cancel are possible. Nothing is
 * ever stored on the invoice in that case.
 *
 * Expects: $advanceEditing (bool), $advanceExcludeSaleId (int), $advancePaidAmount (float),
 *          $advanceLegacy (bool: invoice carries an old automatic advance — no modal, it must be
 *          converted first), $advanceOriginalProject (int: the invoice's project when the page opened).
 * Uses the Bootstrap 5.3 classes the app already loads; no extra stylesheet.
 */
$advanceEditing         = $advanceEditing ?? false;
$advanceExcludeSaleId   = (int) ($advanceExcludeSaleId ?? 0);
$advancePaidAmount      = (float) ($advancePaidAmount ?? 0);
$advanceLegacy          = (bool) ($advanceLegacy ?? false);
$advanceOriginalProject = (int) ($advanceOriginalProject ?? 0);
?>
<div class="modal fade" id="advanceModal" tabindex="-1" aria-labelledby="advanceModalTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header flex-column align-items-start" style="padding:14px">
                <h5 class="modal-title" id="advanceModalTitle"><i class="bi bi-piggy-bank me-2"></i>Customer Advance Available</h5>
                <div class="small text-muted mt-1">Customer has an unused advance for this project. Choose whether to apply it to this invoice.</div>
            </div>
            <div class="modal-body" style="padding:14px">
                <div class="small mb-2" id="amSummary">
                    <div class="d-flex justify-content-between"><span>Customer</span><span class="fw-bold text-end" id="amCustomer">-</span></div>
                    <div class="d-flex justify-content-between"><span>Project</span><span class="fw-bold text-end" id="amProject">-</span></div>
                    <div class="d-flex justify-content-between"><span>Available Customer Advance</span><span class="fw-bold text-end">₹<span id="amAvailable">0.00</span></span></div>
                    <div class="d-flex justify-content-between"><span>Invoice Amount</span><span class="fw-bold text-end">₹<span id="amInvoice">0.00</span></span></div>
                    <div class="d-flex justify-content-between"><span>Invoice Balance After Apply</span><span class="fw-bold text-end">₹<span id="amBalance">0.00</span></span></div>
                </div>

                <div class="alert alert-warning py-1 px-2 small mb-2" id="amUnavailable" style="display:none">
                    ⚠ Advance allocation feature is not available yet.
                </div>

                <div class="d-grid gap-2" id="amCards">
                    <label class="card py-1 px-2" for="advChoiceFull" data-choice="full" style="cursor:pointer">
                        <span class="d-flex align-items-start gap-2">
                            <input class="form-check-input mt-1" type="radio" name="advanceChoice" id="advChoiceFull" value="full">
                            <span>
                                <span class="fw-bold d-block">Apply Full Advance</span>
                                <span class="small text-muted">Apply the available advance to this invoice.</span>
                            </span>
                        </span>
                    </label>
                    <label class="card py-1 px-2" for="advChoicePartial" data-choice="partial" style="cursor:pointer">
                        <span class="d-flex align-items-start gap-2">
                            <input class="form-check-input mt-1" type="radio" name="advanceChoice" id="advChoicePartial" value="partial">
                            <span class="flex-grow-1">
                                <span class="fw-bold d-block">Apply Partial Advance</span>
                                <span class="small text-muted">Choose how much advance to apply.</span>
                            </span>
                        </span>
                        <div class="mt-2" id="amPartialWrap" style="display:none">
                            <label class="small fw-bold" for="amPartialInput">Advance Amount to Apply</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="amPartialInput">
                            <div class="text-danger small" id="amPartialError" style="display:none"></div>
                            <div class="small text-muted mt-1">Remaining Invoice Balance: ₹<span id="amPartialRemaining">0.00</span></div>
                        </div>
                    </label>
                    <label class="card py-1 px-2" for="advChoiceNone" data-choice="none" style="cursor:pointer">
                        <span class="d-flex align-items-start gap-2">
                            <input class="form-check-input mt-1" type="radio" name="advanceChoice" id="advChoiceNone" value="none" checked>
                            <span>
                                <span class="fw-bold d-block">Save Without Applying Advance</span>
                                <span class="small text-muted">Keep the customer advance available for future invoices.</span>
                            </span>
                        </span>
                    </label>
                </div>
            </div>
            <div class="modal-footer" style="padding:10px 14px">
                <button type="button" class="btn-cancel" id="amCancelBtn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-save" id="amContinueBtn"><i class="bi bi-check2-circle"></i> <span id="amContinueLabel">Continue with Save Without Applying Advance</span></button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('saleForm');
    var modalEl = document.getElementById('advanceModal');
    // Mandatory: no Esc, no click-outside close (Cancel is the only way out without saving).
    var modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
    var editing = <?= $advanceEditing ? 'true' : 'false' ?>;
    var legacy = <?= $advanceLegacy ? 'true' : 'false' ?>;
    var origProject = <?= $advanceOriginalProject ?>;
    var excludeId = <?= $advanceExcludeSaleId ?>;
    var decided = false;
    var maxApply = 0, available = 0, before = 0, allocOn = true;
    var LABELS = { full: 'Continue with Apply Full Advance', partial: 'Continue with Apply Partial Advance', none: 'Continue with Save Without Applying Advance' };

    function money(n) { return (parseFloat(n) || 0).toFixed(2); }
    // Display only (thousands separators); form values always go through money().
    function fmt(n) { return (parseFloat(n) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function choice() { return $('input[name=advanceChoice]:checked').val(); }

    function chosenAmount() {
        var c = choice();
        if (c === 'full') return maxApply;
        if (c === 'partial') return parseFloat($('#amPartialInput').val()) || 0;
        return 0;
    }

    function refresh() {
        var c = choice();
        $('#amPartialWrap').toggle(allocOn && c === 'partial');
        var amt = chosenAmount();
        var err = '';
        if (c === 'partial') {
            if (amt <= 0) err = 'Enter an amount greater than 0.';
            else if (amt > maxApply + 0.004) err = 'Cannot exceed ₹' + fmt(maxApply) + '.';
        }
        $('#amPartialError').text(err).toggle(err !== '');
        var shown = err ? 0 : amt;
        $('#amBalance').text(fmt(before - shown));
        $('#amPartialRemaining').text(fmt(before - shown));
        // Selected card is highlighted (Bootstrap utility classes only).
        $('#amCards label.card').each(function () {
            var on = $(this).data('choice') === c;
            $(this).toggleClass('border-primary bg-primary-subtle', on);
        });
        $('#amContinueLabel').text(LABELS[c] || LABELS.none);
        // Full / Partial need a real amount; Save Without Applying Advance is always possible.
        $('#amContinueBtn').prop('disabled', c !== 'none' && (!allocOn || err !== '' || amt <= 0.004));
    }

    // action 'apply' = Continue with Apply Full / Partial, 'none' = Continue with Save Without Applying Advance (0).
    // The server treats a missing action on a project with unused advance as "not answered" and refuses.
    function finish(action, amount) {
        $('#amContinueBtn, #amCancelBtn').prop('disabled', true);
        if (!$('#advanceAction').length) $(form).append('<input type="hidden" name="advance_action" id="advanceAction" value="">');
        $('#advanceAction').val(action || '');
        if (amount !== null) $('#advanceToApply').val(money(amount));
        decided = true;
        modal.hide();
        form.submit();
    }

    $(form).on('submit', function (e) {
        if (decided) return;
        var projectId = $('#projectSelect').val();
        if (!projectId) return;
        // A legacy (old automatic) advance is never touched from this popup: convert it first.
        if (legacy && String(projectId) === String(origProject)) return;
        e.preventDefault();
        // Every figure in the popup comes from the server; only the grand total being saved is sent up.
        var params = { invoice_total: money(String($('#grandTotal').text()).replace(/,/g, '')) };
        if (excludeId) params.exclude_sale_id = excludeId;
        $.get('<?= base_url('sales/project-financial-summary') ?>/' + projectId, params)
            .done(function (s) {
                available = parseFloat(s.available_customer_advance) || 0;
                var current = parseFloat(s.current_invoice_allocation) || 0;
                allocOn = !!s.advance_allocation_enabled;
                before = parseFloat(s.invoice_outstanding_before_apply) || 0;
                maxApply = Math.max(0, Math.min(available, before));
                var customerId = $('select[name=customer_id]').val() || s.modal_customer_id;
                // No customer or no unused advance: nothing to ask, save exactly as before.
                if (!customerId || available <= 0.004) {
                    finish(null, null);
                    return;
                }
                var custText = $('select[name=customer_id] option:selected').val() ? $('select[name=customer_id] option:selected').text() : (s.modal_customer_name || '-');
                $('#amProject').text(s.modal_project_name || $('#projectSelect option:selected').text());
                $('#amCustomer').text($.trim(custText));
                // Compact summary, all straight from the server (Sales::projectFinancialSummary).
                $('#amAvailable').text(fmt(available));
                $('#amInvoice').text(fmt(s.invoice_total));
                $('#amPartialInput').val('');
                // Migration missing: Full / Partial are disabled, info message shown, Save Without Applying Advance only.
                $('#advChoiceFull, #advChoicePartial').prop('disabled', !allocOn);
                $('#advChoiceFull, #advChoicePartial').closest('label.card').toggleClass('opacity-50', !allocOn).css('cursor', allocOn ? 'pointer' : 'not-allowed');
                $('#amUnavailable').toggle(!allocOn);
                // Never default to applying: Save Without Applying Advance unless this invoice already has an allocation.
                $('#advChoiceNone').prop('checked', true);
                if (allocOn && editing && current > 0.004) {
                    if (Math.abs(current - maxApply) < 0.005) { $('#advChoiceFull').prop('checked', true); }
                    else { $('#advChoicePartial').prop('checked', true); $('#amPartialInput').val(money(current)); }
                }
                $('#amContinueBtn, #amCancelBtn').prop('disabled', false);
                refresh();
                modal.show();
            })
            // Could not find out whether the project has unused advance: do NOT save blindly.
            .fail(function () { alert('Could not check the customer advance for this project. The invoice was not saved; please try again.'); });
    });

    $('input[name=advanceChoice]').on('change', refresh);
    $('#amPartialInput').on('input', refresh);
    $('#amContinueBtn').on('click', function () {
        var c = choice();
        if (c === 'none') finish('none', 0);
        else finish('apply', chosenAmount());
    });
})();
</script>
