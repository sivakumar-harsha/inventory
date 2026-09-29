<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.2A (Phase 1): one row per bill a voucher pays against. purchase_id
 * is deliberately a plain unsigned int with no foreign key: it is polymorphic,
 * pointing at either `purchases.id` (purchase_type = PROJECT) or
 * `general_purchases.id` (purchase_type = GENERAL) depending on the sibling
 * column, and a single FK cannot target two tables. Referential integrity
 * for this column is enforced in the model/service layer, not the schema.
 */
class CreateSupplierPaymentAllocations extends Migration
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
            'supplier_payment_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'purchase_type' => [
                'type'       => 'ENUM',
                'constraint' => ['PROJECT', 'GENERAL'],
            ],
            'purchase_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'bill_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'paid_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'balance_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
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
        $this->forge->addKey('supplier_payment_id');
        $this->forge->addKey(['purchase_type', 'purchase_id']);
        $this->forge->addForeignKey('supplier_payment_id', 'supplier_payments', 'id', '', 'CASCADE');
        $this->forge->createTable('supplier_payment_allocations');
    }

    public function down()
    {
        $this->forge->dropTable('supplier_payment_allocations');
    }
}
