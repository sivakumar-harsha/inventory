<?php

namespace App\Controllers;

use App\Libraries\CashOpeningGuard;
use App\Models\BankAccountModel;
use App\Models\BankTransactionModel;
use App\Models\CustomerModel;
use App\Models\ServiceReceiptItemModel;
use App\Models\ServiceReceiptModel;
use CodeIgniter\Controller;

/**
 * Release 4.8.4A: Service Received foundation (Module 4) — backend CRUD.
 * Release 4.8.4B adds the UI: index()/create()/view()/edit() now render the
 * views under app/Views/service_receipts/ instead of the 4.8.4A JSON
 * placeholders. store()/update()/delete() are unchanged and still answer with
 * JSON, because the create/edit pages submit them via AJAX (same convention
 * as SupplierPayments).
 *
 * Release 4.9.0BQ: every NEW receipt is DIRECT and paid in full — receipt_type is
 * forced server-side, received_amount = grand_total, outstanding 0, status PAID,
 * whatever the client submits. INVOICE survives only as a historical type: an
 * existing Invoice keeps its type when edited, and one that carries a balance or
 * customer-payment allocations (see _isLegacyPartial) keeps the old rule
 * received_amount 0..grand_total, the difference being its outstanding balance.
 *
 * Release 4.8.4F: bank posting. A receipt paid by BANK/CHEQUE/UPI posts one
 * DEPOSIT (reference_type SERVICE_RECEIPT, reference_id = the receipt id) of the
 * amount taken when the receipt was raised — received_amount minus whatever
 * customer payment vouchers have since been allocated to it. Those vouchers
 * post their own deposits (CustomerPayments), so counting them here would bank
 * the same money twice. CASH/OTHER post nothing. store()/update()/delete() each
 * do the posting inside their own DB transaction; update() and delete() first
 * reverse whatever was posted, so the balance always ends right and there is
 * never a second row for one receipt.
 */
class ServiceReceipts extends Controller
{
    private const PAYMENT_MODES  = ['CASH', 'BANK', 'CHEQUE', 'UPI', 'OTHER'];
    private const BANK_MODES     = ['BANK', 'CHEQUE', 'UPI'];
    private const REFERENCE_TYPE = 'SERVICE_RECEIPT';

    // Column limits (DECIMAL(15,3) qty, DECIMAL(15,2) money). MySQL/MariaDB in
    // non-strict mode silently clamps an overflowing value instead of failing,
    // so an out-of-range amount is rejected here rather than stored wrong.
    private const MAX_QTY    = 999999999999.999;
    private const MAX_AMOUNT = 9999999999999.99;

    public function index()
    {
        $db = \Config\Database::connect();

        $receipts = $db->query("
            SELECT sr.*, c.name AS customer_current_name
            FROM service_receipts sr
            LEFT JOIN customers c ON sr.customer_id = c.id
            ORDER BY sr.receipt_date DESC, sr.id DESC
        ")->getResultArray();

        $data['receipts']            = $receipts;
        $data['kpi_total_receipts']  = count($receipts);
        $data['kpi_invoice_amount']  = round(array_sum(array_column($receipts, 'grand_total')), 2);
        $data['kpi_received_amount'] = round(array_sum(array_column($receipts, 'received_amount')), 2);
        $data['kpi_outstanding']     = round(array_sum(array_column($receipts, 'outstanding_amount')), 2);

        return view('service_receipts/index', $data);
    }

    public function create()
    {
        $data = $this->_formData();
        $data['nextReceiptNo'] = (new ServiceReceiptModel())->nextReceiptNo();

        return view('service_receipts/create', $data);
    }

    public function store()
    {
        // Release 4.9.0BQ: no existing row => a NEW receipt (always DIRECT, full payment).
        $prepared = $this->_prepare($this->_extractInput(), $this->request->getPost('items'));

        if ($prepared['errors']) {
            return $this->_error($prepared['errors'], 422);
        }

        // Release 4.9.0CF: a CASH receipt dated before the Cash Opening Date is warned about, never blocked.
        if ($warn = CashOpeningGuard::gate($this->request, 'service-receipt', 0, $prepared['header']['payment_mode'] ?? '', $prepared['header']['receipt_date'] ?? '', true)) {
            return $warn;
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $model     = new ServiceReceiptModel();
            $receiptNo = $model->nextReceiptNo();

            $ok = $model->insert($prepared['header'] + [
                'receipt_no' => $receiptNo,
                'created_by' => session()->get('user_id'),
            ] + $this->_customerSnapshot($prepared['customer']));

            if (! $ok) {
                throw new \RuntimeException('The receipt could not be saved.');
            }

            $receiptId = (int) $model->getInsertID();
            $this->_insertItems($receiptId, $prepared['items']);
            $this->_postBankDeposit($receiptId, $receiptNo, $prepared['header'], $prepared['customer']['name'], (float) $prepared['header']['received_amount']);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->_error(['Failed to save service receipt: ' . $e->getMessage()], 500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->_error(['Failed to save service receipt due to a database error.'], 500);
        }

        // Release 4.8.8A: audit trail only.
        audit_create('Service Receipt', 'SERVICE_RECEIPT', $receiptId, $receiptNo, $prepared['header'], 'Service receipt ' . $receiptNo . ' created.');

        return $this->response->setJSON([
            'status'     => true,
            'message'    => 'Service receipt saved successfully.',
            'id'         => $receiptId,
            'receipt_no' => $receiptNo,
        ]);
    }

    public function view($id)
    {
        $data = $this->_loadReceipt((int) $id);

        if (! $data) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Service receipt not found.');
        }

        // Contact details (mobile / email / GST) are not part of the receipt's
        // customer snapshot, so read them from the customer record. A customer
        // cannot be deleted while a receipt references it (FK), so this exists.
        $data['customer'] = (new CustomerModel())->find((int) $data['receipt']['customer_id']) ?: [];

        return view('service_receipts/view', $data);
    }

    public function edit($id)
    {
        $data = $this->_loadReceipt((int) $id);

        if (! $data) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Service receipt not found.');
        }

        $data['legacy_partial'] = $this->_isLegacyPartial($data['receipt']);
        return view('service_receipts/edit', $data + $this->_formData());
    }

