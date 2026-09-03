<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-title">
    <span><i class="bi bi-plus-circle me-2"></i>Add Product</span>
    <a href="<?= base_url('products') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card-custom" style="max-width:600px">
    <div class="card-custom-header">Product Information</div>
    <div class="card-custom-body">
        <form action="<?= base_url('products/store') ?>" method="POST">
            <div class="row">
				<div class="col-md-12">
					<div class="form-section">
						<label class="form-label">Product Name <span class="text-danger">*</span></label>
						<input type="text" name="name" class="form-control" required>
					</div>
				</div>
			</div>

			<div class="form-section">
				<label class="form-label">Description</label>
				<textarea name="description" class="form-control"></textarea>
			</div>
			
            <div class="row">
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Unit</label>
                        <input type="text" name="unit" class="form-control" placeholder="Enter unit (e.g. pcs, kg, box)">
                    </div>
                </div>
            </div>
			
			<div class="row">
				<div class="col-md-6">
					<div class="form-section">
						<label class="form-label">HSN Code</label>
						<input type="text" name="hsn_code" class="form-control">
					</div>
				</div>
				<div class="col-md-6">
					<div class="form-section">
						<label class="form-label">GST %</label>
						<input type="number" name="gst_percent" class="form-control" step="0.01">
					</div>
				</div>
			</div>
			
            <div class="row">
				<div class="col-md-6">
					<div class="form-section">
						<label class="form-label">Price <span class="text-danger">*</span></label>
						<input type="number" name="selling_price" class="form-control" step="0.01" required>
					</div>
				</div>
			</div>
			
            <div class="mt-2">
                <button type="submit" class="btn-save"><i class="bi bi-save"></i> Save Product</button>
                <a href="<?= base_url('products') ?>" class="btn-cancel ms-2"><i class="bi bi-x"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
