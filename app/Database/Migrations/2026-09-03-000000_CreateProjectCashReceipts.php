<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.5 (Phase 1): Project Cash Receipt.
 *
 * A single new, independent table. No FK to sales/payments, no allocation
 * table — a Project Cash Receipt is money received against a project as a
 * whole, tracked as a simple ledger. It never touches sales.paid_amount,
 * sales.balance_amount, payments, or projects.advance_amount (all frozen
 * per the Release 4.5 spec). Existing invoice-payment flow is unmodified.
 */
class CreateProjectCashReceipts extends Migration
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
            'project_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'customer_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'receipt_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
            ],
            'receipt_date' => [
                'type' => 'DATE',
            ],
            'payment_method' => [
                'type'       => 'ENUM',
                'constraint' => ['CASH', 'BANK_TRANSFER', 'CHECK', 'OTHER'],
                'default'    => 'CASH',
                'null'       => false,
            ],
            'reference' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
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
        $this->forge->addUniqueKey('receipt_no');
        $this->forge->addKey('project_id');
        $this->forge->addForeignKey('project_id', 'projects', 'id');
        $this->forge->createTable('project_cash_receipts');
    }

    public function down()
    {
        $this->forge->dropTable('project_cash_receipts');
    }
}
