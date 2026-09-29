<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.3A (Module 3, Phase 1): company bank account master. Manual
 * entries only — no automatic posting from any other module yet.
 * current_balance is denormalized (kept in sync by BankAccounts::store()/
 * store-transaction() rather than recomputed on every read) so the account
 * list can show balances without summing bank_transactions each time.
 */
class CreateBankAccounts extends Migration
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
            'bank_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'account_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'account_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'ifsc_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'branch_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'opening_balance' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'current_balance' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
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
        $this->forge->addKey('is_active');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('bank_accounts');
    }

    public function down()
    {
        $this->forge->dropTable('bank_accounts');
    }
}
