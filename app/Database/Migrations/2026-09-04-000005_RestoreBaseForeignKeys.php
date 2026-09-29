<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.5D recovery (4 of 4): the Sep-18 restore came back with none of
 * the 15 foreign keys the dump defined on the base tables. Re-adds them with
 * the same names and ON DELETE / ON UPDATE rules. The 4.8.x foreign keys are
 * created by their own migrations and are not repeated here.
 *
 * Adding a key makes InnoDB validate every existing row, so this checks all
 * missing keys for orphan rows first and, if any exist, stops with the list
 * BEFORE altering anything. It never deletes or edits data. Idempotent: a
 * constraint that already exists by name is skipped.
 */
class RestoreBaseForeignKeys extends Migration
{
    /** [constraint name, child table, child column, parent table, ON DELETE, ON UPDATE] — null = MySQL default (RESTRICT) */
    private const FOREIGN_KEYS = [
        ['expenses_ibfk_1',                          'expenses',              'project_id',  'projects',  'SET NULL', null],
        ['password_resets_user_id_foreign',          'password_resets',       'user_id',     'users',     'CASCADE',  'CASCADE'],
        ['payments_ibfk_1',                          'payments',              'sale_id',     'sales',     null,       null],
        ['projects_ibfk_1',                          'projects',              'customer_id', 'customers', 'SET NULL', null],
        ['project_cash_receipts_project_id_foreign', 'project_cash_receipts', 'project_id',  'projects',  null,       null],
        ['purchases_ibfk_1',                         'purchases',             'supplier_id', 'suppliers', null,       null],
        ['purchases_ibfk_2',                         'purchases',             'project_id',  'projects',  'SET NULL', null],
        ['purchase_items_ibfk_1',                    'purchase_items',        'purchase_id', 'purchases', 'CASCADE',  null],
        ['purchase_items_ibfk_2',                    'purchase_items',        'product_id',  'products',  null,       null],
        ['sales_ibfk_1',                             'sales',                 'project_id',  'projects',  null,       null],
        ['sales_ibfk_2',                             'sales',                 'customer_id', 'customers', 'SET NULL', null],
        ['sale_items_ibfk_1',                        'sale_items',            'sale_id',     'sales',     'CASCADE',  null],
        ['sale_items_ibfk_2',                        'sale_items',            'product_id',  'products',  null,       null],
        ['stock_ledger_ibfk_1',                      'stock_ledger',          'product_id',  'products',  null,       null],
        ['stock_ledger_ibfk_2',                      'stock_ledger',          'project_id',  'projects',  'SET NULL', null],
    ];

    public function up()
    {
        $missing = [];
        $orphans = [];

        foreach (self::FOREIGN_KEYS as $fk) {
            [$name, $child, $column, $parent] = $fk;

            if ($this->hasForeignKey($child, $name)) {
                continue;
            }

            $missing[] = $fk;

            $row = $this->db->query(
                "SELECT COUNT(*) AS orphans FROM `{$child}` ch LEFT JOIN `{$parent}` p ON p.`id` = ch.`{$column}` "
                . "WHERE ch.`{$column}` IS NOT NULL AND p.`id` IS NULL"
            )->getRow();

            if ((int) $row->orphans > 0) {
                $orphans[] = "{$child}.{$column} -> {$parent}.id ({$row->orphans} row(s))";
            }
        }

        if ($orphans !== []) {
            throw new \RuntimeException(
                'Base foreign keys not added, nothing was changed. Rows reference missing parent rows: '
                . implode('; ', $orphans)
            );
        }

        foreach ($missing as [$name, $child, $column, $parent, $onDelete, $onUpdate]) {
            $sql = "ALTER TABLE `{$child}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`) REFERENCES `{$parent}` (`id`)";

            if ($onDelete !== null) {
                $sql .= " ON DELETE {$onDelete}";
            }

            if ($onUpdate !== null) {
                $sql .= " ON UPDATE {$onUpdate}";
            }

            $this->db->query($sql);
        }
    }

    public function down()
    {
        foreach (array_reverse(self::FOREIGN_KEYS) as [$name, $child]) {
            if ($this->hasForeignKey($child, $name)) {
                $this->db->query("ALTER TABLE `{$child}` DROP FOREIGN KEY `{$name}`");
            }
        }
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS '
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $name]
        )->getRow();

        return ((int) $row->c) > 0;
    }
}
