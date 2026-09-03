<?php

namespace App\Models;

use CodeIgniter\Model;

class ProjectModel extends Model
{
    protected $table      = 'projects';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'name', 'customer_id', 'status', 'start_date', 'end_date', 'description',
        'total_project_value', 'advance_amount', 'advance_date', 'advance_notes',
        'billing_status',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Release 2.1C (Bug 1): project payment status represents entire project
     * billing, not one invoice — built from getFinancialSummary() (unchanged),
     * not invoice-level sales.status counts (the old rule read PAID as soon as
     * every *existing* invoice was paid, even with most of the contract not
     * yet billed).
     * Returns: 'PENDING' | 'PARTIAL' | 'PAID'
     */
    public function getPaymentStatus(int $projectId): string
    {
        $db        = \Config\Database::connect();
        $saleCount = (int) ($db->query("SELECT COUNT(*) AS c FROM sales WHERE project_id = ?", [$projectId])->getRow()->c ?? 0);

        if ($saleCount === 0) {
            return 'PENDING';
        }

        $fs = $this->getFinancialSummary($projectId);

        if (($fs['remaining_billable_value'] ?? 0) > 0.004) {
            return 'PARTIAL';
        }
        if (($fs['outstanding_collection_balance'] ?? 0) > 0.004) {
            return 'PARTIAL';
        }
        return 'PAID';
    }

    /**
     * Shared project financial summary: value, advance, billing, and collection figures.
     * Remaining Billable Value and Outstanding Collection Balance are independent figures
     * and are intentionally not clamped to zero (a negative value is meaningful).
     */
    public function getFinancialSummary(int $projectId, ?int $excludeSaleId = null): array
    {
        $project = $this->find($projectId);
        if (!$project) {
            return [];
        }

        $db  = \Config\Database::connect();
        $sql = "
            SELECT
                COALESCE(SUM(total_amount),    0) AS total_billed,
                COALESCE(SUM(paid_amount),     0) AS total_paid,
                COALESCE(SUM(advance_applied), 0) AS total_advance_applied,
                COALESCE(SUM(balance_amount),  0) AS total_pending_collection
            FROM sales
            WHERE project_id = ?
        ";
        $params = [$projectId];
        if ($excludeSaleId !== null) {
            $sql       .= " AND id != ?";
            $params[]   = $excludeSaleId;
        }
        $row = $db->query($sql, $params)->getRow();

        $totalProjectValue      = (float) ($project['total_project_value'] ?? 0);
        $advanceAmount          = (float) ($project['advance_amount'] ?? 0);
        $totalBilled            = (float) ($row->total_billed ?? 0);
        $totalPaid              = (float) ($row->total_paid ?? 0);
        $totalAdvanceApplied    = (float) ($row->total_advance_applied ?? 0);
        $totalPendingCollection = (float) ($row->total_pending_collection ?? 0);

        // Bug fix (final): collection pending must equal SUM(sales.balance_amount)
        // directly — the per-invoice ground truth already produced by SaleModel's
        // FIFO allocation (SaleModel.php:73: balance = total_amount -
        // advance_applied - paid_amount). Reading it straight from the column
        // instead of re-deriving it via total_billed - total_paid -
        // total_advance_applied removes any risk of double-subtracting advance
        // at the project level. unusedAdvance is the leftover advance FIFO
        // hasn't consumed yet — a credit sitting on account, reported only when
        // nothing is currently pending on an invoice.
        $collectionPending = max(0, $totalPendingCollection);
        $unusedAdvance     = max(0, $advanceAmount - $totalAdvanceApplied);

        if ($collectionPending > 0.004) {
            $outstandingCollectionBalance = $collectionPending;
        } elseif ($unusedAdvance > 0.004) {
            $outstandingCollectionBalance = -$unusedAdvance;
        } else {
            $outstandingCollectionBalance = 0;
        }

        return [
            'total_project_value'            => $totalProjectValue,
            'advance_amount'                 => $advanceAmount,
            'advance_date'                   => $project['advance_date'] ?? null,
            'advance_notes'                  => $project['advance_notes'] ?? null,
            'total_billed'                   => $totalBilled,
            'total_paid'                     => $totalPaid,
            'total_advance_applied'          => $totalAdvanceApplied,
            'total_pending_collection'       => $totalPendingCollection,
            'unused_advance'                 => $unusedAdvance,
            'remaining_billable_value'       => $totalProjectValue - $advanceAmount - $totalBilled,
            'outstanding_collection_balance' => $outstandingCollectionBalance,
            'billing_progress_percent'       => $totalProjectValue > 0
                ? ($totalBilled / $totalProjectValue) * 100
                : 0,
        ];
    }

