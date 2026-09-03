<?php

namespace App\Controllers;

use App\Models\ProjectModel;
use CodeIgniter\Controller;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

use Dompdf\Dompdf;
use Dompdf\Options;

class Reports extends Controller
{
    public function index()
    {
        return view('reports/index');
    }

    /**
     * Release 5.0B (Phase A/B/D): single source of truth for the per-project
     * revenue/expense rows shown in the Profit & Loss breakdown table on the
     * screen, Excel export, and PDF export. Revenue and Expenses are summed
     * in their own independently-filtered subqueries and joined back to
     * projects by project_id — this avoids the old LEFT JOIN sales x
     * expenses fan-out, which required SUM(DISTINCT ...) to paper over
     * duplicated rows and could drop a project's revenue entirely when its
     * sales and expenses didn't share dates inside the filter window. All
     * inputs are bound as query parameters — no raw string interpolation.
     */
    private function getProfitLossBreakdown($db, ProjectModel $projectModel, ?int $projectId, ?string $startDate, ?string $endDate): array
    {
        $salesParams = [];
        $salesWhere  = "WHERE 1=1";
        if ($startDate) { $salesWhere .= " AND s.sale_date >= ?"; $salesParams[] = $startDate; }
        if ($endDate)   { $salesWhere .= " AND s.sale_date <= ?"; $salesParams[] = $endDate; }

        $expParams = [];
        $expWhere  = "WHERE 1=1";
        if ($startDate) { $expWhere .= " AND e.expense_date >= ?"; $expParams[] = $startDate; }
        if ($endDate)   { $expWhere .= " AND e.expense_date <= ?"; $expParams[] = $endDate; }

        $projParams = [];
        $projWhere  = "WHERE 1=1";
        if ($projectId) { $projWhere .= " AND pr.id = ?"; $projParams[] = $projectId; }

        $sql = "
            SELECT pr.id, pr.name AS project_name,
                COALESCE(rev.revenue, 0)  AS revenue,
                COALESCE(exp.expenses, 0) AS expenses
            FROM projects pr
            LEFT JOIN (
                SELECT s.project_id, SUM(s.total_amount) AS revenue
                FROM sales s
                $salesWhere
                GROUP BY s.project_id
            ) rev ON rev.project_id = pr.id
            LEFT JOIN (
                SELECT e.project_id, SUM(e.amount) AS expenses
                FROM expenses e
                $expWhere
                GROUP BY e.project_id
            ) exp ON exp.project_id = pr.id
            $projWhere
            ORDER BY revenue DESC
        ";

        $rows = $db->query($sql, array_merge($salesParams, $expParams, $projParams))->getResultArray();

        // Release 1.6B (Approved Design Decision 5): COGS stays allocation-
        // aware — untouched, same ProjectModel method as before, already
        // parameter-bound internally.
        $costByProject = $projectModel->getAllocatedPurchaseCostByProject($projectId, $startDate, $endDate);
        foreach ($rows as &$r) {
            $r['cogs'] = $costByProject[(int) $r['id']] ?? 0.0;
        }
        unset($r);

        return $rows;
    }

