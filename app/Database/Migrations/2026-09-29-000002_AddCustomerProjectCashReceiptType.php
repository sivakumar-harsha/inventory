<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.9.0T: Customer Project Cash Without Invoice. Extends
 * project_cash_receipts.receipt_type with a third value, CUSTOMER_PROJECT_CASH
 * — genuine customer money received for a project without selecting a sales
 * invoice. Distinct from ADVANCE (held against a future invoice, a liability
 * until applied) and DIRECT_INCOME (revenue that will never be invoiced):
 * CUSTOMER_PROJECT_CASH is a project-level customer collection that reduces
 * Outstanding Collection directly, without touching any sales.balance_amount.
 * Default stays 'ADVANCE' — no existing row is reinterpreted by this migration.
 */
class AddCustomerProjectCashReceiptType extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('project_cash_receipts', [
            'receipt_type' => [
                'name'       => 'receipt_type',
                'type'       => 'ENUM',
                'constraint' => ['ADVANCE', 'DIRECT_INCOME', 'CUSTOMER_PROJECT_CASH'],
                'default'    => 'ADVANCE',
                'null'       => false,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('project_cash_receipts', [
            'receipt_type' => [
                'name'       => 'receipt_type',
                'type'       => 'ENUM',
                'constraint' => ['ADVANCE', 'DIRECT_INCOME'],
                'default'    => 'ADVANCE',
                'null'       => false,
            ],
        ]);
    }
}
