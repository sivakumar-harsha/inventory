<?php

namespace App\Controllers;

use App\Libraries\CashOpeningGuard;
use App\Models\SaleModel;
use App\Models\ProjectModel;
use App\Models\BankAccountModel;
use App\Models\BankTransactionModel;
use CodeIgniter\Controller;

class Payments extends Controller
{
    // Release 4.9.0I: reference_type for the automatic bank DEPOSIT posted by
    // an Invoice Payment (payments.id). Deliberately distinct from
    // CustomerPayments' own CUSTOMER_PAYMENT reference_type — the two
    // controllers key off different tables (payments vs customer_payments),
    // whose ids can collide, and createBankTransaction() de-dupes purely on
    // (reference_type, reference_id).
    private const REFERENCE_TYPE = 'SALE_PAYMENT';

    public function index()
    {
        $db = \Config\Database::connect();
        $data['payments'] = $db->query("
            SELECT py.*, s.invoice_no, s.total_amount,
                   p.name AS project_name, c.name AS customer_name, ba.bank_name AS bank_name
            FROM payments py
            LEFT JOIN sales s ON py.sale_id = s.id
            LEFT JOIN projects p ON s.project_id = p.id
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN bank_accounts ba ON ba.id = py.bank_account_id
            ORDER BY py.created_at DESC
        ")->getResultArray();

        // Release 4.0B (Phase C): Project filter options built from the
        // project_name already present on each fetched payment row — no
        // new query.
        $projectNames = [];
        foreach ($data['payments'] as $p) {
            if (!empty($p['project_name'])) {
                $projectNames[$p['project_name']] = true;
            }
        }
        $data['projects'] = array_keys($projectNames);
        sort($data['projects']);

        return view('payments/index', $data);
    }

    public function create($saleId = null)
    {
        $db = \Config\Database::connect();

        // Release 1.6.4 (Rule 4): total_amount/advance_applied/paid_amount/
        // balance_amount are all advance-aware already (SaleModel keeps them
        // in sync) — the dropdown's "Balance" is the pending-after-advance
        // figure straight from the column, no extra computation needed here.
        //
        // Release 2.1B (Phase 2, UI only): the previous status != 'PAID'
        // filter is dropped so every invoice for a project is visible on its
        // card for context (Final §2 of RELEASE_2_1A_PROJECT_WORKFLOW_DESIGN.md);
        // project_id/sale_date/status are added to the SELECT list purely so
        // the invoice cards and the Step 1 project filter can render — no new
        // calculation, same columns other controllers already read.
        $data['sales'] = $db->query("
            SELECT s.id, s.invoice_no, s.sale_date, s.status, s.project_id,
                   s.total_amount, s.advance_applied, s.paid_amount, s.balance_amount,
                   p.name AS project_name, c.name AS customer_name
            FROM sales s
            LEFT JOIN projects p ON s.project_id = p.id
            LEFT JOIN customers c ON s.customer_id = c.id
            ORDER BY s.sale_date DESC
        ")->getResultArray();

        // Release 2.1C (Bug 2, Issue A): Step 1 project list is Active
        // projects only — Completed projects shouldn't accept new payments
        // from this screen.
        // Release 4.5.4 (Phase D): customer_name added so the Project Cash
        // Receipt mode's Project dropdown can auto-fill Customer, same as
        // project_cash_receipts/create.php.
        $data['projects'] = $db->query("
            SELECT p.id, p.name, c.name AS customer_name
            FROM projects p
            LEFT JOIN customers c ON p.customer_id = c.id
            WHERE p.status = 'ACTIVE'
            ORDER BY p.name ASC
        ")->getResultArray();

        // Release 4.5.3/4.5.4: per-project { cash_received, net_outstanding },
        // for the Step 3 Invoice Payment cards (Phase C) and the Project Cash
        // Receipt mode summary cards (Phase D). Covers every project that has
        // an invoice plus every Active project (so the Cash Receipt dropdown,
        // which lists Active projects with no invoices yet too, is covered).
        // Reuses ProjectModel::getFinancialSummary() — no new calculation.
        $data['project_financials'] = $this->buildProjectFinancialsMap(
            array_merge(array_column($data['sales'], 'project_id'), array_column($data['projects'], 'id'))
        );

        // Release 4.9.0I: active bank accounts for the Invoice Payment form's
        // new Bank Account field (Bank Transfer/Check only) — the Project
        // Cash Receipt form below already falls back to this same query
        // under the same $bankAccounts name if it isn't set, so this one
        // query now serves both.
        $data['bankAccounts'] = (new BankAccountModel())->where('is_active', 1)->orderBy('bank_name', 'ASC')->findAll();

        $data['selected_sale_id'] = $saleId;
        return view('payments/create', $data);
    }

    // Release 4.5.3/4.5.4/4.5.7: builds { project_id: { cash_received,
    // net_outstanding, remaining_balance, total_customer_paid } } for the
    // given list of project ids, using ProjectModel's existing
    // getFinancialSummary() so every figure matches Dashboard/Statement/
    // Balance Sheet exactly — no new calculation, just exposed here for the
    // Cash Receipt mode's live preview cards.
    // Release 4.6.5: remaining_balance/total_customer_paid now read the
    // model's own remaining_balance_display/cash_received_combined fields
    // (Project Cash Receipts split ADVANCE vs DIRECT_INCOME internally)
    // instead of re-deriving the same formula inline — same values as
    // before this release for any project whose receipts are all ADVANCE
    // (the default), since remaining_balance_display/cash_received_combined
    // are defined identically to the old inline formulas.
    private function buildProjectFinancialsMap(array $projectIds): array
    {
        $projectModel = new ProjectModel();
        $map = [];
        foreach ($projectIds as $pid) {
            $pid = (int) $pid;
            if ($pid && !isset($map[$pid])) {
                $summary       = $projectModel->getFinancialSummary($pid);
                $cashReceived  = (float) ($summary['total_cash_received'] ?? 0);
                // Release 4.9.0Q (bug fix): this map's key is named 'total_customer_paid'
                // and the Cash Receipt form's own chip is labeled "Customer Paid Total
                // After Receipt" — it must read the model's actual total_customer_paid
                // field (advance_amount + total_advance_receipts + total_paid, excludes
                // Direct Income per Release 4.8.6A-1's own "not a customer collection"
                // rule), the same field Project Detail/Statement/Exports show under the
                // identical "Total Customer Paid" label. It previously read
                // cash_received_combined instead — a different concept (excludes the
                // upfront Advance, includes Direct Income) that happens to share the
                // word "paid"/"received" but is not what this label means (Release
                // 4.9.0O, Finding #2). remaining_balance/cash_received above are
                // untouched — those preview a genuinely different concept
                // (remaining_balance_display) on purpose, unrelated to this fix.
                $map[$pid] = [
                    'cash_received'       => $cashReceived,
                    'net_outstanding'     => (float) ($summary['net_outstanding_collection_balance'] ?? 0),
                    // Release 4.9.0EC: the one authoritative Project Remaining Balance
                    // (Contract - Advance Received - Total Invoiced), same as Project
                    // View/Statement/List/Dashboard. remaining_balance_display (net of
                    // receipts) is a different concept and is no longer read here.
                    'remaining_balance'   => (float) ($summary['remaining_billable_value'] ?? 0),
                    'total_customer_paid' => (float) ($summary['total_customer_paid'] ?? 0),
                ];
            }
        }
        return $map;
    }

    public function store()
    {
        $db = \Config\Database::connect();
        $saleModel = new SaleModel();

        $saleId    = (int) $this->request->getPost('sale_id');
        $amount    = (float) $this->request->getPost('amount');
        $payDate   = trim((string) $this->request->getPost('payment_date'));
        $method    = $this->request->getPost('method');
        $reference = $this->request->getPost('reference');
        $notes     = $this->request->getPost('notes');
        // Release 4.9.0I: the receiving bank account for Bank Transfer/Check;
        // never stored (and never required) for Cash/Other.
        $bankAccountId = (int) $this->request->getPost('bank_account_id');

        // Release 4.9.0CF: a real calendar date is required (a blank or 0000-00-00 date used to be stored).
        if (! CashOpeningGuard::isValidDate($payDate)) {
            return redirect()->back()->withInput()->with('error', 'Enter a valid payment date.');
        }

        $sale = $db->query("SELECT * FROM sales WHERE id = ?", [$saleId])->getRowArray();
        if (!$sale) {
            return redirect()->back()->withInput()->with('error', 'Selected sale/invoice not found.');
        }

        // Release 1.6.4 (Rule 5): pending amount already excludes advance —
        // reject any payment that would overpay the invoice.
        $pending = (float) $sale['balance_amount'];
        if ($amount > $pending + 0.01) {
            return redirect()->back()->withInput()->with('error',
                'Payment amount (' . number_format($amount, 2) . ') exceeds the pending amount (' . number_format($pending, 2) . ') for this invoice.');
        }

        // Release 4.9.0I: server-side gate — Bank Transfer/Check must name a
        // real, active bank account before anything is written. Never rely
        // on the JS toggle alone.
        $bankError = $this->_validateBankAccount($method, $bankAccountId);
        if ($bankError) {
            return redirect()->back()->withInput()->with('error', $bankError);
        }

        // Release 4.9.0CF: a CASH payment dated before the Cash Opening Date is warned about, never blocked.
        if ($warn = CashOpeningGuard::gate($this->request, 'invoice-payment', 0, $method, $payDate)) {
            return $warn;
        }

        $db->transStart();

        try {
            $db->table('payments')->insert([
                'sale_id'         => $saleId,
                'amount'          => $amount,
                'payment_date'    => $payDate,
                'method'          => $method,
                'reference'       => $reference,
                'notes'           => $notes,
                'bank_account_id' => BankTransactionModel::isBankMethod($method) ? $bankAccountId : null,
            ]);
            $paymentId = (int) $db->insertID();

            // Release 1.6.4 (Rules 6-7): paid_amount/balance_amount/status
            // recomputed from source (advance_applied stays untouched — a
            // payment never re-triggers advance FIFO).
            $saleModel->recalculatePaymentState($saleId);

            // Release 4.9.0I: the actual cash inflow — posted only for
            // Bank Transfer/Check, never for the advance already applied to
            // this invoice (that was posted, if at all, when the advance
            // itself was received).
            $this->_postBankDeposit($paymentId, (string) ($sale['invoice_no'] ?: ('Sale #' . $saleId)), $bankAccountId, $amount, (string) $payDate, $method);
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', 'Failed to record payment: ' . $e->getMessage());
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', 'Failed to record payment due to a database error.');
        }

        return redirect()->to('/payments')->with('success', 'Payment recorded successfully.');
    }

    public function edit($id)
    {
        $db = \Config\Database::connect();

        $data['payment'] = $db->query("SELECT * FROM payments WHERE id=?", [$id])->getRowArray();

        // Release 2.1B (Phase 2, UI only): same SELECT-list widening as
        // create() — project_id/sale_date/status added for the invoice cards
        // and Step 1 project filter, no calculation change.
        $data['sales'] = $db->query("
            SELECT s.id, s.invoice_no, s.sale_date, s.status, s.project_id,
                   s.total_amount, s.advance_applied, s.paid_amount, s.balance_amount,
                   p.name AS project_name, c.name AS customer_name
            FROM sales s
            LEFT JOIN projects p ON s.project_id = p.id
            LEFT JOIN customers c ON s.customer_id = c.id
            ORDER BY s.sale_date DESC
        ")->getResultArray();

        // Release 2.1C (Bug 2, Issue A): Active projects only, same as
        // create() — but this payment's own project must still be selectable
        // even if it has since been marked Completed, so the edit screen can
        // still auto-select it (append it if the Active-only query excluded it).
        $data['projects'] = $db->query("
            SELECT id, name FROM projects WHERE status = 'ACTIVE' ORDER BY name ASC
        ")->getResultArray();

        $currentProjectId = null;
        foreach ($data['sales'] as $s) {
            if ($s['id'] == $data['payment']['sale_id']) {
                $currentProjectId = $s['project_id'];
                break;
            }
        }
        if ($currentProjectId) {
            $alreadyListed = array_filter($data['projects'], fn ($p) => $p['id'] == $currentProjectId);
            if (!$alreadyListed) {
                $currentProject = $db->query("SELECT id, name FROM projects WHERE id = ?", [$currentProjectId])->getRowArray();
                if ($currentProject) {
                    $data['projects'][] = $currentProject;
                    usort($data['projects'], fn ($a, $b) => strcasecmp($a['name'], $b['name']));
                }
            }
        }

        $data['project_financials'] = $this->buildProjectFinancialsMap(array_column($data['sales'], 'project_id'));

        // Release 4.9.0I: same active-bank-accounts list as create(), so the
        // edit screen's Bank Account field can render and pre-select
        // $payment['bank_account_id'].
        $data['bankAccounts'] = (new BankAccountModel())->where('is_active', 1)->orderBy('bank_name', 'ASC')->findAll();

        return view('payments/edit', $data);
    }

    public function update($id)
    {
        $db = \Config\Database::connect();
        $saleModel = new SaleModel();

        $payment = $db->query("SELECT * FROM payments WHERE id=?", [$id])->getRowArray();
        if (!$payment) {
            return redirect()->to('/payments')->with('error', 'Payment not found');
        }

        $oldSaleId = (int) $payment['sale_id'];
        $oldAmount = (float) $payment['amount'];

        $newSaleId = (int) $this->request->getPost('sale_id');
        $newAmount = (float) $this->request->getPost('amount');
        $payDate   = trim((string) $this->request->getPost('payment_date'));
        $method    = $this->request->getPost('method');
        $reference = $this->request->getPost('reference');
        $notes     = $this->request->getPost('notes');
        $bankAccountId = (int) $this->request->getPost('bank_account_id');

        if (! CashOpeningGuard::isValidDate($payDate)) {
            return redirect()->back()->withInput()->with('error', 'Enter a valid payment date.');
        }

        $newSale = $db->query("SELECT * FROM sales WHERE id=?", [$newSaleId])->getRowArray();
        if (!$newSale) {
            return redirect()->back()->withInput()->with('error', 'Selected sale/invoice not found.');
        }

        // Release 1.6.4 (Rule 5): validate against pending recomputed as if
        // this payment's old amount were first removed — same invoice keeps
        // the room the old payment was occupying; a different invoice is
        // validated against its own current pending (untouched by this
        // payment today).
        $pendingBeforeThisPayment = ($oldSaleId === $newSaleId)
            ? (float) $newSale['balance_amount'] + $oldAmount
            : (float) $newSale['balance_amount'];

        if ($newAmount > $pendingBeforeThisPayment + 0.01) {
            return redirect()->back()->withInput()->with('error',
                'Payment amount (' . number_format($newAmount, 2) . ') exceeds the pending amount (' . number_format($pendingBeforeThisPayment, 2) . ') for this invoice.');
        }

        $bankError = $this->_validateBankAccount($method, $bankAccountId);
        if ($bankError) {
            return redirect()->back()->withInput()->with('error', $bankError);
        }

        // Release 4.9.0CF: a CASH payment dated before the Cash Opening Date is warned about, never blocked.
        if ($warn = CashOpeningGuard::gate($this->request, 'invoice-payment', (int) $id, $method, $payDate)) {
            return $warn;
        }

        $db->transStart();

        try {
            // Release 4.9.0I: reverse this payment's existing bank posting
            // (if any) before reposting under the possibly-new
            // method/account/amount — same reverse-then-repost pattern as
            // CustomerPayments::update()/ProjectCashReceipts.
            $this->_deleteBankDeposit((int) $id);

            $db->table('payments')->where('id', $id)->update([
                'sale_id'         => $newSaleId,
                'amount'          => $newAmount,
                'payment_date'    => $payDate,
                'method'          => $method,
                'reference'       => $reference,
                'notes'           => $notes,
                'bank_account_id' => BankTransactionModel::isBankMethod($method) ? $bankAccountId : null,
            ]);

            // Release 1.6.4 (Rule 6): recompute from source for the (possibly
            // two) affected invoices — advance_applied is never touched here.
            $saleModel->recalculatePaymentState($newSaleId);
            if ($oldSaleId !== $newSaleId) {
                $saleModel->recalculatePaymentState($oldSaleId);
            }

            $this->_postBankDeposit((int) $id, (string) ($newSale['invoice_no'] ?: ('Sale #' . $newSaleId)), $bankAccountId, $newAmount, (string) $payDate, $method);
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', 'Failed to update payment: ' . $e->getMessage());
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', 'Failed to update payment due to a database error.');
        }

        return redirect()->to('/payments')->with('success', 'Payment updated successfully.');
    }

    public function delete($id)
    {
        $db = \Config\Database::connect();
        $saleModel = new SaleModel();

        $payment = $db->query("SELECT * FROM payments WHERE id=?", [$id])->getRowArray();

        $db->transStart();

        try {
            // Release 4.9.0I: reverse whatever bank DEPOSIT this payment
            // posted before the row itself disappears (0 rows is fine for a
            // Cash/Other payment or one saved before this release).
            $this->_deleteBankDeposit((int) $id);

            $db->table('payments')->where('id', $id)->delete();

            // Release 1.6.4 (Rule 6): recompute from source — advance_applied
            // stays attached to the project/invoice, never removed by a
            // payment deletion.
            if ($payment) {
                $saleModel->recalculatePaymentState((int) $payment['sale_id']);
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->to('/payments')->with('error', 'Failed to delete payment: ' . $e->getMessage());
        }

        $db->transComplete();
        return redirect()->to('/payments')->with('success', 'Payment deleted successfully.');
    }

    // =========================================================
    // BANK INTEGRATION (Release 4.9.0I)
    // Mirrors ProjectCashReceipts' _validateBankAccount()/_createBankTransaction()
    // and CustomerPayments' reverse-then-repost edit pattern — no new
    // accounting mechanism, just this controller's own reference_type.
    // =========================================================

    /** Returns an error message, or null when no bank account is needed or the one given is usable. */
    private function _validateBankAccount(?string $method, int $bankAccountId): ?string
    {
        if (! BankTransactionModel::isBankMethod($method)) {
            return null;
        }
        if ($bankAccountId <= 0) {
            return 'A bank account is required for Bank Transfer and Check payments.';
        }

        $account = (new BankAccountModel())->find($bankAccountId);
        if (! $account) {
            return 'The selected bank account was not found.';
        }
        if ((int) $account['is_active'] !== 1) {
            return 'Payments cannot be posted against an inactive bank account.';
        }

        return null;
    }

    /** Posts the DEPOSIT for one invoice payment; no-op unless paid through a bank. */
    private function _postBankDeposit(int $paymentId, string $invoiceNo, int $bankAccountId, float $amount, string $date, ?string $method): void
    {
        if (! BankTransactionModel::isBankMethod($method)) {
            return;
        }

        (new BankTransactionModel())->createBankTransaction([
            'bank_account_id'  => $bankAccountId,
            'transaction_date' => $date,
            'transaction_type' => 'DEPOSIT',
            'amount'           => $amount,
            'reference_type'   => self::REFERENCE_TYPE,
            'reference_id'     => $paymentId,
            'reference_no'     => $invoiceNo,
            'remarks'          => 'Invoice Payment - ' . $invoiceNo,
            'created_by'       => session()->get('user_id'),
        ]);
    }

    /** Reverses and removes whatever was posted for one invoice payment (0 rows is fine). */
    private function _deleteBankDeposit(int $paymentId): void
    {
        (new BankTransactionModel())->deleteBankTransaction(self::REFERENCE_TYPE, $paymentId);
    }
}
