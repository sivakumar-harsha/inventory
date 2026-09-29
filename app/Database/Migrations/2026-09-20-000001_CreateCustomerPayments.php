<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.4C (Phase A): customer payment voucher header (CPV-000001).
 * One voucher records money received from one customer, split into payments
 * against outstanding service-receipt invoices (customer_payment_allocations)
 * and an unallocated advance (advance_amount). total_amount is the whole cash
 * received: allocated + advance.
 *
 * bank_account_id is set only for BANK/CHEQUE/UPI, where the voucher posts a
 * DEPOSIT (reference_type SERVICE_RECEIPT_PAYMENT) to that account.
 */
class CreateCustomerPayments extends Migration
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
            'customer_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'payment_date' => [
                'type' => 'DATE',
            ],
            'payment_method' => [
                'type'       => 'ENUM',
                'constraint' => ['CASH', 'BANK', 'CHEQUE', 'UPI', 'OTHER'],
                'default'    => 'CASH',
            ],
            'bank_account_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
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
        $this->forge->addKey('customer_id');
        $this->forge->addKey('payment_date');
        $this->forge->addKey('bank_account_id');
        $this->forge->addForeignKey('customer_id', 'customers', 'id');
        $this->forge->addForeignKey('bank_account_id', 'bank_accounts', 'id');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('customer_payments');
    }

    public function down()
    {
        $this->forge->dropTable('customer_payments');
    }
}
