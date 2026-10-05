<?php

namespace App\Controllers;

use App\Models\BankTransactionModel;

/**
 * Release 4.8.4I (Phase B): manual Bank Withdrawal. One bank_transactions row
 * per entry — transaction_type WITHDRAWAL, reference_type MANUAL_WITHDRAWAL,
 * reference_id NULL. A withdrawal (or an edit that enlarges one) that would
 * take the account below zero is refused with a 422 by
 * BankTransactionModel::assertNotOverdrawn(). Withdrawal Type is stored in
 * payment_mode, "Paid To" in party_name.
 */
class BankWithdrawals extends BankEntryController
{
    protected function cfg(): array
    {
        $modes = ['CASH' => 'Cash', 'CHEQUE' => 'Cheque', 'UPI' => 'UPI', 'NEFT' => 'NEFT', 'IMPS' => 'IMPS', 'RTGS' => 'RTGS', 'OTHER' => 'Other'];

        return [
            'slug'      => 'bank-withdrawals',
            'title'     => 'Bank Withdrawals',
            'plural'    => 'Withdrawals',
            'singular'  => 'Withdrawal',
            'icon'      => 'bi-arrow-up-circle',
            'kpi_class' => 'kpi-red',
            'columns'   => [
                ['key' => 'transaction_date', 'label' => 'Date',            'type' => 'date'],
                ['key' => 'bank_label',       'label' => 'Bank Account',    'type' => 'text'],
                ['key' => 'withdrawal_type',  'label' => 'Withdrawal Type', 'type' => 'badge', 'badges' => []],
                ['key' => 'party_name',       'label' => 'Paid To',         'type' => 'text'],
                ['key' => 'reference_no',     'label' => 'Reference No',    'type' => 'text'],
                ['key' => 'amount',           'label' => 'Amount',          'type' => 'money'],
            ],
            'fields'    => [
                ['name' => 'transaction_date', 'label' => 'Date',            'type' => 'date',   'required' => true, 'default' => date('Y-m-d')],
                ['name' => 'bank_account_id',  'label' => 'Bank Account',    'type' => 'bank',   'required' => true],
                ['name' => 'amount',           'label' => 'Amount',          'type' => 'money',  'required' => true],
                ['name' => 'withdrawal_type',  'label' => 'Withdrawal Type', 'type' => 'select', 'required' => true, 'options' => $modes, 'default' => 'CASH'],
                ['name' => 'reference_no',     'label' => 'Reference No',    'type' => 'text',   'maxlength' => 100, 'hint' => 'Cheque / UTR / slip number (optional).'],
                ['name' => 'party_name',       'label' => 'Paid To',         'type' => 'text',   'required' => true, 'maxlength' => 150],
                ['name' => 'remarks',          'label' => 'Remarks',         'type' => 'textarea', 'wide' => true],
            ],
            'detail'    => [
                ['key' => 'transaction_date', 'label' => 'Date',            'type' => 'date'],
                ['key' => 'bank_label',       'label' => 'Bank Account',    'type' => 'text'],
                ['key' => 'amount',           'label' => 'Amount',          'type' => 'money'],
                ['key' => 'withdrawal_type',  'label' => 'Withdrawal Type', 'type' => 'badge', 'badges' => []],
                ['key' => 'party_name',       'label' => 'Paid To',         'type' => 'text'],
                ['key' => 'reference_no',     'label' => 'Reference No',    'type' => 'text'],
                ['key' => 'remarks',          'label' => 'Remarks',         'type' => 'text'],
            ],
            'preview'   => ['mode' => 'single', 'account' => 'bank_account_id', 'direction' => 'debit'],
        ];
    }

    protected function referenceType(): string
    {
        return BankTransactionModel::REF_MANUAL_WITHDRAWAL;
    }

    /** Release 4.9.0CF: the mode the Cash Book keys on (see BankEntryController::cashMode). */
    protected function cashMode(array $input): string
    {
        return strtoupper(trim((string) ($input['withdrawal_type'] ?? '')));
    }

    protected function extractInput(): array
    {
        return [
            'transaction_date' => trim((string) $this->request->getPost('transaction_date')),
            'bank_account_id'  => (int) $this->request->getPost('bank_account_id'),
            'amount'           => trim((string) $this->request->getPost('amount')),
            'withdrawal_type'  => strtoupper(trim((string) $this->request->getPost('withdrawal_type'))),
            'reference_no'     => $this->optional('reference_no'),
            'party_name'       => $this->optional('party_name'),
            'remarks'          => $this->optional('remarks'),
        ];
    }

    /**
     * Phase G: date required, active bank account, amount > 0, a withdrawal
     * type from the fixed list, Paid To required. The balance rule is the
     * model's (it needs the balance as it stands inside the transaction).
     */
    protected function validateInput(array $input): array
    {
        return array_values(array_filter([
            $this->dateError($input['transaction_date']),
            $this->accountError($input['bank_account_id']),
            $this->amountError($input['amount']),
            in_array($input['withdrawal_type'], self::MODES, true) ? null : 'A valid withdrawal type is required.',
            $input['party_name'] === null ? 'Paid to is required.' : null,
            $this->lengthError($input['reference_no'], 100, 'Reference No'),
            $this->lengthError($input['party_name'], 150, 'Paid to'),
        ]));
    }

    protected function rowData(array $input): array
    {
        return [
            'bank_account_id'  => $input['bank_account_id'],
            'transaction_date' => $input['transaction_date'],
            'transaction_type' => 'WITHDRAWAL',
            'amount'           => round((float) $input['amount'], 2),
            'reference_type'   => BankTransactionModel::REF_MANUAL_WITHDRAWAL,
            'reference_no'     => $input['reference_no'],
            'remarks'          => $input['remarks'],
            'payment_mode'     => $input['withdrawal_type'],
            'party_name'       => $input['party_name'],
        ];
    }

    protected function presentRow(array $row): array
    {
        return [
            'withdrawal_type' => $row['payment_mode'],
            'party_name'      => $row['party_name'],
        ];
    }
}
