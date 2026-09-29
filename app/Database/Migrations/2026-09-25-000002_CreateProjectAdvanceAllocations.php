<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.6A-2: record of a project advance being applied to a Sales Invoice by the
 * accountant. An allocation is a matching record only — no cash moves, so nothing here
 * touches bank_transactions, payments or customer payment vouchers.
 *
 * sales.advance_applied stays the figure every screen reads; it equals SUM(allocated_amount)
 * for an invoice that has allocation rows. No backfill: invoices that already carry an
 * advance_applied from the old automatic run (Release 1.6.4) are left exactly as they are.
 */
class CreateProjectAdvanceAllocations extends Migration
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
            'project_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'customer_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'sales_invoice_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'allocated_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'allocation_date' => [
                'type' => 'DATE',
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
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('project_id');
        $this->forge->addKey('sales_invoice_id');
        $this->forge->addKey('customer_id');
        $this->forge->addForeignKey('project_id', 'projects', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('sales_invoice_id', 'sales', 'id', '', 'CASCADE');
        $this->forge->createTable('project_advance_allocations');
    }

    public function down()
    {
        $this->forge->dropTable('project_advance_allocations');
    }
}
