<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddGstApplicableFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('sale_items', [
            'gst_applicable' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 1,
                'after'      => 'gst_percent',
            ],
        ]);

        $this->forge->addColumn('purchase_items', [
            'gst_applicable' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 1,
                'after'      => 'gst_percent',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('sale_items', ['gst_applicable']);
        $this->forge->dropColumn('purchase_items', ['gst_applicable']);
    }
}
