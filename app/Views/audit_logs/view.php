<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$fmtDateTime = static fn ($d) => $d ? date('d-m-Y h:i A', strtotime($d)) : '—';
?>

<style>
.al-json { background:#0f172a; color:#e2e8f0; padding:12px; border-radius:8px; font-size:.78rem; max-height:420px; overflow:auto; white-space:pre-wrap; word-break:break-word; }
.al-changed { background:#7c2d12; color:#fed7aa; }
.al-summary dt { color:#64748b; font-size:.72rem; text-transform:uppercase; letter-spacing:.03em; }
.al-summary dd { font-size:.9rem; margin-bottom:10px; }
@media print {
    .sidebar, .topbar, .no-print { display: none !important; }
    .main-content { margin-left: 0 !important; }
}
</style>

<nav aria-label="breadcrumb" class="no-print" style="margin-bottom:8px;">
    <ol class="breadcrumb" style="font-size:.78rem;background:transparent;padding:0;margin-bottom:0;">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('audit-logs') ?>">Audit Logs</a></li>
        <li class="breadcrumb-item active" aria-current="page">#<?= (int) $row['id'] ?></li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-clock-history me-2"></i>Audit Log #<?= (int) $row['id'] ?></span>
    <div class="d-flex gap-2 no-print">
        <a href="<?= base_url('audit-logs') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back to Audit Logs</a>
        <button type="button" class="btn-save" style="background:#6c757d;" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        <button type="button" class="btn-cancel" id="alExportPdfBtn" disabled title="Coming soon"><i class="bi bi-file-earmark-pdf"></i> Export PDF</button>
    </div>
</div>

<div id="alNoticeHost" class="no-print"></div>

<div class="card-custom mb-3">
    <div class="card-custom-header">Summary</div>
    <div class="card-custom-body">
        <dl class="al-summary row">
            <div class="col-6 col-md-3">
                <dt>User</dt>
                <dd><?= esc($row['username'] ?? 'System') ?></dd>
            </div>
            <div class="col-6 col-md-3">
                <dt>Module</dt>
                <dd><?= esc($row['module']) ?></dd>
            </div>
            <div class="col-6 col-md-3">
                <dt>Action</dt>
                <dd><?= esc(str_replace('_', ' ', $row['action'])) ?></dd>
            </div>
            <div class="col-6 col-md-3">
                <dt>Date/Time</dt>
                <dd><?= $fmtDateTime($row['created_at']) ?></dd>
            </div>
            <div class="col-6 col-md-3">
                <dt>Reference</dt>
                <dd><?= esc($row['reference_no'] ?? ($row['reference_type'] ? $row['reference_type'] . ' #' . $row['reference_id'] : '-')) ?></dd>
            </div>
            <div class="col-6 col-md-3">
                <dt>Reference Type</dt>
                <dd><?= esc($row['reference_type'] ?? '-') ?></dd>
            </div>
            <div class="col-6 col-md-3">
                <dt>IP Address</dt>
                <dd><?= esc($row['ip_address'] ?? '-') ?></dd>
            </div>
            <div class="col-6 col-md-3">
                <dt>User Agent</dt>
                <dd style="font-size:.72rem;word-break:break-word;"><?= esc($row['user_agent'] ?? '-') ?></dd>
            </div>
            <div class="col-12">
                <dt>Description</dt>
                <dd><?= esc($row['description'] ?? '-') ?></dd>
            </div>
        </dl>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-custom-header">Old Values</div>
            <div class="card-custom-body">
                <?php if ($old === null): ?>
                <div class="al-json">No previous values recorded.</div>
                <?php else: ?>
                <div class="al-json"><?php foreach ($old as $key => $val): ?><span class="<?= in_array($key, $changedKeys, true) ? 'al-changed' : '' ?>"><?= esc($key) ?>: <?= esc(is_scalar($val) || $val === null ? (string) $val : json_encode($val)) ?></span><?php echo "\n"; endforeach; ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-custom-header">New Values</div>
            <div class="card-custom-body">
                <?php if ($new === null): ?>
                <div class="al-json">No new values recorded.</div>
                <?php else: ?>
                <div class="al-json"><?php foreach ($new as $key => $val): ?><span class="<?= in_array($key, $changedKeys, true) ? 'al-changed' : '' ?>"><?= esc($key) ?>: <?= esc(is_scalar($val) || $val === null ? (string) $val : json_encode($val)) ?></span><?php echo "\n"; endforeach; ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.getElementById('alExportPdfBtn').addEventListener('click', function () {
    var host = document.getElementById('alNoticeHost');
    if (!host) return;
    host.innerHTML = '<div class="alert alert-info alert-dismissible fade show py-2" role="alert" style="font-size:.82rem;">'
        + '<i class="bi bi-info-circle me-1"></i>Export PDF is not available yet. It will come in a future release.'
        + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
});
</script>
<?= $this->endSection() ?>
