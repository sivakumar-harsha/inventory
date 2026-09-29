<?php

namespace App\Controllers;

use App\Models\BankTransactionModel;

/**
 * Release 4.8.4I (Phase D): manual accounting entries that belong to no
 * supplier, customer, loan or expense (cash deposited, owner capital, bank
 * charges, interest received, ATM withdrawal, miscellaneous). A CREDIT entry is
 * a DEPOSIT row and a DEBIT entry a WITHDRAWAL row, both with reference_type
 * BANK_DAYBOOK and reference_id NULL. The category goes in category, the
 * narration in remarks.
 *
 * Release 4.8.4I Patch: the screen is presented as "Manual Entry" under Bank
 * Accounts (bank-accounts/manual-entry). Only the slug and the labels changed;
 * the class name, the posting logic and reference_type BANK_DAYBOOK did not.
 */
class BankDaybook extends BankEntryController
{
    private const ENTRY_TYPES = ['CREDIT', 'DEBIT'];
    private const CATEGORIES  = ['CASH DEPOSIT', 'CASH WITHDRAWAL', 'BANK CHARGES', 'INTEREST RECEIVED', 'OWNER CAPITAL', 'MISCELLANEOUS'];

    protected function cfg(): array
    {
        $categories = [];
        foreach (self::CATEGORIES as $category) {
            $categories[$category] = ucwords(strtolower($category));
        }

        $entryBadges = ['CREDIT' => 'be-badge-green', 'DEBIT' => 'be-badge-red'];

        return [
            'slug'      => 'bank-accounts/manual-entry',
            'title'     => 'Manual Entry',
            'plural'    => 'Entries',
            'singular'  => 'Manual Entry',
            'icon'      => 'bi-pencil-square',
            'kpi_class' => 'kpi-blue',
            'columns'   => [
                ['key' => 'transaction_date', 'label' => 'Date',         'type' => 'date'],
                ['key' => 'bank_label',       'label' => 'Bank Account', 'type' => 'text'],
                ['key' => 'entry_type',       'label' => 'Type',         'type' => 'badge', 'badges' => $entryBadges],
                ['key' => 'category',         'label' => 'Category',     'type' => 'badge', 'badges' => []],
                ['key' => 'reference_no',     'label' => 'Reference No', 'type' => 'text'],
                ['key' => 'narration',        'label' => 'Narration',    'type' => 'text'],
                ['key' => 'amount',           'label' => 'Amount',       'type' => 'money', 'signed_by' => 'entry_type'],
            ],
            'fields'    => [
                ['name' => 'transaction_date', 'label' => 'Date',         'type' => 'date',   'required' => true, 'default' => date('Y-m-d')],
                ['name' => 'bank_account_id',  'label' => 'Bank Account', 'type' => 'bank',   'required' => true],
                ['name' => 'entry_type',       'label' => 'Entry Type',   'type' => 'select', 'required' => true, 'options' => ['CREDIT' => 'Credit (money in)', 'DEBIT' => 'Debit (money out)'], 'default' => 'CREDIT'],
                ['name' => 'category',         'label' => 'Category',     'type' => 'select', 'required' => true, 'options' => $categories, 'default' => 'MISCELLANEOUS'],
                ['name' => 'amount',           'label' => 'Amount',       'type' => 'money',  'required' => true],
                ['name' => 'reference_no',     'label' => 'Reference No', 'type' => 'text',   'maxlength' => 100],
                ['name' => 'narration',        'label' => 'Narration',    'type' => 'textarea', 'required' => true, 'wide' => true],
            ],
            'detail'    => [
                ['key' => 'transaction_date', 'label' => 'Date',         'type' => 'date'],
                ['key' => 'bank_label',       'label' => 'Bank Account', 'type' => 'text'],
                ['key' => 'entry_type',       'label' => 'Entry Type',   'type' => 'badge', 'badges' => $entryBadges],
                ['key' => 'category',         'label' => 'Category',     'type' => 'badge', 'badges' => []],
                ['key' => 'amount',           'label' => 'Amount',       'type' => 'money'],
                ['key' => 'reference_no',     'label' => 'Reference No', 'type' => 'text'],
                ['key' => 'narration',        'label' => 'Narration',    'type' => 'text'],
            ],
            'preview'   => ['mode' => 'single', 'account' => 'bank_account_id', 'direction_field' => 'entry_type'],
        ];
    }

    protected function referenceType(): string
    {
        return BankTransactionModel::REF_BANK_DAYBOOK;
    }

    protected function extractInput(): array
    {
        return [
            'transaction_date' => trim((string) $this->request->getPost('transaction_date')),
            'bank_account_id'  => (int) $this->request->getPost('bank_account_id'),
            'entry_type'       => strtoupper(trim((string) $this->request->getPost('entry_type'))),
            'category'         => strtoupper(trim((string) $this->request->getPost('category'))),
            'amount'           => trim((string) $this->request->getPost('amount')),
            'reference_no'     => $this->optional('reference_no'),
            'narration'        => $this->optional('narration'),
        ];
    }

    /** Phase G (Manual Entry): narration required, amount > 0, plus the date / account / type / category basics. */
    protected function validateInput(array $input): array
    {
        return array_values(array_filter([
            $this->dateError($input['transaction_date']),
            $this->accountError($input['bank_account_id']),
            in_array($input['entry_type'], self::ENTRY_TYPES, true) ? null : 'A valid entry type (Credit or Debit) is required.',
            in_array($input['category'], self::CATEGORIES, true) ? null : 'A valid category is required.',
            $this->amountError($input['amount']),
            $input['narration'] === null ? 'Narration is required.' : null,
            $this->lengthError($input['reference_no'], 100, 'Reference No'),
        ]));
    }

    protected function rowData(array $input): array
    {
        return [
            'bank_account_id'  => $input['bank_account_id'],
            'transaction_date' => $input['transaction_date'],
            'transaction_type' => $input['entry_type'] === 'CREDIT' ? 'DEPOSIT' : 'WITHDRAWAL',
            'amount'           => round((float) $input['amount'], 2),
            'reference_type'   => BankTransactionModel::REF_BANK_DAYBOOK,
            'reference_no'     => $input['reference_no'],
            'remarks'          => $input['narration'],
            'category'         => $input['category'],
        ];
    }

    protected function presentRow(array $row): array
    {
        return [
            'entry_type' => $row['transaction_type'] === 'DEPOSIT' ? 'CREDIT' : 'DEBIT',
            'category'   => $row['category'],
            'narration'  => $row['remarks'],
        ];
    }

    /** Credits / debits instead of the generic totals. */
    protected function kpis(array $entries): array
    {
        $credits = 0.0;
        $debits  = 0.0;

        foreach ($entries as $entry) {
            $entry['entry_type'] === 'CREDIT' ? $credits += $entry['amount'] : $debits += $entry['amount'];
        }

        return [
            ['label' => 'Entries',       'value' => number_format(count($entries)), 'class' => 'kpi-blue',  'icon' => 'bi-list-check'],
            ['label' => 'Total Credits', 'value' => number_format($credits, 2),     'class' => 'kpi-green', 'icon' => 'bi-arrow-down-circle'],
            ['label' => 'Total Debits',  'value' => number_format($debits, 2),      'class' => 'kpi-red',   'icon' => 'bi-arrow-up-circle'],
        ];
    }
}
