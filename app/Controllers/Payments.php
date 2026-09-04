<?php

namespace App\Controllers;

use App\Models\SaleModel;
use App\Models\ProjectModel;
use CodeIgniter\Controller;

class Payments extends Controller
{
    public function index()
    {
        $db = \Config\Database::connect();
        $data['payments'] = $db->query("
            SELECT py.*, s.invoice_no, s.total_amount,
                   p.name AS project_name, c.name AS customer_name
            FROM payments py
            LEFT JOIN sales s ON py.sale_id = s.id
            LEFT JOIN projects p ON s.project_id = p.id
            LEFT JOIN customers c ON s.customer_id = c.id
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
                $map[$pid] = [
                    'cash_received'       => $cashReceived,
                    'net_outstanding'     => (float) ($summary['net_outstanding_collection_balance'] ?? 0),
                    'remaining_balance'   => (float) ($summary['remaining_balance_display'] ?? 0),
                    'total_customer_paid' => (float) ($summary['cash_received_combined'] ?? 0),
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
        $payDate   = $this->request->getPost('payment_date');
        $method    = $this->request->getPost('method');
        $reference = $this->request->getPost('reference');
        $notes     = $this->request->getPost('notes');

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

        $db->transStart();

        $db->table('payments')->insert([
            'sale_id'      => $saleId,
            'amount'       => $amount,
            'payment_date' => $payDate,
            'method'       => $method,
            'reference'    => $reference,
            'notes'        => $notes,
        ]);

        // Release 1.6.4 (Rules 6-7): paid_amount/balance_amount/status
        // recomputed from source (advance_applied stays untouched — a
        // payment never re-triggers advance FIFO).
        $saleModel->recalculatePaymentState($saleId);

        $db->transComplete();
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
        $payDate   = $this->request->getPost('payment_date');
        $method    = $this->request->getPost('method');
        $reference = $this->request->getPost('reference');
        $notes     = $this->request->getPost('notes');

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

        $db->transStart();

        $db->table('payments')->where('id', $id)->update([
            'sale_id'      => $newSaleId,
            'amount'       => $newAmount,
            'payment_date' => $payDate,
            'method'       => $method,
            'reference'    => $reference,
            'notes'        => $notes,
        ]);

        // Release 1.6.4 (Rule 6): recompute from source for the (possibly
        // two) affected invoices — advance_applied is never touched here.
        $saleModel->recalculatePaymentState($newSaleId);
        if ($oldSaleId !== $newSaleId) {
            $saleModel->recalculatePaymentState($oldSaleId);
        }

        $db->transComplete();

        return redirect()->to('/payments')->with('success', 'Payment updated successfully.');
    }

    public function delete($id)
    {
        $db = \Config\Database::connect();
        $saleModel = new SaleModel();

        $payment = $db->query("SELECT * FROM payments WHERE id=?", [$id])->getRowArray();

        $db->transStart();

        $db->table('payments')->where('id', $id)->delete();

        // Release 1.6.4 (Rule 6): recompute from source — advance_applied
        // stays attached to the project/invoice, never removed by a
        // payment deletion.
        if ($payment) {
            $saleModel->recalculatePaymentState((int) $payment['sale_id']);
        }

        $db->transComplete();
        return redirect()->to('/payments')->with('success', 'Payment deleted successfully.');
    }
}
