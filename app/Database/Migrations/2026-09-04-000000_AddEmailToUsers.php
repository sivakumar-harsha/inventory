<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.7.0: Forgot Password. Adds a nullable email column to users so
 * an account can be matched to an email address for OTP-based password
 * reset. Nullable and unique-when-set so existing rows are unaffected.
 */
class AddEmailToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
                'after'      => 'username',
            ],
        ]);
        $this->db->query('ALTER TABLE users ADD UNIQUE KEY users_email_unique (email)');
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'email');
    }
}