    public function update($id)
    {
        $model   = new ServiceReceiptModel();
        $receipt = $model->find((int) $id);

        if (! $receipt) {
            return $this->_error(['Service receipt not found.'], 404);
        }

        $prepared = $this->_prepare($this->_extractInput(), $this->request->getPost('items'), $receipt);

        if ($prepared['errors']) {
            return $this->_error($prepared['errors'], 422);
        }

        // Release 4.9.0CF: a CASH receipt dated before the Cash Opening Date is warned about, never blocked.
        if ($warn = CashOpeningGuard::gate($this->request, 'service-receipt', (int) $id, $prepared['header']['payment_mode'] ?? '', $prepared['header']['receipt_date'] ?? '', true)) {
            return $warn;
        }

        // receipt_no never changes. The customer snapshot is re-taken only when
        // the customer itself changed; otherwise the stored snapshot stands.
        $header = $prepared['header'];
        if ((int) $receipt['customer_id'] !== (int) $prepared['customer']['id']) {
            $header += $this->_customerSnapshot($prepared['customer']);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Row lock first: customer payment vouchers lock the same row
            // while allocating, so the allocated total read below is settled.
            $model->outstanding((int) $id, true);

            // Take back whatever was posted for the old version (bank, amount
            // or mode may all have changed), then post the new one.
            $this->_deleteBankDeposit((int) $id);

            if (! $model->update((int) $id, $header)) {
                throw new \RuntimeException('The receipt could not be updated.');
            }

            (new ServiceReceiptItemModel())->where('service_receipt_id', (int) $id)->delete();
            $this->_insertItems((int) $id, $prepared['items']);

            $own = max(0.0, round((float) $header['received_amount'] - $this->_allocatedTotal((int) $id), 2));
            $this->_postBankDeposit((int) $id, $receipt['receipt_no'], $header, $header['customer_name'] ?? $receipt['customer_name'], $own);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->_error(['Failed to update service receipt: ' . $e->getMessage()], 500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->_error(['Failed to update service receipt due to a database error.'], 500);
        }

        // Release 4.8.8A: audit trail only. $receipt is the row read at the
        // top of this method, before any change was applied.
        audit_update('Service Receipt', 'SERVICE_RECEIPT', (int) $id, $receipt['receipt_no'], $receipt, $header, 'Service receipt ' . $receipt['receipt_no'] . ' updated.');

        return $this->response->setJSON([
            'status'  => true,
            'message' => 'Service receipt updated successfully.',
            'id'      => (int) $id,
        ]);
    }

    public function delete($id)
    {
        $model   = new ServiceReceiptModel();
        $receipt = $model->find((int) $id);

        if (! $receipt) {
            return $this->_error(['Service receipt not found.'], 404);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $model->outstanding((int) $id, true);

            // The allocation FK would refuse this delete anyway; say why.
            if ($this->_allocatedTotal((int) $id) > 0) {
                $db->transRollback();
                return $this->_error(['This receipt has customer payments allocated to it. Delete those payments first.'], 422);
            }

            // Reverse the bank deposit (balance restored, row removed), then
            // hard delete. Items are removed explicitly (the FK also cascades).
            $this->_deleteBankDeposit((int) $id);
            (new ServiceReceiptItemModel())->where('service_receipt_id', (int) $id)->delete();
            $model->delete((int) $id);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->_error(['Failed to delete service receipt: ' . $e->getMessage()], 500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->_error(['Failed to delete service receipt due to a database error.'], 500);
        }

        // Release 4.8.8A: audit trail only.
        audit_delete('Service Receipt', 'SERVICE_RECEIPT', (int) $id, $receipt['receipt_no'], $receipt, 'Service receipt ' . $receipt['receipt_no'] . ' deleted.');

        return $this->response->setJSON([
            'status'  => true,
            'message' => 'Service receipt deleted successfully.',
        ]);
    }

    // =========================================================
    // BANK POSTING (Release 4.8.4F)
    // =========================================================

    /**
     * Posts one DEPOSIT for a receipt paid through a bank; no-op for CASH/OTHER
     * or when nothing was taken at the counter. Must run inside the caller's
     * DB transaction. $header supplies payment_mode, bank_account_id and
     * receipt_date; $amount is the receipt's own share (see the class note).
     */
    private function _postBankDeposit(int $receiptId, string $receiptNo, array $header, string $customerName, float $amount): void
    {
        $amount = round($amount, 2);

        if (! BankTransactionModel::isBankMethod($header['payment_mode']) || $amount <= 0) {
            return;
        }

        (new BankTransactionModel())->createBankTransaction([
            'bank_account_id'  => (int) $header['bank_account_id'],
            'transaction_date' => $header['receipt_date'],
            'transaction_type' => 'DEPOSIT',
            'amount'           => $amount,
            'reference_type'   => self::REFERENCE_TYPE,
            'reference_id'     => $receiptId,
            'reference_no'     => $receiptNo,
            'remarks'          => 'Service Receipt - ' . $customerName,
            'created_by'       => session()->get('user_id'),
        ]);
    }

    /** Reverses and removes whatever was posted for one receipt (0 rows is fine). */
    private function _deleteBankDeposit(int $receiptId): void
    {
        (new BankTransactionModel())->deleteBankTransaction(self::REFERENCE_TYPE, $receiptId);
    }

    /** Sum of customer payment allocations against one receipt (0 before those tables exist). */
    private function _allocatedTotal(int $receiptId): float
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('customer_payment_allocations')) {
            return 0.0;
        }

        $row = $db->query('SELECT COALESCE(SUM(paid_amount), 0) AS t FROM customer_payment_allocations WHERE service_receipt_id = ?', [$receiptId])->getRow();

        return round((float) $row->t, 2);
    }

