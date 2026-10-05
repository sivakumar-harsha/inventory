<?php

namespace App\Controllers;

use App\Libraries\CashMovements;
use App\Libraries\MonthlyStatement;
use App\Models\CashOpeningBalanceModel;
use App\Models\LoanModel;
use App\Models\ProjectModel;
use CodeIgniter\Controller;

class Dashboard extends Controller
{
    /**
     * Dashboard cleanup: a summary-first overview. Every financial figure is read
     * from the authoritative calculation of its own module, nothing is re-derived here:
     *
     * - Cash:        Cash Book closing cash (_cashClosing(), the Cash Book's own walk over
     *                CashMovements::all() from the stored opening cash, no To date).
     * - Bank:        SUM(bank_accounts.current_balance), as the Bank Accounts page.
     * - Money In/Out (this month): MonthlyStatement::build() total receipts / total payments
     *                (bank + cash; internal transfers are reported separately and excluded).
     * - Net Profit:  the P&L definition (Reports::profitLoss): Sales + Direct Income -
     *                allocated purchase cost - PAID expenses.
     * - Outstanding Collection / Remaining Balance: ProjectModel::getFinancialSummary()
     *                ('project_outstanding_collection' and 'remaining_billable_value').
     */
    public function index()
    {
        $db = \Config\Database::connect();

        // Project status breakdown, using the 'status' enum already on projects.
        $projectModel               = new ProjectModel();
        $data['active_projects']    = $projectModel->where('status', 'ACTIVE')->countAllResults();
        $data['completed_projects'] = $projectModel->where('status', 'COMPLETED')->countAllResults();
        $data['onhold_projects']    = $projectModel->where('status', 'ON_HOLD')->countAllResults();

        $data['total_sales']     = (float) $db->query("SELECT COALESCE(SUM(total_amount),0) AS t FROM sales")->getRow()->t;
        $data['total_purchases'] = (float) $db->query("SELECT COALESCE(SUM(total_amount),0) AS t FROM purchases")->getRow()->t;
        // PAID only, exactly as the P&L, Expense Register and the Expense widget below.
        $data['total_expenses']  = (float) $db->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE status = 'PAID'")->getRow()->t;

        // Net Profit = Reports::profitLoss() net_profit with no filters:
        // (Sales + DIRECT_INCOME receipts) - allocated purchase cost - PAID expenses.
        $totalDirectIncome        = (float) $db->query("SELECT COALESCE(SUM(amount),0) AS t FROM project_cash_receipts WHERE receipt_type = 'DIRECT_INCOME'")->getRow()->t;
        $totalCogs                = array_sum($projectModel->getAllocatedPurchaseCostByProject(null, null, null));
        $data['total_net_profit'] = $data['total_sales'] + $totalDirectIncome - $totalCogs - $data['total_expenses'];

        // One pass over the projects through the shared financial summary: the Outstanding Collection
        // total and the Top Pending Projects list both come from it.
        $outstandingTotal = 0.0;
        $topPending       = [];
        foreach ($projectModel->orderBy('name', 'ASC')->findAll() as $project) {
            $summary     = $projectModel->getFinancialSummary($project['id']);
            $outstanding = (float) ($summary['project_outstanding_collection'] ?? 0);
            if ($outstanding > 0.004) {
                $outstandingTotal += $outstanding;
                $topPending[] = [
                    'project_name'           => $project['name'],
                    // Remaining Balance = MAX(0, Contract - Advance Received - Invoiced); excess is Over Billed.
                    'remaining_balance'      => max(0, $summary['remaining_billable_value']),
                    'over_billed'            => max(0, -$summary['remaining_billable_value']),
                    'outstanding_collection' => $outstanding,
                ];
            }
        }
        usort($topPending, fn($a, $b) => $b['outstanding_collection'] <=> $a['outstanding_collection']);
        $data['total_outstanding']    = round($outstandingTotal, 2);
        $data['top_pending_projects'] = array_slice($topPending, 0, 5);

        $data['cash_balance'] = $this->_cashClosing();
        $data += $this->_monthMoney();

        $data += $this->_serviceSummary($db);
        $data += $this->_loanSummary($db);
        $data += $this->_expenseSummary($db);
        $data += $this->_bankSummary($db);
        $data += $this->_backupStatus($db);

        return view('dashboard/index', $data);
    }

