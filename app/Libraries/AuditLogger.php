<?php

namespace App\Libraries;

use App\Models\AuditLogModel;

/**
 * Release 4.8.8A: writes audit_logs rows for create/update/delete/status-
 * change/login/logout actions across the app. Called from controllers right
 * after a successful DB write (never from inside a model's save(), so it
 * never runs as part of the calling transaction and never affects it).
 *
 * Every public method is a thin wrapper that builds one row and inserts it.
 * Insertion is wrapped in try/catch: audit logging must never break the
 * request it is describing, so any failure here is only log_message()'d and
 * swallowed. logUpdate() diffs old vs new and stores only the fields that
 * actually changed; if nothing changed it is a silent no-op (no empty row).
 */
class AuditLogger
{
    public function logCreate(string $module, string $referenceType, int $referenceId, ?string $referenceNo, array $newValues, ?string $description = null): void
    {
        $this->_write([
            'module'         => $module,
            'action'         => 'CREATE',
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'reference_no'   => $referenceNo,
            'description'    => $description,
            'old_values'     => null,
            'new_values'     => $this->_encode($newValues),
        ]);
    }

    /**
     * Diffs $oldValues against $newValues and stores only the fields present
     * in $newValues whose value actually changed. No changed field -> no-op,
     * nothing is written.
     */
    public function logUpdate(string $module, string $referenceType, int $referenceId, ?string $referenceNo, array $oldValues, array $newValues, ?string $description = null): void
    {
        $oldChanged = [];
        $newChanged = [];

        foreach ($newValues as $key => $newVal) {
            $oldVal = $oldValues[$key] ?? null;

            // Loose comparison after string-casting both sides: DB rows and
            // freshly-saved arrays mix ints/strings/nulls for the "same"
            // value (e.g. '10' vs 10, '' vs null), which would otherwise
            // register as a false change on every save.
            if ((string) $oldVal === (string) $newVal) {
                continue;
            }

            $oldChanged[$key] = $oldVal;
            $newChanged[$key] = $newVal;
        }

        if (! $newChanged) {
            return;
        }

        $this->_write([
            'module'         => $module,
            'action'         => 'UPDATE',
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'reference_no'   => $referenceNo,
            'description'    => $description,
            'old_values'     => $this->_encode($oldChanged),
            'new_values'     => $this->_encode($newChanged),
        ]);
    }

    public function logDelete(string $module, string $referenceType, int $referenceId, ?string $referenceNo, array $oldValues, ?string $description = null): void
    {
        $this->_write([
            'module'         => $module,
            'action'         => 'DELETE',
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'reference_no'   => $referenceNo,
            'description'    => $description,
            'old_values'     => $this->_encode($oldValues),
            'new_values'     => null,
        ]);
    }

    public function logStatus(string $module, string $referenceType, int $referenceId, ?string $referenceNo, string $oldStatus, string $newStatus, ?string $description = null): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        $this->_write([
            'module'         => $module,
            'action'         => 'STATUS_CHANGE',
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'reference_no'   => $referenceNo,
            'description'    => $description,
            'old_values'     => $this->_encode(['status' => $oldStatus]),
            'new_values'     => $this->_encode(['status' => $newStatus]),
        ]);
    }

    public function logLogin(int $userId, string $username): void
    {
        $this->_write([
            'module'         => 'Authentication',
            'action'         => 'LOGIN',
            'reference_type' => 'USER',
            'reference_id'   => $userId,
            'reference_no'   => $username,
            'description'    => "User '{$username}' logged in.",
            'old_values'     => null,
            'new_values'     => null,
        ], $userId);
    }

    public function logLogout(int $userId, string $username): void
    {
        $this->_write([
            'module'         => 'Authentication',
            'action'         => 'LOGOUT',
            'reference_type' => 'USER',
            'reference_id'   => $userId,
            'reference_no'   => $username,
            'description'    => "User '{$username}' logged out.",
            'old_values'     => null,
            'new_values'     => null,
        ], $userId);
    }

    // =========================================================
    // INTERNALS
    // =========================================================

    /**
     * Builds and inserts the full row. $userIdOverride is used by
     * logLogin()/logLogout() where the acting user is known explicitly
     * (login: session()->get('user_id') isn't set yet the instant this is
     * called; logout: the caller reads it before session()->destroy() wipes
     * it) rather than read from the current session.
     */
    private function _write(array $fields, ?int $userIdOverride = null): void
    {
        try {
            $request = service('request');

            $userId = $userIdOverride ?? session()->get('user_id');

            $userAgent = null;
            if (method_exists($request, 'getUserAgent') && $request->getUserAgent()) {
                $userAgent = $request->getUserAgent()->getAgentString();
            }
            $userAgent = $userAgent ?: ($_SERVER['HTTP_USER_AGENT'] ?? null);

            (new AuditLogModel())->insert($fields + [
                'user_id'    => $userId,
                'ip_address' => $request->getIPAddress(),
                'user_agent' => $userAgent !== null ? mb_substr((string) $userAgent, 0, 255) : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'AuditLogger failed to write a log row: ' . $e->getMessage());
        }
    }

    private function _encode(?array $values): ?string
    {
        if ($values === null || $values === []) {
            return null;
        }

        return json_encode($values, JSON_UNESCAPED_SLASHES);
    }
}
