<?php

namespace App\Libraries;

use App\Models\DatabaseBackupModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Backup as BackupConfig;

/**
 * Release 4.8.8C: all mysqldump/mysql shell-out work and database_backups
 * bookkeeping for the Backup & Restore Center lives here — nothing in
 * DatabaseBackup (the controller) builds a shell command or writes this
 * table directly.
 *
 * SAFETY (see the release brief's "CRITICAL SAFETY RULES"):
 *  - Every credential/path piece that reaches a shell command goes through
 *    escapeshellarg(); nothing user-controlled (an uploaded filename, a
 *    form field) is ever interpolated into a command string directly.
 *  - Every shell-out is wrapped in try/catch and its exit code is checked
 *    explicitly (not just "was there output").
 *  - createBackup()/restoreBackup() always resolve DB credentials from the
 *    live \Config\Database::connect() connection (hostname/username/
 *    password/database/port), i.e. whatever THIS running app's .env points
 *    at — never a hardcoded database name. That is what makes it safe to
 *    exercise this class inside a cloned app pointed at a scratch DB.
 *  - restoreBackup() always takes a fresh AUTO safety backup first and
 *    aborts (no import attempted) if that safety backup itself fails.
 */
class DatabaseBackupManager
{
    private BackupConfig $config;

    public function __construct()
    {
        $this->config = config('Backup');
    }

    // =========================================================
    // BACKUP
    // =========================================================

    /**
     * Runs mysqldump against the current app's configured database and
     * records a database_backups row for it.
     *
     * @return array{success:bool,id:?int,file_name:?string,file_path:?string,file_size:?int,error:?string}
     */
    public function createBackup(string $type = 'MANUAL', ?int $userId = null, ?string $remarks = null): array
    {
        $type = in_array($type, ['MANUAL', 'AUTO'], true) ? $type : 'MANUAL';

        $db   = \Config\Database::connect();
        $host = (string) ($db->hostname ?: '127.0.0.1');
        $port = (int) ($db->port ?: 3306);
        $user = (string) $db->username;
        $pass = (string) $db->password;
        $name = (string) $db->database;

        $dir = $this->_backupDir();

        $fileName = 'AANDA_Backup_' . date('Y-m-d') . '_' . date('His') . '.sql';
        $filePath = $dir . $fileName;
        $errPath  = $filePath . '.err';

        try {
            $cmd = $this->_mysqldumpCommand($host, $port, $user, $pass, $name, $filePath, $errPath);
            $exitCode = $this->_exec($cmd);
        } catch (\Throwable $e) {
            log_message('error', 'DatabaseBackupManager::createBackup threw: ' . $e->getMessage());
            return ['success' => false, 'id' => null, 'file_name' => null, 'file_path' => null, 'file_size' => null, 'error' => $e->getMessage()];
        }

        $errText = $this->_readAndDeleteErrFile($errPath);

        if ($exitCode !== 0 || ! is_file($filePath) || filesize($filePath) === 0) {
            $error = $errText !== '' ? $errText : ('mysqldump exited with code ' . $exitCode . '.');
            log_message('error', 'DatabaseBackupManager::createBackup failed (exit ' . $exitCode . '): ' . $error);
            if (is_file($filePath)) {
                @unlink($filePath);
            }
            return ['success' => false, 'id' => null, 'file_name' => null, 'file_path' => null, 'file_size' => null, 'error' => $error];
        }

        $fileSize    = (int) filesize($filePath);
        $totalTables = $this->_countTables();

        $row = [
            'backup_name'  => ($type === 'AUTO' ? 'Auto Safety Backup ' : 'Backup ') . date('d-m-Y H:i:s'),
            'backup_type'  => $type,
            'file_name'    => $fileName,
            'file_size'    => $fileSize,
            'total_tables' => $totalTables,
            'status'       => 'SUCCESS',
            'created_by'   => $userId,
            'created_at'   => date('Y-m-d H:i:s'),
            'remarks'      => $remarks !== null ? mb_substr($remarks, 0, 255) : null,
        ];

        try {
            $id = (new DatabaseBackupModel())->insert($row, true);
        } catch (\Throwable $e) {
            log_message('error', 'DatabaseBackupManager::createBackup: dump succeeded but history insert failed: ' . $e->getMessage());
            return ['success' => false, 'id' => null, 'file_name' => $fileName, 'file_path' => $filePath, 'file_size' => $fileSize, 'error' => 'Backup file was created but could not be recorded: ' . $e->getMessage()];
        }

        // 'row' is returned so restoreBackup() can re-register this exact
        // history row if a later import overwrites database_backups itself.
        return ['success' => true, 'id' => (int) $id, 'file_name' => $fileName, 'file_path' => $filePath, 'file_size' => $fileSize, 'error' => null, 'row' => $row];
    }

