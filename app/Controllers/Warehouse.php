<?php

namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\StockLedgerModel;
use CodeIgniter\Controller;

/**
 * Release 4.8.1E: read-only warehouse reporting, built entirely on the
 * existing stock_ledger table (source=GENERAL). No new tables, no writes —
 * every method here is a SELECT. Reuses StockLedgerModel::getAvailableStock()
 * unchanged for the summary page; the ledger/product-ledger pages compute
 * their own running balance since that view doesn't exist on the model yet.
 */
class Warehouse extends Controller
{
    /**
     * One row per product that has ever moved through GENERAL stock, with
     * its current available balance (SUM(IN) - SUM(OUT)) plus IN/OUT totals
     * for the KPI cards.
     */
    public function stockSummary()
    {
        $db = \Config\Database::connect();

        $rows = $db->query("
            SELECT
                p.id AS product_id,
                p.name AS product_name,
                p.unit,
                p.hsn_code,
                COALESCE(SUM(CASE WHEN sl.transaction_type='IN' THEN sl.quantity ELSE 0 END), 0) AS total_in,
                COALESCE(SUM(CASE WHEN sl.transaction_type='OUT' THEN sl.quantity ELSE 0 END), 0) AS total_out
            FROM products p
            INNER JOIN stock_ledger sl ON sl.product_id = p.id AND sl.source = 'GENERAL'
            GROUP BY p.id, p.name, p.unit, p.hsn_code
            ORDER BY p.name ASC
        ")->getResultArray();

        foreach ($rows as &$row) {
            $row['balance'] = (float) $row['total_in'] - (float) $row['total_out'];
        }
        unset($row);

        $data['products']         = $rows;
        $data['kpi_product_count'] = count($rows);
        $data['kpi_total_qty_in']  = array_sum(array_column($rows, 'total_in'));
        $data['kpi_total_qty_out'] = array_sum(array_column($rows, 'total_out'));
        $data['kpi_total_balance'] = array_sum(array_column($rows, 'balance'));

        return view('warehouse/stock_summary', $data);
    }

    /**
     * Full GENERAL-source transaction history across all products, oldest
     * first per product so the running balance is correct, then re-sorted
     * for display (newest first) while keeping the balance already computed.
     */
    public function stockLedger()
    {
        $db = \Config\Database::connect();

        $rows = $db->query("
            SELECT sl.*, p.name AS product_name, p.unit
            FROM stock_ledger sl
            LEFT JOIN products p ON sl.product_id = p.id
            WHERE sl.source = 'GENERAL'
            ORDER BY sl.product_id ASC, sl.transaction_date ASC, sl.id ASC
        ")->getResultArray();

        $rows = $this->_withRunningBalance($rows);

        // Newest activity first for display, balance values already fixed above.
        usort($rows, function ($a, $b) {
            return strcmp($b['transaction_date'] . str_pad($b['id'], 10, '0', STR_PAD_LEFT),
                          $a['transaction_date'] . str_pad($a['id'], 10, '0', STR_PAD_LEFT));
        });

        $data['ledger']          = $rows;
        $data['products']        = (new ProductModel())->orderBy('name', 'ASC')->findAll();
        $data['kpi_total_rows']  = count($rows);
        $data['kpi_total_in']    = array_sum(array_map(fn ($r) => $r['transaction_type'] === 'IN' ? (float) $r['quantity'] : 0, $rows));
        $data['kpi_total_out']   = array_sum(array_map(fn ($r) => $r['transaction_type'] === 'OUT' ? (float) $r['quantity'] : 0, $rows));

        return view('warehouse/stock_ledger', $data);
    }

    /**
     * Movement history for a single product, oldest-first with a running
     * balance column, then reversed for newest-first display.
     */
    public function productLedger($productId)
    {
        $product = (new ProductModel())->find((int) $productId);

        if (! $product) {
            return redirect()->to('/warehouse/stock-summary')->with('error', 'Product not found.');
        }

        $db = \Config\Database::connect();

        $rows = $db->query("
            SELECT sl.*
            FROM stock_ledger sl
            WHERE sl.source = 'GENERAL' AND sl.product_id = ?
            ORDER BY sl.transaction_date ASC, sl.id ASC
        ", [(int) $productId])->getResultArray();

        $rows = $this->_withRunningBalance($rows);
        $rows = array_reverse($rows);

        $data['product']        = $product;
        $data['ledger']         = $rows;
        $data['kpi_balance']    = (new StockLedgerModel())->getAvailableStock((int) $productId, 'GENERAL');
        $data['kpi_total_in']   = array_sum(array_map(fn ($r) => $r['transaction_type'] === 'IN' ? (float) $r['quantity'] : 0, $rows));
        $data['kpi_total_out']  = array_sum(array_map(fn ($r) => $r['transaction_type'] === 'OUT' ? (float) $r['quantity'] : 0, $rows));

        return view('warehouse/product_ledger', $data);
    }

    /**
     * Adds a 'balance' key to each row, running per product_id, assuming the
     * input is already ordered oldest-first (transaction_date, id ASC).
     */
    private function _withRunningBalance(array $rows): array
    {
        $running = [];

        foreach ($rows as &$row) {
            $pid = $row['product_id'];
            if (! isset($running[$pid])) {
                $running[$pid] = 0.0;
            }

            $running[$pid] += $row['transaction_type'] === 'IN' ? (float) $row['quantity'] : -(float) $row['quantity'];
            $row['balance'] = $running[$pid];
        }
        unset($row);

        return $rows;
    }
}
