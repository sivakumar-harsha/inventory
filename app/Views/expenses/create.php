<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-title">
    <span><i class="bi bi-plus-circle me-2"></i>Add Expense</span>
    <a href="<?= base_url('expenses') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card-custom" style="max-width:600px">
    <div class="card-custom-header">Expense Information</div>
    <div class="card-custom-body">
        <form action="<?= base_url('expenses/store') ?>" method="POST">
            <div class="form-section">
                <label class="form-label">Project <span class="text-danger">*</span></label>
                <select name="project_id" class="form-control">
                    <option value="0">General (No Project)</option>
                    <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= esc($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category" id="categorySelect" class="form-control" required>
                            <option value=""></option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= esc($c['category_name']) ?>"><?= esc($c['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                    </div>
                </div>
            </div>
            <div class="form-section">
                <label class="form-label">Expense Date <span class="text-danger">*</span></label>
                <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-section">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control"></textarea>
            </div>
            <div class="mt-2">
                <button type="submit" class="btn-save"><i class="bi bi-save"></i> Save Expense</button>
                <a href="<?= base_url('expenses') ?>" class="btn-cancel ms-2"><i class="bi bi-x"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function () {
    $('#categorySelect').select2({
        width: '100%',
        placeholder: 'Select Expense Category',
        allowClear: true
    });
});
</script>
<?= $this->endSection() ?>
