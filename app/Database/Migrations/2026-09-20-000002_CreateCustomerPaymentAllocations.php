<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.4C (Phase A): one row per service-receipt invoice a customer
 * payment voucher pays against. invoice_amount is the receipt's grand total
 * when the row was written; balance_amount is what remained outstanding on
 * that invoice right after this allocation.
 *
 * The voucher FK cascades (deleting a voucher removes its rows), but the
 * service_receipt_id FK deliberately does NOT: a receipt that has customer
 * payments against it cannot be deleted until those payments are deleted, so
 * an allocation can never point at a missing invoice. One row per
 * (voucher, receipt) — enforced by the unique key.
 */
class CreateCustomerPaymentAllocations extends Migration
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
            'customer_payment_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'service_receipt_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'invoice_amount' => [
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
        $this->forge->addUniqueKey(['customer_payment_id', 'service_receipt_id']);
        $this->forge->addKey('service_receipt_id');
        $this->forge->addForeignKey('customer_payment_id', 'customer_payments', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('service_receipt_id', 'service_receipts', 'id');
        $this->forge->createTable('customer_payment_allocations');
    }

    public function down()
    {
        $this->forge->dropTable('customer_payment_allocations');
    }
}