    /**
     * Cash Book closing cash with no date filter: the same walk as CashBook::_statement()
     * (movements ordered by date, starting from the stored opening cash, earlier movements ignored).
     */
    private function _cashClosing(): float
    {
        try {
            $moves = (new CashMovements())->all();
            usort($moves, static fn ($a, $b) => [$a['date'], $a['seq']] <=> [$b['date'], $b['seq']]);

            $ob      = CashOpeningBalanceModel::effective((new CashOpeningBalanceModel())->current(), null);
            $balance = $ob ? $ob['amount'] : 0.0;
            foreach ($moves as $m) {
                if ($ob && $m['date'] < $ob['opening_date']) {
                    continue;
                }
                $balance = round($balance + $m['in'] - $m['out'], 2);
            }

            return (float) $balance;
        } catch (\Throwable $e) {
            log_message('error', 'Dashboard cash closing failed: ' . $e->getMessage());

            return 0.0;
        }
    }

    /**
     * This month's Money In / Money Out from the Monthly Statement engine (bank + cash). Its transfers
     * are a separate section, so neither figure includes an internal transfer.
     */
    private function _monthMoney(): array
    {
        $empty = ['money_available' => false, 'money_in' => 0.0, 'money_out' => 0.0];

        try {
            $stmt = (new MonthlyStatement())->build(date('Y-m'));

            return [
                'money_available' => true,
                'money_in'        => (float) $stmt['total_receipts']['total'],
                'money_out'       => (float) $stmt['total_payments']['total'],
            ];
        } catch (\Throwable $e) {
            log_message('error', 'Dashboard month money failed: ' . $e->getMessage());

            return $empty;
        }
    }

