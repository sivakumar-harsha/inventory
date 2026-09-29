<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.2A (Phase 1): Supplier Payment voucher header — database and
 * model foundation only, no controller/UI. One voucher can pay multiple
 * bills (across Project Purchase and General Purchase) and/or carry a
 * supplier advance; the per-bill split lives in supplier_payment_allocations
 * (created separately). This migration does not touch `purchases`,
 * `general_purchases`, `stock_ledger`, or any bank table.
 */
class CreateSupplierPayments extends Migration
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
            'payment_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'supplier_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'payment_date' => [
                'type' => 'DATE',
            ],
            'payment_method' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'reference_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'remarks' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'total_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'advance_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
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
        $this->forge->addUniqueKey('payment_no');
        $this->forge->addKey('supplier_id');
        $this->forge->addKey('payment_date');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('supplier_payments');
    }

    public function down()
    {
        $this->forge->dropTable('supplier_payments');
    }
}
