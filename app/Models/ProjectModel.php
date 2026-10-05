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
     * Remaining Balance (remaining_billable_value = Contract - Advance Received - Total Invoiced) and Outstanding Collection Balance are independent figures
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
        // total_advance_applied/remaining_billable_value above are all
        // unchanged.
        $cashReceiptModel  = new ProjectCashReceiptModel();
        $totalCashReceived = $cashReceiptModel->totalForProject($projectId);
        $totalInvoicePayments = $totalPaid;

        // Release 4.6.5 (Direct Project Income): split the same receipt total
        // above by receipt_type — ADVANCE (money held against a future
        // invoice, not yet revenue) vs DIRECT_INCOME (cash that will never be
        // invoiced, recognized as revenue immediately). Both are still part
        // of total_cash_received above; this split only feeds fields below,
        // nothing existing is recalculated from it.
        $totalAdvanceReceipts = $cashReceiptModel->totalForProject($projectId, null, null, 'ADVANCE');
        $totalDirectIncome    = $cashReceiptModel->totalForProject($projectId, null, null, 'DIRECT_INCOME');

        // Release 4.9.0T / 4.9.0U (correction): CUSTOMER_PROJECT_CASH —
        // genuine customer money received for the project without selecting
        // an invoice. 4.9.0T subtracted it directly from
        // outstanding_collection_balance, but that field also drives
        // getPaymentStatus() (PARTIAL/PAID) and the Dashboard/Projects List
        // "still pending" filters — reducing it let an unallocated cash
        // receipt make a project (and, via customer_balance_status below,
        // an invoice) LOOK settled/paid while the invoice's own
        // sales.balance_amount was still genuinely outstanding. 4.9.0U
        // restores outstanding_collection_balance / invoice_outstanding /
        // total_cash_available to their pre-4.9.0T, invoice-only meaning
        // (Release 4.5.6's "never netted against Project Cash Receipts"
        // rule, now applied uniformly to all three receipt types again) and
        // instead exposes the CUSTOMER_PROJECT_CASH-aware figure as a
        // separate field, project_outstanding_collection (below), which is
        // the only thing the Outstanding Collection card's NUMBER reads —
        // its branch/label decision still reads the untouched
        // invoice_outstanding, so the card can never claim "Settled" while a
        // real invoice balance remains.
        $totalCustomerProjectCash = $cashReceiptModel->totalForProject($projectId, null, null, 'CUSTOMER_PROJECT_CASH');

        $totalCashAvailable = $totalCashReceived;

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
        //
        // Release 4.9.0H (bug fix): advance_amount is NOT subtracted here
        // any more. Once a project's advance is applied to an invoice
        // (sales.advance_applied), that invoice's own total_amount already
        // reflects it — total_billed is the full invoice value, not "invoice
        // value on top of the advance". Subtracting advance_amount again on
        // top of total_billed double-counted the same rupee twice and could
        // report a project as "Over Billed" while it was genuinely still
        // under contract value (e.g. Vinayaga Builders: contract ₹200,000,
        // invoiced ₹151,925 — 76% billed, ₹48,075 still billable — was
        // wrongly showing "Over Billed ₹26,925"). total_advance_receipts and
        // total_direct_income are untouched: both come from the independent
        // Project Cash Receipts ledger (Release 4.5 Phase 5), never applied
        // to a sales.advance_applied, so they never overlap with total_billed
        // and still correctly reduce how much of the contract remains to be
        // invoiced.
        $remainingBalanceDisplay = $totalProjectValue - $totalBilled - $totalAdvanceReceipts - $totalDirectIncome;
        $cashReceivedCombined    = $totalPaid + $totalAdvanceReceipts + $totalDirectIncome;

        // Release 4.8.6A-1: Total Customer Paid = everything the customer actually paid us — the
        // project's own Advance plus Customer Payment vouchers (invoice payments, advance
        // receipts, direct income). Internal adjustments and invoice settlement are never money
        // received, so they do not appear here.
        $customerPaidAdvance = $advanceAmount + $totalAdvanceReceipts;
        // Final patch: Direct Income is NOT a customer collection, so it is left out here
        // (it stays available as total_direct_income and inside cash_received_combined).
        // Release 4.9.0T: CUSTOMER_PROJECT_CASH is genuine customer money
        // received for the project, so it is added to Total Customer Paid
        // the same as an invoice payment or an advance receipt.
        $totalCustomerPaid   = $advanceAmount + $totalAdvanceReceipts + $totalPaid + $totalCustomerProjectCash;

        // Release 4.8.6A-2 Final Patch: TWO independent customer figures, never one shared balance.
        //   unused_customer_advance = Advance Received - Advance Allocated (sales.advance_applied)
        //   invoice_outstanding     = Invoice Total - Advance Allocated - Customer Payments
        // The Project Statement / Project View show them side by side (Customer Advance Balance and
        // Outstanding Collection), so an advance the accountant has not applied is visible next to the
        // invoice it could settle. Neither uses Remaining Balance; Direct Income and Project Cash
        // Receipts are not part of either. Without the allocation table (old automatic FIFO) the two
        // are never both above zero, so the old single card is reproduced.
        $unusedCustomerAdvance = max(0.0, round($advanceAmount - $totalAdvanceApplied, 2));
        // Release 4.9.0U: reverted to the pre-4.9.0T, invoice-only formula —
        // see the CUSTOMER_PROJECT_CASH comment above. This is the TRUE
        // invoice-level outstanding figure: it drives customer_balance_status
        // below (Outstanding/Advance/Settled) and the Outstanding Collection
        // card's branch decision in Project Detail/Statement, so neither may
        // ever report "Settled" from an unallocated cash receipt alone.
        $invoiceOutstanding    = max(0.0, round($totalBilled - $totalAdvanceApplied - $totalPaid, 2));
        $hasInvoices           = $totalBilled > 0.004;

        // Release 4.9.0U: the actual CUSTOMER_PROJECT_CASH-aware number —
        // "how much of the true invoice_outstanding above is left after the
        // customer's unallocated project cash is counted against it".
        // Never negative (a receipt bigger than invoice_outstanding shows as
        // Available Project Cash/credit instead — see
        // net_outstanding_collection_balance above — never as a negative
        // here, and never as "Settled": invoice_outstanding itself, which
        // still governs the branch/status decision, is untouched by
        // CUSTOMER_PROJECT_CASH). This is the only field the Outstanding
        // Collection card's NUMBER should read from now on.
        $projectOutstandingCollection = max(0.0, round($invoiceOutstanding - $totalCustomerProjectCash, 2));

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
            // Release 4.9.0U: card is shown/hidden based on the TRUE invoice
            // outstanding (invoiceOutstanding, above) so it never disappears
            // into "Settled" from unallocated cash alone, but the number
            // itself is the CUSTOMER_PROJECT_CASH-aware figure — same split
            // used by the Project Detail/Statement views.
            $customerCards[] = ['type' => 'OUTSTANDING', 'label' => 'Outstanding Collection', 'amount' => $projectOutstandingCollection];
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
            // Release 4.9.0H (bug fix): same double-count as
            // remaining_balance_display above — advance_amount is already
            // inside total_billed once applied to an invoice, so it is no
            // longer subtracted a second time here. This field drives
            // getPaymentStatus()/getBillingCompletionStatus() (PARTIAL vs
            // PAID/ACTIVE) and the Project list / Dashboard "Remaining
            // Balance" columns; Sales Create/Edit's own invoice-eligibility
            // check does not read this field (it compares total_billed to
            // total_project_value directly), so it is unaffected either way.
            // Release 4.9.0EC: confirmed business rule — the customer advance is
            // PART OF the contract value, so BOTH Advance Received and Total
            // Invoiced consume it: Contract - Advance Received - Total Invoiced.
            // This supersedes the 4.9.0H comment above. It is the ONE
            // authoritative project Remaining Balance: signed (negative = Over
            // Billed, shown as -value by every consumer), clamped only at display.
            // total_billed, advance_amount, advance applied, invoice outstanding
            // and unused advance are all unchanged.
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
            'total_customer_project_cash'      => $totalCustomerProjectCash,
            'project_outstanding_collection'   => $projectOutstandingCollection,
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
     * 'COMPLETED' only ever comes from markBillingComplete() (user action)
     * and keeps priority over every automatic state below — a project the
     * accountant has manually closed for billing stays COMPLETED even if a
     * later invoice pushes total_billed past total_project_value.
     *
     * Release 4.9.0K: the three automatic states below are all derived from
     * remaining_billable_value (= total_project_value - advance_amount - total_billed, 4.9.0EC) alone
     * — Contract vs Invoiced, never customer payments/advances/cash
     * receipts (see ProjectModel::getFinancialSummary(), Release 4.9.0J).
     * 'OVER_BILLED' (total_billed > total_project_value) did not exist
     * before this release; a genuinely over-billed project used to fall
     * through to 'ACTIVE', hiding the very condition Release 4.9.0J's
     * Over Billed display exists to surface. No new stored value: like
     * 'PARTIAL', 'OVER_BILLED' is computed here, never written to
     * projects.billing_status (that column only ever holds ACTIVE/COMPLETED,
     * the manual flag).
     *
     * Deliberately NOT changed: a project that is EXACTLY fully billed
     * (remaining_billable_value == 0) still returns 'ACTIVE', not
     * 'COMPLETED', unless markBillingComplete() was called — same as before
     * this release. 'COMPLETED' has always been this method's one manual,
     * user-driven state (Release 2.3A), read by Sales::index()'s Completed
     * tab and this method's own priority check above; auto-promoting exact
     * 100% billing to 'COMPLETED' would silently reinterpret that flag as
     * automatic for one boundary value while every other project still
     * needs the explicit action, which is a bigger behavior change than
     * this release's stated scope (closing the missing OVER_BILLED gap).
     *
     * Release 4.9.0K (second finding, same audit): a project with zero
     * invoices raised (total_billed <= 0) used to return 'PARTIAL' here —
     * remaining_billable_value equals the full contract value, which is
     * positive, so it fell into the same branch as a project that is
     * genuinely in progress. That misrepresented "billing has not started"
     * as "billing is partway done". getPaymentStatus() above already
     * special-cases this exact situation (sale count 0 -> 'PENDING', its own
     * first check, before any amount is computed); this method now mirrors
     * that convention with its own leading check so a project with no
     * invoices reads 'ACTIVE' (not yet started), consistent with the
     * pre-invoice state everywhere else in the app.
     *
     * Release 4.9.0L (audit, closed — no logic change): resolves whether
     * 'COMPLETED' means "100% invoiced" or "a user manually confirmed
     * billing is done", left open by 4.9.0K. Two pieces of decisive
     * evidence from the existing architecture:
     *   1. markBillingComplete() (Projects::markBillingComplete()) is a
     *      one-way action with no unmark/reset endpoint anywhere in the app.
     *      A state meaning "100% invoiced" would need to be reversible —
     *      a credit note or a corrected invoice could legitimately drop
     *      total_billed back under total_project_value, and an auto-derived
     *      state must follow the numbers back down. A one-way flag cannot
     *      represent that; it can only represent a deliberate, permanent
     *      decision.
     *   2. The "Mark Billing Complete" button (projects/view.php) is shown
     *      regardless of the current billing percentage — it is not gated
     *      behind 100% — and its confirmation text reads "No more sales
     *      invoices should be created for this project", a statement about
     *      FUTURE invoicing intent, not about the CURRENT invoiced amount.
     *      An accountant can legitimately close billing on a project at 60%
     *      (scope was reduced) or leave a 100%-billed project open (more
     *      invoices are still expected).
     * Both facts independently confirm meaning B ("manually confirmed"),
     * not meaning A ("100% invoiced"). Sales::index()'s own Completed tab
     * already reads projects.billing_status (the raw manual column)
     * directly, never a computed percentage, for the same reason.
     * Decision: Option A — no code change. A project at exactly 100%
     * invoiced and not manually completed continues to return 'ACTIVE'.
     * This closes the question opened in 4.9.0K; see Release 4.9.0L's
     * report for the full test matrix proving this against all four states,
     * payment independence and advance independence.
     */
    public function getBillingCompletionStatus(array $project, array $financialSummary): string
    {
        if (($project['billing_status'] ?? 'ACTIVE') === 'COMPLETED') {
            return 'COMPLETED';
        }
        $totalBilled = (float) ($financialSummary['total_billed'] ?? 0);
        if ($totalBilled <= 0.004) {
            return 'ACTIVE';
        }
        $remaining = (float) ($financialSummary['remaining_billable_value'] ?? 0);
        if ($remaining < -0.004) {
            return 'OVER_BILLED';
        }
        if ($remaining > 0.004) {
            return 'PARTIAL';
        }
        // Release 4.9.0DZ: exactly invoiced to contract value (billed > 0,
        // remaining == 0) is FULLY_BILLED — computed from Contract vs
        // Invoiced only, never customer paid / advance. Supersedes the
        // 4.9.0L decision to leave this boundary as 'ACTIVE'; the manual
        // 'COMPLETED' flag above keeps priority and is untouched.
        return 'FULLY_BILLED';
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
                'method'      => (string) ($project['advance_payment_method'] ?? ''),
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
                'description' => 'Payment against ' . $against,
                'method'      => (string) ($p['method'] ?? ''),
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
            $rtype = $cr['receipt_type'] ?? 'ADVANCE';
            // Release 4.9.0T: CUSTOMER_PROJECT_CASH is a genuine customer
            // collection like Invoice Payment/Advance — category 'Payment' so
            // it reduces the running balance the same way — but labeled on
            // its own, never folded into "Advance Receipt" (it is not held
            // against a future invoice).
            if ($rtype === 'DIRECT_INCOME') {
                $type = 'Direct Project Income';
                $desc = 'Direct income received (';
                $category = 'Income';
            } elseif ($rtype === 'CUSTOMER_PROJECT_CASH') {
                $type = 'Unallocated Project Receipt';
                $desc = 'Customer payment received, no invoice selected (';
                $category = 'Payment';
            } else {
                $type = 'Advance Receipt';
                $desc = 'Advance received (';
                $category = 'Payment';
            }
            $events[] = [
                'date'        => $cr['receipt_date'],
                'type'        => $type,
                'reference'   => $cr['reference'] ?: $cr['receipt_no'],
                'description' => $desc . $cr['receipt_no'] . ')',
                'method'      => (string) ($cr['payment_method'] ?? ''),
                'amount'      => (float) $cr['amount'],
                'category'    => $category,
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
            SELECT id, expense_date, amount, category, description, payment_method
            FROM expenses WHERE project_id = ?
        ", [$projectId])->getResultArray();
        foreach ($expenses as $e) {
            $events[] = [
                'date'        => $e['expense_date'],
                'type'        => 'Expense',
                'reference'   => 'EXP-' . $e['id'],
                'description' => $e['description'] ?: ($e['category'] ?: 'Expense'),
                'method'      => (string) ($e['payment_method'] ?? ''),
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