    /** @return array<int,array<string,mixed>> */
    public function listBackups(array $filters = []): array
    {
        $db = \Config\Database::connect();

        $builder = $db->table('database_backups db')
            ->select('db.*, u.username AS created_by_name')
            ->join('users u', 'u.id = db.created_by', 'left');

        if (! empty($filters['backup_type'])) {
            $builder->where('db.backup_type', $filters['backup_type']);
        }

        return $builder->orderBy('db.created_at', 'DESC')->orderBy('db.id', 'DESC')->get()->getResultArray();
    }

    public function deleteBackup(int $id): bool
    {
        $model = new DatabaseBackupModel();
        $row   = $model->find($id);

        if (! $row) {
            return false;
        }

        if (! empty($row['file_name'])) {
            $path = $this->backupPath($row['file_name']);
            if (is_file($path)) {
                @unlink($path);
            }
            // File already missing on disk is not an error — the row is
            // still removed below, per spec.
        }

        return (bool) $model->delete($id);
    }

    /** @return array{path:string,file_name:string}|null */
    public function downloadBackup(int $id): ?array
    {
        $row = (new DatabaseBackupModel())->find($id);

        if (! $row || empty($row['file_name'])) {
            return null;
        }

        $path = $this->backupPath($row['file_name']);

        if (! is_file($path)) {
            return null;
        }

        return ['path' => $path, 'file_name' => $row['file_name']];
    }

    /** Full path to a stored backup file by its file_name. basename()'d defensively. */
    public function backupPath(string $fileName): string
    {
        return $this->_backupDir() . basename($fileName);
    }

    // =========================================================
    // RESTORE
    // =========================================================

    /**
     * Validates an uploaded restore file BEFORE any mysql import is
     * attempted. Never trusts client-supplied MIME type alone: checks the
     * uploaded file's own reported extension plus a cheap content
     * sanity-check that it actually looks like a SQL dump.
     *
     * @return array{valid:bool,reason:?string,size:?int}
     */
    public function validateBackup(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            return ['valid' => false, 'reason' => 'Upload failed: ' . $file->getErrorString(), 'size' => null];
        }

        $sizeBytes = $file->getSize();
        if ($sizeBytes === false || $sizeBytes === 0) {
            return ['valid' => false, 'reason' => 'The uploaded file is empty.', 'size' => 0];
        }

        if ($sizeBytes > $this->config->maxUploadSizeBytes) {
            return ['valid' => false, 'reason' => 'File exceeds the maximum allowed size of ' . $this->humanFileSize($this->config->maxUploadSizeBytes) . '.', 'size' => $sizeBytes];
        }

        $ext = strtolower((string) $file->getClientExtension());
        if ($ext !== 'sql') {
            return ['valid' => false, 'reason' => 'Only .sql files are accepted (got .' . ($ext !== '' ? $ext : '?') . ').', 'size' => $sizeBytes];
        }

        $tmpPath = $file->getTempName();
        $sample  = $tmpPath !== '' && is_file($tmpPath) ? @file_get_contents($tmpPath, false, null, 0, 8192) : false;

        if ($sample === false || trim($sample) === '') {
            return ['valid' => false, 'reason' => 'The uploaded file could not be read or is empty.', 'size' => $sizeBytes];
        }

        if (! $this->_looksLikeSql($sample)) {
            return ['valid' => false, 'reason' => 'The uploaded file does not look like a valid SQL dump.', 'size' => $sizeBytes];
        }

