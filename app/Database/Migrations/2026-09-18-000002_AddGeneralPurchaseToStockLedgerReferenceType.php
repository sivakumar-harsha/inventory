<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.1D: stock_ledger.reference_type is a fixed ENUM('PURCHASE',
 * 'SALE','MANUAL') with no 'GENERAL_PURCHASE' value, so General Purchase
 * cannot post stock without widening it. Adds 'GENERAL_PURCHASE' only —
 * existing values/rows are untouched, and 'WAREHOUSE_ISSUE' (needed by the
 * future Warehouse Issue screen) is intentionally left out, out of scope here.
 */
class AddGeneralPurchaseToStockLedgerReferenceType extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE stock_ledger MODIFY reference_type ENUM('PURCHASE','SALE','MANUAL','GENERAL_PURCHASE') NULL");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE stock_ledger MODIFY reference_type ENUM('PURCHASE','SALE','MANUAL') NULL");
    }
}
