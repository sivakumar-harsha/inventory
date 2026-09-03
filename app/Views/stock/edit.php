<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-title">
    <span><i class="bi bi-box-seam me-2"></i>Edit Stock</span>
    <a href="<?= base_url('stock-entry') ?>" class="btn-cancel">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="card-custom" style="max-width:600px">
    <div class="card-custom-header">Stock Information</div>
    <div class="card-custom-body">

        <form method="post" action="<?= base_url('stock-entry/update/'.$stock['id']) ?>">
        <?= csrf_field() ?>

            <div class="form-section">
                <label class="form-label">Product <span class="text-danger">*</span></label>
                <select name="product_id" class="form-control" required>
                    <option value="">Select Product</option>
                    <?php foreach($products as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($stock['product_id'] == $p['id']) ? 'selected' : '' ?>>
							<?= esc($p['name']) ?>
						</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control"
						   value="<?= $stock['quantity'] ?>" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" value="<?= $stock['transaction_date'] ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control"><?= $stock['notes'] ?></textarea>
            </div>

            <div class="mt-2">
                <button type="submit" class="btn-save">
                    <i class="bi bi-save"></i> Update Stock
                </button>
                <a href="<?= base_url('stock-entry') ?>" class="btn-cancel ms-2">
                    <i class="bi bi-x"></i> Cancel
                </a>
            </div>

        </form>

    </div>
</div>

<?= $this->endSection() ?>