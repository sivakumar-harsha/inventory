<?php

namespace App\Controllers;

use App\Libraries\ExcelReport;
use App\Libraries\PdfReport;
use App\Models\ProjectModel;
use CodeIgniter\Controller;

/**
 * Release 4.9.0B: the Customer Ledger is one daily-transaction page, laid out
 * exactly like the Supplier Ledger (Release 4.9.0A): customer picker, summary
 * strip, running-balance ledger, outstanding invoices and advance history. It is
 * built read-only from data the Service Received / Customer Payment / Project
 * tables already hold. No new columns, no writes, no posting rule touched — the
 * accounting is the Release 4.8.4D / 4.8.4J statement, unchanged:
 *
 *  - every INVOICE service receipt is a debit (grand_total); DIRECT receipts are
 *    paid at the counter and never appear;
 *  - money received is a credit: the amount taken when the invoice was raised
 *    (service_receipts.received_amount less its allocations), each customer
 *    payment allocation, each voucher's unallocated advance, and each project's
 *    advance amount;
 *
 * so closing balance = Invoices - Received = Outstanding Amount - Advance
 * = Net Receivable (negative = the customer is in credit).
 *
 * The customer modules have no mechanism that consumes an advance against a
 * service invoice, so there are no Adjustment / Credit Note rows today. The type
 * exists in the filter and badges so such rows slot in without a UI change.
 */
class CustomerLedger extends Controller
{
    // Release 4.9.0AH: 'Customer Project Cash' added — genuine customer money
    // received for a project without an invoice (project_cash_receipts,
    // receipt_type = CUSTOMER_PROJECT_CASH). The other three types now also
    // cover the separate Sales/Invoice module (sales + payments tables),
    // previously invisible on this ledger.
    // Release 4.9.0CB: user-facing label of that receipt type is now 'Unallocated
    // Project Receipt' (stored value CUSTOMER_PROJECT_CASH is unchanged).
    private const TYPES = ['Invoice', 'Payment', 'Advance', 'Adjustment', 'Unallocated Project Receipt'];

    public function index()
    {
        $db        = \Config\Database::connect();
        $customers = $db->table('customers')->select('id, name')->orderBy('name', 'ASC')->get()->getResultArray();
        $filters   = $this->_filters();

        $data = [
            'customers' => $customers,
            'filters'   => $filters,
            'types'     => self::TYPES,
            'customer'  => null,
        ];

        if ($filters['customer_id'] > 0) {
            $customer = $db->table('customers')->where('id', $filters['customer_id'])->get()->getRowArray();
            if (! $customer) {
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Customer not found.');
            }
            // array_merge (not +=) so 'customer' overwrites the null placeholder set above —
            // += keeps the left operand's value on a key collision and silently dropped $customer.
            $data = array_merge($data, ['customer' => $customer], $this->_statement($customer, $filters));
        }

        return view('customer_ledger/index', $data);
    }

    /** Legacy per-customer URL: the single ledger page now serves it. */
    public function view($id)
    {
        return redirect()->to(base_url('customer-ledger?customer_id=' . (int) $id));
    }

    /** GET customer-ledger/export/pdf/{id}[?from&to&type&q] — the filtered ledger only. */
    public function exportPdf($id)
    {
        [$customer, $filters, $st] = $this->_forExport((int) $id);

        (new PdfReport())->render('pdf/customer_statement', [
            'customer' => $customer,
            'filters'  => $filters,
            'summary'  => $st['summary'],
            'opening'  => $st['opening'],
            'rows'     => $st['rows'],
            'closing'  => $st['closing'],
        ], [
            'title'       => 'Customer Ledger - ' . $customer['name'],
            'orientation' => 'landscape',
        ]);
    }

