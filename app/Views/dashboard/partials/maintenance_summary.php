<?php
/**
 * Dashboard: compact one-line backup status. Figures come from Dashboard::_backupStatus().
 * Renders nothing when the backup table does not exist yet. The full screen is the Backup Center.
 */
if (empty($backup_available)) {
    return;
}

$lastBackup = $backup_last_at ? date('d-m-Y H:i', strtotime($backup_last_at)) : null;
?>
<div class="dbx-sys">
    <span>Backup —
        <?php if ($lastBackup): ?>
            <span class="ok">last taken <?= esc($lastBackup) ?></span> · <?= number_format($backup_count) ?> stored
        <?php else: ?>
            <span class="bad">no backup taken yet</span>
        <?php endif; ?>
        <?php if ($backup_restore_failed): ?> · <span class="bad">last restore failed</span><?php endif; ?>
    </span>
    <a href="<?= base_url('database-backup') ?>">Backup Center</a>
</div>
