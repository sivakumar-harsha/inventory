<?php

namespace App\Controllers;

use App\Models\ProjectModel;
use App\Models\CustomerModel;
use App\Models\SaleModel;
use App\Models\ExpenseModel;
use App\Models\StockLedgerModel;
use CodeIgniter\Controller;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Dompdf\Dompdf;
use Dompdf\Options;

class Projects extends Controller
{
    protected $model;

    public function __construct()
    {
        $this->model = new ProjectModel();
    }

    public function index()
    {
        $db  = \Config\Database::connect();
        $tab = $this->request->getGet('tab') ?? 'active';

        // Correlated subqueries for payment totals avoid cross-join aggregation errors
        //
        // Release 2.1C (Bug 1): payment_status is now derived from the same
        // whole-contract rule as ProjectModel::getPaymentStatus() (Contract
        // Value / Remaining To Bill / Customer Pending via getFinancialSummary()),
        // not invoice-level sales.status counts — sale_count is kept only to
        // detect the "no invoices yet" case.
        $selectCols = "
            SELECT p.*, c.name AS customer_name,
                COALESCE(SUM(DISTINCT s.total_amount), 0) AS total_sales,
                COALESCE(SUM(DISTINCT e.amount),       0) AS total_expenses,
                (SELECT COUNT(*) FROM sales WHERE project_id = p.id) AS sale_count
            FROM projects p
            LEFT JOIN customers c ON p.customer_id = c.id
            LEFT JOIN sales     s ON s.project_id  = p.id
            LEFT JOIN expenses  e ON e.project_id  = p.id
        ";

        if ($tab === 'completed') {
            $projects = $db->query(
                $selectCols . " WHERE p.status = 'COMPLETED' GROUP BY p.id ORDER BY p.updated_at DESC"
            )->getResultArray();
        } else {
            $projects = $db->query(
                $selectCols . " WHERE p.status != 'COMPLETED' GROUP BY p.id ORDER BY p.created_at DESC"
            )->getResultArray();
        }

        // Release 1.7A (UI only): Contract Value / Customer Pending columns,
        // reusing ProjectModel::getFinancialSummary() (unchanged) as the
        // single source of truth for these figures.
        //
        // Release 2.1C (Bug 1): payment_status reuses this same $fs call —
        // same whole-contract rule as ProjectModel::getPaymentStatus(), no
        // second/duplicate calculation.
        foreach ($projects as &$p) {
            $fs = $this->model->getFinancialSummary((int) $p['id']);
            $p['contract_value']   = $fs['total_project_value'] ?? 0;
            $p['customer_pending'] = $fs['outstanding_collection_balance'] ?? 0;

            if ((int) $p['sale_count'] === 0) {
                $p['payment_status'] = 'PENDING';
            } elseif (($fs['remaining_billable_value'] ?? 0) > 0.004) {
                $p['payment_status'] = 'PARTIAL';
            } elseif (($fs['outstanding_collection_balance'] ?? 0) > 0.004) {
                $p['payment_status'] = 'PARTIAL';
            } else {
                $p['payment_status'] = 'PAID';
            }

            // Release 2.3A (final): Billing Completion Status is independent of
            // Customer Pending / payment_status above — it never auto-derives
            // to COMPLETED, only markBillingComplete() (user action) sets it.
            $p['billing_completion_status'] = $this->model->getBillingCompletionStatus($p, $fs);

            // Release 2.3F: same remaining_billable_value used by Project View's
            // Remaining Balance figure — no customer_pending / outstanding_collection_balance
            // / payment_status involved in this column.
            $p['remaining_balance'] = max(0, $fs['remaining_billable_value'] ?? 0);

            if ($p['billing_completion_status'] === 'COMPLETED') {
                $p['remaining_balance'] = 0;
            }
        }
        unset($p);

        $data['projects'] = $projects;
        $data['tab']      = $tab;
        return view('projects/index', $data);
    }

    public function create()
    {
        $data['customers'] = (new CustomerModel())->orderBy('name','ASC')->findAll();
        return view('projects/create', $data);
    }

    public function store()
    {
        $this->model->insert([
            'name'                 => $this->request->getPost('name'),
            'customer_id'          => $this->request->getPost('customer_id') ?: null,
            'status'               => $this->request->getPost('status'),
            'start_date'           => $this->request->getPost('start_date') ?: null,
            'end_date'             => $this->request->getPost('end_date') ?: null,
            'description'          => $this->request->getPost('description'),
            'total_project_value'  => $this->request->getPost('total_project_value') ?: 0,
            'advance_amount'       => $this->request->getPost('advance_amount') ?: 0,
            'advance_date'         => $this->request->getPost('advance_date') ?: null,
            'advance_notes'        => $this->request->getPost('advance_notes'),
        ]);
        return redirect()->to('/projects')->with('success', 'Project created successfully.');
    }

