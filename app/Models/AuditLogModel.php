<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Release 4.8.8A: shape/validation for audit_logs, written to only by
 * App\Libraries\AuditLogger and read by AuditLogs (the read-only viewer
 * controller). Insert-only — created_at is set by AuditLogger itself, not
 * CI4's auto-timestamps, and there is no updated_at/deleted_at.
 */
class AuditLogModel extends Model
{
    protected $table      = 'audit_logs';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'user_id',
        'module',
        'action',
        'reference_type',
        'reference_id',
        'reference_no',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];
    protected $useTimestamps = false;

    protected $validationRules = [
        'module' => 'required|max_length[100]',
        'action' => 'required|in_list[CREATE,UPDATE,DELETE,STATUS_CHANGE,LOGIN,LOGOUT]',
    ];
}
