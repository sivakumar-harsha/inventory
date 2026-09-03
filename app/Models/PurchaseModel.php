<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseModel extends Model
{
    protected $table      = 'purchases';
    protected $primaryKey = 'id';
    protected $allowedFields = [
		'supplier_id',
		'project_id',
		'purchase_date',
		'invoice_no',
		'total_amount',
		'notes'
	];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
