<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-title">
    <span><i class="bi bi-pencil me-2"></i>Edit Project</span>
    <a href="<?= base_url('projects') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

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
                        <select name="customer_id" class="form-control">
                            <option value="">-- Select Customer --</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $project['customer_id'] == $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
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
                        <input type="number" name="advance_amount" class="form-control" step="0.01" min="0" value="<?= esc($project['advance_amount'] ?? 0) ?>">
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

<?= $this->endSection() ?>
