<?php

namespace App\Controllers;

use App\Libraries\CashOpeningGuard;
use App\Models\BankAccountModel;
use App\Models\BankTransactionModel;
use App\Models\SupplierPaymentModel;
use App\Models\SupplierPaymentAllocationModel;
use App\Models\PurchaseModel;
use App\Models\GeneralPurchaseModel;
use App\Models\SupplierModel;
use CodeIgniter\Controller;

/**
 * Release 4.8.2B: backend payment processing only — no views yet (those land
 * in a later release). A voucher can pay any mix of Project Purchase
 * (`purchases`) and General Purchase (`general_purchases`) bills, plus an
 * unallocated supplier advance, in one transaction.
 *
 * Schema constraint discovered while building this release: `purchases` has
 * only `total_amount` — no paid_amount/outstanding_amount/payment_status
 * columns exist on it (unlike `general_purchases`, which already has
 * outstanding_amount/payment_status from Release 4.8.1). Adding those columns
 * would be a Purchases-module schema change, which is outside this release's
 * authorized scope (only SupplierPayments.php was listed under CREATE; no
 * migration was authorized). So "paid" for a PROJECT bill is always derived
 * live from supplier_payment_allocations via PurchaseModel::outstanding() and
 * never persisted back onto `purchases` — consistent with that model's
 * "read-only outstanding helper only" scoping. For a GENERAL bill, the
 * existing outstanding_amount/payment_status columns on general_purchases ARE
 * refreshed after every allocation change, so the already-shipped (frozen)
 * General Purchase list/view pages keep showing correct figures.
 *
 * Release 4.8.6F-1: General Purchase advances now appear here as vouchers of
 * their own (payment_no GPA-000003, written by GeneralPurchases through
 * SupplierPaymentModel::syncForGeneralPurchase()). Those vouchers are owned by
 * their purchase, so this controller shows them but refuses to edit or delete
 * them. Release 4.8.6F-2: they are audit history only and never count toward
 * the supplier advance pool (the advance is already applied to its own bill),
 * exactly as SupplierPaymentModel::voucherAdvanceRows() excludes them.
 *
 * Release 4.8.3C: a voucher paid by Bank Transfer/Cheque/UPI also posts one
 * WITHDRAWAL (reference_type SUPPLIER_PAYMENT) of total_amount to the chosen
 * bank account, inside the same DB transaction as the allocations. The
 * allocation engine itself is unchanged; the bank helpers are at the bottom.
 */
