<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-title">
    <span><i class="bi bi-plus-circle me-2"></i>Add Project</span>
    <a href="<?= base_url('projects') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card-custom" style="max-width:650px">
    <div class="card-custom-header">Project Information</div>
    <div class="card-custom-body">
        <form action="<?= base_url('projects/store') ?>" method="POST">
            <div class="form-section">
                <label class="form-label">Project Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Customer</label>
                        <select name="customer_id" class="form-control">
                            <option value="">-- Select Customer --</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-control" required>
                            <option value="ACTIVE">ACTIVE</option>
                            <option value="ON_HOLD">ON HOLD</option>
                            <option value="COMPLETED">COMPLETED</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Total Project Value</label>
                        <input type="number" name="total_project_value" class="form-control" step="0.01" min="0">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Advance Amount Received</label>
                        <input type="number" name="advance_amount" class="form-control" step="0.01" min="0">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Advance Date</label>
                        <input type="date" name="advance_date" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Advance Notes</label>
                        <input type="text" name="advance_notes" class="form-control">
                    </div>
                </div>
            </div>
            <div class="form-section">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control"></textarea>
            </div>
            <div class="mt-2">
                <button type="submit" class="btn-save"><i class="bi bi-save"></i> Save Project</button>
                <a href="<?= base_url('projects') ?>" class="btn-cancel ms-2"><i class="bi bi-x"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
