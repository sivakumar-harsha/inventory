<?php

namespace App\Controllers;

use App\Libraries\ExcelReport;
use App\Libraries\PdfReport;
use App\Models\BankAccountModel;
use App\Models\BankTransactionModel;
use App\Models\LoanEmiModel;
use App\Models\LoanModel;
use App\Models\LoanPaymentModel;
use CodeIgniter\Controller;

/**
 * Release 4.8.5A: Loan Management foundation. Release 4.8.5B put pages on the
 * GET endpoints (index/create/view/edit render views/loans); the POST endpoints
 * (store/update/delete/generate-schedule) still answer with JSON.
 *
 * A loan is money the company has borrowed (bank, NBFC, friends, vehicle
 * finance, overdraft). Creating one generates its whole reducing-balance EMI
 * schedule (loan_emis); the header keeps the running figures:
 *
 *     outstanding_principal = sanctioned_amount - total_principal_paid
 *
 * and status is ACTIVE until outstanding_principal reaches zero, then CLOSED
 * automatically (and back to ACTIVE if a payment is taken back).
 *
 * Schedule: monthly reducing balance. EMI = P*r*(1+r)^n / ((1+r)^n - 1) with
 * r = annual rate / 12 / 100 (P/n at 0%), rounded to 2 decimals; each month's
 * interest is opening * r rounded to 2 decimals; the LAST instalment absorbs
 * whatever rounding left, so principal always sums exactly to the sanctioned
 * amount. The first EMI falls due one month after start_date, and every due
 * date is the monthly anniversary of start_date (clamped to the month's last
 * day, e.g. 31 Jan -> 28 Feb -> 31 Mar).
 *
 * Payments (Release 4.8.5C): payments()/storePayment()/updatePayment()/
 * deletePayment() drive the engine (_applyPayment(), _restorePayment(),
 * _recalculateLoan()) and the bank posting (a WITHDRAWAL with reference_type
 * LOAN_PAYMENT and reference_id the loan_payments.id, only for BANK/CHEQUE/UPI),
 * exactly as CustomerPayments/ServiceReceipts structure theirs. The engine and
 * the bank helpers never open a DB transaction — each endpoint owns it, so any
 * failure rolls the loan, the EMI, the payment row and the bank balance back
 * together. ledger() is the read-only loan statement.
 *
 * Every other module is untouched.
 */
class Loans extends Controller
{
    private const LOAN_TYPES     = ['BANK', 'PERSONAL', 'VEHICLE', 'OD', 'OTHER'];
    private const BANK_REQUIRED  = ['BANK', 'OD'];
    private const BANK_METHODS   = ['BANK', 'CHEQUE', 'UPI'];
    private const REFERENCE_TYPE = 'LOAN_PAYMENT';

    // Exception code the payment engine puts on a business-rule failure
    // (overpayment, closed loan...), so an endpoint answers 422 with the
    // message instead of a 500.
    private const RULE_CODE = 422;

    // DECIMAL(15,2). MySQL/MariaDB in non-strict mode silently clamps an
    // overflowing value instead of failing, so it is rejected here instead.
    private const MAX_AMOUNT = 9999999999999.99;
    private const MAX_TENURE = 600;   // 50 years
    private const MAX_RATE   = 100.0; // annual %

    // How far a typed-in EMI may sit from the calculated one (lenders round it).
    private const EMI_TOLERANCE = 1.00;

    // =========================================================
    // ENDPOINTS
    // =========================================================