class SupplierPayments extends Controller
{
    /**
     * Release 4.8.2C wires index()/create()/view()/edit() to real views —
     * pure display/data-prep, no change to the allocation engine itself
     * (store()/update()/delete()/ajaxBills() and every private helper below
     * are untouched from Release 4.8.2B).
     *
     * Release 4.8.6F: the list page became a pure transaction index — the four
     * accounting KPI cards were dropped, so the KPI figures they consumed
     * (including the _getOutstandingBills() sweep, which ran one
     * PurchaseModel::outstanding() query per purchase row in the database) are
     * no longer computed here. In their place the voucher rows are decorated
     * with the bill-settlement facts the redesigned cells show. All of it is
     * read-only derivation from the same sources the detail pages already use;
     * no posting, allocation, advance, ledger or bank behaviour is touched and
     * nothing is written back.
     */
    public function index()
    {
        $db = \Config\Database::connect();

        $vouchers = $db->query("
            SELECT sp.*, s.name AS supplier_name,
                   COALESCE((SELECT SUM(spa.paid_amount) FROM supplier_payment_allocations spa WHERE spa.supplier_payment_id = sp.id), 0) AS allocated_amount
            FROM supplier_payments sp
            LEFT JOIN suppliers s ON sp.supplier_id = s.id
            ORDER BY sp.created_at DESC
        ")->getResultArray();

        $data['vouchers'] = $this->_decorateIndexVouchers($vouchers);

        // Release 4.9.0Z: Pending Payables — every open PROJECT/GENERAL bill
        // across every supplier, grouped by supplier for the new tab. Same
        // outstanding formula ajaxBills() already uses (PurchaseModel /
        // GeneralPurchaseModel::outstanding()); nothing here is written back.
        $pendingBills = $this->_getOutstandingBills(null);
        usort($pendingBills, static fn ($a, $b) => strcmp((string) $a['supplier_name'], (string) $b['supplier_name'])
            ?: strcmp((string) $a['purchase_date'], (string) $b['purchase_date']));

        $pendingSuppliers = array_unique(array_column($pendingBills, 'supplier_id'));

        $data['pendingBills']   = $pendingBills;
        $data['pendingSummary'] = [
            'supplier_count' => count($pendingSuppliers),
            'total'          => round(array_sum(array_column($pendingBills, 'outstanding_amount')), 2),
        ];

        return view('supplier_payments/index', $data);
    }

    /**
     * Release 4.8.6F: per-voucher display facts for the index page only.
     *
     * Payment type / status are classified from data the voucher already
     * carries; the supplier advance pool uses exactly the arithmetic of
     * SupplierPaymentModel::advanceSummary(), and the per-bill figures use
     * exactly the outstanding formula of PurchaseModel::outstanding() /
     * GeneralPurchaseModel::outstanding() — expressed set-based here so the
     * list costs a fixed handful of queries instead of one per row.
     */
    private function _decorateIndexVouchers(array $vouchers): array
    {
        $billsByVoucher        = $this->_indexAllocationBills();
        $outstandingBySupplier = $this->_indexSupplierOutstanding();
        $bankByPayment         = $this->_indexBankAccountNames();

        // Supplier advance pool, same as SupplierPaymentModel::advanceSummary()
        // but folded over the voucher rows already in hand.
        $advancePaid = [];
        $advanceUsed = [];
        foreach ($vouchers as $v) {
            // A General Purchase advance is not spendable advance (see above).
            if (SupplierPaymentModel::isGpAdvance($v['payment_no'])) {
                continue;
            }
            $sid               = (int) $v['supplier_id'];
            $advancePaid[$sid] = ($advancePaid[$sid] ?? 0.0) + (float) $v['advance_amount'];
            $advanceUsed[$sid] = ($advanceUsed[$sid] ?? 0.0) + SupplierPaymentModel::advanceUsedOf(
                (float) $v['total_amount'],
                (float) $v['advance_amount'],
                (float) $v['allocated_amount']
            );
        }

        foreach ($vouchers as &$v) {
            $sid   = (int) $v['supplier_id'];
            $bills = $billsByVoucher[(int) $v['id']] ?? [];

            // Release 4.8.6F-1: a voucher the General Purchase module owns. Its
            // reference_no is that purchase's GP number, which is what the
            // Invoice/Bill cell shows in place of a settled bill.
            $v['gp_advance_id'] = SupplierPaymentModel::gpAdvanceSourceId($v['payment_no']);

            $v['bills']             = $bills;
            $v['bank_account_name'] = $bankByPayment[(int) $v['id']] ?? null;
            $v['advance_used']      = SupplierPaymentModel::advanceUsedOf(
                (float) $v['total_amount'],
                (float) $v['advance_amount'],
                (float) $v['allocated_amount']
            );

            if (! $bills) {
                // No bill settled by this voucher: it is an advance, whatever
                // else it carries.
                $v['payment_type'] = 'ADVANCE';
                $v['status']       = 'ADVANCE';
            } else {
                $v['payment_type'] = (float) $v['advance_amount'] > 0 ? 'MIXED' : 'BILL';

                $stillOpen = false;
                foreach ($bills as $bill) {
                    if ($bill['balance'] > 0.01) {
                        $stillOpen = true;
                        break;
                    }
                }
                $v['status'] = $stillOpen ? 'PARTIAL' : 'PAID';
            }

            $available = round(($advancePaid[$sid] ?? 0.0) - ($advanceUsed[$sid] ?? 0.0), 2);

            $v['supplier_outstanding']       = round($outstandingBySupplier[$sid] ?? 0.0, 2);
            $v['supplier_advance_available'] = $available > 0.01 ? $available : 0.0;
        }
        unset($v);

        return $vouchers;
    }

    /**
     * Release 4.8.6F: the bills each voucher settled, keyed by voucher id, with
     * each bill's live amount / paid-to-date / balance. purchase_no is the same
     * synthesized PUR-000012 style _billLabel() uses for PROJECT bills, and a
     * GENERAL bill's advance_paid counts toward paid exactly as
     * GeneralPurchaseModel::outstanding() counts it.
     */
    private function _indexAllocationBills(): array
    {
        $db = \Config\Database::connect();

        $paidToDate = [];
        $sums       = $db->query('SELECT purchase_type, purchase_id, SUM(paid_amount) AS paid
                                  FROM supplier_payment_allocations
                                  GROUP BY purchase_type, purchase_id')->getResultArray();
        foreach ($sums as $row) {
            $paidToDate[$row['purchase_type'] . '#' . (int) $row['purchase_id']] = (float) $row['paid'];
        }

        $rows = $db->query("
            SELECT spa.supplier_payment_id, spa.purchase_type, spa.purchase_id, spa.paid_amount,
                   CASE WHEN spa.purchase_type = 'PROJECT'
                        THEN CONCAT('PUR-', LPAD(spa.purchase_id, 6, '0'))
                        ELSE gp.purchase_no END AS purchase_no,
                   CASE WHEN spa.purchase_type = 'PROJECT' THEN p.invoice_no   ELSE gp.bill_no     END AS bill_no,
                   CASE WHEN spa.purchase_type = 'PROJECT' THEN p.total_amount ELSE gp.grand_total END AS bill_amount,
                   CASE WHEN spa.purchase_type = 'PROJECT' THEN 0 ELSE COALESCE(gp.advance_paid, 0) END AS bill_advance_paid
            FROM supplier_payment_allocations spa
            LEFT JOIN purchases p          ON spa.purchase_type = 'PROJECT' AND p.id  = spa.purchase_id
            LEFT JOIN general_purchases gp ON spa.purchase_type = 'GENERAL' AND gp.id = spa.purchase_id
            ORDER BY spa.id ASC
        ")->getResultArray();

        $out = [];
        foreach ($rows as $row) {
            $billAmount = (float) $row['bill_amount'];
            $paid       = round(($paidToDate[$row['purchase_type'] . '#' . (int) $row['purchase_id']] ?? 0.0) + (float) $row['bill_advance_paid'], 2);

            $out[(int) $row['supplier_payment_id']][] = [
                'purchase_type' => $row['purchase_type'],
                'purchase_no'   => $row['purchase_no'] ?: ('#' . (int) $row['purchase_id']),
                'bill_no'       => $row['bill_no'],
                'bill_amount'   => $billAmount,
                'paid_to_date'  => $paid,
                'balance'       => round($billAmount - $paid, 2),
                'paid_here'     => (float) $row['paid_amount'],
            ];
        }

        return $out;
    }

    /**
     * Release 4.8.6F: total open payable per supplier id, over PROJECT and
     * GENERAL bills. Same per-bill formula and same "> 0.004 counts as open"
     * cut-off _getOutstandingBills() applies, so an overpaid bill never nets
     * off another bill's balance.
     */
    private function _indexSupplierOutstanding(): array
    {
        $db  = \Config\Database::connect();
        $out = [];

        $add = static function (array $rows) use (&$out): void {
            foreach ($rows as $row) {
                $balance = round((float) $row['bill_amount'] - (float) $row['paid'], 2);
                if ($balance > 0.004) {
                    $sid       = (int) $row['supplier_id'];
                    $out[$sid] = ($out[$sid] ?? 0.0) + $balance;
                }
            }
        };

        $add($db->query("
            SELECT p.supplier_id, p.total_amount AS bill_amount, COALESCE(a.paid, 0) AS paid
            FROM purchases p
            LEFT JOIN (SELECT purchase_id, SUM(paid_amount) AS paid
                       FROM supplier_payment_allocations WHERE purchase_type = 'PROJECT'
                       GROUP BY purchase_id) a ON a.purchase_id = p.id
        ")->getResultArray());

        $add($db->query("
            SELECT gp.supplier_id, gp.grand_total AS bill_amount,
                   COALESCE(a.paid, 0) + COALESCE(gp.advance_paid, 0) AS paid
            FROM general_purchases gp
            LEFT JOIN (SELECT purchase_id, SUM(paid_amount) AS paid
                       FROM supplier_payment_allocations WHERE purchase_type = 'GENERAL'
                       GROUP BY purchase_id) a ON a.purchase_id = gp.id
        ")->getResultArray());

        return $out;
    }

    /**
     * Release 4.8.6F: bank account label per voucher, read the same way edit()
     * reads it — a voucher has no bank column, the account is the one on its
     * posted SUPPLIER_PAYMENT withdrawal (none for cash or for vouchers saved
     * before automatic posting existed). Shown in the expanded row only.
     */
    private function _indexBankAccountNames(): array
    {
        $rows = \Config\Database::connect()->query("
            SELECT bt.reference_id, ba.bank_name, ba.account_name
            FROM bank_transactions bt
            JOIN bank_accounts ba ON ba.id = bt.bank_account_id
            WHERE bt.reference_type = 'SUPPLIER_PAYMENT'
        ")->getResultArray();

        $out = [];
        foreach ($rows as $row) {
            $parts = array_values(array_filter([
                trim((string) $row['bank_name']),
                trim((string) $row['account_name']),
            ], static fn ($part) => $part !== ''));

            $out[(int) $row['reference_id']] = $parts ? implode(' — ', $parts) : null;
        }

        return $out;
    }

    public function create()
    {
        $data['suppliers']     = (new SupplierModel())->orderBy('name', 'ASC')->findAll();
        $data['nextPaymentNo'] = (new SupplierPaymentModel())->nextPaymentNo();
        $data['bankAccounts']  = $this->_activeBankAccounts();

        return view('supplier_payments/create', $data);
    }

    public function store()
    {
        $input = $this->_extractInput();

        $errors = array_merge($this->_validateAllocations($input), $this->_validateBankAccount($input));
        if ($errors) {
            return $this->response->setJSON(['status' => false, 'errors' => $errors])->setStatusCode(422);
        }

        // Release 4.9.0CF: a CASH voucher dated before the Cash Opening Date is warned about, never blocked.
        if ($warn = CashOpeningGuard::gate($this->request, 'supplier-payment', 0, $input['payment_method'], $input['payment_date'], true)) {
            return $warn;
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $paymentId = null;
        $paymentNo = null;

        try {
            $paymentModel = new SupplierPaymentModel();
            $paymentNo    = $paymentModel->nextPaymentNo();

            $paymentModel->insert([
                'payment_no'     => $paymentNo,
                'supplier_id'    => $input['supplier_id'],
                'payment_date'   => $input['payment_date'],
                'payment_method' => $input['payment_method'],
                'reference_no'   => $input['reference_no'],
                'remarks'        => $input['remarks'],
                'total_amount'   => $input['total_amount'],
                'advance_amount' => $input['advance_amount'],
                'created_by'     => session()->get('user_id'),
            ]);

            $paymentId = $paymentModel->getInsertID();

            $this->_insertAllocations((int) $paymentId, $input['allocations']);
            $this->_createBankTransaction((int) $paymentId, $paymentNo, $input);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to save payment: ' . $e->getMessage()]])->setStatusCode(500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to save payment due to a database error.']])->setStatusCode(500);
        }

        // Release 4.8.8A: audit trail only.
        audit_create('Supplier Payment', 'SUPPLIER_PAYMENT', (int) $paymentId, $paymentNo, [
            'supplier_id'    => $input['supplier_id'],
            'payment_date'   => $input['payment_date'],
            'payment_method' => $input['payment_method'],
            'reference_no'   => $input['reference_no'],
            'remarks'        => $input['remarks'],
            'total_amount'   => $input['total_amount'],
            'advance_amount' => $input['advance_amount'],
        ] + $this->_auditAdvanceUsed($input), 'Supplier payment ' . $paymentNo . ' created.');

        // Flash message for the list page the UI redirects to on success —
        // additive only, doesn't change the JSON contract or any allocation
        // math above.
        session()->setFlashdata('success', 'Supplier payment saved successfully.');

        return $this->response->setJSON([
            'status'     => true,
            'message'    => 'Supplier payment saved successfully.',
            'payment_no' => $paymentNo,
            'id'         => $paymentId,
        ]);
    }

    public function view($id)
    {
        $data = $this->_loadPaymentWithAllocations((int) $id);

        if (! $data) {
            return redirect()->to('/supplier-payments')->with('error', 'Supplier payment not found.');
        }

        // Release 4.8.6E: cash paid vs. advance used, and the supplier's advance
        // left immediately after this voucher.
        $model     = new SupplierPaymentModel();
        $allocSum  = (float) array_sum(array_column($data['allocations'], 'paid_amount'));
        $advUsed   = SupplierPaymentModel::advanceUsedOf((float) $data['payment']['total_amount'], (float) $data['payment']['advance_amount'], $allocSum);
        $data['cashPaid']          = round((float) $data['payment']['total_amount'] - (float) $data['payment']['advance_amount'], 2);
        $data['advanceUsed']       = $advUsed;
        $data['billsSettled']      = round($allocSum, 2);
        $data['outstandingAfter']  = round((float) array_sum(array_column($data['allocations'], 'balance_amount')), 2);
        $data['remainingAdvance']  = $model->advanceSummaryThrough((int) $data['payment']['supplier_id'], (int) $id)['available'];

        // Release 4.8.6F-1: non-zero when this voucher is the mirror of a
        // General Purchase advance, which the page states plainly instead of
        // offering an Edit button that would desync the two.
        $data['gpAdvanceId'] = SupplierPaymentModel::gpAdvanceSourceId($data['payment']['payment_no']);

        return view('supplier_payments/view', $data);
    }

    public function edit($id)
    {
        $data = $this->_loadPaymentWithAllocations((int) $id);

        if (! $data) {
            return redirect()->to('/supplier-payments')->with('error', 'Supplier payment not found.');
        }

        // Release 4.8.6F-1: a General Purchase advance voucher is a mirror of
        // its purchase, not an independent document — editing it here would let
        // the two disagree. Send the accountant to the one screen that owns the
        // figure instead.
        if ($gpId = SupplierPaymentModel::gpAdvanceSourceId($data['payment']['payment_no'])) {
            return redirect()->to('/general-purchases/edit/' . $gpId)
                ->with('error', 'This voucher records the advance paid on General Purchase ' . ($data['payment']['reference_no'] ?: '#' . $gpId) . '. Change the Advance Paid on the purchase itself and the voucher follows.');
        }

        $data['suppliers']    = (new SupplierModel())->orderBy('name', 'ASC')->findAll();
        $data['bankAccounts'] = $this->_activeBankAccounts();

        // The voucher has no bank column of its own; the account it was paid
        // from is the one on its posted bank transaction (none for cash or
        // for vouchers saved before automatic posting existed).
        $posted = \Config\Database::connect()->table('bank_transactions')
            ->select('bank_account_id')
            ->where('reference_type', 'SUPPLIER_PAYMENT')
            ->where('reference_id', (int) $id)
            ->get()->getRowArray();
        $data['selectedBankAccountId'] = $posted ? (int) $posted['bank_account_id'] : null;

        // Release 4.8.6D: how much previously paid supplier advance this
        // voucher applies to its bills (derived, see SupplierPaymentModel).
        $allocationSum        = array_sum(array_column($data['allocations'], 'paid_amount'));
        $data['advanceUsed']  = SupplierPaymentModel::advanceUsedOf((float) $data['payment']['total_amount'], (float) $data['payment']['advance_amount'], (float) $allocationSum);

        return view('supplier_payments/edit', $data);
    }

    public function update($id)
    {
        $paymentModel = new SupplierPaymentModel();
        $payment      = $paymentModel->find((int) $id);

        if (! $payment) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Supplier payment not found.']])->setStatusCode(404);
        }

        if ($gpError = $this->_rejectGpAdvance($payment, 'edited')) {
            return $gpError;
        }

        $input = $this->_extractInput();

        $bankErrors = $this->_validateBankAccount($input);
        if ($bankErrors) {
            return $this->response->setJSON(['status' => false, 'errors' => $bankErrors])->setStatusCode(422);
        }

        // Release 4.9.0CF: a CASH voucher dated before the Cash Opening Date is warned about, never blocked.
        if ($warn = CashOpeningGuard::gate($this->request, 'supplier-payment', (int) $id, $input['payment_method'], $input['payment_date'], true)) {
            return $warn;
        }

        // Release 4.8.6D: moving the voucher to another supplier takes its
        // advance with it, so the old supplier must not be left with more
        // advance applied to bills than advance paid.
        if ((int) $payment['supplier_id'] !== $input['supplier_id']
            && $paymentModel->advanceSummary((int) $payment['supplier_id'], (int) $id)['available'] < -0.004) {
            return $this->response->setJSON(['status' => false, 'errors' => ['The advance on this voucher has already been applied to bills of the current supplier, so the supplier cannot be changed.']])->setStatusCode(422);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Reverse the previous bank withdrawal (if any) before anything
            // else, so the replacement below is posted against a balance with
            // this voucher's old effect already backed out.
            $this->_deleteBankTransaction((int) $id);

            // Restore first (delete old allocations, refresh the bills they
            // touched), so the validation that follows checks against each
            // bill's true outstanding with this voucher's own old
            // contribution already backed out — this is what lets Scenario 5
            // (change allocation amounts) recalculate correctly instead of
            // treating the old amount as unavailable headroom.
            $this->_restoreVoucherAllocations((int) $id);

            $errors = $this->_validateAllocations($input, (int) $id);
            if ($errors) {
                throw new \RuntimeException(implode(' ', $errors));
            }

            $paymentModel->update((int) $id, [
                'supplier_id'    => $input['supplier_id'],
                'payment_date'   => $input['payment_date'],
                'payment_method' => $input['payment_method'],
                'reference_no'   => $input['reference_no'],
                'remarks'        => $input['remarks'],
                'total_amount'   => $input['total_amount'],
                'advance_amount' => $input['advance_amount'],
            ]);

            $this->_insertAllocations((int) $id, $input['allocations']);
            $this->_createBankTransaction((int) $id, $payment['payment_no'], $input);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'errors' => [$e->getMessage()]])->setStatusCode(422);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to update payment due to a database error.']])->setStatusCode(500);
        }

        // Release 4.8.8A: audit trail only. $payment is the row read at the
        // top of this method, before any change was applied.
        audit_update('Supplier Payment', 'SUPPLIER_PAYMENT', (int) $id, $payment['payment_no'], $payment, [
            'supplier_id'    => $input['supplier_id'],
            'payment_date'   => $input['payment_date'],
            'payment_method' => $input['payment_method'],
            'reference_no'   => $input['reference_no'],
            'remarks'        => $input['remarks'],
            'total_amount'   => $input['total_amount'],
            'advance_amount' => $input['advance_amount'],
        ] + $this->_auditAdvanceUsed($input), 'Supplier payment ' . $payment['payment_no'] . ' updated.');

        session()->setFlashdata('success', 'Supplier payment updated successfully.');

        return $this->response->setJSON(['status' => true, 'message' => 'Supplier payment updated successfully.']);
    }

    public function delete($id)
    {
        $paymentModel = new SupplierPaymentModel();
        $payment      = $paymentModel->find((int) $id);

        if (! $payment) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Supplier payment not found.']])->setStatusCode(404);
        }

        if ($gpError = $this->_rejectGpAdvance($payment, 'deleted')) {
            return $gpError;
        }

        // Release 4.8.6D: an advance that has already been applied to bills
        // cannot be deleted from under them — remove those settlements first.
        if ((float) $payment['advance_amount'] > 0
            && $paymentModel->advanceSummary((int) $payment['supplier_id'], (int) $id)['available'] < -0.004) {
            return $this->response->setJSON(['status' => false, 'errors' => ['The advance paid on this voucher has already been applied to supplier bills. Delete or edit those advance settlements first.']])->setStatusCode(422);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $this->_deleteBankTransaction((int) $id);
            $this->_restoreVoucherAllocations((int) $id);
            $paymentModel->delete((int) $id);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to delete payment: ' . $e->getMessage()]])->setStatusCode(500);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to delete payment due to a database error.']])->setStatusCode(500);
        }

