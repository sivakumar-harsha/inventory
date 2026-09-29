<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.5A (Module 5, Phase A): EMI schedule, one row per instalment of a
 * loan. principal_amount + interest_amount = emi_amount. paid_amount is what has
 * been paid against this instalment and balance_amount what is still unpaid on
 * it (emi_amount - paid_amount); payment_status follows from those. The
 * principal opening/closing balances of the schedule are not stored — they are
 * derived from the running total of principal_amount when the schedule is read.
 */
class CreateLoanEmis extends Migration
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
            'loan_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'emi_no' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'due_date' => [
                'type' => 'DATE',
            ],
            'principal_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'interest_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'emi_amount' => [
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
            'payment_status' => [
                'type'       => 'ENUM',
                'constraint' => ['PENDING', 'PARTIAL', 'PAID'],
                'default'    => 'PENDING',
            ],
            'paid_date' => [
                'type' => 'DATE',
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
        $this->forge->addUniqueKey(['loan_id', 'emi_no']);
        $this->forge->addKey('due_date');
        $this->forge->addKey('payment_status');
        $this->forge->addForeignKey('loan_id', 'loans', 'id', '', 'CASCADE');
        $this->forge->createTable('loan_emis');
    }

    public function down()
    {
        $this->forge->dropTable('loan_emis');
    }
}
