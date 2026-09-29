<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Release 4.8.3A (Phase 1): one row per manual Deposit/Withdrawal/Transfer.
 * BankAccounts controller computes and applies every manual balance change
 * via its own _updateBankBalance()/_getRunningBalance() (frozen).
 *
 * Release 4.8.3C adds the reference-posting helpers at the bottom of this
 * class, used by ProjectCashReceipts and SupplierPayments to post automatic
 * DEPOSIT/WITHDRAWAL rows keyed by (reference_type, reference_id). They never
 * open their own DB transaction — the calling controller owns it, so a
 * rollback there restores balances too.
 *
 * Release 4.8.4I adds the manual ledger engine at the bottom of this class
 * (Deposit / Withdrawal / Daybook / two-row Transfer, the statement reader and
 * the transaction labels). createBankTransaction() is extended additively — new
 * transaction types, an optional NULL reference_id, optional descriptive
 * columns — so every existing automatic-posting caller behaves exactly as
 * before.
 */
class BankTransactionModel extends Model
{
    /** Types createBankTransaction() will post. TRANSFER (single row) is legacy and never posted here. */
    public const POSTING_TYPES  = ['DEPOSIT', 'WITHDRAWAL', 'TRANSFER_OUT', 'TRANSFER_IN'];
    public const TRANSFER_TYPES = ['TRANSFER_OUT', 'TRANSFER_IN'];

    // Reference types owned by the manual bank ledger (Release 4.8.4I).
    public const REF_MANUAL_DEPOSIT    = 'MANUAL_DEPOSIT';
    public const REF_MANUAL_WITHDRAWAL = 'MANUAL_WITHDRAWAL';
    public const REF_BANK_TRANSFER     = 'BANK_TRANSFER';
    public const REF_BANK_DAYBOOK      = 'BANK_DAYBOOK';

