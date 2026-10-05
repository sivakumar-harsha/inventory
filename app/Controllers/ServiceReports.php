<?php

namespace App\Controllers;

use App\Libraries\ExcelReport;
use App\Libraries\PdfReport;
use CodeIgniter\Controller;

/**
 * Release 4.8.4E: read-only reports over the frozen Service Received (4.8.4A/B),
 * Customer Payment (4.8.4C) and Customer Ledger (4.8.4D) data. Nothing in this
 * controller writes, and it adds no columns or business rules.
 *
 * All filtering is server-side through GET parameters, so every KPI describes
 * exactly the rows the table shows. Bad input never raises: an unknown or
 * non-numeric id / enum / amount is ignored (filter off), and a malformed date
 * makes the report return an empty dataset with a notice.
 *
 * Two outstanding figures exist, on purpose:
 *  - Register / Outstanding reports use service_receipts.outstanding_amount,
 *    the per-invoice balance, so they tie back to each receipt.
 *  - Customer Summary uses the Customer Ledger definition (invoices minus
 *    EVERYTHING received including unallocated advance, floored at zero), so
 *    it agrees with each customer's statement.
 */
class ServiceReports extends Controller
{
    private const RECEIPT_TYPES = ['INVOICE', 'DIRECT'];
    private const STATUSES      = ['PENDING', 'PARTIAL', 'PAID'];
    private const PAYMENT_MODES = ['CASH', 'BANK', 'CHEQUE', 'UPI', 'OTHER'];

    // =========================================================
    // PAGES
    // =========================================================

    public function register()
    {
        $db        = \Config\Database::connect();
        $customers = $this->_customers();
        $f         = $this->_getRegisterFilters($customers);
        $rows      = $this->_registerRows($f);

        $data['f']             = $f;
        $data['rows']          = $rows;
        $data['customers']     = $customers;
        $data['attendedList']  = array_column(
            $db->table('service_receipts')->distinct()->select('attended_person')->where("attended_person <> ''", null, false)->orderBy('attended_person', 'ASC')->get()->getResultArray(),
            'attended_person'
        );
        $data['receiptTypes']  = self::RECEIPT_TYPES;
        $data['statuses']      = self::STATUSES;
        $data['paymentModes']  = self::PAYMENT_MODES;
        $data['kpi_total_receipts']  = count($rows);
        $data['kpi_invoice_amount']  = round(array_sum(array_column($rows, 'grand_total')), 2);
        $data['kpi_received_amount'] = round(array_sum(array_column($rows, 'received_amount')), 2);
        $data['kpi_outstanding']     = round(array_sum(array_column($rows, 'outstanding_amount')), 2);

        return view('service_reports/register', $data);
    }

    public function outstanding()
    {
        $customers = $this->_customers();
        $f         = $this->_getOutstandingFilters($customers);
        $rows      = $this->_outstandingRows($f);

        $pending = array_filter($rows, static fn ($r) => $r['payment_status'] === 'PENDING');
        $partial = array_filter($rows, static fn ($r) => $r['payment_status'] === 'PARTIAL');

        $data['f']                    = $f;
        $data['rows']                 = $rows;
        $data['customers']            = $customers;
        $data['kpi_pending_count']    = count($pending);
        $data['kpi_pending_amount']   = round(array_sum(array_column($pending, 'outstanding_amount')), 2);
        $data['kpi_partial_count']    = count($partial);
        $data['kpi_total_outstanding'] = round(array_sum(array_column($rows, 'outstanding_amount')), 2);

        return view('service_reports/outstanding', $data);
    }

