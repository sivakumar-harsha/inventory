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

    /**
     * Quick-add customer via AJAX (used by Project Create/Edit modal).
     */
    public function ajaxStore()
    {
        $rules = [
            'name'   => 'required',
            'mobile' => 'required',
            'email'  => 'permit_empty|valid_email',
        ];

        $messages = [
            'name'   => ['required' => 'Customer Name is required.'],
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
                'message'  => 'Customer created successfully.',
                'customer' => [
                    'id'     => $id,
                    'name'   => $this->request->getPost('name'),
                    'mobile' => $this->request->getPost('mobile'),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => false,
                'message' => 'Unable to save customer.',
            ]);
        }
    }
}
