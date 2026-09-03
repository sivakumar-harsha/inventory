<?php

namespace App\Models;

use CodeIgniter\Model;

class ExpenseCategoryModel extends Model
{
    protected $table      = 'expense_categories';
    protected $primaryKey = 'id';
    protected $allowedFields = ['category_name', 'status'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getActive()
    {
        return $this->where('status', 'ACTIVE')->orderBy('category_name', 'ASC')->findAll();
    }
}
