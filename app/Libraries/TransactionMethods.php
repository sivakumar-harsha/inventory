<?php

namespace App\Libraries;

/**
 * Release 4.9.0CB: the stored payment method of the record behind a bank row, for the
 * Bank Ledger and the bank transaction lists. Strictly read-only and display-only.
 *
 * bank_transactions only says WHERE the money moved (the account). HOW it was paid
 * (UPI, Cheque, Bank Transfer ...) lives on the owning record, found through
 * reference_type / reference_id, so it is read from there, never inferred from the
 * fact that the row is in a bank ledger. A row whose owner has no method column
 * (account-to-account transfers) or whose method is blank returns '' - the caller
 * shows "Not recorded" / a dash, it never guesses Cash or Bank.
 */
class TransactionMethods
{
    /** reference_type => [owning table, its id column, its method column]. */
    private const OWNERS = [
        'CUSTOMER_PAYMENT'        => ['customer_payments', 'id', 'payment_method'],
        'SERVICE_RECEIPT_PAYMENT' => ['customer_payments', 'id', 'payment_method'],
        'SERVICE_RECEIPT'         => ['service_receipts', 'id', 'payment_mode'],
        'SALE_PAYMENT'            => ['payments', 'id', 'method'],
        'SUPPLIER_PAYMENT'        => ['supplier_payments', 'id', 'payment_method'],
        'SUPPLIER_ADVANCE'        => ['general_purchases', 'id', 'payment_method'],
        'EXPENSE'                 => ['expenses', 'id', 'payment_method'],
        'PROJECT_ADVANCE'         => ['project_cash_receipts', 'id', 'payment_method'],
        'PROJECT_ADVANCE_DEPOSIT' => ['projects', 'id', 'advance_payment_method'],
        'LOAN_DISBURSEMENT'       => ['loan_receipts', 'id', 'payment_method'],
        'LOAN_PAYMENT'            => ['loan_payments', 'id', 'payment_method'],
    ];

    /**
     * @param int[] $bankTransactionIds bank_transactions.id values
     *
     * @return array<int,string> bank_transactions.id => stored method ('' when none is recorded)
     */
    public static function forBankTransactions(array $bankTransactionIds): array
    {
        $bankTransactionIds = array_values(array_unique(array_filter(array_map('intval', $bankTransactionIds))));
        if (! $bankTransactionIds) {
            return [];
        }

        $db      = \Config\Database::connect();
        $rows    = $db->table('bank_transactions')->select('id, reference_type, reference_id, payment_mode')->whereIn('id', $bankTransactionIds)->get()->getResultArray();
        $byOwner = [];
        foreach ($rows as $r) {
            if (isset(self::OWNERS[$r['reference_type']]) && (int) $r['reference_id'] > 0) {
                $byOwner[$r['reference_type']][] = (int) $r['reference_id'];
            }
        }

        $owned = [];
        foreach ($byOwner as $refType => $ids) {
            [$table, $idCol, $methodCol] = self::OWNERS[$refType];
            if (! $db->tableExists($table)) {
                continue;
            }
            foreach ($db->table($table)->select("$idCol AS rid, $methodCol AS method")->whereIn($idCol, array_values(array_unique($ids)))->get()->getResultArray() as $o) {
                $owned[$refType . ':' . $o['rid']] = (string) ($o['method'] ?? '');
            }
        }

        $out = [];
        foreach ($rows as $r) {
            $method = $owned[$r['reference_type'] . ':' . $r['reference_id']] ?? '';
            // Manual vouchers have no owning record; they carry their own payment_mode (blank for most Day-book rows).
            if ($method === '' && ! isset(self::OWNERS[$r['reference_type']])) {
                $method = (string) ($r['payment_mode'] ?? '');
            }
            $out[(int) $r['id']] = $method;
        }

        return $out;
    }

    /**
     * bank_transactions.id => true for the rows that post an "Unallocated Project Receipt"
     * (a project_cash_receipts row of type CUSTOMER_PROJECT_CASH, reference_type PROJECT_ADVANCE).
     * Used only to word the display text; their stored remarks still read "Project Advance - ...".
     *
     * @param int[] $bankTransactionIds
     *
     * @return array<int,bool>
     */
    public static function unallocatedProjectReceipts(array $bankTransactionIds): array
    {
        $bankTransactionIds = array_values(array_unique(array_filter(array_map('intval', $bankTransactionIds))));
        if (! $bankTransactionIds) {
            return [];
        }

        $out  = [];
        $rows = \Config\Database::connect()->query(
            "SELECT bt.id FROM bank_transactions bt
             INNER JOIN project_cash_receipts r ON r.id = bt.reference_id
             WHERE bt.reference_type = 'PROJECT_ADVANCE' AND r.receipt_type = 'CUSTOMER_PROJECT_CASH' AND bt.id IN ?",
            [$bankTransactionIds]
        )->getResultArray();
        foreach ($rows as $r) {
            $out[(int) $r['id']] = true;
        }

        return $out;
    }

    /** Display wording for the stored bank remark of an Unallocated Project Receipt ("Project Advance - ..." on rows posted before 4.9.0CB). */
    public static function receiptRemark(string $remarks): string
    {
        return str_starts_with($remarks, 'Project Advance - ') ? 'Unallocated Project Receipt — ' . substr($remarks, strlen('Project Advance - ')) : $remarks;
    }
}
