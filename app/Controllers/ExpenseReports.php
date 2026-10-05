<?php

namespace App\Controllers;

use App\Libraries\ExcelReport;
use App\Libraries\PdfReport;
use App\Models\ExpenseCategoryModel;
use App\Models\ExpenseModel;
use App\Models\ProjectModel;
use CodeIgniter\Controller;

/**
 * Release 4.8.6D: read-only reports over the frozen Expense data (4.8.6A–C).
 * Nothing here writes, and it adds no columns, no posting and no business
 * rules — it only reads what Expenses/ExpenseModel already produce.
 *
 * Expense Register (index()) is the only page with filters, all of them
 * server-side through GET parameters (full page reload), following the
 * exact LoanReports.php convention: bad input never raises (an unknown
 * id/enum is ignored, a malformed date or a From date after the To date
 * returns an empty dataset with a warning), and search text is LIKE-escaped
 * before being bound by the Query Builder.
 *
 * Category / Project / Payment Method / Monthly Summary take only an
 * optional From/To expense_date range (Release 4.9.0F) — each is a PAID-only
 * breakdown, so with no range their totals equal the ledger's unfiltered
 * PAID total. Category and Project Summary reuse
 * ExpenseModel::categoryTotals()/projectTotals(); Payment Method and Monthly
 * Summary group here (see _summary()). Cancelled expenses never enter any
 * total or count on any of these pages.
 */
class ExpenseReports extends Controller
{
    private const PAYMENT_METHODS = ['CASH', 'BANK', 'CHEQUE', 'UPI', 'OTHER'];
    private const STATUSES        = ['PAID', 'CANCELLED'];

    // =========================================================
    // PAGES
    // =========================================================

    /**
     * Release 4.9.0C: Expense Ledger (this route used to be the report-style
     * Expense Register, which is now the voucher list at /expenses). Read-only.
     * See _ledger() for the accounting.
     */
    public function index()
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $categories = (new ExpenseCategoryModel())->orderBy('category_name', 'ASC')->findAll();
        $projects   = (new ProjectModel())->orderBy('name', 'ASC')->findAll();
        $f          = $this->_registerFilters(array_column($categories, 'id'), array_column($projects, 'id'));

