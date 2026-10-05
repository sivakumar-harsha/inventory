<?php

namespace App\Controllers;

use App\Libraries\CashOpeningGuard;
use App\Libraries\ExcelReport;
use App\Libraries\PdfReport;
use App\Models\BankAccountModel;
use App\Models\BankTransactionModel;
use App\Models\ExpenseCategoryModel;
use App\Models\ExpenseModel;
use App\Models\ProjectModel;
use App\Models\UserModel;
use CodeIgniter\Controller;

/**
 * Release 4.8.6A: Expense Management Foundation. Backend endpoints return
 * JSON, matching BankAccounts' store()/update()/delete() shape
 * ({status, message|errors[, id]}) and its transStart/transComplete
 * pattern.
 *
 * Release 4.8.6B: the four GET endpoints now also serve the HTML page shell
 * for plain browser navigation — each page's own JS then calls the same URL
 * via AJAX to fetch its JSON data, so the JSON contract below (and every
 * validation/business rule) is completely unchanged for AJAX callers.
 *
 * Release 4.8.6C: BANK/CHEQUE/UPI expenses now post a WITHDRAWAL to
 * bank_transactions (reference_type=EXPENSE), following the exact same
 * reverse-then-repost pattern as SupplierPayments/Loans: store() posts once;
 * update() always reverses the prior posting first (a no-op if none exists)
 * then reposts using the new values only if the expense remains PAID;
 * delete() reverses before deleting the row. Cancelling an expense (via
 * update()'s status field) reverses the withdrawal without posting a new
 * one, mirroring what a delete would do to the bank side while leaving the
 * expense row itself intact (blocked edits/deletes for CANCELLED are
 * unchanged from 4.8.6A). No migration was needed — expenses.bank_account_id
 * already existed; the link to bank_transactions is a lookup by
 * reference_type+reference_id, the same no-FK pattern every other module
 * uses.
 */
class Expenses extends Controller
{
    private const REFERENCE_TYPE = 'EXPENSE';

