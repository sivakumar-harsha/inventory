<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Release 4.8.7B: shared PhpSpreadsheet wrapper for the read-only Excel
 * exports built alongside 4.8.7A's PdfReport. Nothing here writes to the
 * database; it only turns arrays already produced by each controller's
 * existing row-builder methods into a formatted .xlsx download.
 *
 * Mirrors PdfReport's conventions on purpose (company title, report title,
 * generated timestamp, "Applied filters" line via pdf_filter_line() from
 * pdf_helper.php, landscape for registers/summaries, portrait for
 * ledgers/single-record views) so a report's PDF and Excel always agree.
 *
 * Three layouts are provided:
 *  - table()  : standard report sheet — header rows, a data table with a
 *               bold/frozen/auto-filtered header row, and a bold Grand Total
 *               footer that sums the requested currency column(s).
 *  - ledger() : running-balance statement (Customer/Supplier/Loan Ledger) —
 *               an info block (e.g. customer/loan header fields) above the
 *               same kind of data table, plus an opening/closing balance.
 *  - detail() : single-record label/value sheet (Expense View) — a simple
 *               two-column "Field" / "Value" layout per section.
 *
 * All cell addressing uses plain string coordinates (e.g. "A6"), built via
 * Coordinate::stringFromColumnIndex(), which is PhpSpreadsheet's
 * best-documented/most stable addressing form.
 */
class ExcelReport
{
    private Spreadsheet $spreadsheet;
    private $sheet;

    private const COMPANY = 'A&A Inventory ERP';

    public function __construct()
    {
        helper('pdf');

        $this->spreadsheet = new Spreadsheet();
        $this->sheet        = $this->spreadsheet->getActiveSheet();
    }

    // =========================================================
    // TABLE LAYOUT — Register / Summary style reports
    // =========================================================

    /**
     * @param string   $title        Report title (also used as sheet title / filename base)
     * @param array    $filters      label => value, rendered via pdf_filter_line()
     * @param string[] $headers      Column header labels, in order
     * @param array[]  $rows         List of rows; each row is a list of raw values in header order
     * @param string[] $colTypes     Per-column type: 'text' | 'currency' | 'date' | 'int'
     * @param int[]    $totalCols    Header indexes (0-based) to sum in the Grand Total footer row
     * @param string   $orientation  'landscape' (default) | 'portrait'
     * @param string   $noDataMessage Shown as a single row under the headers when $rows is empty
     */
    public function table(
        string $title,
        array $filters,
        array $headers,
        array $rows,
        array $colTypes,
        array $totalCols = [],
        string $orientation = 'landscape',
        string $noDataMessage = 'No records found'
    ): self {
        $this->sheet->setTitle($this->_safeSheetTitle($title));

        $row       = $this->_writeChrome($title, $filters);
        $headerRow = $row;
        $this->_writeHeaderRow($headerRow, $headers);

        $dataStartRow = $headerRow + 1;
        $r            = $dataStartRow;
        $lastCol      = max(1, count($headers));

        if (! $rows) {
            $this->sheet->setCellValueExplicit($this->_cell(1, $r), $noDataMessage, DataType::TYPE_STRING);
            $this->sheet->mergeCells($this->_range(1, $r, $lastCol, $r));
            $r++;
        } else {
            foreach ($rows as $dataRow) {
                foreach (array_values($dataRow) as $i => $value) {
                    $this->_writeCell($r, $i + 1, $value, $colTypes[$i] ?? 'text');
                }
                $r++;
            }
        }

        $lastDataRow = $r - 1;

        if ($rows && $totalCols) {
            $this->sheet->setCellValueExplicit($this->_cell(1, $r), 'Grand Total', DataType::TYPE_STRING);

            foreach ($totalCols as $colIdx) {
                $col     = $colIdx + 1;
                $colLtr  = Coordinate::stringFromColumnIndex($col);
                $this->sheet->setCellValue($this->_cell($col, $r), "=SUM({$colLtr}{$dataStartRow}:{$colLtr}{$lastDataRow})");
                $this->sheet->getStyle($this->_cell($col, $r))->getNumberFormat()->setFormatCode('#,##0.00');
            }

            $this->sheet->getStyle($this->_range(1, $r, $lastCol, $r))->applyFromArray([
                'font'    => ['bold' => true],
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF2F7']],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
            ]);
            $r++;
        }

        $this->_finishSheet($headers, $headerRow, $orientation);

        return $this;
    }

    // =========================================================
    // LEDGER LAYOUT — Customer / Supplier / Loan statements
    // =========================================================

