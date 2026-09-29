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
        'loan_type'             => 'required|in_list[BANK,PERSONAL,VEHICLE,OD,OTHER]',
        'sanctioned_amount'     => 'required|numeric|greater_than[0]',
        'interest_rate'         => 'required|numeric|greater_than_equal_to[0]',
        'tenure_months'         => 'required|integer|greater_than[0]',
        'emi_amount'            => 'required|numeric|greater_than[0]',
        'start_date'            => 'required|valid_date',
        'end_date'              => 'permit_empty|valid_date',
        'outstanding_principal' => 'permit_empty|numeric|greater_than_equal_to[0]',
        'total_principal_paid'  => 'permit_empty|numeric|greater_than_equal_to[0]',
        'total_interest_paid'   => 'permit_empty|numeric|greater_than_equal_to[0]',
        'status'                => 'permit_empty|in_list[ACTIVE,CLOSED]',
    ];

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
     * Money snapshot of one loan for the loan engine: what was sanctioned, what
     * principal is still owed, what has been paid, and the next unpaid EMI.
     * Returns null when the loan is missing.
     *
     * $lock = true adds FOR UPDATE, so two payments against the same loan at
     * once serialise instead of both seeing the same balance. Only meaningful
     * (and only used) inside the caller's DB transaction.
     */
    public function outstanding(int $id, bool $lock = false): ?array
    {
        $sql = 'SELECT id, loan_no, lender_name, status, sanctioned_amount, emi_amount, outstanding_principal,
                       total_principal_paid, total_interest_paid
                FROM loans WHERE id = ?' . ($lock ? ' FOR UPDATE' : '');
        $row = $this->db->query($sql, [$id])->getRowArray();

        if (! $row) {
            return null;
        }

        $next = $this->db->query(
            "SELECT id, emi_no, due_date, balance_amount FROM loan_emis
             WHERE loan_id = ? AND payment_status <> 'PAID'
             ORDER BY emi_no ASC LIMIT 1",
            [$id]
        )->getRowArray();

        return [
            'loan_id'               => (int) $row['id'],
            'loan_no'               => $row['loan_no'],
            'lender_name'           => $row['lender_name'],
            'status'                => $row['status'],
            'sanctioned_amount'     => round((float) $row['sanctioned_amount'], 2),
            'emi_amount'            => round((float) $row['emi_amount'], 2),
            'outstanding_principal' => round((float) $row['outstanding_principal'], 2),
            'principal_paid'        => round((float) $row['total_principal_paid'], 2),
            'interest_paid'         => round((float) $row['total_interest_paid'], 2),
            'total_paid'            => round((float) $row['total_principal_paid'] + (float) $row['total_interest_paid'], 2),
            'next_emi'              => $next ? [
                'emi_id'         => (int) $next['id'],
                'emi_no'         => (int) $next['emi_no'],
                'due_date'       => $next['due_date'],
                'balance_amount' => round((float) $next['balance_amount'], 2),
            ] : null,
        ];
    }
}
