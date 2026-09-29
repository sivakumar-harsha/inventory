<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.4I (Bank Deposit / Withdrawal / Transfer / Daybook): the manual
 * bank ledger needs two things bank_transactions does not have yet.
 *
 * 1. A transfer between two accounts is two rows — TRANSFER_OUT on the source
 *    and TRANSFER_IN on the destination — so transaction_type is widened with
 *    those two values. 'TRANSFER' (the single-row form written by the 4.8.3B
 *    quick-entry page) stays in the list, so every existing row and that
 *    page keep working untouched.
 * 2. The manual entry forms carry a few descriptive fields that have no column
 *    yet: payment_mode (Deposit Type / Withdrawal Type / Transfer Method),
 *    party_name (Received From / Paid To) and category (Bank Daybook
 *    category). All three are nullable and only ever written by the new
 *    screens, so every existing posting path is unaffected whether or not this
 *    migration has run.
 */
class ExtendBankTransactionsForManualLedger extends Migration
{
    public function up()
    {
        $this->forge->addColumn('bank_transactions', [
            'payment_mode' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'reference_no',
            ],
            'party_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
                'after'      => 'payment_mode',
            ],
            'category' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'party_name',
            ],
        ]);

        $this->db->query("ALTER TABLE bank_transactions MODIFY transaction_type ENUM('DEPOSIT','WITHDRAWAL','TRANSFER','TRANSFER_OUT','TRANSFER_IN') NOT NULL");
    }

    public function down()
    {
        // Narrowing the ENUM would truncate or reject any transfer already
        // posted with the new values; refuse rather than lose ledger rows.
        $twoRowTransfers = $this->db->table('bank_transactions')
            ->whereIn('transaction_type', ['TRANSFER_OUT', 'TRANSFER_IN'])
            ->countAllResults();

        if ($twoRowTransfers > 0) {
            throw new \RuntimeException("Cannot roll back: {$twoRowTransfers} bank transfer row(s) use TRANSFER_OUT/TRANSFER_IN. Delete those transfers first.");
        }

        $this->db->query("ALTER TABLE bank_transactions MODIFY transaction_type ENUM('DEPOSIT','WITHDRAWAL','TRANSFER') NOT NULL");

        $this->forge->dropColumn('bank_transactions', ['payment_mode', 'party_name', 'category']);
    }
}
