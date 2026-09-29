<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Release 4.8.8C: Backup & Restore Center settings.
 *
 * Every value here can be overridden per-environment via .env (e.g.
 * `backup.mysqldumpPath = "C:\xampp\mysql\bin\mysqldump.exe"`) the same way
 * every other Config class in this app is overridden — see
 * BaseConfig::initEnvValue(), which this class inherits for free simply by
 * declaring these as public properties. Nothing here is read directly by
 * app code except App\Libraries\DatabaseBackupManager.
 *
 * The mysqldump/mysql paths default to this machine's XAMPP install
 * (D:\xampp\mysql\bin\...) only as a fallback for local development; a
 * different machine sets `backup.mysqldumpPath` / `backup.mysqlPath` in its
 * own .env instead of this file being edited.
 */
class Backup extends BaseConfig
{
    /** Absolute path to mysqldump.exe (used by createBackup()). */
    public string $mysqldumpPath = 'D:\\xampp\\mysql\\bin\\mysqldump.exe';

    /** Absolute path to mysql.exe (used by restoreBackup() to import a .sql file). */
    public string $mysqlPath = 'D:\\xampp\\mysql\\bin\\mysql.exe';

    /**
     * Where generated backup .sql files are written. Always under writable/,
     * never public/. Trailing slash kept for direct concatenation.
     */
    public string $backupDir = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR;

    /**
     * Spec ceiling for an uploaded restore file: 500MB, enforced at the
     * application layer. NOTE: this machine's php.ini currently caps actual
     * uploads at upload_max_filesize=2M / post_max_size=8M, which this app
     * does not (and must not) change — see Release 4.8.8C Known Limitations.
     * This value only governs the app-level check in validateBackup(); it
     * cannot make php.ini accept a bigger request body.
     */
    public int $maxUploadSizeBytes = 500 * 1024 * 1024;
}
