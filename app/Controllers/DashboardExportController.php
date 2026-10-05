<?php

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Models\ExpenseCategoryModel;
use App\Models\LoanTypeModel;
use App\Models\ProjectModel;
use App\Models\SupplierModel;
use CodeIgniter\Controller;

/**
 * Release 4.8.7C: Print/Export Center page.
 *
 * This controller renders ONLY the new "Print/Export Center" page — a pure
 * UI hub of links pointing at the PDF/Excel export routes that already exist
 * from 4.8.7A/4.8.7B, plus the on-screen report "View" pages and a handful
 * of GET-filter modals that submit straight to those same existing routes.
 *
 * It performs no report computation, no totals, no PDF/Excel generation. The
 * only queries here are cheap id/name lookups to populate <select> dropdowns
 * (customers, suppliers, loans, expense categories, projects, lenders, bank
 * accounts) — the same trivial read pattern already used by
 * LoanReports::_lenders(), ServiceReports::_customers() and
 * ExpenseReports::index() for their own filter dropdowns.
 */
class DashboardExportController extends Controller
{
    private const EXPENSE_PAYMENT_METHODS = ['CASH', 'BANK', 'CHEQUE', 'UPI', 'OTHER'];
    private const EXPENSE_STATUSES        = ['PAID', 'CANCELLED'];
    private const LOAN_STATUSES           = ['ACTIVE', 'CLOSED'];
    private const SERVICE_RECEIPT_TYPES   = ['INVOICE', 'DIRECT'];
    private const SERVICE_STATUSES        = ['PENDING', 'PARTIAL', 'PAID'];
    private const SERVICE_PAYMENT_MODES   = ['CASH', 'BANK', 'CHEQUE', 'UPI', 'OTHER'];

    /** Print/Export Center: links + filter-modal dropdown data only. */
    public function index()
    {
        $db = \Config\Database::connect();

        $customers = (new CustomerModel())->orderBy('name', 'ASC')->findAll();
        $suppliers = (new SupplierModel())->orderBy('name', 'ASC')->findAll();
        $categories = (new ExpenseCategoryModel())->orderBy('category_name', 'ASC')->findAll();
        $projects   = (new ProjectModel())->orderBy('name', 'ASC')->findAll();

        // Same trivial lookups LoanReports/ServiceReports already run for
        // their own filter dropdowns (distinct lender names, bank list, and
        // an id/loan_no/lender list for the Loan Ledger picker).
        $lenders = array_column(
            $db->table('loans')->distinct()->select('lender_name')->orderBy('lender_name', 'ASC')->get()->getResultArray(),
            'lender_name'
        );
        $loans = $db->table('loans')->select('id, loan_no, lender_name')->orderBy('loan_no', 'ASC')->get()->getResultArray();
        $banks = $db->table('bank_accounts')->select('id, bank_name, account_name, account_number')->orderBy('bank_name', 'ASC')->get()->getResultArray();

        return view('export_center/index', [
            'title'                 => 'Print / Export Center',
            'customers'             => $customers,
            'suppliers'             => $suppliers,
            'categories'            => $categories,
            'projects'              => $projects,
            'lenders'               => $lenders,
            'loans'                 => $loans,
            'banks'                 => $banks,
            'expensePaymentMethods' => self::EXPENSE_PAYMENT_METHODS,
            'expenseStatuses'       => self::EXPENSE_STATUSES,
            'loanTypes'             => LoanTypeModel::codes(),
            'loanStatuses'          => self::LOAN_STATUSES,
            'serviceReceiptTypes'   => self::SERVICE_RECEIPT_TYPES,
            'serviceStatuses'       => self::SERVICE_STATUSES,
            'servicePaymentModes'   => self::SERVICE_PAYMENT_MODES,
        ]);
    }
}
