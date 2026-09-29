<?php

namespace App\Models;

use CodeIgniter\Model;

class GeneralPurchaseItemModel extends Model
{
    protected $table      = 'general_purchase_items';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'general_purchase_id',
        'product_id',
        'quantity',
        'unit',
        'rate',
        'gst_percent',
        'gst_amount',
        'line_total',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
