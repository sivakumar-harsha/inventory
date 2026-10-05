<?php

namespace App\Controllers;

use App\Libraries\CashOpeningGuard;
use App\Models\BankAccountModel;
use App\Models\BankTransactionModel;
use App\Models\ProjectCashReceiptModel;
use App\Models\ProjectModel;
use CodeIgniter\Controller;

/**
 * Release 4.5: Project Cash Receipt — money received directly against a
 * project before (or between) invoices exist. Independent of the Payments
 * module (payments.sale_id) and of projects.advance_amount. Simple CRUD,
 * no FIFO/allocation logic — see ProjectCashReceiptModel.
 */
class ProjectCashReceipts extends Controller
{
    protected $model;

    public function __construct()
    {
        $this->model = new ProjectCashReceiptModel();
    }

    public function store()
    {
        $projectId   = (int) $this->request->getPost('project_id');
        $amount      = (float) $this->request->getPost('amount');
        $recDate     = trim((string) $this->request->getPost('receipt_date'));
        $method      = $this->request->getPost('payment_method');
        $reference   = $this->request->getPost('reference');
        $notes       = $this->request->getPost('notes');

        // Release 4.6.5: Direct Project Income — ADVANCE (default, existing
        // behavior) vs DIRECT_INCOME (recognized as revenue immediately,
        // since by definition no invoice will ever be raised for it).
        // Release 4.9.0T: CUSTOMER_PROJECT_CASH — genuine customer money
        // received for the project without selecting an invoice; reduces
        // Outstanding Collection directly (ProjectModel::getFinancialSummary())
        // without ever touching sales.balance_amount or auto-selecting an
        // invoice. Any unrecognized value falls back to ADVANCE rather than
        // being trusted.
        $receiptType = $this->request->getPost('receipt_type');
        if (!in_array($receiptType, ['ADVANCE', 'DIRECT_INCOME', 'CUSTOMER_PROJECT_CASH'], true)) {
            $receiptType = 'ADVANCE';
        }

        $project = (new ProjectModel())->find($projectId);
        if (!$project) {
            return redirect()->back()->withInput()->with('error', 'Selected project not found.');
        }

        if ($amount <= 0) {
            return redirect()->back()->withInput()->with('error', 'Amount must be greater than zero.');
        }

        // Release 4.9.0CF: a real calendar date is required (a blank or 0000-00-00 date used to be stored).
        if (! CashOpeningGuard::isValidDate($recDate)) {
            return redirect()->back()->withInput()->with('error', 'Enter a valid receipt date.');
        }

        // Release 4.8.3C: money received by bank must land in a real, active
        // bank account. Cash (and Other) never touches the bank tables.
        $bankAccountId = (int) $this->request->getPost('bank_account_id');
        $bankError     = $this->_validateBankAccount($method, $bankAccountId);
        if ($bankError) {
            return redirect()->back()->withInput()->with('error', $bankError);
        }

        // Release 4.9.0CF: a CASH receipt dated before the Cash Opening Date is warned about, never blocked.
        if ($warn = CashOpeningGuard::gate($this->request, 'project-receipt', 0, $method, $recDate)) {
            return $warn;
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $receiptNo = $this->model->nextReceiptNo();

            $receiptId = $this->model->insert([
                'project_id'      => $projectId,
                'customer_id'     => $project['customer_id'] ?: null,
                'receipt_no'      => $receiptNo,
                'amount'          => $amount,
                'receipt_date'    => $recDate,
                'payment_method'  => $method,
                'receipt_type'    => $receiptType,
                'reference'       => $reference,
                'notes'           => $notes,
                // Only bank receipts keep an account; a stray value posted
                // alongside Cash is dropped, never stored.
                'bank_account_id' => BankTransactionModel::isBankMethod($method) ? $bankAccountId : null,
            ]);

            $this->_createBankTransaction((int) $receiptId, $receiptNo, $bankAccountId, $project['name'], $amount, (string) $recDate, $method, $receiptType);
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', 'Failed to record project receipt: ' . $e->getMessage());
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', 'Failed to record project receipt due to a database error.');
        }

        return redirect()->to('/payments')->with('success', 'Project receipt recorded successfully.');
    }

    // =========================================================
    // BANK INTEGRATION (Release 4.8.3C)
    // Balance math lives in BankTransactionModel::updateBankBalance()/
    // restoreBankBalance(). There is no receipt update/delete endpoint yet,
    // so _deleteBankTransaction() is ready for that release but not called.
    // =========================================================

    /** Returns an error message, or null when no bank account is needed or the one given is usable. */
    private function _validateBankAccount(?string $method, int $bankAccountId): ?string
    {
        if (! BankTransactionModel::isBankMethod($method)) {
            return null;
        }
        if ($bankAccountId <= 0) {
            return 'A bank account is required for Bank Transfer and Cheque receipts.';
        }

        $account = (new BankAccountModel())->find($bankAccountId);
        if (! $account) {
            return 'The selected bank account was not found.';
        }
        if ((int) $account['is_active'] !== 1) {
            return 'Transactions cannot be posted against an inactive bank account.';
        }

        return null;
    }

    /** Posts the DEPOSIT for one receipt; no-op unless received through a bank. */
    private function _createBankTransaction(int $receiptId, string $receiptNo, int $bankAccountId, string $projectName, float $amount, string $date, ?string $method, string $receiptType = 'ADVANCE'): void
    {
        if (! BankTransactionModel::isBankMethod($method)) {
            return;
        }

        // Release 4.9.0CB: wording only. The row is still reference_type PROJECT_ADVANCE with the same
        // reference_id, amount, account and posting; an Unallocated Project Receipt (CUSTOMER_PROJECT_CASH)
        // is no longer described as an "advance" in the bank remark.
        $remarks = ($receiptType === 'CUSTOMER_PROJECT_CASH' ? 'Unallocated Project Receipt — ' : 'Project Advance - ') . $projectName;

        (new BankTransactionModel())->createBankTransaction([
            'bank_account_id'  => $bankAccountId,
            'transaction_date' => $date,
            'transaction_type' => 'DEPOSIT',
            'amount'           => $amount,
            'reference_type'   => 'PROJECT_ADVANCE',
            'reference_id'     => $receiptId,
            'reference_no'     => $receiptNo,
            'remarks'          => $remarks,
            'created_by'       => session()->get('user_id'),
        ]);
    }

    /** Reverses and removes whatever was posted for one receipt (0 rows is fine). */
    private function _deleteBankTransaction(int $receiptId): void
    {
        (new BankTransactionModel())->deleteBankTransaction('PROJECT_ADVANCE', $receiptId);
    }
}
