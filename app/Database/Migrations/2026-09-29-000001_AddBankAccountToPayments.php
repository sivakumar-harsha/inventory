<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.9.0I: payments (Project Invoice Payment) gains bank_account_id —
 * the receiving account for a Bank Transfer/Check invoice payment, same
 * nullable FK-to-bank_accounts pattern as project_cash_receipts.bank_account_id
 * (2026-09-19-000000). NULL for Cash/Other and for every pre-existing row.
 */
class AddBankAccountToPayments extends Migration
{
    public function up()
    {
        $this->forge->addColumn('payments', [
            'bank_account_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'method',
            ],
        ]);

        $this->db->query('ALTER TABLE `payments` ADD KEY `bank_account_id` (`bank_account_id`)');
        $this->db->query(
            'ALTER TABLE `payments` ADD CONSTRAINT `payments_bank_account_id_foreign` '
            . 'FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE'
        );
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `payments` DROP FOREIGN KEY `payments_bank_account_id_foreign`');
        $this->forge->dropColumn('payments', 'bank_account_id');
    }
}
