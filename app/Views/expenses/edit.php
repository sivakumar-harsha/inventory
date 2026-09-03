<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-title">
    <span><i class="bi bi-pencil me-2"></i>Edit Expense</span>
    <a href="<?= base_url('expenses') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card-custom" style="max-width:600px">
    <div class="card-custom-header">Edit Expense</div>
    <div class="card-custom-body">
        <form action="<?= base_url('expenses/update/' . $expense['id']) ?>" method="POST">
            <div class="form-section">
                <label class="form-label">Project <span class="text-danger">*</span></label>
                <select name="project_id" class="form-control" required>
                     <option value="0">General (No Project)</option>
                    <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $expense['project_id'] == $p['id'] ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
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
                            <option value="<?= esc($c['category_name']) ?>" <?= $expense['category'] === $c['category_name'] ? 'selected' : '' ?>><?= esc($c['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Amount </label>
                        <input type="number" name="amount" class="form-control" step="0.01" value="<?= $expense['amount'] ?>">
                    </div>
                </div>
            </div>
            <div class="form-section">
                <label class="form-label">Expense Date</label>
                <input type="date" name="expense_date" class="form-control" value="<?= $expense['expense_date'] ?>">
            </div>
            <div class="form-section">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control"><?= esc($expense['description']) ?></textarea>
            </div>
            <div class="mt-2">
                <button type="submit" class="btn-save"><i class="bi bi-save"></i> Update Expense</button>
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
