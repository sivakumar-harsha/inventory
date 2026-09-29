<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Release 4.8.6A: Expense Management Foundation. Field-shape validation
 * only — bank account/project/category cross-checks and the cancelled-
 * expense edit/delete lock live in the Expenses controller, matching how
 * LoanModel is split from the Loans controller.
 */
class ExpenseModel extends Model
{
    protected $table      = 'expenses';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'expense_no',
        'expense_date',
        'category_id',
        'project_id',
        'paid_to',
        'payment_method',
        'bank_account_id',
        'amount',
        'remarks',
        'status',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'expense_date'   => 'required|valid_date',
        'category_id'    => 'required|is_natural_no_zero',
        'paid_to'        => 'required|max_length[150]',
        'payment_method' => 'required|in_list[CASH,BANK,CHEQUE,UPI,OTHER]',
        'amount'         => 'required|numeric|greater_than[0]',
        'status'         => 'permit_empty|in_list[PAID,CANCELLED]',
    ];

    /**
     * Next expense number in EXP-000001 style. Reads only this table's own
     * id sequence; same accepted trade-off as LoanModel::nextLoanNo() — the
     * unique key on expense_no turns a race into a failed insert, never a
     * duplicate number.
     */
    public function nextExpenseNo(): string
    {
        $last = $this->select('id')->orderBy('id', 'DESC')->first();
        $next = $last ? ((int) $last['id'] + 1) : 1;
        return 'EXP-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Count and PAID total for today. Cancelled expenses never count toward
     * a total.
     */
    public function todayExpenses(): array
    {
        $row = $this->db->query("
            SELECT COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total
            FROM expenses
            WHERE status = 'PAID' AND expense_date = CURDATE()
        ")->getRowArray();

        return ['count' => (int) $row['count'], 'total' => round((float) $row['total'], 2)];
    }

    /**
     * Count and PAID total for the current calendar month.
     */
    public function monthlyExpenses(): array
    {
        $row = $this->db->query("
            SELECT COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total
            FROM expenses
            WHERE status = 'PAID'
              AND YEAR(expense_date) = YEAR(CURDATE())
              AND MONTH(expense_date) = MONTH(CURDATE())
        ")->getRowArray();

        return ['count' => (int) $row['count'], 'total' => round((float) $row['total'], 2)];
    }

    /**
     * PAID totals grouped by category, highest total first.
     */
    public function categoryTotals(): array
    {
        return $this->db->query("
            SELECT e.category_id, c.category_name,
                   COUNT(*) AS count,
                   COALESCE(SUM(e.amount), 0) AS total
            FROM expenses e
            LEFT JOIN expense_categories c ON c.id = e.category_id
            WHERE e.status = 'PAID'
            GROUP BY e.category_id, c.category_name
            ORDER BY total DESC
        ")->getResultArray();
    }

    /**
     * PAID totals grouped by project (a NULL project_id groups as
     * unassigned), highest total first.
     */
    public function projectTotals(): array
    {
        return $this->db->query("
            SELECT e.project_id, p.name AS project_name,
                   COUNT(*) AS count,
                   COALESCE(SUM(e.amount), 0) AS total
            FROM expenses e
            LEFT JOIN projects p ON p.id = e.project_id
            WHERE e.status = 'PAID'
            GROUP BY e.project_id, p.name
            ORDER BY total DESC
        ")->getResultArray();
    }
}
