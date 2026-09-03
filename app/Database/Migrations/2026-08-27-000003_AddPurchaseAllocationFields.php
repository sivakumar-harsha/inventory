<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 1.6B — Purchase Allocation (Project + General in One Purchase).
 * Adds project_qty/general_qty to purchase_items (schema Approach A, per
 * RELEASE_1_6A_APPROVED_DESIGN.md Decision 1). One row per purchased line
 * is kept — no row-splitting — so purchase_items.id (and the FK from
 * stock_return_items.purchase_item_id) stays stable.
 *
 * Backfill makes every historical row behave exactly as before this
 * feature: project_qty = quantity, general_qty = 0 (100% project, as it
 * already was in practice).
 */
class AddPurchaseAllocationFields extends Migration
{
    public function up()
    {
        $this->db->query("
            ALTER TABLE purchase_items
                ADD COLUMN project_qty INT NOT NULL DEFAULT 0 AFTER quantity,
                ADD COLUMN general_qty INT NOT NULL DEFAULT 0 AFTER project_qty
        ");

        $this->db->query("UPDATE purchase_items SET project_qty = quantity, general_qty = 0");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE purchase_items DROP COLUMN project_qty, DROP COLUMN general_qty");
    }
}
