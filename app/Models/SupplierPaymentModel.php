<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Release 4.8.2A (Phase 1): voucher header only — no allocation logic here.
 * Validation is architecture-level guarding (duplicate number, negative
 * amount); it does not check paid_amount vs. a bill's outstanding balance,
 * since that comparison is per-allocation and belongs to
 * SupplierPaymentAllocationModel / a future service layer, not the header.
 */
class SupplierPaymentModel extends Model
{
    protected $table      = 'supplier_payments';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'payment_no',
        'supplier_id',
        'payment_date',
        'payment_method',
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
        'payment_no'     => 'required|max_length[20]|is_unique[supplier_payments.payment_no,id,{id}]',
        'supplier_id'    => 'required|integer',
        'payment_date'   => 'required|valid_date',
        'total_amount'   => 'permit_empty|numeric|greater_than_equal_to[0]',
        'advance_amount' => 'permit_empty|numeric|greater_than_equal_to[0]',
    ];

    /**
     * Next payment number in SPV-000001 style. Reads only this table's own
     * id sequence. Not transaction-safe against a true race (no DB
     * sequence/lock), same trade-off already accepted by this codebase's
     * other manual reference numbers (see GeneralPurchaseModel::nextPurchaseNo(),
     * ProjectCashReceiptModel::nextReceiptNo()).
     */
    public function nextPaymentNo(): string
    {
        $last = $this->select('id')->orderBy('id', 'DESC')->first();
        $next = $last ? ((int) $last['id'] + 1) : 1;
        return 'SPV-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /** payment_method stored on a voucher that settles bills purely from advance. */
    public const ADVANCE_ADJUSTMENT_METHOD = 'Advance Adjustment';

    /**
     * Release 4.8.6D: supplier advance settlement without any new table/column.
     *
     * A voucher's money out is total_amount = (allocations - advance used) +
     * advance_amount, i.e. every bill allocation is the FULL settlement of that
     * bill, and the part of it funded by a previously paid supplier advance is
     * whatever the cash/bank total does not cover. So, per voucher:
     *
     *     advance used = SUM(allocation paid_amount) - (total_amount - advance_amount)
     *
     * Vouchers saved before this release always satisfied
     * total = allocations + advance (within the old 0.01 tolerance), so they
     * derive to exactly 0 and are unaffected.
     */
    public static function advanceUsedOf(float $total, float $advance, float $allocationSum): float
    {
        $used = round($allocationSum - ($total - $advance), 2);

        return $used > 0.01 ? $used : 0.0;
    }

    /**
     * One row per voucher of a supplier (optionally excluding one voucher) with
     * its allocation sum and derived advance used.
     */
    public function voucherAdvanceRows(?int $supplierId = null, ?int $excludeId = null): array
    {
        // Release 4.8.6F-2: the supplier advance pool holds ONLY standalone
        // advance vouchers paid from Supplier Payments. A General Purchase
        // advance (GPA-) belongs to its own bill — that bill's outstanding is
        // already grand_total - advance_paid - allocations — so counting it here
        // as well would apply the same money twice and let a bill show PAID
        // while the supplier is still owed. See gpAdvanceNo().
        $sql    = 'SELECT sp.id, sp.supplier_id, sp.total_amount, sp.advance_amount,
                          COALESCE(SUM(spa.paid_amount), 0) AS allocation_sum
                   FROM supplier_payments sp
                   LEFT JOIN supplier_payment_allocations spa ON spa.supplier_payment_id = sp.id
                   WHERE sp.payment_no NOT LIKE ' . $this->db->escape(self::GP_ADVANCE_PREFIX . '%');
        $params = [];
        if ($supplierId !== null) {
            $sql     .= ' AND sp.supplier_id = ?';
            $params[] = $supplierId;
        }
        if ($excludeId !== null) {
            $sql     .= ' AND sp.id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' GROUP BY sp.id, sp.supplier_id, sp.total_amount, sp.advance_amount';

        $rows = $this->db->query($sql, $params)->getResultArray();
        foreach ($rows as &$row) {
            $row['advance_used'] = self::advanceUsedOf((float) $row['total_amount'], (float) $row['advance_amount'], (float) $row['allocation_sum']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Supplier advance pool: advance paid on vouchers minus advance already
     * applied to bills. $excludeId leaves one voucher out entirely (used while
     * editing it, so its own previous application is released and its own new
     * advance can never fund itself).
     */
    public function advanceSummary(int $supplierId, ?int $excludeId = null): array
    {
        $paid = 0.0;
        $used = 0.0;
        foreach ($this->voucherAdvanceRows($supplierId, $excludeId) as $row) {
            $paid += (float) $row['advance_amount'];
            $used += (float) $row['advance_used'];
        }

        return [
            'advance_paid'  => round($paid, 2),
            'advance_used'  => round($used, 2),
            'available'     => round($paid - $used, 2),
        ];
    }

    /**
     * Release 4.8.6E: the supplier's advance position immediately after one
     * voucher (that voucher and every earlier one), for the payment view page.
     */
    public function advanceSummaryThrough(int $supplierId, int $voucherId): array
    {
        $paid = 0.0;
        $used = 0.0;
        foreach ($this->voucherAdvanceRows($supplierId) as $row) {
            if ((int) $row['id'] > $voucherId) {
                continue;
            }
            $paid += (float) $row['advance_amount'];
            $used += (float) $row['advance_used'];
        }

        return [
            'advance_paid' => round($paid, 2),
            'advance_used' => round($used, 2),
            'available'    => round($paid - $used, 2),
        ];
    }

    /** Advance already applied to bills, per supplier id (for the ledger list). */
    public function advanceUsedBySupplier(): array
    {
        $out = [];
        foreach ($this->voucherAdvanceRows() as $row) {
            $sid       = (int) $row['supplier_id'];
            $out[$sid] = ($out[$sid] ?? 0.0) + (float) $row['advance_used'];
        }

        return $out;
    }

    // =========================================================
    // GENERAL PURCHASE ADVANCE VOUCHER (Release 4.8.6F-1)
    //
    // An Advance Paid entered on a General Purchase is a real payment to the
    // supplier, so it gets its own voucher in this table and appears in the
    // Supplier Payments history. The voucher is OWNED by its purchase: it is
    // created, updated and removed only through syncForGeneralPurchase(), and
    // its number is derived from the purchase id, so the UNIQUE index on
    // payment_no makes a duplicate advance voucher structurally impossible.
    //
    // It deliberately posts no bank transaction of its own — GeneralPurchases
    // already posts exactly one SUPPLIER_ADVANCE withdrawal for the same money
    // — and it carries no allocation rows of its own.
    //
    // Release 4.8.6F-2: it is history only. The advance is already consumed by
    // its own bill (the purchase row's advance_paid reduces that bill's
    // outstanding), so voucherAdvanceRows() skips it and it is never offered as
    // supplier advance for settling bills.
    // =========================================================

    /** Voucher numbers in this namespace belong to a General Purchase advance. */
    public const GP_ADVANCE_PREFIX = 'GPA-';

    /** The one voucher number a given General Purchase may ever own. */
    public static function gpAdvanceNo(int $generalPurchaseId): string
    {
        return self::GP_ADVANCE_PREFIX . str_pad((string) $generalPurchaseId, 6, '0', STR_PAD_LEFT);
    }

    /** True when a voucher is owned by a General Purchase (so it is not editable on its own). */
    public static function isGpAdvance(?string $paymentNo): bool
    {
        return strpos((string) $paymentNo, self::GP_ADVANCE_PREFIX) === 0;
    }

    /** The General Purchase id a GPA-000003 voucher belongs to (0 when it is an ordinary voucher). */
    public static function gpAdvanceSourceId(?string $paymentNo): int
    {
        return self::isGpAdvance($paymentNo)
            ? (int) substr((string) $paymentNo, strlen(self::GP_ADVANCE_PREFIX))
            : 0;
    }

    /** The advance voucher one General Purchase owns, if it has one. */
    public function findGpAdvance(int $generalPurchaseId): ?array
    {
        return $this->where('payment_no', self::gpAdvanceNo($generalPurchaseId))->first();
    }

    /**
     * Brings one General Purchase's advance voucher in line with the purchase.
     *
     * Advance > 0 creates the voucher, or updates the existing one in place
     * (never a second one); advance <= 0 removes it. Called from
     * GeneralPurchases::store()/update()/delete() inside that method's own DB
     * transaction, so the voucher can never survive a rolled-back purchase.
     *
     * $purchase is the saved purchase row's fields: supplier_id, purchase_no,
     * purchase_date, advance_paid, payment_method.
     *
     * Returns what happened so the caller can audit it once the surrounding
     * transaction has committed: ['action' => 'created'|'updated'|'removed',
     * 'id', 'payment_no', 'old', 'new'], or null when nothing changed (a repeat
     * save of an untouched advance must not add another audit entry).
     */
    public function syncForGeneralPurchase(int $generalPurchaseId, array $purchase): ?array
    {
        $advance = round((float) ($purchase['advance_paid'] ?? 0), 2);
        $existing = $this->findGpAdvance($generalPurchaseId);

        if ($advance <= 0.004) {
            if (! $existing) {
                return null;
            }
            $this->delete((int) $existing['id']);

            return ['action' => 'removed', 'id' => (int) $existing['id'], 'payment_no' => $existing['payment_no'], 'old' => $existing, 'new' => null];
        }

        $fields = [
            'supplier_id'    => (int) $purchase['supplier_id'],
            'payment_date'   => $purchase['purchase_date'],
            'payment_method' => $purchase['payment_method'] ?: null,
            'reference_no'   => $purchase['purchase_no'],
            'remarks'        => 'Advance paid while creating General Purchase ' . $purchase['purchase_no'],
            // The advance is both the money that left (total) and the reason it
            // left (advance), which is what makes this an ADVANCE PAYMENT
            // voucher with no bill settlement of its own.
            'total_amount'   => $advance,
            'advance_amount' => $advance,
        ];

        if ($existing) {
            $changed = false;
            foreach ($fields as $key => $value) {
                $was = $existing[$key];
                $differs = in_array($key, ['total_amount', 'advance_amount'], true)
                    ? abs((float) $was - (float) $value) > 0.004
                    : (string) $was !== (string) $value;
                if ($differs) {
                    $changed = true;
                    break;
                }
            }
            if (! $changed) {
                return null;
            }

            // payment_no is deliberately left out: it is derived from the
            // purchase id and never changes, so the update can never trip the
            // unique-number rule.
            $this->update((int) $existing['id'], $fields);

            return ['action' => 'updated', 'id' => (int) $existing['id'], 'payment_no' => $existing['payment_no'], 'old' => $existing, 'new' => $fields];
        }

        $paymentNo = self::gpAdvanceNo($generalPurchaseId);
        $this->insert($fields + [
            'payment_no' => $paymentNo,
            'created_by' => session()->get('user_id'),
        ]);

        return ['action' => 'created', 'id' => (int) $this->getInsertID(), 'payment_no' => $paymentNo, 'old' => null, 'new' => $fields];
    }

    /** Removes the advance voucher a General Purchase owns; returns it (for the audit entry), or null when it had none. */
    public function removeForGeneralPurchase(int $generalPurchaseId): ?array
    {
        $existing = $this->findGpAdvance($generalPurchaseId);
        if ($existing) {
            $this->delete((int) $existing['id']);
        }

        return $existing;
    }
}
