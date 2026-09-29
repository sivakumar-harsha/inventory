<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.5D recovery (1 of 4): the database restored from the Sep-18 dump
 * came back with no PRIMARY KEY on sale_items / stock_ledger / suppliers / users
 * and no AUTO_INCREMENT on any table, so nothing can allocate an id — including
 * the migration runner's own history row. Restores both exactly as the dump had
 * them. `migrations` is listed first so the runner can record this migration.
 *
 * Idempotent and non-destructive: a table that already has its primary key /
 * AUTO_INCREMENT is skipped, existing ids are never changed, and the next id is
 * never lower than the dump's counter or MAX(id)+1, so deleted ids are not reused.
 * `customers` is handled by RestoreBaseCustomersTable, which creates it complete.
 */
class RestoreBasePrimaryKeysAndAutoIncrement extends Migration
{
    /** table => [id column type, AUTO_INCREMENT counter in the Sep-18 dump] */
    private const TABLES = [
        'migrations'            => ['BIGINT(20) UNSIGNED', 13],
        'expenses'              => ['INT(10) UNSIGNED', 4],
        'expense_categories'    => ['INT(10) UNSIGNED', 7],
        'password_resets'       => ['INT(10) UNSIGNED', 7],
        'payments'              => ['INT(10) UNSIGNED', 5],
        'products'              => ['INT(10) UNSIGNED', 28],
        'projects'              => ['INT(10) UNSIGNED', 23],
        'project_cash_receipts' => ['INT(10) UNSIGNED', 4],
        'purchases'             => ['INT(10) UNSIGNED', 9],
        'purchase_items'        => ['INT(10) UNSIGNED', 17],
        'sales'                 => ['INT(10) UNSIGNED', 5],
        'sale_items'            => ['INT(10) UNSIGNED', 9],
        'stock_ledger'          => ['INT(10) UNSIGNED', 26],
        'suppliers'             => ['INT(10) UNSIGNED', 17],
        'users'                 => ['INT(10) UNSIGNED', 2],
    ];

    public function up()
    {
        foreach (self::TABLES as $table => [$type, $floor]) {
            if (! $this->hasPrimaryKey($table)) {
                $this->db->query("ALTER TABLE `{$table}` ADD PRIMARY KEY (`id`)");
            }

            if (! $this->isAutoIncrement($table)) {
                $max  = (int) $this->db->query("SELECT COALESCE(MAX(`id`), 0) AS m FROM `{$table}`")->getRow()->m;
                $next = max($floor, $max + 1);

                $this->db->query("ALTER TABLE `{$table}` MODIFY `id` {$type} NOT NULL AUTO_INCREMENT, AUTO_INCREMENT={$next}");
            }
        }
    }

    /**
     * Deliberately a no-op: dropping these keys would put the database back into
     * the state where no INSERT can allocate an id (including the migration
     * history row that a rollback itself needs).
     */
    public function down()
    {
    }

    private function hasPrimaryKey(string $table): bool
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_TYPE = 'PRIMARY KEY'",
            [$table]
        )->getRow();

        return ((int) $row->c) > 0;
    }

    private function isAutoIncrement(string $table): bool
    {
        $row = $this->db->query(
            "SELECT EXTRA AS e FROM information_schema.COLUMNS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'id'",
            [$table]
        )->getRow();

        return $row !== null && stripos((string) $row->e, 'auto_increment') !== false;
    }
}
