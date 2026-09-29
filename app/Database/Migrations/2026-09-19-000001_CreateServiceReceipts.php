<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.4A (Phase A): Service Received header table (Module 4).
 * A document recording services A&A delivered to a customer, either as an
 * INVOICE (received_amount may be less than grand_total, leaving an
 * outstanding balance) or DIRECT (paid in full on the spot, outstanding 0).
 *
 * customer_name / customer_address are a snapshot of the customer at
 * document time, so a later edit of the customer record does not silently
 * rewrite historical service receipts. bank_account_id is stored only — the
 * automatic bank deposit is a later release (4.8.4C).
 */
class CreateServiceReceipts extends Migration
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
            'receipt_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'receipt_type' => [
                'type'       => 'ENUM',
                'constraint' => ['INVOICE', 'DIRECT'],
                'default'    => 'INVOICE',
                'null'       => false,
            ],
            'customer_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'customer_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'customer_address' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'receipt_date' => [
                'type' => 'DATE',
            ],
            'attended_person' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'subtotal' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'gst_total' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'grand_total' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'received_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'outstanding_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'payment_status' => [
                'type'       => 'ENUM',
                'constraint' => ['PENDING', 'PARTIAL', 'PAID'],
                'default'    => 'PENDING',
                'null'       => false,
            ],
            'payment_mode' => [
                'type'       => 'ENUM',
                'constraint' => ['CASH', 'BANK', 'CHEQUE', 'UPI', 'OTHER'],
                'default'    => 'CASH',
                'null'       => false,
            ],
            'bank_account_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'remarks' => [
                'type' => 'TEXT',
                'null' => true,
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
        $this->forge->addUniqueKey('receipt_no');
        $this->forge->addKey('customer_id');
        $this->forge->addKey('receipt_date');
        $this->forge->addKey('payment_status');
        $this->forge->addKey('bank_account_id');
        $this->forge->addForeignKey('customer_id', 'customers', 'id');
        $this->forge->addForeignKey('bank_account_id', 'bank_accounts', 'id');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('service_receipts');
    }

    public function down()
    {
        $this->forge->dropTable('service_receipts');
    }
}
