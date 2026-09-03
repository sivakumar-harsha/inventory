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
        $this->model->delete($id);
        return redirect()->to('/expense-categories')->with('success', 'Expense category deleted successfully.');
    }
}
