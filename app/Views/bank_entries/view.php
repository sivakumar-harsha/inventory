<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
/**
 * Release 4.8.4I: one read-only detail page for Bank Deposit / Withdrawal /
 * Transfer / Daybook, driven by the controller's $cfg['detail'].
 */
$slug = $cfg['slug'];

$formatLabel = static fn ($value) => ucwords(strtolower(str_replace('_', ' ', (string) $value)));
$heading     = $cfg['singular'] . (isset($entry['transfer_no']) ? ' ' . $entry['transfer_no'] : ' #' . $entry['id']);
?>

<?= $this->include('bank_entries/partials/styles') ?>

<nav aria-label="breadcrumb" class="ba-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Accounts</li>
        <li class="breadcrumb-item"><a href="<?= base_url('bank-accounts') ?>">Bank Accounts</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url($slug) ?>"><?= esc($cfg['title']) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= esc($heading) ?></li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span><i class="bi <?= esc($cfg['icon'], 'attr') ?> me-2"></i><?= esc($heading) ?></span>
    <div class="d-flex gap-2">
        <a href="<?= base_url($slug . '/edit/' . $entry['id']) ?>" class="btn-save" style="background:#6c757d;"><i class="bi bi-pencil"></i> Edit</a>
        <a href="javascript:void(0)" class="btn-cancel" onclick="confirmDeleteEntry()"><i class="bi bi-trash"></i> Delete</a>
        <a href="<?= base_url($slug) ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-7">
        <div class="card-custom">
            <div class="card-custom-header"><?= esc($cfg['singular']) ?> Information</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <?php foreach ($cfg['detail'] as $row): ?>
                    <?php $value = $entry[$row['key']] ?? null; ?>
                    <tr>
                        <td><strong><?= esc($row['label']) ?></strong></td>
                        <td>
                            <?php if ($value === null || $value === ''): ?>
                                <span class="be-muted">-</span>
                            <?php elseif ($row['type'] === 'money'): ?>
                                <strong style="color:#2F7E8A;"><?= number_format((float) $value, 2) ?></strong>
                            <?php elseif ($row['type'] === 'date'): ?>
                                <?= esc(date('d-m-Y', strtotime((string) $value))) ?>
                            <?php elseif ($row['type'] === 'badge'): ?>
                                <span class="be-badge <?= esc($row['badges'][$value] ?? 'be-badge-gray', 'attr') ?>"><?= esc($formatLabel($value)) ?></span>
                            <?php else: ?>
                                <?= nl2br(esc($value)) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card-custom">
            <div class="card-custom-header"><i class="bi bi-clock-history me-2"></i>Audit Information</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td><strong>Recorded By</strong></td><td><?= esc($entry['created_by_name'] ?: '-') ?></td></tr>
                    <tr><td><strong>Created On</strong></td><td><?= $entry['created_at'] ? esc(date('d-m-Y H:i', strtotime($entry['created_at']))) : '-' ?></td></tr>
                    <tr><td><strong>Last Updated</strong></td><td><?= $entry['updated_at'] ? esc(date('d-m-Y H:i', strtotime($entry['updated_at']))) : '-' ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
	function confirmDeleteEntry() {
		if (!confirm('Delete this <?= esc(strtolower($cfg['singular']), 'js') ?>? The bank balance will be reversed.')) return;

		$.ajax({
			url: "<?= base_url($slug . '/delete/' . $entry['id']) ?>",
			type: 'POST',
			dataType: 'json'
		}).done(function (resp) {
			if (resp.status) {
				window.location.href = "<?= base_url($slug) ?>";
			} else {
				alert((resp.errors && resp.errors.join(' ')) || 'Failed to delete.');
			}
		}).fail(function (xhr) {
			var data = xhr.responseJSON;
			alert((data && data.errors && data.errors.join(' ')) || 'A network error occurred.');
		});
	}
</script>
<?= $this->endSection() ?>
