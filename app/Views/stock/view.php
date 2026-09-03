<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-title">
    <span><i class="bi bi-eye me-2"></i>Stock Details</span>
    <a href="<?= base_url('stock-entry') ?>" class="btn-cancel">Back</a>
</div>

<?php if (!$stock): ?>
    <div class="alert alert-danger">Stock not found</div>
<?php else: ?>

<div class="card-custom">
    <div class="card-custom-body">
        <table class="table-custom">
            <tr><td><strong>Product</strong></td><td><?= esc($stock['product_name']) ?></td></tr>
			<tr><td><strong>Unit</strong></td><td><?= esc($stock['unit']) ?></td></tr>
        </table>
    </div>
</div>

<?php endif; ?>

<?= $this->endSection() ?>