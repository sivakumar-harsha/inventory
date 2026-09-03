<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-title">
    <span><i class="bi bi-pencil me-2"></i>Edit Customer</span>
    <a href="<?= base_url('customers') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card-custom" style="max-width:600px">
    <div class="card-custom-header">Edit: <?= esc($customer['name']) ?></div>
    <div class="card-custom-body">
        <form action="<?= base_url('customers/update/' . $customer['id']) ?>" method="POST">
            <div class="form-section">
                <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= esc($customer['name']) ?>" required>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">GST</label>
                        <input type="text" name="gst" class="form-control" value="<?= esc($customer['gst']) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-section">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= esc($customer['phone']) ?>">
                    </div>
                </div>
            </div>
            <div class="form-section">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= esc($customer['email']) ?>">
            </div>
            <div class="form-section">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control"><?= esc($customer['address']) ?></textarea>
            </div>
            <div class="mt-2">
                <button type="submit" class="btn-save"><i class="bi bi-save"></i> Update Customer</button>
                <a href="<?= base_url('customers') ?>" class="btn-cancel ms-2"><i class="bi bi-x"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
