<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Release 4.8.2A (Phase 1): relationships only — one row per bill a voucher
 * pays against. purchase_id is polymorphic (see the accompanying migration):
 * it resolves against `purchases` when purchase_type = PROJECT, or
 * `general_purchases` when purchase_type = GENERAL. No controller/service
 * logic lives here yet; validation only guards shape (non-negative amounts,
 * paid_amount not exceeding bill_amount), not cross-table outstanding checks.
 */
class SupplierPaymentAllocationModel extends Model
{
    protected $table      = 'supplier_payment_allocations';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'supplier_payment_id',
        'purchase_type',
        'purchase_id',
        'bill_amount',
        'paid_amount',
        'balance_amount',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'supplier_payment_id' => 'required|integer',
        'purchase_type'       => 'required|in_list[PROJECT,GENERAL]',
        'purchase_id'         => 'required|integer',
        'bill_amount'         => 'permit_empty|numeric|greater_than_equal_to[0]',
        'paid_amount'         => 'permit_empty|numeric|greater_than_equal_to[0]',
        'balance_amount'      => 'permit_empty|numeric',
    ];

    /**
     * All allocation rows for one voucher, in insertion order.
     */
    public function forPayment(int $supplierPaymentId): array
    {
        return $this->where('supplier_payment_id', $supplierPaymentId)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * All allocation rows ever recorded against one bill (PROJECT or
     * GENERAL purchase), across every voucher — used to compute how much of
     * that bill has been paid to date.
     */
    public function forPurchase(string $purchaseType, int $purchaseId): array
    {
        return $this->where('purchase_type', $purchaseType)
            ->where('purchase_id', $purchaseId)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Sum of paid_amount already recorded against one bill, across every
     * voucher. Simple SUM — no allocation math beyond that.
     */
    public function totalPaidForPurchase(string $purchaseType, int $purchaseId): float
    {
        $sum = $this->where('purchase_type', $purchaseType)
            ->where('purchase_id', $purchaseId)
            ->selectSum('paid_amount')
            ->first();
        return (float) ($sum['paid_amount'] ?? 0);
    }
}