        return view('expense_reports/index', [
            'title'          => 'Expense Ledger',
            'f'              => $f,
            'categories'     => $categories,
            'projects'       => $projects,
            'paymentMethods' => self::PAYMENT_METHODS,
            'warning'        => $this->_warning($f),
        ] + $this->_ledger($f));
    }

    /** Category Summary: PAID expenses grouped by category, highest first. */
    public function categorySummary()
    {
        return $this->_summaryPage('category');
    }

    /** Project Summary: PAID expenses grouped by project (incl. "No Project"), highest first. */
    public function projectSummary()
    {
        return $this->_summaryPage('project');
    }

    /** Payment Method Summary: PAID expenses grouped by method, all 5 methods always shown. */
    public function paymentSummary()
    {
        return $this->_summaryPage('payment');
    }

    /** Monthly Summary: PAID expenses grouped by calendar month, newest first. */
    public function monthlySummary()
    {
        return $this->_summaryPage('monthly');
    }

    // =========================================================
    // PDF EXPORTS (Release 4.8.7A) — same filtered rows/KPIs as the pages
    // above, reusing the same filter helpers and (for the Register) the
    // same row query via _registerRows(). Landscape for every report here;
    // the single-expense PDF lives on Expenses::exportPdf() instead.
    // =========================================================

    public function exportPdf()
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $categories = (new ExpenseCategoryModel())->orderBy('category_name', 'ASC')->findAll();
        $projects   = (new ProjectModel())->orderBy('name', 'ASC')->findAll();
        $f          = $this->_registerFilters(array_column($categories, 'id'), array_column($projects, 'id'));
        $ledger     = $this->_ledger($f);

        $categoryName = $f['category_id'] > 0 ? (array_column($categories, 'category_name', 'id')[$f['category_id']] ?? '') : '';
        $projectName  = $f['project_id'] > 0 ? (array_column($projects, 'name', 'id')[$f['project_id']] ?? '') : '';

        (new PdfReport())->render('pdf/expense_register', $ledger, [
            'title'       => 'Expense Ledger',
            'orientation' => 'landscape',
            'filters'     => [
                'Category'       => $categoryName,
                'Project'        => $projectName,
                'Payment Method' => $f['payment_method'],
                'From'           => $f['date_from'] !== '' ? pdf_date($f['date_from']) : '',
                'To'             => $f['date_to'] !== '' ? pdf_date($f['date_to']) : '',
                'Search'         => $f['search'],
            ],
        ]);
    }

    public function exportCategorySummaryPdf()
    {
        return $this->_summaryPdf('category');
    }

    public function exportProjectSummaryPdf()
    {
        return $this->_summaryPdf('project');
    }

    public function exportPaymentSummaryPdf()
    {
        return $this->_summaryPdf('payment');
    }

    public function exportMonthlySummaryPdf()
    {
        return $this->_summaryPdf('monthly');
    }

    // =========================================================
    // EXCEL EXPORTS (Release 4.8.7B) — same filtered rows/KPIs as the PDF
    // twins above, reusing the same filter + row-builder helpers so Excel
    // totals equal PDF totals equal on-screen totals by construction.
    // =========================================================

    public function exportExcel()
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $categories = (new ExpenseCategoryModel())->orderBy('category_name', 'ASC')->findAll();
        $projects   = (new ProjectModel())->orderBy('name', 'ASC')->findAll();
        $f          = $this->_registerFilters(array_column($categories, 'id'), array_column($projects, 'id'));
        $ledger     = $this->_ledger($f);

        $categoryName = $f['category_id'] > 0 ? (array_column($categories, 'category_name', 'id')[$f['category_id']] ?? '') : '';
        $projectName  = $f['project_id'] > 0 ? (array_column($projects, 'name', 'id')[$f['project_id']] ?? '') : '';

        $excelRows = [];
        foreach ($ledger['ledger_rows'] as $r) {
            $excelRows[] = [$r['date'], $r['voucher'], $r['category'], $r['project'], $r['particulars'], pm_label($r['payment_method'], 'Not recorded'), $r['expense_amount'], ucfirst(strtolower($r['status']))];
        }

        (new ExcelReport())->table(
            'Expense Ledger',
            [
                'Category'       => $categoryName,
                'Project'        => $projectName,
                'Payment Method' => $f['payment_method'],
                'From'           => $f['date_from'] !== '' ? pdf_date($f['date_from']) : '',
                'To'             => $f['date_to'] !== '' ? pdf_date($f['date_to']) : '',
                'Search'         => $f['search'],
            ],
            ['Date', 'Voucher No', 'Category', 'Project', 'Particulars', 'Payment Method', 'Expense Amount', 'Status'],
            $excelRows,
            ['date', 'text', 'text', 'text', 'text', 'text', 'currency', 'text'],
            [6],
            'landscape'
        )->stream('expense_ledger_' . date('Ymd_His'));
    }

    public function exportCategorySummaryExcel()
    {
        return $this->_summaryExcel('category');
    }

    public function exportProjectSummaryExcel()
    {
        return $this->_summaryExcel('project');
    }

    public function exportPaymentSummaryExcel()
    {
        return $this->_summaryExcel('payment');
    }

    public function exportMonthlySummaryExcel()
    {
        return $this->_summaryExcel('monthly');
    }

    // =========================================================
    // SUMMARIES (Release 4.9.0F) — one builder per summary feeds the page,
    // the PDF and the Excel export, so all three show the same PAID-only
    // totals for the same optional From/To expense_date range.
    // =========================================================

    private const SUMMARIES = [
        'category' => ['title' => 'Category Summary',       'view' => 'expense_reports/category_summary', 'pdf' => 'pdf/expense_category_summary', 'file' => 'expense_category_summary'],
        'project'  => ['title' => 'Project Summary',        'view' => 'expense_reports/project_summary',  'pdf' => 'pdf/expense_project_summary',  'file' => 'expense_project_summary'],
        'payment'  => ['title' => 'Payment Method Summary', 'view' => 'expense_reports/payment_summary',  'pdf' => 'pdf/expense_payment_summary',  'file' => 'expense_payment_summary'],
        'monthly'  => ['title' => 'Monthly Summary',        'view' => 'expense_reports/monthly_summary',  'pdf' => 'pdf/expense_monthly_summary',  'file' => 'expense_monthly_summary'],
    ];

    private function _summaryPage(string $kind)
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $f = $this->_summaryFilters();

        return view(self::SUMMARIES[$kind]['view'], [
            'title'   => self::SUMMARIES[$kind]['title'],
            'f'       => $f,
            'warning' => $this->_warning($f),
        ] + $this->_summary($kind, $f));
    }

    private function _summaryPdf(string $kind)
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $f = $this->_summaryFilters();

        (new PdfReport())->render(self::SUMMARIES[$kind]['pdf'], $this->_summary($kind, $f), [
            'title'       => self::SUMMARIES[$kind]['title'],
            'orientation' => 'landscape',
            'filters'     => $this->_periodFilters($f),
        ]);
    }

    private function _summaryExcel(string $kind)
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $f    = $this->_summaryFilters();
        $data = $this->_summary($kind, $f);

        $excelRows = [];
        if ($kind === 'monthly') {
            foreach ($data['rows'] as $r) {
                $excelRows[] = [$r['month_label'], $r['count'], $r['total']];
            }
            $headers = ['Month', 'Count', 'Total'];
            $types   = ['text', 'int', 'currency'];
        } else {
            foreach ($data['rows'] as $r) {
                $label = match ($kind) {
                    'category' => $r['category_name'] ?? '',
                    'project'  => $r['project_name'] ?? 'No Project',
                    default    => $r['payment_method'],
                };
                $excelRows[] = [$label, (int) $r['count'], (float) $r['total'], $r['percentage']];
            }
            $headers = [['category' => 'Category', 'project' => 'Project', 'payment' => 'Payment Method'][$kind], 'Count', 'Total', 'Percentage'];
            $types   = ['text', 'int', 'currency', 'number'];
        }

        (new ExcelReport())->table(
            self::SUMMARIES[$kind]['title'],
            $this->_periodFilters($f),
            $headers,
            $excelRows,
            $types,
            [2],
            'landscape'
        )->stream(self::SUMMARIES[$kind]['file'] . '_' . date('Ymd_His'));
    }

    /** Rows + KPIs for one summary. An invalid date range yields an empty summary. */
    private function _summary(string $kind, array $f): array
    {
        $ok   = $f['dates_valid'];
        $from = $f['date_from'] !== '' ? $f['date_from'] : null;
        $to   = $f['date_to'] !== '' ? $f['date_to'] : null;

        if ($kind === 'category' || $kind === 'project') {
            $model = new ExpenseModel();
            $rows  = ! $ok ? [] : ($kind === 'category' ? $model->categoryTotals($from, $to) : $model->projectTotals($from, $to));
            $total = $this->_sum($rows, 'total');

            foreach ($rows as &$r) {
                $r['percentage'] = $total > 0 ? round(((float) $r['total'] / $total) * 100, 1) : 0.0;
            }
            unset($r);

            $count   = count($rows);
            $highest = $rows ? max(array_map('floatval', array_column($rows, 'total'))) : 0.0;

            return [
                'rows'                                                            => $rows,
                ($kind === 'category' ? 'kpi_categories_used' : 'kpi_project_count') => $count,
                'kpi_highest'                                                     => round($highest, 2),
                'kpi_total'                                                       => $total,
                'kpi_average'                                                     => $count > 0 ? round($total / $count, 2) : 0.0,
            ];
        }

        $where  = "WHERE status = 'PAID'";
        $params = [];
        if ($from !== null) {
            $where   .= ' AND expense_date >= ?';
            $params[] = $from;
        }
        if ($to !== null) {
            $where   .= ' AND expense_date <= ?';
            $params[] = $to;
        }
        $db = \Config\Database::connect();

        if ($kind === 'payment') {
            $agg = ! $ok ? [] : $db->query("
                SELECT payment_method, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total
                FROM expenses
                $where
                GROUP BY payment_method
            ", $params)->getResultArray();

            $byMethod = array_column($agg, null, 'payment_method');

            $rows = [];
            foreach (self::PAYMENT_METHODS as $m) {
                $rows[] = [
                    'payment_method' => $m,
                    'count'          => (int) ($byMethod[$m]['count'] ?? 0),
                    'total'          => round((float) ($byMethod[$m]['total'] ?? 0), 2),
                ];
            }

            $grandTotal = $this->_sum($rows, 'total');

            foreach ($rows as &$r) {
                $r['percentage'] = $grandTotal > 0 ? round($r['total'] / $grandTotal * 100, 1) : 0.0;
            }
            unset($r);

            $byTotal = array_column($rows, 'total', 'payment_method');

            return [
                'rows'        => $rows,
                'kpi_total'   => $grandTotal,
                'kpi_cash'    => $byTotal['CASH'] ?? 0.0,
                'kpi_bank'    => $byTotal['BANK'] ?? 0.0,
                'kpi_digital' => round(($byTotal['BANK'] ?? 0.0) + ($byTotal['CHEQUE'] ?? 0.0) + ($byTotal['UPI'] ?? 0.0), 2),
            ];
        }

        $rows = ! $ok ? [] : $db->query("
            SELECT DATE_FORMAT(expense_date, '%Y-%m') AS month_key,
                   COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total
            FROM expenses
            $where
            GROUP BY DATE_FORMAT(expense_date, '%Y-%m')
            ORDER BY month_key DESC
        ", $params)->getResultArray();

        foreach ($rows as &$r) {
            $r['total']       = round((float) $r['total'], 2);
            $r['count']       = (int) $r['count'];
            $r['month_label'] = date('F Y', strtotime($r['month_key'] . '-01'));
        }
        unset($r);

        $count  = count($rows);
        $totals = array_map('floatval', array_column($rows, 'total'));

        return [
            'rows'             => $rows,
            'kpi_total_months' => $count,
            'kpi_highest'      => $totals ? max($totals) : 0.0,
            'kpi_lowest'       => $totals ? min($totals) : 0.0,
            'kpi_average'      => $count > 0 ? round(array_sum($totals) / $count, 2) : 0.0,
        ];
    }

    private function _summaryFilters(): array
    {
        $ok = true;

        return $this->_rangeCheck([
            'date_from'   => $this->_date('date_from', $ok),
            'date_to'     => $this->_date('date_to', $ok),
            'dates_valid' => $ok,
        ]);
    }

    private function _periodFilters(array $f): array
    {
        return [
            'From' => $f['date_from'] !== '' ? pdf_date($f['date_from']) : '',
            'To'   => $f['date_to'] !== '' ? pdf_date($f['date_to']) : '',
        ];
    }

    // =========================================================
    // FILTER HELPERS (adapted from LoanReports.php)
    // =========================================================

    /**
     * Exact Expense Register row query used by index() and exportPdf() — the
     * PDF must show precisely what the screen shows, so this is the single
     * source of truth for that filtered SELECT.
     */
    private function _registerRows(array $f): array
    {
        if (! $f['dates_valid']) {
            return [];
        }

        $db = \Config\Database::connect();
        $b  = $db->table('expenses e')
            ->select('e.*, c.category_name, p.name AS project_name, ba.bank_name, ba.account_name')
            ->join('expense_categories c', 'c.id = e.category_id', 'left')
            ->join('projects p', 'p.id = e.project_id', 'left')
            ->join('bank_accounts ba', 'ba.id = e.bank_account_id', 'left');

        if ($f['category_id'] > 0) {
            $b->where('e.category_id', $f['category_id']);
        }
        if ($f['project_id'] > 0) {
            $b->where('e.project_id', $f['project_id']);
        }
        if ($f['payment_method'] !== '') {
            $b->where('e.payment_method', $f['payment_method']);
        }
        if ($f['status'] !== '') {
            $b->where('e.status', $f['status']);
        }
        if ($f['date_from'] !== '') {
            $b->where('e.expense_date >=', $f['date_from']);
        }
        if ($f['date_to'] !== '') {
            $b->where('e.expense_date <=', $f['date_to']);
        }
        if ($f['search'] !== '') {
            $b->groupStart()
                ->like('e.expense_no', $this->_likeTerm($f['search']))
                ->orLike('e.paid_to', $this->_likeTerm($f['search']))
                ->orLike('e.remarks', $this->_likeTerm($f['search']))
                ->orLike('c.category_name', $this->_likeTerm($f['search']))
                ->orLike('p.name', $this->_likeTerm($f['search']))
                ->groupEnd();
        }

        return $b->orderBy('e.expense_date', 'DESC')->orderBy('e.id', 'DESC')->get()->getResultArray();
    }

    /**
     * Release 4.9.0AK: single-table consolidation of the 4.9.0AJ Expense
     * Ledger. One row per expense — no separate Pending/Paid/Payment-History
     * arrays, since those were always the same rows filtered by status, not
     * distinct data.
     *
     * Release 4.9.0AL audited the actual lifecycle: ExpenseModel allows only
     * PAID / CANCELLED and Expenses::store() always posts payment in the same
     * transaction as creation, so there is no unpaid/partial expense state
     * today. Release 4.9.0AM removed the Outstanding/Net Expense figures that
     * followed from that never-reachable state — Total Expense Paid is the
     * only authoritative total.
     */
    private function _ledger(array $f): array
    {
        $rows = array_reverse($this->_registerRows($f)); // ASC: oldest first, ties by id

        $ledger    = [];
        $paidTotal = 0.0;

        foreach ($rows as $r) {
            $amount = round((float) $r['amount'], 2);
            $status = $r['status'];
            $method = $r['payment_method'];
            $bank   = trim(($r['bank_name'] ?? '') . ' ' . ($r['account_name'] ?? ''));
            $via    = ucfirst(strtolower($method)) . ($bank !== '' ? ' — ' . $bank : '');

            $expenseAmount = $status === 'CANCELLED' ? 0.0 : $amount;

            if ($status === 'PAID') {
                $paidTotal += $amount;
            }

            $particulars = trim($r['paid_to'] . ($r['remarks'] !== null && $r['remarks'] !== '' ? ' — ' . $r['remarks'] : ''));
            if ($status === 'CANCELLED') {
                $particulars = 'Cancelled: ' . $particulars;
            }

            $ledger[] = [
                'id' => (int) $r['id'], 'date' => $r['expense_date'], 'voucher' => $r['expense_no'], 'category' => (string) ($r['category_name'] ?? ''),
                'project' => (string) ($r['project_name'] ?? ''), 'particulars' => $particulars,
                'payment_method' => $via, 'expense_amount' => $expenseAmount, 'status' => $status,
            ];
        }

        return [
            'ledger_rows' => $ledger,
            'summary'     => [
                'paid_total' => round($paidTotal, 2),
            ],
        ];
    }

    private function _registerFilters(array $categoryIds, array $projectIds): array
    {
        $ok = true;

        return $this->_rangeCheck([
            'category_id'    => $this->_id('category_id', $categoryIds),
            'project_id'     => $this->_id('project_id', $projectIds),
            'payment_method' => $this->_enum('payment_method', self::PAYMENT_METHODS),
            'status'         => $this->_enum('status', self::STATUSES),
            'date_from'      => $this->_date('date_from', $ok),
            'date_to'        => $this->_date('date_to', $ok),
            'search'         => $this->_get('search'),
            'dates_valid'    => $ok,
        ]);
    }

    /**
     * Adds `date_error` and settles `dates_valid`: a malformed date (already
     * flagged by _date()) or a From date after the To date leaves the
     * report empty, with a reason.
     */
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

    private function _warning(array $f): ?string
    {
        return ($f['date_error'] ?? '') !== '' ? $f['date_error'] : null;
    }

    /** Trimmed GET string; anything that is not a plain string (arrays) counts as empty. */
    private function _get(string $key): string
    {
        $v = $this->request->getGet($key);

        return is_string($v) ? trim($v) : '';
    }

    /**
     * Search text made safe for LIKE: the Query Builder already binds and
     * quotes it, but leaves the LIKE wildcards (% and _) alone, so escaping
     * just those (and the escape character) here makes the term literal.
     */
    private function _likeTerm(string $search): string
    {
        $esc = \Config\Database::connect()->likeEscapeChar;

        return str_replace([$esc, '%', '_'], [$esc . $esc, $esc . '%', $esc . '_'], $search);
    }

    /** Valid Y-m-d, '' when absent; a malformed date clears $ok so the report returns nothing. */
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

    /** An id that exists in $valid, else 0 (filter ignored). */
    private function _id(string $key, array $valid): int
    {
        $raw = $this->_get($key);

        if (! ctype_digit($raw)) {
            return 0;
        }

        return in_array((int) $raw, array_map('intval', $valid), true) ? (int) $raw : 0;
    }

    private function _enum(string $key, array $allowed): string
    {
        $v = strtoupper($this->_get($key));

        return in_array($v, $allowed, true) ? $v : '';
    }

    // =========================================================
    // READ HELPERS
    // =========================================================

    private function _guard()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('expenses')) {
            return redirect()->to(base_url('dashboard'))->with('error', 'Expense reports are not available yet: the expenses table has not been created.');
        }

        return null;
    }

    private function _sum(array $rows, string $key): float
    {
        return round(array_sum(array_map('floatval', array_column($rows, $key))), 2);
    }
}