    /** GET customer-ledger/export/excel/{id}[?from&to&type&q] — the filtered ledger only. */
    public function exportExcel($id)
    {
        [$customer, $filters, $st] = $this->_forExport((int) $id);

        $rows = [];
        foreach ($st['rows'] as $t) {
            $rows[] = [$t['date'], $t['voucher'], $t['ttype'], $t['particulars'], pm_label($t['method'], $t['ttype'] === 'Invoice' ? '—' : 'Not recorded'), $t['debit'], $t['credit'], $t['balance']];
        }

        $applied = array_filter([
            'From'   => $filters['from'],
            'To'     => $filters['to'],
            'Type'   => $filters['type'],
            'Search' => $filters['q'],
        ], static fn ($v) => $v !== '');

        // Release 4.9.0AI: 'Net Receivable' (outstanding - gross advance) is a
        // narrower figure than the customer's true ledger position now that
        // Sales-module payments and Customer Project Cash also post credits
        // (see _statement()); it can be negative even when the customer's
        // real position is positive, and vice versa. summary['balance'] is the
        // authoritative whole-history debit-minus-credit figure already used
        // for the on-screen Closing Balance, so the same label swap used there
        // is applied here: negative = money owed BACK to the customer, shown
        // as "Customer Credit" with the app's existing 'Cr' suffix convention
        // (see projects/statement.php, customer_ledger/view.php) rather than a
        // misleading negative "Net Receivable". No accounting figures change.
        $balance  = (float) $st['summary']['balance'];
        $infoBlock = [
            'Customer'             => $customer['name'],
            'GST'                  => $customer['gst'] ?? '',
            'Outstanding Invoices' => (string) $st['summary']['unpaid_count'],
            'Outstanding Amount'   => number_format($st['summary']['outstanding'], 2),
            'Customer Advance'     => number_format($st['summary']['advance'], 2),
        ];
        $infoBlock[$balance < -0.004 ? 'Customer Credit' : 'Net Receivable']
            = $balance < -0.004 ? number_format(abs($balance), 2) . ' Cr' : number_format($balance, 2);

        (new ExcelReport())->ledger(
            'Customer Ledger - ' . $customer['name'],
            $applied,
            $infoBlock,
            $st['opening'],
            ['Date', 'Voucher No', 'Type', 'Particulars', 'Method', 'Debit', 'Credit', 'Balance'],
            $rows,
            ['date', 'text', 'text', 'text', 'text', 'currency', 'currency', 'currency'],
            7,
            $st['closing'],
            [5, 6],
            'landscape'
        )->stream('customer_ledger_' . preg_replace('/[^a-z0-9]+/i', '_', $customer['name']) . '_' . date('Ymd_His'));
    }

    private function _forExport(int $id): array
    {
        $customer = \Config\Database::connect()->table('customers')->where('id', $id)->get()->getRowArray();
        if (! $customer) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Customer not found.');
        }

        $filters = $this->_filters();

