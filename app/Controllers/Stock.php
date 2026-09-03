<?php

namespace App\Controllers;

use App\Models\ProductModel;
use CodeIgniter\Controller;

class Stock extends Controller
{
	public function index()
	{
		$db = \Config\Database::connect();
		$data['stocks'] = $db->query("
			SELECT 
				MAX(sl.id) as id,
				p.id as product_id,
				p.name AS product_name,
				p.unit,
				SUM(CASE WHEN sl.transaction_type='IN' THEN sl.quantity ELSE 0 END) -
				SUM(CASE WHEN sl.transaction_type='OUT' THEN sl.quantity ELSE 0 END) 
				AS current_stock
			FROM products p
			LEFT JOIN stock_ledger sl 
				ON sl.product_id = p.id 
				AND sl.source = 'GENERAL'
			GROUP BY p.id
			HAVING current_stock > 0
			ORDER BY p.id DESC
		")->getResultArray();
		
		

		return view('stock/index', $data);
	}

	public function view($id)
	{
		$db = \Config\Database::connect();
		$data['stock'] = $db->query("
			SELECT sl.*, p.name AS product_name, p.unit
			FROM stock_ledger sl
			LEFT JOIN products p ON sl.product_id = p.id
			WHERE p.id = ?
			AND sl.source = 'GENERAL'
			LIMIT 1
		", [$id])->getRowArray();

		if (!$data['stock']) {
			return redirect()->to('/stock-entry')->with('error', 'Stock not found');
		}

		return view('stock/view', $data);
	}
	
    public function create()
    {
        $data['products'] = (new ProductModel())->orderBy('name','ASC')->findAll();
        return view('stock/create', $data);
    }

    public function store()
    {
        $db = \Config\Database::connect();
        $db->transStart();

        $dataInsert = [
            'product_id'       => $this->request->getPost('product_id'),
            'transaction_type' => 'IN',
            'quantity'         => $this->request->getPost('quantity'),
            'source'           => 'GENERAL',
            'project_id'       => null,
            'reference_type'   => 'MANUAL',
            'reference_id'     => 0,
            'transaction_date' => $this->request->getPost('date') ?: date('Y-m-d'),
            'notes'            => $this->request->getPost('notes'),
        ];
		
		$result = $db->table('stock_ledger')->insert($dataInsert);

		if (!$result) {
			dd($db->error(), $dataInsert);
		}

        $db->transComplete();

		if ($db->transStatus() === false) {
			$error = $db->error();
			return redirect()->back()->with('error', $error['message']);
		}

        return redirect()->to('/stock-entry')->with('success','Stock added successfully');
    }
	
	public function edit($id)
	{
		$db = \Config\Database::connect();

		$data['stock'] = $db->query("
			SELECT sl.*, p.name AS product_name
			FROM stock_ledger sl
			LEFT JOIN products p ON sl.product_id = p.id
			WHERE sl.id = ?
			AND sl.source = 'GENERAL'
		", [$id])->getRowArray();

		if (!$data['stock']) {
			return redirect()->to('/stock-entry')->with('error', 'Stock not found or not editable');
		}

		$data['products'] = (new \App\Models\ProductModel())->orderBy('name','ASC')->findAll();

		return view('stock/edit', $data);
	}
	
	public function update($id)
	{
		$db = \Config\Database::connect();
		$db->transStart();

		$stock = $db->query("SELECT * FROM stock_ledger WHERE id=?", [$id])->getRowArray();

		if (!$stock) {
			return redirect()->to('/stock-entry')->with('error', 'Stock not found');
		}

		$db->table('stock_ledger')->where('id', $id)->update([
			'product_id'       => $this->request->getPost('product_id'),
			'quantity'         => $this->request->getPost('quantity'),
			'transaction_date' => $this->request->getPost('date'),
			'notes'            => $this->request->getPost('notes'),
		]);

		$db->transComplete();

		return redirect()->to('/stock-entry')->with('success','Stock updated successfully');
	}
	
}