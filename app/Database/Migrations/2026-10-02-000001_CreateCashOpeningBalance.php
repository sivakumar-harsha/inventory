<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.9.0CC: the stored opening cash balance. Cash has no account row (bank
 * opening balances live on bank_accounts.opening_balance), so the opening is a tiny
 * dated "balance brought forward" record. It is NOT a transaction: nothing reads it as
 * a receipt, income, bank or customer movement.
 *
 * Release 4.9.0CF: the opening is permanent and one-time. UNIQUE(lock_key) with a constant
 * lock_key = 1 lets the database itself hold at most ONE row; the application can only
 * insert it, never update or delete it. (Not yet applied to any live database.)
 */
class CreateCashOpeningBalance extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'opening_date' => ['type' => 'DATE'],
            'amount'       => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0.00],
            'remarks'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'lock_key'     => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 1],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('lock_key');
        $this->forge->createTable('cash_opening_balance', true);
    }

    public function down()
    {
        $this->forge->dropTable('cash_opening_balance', true);
    }
}
