<?php

namespace App\Controllers;

use App\Libraries\TransactionMethods;
use App\Models\BankAccountModel;
use App\Models\BankTransactionModel;
use CodeIgniter\Controller;

/**
 * Release 4.8.4I Patch: Bank Transactions (Accounts -> Bank Accounts ->
 * Transactions) — one read-only list of what is in bank_transactions across
 * every bank account. Nothing is posted, recalculated or written here.
 *
 * Every line comes from BankTransactionModel::statementFor(), the routine the
 * Bank Statement already uses, so the Balance column is by construction the
 * same running balance the statement shows (opening balance carried forward
 * row by row in posting order) and not a second calculation. The filters only
 * choose which lines are displayed; they never re-base a balance. A legacy
 * single-row TRANSFER (written by the 4.8.3B quick-entry form) is one row in
 * bank_transactions that touches two accounts, so it is listed once per
 * account, Transfer Out on the source and Transfer In on the destination,
 * exactly as the two statements show it.
 *
 * Release 4.8.5A: this is the single bank transaction module. The toolbar's
 * voucher tabs (Deposit / Withdrawal / Transfer / Manual Entry) filter the list,
 * "+ New Transaction" opens the unified entry screen (create()), and the former
 * per-voucher list URLs redirect here (legacy()). Entries are still posted by the
 * existing BankDeposits / BankWithdrawals / BankTransfers / BankDaybook endpoints.
 *
 * Server-rendered GET page: the filters are query-string parameters, the same
 * shape as BankStatement / LoanReports. Invalid filter values are ignored.
 */
class BankTransactions extends Controller
{
    /** Transaction Type filter / column. A legacy TRANSFER counts as Transfer In or Out depending on the account it is listed under. */
    private const TYPES = [
        'DEPOSIT'      => 'Deposit',
        'WITHDRAWAL'   => 'Withdrawal',
        'TRANSFER_IN'  => 'Transfer In',
        'TRANSFER_OUT' => 'Transfer Out',
    ];

    /** Voucher Type tabs. Manual Entry is the BANK_DAYBOOK reference type; every other row is Deposit / Withdrawal / Transfer by its type. */
    private const VOUCHERS = [
        'deposit'    => 'Deposit',
        'withdrawal' => 'Withdrawal',
        'transfer'   => 'Transfer',
        'manual'     => 'Manual Entry',
    ];

    /** Voucher key -> controller whose cfg() / store endpoint the unified entry screen reuses. */
    private const ENTRY_CONTROLLERS = [
        'deposit'    => BankDeposits::class,
        'withdrawal' => BankWithdrawals::class,
        'transfer'   => BankTransfers::class,
        'manual'     => BankDaybook::class,
    ];

    /** Reference types whose entry has a detail page (with Edit / Delete): reference_type => route slug. */
    private const ENTRY_SLUGS = [
        'MANUAL_DEPOSIT'    => 'bank-deposits',
        'MANUAL_WITHDRAWAL' => 'bank-withdrawals',
        'BANK_TRANSFER'     => 'bank-transfers',
        'BANK_DAYBOOK'      => 'bank-accounts/manual-entry',
    ];

