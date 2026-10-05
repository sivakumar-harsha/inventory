<?php

namespace App\Controllers;

use App\Libraries\TransactionMethods;
use App\Models\BankAccountModel;
use App\Models\BankTransactionModel;
use CodeIgniter\Controller;

/**
 * Release 4.8.3A built the backend. Release 4.8.3B wired index()/create()/
 * view()/edit()/transactions() to real views — pure display/data-prep, same
 * transition Supplier Payments went through between 4.8.2B and 4.8.2C.
 * Manual entries only; nothing in store()/update()/delete() is wired to
 * Supplier Payments, Purchases, or any other module.
 *
 * Release 4.9.0AO: storeTransaction() (this page's Deposit/Withdrawal/
 * Transfer entry form) no longer keeps its own insert + balance-update
 * logic. It now posts through the same BankTransactionModel engine as the
 * Bank Deposit/Withdrawal/Transfer screens (postManualEntry()/postTransfer()),
 * so there is one accounting engine, not two: reference_type/reference_id
 * are always the fixed manual ones (never taken from the request), and the
 * overdraft rule applies here too. _getRunningBalance() (read-only) is
 * unchanged and already understood two-row transfers.
 */
class BankAccounts extends Controller
{
    public function index()
    {
        $accountModel = new BankAccountModel();
        $accounts     = $accountModel->orderBy('bank_name', 'ASC')->findAll();

        $db = \Config\Database::connect();
        $totals = $db->table('bank_transactions')
            ->select("
                COALESCE(SUM(CASE WHEN transaction_type = 'DEPOSIT' THEN amount ELSE 0 END), 0) AS total_deposits,
                COALESCE(SUM(CASE WHEN transaction_type = 'WITHDRAWAL' THEN amount ELSE 0 END), 0) AS total_withdrawals
            ")
            ->get()->getRowArray();

        $data['accounts']              = $accounts;
        $data['kpi_total_accounts']    = count($accounts);
        $data['kpi_total_balance']     = array_sum(array_column($accounts, 'current_balance'));
        $data['kpi_total_deposits']    = (float) $totals['total_deposits'];
        $data['kpi_total_withdrawals'] = (float) $totals['total_withdrawals'];

        return view('bank_accounts/index', $data);
    }

    public function create()
    {
        return view('bank_accounts/create');
    }

    public function store()
    {
        $model = new BankAccountModel();

        $errors = $this->_validateAccountInput($this->_extractAccountInput(), null);
        if ($errors) {
            return $this->response->setJSON(['status' => false, 'errors' => $errors])->setStatusCode(422);
        }

        $input = $this->_extractAccountInput();

        $db = \Config\Database::connect();
        $db->transStart();

        $accountId = null;

        try {
            $model->insert([
                'bank_name'       => $input['bank_name'],
                'account_name'    => $input['account_name'],
                'account_number'  => $input['account_number'],
                'ifsc_code'       => $input['ifsc_code'],
                'branch_name'     => $input['branch_name'],
                'opening_balance' => $input['opening_balance'],
                'current_balance' => $input['opening_balance'],
                'is_active'       => 1,
                'created_by'      => session()->get('user_id'),
            ]);

            $accountId = $model->getInsertID();
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to save bank account: ' . $e->getMessage()]])->setStatusCode(500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to save bank account due to a database error.']])->setStatusCode(500);
        }

        // Release 4.8.8A: audit trail only.
        audit_create('Bank', 'BANK_ACCOUNT', (int) $accountId, $input['account_number'], [
            'bank_name'       => $input['bank_name'],
            'account_name'    => $input['account_name'],
            'account_number'  => $input['account_number'],
            'ifsc_code'       => $input['ifsc_code'],
            'branch_name'     => $input['branch_name'],
            'opening_balance' => $input['opening_balance'],
            'is_active'       => 1,
        ], 'Bank account ' . $input['account_number'] . ' created.');

        session()->setFlashdata('success', 'Bank account created successfully.');

        return $this->response->setJSON([
            'status'  => true,
            'message' => 'Bank account created successfully.',
            'id'      => $accountId,
        ]);
    }

    public function view($id)
    {
        $account = (new BankAccountModel())->find((int) $id);

        if (! $account) {
            return redirect()->to('/bank-accounts')->with('error', 'Bank account not found.');
        }

        return view('bank_accounts/view', ['account' => $account]);
    }

    public function edit($id)
    {
        $account = (new BankAccountModel())->find((int) $id);

        if (! $account) {
            return redirect()->to('/bank-accounts')->with('error', 'Bank account not found.');
        }

        return view('bank_accounts/edit', ['account' => $account]);
    }

    public function update($id)
    {
        $model   = new BankAccountModel();
        $account = $model->find((int) $id);

        if (! $account) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Bank account not found.']])->setStatusCode(404);
        }

        $input  = $this->_extractAccountInput();
        $errors = $this->_validateAccountInput($input, (int) $id);
        if ($errors) {
            return $this->response->setJSON(['status' => false, 'errors' => $errors])->setStatusCode(422);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Preserve the net effect of every transaction posted so far
            // when the opening balance itself is edited, instead of
            // silently wiping out transaction history's contribution to
            // the balance.
            $netFromTransactions = (float) $account['current_balance'] - (float) $account['opening_balance'];
            $newCurrentBalance   = $input['opening_balance'] + $netFromTransactions;

            $model->update((int) $id, [
                'bank_name'       => $input['bank_name'],
                'account_name'    => $input['account_name'],
                'account_number'  => $input['account_number'],
                'ifsc_code'       => $input['ifsc_code'],
                'branch_name'     => $input['branch_name'],
                'opening_balance' => $input['opening_balance'],
                'current_balance' => $newCurrentBalance,
                'is_active'       => $input['is_active'],
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to update bank account: ' . $e->getMessage()]])->setStatusCode(500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to update bank account due to a database error.']])->setStatusCode(500);
        }

        // Release 4.8.8A: audit trail only. $account is the row read at the
        // top of this method, before any change was applied.
        audit_update('Bank', 'BANK_ACCOUNT', (int) $id, $input['account_number'], $account, [
            'bank_name'       => $input['bank_name'],
            'account_name'    => $input['account_name'],
            'account_number'  => $input['account_number'],
            'ifsc_code'       => $input['ifsc_code'],
            'branch_name'     => $input['branch_name'],
            'opening_balance' => $input['opening_balance'],
            'current_balance' => $newCurrentBalance,
            'is_active'       => $input['is_active'],
        ], 'Bank account ' . $input['account_number'] . ' updated.');

        session()->setFlashdata('success', 'Bank account updated successfully.');

        return $this->response->setJSON(['status' => true, 'message' => 'Bank account updated successfully.']);
    }

    public function delete($id)
    {
        $model   = new BankAccountModel();
        $account = $model->find((int) $id);

        if (! $account) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Bank account not found.']])->setStatusCode(404);
        }

        $db = \Config\Database::connect();
        $inUse = $db->table('bank_transactions')
            ->groupStart()
                ->where('bank_account_id', (int) $id)
                ->orWhere('transfer_bank_account_id', (int) $id)
            ->groupEnd()
            ->countAllResults();

        if ($inUse > 0) {
            return $this->response->setJSON(['status' => false, 'errors' => ['This bank account has transaction history and cannot be deleted. Deactivate it instead.']])->setStatusCode(422);
        }

        $db->transStart();

        try {
            $model->delete((int) $id);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to delete bank account: ' . $e->getMessage()]])->setStatusCode(500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to delete bank account due to a database error.']])->setStatusCode(500);
        }

        // Release 4.8.8A: audit trail only.
        audit_delete('Bank', 'BANK_ACCOUNT', (int) $id, $account['account_number'] ?? null, $account, 'Bank account ' . ($account['account_number'] ?? $id) . ' deleted.');

        session()->setFlashdata('success', 'Bank account deleted successfully.');

        return $this->response->setJSON(['status' => true, 'message' => 'Bank account deleted successfully.']);
    }

    /**
     * Bank Transactions page: transaction entry (Deposit/Withdrawal/
     * Transfer) plus the full running-balance statement for one account,
     * combined on a single page (this release authorizes only one view
     * file for both).
     */
    public function transactions($id)
    {
        $account = (new BankAccountModel())->find((int) $id);

        if (! $account) {
            return redirect()->to('/bank-accounts')->with('error', 'Bank account not found.');
        }

        $data['account']         = $account;
        $data['otherAccounts']   = (new BankAccountModel())->where('is_active', 1)->where('id !=', (int) $id)->orderBy('bank_name', 'ASC')->findAll();
        $data['transactions']    = $this->_getRunningBalance((int) $id);

        // Release 4.9.0CB: payment method (HOW it was paid) from the owning record + Unallocated Project Receipt wording. Display only.
        $ids       = array_column($data['transactions'], 'id');
        $methods   = TransactionMethods::forBankTransactions($ids);
        $projRecpt = TransactionMethods::unallocatedProjectReceipts($ids);
        foreach ($data['transactions'] as &$t) {
            $t['method'] = $methods[$t['id']] ?? '';
            if (isset($projRecpt[$t['id']])) {
                $t['remarks'] = TransactionMethods::receiptRemark((string) $t['remarks']);
            }
        }
        unset($t);

        return view('bank_accounts/transactions', $data);
    }

    /**
     * Records one manual Deposit/Withdrawal/Transfer from the per-account
     * page. Release 4.9.0AO: rewired onto the same engine as the Bank
     * Deposit/Withdrawal/Transfer screens (BankTransactionModel::
     * postManualEntry()/postTransfer()) instead of this controller's own
     * insert + balance update, so this page no longer runs a second,
     * divergent set of accounting rules. Concretely this closes two gaps
     * the 4.9.0AN audit found: reference_type/reference_id are no longer
     * accepted from the request at all (every row here is always a genuine
     * manual entry, keyed by the same MANUAL_DEPOSIT/MANUAL_WITHDRAWAL/
     * BANK_TRANSFER reference types the unified Transactions screen uses),
     * and the overdraft rule (assertNotOverdrawn) now applies here too. The
     * view never sent reference_type/reference_id (confirmed: no such field
     * exists in bank_accounts/transactions.php), so no legitimate use of
     * this page is affected.
     */
    public function storeTransaction()
    {
        $input  = $this->_extractTransactionInput();
        $errors = $this->_validateTransactionInput($input);

        if ($errors) {
            return $this->response->setJSON(['status' => false, 'errors' => $errors])->setStatusCode(422);
        }

        $db    = \Config\Database::connect();
        $model = new BankTransactionModel();
        $db->transStart();

        $transactionId = null;

        try {
            if ($input['transaction_type'] === 'TRANSFER') {
                $transactionId = $model->postTransfer([
                    'from_account_id'  => $input['bank_account_id'],
                    'to_account_id'    => $input['transfer_bank_account_id'],
                    'transaction_date' => $input['transaction_date'],
                    'amount'           => $input['amount'],
                    'reference_no'     => $input['reference_no'],
                    'remarks'          => $input['remarks'],
                    'created_by'       => session()->get('user_id'),
                ]);
            } else {
                $transactionId = $model->postManualEntry([
                    'bank_account_id'  => $input['bank_account_id'],
                    'transaction_date' => $input['transaction_date'],
                    'transaction_type' => $input['transaction_type'],
                    'amount'           => $input['amount'],
                    'reference_type'   => $input['transaction_type'] === 'DEPOSIT'
                        ? BankTransactionModel::REF_MANUAL_DEPOSIT
                        : BankTransactionModel::REF_MANUAL_WITHDRAWAL,
                    'reference_no'     => $input['reference_no'],
                    'remarks'          => $input['remarks'],
                    'created_by'       => session()->get('user_id'),
                ]);
            }
        } catch (\DomainException $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'errors' => [$e->getMessage()]])->setStatusCode(422);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to save transaction: ' . $e->getMessage()]])->setStatusCode(500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to save transaction due to a database error.']])->setStatusCode(500);
        }

        // Audit trail: postManualEntry()/postTransfer() already call
        // BankTransactionModel::createBankTransaction(), which fires the
        // single shared audit_create() hook for every row it inserts —
        // no separate audit call needed here any more.

        session()->setFlashdata('success', 'Bank transaction saved successfully.');

        return $this->response->setJSON([
            'status'  => true,
            'message' => 'Bank transaction saved successfully.',
            'id'      => $transactionId,
        ]);
    }

    // =========================================================
    // SHARED HELPERS
    // =========================================================

    private function _extractAccountInput(): array
    {
        return [
            'bank_name'       => (string) $this->request->getPost('bank_name'),
            'account_name'    => (string) $this->request->getPost('account_name'),
            'account_number'  => (string) $this->request->getPost('account_number'),
            'ifsc_code'       => $this->request->getPost('ifsc_code') ?: null,
            'branch_name'     => $this->request->getPost('branch_name') ?: null,
            'opening_balance' => (float) ($this->request->getPost('opening_balance') ?: 0),
            'is_active'       => $this->request->getPost('is_active') === null ? 1 : (int) (bool) $this->request->getPost('is_active'),
        ];
    }

    private function _validateAccountInput(array $input, ?int $excludeId): array
    {
        $errors = [];

        if ($input['bank_name'] === '') {
            $errors[] = 'Bank name is required.';
        }
        if ($input['account_name'] === '') {
            $errors[] = 'Account name is required.';
        }
        if ($input['account_number'] === '') {
            $errors[] = 'Account number is required.';
        }
        if ($input['opening_balance'] < 0) {
            $errors[] = 'Opening balance cannot be negative.';
        }

        return $errors;
    }

    /**
     * Release 4.9.0AO: no reference_type/reference_id here — every row this
     * page posts is a genuine manual entry, and storeTransaction() now
     * assigns the fixed MANUAL_DEPOSIT/MANUAL_WITHDRAWAL/BANK_TRANSFER
     * reference type itself. This page's form never sent either field
     * (there is no such input in bank_accounts/transactions.php), so this
     * only removes a request field nothing legitimate relied on.
     */
    private function _extractTransactionInput(): array
    {
        return [
            'bank_account_id'          => (int) $this->request->getPost('bank_account_id'),
            'transaction_date'         => (string) $this->request->getPost('transaction_date'),
            'transaction_type'         => strtoupper((string) $this->request->getPost('transaction_type')),
            'amount'                   => (float) $this->request->getPost('amount'),
            'reference_no'             => $this->request->getPost('reference_no') ?: null,
            'remarks'                  => $this->request->getPost('remarks') ?: null,
            'transfer_bank_account_id' => $this->request->getPost('transfer_bank_account_id') ? (int) $this->request->getPost('transfer_bank_account_id') : null,
        ];
    }

    /**
     * Phase G-style validation for one manual transaction: valid account(s),
     * positive amount, no posting against an inactive account, and a
     * transfer must name two distinct accounts.
     */
    private function _validateTransactionInput(array $input): array
    {
        $errors = [];

        if (! in_array($input['transaction_type'], ['DEPOSIT', 'WITHDRAWAL', 'TRANSFER'], true)) {
            $errors[] = 'A valid transaction type (Deposit, Withdrawal, or Transfer) is required.';
        }
        if ($input['transaction_date'] === '' || ! \DateTime::createFromFormat('Y-m-d', $input['transaction_date'])) {
            $errors[] = 'A valid transaction date is required.';
        }
        if ($input['amount'] <= 0) {
            $errors[] = 'Amount must be greater than zero.';
        }

        $accountModel = new BankAccountModel();
        $account      = $input['bank_account_id'] > 0 ? $accountModel->find($input['bank_account_id']) : null;

        if (! $account) {
            $errors[] = 'The bank account is required and must exist.';
        } elseif ((int) $account['is_active'] !== 1) {
            $errors[] = 'Transactions cannot be posted against an inactive bank account.';
        }

        if ($input['transaction_type'] === 'TRANSFER') {
            if (! $input['transfer_bank_account_id']) {
                $errors[] = 'A destination account is required for a transfer.';
            } elseif ($input['transfer_bank_account_id'] === $input['bank_account_id']) {
                $errors[] = 'Transfer cannot use the same source and destination account.';
            } else {
                $destination = $accountModel->find($input['transfer_bank_account_id']);
                if (! $destination) {
                    $errors[] = 'The destination account is required and must exist.';
                } elseif ((int) $destination['is_active'] !== 1) {
                    $errors[] = 'Transactions cannot be posted against an inactive bank account.';
                }
            }
        }

        return $errors;
    }

    /**
     * Full chronological ledger for one account: starts at opening_balance,
     * then walks every transaction that touches the account (as source or,
     * for a TRANSFER, as destination), applying a debit/credit per row and
     * carrying a running balance forward.
     */
    private function _getRunningBalance(int $bankAccountId): array
    {
        $account = (new BankAccountModel())->find($bankAccountId);
        $rows    = (new BankTransactionModel())->forAccount($bankAccountId);

        $balance = (float) ($account['opening_balance'] ?? 0);
        $ledger  = [];

        foreach ($rows as $row) {
            $isDestinationSide = $row['transaction_type'] === 'TRANSFER' && (int) $row['transfer_bank_account_id'] === $bankAccountId;

            // Release 4.8.4I: a two-row transfer's TRANSFER_IN leg is a credit (read-side only).
            if ($row['transaction_type'] === 'DEPOSIT' || $row['transaction_type'] === 'TRANSFER_IN' || $isDestinationSide) {
                $debit  = 0.0;
                $credit = (float) $row['amount'];
            } else { // WITHDRAWAL, or TRANSFER viewed from the source side
                $debit  = (float) $row['amount'];
                $credit = 0.0;
            }

            $balance += $credit - $debit;

            $ledger[] = [
                'id'                => (int) $row['id'],
                'date'              => $row['transaction_date'],
                'type'              => $row['transaction_type'],
                'reference_no'      => $row['reference_no'],
                'remarks'           => $row['remarks'],
                'debit'             => $debit,
                'credit'            => $credit,
                'balance'           => $balance,
            ];
        }

        return $ledger;
    }
}
