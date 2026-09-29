<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.4A (Phase A): Service Received line items. Free-text
 * description lines (no product master, no stock effect — services are not
 * inventory). line_total is the line amount INCLUDING GST, matching
 * general_purchase_items.line_total.
 */
class CreateServiceReceiptItems extends Migration
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
            'service_receipt_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
            ],
            'qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,3',
            ],
            'rate' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
            ],
            'gst_percent' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 0.00,
            ],
            'gst_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'line_total' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
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
        $this->forge->addKey('service_receipt_id');
        $this->forge->addForeignKey('service_receipt_id', 'service_receipts', 'id', '', 'CASCADE');
        $this->forge->createTable('service_receipt_items');
    }

    public function down()
    {
        $this->forge->dropTable('service_receipt_items');
    }
}