    public function collections()
    {
        $db        = \Config\Database::connect();
        $customers = $this->_customers();
        $banks     = $db->table('bank_accounts')->select('id, bank_name, account_name, account_number')->orderBy('bank_name', 'ASC')->get()->getResultArray();
        $f         = $this->_getCollectionFilters($customers, array_column($banks, 'id'));

        $rows = $f['dates_valid'] ? $this->_collectionRows($f) : [];

        // Today / this month ignore every filter except the date they define.
        $blank = ['customer_id' => 0, 'payment_mode' => '', 'bank_id' => 0, 'search' => '', 'dates_valid' => true];
        $today = date('Y-m-d');

        $data['f']                 = $f;
        $data['rows']              = $rows;
        $data['customers']         = $customers;
        $data['banks']             = $banks;
        $data['paymentModes']      = self::PAYMENT_MODES;
        $data['kpi_today']         = $this->_sumAmount($this->_collectionRows($blank + ['date_from' => $today, 'date_to' => $today]));
        $data['kpi_month']         = $this->_sumAmount($this->_collectionRows($blank + ['date_from' => date('Y-m-01'), 'date_to' => date('Y-m-t')]));
        $data['kpi_filtered']      = $this->_sumAmount($rows);
        $data['kpi_advance']       = $this->_sumAmount(array_filter($rows, static fn ($r) => $r['source'] === 'Customer Advance'));

        return view('service_reports/collections', $data);
    }

    public function customerSummary()
    {
        $customers = $this->_customers();
        $f         = $this->_getCustomerSummaryFilters($customers);
        $rows      = $this->_customerSummaryRows($f);

        $data['f']                   = $f;
        $data['rows']                = $rows;
        $data['customers']           = $customers;
        $data['kpi_total_customers'] = count($rows);
        $data['kpi_invoice_amount']  = round(array_sum(array_column($rows, 'invoice')), 2);
        $data['kpi_received']        = round(array_sum(array_column($rows, 'received')), 2);
        $data['kpi_outstanding']     = round(array_sum(array_column($rows, 'outstanding')), 2);

        return view('service_reports/customer_summary', $data);
    }

    // =========================================================
    // PDF EXPORTS (Release 4.8.7A) — same filtered rows/KPIs as the pages
    // above, reusing the same filter + row-builder helpers. All landscape.
    // =========================================================

    public function exportPdf()
    {
        $customers = $this->_customers();
        $f         = $this->_getRegisterFilters($customers);
        $rows      = $this->_registerRows($f);

        (new PdfReport())->render('pdf/service_register', [
            'rows'                 => $rows,
            'kpi_total_receipts'   => count($rows),
            'kpi_invoice_amount'   => round(array_sum(array_column($rows, 'grand_total')), 2),
            'kpi_received_amount'  => round(array_sum(array_column($rows, 'received_amount')), 2),
            'kpi_outstanding'      => round(array_sum(array_column($rows, 'outstanding_amount')), 2),
        ], [
            'title'       => 'Service Receipt Register',
            'orientation' => 'landscape',
            'filters'     => [
                'Receipt Type'    => $f['receipt_type'],
                'Status'          => $f['status'],
                'Payment Mode'    => $f['payment_mode'],
                'Attended Person' => $f['attended'],
                'From'            => $f['date_from'] !== '' ? pdf_date($f['date_from']) : '',
                'To'              => $f['date_to'] !== '' ? pdf_date($f['date_to']) : '',
                'Search'          => $f['search'],
            ],
        ]);
    }

    public function exportOutstandingPdf()
    {
        $customers = $this->_customers();
        $f         = $this->_getOutstandingFilters($customers);
        $rows      = $this->_outstandingRows($f);
        $pending   = array_filter($rows, static fn ($r) => $r['payment_status'] === 'PENDING');
        $partial   = array_filter($rows, static fn ($r) => $r['payment_status'] === 'PARTIAL');

        (new PdfReport())->render('pdf/service_outstanding', [
            'rows'                  => $rows,
            'kpi_pending_count'     => count($pending),
            'kpi_pending_amount'    => round(array_sum(array_column($pending, 'outstanding_amount')), 2),
            'kpi_partial_count'     => count($partial),
            'kpi_total_outstanding' => round(array_sum(array_column($rows, 'outstanding_amount')), 2),
        ], [
            'title'       => 'Outstanding Service Report',
            'orientation' => 'landscape',
            'filters'     => [
                'Min Amount' => $f['min_amount'] !== null ? pdf_currency($f['min_amount']) : '',
                'Max Amount' => $f['max_amount'] !== null ? pdf_currency($f['max_amount']) : '',
                'From'       => $f['date_from'] !== '' ? pdf_date($f['date_from']) : '',
                'To'         => $f['date_to'] !== '' ? pdf_date($f['date_to']) : '',
                'Search'     => $f['search'],
            ],
        ]);
    }

