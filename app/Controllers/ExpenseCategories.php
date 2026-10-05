<?php

namespace App\Controllers;

use App\Models\ExpenseCategoryModel;
use CodeIgniter\Controller;

class ExpenseCategories extends Controller
{
    protected $model;

    public function __construct()
    {
        $this->model = new ExpenseCategoryModel();
    }

    public function index()
    {
        $data['categories'] = $this->model->orderBy('id', 'DESC')->findAll();
        return view('expense_categories/index', $data);
    }

    public function create()
    {
        return view('expense_categories/create');
    }

    public function store()
    {
        $name = trim((string) $this->request->getPost('category_name'));

        if ($name === '') {
            return redirect()->back()->withInput()->with('error', 'Category name is required.');
        }

        if ($this->model->where('category_name', $name)->first()) {
            return redirect()->back()->withInput()->with('error', 'This category already exists.');
        }

        $this->model->insert([
            'category_name' => $name,
            'status'        => $this->request->getPost('status') === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE',
        ]);
        return redirect()->to('/expense-categories')->with('success', 'Expense category created successfully.');
    }

    /**
     * Quick-add from the Add Expense page (JSON). Same rules as store(): name required, no duplicate,
     * new category ACTIVE. Same auth gate as the rest of this master (there is no finer-grained permission system).
     */
    public function quickStore()
    {
        $name = trim((string) $this->request->getPost('category_name'));

        if ($name === '') {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'errors' => ['Category name is required.']]);
        }

        if ($this->model->where('category_name', $name)->first()) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'errors' => ['This category already exists.']]);
        }

        $id = $this->model->insert(['category_name' => $name, 'status' => 'ACTIVE']);

        return $this->response->setJSON(['status' => true, 'category' => ['id' => (int) $id, 'category_name' => $name]]);
    }

    public function edit($id)
    {
        $data['category'] = $this->model->find($id);
        if (!$data['category']) return redirect()->to('/expense-categories')->with('error', 'Expense category not found.');
        return view('expense_categories/edit', $data);
    }

    public function update($id)
    {
        $name = trim((string) $this->request->getPost('category_name'));

        if ($name === '') {
            return redirect()->back()->withInput()->with('error', 'Category name is required.');
        }

        $existing = $this->model->where('category_name', $name)->first();
        if ($existing && (int) $existing['id'] !== (int) $id) {
            return redirect()->back()->withInput()->with('error', 'This category already exists.');
        }

        $this->model->update($id, [
            'category_name' => $name,
            'status'        => $this->request->getPost('status') === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE',
        ]);
        return redirect()->to('/expense-categories')->with('success', 'Expense category updated successfully.');
    }

    public function delete($id)
    {
        // Release 4.8.6A added expenses.category_id -> expense_categories.id
        // (RESTRICT). Deleting a category still referenced by an expense now
        // fails at the DB layer; catch that and show a friendly message
        // instead of an uncaught database error.
        try {
            $this->model->delete($id);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return redirect()->to('/expense-categories')->with('error', 'This category is used by one or more expenses and cannot be deleted.');
        }
        return redirect()->to('/expense-categories')->with('success', 'Expense category deleted successfully.');
    }
}
