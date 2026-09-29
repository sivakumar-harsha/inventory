<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.6A (Phase A): additive-only extension of expense_categories
 * (created 2026-09-02, 5 live rows). Only `description` is genuinely
 * missing — `category_name`/`status` already serve as `name`/`is_active`
 * for the live ExpenseCategories module, so they are left exactly as they
 * are rather than renamed or duplicated.
 */
class AddDescriptionToExpenseCategories extends Migration
{
    public function up()
    {
        $this->forge->addColumn('expense_categories', [
            'description' => [
                'type'   => 'TEXT',
                'null'   => true,
                'after'  => 'category_name',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('expense_categories', 'description');
    }
}