    public function exportCollectionsPdf()
    {
        $db        = \Config\Database::connect();
        $customers = $this->_customers();
        $banks     = $db->table('bank_accounts')->select('id, bank_name, account_name, account_number')->orderBy('bank_name', 'ASC')->get()->getResultArray();
        $f         = $this->_getCollectionFilters($customers, array_column($banks, 'id'));
        $rows      = $f['dates_valid'] ? $this->_collectionRows($f) : [];

        $blank = ['customer_id' => 0, 'payment_mode' => '', 'bank_id' => 0, 'search' => '', 'dates_valid' => true];
        $today = date('Y-m-d');

        (new PdfReport())->render('pdf/service_collections', [
            'rows'        => $rows,
            'kpi_today'   => $this->_sumAmount($this->_collectionRows($blank + ['date_from' => $today, 'date_to' => $today])),
            'kpi_month'   => $this->_sumAmount($this->_collectionRows($blank + ['date_from' => date('Y-m-01'), 'date_to' => date('Y-m-t')])),
            'kpi_filtered' => $this->_sumAmount($rows),
            'kpi_advance'  => $this->_sumAmount(array_filter($rows, static fn ($r) => $r['source'] === 'Customer Advance')),
        ], [
            'title'       => 'Collections Report',
            'orientation' => 'landscape',
            'filters'     => [
                'Payment Mode' => $f['payment_mode'],
                'From'         => $f['date_from'] !== '' ? pdf_date($f['date_from']) : '',
                'To'           => $f['date_to'] !== '' ? pdf_date($f['date_to']) : '',
                'Search'       => $f['search'],
            ],
        ]);
    }

    public function exportCustomerSummaryPdf()
    {
        $customers = $this->_customers();
        $f         = $this->_getCustomerSummaryFilters($customers);
        $rows      = $this->_customerSummaryRows($f);

        (new PdfReport())->render('pdf/service_customer_summary', [
            'rows'                => $rows,
            'kpi_total_customers' => count($rows),
            'kpi_invoice_amount'  => round(array_sum(array_column($rows, 'invoice')), 2),
            'kpi_received'        => round(array_sum(array_column($rows, 'received')), 2),
            'kpi_outstanding'     => round(array_sum(array_column($rows, 'outstanding')), 2),
        ], [
            'title'       => 'Service Customer Summary',
            'orientation' => 'landscape',
            'filters'     => [
                'Outstanding Only' => $f['outstanding_only'] ? 'Yes' : '',
                'Search'            => $f['search'],
            ],
        ]);
    }

    // =========================================================
    // EXCEL EXPORTS (Release 4.8.7B) — same filtered rows/KPIs as the PDF
    // twins above, reusing the same filter + row-builder helpers so Excel
    // totals equal PDF totals equal on-screen totals by construction.
    // =========================================================

    public function exportExcel()
    {
        $customers = $this->_customers();
        $f         = $this->_getRegisterFilters($customers);
        $rows      = $this->_registerRows($f);

        $excelRows = [];
        foreach ($rows as $r) {
            $excelRows[] = [
                $r['receipt_no'], $r['receipt_date'], $r['receipt_type'], $r['customer_name'],
                $r['attended_person'] ?? '', $r['payment_mode'] ?? '', $r['payment_status'],
                (float) $r['grand_total'], (float) $r['received_amount'], (float) $r['outstanding_amount'],
            ];
        }

        (new ExcelReport())->table(
            'Service Receipt Register',
            [
                'Receipt Type'    => $f['receipt_type'],
                'Status'          => $f['status'],
                'Payment Mode'    => $f['payment_mode'],
                'Attended Person' => $f['attended'],
                'From'            => $f['date_from'] !== '' ? pdf_date($f['date_from']) : '',
                'To'              => $f['date_to'] !== '' ? pdf_date($f['date_to']) : '',
                'Search'          => $f['search'],
            ],
            ['Receipt No', 'Date', 'Type', 'Customer', 'Attended By', 'Mode', 'Status', 'Grand Total', 'Received', 'Outstanding'],
            $excelRows,
            ['text', 'date', 'text', 'text', 'text', 'text', 'text', 'currency', 'currency', 'currency'],
            [7, 8, 9],
            'landscape'
        )->stream('service_register_' . date('Ymd_His'));
    }

