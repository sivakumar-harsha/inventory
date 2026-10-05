<?php

namespace App\Controllers;

use App\Libraries\CashOpeningGuard;
use App\Libraries\ExcelReport;
use App\Libraries\PdfReport;
use App\Models\BankAccountModel;
use App\Models\BankTransactionModel;
use App\Models\LoanModel;
use App\Models\LoanPaymentModel;
use App\Models\LoanReceiptModel;
use App\Models\LoanTypeModel;
use CodeIgniter\Controller;

/**
 * Loan Management — a manual loan transaction register (Release 4.9.0BC view).
 *
 * A loan is money the company has borrowed (bank, NBFC, friends, vehicle
 * finance, overdraft). The loan master holds the basic loan information; no
 * schedule is generated for new loans. The header keeps the running figures:
 *
 *     outstanding_principal = MAX(0, sanctioned_amount - SUM(loan_payments.total_paid))
 *
 * and status is ACTIVE until that reaches zero, then CLOSED automatically (and
 * back to ACTIVE if a payment is taken back). loan_payments is the source of
 * truth; loan_emis and the principal/interest columns are legacy reference data
 * that nothing here writes.
 *
 * Payments: payments()/storePayment()/updatePayment()/deletePayment() record
 * one manual Payment Amount (loan_payments.total_paid) and the bank posting (a
 * WITHDRAWAL with reference_type LOAN_PAYMENT and reference_id the
 * loan_payments.id, only for BANK/CHEQUE/UPI). The helpers never open a DB
 * transaction — each endpoint owns it, so any failure rolls the payment row,
 * the loan header and the bank balance back together. ledger() is the
 * read-only loan statement.
 *
 * Loan receipts: the opposite side of a loan — actual money the company
 * received from the lender ("Loan Received"), tracked separately from
 * loan_payments. receiveLoan()/storeReceipt()/updateReceipt()/deleteReceipt()
 * post a DEPOSIT (reference_type LOAN_DISBURSEMENT, reference_id the
 * loan_receipts.id) for BANK/CHEQUE/UPI only. A receipt never touches the
 * outstanding figure. Total receipts are hard-capped at the sanctioned amount.
 *
 * Every other module is untouched.
 */
class Loans extends Controller
{
    private const BANK_REQUIRED  = ['BANK', 'OD'];
    private const BANK_METHODS   = ['BANK', 'CHEQUE', 'UPI'];
    private const REFERENCE_TYPE = 'LOAN_PAYMENT';

    // Release 4.9.0AT: an overdraft is drawn incrementally, not received as a
    // lump sum, so Receive Loan is not offered for it.
    private const RECEIPT_EXCLUDED_TYPES = ['OD'];
    private const REFERENCE_TYPE_RECEIPT = 'LOAN_DISBURSEMENT';

    // Exception code the payment engine puts on a business-rule failure
    // (overpayment, closed loan...), so an endpoint answers 422 with the
    // message instead of a 500.
    private const RULE_CODE = 422;

    // DECIMAL(15,2). MySQL/MariaDB in non-strict mode silently clamps an
    // overflowing value instead of failing, so it is rejected here instead.
    private const MAX_AMOUNT = 9999999999999.99;
    private const MAX_TENURE = 600;   // 50 years
    private const MAX_RATE   = 100.0; // annual %

    // =========================================================
    // ENDPOINTS
    // =========================================================

