<?php

namespace App\Models;

use CodeIgniter\Model;

class GeneralPurchaseModel extends Model
{
    protected $table      = 'general_purchases';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'purchase_no',
        'supplier_id',
        'purchase_date',
        'bill_no',
        'bill_date',
        'subtotal',
        'gst_total',
        'grand_total',
        'advance_paid',
        'outstanding_amount',
        'payment_status',
        'payment_method',
        'bank_account_id',
        'remarks',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Next purchase number in GP-000001 style. Reads only this table's own
     * id sequence, so it is entirely independent of Project Purchase (which
     * has no running number today). Not transaction-safe against a true
     * race (no DB sequence/lock), same trade-off already accepted by this
     * codebase's other manual reference numbers (see
     * ProjectCashReceiptModel::nextReceiptNo()).
     */
    public function nextPurchaseNo(): string
    {
        $last = $this->select('id')->orderBy('id', 'DESC')->first();
        $next = $last ? ((int) $last['id'] + 1) : 1;
        return 'GP-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Sum of paid_amount that Supplier Payment vouchers have allocated to this
     * bill. Excludes the advance paid at purchase time (that lives on the
     * purchase row itself as advance_paid).
     */
    public function allocatedPaid(int $id): float
    {
        $row = $this->db->table('supplier_payment_allocations')
            ->selectSum('paid_amount')
            ->where('purchase_type', 'GENERAL')
            ->where('purchase_id', $id)
            ->get()->getRowArray();

        return round((float) ($row['paid_amount'] ?? 0), 2);
    }

    /**
     * Release 4.8.2B: read-only outstanding snapshot for the Supplier Payment
     * ledger. Deliberately recomputed from supplier_payment_allocations every
     * call rather than trusting the stored outstanding_amount column, so the
     * payment module never drifts from its own source of truth. The
     * SupplierPayments controller (not this model) is what writes the
     * refreshed outstanding_amount/payment_status back onto this row — kept
     * out of this model so it stays a read-only helper.
     *
     * Release 4.8.4H: the advance paid when the purchase was saved is money
     * already paid against this bill, so it counts toward paid_amount and
     * reduces outstanding_amount alongside the voucher allocations. Without
     * it the Supplier Payment screen showed the full grand_total as due and
     * let a supplier be paid the advance a second time.
     */
    public function outstanding(int $id): ?array
    {
        $purchase = $this->find($id);
        if (! $purchase) {
            return null;
        }

        $paid       = $this->allocatedPaid($id) + (float) $purchase['advance_paid'];
        $billAmount = (float) $purchase['grand_total'];

        return [
            'bill_amount'        => $billAmount,
            'paid_amount'        => round($paid, 2),
            'outstanding_amount' => round($billAmount - $paid, 2),
        ];
    }
}
