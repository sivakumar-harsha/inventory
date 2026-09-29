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
        // Release 4.8.4J (migration 2026-09-25-000001); written only while a project carries an advance.
        'advance_payment_method', 'advance_bank_account_id',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** Bank reference_type of a project's advance deposit (reference_id = projects.id). Distinct from ProjectCashReceipts' PROJECT_ADVANCE, whose reference_id is a receipt id. */
    public const REF_PROJECT_ADVANCE = 'PROJECT_ADVANCE_DEPOSIT';

    /** Display number of a project, e.g. PRJ-000012 (projects carry no number column of their own). */
    public static function projectNumber(int $projectId): string
    {
        return 'PRJ-' . str_pad((string) $projectId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Date the project's advance was received: Advance Date when given, else
     * the day the project was created (the same fallback the project timeline
     * uses). Shared by the bank deposit and the Customer Ledger row so the two
     * always carry the same date.
     */
    public static function advanceDate(array $project): string
    {
        $date = (string) ($project['advance_date'] ?? '');
        if ($date === '') {
            $date = substr((string) ($project['created_at'] ?? ''), 0, 10);
        }

        return $date !== '' ? $date : date('Y-m-d');
    }

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

        // Release 4.5 (Phase 5): Project Cash Receipt — a separate, independent
        // ledger from both invoice payments (total_paid, above) and
        // projects.advance_amount. No allocation table exists, so
        // total_cash_available is simply the receipt total, not netted
        // against any specific invoice here. total_billed/total_paid/
        // total_advance_applied/outstanding_collection_balance/
        // remaining_billable_value above are all unchanged.
        $cashReceiptModel  = new ProjectCashReceiptModel();
        $totalCashReceived = $cashReceiptModel->totalForProject($projectId);
        $totalInvoicePayments = $totalPaid;
        $totalCashAvailable   = $totalCashReceived;

        // Release 4.6.5 (Direct Project Income): split the same receipt total
        // above by receipt_type — ADVANCE (money held against a future
        // invoice, not yet revenue) vs DIRECT_INCOME (cash that will never be
        // invoiced, recognized as revenue immediately). Both are still part
        // of total_cash_received above; this split only feeds the two new
        // fields below, nothing existing is recalculated from it.
        $totalAdvanceReceipts = $cashReceiptModel->totalForProject($projectId, null, null, 'ADVANCE');
        $totalDirectIncome    = $cashReceiptModel->totalForProject($projectId, null, null, 'DIRECT_INCOME');

        // net_outstanding_collection_balance: presentation-only figure combining
        // outstanding_collection_balance (invoice pending, net of advance) with
        // available project cash. Same sign convention as
        // outstanding_collection_balance: positive = still owed after cash,
        // negative = credit on account (unused advance + unused cash combined).
        $netOutstandingCollectionBalance = round($outstandingCollectionBalance - $totalCashAvailable, 2);

        // Release 4.5.5 (Phase A): single source of truth for Available Project
        // Cash — derived only from net_outstanding_collection_balance (negative
        // = customer has excess cash on account), never recomputed elsewhere as
        // total_cash_received - outstanding_collection_balance.
        $availableProjectCash = max(0, -$netOutstandingCollectionBalance);

        // Release 4.6.5 (Direct Project Income):
        // - remaining_balance_display: the same "net Project Cash Receipts
        //   against Remaining Balance" figure the views already computed
        //   inline (Release 4.5.5) — now split by type and named, so both
        //   Project View and Project Statement read one shared value instead
        //   of duplicating the formula. remaining_billable_value itself
        //   (above) is never touched — it still governs invoice eligibility.
        // - cash_received_combined: the one "Cash Received" figure the whole
        //   app should show — Invoice Payments + Advance Receipts + Direct
        //   Income. total_cash_received (above) keeps its original meaning
        //   (Project Cash Receipts only, both types) for the internal chips
        //   that already depend on it (Payments create/edit summary cards).
        $remainingBalanceDisplay = $totalProjectValue - $advanceAmount - $totalBilled - $totalAdvanceReceipts - $totalDirectIncome;
        $cashReceivedCombined    = $totalPaid + $totalAdvanceReceipts + $totalDirectIncome;

        // Release 4.8.6A-1: Total Customer Paid = everything the customer actually paid us — the
        // project's own Advance plus Customer Payment vouchers (invoice payments, advance
        // receipts, direct income). Internal adjustments and invoice settlement are never money
        // received, so they do not appear here.
        $customerPaidAdvance = $advanceAmount + $totalAdvanceReceipts;
        // Final patch: Direct Income is NOT a customer collection, so it is left out here
        // (it stays available as total_direct_income and inside cash_received_combined).
        $totalCustomerPaid   = $advanceAmount + $totalAdvanceReceipts + $totalPaid;

        // Release 4.8.6A-2 Final Patch: TWO independent customer figures, never one shared balance.
        //   unused_customer_advance = Advance Received - Advance Allocated (sales.advance_applied)
        //   invoice_outstanding     = Invoice Total - Advance Allocated - Customer Payments
        // The Project Statement / Project View show them side by side (Customer Advance Balance and
        // Outstanding Collection), so an advance the accountant has not applied is visible next to the
        // invoice it could settle. Neither uses Remaining Balance; Direct Income and Project Cash
        // Receipts are not part of either. Without the allocation table (old automatic FIFO) the two
        // are never both above zero, so the old single card is reproduced.
        $unusedCustomerAdvance = max(0.0, round($advanceAmount - $totalAdvanceApplied, 2));
        $invoiceOutstanding    = max(0.0, round($totalBilled - $totalAdvanceApplied - $totalPaid, 2));
        $hasInvoices           = $totalBilled > 0.004;

        // Headline status for exports / summaries (the cards themselves use the two values above).
        if ($invoiceOutstanding > 0.004) {
            $customerBalanceStatus = 'Outstanding';
        } elseif ($unusedCustomerAdvance > 0.004) {
            $customerBalanceStatus = 'Advance';
        } else {
            $customerBalanceStatus = 'Settled';
        }

        // Display matrix: Customer Advance Balance whenever unused advance exists; Outstanding
        // Collection when invoices are still owed, "Settled" when invoices exist and nothing is owed;
        // nothing in the second slot when there is no invoice yet (unless the whole page is empty).
        $customerCards = [];
        if ($unusedCustomerAdvance > 0.004) {
            $customerCards[] = ['type' => 'ADVANCE', 'label' => 'Customer Advance Balance', 'amount' => $unusedCustomerAdvance];
        }
        if ($invoiceOutstanding > 0.004) {
            $customerCards[] = ['type' => 'OUTSTANDING', 'label' => 'Outstanding Collection', 'amount' => $invoiceOutstanding];
        } elseif ($hasInvoices || ! $customerCards) {
            $customerCards[] = ['type' => 'SETTLED', 'label' => 'Settled', 'amount' => 0.0];
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
            'total_cash_received'              => $totalCashReceived,
            'total_invoice_payments'           => $totalInvoicePayments,
            'total_cash_available'             => $totalCashAvailable,
            'net_outstanding_collection_balance' => $netOutstandingCollectionBalance,
            'available_project_cash'           => $availableProjectCash,
            'total_advance_receipts'           => $totalAdvanceReceipts,
            'total_direct_income'              => $totalDirectIncome,
            'remaining_balance_display'        => $remainingBalanceDisplay,
            'cash_received_combined'           => $cashReceivedCombined,
            'customer_paid_advance'            => $customerPaidAdvance,
            'total_customer_paid'              => $totalCustomerPaid,
            'unused_customer_advance'          => $unusedCustomerAdvance,
            'invoice_outstanding'              => $invoiceOutstanding,
            'customer_balance_status'          => $customerBalanceStatus,
            'customer_cards'                   => $customerCards,
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

        // Release 4.6.5 (Direct Project Income): Project Cash Received is
        // split into two timeline event types by receipt_type — independent
        // of both Project Advance and Invoice Payment above (its own table,
        // no sale_id).
        // - Advance Receipt: category 'Payment' — same as before this
        //   release, reduces the running collection balance like an
        //   invoice payment does (it's still cash held against a future
        //   invoice).
        // - Direct Project Income: category 'Income' (new) — money that will
        //   never be invoiced must never offset invoice-outstanding running
        //   balance, so it carries running_balance = null, like Purchase/
        //   Expense events do for 'Cost'.
        $cashReceipts = $db->query("
            SELECT id, receipt_no, receipt_date, amount, payment_method, receipt_type, reference
            FROM project_cash_receipts WHERE project_id = ?
        ", [$projectId])->getResultArray();
        foreach ($cashReceipts as $cr) {
            $isDirectIncome = ($cr['receipt_type'] ?? 'ADVANCE') === 'DIRECT_INCOME';
            $events[] = [
                'date'        => $cr['receipt_date'],
                'type'        => $isDirectIncome ? 'Direct Project Income' : 'Advance Receipt',
                'reference'   => $cr['reference'] ?: $cr['receipt_no'],
                'description' => ($isDirectIncome ? 'Direct income received (' : 'Advance received (') . $cr['receipt_no'] . ')' . ($cr['payment_method'] ? ' via ' . $cr['payment_method'] : ''),
                'amount'      => (float) $cr['amount'],
                'category'    => $isDirectIncome ? 'Income' : 'Payment',
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
