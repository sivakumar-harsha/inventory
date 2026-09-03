<?php

namespace App\Controllers;

use App\Models\ExpenseCategoryModel;
use App\Models\ExpenseModel;
use App\Models\ProjectModel;
use CodeIgniter\Controller;

class Expenses extends Controller
{
    protected $model;

    public function __construct()
    {
        $this->model = new ExpenseModel();
    }

    public function index()
    {
        $db = \Config\Database::connect();
        $data['expenses'] = $db->query("
            SELECT e.*, p.name AS project_name
            FROM expenses e
            LEFT JOIN projects p ON e.project_id = p.id
            ORDER BY e.expense_date DESC
        ")->getResultArray();
        return view('expenses/index', $data);
    }

    public function create()
    {
        // Only non-COMPLETED projects allowed for new expenses
        $data['projects'] = (new ProjectModel())
            ->where('status !=', 'COMPLETED')
            ->orderBy('name', 'ASC')
            ->findAll();
        $data['categories'] = (new ExpenseCategoryModel())->getActive();
        return view('expenses/create', $data);
    }

    public function store()
    {
        $projectId = $this->request->getPost('project_id');
		$projectId = ($projectId == '0') ? null : $projectId;

        // Server-side: reject if project is COMPLETED
        if ($projectId) {
            $project = (new ProjectModel())->find($projectId);
            if ($project && $project['status'] === 'COMPLETED') {
                return redirect()->back()
                    ->with('error', 'Cannot add expenses to a completed project.');
            }
        }

        $this->model->insert([
            'project_id'   => $projectId,
            'category'     => $this->request->getPost('category'),
            'description'  => $this->request->getPost('description'),
            'amount'       => $this->request->getPost('amount'),
            'expense_date' => $this->request->getPost('expense_date'),
        ]);
        return redirect()->to('/expenses')->with('success', 'Expense recorded successfully.');
    }

    public function edit($id)
    {
        $data['expense'] = $this->model->find($id);
        if (!$data['expense']) return redirect()->to('/expenses')->with('error', 'Expense not found.');

        // Only non-COMPLETED projects in dropdown, but always include the current project
        $allActive = (new ProjectModel())
            ->where('status !=', 'COMPLETED')
            ->orderBy('name', 'ASC')
            ->findAll();

        // If the expense belongs to a COMPLETED project, include it so the form is valid
        $currentProjectId = $data['expense']['project_id'];
        $inList = false;
        foreach ($allActive as $p) {
            if ($p['id'] == $currentProjectId) { $inList = true; break; }
        }
        if (!$inList && $currentProjectId) {
            $currentProject = (new ProjectModel())->find($currentProjectId);
            if ($currentProject) {
                array_unshift($allActive, $currentProject);
            }
        }

        $data['projects'] = $allActive;

        // Only ACTIVE categories in dropdown, but always include the expense's
        // current category so the form stays valid even if it was later
        // deactivated or is a legacy value with no matching master row.
        $categoryModel   = new ExpenseCategoryModel();
        $activeCategories = $categoryModel->getActive();
        $currentCategory  = $data['expense']['category'];
        $hasCurrent = false;
        foreach ($activeCategories as $c) {
            if ($c['category_name'] === $currentCategory) { $hasCurrent = true; break; }
        }
        if (!$hasCurrent && $currentCategory) {
            array_unshift($activeCategories, ['id' => 0, 'category_name' => $currentCategory, 'status' => 'ACTIVE']);
        }
        $data['categories'] = $activeCategories;

        return view('expenses/edit', $data);
    }

    public function update($id)
    {
        $projectId = $this->request->getPost('project_id');
		$projectId = ($projectId == '0') ? null : $projectId;

        // Server-side: reject if target project is COMPLETED
        if ($projectId) {
            $project = (new ProjectModel())->find($projectId);
            if ($project && $project['status'] === 'COMPLETED') {
                return redirect()->back()
                    ->with('error', 'Cannot assign expenses to a completed project.');
            }
        }

        $this->model->update($id, [
            'project_id'   => $projectId,
            'category'     => $this->request->getPost('category'),
            'description'  => $this->request->getPost('description'),
            'amount'       => $this->request->getPost('amount'),
            'expense_date' => $this->request->getPost('expense_date'),
        ]);
        return redirect()->to('/expenses')->with('success', 'Expense updated successfully.');
    }

    public function delete($id)
    {
        $this->model->delete($id);
        return redirect()->to('/expenses')->with('success', 'Expense deleted successfully.');
    }
}
