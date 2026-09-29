<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Release 4.5 (Phase 2): simple CRUD only, no invoice allocation logic.
 * A Project Cash Receipt is independent of payments/sales — it never
 * writes to sales.paid_amount, sales.balance_amount, or projects.advance_amount.
 */
class ProjectCashReceiptModel extends Model
{
    protected $table      = 'project_cash_receipts';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'project_id', 'customer_id', 'receipt_no', 'amount',
        'receipt_date', 'payment_method', 'receipt_type', 'reference', 'notes',
        'bank_account_id',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Next receipt number in CASH-0001 style. Not transaction-safe against a
     * true race (no DB sequence/lock), same trade-off already accepted by
     * this codebase's other manual reference numbers — acceptable for this
     * app's single-admin usage pattern.
     */
    public function nextReceiptNo(): string
    {
        $last = $this->select('id')->orderBy('id', 'DESC')->first();
        $next = $last ? ((int) $last['id'] + 1) : 1;
        return 'CASH-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Total project cash received for one project, optionally within a date
     * range and/or filtered by receipt_type. Simple SUM — no allocation math.
     */
    public function totalForProject(int $projectId, ?string $startDate = null, ?string $endDate = null, ?string $receiptType = null): float
    {
        $builder = $this->where('project_id', $projectId);
        if ($startDate) {
            $builder->where('receipt_date >=', $startDate);
        }
        if ($endDate) {
            $builder->where('receipt_date <=', $endDate);
        }
        if ($receiptType) {
            $builder->where('receipt_type', $receiptType);
        }
        return (float) ($builder->selectSum('amount')->first()['amount'] ?? 0);
    }
}
