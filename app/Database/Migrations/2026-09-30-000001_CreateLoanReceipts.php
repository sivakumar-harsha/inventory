<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.9.0AT (Loan Receipt / Disbursement): one row per actual amount the
 * company received against a sanctioned loan. Separate from loan_payments (money
 * going OUT to the lender as EMIs) — this is money coming IN from the lender.
 * bank_account_id is set only when the money arrived through a bank (a future
 * release posts it as a DEPOSIT, reference_type LOAN_DISBURSEMENT, reference_id
 * this row's id — never loan_id, so multiple receipts of one loan each get their
 * own bank row).
 *
 * A receipt never touches loans.outstanding_principal / total_principal_paid /
 * total_interest_paid — those remain driven solely by loan_payments.
 *
 * The foreign key on loan_id deliberately does not cascade, matching
 * loan_payments: a loan with receipts recorded against it should not silently
 * lose that history if the loan row is ever removed by a path that does not
 * already check for it.
 */
class CreateLoanReceipts extends Migration
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
            'receipt_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'receipt_date' => [
                'type' => 'DATE',
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
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
        $this->forge->addUniqueKey('receipt_no');
        $this->forge->addKey('loan_id');
        $this->forge->addKey('receipt_date');
        $this->forge->addKey('bank_account_id');
        $this->forge->addForeignKey('loan_id', 'loans', 'id');
        $this->forge->addForeignKey('bank_account_id', 'bank_accounts', 'id');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('loan_receipts');
    }

    public function down()
    {
        $this->forge->dropTable('loan_receipts');
    }
}