    public function index()
    {
        if (! $this->request->isAJAX()) {
            return view('expenses/index');
        }

        $model = new ExpenseModel();
        $db    = \Config\Database::connect();

        $expenses = $db->query("
            SELECT e.*, c.category_name, p.name AS project_name,
                   b.bank_name, b.account_name
            FROM expenses e
            LEFT JOIN expense_categories c ON c.id = e.category_id
            LEFT JOIN projects p ON p.id = e.project_id
            LEFT JOIN bank_accounts b ON b.id = e.bank_account_id
            ORDER BY e.expense_date DESC, e.id DESC
        ")->getResultArray();

        $paidAmount = 0.0;
        foreach ($expenses as $row) {
            if ($row['status'] === 'PAID') {
                $paidAmount += (float) $row['amount'];
            }
        }

        return $this->response->setJSON([
            'status' => true,
            'totals' => [
                'count'  => count($expenses),
                'amount' => round($paidAmount, 2),
            ],
            'today'                   => $model->todayExpenses(),
            'month'                   => $model->monthlyExpenses(),
            'category_summary'       => $model->categoryTotals(),
            // Additive for the 4.8.6B "Active Categories" KPI card — read-only count, no new query shape.
            'active_categories_count' => count((new ExpenseCategoryModel())->activeCategories()),
            'expenses'                => $expenses,
        ]);
    }

    public function create()
    {
        if (! $this->request->isAJAX()) {
            return view('expenses/create');
        }

        $model = new ExpenseModel();

        return $this->response->setJSON([
            'status'          => true,
            'next_expense_no' => $model->nextExpenseNo(),
            'categories'      => (new ExpenseCategoryModel())->activeCategories(),
            'bank_accounts'   => (new BankAccountModel())->activeAccounts(),
            'projects'        => (new ProjectModel())->orderBy('name', 'ASC')->findAll(),
        ]);
    }

    public function store()
    {
        $input  = $this->_extractExpenseInput();
        $errors = $this->_validateExpenseInput($input);

        if ($errors) {
            return $this->response->setJSON(['status' => false, 'errors' => $errors])->setStatusCode(422);
        }

        // Release 4.9.0CF: a CASH expense dated before the Cash Opening Date is warned about, never blocked.
        if ($warn = CashOpeningGuard::gate($this->request, 'expense', 0, $input['payment_method'], $input['expense_date'], true)) {
            return $warn;
        }

        $model = new ExpenseModel();
        $db    = \Config\Database::connect();
        $db->transStart();

        $expenseId = null;
        $expenseNo = null;

        try {
            $expenseNo = $model->nextExpenseNo();

            $model->insert([
                'expense_no'      => $expenseNo,
                'expense_date'    => $input['expense_date'],
                'category_id'     => $input['category_id'],
                'project_id'      => $input['project_id'],
                'paid_to'         => $input['paid_to'],
                'payment_method'  => $input['payment_method'],
                'bank_account_id' => $input['bank_account_id'],
                'amount'          => $input['amount'],
                'remarks'         => $input['remarks'],
                'status'          => 'PAID',
                'created_by'      => session()->get('user_id'),
            ]);

            $expenseId = $model->getInsertID();

            $this->_postBankWithdrawal((int) $expenseId, $expenseNo, $input);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to save expense: ' . $e->getMessage()]])->setStatusCode(500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to save expense due to a database error.']])->setStatusCode(500);
        }

        // Release 4.8.8A: audit trail only, no effect on the response above.
        audit_create('Expense', 'EXPENSE', (int) $expenseId, $expenseNo, [
            'expense_no'      => $expenseNo,
            'expense_date'    => $input['expense_date'],
            'category_id'     => $input['category_id'],
            'project_id'      => $input['project_id'],
            'paid_to'         => $input['paid_to'],
            'payment_method'  => $input['payment_method'],
            'bank_account_id' => $input['bank_account_id'],
            'amount'          => $input['amount'],
            'remarks'         => $input['remarks'],
            'status'          => 'PAID',
        ], 'Expense ' . $expenseNo . ' created.');

        return $this->response->setJSON([
            'status'     => true,
            'message'    => 'Expense recorded successfully.',
            'id'         => $expenseId,
            'expense_no' => $expenseNo,
        ]);
    }

    public function view($id)
    {
        if (! $this->request->isAJAX()) {
            return view('expenses/view', ['id' => (int) $id]);
        }

        $expense = (new ExpenseModel())->find((int) $id);

        if (! $expense) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Expense not found.']])->setStatusCode(404);
        }

        $category = (new ExpenseCategoryModel())->find($expense['category_id']);
        $project  = $expense['project_id'] ? (new ProjectModel())->find($expense['project_id']) : null;
        $bank     = $expense['bank_account_id'] ? (new BankAccountModel())->find($expense['bank_account_id']) : null;
        // Additive for the 4.8.6B view page's "Audit Information" card.
        $creator  = $expense['created_by'] ? (new UserModel())->find((int) $expense['created_by']) : null;

        $expense['category_name']   = $category['category_name'] ?? null;
        $expense['project_name']    = $project['name'] ?? null;
        $expense['bank_account']    = $bank;
        $expense['created_by_name'] = $creator['username'] ?? null;

        return $this->response->setJSON(['status' => true, 'expense' => $expense]);
    }

    /**
     * GET expenses/export/pdf/{id} — Release 4.8.7A. Single-expense PDF
     * (portrait), built the same way view()'s AJAX branch assembles its
     * $expense payload above, so the PDF matches the view page exactly.
     */
    public function exportPdf($id)
    {
        $expense = (new ExpenseModel())->find((int) $id);

        if (! $expense) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Expense not found.');
        }

        $category = (new ExpenseCategoryModel())->find($expense['category_id']);
        $project  = $expense['project_id'] ? (new ProjectModel())->find($expense['project_id']) : null;
        $bank     = $expense['bank_account_id'] ? (new BankAccountModel())->find($expense['bank_account_id']) : null;
        $creator  = $expense['created_by'] ? (new UserModel())->find((int) $expense['created_by']) : null;

        $expense['category_name'] = $category['category_name'] ?? null;
        $expense['project_name']  = $project['name'] ?? null;

        $bankLabel = $bank ? trim(($bank['bank_name'] ?? '') . ' ' . ($bank['account_name'] ?? '')) : '';

        (new PdfReport())->render('pdf/expense_view', [
            'expense'      => $expense,
            'bankLabel'    => $bankLabel,
            'creatorName'  => $creator['username'] ?? null,
        ], [
            'title'       => 'Expense ' . $expense['expense_no'],
            'orientation' => 'portrait',
        ]);
    }

    /**
     * GET expenses/export/excel/{id} — Release 4.8.7B. Single-expense
     * label/value Excel sheet, built exactly the same way exportPdf($id)
     * assembles its $expense payload, so Excel matches the view page and
     * PDF exactly.
     */
    public function exportExcel($id)
    {
        $expense = (new ExpenseModel())->find((int) $id);

        if (! $expense) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Expense not found.');
        }

        $category = (new ExpenseCategoryModel())->find($expense['category_id']);
        $project  = $expense['project_id'] ? (new ProjectModel())->find($expense['project_id']) : null;
        $bank     = $expense['bank_account_id'] ? (new BankAccountModel())->find($expense['bank_account_id']) : null;
        $creator  = $expense['created_by'] ? (new UserModel())->find((int) $expense['created_by']) : null;

        $categoryName = $category['category_name'] ?? '';
        $projectName  = $project['name'] ?? '';
        $bankLabel    = $bank ? trim(($bank['bank_name'] ?? '') . ' ' . ($bank['account_name'] ?? '')) : '';

        (new ExcelReport())->detail(
            'Expense ' . $expense['expense_no'],
            [],
            [
                'Expense Information' => [
                    'Expense No'     => $expense['expense_no'],
                    'Date'           => pdf_date($expense['expense_date']),
                    'Category'       => $categoryName,
                    'Project'        => $projectName,
                    'Paid To'        => $expense['paid_to'],
                    'Payment Method' => $expense['payment_method'],
                    'Bank Account'   => $bankLabel,
                    'Amount'         => pdf_currency($expense['amount']),
                    'Status'         => $expense['status'],
                    'Remarks'        => $expense['remarks'] ?? '',
                ],
                'Audit Information' => [
                    'Recorded By' => $creator['username'] ?? '',
                    'Created On'  => pdf_datetime($expense['created_at'] ?? null),
                ],
            ],
            'portrait'
        )->stream('expense_' . preg_replace('/[^a-z0-9]+/i', '_', $expense['expense_no']) . '_' . date('Ymd_His'));
    }

    public function edit($id)
    {
        if (! $this->request->isAJAX()) {
            return view('expenses/edit', ['id' => (int) $id]);
        }

        $expense = (new ExpenseModel())->find((int) $id);

        if (! $expense) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Expense not found.']])->setStatusCode(404);
        }

        return $this->response->setJSON([
            'status'        => true,
            'expense'       => $expense,
            'categories'    => (new ExpenseCategoryModel())->activeCategories(),
            'bank_accounts' => (new BankAccountModel())->activeAccounts(),
            'projects'      => (new ProjectModel())->orderBy('name', 'ASC')->findAll(),
        ]);
    }

    public function update($id)
    {
        $model   = new ExpenseModel();
        $expense = $model->find((int) $id);

        if (! $expense) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Expense not found.']])->setStatusCode(404);
        }

        if ($expense['status'] === 'CANCELLED') {
            return $this->response->setJSON(['status' => false, 'errors' => ['Cancelled expenses cannot be edited.']])->setStatusCode(422);
        }

        $input  = $this->_extractExpenseInput();
        $errors = $this->_validateExpenseInput($input);

        $statusInput = $this->request->getPost('status');
        $status      = $statusInput ? strtoupper((string) $statusInput) : $expense['status'];
        if (! in_array($status, ['PAID', 'CANCELLED'], true)) {
            $errors[] = 'A valid status is required.';
        }

        if ($errors) {
            return $this->response->setJSON(['status' => false, 'errors' => $errors])->setStatusCode(422);
        }

        // Release 4.9.0CF: only a PAID expense is a cash movement; cancelling one needs no warning.
        if ($status === 'PAID' && ($warn = CashOpeningGuard::gate($this->request, 'expense', (int) $id, $input['payment_method'], $input['expense_date'], true))) {
            return $warn;
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Reverse whatever was previously posted (no-op if the expense
            // was CASH/OTHER, i.e. nothing to reverse) before saving the new
            // values, so amount/method/account/date changes — and PAID ->
            // CANCELLED — never leave a stale bank_transactions row behind.
            $this->_deleteBankWithdrawal((int) $id);

            // expense_no and created_by are never accepted from input, so
            // they always survive an edit unchanged.
            $model->update((int) $id, [
                'expense_date'    => $input['expense_date'],
                'category_id'     => $input['category_id'],
                'project_id'      => $input['project_id'],
                'paid_to'         => $input['paid_to'],
                'payment_method'  => $input['payment_method'],
                'bank_account_id' => $input['bank_account_id'],
                'amount'          => $input['amount'],
                'remarks'         => $input['remarks'],
                'status'          => $status,
            ]);

            // Cancelling reverses the withdrawal but never re-posts one.
            if ($status === 'PAID') {
                $this->_postBankWithdrawal((int) $id, $expense['expense_no'], $input);
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to update expense: ' . $e->getMessage()]])->setStatusCode(500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to update expense due to a database error.']])->setStatusCode(500);
        }

        // Release 4.8.8A: audit trail only. $expense is the row read at the
        // top of this method, before any change was applied.
        audit_update('Expense', 'EXPENSE', (int) $id, $expense['expense_no'], $expense, [
            'expense_date'    => $input['expense_date'],
            'category_id'     => $input['category_id'],
            'project_id'      => $input['project_id'],
            'paid_to'         => $input['paid_to'],
            'payment_method'  => $input['payment_method'],
            'bank_account_id' => $input['bank_account_id'],
            'amount'          => $input['amount'],
            'remarks'         => $input['remarks'],
            'status'          => $status,
        ], 'Expense ' . $expense['expense_no'] . ' updated.');

        return $this->response->setJSON(['status' => true, 'message' => 'Expense updated successfully.']);
    }

    public function delete($id)
    {
        $model   = new ExpenseModel();
        $expense = $model->find((int) $id);

        if (! $expense) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Expense not found.']])->setStatusCode(404);
        }

        if ($expense['status'] === 'CANCELLED') {
            return $this->response->setJSON(['status' => false, 'errors' => ['Cancelled expenses cannot be deleted.']])->setStatusCode(422);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $this->_deleteBankWithdrawal((int) $id);
            $model->delete((int) $id);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to delete expense: ' . $e->getMessage()]])->setStatusCode(500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to delete expense due to a database error.']])->setStatusCode(500);
        }

        // Release 4.8.8A: audit trail only. delete() performs a real row
        // delete (not a Cancel — that's update()'s status field), so this is
        // logged as DELETE with the row as it stood before removal.
        audit_delete('Expense', 'EXPENSE', (int) $id, $expense['expense_no'], $expense, 'Expense ' . $expense['expense_no'] . ' deleted.');

        return $this->response->setJSON(['status' => true, 'message' => 'Expense deleted successfully.']);
    }

    // =========================================================
    // SHARED HELPERS
    // =========================================================

    /**
     * Posts the WITHDRAWAL for one expense; no-op unless paid through a
     * bank (mirrors SupplierPayments::_createBankTransaction() /
     * Loans::_postBankWithdrawal()). Must run inside the caller's own
     * transStart()/transComplete() — BankTransactionModel never opens its
     * own transaction.
     */
    private function _postBankWithdrawal(int $expenseId, string $expenseNo, array $row): void
    {
        if (! BankTransactionModel::isBankMethod($row['payment_method'])) {
            return;
        }

        if (round((float) $row['amount'], 2) <= 0) {
            return;
        }

        $category = (new ExpenseCategoryModel())->find($row['category_id']);

        (new BankTransactionModel())->createBankTransaction([
            'bank_account_id'  => (int) $row['bank_account_id'],
            'transaction_date' => $row['expense_date'],
            'transaction_type' => 'WITHDRAWAL',
            'amount'           => round((float) $row['amount'], 2),
            'reference_type'   => self::REFERENCE_TYPE,
            'reference_id'     => $expenseId,
            'reference_no'     => $expenseNo,
            'remarks'          => 'Expense - ' . ($category['category_name'] ?? 'Unknown Category'),
            'created_by'       => session()->get('user_id'),
        ]);
    }

    /** Reverses and removes whatever was posted for one expense (0 rows is fine). */
    private function _deleteBankWithdrawal(int $expenseId): void
    {
        (new BankTransactionModel())->deleteBankTransaction(self::REFERENCE_TYPE, $expenseId);
    }

    private function _extractExpenseInput(): array
    {
        return [
            'expense_date'    => trim((string) $this->request->getPost('expense_date')),
            'category_id'     => $this->request->getPost('category_id') ? (int) $this->request->getPost('category_id') : 0,
            'project_id'      => $this->request->getPost('project_id') ? (int) $this->request->getPost('project_id') : null,
            'paid_to'         => trim((string) $this->request->getPost('paid_to')),
            'payment_method'  => strtoupper((string) $this->request->getPost('payment_method')),
            'bank_account_id' => $this->request->getPost('bank_account_id') ? (int) $this->request->getPost('bank_account_id') : null,
            'amount'          => trim((string) $this->request->getPost('amount')),
            'remarks'         => trim((string) $this->request->getPost('remarks')) !== '' ? trim((string) $this->request->getPost('remarks')) : null,
        ];
    }

    /**
     * Phase D validation: expense_date/category_id/paid_to/amount required,
     * amount > 0 with at most 2 decimals, payment_method must be one of the
     * 5 allowed values, bank_account_id required (and must exist + be
     * active) only for BANK/CHEQUE/UPI, project must exist if supplied,
     * category must exist.
     */
    private function _validateExpenseInput(array $input): array
    {
        $errors = [];

        if (! CashOpeningGuard::isValidDate($input['expense_date'])) { // Release 4.9.0CF: strict calendar date
            $errors[] = 'A valid expense date is required.';
        }

        if ($input['category_id'] <= 0) {
            $errors[] = 'Category is required.';
        } elseif (! (new ExpenseCategoryModel())->find($input['category_id'])) {
            $errors[] = 'The selected category does not exist.';
        }

        if ($input['project_id'] !== null && ! (new ProjectModel())->find($input['project_id'])) {
            $errors[] = 'The selected project does not exist.';
        }

        if ($input['paid_to'] === '') {
            $errors[] = 'Paid to is required.';
        } elseif (mb_strlen($input['paid_to']) > 150) {
            $errors[] = 'Paid to cannot exceed 150 characters.';
        }

        if (! in_array($input['payment_method'], ['CASH', 'BANK', 'CHEQUE', 'UPI', 'OTHER'], true)) {
            $errors[] = 'A valid payment method is required.';
        }

        $bankRequired = in_array($input['payment_method'], ['BANK', 'CHEQUE', 'UPI'], true);

        if ($bankRequired && ! $input['bank_account_id']) {
            $errors[] = 'A bank account is required for Bank, Cheque, or UPI payments.';
        } elseif ($input['bank_account_id']) {
            $bankAccount = (new BankAccountModel())->find($input['bank_account_id']);
            if (! $bankAccount) {
                $errors[] = 'The selected bank account does not exist.';
            } elseif ((int) $bankAccount['is_active'] !== 1) {
                $errors[] = 'The selected bank account is not active.';
            }
        }

        if ($input['amount'] === '' || ! is_numeric($input['amount']) || (float) $input['amount'] <= 0) {
            $errors[] = 'Amount must be greater than zero.';
        } elseif (! preg_match('/^\d+(\.\d{1,2})?$/', $input['amount'])) {
            $errors[] = 'Amount cannot have more than 2 decimal places.';
        }

        return $errors;
    }
}
