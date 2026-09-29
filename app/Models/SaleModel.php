<?php

namespace App\Models;

use CodeIgniter\Model;

class SaleModel extends Model
{
    public const ALLOCATION_TABLE = 'project_advance_allocations';

    protected $table      = 'sales';
    protected $primaryKey = 'id';
    protected $allowedFields = ['project_id', 'customer_id', 'sale_date', 'invoice_no', 'stock_source', 'total_amount', 'advance_applied', 'paid_amount', 'balance_amount', 'status', 'notes'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Refreshes paid_amount/balance_amount/status (Rules 6-7 of Release 1.6.4) for every invoice of
     * the project from what each invoice ALREADY carries as advance_applied.
     *
     * Release 4.8.6B Hotfix: this never applies advance any more. The original Release 1.6.4 FIFO run
     * (which handed the project's advance to the oldest invoices automatically) is gone, with or
     * without the allocation table: advance reaches an invoice only when the accountant chooses it in
     * the "Customer Advance Available" modal (Sales::store/update -> setInvoiceAllocation). All this
     * does is trim an invoice's advance back when its own total or the project's advance_amount was
     * edited below it, then recompute the invoice rows.
     *
     * Call whenever a sale is created/updated/deleted, or the project's advance_amount changes.
     */
    public function recalculateProjectBilling(int $projectId): void
    {
        $db = \Config\Database::connect();

        $project = $db->query("SELECT advance_amount FROM projects WHERE id = ?", [$projectId])->getRowArray();
        $remainingAdvance = (float) ($project['advance_amount'] ?? 0);

        $sales = $db->query("
            SELECT id, total_amount, advance_applied FROM sales WHERE project_id = ? ORDER BY sale_date ASC, id ASC
        ", [$projectId])->getResultArray();

        $this->keepAdvanceWithinLimits($db, $projectId, $remainingAdvance, $sales);
    }

    /** True once migration 2026-09-25-000002 has created project_advance_allocations. */
    public function allocationsEnabled(): bool
    {
        return \Config\Database::connect()->tableExists(self::ALLOCATION_TABLE);
    }

    /**
     * Release 4.8.6A-2: an invoice's advance_applied is what the accountant allocated to it (the sum
     * of its allocation rows), or — for an old invoice that has no rows — the amount the automatic run
     * had already given it. Nothing is ever added here. The only thing this does is cut an invoice's
     * advance back when its own total or the project's advance_amount was edited below it, oldest
     * invoice keeping its share first, then refresh paid/balance/status for every invoice.
     */
    private function keepAdvanceWithinLimits(\CodeIgniter\Database\BaseConnection $db, int $projectId, float $advanceAmount, array $sales): void
    {
        $rowsBySale = [];
        // Before migration 2026-09-25-000002 there is no allocation table: every invoice is then
        // simply "rows-less" and keeps the advance_applied it already has.
        $rows = $this->allocationsEnabled() ? $db->query("
            SELECT id, sales_invoice_id, allocated_amount FROM project_advance_allocations
            WHERE project_id = ? ORDER BY id ASC
        ", [$projectId])->getResultArray() : [];
        foreach ($rows as $r) {
            $rowsBySale[(int) $r['sales_invoice_id']][] = $r;
        }

        $remainingAdvance = round($advanceAmount, 2);
        foreach ($sales as $s) {
            $cap  = round(min($remainingAdvance, (float) $s['total_amount']), 2);
            $kept = 0.0;
            if (isset($rowsBySale[(int) $s['id']])) {
                foreach ($rowsBySale[(int) $s['id']] as $r) {
                    $amount = round((float) $r['allocated_amount'], 2);
                    $keep   = round(min($amount, max(0.0, $cap - $kept)), 2);
                    if ($keep <= 0) {
                        $db->table(self::ALLOCATION_TABLE)->where('id', (int) $r['id'])->delete();
                    } elseif ($keep !== $amount) {
                        $db->table(self::ALLOCATION_TABLE)->where('id', (int) $r['id'])->update(['allocated_amount' => $keep]);
                    }
                    $kept = round($kept + max(0.0, $keep), 2);
                }
            } else {
                $kept = round(min((float) $s['advance_applied'], $cap), 2);
            }

            $remainingAdvance = round($remainingAdvance - $kept, 2);
            $this->_recalculateSaleRow($db, (int) $s['id'], (float) $s['total_amount'], $kept);
        }
    }

    /**
     * Release 4.8.6A-2: Project Advance Balance = advance received less what is applied to invoices
     * (sales.advance_applied). Never negative. Pass $excludeSaleId to get the balance available to
     * that invoice itself (its own current allocation counted as still free).
     */
    public function advanceBalance(int $projectId, ?int $excludeSaleId = null): float
    {
        $db      = \Config\Database::connect();
        $project = $db->query("SELECT advance_amount FROM projects WHERE id = ?", [$projectId])->getRowArray();
        $advance = (float) ($project['advance_amount'] ?? 0);

        $sql    = "SELECT COALESCE(SUM(advance_applied), 0) AS t FROM sales WHERE project_id = ?";
        $params = [$projectId];
        if ($excludeSaleId) {
            $sql     .= " AND id <> ?";
            $params[] = $excludeSaleId;
        }
        $applied = (float) $db->query($sql, $params)->getRow()->t;

        return max(0.0, round($advance - $applied, 2));
    }

    /**
     * Release 4.8.6A-2 Patch: true when an invoice carries an advance_applied that came from the old
     * automatic FIFO run (Release 1.6.4) — an advance amount but no allocation rows. Only meaningful
     * once the allocation table exists.
     */
    public function isLegacyAdvance(int $saleId): bool
    {
        if (! $this->allocationsEnabled()) {
            return false;
        }
        $db   = \Config\Database::connect();
        $sale = $db->query("SELECT advance_applied FROM sales WHERE id = ?", [$saleId])->getRowArray();
        if (! $sale || (float) $sale['advance_applied'] <= 0.004) {
            return false;
        }
        $n = (int) $db->query("SELECT COUNT(*) AS n FROM project_advance_allocations WHERE sales_invoice_id = ?", [$saleId])->getRow()->n;
        return $n === 0;
    }

    /**
     * Release 4.8.6A-2 Patch: the accountant's explicit "Convert to New Allocation" for a legacy
     * invoice. Writes ONE allocation row for the advance_applied it already carries (dated the
     * invoice date), so the amount, the invoice balance and its status stay exactly as they are — the
     * invoice just becomes a normal, editable allocation. Nothing else calls this; opening an edit
     * page never does. Returns false when the invoice is not a legacy one or the row fails.
     */
    public function convertLegacyAdvance(int $saleId, ?int $userId): bool
    {
        if (! $this->isLegacyAdvance($saleId)) {
            return false;
        }
        $db   = \Config\Database::connect();
        $sale = $db->query("
            SELECT s.project_id, s.advance_applied, s.sale_date, COALESCE(s.customer_id, p.customer_id) AS customer_id
            FROM sales s LEFT JOIN projects p ON p.id = s.project_id WHERE s.id = ?
        ", [$saleId])->getRowArray();
        if (! $sale || ! $sale['project_id']) {
            return false;
        }
        return (bool) $db->table(self::ALLOCATION_TABLE)->insert([
            'project_id'       => (int) $sale['project_id'],
            'customer_id'      => $sale['customer_id'] ? (int) $sale['customer_id'] : null,
            'sales_invoice_id' => $saleId,
            'allocated_amount' => round((float) $sale['advance_applied'], 2),
            'allocation_date'  => $sale['sale_date'],
            'created_by'       => $userId,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Release 4.8.6A-2: set one invoice's allocation to $target (0 removes it). History is kept:
     * raising it adds a new row for the difference, lowering it trims the newest rows first. An old
     * (legacy) invoice — advance_applied but no rows — is never given rows here: it can only be
     * released (target 0) or converted on purpose with convertLegacyAdvance(). Recalculate the
     * project afterwards. Matching record only — no bank, payment or voucher row is ever written.
     */
    public function setInvoiceAllocation(int $saleId, int $projectId, ?int $customerId, string $date, float $target, ?int $userId): bool
    {
        $db     = \Config\Database::connect();
        $target = round($target, 2);

        // Release 4.8.6B-1: without the allocation table (migration 2026-09-25-000002 not run yet)
        // no advance can be applied and nothing is written: sales.advance_applied is never set
        // directly. Asking for 0 is a harmless no-op (an old automatic amount stays as it is).
        if (! $this->allocationsEnabled()) {
            return $target <= 0.004;
        }

        $rows   = $db->query("SELECT id, allocated_amount FROM project_advance_allocations WHERE sales_invoice_id = ? ORDER BY id ASC", [$saleId])->getResultArray();
        $sum    = 0.0;
        foreach ($rows as $r) {
            $sum += (float) $r['allocated_amount'];
        }
        $sum = round($sum, 2);

        // A legacy invoice cannot be re-allocated behind the accountant's back: it must be converted first.
        if ($target > 0.004 && $this->isLegacyAdvance($saleId)) {
            return false;
        }

        if ($target > $sum + 0.004) {
            return (bool) $db->table(self::ALLOCATION_TABLE)->insert([
                'project_id' => $projectId, 'customer_id' => $customerId, 'sales_invoice_id' => $saleId,
                'allocated_amount' => round($target - $sum, 2), 'allocation_date' => $date, 'created_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $excess = round($sum - $target, 2);
        foreach (array_reverse($rows) as $r) {
            if ($excess <= 0.004) {
                break;
            }
            $amount = round((float) $r['allocated_amount'], 2);
            if ($amount <= $excess + 0.004) {
                $db->table(self::ALLOCATION_TABLE)->where('id', (int) $r['id'])->delete();
                $excess = round($excess - $amount, 2);
            } else {
                $db->table(self::ALLOCATION_TABLE)->where('id', (int) $r['id'])->update(['allocated_amount' => round($amount - $excess, 2)]);
                $excess = 0.0;
            }
        }
        // An invoice with no rows left must not fall back to an old automatic amount.
        if ($target <= 0.004) {
            $db->table('sales')->where('id', $saleId)->update(['advance_applied' => 0]);
        }
        return true;
    }

    /**
     * Release 1.6.4 (Rule 6): recomputes paid_amount/balance_amount/status
     * for one sale from its current advance_applied (unchanged — payments
     * never touch advance allocation) and its payments table rows. Used
     * after a payment is recorded, edited, or deleted.
     */
    public function recalculatePaymentState(int $saleId): void
    {
        $db   = \Config\Database::connect();
        $sale = $db->query("SELECT total_amount, advance_applied FROM sales WHERE id = ?", [$saleId])->getRowArray();
        if (!$sale) {
            return;
        }
        $this->_recalculateSaleRow($db, $saleId, (float) $sale['total_amount'], (float) $sale['advance_applied']);
    }

    /**
     * Shared math for both recompute paths: paid_amount is derived fresh
     * from SUM(payments.amount) rather than maintained incrementally, so a
     * missed/duplicated update can never permanently skew it (Rule 6).
     */
    private function _recalculateSaleRow(\CodeIgniter\Database\BaseConnection $db, int $saleId, float $totalAmount, float $advanceApplied): void
    {
        $paid = (float) ($db->query("SELECT COALESCE(SUM(amount), 0) AS t FROM payments WHERE sale_id = ?", [$saleId])->getRow()->t);

        $pending = round($totalAmount - $advanceApplied - $paid, 2);
        $balance = max(0.0, $pending);

        if ($pending <= 0.004) {
            $status = 'PAID';
        } elseif ($advanceApplied > 0 || $paid > 0) {
            $status = 'PARTIAL';
        } else {
            $status = 'UNPAID';
        }

        $db->table('sales')->where('id', $saleId)->update([
            'advance_applied' => $advanceApplied,
            'paid_amount'     => $paid,
            'balance_amount'  => $balance,
            'status'          => $status,
        ]);
    }

    /**
     * Pending amount for one invoice after advance allocation, without
     * writing anything — used by Payments::store()/update() to validate a
     * new payment amount against the true current pending balance (Rule 5).
     */
    public function getPendingAmount(int $saleId): float
    {
        $sale = $this->find($saleId);
        return $sale ? (float) $sale['balance_amount'] : 0.0;
    }
}
