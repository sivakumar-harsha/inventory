<?php

namespace App\Controllers;

use App\Libraries\CashOpeningGuard;
use App\Models\BankAccountModel;
use App\Models\BankTransactionModel;
use App\Models\GeneralPurchaseModel;
use App\Models\GeneralPurchaseItemModel;
use App\Models\SupplierModel;
use App\Models\ProductModel;
use App\Models\StockLedgerModel;
use App\Models\SupplierPaymentModel;
use CodeIgniter\Controller;

/**
 * Release 4.8.1B laid down the backend CRUD; Release 4.8.1C adds the views
 * under app/Views/general_purchases/. index()/create()/edit()/view() now
 * render those views instead of the 4.8.1B placeholder JSON response.
 *
 * Release 4.8.1D adds warehouse stock posting: store()/update()/delete() now
 * write/reverse stock_ledger rows (source=GENERAL, reference_type=
 * GENERAL_PURCHASE), mirroring the exact pattern Purchases::_insertItemsAndStock()
 * / ::delete() already use for the GENERAL side of a project purchase. This app
 * has no separate FIFO-layer table — stock_ledger's IN/OUT sum (via
 * StockLedgerModel::getAvailableStock(), reused unchanged) already is the
 * available-quantity mechanism, so Phase B of 4.8.1D is satisfied by reuse,
 * not new tables.
 *
 * Release 4.8.4H completes the accounting flow for the Advance Paid entered
 * on this page. The purchase row itself is the supplier-advance record
 * (advance_paid / payment_method / bank_account_id / purchase_date) — the
 * Supplier Ledger statement is derived from it, so no second copy is kept.
 * A Bank Transfer / Cheque / UPI advance also posts one WITHDRAWAL
 * (reference_type SUPPLIER_ADVANCE, reference_id = this purchase's id) through
 * BankTransactionModel, exactly as SupplierPayments and Expenses do: store()
 * posts it, update() reverses the old posting first and reposts, delete()
 * reverses it — all inside the same DB transaction as the purchase itself.
 *
 * Release 4.8.6F-1 adds the missing half of that flow: the same advance also
 * gets one Supplier Payment voucher (payment_no GPA-000003, derived from this
 * purchase's id), so the accountant sees it in the Supplier Payments history
 * instead of only inside the purchase. The voucher is written through
 * SupplierPaymentModel::syncForGeneralPurchase() from the three places below
 * that can change the advance, always inside the same DB transaction — so one
 * advance means exactly one voucher, exactly one bank withdrawal (the
 * SUPPLIER_ADVANCE one already posted here, never a second one from the
 * voucher) and exactly one audit entry. Editing the advance rewrites that same
 * voucher; clearing it removes the voucher and reverses the withdrawal.
 */
