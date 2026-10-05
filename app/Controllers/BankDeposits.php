<?php

namespace App\Controllers;

use App\Models\BankTransactionModel;

/**
 * Release 4.8.4I (Phase A): manual Bank Deposit. One bank_transactions row per
 * entry — transaction_type DEPOSIT, reference_type MANUAL_DEPOSIT,
 * reference_id NULL — posted, edited (reverse then repost) and deleted through
 * BankTransactionModel. Deposit Type is stored in payment_mode, "From" in
 * party_name.
 */
class BankDeposits extends BankEntryController
{
    protected function cfg(): array
    {
        $modes = ['CASH' => 'Cash', 'CHEQUE' => 'Cheque', 'UPI' => 'UPI', 'NEFT' => 'NEFT', 'IMPS' => 'IMPS', 'RTGS' => 'RTGS', 'OTHER' => 'Other'];

        return [
            'slug'      => 'bank-deposits',
            'title'     => 'Bank Deposits',
            'plural'    => 'Deposits',
            'singular'  => 'Deposit',
            'icon'      => 'bi-arrow-down-circle',
            'kpi_class' => 'kpi-green',
            'columns'   => [
                ['key' => 'transaction_date', 'label' => 'Date',         'type' => 'date'],
                ['key' => 'bank_label',       'label' => 'Bank Account', 'type' => 'text'],
                ['key' => 'deposit_type',     'label' => 'Deposit Type', 'type' => 'badge', 'badges' => []],
                ['key' => 'party_name',       'label' => 'From',         'type' => 'text'],
                ['key' => 'reference_no',     'label' => 'Reference No', 'type' => 'text'],
                ['key' => 'amount',           'label' => 'Amount',       'type' => 'money'],
            ],
            'fields'    => [
                ['name' => 'transaction_date', 'label' => 'Date',         'type' => 'date',   'required' => true, 'default' => date('Y-m-d')],
                ['name' => 'bank_account_id',  'label' => 'Bank Account', 'type' => 'bank',   'required' => true],
                ['name' => 'amount',           'label' => 'Amount',       'type' => 'money',  'required' => true],
                ['name' => 'deposit_type',     'label' => 'Deposit Type', 'type' => 'select', 'required' => true, 'options' => $modes, 'default' => 'CASH'],
                ['name' => 'reference_no',     'label' => 'Reference No', 'type' => 'text',   'maxlength' => 100, 'hint' => 'Cheque / UTR / slip number (optional).'],
                ['name' => 'party_name',       'label' => 'From',         'type' => 'text',   'maxlength' => 150, 'placeholder' => 'Received from'],
                ['name' => 'remarks',          'label' => 'Remarks',      'type' => 'textarea', 'wide' => true],
            ],
            'detail'    => [
                ['key' => 'transaction_date', 'label' => 'Date',          'type' => 'date'],
                ['key' => 'bank_label',       'label' => 'Bank Account',  'type' => 'text'],
                ['key' => 'amount',           'label' => 'Amount',        'type' => 'money'],
                ['key' => 'deposit_type',     'label' => 'Deposit Type',  'type' => 'badge', 'badges' => []],
                ['key' => 'party_name',       'label' => 'Received From', 'type' => 'text'],
                ['key' => 'reference_no',     'label' => 'Reference No',  'type' => 'text'],
                ['key' => 'remarks',          'label' => 'Remarks',       'type' => 'text'],
            ],
            'preview'   => ['mode' => 'single', 'account' => 'bank_account_id', 'direction' => 'credit'],
        ];
    }

    protected function referenceType(): string
    {
        return BankTransactionModel::REF_MANUAL_DEPOSIT;
    }

    /** Release 4.9.0CF: the mode the Cash Book keys on (see BankEntryController::cashMode). */
    protected function cashMode(array $input): string
    {
        return strtoupper(trim((string) ($input['deposit_type'] ?? '')));
    }

    protected function extractInput(): array
    {
        return [
            'transaction_date' => trim((string) $this->request->getPost('transaction_date')),
            'bank_account_id'  => (int) $this->request->getPost('bank_account_id'),
            'amount'           => trim((string) $this->request->getPost('amount')),
            'deposit_type'     => strtoupper(trim((string) $this->request->getPost('deposit_type'))),
            'reference_no'     => $this->optional('reference_no'),
            'party_name'       => $this->optional('party_name'),
            'remarks'          => $this->optional('remarks'),
        ];
    }

    /**
     * Phase G: date required, active bank account, amount > 0, a deposit type
     * from the fixed list. Reference No and From are optional but must fit
     * their columns.
     */
    protected function validateInput(array $input): array
    {
        return array_values(array_filter([
            $this->dateError($input['transaction_date']),
            $this->accountError($input['bank_account_id']),
            $this->amountError($input['amount']),
            in_array($input['deposit_type'], self::MODES, true) ? null : 'A valid deposit type is required.',
            $this->lengthError($input['reference_no'], 100, 'Reference No'),
            $this->lengthError($input['party_name'], 150, 'From'),
        ]));
    }

    protected function rowData(array $input): array
    {
        return [
            'bank_account_id'  => $input['bank_account_id'],
            'transaction_date' => $input['transaction_date'],
            'transaction_type' => 'DEPOSIT',
            'amount'           => round((float) $input['amount'], 2),
            'reference_type'   => BankTransactionModel::REF_MANUAL_DEPOSIT,
            'reference_no'     => $input['reference_no'],
            'remarks'          => $input['remarks'],
            'payment_mode'     => $input['deposit_type'],
            'party_name'       => $input['party_name'],
        ];
    }

    protected function presentRow(array $row): array
    {
        return [
            'deposit_type' => $row['payment_mode'],
            'party_name'   => $row['party_name'],
        ];
    }
}