    // =========================================================
    // SHARED HELPERS
    // =========================================================

    /**
     * Validates every service row before anything is written. Returns a list
     * of error strings (empty when all rows are valid).
     */
    private function _validateItems($items): array
    {
        if (empty($items) || ! is_array($items)) {
            return ['At least one service row is required.'];
        }

        $errors = [];
        $row    = 0;

        foreach ($items as $item) {
            $row++;
            $item = is_array($item) ? $item : [];

            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '') {
                $errors[] = "Row {$row}: description is required.";
            } elseif (mb_strlen($description) > 500) {
                $errors[] = "Row {$row}: description cannot exceed 500 characters.";
            }

            if (! isset($item['qty']) || ! is_numeric($item['qty']) || (float) $item['qty'] <= 0) {
                $errors[] = "Row {$row}: quantity must be greater than zero.";
            } elseif ((float) $item['qty'] > self::MAX_QTY) {
                $errors[] = "Row {$row}: quantity is too large.";
            }

            if (! isset($item['rate']) || ! is_numeric($item['rate']) || (float) $item['rate'] < 0) {
                $errors[] = "Row {$row}: rate must be zero or more.";
            } elseif ((float) $item['rate'] > self::MAX_AMOUNT) {
                $errors[] = "Row {$row}: rate is too large.";
            }

            // GST is optional: blank means 0%.
            $gst = $item['gst_percent'] ?? '';
            if ($gst !== '' && $gst !== null && (! is_numeric($gst) || (float) $gst < 0 || (float) $gst > 100)) {
                $errors[] = "Row {$row}: GST % must be between 0 and 100.";
            }
        }

