<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.6.5: Direct Project Income. Adds a receipt_type classification
 * to Project Cash Receipts so cash that will never be invoiced (DIRECT_INCOME)
 * can be recognized as revenue, while true advances (ADVANCE) stay excluded
 * from revenue until (if ever) invoiced. Default 'ADVANCE' preserves the
 * exact current behavior for every existing row — no historical revenue
 * appears retroactively from this migration alone.
 */
class AddReceiptTypeToProjectCashReceipts extends Migration
{
    public function up()
    {
        $this->forge->addColumn('project_cash_receipts', [
            'receipt_type' => [
                'type'       => 'ENUM',
                'constraint' => ['ADVANCE', 'DIRECT_INCOME'],
                'default'    => 'ADVANCE',
                'null'       => false,
                'after'      => 'payment_method',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('project_cash_receipts', 'receipt_type');
    }
}
