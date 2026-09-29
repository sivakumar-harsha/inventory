<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.5A (Module 5, Phase A): loan header. One row per loan the company
 * has taken (bank, NBFC, friends, vehicle finance, overdraft). The three running
 * figures — outstanding_principal, total_principal_paid, total_interest_paid —
 * and status are maintained by the Loans controller's engine, never typed in.
 *
 * bank_account_id is the company account the loan is serviced from (or, for a
 * bank loan/OD, the account it was drawn into). It is nullable because personal
 * and other private loans have no bank account.
 */
class CreateLoans extends Migration
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
            'loan_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'lender_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'loan_type' => [
                'type'       => 'ENUM',
                'constraint' => ['BANK', 'PERSONAL', 'VEHICLE', 'OD', 'OTHER'],
                'default'    => 'BANK',
            ],
            'bank_account_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'account_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'sanctioned_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'interest_rate' => [
                'type'       => 'DECIMAL',
                'constraint' => '7,3',
                'default'    => 0.000,
            ],
            'tenure_months' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'emi_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'start_date' => [
                'type' => 'DATE',
            ],
            'end_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'outstanding_principal' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'total_principal_paid' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'total_interest_paid' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['ACTIVE', 'CLOSED'],
                'default'    => 'ACTIVE',
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
        $this->forge->addUniqueKey('loan_no');
        $this->forge->addKey('status');
        $this->forge->addKey('start_date');
        $this->forge->addKey('lender_name');
        $this->forge->addKey('bank_account_id');
        $this->forge->addForeignKey('bank_account_id', 'bank_accounts', 'id');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('loans');
    }

    public function down()
    {
        $this->forge->dropTable('loans');
    }
}
