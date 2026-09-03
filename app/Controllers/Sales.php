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
            // existing financial summary (total_project_value/total_billed) —
            // no new calculation.
            if ($project) {
                $summary       = $projectModel->getFinancialSummary((int) $projectId);
                $contractValue = (float) ($summary['total_project_value'] ?? 0);
                $totalBilled   = (float) ($summary['total_billed'] ?? 0);
                if ($contractValue > 0 && $totalBilled >= $contractValue - 0.005) {
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

        $saleModel = new SaleModel();
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

        // Release 1.6.4 (Rules 1-3): new invoice changes the project's FIFO
        // advance allocation across all of its invoices.
        (new SaleModel())->recalculateProjectBilling((int) $projectId);

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
        $saleModel = new SaleModel();
        $saleModel->recalculateProjectBilling((int) $projectId);
        if ($oldProjectId !== (int) $projectId) {
            $saleModel->recalculateProjectBilling($oldProjectId);
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
