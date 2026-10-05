<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Loan Type master. Replaces the fixed loans.loan_type ENUM
 * (BANK, PERSONAL, VEHICLE, OD, OTHER) with a master table so new types can be
 * added from the New Loan page.
 *
 * - loan_types.code is the stable identifier stored in loans.loan_type. The five
 *   existing codes are seeded unchanged, so every existing loan row keeps its
 *   value and business rules that test a code (OD = overdraft) are unaffected.
 * - loan_types.label is the user-facing name (labels match the ones the Loan
 *   screens already showed).
 * - loans.loan_type becomes VARCHAR(30) NOT NULL DEFAULT 'BANK'. Converting an
 *   ENUM to VARCHAR keeps the stored strings; nothing is rewritten.
 *
 * down() refuses to run if any loan uses a type outside the original five,
 * because narrowing back to the ENUM would truncate or fail on that data.
 */
class CreateLoanTypes extends Migration
{
    private const ORIGINAL = ['BANK', 'PERSONAL', 'VEHICLE', 'OD', 'OTHER'];

    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['ACTIVE', 'INACTIVE'],
                'default'    => 'ACTIVE',
                'null'       => false,
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
        $this->forge->addUniqueKey('code');
        $this->forge->addUniqueKey('label');
        $this->forge->createTable('loan_types');

        $now = date('Y-m-d H:i:s');
        $this->db->table('loan_types')->insertBatch([
            ['code' => 'BANK',     'label' => 'Bank Loan', 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PERSONAL', 'label' => 'Personal',  'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'VEHICLE',  'label' => 'Vehicle',   'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'OD',       'label' => 'Overdraft', 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'OTHER',    'label' => 'Other',     'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->forge->modifyColumn('loans', [
            'loan_type' => [
                'name'       => 'loan_type',
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'BANK',
                'null'       => false,
            ],
        ]);
    }

    public function down()
    {
        $extra = $this->db->table('loans')->whereNotIn('loan_type', self::ORIGINAL)->countAllResults();
        if ($extra > 0) {
            throw new \RuntimeException('Cannot roll back: ' . $extra . ' loan(s) use a Loan Type that is not one of the original five.');
        }

        $this->forge->modifyColumn('loans', [
            'loan_type' => [
                'name'       => 'loan_type',
                'type'       => 'ENUM',
                'constraint' => self::ORIGINAL,
                'default'    => 'BANK',
                'null'       => false,
            ],
        ]);

        $this->forge->dropTable('loan_types');
    }
}