        return $errors;
    }

    /**
     * Recomputes every line via the shared gst_calculate_line() helper —
     * never trusts a client-submitted total, same rule GeneralPurchases
     * follows. GST applies to a row only when its GST % is above zero.
     */
    private function _calculateTotals(array $items): array
    {
        $subtotal = 0.0;
        $gstTotal = 0.0;

        foreach ($items as $item) {
            $calc = $this->_calculateLine($item);
            $subtotal += $calc['total'];
            $gstTotal += $calc['gst_amount'];
        }

        $subtotal = round($subtotal, 2);
        $gstTotal = round($gstTotal, 2);

        return [
            'subtotal'    => $subtotal,
            'gst_total'   => $gstTotal,
            'grand_total' => round($subtotal + $gstTotal, 2),
        ];
    }

    private function _calculateLine(array $item): array
    {
        helper('gst');

        $gstPercent = (float) ($item['gst_percent'] ?? 0);

        return gst_calculate_line($item['qty'], $item['rate'], $gstPercent, $gstPercent > 0);
    }

    /**
     * Inserts one service_receipt_items row per service row.
     */
    private function _insertItems(int $receiptId, array $items): void
    {
        $itemModel = new ServiceReceiptItemModel();

        foreach ($items as $item) {
            $calc = $this->_calculateLine($item);

            $ok = $itemModel->insert([
                'service_receipt_id' => $receiptId,
                'description'        => trim((string) $item['description']),
                'qty'                => (float) $item['qty'],
                'rate'               => (float) $item['rate'],
                'gst_percent'        => $calc['gst_percent'],
                'gst_amount'         => $calc['gst_amount'],
                'line_total'         => $calc['total_with_gst'],
            ]);

            if (! $ok) {
                throw new \RuntimeException('A service row could not be saved.');
            }
        }
    }

    /**
     * Pending — nothing outstanding has been reduced (outstanding equals the
     * grand total); Paid — nothing outstanding; Partial — anything in
     * between. Same rule and 0.004 epsilon as
     * SupplierPayments::_recalculatePaymentStatus(), in this module's
     * upper-case enum values.
     */
    private function _recalculateStatus(float $grandTotal, float $outstanding): string
    {
        if ($outstanding <= 0.004) {
            return 'PAID';
        }
        if (abs($outstanding - $grandTotal) <= 0.004) {
            return 'PENDING';
        }
        return 'PARTIAL';
    }

    /**
     * Release 4.9.0BJ: the simplified entry screen submits a service row as
     * {description, amount} — the final amount for that service. It is stored in
     * the existing columns as qty 1, rate = amount, GST 0 (no GST is ever
     * derived), so reports, PDF, Excel and old receipts need no change. A row
     * that already carries qty/rate (the edit screen resubmitting a historical
     * qty x rate / GST line) goes through the unchanged legacy path.
     *
     * The amount must be a plain positive decimal with at most two places and
     * within the column range; a bad one adds an error and the row is replaced
     * by a harmless placeholder so it is not reported twice.
     */
    private function _normalizeItems($items, array &$errors)
    {
        if (! is_array($items)) {
            return $items;
        }

        $row = 0;
        foreach ($items as $k => $item) {
            $row++;
            if (! is_array($item) || isset($item['qty']) || isset($item['rate']) || ! array_key_exists('amount', $item)) {
                continue;
            }

            $amount = is_string($item['amount']) ? trim($item['amount']) : '';

            if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
                $errors[] = "Row {$row}: amount must be a number greater than zero with at most two decimal places.";
                $amount   = '0';
            } elseif ((float) $amount <= 0) {
                $errors[] = "Row {$row}: amount must be greater than zero.";
            } elseif ((float) $amount > self::MAX_AMOUNT) {
                $errors[] = "Row {$row}: amount is too large.";
                $amount   = '0';
            }

            $items[$k] = [
                'description' => $item['description'] ?? '',
                'qty'         => 1,
                'rate'        => $amount,
                'gst_percent' => 0,
            ];
        }

        return $items;
    }

    /**
     * Release 4.9.0BM: true for a historical receipt whose saved data is a real
     * partial payment (a balance outstanding, or received != grand total). Such a
     * receipt keeps its Received/Outstanding figures when edited; everything else
     * is a full-payment receipt.
     */
    private function _isLegacyPartial(array $receipt): bool
    {
        // Release 4.9.0BQ: only a historical INVOICE can be partial, and one that
        // vouchers have been allocated to keeps its received figure too (forcing it
        // to a new grand total would book bank money nobody paid).
        return ($receipt['receipt_type'] ?? '') === 'INVOICE'
            && ((float) $receipt['outstanding_amount'] > 0.004
                || abs((float) $receipt['received_amount'] - (float) $receipt['grand_total']) > 0.004
                || $this->_allocatedTotal((int) $receipt['id']) > 0);
    }

    /**
     * Collects the request's header fields, untouched. Validation happens in
     * _prepare().
     */
    private function _extractInput(): array
    {
        $post = $this->request;

        // receipt_type / received_amount / outstanding_amount / payment_status are
        // never trusted from the browser: _prepare() derives the first from the
        // stored row (or DIRECT for a new receipt) and the rest from the items.
        // received_amount is read only so a historical partial Invoice can be edited.
        return [
            'customer_id'     => (int) $post->getPost('customer_id'),
            'receipt_date'    => trim((string) $post->getPost('receipt_date')),
            'attended_person' => trim((string) $post->getPost('attended_person')),
            'received_amount' => trim((string) $post->getPost('received_amount')),
            'payment_mode'    => strtoupper(trim((string) $post->getPost('payment_mode'))),
            'bank_account_id' => (int) $post->getPost('bank_account_id'),
            'remarks'         => trim((string) $post->getPost('remarks')),
        ];
    }

    /**
     * Validates the whole submission and, when valid, derives the header row
     * (totals, received/outstanding, status, bank account). Shared by store()
     * and update() so both apply identical rules.
     *
     * @return array{errors: string[], header: array, items: array, customer: array}
     */
    private function _prepare(array $input, $items, ?array $existing = null): array
    {
        $errors   = [];
        $customer = null;

        // Release 4.9.0BQ: a NEW receipt is always DIRECT and full-paid. An existing
        // receipt keeps the type it already has (a historical Invoice stays an
        // Invoice, a Direct can never become one) — the browser has no say.
        $receiptType   = $existing ? $existing['receipt_type'] : 'DIRECT';
        $legacyPartial = $existing !== null && $this->_isLegacyPartial($existing);

        if ($existing === null && is_array($items)) {
            // New rows are Description + Amount only; a forged qty/rate/gst_percent is dropped.
            foreach ($items as $k => $item) {
                $items[$k] = is_array($item)
                    ? ['description' => $item['description'] ?? '', 'amount' => $item['amount'] ?? null]
                    : $item;
            }
        }

        $items = $this->_normalizeItems($items, $errors);

        if ($input['customer_id'] <= 0) {
            $errors[] = 'Customer is required.';
        } else {
            $customer = (new CustomerModel())->find($input['customer_id']);
            if (! $customer) {
                $errors[] = 'Selected customer does not exist.';
            }
        }

        if (! $this->_isValidDate($input['receipt_date'])) {
            $errors[] = 'Receipt date is required and must be a valid date.';
        }

        if ($input['attended_person'] === '') {
            $errors[] = 'Attended person is required.';
        } elseif (mb_strlen($input['attended_person']) > 150) {
            $errors[] = 'Attended person cannot exceed 150 characters.';
        }

        if ($input['payment_mode'] === '') {
            $errors[] = 'Payment mode is required.';
        } elseif (! in_array($input['payment_mode'], self::PAYMENT_MODES, true)) {
            $errors[] = 'Payment mode must be one of: ' . implode(', ', self::PAYMENT_MODES) . '.';
        }

        $itemErrors = $this->_validateItems($items);
        $errors     = array_merge($errors, $itemErrors);

        $header = [];

        if (! $itemErrors) {
            $totals   = $this->_calculateTotals($items);
            $received = null;

            if ($totals['grand_total'] > self::MAX_AMOUNT) {
                $errors[] = 'Grand total is too large.';
            } elseif (! $legacyPartial) {
                // Release 4.9.0BM/BQ: a service receipt is money received in full, so
                // the received amount is always the calculated grand total and any
                // client-submitted received_amount is ignored. Only a historical
                // Invoice that really carries a balance or allocations ($legacyPartial,
                // edit only) keeps the old partial-payment rules below.
                $received    = $totals['grand_total'];
                $outstanding = 0.0;
            } else {
                // A blank value keeps the saved figure rather than silently zeroing it.
                $raw = $input['received_amount'] !== '' ? $input['received_amount'] : (string) $existing['received_amount'];
                if (! is_numeric($raw) || (float) $raw < 0) {
                    $errors[] = 'Received amount must be zero or more.';
                } elseif ((float) $raw > $totals['grand_total'] + 0.004) {
                    $errors[] = 'Received amount cannot exceed the grand total of '
                        . number_format($totals['grand_total'], 2) . '.';
                } elseif ((float) $raw + 0.004 < $this->_allocatedTotal((int) $existing['id'])) {
                    $errors[] = 'Received amount cannot be less than the '
                        . number_format($this->_allocatedTotal((int) $existing['id']), 2)
                        . ' already allocated from customer payments.';
                } else {
                    $received    = round((float) $raw, 2);
                    $outstanding = round($totals['grand_total'] - $received, 2);
                }
            }

            if ($received !== null) {
                $header = $totals + [
                    'received_amount'    => $received,
                    'outstanding_amount' => $outstanding,
                    'payment_status'     => $this->_recalculateStatus($totals['grand_total'], $outstanding),
                ];
            }
        }

        $bankError = $this->_validateBankAccount($input['payment_mode'], $input['bank_account_id']);
        if ($bankError) {
            $errors[] = $bankError;
        }

        if (! $errors) {
            $header += [
                'receipt_type'    => $receiptType,
                'customer_id'     => (int) $customer['id'],
                'receipt_date'    => $input['receipt_date'],
                'attended_person' => $input['attended_person'],
                'payment_mode'    => $input['payment_mode'],
                // A stray bank_account_id sent with a non-bank mode is dropped.
                'bank_account_id' => BankTransactionModel::isBankMethod($input['payment_mode'])
                    ? $input['bank_account_id']
                    : null,
                'remarks'         => $input['remarks'] !== '' ? $input['remarks'] : null,
            ];
        }

        return [
            'errors'   => $errors,
            'header'   => $header,
            'items'    => is_array($items) ? array_values($items) : [],
            'customer' => $customer ?? [],
        ];
    }

    /**
     * A bank account is required only for BANK / CHEQUE / UPI, and must be an
     * existing, active account. CASH and OTHER need none.
     */
    private function _validateBankAccount(string $paymentMode, int $bankAccountId): ?string
    {
        if (! BankTransactionModel::isBankMethod($paymentMode)) {
            return null;
        }

        if ($bankAccountId <= 0) {
            return 'Please select the bank account the amount was received in.';
        }

        $account = (new BankAccountModel())->find($bankAccountId);

        if (! $account) {
            return 'Selected bank account does not exist.';
        }

        if ((int) $account['is_active'] !== 1) {
            return 'Selected bank account is inactive.';
        }

        return null;
    }

    private function _isValidDate(string $date): bool
    {
        $parsed = \DateTime::createFromFormat('Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function _customerSnapshot(array $customer): array
    {
        return [
            'customer_name'    => $customer['name'],
            'customer_address' => $customer['address'] ?? null,
        ];
    }

    /**
     * Header (with the customer's current name) plus its items, or null when
     * the receipt does not exist.
     */
    private function _loadReceipt(int $id): ?array
    {
        $db = \Config\Database::connect();

        $receipt = $db->query("
            SELECT sr.*, ba.bank_name, ba.account_name, ba.account_number
            FROM service_receipts sr
            LEFT JOIN bank_accounts ba ON sr.bank_account_id = ba.id
            WHERE sr.id = ?
        ", [$id])->getRowArray();

        if (! $receipt) {
            return null;
        }

        $items = $db->query("
            SELECT * FROM service_receipt_items
            WHERE service_receipt_id = ?
            ORDER BY id ASC
        ", [$id])->getResultArray();

        return ['receipt' => $receipt, 'items' => $items];
    }

    /**
     * Dropdown data for the create/edit forms.
     */
    private function _formData(): array
    {
        return [
            'customers'     => (new CustomerModel())->orderBy('name', 'ASC')->findAll(),
            'bank_accounts' => (new BankAccountModel())->where('is_active', 1)->orderBy('bank_name', 'ASC')->findAll(),
            'payment_modes' => self::PAYMENT_MODES,
            'bank_modes'    => self::BANK_MODES,
        ];
    }

    private function _error(array $errors, int $status)
    {
        return $this->response->setJSON(['status' => false, 'errors' => $errors])->setStatusCode($status);
    }
}
