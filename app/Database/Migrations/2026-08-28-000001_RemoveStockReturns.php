<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 1.7C: safe removal of the Stock Return module. Purchase Allocation
 * (Release 1.6B) makes Stock Return unnecessary for the normal workflow.
 *
 * up() purges historical stock_ledger RETURN rows, drops stock_return_items
 * and stock_returns, then narrows stock_ledger.reference_type back to its
 * pre-1.6B enum. down() reverses the schema changes (recreates both tables
 * and re-widens the enum), but — being a destructive removal — cannot restore
 * the purged stock_ledger RETURN rows or any stock_returns/stock_return_items
 * data that existed before up() ran.
 */
class RemoveStockReturns extends Migration
{
    public function up()
    {
        // Purge historical RETURN ledger rows before narrowing the enum —
        // MySQL silently blanks values outside a MODIFY'd ENUM under
        // non-strict mode, or errors under strict mode; deleting first avoids both.
        $this->db->query("DELETE FROM stock_ledger WHERE reference_type = 'RETURN'");

        $this->forge->dropTable('stock_return_items', true);
        $this->forge->dropTable('stock_returns', true);

        $this->db->query("ALTER TABLE stock_ledger MODIFY reference_type ENUM('PURCHASE','SALE','MANUAL') DEFAULT NULL");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE stock_ledger MODIFY reference_type ENUM('PURCHASE','SALE','MANUAL','RETURN') DEFAULT NULL");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS stock_returns (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                project_id INT UNSIGNED NOT NULL,
                return_date DATE NOT NULL,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY project_id (project_id)
            ) ENGINE=InnoDB
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS stock_return_items (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                stock_return_id INT UNSIGNED NOT NULL,
                product_id INT UNSIGNED NOT NULL,
                purchase_item_id INT UNSIGNED NOT NULL,
                quantity INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY stock_return_id (stock_return_id),
                KEY product_id (product_id),
                KEY purchase_item_id (purchase_item_id)
            ) ENGINE=InnoDB
        ");

        $this->db->query("
            ALTER TABLE stock_returns
                ADD CONSTRAINT stock_returns_ibfk_1 FOREIGN KEY (project_id) REFERENCES projects (id)
        ");

        $this->db->query("
            ALTER TABLE stock_return_items
                ADD CONSTRAINT stock_return_items_ibfk_1 FOREIGN KEY (stock_return_id) REFERENCES stock_returns (id) ON DELETE CASCADE,
                ADD CONSTRAINT stock_return_items_ibfk_2 FOREIGN KEY (product_id) REFERENCES products (id),
                ADD CONSTRAINT stock_return_items_ibfk_3 FOREIGN KEY (purchase_item_id) REFERENCES purchase_items (id)
        ");
    }
}