    public function exportOutstandingExcel()
    {
        $customers = $this->_customers();
        $f         = $this->_getOutstandingFilters($customers);
        $rows      = $this->_outstandingRows($f);

        $excelRows = [];
        foreach ($rows as $r) {
            $excelRows[] = [
                $r['receipt_no'], $r['receipt_date'], $r['customer_name'],
                (float) $r['grand_total'], (float) $r['outstanding_amount'], $r['payment_status'], (int) $r['age_days'],
            ];
        }

        (new ExcelReport())->table(
            'Outstanding Service Report',
            [
                'Min Amount' => $f['min_amount'] !== null ? pdf_currency($f['min_amount']) : '',
                'Max Amount' => $f['max_amount'] !== null ? pdf_currency($f['max_amount']) : '',
                'From'       => $f['date_from'] !== '' ? pdf_date($f['date_from']) : '',
                'To'         => $f['date_to'] !== '' ? pdf_date($f['date_to']) : '',
                'Search'     => $f['search'],
            ],
            ['Receipt No', 'Date', 'Customer', 'Grand Total', 'Outstanding', 'Status', 'Age (Days)'],
            $excelRows,
            ['text', 'date', 'text', 'currency', 'currency', 'text', 'int'],
            [4],
            'landscape'
        )->stream('service_outstanding_' . date('Ymd_His'));
    }

    public function exportCollectionsExcel()
    {
        $db        = \Config\Database::connect();
        $customers = $this->_customers();
        $banks     = $db->table('bank_accounts')->select('id, bank_name, account_name, account_number')->orderBy('bank_name', 'ASC')->get()->getResultArray();
        $f         = $this->_getCollectionFilters($customers, array_column($banks, 'id'));
        $rows      = $f['dates_valid'] ? $this->_collectionRows($f) : [];

        $excelRows = [];
        foreach ($rows as $r) {
            $excelRows[] = [
                $r['date'], $r['reference'], $r['source'], $r['customer'],
                pm_label($r['mode'] ?? '', '-'), $r['bank'] ?? '', (float) $r['amount'], $r['remarks'] ?? '',
            ];
        }

        (new ExcelReport())->table(
            'Collections Report',
            [
                'Payment Mode' => $f['payment_mode'],
                'From'         => $f['date_from'] !== '' ? pdf_date($f['date_from']) : '',
                'To'           => $f['date_to'] !== '' ? pdf_date($f['date_to']) : '',
                'Search'       => $f['search'],
            ],
            ['Date', 'Reference', 'Source', 'Customer', 'Mode', 'Bank', 'Amount', 'Remarks'],
            $excelRows,
            ['date', 'text', 'text', 'text', 'text', 'text', 'currency', 'text'],
            [6],
            'landscape'
        )->stream('service_collections_' . date('Ymd_His'));
    }

    public function exportCustomerSummaryExcel()
    {
        $customers = $this->_customers();
        $f         = $this->_getCustomerSummaryFilters($customers);
        $rows      = $this->_customerSummaryRows($f);

        $excelRows = [];
        foreach ($rows as $r) {
            $excelRows[] = [
                $r['name'], $r['phone'] ?? '', (float) $r['invoice'], (float) $r['received'],
                (float) $r['outstanding'], (float) $r['advance'],
            ];
        }

        (new ExcelReport())->table(
            'Service Customer Summary',
            [
                'Outstanding Only' => $f['outstanding_only'] ? 'Yes' : '',
                'Search'            => $f['search'],
            ],
            ['Customer', 'Phone', 'Invoice', 'Received', 'Outstanding', 'Advance'],
            $excelRows,
            ['text', 'text', 'currency', 'currency', 'currency', 'currency'],
            [2, 3, 4],
            'landscape'
        )->stream('service_customer_summary_' . date('Ymd_His'));
    }

    // =========================================================
    // ROW BUILDERS — the exact queries each page above uses, reused as-is
    // by the matching export*Pdf() action so a PDF always shows precisely
    // what the screen shows.
    // =========================================================

