<?php

function getStock($productId, $projectId = null)
{
    $db = \Config\Database::connect();

    // GENERAL STOCK
    $general = $db->query("
        SELECT 
            SUM(CASE WHEN transaction_type='IN' THEN quantity ELSE 0 END) -
            SUM(CASE WHEN transaction_type='OUT' THEN quantity ELSE 0 END) AS qty
        FROM stock_ledger
        WHERE product_id = ? AND source = 'GENERAL'
    ", [$productId])->getRow()->qty ?? 0;

    // PROJECT STOCK
    $project = 0;
    if ($projectId) {
        $project = $db->query("
            SELECT 
                SUM(CASE WHEN transaction_type='IN' THEN quantity ELSE 0 END) -
                SUM(CASE WHEN transaction_type='OUT' THEN quantity ELSE 0 END) AS qty
            FROM stock_ledger
            WHERE product_id = ? AND source = 'PROJECT' AND project_id = ?
        ", [$productId, $projectId])->getRow()->qty ?? 0;
    }

    return $general + $project;
}