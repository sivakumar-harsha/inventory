<?php

namespace App\Controllers;

use App\Models\BankAccountModel;
use App\Models\BankTransactionModel;
use App\Models\CustomerModel;
use App\Models\CustomerPaymentAllocationModel;
use App\Models\CustomerPaymentModel;
use App\Models\ServiceReceiptModel;
use CodeIgniter\Controller;

/**
 * Release 4.8.4C: Customer Payment Allocation Engine — backend only, no views
 * (they land in a later release), so every endpoint answers with JSON.
 *
 * One voucher (CPV-000001) records money received from a customer and splits
 * it across outstanding INVOICE service receipts plus an unallocated advance:
 *
 *     total_amount = SUM(allocation paid_amount) + advance_amount
 *
 * Each allocation raises that receipt's received_amount, lowers its
 * outstanding_amount and refreshes its payment_status (PENDING/PARTIAL/PAID,
 * the same rule ServiceReceipts::_recalculateStatus() uses). DIRECT receipts
 * are already paid in full and are never offered or accepted.
 *
 * Bank: a voucher paid by BANK/CHEQUE/UPI also posts one DEPOSIT of
 * total_amount (reference_type CUSTOMER_PAYMENT, reference_id = the voucher id)
 * inside the same DB transaction; CASH/OTHER post nothing. update()/delete()
 * reverse that deposit before anything else, so a changed account, amount or
 * method (Bank -> Cash removes it) always ends with exactly one correct row.
 * Release 4.8.4F renamed the type from SERVICE_RECEIPT_PAYMENT; rows posted
 * under the old name are still found, reversed and re-posted under the new one.
 *
 * Service Received (4.8.4A/B) is frozen and untouched. Two consequences worth
 * knowing:
 *  - service_receipts.received_amount is the single running total of
 *    everything received against a receipt (the amount taken when it was
 *    raised plus these allocations), so applying/restoring an allocation just
 *    adds/subtracts that allocation's paid_amount.
 *  - The amount taken when a receipt was raised is not posted to the bank by
 *    anything yet; only these vouchers post deposits.
 */
class CustomerPayments extends Controller
{
    private const PAYMENT_METHODS = ['CASH', 'BANK', 'CHEQUE', 'UPI', 'OTHER'];
    private const BANK_METHODS    = ['BANK', 'CHEQUE', 'UPI'];
    private const REFERENCE_TYPE  = 'CUSTOMER_PAYMENT';
    private const LEGACY_REFERENCE_TYPE = 'SERVICE_RECEIPT_PAYMENT';

    // DECIMAL(15,2). MySQL/MariaDB in non-strict mode silently clamps an
    // overflowing value instead of failing, so it is rejected here instead.
    private const MAX_AMOUNT = 9999999999999.99;

    // =========================================================
    // ENDPOINTS
    // =========================================================