        return [$customer, $filters, $this->_statement($customer, $filters)];
    }

    private function _filters(): array
    {
        $req  = $this->request;
        $date = static fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : '';
        $type = (string) $req->getGet('type');

        return [
            'customer_id' => (int) $req->getGet('customer_id'),
            'from'        => $date($req->getGet('from')),
            'to'          => $date($req->getGet('to')),
            'type'        => in_array($type, self::TYPES, true) ? $type : '',
            'q'           => trim((string) $req->getGet('q')),
            'show_paid'   => (bool) $req->getGet('show_paid'),
        ];
    }

    /**
     * Everything the page shows for one customer. Running balances are computed
     * over the customer's WHOLE history and only then filtered, so a filtered row
     * still shows the true balance; a From date carries the earlier balance
     * forward as the opening balance.
     */
    private function _statement(array $customer, array $f): array
    {
        $db         = \Config\Database::connect();
        $customerId = (int) $customer['id'];

        $invoices = $db->query("
            SELECT sr.id, sr.receipt_no, sr.receipt_date, sr.grand_total, sr.received_amount, sr.payment_mode,
                   COALESCE((SELECT SUM(cpa.paid_amount) FROM customer_payment_allocations cpa WHERE cpa.service_receipt_id = sr.id), 0) AS allocated
            FROM service_receipts sr
            WHERE sr.customer_id = ? AND sr.receipt_type = 'INVOICE'
        ", [$customerId])->getResultArray();

        $allocations = $db->query("
            SELECT cp.id AS payment_id, cp.payment_no, cp.payment_date, cp.payment_method, cpa.paid_amount, sr.receipt_no
            FROM customer_payment_allocations cpa
            INNER JOIN customer_payments cp ON cp.id = cpa.customer_payment_id
            INNER JOIN service_receipts sr ON sr.id = cpa.service_receipt_id
            WHERE cp.customer_id = ?
        ", [$customerId])->getResultArray();

        $vouchers = $db->table('customer_payments')->where('customer_id', $customerId)->get()->getResultArray();
        $remarksOf = [];
        foreach ($vouchers as $v) {
            $remarksOf[(int) $v['id']] = (string) ($v['remarks'] ?? '');
        }

        // 'order' breaks same-day ties so a day's invoices land before the money
        // received against them; 'seq' keeps the sort deterministic within a kind.
        $rows = [];

        foreach ($invoices as $inv) {
            $rows[] = $this->_row($inv['receipt_date'], 0, (int) $inv['id'], $inv['receipt_no'], 'Invoice', 'Service invoice', (float) $inv['grand_total'], 0.0, $inv['receipt_no'], '');

            $initial = round(max(0.0, (float) $inv['received_amount'] - (float) $inv['allocated']), 2);
            if ($initial > 0) {
                $rows[] = $this->_row($inv['receipt_date'], 1, (int) $inv['id'], $inv['receipt_no'], 'Payment', 'Received at invoice ' . $inv['receipt_no'], 0.0, $initial, $inv['receipt_no'], '', (string) ($inv['payment_mode'] ?? ''));
            }
        }

        foreach ($allocations as $a) {
            $rows[] = $this->_row($a['payment_date'], 2, (int) $a['payment_id'], $a['payment_no'], 'Payment', 'Payment against invoice ' . $a['receipt_no'], 0.0, (float) $a['paid_amount'], $a['receipt_no'], $remarksOf[(int) $a['payment_id']] ?? '', (string) ($a['payment_method'] ?? ''));
        }

        // Release 4.9.0AH: the separate Sales/Invoice module (sales + payments
        // tables — distinct from Service Received above) was never read by
        // this ledger, so its invoice debits and payment credits were
        // invisible here. advance_applied is deliberately NOT posted as a
        // second credit: it only spends advance money already credited once,
        // above, from customer_payments/projects.advance_amount.
        $sales = $db->query("
            SELECT id, invoice_no, sale_date, total_amount, advance_applied, paid_amount, balance_amount, status
            FROM sales
            WHERE customer_id = ?
        ", [$customerId])->getResultArray();

        $salePayments = $db->query("
            SELECT p.id, p.sale_id, p.amount, p.payment_date, p.reference, p.method, s.invoice_no
            FROM payments p
            INNER JOIN sales s ON s.id = p.sale_id
            WHERE s.customer_id = ?
        ", [$customerId])->getResultArray();

        foreach ($sales as $s) {
            $rows[] = $this->_row($s['sale_date'], 4, (int) $s['id'], $s['invoice_no'], 'Invoice', 'Sales invoice', (float) $s['total_amount'], 0.0, $s['invoice_no'], '');
        }
        foreach ($salePayments as $p) {
            $rows[] = $this->_row($p['payment_date'], 5, (int) $p['id'], $p['invoice_no'], 'Payment', 'Payment against invoice ' . $p['invoice_no'], 0.0, (float) $p['amount'], $p['invoice_no'], (string) ($p['reference'] ?? ''), (string) ($p['method'] ?? ''));
        }

        // Release 4.9.0AH: Customer Project Cash — genuine customer money
        // received for a project without selecting an invoice. Credit-only
        // (no invoice, no FIFO), scoped by the receipt's own stored
        // customer_id so it can never leak to another customer's ledger.
        $projectCash = $db->query("
            SELECT pcr.id, pcr.receipt_no, pcr.receipt_date, pcr.amount, pcr.reference, pcr.notes, pcr.payment_method, p.name AS project_name
            FROM project_cash_receipts pcr
            INNER JOIN projects p ON p.id = pcr.project_id
            WHERE pcr.customer_id = ? AND pcr.receipt_type = 'CUSTOMER_PROJECT_CASH'
        ", [$customerId])->getResultArray();

        foreach ($projectCash as $pc) {
            $remarks = (string) ($pc['notes'] ?? ($pc['reference'] ?? ''));
            $rows[]  = $this->_row($pc['receipt_date'], 6, (int) $pc['id'], $pc['receipt_no'], 'Unallocated Project Receipt', 'Unallocated project receipt - ' . $pc['project_name'], 0.0, (float) $pc['amount'], (string) ($pc['reference'] ?? ''), $remarks, (string) ($pc['payment_method'] ?? ''));
        }

        // Advance history = every advance received, voucher-based and project-based.
        $advRows      = [];
        $advanceTotal = 0.0;

        foreach ($vouchers as $v) {
            if ((float) $v['advance_amount'] > 0) {
                $remarks   = (string) ($v['remarks'] ?? '');
                $rows[]    = $this->_row($v['payment_date'], 3, (int) $v['id'], $v['payment_no'], 'Advance', 'Advance received from customer', 0.0, (float) $v['advance_amount'], '', $remarks, (string) ($v['payment_method'] ?? ''));
                $advRows[] = ['date' => $v['payment_date'], 'voucher' => $v['payment_no'], 'source' => 'Payment voucher', 'method' => (string) ($v['payment_method'] ?? ''), 'received' => (float) $v['advance_amount'], 'remarks' => $remarks !== '' ? $remarks : 'Advance Received'];
                $advanceTotal += (float) $v['advance_amount'];
            }
        }

        // Release 4.8.4J: each project with an advance is one credit row, read from the project itself.
        $projects = $db->query("
            SELECT id, name, advance_amount, advance_date, advance_payment_method, created_at
            FROM projects
            WHERE customer_id = ? AND advance_amount > 0
        ", [$customerId])->getResultArray();

        foreach ($projects as $p) {
            $date      = ProjectModel::advanceDate($p);
            $no        = ProjectModel::projectNumber((int) $p['id']);
            $amt       = round((float) $p['advance_amount'], 2);
            $rows[]    = $this->_row($date, 3, (int) $p['id'], $no, 'Advance', 'Project advance received - ' . $p['name'], 0.0, $amt, '', '', (string) ($p['advance_payment_method'] ?? ''));
            $advRows[] = ['date' => $date, 'voucher' => $no, 'source' => 'Project', 'method' => (string) ($p['advance_payment_method'] ?? ''), 'received' => $amt, 'remarks' => 'Project Advance - ' . $p['name']];
            $advanceTotal += $amt;
        }

        usort($rows, static fn ($x, $y) => [(string) $x['date'], $x['order'], $x['seq']] <=> [(string) $y['date'], $y['order'], $y['seq']]);

        $balance = 0.0;
        foreach ($rows as &$row) {
            $balance        = round($balance + $row['debit'] - $row['credit'], 2);
            $row['balance'] = $balance;
        }
        unset($row);

        // ---- filter (balances already final) ----
        $opening = 0.0;
        $shown   = [];
        $needle  = mb_strtolower($f['q']);
        foreach ($rows as $row) {
            if ($f['from'] !== '' && $row['date'] < $f['from']) {
                $opening = $row['balance'];
                continue;
            }
            if ($f['to'] !== '' && $row['date'] > $f['to']) {
                continue;
            }
            if ($f['type'] !== '' && $row['ttype'] !== $f['type']) {
                continue;
            }
            if ($needle !== '' && ! str_contains(mb_strtolower($row['voucher'] . ' ' . $row['particulars'] . ' ' . $row['ref'] . ' ' . $row['remarks'] . ' ' . $row['ttype'] . ' ' . $customer['name']), $needle)) {
                continue;
            }
            $shown[] = $row;
        }
        $closing = $shown ? end($shown)['balance'] : $opening;

        // ---- outstanding invoices ----
        $bills       = [];
        $outstanding = 0.0;
        $unpaidCount = 0;
        foreach ($invoices as $inv) {
            $amount = (float) $inv['grand_total'];
            // Paid mirrors the ledger's credits for this invoice: taken at creation + allocations.
            $paid = round(max((float) $inv['received_amount'], (float) $inv['allocated']), 2);
            $due  = round($amount - $paid, 2);
            if ($due > 0.004) {
                $outstanding += $due;
                $unpaidCount++;
            }
            $bills[] = [
                'no' => $inv['receipt_no'], 'date' => (string) $inv['receipt_date'], 'amount' => $amount, 'paid' => $paid, 'outstanding' => $due,
                'status' => $due <= 0.004 ? 'PAID' : ($paid > 0.004 ? 'PARTIAL' : 'PENDING'),
            ];
        }

        // Release 4.9.0AH: the Sales module's own invoices are now part of
        // "Outstanding Invoices" too, using its own authoritative running
        // fields (balance_amount already nets out advance_applied — see
        // SaleModel::_recalculateSaleRow()) rather than re-deriving them.
        foreach ($sales as $s) {
            $amount = (float) $s['total_amount'];
            $due    = round((float) $s['balance_amount'], 2);
            $paid   = round($amount - $due, 2);
            if ($due > 0.004) {
                $outstanding += $due;
                $unpaidCount++;
            }
            $bills[] = [
                'no' => $s['invoice_no'], 'date' => (string) $s['sale_date'], 'amount' => $amount, 'paid' => $paid, 'outstanding' => $due,
                'status' => $s['status'] === 'UNPAID' ? 'PENDING' : $s['status'],
            ];
        }
        usort($bills, static fn ($x, $y) => strcmp($x['date'], $y['date']));
        $billsShown = array_values(array_filter($bills, static function ($b) use ($f, $needle) {
            if (! $f['show_paid'] && $b['outstanding'] <= 0.004) {
                return false;
            }

            return $needle === '' || str_contains(mb_strtolower($b['no']), $needle);
        }));

        // ---- advance history with running total ----
        usort($advRows, static fn ($x, $y) => strcmp((string) $x['date'], (string) $y['date']));
        $running = 0.0;
        foreach ($advRows as &$a) {
            $running     += $a['received'];
            $a['running'] = round($running, 2);
        }
        unset($a);

        $outstanding = round($outstanding, 2);
        $advance     = round($advanceTotal, 2);

        return [
            'rows'         => $shown,
            'opening'      => $opening,
            'closing'      => $closing,
            'bills'        => $billsShown,
            'advance_rows' => $advRows,
            'summary'      => [
                'unpaid_count' => $unpaidCount,
                'outstanding'  => $outstanding,
                'advance'      => $advance,
                'net'          => round($outstanding - $advance, 2),
                'balance'      => $rows ? end($rows)['balance'] : 0.0,
            ],
        ];
    }

    // Release 4.9.0CB: $method is the stored payment method of the source record
    // ('' for invoices and anything with no method) - presentation only.
    private function _row(?string $date, int $order, int $seq, string $voucher, string $ttype, string $particulars, float $debit, float $credit, string $ref, string $remarks, string $method = ''): array
    {
        return [
            'date' => (string) $date, 'order' => $order, 'seq' => $seq, 'voucher' => $voucher, 'ttype' => $ttype,
            'particulars' => $particulars, 'debit' => $debit, 'credit' => $credit, 'ref' => $ref, 'remarks' => $remarks,
            'method' => $method,
        ];
    }
}
