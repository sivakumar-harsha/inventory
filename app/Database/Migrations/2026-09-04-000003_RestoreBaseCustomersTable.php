<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Release 4.8.5D recovery (2 of 4): `customers` is a base table with no
 * migration of its own, and the Sep-18 restore stopped before reaching it.
 * The live projects / sales / project_cash_receipts rows still reference
 * customer ids 1-7 and 9, and the Customers module, service receipts and
 * customer payments all need the table.
 *
 * Recreates the table exactly as in the Sep-18 dump (same columns, utf8,
 * AUTO_INCREMENT=17 so ids 11-13, 15 and 16 stay unused) and restores its 11
 * rows from that dump. If a `customers` table already exists, this migration
 * touches nothing.
 */
class RestoreBaseCustomersTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('customers', false)) {
            return;
        }

        $this->db->query(
            'CREATE TABLE `customers` ('
            . '`id` int(10) unsigned NOT NULL AUTO_INCREMENT, '
            . '`name` varchar(200) NOT NULL, '
            . '`gst` varchar(100) NOT NULL, '
            . '`contact` varchar(100) DEFAULT NULL, '
            . '`phone` varchar(50) DEFAULT NULL, '
            . '`email` varchar(150) DEFAULT NULL, '
            . '`address` text DEFAULT NULL, '
            . '`created_at` datetime DEFAULT current_timestamp(), '
            . '`updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(), '
            . 'PRIMARY KEY (`id`)'
            . ') ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci'
        );

        $created = '2026-09-03 15:25:46';

        $this->db->table('customers')->insertBatch([
            ['id' => 1,  'name' => 'Shreeya Clinic',       'gst' => '33AACCS1234F1Z5', 'contact' => 'Dr. Shreeya Menon',   'phone' => '9876543210', 'email' => 'info@shreeyaclinic.com',       'address' => 'Anna Nagar, Chennai, Tamil Nadu',         'created_at' => $created, 'updated_at' => $created],
            ['id' => 2,  'name' => 'Sri Balaji Builders',  'gst' => '33AABCS5678G1Z2', 'contact' => 'S. Balaji',           'phone' => '9840011122', 'email' => 'admin@balajibuilders.com',     'address' => 'Race Course Road, Coimbatore, Tamil Nadu', 'created_at' => $created, 'updated_at' => $created],
            ['id' => 3,  'name' => 'RK Interiors',         'gst' => '33AAFCR4321H1Z9', 'contact' => 'R. Kumaresan',        'phone' => '9894123456', 'email' => 'rkinteriors@gmail.com',        'address' => 'Anna Nagar, Madurai, Tamil Nadu',         'created_at' => $created, 'updated_at' => $created],
            ['id' => 4,  'name' => 'Green Leaf Hospital',  'gst' => '33AAACG8765J1Z4', 'contact' => 'Dr. Green Leaf Admin', 'phone' => '9789012345', 'email' => 'accounts@greenleaf.com',       'address' => 'Fort Road, Salem, Tamil Nadu',            'created_at' => $created, 'updated_at' => $created],
            ['id' => 5,  'name' => 'Anand Dental Care',    'gst' => '33AABCA2468K1Z7', 'contact' => 'Dr. Anand Raj',       'phone' => '9884561234', 'email' => 'dental@anandcare.com',         'address' => 'Thillai Nagar, Trichy, Tamil Nadu',       'created_at' => $created, 'updated_at' => $created],
            ['id' => 6,  'name' => 'Harsha Medical Center', 'gst' => '33AACCH1357L1Z1', 'contact' => 'Dr. Harsha Vardhan', 'phone' => '9944123456', 'email' => 'info@harshamedical.com',       'address' => 'Perundurai Road, Erode, Tamil Nadu',      'created_at' => $created, 'updated_at' => $created],
            ['id' => 7,  'name' => 'Elite Diagnostics',    'gst' => '33AABCE9753M1Z6', 'contact' => 'V. Elumalai',         'phone' => '9952012345', 'email' => 'contact@elitediagnostics.com', 'address' => 'T Nagar, Chennai, Tamil Nadu',            'created_at' => $created, 'updated_at' => $created],
            ['id' => 8,  'name' => 'Vignesh Industries',   'gst' => '33AAFCV8642N1Z3', 'contact' => 'M. Vignesh',          'phone' => '9790011122', 'email' => 'purchase@vigneshind.com',      'address' => 'Avinashi Road, Tiruppur, Tamil Nadu',     'created_at' => $created, 'updated_at' => $created],
            ['id' => 9,  'name' => 'Lotus Residency',      'gst' => '33AABCL7531P1Z8', 'contact' => 'K. Lotus Prakash',    'phone' => '9843012345', 'email' => 'admin@lotusresidency.com',     'address' => 'Bagalur Road, Hosur, Tamil Nadu',         'created_at' => $created, 'updated_at' => $created],
            ['id' => 10, 'name' => 'Sun Tech Solutions',   'gst' => '29AAECS6420Q1Z0', 'contact' => 'R. Suryanarayan',     'phone' => '9870012233', 'email' => 'accounts@suntech.com',         'address' => 'Whitefield, Bengaluru, Karnataka',        'created_at' => $created, 'updated_at' => $created],
            ['id' => 14, 'name' => 'MARTS',                'gst' => '',                'contact' => null,                  'phone' => '9675645445', 'email' => '',                             'address' => '',                                        'created_at' => '2026-09-05 11:12:06', 'updated_at' => '2026-09-05 11:12:06'],
        ]);
    }

    /**
     * Deliberately a no-op: `customers` holds business data, and a rollback must
     * never drop a table (or the customer rows entered since) — same rule as the
     * rest of this recovery set.
     */
    public function down()
    {
    }
}