    /**
     * Release 2.3A: Project Billing Status — a manual, separate concept from
     * Customer Pending Collection (outstanding_collection_balance). This
     * tracks whether the project is still expected to raise more sales
     * invoices, not whether existing invoices are collected.
     *
     * 'COMPLETED' only ever comes from markBillingComplete() (user action).
     * 'PARTIAL' is derived automatically whenever billing isn't marked
     * complete and there's still contract value left to invoice. Otherwise
     * 'ACTIVE'.
     */
    public function getBillingCompletionStatus(array $project, array $financialSummary): string
    {
        if (($project['billing_status'] ?? 'ACTIVE') === 'COMPLETED') {
            return 'COMPLETED';
        }
        if (($financialSummary['remaining_billable_value'] ?? 0) > 0.004) {
            return 'PARTIAL';
        }
        return 'ACTIVE';
    }

    /**
     * Release 2.3A: user-triggered, one-way billing completion flag. Does not
     * touch total_project_value, advance, FIFO allocation, or any invoice/
     * payment figures — purely a manual marker that no more sales invoices
     * are expected for this project.
     */
    public function markBillingComplete(int $projectId): bool
    {
        return (bool) $this->update($projectId, ['billing_status' => 'COMPLETED']);
    }

    /**
     * Release 1.6B (Approved Design Decision 4): allocation-aware project
     * purchase cost. Only the PROJECT-allocated portion of each purchase line
     * counts toward project cost — General-allocated quantity is warehouse
     * inventory, excluded here. GST follows the same qty split, reusing the
     * existing gst_calculate_line() helper (no duplicate GST logic).
     * purchases.total_amount (the supplier invoice total) is never read here.
     */
    public function getAllocatedPurchaseCost(int $projectId): float
    {
        $costByProject = $this->getAllocatedPurchaseCostByProject($projectId);
        return $costByProject[$projectId] ?? 0.0;
    }

    /**
     * Same allocation-aware cost as getAllocatedPurchaseCost(), computed for
     * every matching project in one query (keyed by project_id) so report
     * breakdowns don't need an N+1 loop. Optional project/date filters mirror
     * the filters already used by Reports::profitLoss().
     */
    public function getAllocatedPurchaseCostByProject(?int $projectId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        helper('gst');
        $db = \Config\Database::connect();

        $sql    = "
            SELECT pu.project_id, pi.project_qty, pi.unit_price, pi.gst_percent, pi.gst_applicable
            FROM purchase_items pi
            INNER JOIN purchases pu ON pi.purchase_id = pu.id
            WHERE pu.project_id IS NOT NULL
        ";
        $params = [];

        if ($projectId !== null) {
            $sql     .= " AND pu.project_id = ?";
            $params[] = $projectId;
        }
        if ($startDate) {
            $sql     .= " AND pu.purchase_date >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $sql     .= " AND pu.purchase_date <= ?";
            $params[] = $endDate;
        }

        $rows = $db->query($sql, $params)->getResultArray();

        $costByProject = [];
        foreach ($rows as $r) {
            if ((float) $r['project_qty'] <= 0) continue;
            $calc = gst_calculate_line($r['project_qty'], $r['unit_price'], $r['gst_percent'], $r['gst_applicable']);
            $pid  = (int) $r['project_id'];
            $costByProject[$pid] = ($costByProject[$pid] ?? 0.0) + $calc['total_with_gst'];
        }

        foreach ($costByProject as $pid => $cost) {
            $costByProject[$pid] = round($cost, 2);
        }

        return $costByProject;
    }

