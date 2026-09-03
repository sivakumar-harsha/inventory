<?php

namespace App\Models;

use CodeIgniter\Model;

class StockLedgerModel extends Model
{
    protected $table      = 'stock_ledger';
    protected $primaryKey = 'id';
    protected $allowedFields = ['product_id', 'transaction_type', 'quantity', 'source', 'project_id', 'reference_type', 'reference_id', 'transaction_date', 'notes'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = null;

    /**
     * Get available stock for a product by source
     */
    public function getAvailableStock($productId, $source = 'GENERAL', $projectId = null)
    {
        $db = \Config\Database::connect();
        if ($source === 'GENERAL') {
            $row = $db->query("
                SELECT
                    COALESCE(SUM(CASE WHEN transaction_type='IN' THEN quantity ELSE 0 END),0)
                    - COALESCE(SUM(CASE WHEN transaction_type='OUT' THEN quantity ELSE 0 END),0) AS qty
                FROM stock_ledger
                WHERE product_id=? AND source='GENERAL'
            ", [$productId])->getRow();
        } else {
            $row = $db->query("
                SELECT
                    COALESCE(SUM(CASE WHEN transaction_type='IN' THEN quantity ELSE 0 END),0)
                    - COALESCE(SUM(CASE WHEN transaction_type='OUT' THEN quantity ELSE 0 END),0) AS qty
                FROM stock_ledger
                WHERE product_id=? AND source='PROJECT' AND project_id=?
            ", [$productId, $projectId])->getRow();
        }
        return $row ? (float)$row->qty : 0;
    }
}
