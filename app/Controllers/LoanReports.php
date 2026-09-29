<?php

namespace App\Controllers;

use App\Libraries\ExcelReport;
use App\Libraries\PdfReport;
use CodeIgniter\Controller;

/**
 * Release 4.8.5D: read-only reports over the frozen Loan Management data
 * (loans, loan_emis, loan_payments — 4.8.5A/B/C). Nothing in this controller
 * writes, and it adds no columns, no posting and no business rules.
 *
 * All filtering is server-side through GET parameters, so every KPI describes
 * exactly the rows the table shows. Bad input never raises: an unknown or
 * non-numeric id / enum / amount is ignored (filter off), and a malformed date
 * (or a From date after the To date) makes the report return an empty dataset
 * with a warning. Search text goes through the Query Builder's like() (bound and
 * quoted) after its LIKE wildcards (% and _) are escaped, so it always matches literally.
 *
 * Figures come straight from what the engine maintains, never recomputed:
 *  - outstanding      = loans.outstanding_principal
 *  - EMI balance      = loan_emis.balance_amount (unpaid EMIs are PENDING/PARTIAL)
 *  - payment split    = loan_payments.principal_paid / interest_paid / total_paid
 *  - lender totals    = SUM(sanctioned_amount / total_principal_paid /
 *                       total_interest_paid / outstanding_principal)
 * Dashboard::_loanSummary() uses the same definitions, unfiltered.
 */
class LoanReports extends Controller
{
    private const LOAN_TYPES      = ['BANK', 'PERSONAL', 'VEHICLE', 'OD', 'OTHER'];
    private const STATUSES        = ['ACTIVE', 'CLOSED'];
    private const PAYMENT_METHODS = ['CASH', 'BANK', 'CHEQUE', 'UPI', 'OTHER'];

    // =========================================================
    // PAGES
    // =========================================================

    /** Loan Register: every loan, newest first. */
    public function index()
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $lenders = $this->_lenders();
        $f       = $this->_registerFilters($lenders);
        $rows    = $this->_registerRows($f);

