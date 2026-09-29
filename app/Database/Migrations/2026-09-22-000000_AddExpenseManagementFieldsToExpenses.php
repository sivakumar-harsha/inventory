<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.6A (Phase A): extends the pre-4.5 `expenses` table (project_id,
 * category, description, amount, expense_date — 2 live rows) into the
 * Expense Management Foundation shape, without a destructive rewrite.
 *
 * `category` and `description` are left in place, untouched and unused by
 * the new code, so the 2 existing rows keep their original data verbatim.
 * `category_id` and `expense_no` are added nullable first, backfilled from
 * the existing data, then locked to NOT NULL — `category_id` via a join on
 * the category name (expense_categories was itself backfilled from these
 * same rows in 2026-09-02-000000_CreateExpenseCategories), `expense_no` via
 * a generated EXP-000001 style value. `paid_to` has no equivalent legacy
 * value, so it is backfilled with a placeholder ('Legacy Entry') purely so
 * the column can be NOT NULL from here on — every new insert supplies a
 * real value via controller validation.
 */
class AddExpenseManagementFieldsToExpenses extends Migration
{
    public function up()
    {
        $this->forge->addColumn('expenses', [
            'expense_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'id',
            ],
            'category_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'category',
            ],
            'paid_to' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => false,
                'default'    => '',
                'after'      => 'description',
            ],
            'payment_method' => [
                'type'       => 'ENUM',
                'constraint' => ['CASH', 'BANK', 'CHEQUE', 'UPI', 'OTHER'],
                'null'       => false,
                'default'    => 'CASH',
                'after'      => 'paid_to',
            ],
            'bank_account_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'payment_method',
            ],
            'remarks' => [
                'type'       => 'TEXT',
                'null'       => true,
                'after'      => 'amount',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['PAID', 'CANCELLED'],
                'null'       => false,
                'default'    => 'PAID',
                'after'      => 'remarks',
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'status',
            ],
        ]);

        // Backfill category_id from the existing free-text category name —
        // every current value already has a matching expense_categories row
        // (they were the source of that table's own backfill).
        $this->db->query("
            UPDATE expenses e
            JOIN expense_categories c ON c.category_name = e.category
            SET e.category_id = c.id
            WHERE e.category_id IS NULL
        ");

        // Backfill expense_no in id order so existing rows get a stable,
        // unique number before the unique key is added.
        $this->db->query("
            UPDATE expenses
            SET expense_no = CONCAT('EXP-', LPAD(id, 6, '0'))
            WHERE expense_no IS NULL
        ");

        // Bootstrap-only placeholder for the 2 pre-existing rows; every
        // insert from here on supplies a real paid_to via validation.
        $this->db->query("UPDATE expenses SET paid_to = 'Legacy Entry' WHERE paid_to = ''");

        $this->db->query('ALTER TABLE `expenses` MODIFY `category_id` INT(10) UNSIGNED NOT NULL');
        $this->db->query('ALTER TABLE `expenses` MODIFY `expense_no` VARCHAR(20) NOT NULL');

        $this->db->query('ALTER TABLE `expenses` ADD UNIQUE KEY `expenses_expense_no_unique` (`expense_no`)');
        $this->db->query('ALTER TABLE `expenses` ADD KEY `expenses_expense_date_index` (`expense_date`)');
        $this->db->query('ALTER TABLE `expenses` ADD KEY `expenses_status_index` (`status`)');

        $this->db->query('ALTER TABLE `expenses` ADD KEY `expenses_category_id_index` (`category_id`)');
        $this->db->query(
            'ALTER TABLE `expenses` ADD CONSTRAINT `expenses_category_id_foreign` '
            . 'FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`)'
        );

        $this->db->query('ALTER TABLE `expenses` ADD KEY `expenses_bank_account_id_index` (`bank_account_id`)');
        $this->db->query(
            'ALTER TABLE `expenses` ADD CONSTRAINT `expenses_bank_account_id_foreign` '
            . 'FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`)'
        );

        $this->db->query('ALTER TABLE `expenses` ADD KEY `expenses_created_by_index` (`created_by`)');
        $this->db->query(
            'ALTER TABLE `expenses` ADD CONSTRAINT `expenses_created_by_foreign` '
            . 'FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL'
        );
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `expenses` DROP FOREIGN KEY `expenses_created_by_foreign`');
        $this->db->query('ALTER TABLE `expenses` DROP FOREIGN KEY `expenses_bank_account_id_foreign`');
        $this->db->query('ALTER TABLE `expenses` DROP FOREIGN KEY `expenses_category_id_foreign`');

        $this->db->query('ALTER TABLE `expenses` DROP KEY `expenses_created_by_index`');
        $this->db->query('ALTER TABLE `expenses` DROP KEY `expenses_bank_account_id_index`');
        $this->db->query('ALTER TABLE `expenses` DROP KEY `expenses_category_id_index`');
        $this->db->query('ALTER TABLE `expenses` DROP KEY `expenses_status_index`');
        $this->db->query('ALTER TABLE `expenses` DROP KEY `expenses_expense_date_index`');
        $this->db->query('ALTER TABLE `expenses` DROP KEY `expenses_expense_no_unique`');

        $this->forge->dropColumn('expenses', [
            'expense_no',
            'category_id',
            'paid_to',
            'payment_method',
            'bank_account_id',
            'remarks',
            'status',
            'created_by',
        ]);
    }
}