    private function _registerRows(array $f): array
    {
        if (! $f['dates_valid']) {
            return [];
        }

        $b = \Config\Database::connect()->table('service_receipts sr')->select('sr.*');

        if ($f['receipt_type'] !== '') {
            $b->where('sr.receipt_type', $f['receipt_type']);
        }
        if ($f['status'] !== '') {
            $b->where('sr.payment_status', $f['status']);
        }
        if ($f['customer_id'] > 0) {
            $b->where('sr.customer_id', $f['customer_id']);
        }
        if ($f['payment_mode'] !== '') {
            $b->where('sr.payment_mode', $f['payment_mode']);
        }
        if ($f['attended'] !== '') {
            $b->where('sr.attended_person', $f['attended']);
        }
        if ($f['date_from'] !== '') {
            $b->where('sr.receipt_date >=', $f['date_from']);
        }
        if ($f['date_to'] !== '') {
            $b->where('sr.receipt_date <=', $f['date_to']);
        }
        if ($f['search'] !== '') {
            $b->groupStart()
                ->like('sr.receipt_no', $f['search'])
                ->orLike('sr.customer_name', $f['search'])
                ->orLike('sr.attended_person', $f['search'])
                ->orLike('sr.remarks', $f['search'])
                ->groupEnd();
        }

        return $b->orderBy('sr.receipt_date', 'DESC')->orderBy('sr.id', 'DESC')->get()->getResultArray();
    }

    private function _outstandingRows(array $f): array
    {
        $rows = [];

        if ($f['dates_valid']) {
            $b = \Config\Database::connect()->table('service_receipts sr')
                ->select('sr.*')
                ->where('sr.receipt_type', 'INVOICE')
                ->whereIn('sr.payment_status', ['PENDING', 'PARTIAL']);

            if ($f['customer_id'] > 0) {
                $b->where('sr.customer_id', $f['customer_id']);
            }
            if ($f['min_amount'] !== null) {
                $b->where('sr.outstanding_amount >=', $f['min_amount']);
            }
            if ($f['max_amount'] !== null) {
                $b->where('sr.outstanding_amount <=', $f['max_amount']);
            }
            if ($f['date_from'] !== '') {
                $b->where('sr.receipt_date >=', $f['date_from']);
            }
            if ($f['date_to'] !== '') {
                $b->where('sr.receipt_date <=', $f['date_to']);
            }
            if ($f['search'] !== '') {
                $b->groupStart()
                    ->like('sr.receipt_no', $f['search'])
                    ->orLike('sr.customer_name', $f['search'])
                    ->groupEnd();
            }

            // Oldest first: the most overdue invoices lead the list.
            $rows = $b->orderBy('sr.receipt_date', 'ASC')->orderBy('sr.id', 'ASC')->get()->getResultArray();
        }

        $today = new \DateTime('today');
        foreach ($rows as &$row) {
            $days = (int) $today->diff(new \DateTime($row['receipt_date']))->format('%r%a');
            $row['age_days'] = max(0, -$days);
            $row['age_band'] = $this->_ageBand($row['age_days']);
        }
        unset($row);

        return $rows;
    }