        // Release 4.8.8A: audit trail only.
        audit_delete('Supplier Payment', 'SUPPLIER_PAYMENT', (int) $id, $payment['payment_no'], $payment, 'Supplier payment ' . $payment['payment_no'] . ' deleted.');

        session()->setFlashdata('success', 'Supplier payment deleted successfully.');

        return $this->response->setJSON(['status' => true, 'message' => 'Supplier payment deleted successfully.']);
    }

    /**
     * POST supplier-payments/get-bills — { supplier_id }. Returns every
     * PROJECT/GENERAL bill for that supplier with outstanding_amount > 0,
     * combined and sorted by purchase_date ASC. JSON only, per Phase C.
     */
    public function ajaxBills()
    {
        $supplierId = (int) $this->request->getPost('supplier_id');

        if ($supplierId <= 0) {
            return $this->response->setJSON(['status' => false, 'message' => 'supplier_id is required.'])->setStatusCode(422);
        }

        $bills = $this->_getOutstandingBills($supplierId);

        // Release 4.8.6D: with `with_advance=1` the response also carries the
        // supplier's unused advance ({bills, advance_available}); without it
        // the original bare bill array is returned unchanged. On the edit page
        // `exclude_payment_id` releases that voucher's own application.
        if ($this->request->getPost('with_advance')) {
            $exclude = (int) $this->request->getPost('exclude_payment_id');

            return $this->response->setJSON([
                'bills'             => $bills,
                'advance_available' => max(0.0, (new SupplierPaymentModel())->advanceSummary($supplierId, $exclude > 0 ? $exclude : null)['available']),
            ]);
        }

        return $this->response->setJSON($bills);
    }

    /**
     * Release 4.8.6F-1: a General Purchase advance voucher may only be changed
     * through its purchase, which is the single place that keeps the voucher,
     * the bill's outstanding and the bank withdrawal in step. Returns null for
     * every ordinary voucher, so the existing engine is untouched.
     */
    private function _rejectGpAdvance(array $payment, string $verb)
    {
        $gpId = SupplierPaymentModel::gpAdvanceSourceId($payment['payment_no']);

        if ($gpId === 0) {
            return null;
        }

        return $this->response->setJSON(['status' => false, 'errors' => [
            'This voucher records the advance paid on General Purchase ' . ($payment['reference_no'] ?: '#' . $gpId)
            . ' and cannot be ' . $verb . ' here. Change the Advance Paid on that purchase instead.',
        ]])->setStatusCode(422);
    }

    /** Audit detail for supplier advance applied to bills (omitted when none, so ordinary payments audit exactly as before). */
    private function _auditAdvanceUsed(array $input): array
    {
        return $input['advance_used'] > 0 ? ['advance_used' => $input['advance_used']] : [];
    }

    // =========================================================
    // SHARED HELPERS
    // =========================================================

    /**
     * Release 4.8.2C display helper for view()/edit(): the voucher header
     * plus its allocation rows, each enriched with the bill's purchase_no/
     * bill_no label for display. Read-only — no allocation math here, just
     * joins for labels the allocation table doesn't itself store.
     */
    private function _loadPaymentWithAllocations(int $id): ?array
    {
        $payment = (new SupplierPaymentModel())->find($id);

        if (! $payment) {
            return null;
        }

        $db = \Config\Database::connect();
        $supplier = $db->table('suppliers')->where('id', $payment['supplier_id'])->get()->getRowArray();

        $allocations = (new SupplierPaymentAllocationModel())->forPayment($id);
        foreach ($allocations as &$row) {
            $label               = $this->_billLabel($row['purchase_type'], (int) $row['purchase_id']);
            $row['purchase_no']  = $label['purchase_no'];
            $row['bill_no']      = $label['bill_no'];
            $row['purchase_date'] = $label['purchase_date'];
        }
        unset($row);

        return [
            'payment'     => $payment,
            'supplier'    => $supplier,
            'allocations' => $allocations,
        ];
    }

    /**
     * Display-only purchase_no/bill_no lookup for one bill — same
     * synthesized PUR-000012 style _getOutstandingBills() already uses for
     * PROJECT bills (which have no purchase_no column of their own).
     */
    private function _billLabel(string $type, int $id): array
    {
        $db = \Config\Database::connect();

        if ($type === 'PROJECT') {
            $row = $db->table('purchases')->select('invoice_no, purchase_date')->where('id', $id)->get()->getRowArray();
            return [
                'purchase_no'   => 'PUR-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT),
                'bill_no'       => $row['invoice_no'] ?? null,
                'purchase_date' => $row['purchase_date'] ?? null,
            ];
        }

        $row = $db->table('general_purchases')->select('purchase_no, bill_no, purchase_date')->where('id', $id)->get()->getRowArray();
        return [
            'purchase_no'   => $row['purchase_no'] ?? null,
            'bill_no'       => $row['bill_no'] ?? null,
            'purchase_date' => $row['purchase_date'] ?? null,
        ];
    }

    /**
     * Normalizes voucher input from either a classic form POST or a JSON
     * body (no JS is written this release, but the API is shaped so a later
     * release's front end can post allocations as a JSON array without a
     * controller change). Never trusts any bill_amount/outstanding value the
     * client might send — only purchase_type/purchase_id/paid_amount per
     * allocation are read; everything financial is recomputed server-side.
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
                'purchase_type' => strtoupper((string) ($alloc['purchase_type'] ?? '')),
                'purchase_id'   => (int) ($alloc['purchase_id'] ?? 0),
                'paid_amount'   => (float) ($alloc['paid_amount'] ?? 0),
            ];
        }

        $input = [
            'supplier_id'     => (int) $this->request->getPost('supplier_id'),
            'bank_account_id' => (int) $this->request->getPost('bank_account_id'),
            'payment_date'    => trim((string) $this->request->getPost('payment_date')),
            'payment_method'  => $this->request->getPost('payment_method'),
            'reference_no'    => $this->request->getPost('reference_no'),
            'remarks'         => $this->request->getPost('remarks'),
            'total_amount'    => (float) $this->request->getPost('total_amount'),
            'advance_amount'  => (float) ($this->request->getPost('advance_amount') ?: 0),
            // Release 4.8.6D: previously paid supplier advance applied to the
            // bills below. Not stored as a column — see SupplierPaymentModel.
            'advance_used'    => round((float) ($this->request->getPost('advance_used') ?: 0), 2),
            // Release 4.8.6E-1: the "Pay Advance to Supplier" box was ticked. Posted so
            // that a ticked box with a zero or blank amount is rejected server-side too,
            // which advance_amount alone cannot express.
            'pay_advance'     => (bool) $this->request->getPost('pay_advance'),
            // Release 4.8.6F-1A: the answer to "Use Supplier Advance / Don't Use
            // Advance". null when a client does not send it (older callers).
            'use_advance'     => $this->request->getPost('use_advance') === null ? null : (bool) $this->request->getPost('use_advance'),
            'allocations'     => $clean,
        ];

        // A voucher that moves no money (bills settled purely from advance)
        // posts nothing to any bank, so it carries no bank method/account.
        if ($input['advance_used'] > 0 && $input['total_amount'] <= 0) {
            $input['payment_method']  = SupplierPaymentModel::ADVANCE_ADJUSTMENT_METHOD;
            $input['bank_account_id'] = 0;
        }

        return $input;
    }

    /**
     * Combined outstanding-bill list across `purchases` (PROJECT) and
     * `general_purchases` (GENERAL). `purchases` has no purchase_no/bill_no
     * columns of its own, so purchase_no is synthesized as PUR-000012-style
     * from its id (matching the GP-000005 style General Purchase already
     * uses) and bill_no is its invoice_no column.
     *
     * $supplierId is optional (Release 4.8.2C addition, for the List page's
     * "Pending Bills" KPI across every supplier) — when given, behavior is
     * byte-for-byte identical to the Release 4.8.2B version this method
     * shipped with; the underlying outstanding formula is untouched either
     * way.
     */
    private function _getOutstandingBills(?int $supplierId = null): array
    {
        $db = \Config\Database::connect();

        // Release 4.9.0Z: supplier_id/name are carried on every row (not just
        // the single-supplier call) so the all-supplier Pending Payables view
        // can group and link back to a specific supplier. Byte-for-byte the
        // same bills/formula as before for the $supplierId path.
        $supplierNames = [];
        if ($supplierId) {
            $supplierNames[$supplierId] = ((new SupplierModel())->find($supplierId))['name'] ?? null;
        } else {
            foreach ((new SupplierModel())->select('id, name')->findAll() as $s) {
                $supplierNames[(int) $s['id']] = $s['name'];
            }
        }

        $bills = [];

        $projectSql    = 'SELECT id, supplier_id, purchase_date, invoice_no FROM purchases';
        $projectParams = [];
        if ($supplierId) {
            $projectSql      .= ' WHERE supplier_id = ?';
            $projectParams[]  = $supplierId;
        }
        $projectRows = $db->query($projectSql, $projectParams)->getResultArray();

        $purchaseModel = new PurchaseModel();
        foreach ($projectRows as $row) {
            $info = $purchaseModel->outstanding((int) $row['id']);
            if (! $info || $info['outstanding_amount'] <= 0.004) {
                continue;
            }

            $bills[] = [
                'purchase_type'      => 'PROJECT',
                'purchase_id'        => (int) $row['id'],
                'purchase_no'        => 'PUR-' . str_pad((string) $row['id'], 6, '0', STR_PAD_LEFT),
                'bill_no'            => $row['invoice_no'],
                'purchase_date'      => $row['purchase_date'],
                'bill_amount'        => $info['bill_amount'],
                'paid_amount'        => $info['paid_amount'],
                'outstanding_amount' => $info['outstanding_amount'],
                'payment_status'     => $this->_recalculatePaymentStatus($info['bill_amount'], $info['outstanding_amount']),
                'supplier_id'        => (int) $row['supplier_id'],
                'supplier_name'      => $supplierNames[(int) $row['supplier_id']] ?? null,
            ];
        }

        $generalSql    = 'SELECT id, supplier_id, purchase_no, purchase_date, bill_no FROM general_purchases';
        $generalParams = [];
        if ($supplierId) {
            $generalSql      .= ' WHERE supplier_id = ?';
            $generalParams[]  = $supplierId;
        }
        $generalRows = $db->query($generalSql, $generalParams)->getResultArray();

        $generalPurchaseModel = new GeneralPurchaseModel();
        foreach ($generalRows as $row) {
            $info = $generalPurchaseModel->outstanding((int) $row['id']);
            if (! $info || $info['outstanding_amount'] <= 0.004) {
                continue;
            }

            $bills[] = [
                'purchase_type'      => 'GENERAL',
                'purchase_id'        => (int) $row['id'],
                'purchase_no'        => $row['purchase_no'],
                'bill_no'            => $row['bill_no'],
                'purchase_date'      => $row['purchase_date'],
                'bill_amount'        => $info['bill_amount'],
                'paid_amount'        => $info['paid_amount'],
                'outstanding_amount' => $info['outstanding_amount'],
                'payment_status'     => $this->_recalculatePaymentStatus($info['bill_amount'], $info['outstanding_amount']),
                'supplier_id'        => (int) $row['supplier_id'],
                'supplier_name'      => $supplierNames[(int) $row['supplier_id']] ?? null,
            ];
        }

        usort($bills, static fn ($a, $b) => strcmp((string) $a['purchase_date'], (string) $b['purchase_date']));

        return $bills;
    }

    /**
     * Validates a voucher's shape and every allocation's amount against that
     * bill's live outstanding balance (Phase G). Never trusts any
     * bill_amount the client sent — always re-reads it via
     * PurchaseModel::outstanding()/GeneralPurchaseModel::outstanding().
     * Returns an array of error strings; empty means valid.
     */
    private function _validateAllocations(array $input, ?int $excludePaymentId = null): array
    {
        $errors = [];

        if ($input['supplier_id'] <= 0) {
            $errors[] = 'Supplier is required.';
        }
        // Release 4.8.6E-1: payment method is mandatory. A voucher that settles its bills
        // purely from supplier advance moves no money and is stamped
        // ADVANCE_ADJUSTMENT_METHOD by _extractInput(), so it satisfies this rule too.
        if (trim((string) $input['payment_method']) === '') {
            $errors[] = 'Payment method is required.';
        }
        // Release 4.8.6E-1: "Pay Advance to Supplier" ticked with no amount.
        if (! empty($input['pay_advance']) && $input['advance_amount'] <= 0) {
            $errors[] = 'Enter an advance amount greater than zero, or untick "Pay Advance to Supplier".';
        }
        if (! CashOpeningGuard::isValidDate($input['payment_date'])) { // Release 4.9.0CF: strict calendar date
            $errors[] = 'A valid payment date is required.';
        }
        // Release 4.8.6D: a voucher that settles bills purely from advance
        // legitimately has a zero cash/bank total.
        if ($input['total_amount'] <= 0 && $input['advance_used'] <= 0) {
            $errors[] = 'Total payment must be greater than zero.';
        }
        if ($input['advance_amount'] < 0) {
            $errors[] = 'Advance amount cannot be negative.';
        }
        if ($input['advance_used'] < 0) {
            $errors[] = 'Supplier advance used cannot be negative.';
        }
        // Release 4.8.6E: the cash/bank part of a payment can never be negative.
        if ($input['total_amount'] < 0) {
            $errors[] = 'Cash / bank payment cannot be negative.';
        }
        // Release 4.8.6F-1A: "Don't Use Advance" means no advance is applied.
        if ($input['use_advance'] === false && $input['advance_used'] > 0) {
            $errors[] = 'Advance cannot be used when "Don\'t Use Advance" is selected.';
        }
        if ($input['advance_used'] > 0 && empty($input['allocations'])) {
            $errors[] = 'Select at least one bill to use supplier advance against.';
        }
        if (empty($input['allocations']) && $input['advance_amount'] <= 0) {
            $errors[] = 'At least one bill allocation or an advance payment is required.';
        }

        $seen          = [];
        $allocationSum = 0.0;

        foreach ($input['allocations'] as $alloc) {
            $type = $alloc['purchase_type'];
            $pid  = $alloc['purchase_id'];
            $paid = $alloc['paid_amount'];

            if (! in_array($type, ['PROJECT', 'GENERAL'], true) || $pid <= 0) {
                $errors[] = 'Each allocation must reference a valid bill.';
                continue;
            }

            $key = $type . '-' . $pid;
            if (isset($seen[$key])) {
                $errors[] = "Bill {$type} #{$pid} is selected more than once in this voucher.";
                continue;
            }
            $seen[$key] = true;

            if ($paid <= 0) {
                $errors[] = "Paid amount for {$type} #{$pid} must be greater than zero.";
                continue;
            }

            $model = $type === 'PROJECT' ? new PurchaseModel() : new GeneralPurchaseModel();
            $info  = $model->outstanding($pid);

            if ($info === null) {
                $errors[] = "{$type} bill #{$pid} was not found.";
                continue;
            }

            if ($paid > $info['outstanding_amount'] + 0.004) {
                $errors[] = "Paid amount for {$type} #{$pid} exceeds its outstanding balance of " . number_format($info['outstanding_amount'], 2) . '.';
                continue;
            }

            $allocationSum += $paid;
        }

        if (! $errors && $input['advance_used'] > 0) {
            // Release 4.8.6D: settle bills from previously paid advance.
            $available = (new SupplierPaymentModel())->advanceSummary($input['supplier_id'], $excludePaymentId)['available'];

            if ($input['advance_used'] > $available + 0.004) {
                $errors[] = 'Supplier advance used (' . number_format($input['advance_used'], 2) . ') exceeds the advance available (' . number_format(max(0, $available), 2) . ').';
            }
            if ($input['advance_used'] > $allocationSum + 0.004) {
                $errors[] = 'Supplier advance used (' . number_format($input['advance_used'], 2) . ') exceeds the selected bill outstanding (' . number_format($allocationSum, 2) . ').';
            }
        }

        // Editing a voucher must not shrink its own advance below what other
        // vouchers have already applied to bills.
        if (! $errors && $excludePaymentId !== null) {
            $others = (new SupplierPaymentModel())->advanceSummary($input['supplier_id'], $excludePaymentId)['available'];

            if ($others + $input['advance_amount'] - $input['advance_used'] < -0.004) {
                $errors[] = 'This voucher\'s advance has already been applied to supplier bills, so it cannot be reduced below ' . number_format(max(0, -$others + $input['advance_used']), 2) . '.';
            }
        }

        // Money leaving = bills settled in cash/bank (allocations minus the part
        // settled from advance) + any new advance paid today. With no advance
        // used this is exactly the original allocations + advance rule.
        // Release 4.8.6E: the Payment Amount field is the cash/bank part of the voucher
        // and may be trimmed for a partial payment — but only by allocating less to the
        // bills, so the two figures must still reconcile exactly. Over-paying the bills
        // selected gets its own message, because that is the rule accountants hit.
        if (! $errors) {
            $expected = round($allocationSum - $input['advance_used'] + $input['advance_amount'], 2);

            if ($input['total_amount'] > $expected + 0.01) {
                $errors[] = 'Payment amount (' . number_format($input['total_amount'], 2) . ') exceeds the outstanding after advance (' . number_format(max(0, $expected), 2) . ').';
            } elseif ($input['total_amount'] < $expected - 0.01) {
                $errors[] = 'Total payment must equal the sum of bill allocations plus the advance amount, less any supplier advance used.';
            }
        }

        return $errors;
    }

    /**
     * Inserts one supplier_payment_allocations row per bill and applies it to
     * that bill's outstanding balance. Reads each bill's outstanding
     * immediately before inserting its row, so multiple allocations to
     * different bills within the same voucher never see stale figures.
     */
    private function _insertAllocations(int $paymentId, array $allocations): void
    {
        if (empty($allocations)) {
            return;
        }

        $allocationModel = new SupplierPaymentAllocationModel();

        foreach ($allocations as $alloc) {
            $type = $alloc['purchase_type'];
            $pid  = $alloc['purchase_id'];
            $paid = $alloc['paid_amount'];

            $model = $type === 'PROJECT' ? new PurchaseModel() : new GeneralPurchaseModel();
            $info  = $model->outstanding($pid);

            $newOutstanding = round($info['outstanding_amount'] - $paid, 2);

            $allocationModel->insert([
                'supplier_payment_id' => $paymentId,
                'purchase_type'       => $type,
                'purchase_id'         => $pid,
                'bill_amount'         => $info['bill_amount'],
                'paid_amount'         => $paid,
                'balance_amount'      => $newOutstanding,
            ]);

            $this->_applyAllocation($type, $pid, $newOutstanding, $info['bill_amount']);
        }
    }

    /**
     * Writes the refreshed outstanding_amount/payment_status back onto the
     * bill's own row — GENERAL only, since `purchases` (PROJECT) has no such
     * columns to write (see class docblock). Never touches grand_total or
     * total_amount.
     */
    private function _applyAllocation(string $type, int $purchaseId, float $newOutstanding, float $billAmount): void
    {
        if ($type !== 'GENERAL') {
            return;
        }

        \Config\Database::connect()->table('general_purchases')
            ->where('id', $purchaseId)
            ->update([
                'outstanding_amount' => $newOutstanding,
                'payment_status'     => $this->_recalculatePaymentStatus($billAmount, $newOutstanding),
            ]);
    }

    /**
     * Deletes every allocation row belonging to one voucher, then refreshes
     * the outstanding_amount/payment_status of each distinct bill they
     * touched (GENERAL only — PROJECT is always derived live, nothing cached
     * to refresh). Used by both update() (delete-then-reapply) and delete().
     */
    private function _restoreVoucherAllocations(int $paymentId): void
    {
        $allocationModel = new SupplierPaymentAllocationModel();
        $old             = $allocationModel->forPayment($paymentId);

        $allocationModel->where('supplier_payment_id', $paymentId)->delete();

        $touched = [];
        foreach ($old as $row) {
            $touched[$row['purchase_type'] . '-' . $row['purchase_id']] = [$row['purchase_type'], (int) $row['purchase_id']];
        }

        foreach ($touched as [$type, $pid]) {
            $this->_restoreAllocation($type, $pid);
        }
    }

    /**
     * Recomputes one bill's outstanding balance from
     * supplier_payment_allocations (after its old rows were just deleted) and
     * writes it back — GENERAL only, same reasoning as _applyAllocation().
     */
    private function _restoreAllocation(string $type, int $purchaseId): void
    {
        if ($type !== 'GENERAL') {
            return;
        }

        $model = new GeneralPurchaseModel();
        $info  = $model->outstanding($purchaseId);

        if ($info === null) {
            return;
        }

        \Config\Database::connect()->table('general_purchases')
            ->where('id', $purchaseId)
            ->update([
                'outstanding_amount' => $info['outstanding_amount'],
                'payment_status'     => $this->_recalculatePaymentStatus($info['bill_amount'], $info['outstanding_amount']),
            ]);
    }

    /**
     * Single shared status rule (Pending/Partial/Paid), used everywhere a
     * bill's outstanding changes. Epsilon-based comparisons match the
     * threshold GeneralPurchases::_derivePaymentStatus() already uses.
     */
    private function _recalculatePaymentStatus(float $billAmount, float $outstanding): string
    {
        if ($outstanding <= 0.004) {
            return 'Paid';
        }
        if (abs($outstanding - $billAmount) <= 0.004) {
            return 'Pending';
        }
        return 'Partial';
    }

    // =========================================================
    // BANK INTEGRATION (Release 4.8.3C)
    // Balance math lives in BankTransactionModel::updateBankBalance()/
    // restoreBankBalance(); these two only decide *whether* and *what* to
    // post for a voucher. Cash (or an unspecified method) posts nothing.
    // =========================================================

    /** Accounts the "Pay From Bank Account" dropdown may offer (active only). */
    private function _activeBankAccounts(): array
    {
        return (new BankAccountModel())->where('is_active', 1)->orderBy('bank_name', 'ASC')->findAll();
    }

    /**
     * A bank-type payment method must name an existing, active bank account.
     * Kept separate from _validateAllocations() so the allocation engine's
     * validation is untouched.
     */
    private function _validateBankAccount(array $input): array
    {
        if (! BankTransactionModel::isBankMethod($input['payment_method'])) {
            return [];
        }
        if ($input['bank_account_id'] <= 0) {
            return ['A bank account is required for Bank Transfer, Cheque and UPI payments.'];
        }

        $account = (new BankAccountModel())->find($input['bank_account_id']);
        if (! $account) {
            return ['The selected bank account was not found.'];
        }
        if ((int) $account['is_active'] !== 1) {
            return ['Transactions cannot be posted against an inactive bank account.'];
        }

        return [];
    }

    /** Posts the WITHDRAWAL for one voucher; no-op unless paid through a bank. */
    private function _createBankTransaction(int $paymentId, string $paymentNo, array $input): void
    {
        if (! BankTransactionModel::isBankMethod($input['payment_method'])) {
            return;
        }

        $supplier = (new SupplierModel())->find($input['supplier_id']);

        (new BankTransactionModel())->createBankTransaction([
            'bank_account_id'  => $input['bank_account_id'],
            'transaction_date' => $input['payment_date'],
            'transaction_type' => 'WITHDRAWAL',
            'amount'           => $input['total_amount'],
            'reference_type'   => 'SUPPLIER_PAYMENT',
            'reference_id'     => $paymentId,
            'reference_no'     => $paymentNo,
            'remarks'          => 'Supplier Payment - ' . ($supplier['name'] ?? 'Unknown Supplier'),
            'created_by'       => session()->get('user_id'),
        ]);
    }

    /** Reverses and removes whatever was posted for one voucher (0 rows is fine). */
    private function _deleteBankTransaction(int $paymentId): void
    {
        (new BankTransactionModel())->deleteBankTransaction('SUPPLIER_PAYMENT', $paymentId);
    }
}
