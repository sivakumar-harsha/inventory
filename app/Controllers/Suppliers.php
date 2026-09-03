<?php

namespace App\Controllers;

use App\Models\SupplierModel;
use CodeIgniter\Controller;

class Suppliers extends Controller
{
    protected $model;

    public function __construct()
    {
        $this->model = new SupplierModel();
    }

    public function index()
    {
        $data['suppliers'] = $this->model->orderBy('id', 'DESC')->findAll();
        return view('suppliers/index', $data);
    }

    public function create()
    {
        return view('suppliers/create');
    }

    public function store()
    {
        $this->model->insert([
            'name'    => $this->request->getPost('name'),
            'gst' => $this->request->getPost('gst'),
            'phone'   => $this->request->getPost('phone'),
            'email'   => $this->request->getPost('email'),
            'address' => $this->request->getPost('address'),
        ]);
        return redirect()->to('/suppliers')->with('success', 'Supplier created successfully.');
    }

    public function edit($id)
    {
        $data['supplier'] = $this->model->find($id);
        if (!$data['supplier']) return redirect()->to('/suppliers')->with('error', 'Supplier not found.');
        return view('suppliers/edit', $data);
    }

    public function update($id)
    {
        $this->model->update($id, [
            'name'    => $this->request->getPost('name'),
            'gst' => $this->request->getPost('gst'),
            'phone'   => $this->request->getPost('phone'),
            'email'   => $this->request->getPost('email'),
            'address' => $this->request->getPost('address'),
        ]);
        return redirect()->to('/suppliers')->with('success', 'Supplier updated successfully.');
    }

    public function delete($id)
    {
        $this->model->delete($id);
        return redirect()->to('/suppliers')->with('success', 'Supplier deleted successfully.');
    }
}
