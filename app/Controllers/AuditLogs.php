<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\Controller;

/**
 * Release 4.8.8A: read-only viewer over audit_logs. Modelled on
 * ExpenseReports.php's server-rendered, GET-query-driven filter pattern
 * (not the isAJAX CRUD-list pattern the CRUD modules use) — this is a
 * report, the same convention Release 4.8.6D documented. No delete/edit
 * routes or actions anywhere in this controller.
 */
class AuditLogs extends Controller
{
    private const ACTIONS = ['CREATE', 'UPDATE', 'DELETE', 'STATUS_CHANGE', 'LOGIN', 'LOGOUT'];

    /** GET audit-logs — filtered list with KPI cards. */
    public function index()
    {
        $f    = $this->_filters();
        $rows = $this->_rows($f);

        $db = \Config\Database::connect();

        return view('audit_logs/index', [
            'title'                  => 'Audit Logs',
            'f'                      => $f,
            'rows'                   => $rows,
            'users'                  => (new UserModel())->orderBy('username', 'ASC')->findAll(),
            'modules'                => $this->_distinctModules(),
            'actions'                => self::ACTIONS,
            'kpi_today_logs'         => $this->_countToday(),
            'kpi_total_logs'         => $db->table('audit_logs')->countAllResults(),
            'kpi_active_users_today' => $this->_activeUsersToday(),
            'kpi_modules_today'      => $this->_modulesToday(),
        ]);
    }

    /** GET audit-logs/{id} — detail view: summary, old/new JSON with a simple diff highlight. */
    public function view($id)
    {
        $row = \Config\Database::connect()->table('audit_logs al')
            ->select('al.*, u.username')
            ->join('users u', 'u.id = al.user_id', 'left')
            ->where('al.id', (int) $id)
            ->get()->getRowArray();

        if (! $row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Audit log entry not found.');
        }

        $old = $row['old_values'] !== null ? json_decode($row['old_values'], true) : null;
        $new = $row['new_values'] !== null ? json_decode($row['new_values'], true) : null;

        $changedKeys = [];
        if (is_array($old) && is_array($new)) {
            foreach ($new as $key => $val) {
                if (! array_key_exists($key, $old) || (string) $old[$key] !== (string) $val) {
                    $changedKeys[] = $key;
                }
            }
        }

        return view('audit_logs/view', [
            'title'       => 'Audit Log #' . $row['id'],
            'row'         => $row,
            'old'         => $old,
            'new'         => $new,
            'oldJson'     => $old !== null ? json_encode($old, JSON_PRETTY_PRINT) : null,
            'newJson'     => $new !== null ? json_encode($new, JSON_PRETTY_PRINT) : null,
            'changedKeys' => $changedKeys,
        ]);
    }

    // =========================================================
    // FILTER HELPERS (same convention as ExpenseReports.php)
    // =========================================================

    private function _rows(array $f): array
    {
        if (! $f['dates_valid']) {
            return [];
        }

        $db = \Config\Database::connect();
        $b  = $db->table('audit_logs al')
            ->select('al.*, u.username')
            ->join('users u', 'u.id = al.user_id', 'left');

        if ($f['user_id'] > 0) {
            $b->where('al.user_id', $f['user_id']);
        }
        if ($f['module'] !== '') {
            $b->where('al.module', $f['module']);
        }
        if ($f['action'] !== '') {
            $b->where('al.action', $f['action']);
        }
        if ($f['date_from'] !== '') {
            $b->where('al.created_at >=', $f['date_from'] . ' 00:00:00');
        }
        if ($f['date_to'] !== '') {
            $b->where('al.created_at <=', $f['date_to'] . ' 23:59:59');
        }
        if ($f['search'] !== '') {
            $b->groupStart()
                ->like('al.description', $this->_likeTerm($f['search']))
                ->orLike('al.reference_no', $this->_likeTerm($f['search']))
                ->orLike('al.module', $this->_likeTerm($f['search']))
                ->groupEnd();
        }

        return $b->orderBy('al.created_at', 'DESC')->orderBy('al.id', 'DESC')->get()->getResultArray();
    }

    private function _filters(): array
    {
        $ok = true;

        return $this->_rangeCheck([
            'user_id'     => $this->_get('user_id') !== '' && ctype_digit($this->_get('user_id')) ? (int) $this->_get('user_id') : 0,
            'module'      => $this->_get('module'),
            'action'      => in_array(strtoupper($this->_get('action')), self::ACTIONS, true) ? strtoupper($this->_get('action')) : '',
            'date_from'   => $this->_date('date_from', $ok),
            'date_to'     => $this->_date('date_to', $ok),
            'search'      => $this->_get('search'),
            'dates_valid' => $ok,
        ]);
    }

    private function _rangeCheck(array $f): array
    {
        $f['date_error'] = '';

        if (! $f['dates_valid']) {
            $f['date_error'] = 'One of the dates is not valid (use YYYY-MM-DD), so no rows are shown.';
        } elseif ($f['date_from'] !== '' && $f['date_to'] !== '' && $f['date_from'] > $f['date_to']) {
            $f['dates_valid'] = false;
            $f['date_error']  = 'The From date is after the To date, so no rows are shown.';
        }

        return $f;
    }

    private function _get(string $key): string
    {
        $v = $this->request->getGet($key);

        return is_string($v) ? trim($v) : '';
    }

    private function _likeTerm(string $search): string
    {
        $esc = \Config\Database::connect()->likeEscapeChar;

        return str_replace([$esc, '%', '_'], [$esc . $esc, $esc . '%', $esc . '_'], $search);
    }

    private function _date(string $key, bool &$ok): string
    {
        $raw = $this->_get($key);

        if ($raw === '') {
            return '';
        }

        $d = \DateTime::createFromFormat('Y-m-d', $raw);
        if (! $d || $d->format('Y-m-d') !== $raw) {
            $ok = false;
            return '';
        }

        return $raw;
    }

    // =========================================================
    // KPI HELPERS — plain Query Builder aggregates, no new business logic.
    // =========================================================

    private function _distinctModules(): array
    {
        $rows = \Config\Database::connect()->table('audit_logs')
            ->distinct()
            ->select('module')
            ->orderBy('module', 'ASC')
            ->get()->getResultArray();

        return array_column($rows, 'module');
    }

    private function _countToday(): int
    {
        return \Config\Database::connect()->table('audit_logs')
            ->where('DATE(created_at)', date('Y-m-d'))
            ->countAllResults();
    }

    private function _activeUsersToday(): int
    {
        $row = \Config\Database::connect()->table('audit_logs')
            ->select('COUNT(DISTINCT user_id) AS c')
            ->where('DATE(created_at)', date('Y-m-d'))
            ->where('user_id IS NOT NULL')
            ->get()->getRowArray();

        return (int) ($row['c'] ?? 0);
    }

    private function _modulesToday(): int
    {
        $row = \Config\Database::connect()->table('audit_logs')
            ->select('COUNT(DISTINCT module) AS c')
            ->where('DATE(created_at)', date('Y-m-d'))
            ->get()->getRowArray();

        return (int) ($row['c'] ?? 0);
    }
}
