<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.4J: a Project's Advance Amount is the customer's first advance
 * payment, so the project row also records HOW it was received —
 * advance_payment_method (CASH / BANK_TRANSFER / CHEQUE / UPI / OTHER) and,
 * for the bank methods, advance_bank_account_id (the account the automatic
 * DEPOSIT is posted to). No new table: the project row itself is the advance
 * entry, exactly as the General Purchase row is the supplier advance
 * (Release 4.8.4H).
 *
 * Both columns are nullable, so every existing project stays valid untouched
 * (legacy advances carry no method until the project is next edited).
 * RESTRICT, same as project_cash_receipts.bank_account_id, so an account that
 * received a project advance can't be removed.
 */
class AddAdvancePaymentToProjects extends Migration
{
    public function up()
    {
        $this->forge->addColumn('projects', [
            'advance_payment_method' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'default'    => null,
                'after'      => 'advance_notes',
            ],
            'advance_bank_account_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'after'      => 'advance_payment_method',
            ],
        ]);

        $this->db->query('ALTER TABLE `projects` ADD KEY `advance_bank_account_id` (`advance_bank_account_id`)');
        $this->db->query(
            'ALTER TABLE `projects` ADD CONSTRAINT `projects_advance_bank_account_id_foreign` '
            . 'FOREIGN KEY (`advance_bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE'
        );
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `projects` DROP FOREIGN KEY `projects_advance_bank_account_id_foreign`');
        $this->forge->dropColumn('projects', ['advance_bank_account_id', 'advance_payment_method']);
    }
}