    public function edit($id)
    {
        $data['project']   = $this->model->find($id);
        $data['customers'] = (new CustomerModel())->orderBy('name','ASC')->findAll();
        if (!$data['project']) return redirect()->to('/projects')->with('error', 'Project not found.');
        return view('projects/edit', $data);
    }

    public function update($id)
    {
        $status  = $this->request->getPost('status');
        $endDate = $this->request->getPost('end_date') ?: null;

        // If the project is being marked COMPLETED and no end date was given, default it
        // to today — projects normally only get a start date while ACTIVE.
        if ($status === 'COMPLETED' && !$endDate) {
            $endDate = date('Y-m-d');
        }

        $this->model->update($id, [
            'name'                 => $this->request->getPost('name'),
            'customer_id'          => $this->request->getPost('customer_id') ?: null,
            'status'               => $status,
            'start_date'           => $this->request->getPost('start_date') ?: null,
            'end_date'             => $endDate,
            'description'          => $this->request->getPost('description'),
            'total_project_value'  => $this->request->getPost('total_project_value') ?: 0,
            'advance_amount'       => $this->request->getPost('advance_amount') ?: 0,
            'advance_date'         => $this->request->getPost('advance_date') ?: null,
            'advance_notes'        => $this->request->getPost('advance_notes'),
        ]);

        // Release 1.6.4 (Rules 1-3): advance_amount may have changed —
        // re-run FIFO allocation across this project's invoices.
        (new SaleModel())->recalculateProjectBilling((int) $id);

        return redirect()->to('/projects')->with('success', 'Project updated successfully.');
    }

    public function delete($id)
    {
        $this->model->delete($id);
        return redirect()->to('/projects')->with('success', 'Project deleted successfully.');
    }

