<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProjectBillingStatus extends Migration
{
    public function up()
    {
        $this->forge->addColumn('projects', [
            'billing_status' => [
                'type'       => 'ENUM',
                'constraint' => ['ACTIVE', 'COMPLETED'],
                'default'    => 'ACTIVE',
                'null'       => false,
                'after'      => 'advance_notes',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('projects', ['billing_status']);
    }
}