    public function index()
    {
        $accounts = (new BankAccountModel())->orderBy('bank_name', 'ASC')->orderBy('account_number', 'ASC')->findAll();

        // Release 4.9.3: a bank account is always selected (no "All Accounts" state).
        // Default is the first active account in the list above, or the first account
        // of any kind if none are active.
        $activeAccounts   = array_values(array_filter($accounts, static fn (array $a) => (int) $a['is_active'] === 1));
        $defaultAccountId = (int) ($activeAccounts[0]['id'] ?? $accounts[0]['id'] ?? 0);

        $accountId = (int) $this->_param('bank_account_id');
        if (! in_array($accountId, array_map('intval', array_column($accounts, 'id')), true)) {
            $accountId = $defaultAccountId;
        }

        $voucher = strtolower($this->_param('voucher'));
        if (! isset(self::VOUCHERS[$voucher])) {
            $voucher = '';
        }

        // Release 4.9.2: the three filters collapsed to Voucher Type / Bank Account / Month.
        // Month defaults to the current month so the page opens already scoped to it.
        $month      = $this->_monthParam('month');
        $currentMonth = date('Y-m');
        [$from, $to] = $this->_monthRange($month);

        // Every line of the selected account, with its running balance.
        $model       = new BankTransactionModel();
        $lines       = [];
        $transferIds = $this->_transferIds();

        foreach ($accounts as $account) {
            if ((int) $account['id'] !== $accountId) {
                continue;
            }

            foreach ($model->statementFor((int) $account['id'])['rows'] ?? [] as $row) {
                $lines[] = $this->_line($row, $account, $transferIds);
            }
        }

        $lines = array_values(array_filter($lines, function (array $line) use ($voucher, $from, $to) {
            if ($voucher !== '' && $line['voucher_key'] !== $voucher) {
                return false;
            }

            return $line['date'] >= $from && $line['date'] <= $to;
        }));

        // Release 4.9.0CB: payment method (HOW it was paid) read from the owning record, and the
        // wording of Unallocated Project Receipts. Display only; no amount or filter reads these.
        $ids       = array_column($lines, 'id');
        $methods   = TransactionMethods::forBankTransactions($ids);
        $projRecpt = TransactionMethods::unallocatedProjectReceipts($ids);
        foreach ($lines as &$line) {
            $line['method'] = $methods[$line['id']] ?? '';
            if (isset($projRecpt[$line['id']])) {
                // Rows posted before 4.9.0CB store "Project Advance - <project>"; newer ones "Unallocated Project Receipt — <project>".
                $project = (string) preg_replace('/^(Project Advance - |Unallocated Project Receipt — )/u', '', $line['remarks']);
                $line['remarks_display'] = implode(' - ', array_filter(['Unallocated Project Receipt', $line['reference'], $line['reference_sub'], $project], static fn (string $p) => $p !== ''));
            }
        }
        unset($line);

        // Release 4.9.4: oldest first (display order only — statementFor() already computed
        // each row's running balance in this same ascending posting order). The two lines of
        // one legacy TRANSFER share date and id, so the account breaks the tie.
        usort($lines, static fn (array $a, array $b) => [$a['date'], $a['id'], $a['account_id']] <=> [$b['date'], $b['id'], $b['account_id']]);

        // Release 4.9.1: the right-side "Bank Transaction Entry" panel embeds the same
        // voucher forms the standalone New Transaction screen (create()) uses.
        $entryVoucher = $voucher !== '' ? $voucher : 'deposit';

        return view('bank_transactions/index', [
            'accounts'         => $accounts,
            'vouchers'         => self::VOUCHERS,
            'filters'          => [
                'bank_account_id' => $accountId,
                'voucher'         => $voucher,
                'month'           => $month,
            ],
            'defaultAccountId' => $defaultAccountId,
            'currentMonth'     => $currentMonth,
            'isFiltered'       => $accountId !== $defaultAccountId || $voucher !== '' || $month !== $currentMonth,
            'lines'            => $lines,
            'entryVoucher'     => $entryVoucher,
            'forms'            => $this->_entryForms(),
        ]);
    }

    /** Unified New Transaction screen: a voucher-type dropdown that swaps between the four existing entry forms. */
    public function create()
    {
        $voucher = strtolower($this->_param('voucher'));
        if (! isset(self::VOUCHERS[$voucher])) {
            $voucher = 'deposit';
        }

        return view('bank_transactions/entry', [
            'vouchers' => self::VOUCHERS,
            'voucher'  => $voucher,
            'forms'    => $this->_entryForms(),
        ]);
    }

    /** Voucher key => entryFormData() of its controller (cfg + active accounts) — shared by index() and create(). */
    private function _entryForms(): array
    {
        $forms = [];
        foreach (self::ENTRY_CONTROLLERS as $key => $class) {
            $forms[$key] = (new $class())->entryFormData();
        }

        return $forms;
    }

    /** Old per-voucher list URLs (bank-deposits, bank-accounts/withdrawal, ...) land on Transactions with that voucher tab selected. */
    public function legacy(string $voucher = '')
    {
        $query = isset(self::VOUCHERS[$voucher]) ? '?voucher=' . $voucher : '';

        return redirect()->to(base_url('bank-accounts/transactions' . $query));
    }

    /** "Manual Deposit", "Supplier Payment", "Manual Entry" ... the readable form of a bank_transactions.reference_type. */
    public static function referenceLabel(string $referenceType): string
    {
        if ($referenceType === 'LOAN_DISBURSEMENT') {
            return 'Loan Received'; // display label only (Release 4.9.0BB); the stored reference_type is unchanged
        }

        return $referenceType === BankTransactionModel::REF_BANK_DAYBOOK
            ? 'Manual Entry'
            : ucwords(strtolower(str_replace('_', ' ', $referenceType)));
    }

    /** bank_transactions.id => transfer id for the two legs of every BANK_TRANSFER (the detail page is keyed by the transfer id). */
    private function _transferIds(): array
    {
        $rows = \Config\Database::connect()->table('bank_transactions')
            ->select('id, reference_id')
            ->where('reference_type', BankTransactionModel::REF_BANK_TRANSFER)
            ->where('reference_id IS NOT NULL')
            ->get()->getResultArray();

        return array_column($rows, 'reference_id', 'id');
    }

