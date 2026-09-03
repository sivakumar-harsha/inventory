<?php

namespace App\Controllers;

use App\Models\ProjectCashReceiptModel;
use App\Models\ProjectModel;
use CodeIgniter\Controller;

/**
 * Release 4.5: Project Cash Receipt — money received directly against a
 * project before (or between) invoices exist. Independent of the Payments
 * module (payments.sale_id) and of projects.advance_amount. Simple CRUD,
 * no FIFO/allocation logic — see ProjectCashReceiptModel.
 */
class ProjectCashReceipts extends Controller
{
    protected $model;

    public function __construct()
    {
        $this->model = new ProjectCashReceiptModel();
    }

    public function index()
    {
        $db = \Config\Database::connect();
        $data['receipts'] = $db->query("
            SELECT r.*, p.name AS project_name, c.name AS customer_name
            FROM project_cash_receipts r
            LEFT JOIN projects p ON r.project_id = p.id
            LEFT JOIN customers c ON r.customer_id = c.id
            ORDER BY r.created_at DESC
        ")->getResultArray();

        return view('project_cash_receipts/index', $data);
    }

    public function create()
    {
        $db = \Config\Database::connect();

        // Same rule as Payments::create(): only ACTIVE projects accept new
        // cash receipts from this screen.
        $data['projects'] = $db->query("
            SELECT p.id, p.name, p.customer_id, c.name AS customer_name
            FROM projects p
            LEFT JOIN customers c ON p.customer_id = c.id
            WHERE p.status = 'ACTIVE'
            ORDER BY p.name ASC
        ")->getResultArray();

        return view('project_cash_receipts/create', $data);
    }

    public function store()
    {
        $projectId = (int) $this->request->getPost('project_id');
        $amount    = (float) $this->request->getPost('amount');
        $recDate   = $this->request->getPost('receipt_date');
        $method    = $this->request->getPost('payment_method');
        $reference = $this->request->getPost('reference');
        $notes     = $this->request->getPost('notes');

        $project = (new ProjectModel())->find($projectId);
        if (!$project) {
            return redirect()->back()->withInput()->with('error', 'Selected project not found.');
        }

        if ($amount <= 0) {
            return redirect()->back()->withInput()->with('error', 'Amount must be greater than zero.');
        }

        $this->model->insert([
            'project_id'      => $projectId,
            'customer_id'     => $project['customer_id'] ?: null,
            'receipt_no'      => $this->model->nextReceiptNo(),
            'amount'          => $amount,
            'receipt_date'    => $recDate,
            'payment_method'  => $method,
            'reference'       => $reference,
            'notes'           => $notes,
        ]);

        return redirect()->to('/project-cash-receipts')->with('success', 'Project cash receipt recorded successfully.');
    }

    public function edit($id)
    {
        $data['receipt'] = $this->model->find($id);
        if (!$data['receipt']) {
            return redirect()->to('/project-cash-receipts')->with('error', 'Project cash receipt not found.');
        }

        $db = \Config\Database::connect();
        $data['projects'] = $db->query("
            SELECT p.id, p.name, p.customer_id, c.name AS customer_name
            FROM projects p
            LEFT JOIN customers c ON p.customer_id = c.id
            WHERE p.status = 'ACTIVE'
            ORDER BY p.name ASC
        ")->getResultArray();

        // Keep the receipt's own project selectable even if it's since been
        // marked Completed (same pattern as Payments::edit()).
        $currentProjectId = (int) $data['receipt']['project_id'];
        $alreadyListed = array_filter($data['projects'], fn ($p) => (int) $p['id'] === $currentProjectId);
        if (!$alreadyListed) {
            $currentProject = $db->query("
                SELECT p.id, p.name, p.customer_id, c.name AS customer_name
                FROM projects p LEFT JOIN customers c ON p.customer_id = c.id
                WHERE p.id = ?
            ", [$currentProjectId])->getRowArray();
            if ($currentProject) {
                $data['projects'][] = $currentProject;
            }
        }

        return view('project_cash_receipts/edit', $data);
    }

    public function update($id)
    {
        $receipt = $this->model->find($id);
        if (!$receipt) {
            return redirect()->to('/project-cash-receipts')->with('error', 'Project cash receipt not found.');
        }

        $projectId = (int) $this->request->getPost('project_id');
        $amount    = (float) $this->request->getPost('amount');
        $recDate   = $this->request->getPost('receipt_date');
        $method    = $this->request->getPost('payment_method');
        $reference = $this->request->getPost('reference');
        $notes     = $this->request->getPost('notes');

        $project = (new ProjectModel())->find($projectId);
        if (!$project) {
            return redirect()->back()->withInput()->with('error', 'Selected project not found.');
        }

        if ($amount <= 0) {
            return redirect()->back()->withInput()->with('error', 'Amount must be greater than zero.');
        }

        $this->model->update($id, [
            'project_id'     => $projectId,
            'customer_id'    => $project['customer_id'] ?: null,
            'amount'         => $amount,
            'receipt_date'   => $recDate,
            'payment_method' => $method,
            'reference'      => $reference,
            'notes'          => $notes,
        ]);

        return redirect()->to('/project-cash-receipts')->with('success', 'Project cash receipt updated successfully.');
    }

    public function delete($id)
    {
        $this->model->delete($id);
        return redirect()->to('/project-cash-receipts')->with('success', 'Project cash receipt deleted successfully.');
    }
}
