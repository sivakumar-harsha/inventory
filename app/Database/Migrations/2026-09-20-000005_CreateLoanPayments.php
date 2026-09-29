<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.5A (Module 5, Phase A): payment history of a loan. One row per
 * amount paid to the lender, split into principal_paid + interest_paid
 * (= total_paid). loan_emi_id ties the payment to a scheduled instalment and is
 * NULL for a prepayment or any payment outside the schedule.
 *
 * bank_account_id is set only when the money left through a bank; a future
 * release will post it as a WITHDRAWAL (reference_type LOAN_PAYMENT).
 *
 * The foreign keys deliberately do not cascade: a loan that has payments
 * cannot be deleted, and an EMI row that has payments cannot be dropped.
 */
class CreateLoanPayments extends Migration
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
            'loan_emi_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'payment_date' => [
                'type' => 'DATE',
            ],
            'payment_method' => [
                'type'       => 'ENUM',
                'constraint' => ['CASH', 'BANK', 'CHEQUE', 'UPI', 'OTHER'],
                'default'    => 'BANK',
            ],
            'bank_account_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'principal_paid' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'interest_paid' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'total_paid' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
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
        $this->forge->addKey('loan_id');
        $this->forge->addKey('loan_emi_id');
        $this->forge->addKey('payment_date');
        $this->forge->addKey('bank_account_id');
        $this->forge->addForeignKey('loan_id', 'loans', 'id');
        $this->forge->addForeignKey('loan_emi_id', 'loan_emis', 'id');
        $this->forge->addForeignKey('bank_account_id', 'bank_accounts', 'id');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('loan_payments');
    }

    public function down()
    {
        $this->forge->dropTable('loan_payments');
    }
}
