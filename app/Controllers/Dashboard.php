<?php

namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\SupplierModel;
use App\Models\CustomerModel;
use App\Models\ProjectModel;
use App\Models\PurchaseModel;
use App\Models\SaleModel;
use App\Models\PaymentModel;
use App\Models\ExpenseModel;
use CodeIgniter\Controller;

class Dashboard extends Controller
{
    public function index()
    {
        $db = \Config\Database::connect();

        $data['total_products']   = (new ProductModel())->countAll();
        $data['total_suppliers']  = (new SupplierModel())->countAll();
        $data['total_customers']  = (new CustomerModel())->countAll();

        // Release 4.3.1 (Phase C): full project status breakdown, using the
        // same 'status' enum (ACTIVE/COMPLETED/ON_HOLD) already on projects.
        $projectModel             = new ProjectModel();
        $data['active_projects']    = $projectModel->where('status', 'ACTIVE')->countAllResults();
        $data['completed_projects'] = $projectModel->where('status', 'COMPLETED')->countAllResults();
        $data['onhold_projects']    = $projectModel->where('status', 'ON_HOLD')->countAllResults();

        $data['total_sales']      = $db->query("SELECT COALESCE(SUM(total_amount),0) AS t FROM sales")->getRow()->t;
        $data['total_purchases']  = $db->query("SELECT COALESCE(SUM(total_amount),0) AS t FROM purchases")->getRow()->t;
        $data['total_expenses']   = $db->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses")->getRow()->t;
        $data['total_payments']   = $db->query("SELECT COALESCE(SUM(amount),0) AS t FROM payments")->getRow()->t;
        $data['total_outstanding']= $db->query("SELECT COALESCE(SUM(balance_amount),0) AS t FROM sales")->getRow()->t;

        // Release 4.5 (Phase 8): Project Cash Receipts — independent ledger,
        // does not touch sales.balance_amount/payments. Cash Received KPI is
        // simply Invoice Payments + Project Cash Receipts (no netting).
        $data['total_project_cash_received'] = (float) $db->query("SELECT COALESCE(SUM(amount),0) AS t FROM project_cash_receipts")->getRow()->t;
        $data['total_cash_received'] = (float) $data['total_payments'] + $data['total_project_cash_received'];

        // Release 4.6.5 (Direct Project Income): revenue also includes
        // DIRECT_INCOME-type Project Cash Receipts (cash that will never be
        // invoiced) — ADVANCE-type receipts never enter this figure, same
        // rule as Reports::profitLoss().
        $data['total_direct_income'] = (float) $db->query("SELECT COALESCE(SUM(amount),0) AS t FROM project_cash_receipts WHERE receipt_type = 'DIRECT_INCOME'")->getRow()->t;

        // Release 4.3.2: overall Net Profit KPI for the redesigned dashboard,
        // derived from the totals already fetched above (no new query).
        // Release 4.6.5: + Direct Income, matching the P&L revenue formula —
        // this is the exact figure that previously showed ₹0 revenue/negative
        // profit for a project paid entirely in un-invoiced cash.
        $data['total_net_profit'] = $data['total_sales'] - $data['total_purchases'] - $data['total_expenses'] + $data['total_direct_income'];

        // Release 4.3.1 (Bug 1 fix): "This month" must be measured against the
        // business transaction date (sale_date / purchase_date / expense_date),
        // exactly like Reports::profitLoss() does — not against created_at,
        // which only reflects when a row was entered/migrated into the system.
        $monthStart = date('Y-m-01');
        $monthEnd   = date('Y-m-t');

        $data['month_sales'] = $db->query("
            SELECT COALESCE(SUM(total_amount),0) AS t
            FROM sales
            WHERE sale_date >= ? AND sale_date <= ?
        ", [$monthStart, $monthEnd])->getRow()->t;

        $data['month_purchases'] = $db->query("
            SELECT COALESCE(SUM(total_amount),0) AS t
            FROM purchases
            WHERE purchase_date >= ? AND purchase_date <= ?
        ", [$monthStart, $monthEnd])->getRow()->t;

        $data['month_expenses'] = $db->query("
            SELECT COALESCE(SUM(amount),0) AS t
            FROM expenses
            WHERE expense_date >= ? AND expense_date <= ?
        ", [$monthStart, $monthEnd])->getRow()->t;

        // Release 4.3.1 (Phase B): "This Month Net Profit" — same allocation-
        // aware COGS formula Reports::profitLoss() uses (revenue - allocated
        // purchase cost - expenses), reusing ProjectModel's existing method
        // rather than re-deriving the GST-aware allocation logic here.
        $monthCogs = array_sum($projectModel->getAllocatedPurchaseCostByProject(null, $monthStart, $monthEnd));
        $data['month_cogs'] = $monthCogs;

        // Release 4.6.5: This Month's Direct Income, filtered on receipt_date
        // the same way month_sales is filtered on sale_date — added to
        // This Month Net Profit for the same reason as the overall KPI above.
        $data['month_direct_income'] = (float) $db->query("
            SELECT COALESCE(SUM(amount),0) AS t
            FROM project_cash_receipts
            WHERE receipt_type = 'DIRECT_INCOME' AND receipt_date >= ? AND receipt_date <= ?
        ", [$monthStart, $monthEnd])->getRow()->t;

        $data['month_net_profit'] = $data['month_sales'] - $monthCogs - $data['month_expenses'] + $data['month_direct_income'];

        $data['recent_sales'] = $db->query("
            SELECT s.*, p.name AS project_name, c.name AS customer_name
            FROM sales s
            LEFT JOIN projects p ON s.project_id = p.id
            LEFT JOIN customers c ON s.customer_id = c.id
            ORDER BY s.created_at DESC LIMIT 5
        ")->getResultArray();

        $data['recent_purchases'] = $db->query("
            SELECT pu.*, s.name AS supplier_name, p.name AS project_name
            FROM purchases pu
            LEFT JOIN suppliers s ON pu.supplier_id = s.id
            LEFT JOIN projects p ON pu.project_id = p.id
            ORDER BY pu.created_at DESC LIMIT 5
        ")->getResultArray();

        // Release 4.3.1 (Phase D): Recent Payments / Recent Expenses activity,
        // for parity with the existing Recent Sales / Recent Purchases panels.
        $data['recent_payments'] = $db->query("
            SELECT pay.*, s.invoice_no AS invoice_no, c.name AS customer_name
            FROM payments pay
            LEFT JOIN sales s ON pay.sale_id = s.id
            LEFT JOIN customers c ON s.customer_id = c.id
            ORDER BY pay.created_at DESC LIMIT 5
        ")->getResultArray();

        $data['recent_expenses'] = $db->query("
            SELECT e.*, p.name AS project_name
            FROM expenses e
            LEFT JOIN projects p ON e.project_id = p.id
            ORDER BY e.created_at DESC LIMIT 5
        ")->getResultArray();

        // Release 4.3.1 (Phase E): Top Pending Projects — reuses
        // ProjectModel::getFinancialSummary() (the same source Project
        // Statement uses) rather than re-deriving outstanding balances here.
        // Project count is small (single digits to low tens), so a per-
        // project call is the correct trade-off against duplicating the
        // FIFO-aware outstanding-collection formula in raw SQL.
        // Release 4.5.1: filter/display net of Project Cash Receipts — a
        // project whose available cash already covers its invoice
        // outstanding is no longer actually "pending".
        // Release 4.5.6 (Phase 4): Outstanding Collection is invoice-only —
        // outstanding_collection_balance (SUM(sales.balance_amount)-derived),
        // never netted against Project Cash Receipts.
        $topPending = [];
        foreach ($projectModel->orderBy('name', 'ASC')->findAll() as $project) {
            $summary = $projectModel->getFinancialSummary($project['id']);
            if (($summary['outstanding_collection_balance'] ?? 0) > 0.004) {
                $topPending[] = [
                    'project_name'            => $project['name'],
                    'remaining_balance'       => $summary['remaining_billable_value'],
                    'outstanding_collection'  => $summary['outstanding_collection_balance'],
                ];
            }
        }
        usort($topPending, fn($a, $b) => $b['outstanding_collection'] <=> $a['outstanding_collection']);
        $data['top_pending_projects'] = array_slice($topPending, 0, 5);

        // Release 4.5.6 (Phase 4): Outstanding Collection KPI stays the raw
        // invoice-only figure (sales.balance_amount) queried above — no
        // longer netted against Project Cash Receipts. Advance Credit
        // sublabel retired along with the netting it depended on.
        $data['total_advance_credit'] = 0.0;

        $data += $this->_serviceSummary($db);
        $data += $this->_loanSummary($db);
        $data += $this->_expenseSummary($db);
        $data += $this->_bankSummary($db);

        return view('dashboard/index', $data);
    }

    /**
     * Release 4.8.4E: Service Received KPIs + latest-10 widgets, read-only.
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
            'service_recent_receipts'   => [],
            'service_recent_payments'   => [],
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
                'service_recent_receipts'  => $db->query("
                    SELECT id, receipt_no, receipt_date, customer_name, receipt_type, grand_total, payment_status
                    FROM service_receipts
                    ORDER BY receipt_date DESC, id DESC LIMIT 10
                ")->getResultArray(),
                'service_recent_payments'  => $db->query("
                    SELECT cp.id, cp.payment_no, cp.payment_date, cp.payment_method, cp.total_amount, c.name AS customer_name
                    FROM customer_payments cp
                    LEFT JOIN customers c ON c.id = cp.customer_id
                    ORDER BY cp.payment_date DESC, cp.id DESC LIMIT 10
                ")->getResultArray(),
            ];
        } catch (\Throwable $e) {
            log_message('error', 'Dashboard service summary failed: ' . $e->getMessage());
            return $empty;
        }
    }

    /**
     * Release 4.8.5D: Loan KPIs + latest-10 widgets, read-only. Guarded by
     * tableExists() so a database that has not run the 4.8.5A migrations yet
     * still renders every existing dashboard card (the section is just hidden).
     *
     * The figures are the unfiltered versions of the Loan Reports:
     * - Outstanding Loans: SUM(loans.outstanding_principal), as Loan Register /
     *   Outstanding / Lender Summary.
     * - EMI Due This Month: SUM(loan_emis.balance_amount) of unpaid (PENDING /
     *   PARTIAL) EMIs of ACTIVE loans due this calendar month, as the EMI Due
     *   Report's "EMI Due This Month".
     * - Total Interest Paid: SUM(loans.total_interest_paid), as Lender Summary.
     * - Closed Loans: loans with status CLOSED.
     * Upcoming EMIs lists unpaid EMIs of ACTIVE loans, earliest due date first,
     * so anything already overdue leads the list.
     *
     * Release 4.8.5E only adds read-only columns and counts for the widget UI
     * (no figure above changes): loan_active_count / loan_total_count / loan_emi_due_count
     * feed the KPI subtitles (the count uses the same WHERE as EMI Due This Month), and the two
     * lists also carry payment_method / emi_no / lender_name for their badges and links.
     */
    private function _loanSummary($db): array
    {
        $empty = [
            'loan_available'       => false,
            'loan_outstanding'     => 0.0,
            'loan_emi_due_month'   => 0.0,
            'loan_interest_paid'   => 0.0,
            'loan_closed_count'    => 0,
            'loan_active_count'    => 0,
            'loan_total_count'     => 0,
            'loan_emi_due_count'   => 0,
            'loan_recent_payments' => [],
            'loan_upcoming_emis'   => [],
        ];

        foreach (['loans', 'loan_emis', 'loan_payments'] as $table) {
            if (! $db->tableExists($table)) {
                return $empty;
            }
        }

        try {
            $totals = $db->query("
                SELECT COALESCE(SUM(outstanding_principal), 0) AS outstanding,
                       COALESCE(SUM(total_interest_paid), 0)   AS interest_paid,
                       COALESCE(SUM(status = 'CLOSED'), 0)     AS closed_count,
                       COALESCE(SUM(status = 'ACTIVE'), 0)     AS active_count,
                       COUNT(*)                                AS total_count
                FROM loans
            ")->getRow();

            $due = $db->query("
                SELECT COALESCE(SUM(le.balance_amount), 0) AS t, COUNT(le.id) AS c
                FROM loan_emis le
                JOIN loans l ON l.id = le.loan_id
                WHERE l.status = 'ACTIVE'
                  AND le.payment_status IN ('PENDING', 'PARTIAL')
                  AND le.due_date >= ? AND le.due_date <= ?
            ", [date('Y-m-01'), date('Y-m-t')])->getRow();

            return [
                'loan_available'       => true,
                'loan_outstanding'     => round((float) $totals->outstanding, 2),
                'loan_emi_due_month'   => round((float) $due->t, 2),
                'loan_interest_paid'   => round((float) $totals->interest_paid, 2),
                'loan_closed_count'    => (int) $totals->closed_count,
                'loan_active_count'    => (int) $totals->active_count,
                'loan_total_count'     => (int) $totals->total_count,
                'loan_emi_due_count'   => (int) $due->c,
                'loan_recent_payments' => $db->query("
                    SELECT lp.id, lp.payment_date, lp.payment_method, lp.total_paid, l.id AS loan_id, l.loan_no, l.lender_name
                    FROM loan_payments lp
                    JOIN loans l ON l.id = lp.loan_id
                    ORDER BY lp.payment_date DESC, lp.id DESC LIMIT 10
                ")->getResultArray(),
                'loan_upcoming_emis'   => $db->query("
                    SELECT le.id, le.emi_no, le.due_date, le.emi_amount, le.balance_amount, l.id AS loan_id, l.loan_no, l.lender_name
                    FROM loan_emis le
                    JOIN loans l ON l.id = le.loan_id
                    WHERE l.status = 'ACTIVE' AND le.payment_status IN ('PENDING', 'PARTIAL')
                    ORDER BY le.due_date ASC, l.id ASC, le.emi_no ASC LIMIT 10
                ")->getResultArray(),
            ];
        } catch (\Throwable $e) {
            log_message('error', 'Dashboard loan summary failed: ' . $e->getMessage());
            return $empty;
        }
    }

    /**
     * Release 4.8.6D: Expense KPIs + latest-10 widget, read-only. Guarded by
     * tableExists() so a database that has not run the 4.8.6A migrations yet
     * still renders every existing dashboard card (the section is just
     * hidden). Figures are the same unfiltered PAID-only definitions
     * ExpenseReports.php uses (Expense Register's Today/This Month/Total
     * Expense KPIs, and Category Summary's category count).
     */
    private function _expenseSummary($db): array
    {
        $empty = [
            'expense_available'      => false,
            'expense_today'          => 0.0,
            'expense_month'          => 0.0,
            'expense_total'          => 0.0,
            'expense_categories_used' => 0,
            'expense_recent'         => [],
        ];

        foreach (['expenses', 'expense_categories'] as $table) {
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

            $totals = $db->query("
                SELECT COALESCE(SUM(amount), 0) AS total, COUNT(DISTINCT category_id) AS categories_used
                FROM expenses WHERE status = 'PAID'
            ")->getRow();

            return [
                'expense_available'       => true,
                'expense_today'           => round((float) $today, 2),
                'expense_month'           => round((float) $month, 2),
                'expense_total'           => round((float) $totals->total, 2),
                'expense_categories_used' => (int) $totals->categories_used,
                'expense_recent'          => $db->query("
                    SELECT e.id, e.expense_no, e.paid_to, e.amount, e.expense_date, e.payment_method, c.category_name
                    FROM expenses e
                    LEFT JOIN expense_categories c ON c.id = e.category_id
                    ORDER BY e.expense_date DESC, e.id DESC LIMIT 10
                ")->getResultArray(),
            ];
        } catch (\Throwable $e) {
            log_message('error', 'Dashboard expense summary failed: ' . $e->getMessage());
            return $empty;
        }
    }

    /**
     * Release 4.8.4I: Bank summary KPIs + latest-10 widgets + overdrawn-account
     * warning, read-only. Guarded by tableExists() so a database without the
     * bank tables still renders every existing dashboard card (the section is
     * just hidden).
     *
     * - Total Bank Balance / Bank Accounts: SUM(current_balance) and the row
     *   count of bank_accounts, the same figures as the Bank Accounts page.
     * - Deposits / Withdrawals This Month: SUM(amount) of DEPOSIT / WITHDRAWAL
     *   rows dated this calendar month, the same definition as that page's
     *   all-time totals — transfers between accounts are internal movements and
     *   count in neither.
     * - Latest 10 Transactions: the newest rows of every kind (automatic
     *   postings included), labelled by BankTransactionModel::transactionLabel().
     * - Latest 10 Transfers: one line per transfer (its TRANSFER_OUT row joined
     *   to its TRANSFER_IN row), plus any legacy single-row TRANSFER.
     * - Low balance: accounts whose current_balance is below zero.
     */
    private function _bankSummary($db): array
    {
        $empty = [
            'bank_available'         => false,
            'bank_total_balance'     => 0.0,
            'bank_month_deposits'    => 0.0,
            'bank_month_withdrawals' => 0.0,
            'bank_account_count'     => 0,
            'bank_active_count'      => 0,
            'bank_recent'            => [],
            'bank_transfers'         => [],
            'bank_low_balance'       => [],
        ];

        foreach (['bank_accounts', 'bank_transactions'] as $table) {
            if (! $db->tableExists($table)) {
                return $empty;
            }
        }

        try {
            $accounts = $db->query("
                SELECT COALESCE(SUM(current_balance), 0) AS balance,
                       COUNT(*)                          AS total,
                       COALESCE(SUM(is_active = 1), 0)   AS active
                FROM bank_accounts
            ")->getRow();

            $month = $db->query("
                SELECT COALESCE(SUM(CASE WHEN transaction_type = 'DEPOSIT'    THEN amount END), 0) AS deposits,
                       COALESCE(SUM(CASE WHEN transaction_type = 'WITHDRAWAL' THEN amount END), 0) AS withdrawals
                FROM bank_transactions
                WHERE transaction_date >= ? AND transaction_date <= ?
            ", [date('Y-m-01'), date('Y-m-t')])->getRow();

            $recent = $db->query("
                SELECT bt.id, bt.transaction_date, bt.transaction_type, bt.reference_type, bt.reference_id, bt.reference_no,
                       bt.amount, bt.bank_account_id, bt.transfer_bank_account_id, ba.bank_name, ba.account_number
                FROM bank_transactions bt
                JOIN bank_accounts ba ON ba.id = bt.bank_account_id
                ORDER BY bt.transaction_date DESC, bt.id DESC LIMIT 10
            ")->getResultArray();

            foreach ($recent as &$row) {
                $row['label']     = \App\Models\BankTransactionModel::transactionLabel($row);
                $row['is_credit'] = \App\Models\BankTransactionModel::isCredit($row['transaction_type']);
            }
            unset($row);

            // payment_mode only exists once the 4.8.4I migration has run.
            $modeColumn = $db->fieldExists('payment_mode', 'bank_transactions') ? 'o.payment_mode' : 'NULL';

            $transfers = $db->query("
                (SELECT o.reference_id AS transfer_id, o.transaction_date, o.amount, {$modeColumn} AS method, o.id AS sort_id,
                        fa.bank_name AS from_bank, fa.account_number AS from_number, ta.bank_name AS to_bank, ta.account_number AS to_number
                 FROM bank_transactions o
                 JOIN bank_transactions i ON i.reference_type = 'BANK_TRANSFER' AND i.reference_id = o.reference_id AND i.transaction_type = 'TRANSFER_IN'
                 JOIN bank_accounts fa ON fa.id = o.bank_account_id
                 JOIN bank_accounts ta ON ta.id = i.bank_account_id
                 WHERE o.reference_type = 'BANK_TRANSFER' AND o.transaction_type = 'TRANSFER_OUT')
                UNION ALL
                (SELECT NULL, o.transaction_date, o.amount, NULL, o.id,
                        fa.bank_name, fa.account_number, ta.bank_name, ta.account_number
                 FROM bank_transactions o
                 JOIN bank_accounts fa ON fa.id = o.bank_account_id
                 JOIN bank_accounts ta ON ta.id = o.transfer_bank_account_id
                 WHERE o.transaction_type = 'TRANSFER')
                ORDER BY transaction_date DESC, sort_id DESC LIMIT 10
            ")->getResultArray();

            return [
                'bank_available'         => true,
                'bank_total_balance'     => round((float) $accounts->balance, 2),
                'bank_month_deposits'    => round((float) $month->deposits, 2),
                'bank_month_withdrawals' => round((float) $month->withdrawals, 2),
                'bank_account_count'     => (int) $accounts->total,
                'bank_active_count'      => (int) $accounts->active,
                'bank_recent'            => $recent,
                'bank_transfers'         => $transfers,
                'bank_low_balance'       => $db->query("
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
