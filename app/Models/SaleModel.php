<?php

namespace App\Models;

use CodeIgniter\Model;

class SaleModel extends Model
{
    protected $table      = 'sales';
    protected $primaryKey = 'id';
    protected $allowedFields = ['project_id', 'customer_id', 'sale_date', 'invoice_no', 'stock_source', 'total_amount', 'advance_applied', 'paid_amount', 'balance_amount', 'status', 'notes'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Release 1.6.4 (Rules 1-3): re-runs the project's advance FIFO
     * allocation across every one of its invoices, oldest sale_date first,
     * then refreshes paid_amount/balance_amount/status for each (Rules 6-7).
     *
     * Advance applied to an invoice is capped at that invoice's total_amount
     * and computed purely from (advance_amount, ordered invoice totals) —
     * never from what's already been paid — so it is a pure, idempotent
     * function of current state and can be safely re-run any number of
     * times without ever "reapplying" or "removing" advance (Rule 2).
     *
     * Call whenever a sale is created/updated/deleted, or the project's
     * advance_amount changes.
     */
    public function recalculateProjectBilling(int $projectId): void
    {
        $db = \Config\Database::connect();

        $project = $db->query("SELECT advance_amount FROM projects WHERE id = ?", [$projectId])->getRowArray();
        $remainingAdvance = (float) ($project['advance_amount'] ?? 0);

        $sales = $db->query("
            SELECT id, total_amount FROM sales WHERE project_id = ? ORDER BY sale_date ASC, id ASC
        ", [$projectId])->getResultArray();

        foreach ($sales as $s) {
            $applied = round(min($remainingAdvance, (float) $s['total_amount']), 2);
            $remainingAdvance = round($remainingAdvance - $applied, 2);
            $this->_recalculateSaleRow($db, (int) $s['id'], (float) $s['total_amount'], $applied);
        }
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
