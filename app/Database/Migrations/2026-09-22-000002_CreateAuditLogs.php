<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.8A (Audit Log Foundation): one row per tracked action across
 * the app — a create/update/delete/status-change on a business record, or a
 * login/logout. Insert-only: nothing here is ever updated or soft-deleted,
 * so there is no updated_at/deleted_at and created_at is set manually by
 * AuditLogger at insert time rather than through CI4's auto-timestamps.
 *
 * user_id is nullable (a failed login attempt or a system-triggered action
 * may have none) and ON DELETE SET NULL, so removing a user never removes
 * their audit trail. reference_type/reference_id/reference_no point at the
 * record the action touched (e.g. EXPENSE / 42 / EXP-000042), the same
 * loose reference_type+id lookup convention already used for bank posting
 * (Release 4.8.6C) rather than a hard FK, since a reference may point at any
 * one of several tables. old_values/new_values hold JSON snapshots — only
 * the fields that actually changed for an UPDATE, the full row for a CREATE/
 * DELETE.
 */
class CreateAuditLogs extends Migration
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
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'module' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'action' => [
                'type'       => 'ENUM',
                'constraint' => ['CREATE', 'UPDATE', 'DELETE', 'STATUS_CHANGE', 'LOGIN', 'LOGOUT'],
            ],
            'reference_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
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
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'old_values' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'new_values' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'user_agent' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addKey('module');
        $this->forge->addKey('action');
        $this->forge->addKey('reference_type');
        $this->forge->addKey('reference_id');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'SET NULL');

        $this->forge->createTable('audit_logs');
    }

    public function down()
    {
        $this->forge->dropTable('audit_logs', true);
    }
}
