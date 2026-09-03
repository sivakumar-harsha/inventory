<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-title">
    <span><i class="bi bi-pencil me-2"></i>Edit Expense Category</span>
    <a href="<?= base_url('expense-categories') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card-custom" style="max-width:600px">
    <div class="card-custom-header">Edit Expense Category</div>
    <div class="card-custom-body">
        <form action="<?= base_url('expense-categories/update/' . $category['id']) ?>" method="POST">
            <div class="form-section">
                <label class="form-label">Category Name <span class="text-danger">*</span></label>
                <input type="text" name="category_name" class="form-control" value="<?= esc($category['category_name']) ?>" required>
            </div>
            <div class="form-section">
                <label class="form-label">Status <span class="text-danger">*</span></label>
                <select name="status" class="form-control no-search">
                    <option value="ACTIVE" <?= $category['status'] === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
                    <option value="INACTIVE" <?= $category['status'] === 'INACTIVE' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="mt-2">
                <button type="submit" class="btn-save"><i class="bi bi-save"></i> Update Category</button>
                <a href="<?= base_url('expense-categories') ?>" class="btn-cancel ms-2"><i class="bi bi-x"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
