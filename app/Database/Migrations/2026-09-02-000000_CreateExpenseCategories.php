<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.5 (Phase B/E): Expense Category Master.
 *
 * expenses.category stays the existing varchar(100) column (no schema
 * change, no FK) — it continues to store the category name directly, so
 * every existing expense row keeps working unmodified. This migration only
 * adds the new expense_categories lookup table and backfills it with the
 * distinct category names already present in expenses, so the dynamic
 * dropdown has every value old data needs.
 */
class CreateExpenseCategories extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'category_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['ACTIVE', 'INACTIVE'],
                'default'    => 'ACTIVE',
                'null'       => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('category_name');
        $this->forge->createTable('expense_categories');

        // Phase E: import every distinct existing expenses.category value so
        // old data keeps matching a real master row. Duplicates are ignored
        // by the unique key above.
        $this->db->query("
            INSERT IGNORE INTO expense_categories (category_name, status, created_at, updated_at)
            SELECT DISTINCT category, 'ACTIVE', NOW(), NOW()
            FROM expenses
            WHERE category IS NOT NULL AND category <> ''
        ");
    }

    public function down()
    {
        $this->forge->dropTable('expense_categories');
    }
}