    public function profitLoss()
    {
        $db = \Config\Database::connect();

        // Release 5.0B (Phase D): project_id cast to int; start/end date
        // bound as query parameters everywhere below — no raw GET value is
        // ever concatenated into SQL in this method.
        $projectIdRaw = $this->request->getGet('project_id');
        $projectId    = ($projectIdRaw !== null && $projectIdRaw !== '') ? (int) $projectIdRaw : null;
        $startDate    = $this->request->getGet('start_date') ?: null;
        $endDate      = $this->request->getGet('end_date') ?: null;

        $projectModel = new ProjectModel();

        // Release 1.6B (Approved Design Decision 5): COGS is allocation-aware —
        // only the project_qty portion of each purchase line counts, not the
        // full supplier invoice (purchases.total_amount).
        $revParams = [];
        $revSql    = "SELECT COALESCE(SUM(total_amount),0) AS t FROM sales s WHERE 1=1";
        if ($projectId) { $revSql .= " AND s.project_id = ?"; $revParams[] = $projectId; }
        if ($startDate) { $revSql .= " AND s.sale_date >= ?"; $revParams[] = $startDate; }
        if ($endDate)   { $revSql .= " AND s.sale_date <= ?"; $revParams[] = $endDate; }
        $data['total_revenue'] = (float) $db->query($revSql, $revParams)->getRow()->t;

        $data['total_cogs'] = array_sum($projectModel->getAllocatedPurchaseCostByProject($projectId, $startDate, $endDate));

        $expParams = [];
        $expSql    = "SELECT COALESCE(SUM(amount),0) AS t FROM expenses e WHERE 1=1";
        if ($projectId) { $expSql .= " AND e.project_id = ?"; $expParams[] = $projectId; }
        if ($startDate) { $expSql .= " AND e.expense_date >= ?"; $expParams[] = $startDate; }
        if ($endDate)   { $expSql .= " AND e.expense_date <= ?"; $expParams[] = $endDate; }
        $data['total_expenses'] = (float) $db->query($expSql, $expParams)->getRow()->t;

        $data['gross_profit'] = $data['total_revenue'] - $data['total_cogs'];
        $data['net_profit']   = $data['gross_profit'] - $data['total_expenses'];

        // Release 4.1 (Phase B): margin KPIs, derived from the totals above —
        // no new query, 0% when revenue is zero to avoid a division by zero.
        $data['gross_margin'] = $data['total_revenue'] > 0 ? ($data['gross_profit'] / $data['total_revenue']) * 100 : 0.0;
        $data['net_margin']   = $data['total_revenue'] > 0 ? ($data['net_profit'] / $data['total_revenue']) * 100 : 0.0;

        // Release 5.0B (Phase A): the breakdown table now shares the exact
        // same project/date filters as the KPI totals above, via the shared
        // helper — table and KPI cards can no longer disagree.
        $data['project_breakdown'] = $this->getProfitLossBreakdown($db, $projectModel, $projectId, $startDate, $endDate);

        // Release 4.1 (Phase C): expense category breakdown, filtered by the
        // exact same project/date filters as the "Total Expenses" KPI above —
        // its grand total must equal $total_expenses.
        $expCatParams = [];
        $expCatWhere  = "WHERE 1=1";
        if ($projectId) { $expCatWhere .= " AND e.project_id = ?"; $expCatParams[] = $projectId; }
        if ($startDate) { $expCatWhere .= " AND e.expense_date >= ?"; $expCatParams[] = $startDate; }
        if ($endDate)   { $expCatWhere .= " AND e.expense_date <= ?"; $expCatParams[] = $endDate; }
        $data['expense_breakdown'] = $db->query("
            SELECT e.category, SUM(e.amount) AS total
            FROM expenses e
            $expCatWhere
            GROUP BY e.category
            ORDER BY total DESC
        ", $expCatParams)->getResultArray();

        $data['projects']   = (new ProjectModel())->orderBy('name', 'ASC')->findAll();
        $data['project_id'] = $projectId;
        $data['start_date'] = $startDate;
        $data['end_date']   = $endDate;

        return view('reports/profit_loss', $data);
    }
	
	public function profitLossExport()
	{
		$db = \Config\Database::connect();

		// Release 5.0B (Phase D): project_id cast to int; start/end date
		// bound as query parameters everywhere — no raw GET value is ever
		// concatenated into SQL in this method.
		$projectIdRaw = $this->request->getGet('project_id');
		$projectId    = ($projectIdRaw !== null && $projectIdRaw !== '') ? (int) $projectIdRaw : null;
		$startDate    = $this->request->getGet('start_date') ?: null;
		$endDate      = $this->request->getGet('end_date') ?: null;

		// Release 5.0B (Phase B): reuses the same independently-filtered
		// subquery helper as the screen — sales are summed by their own
		// sale_date filter, expenses by their own expense_date filter, so a
		// project with sales but no matching expenses (or vice versa) in the
		// selected range still appears with its correct totals instead of
		// disappearing due to LEFT JOIN fan-out.
		$rows = $this->getProfitLossBreakdown($db, new ProjectModel(), $projectId, $startDate, $endDate);

		// ===== CREATE EXCEL =====
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->freezePane('A7');

		// ===== TITLE =====
		$sheet->mergeCells('A1:F1');
		$sheet->setCellValue('A1', 'PROFIT & LOSS REPORT');

		$sheet->mergeCells('A2:F2');
		$sheet->setCellValue('A2', 'Generated On: ' . date('d-m-Y h:i A'));

		// ===== PROJECT =====
		if ($projectId) {
			$projectName = $db->query("SELECT name FROM projects WHERE id = ?", [$projectId])->getRow()->name ?? '';
			$sheet->mergeCells('A3:F3');
			$sheet->setCellValue('A3', 'Project: ' . $projectName);
		} else {
			$sheet->mergeCells('A3:F3');
			$sheet->setCellValue('A3', 'Project: All Projects');
		}

		// ===== DATE =====
		if ($startDate || $endDate) {
			$sheet->mergeCells('A4:F4');
			$sheet->setCellValue('A4', 'Period: ' . ($startDate ?: 'Start') . ' to ' . ($endDate ?: 'End'));
		}

		// ===== TABLE HEADER =====
		$sheet->setCellValue('A6', 'Project');
		$sheet->setCellValue('B6', 'Revenue');
		$sheet->setCellValue('C6', 'COGS');
		$sheet->setCellValue('D6', 'Gross Profit');
		$sheet->setCellValue('E6', 'Expenses');
		$sheet->setCellValue('F6', 'Net Profit');

		// ===== DATA =====
		$rowNum = 7;

		foreach ($rows as $r) {
			$gross = $r['revenue'] - $r['cogs'];
			$net   = $gross - $r['expenses'];

			$sheet->setCellValue('A' . $rowNum, $r['project_name']);
			$sheet->setCellValue('B' . $rowNum, $r['revenue']);
			$sheet->setCellValue('C' . $rowNum, $r['cogs']);
			$sheet->setCellValue('D' . $rowNum, $gross);
			$sheet->setCellValue('E' . $rowNum, $r['expenses']);
			$sheet->setCellValue('F' . $rowNum, $net);

			$rowNum++;
		}

		// ===== AUTO SIZE =====
		foreach (range('A','F') as $col) {
			$sheet->getColumnDimension($col)->setAutoSize(true);
		}
		
		// ===== TITLE STYLE =====
		$sheet->getStyle('A1:F2')->getFill()->setFillType(Fill::FILL_SOLID)
			->getStartColor()->setRGB('2F7E8A'); // dark teal

		$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('FFFFFF');
		$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		$sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('FFFFFF');
		$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		
		$sheet->getStyle('A3:F4')->getFill()->setFillType(Fill::FILL_SOLID)
			->getStartColor()->setRGB('E7F3F5');

		$sheet->getStyle('A3')->getFont()
			->setBold(true)
			->setSize(13)
			->getColor()->setRGB('1F4E79');

		$sheet->getStyle('A4')->getFont()
			->setBold(true)
			->setSize(11)
			->getColor()->setRGB('404040');
		$sheet->getStyle('A3:F4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		// ===== HEADER STYLE =====
		$sheet->getStyle('A6:F6')->getFont()
			->setBold(true)
			->setSize(11)
			->getColor()->setRGB('FFFFFF');

		$sheet->getStyle('A6:F6')->getAlignment()
			->setHorizontal(Alignment::HORIZONTAL_CENTER);

		$sheet->getStyle('A6:F6')->getFill()->setFillType(Fill::FILL_SOLID)
			->getStartColor()->setRGB('3C5A82');
		
		$sheet->getStyle('A6:F6')->getBorders()->getBottom()
    		->setBorderStyle(Border::BORDER_MEDIUM);

		// ===== BORDER =====
		$lastRow = $rowNum - 1;
		if ($lastRow < 7) {
			$lastRow = 7;
		}
		$sheet->getStyle("A6:F{$lastRow}")
			->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

		// ===== NUMBER FORMAT =====
		foreach (range(7, $lastRow) as $r) {
			$sheet->getStyle("B$r:F$r")->getNumberFormat()->setFormatCode('#,##0.00');
			$sheet->getStyle("B$r:F$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
		}
		
		for ($i = 7; $i <= $lastRow; $i++) {
			if ($i % 2 == 0) {
				$sheet->getStyle("A$i:F$i")->getFill()->setFillType(Fill::FILL_SOLID)
					->getStartColor()->setRGB('F7FBFC');
			}
		}
		
		// ===== TOTAL ROW =====
		$totalRow = $lastRow + 1;

		$sheet->setCellValue("A$totalRow", 'TOTAL');
		$sheet->setCellValue("B$totalRow", "=SUM(B7:B$lastRow)");
		$sheet->setCellValue("C$totalRow", "=SUM(C7:C$lastRow)");
		$sheet->setCellValue("D$totalRow", "=SUM(D7:D$lastRow)");
		$sheet->setCellValue("E$totalRow", "=SUM(E7:E$lastRow)");
		$sheet->setCellValue("F$totalRow", "=SUM(F7:F$lastRow)");

		$sheet->getStyle("A$totalRow:F$totalRow")->getFont()->setBold(true);
		$sheet->getStyle("A$totalRow:F$totalRow")->getFill()->setFillType(Fill::FILL_SOLID)
			->getStartColor()->setRGB('D9E1F2');
		
		$sheet->getStyle("A$totalRow:F$totalRow")->getBorders()->getTop()
    		->setBorderStyle(Border::BORDER_MEDIUM);
		
		$sheet->getStyle("B$totalRow:F$totalRow")
    		->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

		// ===== DOWNLOAD =====
		$fileName = 'profit_loss_report_' . date('d-m-Y_H-i') . '.xlsx';

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header("Content-Disposition: attachment;filename=\"$fileName\"");
		header('Cache-Control: max-age=0');

		$writer = new Xlsx($spreadsheet);
		$writer->save('php://output');
		exit;
	}
	
	public function profitLossPdf()
	{
		$db = \Config\Database::connect();

		// Release 5.0B (Phase D): project_id cast to int; start/end date
		// bound as query parameters everywhere — no raw GET value is ever
		// concatenated into SQL in this method.
		$projectIdRaw = $this->request->getGet('project_id');
		$projectId    = ($projectIdRaw !== null && $projectIdRaw !== '') ? (int) $projectIdRaw : null;
		$startDate    = $this->request->getGet('start_date') ?: null;
		$endDate      = $this->request->getGet('end_date') ?: null;

		// Release 5.0B (Phase B): same independently-filtered subquery
		// helper as the screen and the Excel export — sales/expenses are
		// each summed against their own date column, so no LEFT JOIN
		// fan-out and no SUM(DISTINCT ...) is needed.
		$rows = $this->getProfitLossBreakdown($db, new ProjectModel(), $projectId, $startDate, $endDate);

		// ===== PROJECT NAME =====
		$projectName = 'All Projects';
		if ($projectId) {
			$projectName = $db->query("SELECT name FROM projects WHERE id = ?", [$projectId])->getRow()->name ?? '';
		}

		// ===== HTML =====
		$html = '
			<style>
				body {
					font-family: DejaVu Sans;
					font-size: 11px;
					color: #2d3748;
				}

				.header-box {
					background: #2F7E8A;
					color: #fff;
					padding: 12px;
					border-radius: 6px;
					text-align: center;
					margin-bottom: 10px;
				}

				.header-box h2 {
					margin: 0;
					font-size: 16px;
					letter-spacing: 1px;
				}

				.meta {
					text-align: center;
					font-size: 10px;
					margin-top: 4px;
					color: #e2e8f0;
				}

				.project-text {
					display:block;
					margin-top:5px;
					color:#ffffff;
					font-weight:bold;
				}

				.period-text {
					display:block;
					margin-top:2px;
					font-size:10px;
					color:#e2e8f0;
				}

				table {
					width: 100%;
					border-collapse: collapse;
					margin-top: 8px;
				}

				th {
					background: #3C5A82;
					color: #fff;
					padding: 7px;
					font-size: 11px;
				}

				td {
					padding: 6px;
					border: 1px solid #e2e8f0;
				}

				tr:nth-child(even) td {
					background: #f8fafc;
				}

				.project-row {
					font-weight: bold;
					color: #2F7E8A;
				}

				.right { text-align:right; }

				.profit {
					color:#2e7d32;
					font-weight:bold;
				}

				.loss {
					color:#c62828;
					font-weight:bold;
				}

				.total {
					background:#edf2f7;
					font-weight:bold;
					font-size:12px;
				}

				.footer-note {
					margin-top:10px;
					font-size:9px;
					text-align:right;
					color:#888;
				}
			</style>

			<div class="header-box">
				<h2>PROFIT & LOSS REPORT</h2>
				<div class="meta">
					Generated On: '.date('d-m-Y h:i A').'
					<span class="project-text">Project: '.esc($projectName).'</span>
					<span class="period-text">Period: '.esc($startDate ?: 'Start').' to '.esc($endDate ?: 'End').'</span>
				</div>
			</div>

		<table>
			<tr>
				<th>Project</th>
				<th>Revenue</th>
				<th>COGS</th>
				<th>Gross Profit</th>
				<th>Expenses</th>
				<th>Net Profit</th>
			</tr>
		';

		$totalRev = $totalCogs = $totalExp = 0;

		foreach ($rows as $r) {
			$gross = $r['revenue'] - $r['cogs'];
			$net   = $gross - $r['expenses'];

			$totalRev += $r['revenue'];
			$totalCogs += $r['cogs'];
			$totalExp += $r['expenses'];

			$html .= '
			<tr>
				<td class="project-row">'.esc($r['project_name']).'</td>
				<td class="right">'.number_format($r['revenue'],2).'</td>
				<td class="right">'.number_format($r['cogs'],2).'</td>
				<td class="right">'.number_format($gross,2).'</td>
				<td class="right">'.number_format($r['expenses'],2).'</td>
				<td class="right">'.number_format($net,2).'</td>
			</tr>';
		}

		$grossTotal = $totalRev - $totalCogs;
		$netTotal   = $grossTotal - $totalExp;

		$html .= '
			<tr class="total">
				<td>TOTAL</td>
				<td class="right">'.number_format($totalRev,2).'</td>
				<td class="right">'.number_format($totalCogs,2).'</td>
				<td class="right">'.number_format($grossTotal,2).'</td>
				<td class="right">'.number_format($totalExp,2).'</td>
				<td class="right">'.number_format($netTotal,2).'</td>
			</tr>
		</table>';

		// ===== DOMPDF =====
		$options = new Options();
		$options->set('isRemoteEnabled', true);

		$dompdf = new Dompdf($options);
		$dompdf->loadHtml($html);
		$dompdf->setPaper('A4', 'portrait');
		$dompdf->render();

		$fileName = 'profit_loss_report_' . date('d-m-Y_H-i') . '.pdf';

		$dompdf->stream($fileName, ["Attachment" => true]);
	}

	/**
	 * Release 4.4.1: single source of truth for the Balance Sheet, shared by
	 * the screen, Excel export, and PDF export (same pattern as
	 * getProfitLossBreakdown() for Profit & Loss). No new accounting logic is
	 * introduced — every figure is either read straight from an existing
	 * table/column or reuses an existing ProjectModel/Reports formula
	 * (getAllocatedPurchaseCostByProject() for COGS, getFinancialSummary()'s
	 * unused_advance for Customer Advance Liability). Fields with no backing
	 * data anywhere in the schema (Supplier Outstanding, Owner Capital, Cash/
	 * Bank balance) are reported as 0 with an explicit "unavailable" flag
	 * rather than guessed or derived from unrelated columns.
	 */
	private function getBalanceSheetData($db, ProjectModel $projectModel, ?int $projectId, ?string $startDate, ?string $endDate): array
	{
		// ---- Assets ----

		// Cash Received From Customers: SUM(payments.amount), same table the
		// Dashboard's Recent Payments panel and the project timeline both
		// read from. Filtered on payment_date (its own business date) and,
		// when a project is selected, joined to sales.project_id.
		$payParams = [];
		$paySql    = "SELECT COALESCE(SUM(pay.amount),0) AS t FROM payments pay INNER JOIN sales s ON pay.sale_id = s.id WHERE 1=1";
		if ($projectId) { $paySql .= " AND s.project_id = ?"; $payParams[] = $projectId; }
		if ($startDate) { $paySql .= " AND pay.payment_date >= ?"; $payParams[] = $startDate; }
		if ($endDate)   { $paySql .= " AND pay.payment_date <= ?"; $payParams[] = $endDate; }
		$cashReceived = (float) $db->query($paySql, $payParams)->getRow()->t;

		// Release 4.5 (Phase 9): Project Cash Receipt — a separate, independent
		// ledger (own table, no sale_id) added into Cash Received alongside
		// invoice payments above. Same project/date filters, applied to
		// receipt_date (its own business date), mirroring the payments query.
		$cashReceiptParams = [];
		$cashReceiptSql    = "SELECT COALESCE(SUM(amount),0) AS t FROM project_cash_receipts WHERE 1=1";
		if ($projectId) { $cashReceiptSql .= " AND project_id = ?"; $cashReceiptParams[] = $projectId; }
		if ($startDate) { $cashReceiptSql .= " AND receipt_date >= ?"; $cashReceiptParams[] = $startDate; }
		if ($endDate)   { $cashReceiptSql .= " AND receipt_date <= ?"; $cashReceiptParams[] = $endDate; }
		$projectCashReceived = (float) $db->query($cashReceiptSql, $cashReceiptParams)->getRow()->t;

		$cashReceived = round($cashReceived + $projectCashReceived, 2);

		// Accounts Receivable: SUM(sales.balance_amount) — the exact figure
		// already used as "Outstanding Collection" on the Dashboard.
		$arParams = [];
		$arSql    = "SELECT COALESCE(SUM(total_amount),0) AS rev, COALESCE(SUM(balance_amount),0) AS bal FROM sales s WHERE 1=1";
		if ($projectId) { $arSql .= " AND s.project_id = ?"; $arParams[] = $projectId; }
		if ($startDate) { $arSql .= " AND s.sale_date >= ?"; $arParams[] = $startDate; }
		if ($endDate)   { $arSql .= " AND s.sale_date <= ?"; $arParams[] = $endDate; }
		$salesRow           = $db->query($arSql, $arParams)->getRow();
		$totalRevenue        = (float) $salesRow->rev;
		$accountsReceivableRaw = (float) $salesRow->bal;

		// Release 4.5 (Phase 9): Accounts Receivable is reduced by available
		// Project Cash (within the same filters); any cash left over after
		// covering the receivable becomes Customer Advance Credit (below),
		// not a negative receivable.
		$cashAppliedToReceivable = min($accountsReceivableRaw, $projectCashReceived);
		$accountsReceivable      = round($accountsReceivableRaw - $cashAppliedToReceivable, 2);
		$excessProjectCash       = round($projectCashReceived - $cashAppliedToReceivable, 2);

		// Inventory Value: current on-hand quantity per product (stock_ledger
		// IN minus OUT — the same ledger Reports::stock() already reads),
		// valued at each product's weighted-average purchase unit price from
		// purchase_items (existing GST-line data, not a new cost model).
		// This is a point-in-time stock valuation, not a period flow, so — like
		// the Dashboard's Outstanding Collection — it intentionally is not
		// date-filtered; the Project filter narrows it to that project's own
		// PROJECT-sourced stock when selected.
		$stockWhere  = "WHERE 1=1";
		$stockParams = [];
		if ($projectId) {
			$stockWhere .= " AND sl.source = 'PROJECT' AND sl.project_id = ?";
			$stockParams[] = $projectId;
		}
		$qtyRows = $db->query("
			SELECT sl.product_id,
				COALESCE(SUM(CASE WHEN sl.transaction_type='IN' THEN sl.quantity ELSE 0 END),0)
				- COALESCE(SUM(CASE WHEN sl.transaction_type='OUT' THEN sl.quantity ELSE 0 END),0) AS on_hand
			FROM stock_ledger sl
			$stockWhere
			GROUP BY sl.product_id
		", $stockParams)->getResultArray();

		$avgCostRows = $db->query("
			SELECT product_id,
				CASE WHEN SUM(quantity) > 0 THEN SUM(unit_price * quantity) / SUM(quantity) ELSE 0 END AS avg_unit_price
			FROM purchase_items
			GROUP BY product_id
		")->getResultArray();
		$avgCostByProduct = [];
		foreach ($avgCostRows as $r) {
			$avgCostByProduct[(int) $r['product_id']] = (float) $r['avg_unit_price'];
		}

		$inventoryValue = 0.0;
		foreach ($qtyRows as $r) {
			$onHand = (float) $r['on_hand'];
			if ($onHand <= 0) continue;
			$inventoryValue += $onHand * ($avgCostByProduct[(int) $r['product_id']] ?? 0.0);
		}
		$inventoryValue = round($inventoryValue, 2);

		$totalAssets = round($cashReceived + $accountsReceivable + $inventoryValue, 2);

		// ---- Liabilities ----

		// Customer Advance Liability: unused portion of project advances —
		// advance money already received but not yet applied to any invoice
		// (ProjectModel::getFinancialSummary()'s existing 'unused_advance'
		// figure, the same one that drives the negative/credit case of the
		// Dashboard's Top Pending Projects list). A snapshot figure, like
		// Inventory — not date-filtered; Project filter narrows to one project.
		$advanceLiability = 0.0;
		$projectsForAdvance = $projectId
			? [$projectModel->find($projectId)]
			: $projectModel->findAll();
		foreach ($projectsForAdvance as $p) {
			if (!$p) continue;
			$fs = $projectModel->getFinancialSummary((int) $p['id']);
			$advanceLiability += $fs['unused_advance'] ?? 0.0;
		}
		// Release 4.5 (Phase 9): Customer Advance Credit — unused project
		// advance (existing) plus any Project Cash Receipt not consumed by
		// Accounts Receivable above (excessProjectCash). Field name is kept
		// as advance_liability for backward compatibility with existing
		// consumers of this array; the view label is updated to "Customer
		// Advance Credit" to reflect the combined figure.
		$advanceLiability = round($advanceLiability + $excessProjectCash, 2);

		// Supplier Outstanding: no supplier-payment tracking exists anywhere
		// in this schema (purchases has no paid_amount/balance_amount/status
		// column, and there is no supplier-side payments table) — reported as
		// unavailable rather than guessed.
		$supplierOutstanding          = 0.0;
		$supplierOutstandingAvailable = false;

		// Other Liabilities: no other liability data source exists in the
		// schema (no loans/tax-payable/accrued-expense tables) — reported as
		// unavailable rather than guessed.
		$otherLiabilities          = 0.0;
		$otherLiabilitiesAvailable = false;

		$totalLiabilities = round($advanceLiability + $supplierOutstanding + $otherLiabilities, 2);

		// ---- Equity ----

		// Net Profit: identical formula to Reports::profitLoss() — revenue
		// (already computed above) minus allocation-aware COGS minus expenses,
		// under the exact same project/date filters.
		$totalCogs = array_sum($projectModel->getAllocatedPurchaseCostByProject($projectId, $startDate, $endDate));

		$expParams = [];
		$expSql    = "SELECT COALESCE(SUM(amount),0) AS t FROM expenses e WHERE 1=1";
		if ($projectId) { $expSql .= " AND e.project_id = ?"; $expParams[] = $projectId; }
		if ($startDate) { $expSql .= " AND e.expense_date >= ?"; $expParams[] = $startDate; }
		if ($endDate)   { $expSql .= " AND e.expense_date <= ?"; $expParams[] = $endDate; }
		$totalExpenses = (float) $db->query($expSql, $expParams)->getRow()->t;

		$netProfit = round($totalRevenue - $totalCogs - $totalExpenses, 2);

		// Owner Capital: no capital/owner-investment table exists in the
		// schema — reported as unavailable rather than guessed.
		$ownerCapital          = 0.0;
		$ownerCapitalAvailable = false;

		$totalEquity = round($netProfit + $ownerCapital, 2);

		// ---- Accounting Equation ----
		// Cash/Bank balance and Owner Capital are not tracked anywhere in this
		// schema, so Assets = Liabilities + Equity is not expected to hold —
		// this is flagged explicitly rather than forcing a fake balance.
		$difference = round($totalAssets - ($totalLiabilities + $totalEquity), 2);
		$isBalanced = abs($difference) < 0.01;

		return [
			'cash_received'                 => $cashReceived,
			'accounts_receivable'           => $accountsReceivable,
			'inventory_value'               => $inventoryValue,
			'total_assets'                  => $totalAssets,

			'advance_liability'             => $advanceLiability,
			'supplier_outstanding'          => $supplierOutstanding,
			'supplier_outstanding_available'=> $supplierOutstandingAvailable,
			'other_liabilities'             => $otherLiabilities,
			'other_liabilities_available'   => $otherLiabilitiesAvailable,
			'total_liabilities'             => $totalLiabilities,

			'total_revenue'                 => round($totalRevenue, 2),
			'net_profit'                    => $netProfit,
			'owner_capital'                 => $ownerCapital,
			'owner_capital_available'       => $ownerCapitalAvailable,
			'total_equity'                  => $totalEquity,

			'is_balanced'                   => $isBalanced,
			'difference'                    => $difference,
		];
	}

	public function balanceSheet()
	{
		$db = \Config\Database::connect();

		$projectIdRaw = $this->request->getGet('project_id');
		$projectId    = ($projectIdRaw !== null && $projectIdRaw !== '') ? (int) $projectIdRaw : null;
		$startDate    = $this->request->getGet('start_date') ?: null;
		$endDate      = $this->request->getGet('end_date') ?: null;

		$projectModel = new ProjectModel();

		$data              = $this->getBalanceSheetData($db, $projectModel, $projectId, $startDate, $endDate);
		$data['projects']  = $projectModel->orderBy('name', 'ASC')->findAll();
		$data['project_id'] = $projectId;
		$data['start_date'] = $startDate;
		$data['end_date']   = $endDate;

		return view('reports/balance_sheet', $data);
	}

	public function balanceSheetExport()
	{
		$db = \Config\Database::connect();

		$projectIdRaw = $this->request->getGet('project_id');
		$projectId    = ($projectIdRaw !== null && $projectIdRaw !== '') ? (int) $projectIdRaw : null;
		$startDate    = $this->request->getGet('start_date') ?: null;
		$endDate      = $this->request->getGet('end_date') ?: null;

		$data = $this->getBalanceSheetData($db, new ProjectModel(), $projectId, $startDate, $endDate);

		$spreadsheet = new Spreadsheet();
		$sheet       = $spreadsheet->getActiveSheet();
		$sheet->freezePane('A7');

		$sheet->mergeCells('A1:C1');
		$sheet->setCellValue('A1', 'BALANCE SHEET REPORT');

		$sheet->mergeCells('A2:C2');
		$sheet->setCellValue('A2', 'Generated On: ' . date('d-m-Y h:i A'));

		$projectName = 'All Projects';
		if ($projectId) {
			$projectName = $db->query("SELECT name FROM projects WHERE id = ?", [$projectId])->getRow()->name ?? '';
		}
		$sheet->mergeCells('A3:C3');
		$sheet->setCellValue('A3', 'Project: ' . $projectName . (($startDate || $endDate) ? ('  |  Period: ' . ($startDate ?: 'Start') . ' to ' . ($endDate ?: 'End')) : ''));

		$sheet->getStyle('A1:C3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2F7E8A');
		$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('FFFFFF');
		$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$sheet->getStyle('A2:A3')->getFont()->setSize(10)->getColor()->setRGB('FFFFFF');
		$sheet->getStyle('A2:C3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		$rowNum = 6;
		$writeSection = function (string $title, array $rows) use ($sheet, &$rowNum) {
			$sheet->setCellValue("A$rowNum", $title);
			$sheet->getStyle("A$rowNum:C$rowNum")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
			$sheet->getStyle("A$rowNum:C$rowNum")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('3C5A82');
			$rowNum++;
			foreach ($rows as $r) {
				$sheet->setCellValue("A$rowNum", $r[0]);
				$sheet->setCellValue("B$rowNum", $r[1]);
				$sheet->setCellValue("C$rowNum", $r[2]);
				$sheet->getStyle("B$rowNum")->getNumberFormat()->setFormatCode('#,##0.00');
				$rowNum++;
			}
			$rowNum++;
		};

		$writeSection('ASSETS', [
			['Cash Received From Customers', $data['cash_received'], 'payments.amount + project_cash_receipts.amount'],
			['Accounts Receivable', $data['accounts_receivable'], 'sales.balance_amount (net of Project Cash)'],
			['Inventory Value', $data['inventory_value'], 'stock_ledger + purchase_items (avg cost)'],
			['TOTAL ASSETS', $data['total_assets'], ''],
		]);

		$writeSection('LIABILITIES', [
			['Customer Advance Credit', $data['advance_liability'], 'projects (unused advance + cash)'],
			['Supplier Outstanding', $data['supplier_outstanding'], $data['supplier_outstanding_available'] ? 'purchases' : 'Not tracked (unavailable)'],
			['Other Liabilities', $data['other_liabilities'], $data['other_liabilities_available'] ? '' : 'Not tracked (unavailable)'],
			['TOTAL LIABILITIES', $data['total_liabilities'], ''],
		]);

		$writeSection('EQUITY', [
			['Total Revenue', $data['total_revenue'], 'sales.total_amount'],
			['Net Profit', $data['net_profit'], 'Profit & Loss formula'],
			['Owner Capital', $data['owner_capital'], $data['owner_capital_available'] ? '' : 'Not tracked (unavailable)'],
			['TOTAL EQUITY', $data['total_equity'], ''],
		]);

		$sheet->setCellValue("A$rowNum", 'Assets = Liabilities + Equity');
		$rowNum++;
		$sheet->setCellValue("A$rowNum", $data['total_assets'] . ' = ' . $data['total_liabilities'] . ' + ' . $data['total_equity']
			. ($data['is_balanced'] ? ' (Balanced)' : ' (Partial Balance Sheet — Cash/Capital module not implemented; difference ' . $data['difference'] . ')'));

		foreach (range('A', 'C') as $col) {
			$sheet->getColumnDimension($col)->setAutoSize(true);
		}

		$fileName = 'balance_sheet_report_' . date('d-m-Y_H-i') . '.xlsx';

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header("Content-Disposition: attachment;filename=\"$fileName\"");
		header('Cache-Control: max-age=0');

		$writer = new Xlsx($spreadsheet);
		$writer->save('php://output');
		exit;
	}

	public function balanceSheetPdf()
	{
		$db = \Config\Database::connect();

		$projectIdRaw = $this->request->getGet('project_id');
		$projectId    = ($projectIdRaw !== null && $projectIdRaw !== '') ? (int) $projectIdRaw : null;
		$startDate    = $this->request->getGet('start_date') ?: null;
		$endDate      = $this->request->getGet('end_date') ?: null;

		$data = $this->getBalanceSheetData($db, new ProjectModel(), $projectId, $startDate, $endDate);

		$projectName = 'All Projects';
		if ($projectId) {
			$projectName = $db->query("SELECT name FROM projects WHERE id = ?", [$projectId])->getRow()->name ?? '';
		}

		$rowsHtml = function (array $rows) {
			$html = '';
			foreach ($rows as $r) {
				$html .= '<tr><td>' . esc($r[0]) . '</td><td class="right">' . number_format($r[1], 2) . '</td><td>' . esc($r[2]) . '</td></tr>';
			}
			return $html;
		};

		$html = '
			<style>
				body { font-family: DejaVu Sans; font-size: 11px; color: #2d3748; }
				.header-box { background: #2F7E8A; color: #fff; padding: 12px; border-radius: 6px; text-align: center; margin-bottom: 10px; }
				.header-box h2 { margin: 0; font-size: 16px; letter-spacing: 1px; }
				.meta { text-align: center; font-size: 10px; margin-top: 4px; color: #e2e8f0; }
				table { width: 100%; border-collapse: collapse; margin-top: 8px; }
				th { background: #3C5A82; color: #fff; padding: 7px; font-size: 11px; text-align:left; }
				td { padding: 6px; border: 1px solid #e2e8f0; }
				tr:nth-child(even) td { background: #f8fafc; }
				.right { text-align:right; }
				.section-title td { background:#edf2f7; font-weight:bold; font-size:12px; }
				.total-row td { background:#d9e1f2; font-weight:bold; }
				.balance-note { margin-top:10px; padding:8px; text-align:center; font-size:11px; font-weight:bold; border-radius:4px; }
				.balanced { background:#dcfce7; color:#166534; }
				.partial { background:#fef3c7; color:#92400e; }
			</style>
			<div class="header-box">
				<h2>BALANCE SHEET REPORT</h2>
				<div class="meta">
					Generated On: ' . date('d-m-Y h:i A') . '<br>
					Project: ' . esc($projectName) . ' | Period: ' . esc($startDate ?: 'Start') . ' to ' . esc($endDate ?: 'End') . '
				</div>
			</div>
			<table>
				<tr><th>Item</th><th>Amount</th><th>Source</th></tr>
				<tr class="section-title"><td colspan="3">ASSETS</td></tr>'
				. $rowsHtml([
					['Cash Received From Customers', $data['cash_received'], 'payments.amount + project_cash_receipts.amount'],
					['Accounts Receivable', $data['accounts_receivable'], 'sales.balance_amount (net of Project Cash)'],
					['Inventory Value', $data['inventory_value'], 'stock_ledger + purchase_items (avg cost)'],
				])
				. '<tr class="total-row"><td>TOTAL ASSETS</td><td class="right">' . number_format($data['total_assets'], 2) . '</td><td></td></tr>
				<tr class="section-title"><td colspan="3">LIABILITIES</td></tr>'
				. $rowsHtml([
					['Customer Advance Credit', $data['advance_liability'], 'projects (unused advance + cash)'],
					['Supplier Outstanding', $data['supplier_outstanding'], $data['supplier_outstanding_available'] ? 'purchases' : 'Not tracked (unavailable)'],
					['Other Liabilities', $data['other_liabilities'], $data['other_liabilities_available'] ? '' : 'Not tracked (unavailable)'],
				])
				. '<tr class="total-row"><td>TOTAL LIABILITIES</td><td class="right">' . number_format($data['total_liabilities'], 2) . '</td><td></td></tr>
				<tr class="section-title"><td colspan="3">EQUITY</td></tr>'
				. $rowsHtml([
					['Total Revenue', $data['total_revenue'], 'sales.total_amount'],
					['Net Profit', $data['net_profit'], 'Profit & Loss formula'],
					['Owner Capital', $data['owner_capital'], $data['owner_capital_available'] ? '' : 'Not tracked (unavailable)'],
				])
				. '<tr class="total-row"><td>TOTAL EQUITY</td><td class="right">' . number_format($data['total_equity'], 2) . '</td><td></td></tr>
			</table>
			<div class="balance-note ' . ($data['is_balanced'] ? 'balanced' : 'partial') . '">
				Assets (' . number_format($data['total_assets'], 2) . ') = Liabilities (' . number_format($data['total_liabilities'], 2) . ') + Equity (' . number_format($data['total_equity'], 2) . ')'
				. ($data['is_balanced'] ? '' : ' &mdash; Partial Balance Sheet (Cash/Capital module not implemented)') . '
			</div>';

		$options = new Options();
		$options->set('isRemoteEnabled', true);

		$dompdf = new Dompdf($options);
		$dompdf->loadHtml($html);
		$dompdf->setPaper('A4', 'portrait');
		$dompdf->render();

		$fileName = 'balance_sheet_report_' . date('d-m-Y_H-i') . '.pdf';

		$dompdf->stream($fileName, ["Attachment" => true]);
	}

    public function stock()
	{
		$db = \Config\Database::connect();

		$productId = $this->request->getGet('product_id');
		$productId = ($productId === 'all' || $productId === '' || $productId === null) ? null : (int)$productId;

		$whereProduct = ($productId !== null) ? " AND p.id = $productId" : "";

		$data['stock'] = $db->query("
			SELECT
				p.id, p.name, p.unit,
				COALESCE(SUM(CASE WHEN sl.source='GENERAL' AND sl.transaction_type='IN' THEN sl.quantity ELSE 0 END),0)
				- COALESCE(SUM(CASE WHEN sl.source='GENERAL' AND sl.transaction_type='OUT' THEN sl.quantity ELSE 0 END),0) AS general_stock,
				COALESCE(SUM(CASE WHEN sl.source='PROJECT' AND sl.transaction_type='IN' THEN sl.quantity ELSE 0 END),0)
				- COALESCE(SUM(CASE WHEN sl.source='PROJECT' AND sl.transaction_type='OUT' THEN sl.quantity ELSE 0 END),0) AS project_stock
			FROM products p
			LEFT JOIN stock_ledger sl ON sl.product_id = p.id
			WHERE 1=1 $whereProduct
			GROUP BY p.id, p.name, p.unit
			ORDER BY p.name DESC
		")->getResultArray();

		$data['products']   = $db->query("SELECT id,name FROM products ORDER BY name ASC")->getResultArray();
		$data['product_id'] = $productId;

		return view('reports/stock', $data);
	}
	
	public function stockExport()
	{
		$db = \Config\Database::connect();

		$productId = $this->request->getGet('product_id');
		$productId = ($productId === 'all' || $productId === '') ? null : $productId;
		$whereProduct = $productId ? " AND p.id = $productId" : "";

		$rows = $db->query("
			SELECT
				p.name, p.unit,
				COALESCE(SUM(CASE WHEN sl.source='GENERAL' AND sl.transaction_type='IN' THEN sl.quantity ELSE 0 END),0)
				- COALESCE(SUM(CASE WHEN sl.source='GENERAL' AND sl.transaction_type='OUT' THEN sl.quantity ELSE 0 END),0) AS general_stock,
				COALESCE(SUM(CASE WHEN sl.source='PROJECT' AND sl.transaction_type='IN' THEN sl.quantity ELSE 0 END),0)
				- COALESCE(SUM(CASE WHEN sl.source='PROJECT' AND sl.transaction_type='OUT' THEN sl.quantity ELSE 0 END),0) AS project_stock
			FROM products p
			LEFT JOIN stock_ledger sl ON sl.product_id = p.id
			WHERE 1=1 $whereProduct
			GROUP BY p.id, p.name, p.unit
			ORDER BY p.name ASC
		")->getResultArray();

		// ===== EXCEL =====
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		
		$sheet->freezePane('A5');

		$sheet->mergeCells('A1:E1');
		$sheet->setCellValue('A1', 'STOCK REPORT');

		$sheet->mergeCells('A2:E2');
		$sheet->setCellValue('A2', 'Generated On: ' . date('d-m-Y h:i A'));
		
		$sheet->getStyle('A1:E2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
			->getStartColor()->setRGB('2F7E8A');

		$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('FFFFFF');
		$sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

		$sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('FFFFFF');
		$sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

		// HEADER
		$sheet->setCellValue('A4', 'Product');
		$sheet->setCellValue('B4', 'Unit');
		$sheet->setCellValue('C4', 'General Stock');
		$sheet->setCellValue('D4', 'Project Stock');
		$sheet->setCellValue('E4', 'Total');
		
		$sheet->getStyle('A4:E4')->getFont()
			->setBold(true)
			->setSize(11)
			->getColor()->setRGB('FFFFFF');

		$sheet->getStyle('A4:E4')->getAlignment()
			->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

		$sheet->getStyle('A4:E4')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
			->getStartColor()->setRGB('3C5A82');

		$sheet->getStyle('A4:E4')->getBorders()->getBottom()
			->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

		$rowNum = 5;

		foreach ($rows as $r) {
			$total = $r['general_stock'] + $r['project_stock'];

			$sheet->setCellValue('A'.$rowNum, $r['name']);
			$sheet->setCellValue('B'.$rowNum, $r['unit']);
			$sheet->setCellValue('C'.$rowNum, $r['general_stock']);
			$sheet->setCellValue('D'.$rowNum, $r['project_stock']);
			$sheet->setCellValue('E'.$rowNum, $total);

			$rowNum++;
		}
		
		$lastRow = $rowNum - 1;
		
		if ($lastRow < 5) {
			$lastRow = 5;
		}

		$sheet->getStyle("A4:E{$lastRow}")
			->getBorders()->getAllBorders()
			->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

		for ($i = 5; $i <= $lastRow; $i++) {
			$sheet->getStyle("C$i:E$i")->getNumberFormat()->setFormatCode('#,##0');
			$sheet->getStyle("C$i:E$i")->getAlignment()
				->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
			$sheet->getStyle("B$i")->getAlignment()
				->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
			$sheet->getStyle("A$i")->getFont()->setBold(true);
			
			// ===== STOCK HIGHLIGHT =====
			$total = $sheet->getCell("E$i")->getValue();

			if ($total < 0) {
				$sheet->getStyle("E$i")->getFont()->getColor()->setRGB('9C6500');
				$sheet->getStyle("E$i")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
					->getStartColor()->setRGB('FCE4D6');
			}
			elseif ($total == 0) {
				$sheet->getStyle("E$i")->getFont()->getColor()->setRGB('C00000');
				$sheet->getStyle("E$i")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
					->getStartColor()->setRGB('FFF2CC');
			}
			else {
				$sheet->getStyle("E$i")->getFont()->getColor()->setRGB('006100');
			}
		}
		
		for ($i = 5; $i <= $lastRow; $i++) {
			if ($i % 2 == 0) {
				$sheet->getStyle("A$i:E$i")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
					->getStartColor()->setRGB('F7FBFC');
			}
		}
		
		$totalRow = $lastRow + 1;

		$sheet->setCellValue("A$totalRow", 'TOTAL');
		$sheet->getStyle("A$totalRow")->getAlignment()
    		->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
		$sheet->setCellValue("C$totalRow", "=SUM(C5:C$lastRow)");
		$sheet->setCellValue("D$totalRow", "=SUM(D5:D$lastRow)");
		$sheet->setCellValue("E$totalRow", "=SUM(E5:E$lastRow)");

		$sheet->getStyle("A$totalRow:E$totalRow")->getFont()->setBold(true);
		$sheet->getStyle("A$totalRow:E$totalRow")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
			->getStartColor()->setRGB('D9E1F2');

		$sheet->getStyle("A$totalRow:E$totalRow")->getBorders()->getTop()
			->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
		
		$sheet->getStyle("C$totalRow:E$totalRow")
    		->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

		foreach (range('A','E') as $col) {
			$sheet->getColumnDimension($col)->setAutoSize(true);
		}

		$fileName = 'stock_report_' . date('d-m-Y_H-i') . '.xlsx';

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header("Content-Disposition: attachment;filename=\"$fileName\"");

		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
		$writer->save('php://output');
		exit;
	}
	
	public function stockPdf()
	{
		$db = \Config\Database::connect();

		$productId = $this->request->getGet('product_id');
		$whereProduct = $productId ? " AND p.id = $productId" : "";

		$rows = $db->query("
			SELECT
				p.name, p.unit,
				COALESCE(SUM(CASE WHEN sl.source='GENERAL' AND sl.transaction_type='IN' THEN sl.quantity ELSE 0 END),0)
				- COALESCE(SUM(CASE WHEN sl.source='GENERAL' AND sl.transaction_type='OUT' THEN sl.quantity ELSE 0 END),0) AS general_stock,
				COALESCE(SUM(CASE WHEN sl.source='PROJECT' AND sl.transaction_type='IN' THEN sl.quantity ELSE 0 END),0)
				- COALESCE(SUM(CASE WHEN sl.source='PROJECT' AND sl.transaction_type='OUT' THEN sl.quantity ELSE 0 END),0) AS project_stock
			FROM products p
			LEFT JOIN stock_ledger sl ON sl.product_id = p.id
			WHERE 1=1 $whereProduct
			GROUP BY p.id, p.name, p.unit
			ORDER BY p.name ASC
		")->getResultArray();

		// ===== HTML =====
		$html = '
			<style>
				body {
					font-family: DejaVu Sans;
					font-size: 11px;
					color: #2d3748;
				}

				.header-box {
					background: #2F7E8A;
					color: #fff;
					padding: 12px;
					border-radius: 6px;
					text-align: center;
					margin-bottom: 10px;
				}

				.header-box h2 {
					margin: 0;
					font-size: 16px;
					letter-spacing: 1px;
				}

				.meta {
					text-align: center;
					font-size: 10px;
					margin-top: 4px;
					color: #e2e8f0;
				}

				table {
					width: 100%;
					border-collapse: collapse;
					margin-top: 8px;
				}

				th {
					background: #3C5A82;
					color: #fff;
					padding: 7px;
					font-size: 11px;
				}

				td {
					padding: 6px;
					border: 1px solid #e2e8f0;
				}

				tr:nth-child(even) td {
					background: #f8fafc;
				}

				.center { text-align: center; }
				.right { text-align: right; }

				.total-row {
					background: #edf2f7;
					font-weight: bold;
					font-size: 12px;
				}

				.negative { color: #c62828; font-weight: bold; }
				.zero { color: #c62828; font-weight: bold; }
				.positive { color: #2e7d32; font-weight: bold; }

				.footer-note {
					margin-top: 10px;
					font-size: 9px;
					text-align: right;
					color: #888;
				}
			</style>

			<div class="header-box">
				<h2>STOCK REPORT</h2>
				<div class="meta">
					Generated On: '.date('d-m-Y h:i A').'
				</div>
			</div>

		<table>
			<tr>
				<th>Product</th>
				<th>Unit</th>
				<th>General</th>
				<th>Project</th>
				<th>Total</th>
			</tr>
		';

		$totalGen = $totalProj = 0;

		foreach ($rows as $r) {
			$total = $r['general_stock'] + $r['project_stock'];

			$class = $total < 0 ? 'negative' : ($total == 0 ? 'zero' : 'positive');

			$html .= '
			<tr>
				<td><strong>'.$r['name'].'</strong></td>
				<td class="center">'.$r['unit'].'</td>
				<td class="center">'.number_format($r['general_stock']).'</td>
				<td class="center">'.number_format($r['project_stock']).'</td>
				<td class="center '.$class.'">'.number_format($total).'</td>
			</tr>';

			$totalGen += $r['general_stock'];
			$totalProj += $r['project_stock'];
		}

		$grand = $totalGen + $totalProj;

		$html .= '
			<tr class="total-row">
				<td>TOTAL</td>
				<td></td>
				<td class="center">'.number_format($totalGen).'</td>
				<td class="center">'.number_format($totalProj).'</td>
				<td class="center">'.number_format($grand).'</td>
			</tr>
		</table>';

		// ===== DOMPDF =====
		$options = new \Dompdf\Options();
		$options->set('isRemoteEnabled', true);

		$dompdf = new \Dompdf\Dompdf($options);
		$dompdf->loadHtml($html);
		$dompdf->setPaper('A4', 'portrait');
		$dompdf->render();

		$fileName = 'stock_report_' . date('d-m-Y_H-i') . '.pdf';

		$dompdf->stream($fileName, ["Attachment" => true]);
	}

    public function sales()
	{
		$db = \Config\Database::connect();

		$projectId     = $this->request->getGet('project_id');
		$customerId    = $this->request->getGet('customer_id');
		$status        = $this->request->getGet('status');
		$startDate     = $this->request->getGet('start_date');
		$endDate       = $this->request->getGet('end_date');
		$paymentMethod = $this->request->getGet('payment_method');
		$search        = $this->request->getGet('search');

		$where  = "WHERE 1=1";
		$params = [];

		if (!empty($projectId)) {
			$where .= " AND s.project_id = $projectId";
		}

		if (!empty($customerId)) {
			$where .= " AND s.customer_id = $customerId";
		}

		if (!empty($status)) {
			$where   .= " AND s.status = ?";
			$params[] = $status;
		}

		if (!empty($startDate)) {
			$where   .= " AND s.sale_date >= ?";
			$params[] = $startDate;
		}

		if (!empty($endDate)) {
			$where   .= " AND s.sale_date <= ?";
			$params[] = $endDate;
		}

		if (!empty($paymentMethod)) {
			$where   .= " AND EXISTS (SELECT 1 FROM payments pm WHERE pm.sale_id = s.id AND pm.method = ?)";
			$params[] = $paymentMethod;
		}

		if (!empty($search)) {
			$where   .= " AND s.invoice_no LIKE ?";
			$params[] = '%' . $search . '%';
		}

		$data['sales'] = $db->query("
			SELECT s.*, p.name AS project_name, c.name AS customer_name
			FROM sales s
			LEFT JOIN projects p ON s.project_id = p.id
			LEFT JOIN customers c ON s.customer_id = c.id
			$where
			ORDER BY s.sale_date DESC
		", $params)->getResultArray();

		// KPI totals — pure aggregation of the already-fetched rows
		$data['kpi_total_sales']       = 0;
		$data['kpi_customer_received'] = 0;
		$data['kpi_customer_pending']  = 0;
		$data['kpi_total_invoices']    = count($data['sales']);

		foreach ($data['sales'] as $s) {
			$data['kpi_total_sales']       += $s['total_amount'];
			$data['kpi_customer_received'] += $s['paid_amount'];
			$data['kpi_customer_pending']  += $s['balance_amount'];
		}

		// dropdown data
		$data['projects']  = $db->query("SELECT id,name FROM projects ORDER BY name ASC")->getResultArray();
		$data['customers'] = $db->query("SELECT id,name FROM customers ORDER BY name ASC")->getResultArray();

		// keep selected
		$data['project_id']     = $projectId;
		$data['customer_id']    = $customerId;
		$data['status']         = $status;
		$data['start_date']     = $startDate;
		$data['end_date']       = $endDate;
		$data['payment_method'] = $paymentMethod;
		$data['search']         = $search;

		return view('reports/sales', $data);
	}
	
	public function salesExport()
	{
		$db = \Config\Database::connect();

		$projectId  = $this->request->getGet('project_id');
		$customerId = $this->request->getGet('customer_id');
		$status     = $this->request->getGet('status');

		$where = "WHERE 1=1";

		if (!empty($projectId)) {
			$where .= " AND s.project_id = $projectId";
		}

		if (!empty($customerId)) {
			$where .= " AND s.customer_id = $customerId";
		}

		if (!empty($status)) {
			$where .= " AND s.status = '$status'";
		}

		$rows = $db->query("
			SELECT s.*, p.name AS project_name, c.name AS customer_name
			FROM sales s
			LEFT JOIN projects p ON s.project_id = p.id
			LEFT JOIN customers c ON s.customer_id = c.id
			$where
			ORDER BY s.sale_date DESC
		")->getResultArray();

		// ===== EXCEL =====
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();

		$sheet->freezePane('A7');

		// ===== TITLE =====
		$sheet->mergeCells('A1:J1');
		$sheet->setCellValue('A1', 'SALES REPORT');

		$sheet->mergeCells('A2:J2');
		$sheet->setCellValue('A2', 'Generated On: ' . date('d-m-Y h:i A'));

		// ===== PROJECT =====
		$projectName = 'All Projects';
		if (!empty($projectId)) {
			$projectName = $db->query("SELECT name FROM projects WHERE id = ?", [$projectId])->getRow()->name ?? '';
		}

		$sheet->mergeCells('A3:J3');
		$sheet->setCellValue('A3', 'Project: ' . $projectName);

		// ===== HEADER STYLE =====
		$sheet->getStyle('A1:J3')->getFill()
			->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
			->getStartColor()->setRGB('2F7E8A');

		$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('FFFFFF');
		$sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

		$sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('FFFFFF');
		$sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

		$sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
		$sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

		// ===== TABLE HEADER =====
		$sheet->setCellValue('A6', '#');
		$sheet->setCellValue('B6', 'Invoice');
		$sheet->setCellValue('C6', 'Project');
		$sheet->setCellValue('D6', 'Customer');
		$sheet->setCellValue('E6', 'Source');
		$sheet->setCellValue('F6', 'Date');
		$sheet->setCellValue('G6', 'Total');
		$sheet->setCellValue('H6', 'Paid');
		$sheet->setCellValue('I6', 'Balance');
		$sheet->setCellValue('J6', 'Status');

		$sheet->getStyle('A6:J6')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
		$sheet->getStyle('A6:J6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

		$sheet->getStyle('A6:J6')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
			->getStartColor()->setRGB('3C5A82');

		// ===== DATA =====
		$rowNum = 7;
		$i = 1;

		$total = $paid = $balance = 0;

		foreach ($rows as $r) {

			$sheet->setCellValue('A'.$rowNum, $i++);
			$sheet->setCellValue('B'.$rowNum, $r['invoice_no']);
			$sheet->setCellValue('C'.$rowNum, $r['project_name']);
			$sheet->setCellValue('D'.$rowNum, $r['customer_name']);
			$sheet->setCellValue('E'.$rowNum, $r['stock_source']);
			$sheet->setCellValue('F'.$rowNum, $r['sale_date']);
			$sheet->setCellValue('G'.$rowNum, $r['total_amount']);
			$sheet->setCellValue('H'.$rowNum, $r['paid_amount']);
			$sheet->setCellValue('I'.$rowNum, $r['balance_amount']);
			$sheet->setCellValue('J'.$rowNum, $r['status']);

			$total += $r['total_amount'];
			$paid += $r['paid_amount'];
			$balance += $r['balance_amount'];

			$rowNum++;
		}

		$lastRow = $rowNum - 1;

		// ===== TOTAL ROW =====
		$totalRow = $lastRow + 1;

		$sheet->setCellValue("A$totalRow", 'TOTAL');
		$sheet->mergeCells("A$totalRow:F$totalRow");

		$sheet->setCellValue("G$totalRow", $total);
		$sheet->setCellValue("H$totalRow", $paid);
		$sheet->setCellValue("I$totalRow", $balance);

		$sheet->getStyle("A$totalRow:J$totalRow")->getFont()->setBold(true);
		$sheet->getStyle("A$totalRow:J$totalRow")->getFill()
			->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
			->getStartColor()->setRGB('D9E1F2');

		// ===== BORDER =====
		$sheet->getStyle("A6:J{$totalRow}")
			->getBorders()->getAllBorders()
			->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

		// ===== ZEBRA =====
		for ($r = 7; $r <= $lastRow; $r++) {
			if ($r % 2 == 0) {
				$sheet->getStyle("A$r:J$r")->getFill()
					->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
					->getStartColor()->setRGB('F7FBFC');
			}
		}

		// ===== ALIGN =====
		$sheet->getStyle("G7:I{$totalRow}")
			->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

		foreach (range('A','J') as $col) {
			$sheet->getColumnDimension($col)->setAutoSize(true);
		}

		$fileName = 'sales_report_' . date('d-m-Y_H-i') . '.xlsx';

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header("Content-Disposition: attachment;filename=\"$fileName\"");

		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
		$writer->save('php://output');
		exit;
	}
	
	public function salesPdf()
	{
		$db = \Config\Database::connect();

		$projectId  = $this->request->getGet('project_id');
		$customerId = $this->request->getGet('customer_id');
		$status     = $this->request->getGet('status');

		$where = "WHERE 1=1";

		if (!empty($projectId)) {
			$where .= " AND s.project_id = $projectId";
		}

		if (!empty($customerId)) {
			$where .= " AND s.customer_id = $customerId";
		}

		if (!empty($status)) {
			$where .= " AND s.status = '$status'";
		}

		$rows = $db->query("
			SELECT s.*, p.name AS project_name, c.name AS customer_name
			FROM sales s
			LEFT JOIN projects p ON s.project_id = p.id
			LEFT JOIN customers c ON s.customer_id = c.id
			$where
			ORDER BY s.sale_date DESC
		")->getResultArray();

		// ===== PROJECT NAME =====
		$projectName = 'All Projects';
		if (!empty($projectId)) {
			$projectName = $db->query("SELECT name FROM projects WHERE id = ?", [$projectId])->getRow()->name ?? '';
		}

		// ===== HTML =====
		$html = '
			<style>
				body {
					font-family: DejaVu Sans;
					font-size: 11px;
					color: #2d3748;
				}

				.header-box {
					background: #2F7E8A;
					color: #fff;
					padding: 12px;
					border-radius: 6px;
					text-align: center;
					margin-bottom: 10px;
				}

				.header-box h2 {
					margin: 0;
					font-size: 16px;
					letter-spacing: 1px;
				}

				.meta {
					text-align: center;
					font-size: 10px;
					margin-top: 4px;
					color: #e2e8f0;
				}

				.project {
					text-align: center;
					font-size: 11px;
					font-weight: bold;
					color: #2F7E8A;
					margin: 10px 0;
				}

				table {
					width: 100%;
					border-collapse: collapse;
					margin-top: 5px;
				}

				th {
					background: #3C5A82;
					color: #fff;
					padding: 7px;
					font-size: 11px;
					letter-spacing: 0.5px;
				}

				td {
					padding: 6px;
					border: 1px solid #e2e8f0;
				}

				tr:nth-child(even) td {
					background: #f8fafc;
				}

				.right { text-align: right; }
				.center { text-align: center; }

				.paid { color: #2e7d32; font-weight: bold; }
				.pending { color: #c62828; font-weight: bold; }
				.partial { color: #ef6c00; font-weight: bold; }

				.total-row {
					background: #edf2f7;
					font-weight: bold;
					font-size: 12px;
				}

				.footer-note {
					margin-top: 10px;
					font-size: 9px;
					text-align: right;
					color: #888;
				}
			</style>

			<div class="header-box">
				<h2>SALES REPORT</h2>
				<div class="meta">
					Generated On: '.date('d-m-Y h:i A').'
				</div>
			</div>

			<div class="project">
				Project: '.$projectName.'
			</div>

		<table>
			<tr>
				<th>#</th>
				<th>Invoice</th>
				<th>Project</th>
				<th>Customer</th>
				<th>Source</th>
				<th>Date</th>
				<th>Total</th>
				<th>Paid</th>
				<th>Balance</th>
				<th>Status</th>
			</tr>
		';

		$i = 1;
		$grandTotal = $grandPaid = $grandBalance = 0;

		foreach ($rows as $r) {

			$statusClass = strtolower($r['status']);

			$grandTotal += $r['total_amount'];
			$grandPaid  += $r['paid_amount'];
			$grandBalance += $r['balance_amount'];

			$html .= '
			<tr>
				<td class="center">'.$i++.'</td>
				<td>'.$r['invoice_no'].'</td>
				<td>'.$r['project_name'].'</td>
				<td>'.$r['customer_name'].'</td>
				<td class="center">'.$r['stock_source'].'</td>
				<td>'.$r['sale_date'].'</td>
				<td class="right">'.number_format($r['total_amount'],2).'</td>
				<td class="right">'.number_format($r['paid_amount'],2).'</td>
				<td class="right">'.number_format($r['balance_amount'],2).'</td>
				<td class="center '.$statusClass.'">'.$r['status'].'</td>
			</tr>';
		}

		// ===== TOTAL =====
		$html .= '
			<tr class="total-row">
				<td colspan="6">TOTAL</td>
				<td class="right">'.number_format($grandTotal,2).'</td>
				<td class="right">'.number_format($grandPaid,2).'</td>
				<td class="right">'.number_format($grandBalance,2).'</td>
				<td></td>
			</tr>
		</table>';

		// ===== DOMPDF =====
		$options = new \Dompdf\Options();
		$options->set('isRemoteEnabled', true);

		$dompdf = new \Dompdf\Dompdf($options);
		$dompdf->loadHtml($html);
		$dompdf->setPaper('A4', 'landscape'); // better fit
		$dompdf->render();

		$fileName = 'sales_report_' . date('d-m-Y_H-i') . '.pdf';

		$dompdf->stream($fileName, ["Attachment" => true]);
	}

    public function purchases()
	{
		$db = \Config\Database::connect();

		$projectId   = $this->request->getGet('project_id');
		$supplierId  = $this->request->getGet('supplier_id');
		$startDate   = $this->request->getGet('start_date');
		$endDate     = $this->request->getGet('end_date');
		$stockSource = $this->request->getGet('stock_source');
		$search      = $this->request->getGet('search');

		$where  = "WHERE 1=1";
		$params = [];

		if (!empty($projectId)) {
			$where .= " AND pu.project_id = $projectId";
		}

		if (!empty($supplierId)) {
			$where .= " AND pu.supplier_id = $supplierId";
		}

		if (!empty($startDate)) {
			$where   .= " AND pu.purchase_date >= ?";
			$params[] = $startDate;
		}

		if (!empty($endDate)) {
			$where   .= " AND pu.purchase_date <= ?";
			$params[] = $endDate;
		}

		if (!empty($search)) {
			$where .= " AND (pu.invoice_no LIKE ? OR EXISTS (
				SELECT 1 FROM purchase_items pi
				JOIN products pr ON pi.product_id = pr.id
				WHERE pi.purchase_id = pu.id AND pr.name LIKE ?
			))";
			$params[] = '%' . $search . '%';
			$params[] = '%' . $search . '%';
		}

		$rows = $db->query("
			SELECT pu.*, s.name AS supplier_name, p.name AS project_name
			FROM purchases pu
			LEFT JOIN suppliers s ON pu.supplier_id = s.id
			LEFT JOIN projects p ON pu.project_id = p.id
			$where
			ORDER BY pu.purchase_date DESC
		", $params)->getResultArray();

		// per-purchase quantity split, already stored on purchase_items (Release 1.6B allocation)
		$qtyByPurchase = [];
		foreach ($db->query("
			SELECT purchase_id, SUM(quantity) AS purchased_qty, SUM(project_qty) AS project_qty, SUM(general_qty) AS general_qty
			FROM purchase_items
			GROUP BY purchase_id
		")->getResultArray() as $q) {
			$qtyByPurchase[$q['purchase_id']] = $q;
		}

		$projectModel = new ProjectModel();

		foreach ($rows as &$r) {
			$qty = $qtyByPurchase[$r['id']] ?? ['purchased_qty' => 0, 'project_qty' => 0, 'general_qty' => 0];

			$r['purchased_qty'] = (float) $qty['purchased_qty'];
			$r['project_qty']   = (float) $qty['project_qty'];
			$r['general_qty']   = (float) $qty['general_qty'];

			// same PROJECT/GENERAL/MIXED derivation already used in purchases/view.php
			$r['source_label'] = $r['general_qty'] == 0 ? 'PROJECT' : ($r['project_qty'] == 0 ? 'GENERAL' : 'MIXED');

			$r['project_allocated_amount'] = $projectModel->getAllocatedPurchaseCostForPurchase((int) $r['id']);
		}
		unset($r);

		if (!empty($stockSource)) {
			$rows = array_values(array_filter($rows, fn ($r) => $r['source_label'] === $stockSource));
		}

		$data['purchases'] = $rows;

		// KPI totals — pure aggregation of the already-fetched/derived rows
		$data['kpi_total_purchases']    = 0;
		$data['kpi_project_allocation'] = 0;
		$data['kpi_supplier_count']     = count(array_unique(array_column($rows, 'supplier_id')));

		foreach ($rows as $r) {
			$data['kpi_total_purchases']    += $r['total_amount'];
			$data['kpi_project_allocation'] += $r['project_allocated_amount'];
		}
		$data['kpi_general_allocation'] = $data['kpi_total_purchases'] - $data['kpi_project_allocation'];

		// dropdowns
		$data['projects']  = $db->query("SELECT id,name FROM projects ORDER BY name ASC")->getResultArray();
		$data['suppliers'] = $db->query("SELECT id,name FROM suppliers ORDER BY name ASC")->getResultArray();

		// selected values
		$data['project_id']   = $projectId;
		$data['supplier_id']  = $supplierId;
		$data['start_date']   = $startDate;
		$data['end_date']     = $endDate;
		$data['stock_source'] = $stockSource;
		$data['search']       = $search;

		return view('reports/purchases', $data);
	}
	
	public function purchasesExport()
	{
		$db = \Config\Database::connect();

		$projectId  = $this->request->getGet('project_id');
		$supplierId = $this->request->getGet('supplier_id');

		$where = "WHERE 1=1";

		if (!empty($projectId)) {
			$where .= " AND pu.project_id = $projectId";
		}

		if (!empty($supplierId)) {
			$where .= " AND pu.supplier_id = $supplierId";
		}

		$rows = $db->query("
			SELECT pu.*, s.name AS supplier_name, p.name AS project_name
			FROM purchases pu
			LEFT JOIN suppliers s ON pu.supplier_id = s.id
			LEFT JOIN projects p ON pu.project_id = p.id
			$where
			ORDER BY pu.purchase_date DESC
		")->getResultArray();

		// ===== PROJECT NAME =====
		$projectName = 'All Projects';
		if (!empty($projectId)) {
			$projectName = $db->query("SELECT name FROM projects WHERE id = ?", [$projectId])->getRow()->name ?? '';
		}

		// ===== EXCEL =====
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();

		$sheet->freezePane('A6');

		// ===== TITLE =====
		$sheet->mergeCells('A1:G1');
		$sheet->setCellValue('A1', 'PURCHASE REPORT');

		$sheet->mergeCells('A2:G2');
		$sheet->setCellValue('A2', 'Generated On: ' . date('d-m-Y h:i A'));

		$sheet->mergeCells('A3:G3');
		$sheet->setCellValue('A3', 'Project: ' . $projectName);

		// ===== HEADER STYLE =====
		$sheet->getStyle('A1:G3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
			->getStartColor()->setRGB('2F7E8A');

		$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('FFFFFF');
		$sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

		$sheet->getStyle('A2:A3')->getFont()->setSize(10)->getColor()->setRGB('FFFFFF');
		$sheet->getStyle('A2:A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

		// ===== TABLE HEADER =====
		$sheet->setCellValue('A5', '#');
		$sheet->setCellValue('B5', 'Invoice');
		$sheet->setCellValue('C5', 'Supplier');
		$sheet->setCellValue('D5', 'Project');
		$sheet->setCellValue('E5', 'Source');
		$sheet->setCellValue('F5', 'Date');
		$sheet->setCellValue('G5', 'Total');

		$sheet->getStyle('A5:G5')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
		$sheet->getStyle('A5:G5')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

		$sheet->getStyle('A5:G5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
			->getStartColor()->setRGB('3C5A82');

		// ===== DATA =====
		$rowNum = 6;
		$i = 1;
		$grand = 0;

		foreach ($rows as $r) {

			$grand += $r['total_amount'];

			$sheet->setCellValue('A'.$rowNum, $i++);
			$sheet->setCellValue('B'.$rowNum, $r['invoice_no']);
			$sheet->setCellValue('C'.$rowNum, $r['supplier_name']);
			$sheet->setCellValue('D'.$rowNum, $r['project_name']);
			$sheet->setCellValue('E'.$rowNum, 'PROJECT');
			$sheet->setCellValue('F'.$rowNum, $r['purchase_date']);
			$sheet->setCellValue('G'.$rowNum, $r['total_amount']);

			// ALIGN
			$sheet->getStyle('A'.$rowNum.':F'.$rowNum)
				->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

			$sheet->getStyle('G'.$rowNum)
				->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

			$rowNum++;
		}

		$lastRow = $rowNum - 1;

		// ===== TOTAL =====
		$totalRow = $lastRow + 1;

		$sheet->setCellValue("A$totalRow", 'TOTAL');
		$sheet->mergeCells("A$totalRow:F$totalRow");
		$sheet->setCellValue("G$totalRow", $grand);

		$sheet->getStyle("A$totalRow:G$totalRow")->getFont()->setBold(true);
		$sheet->getStyle("A$totalRow:G$totalRow")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
			->getStartColor()->setRGB('D9E1F2');

		// ===== BORDER =====
		$sheet->getStyle("A5:G{$lastRow}")
			->getBorders()->getAllBorders()
			->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

		// ===== ZEBRA =====
		for ($r = 6; $r <= $lastRow; $r++) {
			if ($r % 2 == 0) {
				$sheet->getStyle("A$r:G$r")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
					->getStartColor()->setRGB('F7FBFC');
			}
		}

		// ===== AUTO SIZE =====
		foreach (range('A','G') as $col) {
			$sheet->getColumnDimension($col)->setAutoSize(true);
		}

		// ===== DOWNLOAD =====
		$fileName = 'purchase_report_' . date('d-m-Y_H-i') . '.xlsx';

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header("Content-Disposition: attachment;filename=\"$fileName\"");

		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
		$writer->save('php://output');
		exit;
	}
	
	public function purchasesPdf()
	{
		$db = \Config\Database::connect();

		$projectId  = $this->request->getGet('project_id');
		$supplierId = $this->request->getGet('supplier_id');

		$where = "WHERE 1=1";

		if (!empty($projectId)) {
			$where .= " AND pu.project_id = $projectId";
		}

		if (!empty($supplierId)) {
			$where .= " AND pu.supplier_id = $supplierId";
		}

		$rows = $db->query("
			SELECT pu.*, s.name AS supplier_name, p.name AS project_name
			FROM purchases pu
			LEFT JOIN suppliers s ON pu.supplier_id = s.id
			LEFT JOIN projects p ON pu.project_id = p.id
			$where
			ORDER BY pu.purchase_date DESC
		")->getResultArray();

		// ===== PROJECT NAME =====
		$projectName = 'All Projects';
		if (!empty($projectId)) {
			$projectName = $db->query("SELECT name FROM projects WHERE id = ?", [$projectId])->getRow()->name ?? '';
		}

		// ===== HTML =====
		$html = '
			<style>
				body {
					font-family: DejaVu Sans;
					font-size: 11px;
					color: #2d3748;
				}

				.header-box {
					background: #2F7E8A;
					color: #fff;
					padding: 12px;
					border-radius: 6px;
					text-align: center;
					margin-bottom: 10px;
				}

				.header-box h2 {
					margin: 0;
					font-size: 16px;
					letter-spacing: 1px;
				}

				.meta {
					text-align: center;
					font-size: 10px;
					margin-top: 4px;
					color: #e2e8f0;
				}

				.project {
					text-align: center;
					font-size: 11px;
					font-weight: bold;
					color: #2F7E8A;
					margin: 10px 0;
				}

				table {
					width: 100%;
					border-collapse: collapse;
					margin-top: 5px;
				}

				th {
					background: #3C5A82;
					color: #fff;
					padding: 7px;
					font-size: 11px;
					letter-spacing: 0.5px;
				}

				td {
					padding: 6px;
					border: 1px solid #e2e8f0;
				}

				tr:nth-child(even) td {
					background: #f8fafc;
				}

				.right { text-align: right; }
				.center { text-align: center; }

				.total-row {
					background: #edf2f7;
					font-weight: bold;
					font-size: 12px;
				}

				.footer-note {
					margin-top: 10px;
					font-size: 9px;
					text-align: right;
					color: #888;
				}
			</style>

			<div class="header-box">
				<h2>PURCHASE REPORT</h2>
				<div class="meta">
					Generated On: '.date('d-m-Y h:i A').'
				</div>
			</div>

			<div class="project">
				Project: '.$projectName.'
			</div>';

		$html .= '

		<table>
			<tr>
				<th>#</th>
				<th>Invoice</th>
				<th>Supplier</th>
				<th>Project</th>
				<th>Source</th>
				<th>Date</th>
				<th>Total</th>
			</tr>
		';

		$i = 1;
		$grand = 0;

		foreach ($rows as $r) {

			$grand += $r['total_amount'];

			$html .= '
			<tr>
				<td class="center">'.$i++.'</td>
				<td>'.$r['invoice_no'].'</td>
				<td>'.$r['supplier_name'].'</td>
				<td>'.$r['project_name'].'</td>
				<td class="center">PROJECT</td>
				<td>'.$r['purchase_date'].'</td>
				<td class="right">'.number_format($r['total_amount'],2).'</td>
			</tr>';
		}

		// ===== TOTAL =====
		$html .= '
			<tr class="total-row">
				<td colspan="6">TOTAL</td>
				<td class="right">'.number_format($grand,2).'</td>
			</tr>
		</table>';

		// ===== DOMPDF =====
		$options = new \Dompdf\Options();
		$options->set('isRemoteEnabled', true);

		$dompdf = new \Dompdf\Dompdf($options);
		$dompdf->loadHtml($html);
		$dompdf->setPaper('A4', 'landscape'); // better layout
		$dompdf->render();

		$fileName = 'purchase_report_' . date('d-m-Y_H-i') . '.pdf';

		$dompdf->stream($fileName, ["Attachment" => true]);
	}

    public function ledger()
    {
        $db        = \Config\Database::connect();
		
        $productId = $this->request->getGet('product_id');
		$projectId = $this->request->getGet('project_id');

		$whereArr = [];

		if ($productId) {
			$whereArr[] = "sl.product_id = $productId";
		}

		if ($projectId === 'general') {
			$whereArr[] = "sl.project_id IS NULL";
		} elseif ($projectId) {
			$whereArr[] = "sl.project_id = $projectId";
		}

		$where = !empty($whereArr) ? 'WHERE ' . implode(' AND ', $whereArr) : '';
		
		$data['projects']   = $db->query("SELECT id, name FROM projects ORDER BY name ASC")->getResultArray();
		$data['project_id'] = $projectId;

        $data['ledger'] = $db->query("
            SELECT sl.*, p.name AS product_name, pr.name AS project_name
            FROM stock_ledger sl
            LEFT JOIN products p ON sl.product_id = p.id
            LEFT JOIN projects pr ON sl.project_id = pr.id
            $where
            ORDER BY sl.created_at DESC
        ")->getResultArray();

        $data['products']   = \Config\Database::connect()->query("SELECT id, name FROM products ORDER BY name ASC")->getResultArray();
        $data['product_id'] = $productId;

        return view('reports/ledger', $data);
    }
	
	public function ledgerExport()
	{
		$db = \Config\Database::connect();

		$productId = $this->request->getGet('product_id');
		$projectId = $this->request->getGet('project_id');

		// ===== FILTER FIX =====
		$productId = ($productId === '' || $productId === null) ? null : (int)$productId;

		$whereArr = [];

		if ($productId !== null) {
			$whereArr[] = "sl.product_id = $productId";
		}

		if ($projectId === 'general') {
			$whereArr[] = "sl.project_id IS NULL";
		} elseif (!empty($projectId)) {
			$whereArr[] = "sl.project_id = $projectId";
		}

		$where = !empty($whereArr) ? 'WHERE ' . implode(' AND ', $whereArr) : '';

		$rows = $db->query("
			SELECT sl.*, p.name AS product_name, pr.name AS project_name
			FROM stock_ledger sl
			LEFT JOIN products p ON sl.product_id = p.id
			LEFT JOIN projects pr ON sl.project_id = pr.id
			$where
			ORDER BY sl.created_at DESC
		")->getResultArray();

		// ===== EXCEL =====
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();

		$sheet->freezePane('A7');

		// ===== TITLE =====
		$sheet->mergeCells('A1:I1');
		$sheet->setCellValue('A1', 'STOCK LEDGER REPORT');

		$sheet->mergeCells('A2:I2');
		$sheet->setCellValue('A2', 'Generated On: ' . date('d-m-Y h:i A'));

		// ===== PROJECT NAME =====
		$projectName = 'All Projects';

		if ($projectId === 'general') {
			$projectName = 'GENERAL PRODUCTS';
		} elseif (!empty($projectId)) {
			$projectName = $db->query("SELECT name FROM projects WHERE id = ?", [$projectId])
							  ->getRow()->name ?? '';
		}

		$sheet->mergeCells('A3:I3');
		$sheet->setCellValue('A3', 'Project: ' . $projectName);

		// ===== HEADER STYLE =====
		$sheet->getStyle('A1:I3')->getFill()
			->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
			->getStartColor()->setRGB('2F7E8A');

		$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('FFFFFF');
		$sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

		$sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('FFFFFF');
		$sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
		
		$sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
		$sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

		// ===== TABLE HEADER =====
		$sheet->setCellValue('A6', '#');
		$sheet->setCellValue('B6', 'Date');
		$sheet->setCellValue('C6', 'Product');
		$sheet->setCellValue('D6', 'Type');
		$sheet->setCellValue('E6', 'Qty');
		$sheet->setCellValue('F6', 'Source');
		$sheet->setCellValue('G6', 'Project');
		$sheet->setCellValue('H6', 'Reference');
		$sheet->setCellValue('I6', 'Notes');

		$sheet->getStyle('A6:I6')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
		$sheet->getStyle('A6:I6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

		$sheet->getStyle('A6:I6')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
			->getStartColor()->setRGB('3C5A82');

		// ===== DATA =====
		$rowNum = 7;
		$i = 1;

		foreach ($rows as $r) {

			$sheet->setCellValue('A'.$rowNum, $i++);
			$sheet->setCellValue('B'.$rowNum, $r['transaction_date']);
			$sheet->setCellValue('C'.$rowNum, $r['product_name']);
			$sheet->setCellValue('D'.$rowNum, $r['transaction_type']);
			$sheet->setCellValue('E'.$rowNum, $r['quantity']);
			$sheet->setCellValue('F'.$rowNum, $r['source']);
			$sheet->setCellValue('G'.$rowNum, $r['project_name'] ?: '-');
			$sheet->setCellValue('H'.$rowNum, $r['reference_type'].' #'.$r['reference_id']);
			$sheet->setCellValue('I'.$rowNum, $r['notes']);

			// TYPE COLOR
			if ($r['transaction_type'] === 'IN') {
				$sheet->getStyle('D'.$rowNum)->getFont()->getColor()->setRGB('006100');
			} else {
				$sheet->getStyle('D'.$rowNum)->getFont()->getColor()->setRGB('C00000');
			}

			$rowNum++;
		}

		$lastRow = $rowNum - 1;

		// ===== BORDER =====
		$sheet->getStyle("A6:I{$lastRow}")
			->getBorders()->getAllBorders()
			->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

		// ===== ZEBRA =====
		for ($r = 7; $r <= $lastRow; $r++) {
			if ($r % 2 == 0) {
				$sheet->getStyle("A$r:I$r")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
					->getStartColor()->setRGB('F7FBFC');
			}
		}

		// ===== AUTO SIZE =====
		foreach (range('A','I') as $col) {
			$sheet->getColumnDimension($col)->setAutoSize(true);
		}

		// ===== DOWNLOAD =====
		$fileName = 'ledger_report_' . date('d-m-Y_H-i') . '.xlsx';

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header("Content-Disposition: attachment;filename=\"$fileName\"");

		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
		$writer->save('php://output');
		exit;
	}
	
	public function ledgerPdf()
	{
		$db = \Config\Database::connect();

		$productId = $this->request->getGet('product_id');
		$projectId = $this->request->getGet('project_id');

		$productId = ($productId === '' || $productId === null) ? null : (int)$productId;

		$whereArr = [];

		if ($productId !== null) {
			$whereArr[] = "sl.product_id = $productId";
		}

		if ($projectId === 'general') {
			$whereArr[] = "sl.project_id IS NULL";
		} elseif (!empty($projectId)) {
			$whereArr[] = "sl.project_id = $projectId";
		}

		$where = !empty($whereArr) ? 'WHERE ' . implode(' AND ', $whereArr) : '';

		$rows = $db->query("
			SELECT sl.*, p.name AS product_name, pr.name AS project_name
			FROM stock_ledger sl
			LEFT JOIN products p ON sl.product_id = p.id
			LEFT JOIN projects pr ON sl.project_id = pr.id
			$where
			ORDER BY sl.created_at DESC
		")->getResultArray();

		// ===== PROJECT NAME =====
		$projectName = 'All Projects';

		if ($projectId === 'general') {
			$projectName = 'GENERAL PRODUCTS';
		} elseif (!empty($projectId)) {
			$projectName = $db->query("SELECT name FROM projects WHERE id = ?", [$projectId])
							  ->getRow()->name ?? '';
		}

		// ===== HTML =====
		$html = '
			<style>
				body {
					font-family: DejaVu Sans;
					font-size: 11px;
					color: #2d3748;
				}

				.header-box {
					background: #2F7E8A;
					color: #fff;
					padding: 12px;
					border-radius: 6px;
					text-align: center;
					margin-bottom: 10px;
				}

				.header-box h2 {
					margin: 0;
					font-size: 16px;
					letter-spacing: 1px;
				}

				.meta {
					text-align: center;
					font-size: 10px;
					margin-top: 4px;
					color: #e2e8f0;
				}

				.project {
					text-align: center;
					font-size: 11px;
					font-weight: bold;
					color: #2F7E8A;
					margin: 10px 0;
				}

				table {
					width: 100%;
					border-collapse: collapse;
					margin-top: 5px;
				}

				th {
					background: #3C5A82;
					color: #fff;
					padding: 7px;
					font-size: 11px;
				}

				td {
					padding: 6px;
					border: 1px solid #e2e8f0;
				}

				tr:nth-child(even) td {
					background: #f8fafc;
				}

				.right { text-align: right; }
				.center { text-align: center; }

				.in {
					color: #2e7d32;
					font-weight: bold;
				}

				.out {
					color: #c62828;
					font-weight: bold;
				}

				.footer-note {
					margin-top: 10px;
					font-size: 9px;
					text-align: right;
					color: #888;
				}
			</style>

			<div class="header-box">
				<h2>STOCK LEDGER REPORT</h2>
				<div class="meta">
					Generated On: '.date('d-m-Y h:i A').'
				</div>
			</div>

			<div class="project">
				Project: '.$projectName.'
			</div>

		<table>
			<tr>
				<th>#</th>
				<th>Date</th>
				<th>Product</th>
				<th>Type</th>
				<th>Qty</th>
				<th>Source</th>
				<th>Project</th>
			</tr>
		';

		$i = 1;

		foreach ($rows as $r) {

			$typeClass = ($r['transaction_type'] === 'IN') ? 'in' : 'out';

			$html .= '
			<tr>
				<td class="center">'.$i++.'</td>
				<td>'.$r['transaction_date'].'</td>
				<td>'.$r['product_name'].'</td>
				<td class="center '.$typeClass.'">'.$r['transaction_type'].'</td>
				<td class="right">'.number_format($r['quantity']).'</td>
				<td class="center">'.$r['source'].'</td>
				<td>'.$r['project_name'] ?: '-'.'</td>
			</tr>';
		}

		$html .= '</table>';

		// ===== DOMPDF =====
		$options = new \Dompdf\Options();
		$options->set('isRemoteEnabled', true);

		$dompdf = new \Dompdf\Dompdf($options);
		$dompdf->loadHtml($html);
		$dompdf->setPaper('A4', 'landscape'); // important for ledger
		$dompdf->render();

		$fileName = 'ledger_report_' . date('d-m-Y_H-i') . '.pdf';

		$dompdf->stream($fileName, ["Attachment" => true]);
	}
	
	public function getProductsByProject()
	{
		$db = \Config\Database::connect();
		$projectId = $this->request->getGet('project_id');

		if ($projectId === 'general') {

			// GENERAL products (no project)
			$products = $db->query("
				SELECT DISTINCT p.id, p.name
				FROM stock_ledger sl
				LEFT JOIN products p ON p.id = sl.product_id
				WHERE sl.project_id IS NULL
				  AND sl.source = 'GENERAL'
				ORDER BY p.name ASC
			")->getResultArray();

		} elseif ($projectId) {

			// PROJECT products
			$products = $db->query("
				SELECT DISTINCT p.id, p.name
				FROM stock_ledger sl
				LEFT JOIN products p ON p.id = sl.product_id
				WHERE sl.project_id = ?
				ORDER BY p.name ASC
			", [$projectId])->getResultArray();

		} else {

			// ALL products
			$products = $db->query("
				SELECT id, name FROM products ORDER BY name ASC
			")->getResultArray();
		}

		return $this->response->setJSON($products);
	}
}