class GeneralPurchases extends Controller
{
    public function index()
    {
        $db = \Config\Database::connect();

        $purchases = $db->query("
            SELECT gp.*, s.name AS supplier_name
            FROM general_purchases gp
            LEFT JOIN suppliers s ON gp.supplier_id = s.id
            ORDER BY gp.created_at DESC
        ")->getResultArray();

        $data['purchases']          = $purchases;
        $data['suppliers']          = (new SupplierModel())->orderBy('name', 'ASC')->findAll();
        $data['kpi_total_purchases'] = count($purchases);
        $data['kpi_purchase_value']  = array_sum(array_column($purchases, 'grand_total'));
        $data['kpi_outstanding']     = array_sum(array_column($purchases, 'outstanding_amount'));
        $data['kpi_paid_amount']     = $data['kpi_purchase_value'] - $data['kpi_outstanding'];

        return view('general_purchases/index', $data);
    }

    public function create()
    {
        $data['suppliers']      = (new SupplierModel())->orderBy('name', 'ASC')->findAll();
        $data['products']       = (new ProductModel())->orderBy('name', 'ASC')->findAll();
        $data['nextPurchaseNo'] = (new GeneralPurchaseModel())->nextPurchaseNo();
        $data['warehouseQty']   = $this->_warehouseQtyByProduct($data['products']);
        $data['bankAccounts']   = $this->_activeBankAccounts();

        return view('general_purchases/create', $data);
    }

    public function store()
    {
        $rules = [
            'supplier_id'   => 'required|is_natural_no_zero',
            'purchase_date' => 'required|valid_date[Y-m-d]',
            'bill_date'     => 'required|valid_date[Y-m-d]',
            'bill_no'       => 'permit_empty|string|max_length[100]',
            'remarks'       => 'permit_empty|string',
            'advance_paid'  => 'permit_empty|numeric|greater_than_equal_to[0]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        // Release 4.9.0CF: CI's valid_date[Y-m-d] still accepts 2026-6-5; both dates must be exactly YYYY-MM-DD.
        if (! CashOpeningGuard::isValidDate((string) $this->request->getPost('purchase_date')) || ! CashOpeningGuard::isValidDate((string) $this->request->getPost('bill_date'))) {
            return redirect()->back()->withInput()->with('error', 'Enter valid purchase and bill dates.');
        }

        $items = $this->request->getPost('items');

        if ($itemError = $this->_validateItems($items)) {
            return redirect()->back()->withInput()->with('error', $itemError);
        }

        $totals = $this->_calculateTotals($items);
        $pay    = $this->_extractPayment();

        if ($paymentErrors = $this->_validatePayment($pay, $totals['grand_total'])) {
            return $this->_paymentRejected($paymentErrors, null);
        }
        $pay = $this->_normalizePayment($pay);

        // Release 4.9.0CF: a CASH advance dated before the Cash Opening Date is warned about, never blocked.
        if ($pay['advance'] > 0 && ($warn = CashOpeningGuard::gate($this->request, 'general-purchase-advance', 0, $pay['method'], trim((string) $this->request->getPost('purchase_date'))))) {
            return $warn;
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $generalPurchaseModel = new GeneralPurchaseModel();
            $purchaseNo           = $generalPurchaseModel->nextPurchaseNo();

            $advancePaid        = $pay['advance'];
            $outstandingAmount  = round($totals['grand_total'] - $advancePaid, 2);
            $paymentStatus      = $this->_derivePaymentStatus($totals['grand_total'], $advancePaid, $outstandingAmount);

            $generalPurchaseModel->insert([
                'purchase_no'         => $purchaseNo,
                'supplier_id'         => (int) $this->request->getPost('supplier_id'),
                'purchase_date'       => $this->request->getPost('purchase_date'),
                'bill_no'             => $this->request->getPost('bill_no'),
                'bill_date'           => $this->request->getPost('bill_date'),
                'subtotal'            => $totals['subtotal'],
                'gst_total'           => $totals['gst_total'],
                'grand_total'         => $totals['grand_total'],
                'advance_paid'        => $advancePaid,
                'outstanding_amount'  => $outstandingAmount,
                'payment_status'      => $paymentStatus,
                'payment_method'      => $pay['method'],
                'bank_account_id'     => $pay['bank_account_id'],
                'remarks'             => $this->request->getPost('remarks'),
                'created_by'          => session()->get('user_id'),
            ]);

            $generalPurchaseId = $generalPurchaseModel->getInsertID();

            $this->_insertItems((int) $generalPurchaseId, $items);
            $this->_postStockLedger((int) $generalPurchaseId, $items, $this->request->getPost('purchase_date'), $purchaseNo);
            $this->_postAdvanceWithdrawal((int) $generalPurchaseId, $purchaseNo, $pay, (string) $this->request->getPost('purchase_date'), (int) $this->request->getPost('supplier_id'));
            $voucherChange = $this->_syncAdvanceVoucher((int) $generalPurchaseId, $purchaseNo, $pay, (string) $this->request->getPost('purchase_date'), (int) $this->request->getPost('supplier_id'));
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', 'Failed to save purchase: ' . $e->getMessage());
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', 'Failed to save purchase due to a database error.');
        }

        $this->_auditAdvanceVoucher($voucherChange);

        return redirect()->to('/general-purchases')->with('success', 'General purchase saved successfully.');
    }

    public function view($id)
    {
        $data = $this->_loadPurchaseWithItems((int) $id);

        if (! $data) {
            return redirect()->to('/general-purchases')->with('error', 'General purchase not found.');
        }

        return view('general_purchases/view', $data);
    }

    public function edit($id)
    {
        $data = $this->_loadPurchaseWithItems((int) $id);

        if (! $data) {
            return redirect()->to('/general-purchases')->with('error', 'General purchase not found.');
        }

        $data['suppliers']    = (new SupplierModel())->orderBy('name', 'ASC')->findAll();
        $data['products']     = (new ProductModel())->orderBy('name', 'ASC')->findAll();
        $data['warehouseQty'] = $this->_warehouseQtyByProduct($data['products']);
        $data['bankAccounts'] = $this->_activeBankAccounts();

        return view('general_purchases/edit', $data);
    }

    public function update($id)
    {
        $generalPurchaseModel = new GeneralPurchaseModel();
        $purchase             = $generalPurchaseModel->find((int) $id);

        // A general purchase is hard-deleted (Phase F), so "not found" here
        // already covers the "do not allow editing a deleted purchase" rule.
        if (! $purchase) {
            return redirect()->to('/general-purchases')->with('error', 'General purchase not found.');
        }

        if ($consumptionError = $this->_checkStockConsumed((int) $id)) {
            return redirect()->back()->withInput()->with('error', $consumptionError);
        }

        $rules = [
            'supplier_id'   => 'required|is_natural_no_zero',
            'purchase_date' => 'required|valid_date[Y-m-d]',
            'bill_date'     => 'required|valid_date[Y-m-d]',
            'bill_no'       => 'permit_empty|string|max_length[100]',
            'remarks'       => 'permit_empty|string',
            'advance_paid'  => 'permit_empty|numeric|greater_than_equal_to[0]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        // Release 4.9.0CF: CI's valid_date[Y-m-d] still accepts 2026-6-5; both dates must be exactly YYYY-MM-DD.
        if (! CashOpeningGuard::isValidDate((string) $this->request->getPost('purchase_date')) || ! CashOpeningGuard::isValidDate((string) $this->request->getPost('bill_date'))) {
            return redirect()->back()->withInput()->with('error', 'Enter valid purchase and bill dates.');
        }

        $items = $this->request->getPost('items');

        if ($itemError = $this->_validateItems($items)) {
            return redirect()->back()->withInput()->with('error', $itemError);
        }

        $totals    = $this->_calculateTotals($items);
        $allocated = $generalPurchaseModel->allocatedPaid((int) $id);
        $pay       = $this->_extractPayment();

        if ($paymentErrors = $this->_validatePayment($pay, $totals['grand_total'], $allocated)) {
            return $this->_paymentRejected($paymentErrors, $purchase);
        }
        $pay = $this->_normalizePayment($pay);

        // Release 4.9.0CF: a CASH advance dated before the Cash Opening Date is warned about, never blocked.
        if ($pay['advance'] > 0 && ($warn = CashOpeningGuard::gate($this->request, 'general-purchase-advance', (int) $id, $pay['method'], trim((string) $this->request->getPost('purchase_date'))))) {
            return $warn;
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Amount already settled = this purchase's own advance plus whatever
            // Supplier Payment vouchers have allocated to it since.
            $advancePaid       = $pay['advance'];
            $outstandingAmount = round($totals['grand_total'] - $advancePaid - $allocated, 2);
            $paymentStatus     = $this->_derivePaymentStatus($totals['grand_total'], $advancePaid + $allocated, $outstandingAmount);

            // Reverse the previous advance withdrawal (if any) first, so the
            // replacement below is posted against a balance with the old
            // posting already backed out — same order Expenses::update() uses.
            $this->_reverseAdvanceWithdrawal((int) $id);

            $generalPurchaseModel->update((int) $id, [
                'supplier_id'        => (int) $this->request->getPost('supplier_id'),
                'purchase_date'      => $this->request->getPost('purchase_date'),
                'bill_no'            => $this->request->getPost('bill_no'),
                'bill_date'          => $this->request->getPost('bill_date'),
                'subtotal'           => $totals['subtotal'],
                'gst_total'          => $totals['gst_total'],
                'grand_total'        => $totals['grand_total'],
                'advance_paid'       => $advancePaid,
                'outstanding_amount' => $outstandingAmount,
                'payment_status'     => $paymentStatus,
                'payment_method'     => $pay['method'],
                'bank_account_id'    => $pay['bank_account_id'],
                'remarks'            => $this->request->getPost('remarks'),
            ]);

            // Reverse-then-recreate, same pattern Purchases::update() uses for
            // stock_ledger — delete old ledger rows and items, then reinsert both.
            $db->table('stock_ledger')
               ->where('reference_type', 'GENERAL_PURCHASE')
               ->where('reference_id', (int) $id)
               ->delete();
            (new GeneralPurchaseItemModel())->where('general_purchase_id', (int) $id)->delete();

            $this->_insertItems((int) $id, $items);
            $this->_postStockLedger((int) $id, $items, $this->request->getPost('purchase_date'), $purchase['purchase_no']);
            $this->_postAdvanceWithdrawal((int) $id, $purchase['purchase_no'], $pay, (string) $this->request->getPost('purchase_date'), (int) $this->request->getPost('supplier_id'));
            $voucherChange = $this->_syncAdvanceVoucher((int) $id, $purchase['purchase_no'], $pay, (string) $this->request->getPost('purchase_date'), (int) $this->request->getPost('supplier_id'));
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', 'Failed to update purchase: ' . $e->getMessage());
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', 'Failed to update purchase due to a database error.');
        }

        $this->_auditAdvanceVoucher($voucherChange);

        return redirect()->to('/general-purchases')->with('success', 'General purchase updated successfully.');
    }

    public function delete($id)
    {
        $generalPurchaseModel = new GeneralPurchaseModel();
        $purchase             = $generalPurchaseModel->find((int) $id);

        if (! $purchase) {
            return redirect()->to('/general-purchases')->with('error', 'General purchase not found.');
        }

        if ($blockReason = $this->_checkDeletable((int) $id)) {
            return redirect()->to('/general-purchases')->with('error', $blockReason);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Release 4.8.4H: give the advance's bank withdrawal back to the
            // account (no-op for cash/other advances and for purchases saved
            // before automatic posting existed) so no orphan bank row remains.
            $this->_reverseAdvanceWithdrawal((int) $id);

            // Release 4.8.6F-1: and drop the advance voucher this purchase owns,
            // so no Supplier Payment row is left pointing at a deleted bill.
            $removed       = (new SupplierPaymentModel())->removeForGeneralPurchase((int) $id);
            $voucherChange = $removed
                ? ['action' => 'removed', 'id' => (int) $removed['id'], 'payment_no' => $removed['payment_no'], 'old' => $removed, 'new' => null]
                : null;

            // Hard delete only, per Phase F. Reverse stock_ledger first (its FK is
            // ON DELETE CASCADE-free — reference_id has no FK constraint — so this
            // must be done explicitly, same as Purchases::delete()).
            $db->table('stock_ledger')
               ->where('reference_type', 'GENERAL_PURCHASE')
               ->where('reference_id', (int) $id)
               ->delete();
            (new GeneralPurchaseItemModel())->where('general_purchase_id', (int) $id)->delete();
            $generalPurchaseModel->delete((int) $id);
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->to('/general-purchases')->with('error', 'Failed to delete purchase: ' . $e->getMessage());
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->to('/general-purchases')->with('error', 'Failed to delete purchase due to a database error.');
        }

        $this->_auditAdvanceVoucher($voucherChange);

        return redirect()->to('/general-purchases')->with('success', 'General purchase deleted successfully.');
    }

    // =========================================================
    // SHARED HELPERS
    // =========================================================

    /**
     * Reject if there are no product rows, or any row fails its own rule set.
     * Mirrors Purchases::_validateAllocation()'s "validate everything before
     * writing anything" approach.
     */
    private function _validateItems($items): ?string
    {
        if (empty($items) || ! is_array($items)) {
            return 'At least one product row is required.';
        }

        foreach ($items as $item) {
            if (empty($item['product_id'])) {
                return 'Product is required for every item row.';
            }
            if (! isset($item['quantity']) || (float) $item['quantity'] <= 0) {
                return 'Quantity must be greater than zero for every item row.';
            }
            if (! isset($item['rate']) || (float) $item['rate'] < 0) {
                return 'Rate cannot be negative for any item row.';
            }
        }

        return null;
    }

    /**
     * Recomputes every line via the shared gst_calculate_line() helper —
     * never trusts a client-submitted total/gst_amount, same rule Purchases
     * already follows. Returns subtotal/gst_total/grand_total across items.
     */
    private function _calculateTotals(array $items): array
    {
        helper('gst');

        $subtotal = 0.0;
        $gstTotal = 0.0;

        foreach ($items as $item) {
            if (empty($item['product_id']) || empty($item['quantity']) || ! isset($item['rate'])) {
                continue;
            }

            $product       = (new ProductModel())->find($item['product_id']);
            $gstPercent    = $item['gst_percent'] ?? ($product['gst_percent'] ?? 0);
            $gstApplicable = !isset($item['gst_applicable']) || (int) $item['gst_applicable'] === 1;
            $calc          = gst_calculate_line($item['quantity'], $item['rate'], $gstPercent, $gstApplicable);

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

    /**
     * Inserts general_purchase_items rows for one purchase, recomputing GST
     * per line via the same helper _calculateTotals() uses (single source of
     * truth, no duplicate math). No stock_ledger writes here.
     */
    private function _insertItems(int $generalPurchaseId, array $items): void
    {
        helper('gst');
        $itemModel = new GeneralPurchaseItemModel();

        foreach ($items as $item) {
            if (empty($item['product_id']) || empty($item['quantity']) || ! isset($item['rate'])) {
                continue;
            }

            $product       = (new ProductModel())->find($item['product_id']);
            $gstPercent    = $item['gst_percent'] ?? ($product['gst_percent'] ?? 0);
            $gstApplicable = !isset($item['gst_applicable']) || (int) $item['gst_applicable'] === 1;
            $calc          = gst_calculate_line($item['quantity'], $item['rate'], $gstPercent, $gstApplicable);

            $itemModel->insert([
                'general_purchase_id' => $generalPurchaseId,
                'product_id'          => (int) $item['product_id'],
                'quantity'            => (float) $item['quantity'],
                'unit'                => $item['unit'] ?? ($product['unit'] ?? null),
                'rate'                => (float) $item['rate'],
                'gst_percent'         => $calc['gst_percent'],
                'gst_amount'          => $calc['gst_amount'],
                'line_total'          => $calc['total_with_gst'],
            ]);
        }
    }

    /**
     * 'Pending' — nothing paid; 'Paid' — outstanding balance is settled;
     * 'Partial' — anything paid but a balance remains. Same thresholding
     * style (0.004 epsilon) already used by ProjectModel's financial summary.
     * $advancePaid is the total already settled: the purchase's own advance,
     * plus (from update() only) anything Supplier Payment vouchers allocated.
     */
    private function _derivePaymentStatus(float $grandTotal, float $advancePaid, float $outstandingAmount): string
    {
        if ($advancePaid <= 0.004) {
            return 'Pending';
        }
        if ($outstandingAmount <= 0.004) {
            return 'Paid';
        }
        return 'Partial';
    }

    // =========================================================
    // ADVANCE PAYMENT (Release 4.8.4H)
    // Balance math lives in BankTransactionModel; these helpers only decide
    // *whether* and *what* to post for a purchase's advance. Cash, Other or
    // an unspecified method (or a zero advance) posts nothing.
    // =========================================================

    /** Accounts the Bank Account dropdown may offer (active only) — same list Supplier Payments offers. */
    private function _activeBankAccounts(): array
    {
        return (new BankAccountModel())->where('is_active', 1)->orderBy('bank_name', 'ASC')->findAll();
    }

    /** The advance/method/bank fields exactly as submitted, before validation. */
    private function _extractPayment(): array
    {
        return [
            'advance'         => round((float) ($this->request->getPost('advance_paid') ?: 0), 2),
            'method'          => trim((string) $this->request->getPost('payment_method')),
            'bank_account_id' => (int) $this->request->getPost('bank_account_id'),
        ];
    }

    /**
     * Advance rules: cannot exceed the Grand Total (nor, on update, exceed it
     * once payments already allocated through Supplier Payments are added),
     * and a Bank Transfer/Cheque/UPI advance must name an existing, active
     * bank account. Cash/Other ignore the bank account entirely. Negative or
     * non-numeric advances are already rejected by the controller's field
     * rules. Returns error strings; empty means valid.
     */
    private function _validatePayment(array $pay, float $grandTotal, float $allocated = 0.0): array
    {
        $errors = [];

        if ($pay['advance'] > $grandTotal + 0.004) {
            $errors[] = 'Advance paid (' . number_format($pay['advance'], 2) . ') cannot exceed the Grand Total of ' . number_format($grandTotal, 2) . '.';
        } elseif ($allocated > 0.004 && $pay['advance'] + $allocated > $grandTotal + 0.004) {
            $errors[] = 'Advance paid plus the ' . number_format($allocated, 2) . ' already paid through Supplier Payments cannot exceed the Grand Total of ' . number_format($grandTotal, 2) . '.';
        }

        if ($pay['advance'] > 0 && BankTransactionModel::isBankMethod($pay['method'])) {
            if ($pay['bank_account_id'] <= 0) {
                $errors[] = 'A bank account is required for Bank Transfer, Cheque and UPI payments.';
            } else {
                $account = (new BankAccountModel())->find($pay['bank_account_id']);
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
     * What actually gets stored: a zero advance carries no method or bank
     * account, and Cash/Other never carry a bank account (the form clears it;
     * this enforces it for any other client). Empty values become NULL.
     */
    private function _normalizePayment(array $pay): array
    {
        $method = $pay['method'];
        $bank   = $pay['bank_account_id'];

        if ($pay['advance'] <= 0) {
            $method = '';
            $bank   = 0;
        } elseif (! BankTransactionModel::isBankMethod($method)) {
            $bank = 0;
        }

        return [
            'advance'         => $pay['advance'],
            'method'          => $method !== '' ? $method : null,
            'bank_account_id' => $bank > 0 ? $bank : null,
        ];
    }

    /**
     * HTTP 422 for a rejected advance/bank rule: re-renders the same form
     * with the error and every entered value (fields and product rows), so the
     * user fixes the one field instead of re-keying the purchase. Product rows
     * are re-fed through the pages' existing row-prefill code.
     */
    private function _paymentRejected(array $errors, ?array $purchase)
    {
        $post     = $this->request->getPost();
        $products = (new ProductModel())->orderBy('name', 'ASC')->findAll();
        $items    = $this->_postedItems(is_array($post['items'] ?? null) ? $post['items'] : [], $products);

        $data = [
            'suppliers'    => (new SupplierModel())->orderBy('name', 'ASC')->findAll(),
            'products'     => $products,
            'warehouseQty' => $this->_warehouseQtyByProduct($products),
            'bankAccounts' => $this->_activeBankAccounts(),
            'formErrors'   => $errors,
            'old'          => $post,
        ];

        if ($purchase === null) {
            $data['nextPurchaseNo'] = (new GeneralPurchaseModel())->nextPurchaseNo();
            $data['oldItems']       = $items;
            $view                   = 'general_purchases/create';
        } else {
            $data['purchase'] = array_merge($purchase, [
                'supplier_id'     => $post['supplier_id'] ?? $purchase['supplier_id'],
                'purchase_date'   => $post['purchase_date'] ?? $purchase['purchase_date'],
                'bill_no'         => $post['bill_no'] ?? $purchase['bill_no'],
                'bill_date'       => $post['bill_date'] ?? $purchase['bill_date'],
                'remarks'         => $post['remarks'] ?? $purchase['remarks'],
                'advance_paid'    => $post['advance_paid'] ?? $purchase['advance_paid'],
                'payment_method'  => $post['payment_method'] ?? $purchase['payment_method'],
                'bank_account_id' => $post['bank_account_id'] ?? $purchase['bank_account_id'],
            ]);
            $data['items'] = $items;
            $view          = 'general_purchases/edit';
        }

        return $this->response->setStatusCode(422)->setBody(view($view, $data));
    }

    /**
     * Posted item rows in the shape the edit page's row-prefill code expects
     * (unit is display-only). These values end up inside a <script> block and
     * inside HTML the prefill code builds, so anything that is not numeric is
     * cut down to its numeric value — the same (float) reading the save path
     * itself applies — which leaves no room for markup in a posted field.
     */
    private function _postedItems(array $raw, array $products): array
    {
        $units = array_column($products, 'unit', 'id');
        $num   = static fn ($v): string => is_numeric($v) ? (string) $v : (trim((string) $v) === '' ? '' : (string) (float) $v);
        $out   = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $pid   = (int) ($item['product_id'] ?? 0);
            $gst   = $num($item['gst_percent'] ?? 0);
            $out[] = [
                'product_id'  => $pid > 0 ? $pid : '',
                'quantity'    => $num($item['quantity'] ?? ''),
                'rate'        => $num($item['rate'] ?? ''),
                'gst_percent' => $gst === '' ? '0' : $gst,
                'unit'        => $units[$pid] ?? '',
            ];
        }

        return $out;
    }

    /**
     * Posts the WITHDRAWAL for one purchase's advance; no-op unless there is
     * an advance paid through a bank. reference_id is the purchase id, so the
     * one-posting-per-reference rule in BankTransactionModel also guarantees
     * a purchase can never carry two advance withdrawals.
     */
    private function _postAdvanceWithdrawal(int $purchaseId, string $purchaseNo, array $pay, string $purchaseDate, int $supplierId): void
    {
        if ($pay['advance'] <= 0 || ! BankTransactionModel::isBankMethod($pay['method'])) {
            return;
        }

        $supplier = (new SupplierModel())->find($supplierId);

        (new BankTransactionModel())->createBankTransaction([
            'bank_account_id'  => $pay['bank_account_id'],
            'transaction_date' => $purchaseDate,
            'transaction_type' => 'WITHDRAWAL',
            'amount'           => $pay['advance'],
            'reference_type'   => 'SUPPLIER_ADVANCE',
            'reference_id'     => $purchaseId,
            'reference_no'     => $purchaseNo,
            'remarks'          => 'Supplier Advance - ' . ($supplier['name'] ?? 'Unknown Supplier'),
            'created_by'       => session()->get('user_id'),
        ]);
    }

    /**
     * Release 4.8.6F-1: one audit entry per real change to the advance voucher,
     * written only after the purchase's transaction has committed (so a rolled
     * back save leaves no entry behind). $change is what
     * SupplierPaymentModel::syncForGeneralPurchase() reported; null means the
     * voucher was untouched and nothing is logged.
     */
    private function _auditAdvanceVoucher(?array $change): void
    {
        if (! $change) {
            return;
        }

        $desc = 'Supplier advance voucher ' . $change['payment_no'];

        if ($change['action'] === 'created') {
            audit_create('Supplier Payment', 'SUPPLIER_PAYMENT', $change['id'], $change['payment_no'], $change['new'], $desc . ' created from General Purchase advance.');
        } elseif ($change['action'] === 'updated') {
            audit_update('Supplier Payment', 'SUPPLIER_PAYMENT', $change['id'], $change['payment_no'], $change['old'], $change['new'], $desc . ' updated from General Purchase advance.');
        } else {
            audit_delete('Supplier Payment', 'SUPPLIER_PAYMENT', $change['id'], $change['payment_no'], $change['old'], $desc . ' removed with its General Purchase advance.');
        }
    }

    /** Reverses and removes whatever was posted for one purchase's advance (0 rows is fine). */
    private function _reverseAdvanceWithdrawal(int $purchaseId): void
    {
        (new BankTransactionModel())->deleteBankTransaction('SUPPLIER_ADVANCE', $purchaseId);
    }

    /**
     * Release 4.8.6F-1: creates, updates or removes the one Supplier Payment
     * voucher that mirrors this purchase's advance. $pay is the already
     * normalized payment array, so a zero advance arrives here with no method
     * and no bank account and simply removes the voucher. Unlike
     * _postAdvanceWithdrawal() this runs for every method — a cash advance is
     * still a payment the accountant must see in the payment history, it just
     * posts nothing to any bank.
     */
    private function _syncAdvanceVoucher(int $purchaseId, string $purchaseNo, array $pay, string $purchaseDate, int $supplierId): ?array
    {
        return (new SupplierPaymentModel())->syncForGeneralPurchase($purchaseId, [
            'supplier_id'    => $supplierId,
            'purchase_no'    => $purchaseNo,
            'purchase_date'  => $purchaseDate,
            'advance_paid'   => $pay['advance'],
            'payment_method' => $pay['method'],
        ]);
    }

    /**
     * Loads a general purchase header (with supplier name) plus its items
     * and totals, for view()/edit(). Returns null when the purchase does not
     * exist (covers "not found" and "already deleted" identically, since
     * deletes in this release are hard deletes).
     */
    private function _loadPurchaseWithItems(int $id): ?array
    {
        $db = \Config\Database::connect();

        $purchase = $db->query("
            SELECT gp.*, s.name AS supplier_name, s.gst AS supplier_gst,
                   s.phone AS supplier_phone, s.email AS supplier_email, s.address AS supplier_address
            FROM general_purchases gp
            LEFT JOIN suppliers s ON gp.supplier_id = s.id
            WHERE gp.id = ?
        ", [$id])->getRowArray();

        if (! $purchase) {
            return null;
        }

        $items = $db->query("
            SELECT gpi.*, p.name AS product_name, p.hsn_code AS product_hsn
            FROM general_purchase_items gpi
            LEFT JOIN products p ON gpi.product_id = p.id
            WHERE gpi.general_purchase_id = ?
            ORDER BY gpi.id ASC
        ", [$id])->getResultArray();

        return [
            'purchase' => $purchase,
            'items'    => $items,
            'totals'   => [
                'subtotal'           => (float) $purchase['subtotal'],
                'gst_total'          => (float) $purchase['gst_total'],
                'grand_total'        => (float) $purchase['grand_total'],
                'advance_paid'       => (float) $purchase['advance_paid'],
                'outstanding_amount' => (float) $purchase['outstanding_amount'],
                'payment_status'     => $purchase['payment_status'],
            ],
        ];
    }

    /**
     * Read-only display data for the Create/Edit product grid's "Warehouse
     * Qty" badge — current GENERAL-source balance per product, reusing
     * StockLedgerModel::getAvailableStock() unchanged. Never writes to
     * stock_ledger; purely informational, matching the spec's "readonly
     * badge" requirement.
     */
    private function _warehouseQtyByProduct(array $products): array
    {
        $ledgerModel = new StockLedgerModel();
        $qtyByProduct = [];

        foreach ($products as $product) {
            $qtyByProduct[$product['id']] = $ledgerModel->getAvailableStock((int) $product['id'], 'GENERAL');
        }

        return $qtyByProduct;
    }

    /**
     * Delete guard (Phase F/D): blocks delete if any warehouse quantity this
     * purchase posted has already been consumed. Delegates to
     * _checkStockConsumed(), the same check edit() runs before recreating
     * ledger entries — a purchase should never be deleted (or edited) out
     * from under stock that's already left the warehouse.
     */
    private function _checkDeletable(int $id): ?string
    {
        return $this->_checkStockConsumed($id);
    }

    /**
     * Posts one stock_ledger IN row per item line — source=GENERAL,
     * reference_type=GENERAL_PURCHASE, reference_id=$generalPurchaseId.
     * Mirrors Purchases::_insertItemsAndStock()'s GENERAL-side insert exactly
     * (same column set), since every General Purchase line is 100% warehouse
     * stock (no project_qty/general_qty split here).
     */
    private function _postStockLedger(int $generalPurchaseId, array $items, string $purchaseDate, string $purchaseNo): void
    {
        $db = \Config\Database::connect();

        foreach ($items as $item) {
            if (empty($item['product_id']) || empty($item['quantity'])) {
                continue;
            }

            $db->table('stock_ledger')->insert([
                'product_id'       => (int) $item['product_id'],
                'transaction_type' => 'IN',
                'quantity'         => (float) $item['quantity'],
                'source'           => 'GENERAL',
                'project_id'       => null,
                'reference_type'   => 'GENERAL_PURCHASE',
                'reference_id'     => $generalPurchaseId,
                'transaction_date' => $purchaseDate,
                'notes'            => 'General Purchase ' . $purchaseNo,
            ]);
        }
    }

    /**
     * Blocks edit/delete when the GENERAL stock this purchase posted has
     * already been drawn down below what it contributed — i.e. some of it
     * has since been issued/sold. Aggregates quantity per product across this
     * purchase's items (a product can't repeat within one purchase, per the
     * UI's duplicate-prevention, but this sums defensively either way), then
     * compares against current available balance via
     * StockLedgerModel::getAvailableStock(), unchanged. Same logic shape as
     * Purchases::_checkAllocationConsumed()'s GENERAL branch.
     */
    private function _checkStockConsumed(int $generalPurchaseId): ?string
    {
        $db = \Config\Database::connect();

        $rows = $db->query("
            SELECT gpi.product_id, SUM(gpi.quantity) AS qty, p.name AS product_name
            FROM general_purchase_items gpi
            LEFT JOIN products p ON gpi.product_id = p.id
            WHERE gpi.general_purchase_id = ?
            GROUP BY gpi.product_id
        ", [$generalPurchaseId])->getResultArray();

        $ledgerModel = new StockLedgerModel();

        foreach ($rows as $row) {
            $productId = (int) $row['product_id'];
            $name      = $row['product_name'] ?? ('Product #' . $productId);
            $available = $ledgerModel->getAvailableStock($productId, 'GENERAL');

            if ($available < (float) $row['qty']) {
                return "Cannot modify: {$name} — warehouse stock from this purchase has already been issued/consumed.";
            }
        }

        return null;
    }
}