    /**
     * Allocation-aware cost for a single purchase (all its lines' project_qty
     * portions), used by the project timeline to show the project-allocated
     * amount rather than the full supplier invoice for that purchase.
     */
    public function getAllocatedPurchaseCostForPurchase(int $purchaseId): float
    {
        helper('gst');
        $db   = \Config\Database::connect();
        $rows = $db->query("
            SELECT project_qty, unit_price, gst_percent, gst_applicable
            FROM purchase_items WHERE purchase_id = ?
        ", [$purchaseId])->getResultArray();

        $total = 0.0;
        foreach ($rows as $r) {
            if ((float) $r['project_qty'] <= 0) continue;
            $calc   = gst_calculate_line($r['project_qty'], $r['unit_price'], $r['gst_percent'], $r['gst_applicable']);
            $total += $calc['total_with_gst'];
        }

        return round($total, 2);
    }

    /**
     * Merged, chronologically-sorted financial timeline for a project:
     * Advance, Sales Invoices, Invoice Payments, Purchases, Expenses.
     * Running balance is computed only across Billing (+) and Payment (-) rows,
     * per the Outstanding Collection Balance definition — Purchases and
     * Expenses never touch it (they carry running_balance = null).
     */
    public function getTimelineEvents(int $projectId): array
    {
        $project = $this->find($projectId);
        if (!$project) {
            return [];
        }

        $db     = \Config\Database::connect();
        $events = [];

        if ((float) ($project['advance_amount'] ?? 0) > 0) {
            $events[] = [
                'date'        => $project['advance_date'] ?: substr((string) $project['created_at'], 0, 10),
                'type'        => 'Project Advance',
                'reference'   => 'ADV-' . $projectId,
                'description' => $project['advance_notes'] ?: 'Advance received',
                'amount'      => (float) $project['advance_amount'],
                'category'    => 'Payment',
            ];
        }

        $sales = $db->query("
            SELECT id, invoice_no, sale_date, total_amount
            FROM sales WHERE project_id = ?
        ", [$projectId])->getResultArray();
        foreach ($sales as $s) {
            $events[] = [
                'date'        => $s['sale_date'],
                'type'        => 'Sales Invoice',
                'reference'   => $s['invoice_no'] ?: ('SALE-' . $s['id']),
                'description' => 'Invoice raised',
                'amount'      => (float) $s['total_amount'],
                'category'    => 'Billing',
            ];
        }

        $payments = $db->query("
            SELECT py.id, py.payment_date, py.amount, py.method, py.reference, s.invoice_no
            FROM payments py
            INNER JOIN sales s ON py.sale_id = s.id
            WHERE s.project_id = ?
        ", [$projectId])->getResultArray();
        foreach ($payments as $p) {
            $against = $p['invoice_no'] ?: 'invoice';
            $events[] = [
                'date'        => $p['payment_date'],
                'type'        => 'Invoice Payment',
                'reference'   => $p['reference'] ?: ('PMT-' . $p['id']),
                'description' => 'Payment against ' . $against . ($p['method'] ? ' via ' . $p['method'] : ''),
                'amount'      => (float) $p['amount'],
                'category'    => 'Payment',
            ];
        }

        $purchases = $db->query("
            SELECT id, invoice_no, purchase_date, total_amount
            FROM purchases WHERE project_id = ?
        ", [$projectId])->getResultArray();
        foreach ($purchases as $pu) {
            // Release 1.6B (Decision 5): timeline shows the project-allocated
            // cost, not the full supplier invoice — a mixed-allocation purchase
            // only partly belongs to this project's cost.
            $allocatedCost = $this->getAllocatedPurchaseCostForPurchase((int) $pu['id']);
            $invoiceTotal  = (float) $pu['total_amount'];
            $isMixed       = round($allocatedCost, 2) !== round($invoiceTotal, 2);

            $events[] = [
                'date'        => $pu['purchase_date'],
                'type'        => 'Purchase',
                'reference'   => $pu['invoice_no'] ?: ('PUR-' . $pu['id']),
                'description' => $isMixed
                    ? 'Material purchase (Project allocation of ' . number_format($invoiceTotal, 2) . ' total invoice)'
                    : 'Material purchase',
                'amount'      => $allocatedCost,
                'category'    => 'Cost',
            ];
        }

        $expenses = $db->query("
            SELECT id, expense_date, amount, category, description
            FROM expenses WHERE project_id = ?
        ", [$projectId])->getResultArray();
        foreach ($expenses as $e) {
            $events[] = [
                'date'        => $e['expense_date'],
                'type'        => 'Expense',
                'reference'   => 'EXP-' . $e['id'],
                'description' => $e['description'] ?: ($e['category'] ?: 'Expense'),
                'amount'      => (float) $e['amount'],
                'category'    => 'Cost',
            ];
        }

        usort($events, fn ($a, $b) => strcmp((string) $a['date'], (string) $b['date']));

        $balance = 0.0;
        foreach ($events as &$ev) {
            if ($ev['category'] === 'Billing') {
                $balance += $ev['amount'];
                $ev['running_balance'] = $balance;
            } elseif ($ev['category'] === 'Payment') {
                $balance -= $ev['amount'];
                $ev['running_balance'] = $balance;
            } else {
                $ev['running_balance'] = null;
            }
        }
        unset($ev);

        return $events;
    }
}
