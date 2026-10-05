<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Release 4.9.0AT: actual money received against a sanctioned loan. Shape
 * validation and the history reads only — the Loans controller writes here
 * and posts each bank-received row as a DEPOSIT, reference_type
 * LOAN_DISBURSEMENT, matching how LoanPaymentModel/Loans::storePayment() work
 * for the opposite (outgoing) side of a loan.
 */
class LoanReceiptModel extends Model
{
    protected $table      = 'loan_receipts';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'loan_id',
        'receipt_no',
        'receipt_date',
        'amount',
        'payment_method',
        'bank_account_id',
        'reference_no',
        'remarks',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'loan_id'        => 'required|integer',
        'receipt_no'     => 'required|max_length[20]|is_unique[loan_receipts.receipt_no,id,{id}]',
        'receipt_date'   => 'required|valid_date',
        'amount'         => 'required|numeric|greater_than[0]',
        'payment_method' => 'required|in_list[CASH,BANK,CHEQUE,UPI,OTHER]',
    ];

    /**
     * Next receipt number in LR-000001 style. Same accepted trade-off as
     * LoanModel::nextLoanNo() — the unique key on receipt_no turns a race into
     * a failed insert, never a duplicate number.
     */
    public function nextReceiptNo(): string
    {
        $last = $this->select('id')->orderBy('id', 'DESC')->first();
        $next = $last ? ((int) $last['id'] + 1) : 1;
        return 'LR-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Every receipt of one loan with the bank account it went into (if any).
     * Oldest first, matching the order the Loan Detail / Ledger screens show.
     */
    public function forLoan(int $loanId): array
    {
        return $this->db->query("
            SELECT lr.*, ba.bank_name, ba.account_name
            FROM loan_receipts lr
            LEFT JOIN bank_accounts ba ON ba.id = lr.bank_account_id
            WHERE lr.loan_id = ?
            ORDER BY lr.receipt_date ASC, lr.id ASC
        ", [$loanId])->getResultArray();
    }

    /** Total already received against one loan (0.00 when none). */
    public function totalReceived(int $loanId): float
    {
        $row = $this->db->query('SELECT COALESCE(SUM(amount), 0) AS total FROM loan_receipts WHERE loan_id = ?', [$loanId])->getRowArray();

        return round((float) $row['total'], 2);
    }

    /** One receipt, row-locked when $lock (only meaningful inside the caller's transaction). */
    public function lockedRow(int $id, bool $lock = true): ?array
    {
        $row = $this->db->query('SELECT * FROM loan_receipts WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id])->getRowArray();

        return $row ?: null;
    }
}
