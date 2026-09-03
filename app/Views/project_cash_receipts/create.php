<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-title">
    <span><i class="bi bi-piggy-bank me-2"></i>Receive Cash</span>
    <a href="<?= base_url('project-cash-receipts') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card-custom" style="max-width:600px">
    <div class="card-custom-header">Project Cash Receipt</div>
    <div class="card-custom-body">
        <form action="<?= base_url('project-cash-receipts/store') ?>" method="POST">
            <div class="form-section">
                <label class="form-label">Project <span class="text-danger">*</span></label>
                <select name="project_id" id="projectSelect" class="form-control" required>
                    <option value=""></option>
                    <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['id'] ?>" data-customer-name="<?= esc($p['customer_name'] ?: 'No Customer') ?>">
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
                        <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Receipt Date <span class="text-danger">*</span></label>
                        <input type="date" name="receipt_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-control" required>
                            <option value="CASH">Cash</option>
                            <option value="BANK_TRANSFER">Bank Transfer</option>
                            <option value="CHECK">Check</option>
                            <option value="OTHER">Other</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" class="form-control">
                    </div>
                </div>
            </div>
            <div class="form-section">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control"></textarea>
            </div>
            <div class="mt-2">
                <button type="submit" class="btn-save"><i class="bi bi-save"></i> Save Receipt</button>
                <a href="<?= base_url('project-cash-receipts') ?>" class="btn-cancel ms-2"><i class="bi bi-x"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function () {
    $('#projectSelect').on('change', function () {
        var customerName = $(this).find(':selected').data('customer-name') || '';
        $('#customerDisplay').val(customerName);
    });
});
</script>
<?= $this->endSection() ?>
