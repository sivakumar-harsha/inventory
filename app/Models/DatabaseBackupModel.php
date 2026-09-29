<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Release 4.8.8C: shape/validation for database_backups, written to only by
 * App\Libraries\DatabaseBackupManager and read by DatabaseBackup (the
 * controller). Insert-only, same convention as AuditLogModel — created_at is
 * set explicitly by the library at insert time, not via CI4 auto-timestamps,
 * and there is no updated_at/deleted_at.
 */
class DatabaseBackupModel extends Model
{
    protected $table      = 'database_backups';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'backup_name',
        'backup_type',
        'file_name',
        'file_size',
        'total_tables',
        'status',
        'restore_of',
        'created_by',
        'created_at',
        'remarks',
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'backup_name' => 'required|max_length[150]',
        'backup_type' => 'required|in_list[MANUAL,AUTO,RESTORE]',
        'status'      => 'permit_empty|in_list[SUCCESS,FAILED]',
    ];
}
