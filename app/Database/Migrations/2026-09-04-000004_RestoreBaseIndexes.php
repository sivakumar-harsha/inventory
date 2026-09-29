<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.5D recovery (3 of 4): the four tables that lost their PRIMARY KEY
 * in the Sep-18 restore also lost their secondary indexes, and `users` lost the
 * unique keys on `username` and `email` (so duplicate logins became possible).
 * Restores exactly the keys the Sep-18 dump had — nothing new. The sale_items
 * and stock_ledger indexes are also what the base foreign keys
 * (RestoreBaseForeignKeys) attach to.
 *
 * Idempotent: an index that already exists by name is skipped.
 */
class RestoreBaseIndexes extends Migration
{
    /** [table, index name, column, unique] */
    private const INDEXES = [
        ['sale_items',   'sale_id',            'sale_id',    false],
        ['sale_items',   'product_id',         'product_id', false],
        ['stock_ledger', 'product_id',         'product_id', false],
        ['stock_ledger', 'project_id',         'project_id', false],
        ['users',        'username',           'username',   true],
        ['users',        'users_email_unique', 'email',      true],
    ];

    public function up()
    {
        foreach (self::INDEXES as [$table, $name, $column, $unique]) {
            if ($this->hasIndex($table, $name)) {
                continue;
            }

            $kind = $unique ? 'UNIQUE KEY' : 'KEY';
            $this->db->query("ALTER TABLE `{$table}` ADD {$kind} `{$name}` (`{$column}`)");
        }
    }

    public function down()
    {
        foreach (array_reverse(self::INDEXES) as [$table, $name]) {
            if ($this->hasIndex($table, $name)) {
                $this->db->query("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
            }
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $name]
        )->getRow();

        return ((int) $row->c) > 0;
    }
}
