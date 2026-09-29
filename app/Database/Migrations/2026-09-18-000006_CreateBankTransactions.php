<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.3A (Module 3, Phase 1): one row per manual bank entry
 * (Deposit / Withdrawal / Transfer). reference_type/reference_id are a
 * polymorphic pointer reserved for a future automatic-posting release (e.g.
 * a Supplier Payment made by Bank Transfer) — deliberately no foreign key on
 * reference_id, same reasoning as supplier_payment_allocations.purchase_id,
 * since it is not authorized or wired up in this release ("No automatic
 * integration yet. Manual entries only."). transfer_bank_account_id is the
 * destination account for a TRANSFER row; null for DEPOSIT/WITHDRAWAL.
 */
class CreateBankTransactions extends Migration
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
            'bank_account_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'transaction_date' => [
                'type' => 'DATE',
            ],
            'transaction_type' => [
                'type'       => 'ENUM',
                'constraint' => ['DEPOSIT', 'WITHDRAWAL', 'TRANSFER'],
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'reference_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'reference_id' => [
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
            'transfer_bank_account_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
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
        $this->forge->addKey('bank_account_id');
        $this->forge->addKey('transaction_date');
        $this->forge->addKey(['reference_type', 'reference_id']);
        $this->forge->addForeignKey('bank_account_id', 'bank_accounts', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('transfer_bank_account_id', 'bank_accounts', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('bank_transactions');
    }

    public function down()
    {
        $this->forge->dropTable('bank_transactions');
    }
}
