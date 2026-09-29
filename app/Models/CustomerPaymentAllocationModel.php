<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Release 4.8.4C: one row per service-receipt invoice a customer payment
 * voucher pays against. Shape validation only; outstanding checks live in
 * the CustomerPayments controller.
 */
class CustomerPaymentAllocationModel extends Model
{
    protected $table      = 'customer_payment_allocations';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'customer_payment_id',
        'service_receipt_id',
        'invoice_amount',
        'paid_amount',
        'balance_amount',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'customer_payment_id' => 'required|integer',
        'service_receipt_id'  => 'required|integer',
        'invoice_amount'      => 'permit_empty|numeric|greater_than_equal_to[0]',
        'paid_amount'         => 'permit_empty|numeric|greater_than_equal_to[0]',
        'balance_amount'      => 'permit_empty|numeric|greater_than_equal_to[0]',
    ];

    /** All allocation rows for one voucher, in insertion order. */
    public function forPayment(int $customerPaymentId): array
    {
        return $this->where('customer_payment_id', $customerPaymentId)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /** All allocation rows ever recorded against one service receipt. */
    public function forReceipt(int $serviceReceiptId): array
    {
        return $this->where('service_receipt_id', $serviceReceiptId)
            ->orderBy('id', 'ASC')
            ->findAll();
    }
}
