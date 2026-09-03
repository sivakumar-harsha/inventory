<?php

namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\StockLedgerModel;
use CodeIgniter\Controller;

class Products extends Controller
{
    protected $model;
    protected $stockLedgerModel;

    public function __construct()
    {
        $this->model = new ProductModel();
        $this->stockLedgerModel = new StockLedgerModel();
    }

    public function index()
    {
        $data['products'] = $this->model->orderBy('id', 'DESC')->findAll();
        return view('products/index', $data);
    }

    public function create()
    {
        return view('products/create');
    }

    public function store()
	{
		$this->model->insert([
			'name'           => $this->request->getPost('name'),
			'description'    => $this->request->getPost('description'),
			'hsn_code'       => $this->request->getPost('hsn_code'),
			'gst_percent'    => $this->request->getPost('gst_percent'),
			'selling_price'  => $this->request->getPost('selling_price'),
			'unit'           => $this->request->getPost('unit'),
		]);

		if ($this->request->isAJAX()) {
			return $this->response->setJSON([
				'status'        => 'success',
				'id'            => $this->model->getInsertID(),
				'name'          => $this->request->getPost('name'),
				'selling_price' => $this->request->getPost('selling_price'),
				'gst_percent' => $this->request->getPost('gst_percent'),
				'unit'          => $this->request->getPost('unit')
			]);
		}

		return redirect()->to('/products')->with('success', 'Product created successfully.');
	}

    public function edit($id)
    {
        $data['product'] = $this->model->find($id);
        if (!$data['product']) return redirect()->to('/products')->with('error', 'Product not found.');
        return view('products/edit', $data);
    }

    public function update($id)
	{
		$this->model->update($id, [
			'name'           => $this->request->getPost('name'),
			'description'    => $this->request->getPost('description'),
			'hsn_code'       => $this->request->getPost('hsn_code'),
			'gst_percent'    => $this->request->getPost('gst_percent'),
			'selling_price'  => $this->request->getPost('selling_price'),
			'unit'           => $this->request->getPost('unit'),
		]);

		return redirect()->to('/products')->with('success', 'Product updated successfully.');
	}

    public function delete($id)
    {
        if ($this->stockLedgerModel->where('product_id', $id)->countAllResults() > 0) {
            return redirect()->to('/products')->with('error', 'This product has stock ledger history and cannot be deleted.');
        }

        $this->model->delete($id);
        return redirect()->to('/products')->with('success', 'Product deleted successfully.');
    }
}