    /** One statement line + its account -> the flat array the view reads. */
    private function _line(array $row, array $account, array $transferIds = []): array
    {
        // A legacy single-row TRANSFER is Transfer In when this account is the one that received the money.
        $typeKey = $row['type'] === 'TRANSFER' ? ($row['deposit'] > 0 ? 'TRANSFER_IN' : 'TRANSFER_OUT') : $row['type'];

        $referenceType = (string) ($row['reference_type'] ?? '');

        // Voucher Type: Manual Entry by reference type, Transfer In/Out by direction, otherwise Deposit / Withdrawal.
        if ($referenceType === BankTransactionModel::REF_BANK_DAYBOOK) {
            $voucherKey   = 'manual';
            $voucherLabel = self::VOUCHERS['manual'];
        } elseif (strpos($typeKey, 'TRANSFER') === 0) {
            $voucherKey   = 'transfer';
            $voucherLabel = self::TYPES[$typeKey];
        } else {
            $voucherKey   = $typeKey === 'DEPOSIT' ? 'deposit' : 'withdrawal';
            $voucherLabel = self::VOUCHERS[$voucherKey];
        }

        // Only the four manual voucher kinds have a detail page to link to (it carries Edit / Delete); system postings are edited in their own module.
        $entryId = $referenceType === BankTransactionModel::REF_BANK_TRANSFER ? ($transferIds[$row['id']] ?? null) : $row['id'];
        $viewUrl = isset(self::ENTRY_SLUGS[$referenceType]) && $entryId !== null ? self::ENTRY_SLUGS[$referenceType] . '/view/' . (int) $entryId : '';

        $referenceLabel = $referenceType !== '' ? self::referenceLabel($referenceType) : '';
        $referenceValue = (string) $row['reference'];
        $remarksText    = (string) $row['remarks'];
        if ($referenceType === 'LOAN_DISBURSEMENT') {
            $remarksText = str_replace('Loan Disbursement', 'Loan Received', $remarksText); // display only (Release 4.9.0BB)
        }

        // Several posting modules already bake the reference label / number into their own
        // remarks text (e.g. remarks "Expense - Utilities" next to reference_type EXPENSE).
        // Strip an exact repeat of what we're already showing so the combined Remarks column
        // reads "Expense - EXP-000006 - Utilities" rather than repeating "Expense" or the
        // reference number a second time. This only removes text byte-for-byte identical to
        // data already displayed — nothing here is invented or reworded.
        if ($referenceLabel !== '' && str_starts_with($remarksText, $referenceLabel . ' - ')) {
            $remarksText = substr($remarksText, strlen($referenceLabel) + 3);
        }
        if ($referenceValue !== '' && str_ends_with($remarksText, ' - ' . $referenceValue)) {
            $remarksText = substr($remarksText, 0, -(strlen($referenceValue) + 3));
        }

        // Release 4.9.3: the Reference Type / Reference No columns were dropped from the
        // table; their information is folded into one Remarks string instead, e.g.
        // "Service Receipt - SRV-000001 - Vinayaga". Nothing here changes reference_type
        // or reference_id — this is a display string only.
        $remarksDisplay = implode(' - ', array_filter([
            $referenceLabel,
            $referenceValue,
            (string) $row['reference_sub'],
            $remarksText,
        ], static fn (string $part) => $part !== ''));

        return [
            'id'             => (int) $row['id'],
            'voucher_key'    => $voucherKey,
            'voucher_label'  => $voucherLabel,
            'view_url'       => $viewUrl,
            'reference_label' => $referenceLabel,
            'account_id'     => (int) $account['id'],
            'date'           => $row['date'],
            'bank_name'      => (string) $account['bank_name'],
            'account_name'   => (string) $account['account_name'],
            'account_number' => (string) $account['account_number'],
            'type_key'       => $typeKey,
            'type_label'     => self::TYPES[$typeKey] ?? ucwords(strtolower(str_replace('_', ' ', $typeKey))),
            'reference_type' => (string) ($row['reference_type'] ?? ''),
            'reference'      => (string) $row['reference'],
            'reference_sub'  => (string) $row['reference_sub'],
            'category'       => (string) $row['category'],
            'counterparty'   => (string) $row['counterparty'],
            'credit'         => (float) $row['deposit'],
            'debit'          => (float) $row['withdrawal'],
            'balance'        => (float) $row['balance'],
            'remarks'        => $referenceType === 'LOAN_DISBURSEMENT' ? str_replace('Loan Disbursement', 'Loan Received', (string) $row['remarks']) : (string) $row['remarks'],
            'remarks_display' => $remarksDisplay,
        ];
    }

    /** A query-string parameter as a trimmed string; '' when it is absent or not a plain string (e.g. ?q[]=x). */
    private function _param(string $name): string
    {
        $value = $this->request->getGet($name);

        return is_string($value) ? trim($value) : '';
    }

    /** A valid "Y-m" (month + year) query parameter, or the current month when absent/invalid. */
    private function _monthParam(string $name): string
    {
        $value = $this->_param($name);
        $date  = \DateTime::createFromFormat('Y-m', $value);

        return ($date && $date->format('Y-m') === $value) ? $value : date('Y-m');
    }

    /** The first and last calendar date ("Y-m-d") of a "Y-m" month. */
    private function _monthRange(string $month): array
    {
        $from = $month . '-01';

        return [$from, date('Y-m-t', strtotime($from))];
    }
}
