<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.3D: records which bank account a Project Cash Receipt was
 * deposited into. Nullable so every existing receipt (all cash, or bank
 * receipts saved before this release) stays valid untouched; the controller
 * requires it only for bank payment methods. RESTRICT, same as
 * bank_transactions, so an account referenced by a receipt can't be removed.
 */
class AddBankAccountToProjectCashReceipts extends Migration
{
    public function up()
    {
        $this->forge->addColumn('project_cash_receipts', [
            'bank_account_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'after'      => 'receipt_type',
            ],
        ]);

        $this->db->query('ALTER TABLE `project_cash_receipts` ADD KEY `bank_account_id` (`bank_account_id`)');
        $this->db->query(
            'ALTER TABLE `project_cash_receipts` ADD CONSTRAINT `project_cash_receipts_bank_account_id_foreign` '
            . 'FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE'
        );
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `project_cash_receipts` DROP FOREIGN KEY `project_cash_receipts_bank_account_id_foreign`');
        $this->forge->dropColumn('project_cash_receipts', 'bank_account_id');
    }
}