    /**
     * Quick status update from the Projects index (Work Status dropdown),
     * so marking a project completed doesn't require opening Edit.
     */
    public function updateStatus($id)
    {
        $status = $this->request->getPost('status');
        $valid  = ['ACTIVE', 'ON_HOLD', 'COMPLETED'];

        if (!in_array($status, $valid, true)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Invalid status.',
            ]);
        }

        $project = $this->model->find($id);
        if (!$project) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Project not found.',
            ]);
        }

        $update = ['status' => $status];

        // Same auto-fill as the full Edit form: completing a project without an end
        // date yet stamps it with today's date.
        if ($status === 'COMPLETED' && empty($project['end_date'])) {
            $update['end_date'] = date('Y-m-d');
        }

        $this->model->update($id, $update);

        return $this->response->setJSON([
            'success'  => true,
            'status'   => $status,
            'end_date' => $update['end_date'] ?? $project['end_date'],
        ]);
    }

    /**
     * Release 2.3A: manual, one-way "Mark Billing Complete" action. Only
     * flips projects.billing_status — no FIFO/advance/invoice/payment figure
     * is touched.
     */
    public function markBillingComplete($id)
    {
        $project = $this->model->find($id);
        if (!$project) {
            return redirect()->to('/projects')->with('error', 'Project not found.');
        }

        $this->model->markBillingComplete((int) $id);

        return redirect()->to('/projects/view/' . $id)->with('success', 'Project marked as Billing Completed.');
    }

    public function view($id)
    {
        $db = \Config\Database::connect();
        $data['project'] = $db->query("
            SELECT p.*, c.name AS customer_name
            FROM projects p
            LEFT JOIN customers c ON p.customer_id = c.id
            WHERE p.id = ?
        ", [$id])->getRowArray();

        if (!$data['project']) return redirect()->to('/projects')->with('error', 'Project not found.');

        // Calculate payment status dynamically
        $data['payment_status'] = $this->model->getPaymentStatus((int)$id);

        // Project Financial Summary (Total Project Value, Advance, Billing, Collection)
        $data['financial_summary'] = $this->model->getFinancialSummary((int)$id);

        // Release 2.3A: Project Billing Status — separate concept from
        // Customer Pending Collection above; ACTIVE/PARTIAL/COMPLETED.
        $data['billing_completion_status'] = $this->model->getBillingCompletionStatus($data['project'], $data['financial_summary']);

        $data['sales'] = $db->query("
            SELECT s.*, c.name AS customer_name FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.project_id = ? ORDER BY s.sale_date DESC
        ", [$id])->getResultArray();

        $data['purchases'] = $db->query("
            SELECT pu.*, su.name AS supplier_name FROM purchases pu
            LEFT JOIN suppliers su ON pu.supplier_id = su.id
            WHERE pu.project_id = ? ORDER BY pu.purchase_date DESC
        ", [$id])->getResultArray();

        $data['expenses'] = $db->query("
            SELECT * FROM expenses WHERE project_id = ? ORDER BY expense_date DESC
        ", [$id])->getResultArray();

        // Release 2.0B (UI only): payment count for the Project Progress Summary
        // strip — reads the existing payments/sales relationship, no new
        // business logic or calculation.
        $data['total_payments_count'] = (int) ($db->query("
            SELECT COUNT(*) AS c FROM payments py
            INNER JOIN sales s ON py.sale_id = s.id
            WHERE s.project_id = ?
        ", [$id])->getRow()->c ?? 0);

        // Release 1.6B (Approved Design Decision 4/5): project purchase cost is
        // allocation-aware — only the project_qty portion of each purchase line
        // counts, not the full supplier invoice total. Individual rows in the
        // Purchases table below still show each purchase's invoice total
        // (Decision 4: "Purchase header total remains unchanged").
        $data['total_sales']     = array_sum(array_column($data['sales'], 'total_amount'));
        $data['total_purchases'] = $this->model->getAllocatedPurchaseCost((int) $id);
        $data['total_expenses']  = array_sum(array_column($data['expenses'], 'amount'));
        $data['net_profit']      = $data['total_sales'] - $data['total_purchases'] - $data['total_expenses'];

        // Release 2.1B (Phase 3/4, UI only): reuses the existing timeline event
        // source (already powers the Statement page) for the new Customer
        // Payment History and Recent Project Activity panels on this page.
        // No new query, no new model method.
        $data['timeline'] = $this->model->getTimelineEvents((int) $id);

        return view('projects/view', $data);
    }

    /**
     * Release 1.6.6 (UI only): simple project list, filtered/sorted for quick
     * access to each project's statement. Reuses the existing statement()
     * route/view per-project — no new calculation logic.
     */
    public function statementsList()
    {
        $db = \Config\Database::connect();
        $data['projects'] = $db->query("
            SELECT p.id, p.name, p.status, c.name AS customer_name
            FROM projects p
            LEFT JOIN customers c ON p.customer_id = c.id
            ORDER BY p.created_at DESC
        ")->getResultArray();

        return view('projects/statements_list', $data);
    }

    public function statement($id)
    {
        $data = $this->_buildStatementData((int) $id);
        if ($data === null) {
            return redirect()->to('/projects')->with('error', 'Project not found.');
        }
        return view('projects/statement', $data);
    }

    public function statementExport($id)
    {
        $data = $this->_buildStatementData((int) $id);
        if ($data === null) {
            return redirect()->to('/projects')->with('error', 'Project not found.');
        }

        $project  = $data['project'];
        $fs       = $data['financial_summary'];
        $timeline = $data['timeline'];

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->freezePane('A20');

        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', 'PROJECT STATEMENT');

        $sheet->mergeCells('A2:G2');
        $sheet->setCellValue('A2', 'Generated On: ' . date('d-m-Y h:i A'));

        $sheet->mergeCells('A3:G3');
        $sheet->setCellValue('A3', 'Project: ' . $project['name'] . ' (' . ($project['customer_name'] ?: 'No Customer') . ')');

        $sheet->getStyle('A1:G3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2F7E8A');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ===== FINANCIAL SUMMARY =====
        $sheet->setCellValue('A5', 'FINANCIAL SUMMARY');
        $sheet->mergeCells('A5:G5');
        $sheet->getStyle('A5')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('3C5A82');

        $summaryRows = [
            ['Project Value', $fs['total_project_value'] ?? 0],
            ['Advance Received', $fs['advance_amount'] ?? 0],
            ['Total Billed', $fs['total_billed'] ?? 0],
            ['Remaining Billable Value', $fs['remaining_billable_value'] ?? 0],
            ['Total Payments Received', $fs['total_paid'] ?? 0],
            ['Outstanding Collection Balance', $fs['outstanding_collection_balance'] ?? 0],
            ['Billing Progress (%)', $fs['billing_progress_percent'] ?? 0],
            ['Total Purchases', $data['total_purchases']],
            ['Total Expenses', $data['total_expenses']],
            ['Total Cost', $data['total_cost']],
            ['Net Profit', $data['net_profit']],
        ];

        $r = 6;
        foreach ($summaryRows as [$label, $value]) {
            $sheet->setCellValue("A$r", $label);
            $sheet->mergeCells("A$r:C$r");
            $sheet->setCellValue("D$r", $value);
            $sheet->getStyle("D$r")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("A$r")->getFont()->setBold(true);
            if ($r % 2 == 0) {
                $sheet->getStyle("A$r:D$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F7FBFC');
            }
            $r++;
        }

        // ===== TIMELINE =====
        $timelineHeaderRow = $r + 1;
        $sheet->setCellValue("A$timelineHeaderRow", 'TIMELINE');
        $sheet->mergeCells("A{$timelineHeaderRow}:G{$timelineHeaderRow}");
        $sheet->getStyle("A$timelineHeaderRow")->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A$timelineHeaderRow")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('3C5A82');

        $colHeaderRow = $timelineHeaderRow + 1;
        $headers = ['Date', 'Event Type', 'Reference No.', 'Description', 'Amount', 'Category', 'Running Balance'];
        foreach ($headers as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue("$col$colHeaderRow", $h);
        }
        $sheet->getStyle("A{$colHeaderRow}:G{$colHeaderRow}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$colHeaderRow}:G{$colHeaderRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('3C5A82');
        $sheet->getStyle("A{$colHeaderRow}:G{$colHeaderRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $rowNum = $colHeaderRow + 1;
        foreach ($timeline as $ev) {
            $sheet->setCellValue("A$rowNum", $ev['date']);
            $sheet->setCellValue("B$rowNum", $ev['type']);
            $sheet->setCellValue("C$rowNum", $ev['reference']);
            $sheet->setCellValue("D$rowNum", $ev['description']);
            $sheet->setCellValue("E$rowNum", $ev['amount'] !== null ? $ev['amount'] : '');
            $sheet->setCellValue("F$rowNum", $ev['category']);
            $sheet->setCellValue("G$rowNum", $ev['running_balance'] !== null ? $ev['running_balance'] : '');

            $sheet->getStyle("E$rowNum")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("G$rowNum")->getNumberFormat()->setFormatCode('#,##0.00');

            if ($rowNum % 2 == 0) {
                $sheet->getStyle("A$rowNum:G$rowNum")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F7FBFC');
            }
            $rowNum++;
        }
        $lastTimelineRow = $rowNum - 1;
        if ($lastTimelineRow >= $colHeaderRow) {
            $sheet->getStyle("A{$colHeaderRow}:G{$lastTimelineRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = 'project_statement_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $project['name']) . '_' . date('d-m-Y_H-i') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"$fileName\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function statementPdf($id)
    {
        $data = $this->_buildStatementData((int) $id);
        if ($data === null) {
            return redirect()->to('/projects')->with('error', 'Project not found.');
        }

        $project  = $data['project'];
        $fs       = $data['financial_summary'];
        $timeline = $data['timeline'];

        $summaryRows = [
            ['Project Value', $fs['total_project_value'] ?? 0],
            ['Advance Received', $fs['advance_amount'] ?? 0],
            ['Total Billed', $fs['total_billed'] ?? 0],
            ['Remaining Billable Value', $fs['remaining_billable_value'] ?? 0],
            ['Total Payments Received', $fs['total_paid'] ?? 0],
            ['Outstanding Collection Balance', $fs['outstanding_collection_balance'] ?? 0],
            ['Billing Progress (%)', number_format($fs['billing_progress_percent'] ?? 0, 1) . '%'],
            ['Total Purchases', $data['total_purchases']],
            ['Total Expenses', $data['total_expenses']],
            ['Total Cost', $data['total_cost']],
            ['Net Profit', $data['net_profit']],
        ];

        $html = '
            <style>
                body { font-family: DejaVu Sans; font-size: 10px; color: #2d3748; }
                .header-box { background: #2F7E8A; color: #fff; padding: 12px; border-radius: 6px; text-align: center; margin-bottom: 10px; }
                .header-box h2 { margin: 0; font-size: 16px; letter-spacing: 1px; }
                .meta { text-align: center; font-size: 10px; margin-top: 4px; color: #e2e8f0; }
                .section-title { background: #3C5A82; color: #fff; padding: 6px 8px; font-size: 12px; font-weight: bold; margin-top: 14px; }
                table { width: 100%; border-collapse: collapse; margin-top: 4px; }
                th { background: #3C5A82; color: #fff; padding: 6px; font-size: 10px; }
                td { padding: 5px; border: 1px solid #e2e8f0; }
                tr:nth-child(even) td { background: #f8fafc; }
                .right { text-align: right; }
                .center { text-align: center; }
                .summary-label { font-weight: bold; width: 60%; }
            </style>
            <div class="header-box">
                <h2>PROJECT STATEMENT</h2>
                <div class="meta">
                    Generated On: ' . date('d-m-Y h:i A') . '<br>
                    Project: ' . esc($project['name']) . ' (' . esc($project['customer_name'] ?: 'No Customer') . ')
                </div>
            </div>

            <div class="section-title">FINANCIAL SUMMARY</div>
            <table>';
        foreach ($summaryRows as [$label, $value]) {
            $displayValue = is_numeric($value) ? number_format((float) $value, 2) : $value;
            $html .= '<tr><td class="summary-label">' . esc($label) . '</td><td class="right">' . $displayValue . '</td></tr>';
        }
        $html .= '</table>

            <div class="section-title">TIMELINE</div>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Event Type</th>
                    <th>Reference No.</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Category</th>
                    <th>Running Balance</th>
                </tr>';
        foreach ($timeline as $ev) {
            $html .= '<tr>
                <td>' . esc($ev['date']) . '</td>
                <td>' . esc($ev['type']) . '</td>
                <td>' . esc($ev['reference']) . '</td>
                <td>' . esc($ev['description']) . '</td>
                <td class="right">' . ($ev['amount'] !== null ? number_format($ev['amount'], 2) : '&mdash;') . '</td>
                <td class="center">' . esc($ev['category']) . '</td>
                <td class="right">' . ($ev['running_balance'] !== null ? number_format($ev['running_balance'], 2) : '&mdash;') . '</td>
            </tr>';
        }
        $html .= '</table>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $fileName = 'project_statement_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $project['name']) . '_' . date('d-m-Y_H-i') . '.pdf';
        $dompdf->stream($fileName, ["Attachment" => true]);
    }

    /**
     * Shared summary/timeline/status assembly for statement(), statementExport(), statementPdf().
     * Returns null when the project doesn't exist.
     */
    private function _buildStatementData(int $id): ?array
    {
        $db = \Config\Database::connect();

        $project = $db->query("
            SELECT p.*, c.name AS customer_name
            FROM projects p
            LEFT JOIN customers c ON p.customer_id = c.id
            WHERE p.id = ?
        ", [$id])->getRowArray();

        if (!$project) {
            return null;
        }

        $financialSummary = $this->model->getFinancialSummary($id);

        $totals = $db->query("
            SELECT
                (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE project_id = ?) AS total_expenses
        ", [$id])->getRowArray();

        // Release 1.6B (Decision 4/5): allocation-aware project purchase cost —
        // only the project_qty portion of each purchase line, not the full
        // supplier invoice.
        $totalPurchases = $this->model->getAllocatedPurchaseCost($id);
        $totalExpenses  = (float) $totals['total_expenses'];
        $totalCost      = $totalPurchases + $totalExpenses;
        $netProfit      = ($financialSummary['total_billed'] ?? 0) - $totalCost;

        $balance = (float) ($financialSummary['outstanding_collection_balance'] ?? 0);
        if ($balance > 0.005) {
            $balanceStatus = 'Outstanding';
        } elseif ($balance < -0.005) {
            $balanceStatus = 'Customer Credit';
        } else {
            $balanceStatus = 'Settled';
        }

        // Release 3.0 (UI only): expose the same billing completion status
        // Projects::view() already computes via getBillingCompletionStatus(),
        // needed for the statement header's Billing Status badge.
        $billingCompletionStatus = $this->model->getBillingCompletionStatus($project, $financialSummary);

        return [
            'project'                    => $project,
            'financial_summary'          => $financialSummary,
            'total_purchases'            => $totalPurchases,
            'total_expenses'             => $totalExpenses,
            'total_cost'                 => $totalCost,
            'net_profit'                 => $netProfit,
            'balance_status'             => $balanceStatus,
            'billing_completion_status'  => $billingCompletionStatus,
            'timeline'                   => $this->model->getTimelineEvents($id),
        ];
    }
}
