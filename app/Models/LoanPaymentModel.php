<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Release 4.8.5A: payment history of a loan. Shape validation and the history
 * reads. Release 4.8.5C: the Loans controller now writes here (payments screen)
 * and posts each bank-paid row as a WITHDRAWAL, reference_type LOAN_PAYMENT.
 */
class LoanPaymentModel extends Model
{
    protected $table      = 'loan_payments';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'loan_id',
        'loan_emi_id',
        'payment_date',
        'payment_method',
        'bank_account_id',
        'principal_paid',
        'interest_paid',
        'total_paid',
        'reference_no',
        'remarks',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'loan_id'        => 'required|integer',
        'payment_date'   => 'required|valid_date',
        'payment_method' => 'required|in_list[CASH,BANK,CHEQUE,UPI,OTHER]',
        'principal_paid' => 'permit_empty|numeric|greater_than_equal_to[0]',
        'interest_paid'  => 'permit_empty|numeric|greater_than_equal_to[0]',
        'total_paid'     => 'required|numeric|greater_than[0]',
    ];

    /**
     * Every payment of one loan with the EMI number it settled (if any) and the
     * bank account it left from (if any). Newest first for the payment history;
     * $oldestFirst = true gives the order the ledger runs in.
     */
    public function forLoan(int $loanId, bool $oldestFirst = false): array
    {
        $dir = $oldestFirst ? 'ASC' : 'DESC';

        return $this->db->query("
            SELECT lp.*, le.emi_no, ba.bank_name, ba.account_name
            FROM loan_payments lp
            LEFT JOIN loan_emis le ON le.id = lp.loan_emi_id
            LEFT JOIN bank_accounts ba ON ba.id = lp.bank_account_id
            WHERE lp.loan_id = ?
            ORDER BY lp.payment_date {$dir}, lp.id {$dir}
        ", [$loanId])->getResultArray();
    }

    /** One payment, row-locked when $lock (only meaningful inside the caller's transaction). */
    public function lockedRow(int $id, bool $lock = true): ?array
    {
        $row = $this->db->query('SELECT * FROM loan_payments WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id])->getRowArray();

        return $row ?: null;
    }
}