    /**
     * @param array $infoBlock label => value pairs describing the account
     *                          (e.g. Customer/Loan name, phone, GST, status)
     */
    public function ledger(
        string $title,
        array $filters,
        array $infoBlock,
        float $openingBalance,
        array $headers,
        array $rows,
        array $colTypes,
        int $balanceCol,
        float $closingBalance,
        array $totalCols = [],
        string $orientation = 'portrait',
        string $noDataMessage = 'No records found'
    ): self {
        $this->sheet->setTitle($this->_safeSheetTitle($title));

        $row = $this->_writeChrome($title, $filters);

        if ($infoBlock) {
            foreach ($infoBlock as $label => $value) {
                $this->sheet->setCellValueExplicit($this->_cell(1, $row), $label . ':', DataType::TYPE_STRING);
                $this->sheet->getStyle($this->_cell(1, $row))->getFont()->setBold(true);
                $this->sheet->setCellValueExplicit($this->_cell(2, $row), (string) ($value ?? '-'), DataType::TYPE_STRING);
                $row++;
            }
            $row++;
        }

        $this->sheet->setCellValueExplicit($this->_cell(1, $row), 'Opening Balance', DataType::TYPE_STRING);
        $this->sheet->getStyle($this->_cell(1, $row))->getFont()->setBold(true);
        $this->sheet->setCellValue($this->_cell(2, $row), $openingBalance);
        $this->sheet->getStyle($this->_cell(2, $row))->getNumberFormat()->setFormatCode('#,##0.00');
        $row += 2;

        $headerRow = $row;
        $this->_writeHeaderRow($headerRow, $headers);

        $dataStartRow = $headerRow + 1;
        $r            = $dataStartRow;
        $lastCol      = max(1, count($headers));

        if (! $rows) {
            $this->sheet->setCellValueExplicit($this->_cell(1, $r), $noDataMessage, DataType::TYPE_STRING);
            $this->sheet->mergeCells($this->_range(1, $r, $lastCol, $r));
            $r++;
        } else {
            foreach ($rows as $dataRow) {
                foreach (array_values($dataRow) as $i => $value) {
                    $this->_writeCell($r, $i + 1, $value, $colTypes[$i] ?? 'text');
                }
                $r++;
            }
        }

        $lastDataRow = $r - 1;

        if ($rows && $totalCols) {
            $this->sheet->setCellValueExplicit($this->_cell(1, $r), 'Total', DataType::TYPE_STRING);
            foreach ($totalCols as $colIdx) {
                $col    = $colIdx + 1;
                $colLtr = Coordinate::stringFromColumnIndex($col);
                $this->sheet->setCellValue($this->_cell($col, $r), "=SUM({$colLtr}{$dataStartRow}:{$colLtr}{$lastDataRow})");
                $this->sheet->getStyle($this->_cell($col, $r))->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $this->sheet->getStyle($this->_range(1, $r, $lastCol, $r))->getFont()->setBold(true);
            $r++;
        }

        $r++;
        $this->sheet->setCellValueExplicit($this->_cell(1, $r), 'Closing Balance', DataType::TYPE_STRING);
        $this->sheet->getStyle($this->_cell(1, $r))->getFont()->setBold(true);
        $this->sheet->setCellValue($this->_cell($balanceCol + 1, $r), $closingBalance);
        $this->sheet->getStyle($this->_cell($balanceCol + 1, $r))->getNumberFormat()->setFormatCode('#,##0.00');
        $this->sheet->getStyle($this->_range(1, $r, $lastCol, $r))->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF2F7']],
        ]);

        $this->_finishSheet($headers, $headerRow, $orientation);

