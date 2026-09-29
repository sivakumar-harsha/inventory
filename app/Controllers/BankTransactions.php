<?php

namespace App\Controllers;

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

    /** reference_type values the app writes; anything else found in the table is added to the filter as well. */
    private const KNOWN_REFERENCE_TYPES = [
        'MANUAL_DEPOSIT', 'MANUAL_WITHDRAWAL', 'BANK_TRANSFER', 'BANK_DAYBOOK',
        'SUPPLIER_PAYMENT', 'SUPPLIER_ADVANCE', 'CUSTOMER_PAYMENT', 'SERVICE_RECEIPT', 'SERVICE_RECEIPT_PAYMENT',
        'EXPENSE', 'LOAN_PAYMENT', 'PROJECT_ADVANCE',
    ];

    /** Reference Type filter value for rows that carry no reference_type (the 4.8.3B quick-entry form writes those). */
    private const NO_REFERENCE = 'NONE';

    public function index()
    {
        $accounts       = (new BankAccountModel())->orderBy('bank_name', 'ASC')->orderBy('account_number', 'ASC')->findAll();
        $referenceTypes = $this->_referenceTypes();

        // Filters. Anything unrecognised is dropped rather than turned into an empty page.
        $accountId = (int) $this->_param('bank_account_id');
        if (! in_array($accountId, array_map('intval', array_column($accounts, 'id')), true)) {
            $accountId = 0;
        }

        $type = strtoupper($this->_param('transaction_type'));
        if (! isset(self::TYPES[$type])) {
            $type = '';
        }

        $referenceType = $this->_param('reference_type');
        if ($referenceType !== self::NO_REFERENCE && ! in_array($referenceType, $referenceTypes, true)) {
            $referenceType = '';
        }

        $voucher = strtolower($this->_param('voucher'));
        if (! isset(self::VOUCHERS[$voucher])) {
            $voucher = '';
        }

        $from = $this->_dateParam('from');
        $to   = $this->_dateParam('to');
        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $search = mb_substr($this->_param('q'), 0, 100);

        // Every line of every (or the selected) account, with its running balance.
        $model       = new BankTransactionModel();
        $lines       = [];
        $transferIds = $this->_transferIds();

        foreach ($accounts as $account) {
            if ($accountId > 0 && (int) $account['id'] !== $accountId) {
                continue;
            }

            foreach ($model->statementFor((int) $account['id'])['rows'] ?? [] as $row) {
                $lines[] = $this->_line($row, $account, $transferIds);
            }
        }

        $lines = array_values(array_filter($lines, function (array $line) use ($type, $voucher, $referenceType, $from, $to, $search) {
            if ($type !== '' && $line['type_key'] !== $type) {
                return false;
            }
            if ($voucher !== '' && $line['voucher_key'] !== $voucher) {
                return false;
            }
            if ($referenceType === self::NO_REFERENCE ? $line['reference_type'] !== '' : ($referenceType !== '' && $line['reference_type'] !== $referenceType)) {
                return false;
            }
            if (($from !== null && $line['date'] < $from) || ($to !== null && $line['date'] > $to)) {
                return false;
            }

            return $search === '' || $this->_matches($line, $search);
        }));

        // Newest first; the two lines of one legacy TRANSFER share date and id, so the account breaks the tie.
        usort($lines, static fn (array $a, array $b) => [$b['date'], $b['id'], $b['account_id']] <=> [$a['date'], $a['id'], $a['account_id']]);

        return view('bank_transactions/index', [
            'accounts'       => $accounts,
            'typeOptions'    => self::TYPES,
            'vouchers'       => self::VOUCHERS,
            'referenceLabels' => array_combine($referenceTypes, array_map([self::class, 'referenceLabel'], $referenceTypes)),
            'referenceTypes' => $referenceTypes,
            'noReference'    => self::NO_REFERENCE,
            'filters'        => [
                'bank_account_id' => $accountId,
                'transaction_type' => $type,
                'voucher'         => $voucher,
                'reference_type'  => $referenceType,
                'from'            => $from,
                'to'              => $to,
                'q'               => $search,
            ],
            'isFiltered'     => $accountId > 0 || $type !== '' || $voucher !== '' || $referenceType !== '' || $from !== null || $to !== null || $search !== '',
            'lines'          => $lines,
        ]);
    }

    /** Unified New Transaction screen: a voucher-type dropdown that swaps between the four existing entry forms. */
    public function create()
    {
        $voucher = strtolower($this->_param('voucher'));
        if (! isset(self::VOUCHERS[$voucher])) {
            $voucher = 'deposit';
        }

        $forms = [];
        foreach (self::ENTRY_CONTROLLERS as $key => $class) {
            $forms[$key] = (new $class())->entryFormData();
        }

        return view('bank_transactions/entry', [
            'vouchers' => self::VOUCHERS,
            'voucher'  => $voucher,
            'forms'    => $forms,
        ]);
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

        return [
            'id'             => (int) $row['id'],
            'voucher_key'    => $voucherKey,
            'voucher_label'  => $voucherLabel,
            'view_url'       => $viewUrl,
            'reference_label' => $referenceType !== '' ? self::referenceLabel($referenceType) : '',
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
            'remarks'        => (string) $row['remarks'],
        ];
    }

    /** Free-text search over what the list shows: bank, type, reference type/no, remarks, category, counterparty and the amount. */
    private function _matches(array $line, string $needle): bool
    {
        $amount = $line['credit'] > 0 ? $line['credit'] : $line['debit'];

        $haystack = implode("\n", [
            $line['bank_name'], $line['account_name'], $line['account_number'], $line['type_label'], $line['voucher_label'], $line['reference_label'],
            $line['reference_type'], $line['reference'], $line['reference_sub'], $line['remarks'],
            $line['category'], $line['counterparty'],
            number_format($amount, 2, '.', ''), number_format($amount, 2),
        ]);

        return mb_stripos($haystack, $needle) !== false;
    }

    /** The reference types the app writes plus any other value actually present in bank_transactions, sorted. */
    private function _referenceTypes(): array
    {
        $found = \Config\Database::connect()->table('bank_transactions')
            ->select('reference_type')
            ->distinct()
            ->where('reference_type IS NOT NULL')
            ->where('reference_type !=', '')
            ->get()->getResultArray();

        $types = array_unique(array_merge(self::KNOWN_REFERENCE_TYPES, array_column($found, 'reference_type')));
        sort($types);

        return $types;
    }

    /** A query-string parameter as a trimmed string; '' when it is absent or not a plain string (e.g. ?q[]=x). */
    private function _param(string $name): string
    {
        $value = $this->request->getGet($name);

        return is_string($value) ? trim($value) : '';
    }

    /** A valid Y-m-d query parameter, or null. */
    private function _dateParam(string $name): ?string
    {
        $value = $this->_param($name);
        $date  = \DateTime::createFromFormat('Y-m-d', $value);

        return ($date && $date->format('Y-m-d') === $value) ? $value : null;
    }
}
