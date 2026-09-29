<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Release 4.8.3A (Phase 1): bank account master. current_balance is
 * maintained by BankAccounts::_updateBankBalance() as transactions are
 * posted — this model does not recompute it, it only stores it.
 */
class BankAccountModel extends Model
{
    protected $table      = 'bank_accounts';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'bank_name',
        'account_name',
        'account_number',
        'ifsc_code',
        'branch_name',
        'opening_balance',
        'current_balance',
        'is_active',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'bank_name'       => 'required|max_length[150]',
        'account_name'    => 'required|max_length[150]',
        'account_number'  => 'required|max_length[50]',
        'ifsc_code'       => 'permit_empty|max_length[20]',
        'branch_name'     => 'permit_empty|max_length[150]',
        'opening_balance' => 'permit_empty|numeric|greater_than_equal_to[0]',
    ];

    public function activeAccounts(): array
    {
        return $this->where('is_active', 1)->orderBy('bank_name', 'ASC')->findAll();
    }
}