        return ['valid' => true, 'reason' => null, 'size' => $sizeBytes];
    }

    /**
     * Phase F order: (1) automatic AUTO safety backup first — abort with no
     * import attempted if it fails; (2) mysql import of $sqlFilePath against
     * the SAME resolved credentials createBackup() uses; (3) record a
     * RESTORE-type database_backups row reflecting SUCCESS/FAILED.
     *
     * No true atomic rollback is attempted or claimed (see Safety Rule #4 /
     * Known Limitations): raw multi-statement SQL import via the mysql CLI
     * cannot be made transactional across DDL statements. On failure, the
     * safety backup's id is returned so the caller can point the admin at
     * it for a manual re-import.
     *
     * @return array{success:bool,error:?string,safety_backup_id:?int}
     */
    public function restoreBackup(string $sqlFilePath, ?int $userId = null): array
    {
        if (! is_file($sqlFilePath) || filesize($sqlFilePath) === 0) {
            return ['success' => false, 'error' => 'The SQL file to restore was not found on the server.', 'safety_backup_id' => null];
        }

        // Step 1: mandatory safety backup. Never restore without one.
        $safety = $this->createBackup('AUTO', $userId, 'Automatic safety backup taken before a restore.');

        if (! $safety['success']) {
            $this->_recordRestoreResult('FAILED', $userId, null, 'Restore aborted before any changes were made: the automatic pre-restore safety backup failed (' . $safety['error'] . ').');
            return ['success' => false, 'error' => 'Restore aborted: the automatic pre-restore safety backup failed, so nothing was imported. (' . $safety['error'] . ')', 'safety_backup_id' => null];
        }

        // Step 2: the actual import, same resolved credentials as step 1.
        $db   = \Config\Database::connect();
        $host = (string) ($db->hostname ?: '127.0.0.1');
        $port = (int) ($db->port ?: 3306);
        $user = (string) $db->username;
        $pass = (string) $db->password;
        $name = (string) $db->database;

        $errPath = $sqlFilePath . '.err';

        try {
            $cmd      = $this->_mysqlImportCommand($host, $port, $user, $pass, $name, $sqlFilePath, $errPath);
            $exitCode = $this->_exec($cmd);
        } catch (\Throwable $e) {
            log_message('error', 'DatabaseBackupManager::restoreBackup threw: ' . $e->getMessage());
            $safetyId = $this->_reRegisterSafetyBackup($safety);
            $this->_recordRestoreResult('FAILED', $userId, $safetyId, 'Restore failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'safety_backup_id' => $safetyId];
        }

        $errText = $this->_readAndDeleteErrFile($errPath);

        // The import may have overwritten database_backups itself (the dump
        // being restored can contain an older snapshot of that table), which
        // would erase the safety backup's history row and leave its .sql file
        // orphaned and undownloadable from the UI. Re-register it so it is
        // always listed after a restore, success or failure.
        $safetyId = $this->_reRegisterSafetyBackup($safety);

        if ($exitCode !== 0) {
            $error = $errText !== '' ? $errText : ('mysql import exited with code ' . $exitCode . '.');
            log_message('error', 'DatabaseBackupManager::restoreBackup failed (exit ' . $exitCode . '): ' . $error);
            $this->_recordRestoreResult('FAILED', $userId, $safetyId, $error);
            return ['success' => false, 'error' => $error, 'safety_backup_id' => $safetyId];
        }

        $this->_recordRestoreResult('SUCCESS', $userId, $safetyId, 'Restore completed successfully.');

        return ['success' => true, 'error' => null, 'safety_backup_id' => $safetyId];
    }

    // =========================================================
    // INTERNALS
    // =========================================================

    private function _backupDir(): string
    {
        $dir = rtrim($this->config->backupDir, '/\\') . DIRECTORY_SEPARATOR;

        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }

    private function _countTables(): int
    {
        try {
            return count(\Config\Database::connect()->listTables());
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function _looksLikeSql(string $sample): bool
    {
        // A real dump is plain text; reject anything with NUL bytes outright.
        if (str_contains($sample, "\0")) {
            return false;
        }

        $upper   = strtoupper($sample);
        $markers = ['-- MYSQL DUMP', 'CREATE TABLE', 'INSERT INTO', 'DROP TABLE', 'CREATE DATABASE', 'SET NAMES', 'SET SQL_MODE', '/*!4'];

        foreach ($markers as $marker) {
            if (str_contains($upper, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Builds the mysqldump command line. Every credential/path component is
     * escapeshellarg()'d individually; stdout/stderr are redirected to
     * caller-chosen file paths (also escaped) rather than captured through
     * PHP, since exec()'s own $output capture is unreliable for large dumps.
     */
    private function _mysqldumpCommand(string $host, int $port, string $user, string $pass, string $database, string $outPath, string $errPath): string
    {
        $parts = [
            escapeshellarg($this->config->mysqldumpPath),
            '--host=' . escapeshellarg($host),
            '--port=' . escapeshellarg((string) $port),
            '--user=' . escapeshellarg($user),
        ];

        if ($pass !== '') {
            $parts[] = '--password=' . escapeshellarg($pass);
        }

        $parts[] = '--single-transaction';
        $parts[] = '--routines';
        $parts[] = '--default-character-set=utf8mb4';
        $parts[] = escapeshellarg($database);

        return implode(' ', $parts) . ' > ' . escapeshellarg($outPath) . ' 2> ' . escapeshellarg($errPath);
    }

    private function _mysqlImportCommand(string $host, int $port, string $user, string $pass, string $database, string $sqlFilePath, string $errPath): string
    {
        $parts = [
            escapeshellarg($this->config->mysqlPath),
            '--host=' . escapeshellarg($host),
            '--port=' . escapeshellarg((string) $port),
            '--user=' . escapeshellarg($user),
        ];

        if ($pass !== '') {
            $parts[] = '--password=' . escapeshellarg($pass);
        }

        $parts[] = escapeshellarg($database);

        return implode(' ', $parts) . ' < ' . escapeshellarg($sqlFilePath) . ' 2> ' . escapeshellarg($errPath);
    }

    /**
     * Runs a fully-built, already-escaped shell command and returns its exit code.
     *
     * A dump/import can legitimately take far longer than PHP's default 30s
     * max_execution_time (observed during 4.8.8C verification: a 64KB restore
     * took ~39s on this server because DDL statements are slow here). If PHP
     * killed the request mid-import the database would be left half-restored
     * with no FAILED row recorded, so the time limit is lifted and a client
     * disconnect is ignored for the duration of the shell-out. This only
     * affects the current backup/restore request.
     */
    private function _exec(string $cmd): int
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $output   = [];
        $exitCode = 1;
        exec($cmd, $output, $exitCode);

        return $exitCode;
    }

    private function _readAndDeleteErrFile(string $errPath): string
    {
        if (! is_file($errPath)) {
            return '';
        }

        $text = trim((string) @file_get_contents($errPath));
        @unlink($errPath);

        return mb_substr($text, 0, 2000);
    }

    /**
     * Makes sure the safety backup's database_backups row exists after an
     * import, re-inserting it (with a fresh auto-increment id, to avoid
     * colliding with ids in a restored snapshot) if the import wiped it.
     * Returns the id the row now has, or null if it could not be recorded.
     */
    private function _reRegisterSafetyBackup(array $safety): ?int
    {
        try {
            $model    = new DatabaseBackupModel();
            $existing = $model->where('file_name', $safety['file_name'])->first();

            if ($existing) {
                return (int) $existing['id'];
            }

            $row               = $safety['row'];
            $row['created_by'] = $this->_existingUserIdOrNull($row['created_by'] ?? null);

            return (int) $model->insert($row, true);
        } catch (\Throwable $e) {
            log_message('error', 'DatabaseBackupManager: could not re-register the safety backup row after restore: ' . $e->getMessage());
            return null;
        }
    }

    /** created_by is a FK to users.id; a restored users table may no longer contain the acting user. */
    private function _existingUserIdOrNull(?int $userId): ?int
    {
        if ($userId === null) {
            return null;
        }

        try {
            return \Config\Database::connect()->table('users')->where('id', $userId)->countAllResults() > 0 ? $userId : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function _recordRestoreResult(string $status, ?int $userId, ?int $safetyBackupId, string $message): void
    {
        try {
            (new DatabaseBackupModel())->insert([
                'backup_name'  => 'Restore ' . date('d-m-Y H:i:s'),
                'backup_type'  => 'RESTORE',
                'file_name'    => null,
                'file_size'    => 0,
                'total_tables' => null,
                'status'       => $status,
                'restore_of'   => $safetyBackupId,
                'created_by'   => $this->_existingUserIdOrNull($userId),
                'created_at'   => date('Y-m-d H:i:s'),
                'remarks'      => mb_substr($message, 0, 255),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'DatabaseBackupManager: failed to record restore result row: ' . $e->getMessage());
        }
    }

    public function humanFileSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i     = (int) floor(log($bytes, 1024));
        $i     = max(0, min($i, count($units) - 1));

        return number_format($bytes / (1024 ** $i), $i === 0 ? 0 : 2) . ' ' . $units[$i];
    }
}