    private function _customerSummaryRows(array $f): array
    {
        $db = \Config\Database::connect();

        // Release 4.9.0BH: same sources and signs as CustomerLedger::_statement(), so the two reconcile.
        //   debits  = service INVOICE receipts + sales.total_amount
        //   credits = service amount taken at invoice + service allocations + sales payments
        //             + CUSTOMER_PROJECT_CASH + voucher advances + projects.advance_amount
        // Advance applied (sales.advance_applied) is NOT a credit: it only spends advance already counted once.
        // DIRECT service receipts are not in the ledger either, so they are not here.
        $b = $db->table('customers c')->select("c.id, c.name, c.phone, c.gst,
            COALESCE((SELECT SUM(sr.grand_total) FROM service_receipts sr
                      WHERE sr.customer_id = c.id AND sr.receipt_type = 'INVOICE'), 0) AS service_invoice,
            COALESCE((SELECT SUM(GREATEST(0, sr2.received_amount - COALESCE(
                          (SELECT SUM(cpa0.paid_amount) FROM customer_payment_allocations cpa0 WHERE cpa0.service_receipt_id = sr2.id), 0)))
                      FROM service_receipts sr2
                      WHERE sr2.customer_id = c.id AND sr2.receipt_type = 'INVOICE'), 0) AS initial_received,
            COALESCE((SELECT SUM(cpa.paid_amount) FROM customer_payment_allocations cpa
                        INNER JOIN customer_payments cp ON cp.id = cpa.customer_payment_id
                      WHERE cp.customer_id = c.id), 0) AS allocation_paid,
            COALESCE((SELECT SUM(cp2.advance_amount) FROM customer_payments cp2 WHERE cp2.customer_id = c.id), 0) AS voucher_advance,
            COALESCE((SELECT SUM(s.total_amount) FROM sales s WHERE s.customer_id = c.id), 0) AS sales_total,
            COALESCE((SELECT SUM(s2.advance_applied) FROM sales s2 WHERE s2.customer_id = c.id), 0) AS advance_applied,
            COALESCE((SELECT SUM(p.amount) FROM payments p INNER JOIN sales s3 ON s3.id = p.sale_id WHERE s3.customer_id = c.id), 0) AS sales_paid,
            COALESCE((SELECT SUM(pcr.amount) FROM project_cash_receipts pcr
                      WHERE pcr.customer_id = c.id AND pcr.receipt_type = 'CUSTOMER_PROJECT_CASH'), 0) AS project_cash,
            COALESCE((SELECT SUM(pr.advance_amount) FROM projects pr WHERE pr.customer_id = c.id AND pr.advance_amount > 0), 0) AS project_advance", false);

        if ($f['customer_id'] > 0) {
            $b->where('c.id', $f['customer_id']);
        }
        if ($f['search'] !== '') {
            $b->groupStart()
                ->like('c.name', $f['search'])
                ->orLike('c.phone', $f['search'])
                ->orLike('c.gst', $f['search'])
                ->groupEnd();
        }

        $rows = [];
        foreach ($b->get()->getResultArray() as $r) {
            $advanceReceived = (float) $r['voucher_advance'] + (float) $r['project_advance'];
            $invoice  = round((float) $r['service_invoice'] + (float) $r['sales_total'], 2);
            $received = round((float) $r['initial_received'] + (float) $r['allocation_paid'] + (float) $r['sales_paid']
                + (float) $r['project_cash'] + $advanceReceived, 2);
            $net      = round($invoice - $received, 2);   // ledger closing balance: > 0 owed, < 0 customer credit

            if ($f['outstanding_only'] && $net <= 0.004) {
                continue;
            }

            $rows[] = [
                'id'          => (int) $r['id'],
                'name'        => $r['name'],
                'phone'       => $r['phone'],
                'invoice'     => $invoice,
                'received'    => $received,
                'outstanding' => max(0.0, $net),
                'credit'      => max(0.0, -$net),
                'net'         => $net,
                'advance'     => max(0.0, round($advanceReceived - (float) $r['advance_applied'], 2)),
            ];
        }

        usort($rows, static fn ($x, $y) => [$y['outstanding'], $x['name']] <=> [$x['outstanding'], $y['name']]);

        return $rows;
    }

    // =========================================================
    // FILTER HELPERS
    // =========================================================

    private function _getRegisterFilters(array $customers): array
    {
        $ok = true;

        return [
            'receipt_type' => $this->_enum('receipt_type', self::RECEIPT_TYPES),
            'status'       => $this->_enum('status', self::STATUSES),
            'customer_id'  => $this->_id('customer_id', array_column($customers, 'id')),
            'payment_mode' => $this->_enum('payment_mode', self::PAYMENT_MODES),
            'attended'     => $this->_get('attended_person'),
            'date_from'    => $this->_date('date_from', $ok),
            'date_to'      => $this->_date('date_to', $ok),
            'search'       => $this->_get('search'),
            'dates_valid'  => $ok,
        ];
    }

    private function _getOutstandingFilters(array $customers): array
    {
        $ok = true;

        return [
            'customer_id' => $this->_id('customer_id', array_column($customers, 'id')),
            'min_amount'  => $this->_amount('min_amount'),
            'max_amount'  => $this->_amount('max_amount'),
            'date_from'   => $this->_date('date_from', $ok),
            'date_to'     => $this->_date('date_to', $ok),
            'search'      => $this->_get('search'),
            'dates_valid' => $ok,
        ];
    }

    private function _getCollectionFilters(array $customers, array $bankIds): array
    {
        $ok = true;

        return [
            'customer_id'  => $this->_id('customer_id', array_column($customers, 'id')),
            'payment_mode' => $this->_enum('payment_mode', self::PAYMENT_MODES),
            'bank_id'      => $this->_id('bank_account_id', $bankIds),
            'date_from'    => $this->_date('date_from', $ok),
            'date_to'      => $this->_date('date_to', $ok),
            'search'       => $this->_get('search'),
            'dates_valid'  => $ok,
        ];
    }

    private function _getCustomerSummaryFilters(array $customers): array
    {
        return [
            'customer_id'      => $this->_id('customer_id', array_column($customers, 'id')),
            'outstanding_only' => $this->_get('outstanding_only') === '1',
            'search'           => $this->_get('search'),
        ];
    }

    /** Trimmed GET string; anything that is not a plain string (arrays) counts as empty. */
    private function _get(string $key): string
    {
        $v = $this->request->getGet($key);

        return is_string($v) ? trim($v) : '';
    }

    /** Valid Y-m-d, '' when absent; a malformed date clears $ok so the report returns nothing. */
    private function _date(string $key, bool &$ok): string
    {
        $raw = $this->_get($key);

        if ($raw === '') {
            return '';
        }

        $d = \DateTime::createFromFormat('Y-m-d', $raw);
        if (! $d || $d->format('Y-m-d') !== $raw) {
            $ok = false;
            return '';
        }

        return $raw;
    }

    /** An id that exists in $valid, else 0 (filter ignored). */
    private function _id(string $key, array $valid): int
    {
        $raw = $this->_get($key);

        if (! ctype_digit($raw)) {
            return 0;
        }

        return in_array((int) $raw, array_map('intval', $valid), true) ? (int) $raw : 0;
    }

    private function _enum(string $key, array $allowed): string
    {
        $v = strtoupper($this->_get($key));

        return in_array($v, $allowed, true) ? $v : '';
    }

    /** Non-negative number or null (filter ignored). */
    private function _amount(string $key): ?float
    {
        $raw = $this->_get($key);

        return ($raw !== '' && is_numeric($raw) && (float) $raw >= 0) ? (float) $raw : null;
    }

    // =========================================================
    // READ HELPERS
    // =========================================================

    private function _customers(): array
    {
        return \Config\Database::connect()->table('customers')->select('id, name')->orderBy('name', 'ASC')->get()->getResultArray();
    }

    private function _ageBand(int $days): string
    {
        if ($days <= 30) {
            return '0-30';
        }
        if ($days <= 60) {
            return '31-60';
        }
        if ($days <= 90) {
            return '61-90';
        }

        return '90+';
    }

    private function _sumAmount(array $rows): float
    {
        return round(array_sum(array_column($rows, 'amount')), 2);
    }

    /**
     * Money received, one row per source, so nothing is counted twice:
     *  - Invoice Receipt: what was taken when the receipt was raised, i.e.
     *    received_amount minus that receipt's allocations (floored at 0). For a
     *    DIRECT receipt that is the whole amount.
     *  - Customer Payment: a voucher's allocated part.
     *  - Customer Advance: a voucher's unallocated advance.
     * A voucher therefore yields up to two rows whose amounts sum to its
     * total_amount. $f needs customer_id, payment_mode, bank_id, search,
     * date_from, date_to.
     */
    private function _collectionRows(array $f): array
    {
        $db   = \Config\Database::connect();
        $rows = [];

        $bank = "CONCAT(ba.bank_name, ' - ', ba.account_name)";

        // -- invoice-time receipts
        $b = $db->table('service_receipts sr')
            ->select("sr.id, sr.receipt_no AS reference, sr.receipt_date AS date, sr.customer_name,
                      sr.payment_mode AS mode, $bank AS bank, sr.remarks,
                      GREATEST(0, sr.received_amount - COALESCE(al.allocated, 0)) AS amount", false)
            ->join('(SELECT service_receipt_id, SUM(paid_amount) AS allocated FROM customer_payment_allocations GROUP BY service_receipt_id) al', 'al.service_receipt_id = sr.id', 'left')
            ->join('bank_accounts ba', 'ba.id = sr.bank_account_id', 'left')
            ->where('GREATEST(0, sr.received_amount - COALESCE(al.allocated, 0)) > 0', null, false);

        if ($f['customer_id'] > 0) {
            $b->where('sr.customer_id', $f['customer_id']);
        }
        if ($f['payment_mode'] !== '') {
            $b->where('sr.payment_mode', $f['payment_mode']);
        }
        if ($f['bank_id'] > 0) {
            $b->where('sr.bank_account_id', $f['bank_id']);
        }
        if ($f['date_from'] !== '') {
            $b->where('sr.receipt_date >=', $f['date_from']);
        }
        if ($f['date_to'] !== '') {
            $b->where('sr.receipt_date <=', $f['date_to']);
        }
        if ($f['search'] !== '') {
            $b->groupStart()
                ->like('sr.receipt_no', $f['search'])
                ->orLike('sr.customer_name', $f['search'])
                ->orLike('sr.remarks', $f['search'])
                ->groupEnd();
        }

        foreach ($b->get()->getResultArray() as $r) {
            $rows[] = $this->_collectionRow($r, 'Invoice Receipt', 0, (int) $r['id']);
        }

        // -- customer payment vouchers (allocated part and advance part)
        $b = $db->table('customer_payments cp')
            ->select("cp.id, cp.payment_no AS reference, cp.payment_date AS date, c.name AS customer_name,
                      cp.payment_method AS mode, $bank AS bank, cp.remarks, cp.advance_amount,
                      COALESCE(al.allocated, 0) AS allocated", false)
            ->join('customers c', 'c.id = cp.customer_id', 'left')
            ->join('(SELECT customer_payment_id, SUM(paid_amount) AS allocated FROM customer_payment_allocations GROUP BY customer_payment_id) al', 'al.customer_payment_id = cp.id', 'left')
            ->join('bank_accounts ba', 'ba.id = cp.bank_account_id', 'left');

        if ($f['customer_id'] > 0) {
            $b->where('cp.customer_id', $f['customer_id']);
        }
        if ($f['payment_mode'] !== '') {
            $b->where('cp.payment_method', $f['payment_mode']);
        }
        if ($f['bank_id'] > 0) {
            $b->where('cp.bank_account_id', $f['bank_id']);
        }
        if ($f['date_from'] !== '') {
            $b->where('cp.payment_date >=', $f['date_from']);
        }
        if ($f['date_to'] !== '') {
            $b->where('cp.payment_date <=', $f['date_to']);
        }
        if ($f['search'] !== '') {
            $b->groupStart()
                ->like('cp.payment_no', $f['search'])
                ->orLike('c.name', $f['search'])
                ->orLike('cp.remarks', $f['search'])
                ->orLike('cp.reference_no', $f['search'])
                ->groupEnd();
        }

        foreach ($b->get()->getResultArray() as $r) {
            if ((float) $r['allocated'] > 0) {
                $rows[] = $this->_collectionRow($r + ['amount' => $r['allocated']], 'Customer Payment', 1, (int) $r['id']);
            }
            if ((float) $r['advance_amount'] > 0) {
                $rows[] = $this->_collectionRow($r + ['amount' => $r['advance_amount']], 'Customer Advance', 2, (int) $r['id']);
            }
        }

        // Newest first; within a day, receipts then payments then advances.
        usort($rows, static fn ($x, $y) => [$y['date'], $x['order'], $y['seq']] <=> [$x['date'], $y['order'], $x['seq']]);

        return $rows;
    }

    private function _collectionRow(array $r, string $source, int $order, int $seq): array
    {
        return [
            'date'      => $r['date'],
            'reference' => $r['reference'],
            'source'    => $source,
            'customer'  => $r['customer_name'],
            'mode'      => $r['mode'],
            'bank'      => $r['bank'],
            'amount'    => round((float) $r['amount'], 2),
            'remarks'   => $r['remarks'],
            'order'     => $order,
            'seq'       => $seq,
        ];
    }
}
