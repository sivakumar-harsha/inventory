<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$fmtDateTime = static fn ($d) => $d ? date('d-m-Y h:i A', strtotime($d)) : '—';
$count       = count($rows);

$typeBadge = static function (string $type): string {
    $map = [
        'MANUAL'  => ['background:#dbeafe;color:#1e40af;', 'Manual'],
        'AUTO'    => ['background:#fef3c7;color:#92400e;', 'Auto (Safety)'],
        'RESTORE' => ['background:#ede9fe;color:#5b21b6;', 'Restore Attempt'],
    ];
    [$style, $label] = $map[$type] ?? ['background:#f1f5f9;color:#475569;', $type];
    return '<span class="badge" style="' . $style . 'font-weight:600;font-size:.7rem;">' . esc($label) . '</span>';
};

$statusBadge = static function (?string $status): string {
    if ($status === 'FAILED') {
        return '<span class="badge" style="background:#fee2e2;color:#991b1b;font-weight:600;font-size:.7rem;">Failed</span>';
    }
    return '<span class="badge" style="background:#dcfce7;color:#166534;font-weight:600;font-size:.7rem;">Success</span>';
};
?>

<style>
.db-table th, .db-table td { padding: 7px 10px; font-size: .78rem; vertical-align: middle; }
.db-warning-banner { border-left: 4px solid #dc2626; background: #fef2f2; color: #7f1d1d; padding: 12px 16px; border-radius: 8px; font-size: .82rem; margin-bottom: 14px; }
.db-warning-banner i { color: #dc2626; }
</style>

<nav aria-label="breadcrumb" style="margin-bottom:8px;">
    <ol class="breadcrumb" style="font-size:.78rem;background:transparent;padding:0;margin-bottom:0;">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">Backup &amp; Restore Center</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-hdd-network me-2"></i>Backup &amp; Restore Center</span>
    <div class="d-flex gap-2">
        <form method="post" action="<?= base_url('database-backup/create') ?>" class="d-inline">
            <button type="submit" class="btn-save"><i class="bi bi-cloud-arrow-up"></i> Create Backup</button>
        </form>
        <a href="<?= base_url('database-backup/restore') ?>" class="btn-cancel"><i class="bi bi-cloud-arrow-down"></i> Upload Restore</a>
        <a href="<?= current_url() ?>" class="btn-cancel"><i class="bi bi-arrow-clockwise"></i> Refresh</a>
    </div>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-archive"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($totalBackups) ?></div>
                <div class="kpi-label">Total Backups</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-calendar-check"></i></div>
            <div>
                <div class="kpi-value" style="font-size:15px;"><?= $latestBackup ? esc($fmtDateTime($latestBackup['created_at'])) : 'Never' ?></div>
                <div class="kpi-label">Latest Backup</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-device-hdd"></i></div>
            <div>
                <div class="kpi-value" style="font-size:15px;"><?= esc($totalSizeText) ?></div>
                <div class="kpi-label">Total Backup Size</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card <?= (! $lastRestore) ? 'kpi-blue' : ($lastRestore['status'] === 'FAILED' ? 'kpi-red' : 'kpi-green') ?>">
            <div class="kpi-icon"><i class="bi bi-arrow-counterclockwise"></i></div>
            <div>
                <div class="kpi-value" style="font-size:15px;"><?= $lastRestore ? esc($lastRestore['status']) : 'No Restores Yet' ?></div>
                <div class="kpi-label">Last Restore Status</div>
            </div>
        </div>
    </div>
</div>

<!-- WARNING BANNER -->
<div class="db-warning-banner">
    <i class="bi bi-exclamation-triangle-fill me-1"></i>
    <strong>No data loss, please read:</strong> Deleting a backup record permanently removes it (and its .sql file) from this list — this cannot be undone.
    Restoring a backup <strong>overwrites the entire current database</strong> with the contents of the uploaded file. Any logged-in user on this system can
    perform these actions (this app has no admin/role restriction — see the Backup &amp; Restore Known Limitations). A safety backup is always taken
    automatically immediately before any restore.
</div>

<div class="card-custom">
    <div class="card-custom-header d-flex justify-content-between">
        <span>Backup History</span>
        <span><?= number_format($count) ?> record<?= $count === 1 ? '' : 's' ?></span>
    </div>
    <div class="table-responsive">
        <table id="dbBackupTable" class="table-custom db-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Backup Name</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Tables</th>
                    <th>Created By</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($count === 0): ?>
                <tr><td colspan="8" style="text-align:center;padding:20px;color:#94a3b8;"><i class="bi bi-inbox"></i> No backups have been taken yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td data-order="<?= esc($r['created_at']) ?>"><?= $fmtDateTime($r['created_at']) ?></td>
                    <td><?= esc($r['backup_name']) ?></td>
                    <td><?= $typeBadge($r['backup_type']) ?></td>
                    <td><?= $r['backup_type'] === 'RESTORE' ? '—' : esc(number_format((int) $r['file_size'] / 1048576, 2) . ' MB') ?></td>
                    <td><?= $r['total_tables'] !== null ? (int) $r['total_tables'] : '—' ?></td>
                    <td><?= esc($r['created_by_name'] ?? 'System') ?></td>
                    <td><?= $statusBadge($r['status']) ?></td>
                    <td>
                        <?php if ($r['backup_type'] !== 'RESTORE'): ?>
                            <?php if ($r['file_missing']): ?>
                                <span class="badge" style="background:#fee2e2;color:#991b1b;font-weight:600;font-size:.68rem;" title="The .sql file is no longer on disk">File Missing</span>
                            <?php else: ?>
                                <a href="<?= base_url('database-backup/download/' . (int) $r['id']) ?>" class="btn btn-sm btn-outline-primary" style="font-size:.7rem;padding:2px 8px;"><i class="bi bi-download"></i> Download</a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="javascript:void(0)" class="btn-delete" title="Delete" style="padding:2px 8px;font-size:.7rem;" onclick="openDeleteBackupModal(<?= (int) $r['id'] ?>, '<?= esc($r['backup_name'], 'js') ?>')"><i class="bi bi-trash"></i> Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div class="modal fade" id="deleteBackupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;border-radius:50%;background:#fee2e2;color:#dc2626;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </span>
                    Delete Backup Record
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteBackupName">-</strong>? This removes the history record and its .sql file (if present) permanently. This cannot be undone.</p>
                <div id="deleteBackupError" class="alert alert-danger d-none" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal"><i class="bi bi-x"></i> Cancel</button>
                <button type="button" class="btn-delete" id="confirmDeleteBackupBtn">
                    <span id="deleteBackupSpinner" class="spinner-border spinner-border-sm d-none"></span>
                    <i class="bi bi-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
var baseUrl = "<?= base_url() ?>";
var pendingDeleteBackupId = null;

function openDeleteBackupModal(id, name) {
    pendingDeleteBackupId = id;
    $('#deleteBackupName').text(name);
    $('#deleteBackupError').addClass('d-none').text('');
    var modal = new bootstrap.Modal(document.getElementById('deleteBackupModal'));
    modal.show();
}

$('#confirmDeleteBackupBtn').on('click', function () {
    if (!pendingDeleteBackupId) return;

    var btn = $(this);
    var spinner = $('#deleteBackupSpinner');
    var errorBox = $('#deleteBackupError');
    errorBox.addClass('d-none').text('');
    btn.prop('disabled', true);
    spinner.removeClass('d-none');

    $.ajax({
        url: baseUrl + 'database-backup/delete/' + pendingDeleteBackupId,
        type: 'POST',
        dataType: 'json'
    }).done(function (resp) {
        if (resp.status) {
            window.location.reload();
        } else {
            errorBox.text((resp.errors && resp.errors.join(' ')) || 'Failed to delete backup.').removeClass('d-none');
        }
    }).fail(function (xhr) {
        var data = xhr.responseJSON;
        errorBox.text((data && data.errors && data.errors.join(' ')) || 'A network error occurred.').removeClass('d-none');
    }).always(function () {
        btn.prop('disabled', false);
        spinner.addClass('d-none');
    });
});

$(document).ready(function () {
    $('#dbBackupTable').DataTable({
        paging: true,
        searching: true,
        lengthChange: false,
        info: false,
        ordering: true,
        order: [],
        pageLength: 15,
        dom: 'tp',
        columnDefs: [{ orderable: false, targets: 7 }],
        language: {
            paginate: {
                previous: '<i class="bi bi-chevron-left"></i>',
                next: '<i class="bi bi-chevron-right"></i>'
            },
            emptyTable: 'No backups have been taken yet.'
        }
    });
});
</script>
<?= $this->endSection() ?>
