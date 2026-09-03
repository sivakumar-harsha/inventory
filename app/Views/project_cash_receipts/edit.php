<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-title">
    <span><i class="bi bi-piggy-bank me-2"></i>Edit Project Cash Receipt (<?= esc($receipt['receipt_no']) ?>)</span>
    <a href="<?= base_url('project-cash-receipts') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card-custom" style="max-width:600px">
    <div class="card-custom-header">Project Cash Receipt</div>
    <div class="card-custom-body">
        <form action="<?= base_url('project-cash-receipts/update/' . $receipt['id']) ?>" method="POST">
            <div class="form-section">
                <label class="form-label">Project <span class="text-danger">*</span></label>
                <select name="project_id" id="projectSelect" class="form-control" required>
                    <option value=""></option>
                    <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['id'] ?>" data-customer-name="<?= esc($p['customer_name'] ?: 'No Customer') ?>"
                        <?= ((int) $p['id'] === (int) $receipt['project_id']) ? 'selected' : '' ?>>
                        <?= esc($p['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-section">
                <label class="form-label">Customer</label>
                <input type="text" id="customerDisplay" class="form-control" value="" readonly placeholder="Auto-filled from Project">
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0.01" value="<?= esc($receipt['amount']) ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Receipt Date <span class="text-danger">*</span></label>
                        <input type="date" name="receipt_date" class="form-control" value="<?= esc($receipt['receipt_date']) ?>" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-control" required>
                            <?php foreach (['CASH' => 'Cash', 'BANK_TRANSFER' => 'Bank Transfer', 'CHECK' => 'Check', 'OTHER' => 'Other'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $receipt['payment_method'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" class="form-control" value="<?= esc($receipt['reference']) ?>">
                    </div>
                </div>
            </div>
            <div class="form-section">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control"><?= esc($receipt['notes']) ?></textarea>
            </div>
            <div class="mt-2">
                <button type="submit" class="btn-save"><i class="bi bi-save"></i> Update Receipt</button>
                <a href="<?= base_url('project-cash-receipts') ?>" class="btn-cancel ms-2"><i class="bi bi-x"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function () {
    function syncCustomer() {
        var customerName = $('#projectSelect').find(':selected').data('customer-name') || '';
        $('#customerDisplay').val(customerName);
    }
    $('#projectSelect').on('change', syncCustomer);
    syncCustomer();
});
</script>
<?= $this->endSection() ?>
