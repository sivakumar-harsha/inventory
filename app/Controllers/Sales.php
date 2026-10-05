<?php

namespace App\Controllers;

use App\Models\SaleModel;
use App\Models\ProductModel;
use App\Models\CustomerModel;
use App\Models\ProjectModel;
use App\Models\StockLedgerModel;
use CodeIgniter\Controller;

class Sales extends Controller
{
    private const ALLOCATION_UNAVAILABLE = 'Advance allocation is unavailable until the pending migration is applied.';

    public function index()
    {
        $db = \Config\Database::connect();
		
		$data['customers'] = (new CustomerModel())->orderBy('name','ASC')->findAll();

		// Release 4.0B.2: Project filter dropdown must list every project
		// (ACTIVE and COMPLETED) — invoices can belong to either, so an
		// Active-only list hid COMPLETED projects' invoices from the filter.
		// Same ProjectModel call style already used unfiltered in edit().
		$data['projects'] = (new ProjectModel())->orderBy('name', 'ASC')->findAll();

        // Release 4.0D: p.billing_status added so the Completed Sales tab can
        // show every invoice of a project whose billing status was manually
        // marked COMPLETED (Projects::markBillingComplete()) — read-only,
        // no calculation added.
        $data['sales'] = $db->query("
            SELECT s.*, p.name AS project_name, c.name AS customer_name, p.billing_status AS project_billing_status
            FROM sales s
            LEFT JOIN projects p ON s.project_id = p.id
            LEFT JOIN customers c ON s.customer_id = c.id
            ORDER BY s.created_at DESC
        ")->getResultArray();
        return view('sales/index', $data);
    }

    public function create()
    {
        $data['projects']  = (new ProjectModel())->where('status','ACTIVE')->orderBy('name','ASC')->findAll();
        $data['customers'] = (new CustomerModel())->orderBy('name','ASC')->findAll();
        return view('sales/create', $data);
    }

    /**
     * AJAX endpoint: Load products with available stock based on source and project
     */
    public function getProducts()
    {
        $source    = $this->request->getGet('source');
        $projectId = $this->request->getGet('project_id');

        $db = \Config\Database::connect();

        if ($source === 'GENERAL') {
            $query = "
                SELECT
                    p.id,
                    p.name,
                    p.unit,
                    p.selling_price,
					p.gst_percent,
                    COALESCE(SUM(CASE WHEN sl.transaction_type = 'IN' THEN sl.quantity ELSE 0 END), 0)
                    - COALESCE(SUM(CASE WHEN sl.transaction_type = 'OUT' THEN sl.quantity ELSE 0 END), 0)
                    AS available_qty
                FROM products p
                LEFT JOIN stock_ledger sl ON sl.product_id = p.id AND sl.source = 'GENERAL'
                GROUP BY p.id, p.name, p.unit, p.selling_price
                HAVING available_qty > 0
                ORDER BY p.name ASC
            ";
            $result = $db->query($query)->getResultArray();
        } elseif ($source === 'PROJECT' && $projectId) {
            $query = "
                SELECT
                    p.id,
                    p.name,
                    p.unit,
                    p.selling_price,
					p.gst_percent,
                    COALESCE(SUM(CASE WHEN sl.transaction_type = 'IN' THEN sl.quantity ELSE 0 END), 0)
                    - COALESCE(SUM(CASE WHEN sl.transaction_type = 'OUT' THEN sl.quantity ELSE 0 END), 0)
                    AS available_qty
                FROM products p
                LEFT JOIN stock_ledger sl ON sl.product_id = p.id AND sl.source = 'PROJECT' AND sl.project_id = ?
                GROUP BY p.id, p.name, p.unit, p.selling_price
                HAVING available_qty > 0
                ORDER BY p.name ASC
            ";
            $result = $db->query($query, [$projectId])->getResultArray();
        } else {
            $result = [];
        }

        return $this->response->setJSON($result);
    }

    /**
     * AJAX endpoint: live Project Financial Summary for the Billing Tracker.
     * exclude_sale_id (optional) omits the sale being edited from total_billed/total_paid
     * so it isn't double-counted against its own in-progress invoice total.
     */
    public function projectFinancialSummary($projectId)
    {
        $excludeSaleId = $this->request->getGet('exclude_sale_id');
        $excludeSaleId = ($excludeSaleId !== null && $excludeSaleId !== '') ? (int) $excludeSaleId : null;

        $summary = (new ProjectModel())->getFinancialSummary((int) $projectId, $excludeSaleId);

        // Release 4.8.6A-2: what the "Customer Advance Available" modal needs. advance_balance is the
        // advance still free for the invoice being saved/edited (its own current allocation counts
        // as free); the financial summary above is untouched.
        $saleModel = new SaleModel();
        $project   = (new ProjectModel())->find((int) $projectId);
        $customer  = ($project && ! empty($project['customer_id'])) ? (new CustomerModel())->find((int) $project['customer_id']) : null;
        $summary['advance_allocation_enabled'] = $saleModel->allocationsEnabled();
        $summary['advance_balance']            = $saleModel->advanceBalance((int) $projectId, $excludeSaleId);
        $summary['advance_current']            = 0.0;
        if ($excludeSaleId) {
            $own = \Config\Database::connect()->query("SELECT advance_applied, project_id FROM sales WHERE id = ?", [$excludeSaleId])->getRowArray();
            if ($own && (int) $own['project_id'] === (int) $projectId) {
                $summary['advance_current'] = (float) $own['advance_applied'];
            }
        }

        // Release 4.8.6C-1: every figure the "Customer Advance Available" modal shows comes from here,
        // not from JavaScript arithmetic. Display only — same rules the server enforces on save:
        //   received  = projects.advance_amount
        //   others    = advance applied on the OTHER invoices of this project (this invoice, when
        //               editing, is excluded, so its own allocation counts as still available)
        //   available = received - others (never negative)
        // invoice_total is the total being saved (the form's grand total, sent as ?invoice_total=;
        // an unsaved total cannot be known to the server any other way; an edit falls back to the
        // stored total); outstanding-before-apply = that total less payments already on this invoice.
        $dbm      = \Config\Database::connect();
        $received = round((float) ($project['advance_amount'] ?? 0), 2);
        $sqlO     = "SELECT COALESCE(SUM(advance_applied), 0) AS t FROM sales WHERE project_id = ?";
        $parO     = [(int) $projectId];
        if ($excludeSaleId) {
            $sqlO .= " AND id <> ?";
            $parO[] = $excludeSaleId;
        }
        $others = round((float) $dbm->query($sqlO, $parO)->getRow()->t, 2);

        $invoiceTotal = $this->request->getGet('invoice_total');
        if ($invoiceTotal !== null && $invoiceTotal !== '') {
            $invoiceTotal = round(max(0.0, (float) $invoiceTotal), 2);
        } elseif ($excludeSaleId) {
            $invoiceTotal = round((float) ($dbm->query("SELECT total_amount FROM sales WHERE id = ?", [$excludeSaleId])->getRow()->total_amount ?? 0), 2);
        } else {
            $invoiceTotal = 0.0;
        }
        $paidHere = $excludeSaleId ? round((float) $dbm->query("SELECT COALESCE(SUM(amount), 0) AS t FROM payments WHERE sale_id = ?", [$excludeSaleId])->getRow()->t, 2) : 0.0;

        $summary['advance_received']                = $received;
        $summary['allocated_to_other_invoices']     = $others;
        $summary['available_customer_advance']      = max(0.0, round($received - $others, 2));
        $summary['current_invoice_allocation']      = $summary['advance_current'];
        $summary['invoice_total']                   = $invoiceTotal;
        $summary['invoice_outstanding_before_apply'] = max(0.0, round($invoiceTotal - $paidHere, 2));

        $summary['modal_project_name']  = $project['name'] ?? '';
        $summary['modal_customer_id']   = $project['customer_id'] ?? null;
        $summary['modal_customer_name'] = $customer['name'] ?? '';

        return $this->response->setJSON($summary);
    }

    public function store()
    {
        $db = \Config\Database::connect();
        $db->transStart();

        $items       = $this->request->getPost('items');
        $stockSource = 'GENERAL';
        $projectId   = $this->request->getPost('project_id');
        $saleDate    = $this->request->getPost('sale_date');

        // Server-side: reject if project is COMPLETED
        if ($projectId) {
            $projectModel = new ProjectModel();
            $project      = $projectModel->find($projectId);
            if ($project && $project['status'] === 'COMPLETED') {
                return redirect()->back()->with('error', 'Cannot create a sale for a completed project.');
            }

            // Release 2.0C: block a new invoice once the project's contract value
            // has already been fully billed by existing invoices. Reuses the
            // existing financial summary — no new calculation.
            // Release 4.9.0EC: the advance consumes the contract too, so this
            // reads the one authoritative Remaining Balance (Contract - Advance
            // Received - Total Invoiced) instead of comparing billed alone.
            if ($project) {
                $summary       = $projectModel->getFinancialSummary((int) $projectId);
                $contractValue = (float) ($summary['total_project_value'] ?? 0);
                if ($contractValue > 0 && (float) ($summary['remaining_billable_value'] ?? 0) <= 0.005) {
                    return redirect()->back()->with('error', "This project's contract value has already been fully billed. Create a new project for additional work.");
                }
            }
        }

        $totalAmount = 0;
        foreach ($items as $item) {
            $product       = (new ProductModel())->find($item['product_id']);
            $gstPercent    = $product['gst_percent'] ?? 0;
            $gstApplicable = !isset($item['gst_applicable']) || (int)$item['gst_applicable'] === 1;
            $calc          = gst_calculate_line($item['quantity'], $item['unit_price'], $gstPercent, $gstApplicable);
            $totalAmount  += $calc['total_with_gst'];
        }

        $sources = [];
        foreach ($items as $item) {
            if (!empty($item['stock_source'])) $sources[] = $item['stock_source'];
        }
        $sources = array_unique($sources);
        $stockSource = count($sources) === 1 ? $sources[0] : 'MIXED';

        foreach ($items as $item) {
            if (empty($item['product_id']) || empty($item['quantity'])) continue;
            $available = getStock(
                $item['product_id'],
                $item['stock_source'] === 'PROJECT' ? $projectId : null
            );
            if ($item['quantity'] > $available) {
                return redirect()->back()->with('error', 'Stock not enough for selected product');
            }
        }

        // Release 4.8.6A-2: the advance the accountant chose to apply in the "Customer Advance
        // Available" modal (0 = Don't Apply). Never applied automatically. Checked inside this
        // transaction with the project row locked, so two invoices saved together cannot spend the
        // same advance twice.
        // Release 4.8.6B Hotfix: advance_action is the modal's explicit answer — 'apply' (Apply & Save)
        // or 'none' (Save Invoice Only). 'none' always means 0 whatever amount was posted.
        $saleModel     = new SaleModel();
        $advanceAction = (string) $this->request->getPost('advance_action');
        $advanceApply  = $advanceAction === 'none' ? 0.0 : round((float) $this->request->getPost('advance_to_apply'), 2);
        $customerId    = $this->request->getPost('customer_id') ?: null;
        if ($advanceApply < 0) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', 'Advance to apply cannot be negative.');
        }
        if ($advanceAction === 'apply' && $advanceApply <= 0.004) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', 'Advance to apply must be greater than 0.');
        }
        // The invoice is not saved until the accountant has answered the modal.
        if (! in_array($advanceAction, ['apply', 'none'], true) && $this->_advanceChoiceRequired($saleModel, $projectId ? (int) $projectId : 0, $customerId, null)) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', 'This project has unused customer advance. Choose whether to apply it before saving the invoice.');
        }
        // Release 4.8.6B-1: no allocation table = no way to apply advance (never stored on the invoice).
        if ($advanceApply > 0 && ! $saleModel->allocationsEnabled()) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', self::ALLOCATION_UNAVAILABLE);
        }
        if ($advanceApply > 0) {
            $err = $this->_checkAdvanceApply($db, $saleModel, $projectId ? (int) $projectId : 0, $customerId, $advanceApply, (float) $totalAmount, 0.0, null);
            if ($err) {
                $db->transRollback();
                return redirect()->back()->withInput()->with('error', $err);
            }
        }

        $saleId    = $saleModel->insert([
            'project_id'     => $projectId,
            'customer_id'    => $this->request->getPost('customer_id') ?: null,
            'sale_date'      => $saleDate,
            'invoice_no'     => $this->request->getPost('invoice_no'),
            'stock_source'   => $stockSource,
            'total_amount'   => $totalAmount,
            'paid_amount'    => 0,
            'balance_amount' => $totalAmount,
            'status'         => 'UNPAID',
            'notes'          => $this->request->getPost('notes'),
        ]);

        if (!$saleId) dd($saleModel->errors());

        foreach ($items as $item) {
            if (empty($item['product_id']) || empty($item['quantity'])) continue;
            $product       = (new ProductModel())->find($item['product_id']);
            $gstPercent    = $product['gst_percent'] ?? 0;
            $gstApplicable = !isset($item['gst_applicable']) || (int)$item['gst_applicable'] === 1;
            $calc          = gst_calculate_line($item['quantity'], $item['unit_price'], $gstPercent, $gstApplicable);

            $db->table('sale_items')->insert([
                'sale_id'        => $saleId,
                'product_id'     => $item['product_id'],
                'quantity'       => $item['quantity'],
                'unit_price'     => $item['unit_price'],
                'gst_percent'    => $calc['gst_percent'],
                'gst_applicable' => $calc['gst_applicable'],
                'gst_amount'     => $calc['gst_amount'],
                'total'          => $calc['total'],
                'total_with_gst' => $calc['total_with_gst'],
                'stock_source'   => $item['stock_source'],
                'project_id'     => $item['stock_source'] === 'PROJECT' ? $projectId : null,
            ]);

            $db->table('stock_ledger')->insert([
                'product_id'       => $item['product_id'],
                'transaction_type' => 'OUT',
                'quantity'         => $item['quantity'],
                'source'           => $item['stock_source'],
                'project_id'       => ($item['stock_source'] === 'PROJECT') ? $projectId : null,
                'reference_type'   => 'SALE',
                'reference_id'     => $saleId,
                'transaction_date' => $saleDate,
                'notes'            => 'Sale #' . $saleId,
            ]);
        }

        // Release 4.8.6A-2: record the chosen allocation (a matching record only — no bank
        // transaction, no payment voucher, no receipt), then refresh the invoice's paid/balance/
        // status. Any failure here rolls the invoice back too.
        if ($advanceApply > 0) {
            $projectRow = (new ProjectModel())->find((int) $projectId);
            $ok = $saleModel->setInvoiceAllocation((int) $saleId, (int) $projectId, (int) ($customerId ?: ($projectRow['customer_id'] ?? 0)) ?: null, (string) $saleDate, $advanceApply, session()->get('user_id') ? (int) session()->get('user_id') : null);
            if (! $ok) {
                $db->transRollback();
                return redirect()->back()->withInput()->with('error', 'Failed to record the advance allocation; the invoice was not saved.');
            }
        }
        $saleModel->recalculateProjectBilling((int) $projectId);
        if ($advanceApply > 0) {
            $applied = (float) $db->query("SELECT advance_applied FROM sales WHERE id = ?", [(int) $saleId])->getRow()->advance_applied;
            if (abs($applied - $advanceApply) > 0.004) {
                $db->transRollback();
                return redirect()->back()->withInput()->with('error', 'The advance allocation could not be applied in full; the invoice was not saved.');
            }
        }

        $db->transComplete();
        if ($db->transStatus() === false) { $error = $db->error(); dd($error); }

        return redirect()->to('/sales')->with('success', 'Sale saved and stock deducted successfully.');
    }
	
	// =========================================================
    // EDIT
    // =========================================================

    public function edit($id)
    {
        $db = \Config\Database::connect();

        $sale = $db->query("
            SELECT s.*, p.name AS project_name, c.name AS customer_name
            FROM sales s
            LEFT JOIN projects p ON s.project_id = p.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.id = ?
        ", [$id])->getRowArray();

        if (!$sale) {
            return redirect()->to('/sales')->with('error', 'Sale not found.');
        }

        $items = $db->query("
            SELECT si.*, pr.name AS product_name, pr.unit, pr.selling_price,pr.gst_percent
            FROM sale_items si
            LEFT JOIN products pr ON si.product_id = pr.id
            WHERE si.sale_id = ?
            ORDER BY si.id ASC
        ", [$id])->getResultArray();

        $data['sale']      = $sale;
        $data['items']     = $items;
        $data['projects']  = (new ProjectModel())->orderBy('name','ASC')->findAll();
        $data['customers'] = (new CustomerModel())->orderBy('name','ASC')->findAll();

        // Release 4.8.6A-2 Patch: an invoice carrying an old automatic (FIFO) advance is shown with a
        // "Legacy Advance Adjustment" badge. Opening this page never converts it.
        $data['legacy_advance'] = (new SaleModel())->isLegacyAdvance((int) $id);

        return view('sales/edit', $data);
    }

    // =========================================================
    // UPDATE
    // =========================================================

    public function update($id)
    {
        $db = \Config\Database::connect();

        $sale = $db->table('sales')->where('id', $id)->get()->getRowArray();
        if (!$sale) {
            return redirect()->to('/sales')->with('error', 'Sale not found.');
        }
        $oldProjectId = (int) $sale['project_id'];

        // Release 4.8.6A-2 Patch: the accountant's explicit "Convert to New Allocation" click for a
        // legacy invoice. This is the ONLY place a legacy invoice gets an allocation row; opening or
        // saving the edit page never does. Amount, balance and status of the invoice do not change.
        if ($this->request->getPost('convert_legacy_advance')) {
            $db->transStart();
            $converted = (new SaleModel())->convertLegacyAdvance((int) $id, session()->get('user_id') ? (int) session()->get('user_id') : null);
            $db->transComplete();
            if (! $converted || $db->transStatus() === false) {
                return redirect()->to('/sales/view/' . (int) $id)->with('error', 'This invoice has no legacy advance adjustment to convert.');
            }
            return redirect()->to('/sales/view/' . (int) $id)->with('success', 'Legacy advance converted to a new allocation.');
        }

        $items     = $this->request->getPost('items');
        $projectId = $this->request->getPost('project_id');
        $saleDate  = $this->request->getPost('sale_date') ?: date('Y-m-d');

        if (empty($items)) {
            return redirect()->back()->with('error', 'At least one item is required.');
        }
        if (empty($projectId)) {
            return redirect()->back()->with('error', 'Project is required.');
        }

        // Filter valid items
        $validItems = [];
        foreach ($items as $item) {
            if (!empty($item['product_id']) && !empty($item['quantity']) && isset($item['unit_price'])
                && !empty($item['stock_source'])) {
                $validItems[] = $item;
            }
        }
        if (empty($validItems)) {
            return redirect()->back()->with('error', 'No valid items submitted.');
        }

        $db->transStart();

        // STEP 1 — Remove old stock OUT entries (restores stock for re-validation)
        $db->table('stock_ledger')
           ->where('reference_type', 'SALE')
           ->where('reference_id', (int)$id)
           ->delete();

        // STEP 2 — Validate stock against restored levels (before inserting new OUT)
        foreach ($validItems as $item) {
            $source      = $item['stock_source'];
            $checkProject = ($source === 'PROJECT') ? (int)$projectId : null;

            // Query available stock (old OUT already removed by step 1, so we see restored qty)
            $available = $this->_getAvailableStock(
                $db,
                (int)$item['product_id'],
                $source,
                $checkProject
            );

            if ((float)$item['quantity'] > $available) {
                $db->transRollback();
                $product = (new ProductModel())->find($item['product_id']);
                $name    = $product ? $product['name'] : 'Product #' . $item['product_id'];
                return redirect()->back()
                    ->with('error', 'Insufficient stock for "' . $name . '". Available: ' . number_format($available, 3));
            }
        }

        // Recalculate totals
        $newTotal = 0;
        foreach ($validItems as $item) {
            $product       = (new ProductModel())->find($item['product_id']);
            $gstPercent    = $product['gst_percent'] ?? 0;
            $gstApplicable = !isset($item['gst_applicable']) || (int)$item['gst_applicable'] === 1;
            $calc          = gst_calculate_line($item['quantity'], $item['unit_price'], $gstPercent, $gstApplicable);
            $newTotal     += $calc['total_with_gst'];
        }

        // Release 4.8.6A-2: the advance chosen in the modal, when the form sent one. Absent = leave
        // this invoice's allocation as it is; 0 = remove it. Validated against the advance still free
        // for this invoice (its own current allocation counts as free) and against what is unpaid.
        // Release 4.8.6B Hotfix: advance_action = 'apply' | 'none' is the modal's explicit answer.
        $saleModel     = new SaleModel();
        $advanceAction = (string) $this->request->getPost('advance_action');
        $advancePost   = $this->request->getPost('advance_to_apply');
        $advanceSent   = ($advancePost !== null && $advancePost !== '') || in_array($advanceAction, ['apply', 'none'], true);
        $advanceApply  = $advanceSent ? ($advanceAction === 'none' ? 0.0 : round((float) $advancePost, 2)) : null;
        $customerId    = $this->request->getPost('customer_id') ?: null;
        if ($advanceAction === 'apply' && $advanceApply <= 0.004) {
            $db->transRollback();
            return redirect()->back()->with('error', 'Advance to apply must be greater than 0.');
        }
        // An old automatic (legacy) advance on the same project is left alone and never asks; anything
        // else with unused customer advance must go through the modal before the invoice is saved.
        $legacySame = $oldProjectId === (int) $projectId && $saleModel->isLegacyAdvance((int) $id);
        if (! $advanceSent && ! $legacySame && $this->_advanceChoiceRequired($saleModel, (int) $projectId, $customerId, (int) $id)) {
            $db->transRollback();
            return redirect()->back()->with('error', 'This project has unused customer advance. Choose whether to apply it before saving the invoice.');
        }
        if ($advanceSent) {
            if ($oldProjectId === (int) $projectId && $saleModel->isLegacyAdvance((int) $id)) {
                $db->transRollback();
                return redirect()->back()->with('error', 'This invoice carries a legacy advance adjustment. Use "Convert to New Allocation" first; editing does not change it.');
            }
            if ($advanceApply < 0) {
                $db->transRollback();
                return redirect()->back()->with('error', 'Advance to apply cannot be negative.');
            }
            // Release 4.8.6B-1: no allocation table = no way to apply advance (never stored on the invoice).
            if ($advanceApply > 0 && ! $saleModel->allocationsEnabled()) {
                $db->transRollback();
                return redirect()->back()->with('error', self::ALLOCATION_UNAVAILABLE);
            }
            if ($advanceApply > 0) {
                $paidNow =(float) $db->query("SELECT COALESCE(SUM(amount), 0) AS t FROM payments WHERE sale_id = ?", [(int) $id])->getRow()->t;
                $err = $this->_checkAdvanceApply($db, $saleModel, (int) $projectId, $customerId, $advanceApply, (float) $newTotal, $paidNow, (int) $id);
                if ($err) {
                    $db->transRollback();
                    return redirect()->back()->with('error', $err);
                }
            }
        }

        // Determine stock_source for the sale header
        $sources     = array_unique(array_column($validItems, 'stock_source'));
        $stockSource = count($sources) === 1 ? $sources[0] : 'MIXED';

        // STEP 3 — Update sale header. balance_amount/status are no longer
        // set here — Release 1.6.4 (Rules 6-7) derives them, along with
        // advance_applied and paid_amount, via recalculateProjectBilling()
        // below, since total_amount and/or project_id may have changed.
        $db->table('sales')->where('id', (int)$id)->update([
            'project_id'     => (int)$projectId,
            'customer_id'    => $this->request->getPost('customer_id') ?: null,
            'sale_date'      => $saleDate,
            'invoice_no'     => $this->request->getPost('invoice_no'),
            'stock_source'   => $stockSource,
            'total_amount'   => $newTotal,
            'notes'          => $this->request->getPost('notes'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        // STEP 4 — Delete old sale items
        $db->table('sale_items')->where('sale_id', (int)$id)->delete();

        // STEPS 5 & 6 — Insert new items + new stock OUT entries
        $this->_insertSaleItemsAndStock($db, (int)$id, (int)$projectId, $saleDate, $validItems);

        // Release 1.6.4 (Rules 1-3): total_amount and/or project_id may have
        // changed — re-run FIFO advance allocation for the new project, and
        // for the old project too if the invoice moved to a different one.
        // Release 4.8.6A-2: advance applied to this invoice came out of its OLD project's advance,
        // so moving the invoice to another project releases that allocation first.
        $allocationsOn = $saleModel->allocationsEnabled();
        if ($oldProjectId !== (int) $projectId) {
            $saleModel->setInvoiceAllocation((int) $id, $oldProjectId, null, (string) $saleDate, 0.0, null);
            if (! $allocationsOn) {
                // Release 4.8.6B-1: no allocation table — release an old automatic amount so the old
                // project's advance is not carried into the new project.
                $db->table('sales')->where('id', (int) $id)->update(['advance_applied' => 0]);
            }
        }
        // Release 4.8.6B-1: without the allocation table the modal only offers Save Invoice Only, which
        // touches no advance at all (an old automatic amount stays as it is).
        $touchAllocation = $advanceSent && $allocationsOn;
        if ($touchAllocation) {
            $projectRow = (new ProjectModel())->find((int) $projectId);
            $ok = $saleModel->setInvoiceAllocation((int) $id, (int) $projectId, (int) ($customerId ?: ($projectRow['customer_id'] ?? 0)) ?: null, (string) $saleDate, (float) $advanceApply, session()->get('user_id') ? (int) session()->get('user_id') : null);
            if (! $ok) {
                $db->transRollback();
                return redirect()->back()->with('error', 'Failed to update the advance allocation; the invoice was not changed.');
            }
        }
        $saleModel->recalculateProjectBilling((int) $projectId);
        if ($oldProjectId !== (int) $projectId) {
            $saleModel->recalculateProjectBilling($oldProjectId);
        }
        if ($touchAllocation) {
            $applied = (float) $db->query("SELECT advance_applied FROM sales WHERE id = ?", [(int) $id])->getRow()->advance_applied;
            if (abs($applied - (float) $advanceApply) > 0.004) {
                $db->transRollback();
                return redirect()->back()->with('error', 'The advance allocation could not be applied in full; the invoice was not changed.');
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            $error = $db->error();
            return redirect()->back()->with('error', 'Update failed: ' . $error['message']);
        }

        return redirect()->to('/sales')->with('success', 'Sale updated and stock re-calculated successfully.');
    }

    // =========================================================
    // PRIVATE HELPERS
    // =========================================================

    /**
     * Release 4.8.6B Hotfix: true when the "Customer Advance Available" modal must be answered before
     * this invoice can be saved — the project has a customer and unused advance (for an edit, its own
     * current allocation counts as free). Same test the modal's JavaScript uses to decide to open.
     */
    private function _advanceChoiceRequired(SaleModel $saleModel, int $projectId, $customerId, ?int $excludeSaleId): bool
    {
        if (! $projectId) {
            return false;
        }
        $project = (new ProjectModel())->find($projectId);
        if (! $project || (! $customerId && empty($project['customer_id']))) {
            return false;
        }
        return $saleModel->advanceBalance($projectId, $excludeSaleId) > 0.004;
    }

    /**
     * Release 4.8.6A-2: validate an advance allocation. Returns an error message, or null when it is
     * fine. Locks the project row so concurrent invoices cannot spend the same advance twice.
     */
    private function _checkAdvanceApply(\CodeIgniter\Database\BaseConnection $db, SaleModel $saleModel, int $projectId, $customerId, float $apply, float $invoiceTotal, float $alreadyPaid, ?int $excludeSaleId): ?string
    {
        if (! $projectId) {
            return 'Advance cannot be applied to this invoice.';
        }
        $db->query("SELECT id FROM projects WHERE id = ? FOR UPDATE", [$projectId]);
        $project = (new ProjectModel())->find($projectId);
        if (! $customerId && empty($project['customer_id'])) {
            return 'Advance cannot be applied: this project has no customer.';
        }
        $totalCents = (int) round($invoiceTotal * 100);
        if ($apply * 100 > $totalCents + 0.5) {
            return 'Advance to apply (' . number_format($apply, 2) . ') cannot exceed the invoice amount (' . number_format($totalCents / 100, 2) . ').';
        }
        if (($apply + $alreadyPaid) * 100 > $totalCents + 0.5) {
            return 'Advance to apply (' . number_format($apply, 2) . ') plus the ' . number_format($alreadyPaid, 2) . ' already paid would exceed the invoice amount.';
        }
        $available = $saleModel->advanceBalance($projectId, $excludeSaleId);
        if ($apply > $available + 0.004) {
            return 'Advance to apply (' . number_format($apply, 2) . ') exceeds the available project advance (' . number_format($available, 2) . ').';
        }
        return null;
    }

    /**
     * Query available stock within the current transaction context.
     * Used for validation inside update() after old entries are removed.
     */
    private function _getAvailableStock(\CodeIgniter\Database\BaseConnection $db, int $productId, string $source, ?int $projectId): float
    {
        if ($source === 'GENERAL') {
            $row = $db->query("
                SELECT COALESCE(
                    SUM(CASE WHEN transaction_type='IN'  THEN quantity ELSE 0 END) -
                    SUM(CASE WHEN transaction_type='OUT' THEN quantity ELSE 0 END)
                , 0) AS qty
                FROM stock_ledger
                WHERE product_id = ? AND source = 'GENERAL'
            ", [$productId])->getRow();
        } else {
            $row = $db->query("
                SELECT COALESCE(
                    SUM(CASE WHEN transaction_type='IN'  THEN quantity ELSE 0 END) -
                    SUM(CASE WHEN transaction_type='OUT' THEN quantity ELSE 0 END)
                , 0) AS qty
                FROM stock_ledger
                WHERE product_id = ? AND source = 'PROJECT' AND project_id = ?
            ", [$productId, $projectId])->getRow();
        }
        return (float)($row->qty ?? 0);
    }

    /**
     * Insert sale_items and stock_ledger OUT entries.
     * Shared by store() pattern and update().
     */
    private function _insertSaleItemsAndStock(\CodeIgniter\Database\BaseConnection $db, int $saleId, int $projectId, string $saleDate, array $items): void
    {
        foreach ($items as $item) {
            $product       = (new ProductModel())->find($item['product_id']);
            $gstPercent    = $product['gst_percent'] ?? 0;
            $gstApplicable = !isset($item['gst_applicable']) || (int)$item['gst_applicable'] === 1;
            $calc          = gst_calculate_line($item['quantity'], $item['unit_price'], $gstPercent, $gstApplicable);
            $source        = $item['stock_source'];

            $db->table('sale_items')->insert([
                'sale_id'        => $saleId,
                'product_id'     => (int)$item['product_id'],
                'quantity'       => (float)$item['quantity'],
                'unit_price'     => (float)$item['unit_price'],
                'gst_percent'    => $calc['gst_percent'],
                'gst_applicable' => $calc['gst_applicable'],
                'gst_amount'     => $calc['gst_amount'],
                'total'          => $calc['total'],
                'total_with_gst' => $calc['total_with_gst'],
                'stock_source'   => $source,
                'project_id'     => ($source === 'PROJECT') ? $projectId : null,
            ]);

            $db->table('stock_ledger')->insert([
                'product_id'       => (int)$item['product_id'],
                'transaction_type' => 'OUT',
                'quantity'         => (float)$item['quantity'],
                'source'           => $source,
                'project_id'       => ($source === 'PROJECT') ? $projectId : null,
                'reference_type'   => 'SALE',
                'reference_id'     => $saleId,
                'transaction_date' => $saleDate,
                'notes'            => 'Sale #' . $saleId,
            ]);
        }
    }

    public function view($id)
    {
        $db = \Config\Database::connect();
        $data['sale'] = $db->query("
            SELECT s.*, p.name AS project_name, c.name AS customer_name
            FROM sales s
            LEFT JOIN projects p ON s.project_id = p.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.id = ?
        ", [$id])->getRowArray();

        if (!$data['sale']) return redirect()->to('/sales')->with('error', 'Sale not found.');

        $data['items'] = $db->query("
            SELECT si.*, pr.name AS product_name, pr.unit
            FROM sale_items si
            LEFT JOIN products pr ON si.product_id = pr.id
            WHERE si.sale_id = ?
        ", [$id])->getResultArray();

        $data['payments'] = $db->query("
            SELECT * FROM payments WHERE sale_id = ? ORDER BY payment_date DESC
        ", [$id])->getResultArray();

        $data['gst_summary'] = gst_summarize_items($data['items']);

        // Release 4.8.6A-2: advance applied to this invoice — the allocation history and the advance
        // that was available to it (what other invoices have not used).
        $saleModel = new SaleModel();
        $data['allocations']       = [];
        $data['advance_available'] = $saleModel->advanceBalance((int) $data['sale']['project_id'], (int) $id);
        if ($saleModel->allocationsEnabled()) {
            $data['allocations'] = $db->query("
                SELECT a.*, u.username AS created_by_name
                FROM project_advance_allocations a
                LEFT JOIN users u ON u.id = a.created_by
                WHERE a.sales_invoice_id = ? ORDER BY a.id ASC
            ", [$id])->getResultArray();
        }

        // Release 4.8.6A-2 Patch: Allocation Status for the Advance Applied card, the legacy flag, and
        // whether the card shows at all (the project has an advance, or this invoice carries one).
        $appliedNow  = (float) ($data['sale']['advance_applied'] ?? 0);
        $invoiceTotal = (float) ($data['sale']['total_amount'] ?? 0);
        $data['allocation_status'] = $appliedNow <= 0.004 ? 'Not Applied'
            : ($appliedNow >= $invoiceTotal - 0.004 ? 'Fully Applied' : 'Partially Applied');
        $data['legacy_advance']    = $saleModel->isLegacyAdvance((int) $id);
        $projectAdv = $data['sale']['project_id']
            ? (float) ($db->query("SELECT advance_amount FROM projects WHERE id = ?", [(int) $data['sale']['project_id']])->getRow()->advance_amount ?? 0)
            : 0.0;
        $data['show_advance_card'] = $saleModel->allocationsEnabled() && ($projectAdv > 0.004 || $appliedNow > 0.004);

        return view('sales/view', $data);
    }

    public function delete($id)
    {
        $db = \Config\Database::connect();
        $db->transStart();
        $sale = $db->table('sales')->where('id', $id)->get()->getRowArray();
        $db->table('stock_ledger')->where('reference_type','SALE')->where('reference_id',$id)->delete();
        $db->table('sale_items')->where('sale_id',$id)->delete();
        $db->table('payments')->where('sale_id',$id)->delete();
        // Release 4.8.6A-2: the invoice's allocation rows go with it, which frees the advance again.
        // Matching records only — no bank change.
        if ((new SaleModel())->allocationsEnabled()) {
            $db->table(SaleModel::ALLOCATION_TABLE)->where('sales_invoice_id', (int) $id)->delete();
        }
        $db->table('sales')->where('id',$id)->delete();

        // Release 1.6.4 (Rules 1-3): deleting an invoice frees up any advance
        // it had absorbed — re-run FIFO for the rest of the project's invoices.
        if ($sale) {
            (new SaleModel())->recalculateProjectBilling((int) $sale['project_id']);
        }

        $db->transComplete();
        return redirect()->to('/sales')->with('success', 'Sale deleted and stock reversed.');
    }
}
