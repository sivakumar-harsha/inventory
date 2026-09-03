<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 1.6.4 — Apply Project Advance to Invoice Payments.
 * Adds sales.advance_applied: the portion of the project's advance FIFO-
 * allocated to this invoice (Rules 1-3). Recomputed by
 * SaleModel::recalculateProjectBilling() whenever a sale or its project's
 * advance changes — never edited directly.
 *
 * Existing rows default to 0; a one-time backfill (recalculateProjectBilling
 * for every project with advance_amount > 0) brings historical data in line
 * with the new rule and is run separately, not inside this migration.
 */
class AddSalesAdvanceApplied extends Migration
{
    public function up()
    {
        $this->db->query("
            ALTER TABLE sales
                ADD COLUMN advance_applied DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER total_amount
        ");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE sales DROP COLUMN advance_applied");
    }
}