        return $this;
    }

    // =========================================================
    // DETAIL LAYOUT — single-record label/value sheet
    // =========================================================

    /**
     * @param array $sections ['Section title' => ['Field' => 'Value', ...], ...]
     */
    public function detail(string $title, array $filters, array $sections, string $orientation = 'portrait'): self
    {
        $this->sheet->setTitle($this->_safeSheetTitle($title));

        $row = $this->_writeChrome($title, $filters);

        foreach ($sections as $sectionTitle => $fields) {
            $this->sheet->setCellValueExplicit($this->_cell(1, $row), $sectionTitle, DataType::TYPE_STRING);
            $this->sheet->getStyle($this->_cell(1, $row))->getFont()->setBold(true)->setSize(12);
            $this->sheet->getStyle($this->_cell(1, $row))->getFont()->getColor()->setRGB('2F7E8A');
            $row++;

            $this->sheet->setCellValueExplicit($this->_cell(1, $row), 'Field', DataType::TYPE_STRING);
            $this->sheet->setCellValueExplicit($this->_cell(2, $row), 'Value', DataType::TYPE_STRING);
            $this->sheet->getStyle($this->_range(1, $row, 2, $row))->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F7E8A']],
            ]);
            $row++;

            foreach ($fields as $label => $value) {
                $this->sheet->setCellValueExplicit($this->_cell(1, $row), $label, DataType::TYPE_STRING);
                $this->sheet->getStyle($this->_cell(1, $row))->getFont()->setBold(true);
                $v = $value === null || $value === '' ? '-' : (string) $value;
                $this->sheet->setCellValueExplicit($this->_cell(2, $row), $v, DataType::TYPE_STRING);
                $row++;
            }

            $row++;
        }

        $this->sheet->getColumnDimension('A')->setWidth(28);
        $this->sheet->getColumnDimension('B')->setWidth(50);
        $this->sheet->getPageSetup()->setOrientation(
            $orientation === 'landscape' ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT
        );
        $this->sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);

        return $this;
    }

    // =========================================================
    // OUTPUT
    // =========================================================

    /**
     * Streams the finished workbook as an .xlsx download and ends the
     * request, mirroring PdfReport::render()'s stream()-then-exit convention
     * so this codebase has one response-termination pattern for report
     * downloads, not two.
     */
    public function stream(string $filename): void
    {
        if (! str_ends_with(strtolower($filename), '.xlsx')) {
            $filename .= '.xlsx';
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($this->spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /** Test/verification helper: saves to a real path instead of streaming to the browser. */
    public function saveTo(string $path): void
    {
        (new Xlsx($this->spreadsheet))->save($path);
    }

    // =========================================================
    // INTERNALS
    // =========================================================

    private function _writeChrome(string $title, array $filters): int
    {
        $this->sheet->setCellValueExplicit('A1', self::COMPANY, DataType::TYPE_STRING);
        $this->sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $this->sheet->getStyle('A1')->getFont()->getColor()->setRGB('2F7E8A');

        $this->sheet->setCellValueExplicit('A2', $title, DataType::TYPE_STRING);
        $this->sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);

        $this->sheet->setCellValueExplicit('A3', 'Generated: ' . date('d-m-Y H:i A'), DataType::TYPE_STRING);
        $this->sheet->getStyle('A3')->getFont()->setSize(9)->setItalic(true);

        $this->sheet->setCellValueExplicit('A4', 'Applied filters: ' . pdf_filter_line($filters), DataType::TYPE_STRING);
        $this->sheet->getStyle('A4')->getFont()->setSize(9);

        return 6; // row 5 left blank
    }

    private function _writeHeaderRow(int $row, array $headers): void
    {
        foreach ($headers as $i => $label) {
            $this->sheet->setCellValueExplicit($this->_cell($i + 1, $row), $label, DataType::TYPE_STRING);
        }

        $lastCol = max(1, count($headers));
        $this->sheet->getStyle($this->_range(1, $row, $lastCol, $row))->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F7E8A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    private function _writeCell(int $row, int $col, $value, string $type): void
    {
        $cell = $this->_cell($col, $row);

        switch ($type) {
            case 'currency':
                $this->sheet->setCellValue($cell, round((float) $value, 2));
                $this->sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
                $this->sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                break;

            case 'int':
            case 'number':
                $this->sheet->setCellValue($cell, $value === null || $value === '' ? null : (float) $value);
                $this->sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                break;

            case 'date':
                $ts = $value ? strtotime((string) $value) : false;
                if ($ts) {
                    $this->sheet->setCellValue($cell, ExcelDate::PHPToExcel(new \DateTime('@' . $ts)));
                    $this->sheet->getStyle($cell)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
                } else {
                    $this->sheet->setCellValueExplicit($cell, '-', DataType::TYPE_STRING);
                }
                break;

            default:
                $v = $value === null || $value === '' ? '-' : (string) $value;
                $this->sheet->setCellValueExplicit($cell, $v, DataType::TYPE_STRING);
        }
    }

    private function _finishSheet(array $headers, int $headerRow, string $orientation): void
    {
        $lastCol = max(1, count($headers));

        for ($c = 1; $c <= $lastCol; $c++) {
            $this->sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        $this->sheet->setAutoFilter($this->_range(1, $headerRow, $lastCol, $headerRow));
        $this->sheet->freezePane($this->_cell(1, $headerRow + 1));

        $this->sheet->getPageSetup()->setOrientation(
            $orientation === 'landscape' ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT
        );
        $this->sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
        $this->sheet->getPageSetup()->setFitToWidth(1);
        $this->sheet->getPageSetup()->setFitToHeight(0);
    }

    private function _cell(int $col, int $row): string
    {
        return Coordinate::stringFromColumnIndex($col) . $row;
    }

    private function _range(int $col1, int $row1, int $col2, int $row2): string
    {
        return $this->_cell($col1, $row1) . ':' . $this->_cell($col2, $row2);
    }

    /** Excel sheet titles: max 31 chars, no : \ / ? * [ ] */
    private function _safeSheetTitle(string $title): string
    {
        $safe = preg_replace('/[:\\\\\/\?\*\[\]]/', ' ', $title);

        return substr(trim($safe), 0, 31) ?: 'Report';
    }
}
