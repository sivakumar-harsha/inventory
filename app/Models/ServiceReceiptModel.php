<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Release 4.8.4A: Service Received header (Module 4). Header persistence and
 * the receipt number only — totals, status and bank rules live in the
 * ServiceReceipts controller, matching how GeneralPurchaseModel and
 * SupplierPaymentModel are split from their controllers.
 */
class ServiceReceiptModel extends Model
{
    protected $table      = 'service_receipts';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'receipt_no',
        'receipt_type',
        'customer_id',
        'customer_name',
        'customer_address',
        'receipt_date',
        'attended_person',
        'subtotal',
        'gst_total',
        'grand_total',
        'received_amount',
        'outstanding_amount',
        'payment_status',
        'payment_mode',
        'bank_account_id',
        'remarks',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Next receipt number in SRV-000001 style. Reads only this table's own
     * id sequence. Not transaction-safe against a true race (no DB
     * sequence/lock) — same trade-off already accepted by
     * SupplierPaymentModel::nextPaymentNo() and
     * GeneralPurchaseModel::nextPurchaseNo(); the unique key on receipt_no
     * turns a collision into a failed insert rather than a duplicate.
     */
    public function nextReceiptNo(): string
    {
        $last = $this->select('id')->orderBy('id', 'DESC')->first();
        $next = $last ? ((int) $last['id'] + 1) : 1;
        return 'SRV-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Release 4.8.4C: read-only outstanding snapshot for one receipt, used by
     * the customer payment engine (same role GeneralPurchaseModel::outstanding()
     * plays for supplier payments). received_amount already holds everything
     * received so far — the amount taken when the receipt was raised plus every
     * customer payment allocated to it — so outstanding is simply
     * grand_total - received_amount. Returns null when the receipt is missing.
     *
     * $lock = true adds FOR UPDATE, so two vouchers allocating to the same
     * invoice at once serialise instead of both seeing the same balance. Only
     * meaningful (and only used) inside the caller's DB transaction.
     */
    public function outstanding(int $id, bool $lock = false): ?array
    {
        $sql = 'SELECT id, receipt_no, receipt_type, customer_id, receipt_date, grand_total, received_amount, payment_status
                FROM service_receipts WHERE id = ?' . ($lock ? ' FOR UPDATE' : '');
        $row = $this->db->query($sql, [$id])->getRowArray();

        if (! $row) {
            return null;
        }

        $invoice  = round((float) $row['grand_total'], 2);
        $received = round((float) $row['received_amount'], 2);

        return [
            'receipt_id'         => (int) $row['id'],
            'receipt_no'         => $row['receipt_no'],
            'receipt_type'       => $row['receipt_type'],
            'customer_id'        => (int) $row['customer_id'],
            'receipt_date'       => $row['receipt_date'],
            'invoice_amount'     => $invoice,
            'paid_amount'        => $received,
            'outstanding_amount' => round($invoice - $received, 2),
            'payment_status'     => $row['payment_status'],
        ];
    }
}
