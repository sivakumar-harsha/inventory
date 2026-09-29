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

    /**
     * Quick-add supplier via AJAX (used by Purchase Create/Edit modal).
     */
    public function ajaxStore()
    {
        $rules = [
            'name'   => 'required',
            'mobile' => 'required',
            'email'  => 'permit_empty|valid_email',
        ];

        $messages = [
            'name'   => ['required' => 'Supplier Name is required.'],
            'mobile' => ['required' => 'Mobile Number is required.'],
            'email'  => ['valid_email' => 'Enter a valid email address.'],
        ];

        if (!$this->validate($rules, $messages)) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => false,
                'errors' => $this->validator->getErrors(),
            ]);
        }

        try {
            $id = $this->model->insert([
                'name'    => $this->request->getPost('name'),
                'gst'     => $this->request->getPost('gst'),
                'phone'   => $this->request->getPost('mobile'),
                'email'   => $this->request->getPost('email'),
                'address' => $this->request->getPost('address'),
            ]);

            return $this->response->setJSON([
                'status'   => true,
                'message'  => 'Supplier created successfully.',
                'supplier' => [
                    'id'     => $id,
                    'name'   => $this->request->getPost('name'),
                    'phone'  => $this->request->getPost('mobile'),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => false,
                'message' => 'Unable to save supplier.',
            ]);
        }
    }
}
