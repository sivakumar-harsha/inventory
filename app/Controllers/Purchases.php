<?php

namespace App\Controllers;

use App\Models\PurchaseModel;
use App\Models\ProductModel;
use App\Models\SupplierModel;
use App\Models\ProjectModel;
use App\Models\StockLedgerModel;
use CodeIgniter\Controller;

class Purchases extends Controller
{
    public function index()
    {
        $db = \Config\Database::connect();
		
		$data['suppliers'] = (new SupplierModel())->orderBy('name','ASC')->findAll();
		
        $data['purchases'] = $db->query("
            SELECT pu.*, s.name AS supplier_name, p.name AS project_name
            FROM purchases pu
            LEFT JOIN suppliers s ON pu.supplier_id = s.id
            LEFT JOIN projects p ON pu.project_id = p.id
            ORDER BY pu.created_at DESC
        ")->getResultArray();
        return view('purchases/index', $data);
    }

    public function create()
    {
        $data['suppliers'] = (new SupplierModel())->orderBy('name','ASC')->findAll();
        $data['projects']  = (new ProjectModel())->where('status','ACTIVE')->orderBy('name','ASC')->findAll();
        $data['products']  = (new ProductModel())->orderBy('name','ASC')->findAll();
        return view('purchases/create', $data);
    }

    public function store()
    {
        $db = \Config\Database::connect();
        $db->transStart();

        $items     = $this->request->getPost('items');
        $projectId = $this->request->getPost('project_id');
        $purchDate = $this->request->getPost('purchase_date') ?: date('Y-m-d');

        if (empty($items)) {
            echo "ITEM EMPTY"; exit;
        }
        if (empty($projectId)) {
            echo "PROJECT EMPTY"; exit;
        }

        // Server-side: reject if project is COMPLETED
        $project = (new ProjectModel())->find($projectId);
        if ($project && $project['status'] === 'COMPLETED') {
            return redirect()->back()->with('error', 'Cannot create a purchase for a completed project.');
        }

        if ($allocationError = $this->_validateAllocation($items)) {
            return redirect()->back()->withInput()->with('error', $allocationError);
        }

        $totalAmount = 0;
        foreach ($items as $item) {
            $product       = (new ProductModel())->find($item['product_id']);
            $gstPercent    = $product['gst_percent'] ?? 0;
            $gstApplicable = !isset($item['gst_applicable']) || (int)$item['gst_applicable'] === 1;
            $calc          = gst_calculate_line($item['quantity'], $item['unit_price'], $gstPercent, $gstApplicable);
            $totalAmount  += $calc['total_with_gst'];
        }

        $db->table('purchases')->insert([
            'supplier_id'   => $this->request->getPost('supplier_id'),
            'project_id'    => $projectId,
            'purchase_date' => $purchDate,
            'invoice_no'    => $this->request->getPost('invoice_no'),
            'total_amount'  => $totalAmount,
            'notes'         => $this->request->getPost('notes'),
        ]);

        $purchaseId = $db->insertID();

        $this->_insertItemsAndStock($db, (int)$purchaseId, (int)$projectId, $purchDate, $items);

        $db->transComplete();

        if ($db->transStatus() === false) {
            $error = $db->error();
            return redirect()->back()->with('error', $error['message']);
        }

        return redirect()->to('/purchases')->with('success', 'Purchase saved and stock updated successfully.');
    }
	
	// =========================================================
    // EDIT
    // =========================================================

    public function edit($id)
    {
        $db = \Config\Database::connect();

        $purchase = $db->query("
            SELECT pu.*, s.name AS supplier_name, p.name AS project_name
            FROM purchases pu
            LEFT JOIN suppliers s ON pu.supplier_id = s.id
            LEFT JOIN projects p  ON pu.project_id  = p.id
            WHERE pu.id = ?
        ", [$id])->getRowArray();

        if (!$purchase) {
            return redirect()->to('/purchases')->with('error', 'Purchase not found.');
        }

        $items = $db->query("
            SELECT pi.*, pr.name AS product_name, pr.unit, pr.hsn_code AS product_hsn
            FROM purchase_items pi
            LEFT JOIN products pr ON pi.product_id = pr.id
            WHERE pi.purchase_id = ?
            ORDER BY pi.id ASC
        ", [$id])->getResultArray();

        $data['purchase']  = $purchase;
        $data['items']     = $items;
        $data['suppliers'] = (new SupplierModel())->orderBy('name','ASC')->findAll();
        $data['projects']  = (new ProjectModel())->orderBy('name','ASC')->findAll();
        $data['products']  = (new ProductModel())->orderBy('name','ASC')->findAll();

        return view('purchases/edit', $data);
    }

    // =========================================================
    // UPDATE
    // =========================================================

    public function update($id)
    {
        $db = \Config\Database::connect();

        $purchase = $db->table('purchases')->where('id', $id)->get()->getRowArray();
        if (!$purchase) {
            return redirect()->to('/purchases')->with('error', 'Purchase not found.');
        }

        $items = $this->request->getPost('items');
        if (empty($items)) {
            return redirect()->back()->with('error', 'At least one item is required.');
        }

        $projectId = $this->request->getPost('project_id');
        if (empty($projectId)) {
            return redirect()->back()->with('error', 'Project is required.');
        }

        $purchDate = $this->request->getPost('purchase_date') ?: date('Y-m-d');

        if ($consumptionError = $this->_checkAllocationConsumed($db, (int) $id)) {
            return redirect()->back()->with('error', $consumptionError);
        }

        if ($allocationError = $this->_validateAllocation($items)) {
            return redirect()->back()->withInput()->with('error', $allocationError);
        }

        // --- Recalculate total ---
        $totalAmount = 0;
        foreach ($items as $item) {
            if (empty($item['product_id']) || empty($item['quantity']) || !isset($item['unit_price'])) continue;
            $product       = (new ProductModel())->find($item['product_id']);
            $gstPercent    = $product['gst_percent'] ?? 0;
            $gstApplicable = !isset($item['gst_applicable']) || (int)$item['gst_applicable'] === 1;
            $calc          = gst_calculate_line($item['quantity'], $item['unit_price'], $gstPercent, $gstApplicable);
            $totalAmount  += $calc['total_with_gst'];
        }

        $db->transStart();

        // STEP 1 — Remove old stock entries
        $db->table('stock_ledger')
           ->where('reference_type', 'PURCHASE')
           ->where('reference_id', (int)$id)
           ->delete();

        // STEP 2 — Update purchase header
        $db->table('purchases')->where('id', $id)->update([
            'supplier_id'   => $this->request->getPost('supplier_id'),
            'project_id'    => (int)$projectId,
            'purchase_date' => $purchDate,
            'invoice_no'    => $this->request->getPost('invoice_no'),
            'total_amount'  => $totalAmount,
            'notes'         => $this->request->getPost('notes'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        // STEP 3 — Delete old items
        $db->table('purchase_items')->where('purchase_id', (int)$id)->delete();

        // STEPS 4 & 5 — Re-insert items and stock
        $this->_insertItemsAndStock($db, (int)$id, (int)$projectId, $purchDate, $items);

        $db->transComplete();

        if ($db->transStatus() === false) {
            $error = $db->error();
            return redirect()->back()->with('error', 'Update failed: ' . $error['message']);
        }

        return redirect()->to('/purchases')->with('success', 'Purchase updated and stock re-calculated successfully.');
    }

    // =========================================================
    // SHARED: insert items + stock ledger entries
    // =========================================================

    private function _insertItemsAndStock(\CodeIgniter\Database\BaseConnection $db, int $purchaseId, int $projectId, string $purchDate, array $items): void
    {
        foreach ($items as $item) {
            if (empty($item['product_id']) || empty($item['quantity']) || !isset($item['unit_price'])) continue;

            $product       = (new ProductModel())->find($item['product_id']);
            $gstPercent    = $product['gst_percent'] ?? 0;
            $hsnCode       = $product['hsn_code']    ?? null;
            $gstApplicable = !isset($item['gst_applicable']) || (int)$item['gst_applicable'] === 1;
            $calc          = gst_calculate_line($item['quantity'], $item['unit_price'], $gstPercent, $gstApplicable);

            $quantity   = (int)$item['quantity'];
            $projectQty = isset($item['project_qty']) ? (int)$item['project_qty'] : $quantity;
            $projectQty = max(0, min($projectQty, $quantity));
            $generalQty = $quantity - $projectQty;

            $db->table('purchase_items')->insert([
                'purchase_id'    => $purchaseId,
                'product_id'     => (int)$item['product_id'],
                'quantity'       => $quantity,
                'project_qty'    => $projectQty,
                'general_qty'    => $generalQty,
                'unit_price'     => (float)$item['unit_price'],
                'hsn_code'       => $hsnCode,
                'gst_percent'    => $calc['gst_percent'],
                'gst_applicable' => $calc['gst_applicable'],
                'gst_amount'     => $calc['gst_amount'],
                'total'          => $calc['total'],
                'total_with_gst' => $calc['total_with_gst'],
            ]);

            if ($projectQty > 0) {
                $db->table('stock_ledger')->insert([
                    'product_id'       => (int)$item['product_id'],
                    'transaction_type' => 'IN',
                    'quantity'         => (float)$projectQty,
                    'source'           => 'PROJECT',
                    'project_id'       => $projectId,
                    'reference_type'   => 'PURCHASE',
                    'reference_id'     => $purchaseId,
                    'transaction_date' => $purchDate,
                    'notes'            => 'Purchase #' . $purchaseId,
                ]);
            }

            if ($generalQty > 0) {
                $db->table('stock_ledger')->insert([
                    'product_id'       => (int)$item['product_id'],
                    'transaction_type' => 'IN',
                    'quantity'         => (float)$generalQty,
                    'source'           => 'GENERAL',
                    'project_id'       => null,
                    'reference_type'   => 'PURCHASE',
                    'reference_id'     => $purchaseId,
                    'transaction_date' => $purchDate,
                    'notes'            => 'Purchase #' . $purchaseId,
                ]);
            }
        }
    }

    /**
     * Validate project_qty against purchased quantity for every line, before
     * anything is written. Approved design (Decision 2): Project Qty must be
     * 0..Purchased Qty; General Qty is always the derived remainder, never
     * trusted from the client.
     */
    private function _validateAllocation(array $items): ?string
    {
        foreach ($items as $item) {
            if (empty($item['product_id']) || !isset($item['quantity'])) continue;

            $quantity = (int) $item['quantity'];
            if ($quantity <= 0) {
                return 'Purchased Qty must be greater than zero for every item.';
            }

            $projectQty = isset($item['project_qty']) ? (int) $item['project_qty'] : $quantity;
            if ($projectQty < 0) {
                return 'Project Qty cannot be negative.';
            }
            if ($projectQty > $quantity) {
                return 'Project Qty cannot exceed Purchased Qty.';
            }
        }

        return null;
    }

    /**
     * Approved design (Decision 8, conditions 2 & 3): block edit when either the
     * PROJECT-side or GENERAL-side stock this purchase posted has already been
     * sold — i.e. the currently available balance for a product/source this
     * purchase contributed to is less than what this purchase posted, meaning
     * some of it has already moved out via a sale (or another purchase's
     * General allocation has since been drawn below this purchase's share).
     */
    private function _checkAllocationConsumed(\CodeIgniter\Database\BaseConnection $db, int $purchaseId): ?string
    {
        $purchase = $db->table('purchases')->where('id', $purchaseId)->get()->getRowArray();
        if (!$purchase) return null;

        $rows = $db->query("
            SELECT pi.product_id, pi.project_qty, pi.general_qty, pr.name AS product_name
            FROM purchase_items pi
            LEFT JOIN products pr ON pi.product_id = pr.id
            WHERE pi.purchase_id = ?
        ", [$purchaseId])->getResultArray();

        $ledgerModel = new StockLedgerModel();
        $projectId   = (int) $purchase['project_id'];

        foreach ($rows as $row) {
            $productId = (int) $row['product_id'];
            $name      = $row['product_name'] ?? ('Product #' . $productId);

            if ((int) $row['project_qty'] > 0) {
                $available = $ledgerModel->getAvailableStock($productId, 'PROJECT', $projectId);
                if ($available < (float) $row['project_qty']) {
                    return "Cannot modify: {$name} — project stock from this purchase has already been sold.";
                }
            }

            if ((int) $row['general_qty'] > 0) {
                $available = $ledgerModel->getAvailableStock($productId, 'GENERAL');
                if ($available < (float) $row['general_qty']) {
                    return "Cannot modify: {$name} — general stock from this purchase has already been sold.";
                }
            }
        }

        return null;
    }


    public function view($id)
    {
        $db = \Config\Database::connect();
        $data['purchase'] = $db->query("
            SELECT pu.*, s.name AS supplier_name, p.name AS project_name
            FROM purchases pu
            LEFT JOIN suppliers s ON pu.supplier_id = s.id
            LEFT JOIN projects p ON pu.project_id = p.id
            WHERE pu.id = ?
        ", [$id])->getRowArray();

        if (!$data['purchase']) return redirect()->to('/purchases')->with('error', 'Purchase not found.');

        $data['items'] = $db->query("
            SELECT pi.*, pr.name AS product_name, pr.unit, pr.hsn_code AS product_hsn
			FROM purchase_items pi
			LEFT JOIN products pr ON pi.product_id = pr.id
			WHERE pi.purchase_id = ?
        ", [$id])->getResultArray();

        $data['gst_summary'] = gst_summarize_items($data['items']);
        $data['allocated_project_amount'] = (new ProjectModel())->getAllocatedPurchaseCostForPurchase((int) $id);

        return view('purchases/view', $data);
    }

    public function delete($id)
    {
        $db = \Config\Database::connect();

        if ($consumptionError = $this->_checkAllocationConsumed($db, (int) $id)) {
            return redirect()->to('/purchases')->with('error', $consumptionError);
        }

        $db->transStart();
        $db->table('stock_ledger')->where('reference_type','PURCHASE')->where('reference_id',$id)->delete();
        $db->table('purchase_items')->where('purchase_id',$id)->delete();
        $db->table('purchases')->where('id',$id)->delete();
        $db->transComplete();
        return redirect()->to('/purchases')->with('success', 'Purchase deleted and stock reversed.');
    }
}
