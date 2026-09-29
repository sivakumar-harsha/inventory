<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Release 4.8.4A: Service Received line items (free-text service rows).
 */
class ServiceReceiptItemModel extends Model
{
    protected $table      = 'service_receipt_items';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'service_receipt_id',
        'description',
        'qty',
        'rate',
        'gst_percent',
        'gst_amount',
        'line_total',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