    public function index()
    {
        $payments = \Config\Database::connect()->query("
            SELECT cp.*, c.name AS customer_name,
                   COALESCE((SELECT SUM(cpa.paid_amount) FROM customer_payment_allocations cpa WHERE cpa.customer_payment_id = cp.id), 0) AS allocated_amount
            FROM customer_payments cp
            LEFT JOIN customers c ON cp.customer_id = c.id
            ORDER BY cp.payment_date DESC, cp.id DESC
        ")->getResultArray();

        return $this->response->setJSON([
            'status'              => true,
            'payments'            => $payments,
            'kpi_total_received'  => round(array_sum(array_column($payments, 'total_amount')), 2),
            'kpi_advance_amount'  => round(array_sum(array_column($payments, 'advance_amount')), 2),
        ]);
    }

    public function create()
    {
        return $this->response->setJSON([
            'status'          => true,
            'next_payment_no' => (new CustomerPaymentModel())->nextPaymentNo(),
            'payment_methods' => self::PAYMENT_METHODS,
            'customers'       => (new CustomerModel())->select('id, name, phone')->orderBy('name', 'ASC')->findAll(),
            'bank_accounts'   => $this->_activeBankAccounts(),
        ]);
    }

    public function store()
    {
        $input = $this->_extractInput();

        $errors = $this->_validateInput($input);
        if ($errors) {
            return $this->_error($errors, 422);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $paymentId = null;
        $paymentNo = null;

        try {
            // Locks each invoice row, so the outstanding it checks is the one
            // this voucher will really be applied against.
            $errors = $this->_validateAllocations($input);
            if ($errors) {
                $db->transRollback();
                return $this->_error($errors, 422);
            }

            $paymentModel = new CustomerPaymentModel();
            $paymentNo    = $paymentModel->nextPaymentNo();

            if (! $paymentModel->insert($this->_headerRow($input) + ['payment_no' => $paymentNo, 'created_by' => session()->get('user_id')])) {
                throw new \RuntimeException(implode(' ', $paymentModel->errors()) ?: 'The voucher could not be saved.');
            }
            $paymentId = (int) $paymentModel->getInsertID();

            $this->_insertAllocations($paymentId, $input['allocations']);
            $this->_postBankDeposit($paymentId, $paymentNo, $input);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->_error(['Failed to save customer payment: ' . $e->getMessage()], 500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->_error(['Failed to save customer payment due to a database error.'], 500);
        }

        // Release 4.8.8A: audit trail only.
        audit_create('Customer Payment', 'CUSTOMER_PAYMENT', $paymentId, $paymentNo, $this->_headerRow($input) + ['payment_no' => $paymentNo], 'Customer payment ' . $paymentNo . ' created.');

        return $this->response->setJSON([
            'status'     => true,
            'message'    => 'Customer payment saved successfully.',
            'id'         => $paymentId,
            'payment_no' => $paymentNo,
        ]);
    }

    public function view($id)
    {
        $data = $this->_loadPayment((int) $id);

        if (! $data) {
            return $this->_error(['Customer payment not found.'], 404);
        }

        return $this->response->setJSON(['status' => true] + $data);
    }

    public function edit($id)
    {
        $data = $this->_loadPayment((int) $id);

        if (! $data) {
            return $this->_error(['Customer payment not found.'], 404);
        }

        return $this->response->setJSON([
            'status'          => true,
            'payment_methods' => self::PAYMENT_METHODS,
            'customers'       => (new CustomerModel())->select('id, name, phone')->orderBy('name', 'ASC')->findAll(),
            'bank_accounts'   => $this->_activeBankAccounts(),
            // Outstanding as it would be with THIS voucher taken back out — what
            // update() will validate against — so an edit form can re-allocate.
            'invoices'        => $this->_getOutstandingInvoices((int) $data['payment']['customer_id'], (int) $id),
        ] + $data);
    }

    public function update($id)
    {
        $paymentModel = new CustomerPaymentModel();
        $payment      = $paymentModel->find((int) $id);

        if (! $payment) {
            return $this->_error(['Customer payment not found.'], 404);
        }

        $input = $this->_extractInput();

        $errors = $this->_validateInput($input);
        if ($errors) {
            return $this->_error($errors, 422);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Undo this voucher completely first — its bank deposit, then its
            // allocations (which puts each invoice's received/outstanding/status
            // back) — so the validation below sees every invoice's true balance
            // with this voucher's own old amounts already backed out. Any
            // failure rolls the whole thing back, restoring the old voucher.
            $this->_deleteBankDeposit((int) $id);
            $this->_restoreVoucherAllocations((int) $id);

            $errors = $this->_validateAllocations($input);
            if ($errors) {
                $db->transRollback();
                return $this->_error($errors, 422);
            }

            if (! $paymentModel->update((int) $id, $this->_headerRow($input))) {
                throw new \RuntimeException(implode(' ', $paymentModel->errors()) ?: 'The voucher could not be updated.');
            }

            $this->_insertAllocations((int) $id, $input['allocations']);
            $this->_postBankDeposit((int) $id, $payment['payment_no'], $input);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->_error(['Failed to update customer payment: ' . $e->getMessage()], 500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->_error(['Failed to update customer payment due to a database error.'], 500);
        }

        // Release 4.8.8A: audit trail only. $payment is the row read at the
        // top of this method, before any change was applied.
        audit_update('Customer Payment', 'CUSTOMER_PAYMENT', (int) $id, $payment['payment_no'], $payment, $this->_headerRow($input), 'Customer payment ' . $payment['payment_no'] . ' updated.');

        return $this->response->setJSON([
            'status'     => true,
            'message'    => 'Customer payment updated successfully.',
            'id'         => (int) $id,
            'payment_no' => $payment['payment_no'],
        ]);
    }

    public function delete($id)
    {
        $paymentModel = new CustomerPaymentModel();
        $payment      = $paymentModel->find((int) $id);

        if (! $payment) {
            return $this->_error(['Customer payment not found.'], 404);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $this->_deleteBankDeposit((int) $id);
            $this->_restoreVoucherAllocations((int) $id);
            $paymentModel->delete((int) $id);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->_error(['Failed to delete customer payment: ' . $e->getMessage()], 500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->_error(['Failed to delete customer payment due to a database error.'], 500);
        }

        // Release 4.8.8A: audit trail only.
        audit_delete('Customer Payment', 'CUSTOMER_PAYMENT', (int) $id, $payment['payment_no'], $payment, 'Customer payment ' . $payment['payment_no'] . ' deleted.');

        return $this->response->setJSON(['status' => true, 'message' => 'Customer payment deleted successfully.']);
    }

    /**
     * POST customer-payments/get-invoices — { customer_id }. Every INVOICE
     * receipt of that customer with something outstanding, oldest first. JSON
     * array only, same shape convention as SupplierPayments::ajaxBills().
     */
    public function ajaxInvoices()
    {
        $customerId = (int) $this->request->getPost('customer_id');

        if ($customerId <= 0) {
            return $this->response->setJSON(['status' => false, 'message' => 'customer_id is required.'])->setStatusCode(422);
        }

        return $this->response->setJSON($this->_getOutstandingInvoices($customerId));
    }

    // =========================================================
    // PHASE I HELPERS
    // =========================================================

    /**
     * Outstanding INVOICE receipts for one customer, oldest first. DIRECT
     * receipts are excluded (paid in full at the counter). With
     * $includePaymentId the figures are those AFTER that voucher's own
     * allocations are taken back out: a receipt that voucher fully paid is
     * listed again, and paid/outstanding/status exclude its contribution.
     */
    private function _getOutstandingInvoices(int $customerId, ?int $includePaymentId = null): array
    {
        $rows = \Config\Database::connect()->query("
            SELECT sr.id, sr.receipt_no, sr.receipt_date, sr.grand_total, sr.received_amount,
                   COALESCE(cpa.paid_amount, 0) AS voucher_paid
            FROM service_receipts sr
            LEFT JOIN customer_payment_allocations cpa
                   ON cpa.service_receipt_id = sr.id AND cpa.customer_payment_id = ?
            WHERE sr.customer_id = ? AND sr.receipt_type = 'INVOICE'
            ORDER BY sr.receipt_date ASC, sr.id ASC
        ", [(int) $includePaymentId, $customerId])->getResultArray();

        $invoices = [];

        foreach ($rows as $row) {
            $invoice     = round((float) $row['grand_total'], 2);
            $paid        = round((float) $row['received_amount'] - (float) $row['voucher_paid'], 2);
            $outstanding = round($invoice - $paid, 2);

            if ($outstanding <= 0.004) {
                continue;
            }

            $invoices[] = [
                'receipt_id'         => (int) $row['id'],
                'receipt_no'         => $row['receipt_no'],
                'receipt_date'       => $row['receipt_date'],
                'invoice_amount'     => $invoice,
                'paid_amount'        => $paid,
                'outstanding_amount' => $outstanding,
                'payment_status'     => $this->_statusFor($invoice, $outstanding),
            ];
        }

        return $invoices;
    }

    /**
     * Inserts one allocation row per invoice and applies it to that invoice.
     * Each invoice's balance is read (and row-locked) immediately before its
     * own row is written, so allocations to different invoices in one voucher
     * never work from stale figures.
     */
    private function _insertAllocations(int $paymentId, array $allocations): void
    {
        $allocationModel = new CustomerPaymentAllocationModel();

        foreach ($allocations as $alloc) {
            $info = (new ServiceReceiptModel())->outstanding($alloc['service_receipt_id'], true);

            $balance = $this->_applyAllocation($info, $alloc['paid_amount']);

            if (! $allocationModel->insert([
                'customer_payment_id' => $paymentId,
                'service_receipt_id'  => $alloc['service_receipt_id'],
                'invoice_amount'      => $info['invoice_amount'],
                'paid_amount'         => $alloc['paid_amount'],
                'balance_amount'      => $balance,
            ])) {
                throw new \RuntimeException('Allocation row could not be saved: ' . implode(' ', $allocationModel->errors()));
            }
        }
    }

    /**
     * Adds one payment to a receipt: received goes up, outstanding and status
     * follow. $info is the receipt's current outstanding() snapshot. Returns
     * the receipt's new outstanding balance.
     */
    private function _applyAllocation(array $info, float $paid): float
    {
        return $this->_recalculateReceiptStatus(
            $info['receipt_id'],
            $info['invoice_amount'],
            round($info['paid_amount'] + $paid, 2)
        );
    }

    /**
     * Deletes every allocation row of one voucher and takes each amount back
     * off its receipt (received down, outstanding and status restored). Used
     * by both update() (before re-applying) and delete().
     */
    private function _restoreVoucherAllocations(int $paymentId): void
    {
        $allocationModel = new CustomerPaymentAllocationModel();
        $old             = $allocationModel->forPayment($paymentId);

        $allocationModel->where('customer_payment_id', $paymentId)->delete();

        foreach ($old as $row) {
            $this->_restoreAllocation((int) $row['service_receipt_id'], (float) $row['paid_amount']);
        }
    }

    /**
     * Reverses one allocation on its receipt. Received is floored at zero, so
     * a receipt whose received amount was later lowered by hand (Service
     * Received edit) can never be pushed negative.
     */
    private function _restoreAllocation(int $receiptId, float $paid): void
    {
        $info = (new ServiceReceiptModel())->outstanding($receiptId, true);

        if ($info === null) {
            throw new \RuntimeException("Service receipt #{$receiptId} was not found while restoring an allocation.");
        }

        $this->_recalculateReceiptStatus(
            $receiptId,
            $info['invoice_amount'],
            max(0.0, round($info['paid_amount'] - $paid, 2))
        );
    }

    /**
     * Single write path for a receipt's money fields: given the invoice total
     * and the new received amount, stores received_amount, outstanding_amount
     * and payment_status together. Returns the new outstanding balance.
     */
    private function _recalculateReceiptStatus(int $receiptId, float $invoiceAmount, float $received): float
    {
        $outstanding = round($invoiceAmount - $received, 2);

        \Config\Database::connect()->table('service_receipts')
            ->where('id', $receiptId)
            ->update([
                'received_amount'    => $received,
                'outstanding_amount' => $outstanding,
                'payment_status'     => $this->_statusFor($invoiceAmount, $outstanding),
                'updated_at'         => date('Y-m-d H:i:s'),
            ]);

        return $outstanding;
    }

    /** Same rule as ServiceReceipts::_recalculateStatus(). */
    private function _statusFor(float $invoiceAmount, float $outstanding): string
    {
        if ($outstanding <= 0.004) {
            return 'PAID';
        }
        if (abs($outstanding - $invoiceAmount) <= 0.004) {
            return 'PENDING';
        }
        return 'PARTIAL';
    }

    /** Posts the DEPOSIT for one voucher; no-op unless paid through a bank. */
    private function _postBankDeposit(int $paymentId, string $paymentNo, array $input): void
    {
        if (! in_array($input['payment_method'], self::BANK_METHODS, true)) {
            return;
        }

        $customer = (new CustomerModel())->find($input['customer_id']);

        (new BankTransactionModel())->createBankTransaction([
            'bank_account_id'  => $input['bank_account_id'],
            'transaction_date' => $input['payment_date'],
            'transaction_type' => 'DEPOSIT',
            'amount'           => $input['total_amount'],
            'reference_type'   => self::REFERENCE_TYPE,
            'reference_id'     => $paymentId,
            'reference_no'     => $paymentNo,
            'remarks'          => 'Customer Payment - ' . ($customer['name'] ?? 'Unknown Customer'),
            'created_by'       => session()->get('user_id'),
        ]);
    }

    /** Reverses and removes whatever was posted for one voucher (0 rows is fine). */
    private function _deleteBankDeposit(int $paymentId): void
    {
        $model = new BankTransactionModel();

        $model->deleteBankTransaction(self::REFERENCE_TYPE, $paymentId);
        $model->deleteBankTransaction(self::LEGACY_REFERENCE_TYPE, $paymentId);
    }

    // =========================================================
    // INPUT + VALIDATION
    // =========================================================

    /**
     * Normalizes the POST body. Allocations may arrive as an array or a JSON
     * string. Only service_receipt_id and paid_amount are read from each
     * allocation — every invoice figure is re-read from the database. An
     * unparseable amount becomes null so validation can name it.
     */
    private function _extractInput(): array
    {
        $allocations = $this->request->getPost('allocations');

        if (is_string($allocations)) {
            $decoded     = json_decode($allocations, true);
            $allocations = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($allocations)) {
            $allocations = [];
        }

        $clean = [];
        foreach ($allocations as $alloc) {
            if (! is_array($alloc)) {
                continue;
            }
            $clean[] = [
                'service_receipt_id' => (int) ($alloc['service_receipt_id'] ?? 0),
                'paid_amount'        => $this->_amount($alloc['paid_amount'] ?? null),
            ];
        }

        // Always lock/apply invoices in id order, so two vouchers touching the
        // same invoices can't deadlock by locking them in opposite orders.
        usort($clean, static fn ($a, $b) => $a['service_receipt_id'] <=> $b['service_receipt_id']);

        $method = strtoupper(trim((string) $this->request->getPost('payment_method')));

        return [
            'customer_id'     => (int) $this->request->getPost('customer_id'),
            'payment_date'    => trim((string) $this->request->getPost('payment_date')),
            'payment_method'  => $method,
            'bank_account_id' => in_array($method, self::BANK_METHODS, true) ? (int) $this->request->getPost('bank_account_id') : 0,
            'reference_no'    => trim((string) $this->request->getPost('reference_no')),
            'remarks'         => trim((string) $this->request->getPost('remarks')),
            'total_amount'    => $this->_amount($this->request->getPost('total_amount')),
            'advance_amount'  => $this->_amount($this->request->getPost('advance_amount')),
            'allocations'     => $clean,
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

    /** True when the value has no more than two decimal places. */
    private function _isMoney(float $value): bool
    {
        return abs($value - round($value, 2)) < 0.000001;
    }

    private function _cents(float $value): int
    {
        return (int) round($value * 100);
    }

    /**
     * Validates everything that needs no invoice lookup (Phase H): voucher
     * fields, amounts, duplicate invoices, allocations + advance = total, and
     * the bank account. Invoice-dependent rules live in _validateAllocations().
     */
    private function _validateInput(array $input): array
    {
        $errors = [];

        if ($input['customer_id'] <= 0) {
            $errors[] = 'Customer is required.';
        } elseif (! (new CustomerModel())->find($input['customer_id'])) {
            $errors[] = 'The selected customer was not found.';
        }

        $date = \DateTime::createFromFormat('Y-m-d', $input['payment_date']);
        if ($input['payment_date'] === '') {
            $errors[] = 'Payment date is required.';
        } elseif (! $date || $date->format('Y-m-d') !== $input['payment_date']) {
            $errors[] = 'Payment date is not a valid date.';
        }

        if (! in_array($input['payment_method'], self::PAYMENT_METHODS, true)) {
            $errors[] = 'Payment method must be one of: ' . implode(', ', self::PAYMENT_METHODS) . '.';
        }

        $total   = $input['total_amount'];
        $advance = $input['advance_amount'];

        if ($total === null) {
            $errors[] = 'Payment amount must be a valid number.';
        } elseif ($total <= 0) {
            $errors[] = 'Payment amount must be greater than zero.';
        } elseif ($total > self::MAX_AMOUNT) {
            $errors[] = 'Payment amount is too large.';
        } elseif (! $this->_isMoney($total)) {
            $errors[] = 'Payment amount cannot have more than two decimal places.';
        }

        if ($advance === null) {
            $errors[] = 'Advance amount must be a valid number.';
        } elseif ($advance < 0) {
            $errors[] = 'Advance amount cannot be negative.';
        } elseif ($advance > self::MAX_AMOUNT) {
            $errors[] = 'Advance amount is too large.';
        } elseif (! $this->_isMoney($advance)) {
            $errors[] = 'Advance amount cannot have more than two decimal places.';
        }

        $seen        = [];
        $allocations = 0.0;
        $allocsOk    = true;

        foreach ($input['allocations'] as $alloc) {
            $rid  = $alloc['service_receipt_id'];
            $paid = $alloc['paid_amount'];

            if ($rid <= 0) {
                $errors[] = 'Each allocation must reference a valid service receipt.';
                $allocsOk = false;
                continue;
            }
            if (isset($seen[$rid])) {
                $errors[] = "Service receipt #{$rid} is selected more than once in this voucher.";
                $allocsOk = false;
                continue;
            }
            $seen[$rid] = true;

            if ($paid === null) {
                $errors[] = "Paid amount for service receipt #{$rid} must be a valid number.";
                $allocsOk = false;
            } elseif ($paid <= 0) {
                $errors[] = "Paid amount for service receipt #{$rid} must be greater than zero.";
                $allocsOk = false;
            } elseif ($paid > self::MAX_AMOUNT) {
                $errors[] = "Paid amount for service receipt #{$rid} is too large.";
                $allocsOk = false;
            } elseif (! $this->_isMoney($paid)) {
                $errors[] = "Paid amount for service receipt #{$rid} cannot have more than two decimal places.";
                $allocsOk = false;
            } else {
                $allocations += $paid;
            }
        }

        if ($allocsOk && $total !== null && $advance !== null && $total > 0 && $advance >= 0
            && $this->_cents($allocations) + $this->_cents($advance) !== $this->_cents($total)) {
            $errors[] = 'Payment amount must equal the sum of the invoice allocations plus the advance amount.';
        }

        // Bank account: required only for BANK/CHEQUE/UPI, and must be usable.
        if (in_array($input['payment_method'], self::BANK_METHODS, true)) {
            if ($input['bank_account_id'] <= 0) {
                $errors[] = 'A bank account is required for Bank, Cheque and UPI payments.';
            } else {
                $account = (new BankAccountModel())->find($input['bank_account_id']);
                if (! $account) {
                    $errors[] = 'The selected bank account was not found.';
                } elseif ((int) $account['is_active'] !== 1) {
                    $errors[] = 'Transactions cannot be posted against an inactive bank account.';
                }
            }
        }

        return $errors;
    }

    /**
     * Checks each allocation against its invoice's live outstanding (row-locked
     * FOR UPDATE, so this must run inside the caller's transaction): the
     * receipt must exist, be an INVOICE, belong to the voucher's customer, and
     * have enough outstanding for the amount. Never trusts anything the client
     * sent about the invoice.
     */
    private function _validateAllocations(array $input): array
    {
        $errors = [];
        $model  = new ServiceReceiptModel();

        foreach ($input['allocations'] as $alloc) {
            $rid  = $alloc['service_receipt_id'];
            $info = $model->outstanding($rid, true);

            if ($info === null) {
                $errors[] = "Service receipt #{$rid} was not found.";
                continue;
            }

            $label = $info['receipt_no'];

            if ($info['receipt_type'] !== 'INVOICE') {
                $errors[] = "{$label} is a Direct receipt (paid in full) and cannot receive a customer payment.";
                continue;
            }
            if ($info['customer_id'] !== $input['customer_id']) {
                $errors[] = "{$label} does not belong to the selected customer.";
                continue;
            }
            if ($alloc['paid_amount'] > $info['outstanding_amount'] + 0.004) {
                $errors[] = "Paid amount for {$label} exceeds its outstanding balance of " . number_format($info['outstanding_amount'], 2) . '.';
            }
        }

        return $errors;
    }

    /** Voucher header columns (payment_no/created_by are added by store() only). */
    private function _headerRow(array $input): array
    {
        return [
            'customer_id'     => $input['customer_id'],
            'payment_date'    => $input['payment_date'],
            'payment_method'  => $input['payment_method'],
            'bank_account_id' => $input['bank_account_id'] > 0 ? $input['bank_account_id'] : null,
            'reference_no'    => $input['reference_no'] !== '' ? $input['reference_no'] : null,
            'remarks'         => $input['remarks'] !== '' ? $input['remarks'] : null,
            'total_amount'    => $input['total_amount'],
            'advance_amount'  => $input['advance_amount'],
        ];
    }

    // =========================================================
    // READ HELPERS
    // =========================================================

    /** One voucher with its customer, bank account and labelled allocation rows. */
    private function _loadPayment(int $id): ?array
    {
        $payment = (new CustomerPaymentModel())->find($id);

        if (! $payment) {
            return null;
        }

        $db = \Config\Database::connect();

        $allocations = $db->query("
            SELECT cpa.*, sr.receipt_no, sr.receipt_date, sr.payment_status AS current_status
            FROM customer_payment_allocations cpa
            JOIN service_receipts sr ON sr.id = cpa.service_receipt_id
            WHERE cpa.customer_payment_id = ?
            ORDER BY cpa.id ASC
        ", [$id])->getResultArray();

        return [
            'payment'     => $payment,
            'customer'    => $db->table('customers')->where('id', $payment['customer_id'])->get()->getRowArray(),
            'bank_account'=> $payment['bank_account_id'] ? (new BankAccountModel())->find((int) $payment['bank_account_id']) : null,
            'allocations' => $allocations,
        ];
    }

    /** Accounts a voucher may deposit into (active only). */
    private function _activeBankAccounts(): array
    {
        return (new BankAccountModel())->where('is_active', 1)->orderBy('bank_name', 'ASC')->findAll();
    }

    private function _error(array $errors, int $status)
    {
        return $this->response->setJSON(['status' => false, 'errors' => $errors])->setStatusCode($status);
    }
}