    public function index()
    {
        $db = \Config\Database::connect();

        $loans = $db->query("
            SELECT l.*, ba.bank_name, ba.account_name,
                   " . LoanModel::paidSql('l') . " AS total_paid,
                   " . LoanModel::outstandingSql('l') . " AS outstanding_principal,
                   (SELECT MIN(le.due_date) FROM loan_emis le
                     WHERE le.loan_id = l.id AND le.payment_status <> 'PAID') AS next_due_date,
                   (SELECT COUNT(*) FROM loan_emis le
                     WHERE le.loan_id = l.id AND le.payment_status = 'PAID') AS emis_paid
            FROM loans l
            LEFT JOIN bank_accounts ba ON ba.id = l.bank_account_id
            ORDER BY l.start_date DESC, l.id DESC
        ")->getResultArray();

        $active = array_filter($loans, static fn ($l) => $l['status'] === 'ACTIVE');

        // Unpaid instalments (PENDING/PARTIAL) of active loans falling due in
        // the current calendar month, for the "EMI Due This Month" card.
        $due = $db->query("
            SELECT COUNT(*) AS emi_count, COALESCE(SUM(le.balance_amount), 0) AS emi_total
            FROM loan_emis le
            JOIN loans l ON l.id = le.loan_id
            WHERE l.status = 'ACTIVE'
              AND le.payment_status <> 'PAID'
              AND le.due_date BETWEEN ? AND ?
        ", [date('Y-m-01'), date('Y-m-t')])->getRowArray();

        return view('loans/index', [
            'title'                 => 'Loan Management',
            'loans'                 => $loans,
            'loan_types'            => LoanTypeModel::codes(),
            'lenders'               => array_values(array_unique(array_column($loans, 'lender_name'))),
            'kpi_active_loans'      => count($active),
            'kpi_total_outstanding' => round(array_sum(array_column($active, 'outstanding_principal')), 2),
            'kpi_total_sanctioned'  => round(array_sum(array_column($loans, 'sanctioned_amount')), 2),
            'kpi_emi_due_month'     => round((float) $due['emi_total'], 2),
            'kpi_emi_due_count'     => (int) $due['emi_count'],
        ]);
    }

    public function create()
    {
        return view('loans/create', [
            'title'         => 'New Loan',
            'next_loan_no'  => (new LoanModel())->nextLoanNo(),
            'loan_types'    => array_keys(LoanTypeModel::activeLabels()),
        ]);
    }

    public function store()
    {
        $input    = $this->_extractInput();
        $prepared = $this->_prepare($input, null);

        if ($prepared['errors']) {
            return $this->_error($prepared['errors'], 422);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $loanId = null;
        $loanNo = null;

        try {
            $loanModel = new LoanModel();
            $loanNo    = $input['loan_no'] !== '' ? $input['loan_no'] : $loanModel->nextLoanNo();

            $header = $prepared['header'] + [
                'loan_no'               => $loanNo,
                'outstanding_principal' => $prepared['header']['sanctioned_amount'],
                'total_principal_paid'  => 0,
                'total_interest_paid'   => 0,
                'status'                => 'ACTIVE',
                'created_by'            => session()->get('user_id'),
            ];

            if (! $loanModel->insert($header)) {
                throw new \RuntimeException(implode(' ', $loanModel->errors()) ?: 'The loan could not be saved.');
            }
            $loanId = (int) $loanModel->getInsertID();

            // Release 4.9.0AY: no EMI schedule is generated. Payments are entered manually.
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->_error(['Failed to save loan: ' . $e->getMessage()], 500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->_error(['Failed to save loan due to a database error.'], 500);
        }

        // Release 4.8.8A: audit trail only.
        audit_create('Loan', 'LOAN', (int) $loanId, $loanNo, $header, 'Loan ' . $loanNo . ' created.');

        return $this->response->setJSON([
            'status'    => true,
            'message'   => 'Loan saved successfully.',
            'id'        => $loanId,
            'loan_no'   => $loanNo,
        ]);
    }

    public function view($id)
    {
        $data = $this->_loadLoan((int) $id);

        if (! $data) {
            return redirect()->to('/loans')->with('error', 'Loan not found.');
        }

        return view('loans/view', ['title' => 'Loan ' . $data['loan']['loan_no']] + $data);
    }

    public function edit($id)
    {
        $data = $this->_loadLoan((int) $id);

        if (! $data) {
            return redirect()->to('/loans')->with('error', 'Loan not found.');
        }

        return view('loans/edit', [
            'title'      => 'Edit Loan ' . $data['loan']['loan_no'],
            'loan_types' => array_values(array_unique(array_merge(array_keys(LoanTypeModel::activeLabels()), [$data['loan']['loan_type']]))),
        ] + $data);
    }

    public function update($id)
    {
        $loanModel = new LoanModel();
        $loan      = $loanModel->find((int) $id);

        if (! $loan) {
            return $this->_error(['Loan not found.'], 404);
        }

        $input    = $this->_extractInput();
        $prepared = $this->_prepare($input, $loan);

        if ($prepared['errors']) {
            return $this->_error($prepared['errors'], 422);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $loanModel->outstanding((int) $id, true); // row lock

            $hasPayments = $this->_hasPayments((int) $id);

            // Release 4.9.0AY: rate, tenure and start date are informational; only
            // the sanctioned amount (which the outstanding principal is built on)
            // is frozen once payments exist.
            if ($hasPayments && $this->_cents((float) $loan['sanctioned_amount']) !== $this->_cents((float) $prepared['header']['sanctioned_amount'])) {
                $db->transRollback();
                return $this->_error(['This loan already has payments, so its sanctioned amount can no longer be changed.'], 422);
            }

            // loan_no goes into the update only when it really changed: the
            // model's is_unique rule can't tell a loan from itself here, and
            // _validateInput() has already checked the new number is free.
            $newNo  = $input['loan_no'] !== '' ? $input['loan_no'] : $loan['loan_no'];
            $header = $prepared['header'] + ($newNo !== $loan['loan_no'] ? ['loan_no' => $newNo] : []);

            if (! $hasPayments) {
                // Nothing paid yet: the schedule is simply rebuilt from the new
                // terms and the running figures start again from the top.
                $header += [
                    'outstanding_principal' => $prepared['header']['sanctioned_amount'],
                    'total_principal_paid'  => 0,
                    'total_interest_paid'   => 0,
                    'status'                => 'ACTIVE',
                ];
            }

            if (! $loanModel->update((int) $id, $header)) {
                throw new \RuntimeException(implode(' ', $loanModel->errors()) ?: 'The loan could not be updated.');
            }

            // Release 4.9.0AY: the legacy loan_emis rows are left exactly as they are.
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->_error(['Failed to update loan: ' . $e->getMessage()], 500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->_error(['Failed to update loan due to a database error.'], 500);
        }

        // Release 4.8.8A: audit trail only. $loan is the row read at the top
        // of this method, before any change was applied.
        audit_update('Loan', 'LOAN', (int) $id, $newNo, $loan, $header, 'Loan ' . $newNo . ' updated.');

        return $this->response->setJSON([
            'status'  => true,
            'message' => 'Loan updated successfully.',
            'id'      => (int) $id,
            'loan_no' => $newNo,
        ]);
    }

    public function delete($id)
    {
        $loanModel = new LoanModel();
        $loan      = $loanModel->find((int) $id);

        if (! $loan) {
            return $this->_error(['Loan not found.'], 404);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $loanModel->outstanding((int) $id, true); // row lock

            if ($this->_hasPayments((int) $id)) {
                $db->transRollback();
                return $this->_error(['This loan has payments recorded against it and cannot be deleted.'], 422);
            }

            // The EMI schedule goes with it (ON DELETE CASCADE).
            $loanModel->delete((int) $id);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->_error(['Failed to delete loan: ' . $e->getMessage()], 500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->_error(['Failed to delete loan due to a database error.'], 500);
        }

        // Release 4.8.8A: audit trail only.
        audit_delete('Loan', 'LOAN', (int) $id, $loan['loan_no'], $loan, 'Loan ' . $loan['loan_no'] . ' deleted.');

        return $this->response->setJSON(['status' => true, 'message' => 'Loan deleted successfully.']);
    }

    // =========================================================
    // PAYMENT ENDPOINTS (Release 4.8.5C)
    // =========================================================

    /** GET loans/payments/{loanId} — the payment screen: record-payment form and payment history. */
    public function payments($loanId)
    {
        $loanModel = new LoanModel();
        $loan      = $loanModel->findWithTotals((int) $loanId);

        if (! $loan) {
            return redirect()->to('/loans')->with('error', 'Loan not found.');
        }

        return view('loans/payments', [
            'title'         => 'Payments ' . $loan['loan_no'],
            'loan'          => $loan,
            'outstanding'   => $loanModel->outstanding((int) $loanId),
            'payments'      => (new LoanPaymentModel())->forLoan((int) $loanId),
            'bank_accounts' => $this->_activeBankAccounts(),
            'bank_methods'  => self::BANK_METHODS,
        ]);
    }

    /**
     * POST loans/payments/store — one manual payment against one loan: a single
     * Payment Amount (stored as total_paid). Release 4.9.0BA: no principal /
     * interest split and no EMI. The payment row, the loan header and the bank
     * withdrawal are written in one transaction.
     */
    public function storePayment()
    {
        $input = $this->_extractPaymentInput();
        $loan  = (new LoanModel())->find($input['loan_id']);

        if (! $loan) {
            return $this->_error(['Loan not found.'], 404);
        }
        if ($loan['status'] !== 'ACTIVE') {
            return $this->_error(["Loan {$loan['loan_no']} is closed, so no further payments can be recorded."], 422);
        }

        $errors = $this->_validatePayment($input);

        if ($errors) {
            return $this->_error($errors, 422);
        }

        // Release 4.9.0CF: a CASH payment dated before the Cash Opening Date is warned about, never blocked.
        if ($warn = CashOpeningGuard::gate($this->request, 'loan-payment', 0, $input['payment_method'], $input['payment_date'], true)) {
            return $warn;
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $this->_assertPaymentFits($input['loan_id'], $input['payment_amount'], null, false);

            $row                = $this->_paymentRow($input);
            $row['created_by']  = session()->get('user_id');
            $paymentModel       = new LoanPaymentModel();

            if (! $paymentModel->insert($row)) {
                throw new \RuntimeException(implode(' ', $paymentModel->errors()) ?: 'The payment could not be saved.');
            }
            $paymentId = (int) $paymentModel->getInsertID();

            $this->_postBankWithdrawal($loan, $row + ['id' => $paymentId]);
            $state = $this->_recalculateLoan($input['loan_id'], true);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->_paymentFailure($e, 'save the payment');
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->_error(['Failed to save the payment due to a database error.'], 500);
        }

        // Release 4.8.8A: audit trail only.
        audit_create('Loan', 'LOAN_PAYMENT', $paymentId, $row['reference_no'] ?? $loan['loan_no'], $row, 'Payment recorded against loan ' . $loan['loan_no'] . '.');
        if ($loan['status'] !== $state['status']) {
            audit_status('Loan', 'LOAN', (int) $input['loan_id'], $loan['loan_no'], $loan['status'], $state['status'], 'Loan ' . $loan['loan_no'] . ' status recalculated after a payment.');
        }

        return $this->response->setJSON([
            'status'                => true,
            'message'               => 'Payment recorded successfully.',
            'id'                    => $paymentId,
            'outstanding_principal' => $state['outstanding_principal'],
            'loan_status'           => $state['status'],
        ]);
    }

    /**
     * POST loans/payments/update/{paymentId} — the old payment is taken back
     * (EMI, loan totals, bank withdrawal) and the new one applied in its place,
     * in one transaction, so a rejected edit changes nothing. A payment never
     * moves to another loan. Works on a closed loan too: taking the old payment
     * back reopens it, and the new figures close it again if they clear it.
     */
    public function updatePayment($paymentId)
    {
        $paymentModel = new LoanPaymentModel();
        $existing     = $paymentModel->find((int) $paymentId);

        if (! $existing) {
            return $this->_error(['Payment not found.'], 404);
        }

        $loanId = (int) $existing['loan_id'];
        $loan   = (new LoanModel())->find($loanId);

        $input            = $this->_extractPaymentInput();
        $input['loan_id'] = $loanId;
        $errors           = $this->_validatePayment($input);

        if ($errors) {
            return $this->_error($errors, 422);
        }

        // Release 4.9.0CF: a CASH payment dated before the Cash Opening Date is warned about, never blocked.
        if ($warn = CashOpeningGuard::gate($this->request, 'loan-payment', (int) $paymentId, $input['payment_method'], $input['payment_date'], true)) {
            return $warn;
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            (new LoanModel())->outstanding($loanId, true); // row lock, before the payment row

            $old = $paymentModel->lockedRow((int) $paymentId);

            if (! $old) {
                throw new \RuntimeException('Payment not found.', self::RULE_CODE);
            }

            $this->_assertPaymentFits($loanId, $input['payment_amount'], (int) $paymentId, true);
            $this->_deleteBankWithdrawal((int) $paymentId);

            $row = $this->_paymentRow($input, $old);

            if (! $paymentModel->update((int) $paymentId, $row)) {
                throw new \RuntimeException(implode(' ', $paymentModel->errors()) ?: 'The payment could not be updated.');
            }

            $this->_postBankWithdrawal($loan, $row + ['id' => (int) $paymentId]);
            $state = $this->_recalculateLoan($loanId, true);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->_paymentFailure($e, 'update the payment');
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->_error(['Failed to update the payment due to a database error.'], 500);
        }

        // Release 4.8.8A: audit trail only. $old is the payment row read
        // (row-locked) before it was taken back and reapplied; $loan's
        // status was read before any of this ran.
        audit_update('Loan', 'LOAN_PAYMENT', (int) $paymentId, $row['reference_no'] ?? ($loan['loan_no'] ?? null), $old, $row, 'Payment updated against loan ' . ($loan['loan_no'] ?? $loanId) . '.');
        if ($loan && $loan['status'] !== $state['status']) {
            audit_status('Loan', 'LOAN', $loanId, $loan['loan_no'], $loan['status'], $state['status'], 'Loan ' . $loan['loan_no'] . ' status recalculated after a payment update.');
        }

        return $this->response->setJSON([
            'status'                => true,
            'message'               => 'Payment updated successfully.',
            'id'                    => (int) $paymentId,
            'outstanding_principal' => $state['outstanding_principal'],
            'loan_status'           => $state['status'],
        ]);
    }

    /**
     * POST loans/payments/delete/{paymentId} — takes the payment back: the EMI
     * returns to PENDING/PARTIAL, the loan's paid totals and outstanding
     * principal are restored (a closed loan reopens), and the bank withdrawal
     * is reversed and removed.
     */
    public function deletePayment($paymentId)
    {
        $paymentModel = new LoanPaymentModel();
        $existing     = $paymentModel->find((int) $paymentId);

        if (! $existing) {
            return $this->_error(['Payment not found.'], 404);
        }

        $loanId    = (int) $existing['loan_id'];
        $loanBefore = (new LoanModel())->find($loanId);
        $db        = \Config\Database::connect();
        $db->transStart();

        try {
            (new LoanModel())->outstanding($loanId, true); // row lock

            $old = $paymentModel->lockedRow((int) $paymentId);

            if (! $old) {
                throw new \RuntimeException('Payment not found.', self::RULE_CODE);
            }

            $this->_deleteBankWithdrawal((int) $paymentId);
            $paymentModel->delete((int) $paymentId);
            $state = $this->_recalculateLoan($loanId, true);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->_paymentFailure($e, 'delete the payment');
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->_error(['Failed to delete the payment due to a database error.'], 500);
        }

        // Release 4.8.8A: audit trail only. $old is the payment row read
        // (row-locked) before deletion; $loanBefore's status was read before
        // any of this ran.
        audit_delete('Loan', 'LOAN_PAYMENT', (int) $paymentId, $old['reference_no'] ?? ($loanBefore['loan_no'] ?? null), $old, 'Payment deleted from loan ' . ($loanBefore['loan_no'] ?? $loanId) . '.');
        if ($loanBefore && $loanBefore['status'] !== $state['status']) {
            audit_status('Loan', 'LOAN', $loanId, $loanBefore['loan_no'], $loanBefore['status'], $state['status'], 'Loan ' . $loanBefore['loan_no'] . ' status recalculated after a payment delete.');
        }

        return $this->response->setJSON([
            'status'                => true,
            'message'               => 'Payment deleted successfully.',
            'outstanding_principal' => $state['outstanding_principal'],
            'loan_status'           => $state['status'],
        ]);
    }

    // =========================================================
    // RECEIPT ENDPOINTS (Release 4.9.0AT)
    // =========================================================

    /** GET loans/receive/{loanId} — the Receive Loan form. Not offered for OD (overdraft is drawn, not received as a lump sum). */
    public function receiveLoan($loanId)
    {
        $loan = (new LoanModel())->find((int) $loanId);

        if (! $loan) {
            return redirect()->to('/loans')->with('error', 'Loan not found.');
        }
        if (in_array($loan['loan_type'], self::RECEIPT_EXCLUDED_TYPES, true)) {
            return redirect()->to('/loans/view/' . $loanId)->with('error', 'Receive Loan is not available for an Overdraft facility.');
        }

        $receiptModel  = new LoanReceiptModel();
        $totalReceived = $receiptModel->totalReceived((int) $loanId);

        return view('loans/receive', [
            'title'                 => 'Receive Loan ' . $loan['loan_no'],
            'loan'                  => $loan,
            'total_received'        => $totalReceived,
            'remaining_to_receive'  => round((float) $loan['sanctioned_amount'] - $totalReceived, 2),
            'bank_accounts'         => $this->_activeBankAccounts(),
            'bank_methods'          => self::BANK_METHODS,
        ]);
    }

    /** GET loans/receipts/edit/{receiptId} — the Edit Receipt form. */
    public function editReceipt($receiptId)
    {
        $receiptModel = new LoanReceiptModel();
        $receipt      = $receiptModel->find((int) $receiptId);

        if (! $receipt) {
            return redirect()->to('/loans')->with('error', 'Receipt not found.');
        }

        $loan = (new LoanModel())->find((int) $receipt['loan_id']);

        if (! $loan) {
            return redirect()->to('/loans')->with('error', 'Loan not found.');
        }

        $totalReceived = $receiptModel->totalReceived((int) $receipt['loan_id']);

        return view('loans/receipt_edit', [
            'title'                 => 'Edit Receipt ' . $receipt['receipt_no'],
            'loan'                  => $loan,
            'receipt'               => $receipt,
            'total_received'        => $totalReceived,
            'remaining_to_receive'  => round((float) $loan['sanctioned_amount'] - $totalReceived, 2),
            'bank_accounts'         => $this->_activeBankAccounts(),
            'bank_methods'          => self::BANK_METHODS,
        ]);
    }

    /**
     * POST loans/receipts/store — one receipt against one loan (full or
     * partial; multiple receipts per loan are supported). Receipt row and the
     * bank deposit are written in one transaction; total receipts are
     * hard-capped at the sanctioned amount.
     */
    public function storeReceipt()
    {
        $input = $this->_extractReceiptInput();
        $loan  = (new LoanModel())->find($input['loan_id']);

        if (! $loan) {
            return $this->_error(['Loan not found.'], 404);
        }
        if (in_array($loan['loan_type'], self::RECEIPT_EXCLUDED_TYPES, true)) {
            return $this->_error(['Receive Loan is not available for an Overdraft facility.'], 422);
        }

        $errors = $this->_validateReceipt($input);

        if ($errors) {
            return $this->_error($errors, 422);
        }

        // Release 4.9.0CF: a CASH receipt dated before the Cash Opening Date is warned about, never blocked.
        if ($warn = CashOpeningGuard::gate($this->request, 'loan-receipt', 0, $input['payment_method'], $input['receipt_date'], true)) {
            return $warn;
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $receiptId = null;
        $row       = null;

        try {
            // Row-locks the loan header, so two receipts against the same loan
            // at once serialise instead of both passing the over-receipt check
            // against the same stale total.
            (new LoanModel())->outstanding($input['loan_id'], true);

            $receiptModel  = new LoanReceiptModel();
            $sanctioned    = round((float) $loan['sanctioned_amount'], 2);
            $totalReceived = $receiptModel->totalReceived($input['loan_id']);

            if (round($totalReceived + $input['amount'], 2) > $sanctioned + 0.004) {
                throw new \RuntimeException(
                    'This receipt would take total receipts to ' . number_format($totalReceived + $input['amount'], 2)
                    . ', which exceeds the sanctioned amount of ' . number_format($sanctioned, 2) . '.',
                    self::RULE_CODE
                );
            }

            $row               = $this->_receiptRow($input);
            $row['receipt_no'] = $receiptModel->nextReceiptNo();
            $row['created_by'] = session()->get('user_id');

            if (! $receiptModel->insert($row)) {
                throw new \RuntimeException(implode(' ', $receiptModel->errors()) ?: 'The receipt could not be saved.');
            }
            $receiptId = (int) $receiptModel->getInsertID();

            $this->_postBankDeposit($loan, $row + ['id' => $receiptId]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->_receiptFailure($e, 'save the receipt');
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->_error(['Failed to save the receipt due to a database error.'], 500);
        }

        audit_create('Loan', self::REFERENCE_TYPE_RECEIPT, $receiptId, $row['receipt_no'], $row, 'Receipt ' . $row['receipt_no'] . ' recorded against loan ' . $loan['loan_no'] . '.');

        return $this->response->setJSON([
            'status'      => true,
            'message'     => 'Loan receipt recorded successfully.',
            'id'          => $receiptId,
            'receipt_no'  => $row['receipt_no'],
        ]);
    }

    /**
     * POST loans/receipts/update/{receiptId} — the old bank deposit (if any) is
     * reversed before the receipt is rewritten and a new deposit posted, all in
     * one transaction, so a rejected edit changes nothing. A receipt never
     * moves to another loan.
     */
    public function updateReceipt($receiptId)
    {
        $receiptModel = new LoanReceiptModel();
        $existing     = $receiptModel->find((int) $receiptId);

        if (! $existing) {
            return $this->_error(['Receipt not found.'], 404);
        }

        $loanId = (int) $existing['loan_id'];
        $loan   = (new LoanModel())->find($loanId);

        if (! $loan) {
            return $this->_error(['Loan not found.'], 404);
        }

        $input            = $this->_extractReceiptInput();
        $input['loan_id'] = $loanId;
        $errors           = $this->_validateReceipt($input);

        if ($errors) {
            return $this->_error($errors, 422);
        }

        // Release 4.9.0CF: a CASH receipt dated before the Cash Opening Date is warned about, never blocked.
        if ($warn = CashOpeningGuard::gate($this->request, 'loan-receipt', (int) $receiptId, $input['payment_method'], $input['receipt_date'], true)) {
            return $warn;
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $row = null;
        $old = null;

        try {
            (new LoanModel())->outstanding($loanId, true); // row lock, before the receipt row

            $old = $receiptModel->lockedRow((int) $receiptId);

            if (! $old) {
                throw new \RuntimeException('Receipt not found.', self::RULE_CODE);
            }

            $sanctioned  = round((float) $loan['sanctioned_amount'], 2);
            $totalOthers = round($receiptModel->totalReceived($loanId) - (float) $old['amount'], 2);

            if (round($totalOthers + $input['amount'], 2) > $sanctioned + 0.004) {
                throw new \RuntimeException(
                    'This receipt would take total receipts to ' . number_format($totalOthers + $input['amount'], 2)
                    . ', which exceeds the sanctioned amount of ' . number_format($sanctioned, 2) . '.',
                    self::RULE_CODE
                );
            }

            $this->_deleteBankDeposit((int) $receiptId);

            $row = $this->_receiptRow($input);

            if (! $receiptModel->update((int) $receiptId, $row)) {
                throw new \RuntimeException(implode(' ', $receiptModel->errors()) ?: 'The receipt could not be updated.');
            }

            $this->_postBankDeposit($loan, $row + ['id' => (int) $receiptId, 'receipt_no' => $old['receipt_no']]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->_receiptFailure($e, 'update the receipt');
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->_error(['Failed to update the receipt due to a database error.'], 500);
        }

        audit_update('Loan', self::REFERENCE_TYPE_RECEIPT, (int) $receiptId, $old['receipt_no'], $old, $row, 'Receipt ' . $old['receipt_no'] . ' updated against loan ' . $loan['loan_no'] . '.');

        return $this->response->setJSON([
            'status'  => true,
            'message' => 'Receipt updated successfully.',
            'id'      => (int) $receiptId,
        ]);
    }

    /**
     * POST loans/receipts/delete/{receiptId} — reverses and removes whatever
     * bank deposit the receipt posted, then deletes the receipt row itself.
     * A receipt has no downstream dependents (nothing else references
     * loan_receipts.id — it never touches outstanding_principal), so this
     * mirrors Loans::deletePayment()'s already-proven reversal architecture
     * rather than adding a separate cancelled/void status.
     */
    public function deleteReceipt($receiptId)
    {
        $receiptModel = new LoanReceiptModel();
        $existing     = $receiptModel->find((int) $receiptId);

        if (! $existing) {
            return $this->_error(['Receipt not found.'], 404);
        }

        $loanId = (int) $existing['loan_id'];
        $loan   = (new LoanModel())->find($loanId);
        $db     = \Config\Database::connect();
        $db->transStart();

        $old = null;

        try {
            (new LoanModel())->outstanding($loanId, true); // row lock

            $old = $receiptModel->lockedRow((int) $receiptId);

            if (! $old) {
                throw new \RuntimeException('Receipt not found.', self::RULE_CODE);
            }

            $this->_deleteBankDeposit((int) $receiptId);
            $receiptModel->delete((int) $receiptId);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->_receiptFailure($e, 'delete the receipt');
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->_error(['Failed to delete the receipt due to a database error.'], 500);
        }

        audit_delete('Loan', self::REFERENCE_TYPE_RECEIPT, (int) $receiptId, $old['receipt_no'], $old, 'Receipt ' . $old['receipt_no'] . ' deleted from loan ' . ($loan['loan_no'] ?? $loanId) . '.');

        return $this->response->setJSON([
            'status'  => true,
            'message' => 'Receipt deleted successfully.',
        ]);
    }

    /** 422 for a business-rule failure raised by the receipt engine, 500 for anything else. */
    private function _receiptFailure(\Throwable $e, string $action)
    {
        if ($e->getCode() === self::RULE_CODE) {
            return $this->_error([$e->getMessage()], 422);
        }

        return $this->_error(["Failed to {$action}: " . $e->getMessage()], 500);
    }

    /**
     * Normalizes a receipt POST. An unparseable amount becomes null so
     * validation can name it.
     */
    private function _extractReceiptInput(): array
    {
        $post = $this->request;

        return [
            'loan_id'         => (int) $post->getPost('loan_id'),
            'receipt_date'    => trim((string) $post->getPost('receipt_date')),
            'payment_method'  => strtoupper(trim((string) $post->getPost('payment_method'))),
            'bank_account_id' => (int) $post->getPost('bank_account_id'),
            'amount'          => $this->_amount($post->getPost('amount')),
            'reference_no'    => trim((string) $post->getPost('reference_no')),
            'remarks'         => trim((string) $post->getPost('remarks')),
        ];
    }

    /**
     * Field rules of a receipt. The over-receipt hard cap needs the locked
     * loan/receipt totals, so it is enforced by storeReceipt()/updateReceipt()
     * inside the transaction, same split as _validatePayment()/_applyPayment().
     */
    private function _validateReceipt(array $input): array
    {
        $errors = [];

        if ($input['loan_id'] <= 0) {
            $errors[] = 'Loan is required.';
        }

        if ($input['receipt_date'] === '') {
            $errors[] = 'Receipt date is required.';
        } elseif (! $this->_isValidDate($input['receipt_date'])) {
            $errors[] = 'Receipt date is not a valid date.';
        }

        if ($input['amount'] === null) {
            $errors[] = 'Amount must be a valid number.';
        } elseif ($input['amount'] <= 0) {
            $errors[] = 'Amount must be greater than zero.';
        } elseif ($input['amount'] > self::MAX_AMOUNT) {
            $errors[] = 'Amount is too large.';
        } elseif (abs($input['amount'] - round($input['amount'], 2)) >= 0.000001) {
            $errors[] = 'Amount cannot have more than two decimal places.';
        }

        if (! in_array($input['payment_method'], ['CASH', 'BANK', 'CHEQUE', 'UPI', 'OTHER'], true)) {
            $errors[] = 'Payment method must be one of: Cash, Bank, Cheque, UPI, Other.';
        } elseif (BankTransactionModel::isBankMethod($input['payment_method'])) {
            if ($input['bank_account_id'] <= 0) {
                $errors[] = 'A bank account is required for Bank, Cheque and UPI receipts.';
            } else {
                $account = (new BankAccountModel())->find($input['bank_account_id']);

                if (! $account) {
                    $errors[] = 'The selected bank account was not found.';
                } elseif ((int) $account['is_active'] !== 1) {
                    $errors[] = 'The selected bank account is inactive.';
                }
            }
        }

        if (mb_strlen($input['reference_no']) > 100) {
            $errors[] = 'Reference number cannot be longer than 100 characters.';
        }

        return $errors;
    }

    /** The loan_receipts columns of a validated receipt (minus receipt_no, assigned separately). Cash/Other carry no bank account. */
    private function _receiptRow(array $input): array
    {
        $viaBank = BankTransactionModel::isBankMethod($input['payment_method']);

        return [
            'loan_id'         => $input['loan_id'],
            'receipt_date'    => $input['receipt_date'],
            'amount'          => round($input['amount'], 2),
            'payment_method'  => $input['payment_method'],
            'bank_account_id' => $viaBank ? $input['bank_account_id'] : null,
            'reference_no'    => $input['reference_no'] !== '' ? $input['reference_no'] : null,
            'remarks'         => $input['remarks'] !== '' ? $input['remarks'] : null,
        ];
    }

    // =========================================================
    // RECEIPT BANK POSTING (used by storeReceipt / updateReceipt / deleteReceipt)
    // =========================================================

    /**
     * The bank row a loan receipt will post: a DEPOSIT of amount dated the
     * receipt date, reference_type LOAN_DISBURSEMENT, reference_id the
     * receipt id (never loan_id — multiple receipts of one loan must each get
     * their own bank row, or createBankTransaction()'s duplicate guard would
     * reject every receipt after the first). Null unless the receipt went
     * through a bank (BANK/CHEQUE/UPI). Pure.
     */
    private function _bankDepositData(array $loan, array $receipt): ?array
    {
        if (! BankTransactionModel::isBankMethod($receipt['payment_method'] ?? null)) {
            return null;
        }
        if (round((float) $receipt['amount'], 2) <= 0) {
            return null;
        }

        return [
            'bank_account_id'  => (int) $receipt['bank_account_id'],
            'transaction_date' => $receipt['receipt_date'],
            'transaction_type' => 'DEPOSIT',
            'amount'           => round((float) $receipt['amount'], 2),
            'reference_type'   => self::REFERENCE_TYPE_RECEIPT,
            'reference_id'     => (int) $receipt['id'],
            'reference_no'     => ($receipt['reference_no'] ?? '') !== '' ? $receipt['reference_no'] : ($receipt['receipt_no'] ?? $loan['loan_no']),
            'remarks'          => 'Loan Received – ' . $loan['lender_name'],
            'created_by'       => session()->get('user_id'),
        ];
    }

    /** Posts the DEPOSIT for one receipt; no-op unless received through a bank. */
    private function _postBankDeposit(array $loan, array $receipt): void
    {
        $data = $this->_bankDepositData($loan, $receipt);

        if ($data !== null) {
            (new BankTransactionModel())->createBankTransaction($data);
        }
    }

    /** Reverses and removes whatever was posted for one receipt (0 rows is fine). */
    private function _deleteBankDeposit(int $receiptId): void
    {
        (new BankTransactionModel())->deleteBankTransaction(self::REFERENCE_TYPE_RECEIPT, $receiptId);
    }

    /**
     * Loan receipts formatted for the Loan Ledger's separate "Loan Receipts"
     * section — kept apart from the principal-driven statement above it
     * (Loan Received / Payment rows and the running Outstanding Balance
     * column), since a receipt never affects outstanding_principal and mixing
     * it into that Debit/Credit pair would invent an accounting meaning for
     * those columns they don't currently have.
     */
    private function _receiptLedgerRows(int $loanId): array
    {
        $rows = [];

        foreach ((new LoanReceiptModel())->forLoan($loanId) as $r) {
            $rows[] = [
                'date'       => $r['receipt_date'],
                'receipt_no' => $r['receipt_no'],
                'amount'     => round((float) $r['amount'], 2),
                'method'     => pm_label($r['payment_method'], 'Not recorded'),
                'account'    => $r['bank_name'] ? $r['bank_name'] . ' ' . $r['account_name'] : '',
                'reference'  => ($r['reference_no'] ?? '') !== '' ? $r['reference_no'] : '',
                'remarks'    => (string) ($r['remarks'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * GET loans/ledger/{loanId} — read-only statement. The disbursement opens
     * the account as a debit of the sanctioned amount; every payment is a
     * credit of its total with its principal and interest split out, and the
     * running outstanding balance is the sanctioned amount less the principal
     * paid so far.
     */
    public function ledger($loanId)
    {
        $loanModel = new LoanModel();
        $loan      = $loanModel->findWithTotals((int) $loanId);

        if (! $loan) {
            return redirect()->to('/loans')->with('error', 'Loan not found.');
        }

        $sanctioned = round((float) $loan['sanctioned_amount'], 2);
        $running    = $sanctioned;
        $rows       = [[
            'date'      => $loan['start_date'],
            'kind'      => 'DISBURSEMENT',
            'label'     => 'Loan Received',
            'reference' => $loan['loan_no'],
            'method'    => '',
            'debit'     => $sanctioned,
            'credit'    => 0.00,
            'balance'   => $running,
        ]];
        $credit = 0.0;

        foreach ((new LoanPaymentModel())->forLoan((int) $loanId, true) as $p) {
            $running = round($running - (float) $p['total_paid'], 2);
            $credit += (float) $p['total_paid'];

            $rows[] = [
                'date'      => $p['payment_date'],
                'kind'      => 'PAYMENT',
                'label'     => $p['loan_emi_id'] ? 'Payment (old EMI ref. #' . (int) $p['emi_no'] . ')' : 'Payment',
                'reference' => ($p['reference_no'] ?? '') !== '' ? $p['reference_no'] : '',
                'method'    => pm_label($p['payment_method'], 'Not recorded'),
                'account'   => $p['bank_name'] ? $p['bank_name'] . ' ' . $p['account_name'] : '',
                'debit'     => 0.00,
                'credit'    => round((float) $p['total_paid'], 2),
                'balance'   => $running,
            ];
        }

        $counts = \Config\Database::connect()->query(
            "SELECT COUNT(*) AS total, SUM(payment_status = 'PAID') AS paid FROM loan_emis WHERE loan_id = ?",
            [(int) $loanId]
        )->getRowArray();

        $totalReceived = (new LoanReceiptModel())->totalReceived((int) $loanId);

        return view('loans/ledger', [
            'title'        => 'Loan Ledger ' . $loan['loan_no'],
            'loan'         => $loan,
            'rows'         => $rows,
            'total_credit' => round($credit, 2),
            'emis_total'   => (int) $counts['total'],
            'emis_paid'    => (int) $counts['paid'],
            'emis_pending' => (int) $counts['total'] - (int) $counts['paid'],
            'receipt_rows' => $this->_receiptLedgerRows((int) $loanId),
            'total_received' => $totalReceived,
            'remaining_to_receive' => round($sanctioned - $totalReceived, 2),
        ]);
    }

    /**
     * GET loans/export/pdf/{loanId} — Release 4.8.7A. Same statement as
     * ledger() above (portrait); the row-building here is an exact copy of
     * ledger()'s so the PDF always matches the screen. ledger() itself is
     * left untouched per this release's file-scope restriction on Loans.php.
     */
    public function exportLedgerPdf($loanId)
    {
        $loanModel = new LoanModel();
        $loan      = $loanModel->findWithTotals((int) $loanId);

        if (! $loan) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Loan not found.');
        }

        $sanctioned = round((float) $loan['sanctioned_amount'], 2);
        $running    = $sanctioned;
        $rows       = [[
            'date'      => $loan['start_date'],
            'kind'      => 'DISBURSEMENT',
            'label'     => 'Loan Received',
            'reference' => $loan['loan_no'],
            'method'    => '',
            'debit'     => $sanctioned,
            'credit'    => 0.00,
            'balance'   => $running,
        ]];
        $credit = 0.0;

        foreach ((new LoanPaymentModel())->forLoan((int) $loanId, true) as $p) {
            $running = round($running - (float) $p['total_paid'], 2);
            $credit += (float) $p['total_paid'];

            $rows[] = [
                'date'      => $p['payment_date'],
                'kind'      => 'PAYMENT',
                'label'     => $p['loan_emi_id'] ? 'Payment (old EMI ref. #' . (int) $p['emi_no'] . ')' : 'Payment',
                'reference' => ($p['reference_no'] ?? '') !== '' ? $p['reference_no'] : '',
                'method'    => pm_label($p['payment_method'], 'Not recorded'),
                'account'   => $p['bank_name'] ? $p['bank_name'] . ' ' . $p['account_name'] : '',
                'debit'     => 0.00,
                'credit'    => round((float) $p['total_paid'], 2),
                'balance'   => $running,
            ];
        }

        $counts = \Config\Database::connect()->query(
            "SELECT COUNT(*) AS total, SUM(payment_status = 'PAID') AS paid FROM loan_emis WHERE loan_id = ?",
            [(int) $loanId]
        )->getRowArray();

        (new PdfReport())->render('pdf/loan_ledger', [
            'loan'             => $loan,
            'rows'             => $rows,
            'total_credit'     => round($credit, 2),
            'emis_total'       => (int) $counts['total'],
            'emis_paid'        => (int) $counts['paid'],
            'emis_pending'     => (int) $counts['total'] - (int) $counts['paid'],
        ], [
            'title'       => 'Loan Ledger ' . $loan['loan_no'],
            'orientation' => 'portrait',
        ]);
    }

    /**
     * GET loans/ledger/export/excel/{loanId} — Release 4.8.7B. Same
     * statement as ledger()/exportLedgerPdf() above (portrait); the
     * row-building here is an exact copy of theirs so Excel always matches
     * the screen and PDF. ledger() itself is left untouched per this
     * release's file-scope restriction on Loans.php.
     */
    public function exportLedgerExcel($loanId)
    {
        $loanModel = new LoanModel();
        $loan      = $loanModel->findWithTotals((int) $loanId);

        if (! $loan) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Loan not found.');
        }

        $sanctioned = round((float) $loan['sanctioned_amount'], 2);
        $running    = $sanctioned;
        $rows       = [[
            'date'      => $loan['start_date'],
            'kind'      => 'DISBURSEMENT',
            'label'     => 'Loan Received',
            'reference' => $loan['loan_no'],
            'method'    => '',
            'debit'     => $sanctioned,
            'credit'    => 0.00,
            'balance'   => $running,
        ]];

        foreach ((new LoanPaymentModel())->forLoan((int) $loanId, true) as $p) {
            $running = round($running - (float) $p['total_paid'], 2);

            $rows[] = [
                'date'      => $p['payment_date'],
                'kind'      => 'PAYMENT',
                'label'     => $p['loan_emi_id'] ? 'Payment (old EMI ref. #' . (int) $p['emi_no'] . ')' : 'Payment',
                'reference' => ($p['reference_no'] ?? '') !== '' ? $p['reference_no'] : '',
                'method'    => pm_label($p['payment_method'], 'Not recorded'),
                'account'   => $p['bank_name'] ? $p['bank_name'] . ' ' . $p['account_name'] : '',
                'debit'     => 0.00,
                'credit'    => round((float) $p['total_paid'], 2),
                'balance'   => $running,
            ];
        }

        $excelRows = [];
        foreach ($rows as $row) {
            $excelRows[] = [
                $row['date'], $row['kind'], $row['reference'], $row['label'], $row['method'] . (($row['account'] ?? '') !== '' ? ' — ' . $row['account'] : ''),
                $row['debit'], $row['credit'], $row['balance'],
            ];
        }

        (new ExcelReport())->ledger(
            'Loan Ledger - ' . $loan['loan_no'],
            [],
            [
                'Loan No'           => $loan['loan_no'],
                'Lender'            => $loan['lender_name'],
                'Sanctioned Amount' => pdf_currency($sanctioned),
                'Status'            => $loan['status'],
            ],
            0.0,
            ['Date', 'Type', 'Reference', 'Description', 'Method', 'Debit', 'Credit', 'Balance'],
            $excelRows,
            ['date', 'text', 'text', 'text', 'text', 'currency', 'currency', 'currency'],
            7,
            $running,
            [5, 6],
            'portrait'
        )->stream('loan_ledger_' . preg_replace('/[^a-z0-9]+/i', '_', $loan['loan_no']) . '_' . date('Ymd_His'));
    }

    /** 422 for a business-rule failure raised by the engine, 500 for anything else. */
    private function _paymentFailure(\Throwable $e, string $action)
    {
        if ($e->getCode() === self::RULE_CODE) {
            return $this->_error([$e->getMessage()], 422);
        }

        return $this->_error(["Failed to {$action}: " . $e->getMessage()], 500);
    }

    // =========================================================
    // PAYMENT INPUT + VALIDATION
    // =========================================================

    /**
     * Normalizes a payment POST. An unparseable Payment Amount becomes null so
     * validation can name it.
     */
    private function _extractPaymentInput(): array
    {
        $post = $this->request;

        // Release 4.9.0BA: one manual Payment Amount. No principal, interest or EMI is read.
        return [
            'loan_id'         => (int) $post->getPost('loan_id'),
            'payment_date'    => trim((string) $post->getPost('payment_date')),
            'payment_method'  => strtoupper(trim((string) $post->getPost('payment_method'))),
            'bank_account_id' => (int) $post->getPost('bank_account_id'),
            'payment_amount'  => $this->_amount($post->getPost('payment_amount')),
            'reference_no'    => trim((string) $post->getPost('reference_no')),
            'remarks'         => trim((string) $post->getPost('remarks')),
        ];
    }

    /**
     * Field rules of a payment. What needs the locked loan (closed loan,
     * overpaying the outstanding amount) is enforced by _assertPaymentFits()
     * inside the transaction.
     */
    private function _validatePayment(array $input): array
    {
        $errors = [];

        if ($input['loan_id'] <= 0) {
            $errors[] = 'Loan is required.';
        }

        if ($input['payment_date'] === '') {
            $errors[] = 'Payment date is required.';
        } elseif (! $this->_isValidDate($input['payment_date'])) {
            $errors[] = 'Payment date is not a valid date.';
        }

        $v = $input['payment_amount'];

        if ($v === null) {
            $errors[] = 'Payment amount must be a valid number.';
        } elseif ($v <= 0) {
            $errors[] = $v < 0 ? 'Payment amount cannot be negative.' : 'Payment amount must be greater than zero.';
        } elseif ($v > self::MAX_AMOUNT) {
            $errors[] = 'Payment amount is too large.';
        } elseif (abs($v - round($v, 2)) >= 0.000001) {
            $errors[] = 'Payment amount cannot have more than two decimal places.';
        }

        if (! in_array($input['payment_method'], ['CASH', 'BANK', 'CHEQUE', 'UPI', 'OTHER'], true)) {
            $errors[] = 'Payment method must be one of: Cash, Bank, Cheque, UPI, Other.';
        } elseif (BankTransactionModel::isBankMethod($input['payment_method'])) {
            if ($input['bank_account_id'] <= 0) {
                $errors[] = 'A bank account is required for Bank, Cheque and UPI payments.';
            } else {
                $account = (new BankAccountModel())->find($input['bank_account_id']);

                if (! $account) {
                    $errors[] = 'The selected bank account was not found.';
                } elseif ((int) $account['is_active'] !== 1) {
                    $errors[] = 'The selected bank account is inactive.';
                }
            }
        }

        if (mb_strlen($input['reference_no']) > 100) {
            $errors[] = 'Reference number cannot be longer than 100 characters.';
        }

        return $errors;
    }

    /**
     * The loan_payments columns of a validated payment. Cash/Other carry no bank
     * account. Release 4.9.0BA: total_paid is exactly the user's Payment Amount;
     * principal_paid / interest_paid are the legacy split columns, which a manual
     * payment does not know, so they are stored as 0 (never guessed). Editing a
     * historical payment without changing its amount keeps its old split and EMI
     * reference untouched; changing the amount drops the now-stale split.
     */
    private function _paymentRow(array $input, ?array $old = null): array
    {
        $viaBank = BankTransactionModel::isBankMethod($input['payment_method']);
        $amount  = round($input['payment_amount'], 2);
        $same    = $old !== null && $this->_cents((float) $old['total_paid']) === $this->_cents($amount);

        return [
            'loan_id'         => $input['loan_id'],
            'loan_emi_id'     => $old['loan_emi_id'] ?? null,
            'payment_date'    => $input['payment_date'],
            'payment_method'  => $input['payment_method'],
            'bank_account_id' => $viaBank ? $input['bank_account_id'] : null,
            'principal_paid'  => $same ? (float) $old['principal_paid'] : 0.00,
            'interest_paid'   => $same ? (float) $old['interest_paid'] : 0.00,
            'total_paid'      => $amount,
            'reference_no'    => $input['reference_no'] !== '' ? $input['reference_no'] : null,
            'remarks'         => $input['remarks'] !== '' ? $input['remarks'] : null,
        ];
    }

    // =========================================================
    // LOAN ENGINE (outstanding / paid / status)
    // =========================================================

    /**
     * Brings a loan header in line with its payments (loan_payments is the
     * source of truth). Release 4.9.0BA: outstanding = sanctioned - SUM(total_paid)
     * (never below zero) and status is ACTIVE until that reaches zero, then
     * CLOSED. The legacy total_principal_paid / total_interest_paid columns keep
     * the sums of the legacy split columns (0 for manual payments); nothing reads
     * them for the outstanding figure. Returns the new outstanding and status.
     */
    private function _recalculateLoan(int $loanId, bool $fromPayments = true): array
    {
        $db = \Config\Database::connect();

        $sums = $db->query(
            'SELECT COALESCE(SUM(principal_paid), 0) AS principal, COALESCE(SUM(interest_paid), 0) AS interest,
                    COALESCE(SUM(total_paid), 0) AS paid
             FROM loan_payments WHERE loan_id = ?',
            [$loanId]
        )->getRowArray();

        $loan = $db->table('loans')->where('id', $loanId)->get()->getRowArray();

        if (! $loan) {
            throw new \RuntimeException("Loan #{$loanId} was not found while recalculating it.");
        }

        $outstanding = round((float) $loan['sanctioned_amount'] - (float) $sums['paid'], 2);
        if ($outstanding <= 0.004) {
            $outstanding = 0.00;
        }
        $status = $outstanding > 0 ? 'ACTIVE' : 'CLOSED';

        $db->table('loans')->where('id', $loanId)->update([
            'total_principal_paid'  => round((float) $sums['principal'], 2),
            'total_interest_paid'   => round((float) $sums['interest'], 2),
            'outstanding_principal' => $outstanding,
            'status'                => $status,
            'updated_at'            => date('Y-m-d H:i:s'),
        ]);

        return ['outstanding_principal' => $outstanding, 'status' => $status];
    }

    /**
     * Guards one payment amount against the locked loan: the loan must be open
     * (unless $allowClosed, used by an edit) and the amount must not exceed what
     * is still outstanding, not counting payment $excludePaymentId (the one being
     * edited). Row-locks the loan, so it must run inside the caller's transaction.
     */
    private function _assertPaymentFits(int $loanId, float $amount, ?int $excludePaymentId, bool $allowClosed): void
    {
        $amount = round($amount, 2);
        $info   = (new LoanModel())->outstanding($loanId, true, $excludePaymentId);

        if ($info === null) {
            throw new \RuntimeException("Loan #{$loanId} was not found while applying a payment.");
        }
        if ($amount <= 0) {
            throw new \RuntimeException('A loan payment must have a positive amount.', self::RULE_CODE);
        }
        if ($info['status'] === 'CLOSED' && ! $allowClosed) {
            throw new \RuntimeException("Loan {$info['loan_no']} is closed, so no further payments can be recorded.", self::RULE_CODE);
        }
        if ($amount > $info['outstanding_principal'] + 0.004) {
            throw new \RuntimeException('Payment amount exceeds the outstanding amount of ' . number_format($info['outstanding_principal'], 2) . '.', self::RULE_CODE);
        }
    }

    /** True when any payment has been recorded (or applied) against the loan. */
    private function _hasPayments(int $loanId): bool
    {
        $db = \Config\Database::connect();

        if ($db->table('loan_payments')->where('loan_id', $loanId)->countAllResults() > 0) {
            return true;
        }

        $loan = $db->table('loans')->select('total_principal_paid, total_interest_paid')->where('id', $loanId)->get()->getRowArray();

        return $loan && ((float) $loan['total_principal_paid'] > 0 || (float) $loan['total_interest_paid'] > 0);
    }

    // =========================================================
    // BANK POSTING (used by storePayment / updatePayment / deletePayment)
    // =========================================================

    /**
     * The bank row a loan payment will post: a WITHDRAWAL of total_paid dated
     * the payment date, reference_type LOAN_PAYMENT, reference_id the payment
     * id, reference_no the payment's own reference (else the loan number).
     * Null unless the payment went through a bank (BANK/CHEQUE/UPI). Pure.
     */
    private function _bankWithdrawalData(array $loan, array $payment): ?array
    {
        if (! BankTransactionModel::isBankMethod($payment['payment_method'] ?? null)) {
            return null;
        }
        if (round((float) $payment['total_paid'], 2) <= 0) {
            return null;
        }

        return [
            'bank_account_id'  => (int) $payment['bank_account_id'],
            'transaction_date' => $payment['payment_date'],
            'transaction_type' => 'WITHDRAWAL',
            'amount'           => round((float) $payment['total_paid'], 2),
            'reference_type'   => self::REFERENCE_TYPE,
            'reference_id'     => (int) $payment['id'],
            'reference_no'     => ($payment['reference_no'] ?? '') !== '' ? $payment['reference_no'] : $loan['loan_no'],
            'remarks'          => 'Loan Payment – ' . $loan['lender_name'],
            'created_by'       => session()->get('user_id'),
        ];
    }

    /** Posts the WITHDRAWAL for one payment; no-op unless paid through a bank. */
    private function _postBankWithdrawal(array $loan, array $payment): void
    {
        $data = $this->_bankWithdrawalData($loan, $payment);

        if ($data !== null) {
            (new BankTransactionModel())->createBankTransaction($data);
        }
    }

    /** Reverses and removes whatever was posted for one payment (0 rows is fine). */
    private function _deleteBankWithdrawal(int $paymentId): void
    {
        (new BankTransactionModel())->deleteBankTransaction(self::REFERENCE_TYPE, $paymentId);
    }

    // =========================================================
    // INPUT + VALIDATION
    // =========================================================

    /**
     * Normalizes the POST body. An unparseable number becomes null so
     * validation can name it; a blank one is 0 (blank EMI amount / interest
     * mean "calculate it" / "no interest" downstream).
     */
    private function _extractInput(): array
    {
        $type = strtoupper(trim((string) $this->request->getPost('loan_type')));
        $tenure = trim((string) $this->request->getPost('tenure_months'));

        return [
            'loan_no'           => trim((string) $this->request->getPost('loan_no')),
            'lender_name'       => trim((string) $this->request->getPost('lender_name')),
            'loan_type'         => $type,
            'account_number'    => trim((string) $this->request->getPost('account_number')),
            'sanctioned_amount' => $this->_amount($this->request->getPost('sanctioned_amount')),
            'interest_rate'     => $this->_amount($this->request->getPost('interest_rate')),
            'tenure_months'     => $tenure === '' ? 0 : (ctype_digit($tenure) ? (int) $tenure : null),
            'start_date'        => trim((string) $this->request->getPost('start_date')),
            'remarks'           => trim((string) $this->request->getPost('remarks')),
        ];
    }

    /** Blank -> 0.0; a number -> float; anything else -> null (invalid). */
    private function _amount($raw): ?float
    {
        if ($raw === null || $raw === '') {
            return 0.0;
        }

        return is_numeric($raw) ? (float) $raw : null;
    }

    private function _cents(float $value): int
    {
        return (int) round($value * 100);
    }

    private function _isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);

        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * The four figures the EMI schedule is built from: sanctioned amount,
     * interest rate, tenure and start date. Shared by loan save and the
     * schedule preview.
     */
    private function _validateTerms(array $input): array
    {
        $errors = [];

        $amount = $input['sanctioned_amount'];
        if ($amount === null) {
            $errors[] = 'Sanctioned amount must be a valid number.';
        } elseif ($amount <= 0) {
            $errors[] = 'Sanctioned amount must be greater than zero.';
        } elseif ($amount > self::MAX_AMOUNT) {
            $errors[] = 'Sanctioned amount is too large.';
        } elseif (abs($amount - round($amount, 2)) >= 0.000001) {
            $errors[] = 'Sanctioned amount cannot have more than two decimal places.';
        }

        $rate = $input['interest_rate'];
        if ($rate === null) {
            $errors[] = 'Interest rate must be a valid number.';
        } elseif ($rate < 0) {
            $errors[] = 'Interest rate cannot be negative.';
        } elseif ($rate > self::MAX_RATE) {
            $errors[] = 'Interest rate cannot be more than ' . self::MAX_RATE . '% a year.';
        } elseif (abs($rate - round($rate, 3)) >= 0.0000001) {
            $errors[] = 'Interest rate cannot have more than three decimal places.';
        }

        // Release 4.9.0AY: rate and tenure are optional (blank = 0 = not given).
        $tenure = $input['tenure_months'];
        if ($tenure === null) {
            $errors[] = 'Tenure must be a whole number of months.';
        } elseif ($tenure > self::MAX_TENURE) {
            $errors[] = 'Tenure cannot be more than ' . self::MAX_TENURE . ' months.';
        }

        if ($input['start_date'] === '') {
            $errors[] = 'Start date is required.';
        } elseif (! $this->_isValidDate($input['start_date'])) {
            $errors[] = 'Start date is not a valid date.';
        }

        return $errors;
    }

    /**
     * Everything that needs no schedule: loan number, lender, type, the terms,
     * end date and the bank account. $existing is the stored loan on update.
     */
    private function _validateInput(array $input, ?array $existing): array
    {
        $errors = [];

        if ($input['loan_no'] !== '') {
            if (mb_strlen($input['loan_no']) > 20) {
                $errors[] = 'Loan number cannot be longer than 20 characters.';
            } else {
                $taken = \Config\Database::connect()->table('loans')->where('loan_no', $input['loan_no']);
                if ($existing) {
                    $taken->where('id <>', (int) $existing['id']);
                }
                if ($taken->countAllResults() > 0) {
                    $errors[] = "Loan number {$input['loan_no']} is already in use.";
                }
            }
        }

        if ($input['lender_name'] === '') {
            $errors[] = 'Lender name is required.';
        } elseif (mb_strlen($input['lender_name']) > 150) {
            $errors[] = 'Lender name cannot be longer than 150 characters.';
        }

        // Active master types; an edit may also keep the loan's current type even if it was since deactivated.
        $allowedTypes = LoanTypeModel::activeLabels();
        if ($existing && isset($existing['loan_type'])) {
            $allowedTypes += array_intersect_key(LoanTypeModel::labels(), [$existing['loan_type'] => true]);
        }
        if (! isset($allowedTypes[$input['loan_type']])) {
            $errors[] = 'Loan type must be one of: ' . implode(', ', $allowedTypes) . '.';
        }

        if (mb_strlen($input['account_number']) > 50) {
            $errors[] = 'Account number cannot be longer than 50 characters.';
        }

        $errors = array_merge($errors, $this->_validateTerms($input));

        return $errors;
    }

    /**
     * Release 4.9.0AY: validates, then builds the loan header columns (minus
     * loan_no and the running figures). No schedule, no EMI amount and no
     * bank account: the loan master is informational and the bank account
     * belongs to each payment / receipt. On update the legacy bank_account_id,
     * emi_amount and end_date columns are simply not written.
     */
    private function _prepare(array $input, ?array $existing): array
    {
        $errors = $this->_validateInput($input, $existing);

        if ($errors) {
            return ['errors' => $errors];
        }

        $header = [
            'lender_name'       => $input['lender_name'],
            'loan_type'         => $input['loan_type'],
            'account_number'    => $input['account_number'] !== '' ? $input['account_number'] : null,
            'sanctioned_amount' => $input['sanctioned_amount'],
            'interest_rate'     => $input['interest_rate'],
            'tenure_months'     => $input['tenure_months'],
            'start_date'        => $input['start_date'],
            'remarks'           => $input['remarks'] !== '' ? $input['remarks'] : null,
        ];

        if (! $existing) {
            $header['emi_amount'] = 0;
        }

        return ['errors' => [], 'header' => $header];
    }

    // =========================================================
    // READ HELPERS
    // =========================================================

    /** One loan with its bank account, outstanding snapshot, schedule, payment and receipt history. */
    private function _loadLoan(int $id): ?array
    {
        $loanModel = new LoanModel();
        $loan      = $loanModel->findWithTotals($id);

        if (! $loan) {
            return null;
        }

        $receiptModel  = new LoanReceiptModel();
        $totalReceived = $receiptModel->totalReceived($id);

        return [
            'loan'                 => $loan,
            'bank_account'         => $loan['bank_account_id'] ? (new BankAccountModel())->find((int) $loan['bank_account_id']) : null,
            'outstanding'          => $loanModel->outstanding($id),
            'payments'             => (new LoanPaymentModel())->forLoan($id),
            'schedule_editable'    => ! $this->_hasPayments($id),
            'receipts'             => $receiptModel->forLoan($id),
            'total_received'       => $totalReceived,
            'remaining_to_receive' => round((float) $loan['sanctioned_amount'] - $totalReceived, 2),
            'receipt_available'    => ! in_array($loan['loan_type'], self::RECEIPT_EXCLUDED_TYPES, true),
        ];
    }

    /** Accounts a loan may be serviced from (active only). */
    private function _activeBankAccounts(): array
    {
        return (new BankAccountModel())->where('is_active', 1)->orderBy('bank_name', 'ASC')->findAll();
    }

    private function _error(array $errors, int $status)
    {
        return $this->response->setJSON(['status' => false, 'errors' => $errors])->setStatusCode($status);
    }
}
