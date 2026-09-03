<?php

namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\SupplierModel;
use App\Models\CustomerModel;
use App\Models\ProjectModel;
use App\Models\PurchaseModel;
use App\Models\SaleModel;
use App\Models\PaymentModel;
use App\Models\ExpenseModel;
use CodeIgniter\Controller;

class Dashboard extends Controller
{
    public function index()
    {
        $db = \Config\Database::connect();

        $data['total_products']   = (new ProductModel())->countAll();
        $data['total_suppliers']  = (new SupplierModel())->countAll();
        $data['total_customers']  = (new CustomerModel())->countAll();

        // Release 4.3.1 (Phase C): full project status breakdown, using the
        // same 'status' enum (ACTIVE/COMPLETED/ON_HOLD) already on projects.
        $projectModel             = new ProjectModel();
        $data['active_projects']    = $projectModel->where('status', 'ACTIVE')->countAllResults();
        $data['completed_projects'] = $projectModel->where('status', 'COMPLETED')->countAllResults();
        $data['onhold_projects']    = $projectModel->where('status', 'ON_HOLD')->countAllResults();

        $data['total_sales']      = $db->query("SELECT COALESCE(SUM(total_amount),0) AS t FROM sales")->getRow()->t;
        $data['total_purchases']  = $db->query("SELECT COALESCE(SUM(total_amount),0) AS t FROM purchases")->getRow()->t;
        $data['total_expenses']   = $db->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses")->getRow()->t;
        $data['total_payments']   = $db->query("SELECT COALESCE(SUM(amount),0) AS t FROM payments")->getRow()->t;
        $data['total_outstanding']= $db->query("SELECT COALESCE(SUM(balance_amount),0) AS t FROM sales")->getRow()->t;

        // Release 4.3.2: overall Net Profit KPI for the redesigned dashboard,
        // derived from the totals already fetched above (no new query).
        $data['total_net_profit'] = $data['total_sales'] - $data['total_purchases'] - $data['total_expenses'];

        // Release 4.3.1 (Bug 1 fix): "This month" must be measured against the
        // business transaction date (sale_date / purchase_date / expense_date),
        // exactly like Reports::profitLoss() does — not against created_at,
        // which only reflects when a row was entered/migrated into the system.
        $monthStart = date('Y-m-01');
        $monthEnd   = date('Y-m-t');

        $data['month_sales'] = $db->query("
            SELECT COALESCE(SUM(total_amount),0) AS t
            FROM sales
            WHERE sale_date >= ? AND sale_date <= ?
        ", [$monthStart, $monthEnd])->getRow()->t;

        $data['month_purchases'] = $db->query("
            SELECT COALESCE(SUM(total_amount),0) AS t
            FROM purchases
            WHERE purchase_date >= ? AND purchase_date <= ?
        ", [$monthStart, $monthEnd])->getRow()->t;

        $data['month_expenses'] = $db->query("
            SELECT COALESCE(SUM(amount),0) AS t
            FROM expenses
            WHERE expense_date >= ? AND expense_date <= ?
        ", [$monthStart, $monthEnd])->getRow()->t;

        // Release 4.3.1 (Phase B): "This Month Net Profit" — same allocation-
        // aware COGS formula Reports::profitLoss() uses (revenue - allocated
        // purchase cost - expenses), reusing ProjectModel's existing method
        // rather than re-deriving the GST-aware allocation logic here.
        $monthCogs = array_sum($projectModel->getAllocatedPurchaseCostByProject(null, $monthStart, $monthEnd));
        $data['month_cogs']       = $monthCogs;
        $data['month_net_profit'] = $data['month_sales'] - $monthCogs - $data['month_expenses'];

        $data['recent_sales'] = $db->query("
            SELECT s.*, p.name AS project_name, c.name AS customer_name
            FROM sales s
            LEFT JOIN projects p ON s.project_id = p.id
            LEFT JOIN customers c ON s.customer_id = c.id
            ORDER BY s.created_at DESC LIMIT 5
        ")->getResultArray();

        $data['recent_purchases'] = $db->query("
            SELECT pu.*, s.name AS supplier_name, p.name AS project_name
            FROM purchases pu
            LEFT JOIN suppliers s ON pu.supplier_id = s.id
            LEFT JOIN projects p ON pu.project_id = p.id
            ORDER BY pu.created_at DESC LIMIT 5
        ")->getResultArray();

        // Release 4.3.1 (Phase D): Recent Payments / Recent Expenses activity,
        // for parity with the existing Recent Sales / Recent Purchases panels.
        $data['recent_payments'] = $db->query("
            SELECT pay.*, s.invoice_no AS invoice_no, c.name AS customer_name
            FROM payments pay
            LEFT JOIN sales s ON pay.sale_id = s.id
            LEFT JOIN customers c ON s.customer_id = c.id
            ORDER BY pay.created_at DESC LIMIT 5
        ")->getResultArray();

        $data['recent_expenses'] = $db->query("
            SELECT e.*, p.name AS project_name
            FROM expenses e
            LEFT JOIN projects p ON e.project_id = p.id
            ORDER BY e.created_at DESC LIMIT 5
        ")->getResultArray();

        // Release 4.3.1 (Phase E): Top Pending Projects — reuses
        // ProjectModel::getFinancialSummary() (the same source Project
        // Statement uses) rather than re-deriving outstanding balances here.
        // Project count is small (single digits to low tens), so a per-
        // project call is the correct trade-off against duplicating the
        // FIFO-aware outstanding-collection formula in raw SQL.
        $topPending = [];
        foreach ($projectModel->orderBy('name', 'ASC')->findAll() as $project) {
            $summary = $projectModel->getFinancialSummary($project['id']);
            if (($summary['outstanding_collection_balance'] ?? 0) > 0.004) {
                $topPending[] = [
                    'project_name'            => $project['name'],
                    'remaining_balance'       => $summary['remaining_billable_value'],
                    'outstanding_collection'  => $summary['outstanding_collection_balance'],
                ];
            }
        }
        usort($topPending, fn($a, $b) => $b['outstanding_collection'] <=> $a['outstanding_collection']);
        $data['top_pending_projects'] = array_slice($topPending, 0, 5);

        return view('dashboard/index', $data);
    }
}
