<?php

namespace App\Controllers;

use App\Models\BankTransactionModel;

/**
 * Release 4.8.4I (Phase C): Bank Transfer between two accounts. One transfer
 * is TWO bank_transactions rows sharing one reference_id (reference_type
 * BANK_TRANSFER): TRANSFER_OUT on the source and TRANSFER_IN on the
 * destination, posted, edited and deleted together inside one database
 * transaction by BankTransactionModel::postTransfer() / updateTransfer() /
 * deleteTransfer(). If either leg fails — or the source cannot cover the
 * amount — nothing is written and both balances are untouched.
 *
 * The transfer's id (and its display number, TRF-000012) is the id of its
 * TRANSFER_OUT row, which survives an edit. Transfer Method is stored in
 * payment_mode on both rows; the user's Reference No (cheque / UTR) is
 * reference_no on both rows.
 */
class BankTransfers extends BankEntryController
{
    private const METHODS = ['CHEQUE', 'UPI', 'NEFT', 'RTGS', 'IMPS', 'CASH'];

    protected function cfg(): array
    {
        return [
            'slug'      => 'bank-transfers',
            'title'     => 'Bank Transfers',
            'plural'    => 'Transfers',
            'singular'  => 'Transfer',
            'icon'      => 'bi-arrow-left-right',
            'kpi_class' => 'kpi-blue',
            'columns'   => [
                ['key' => 'transfer_no',      'label' => 'Transfer No',  'type' => 'text'],
                ['key' => 'transaction_date', 'label' => 'Date',         'type' => 'date'],
                ['key' => 'from_label',       'label' => 'From Account', 'type' => 'text'],
                ['key' => 'to_label',         'label' => 'To Account',   'type' => 'text'],
                ['key' => 'transfer_method',  'label' => 'Method',       'type' => 'badge', 'badges' => []],
                ['key' => 'reference_no',     'label' => 'Reference No', 'type' => 'text'],
                ['key' => 'amount',           'label' => 'Amount',       'type' => 'money'],
            ],
            'fields'    => [
                ['name' => 'transaction_date', 'label' => 'Date',            'type' => 'date',   'required' => true, 'default' => date('Y-m-d')],
                ['name' => 'from_account_id',  'label' => 'From Account',    'type' => 'bank',   'required' => true],
                ['name' => 'to_account_id',    'label' => 'To Account',      'type' => 'bank',   'required' => true],
                ['name' => 'amount',           'label' => 'Amount',          'type' => 'money',  'required' => true],
                ['name' => 'transfer_method',  'label' => 'Transfer Method', 'type' => 'select', 'required' => true, 'options' => ['CHEQUE' => 'Cheque', 'UPI' => 'UPI', 'NEFT' => 'NEFT', 'RTGS' => 'RTGS', 'IMPS' => 'IMPS', 'CASH' => 'Cash'], 'default' => 'NEFT'],
                ['name' => 'reference_no',     'label' => 'Reference No',    'type' => 'text',   'maxlength' => 100, 'hint' => 'Cheque / UTR number (optional).'],
                ['name' => 'remarks',          'label' => 'Remarks',         'type' => 'textarea', 'wide' => true],
            ],
            'detail'    => [
                ['key' => 'transfer_no',      'label' => 'Transfer No',     'type' => 'text'],
                ['key' => 'transaction_date', 'label' => 'Date',            'type' => 'date'],
                ['key' => 'from_label',       'label' => 'From Account',    'type' => 'text'],
                ['key' => 'to_label',         'label' => 'To Account',      'type' => 'text'],
                ['key' => 'amount',           'label' => 'Amount',          'type' => 'money'],
                ['key' => 'transfer_method',  'label' => 'Transfer Method', 'type' => 'badge', 'badges' => []],
                ['key' => 'reference_no',     'label' => 'Reference No',    'type' => 'text'],
                ['key' => 'remarks',          'label' => 'Remarks',         'type' => 'text'],
            ],
            'preview'   => ['mode' => 'transfer', 'from' => 'from_account_id', 'to' => 'to_account_id'],
        ];
    }

    protected function referenceType(): string
    {
        return BankTransactionModel::REF_BANK_TRANSFER;
    }

    protected function extractInput(): array
    {
        return [
            'transaction_date' => trim((string) $this->request->getPost('transaction_date')),
            'from_account_id'  => (int) $this->request->getPost('from_account_id'),
            'to_account_id'    => (int) $this->request->getPost('to_account_id'),
            'amount'           => trim((string) $this->request->getPost('amount')),
            'transfer_method'  => strtoupper(trim((string) $this->request->getPost('transfer_method'))),
            'reference_no'     => $this->optional('reference_no'),
            'remarks'          => $this->optional('remarks'),
        ];
    }

