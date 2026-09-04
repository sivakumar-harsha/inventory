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

    public function store()
    {
        $projectId   = (int) $this->request->getPost('project_id');
        $amount      = (float) $this->request->getPost('amount');
        $recDate     = $this->request->getPost('receipt_date');
        $method      = $this->request->getPost('payment_method');
        $reference   = $this->request->getPost('reference');
        $notes       = $this->request->getPost('notes');

        // Release 4.6.5: Direct Project Income — ADVANCE (default, existing
        // behavior) vs DIRECT_INCOME (recognized as revenue immediately,
        // since by definition no invoice will ever be raised for it). Any
        // unrecognized value falls back to ADVANCE rather than being trusted.
        $receiptType = $this->request->getPost('receipt_type');
        if (!in_array($receiptType, ['ADVANCE', 'DIRECT_INCOME'], true)) {
            $receiptType = 'ADVANCE';
        }

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
            'receipt_type'    => $receiptType,
            'reference'       => $reference,
            'notes'           => $notes,
        ]);

        return redirect()->to('/payments')->with('success', 'Project cash receipt recorded successfully.');
    }
}
