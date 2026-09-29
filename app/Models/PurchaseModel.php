<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseModel extends Model
{
    protected $table      = 'purchases';
    protected $primaryKey = 'id';
    protected $allowedFields = [
		'supplier_id',
		'project_id',
		'purchase_date',
		'invoice_no',
		'total_amount',
		'notes'
	];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Release 4.8.2B: read-only outstanding snapshot for the Supplier Payment
     * ledger. `purchases` has no paid_amount/outstanding_amount/payment_status
     * columns of its own (only total_amount) — nothing is ever written back
     * here. "Paid" is derived purely from supplier_payment_allocations, so
     * this table stays untouched by the payment module, exactly as the
     * Purchases UI/schema already is.
     */
    public function outstanding(int $id): ?array
    {
        $purchase = $this->find($id);
        if (! $purchase) {
            return null;
        }

        $paid = (float) ($this->db->table('supplier_payment_allocations')
            ->selectSum('paid_amount')
            ->where('purchase_type', 'PROJECT')
            ->where('purchase_id', $id)
            ->get()->getRowArray()['paid_amount'] ?? 0);

        $billAmount = (float) $purchase['total_amount'];

        return [
            'bill_amount'        => $billAmount,
            'paid_amount'        => round($paid, 2),
            'outstanding_amount' => round($billAmount - $paid, 2),
        ];
    }
}
