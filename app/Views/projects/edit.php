<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
.customer-select-row { display: flex; gap: 8px; }
.customer-select-row .form-control { flex: 1; }
.btn-quick-add-customer {
    width: 42px;
    height: var(--input-height);
    flex-shrink: 0;
    border: none;
    border-radius: 10px;
    background: var(--primary);
    color: #fff;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.15s ease;
}
.btn-quick-add-customer:hover { background: var(--primary-dark); }
#quickAddCustomerModal .invalid-feedback-text { font-size: 0.8rem; }
.quick-add-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #16a34a;
    color: #fff;
    padding: 10px 18px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    font-size: 0.875rem;
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 0.3s ease, transform 0.3s ease;
    z-index: 2000;
}
.quick-add-toast.show { opacity: 1; transform: translateY(0); }
</style>

<div class="page-title">
    <span><i class="bi bi-pencil me-2"></i>Edit Project</span>
    <a href="<?= base_url('projects') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php if (! empty($formErrors)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= esc(implode(' ', $formErrors)) ?></div>
<?php endif; ?>

<div class="card-custom" style="max-width:650px">
    <div class="card-custom-header">Edit: <?= esc($project['name']) ?></div>
    <div class="card-custom-body">
        <form action="<?= base_url('projects/update/' . $project['id']) ?>" method="POST">
            <div class="form-section">
                <label class="form-label">Project Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= esc($project['name']) ?>" required>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Customer</label>
                        <div class="customer-select-row">
                            <select name="customer_id" id="customerSelect" class="form-control">
                                <option value="">-- Select Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $project['customer_id'] == $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-quick-add-customer" data-bs-toggle="modal" data-bs-target="#quickAddCustomerModal" title="Add New Customer">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-control" required>
                            <?php foreach(['ACTIVE','ON_HOLD','COMPLETED'] as $st): ?>
                            <option value="<?= $st ?>" <?= $project['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $project['start_date'] ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= $project['end_date'] ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Total Project Value</label>
                        <input type="number" name="total_project_value" class="form-control" step="0.01" min="0" value="<?= esc($project['total_project_value'] ?? 0) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Advance Amount Received</label>
                        <input type="number" name="advance_amount" id="advanceAmount" class="form-control" step="0.01" min="0" value="<?= esc($project['advance_amount'] ?? 0) ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Advance Date</label>
                        <input type="date" name="advance_date" class="form-control" value="<?= esc($project['advance_date'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Advance Notes</label>
                        <input type="text" name="advance_notes" class="form-control" value="<?= esc($project['advance_notes'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <!-- Release 4.8.4J: Payment Method shows only while an Advance Amount is entered; Bank Account
                 only for Bank Transfer / Cheque / UPI (the account the automatic DEPOSIT is posted to). -->
            <div class="row">
                <div class="col-md-6 d-none" id="paymentMethodSection">
                    <div class="form-section">
                        <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" id="paymentMethod" class="form-control">
                            <?php $savedMethod = (string) ($project['advance_payment_method'] ?? ''); ?>
                            <option value="">-- Select Payment Method --</option>
                            <option value="CASH" <?= $savedMethod === 'CASH' ? 'selected' : '' ?>>Cash</option>
                            <option value="BANK_TRANSFER" <?= $savedMethod === 'BANK_TRANSFER' ? 'selected' : '' ?>>Bank Transfer</option>
                            <option value="CHEQUE" <?= $savedMethod === 'CHEQUE' ? 'selected' : '' ?>>Cheque</option>
                            <option value="UPI" <?= $savedMethod === 'UPI' ? 'selected' : '' ?>>UPI</option>
                            <option value="OTHER" <?= $savedMethod === 'OTHER' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6 d-none" id="bankAccountSection">
                    <div class="form-section">
                        <label class="form-label">Bank Account <span class="text-danger">*</span></label>
                        <select name="bank_account_id" id="bankAccountId" class="form-control">
                            <option value="">-- Select Bank Account --</option>
                            <?php foreach ($bankAccounts as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= (string) ($project['advance_bank_account_id'] ?? '') === (string) $b['id'] ? 'selected' : '' ?>><?= esc($b['bank_name'] . ' - ' . $b['account_name']) ?> (<?= esc($b['account_number']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-section">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control"><?= esc($project['description']) ?></textarea>
            </div>
            <div class="mt-2">
                <button type="submit" class="btn-save"><i class="bi bi-save"></i> Update Project</button>
                <a href="<?= base_url('projects') ?>" class="btn-cancel ms-2"><i class="bi bi-x"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>

<!-- Quick Add Customer Modal -->
<div class="modal fade" id="quickAddCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="quickAddCustomerError" class="alert alert-danger d-none" role="alert"></div>
                <form id="quickAddCustomerForm">
                    <div class="form-section">
                        <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="qc_name" class="form-control">
                        <div class="invalid-feedback-text text-danger d-none" id="qc_name_error"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-section">
                                <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                <input type="text" name="mobile" id="qc_mobile" class="form-control">
                                <div class="invalid-feedback-text text-danger d-none" id="qc_mobile_error"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-section">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" id="qc_email" class="form-control">
                                <div class="invalid-feedback-text text-danger d-none" id="qc_email_error"></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-section">
                        <label class="form-label">GST Number</label>
                        <input type="text" name="gst" id="qc_gst" class="form-control">
                        <div class="invalid-feedback-text text-danger d-none" id="qc_gst_error"></div>
                    </div>
                    <div class="form-section">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="qc_address" class="form-control"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal"><i class="bi bi-x"></i> Cancel</button>
                <button type="button" class="btn-save" id="quickAddCustomerSaveBtn" onclick="saveQuickAddCustomer()">
                    <span id="quickAddCustomerSpinner" class="spinner-border spinner-border-sm d-none"></span>
                    <i class="bi bi-save" id="quickAddCustomerSaveIcon"></i> Save Customer
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Release 4.8.4J: the General Purchases (4.8.4H) isBankMethod()/toggleBankAccount() pattern, with the
// Advance Amount as the "has advance" trigger: Payment Method shows only while an advance is entered,
// Bank Account only for bank-type methods, and it is required (and cleared for Cash/Other) accordingly.
function isBankMethod(method) {
    return ['BANK', 'BANK_TRANSFER', 'CHEQUE', 'UPI'].indexOf(method) !== -1;
}
function toggleBankAccount() {
    var hasAdvance = (parseFloat($('#advanceAmount').val()) || 0) > 0;
    var bank = hasAdvance && isBankMethod($('#paymentMethod').val());
    $('#paymentMethodSection').toggleClass('d-none', !hasAdvance);
    $('#bankAccountSection').toggleClass('d-none', !bank);
    $('#paymentMethod').prop('required', hasAdvance);
    $('#bankAccountId').prop('required', bank);
    if (!isBankMethod($('#paymentMethod').val())) { $('#bankAccountId').val('').trigger('change'); }
}
$('#paymentMethod').on('change', toggleBankAccount);
$('#advanceAmount').on('input', toggleBankAccount);
toggleBankAccount();
</script>
<script>
(function () {
    var modalEl = document.getElementById('quickAddCustomerModal');

    function resetQuickAddCustomerForm() {
        document.getElementById('quickAddCustomerForm').reset();
        modalEl.querySelectorAll('.invalid-feedback-text').forEach(function (el) {
            el.classList.add('d-none');
            el.textContent = '';
        });
        modalEl.querySelectorAll('.form-control').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        var errorBanner = document.getElementById('quickAddCustomerError');
        errorBanner.classList.add('d-none');
        errorBanner.textContent = '';
    }

    modalEl.addEventListener('hidden.bs.modal', resetQuickAddCustomerForm);

    window.saveQuickAddCustomer = function () {
        var btn = document.getElementById('quickAddCustomerSaveBtn');
        var spinner = document.getElementById('quickAddCustomerSpinner');
        var errorBanner = document.getElementById('quickAddCustomerError');

        modalEl.querySelectorAll('.invalid-feedback-text').forEach(function (el) {
            el.classList.add('d-none');
            el.textContent = '';
        });
        modalEl.querySelectorAll('.form-control').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        errorBanner.classList.add('d-none');
        errorBanner.textContent = '';

        btn.disabled = true;
        spinner.classList.remove('d-none');

        var formData = new FormData(document.getElementById('quickAddCustomerForm'));

        fetch('<?= base_url('customers/ajax-store') ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function (res) {
            return res.json().then(function (data) { return { ok: res.ok, data: data }; });
        })
        .then(function (result) {
            var data = result.data;
            if (data.status) {
                var select = document.getElementById('customerSelect');
                var opt = document.createElement('option');
                opt.value = data.customer.id;
                opt.textContent = data.customer.name;
                select.appendChild(opt);
                select.value = data.customer.id;

                bootstrap.Modal.getInstance(modalEl).hide();
                showQuickAddCustomerToast(data.message || 'Customer Added Successfully.');
            } else if (data.errors) {
                Object.keys(data.errors).forEach(function (field) {
                    var input = document.getElementById('qc_' + field);
                    var errorEl = document.getElementById('qc_' + field + '_error');
                    if (input) input.classList.add('is-invalid');
                    if (errorEl) {
                        errorEl.textContent = data.errors[field];
                        errorEl.classList.remove('d-none');
                    }
                });
            } else {
                errorBanner.textContent = data.message || 'Unable to save customer.';
                errorBanner.classList.remove('d-none');
            }
        })
        .catch(function () {
            errorBanner.textContent = 'A network error occurred. Please try again.';
            errorBanner.classList.remove('d-none');
        })
        .finally(function () {
            btn.disabled = false;
            spinner.classList.add('d-none');
        });
    };

    function showQuickAddCustomerToast(message) {
        var toast = document.createElement('div');
        toast.className = 'quick-add-toast';
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(function () { toast.classList.add('show'); }, 10);
        setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () { toast.remove(); }, 300);
        }, 3000);
    }
})();
</script>
<?= $this->endSection() ?>
