<?php

namespace App\Controllers;

use App\Models\CustomerModel;
use CodeIgniter\Controller;

class Customers extends Controller
{
    protected $model;

    public function __construct()
    {
        $this->model = new CustomerModel();
    }

    public function index()
    {
        $data['customers'] = $this->model->orderBy('id', 'DESC')->findAll();
        return view('customers/index', $data);
    }

    public function create()
    {
        return view('customers/create');
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
        return redirect()->to('/customers')->with('success', 'Customer created successfully.');
    }

    public function edit($id)
    {
        $data['customer'] = $this->model->find($id);
        if (!$data['customer']) return redirect()->to('/customers')->with('error', 'Customer not found.');
        return view('customers/edit', $data);
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
        return redirect()->to('/customers')->with('success', 'Customer updated successfully.');
    }

    public function delete($id)
    {
        $this->model->delete($id);
        return redirect()->to('/customers')->with('success', 'Customer deleted successfully.');
    }
}
