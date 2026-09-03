<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProjectValueFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('projects', [
            'total_project_value' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'default'    => 0.00,
                'after'      => 'description',
            ],
            'advance_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'default'    => 0.00,
                'after'      => 'total_project_value',
            ],
            'advance_date' => [
                'type'   => 'DATE',
                'null'   => true,
                'after'  => 'advance_amount',
            ],
            'advance_notes' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'advance_date',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('projects', [
            'total_project_value',
            'advance_amount',
            'advance_date',
            'advance_notes',
        ]);
    }
}