    public function index()
    {
        $db = \Config\Database::connect();

        $loans = $db->query("
            SELECT l.*, ba.bank_name, ba.account_name,
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
            'loan_types'            => self::LOAN_TYPES,
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
            'loan_types'    => self::LOAN_TYPES,
            'bank_required' => self::BANK_REQUIRED,
            'bank_accounts' => $this->_activeBankAccounts(),
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

            $this->_insertSchedule($loanId, $prepared['schedule']);
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
            'emi_count' => count($prepared['schedule']),
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

        // The account already on the loan must stay selectable even if it has
        // been deactivated since, or saving would silently drop it.
        $accounts = $this->_activeBankAccounts();
        $current  = $data['bank_account'];

        if ($current && ! in_array((int) $current['id'], array_map('intval', array_column($accounts, 'id')), true)) {
            $accounts[] = $current;
        }

        return view('loans/edit', [
            'title'         => 'Edit Loan ' . $data['loan']['loan_no'],
            'loan_types'    => self::LOAN_TYPES,
            'bank_required' => self::BANK_REQUIRED,
            'bank_accounts' => $accounts,
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

            // With payments on record the schedule they were applied against
            // must not move, so the terms that drive it are frozen.
            if ($hasPayments && $this->_termsChanged($loan, $prepared['header'])) {
                $db->transRollback();
                return $this->_error(['This loan already has payments, so its sanctioned amount, interest rate, tenure and start date can no longer be changed.'], 422);
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

            if (! $hasPayments) {
                (new LoanEmiModel())->where('loan_id', (int) $id)->delete();
                $this->_insertSchedule((int) $id, $prepared['schedule']);
            }
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

    /**
     * POST loans/generate-schedule — { sanctioned_amount, interest_rate,
     * tenure_months, start_date }. Previews the schedule the loan form will
     * save; writes nothing.
     */
    public function ajaxGenerateSchedule()
    {
        $input  = $this->_extractInput();
        $errors = $this->_validateTerms($input);

        if ($errors) {
            return $this->_error($errors, 422);
        }

        try {
            $schedule = $this->_generateSchedule($input['sanctioned_amount'], $input['interest_rate'], $input['tenure_months'], $input['start_date']);
        } catch (\RuntimeException $e) {
            return $this->_error([$e->getMessage()], 422);
        }

        $interest = round(array_sum(array_column($schedule, 'interest_amount')), 2);

        return $this->response->setJSON([
            'status'         => true,
            'emi_amount'     => $this->_calculateEmi($input['sanctioned_amount'], $input['interest_rate'], $input['tenure_months']),
            'total_interest' => $interest,
            'total_payable'  => round($input['sanctioned_amount'] + $interest, 2),
            'end_date'       => end($schedule)['due_date'],
            'schedule'       => $schedule,
        ]);
    }

    // =========================================================
    // PAYMENT ENDPOINTS (Release 4.8.5C)
    // =========================================================

    /** GET loans/payments/{loanId} — the EMI payment screen: schedule, pay forms, payment history. */
    public function payments($loanId)
    {
        $loanModel = new LoanModel();
        $loan      = $loanModel->find((int) $loanId);

        if (! $loan) {
            return redirect()->to('/loans')->with('error', 'Loan not found.');
        }

        return view('loans/payments', [
            'title'         => 'Payments ' . $loan['loan_no'],
            'loan'          => $loan,
            'outstanding'   => $loanModel->outstanding((int) $loanId),
            'emis'          => (new LoanEmiModel())->emiListWithRemaining((int) $loanId),
            'payments'      => (new LoanPaymentModel())->forLoan((int) $loanId),
            'bank_accounts' => $this->_activeBankAccounts(),
            'bank_methods'  => self::BANK_METHODS,
        ]);
    }

    /**
     * POST loans/payments/store — one payment against one loan: an instalment
     * (loan_emi_id, part or all of what it still owes) or a prepayment
     * (loan_emi_id blank). Payment row, EMI, loan totals and the bank
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

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $state = $this->_applyPayment($input['loan_id'], $input['loan_emi_id'], $input['principal_paid'], $input['interest_paid'], $input['payment_date']);

            $row                = $this->_paymentRow($input);
            $row['created_by']  = session()->get('user_id');
            $paymentModel       = new LoanPaymentModel();

            if (! $paymentModel->insert($row)) {
                throw new \RuntimeException(implode(' ', $paymentModel->errors()) ?: 'The payment could not be saved.');
            }
            $paymentId = (int) $paymentModel->getInsertID();

            $this->_postBankWithdrawal($loan, $row + ['id' => $paymentId]);
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

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            (new LoanModel())->outstanding($loanId, true); // row lock, before the payment row

            $old = $paymentModel->lockedRow((int) $paymentId);

            if (! $old) {
                throw new \RuntimeException('Payment not found.', self::RULE_CODE);
            }

            $this->_restorePayment($loanId, $old['loan_emi_id'] !== null ? (int) $old['loan_emi_id'] : null, (float) $old['principal_paid'], (float) $old['interest_paid']);
            $this->_deleteBankWithdrawal((int) $paymentId);

            $state = $this->_applyPayment($loanId, $input['loan_emi_id'], $input['principal_paid'], $input['interest_paid'], $input['payment_date'], true);
            $row   = $this->_paymentRow($input);

            if (! $paymentModel->update((int) $paymentId, $row)) {
                throw new \RuntimeException(implode(' ', $paymentModel->errors()) ?: 'The payment could not be updated.');
            }

            $this->_postBankWithdrawal($loan, $row + ['id' => (int) $paymentId]);
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

            $state = $this->_restorePayment($loanId, $old['loan_emi_id'] !== null ? (int) $old['loan_emi_id'] : null, (float) $old['principal_paid'], (float) $old['interest_paid']);
            $this->_deleteBankWithdrawal((int) $paymentId);
            $paymentModel->delete((int) $paymentId);
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
        $loan      = $loanModel->find((int) $loanId);

        if (! $loan) {
            return redirect()->to('/loans')->with('error', 'Loan not found.');
        }

        $sanctioned = round((float) $loan['sanctioned_amount'], 2);
        $running    = $sanctioned;
        $rows       = [[
            'date'      => $loan['start_date'],
            'kind'      => 'DISBURSEMENT',
            'label'     => 'Loan Disbursement',
            'reference' => $loan['loan_no'],
            'method'    => '',
            'debit'     => $sanctioned,
            'credit'    => 0.00,
            'principal' => 0.00,
            'interest'  => 0.00,
            'balance'   => $running,
        ]];
        $credit = $principal = $interest = 0.0;

        foreach ((new LoanPaymentModel())->forLoan((int) $loanId, true) as $p) {
            $running    = round($running - (float) $p['principal_paid'], 2);
            $credit    += (float) $p['total_paid'];
            $principal += (float) $p['principal_paid'];
            $interest  += (float) $p['interest_paid'];

            $rows[] = [
                'date'      => $p['payment_date'],
                'kind'      => $p['loan_emi_id'] ? 'EMI' : 'PREPAYMENT',
                'label'     => $p['loan_emi_id'] ? 'EMI #' . (int) $p['emi_no'] . ' Payment' : 'Prepayment',
                'reference' => ($p['reference_no'] ?? '') !== '' ? $p['reference_no'] : '',
                'method'    => $p['payment_method'] . ($p['bank_name'] ? ' — ' . $p['bank_name'] . ' ' . $p['account_name'] : ''),
                'debit'     => 0.00,
                'credit'    => round((float) $p['total_paid'], 2),
                'principal' => round((float) $p['principal_paid'], 2),
                'interest'  => round((float) $p['interest_paid'], 2),
                'balance'   => $running,
            ];
        }

        $counts = \Config\Database::connect()->query(
            "SELECT COUNT(*) AS total, SUM(payment_status = 'PAID') AS paid FROM loan_emis WHERE loan_id = ?",
            [(int) $loanId]
        )->getRowArray();

        return view('loans/ledger', [
            'title'        => 'Loan Ledger ' . $loan['loan_no'],
            'loan'         => $loan,
            'rows'         => $rows,
            'total_credit' => round($credit, 2),
            'total_principal' => round($principal, 2),
            'total_interest'  => round($interest, 2),
            'emis_total'   => (int) $counts['total'],
            'emis_paid'    => (int) $counts['paid'],
            'emis_pending' => (int) $counts['total'] - (int) $counts['paid'],
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
        $loan      = $loanModel->find((int) $loanId);

        if (! $loan) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Loan not found.');
        }

        $sanctioned = round((float) $loan['sanctioned_amount'], 2);
        $running    = $sanctioned;
        $rows       = [[
            'date'      => $loan['start_date'],
            'kind'      => 'DISBURSEMENT',
            'label'     => 'Loan Disbursement',
            'reference' => $loan['loan_no'],
            'method'    => '',
            'debit'     => $sanctioned,
            'credit'    => 0.00,
            'principal' => 0.00,
            'interest'  => 0.00,
            'balance'   => $running,
        ]];
        $credit = $principal = $interest = 0.0;

        foreach ((new LoanPaymentModel())->forLoan((int) $loanId, true) as $p) {
            $running    = round($running - (float) $p['principal_paid'], 2);
            $credit    += (float) $p['total_paid'];
            $principal += (float) $p['principal_paid'];
            $interest  += (float) $p['interest_paid'];

            $rows[] = [
                'date'      => $p['payment_date'],
                'kind'      => $p['loan_emi_id'] ? 'EMI' : 'PREPAYMENT',
                'label'     => $p['loan_emi_id'] ? 'EMI #' . (int) $p['emi_no'] . ' Payment' : 'Prepayment',
                'reference' => ($p['reference_no'] ?? '') !== '' ? $p['reference_no'] : '',
                'method'    => $p['payment_method'] . ($p['bank_name'] ? ' — ' . $p['bank_name'] . ' ' . $p['account_name'] : ''),
                'debit'     => 0.00,
                'credit'    => round((float) $p['total_paid'], 2),
                'principal' => round((float) $p['principal_paid'], 2),
                'interest'  => round((float) $p['interest_paid'], 2),
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
            'total_principal'  => round($principal, 2),
            'total_interest'   => round($interest, 2),
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
        $loan      = $loanModel->find((int) $loanId);

        if (! $loan) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Loan not found.');
        }

        $sanctioned = round((float) $loan['sanctioned_amount'], 2);
        $running    = $sanctioned;
        $rows       = [[
            'date'      => $loan['start_date'],
            'kind'      => 'DISBURSEMENT',
            'label'     => 'Loan Disbursement',
            'reference' => $loan['loan_no'],
            'method'    => '',
            'debit'     => $sanctioned,
            'credit'    => 0.00,
            'principal' => 0.00,
            'interest'  => 0.00,
            'balance'   => $running,
        ]];

        foreach ((new LoanPaymentModel())->forLoan((int) $loanId, true) as $p) {
            $running = round($running - (float) $p['principal_paid'], 2);

            $rows[] = [
                'date'      => $p['payment_date'],
                'kind'      => $p['loan_emi_id'] ? 'EMI' : 'PREPAYMENT',
                'label'     => $p['loan_emi_id'] ? 'EMI #' . (int) $p['emi_no'] . ' Payment' : 'Prepayment',
                'reference' => ($p['reference_no'] ?? '') !== '' ? $p['reference_no'] : '',
                'method'    => $p['payment_method'] . ($p['bank_name'] ? ' — ' . $p['bank_name'] . ' ' . $p['account_name'] : ''),
                'debit'     => 0.00,
                'credit'    => round((float) $p['total_paid'], 2),
                'principal' => round((float) $p['principal_paid'], 2),
                'interest'  => round((float) $p['interest_paid'], 2),
                'balance'   => $running,
            ];
        }

        $excelRows = [];
        foreach ($rows as $row) {
            $excelRows[] = [
                $row['date'], $row['kind'], $row['reference'], $row['label'], $row['method'],
                $row['debit'], $row['credit'], $row['principal'], $row['interest'], $row['balance'],
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
            ['Date', 'Type', 'Reference', 'Description', 'Method', 'Debit', 'Credit', 'Principal', 'Interest', 'Balance'],
            $excelRows,
            ['date', 'text', 'text', 'text', 'text', 'currency', 'currency', 'currency', 'currency', 'currency'],
            9,
            $running,
            [5, 6, 7, 8],
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
     * Normalizes a payment POST. An unparseable number becomes null so
     * validation can name it; blank principal/interest are 0, and a blank
     * total is 'not given' (it is then principal + interest).
     */
    private function _extractPaymentInput(): array
    {
        $post     = $this->request;
        $totalRaw = trim((string) $post->getPost('total_paid'));
        $emiId    = (int) $post->getPost('loan_emi_id');

        return [
            'loan_id'         => (int) $post->getPost('loan_id'),
            'loan_emi_id'     => $emiId > 0 ? $emiId : null,
            'payment_date'    => trim((string) $post->getPost('payment_date')),
            'payment_method'  => strtoupper(trim((string) $post->getPost('payment_method'))),
            'bank_account_id' => (int) $post->getPost('bank_account_id'),
            'principal_paid'  => $this->_amount($post->getPost('principal_paid')),
            'interest_paid'   => $this->_amount($post->getPost('interest_paid')),
            'total_given'     => $totalRaw !== '',
            'total_paid'      => $totalRaw === '' ? 0.0 : $this->_amount($totalRaw),
            'reference_no'    => trim((string) $post->getPost('reference_no')),
            'remarks'         => trim((string) $post->getPost('remarks')),
        ];
    }

    /**
     * Field rules of a payment. What needs the locked loan / EMI (closed loan,
     * overpaying an instalment, overpaying the principal) is enforced by
     * _applyPayment() inside the transaction.
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

        $amountsOk = true;
        foreach (['principal_paid' => 'Principal', 'interest_paid' => 'Interest'] as $key => $label) {
            $v = $input[$key];

            if ($v === null) {
                $errors[] = "{$label} must be a valid number.";
                $amountsOk = false;
            } elseif ($v < 0) {
                $errors[] = "{$label} cannot be negative.";
                $amountsOk = false;
            } elseif ($v > self::MAX_AMOUNT) {
                $errors[] = "{$label} is too large.";
                $amountsOk = false;
            } elseif (abs($v - round($v, 2)) >= 0.000001) {
                $errors[] = "{$label} cannot have more than two decimal places.";
                $amountsOk = false;
            }
        }

        if ($input['total_given'] && $input['total_paid'] === null) {
            $errors[] = 'Total paid must be a valid number.';
            $amountsOk = false;
        }

        if ($amountsOk) {
            $sum = round($input['principal_paid'] + $input['interest_paid'], 2);

            if ($sum <= 0) {
                $errors[] = 'Total payment must be greater than zero.';
            } elseif ($input['total_given'] && $this->_cents($input['total_paid']) !== $this->_cents($sum)) {
                $errors[] = 'Principal + Interest (' . number_format($sum, 2) . ') must equal the Total Paid (' . number_format($input['total_paid'], 2) . ').';
            }
        }

        if (! in_array($input['payment_method'], ['CASH', 'BANK', 'CHEQUE', 'UPI', 'OTHER'], true)) {
            $errors[] = 'Payment method must be one of: Cash, Bank, Cheque, UPI, Other.';
        } elseif (in_array($input['payment_method'], self::BANK_METHODS, true)) {
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

    /** The loan_payments columns of a validated payment. Cash/Other carry no bank account. */
    private function _paymentRow(array $input): array
    {
        $viaBank = in_array($input['payment_method'], self::BANK_METHODS, true);

        return [
            'loan_id'         => $input['loan_id'],
            'loan_emi_id'     => $input['loan_emi_id'],
            'payment_date'    => $input['payment_date'],
            'payment_method'  => $input['payment_method'],
            'bank_account_id' => $viaBank ? $input['bank_account_id'] : null,
            'principal_paid'  => round($input['principal_paid'], 2),
            'interest_paid'   => round($input['interest_paid'], 2),
            'total_paid'      => round($input['principal_paid'] + $input['interest_paid'], 2),
            'reference_no'    => $input['reference_no'] !== '' ? $input['reference_no'] : null,
            'remarks'         => $input['remarks'] !== '' ? $input['remarks'] : null,
        ];
    }

    // =========================================================
    // EMI SCHEDULE ENGINE
    // =========================================================

    /** Standard reducing-balance EMI, rounded to 2 decimals (P/n at 0% interest). */
    private function _calculateEmi(float $principal, float $annualRate, int $tenure): float
    {
        $r = $annualRate / 12 / 100;

        if ($r <= 0) {
            return round($principal / $tenure, 2);
        }

        $factor = pow(1 + $r, $tenure);

        return round($principal * $r * $factor / ($factor - 1), 2);
    }

    /**
     * Monthly reducing-balance schedule. One row per EMI:
     * emi_no, due_date, opening_balance, principal_amount, interest_amount,
     * emi_amount, closing_balance. All amounts are rounded to 2 decimals; the
     * last EMI takes the remaining balance as its principal so the schedule
     * closes at exactly zero. Pure — touches no table. Throws when the terms
     * cannot amortise (an amount too small for the tenure).
     */
    private function _generateSchedule(float $principal, float $annualRate, int $tenure, string $startDate): array
    {
        $emi = $this->_calculateEmi($principal, $annualRate, $tenure);

        if ($emi <= 0) {
            throw new \RuntimeException('The sanctioned amount is too small for this tenure.');
        }

        $r        = $annualRate / 12 / 100;
        $opening  = round($principal, 2);
        $schedule = [];

        for ($n = 1; $n <= $tenure; $n++) {
            $interest = round($opening * $r, 2);

            if ($n === $tenure) {
                $principalPart = $opening;
            } else {
                $principalPart = round($emi - $interest, 2);

                if ($principalPart <= 0 || $principalPart >= $opening) {
                    throw new \RuntimeException('The EMI schedule cannot be generated for these terms. Check the amount, interest rate and tenure.');
                }
            }

            $closing    = round($opening - $principalPart, 2);
            $schedule[] = [
                'emi_no'           => $n,
                'due_date'         => $this->_dueDate($startDate, $n),
                'opening_balance'  => $opening,
                'principal_amount' => $principalPart,
                'interest_amount'  => $interest,
                'emi_amount'       => round($principalPart + $interest, 2),
                'closing_balance'  => $closing,
            ];

            $opening = $closing;
        }

        return $schedule;
    }

    /**
     * The n-th monthly anniversary of $startDate. Always counted from the
     * original day (not chained), and clamped to the target month's last day:
     * 31 Jan + 1 month = 28/29 Feb, + 2 months = 31 Mar.
     */
    private function _dueDate(string $startDate, int $months): string
    {
        $start = new \DateTimeImmutable($startDate);
        $first = $start->modify('first day of this month')->modify("+{$months} months");
        $day   = min((int) $start->format('j'), (int) $first->format('t'));

        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day)->format('Y-m-d');
    }

    /** Writes a generated schedule as PENDING loan_emis rows. */
    private function _insertSchedule(int $loanId, array $schedule): void
    {
        $rows = [];

        foreach ($schedule as $emi) {
            $rows[] = [
                'loan_id'          => $loanId,
                'emi_no'           => $emi['emi_no'],
                'due_date'         => $emi['due_date'],
                'principal_amount' => $emi['principal_amount'],
                'interest_amount'  => $emi['interest_amount'],
                'emi_amount'       => $emi['emi_amount'],
                'paid_amount'      => 0,
                'balance_amount'   => $emi['emi_amount'],
                'payment_status'   => 'PENDING',
                'paid_date'        => null,
            ];
        }

        $emiModel = new LoanEmiModel();

        if ($emiModel->insertBatch($rows) !== count($rows)) {
            throw new \RuntimeException('The EMI schedule could not be saved: ' . (implode(' ', $emiModel->errors()) ?: 'database error') . '.');
        }
    }

    // =========================================================
    // LOAN ENGINE (outstanding / paid / status)
    // =========================================================

    /**
     * Brings a loan header in line with what has been paid: outstanding
     * principal = sanctioned - principal paid (never below zero), and status
     * ACTIVE until that reaches zero, then CLOSED. With $fromPayments the paid
     * totals are first re-summed from loan_payments (the source of truth),
     * which also repairs any drift. Returns the new outstanding and status.
     */
    private function _recalculateLoan(int $loanId, bool $fromPayments = false): array
    {
        $db = \Config\Database::connect();

        if ($fromPayments) {
            $sums = $db->query(
                'SELECT COALESCE(SUM(principal_paid), 0) AS principal, COALESCE(SUM(interest_paid), 0) AS interest
                 FROM loan_payments WHERE loan_id = ?',
                [$loanId]
            )->getRowArray();

            $db->table('loans')->where('id', $loanId)->update([
                'total_principal_paid' => round((float) $sums['principal'], 2),
                'total_interest_paid'  => round((float) $sums['interest'], 2),
            ]);
        }

        $loan = $db->table('loans')->where('id', $loanId)->get()->getRowArray();

        if (! $loan) {
            throw new \RuntimeException("Loan #{$loanId} was not found while recalculating it.");
        }

        $outstanding = round((float) $loan['sanctioned_amount'] - (float) $loan['total_principal_paid'], 2);
        if ($outstanding <= 0.004) {
            $outstanding = 0.00;
        }
        $status = $outstanding > 0 ? 'ACTIVE' : 'CLOSED';

        $db->table('loans')->where('id', $loanId)->update([
            'outstanding_principal' => $outstanding,
            'status'                => $status,
            'updated_at'            => date('Y-m-d H:i:s'),
        ]);

        return ['outstanding_principal' => $outstanding, 'status' => $status];
    }

    /**
     * Adds one payment to a loan: principal and interest paid go up, and — when
     * the payment settles an instalment ($emiId) — that instalment's paid and
     * balance amounts and status follow. Then the header is recalculated, which
     * closes the loan once no principal is outstanding. Row-locks the loan, so
     * it must run inside the caller's DB transaction. Throws if the payment
     * would pay more principal than is outstanding or more than an instalment
     * still owes, or if the loan is closed (unless $allowClosed, which an edit of
     * an existing payment uses — it has just taken the old payment back); a payment outside the schedule passes $emiId = null.
     * Returns the loan's new outstanding and status.
     */
    private function _applyPayment(int $loanId, ?int $emiId, float $principal, float $interest, string $paymentDate, bool $allowClosed = false): array
    {
        $principal = round($principal, 2);
        $interest  = round($interest, 2);
        $total     = round($principal + $interest, 2);

        if ($principal < 0 || $interest < 0 || $total <= 0) {
            throw new \RuntimeException('A loan payment must have a positive amount.', self::RULE_CODE);
        }

        $info = (new LoanModel())->outstanding($loanId, true);

        if ($info === null) {
            throw new \RuntimeException("Loan #{$loanId} was not found while applying a payment.");
        }
        if ($info['status'] === 'CLOSED' && ! $allowClosed) {
            throw new \RuntimeException("Loan {$info['loan_no']} is closed, so no further payments can be recorded.", self::RULE_CODE);
        }
        if ($principal > $info['outstanding_principal'] + 0.004) {
            throw new \RuntimeException('Principal paid exceeds the outstanding principal of ' . number_format($info['outstanding_principal'], 2) . '.', self::RULE_CODE);
        }

        $db = \Config\Database::connect();

        if ($emiId !== null) {
            $emi = $this->_lockEmi($loanId, $emiId);

            if ($emi['payment_status'] === 'PAID' || (float) $emi['balance_amount'] <= 0.004) {
                throw new \RuntimeException("EMI #{$emi['emi_no']} is already fully paid.", self::RULE_CODE);
            }
            if ($total > (float) $emi['balance_amount'] + 0.004) {
                throw new \RuntimeException("Payment exceeds the balance of EMI #{$emi['emi_no']} (" . number_format((float) $emi['balance_amount'], 2) . ').', self::RULE_CODE);
            }

            $this->_writeEmiPaid($emi, round((float) $emi['paid_amount'] + $total, 2), $paymentDate);
        }

        $db->table('loans')->where('id', $loanId)
            ->set('total_principal_paid', 'ROUND(total_principal_paid + ' . number_format($principal, 2, '.', '') . ', 2)', false)
            ->set('total_interest_paid', 'ROUND(total_interest_paid + ' . number_format($interest, 2, '.', '') . ', 2)', false)
            ->update();

        return $this->_recalculateLoan($loanId);
    }

    /**
     * Takes one payment back off a loan (used when a payment is edited or
     * deleted): the exact reverse of _applyPayment(). Paid totals are floored at
     * zero, the instalment goes back to PENDING/PARTIAL, and a loan that had
     * closed reopens as ACTIVE because its outstanding principal is positive
     * again. Same transaction rules as _applyPayment().
     */
    private function _restorePayment(int $loanId, ?int $emiId, float $principal, float $interest): array
    {
        $principal = round($principal, 2);
        $interest  = round($interest, 2);
        $total     = round($principal + $interest, 2);

        $db = \Config\Database::connect();

        if ((new LoanModel())->outstanding($loanId, true) === null) {
            throw new \RuntimeException("Loan #{$loanId} was not found while restoring a payment.");
        }

        if ($emiId !== null) {
            $emi = $this->_lockEmi($loanId, $emiId);

            $this->_writeEmiPaid($emi, max(0.0, round((float) $emi['paid_amount'] - $total, 2)), null);
        }

        $db->table('loans')->where('id', $loanId)
            ->set('total_principal_paid', 'GREATEST(0, ROUND(total_principal_paid - ' . number_format($principal, 2, '.', '') . ', 2))', false)
            ->set('total_interest_paid', 'GREATEST(0, ROUND(total_interest_paid - ' . number_format($interest, 2, '.', '') . ', 2))', false)
            ->update();

        return $this->_recalculateLoan($loanId);
    }

    /** One instalment of one loan, row-locked. Throws if it is not that loan's. */
    private function _lockEmi(int $loanId, int $emiId): array
    {
        $emi = \Config\Database::connect()
            ->query('SELECT * FROM loan_emis WHERE id = ? AND loan_id = ? FOR UPDATE', [$emiId, $loanId])
            ->getRowArray();

        if (! $emi) {
            throw new \RuntimeException("EMI #{$emiId} does not belong to loan #{$loanId}.", self::RULE_CODE);
        }

        return $emi;
    }

    /**
     * Single write path for an instalment's money fields: given its new paid
     * amount, stores paid_amount, balance_amount, payment_status and paid_date
     * together. paid_date is $paidDate once the instalment is fully paid and
     * NULL otherwise.
     */
    private function _writeEmiPaid(array $emi, float $paid, ?string $paidDate): void
    {
        $balance = max(0.0, round((float) $emi['emi_amount'] - $paid, 2));

        if ($balance <= 0.004) {
            $status  = 'PAID';
            $balance = 0.00;
        } else {
            $status = $paid > 0.004 ? 'PARTIAL' : 'PENDING';
        }

        \Config\Database::connect()->table('loan_emis')->where('id', $emi['id'])->update([
            'paid_amount'    => $paid,
            'balance_amount' => $balance,
            'payment_status' => $status,
            'paid_date'      => $status === 'PAID' ? ($paidDate ?? $emi['paid_date']) : null,
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
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

    /** True when any term that drives the EMI schedule differs from the stored loan. */
    private function _termsChanged(array $loan, array $header): bool
    {
        return $this->_cents((float) $loan['sanctioned_amount']) !== $this->_cents((float) $header['sanctioned_amount'])
            || abs((float) $loan['interest_rate'] - (float) $header['interest_rate']) > 0.0005
            || (int) $loan['tenure_months'] !== (int) $header['tenure_months']
            || $loan['start_date'] !== $header['start_date'];
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
        if (! in_array(strtoupper((string) ($payment['payment_method'] ?? '')), self::BANK_METHODS, true)) {
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
            'remarks'          => 'Loan EMI Payment – ' . $loan['lender_name'],
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
            'bank_account_id'   => (int) $this->request->getPost('bank_account_id'),
            'account_number'    => trim((string) $this->request->getPost('account_number')),
            'sanctioned_amount' => $this->_amount($this->request->getPost('sanctioned_amount')),
            'interest_rate'     => $this->_amount($this->request->getPost('interest_rate')),
            'tenure_months'     => $tenure === '' ? 0 : (ctype_digit($tenure) ? (int) $tenure : null),
            'emi_amount'        => $this->_amount($this->request->getPost('emi_amount')),
            'start_date'        => trim((string) $this->request->getPost('start_date')),
            'end_date'          => trim((string) $this->request->getPost('end_date')),
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

        $tenure = $input['tenure_months'];
        if ($tenure === null) {
            $errors[] = 'Tenure must be a whole number of months.';
        } elseif ($tenure <= 0) {
            $errors[] = 'Tenure must be greater than zero.';
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

        if (! in_array($input['loan_type'], self::LOAN_TYPES, true)) {
            $errors[] = 'Loan type must be one of: ' . implode(', ', self::LOAN_TYPES) . '.';
        }

        if (mb_strlen($input['account_number']) > 50) {
            $errors[] = 'Account number cannot be longer than 50 characters.';
        }

        $errors = array_merge($errors, $this->_validateTerms($input));

        if ($input['end_date'] !== '') {
            if (! $this->_isValidDate($input['end_date'])) {
                $errors[] = 'End date is not a valid date.';
            } elseif ($this->_isValidDate($input['start_date']) && $input['end_date'] < $input['start_date']) {
                $errors[] = 'End date cannot be before the start date.';
            }
        }

        // Bank account: required for BANK/OD, optional otherwise, but always
        // a real one. An account already on this loan may have been made
        // inactive since — keeping it on an edit is not an error.
        $bankId = $input['bank_account_id'];

        if ($bankId <= 0) {
            if (in_array($input['loan_type'], self::BANK_REQUIRED, true)) {
                $errors[] = 'A bank account is required for Bank and Overdraft loans.';
            }
        } else {
            $account = (new BankAccountModel())->find($bankId);

            if (! $account) {
                $errors[] = 'The selected bank account was not found.';
            } elseif ((int) $account['is_active'] !== 1 && (int) ($existing['bank_account_id'] ?? 0) !== $bankId) {
                $errors[] = 'The selected bank account is inactive.';
            }
        }

        // EMI amount: blank means "calculate it"; a typed one must be positive
        // (the calculated-EMI comparison happens once the schedule exists).
        if ($input['emi_amount'] === null) {
            $errors[] = 'EMI amount must be a valid number.';
        } elseif ($input['emi_amount'] < 0) {
            $errors[] = 'EMI amount must be greater than zero.';
        }

        return $errors;
    }

    /**
     * Validates, then builds everything store()/update() write: the header
     * columns (minus loan_no and the running figures), the EMI schedule and
     * the calculated EMI. Returns ['errors' => [...]] or the full set.
     */
    private function _prepare(array $input, ?array $existing): array
    {
        $errors = $this->_validateInput($input, $existing);

        if ($errors) {
            return ['errors' => $errors];
        }

        try {
            $schedule = $this->_generateSchedule($input['sanctioned_amount'], $input['interest_rate'], $input['tenure_months'], $input['start_date']);
        } catch (\RuntimeException $e) {
            return ['errors' => [$e->getMessage()]];
        }

        $emi = $this->_calculateEmi($input['sanctioned_amount'], $input['interest_rate'], $input['tenure_months']);

        if ($input['emi_amount'] > 0 && abs($input['emi_amount'] - $emi) > self::EMI_TOLERANCE) {
            return ['errors' => ['EMI amount ' . number_format($input['emi_amount'], 2) . ' does not match the ' . number_format($emi, 2)
                . ' that these terms work out to. Leave it blank to use the calculated amount.']];
        }

        return [
            'errors'   => [],
            'schedule' => $schedule,
            'header'   => [
                'lender_name'       => $input['lender_name'],
                'loan_type'         => $input['loan_type'],
                'bank_account_id'   => $input['bank_account_id'] > 0 ? $input['bank_account_id'] : null,
                'account_number'    => $input['account_number'] !== '' ? $input['account_number'] : null,
                'sanctioned_amount' => $input['sanctioned_amount'],
                'interest_rate'     => $input['interest_rate'],
                'tenure_months'     => $input['tenure_months'],
                'emi_amount'        => $emi,
                'start_date'        => $input['start_date'],
                'end_date'          => $input['end_date'] !== '' ? $input['end_date'] : end($schedule)['due_date'],
                'remarks'           => $input['remarks'] !== '' ? $input['remarks'] : null,
            ],
        ];
    }

    // =========================================================
    // READ HELPERS
    // =========================================================

    /** One loan with its bank account, outstanding snapshot, schedule and payment history. */
    private function _loadLoan(int $id): ?array
    {
        $loanModel = new LoanModel();
        $loan      = $loanModel->find($id);

        if (! $loan) {
            return null;
        }

        return [
            'loan'              => $loan,
            'bank_account'      => $loan['bank_account_id'] ? (new BankAccountModel())->find((int) $loan['bank_account_id']) : null,
            'outstanding'       => $loanModel->outstanding($id),
            'emis'              => (new LoanEmiModel())->emiList($id),
            'upcoming_emis'     => (new LoanEmiModel())->upcomingEmis(30, $id),
            'payments'          => (new LoanPaymentModel())->forLoan($id),
            'schedule_editable' => ! $this->_hasPayments($id),
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