    /**
     * Phase G (Transfer): source and destination different, both active,
     * amount > 0, a method from the fixed list. "Source balance must be
     * enough" is enforced by the model inside the transaction.
     */
    protected function validateInput(array $input): array
    {
        $sameAccount = $input['from_account_id'] > 0 && $input['from_account_id'] === $input['to_account_id'];

        return array_values(array_filter([
            $this->dateError($input['transaction_date']),
            $this->accountError($input['from_account_id'], 'source account'),
            $this->accountError($input['to_account_id'], 'destination account'),
            $sameAccount ? 'Transfer cannot use the same source and destination account.' : null,
            $this->amountError($input['amount']),
            in_array($input['transfer_method'], self::METHODS, true) ? null : 'A valid transfer method is required.',
            $this->lengthError($input['reference_no'], 100, 'Reference No'),
        ]));
    }

    /** Both legs share these; the model adds the accounts and the TRANSFER_OUT / TRANSFER_IN types. */
    protected function rowData(array $input): array
    {
        return [
            'from_account_id'  => $input['from_account_id'],
            'to_account_id'    => $input['to_account_id'],
            'transaction_date' => $input['transaction_date'],
            'amount'           => round((float) $input['amount'], 2),
            'payment_mode'     => $input['transfer_method'],
            'reference_no'     => $input['reference_no'],
            'remarks'          => $input['remarks'],
        ];
    }

    protected function presentRow(array $row): array
    {
        return [];
    }

    // =========================================================
    // TWO-ROW ENGINE HOOKS
    // =========================================================

    protected function postEntry(BankTransactionModel $model, array $input): int
    {
        return $model->postTransfer($this->rowData($input) + ['created_by' => session()->get('user_id')]);
    }

    protected function repostEntry(BankTransactionModel $model, array $entry, array $input): void
    {
        $model->updateTransfer((int) $entry['id'], $this->rowData($input));
    }

    protected function deleteEntry(BankTransactionModel $model, array $entry): void
    {
        $model->deleteTransfer((int) $entry['id']);
    }

    /** One entry per transfer: its TRANSFER_OUT row joined to its TRANSFER_IN row. */
    protected function fetchEntries(?int $id = null): array
    {
        $builder = \Config\Database::connect()->table('bank_transactions o')
            // o.* rather than naming payment_mode, so the list still opens (empty) on a database that has not run the 4.8.4I migration yet.
            ->select('o.*, o.reference_id AS transfer_id,'
                . ' o.bank_account_id AS from_account_id, i.bank_account_id AS to_account_id,'
                . ' fa.bank_name AS from_bank, fa.account_number AS from_number, ta.bank_name AS to_bank, ta.account_number AS to_number,'
                . ' u.username AS created_by_name')
            ->join('bank_transactions i', "i.reference_type = 'BANK_TRANSFER' AND i.reference_id = o.reference_id AND i.transaction_type = 'TRANSFER_IN'")
            ->join('bank_accounts fa', 'fa.id = o.bank_account_id')
            ->join('bank_accounts ta', 'ta.id = i.bank_account_id')
            ->join('users u', 'u.id = o.created_by', 'left')
            ->where('o.reference_type', BankTransactionModel::REF_BANK_TRANSFER)
            ->where('o.transaction_type', 'TRANSFER_OUT');

        if ($id !== null) {
            $builder->where('o.reference_id', $id);
        }

        $entries = [];
        foreach ($builder->orderBy('o.transaction_date', 'DESC')->orderBy('o.reference_id', 'DESC')->get()->getResultArray() as $row) {
            $amount = (float) $row['amount'];
            $from   = (int) $row['from_account_id'];
            $to     = (int) $row['to_account_id'];

            $entries[] = [
                'id'               => (int) $row['transfer_id'],
                'transfer_no'      => BankTransactionModel::transferNo((int) $row['transfer_id']),
                'transaction_date' => $row['transaction_date'],
                'from_account_id'  => $from,
                'to_account_id'    => $to,
                'from_label'       => $row['from_bank'] . ' — ' . $row['from_number'],
                'to_label'         => $row['to_bank'] . ' — ' . $row['to_number'],
                'amount'           => $amount,
                'transfer_method'  => $row['payment_mode'] ?? null,
                'reference_no'     => $row['reference_no'],
                'remarks'          => $row['remarks'],
                'created_by_name'  => $row['created_by_name'],
                'created_at'       => $row['created_at'],
                'updated_at'       => $row['updated_at'],
                'effects'          => [$from => -$amount, $to => $amount],
            ];
        }

        return $entries;
    }
}
