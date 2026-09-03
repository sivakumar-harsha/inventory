<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStockReturns extends Migration
{
    public function up()
    {
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

        // project_id is NOT NULL with no ON DELETE clause, matching sales.project_id's
        // convention (a project with return history cannot be silently orphaned).
        $this->db->query("
            ALTER TABLE stock_returns
                ADD CONSTRAINT stock_returns_ibfk_1 FOREIGN KEY (project_id) REFERENCES projects (id)
        ");

        // purchase_item_id has no ON DELETE clause (defaults to RESTRICT) so that a
        // purchase line that has been returned against cannot be deleted out from under
        // the return record; Purchases::update()/delete() also check this explicitly
        // to surface a friendly error before the DB constraint would ever fire.
        $this->db->query("
            ALTER TABLE stock_return_items
                ADD CONSTRAINT stock_return_items_ibfk_1 FOREIGN KEY (stock_return_id) REFERENCES stock_returns (id) ON DELETE CASCADE,
                ADD CONSTRAINT stock_return_items_ibfk_2 FOREIGN KEY (product_id) REFERENCES products (id),
                ADD CONSTRAINT stock_return_items_ibfk_3 FOREIGN KEY (purchase_item_id) REFERENCES purchase_items (id)
        ");

        $this->db->query("ALTER TABLE stock_ledger MODIFY reference_type ENUM('PURCHASE','SALE','MANUAL','RETURN') DEFAULT NULL");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE stock_ledger MODIFY reference_type ENUM('PURCHASE','SALE','MANUAL') DEFAULT NULL");
        $this->forge->dropTable('stock_return_items', true);
        $this->forge->dropTable('stock_returns', true);
    }
}