    protected $table      = 'bank_transactions';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'bank_account_id',
        'transaction_date',
        'transaction_type',
        'amount',
        'reference_type',
        'reference_id',
        'reference_no',
        'remarks',
        'transfer_bank_account_id',
        'created_by',
        // Release 4.8.4I (migration 2026-09-24-000001); written only by the manual ledger screens.
        'payment_mode',
        'party_name',
        'category',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'bank_account_id'   => 'required|integer',
        'transaction_date'  => 'required|valid_date',
        'transaction_type'  => 'required|in_list[DEPOSIT,WITHDRAWAL,TRANSFER,TRANSFER_OUT,TRANSFER_IN]',
        'amount'            => 'required|numeric|greater_than[0]',
    ];

    /**
     * Every row touching one account, either as the primary side
     * (bank_account_id) or as the receiving side of a TRANSFER
     * (transfer_bank_account_id) — used by _getRunningBalance() to build a
     * single account's full history.
     */
    public function forAccount(int $bankAccountId): array
    {
        return $this->groupStart()
                ->where('bank_account_id', $bankAccountId)
                ->orWhere('transfer_bank_account_id', $bankAccountId)
            ->groupEnd()
            ->orderBy('transaction_date', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    // =========================================================
    // RELEASE 4.8.3C — AUTOMATIC POSTING HELPERS
    // =========================================================

    /**
     * True when money moves through a bank for this payment method. Covers
     * project receipt values (BANK_TRANSFER, CHECK) and supplier payment
     * labels (Bank Transfer, Cheque, UPI); Cash/Other/empty never qualify.
     */
    public static function isBankMethod(?string $method): bool
    {
        $key = strtoupper((string) preg_replace('/[\s\-]+/', '_', trim((string) $method)));

        return in_array($key, ['BANK', 'BANK_TRANSFER', 'CHEQUE', 'CHECK', 'UPI'], true);
    }

    /**
     * Inserts one DEPOSIT/WITHDRAWAL row (or, from 4.8.4I, one leg of a
     * transfer: TRANSFER_OUT/TRANSFER_IN) and applies it to the account's
     * current_balance. Refuses a second row for the same (reference_type,
     * reference_id) and any inactive/unknown account. Must run inside the
     * caller's DB transaction. Returns the new row's id.
     *
     * Release 4.8.4I: a manual entry has no owning record, so it passes a NULL
     * reference_id and there is nothing to de-duplicate. The two legs of one
     * transfer share a reference_id and are told apart by transaction_type.
     * payment_mode / party_name / category are written only when the caller
     * supplies them.
     */
    public function createBankTransaction(array $data): int
    {
        $type   = (string) $data['transaction_type'];
        $amount = round((float) $data['amount'], 2);

        if (! in_array($type, self::POSTING_TYPES, true)) {
            throw new \RuntimeException('Bank posting supports only DEPOSIT, WITHDRAWAL, TRANSFER_OUT and TRANSFER_IN.');
        }
        if ($amount <= 0) {
            throw new \RuntimeException('Bank transaction amount must be greater than zero.');
        }

        $account = $this->db->table('bank_accounts')->where('id', (int) $data['bank_account_id'])->get()->getRowArray();
        if (! $account) {
            throw new \RuntimeException('The selected bank account was not found.');
        }
        if ((int) $account['is_active'] !== 1) {
            throw new \RuntimeException('Transactions cannot be posted against an inactive bank account.');
        }

        $referenceId = ($data['reference_id'] ?? null) === null ? null : (int) $data['reference_id'];

        if ($referenceId !== null) {
            $this->where('reference_type', $data['reference_type'])->where('reference_id', $referenceId);
            if (in_array($type, self::TRANSFER_TYPES, true)) {
                $this->where('transaction_type', $type);
            }

            if ($this->countAllResults() > 0) {
                throw new \RuntimeException("A bank transaction already exists for {$data['reference_type']} #{$referenceId}.");
            }
        }

        $row = [
            'bank_account_id'  => (int) $data['bank_account_id'],
            'transaction_date' => $data['transaction_date'],
            'transaction_type' => $type,
            'amount'           => $amount,
            'reference_type'   => $data['reference_type'],
            'reference_id'     => $referenceId,
            'reference_no'     => $data['reference_no'] ?? null,
            'remarks'          => $data['remarks'] ?? null,
            'created_by'       => $data['created_by'] ?? null,
        ];
        foreach (['payment_mode', 'party_name', 'category'] as $descriptive) {
            if (array_key_exists($descriptive, $data)) {
                $row[$descriptive] = $data[$descriptive];
            }
        }

        $id = $this->insert($row);

        if ($id === false) {
            throw new \RuntimeException('Failed to save bank transaction: ' . implode(' ', $this->errors()));
        }

        $this->updateBankBalance((int) $data['bank_account_id'], self::isCredit($type) ? $amount : -$amount);

        // Release 4.8.8A: single audit hook for every automatic posting
        // (Expenses/Loans/ServiceReceipts/CustomerPayments/SupplierPayments
        // all funnel through this one method), instead of duplicating the
        // audit call in each caller. audit_create() is a global function
        // from app/Helpers/audit_helper.php (autoloaded); it swallows its
        // own failures, so this never affects the posting above.
        if (function_exists('audit_create')) {
            audit_create('Bank', 'BANK_TRANSACTION', (int) $id, $data['reference_no'] ?? null, [
                'bank_account_id'  => (int) $data['bank_account_id'],
                'transaction_date' => $data['transaction_date'],
                'transaction_type' => $type,
                'amount'           => $amount,
                'reference_type'   => $data['reference_type'],
                'reference_id'     => $referenceId,
            ], 'Automatic ' . strtolower($type) . ' posted from ' . $data['reference_type'] . '.');
        }

        return (int) $id;
    }

    /**
     * Reverses the balance effect of every row posted for one reference, then
     * deletes those rows. Returns how many rows were removed (0 is normal for
     * a cash payment or a record saved before automatic posting existed).
     */
    public function deleteBankTransaction(string $referenceType, int $referenceId): int
    {
        $rows = $this->where('reference_type', $referenceType)->where('reference_id', $referenceId)->findAll();

        foreach ($rows as $row) {
            $this->restoreBankBalance($row);
            $this->delete((int) $row['id']);
        }

        return count($rows);
    }

    /**
     * Undoes one existing row's effect on current_balance (deposit is taken
     * back out, withdrawal is put back, a transfer is returned to its source).
     */
    public function restoreBankBalance(array $transaction): void
    {
        $amount = (float) $transaction['amount'];

        switch ($transaction['transaction_type']) {
            case 'DEPOSIT':
                $this->updateBankBalance((int) $transaction['bank_account_id'], -$amount);
                break;
            case 'WITHDRAWAL':
                $this->updateBankBalance((int) $transaction['bank_account_id'], $amount);
                break;
            case 'TRANSFER':
                $this->updateBankBalance((int) $transaction['bank_account_id'], $amount);
                $this->updateBankBalance((int) $transaction['transfer_bank_account_id'], -$amount);
                break;
            // Release 4.8.4I: each leg of a two-row transfer touches only its own account.
            case 'TRANSFER_OUT':
                $this->updateBankBalance((int) $transaction['bank_account_id'], $amount);
                break;
            case 'TRANSFER_IN':
                $this->updateBankBalance((int) $transaction['bank_account_id'], -$amount);
                break;
        }
    }

    /**
     * Applies $delta (positive credit, negative debit) to one account's
     * current_balance with a single atomic UPDATE, so concurrent postings
     * can't overwrite each other's read-modify-write.
     */
    public function updateBankBalance(int $bankAccountId, float $delta): void
    {
        $builder = $this->db->table('bank_accounts');

        if ($builder->where('id', $bankAccountId)->countAllResults() === 0) {
            throw new \RuntimeException("Bank account #{$bankAccountId} was not found while applying a balance update.");
        }

        $this->db->table('bank_accounts')
            ->set('current_balance', 'ROUND(current_balance + ' . number_format($delta, 2, '.', '') . ', 2)', false)
            ->where('id', $bankAccountId)
            ->update();
    }

    // =========================================================
    // RELEASE 4.8.4I — MANUAL BANK LEDGER ENGINE
    //
    // Deposit / Withdrawal / Bank Daybook (one row each, reference_id NULL)
    // and Transfer (two rows sharing one reference_id). Every balance change
    // goes through createBankTransaction() / restoreBankBalance() /
    // updateBankBalance() above; nothing here computes a balance of its own.
    // Like the helpers above, none of these open a transaction — the calling
    // controller owns transStart()/transComplete(), so a thrown exception
    // there rolls the rows AND the balances back together.
    //
    // Overdraft rule: an operation that lowers an account's balance may not
    // leave it below zero (DomainException, mapped to HTTP 422 by the
    // controllers). It is checked on the final state, after the operation's
    // own UPDATEs have taken their row locks, so an edit that reverses a
    // large entry and reposts a larger one is judged on where the account
    // ends up, and a concurrent posting cannot slip past the check.
    // =========================================================

    /** True when a row of this type adds money to its own account. */
    public static function isCredit(string $type): bool
    {
        return in_array($type, ['DEPOSIT', 'TRANSFER_IN'], true);
    }

    /** Reference types of single-row manual entries — the only rows updateManualEntry()/deleteManualEntry() accept. */
    public static function manualReferenceTypes(): array
    {
        return [self::REF_MANUAL_DEPOSIT, self::REF_MANUAL_WITHDRAWAL, self::REF_BANK_DAYBOOK];
    }

    /** Display number of a transfer, e.g. TRF-000012 (its reference_id, shared by both rows). */
    public static function transferNo(int $transferId): string
    {
        return 'TRF-' . str_pad((string) $transferId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Snapshot of current_balance (id => balance) for the given accounts, taken
     * before an operation and handed to assertNotOverdrawn() afterwards.
     */
    public function balancesOf(array $accountIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $accountIds))));
        if (! $ids) {
            return [];
        }

        $balances = [];
        foreach ($this->db->table('bank_accounts')->select('id, current_balance')->whereIn('id', $ids)->get()->getResultArray() as $row) {
            $balances[(int) $row['id']] = (float) $row['current_balance'];
        }

        return $balances;
    }

    /**
     * Throws a DomainException if any account in $before now sits below zero
     * having lost money since the snapshot. An account that was already
     * negative and did not get worse (a deposit into it, say) passes.
     */
    public function assertNotOverdrawn(array $before): void
    {
        foreach ($this->balancesOf(array_keys($before)) as $accountId => $after) {
            if ($after < -0.004 && $after < $before[$accountId] - 0.004) {
                $account = $this->db->table('bank_accounts')->select('bank_name, account_number')->where('id', $accountId)->get()->getRowArray();

                throw new \DomainException(sprintf(
                    'Insufficient balance in %s (%s): it holds %s, and this would leave it at %s.',
                    $account['bank_name'] ?? ('account #' . $accountId),
                    $account['account_number'] ?? '-',
                    number_format($before[$accountId], 2),
                    number_format($after, 2)
                ));
            }
        }
    }

    /**
     * Posts one manual Deposit / Withdrawal / Daybook row. $data is
     * createBankTransaction()'s array (reference_id is forced to NULL) and
     * must carry the entry's own reference_type.
     */
    public function postManualEntry(array $data): int
    {
        if (! in_array($data['reference_type'] ?? null, self::manualReferenceTypes(), true)) {
            throw new \RuntimeException('Not a manual bank entry reference type.');
        }
        if (! in_array($data['transaction_type'] ?? null, ['DEPOSIT', 'WITHDRAWAL'], true)) {
            throw new \RuntimeException('A manual bank entry must be a DEPOSIT or a WITHDRAWAL.');
        }

        $before = $this->balancesOf([$data['bank_account_id']]);
        $id     = $this->createBankTransaction(array_merge($data, ['reference_id' => null]));
        $this->assertNotOverdrawn($before);

        return $id;
    }

    /**
     * Edit of one manual entry: reverses the previous posting and reposts the
     * new values on the SAME row, so its id, created_at and created_by
     * survive. reference_type is never changed.
     */
    public function updateManualEntry(int $id, array $data): void
    {
        $old = $this->_findManualEntry($id);

        if (! in_array($data['transaction_type'] ?? null, ['DEPOSIT', 'WITHDRAWAL'], true)) {
            throw new \RuntimeException('A manual bank entry must be a DEPOSIT or a WITHDRAWAL.');
        }

        $before = $this->balancesOf([$old['bank_account_id'], $data['bank_account_id']]);
        $this->_repostRow($old, $data);
        $this->assertNotOverdrawn($before);
    }

    /** Deletes one manual entry and puts its effect back on the balance. */
    public function deleteManualEntry(int $id): void
    {
        $old    = $this->_findManualEntry($id);
        $before = $this->balancesOf([$old['bank_account_id']]);

        $this->restoreBankBalance($old);

        // Inside a transaction a failed query does not throw, it returns false and flags the transaction failed;
        // stop here rather than carry on, so the caller reports it and rolls the reversal back.
        if ($this->delete($id) === false) {
            throw new \RuntimeException("Failed to delete bank transaction #{$id}.");
        }

        $this->assertNotOverdrawn($before);
    }

    /**
     * Posts a transfer as two rows sharing one reference_id (the TRANSFER_OUT
     * row's own id): TRANSFER_OUT on the source, TRANSFER_IN on the
     * destination. $data: from_account_id, to_account_id, transaction_date,
     * amount, payment_mode, reference_no, remarks, created_by. Returns the
     * transfer id.
     */
    public function postTransfer(array $data): int
    {
        $from = (int) $data['from_account_id'];
        $to   = (int) $data['to_account_id'];

        if ($from === $to) {
            throw new \DomainException('Transfer cannot use the same source and destination account.');
        }

        $before = $this->balancesOf([$from, $to]);

        $common = [
            'transaction_date' => $data['transaction_date'],
            'amount'           => $data['amount'],
            'reference_type'   => self::REF_BANK_TRANSFER,
            'reference_no'     => $data['reference_no'] ?? null,
            'remarks'          => $data['remarks'] ?? null,
            'payment_mode'     => $data['payment_mode'] ?? null,
            'created_by'       => $data['created_by'] ?? null,
        ];

        $outId = $this->createBankTransaction($common + ['bank_account_id' => $from, 'transaction_type' => 'TRANSFER_OUT', 'reference_id' => null]);

        // The out-leg's own id is the transfer id: unique by construction, no counter to race on.
        $this->db->table('bank_transactions')->where('id', $outId)->update(['reference_id' => $outId]);

        $this->createBankTransaction($common + ['bank_account_id' => $to, 'transaction_type' => 'TRANSFER_IN', 'reference_id' => $outId]);

        $this->assertNotOverdrawn($before);

        return $outId;
    }

    /** Edit of a transfer: reverses and reposts both legs in place; the transfer id survives. */
    public function updateTransfer(int $transferId, array $data): void
    {
        [$out, $in] = $this->_findTransferLegs($transferId);

        $from = (int) $data['from_account_id'];
        $to   = (int) $data['to_account_id'];

        if ($from === $to) {
            throw new \DomainException('Transfer cannot use the same source and destination account.');
        }

        $before = $this->balancesOf([$out['bank_account_id'], $in['bank_account_id'], $from, $to]);

        $common = [
            'transaction_date' => $data['transaction_date'],
            'amount'           => $data['amount'],
            'reference_no'     => $data['reference_no'] ?? null,
            'remarks'          => $data['remarks'] ?? null,
            'payment_mode'     => $data['payment_mode'] ?? null,
        ];

        $this->_repostRow($out, $common + ['bank_account_id' => $from, 'transaction_type' => 'TRANSFER_OUT']);
        $this->_repostRow($in, $common + ['bank_account_id' => $to, 'transaction_type' => 'TRANSFER_IN']);

        $this->assertNotOverdrawn($before);
    }

    /** Deletes both legs of a transfer and reverses both balances. */
    public function deleteTransfer(int $transferId): void
    {
        [$out, $in] = $this->_findTransferLegs($transferId);

        $before = $this->balancesOf([$out['bank_account_id'], $in['bank_account_id']]);

        $this->deleteBankTransaction(self::REF_BANK_TRANSFER, $transferId);

        // deleteBankTransaction() does not check its deletes (a failed one returns false inside the transaction), so confirm both legs are gone.
        if ($this->where('reference_type', self::REF_BANK_TRANSFER)->where('reference_id', $transferId)->countAllResults() > 0) {
            throw new \RuntimeException("Failed to delete bank transfer #{$transferId}.");
        }

        $this->assertNotOverdrawn($before);
    }

    /** @return array<string,mixed> the manual entry row, or a RuntimeException if it is not one. */
    private function _findManualEntry(int $id): array
    {
        $row = $this->find($id);

        if (! $row || ! in_array($row['reference_type'], self::manualReferenceTypes(), true)) {
            throw new \RuntimeException("Manual bank entry #{$id} was not found.");
        }

        return $row;
    }

    /** @return array{0: array, 1: array} [TRANSFER_OUT row, TRANSFER_IN row] of one transfer. */
    private function _findTransferLegs(int $transferId): array
    {
        $legs = [];
        foreach ($this->where('reference_type', self::REF_BANK_TRANSFER)->where('reference_id', $transferId)->findAll() as $row) {
            $legs[$row['transaction_type']] = $row;
        }

        if (! isset($legs['TRANSFER_OUT'], $legs['TRANSFER_IN'])) {
            throw new \RuntimeException("Bank transfer #{$transferId} was not found.");
        }

        return [$legs['TRANSFER_OUT'], $legs['TRANSFER_IN']];
    }

    /**
     * Reverse-then-repost on one existing row (the edit half of the Expense /
     * Loan payment flow, keeping the row's identity): the old effect is taken
     * back off its account, the row is rewritten, and the new effect is
     * applied — all through restoreBankBalance()/updateBankBalance().
     */
    private function _repostRow(array $old, array $data): void
    {
        $type   = (string) $data['transaction_type'];
        $amount = round((float) $data['amount'], 2);

        if (! in_array($type, self::POSTING_TYPES, true)) {
            throw new \RuntimeException('Bank posting supports only DEPOSIT, WITHDRAWAL, TRANSFER_OUT and TRANSFER_IN.');
        }
        if ($amount <= 0) {
            throw new \RuntimeException('Bank transaction amount must be greater than zero.');
        }

        $account = $this->db->table('bank_accounts')->where('id', (int) $data['bank_account_id'])->get()->getRowArray();
        if (! $account) {
            throw new \RuntimeException('The selected bank account was not found.');
        }
        if ((int) $account['is_active'] !== 1) {
            throw new \RuntimeException('Transactions cannot be posted against an inactive bank account.');
        }

        $this->restoreBankBalance($old);

        $row = [
            'bank_account_id'  => (int) $data['bank_account_id'],
            'transaction_date' => $data['transaction_date'],
            'transaction_type' => $type,
            'amount'           => $amount,
            'reference_no'     => $data['reference_no'] ?? null,
            'remarks'          => $data['remarks'] ?? null,
        ];
        foreach (['payment_mode', 'party_name', 'category'] as $descriptive) {
            if (array_key_exists($descriptive, $data)) {
                $row[$descriptive] = $data[$descriptive];
            }
        }

        if ($this->update((int) $old['id'], $row) === false) {
            throw new \RuntimeException('Failed to update bank transaction: ' . implode(' ', $this->errors()));
        }

        $this->updateBankBalance((int) $data['bank_account_id'], self::isCredit($type) ? $amount : -$amount);
    }

    // ---------------------------------------------------------
    // Reading: statement + labels (read-only)
    // ---------------------------------------------------------

    /**
     * Statement of one account: its rows in chronological order with a running
     * balance carried forward from opening_balance (nothing is recomputed
     * beyond that cumulative walk; the amounts are read straight from
     * bank_transactions). With $from the period opens at opening_balance plus
     * everything posted before it; with $to later rows are left out.
     *
     * A row is a credit for the account when it is a DEPOSIT / TRANSFER_IN, or
     * a legacy single-row TRANSFER seen from its destination account; every
     * other row is a debit. Returns [] for an unknown account.
     */
    public function statementFor(int $accountId, ?string $from = null, ?string $to = null): array
    {
        $account = $this->db->table('bank_accounts')->where('id', $accountId)->get()->getRowArray();
        if (! $account) {
            return [];
        }

        $rows = $this->db->table('bank_transactions bt')
            ->select('bt.*, oa.bank_name AS counter_bank, oa.account_number AS counter_account')
            ->join('bank_transactions ot', "bt.reference_type = 'BANK_TRANSFER' AND ot.reference_type = 'BANK_TRANSFER' AND ot.reference_id = bt.reference_id AND ot.id <> bt.id", 'left')
            ->join('bank_accounts oa', 'oa.id = ot.bank_account_id', 'left')
            ->groupStart()
                ->where('bt.bank_account_id', $accountId)
                ->orGroupStart()
                    ->where('bt.transaction_type', 'TRANSFER')
                    ->where('bt.transfer_bank_account_id', $accountId)
                ->groupEnd()
            ->groupEnd()
            ->orderBy('bt.transaction_date', 'ASC')
            ->orderBy('bt.id', 'ASC')
            ->get()->getResultArray();

        $opening     = (float) $account['opening_balance'];
        $balance     = $opening;
        $deposits    = 0.0;
        $withdrawals = 0.0;
        $lines       = [];

        foreach ($rows as $row) {
            $amount   = (float) $row['amount'];
            $isCredit = self::isCredit($row['transaction_type'])
                || ($row['transaction_type'] === 'TRANSFER' && (int) $row['transfer_bank_account_id'] === $accountId && (int) $row['bank_account_id'] !== $accountId);

            if ($from !== null && $row['transaction_date'] < $from) {
                $opening += $isCredit ? $amount : -$amount;
                $balance  = $opening;
                continue;
            }
            if ($to !== null && $row['transaction_date'] > $to) {
                break;
            }

            $balance += $isCredit ? $amount : -$amount;
            $isCredit ? $deposits += $amount : $withdrawals += $amount;

            $isTransferLeg = $row['reference_type'] === self::REF_BANK_TRANSFER && $row['reference_id'];

            $lines[] = [
                'id'             => (int) $row['id'],
                'date'           => $row['transaction_date'],
                'type'           => $row['transaction_type'],
                'reference_type' => $row['reference_type'],
                'label'          => self::transactionLabel($row, $accountId),
                'reference'      => $isTransferLeg ? self::transferNo((int) $row['reference_id']) : (string) ($row['reference_no'] ?? ''),
                'reference_sub'  => $isTransferLeg ? (string) ($row['reference_no'] ?? '') : '',
                'category'       => (string) ($row['category'] ?? ''),
                'counterparty'   => $row['counter_bank'] ? $row['counter_bank'] . ' (' . $row['counter_account'] . ')' : '',
                'deposit'        => $isCredit ? round($amount, 2) : 0.0,
                'withdrawal'     => $isCredit ? 0.0 : round($amount, 2),
                'balance'        => round($balance, 2),
                'remarks'        => (string) ($row['remarks'] ?? ''),
            ];
        }

        return [
            'account'     => $account,
            'opening'     => round($opening, 2),
            'deposits'    => round($deposits, 2),
            'withdrawals' => round($withdrawals, 2),
            'closing'     => round($balance, 2),
            'rows'        => $lines,
        ];
    }

    /**
     * Statement "Transaction" column: what kind of posting a row is. Owning
     * modules are recognised by reference_type; everything else falls back to
     * the row's own type. $accountId is the account the row is being shown
     * for (needed only to read a legacy single-row TRANSFER from the right
     * side).
     */
    public static function transactionLabel(array $row, ?int $accountId = null): string
    {
        switch ((string) ($row['reference_type'] ?? '')) {
            case self::REF_BANK_DAYBOOK:
                return 'Manual Daybook';
            case 'SUPPLIER_PAYMENT':
            case 'SUPPLIER_ADVANCE':
                return 'Supplier Payment';
            case 'CUSTOMER_PAYMENT':
            case 'SERVICE_RECEIPT_PAYMENT':
                return 'Customer Payment';
            case 'SERVICE_RECEIPT':
                return 'Service Receipt';
            case 'EXPENSE':
                return 'Expense';
            case 'LOAN_PAYMENT':
                return 'Loan Payment';
            case 'PROJECT_ADVANCE':
                return 'Project Receipt';
        }

        switch ($row['transaction_type']) {
            case 'TRANSFER_IN':
                return 'Transfer In';
            case 'TRANSFER_OUT':
                return 'Transfer Out';
            case 'TRANSFER':
                return ($accountId !== null && (int) $row['transfer_bank_account_id'] === $accountId && (int) $row['bank_account_id'] !== $accountId)
                    ? 'Transfer In'
                    : 'Transfer Out';
            case 'DEPOSIT':
                return 'Deposit';
            case 'WITHDRAWAL':
                return 'Withdrawal';
        }

        return ucwords(strtolower(str_replace('_', ' ', (string) $row['transaction_type'])));
    }
}
