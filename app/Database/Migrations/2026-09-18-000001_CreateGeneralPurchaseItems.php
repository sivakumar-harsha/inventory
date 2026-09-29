<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.1A (Phase A): General Purchase line items. Every line is 100%
 * warehouse stock at creation time — unlike `purchase_items`, there is no
 * project_qty/general_qty split here (that split belongs to Project
 * Purchase only). Stock posting to `stock_ledger` is not part of this
 * migration; only the document layer is created in this phase.
 */
class CreateGeneralPurchaseItems extends Migration
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
            'general_purchase_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'product_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'quantity' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,3',
            ],
            'unit' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'rate' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
            ],
            'gst_percent' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 0.00,
                'null'       => true,
            ],
            'gst_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
                'null'       => true,
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
        $this->forge->addKey('general_purchase_id');
        $this->forge->addKey('product_id');
        $this->forge->addForeignKey('general_purchase_id', 'general_purchases', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'products', 'id');
        $this->forge->createTable('general_purchase_items');
    }

    public function down()
    {
        $this->forge->dropTable('general_purchase_items');
    }
}
