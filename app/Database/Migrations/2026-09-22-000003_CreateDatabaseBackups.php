<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.8C (Backup & Restore Center): history of every mysqldump this
 * app has taken, plus a row per restore attempt.
 *
 * Field list follows the release spec (backup_name, backup_type, file_name,
 * file_size, total_tables, created_by, created_at, remarks) with three
 * deliberate, minimal additions beyond that list — called out here and in
 * the release report:
 *
 *  1. `status` ENUM(SUCCESS,FAILED) — the spec itself asks for this in
 *     Phase B, to record whether a backup/restore operation actually
 *     succeeded (a mysqldump/mysql shell-out can fail).
 *  2. `backup_type` extended to ('MANUAL','AUTO','RESTORE') instead of just
 *     ('MANUAL','AUTO') — MANUAL/AUTO rows describe an actual .sql file on
 *     disk (a real backup); RESTORE rows describe a *restore attempt*
 *     (no file of its own) so the Dashboard's "Last Restore Status" card and
 *     the Backup Center's restore history have something to query. This is
 *     exactly the "restore-result row" the spec's Phase B/C anticipates.
 *  3. `restore_of` (nullable INT, no hard FK) — on a RESTORE row, points at
 *     the id of the AUTO safety backup taken immediately before that
 *     restore (Safety Rule #4: "point to the pre-restore automatic safety
 *     backup for manual re-import"). Left without a foreign-key constraint
 *     since it is self-referencing (id -> id on the same table) and this
 *     column is a display/traceability aid only, not a data-integrity
 *     requirement.
 *
 * `file_name` is nullable because a RESTORE row (see above) has no backup
 * file of its own.
 *
 * No soft delete, no updated_at — history rows are never edited after
 * insert, matching audit_logs' insert-only convention from Release 4.8.8A.
 */
class CreateDatabaseBackups extends Migration
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
            'backup_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'backup_type' => [
                'type'       => 'ENUM',
                'constraint' => ['MANUAL', 'AUTO', 'RESTORE'],
                'default'    => 'MANUAL',
            ],
            'file_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'file_size' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'total_tables' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['SUCCESS', 'FAILED'],
                'null'       => true,
                'default'    => 'SUCCESS',
            ],
            'restore_of' => [
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
            'remarks' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('backup_type');
        $this->forge->addKey('created_at');
        $this->forge->addKey('created_by');
        $this->forge->addKey('restore_of');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');

        $this->forge->createTable('database_backups');
    }

    public function down()
    {
        $this->forge->dropTable('database_backups', true);
    }
}
