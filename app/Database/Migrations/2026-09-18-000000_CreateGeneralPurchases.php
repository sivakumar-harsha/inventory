<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.1A (Phase A): General Purchase header table — the warehouse
 * purchase document. Deliberately independent of `purchases`/`purchase_items`
 * (Project Purchase) so nothing here can affect that module's edit/delete
 * consumed-stock guard or project-cost timeline. No project_id column: a
 * General Purchase is never linked to a project at creation time. Stock
 * posting itself continues to live in the existing `stock_ledger` table
 * (extended separately) — this migration only creates the document layer.
 */
class CreateGeneralPurchases extends Migration
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
            'purchase_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'supplier_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'purchase_date' => [
                'type' => 'DATE',
            ],
            'bill_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'bill_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'subtotal' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'gst_total' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'grand_total' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'advance_paid' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'outstanding_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'payment_status' => [
                'type'       => 'ENUM',
                'constraint' => ['Pending', 'Partial', 'Paid'],
                'default'    => 'Pending',
                'null'       => false,
            ],
            'payment_method' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            // Nullable, no FK yet: no bank_accounts table exists in this
            // codebase today. Reserved column for a future bank-accounts
            // module; kept as a plain unsigned int with no constraint so
            // this migration never depends on a table that doesn't exist.
            'bank_account_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
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
        $this->forge->addUniqueKey('purchase_no');
        $this->forge->addKey('supplier_id');
        $this->forge->addKey('payment_status');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('general_purchases');
    }

    public function down()
    {
        $this->forge->dropTable('general_purchases');
    }
}
