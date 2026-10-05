<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Release 4.8.5A: loan header. Field-shape validation only; the EMI schedule,
 * the paid/outstanding maintenance and the business rules live in the Loans
 * controller, matching how CustomerPaymentModel is split from CustomerPayments.
 */
class LoanModel extends Model
{
    protected $table      = 'loans';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'loan_no',
        'lender_name',
        'loan_type',
        'bank_account_id',
        'account_number',
        'sanctioned_amount',
        'interest_rate',
        'tenure_months',
        'emi_amount',
        'start_date',
        'end_date',
        'outstanding_principal',
        'total_principal_paid',
        'total_interest_paid',
        'status',
        'remarks',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'loan_no'               => 'required|max_length[20]|is_unique[loans.loan_no,id,{id}]',
        'lender_name'           => 'required|max_length[150]',
        'loan_type'             => 'required|max_length[30]',
        'sanctioned_amount'     => 'required|numeric|greater_than[0]',
        'interest_rate'         => 'permit_empty|numeric|greater_than_equal_to[0]',
        'tenure_months'         => 'permit_empty|integer|greater_than_equal_to[0]',
        'emi_amount'            => 'permit_empty|numeric|greater_than_equal_to[0]',
        'start_date'            => 'required|valid_date',
        'end_date'              => 'permit_empty|valid_date',
        'outstanding_principal' => 'permit_empty|numeric|greater_than_equal_to[0]',
        'total_principal_paid'  => 'permit_empty|numeric|greater_than_equal_to[0]',
        'total_interest_paid'   => 'permit_empty|numeric|greater_than_equal_to[0]',
        'status'                => 'permit_empty|in_list[ACTIVE,CLOSED]',
    ];

    public function __construct(?\CodeIgniter\Database\ConnectionInterface &$db = null, ?\CodeIgniter\Validation\ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);

        // Loan Type comes from the loan_types master (not a fixed list).
        $this->validationRules['loan_type'] = 'required|max_length[30]|in_list[' . implode(',', LoanTypeModel::codes()) . ']';
    }

    /**
     * Next loan number in LN-000001 style. Reads only this table's own id
     * sequence; same accepted trade-off as CustomerPaymentModel::nextPaymentNo()
     * — the unique key on loan_no turns a race into a failed insert, never a
     * duplicate number.
     */
    public function nextLoanNo(): string
    {
        $last = $this->select('id')->orderBy('id', 'DESC')->first();
        $next = $last ? ((int) $last['id'] + 1) : 1;
        return 'LN-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * SQL for "total paid so far" of the loans row aliased $alias:
     * SUM(loan_payments.total_paid). Release 4.9.0BA: the manual Payment Amount
     * is the only figure that reduces a loan, so every screen derives Total Paid
     * and Outstanding from loan_payments instead of the legacy header columns.
     */
    public static function paidSql(string $alias = 'l'): string
    {
        return "(SELECT COALESCE(SUM(lpx.total_paid), 0) FROM loan_payments lpx WHERE lpx.loan_id = {$alias}.id)";
    }

    /**
     * find() with total_paid and outstanding_principal overridden by the figures
     * derived from loan_payments (see paidSql()). Null when the loan is missing.
     */
    public function findWithTotals(int $id): ?array
    {
        $loan = $this->find($id);

        if (! $loan) {
            return null;
        }

        $info                          = $this->outstanding($id);
        $loan['total_paid']            = $info['total_paid'];
        $loan['outstanding_principal'] = $info['outstanding_principal'];

        return $loan;
    }

    /** SQL for Outstanding = Sanctioned Amount - Total Paid (never below zero). */
    public static function outstandingSql(string $alias = 'l'): string
    {
        return 'GREATEST(0, ROUND(' . $alias . '.sanctioned_amount - ' . self::paidSql($alias) . ', 2))';
    }

    /**
     * Money snapshot of one loan for the loan engine. Release 4.9.0BA: total_paid
     * = SUM(loan_payments.total_paid) and outstanding_principal (key kept for
     * callers) = sanctioned amount - total_paid, never below zero. Returns null
     * when the loan is missing. $excludePaymentId leaves one payment out of the
     * sum (the payment being edited).
     *
     * $lock = true adds FOR UPDATE, so two payments against the same loan at
     * once serialise instead of both seeing the same balance. Only meaningful
     * (and only used) inside the caller's DB transaction.
     */
    public function outstanding(int $id, bool $lock = false, ?int $excludePaymentId = null): ?array
    {
        $sql = 'SELECT id, loan_no, lender_name, status, sanctioned_amount FROM loans WHERE id = ?' . ($lock ? ' FOR UPDATE' : '');
        $row = $this->db->query($sql, [$id])->getRowArray();

        if (! $row) {
            return null;
        }

        $paid = (float) $this->db->query(
            'SELECT COALESCE(SUM(total_paid), 0) AS p FROM loan_payments WHERE loan_id = ? AND id <> ?',
            [$id, (int) $excludePaymentId]
        )->getRow()->p;

        $sanctioned = round((float) $row['sanctioned_amount'], 2);
        $paid       = round($paid, 2);

        return [
            'loan_id'               => (int) $row['id'],
            'loan_no'               => $row['loan_no'],
            'lender_name'           => $row['lender_name'],
            'status'                => $row['status'],
            'sanctioned_amount'     => $sanctioned,
            'outstanding_principal' => max(0.0, round($sanctioned - $paid, 2)),
            'total_paid'            => $paid,
        ];
    }
}