        return view('loan_reports/index', [
            'title'                => 'Loan Register',
            'f'                    => $f,
            'rows'                 => $rows,
            'lenders'              => $lenders,
            'loanTypes'            => self::LOAN_TYPES,
            'statuses'             => self::STATUSES,
            'warning'              => $this->_warning($f),
            'kpi_total_loans'      => count($rows),
            'kpi_total_amount'     => $this->_sum($rows, 'sanctioned_amount'),
            'kpi_outstanding'      => $this->_sum($rows, 'outstanding_principal'),
            'kpi_closed_loans'     => count(array_filter($rows, static fn ($r) => $r['status'] === 'CLOSED')),
        ]);
    }

    /** EMI Due: unpaid instalments of ACTIVE loans, earliest due date first. */
    public function emiDue()
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $lenders = $this->_lenders();
        $f       = $this->_emiDueFilters($lenders);
        $rows    = $this->_emiDueRows($f);

        $monthStart = date('Y-m-01');
        $monthEnd   = date('Y-m-t');

        $overdue  = array_filter($rows, static fn ($r) => $r['days_due'] > 0);
        $upcoming = array_filter($rows, static fn ($r) => $r['days_due'] < 0);
        $month    = array_filter($rows, static fn ($r) => $r['due_date'] >= $monthStart && $r['due_date'] <= $monthEnd);

        return view('loan_reports/emi_due', [
            'title'              => 'EMI Due Report',
            'f'                  => $f,
            'rows'               => $rows,
            'lenders'            => $lenders,
            'loanTypes'          => self::LOAN_TYPES,
            'warning'            => $this->_warning($f),
            'kpi_due_month'      => $this->_sum($month, 'balance_amount'),
            'kpi_due_month_count' => count($month),
            'kpi_overdue_count'  => count($overdue),
            'kpi_upcoming_count' => count($upcoming),
            'kpi_overdue_amount' => $this->_sum($overdue, 'balance_amount'),
        ]);
    }

    /** Loan Payment Register: every loan payment, latest first. */
    public function payments()
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $db      = \Config\Database::connect();
        $lenders = $this->_lenders();
        $loans   = $db->table('loans')->select('id, loan_no, lender_name')->orderBy('loan_no', 'ASC')->get()->getResultArray();
        $banks   = $db->table('bank_accounts')->select('id, bank_name, account_name')->orderBy('bank_name', 'ASC')->get()->getResultArray();
        $f       = $this->_paymentFilters($lenders, array_column($loans, 'id'), array_column($banks, 'id'));
        $rows    = $this->_paymentRows($f);

        return view('loan_reports/payments', [
            'title'              => 'Loan Payment Register',
            'f'                  => $f,
            'rows'               => $rows,
            'lenders'            => $lenders,
            'loans'              => $loans,
            'banks'              => $banks,
            'paymentMethods'     => self::PAYMENT_METHODS,
            'warning'            => $this->_warning($f),
            'kpi_total_payments' => count($rows),
            'kpi_principal'      => $this->_sum($rows, 'principal_paid'),
            'kpi_interest'       => $this->_sum($rows, 'interest_paid'),
            'kpi_collection'     => $this->_sum($rows, 'total_paid'),
        ]);
    }

    /** Outstanding Loan Report: principal still owed per loan, highest first. */
    public function outstanding()
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $lenders = $this->_lenders();
        $f       = $this->_outstandingFilters($lenders);

        // Next EMI = the earliest unpaid instalment, and only while the loan is
        // still ACTIVE (a loan closed by prepayment can keep PENDING rows that
        // are no longer payable).
        $rows = $this->_outstandingRows($f);

        $active      = array_filter($rows, static fn ($r) => $r['status'] === 'ACTIVE');
        $outstanding = $this->_sum($rows, 'outstanding_principal');

        return view('loan_reports/outstanding', [
            'title'                => 'Outstanding Loan Report',
            'f'                    => $f,
            'rows'                 => $rows,
            'lenders'              => $lenders,
            'loanTypes'            => self::LOAN_TYPES,
            'warning'              => null,
            'kpi_outstanding'      => $outstanding,
            'kpi_active_loans'     => count($active),
            'kpi_closed_loans'     => count($rows) - count($active),
            // Average over the loans that still owe something (ACTIVE); closed loans are 0 by definition.
            'kpi_average'          => $active ? round($outstanding / count($active), 2) : 0.0,
        ]);
    }

    /** Lender Summary: one row per lender, highest outstanding first. */
    public function lenderSummary()
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $f = [
            'search'      => $this->_get('search'),
            'active_only' => $this->_get('active_only') === '1',
        ];

        $rows = $this->_lenderRows($f);

        return view('loan_reports/lender_summary', [
            'title'               => 'Lender Summary',
            'f'                   => $f,
            'rows'                => $rows,
            'warning'             => null,
            'kpi_total_lenders'   => count($rows),
            'kpi_borrowed'        => $this->_sum($rows, 'borrowed'),
            'kpi_outstanding'     => $this->_sum($rows, 'outstanding'),
            'kpi_interest_paid'   => $this->_sum($rows, 'interest_paid'),
        ]);
    }

    // =========================================================
    // PDF EXPORTS (Release 4.8.7A) — same filtered rows/KPIs as the pages
    // above, reusing the same filter + row-builder helpers. All landscape;
    // the loan ledger PDF lives on Loans::exportLedgerPdf() instead.
    // =========================================================

    public function exportPdf()
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $lenders = $this->_lenders();
        $f       = $this->_registerFilters($lenders);
        $rows    = $this->_registerRows($f);

        (new PdfReport())->render('pdf/loan_register', [
            'rows'             => $rows,
            'kpi_total_loans'  => count($rows),
            'kpi_total_amount' => $this->_sum($rows, 'sanctioned_amount'),
            'kpi_outstanding'  => $this->_sum($rows, 'outstanding_principal'),
            'kpi_closed_loans' => count(array_filter($rows, static fn ($r) => $r['status'] === 'CLOSED')),
        ], [
            'title'       => 'Loan Register',
            'orientation' => 'landscape',
            'filters'     => [
                'Lender'    => $f['lender'],
                'Loan Type' => $f['loan_type'],
                'Status'    => $f['status'],
                'From'      => $f['date_from'] !== '' ? pdf_date($f['date_from']) : '',
                'To'        => $f['date_to'] !== '' ? pdf_date($f['date_to']) : '',
                'Search'    => $f['search'],
            ],
        ]);
    }

    public function exportEmiDuePdf()
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $lenders = $this->_lenders();
        $f       = $this->_emiDueFilters($lenders);
        $rows    = $this->_emiDueRows($f);

        $monthStart = date('Y-m-01');
        $monthEnd   = date('Y-m-t');
        $overdue    = array_filter($rows, static fn ($r) => $r['days_due'] > 0);
        $month      = array_filter($rows, static fn ($r) => $r['due_date'] >= $monthStart && $r['due_date'] <= $monthEnd);

        (new PdfReport())->render('pdf/loan_emi_due', [
            'rows'                => $rows,
            'kpi_due_month'       => $this->_sum($month, 'balance_amount'),
            'kpi_due_month_count' => count($month),
            'kpi_overdue_count'   => count($overdue),
            'kpi_overdue_amount'  => $this->_sum($overdue, 'balance_amount'),
        ], [
            'title'       => 'EMI Due Report',
            'orientation' => 'landscape',
            'filters'     => [
                'Lender'    => $f['lender'],
                'Loan Type' => $f['loan_type'],
                'From'      => $f['date_from'] !== '' ? pdf_date($f['date_from']) : '',
                'To'        => $f['date_to'] !== '' ? pdf_date($f['date_to']) : '',
                'Search'    => $f['search'],
            ],
        ]);
    }

    public function exportPaymentsPdf()
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $db      = \Config\Database::connect();
        $lenders = $this->_lenders();
        $loans   = $db->table('loans')->select('id, loan_no, lender_name')->orderBy('loan_no', 'ASC')->get()->getResultArray();
        $banks   = $db->table('bank_accounts')->select('id, bank_name, account_name')->orderBy('bank_name', 'ASC')->get()->getResultArray();
        $f       = $this->_paymentFilters($lenders, array_column($loans, 'id'), array_column($banks, 'id'));
        $rows    = $this->_paymentRows($f);

        (new PdfReport())->render('pdf/loan_payments', [
            'rows'               => $rows,
            'kpi_total_payments' => count($rows),
            'kpi_principal'      => $this->_sum($rows, 'principal_paid'),
            'kpi_interest'       => $this->_sum($rows, 'interest_paid'),
            'kpi_collection'     => $this->_sum($rows, 'total_paid'),
        ], [
            'title'       => 'Loan Payment Register',
            'orientation' => 'landscape',
            'filters'     => [
                'From'           => $f['date_from'] !== '' ? pdf_date($f['date_from']) : '',
                'To'             => $f['date_to'] !== '' ? pdf_date($f['date_to']) : '',
                'Lender'         => $f['lender'],
                'Payment Method' => $f['payment_method'],
                'Search'         => $f['search'],
            ],
        ]);
    }

    public function exportOutstandingPdf()
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $lenders     = $this->_lenders();
        $f           = $this->_outstandingFilters($lenders);
        $rows        = $this->_outstandingRows($f);
        $active      = array_filter($rows, static fn ($r) => $r['status'] === 'ACTIVE');
        $outstanding = $this->_sum($rows, 'outstanding_principal');

        (new PdfReport())->render('pdf/loan_outstanding', [
            'rows'             => $rows,
            'kpi_outstanding'  => $outstanding,
            'kpi_active_loans' => count($active),
            'kpi_closed_loans' => count($rows) - count($active),
            'kpi_average'      => $active ? round($outstanding / count($active), 2) : 0.0,
        ], [
            'title'       => 'Outstanding Loan Report',
            'orientation' => 'landscape',
            'filters'     => [
                'Lender'      => $f['lender'],
                'Loan Type'   => $f['loan_type'],
                'Min Amount'  => $f['min_amount'] !== null ? pdf_currency($f['min_amount']) : '',
                'Max Amount'  => $f['max_amount'] !== null ? pdf_currency($f['max_amount']) : '',
                'Search'      => $f['search'],
            ],
        ]);
    }

    public function exportLenderSummaryPdf()
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $f = [
            'search'      => $this->_get('search'),
            'active_only' => $this->_get('active_only') === '1',
        ];

        $rows = $this->_lenderRows($f);

        (new PdfReport())->render('pdf/loan_lender_summary', [
            'rows'              => $rows,
            'kpi_total_lenders' => count($rows),
            'kpi_borrowed'      => $this->_sum($rows, 'borrowed'),
            'kpi_outstanding'   => $this->_sum($rows, 'outstanding'),
            'kpi_interest_paid' => $this->_sum($rows, 'interest_paid'),
        ], [
            'title'       => 'Lender Summary',
            'orientation' => 'landscape',
            'filters'     => [
                'Search'      => $f['search'],
                'Active Only' => $f['active_only'] ? 'Yes' : '',
            ],
        ]);
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

        $lenders = $this->_lenders();
        $f       = $this->_registerFilters($lenders);
        $rows    = $this->_registerRows($f);

        $excelRows = [];
        foreach ($rows as $r) {
            $excelRows[] = [
                $r['loan_no'], $r['lender_name'], $r['loan_type'], $r['start_date'],
                (float) $r['sanctioned_amount'], (float) $r['outstanding_principal'], $r['status'],
            ];
        }

        (new ExcelReport())->table(
            'Loan Register',
            [
                'Lender'    => $f['lender'],
                'Loan Type' => $f['loan_type'],
                'Status'    => $f['status'],
                'From'      => $f['date_from'] !== '' ? pdf_date($f['date_from']) : '',
                'To'        => $f['date_to'] !== '' ? pdf_date($f['date_to']) : '',
                'Search'    => $f['search'],
            ],
            ['Loan No', 'Lender', 'Loan Type', 'Start Date', 'Sanctioned Amount', 'Outstanding Principal', 'Status'],
            $excelRows,
            ['text', 'text', 'text', 'date', 'currency', 'currency', 'text'],
            [4, 5],
            'landscape'
        )->stream('loan_register_' . date('Ymd_His'));
    }

    public function exportEmiDueExcel()
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $lenders = $this->_lenders();
        $f       = $this->_emiDueFilters($lenders);
        $rows    = $this->_emiDueRows($f);

        $excelRows = [];
        foreach ($rows as $r) {
            $excelRows[] = [
                $r['loan_no'], $r['lender_name'], (int) $r['emi_no'], $r['due_date'],
                (float) $r['emi_amount'], (float) $r['paid_amount'], (float) $r['balance_amount'],
                $r['payment_status'], $r['band'], (int) $r['days_due'],
            ];
        }

        (new ExcelReport())->table(
            'EMI Due Report',
            [
                'Lender'    => $f['lender'],
                'Loan Type' => $f['loan_type'],
                'From'      => $f['date_from'] !== '' ? pdf_date($f['date_from']) : '',
                'To'        => $f['date_to'] !== '' ? pdf_date($f['date_to']) : '',
                'Search'    => $f['search'],
            ],
            ['Loan No', 'Lender', 'EMI #', 'Due Date', 'EMI Amount', 'Paid', 'Balance', 'Status', 'Band', 'Days Due'],
            $excelRows,
            ['text', 'text', 'int', 'date', 'currency', 'currency', 'currency', 'text', 'text', 'int'],
            [6],
            'landscape'
        )->stream('loan_emi_due_' . date('Ymd_His'));
    }

    public function exportPaymentsExcel()
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $db      = \Config\Database::connect();
        $lenders = $this->_lenders();
        $loans   = $db->table('loans')->select('id, loan_no, lender_name')->orderBy('loan_no', 'ASC')->get()->getResultArray();
        $banks   = $db->table('bank_accounts')->select('id, bank_name, account_name')->orderBy('bank_name', 'ASC')->get()->getResultArray();
        $f       = $this->_paymentFilters($lenders, array_column($loans, 'id'), array_column($banks, 'id'));
        $rows    = $this->_paymentRows($f);

        $excelRows = [];
        foreach ($rows as $r) {
            $excelRows[] = [
                $r['payment_date'], $r['loan_no'], $r['lender_name'], $r['emi_no'] ?? '',
                $r['payment_method'], $r['bank'] ?? '', $r['reference_no'] ?? '',
                (float) $r['principal_paid'], (float) $r['interest_paid'], (float) $r['total_paid'],
            ];
        }

        (new ExcelReport())->table(
            'Loan Payment Register',
            [
                'From'           => $f['date_from'] !== '' ? pdf_date($f['date_from']) : '',
                'To'             => $f['date_to'] !== '' ? pdf_date($f['date_to']) : '',
                'Lender'         => $f['lender'],
                'Payment Method' => $f['payment_method'],
                'Search'         => $f['search'],
            ],
            ['Date', 'Loan No', 'Lender', 'EMI #', 'Payment Method', 'Bank', 'Reference No', 'Principal Paid', 'Interest Paid', 'Total Paid'],
            $excelRows,
            ['date', 'text', 'text', 'text', 'text', 'text', 'text', 'currency', 'currency', 'currency'],
            [7, 8, 9],
            'landscape'
        )->stream('loan_payments_' . date('Ymd_His'));
    }

    public function exportOutstandingExcel()
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $lenders = $this->_lenders();
        $f       = $this->_outstandingFilters($lenders);
        $rows    = $this->_outstandingRows($f);

        $excelRows = [];
        foreach ($rows as $r) {
            $excelRows[] = [
                $r['loan_no'], $r['lender_name'], $r['loan_type'], (float) $r['outstanding_principal'],
                $r['next_emi_date'] ?? '', $r['next_emi_amount'] !== null ? (float) $r['next_emi_amount'] : null, $r['status'],
            ];
        }

        (new ExcelReport())->table(
            'Outstanding Loan Report',
            [
                'Lender'     => $f['lender'],
                'Loan Type'  => $f['loan_type'],
                'Min Amount' => $f['min_amount'] !== null ? pdf_currency($f['min_amount']) : '',
                'Max Amount' => $f['max_amount'] !== null ? pdf_currency($f['max_amount']) : '',
                'Search'     => $f['search'],
            ],
            ['Loan No', 'Lender', 'Loan Type', 'Outstanding Principal', 'Next EMI Date', 'Next EMI Amount', 'Status'],
            $excelRows,
            ['text', 'text', 'text', 'currency', 'date', 'currency', 'text'],
            [3, 5],
            'landscape'
        )->stream('loan_outstanding_' . date('Ymd_His'));
    }

    public function exportLenderSummaryExcel()
    {
        if ($redirect = $this->_guard()) {
            return $redirect;
        }

        $f = [
            'search'      => $this->_get('search'),
            'active_only' => $this->_get('active_only') === '1',
        ];

        $rows = $this->_lenderRows($f);

        $excelRows = [];
        foreach ($rows as $r) {
            $excelRows[] = [
                $r['lender_name'], (int) $r['loan_count'], (float) $r['borrowed'],
                (float) $r['principal_paid'], (float) $r['interest_paid'], (float) $r['outstanding'],
                (int) $r['active_loans'], (int) $r['closed_loans'],
            ];
        }

        (new ExcelReport())->table(
            'Lender Summary',
            [
                'Search'      => $f['search'],
                'Active Only' => $f['active_only'] ? 'Yes' : '',
            ],
            ['Lender', 'Loan Count', 'Borrowed', 'Principal Paid', 'Interest Paid', 'Outstanding', 'Active Loans', 'Closed Loans'],
            $excelRows,
            ['text', 'int', 'currency', 'currency', 'currency', 'currency', 'int', 'int'],
            [2, 3, 4, 5],
            'landscape'
        )->stream('loan_lender_summary_' . date('Ymd_His'));
    }

    // =========================================================
    // ROW BUILDERS — the exact queries each page above uses, reused as-is
    // by the matching export*Pdf() action so a PDF always shows precisely
    // what the screen shows.
    // =========================================================

    private function _registerRows(array $f): array
    {
        if (! $f['dates_valid']) {
            return [];
        }

        $b = \Config\Database::connect()->table('loans l')->select('l.*');

        if ($f['lender'] !== '') {
            $b->where('l.lender_name', $f['lender']);
        }
        if ($f['loan_type'] !== '') {
            $b->where('l.loan_type', $f['loan_type']);
        }
        if ($f['status'] !== '') {
            $b->where('l.status', $f['status']);
        }
        if ($f['date_from'] !== '') {
            $b->where('l.start_date >=', $f['date_from']);
        }
        if ($f['date_to'] !== '') {
            $b->where('l.start_date <=', $f['date_to']);
        }
        if ($f['search'] !== '') {
            $b->groupStart()
                ->like('l.loan_no', $this->_likeTerm($f['search']))
                ->orLike('l.lender_name', $this->_likeTerm($f['search']))
                ->orLike('l.account_number', $this->_likeTerm($f['search']))
                ->orLike('l.remarks', $this->_likeTerm($f['search']))
                ->groupEnd();
        }

        return $b->orderBy('l.start_date', 'DESC')->orderBy('l.id', 'DESC')->get()->getResultArray();
    }

    private function _emiDueRows(array $f): array
    {
        if (! $f['dates_valid']) {
            return [];
        }

        $b = \Config\Database::connect()->table('loan_emis le')
            ->select('le.id, le.emi_no, le.due_date, le.emi_amount, le.paid_amount, le.balance_amount, le.payment_status,
                      l.id AS loan_id, l.loan_no, l.lender_name, l.loan_type')
            ->join('loans l', 'l.id = le.loan_id')
            ->where('l.status', 'ACTIVE')
            ->whereIn('le.payment_status', ['PENDING', 'PARTIAL']);

        if ($f['lender'] !== '') {
            $b->where('l.lender_name', $f['lender']);
        }
        if ($f['loan_type'] !== '') {
            $b->where('l.loan_type', $f['loan_type']);
        }
        if ($f['date_from'] !== '') {
            $b->where('le.due_date >=', $f['date_from']);
        }
        if ($f['date_to'] !== '') {
            $b->where('le.due_date <=', $f['date_to']);
        }
        if ($f['search'] !== '') {
            $b->groupStart()
                ->like('l.loan_no', $this->_likeTerm($f['search']))
                ->orLike('l.lender_name', $this->_likeTerm($f['search']))
                ->groupEnd();
        }

        $rows = $b->orderBy('le.due_date', 'ASC')->orderBy('l.id', 'ASC')->orderBy('le.emi_no', 'ASC')->get()->getResultArray();

        $today = new \DateTime('today');
        foreach ($rows as &$row) {
            // Positive = overdue by that many days, 0 = due today, negative = still to come.
            $row['days_due'] = (int) (new \DateTime($row['due_date']))->diff($today)->format('%r%a');
            $row['band']     = $this->_dueBand($row['days_due']);
        }
        unset($row);

        return $rows;
    }

    private function _paymentRows(array $f): array
    {
        if (! $f['dates_valid']) {
            return [];
        }

        $b = \Config\Database::connect()->table('loan_payments lp')
            ->select("lp.id, lp.payment_date, lp.loan_emi_id, lp.payment_method, lp.reference_no,
                      lp.principal_paid, lp.interest_paid, lp.total_paid,
                      l.id AS loan_id, l.loan_no, l.lender_name, le.emi_no,
                      CONCAT(ba.bank_name, ' - ', ba.account_name) AS bank", false)
            ->join('loans l', 'l.id = lp.loan_id')
            ->join('loan_emis le', 'le.id = lp.loan_emi_id', 'left')
            ->join('bank_accounts ba', 'ba.id = lp.bank_account_id', 'left');

        if ($f['date_from'] !== '') {
            $b->where('lp.payment_date >=', $f['date_from']);
        }
        if ($f['date_to'] !== '') {
            $b->where('lp.payment_date <=', $f['date_to']);
        }
        if ($f['lender'] !== '') {
            $b->where('l.lender_name', $f['lender']);
        }
        if ($f['loan_id'] > 0) {
            $b->where('lp.loan_id', $f['loan_id']);
        }
        if ($f['payment_method'] !== '') {
            $b->where('lp.payment_method', $f['payment_method']);
        }
        if ($f['bank_id'] > 0) {
            $b->where('lp.bank_account_id', $f['bank_id']);
        }
        if ($f['search'] !== '') {
            $b->groupStart()
                ->like('l.loan_no', $this->_likeTerm($f['search']))
                ->orLike('l.lender_name', $this->_likeTerm($f['search']))
                ->orLike('lp.reference_no', $this->_likeTerm($f['search']))
                ->orLike('lp.remarks', $this->_likeTerm($f['search']))
                ->groupEnd();
        }

        return $b->orderBy('lp.payment_date', 'DESC')->orderBy('lp.id', 'DESC')->get()->getResultArray();
    }

    private function _outstandingRows(array $f): array
    {
        $b = \Config\Database::connect()->table('loans l')
            ->select("l.*, ne.due_date AS next_emi_date, ne.emi_amount AS next_emi_amount", false)
            ->join(
                'loan_emis ne',
                "ne.id = (SELECT e2.id FROM loan_emis e2
                          WHERE e2.loan_id = l.id AND e2.payment_status IN ('PENDING', 'PARTIAL')
                          ORDER BY e2.due_date ASC, e2.emi_no ASC LIMIT 1) AND l.status = 'ACTIVE'",
                'left',
                false
            );

        if ($f['lender'] !== '') {
            $b->where('l.lender_name', $f['lender']);
        }
        if ($f['loan_type'] !== '') {
            $b->where('l.loan_type', $f['loan_type']);
        }
        if ($f['min_amount'] !== null) {
            $b->where('l.outstanding_principal >=', $f['min_amount']);
        }
        if ($f['max_amount'] !== null) {
            $b->where('l.outstanding_principal <=', $f['max_amount']);
        }
        if ($f['search'] !== '') {
            $b->groupStart()
                ->like('l.loan_no', $this->_likeTerm($f['search']))
                ->orLike('l.lender_name', $this->_likeTerm($f['search']))
                ->groupEnd();
        }

        return $b->orderBy('l.outstanding_principal', 'DESC')->orderBy('l.id', 'ASC')->get()->getResultArray();
    }

    private function _lenderRows(array $f): array
    {
        $b = \Config\Database::connect()->table('loans l')
            ->select("l.lender_name,
                      COUNT(*) AS loan_count,
                      COALESCE(SUM(l.sanctioned_amount), 0) AS borrowed,
                      COALESCE(SUM(l.total_principal_paid), 0) AS principal_paid,
                      COALESCE(SUM(l.total_interest_paid), 0) AS interest_paid,
                      COALESCE(SUM(l.outstanding_principal), 0) AS outstanding,
                      COALESCE(SUM(l.status = 'ACTIVE'), 0) AS active_loans,
                      COALESCE(SUM(l.status = 'CLOSED'), 0) AS closed_loans", false)
            ->groupBy('l.lender_name');

        if ($f['search'] !== '') {
            $b->like('l.lender_name', $this->_likeTerm($f['search']));
        }
        if ($f['active_only']) {
            // Lenders with at least one ACTIVE loan; their figures still cover every loan.
            $b->having("SUM(l.status = 'ACTIVE') >", 0, false);
        }

        return $b->orderBy('outstanding', 'DESC')->orderBy('l.lender_name', 'ASC')->get()->getResultArray();
    }

    // =========================================================
    // FILTER HELPERS
    // =========================================================

    private function _registerFilters(array $lenders): array
    {
        $ok = true;

        return $this->_rangeCheck([
            'lender'      => $this->_lender($lenders),
            'loan_type'   => $this->_enum('loan_type', self::LOAN_TYPES),
            'status'      => $this->_enum('status', self::STATUSES),
            'date_from'   => $this->_date('date_from', $ok),
            'date_to'     => $this->_date('date_to', $ok),
            'search'      => $this->_get('search'),
            'dates_valid' => $ok,
        ]);
    }

    private function _emiDueFilters(array $lenders): array
    {
        $ok = true;

        return $this->_rangeCheck([
            'lender'      => $this->_lender($lenders),
            'loan_type'   => $this->_enum('loan_type', self::LOAN_TYPES),
            'date_from'   => $this->_date('date_from', $ok),
            'date_to'     => $this->_date('date_to', $ok),
            'search'      => $this->_get('search'),
            'dates_valid' => $ok,
        ]);
    }

    private function _paymentFilters(array $lenders, array $loanIds, array $bankIds): array
    {
        $ok = true;

        return $this->_rangeCheck([
            'date_from'      => $this->_date('date_from', $ok),
            'date_to'        => $this->_date('date_to', $ok),
            'lender'         => $this->_lender($lenders),
            'loan_id'        => $this->_id('loan_id', $loanIds),
            'payment_method' => $this->_enum('payment_method', self::PAYMENT_METHODS),
            'bank_id'        => $this->_id('bank_account_id', $bankIds),
            'search'         => $this->_get('search'),
            'dates_valid'    => $ok,
        ]);
    }

    private function _outstandingFilters(array $lenders): array
    {
        return [
            'lender'     => $this->_lender($lenders),
            'loan_type'  => $this->_enum('loan_type', self::LOAN_TYPES),
            'min_amount' => $this->_amount('min_amount'),
            'max_amount' => $this->_amount('max_amount'),
            'search'     => $this->_get('search'),
        ];
    }

    /**
     * Adds `date_error` and settles `dates_valid` for a filter set holding
     * date_from / date_to: a malformed date (already flagged by _date()) or a
     * From date after the To date leaves the report empty, with a reason.
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
     * Search text made safe for LIKE: the Query Builder already binds and quotes
     * it (no SQL injection), but it leaves the LIKE wildcards alone, so a search
     * for "%" or "_" would match every row. Escaping just those (and the escape
     * character itself) here makes the term literal; the builder adds the matching
     * ESCAPE clause. Deliberately not escapeLikeString(): on MySQL that also
     * quote-escapes the text, and the builder would then escape the quote a second
     * time, so a search for O'Brien would never match.
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

    /** A lender name that exists on some loan (exact, case-insensitive), else '' (filter ignored). */
    private function _lender(array $lenders): string
    {
        $raw = $this->_get('lender');

        if ($raw === '') {
            return '';
        }

        foreach ($lenders as $name) {
            if (strcasecmp($name, $raw) === 0) {
                return $name;
            }
        }

        return '';
    }

    /** Non-negative number or null (filter ignored). */
    private function _amount(string $key): ?float
    {
        $raw = $this->_get($key);

        return ($raw !== '' && is_numeric($raw) && (float) $raw >= 0) ? (float) $raw : null;
    }

    // =========================================================
    // READ HELPERS
    // =========================================================

    /**
     * Loan tables come from the 4.8.5A migrations; on a database that has not
     * run them yet the reports send the user back to the dashboard instead of
     * failing on a missing table.
     */
    private function _guard()
    {
        $db = \Config\Database::connect();

        foreach (['loans', 'loan_emis', 'loan_payments'] as $table) {
            if (! $db->tableExists($table)) {
                return redirect()->to(base_url('dashboard'))->with('error', 'Loan reports are not available yet: the loan tables have not been created.');
            }
        }

        return null;
    }

    private function _lenders(): array
    {
        return array_column(
            \Config\Database::connect()->table('loans')->distinct()->select('lender_name')->orderBy('lender_name', 'ASC')->get()->getResultArray(),
            'lender_name'
        );
    }

    private function _sum(array $rows, string $key): float
    {
        return round(array_sum(array_column($rows, $key)), 2);
    }

    /** Badge band for an unpaid EMI, from days past due (negative = not due yet). */
    private function _dueBand(int $days): string
    {
        if ($days < 0) {
            return 'upcoming';
        }
        if ($days === 0) {
            return 'today';
        }
        if ($days <= 30) {
            return 'overdue-1-30';
        }
        if ($days <= 60) {
            return 'overdue-31-60';
        }

        return 'overdue-60-plus';
    }
}
