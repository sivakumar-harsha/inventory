<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Release 4.8.4C: customer payment voucher header only. Allocation, status
 * and bank rules live in the CustomerPayments controller, matching how
 * SupplierPaymentModel is split from SupplierPayments.
 */
class CustomerPaymentModel extends Model
{
    protected $table      = 'customer_payments';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'payment_no',
        'customer_id',
        'payment_date',
        'payment_method',
        'bank_account_id',
        'reference_no',
        'remarks',
        'total_amount',
        'advance_amount',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'payment_no'     => 'required|max_length[20]|is_unique[customer_payments.payment_no,id,{id}]',
        'customer_id'    => 'required|integer',
        'payment_date'   => 'required|valid_date',
        'payment_method' => 'required|in_list[CASH,BANK,CHEQUE,UPI,OTHER]',
        'total_amount'   => 'permit_empty|numeric|greater_than_equal_to[0]',
        'advance_amount' => 'permit_empty|numeric|greater_than_equal_to[0]',
    ];

    /**
     * Next voucher number in CPV-000001 style. Reads only this table's own id
     * sequence; same accepted trade-off as SupplierPaymentModel::nextPaymentNo()
     * — the unique key on payment_no turns a race into a failed insert, never
     * a duplicate number.
     */
    public function nextPaymentNo(): string
    {
        $last = $this->select('id')->orderBy('id', 'DESC')->first();
        $next = $last ? ((int) $last['id'] + 1) : 1;
        return 'CPV-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