    /** Compact backup status for the one-line Dashboard indicator (guarded: the table may not exist). */
    private function _backupStatus($db): array
    {
        $empty = ['backup_available' => false, 'backup_last_at' => null, 'backup_count' => 0, 'backup_restore_failed' => false];

        try {
            if (! $db->tableExists('database_backups')) {
                return $empty;
            }

            $latest      = $db->table('database_backups')->where('backup_type !=', 'RESTORE')->orderBy('created_at', 'DESC')->get(1)->getRowArray();
            $lastRestore = $db->table('database_backups')->where('backup_type', 'RESTORE')->orderBy('created_at', 'DESC')->get(1)->getRowArray();

            return [
                'backup_available'      => true,
                'backup_last_at'        => $latest['created_at'] ?? null,
                'backup_count'          => $db->table('database_backups')->where('backup_type !=', 'RESTORE')->countAllResults(),
                'backup_restore_failed' => $lastRestore && $lastRestore['status'] === 'FAILED',
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    /**
     * Release 4.8.4E: Service Received KPIs, read-only (dashboard cleanup: the latest receipts / payments lists were removed).
     * Guarded by tableExists() so a database that has not run the 4.8.4
     * migrations yet still renders every existing dashboard card.
     *
     * - Total Service Income: everything billed on service receipts (INVOICE
     *   and DIRECT grand_total), the service counterpart of Total Sales.
     * - Service Outstanding / Pending Invoices: INVOICE receipts still
     *   PENDING or PARTIAL, using each receipt's own outstanding_amount, so it
     *   matches the Outstanding Service Report.
     * - Today's Collection: the same three sources as the Service Collection
     *   Report (amount taken at receipt time, voucher allocations, advances).
     */
    private function _serviceSummary($db): array
    {
        $empty = [
            'service_available'         => false,
            'service_today_collection'  => 0.0,
            'service_outstanding'       => 0.0,
            'service_pending_invoices'  => 0,
            'service_total_income'      => 0.0,
        ];

        foreach (['service_receipts', 'customer_payments', 'customer_payment_allocations'] as $table) {
            if (! $db->tableExists($table)) {
                return $empty;
            }
        }

        try {
            $today = date('Y-m-d');

            $initial = (float) $db->query("
                SELECT COALESCE(SUM(GREATEST(0, sr.received_amount - COALESCE(al.allocated, 0))), 0) AS t
                FROM service_receipts sr
                LEFT JOIN (SELECT service_receipt_id, SUM(paid_amount) AS allocated
                           FROM customer_payment_allocations GROUP BY service_receipt_id) al ON al.service_receipt_id = sr.id
                WHERE sr.receipt_date = ?
            ", [$today])->getRow()->t;

            $vouchers = (float) $db->query("
                SELECT COALESCE(SUM(COALESCE(al.allocated, 0) + cp.advance_amount), 0) AS t
                FROM customer_payments cp
                LEFT JOIN (SELECT customer_payment_id, SUM(paid_amount) AS allocated
                           FROM customer_payment_allocations GROUP BY customer_payment_id) al ON al.customer_payment_id = cp.id
                WHERE cp.payment_date = ?
            ", [$today])->getRow()->t;

            $open = $db->query("
                SELECT COALESCE(SUM(outstanding_amount), 0) AS outstanding,
                       COALESCE(SUM(payment_status = 'PENDING'), 0) AS pending_count
                FROM service_receipts
                WHERE receipt_type = 'INVOICE' AND payment_status IN ('PENDING', 'PARTIAL')
            ")->getRow();

            return [
                'service_available'        => true,
                'service_today_collection' => round($initial + $vouchers, 2),
                'service_outstanding'      => round((float) $open->outstanding, 2),
                'service_pending_invoices' => (int) $open->pending_count,
                'service_total_income'     => (float) $db->query("SELECT COALESCE(SUM(grand_total), 0) AS t FROM service_receipts")->getRow()->t,
            ];
        } catch (\Throwable $e) {
            log_message('error', 'Dashboard service summary failed: ' . $e->getMessage());
            return $empty;
        }
    }

    /**
     * Release 4.8.5D: Loan KPIs + the next 5 upcoming / overdue EMIs, read-only. Guarded by
     * tableExists() so a database that has not run the 4.8.5A migrations yet
     * still renders every existing dashboard card (the section is just hidden).
     *
     * The figures are the unfiltered versions of the Loan Reports:
     * - Outstanding Loans: SUM(sanctioned amount - SUM(loan_payments.total_paid)),
     *   as Loan Register / Outstanding / Lender Summary (Release 4.9.0BA).
     * - EMI Due This Month: SUM(loan_emis.balance_amount) of unpaid (PENDING /
     *   PARTIAL) EMIs of ACTIVE loans due this calendar month, as the EMI Due
     *   Report's "EMI Due This Month".
     * Upcoming EMIs lists the next 5 unpaid EMIs of ACTIVE loans, earliest due date first,
     * so anything already overdue leads the list.
     *
     * loan_active_count / loan_emi_due_count feed the KPI subtitles (the count uses the same
     * WHERE as EMI Due This Month). Dashboard cleanup: Total Paid, Closed Loans and the latest
     * payments list were removed (Loan Reports hold them).
     */
    private function _loanSummary($db): array
    {
        $empty = [
            'loan_available'       => false,
            'loan_outstanding'     => 0.0,
            'loan_emi_due_month'   => 0.0,
            'loan_active_count'    => 0,
            'loan_emi_due_count'   => 0,
            'loan_overdue_count'   => 0,
            'loan_upcoming_emis'   => [],
        ];

        foreach (['loans', 'loan_emis', 'loan_payments'] as $table) {
            if (! $db->tableExists($table)) {
                return $empty;
            }
        }

        try {
            $totals = $db->query("
                SELECT COALESCE(SUM(" . LoanModel::outstandingSql('l') . "), 0) AS outstanding,
                       COALESCE(SUM(l.status = 'ACTIVE'), 0)     AS active_count
                FROM loans l
            ")->getRow();

            $due = $db->query("
                SELECT COALESCE(SUM(le.balance_amount), 0) AS t, COUNT(le.id) AS c
                FROM loan_emis le
                JOIN loans l ON l.id = le.loan_id
                WHERE l.status = 'ACTIVE'
                  AND le.payment_status IN ('PENDING', 'PARTIAL')
                  AND le.due_date >= ? AND le.due_date <= ?
            ", [date('Y-m-01'), date('Y-m-t')])->getRow();

            // Attention indicator: unpaid EMIs of ACTIVE loans already past their due date (same WHERE as the list below).
            $overdue = (int) $db->query("
                SELECT COUNT(le.id) AS c
                FROM loan_emis le
                JOIN loans l ON l.id = le.loan_id
                WHERE l.status = 'ACTIVE' AND le.payment_status IN ('PENDING', 'PARTIAL') AND le.due_date < CURDATE()
            ")->getRow()->c;

            return [
                'loan_available'       => true,
                'loan_outstanding'     => round((float) $totals->outstanding, 2),
                'loan_emi_due_month'   => round((float) $due->t, 2),
                'loan_active_count'    => (int) $totals->active_count,
                'loan_emi_due_count'   => (int) $due->c,
                'loan_overdue_count'   => $overdue,
                'loan_upcoming_emis'   => $db->query("
                    SELECT le.id, le.emi_no, le.due_date, le.emi_amount, le.balance_amount, l.id AS loan_id, l.loan_no, l.lender_name
                    FROM loan_emis le
                    JOIN loans l ON l.id = le.loan_id
                    WHERE l.status = 'ACTIVE' AND le.payment_status IN ('PENDING', 'PARTIAL')
                    ORDER BY le.due_date ASC, l.id ASC, le.emi_no ASC LIMIT 5
                ")->getResultArray(),
            ];
        } catch (\Throwable $e) {
            log_message('error', 'Dashboard loan summary failed: ' . $e->getMessage());
            return $empty;
        }
    }

    /**
     * Release 4.8.6D: Expense Today / This Month KPIs, read-only. Guarded by
     * tableExists() so a database that has not run the 4.8.6A migrations yet
     * still renders every existing dashboard card (the section is just
     * hidden). Figures are the same unfiltered PAID-only definitions
     * ExpenseReports.php uses (Expense Register's Today / This Month KPIs). The all-time
     * total is the headline Total Expenses card (index()), not repeated here.
     */
    private function _expenseSummary($db): array
    {
        $empty = [
            'expense_available'      => false,
            'expense_today'          => 0.0,
            'expense_month'          => 0.0,
        ];

        foreach (['expenses'] as $table) {
            if (! $db->tableExists($table)) {
                return $empty;
            }
        }

        try {
            $today = $db->query("
                SELECT COALESCE(SUM(amount), 0) AS t
                FROM expenses WHERE status = 'PAID' AND expense_date = CURDATE()
            ")->getRow()->t;

            $month = $db->query("
                SELECT COALESCE(SUM(amount), 0) AS t
                FROM expenses
                WHERE status = 'PAID' AND expense_date >= ? AND expense_date <= ?
            ", [date('Y-m-01'), date('Y-m-t')])->getRow()->t;

            return [
                'expense_available'       => true,
                'expense_today'           => round((float) $today, 2),
                'expense_month'           => round((float) $month, 2),
            ];
        } catch (\Throwable $e) {
            log_message('error', 'Dashboard expense summary failed: ' . $e->getMessage());
            return $empty;
        }
    }

    /**
     * Release 4.8.4I: Bank summary, read-only. Guarded by tableExists() so a database
     * without the bank tables still renders every other dashboard card.
     *
     * - Total Bank Balance: SUM(current_balance) of bank_accounts, the same figure as the
     *   Bank Accounts page.
     * - Low balance: accounts whose current_balance is below zero (the attention alert).
     *
     * Dashboard cleanup: the latest transactions / transfers lists and the monthly deposit /
     * withdrawal and account-count figures were removed; Money In / Out come from the Monthly
     * Statement (_monthMoney()) and the history lives in Bank Transactions / Bank Statement.
     */
    private function _bankSummary($db): array
    {
        $empty = [
            'bank_available'     => false,
            'bank_total_balance' => 0.0,
            'bank_low_balance'   => [],
        ];

        foreach (['bank_accounts', 'bank_transactions'] as $table) {
            if (! $db->tableExists($table)) {
                return $empty;
            }
        }

        try {
            $balance = $db->query("SELECT COALESCE(SUM(current_balance), 0) AS balance FROM bank_accounts")->getRow()->balance;

            return [
                'bank_available'     => true,
                'bank_total_balance' => round((float) $balance, 2),
                'bank_low_balance'   => $db->query("
                    SELECT id, bank_name, account_name, account_number, current_balance, is_active
                    FROM bank_accounts
                    WHERE current_balance < 0
                    ORDER BY current_balance ASC
                ")->getResultArray(),
            ];
        } catch (\Throwable $e) {
            log_message('error', 'Dashboard bank summary failed: ' . $e->getMessage());
            return $empty;
        }
    }
}
