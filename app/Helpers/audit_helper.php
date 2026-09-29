<?php

use App\Libraries\AuditLogger;

/**
 * Release 4.8.8A: thin function wrappers around App\Libraries\AuditLogger,
 * autoloaded (see app/Config/Autoload.php) the same way pdf_helper.php is —
 * so a controller calls audit_create()/audit_update()/audit_delete()/
 * audit_status() without instantiating AuditLogger itself. Every call is
 * fire-and-forget: AuditLogger swallows its own failures, so these never
 * throw or affect the caller's response.
 */
if (! function_exists('audit_create')) {
    function audit_create(string $module, string $referenceType, int $referenceId, ?string $referenceNo, array $newValues, ?string $description = null): void
    {
        (new AuditLogger())->logCreate($module, $referenceType, $referenceId, $referenceNo, $newValues, $description);
    }
}

if (! function_exists('audit_update')) {
    function audit_update(string $module, string $referenceType, int $referenceId, ?string $referenceNo, array $oldValues, array $newValues, ?string $description = null): void
    {
        (new AuditLogger())->logUpdate($module, $referenceType, $referenceId, $referenceNo, $oldValues, $newValues, $description);
    }
}

if (! function_exists('audit_delete')) {
    function audit_delete(string $module, string $referenceType, int $referenceId, ?string $referenceNo, array $oldValues, ?string $description = null): void
    {
        (new AuditLogger())->logDelete($module, $referenceType, $referenceId, $referenceNo, $oldValues, $description);
    }
}

if (! function_exists('audit_status')) {
    function audit_status(string $module, string $referenceType, int $referenceId, ?string $referenceNo, string $oldStatus, string $newStatus, ?string $description = null): void
    {
        (new AuditLogger())->logStatus($module, $referenceType, $referenceId, $referenceNo, $oldStatus, $newStatus, $description);
    }
}

if (! function_exists('audit_login')) {
    function audit_login(int $userId, string $username): void
    {
        (new AuditLogger())->logLogin($userId, $username);
    }
}

if (! function_exists('audit_logout')) {
    function audit_logout(int $userId, string $username): void
    {
        (new AuditLogger())->logLogout($userId, $username);
    }
}
