<?php
/**
 * Release 4.8.8C: Dashboard "Maintenance — Backup & Restore" summary cards.
 *
 * Follows export_center.php's house convention exactly (Release 4.8.7C
 * Phase A precedent this release was told to copy): this partial fetches
 * its own tiny data directly and Dashboard.php (the controller) stays
 * completely untouched.
 *
 * Phase H "hide section gracefully if table missing": every query below is
 * wrapped so this partial never throws even if migrated a moment before the
 * database_backups table exists — it just silently renders nothing.
 */
try {
    $msDb = \Config\Database::connect();

    if (! $msDb->tableExists('database_backups')) {
        return;
    }

    $msBackupCount = $msDb->table('database_backups')->where('backup_type !=', 'RESTORE')->countAllResults();

    $msLatest = $msDb->table('database_backups')
        ->where('backup_type !=', 'RESTORE')
        ->orderBy('created_at', 'DESC')
        ->get(1)->getRowArray();

    $msSizeRow = $msDb->table('database_backups')
        ->select('SUM(file_size) AS total_size')
        ->where('backup_type !=', 'RESTORE')
        ->get()->getRowArray();

    $msLastRestore = $msDb->table('database_backups')
        ->where('backup_type', 'RESTORE')
        ->orderBy('created_at', 'DESC')
        ->get(1)->getRowArray();
} catch (\Throwable $e) {
    // Never let the Dashboard break over this partial's own query.
    return;
}

$msTotalSize = (int) ($msSizeRow['total_size'] ?? 0);

$msHumanSize = static function (int $bytes): string {
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i     = (int) floor(log($bytes, 1024));
    $i     = max(0, min($i, count($units) - 1));

    return number_format($bytes / (1024 ** $i), $i === 0 ? 0 : 2) . ' ' . $units[$i];
};

$msRestoreClass = 'kpi-blue';
$msRestoreLabel = 'No Restores Yet';
if ($msLastRestore) {
    $msRestoreClass = $msLastRestore['status'] === 'FAILED' ? 'kpi-red' : 'kpi-green';
    $msRestoreLabel = $msLastRestore['status'] === 'FAILED' ? 'Failed' : 'Success';
}
?>
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card-custom">
            <div class="card-custom-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-hdd-network me-2"></i>Maintenance — Backup &amp; Restore</span>
                <a href="<?= base_url('database-backup') ?>" class="btn-save" style="padding:3px 10px;font-size:12px;">Backup Center</a>
            </div>
            <div class="card-custom-body">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="kpi-card kpi-blue">
                            <div class="kpi-icon"><i class="bi bi-calendar-check"></i></div>
                            <div>
                                <div class="kpi-value" style="font-size:14px;"><?= $msLatest ? esc(date('d-m-Y H:i', strtotime($msLatest['created_at']))) : 'Never' ?></div>
                                <div class="kpi-label">Last Backup Date</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="kpi-card kpi-orange">
                            <div class="kpi-icon"><i class="bi bi-archive"></i></div>
                            <div>
                                <div class="kpi-value"><?= number_format($msBackupCount) ?></div>
                                <div class="kpi-label">Backup Count</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="kpi-card kpi-green">
                            <div class="kpi-icon"><i class="bi bi-device-hdd"></i></div>
                            <div>
                                <div class="kpi-value" style="font-size:14px;"><?= esc($msHumanSize($msTotalSize)) ?></div>
                                <div class="kpi-label">Storage Used</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="kpi-card <?= $msRestoreClass ?>">
                            <div class="kpi-icon"><i class="bi bi-arrow-counterclockwise"></i></div>
                            <div>
                                <div class="kpi-value" style="font-size:14px;"><?= esc($msRestoreLabel) ?></div>
                                <div class="kpi-label">Restore Status</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <a href="<?= base_url('database-backup') ?>" class="btn-save"><i class="bi bi-hdd-network"></i> Backup Center</a>
                    <form method="post" action="<?= base_url('database-backup/create') ?>" class="d-inline">
                        <button type="submit" class="btn-cancel"><i class="bi bi-cloud-arrow-up"></i> Create Backup</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
