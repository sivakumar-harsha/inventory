<?php

namespace App\Controllers;

use App\Libraries\ExcelReport;
use App\Libraries\PdfReport;
use CodeIgniter\Controller;

/**
 * Release 4.9.0E: Cash Book — the accountant's daily cash verification page.
 *
 * The app has no cash account and posts nothing for cash: bank_transactions only
 * holds bank postings. So this ledger is DERIVED and strictly read-only — every
 * cash movement is read from the record that owns it, where the payment method is
 * Cash (customer payments, service receipts, supplier payments, General Purchase
 * advances, expenses, project cash receipts, project advances, loan payments) and
 * from manual bank deposits / withdrawals made in Cash (cash taken to or brought
 * from a bank). Nothing is posted, numbered or recalculated here.
 *
 * The running balance is walked once over the full history up to the To date, then
 * the From date splits it into the Opening row and the listed rows. Type and Search
 * only hide rows: every balance stays the true cash balance. There is no stored
 * opening cash, so cash starts at 0 with the first movement.
 * Exports use the same route with ?export=excel|pdf.
 */
class CashBook extends Controller
{
    /** The only transaction types the UI knows. (Opening Cash is a row, not a filter.) */
    private const TYPES = ['Customer Receipt', 'Supplier Payment', 'Expense Payment', 'Project Transaction', 'Cash Transfer', 'Manual Entry'];

    public function index()
    {
        $from = $this->_dateParam('from');
        $to   = $this->_dateParam('to');
        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $type = (string) $this->request->getGet('type');
        $type = in_array($type, self::TYPES, true) ? $type : '';
        $q    = trim((string) $this->request->getGet('q'));

        $statement = $this->_statement($from, $to);
        $rows      = $this->_filter($statement['rows'], $type, $q);

        $export = (string) $this->request->getGet('export');
        if (in_array($export, ['excel', 'pdf'], true)) {
            return $this->_export($export, $statement, $rows, $from, $to, $type, $q);
        }

        return view('cash_book/index', [
            'from'      => $from,
            'to'        => $to,
            'type'      => $type,
            'q'         => $q,
            'types'     => self::TYPES,
            'statement' => $statement,
            'rows'      => $rows,
            'totalRows' => count($statement['rows']),
        ]);
    }

    /** opening / cash_in / cash_out / closing and the running-balance rows of the selected period. */
    private function _statement(?string $from, ?string $to): array
    {
        $moves = $this->_movements();

        usort($moves, static fn ($a, $b) => [$a['date'], $a['seq']] <=> [$b['date'], $b['seq']]);

        $balance = 0.0;
        $opening = 0.0;
        $in      = 0.0;
        $out     = 0.0;
        $rows    = [];

        foreach ($moves as $m) {
            if ($to !== null && $m['date'] > $to) {
                continue;
            }

            $balance = round($balance + $m['in'] - $m['out'], 2);

            if ($from !== null && $m['date'] < $from) {
                $opening = $balance;
                continue;
            }

            $in  += $m['in'];
            $out += $m['out'];
            $m['balance'] = $balance;
            $rows[]       = $m;
        }

        return [
            'opening'  => $opening,
            'cash_in'  => round($in, 2),
            'cash_out' => round($out, 2),
            'closing'  => $balance,
            'rows'     => $rows,
        ];
    }

    /** Every cash movement, oldest first is decided by the caller: date, ttype, voucher, particulars, in, out, search, seq. */
    private function _movements(): array
    {
        $db  = \Config\Database::connect();
        $out = [];
        $seq = 0;

        $add = static function (string $date, string $voucher, string $ttype, string $particulars, float $in, float $outAmt, array $search) use (&$out, &$seq): void {
            $in    = round($in, 2);
            $outAmt = round($outAmt, 2);
            if ($date === '' || ($in <= 0 && $outAmt <= 0)) {
                return;
            }
            $out[] = [
                'date' => substr($date, 0, 10), 'voucher' => $voucher, 'ttype' => $ttype, 'particulars' => $particulars,
                'in' => $in, 'out' => $outAmt, 'seq' => ++$seq,
                'search' => mb_strtolower(implode(' ', array_merge([$voucher, $ttype, $particulars], $search))),
            ];
        };
        $has = static fn (string $t): bool => $db->tableExists($t);

        // Customer payment vouchers taken in cash.
        if ($has('customer_payments') && $has('customers')) {
            $docs = $has('customer_payment_allocations') && $has('service_receipts')
                ? '(SELECT GROUP_CONCAT(sr.receipt_no ORDER BY sr.id SEPARATOR ", ") FROM customer_payment_allocations a INNER JOIN service_receipts sr ON sr.id = a.service_receipt_id WHERE a.customer_payment_id = cp.id)'
                : "''";
            foreach ($db->query(
                "SELECT cp.payment_no, cp.payment_date, cp.total_amount, cp.remarks, cp.reference_no, c.name AS party, $docs AS docs
                 FROM customer_payments cp INNER JOIN customers c ON c.id = cp.customer_id
                 WHERE cp.payment_method = 'CASH'"
            )->getResultArray() as $r) {
                $add($r['payment_date'], (string) $r['payment_no'], 'Customer Receipt',
                    'Customer: ' . $r['party'] . ($r['docs'] ? ' — Invoice ' . $r['docs'] : ''),
                    (float) $r['total_amount'], 0.0, [(string) $r['party'], (string) $r['docs'], (string) $r['remarks'], (string) $r['reference_no']]);
            }
        }

        // Service receipts taken in cash: only the counter share (the rest arrived through payment vouchers).
        if ($has('service_receipts')) {
            $alloc = $has('customer_payment_allocations')
                ? 'COALESCE((SELECT SUM(a.paid_amount) FROM customer_payment_allocations a WHERE a.service_receipt_id = sr.id), 0)'
                : '0';
            foreach ($db->query(
                "SELECT sr.receipt_no, sr.receipt_date, sr.received_amount - $alloc AS own, sr.remarks, COALESCE(c.name, sr.customer_name) AS party
                 FROM service_receipts sr LEFT JOIN customers c ON c.id = sr.customer_id
                 WHERE sr.payment_mode = 'CASH'"
            )->getResultArray() as $r) {
                $add($r['receipt_date'], (string) $r['receipt_no'], 'Customer Receipt',
                    'Customer: ' . $r['party'] . ' — Invoice ' . $r['receipt_no'],
                    max(0.0, (float) $r['own']), 0.0, [(string) $r['party'], (string) $r['remarks']]);
            }
        }

        // Supplier payment vouchers paid in cash (GPA- vouchers only mirror a General Purchase advance, counted below).
        if ($has('supplier_payments') && $has('suppliers')) {
            $docs = $has('supplier_payment_allocations') && $has('general_purchases')
                ? '(SELECT GROUP_CONCAT(COALESCE(gp.purchase_no, CONCAT("PUR-", LPAD(a.purchase_id, 6, "0"))) ORDER BY a.id SEPARATOR ", ")
                    FROM supplier_payment_allocations a LEFT JOIN general_purchases gp ON a.purchase_type = "GENERAL" AND gp.id = a.purchase_id
                    WHERE a.supplier_payment_id = sp.id)'
                : "''";
            foreach ($db->query(
                "SELECT sp.payment_no, sp.payment_date, sp.total_amount, sp.remarks, sp.reference_no, s.name AS party, $docs AS docs
                 FROM supplier_payments sp INNER JOIN suppliers s ON s.id = sp.supplier_id
                 WHERE LOWER(TRIM(sp.payment_method)) = 'cash' AND sp.payment_no NOT LIKE 'GPA-%'"
            )->getResultArray() as $r) {
                $add($r['payment_date'], (string) $r['payment_no'], 'Supplier Payment',
                    'Supplier: ' . $r['party'] . ($r['docs'] ? ' — ' . $r['docs'] : ''),
                    0.0, (float) $r['total_amount'], [(string) $r['party'], (string) $r['docs'], (string) $r['remarks'], (string) $r['reference_no']]);
            }
        }

        // General Purchase advances paid in cash.
        if ($has('general_purchases') && $has('suppliers')) {
            foreach ($db->query(
                "SELECT gp.purchase_no, gp.purchase_date, gp.advance_paid, gp.bill_no, gp.remarks, s.name AS party
                 FROM general_purchases gp INNER JOIN suppliers s ON s.id = gp.supplier_id
                 WHERE gp.advance_paid > 0 AND LOWER(TRIM(gp.payment_method)) = 'cash'"
            )->getResultArray() as $r) {
                $add($r['purchase_date'], (string) $r['purchase_no'], 'Supplier Payment',
                    'Supplier: ' . $r['party'] . ' — Advance on ' . $r['purchase_no'],
                    0.0, (float) $r['advance_paid'], [(string) $r['party'], (string) $r['bill_no'], (string) $r['remarks']]);
            }
        }

        // Expenses paid in cash (cancelled ones are already reversed).
        if ($has('expenses')) {
            foreach ($db->query(
                "SELECT e.expense_no, e.expense_date, e.amount, e.paid_to, e.remarks, c.category_name, p.name AS project
                 FROM expenses e
                 LEFT JOIN expense_categories c ON c.id = e.category_id
                 LEFT JOIN projects p ON p.id = e.project_id
                 WHERE e.payment_method = 'CASH' AND e.status = 'PAID'"
            )->getResultArray() as $r) {
                $add($r['expense_date'], (string) $r['expense_no'], 'Expense Payment',
                    'Expense: ' . ($r['category_name'] ?: 'General') . ($r['paid_to'] ? ' — ' . $r['paid_to'] : ''),
                    0.0, (float) $r['amount'], [(string) $r['category_name'], (string) $r['paid_to'], (string) $r['project'], (string) $r['remarks']]);
            }
        }

        // Project cash receipts and project advances received in cash.
        if ($has('project_cash_receipts') && $has('projects')) {
            foreach ($db->query(
                "SELECT r.receipt_no, r.receipt_date, r.amount, r.reference, r.notes, p.name AS project, c.name AS party
                 FROM project_cash_receipts r
                 INNER JOIN projects p ON p.id = r.project_id
                 LEFT JOIN customers c ON c.id = COALESCE(r.customer_id, p.customer_id)
                 WHERE r.payment_method = 'CASH'"
            )->getResultArray() as $r) {
                $add($r['receipt_date'], (string) $r['receipt_no'], 'Project Transaction',
                    'Project: ' . $r['project'] . ($r['party'] ? ' — ' . $r['party'] : ''),
                    (float) $r['amount'], 0.0, [(string) $r['project'], (string) $r['party'], (string) $r['reference'], (string) $r['notes']]);
            }
        }
        if ($has('projects') && $db->fieldExists('advance_payment_method', 'projects')) {
            foreach ($db->query(
                "SELECT p.id, COALESCE(p.advance_date, DATE(p.created_at)) AS d, p.advance_amount, p.advance_notes, p.name AS project, c.name AS party
                 FROM projects p LEFT JOIN customers c ON c.id = p.customer_id
                 WHERE p.advance_amount > 0 AND UPPER(p.advance_payment_method) = 'CASH'"
            )->getResultArray() as $r) {
                $add((string) $r['d'], 'PRJ-' . str_pad((string) $r['id'], 6, '0', STR_PAD_LEFT), 'Project Transaction',
                    'Project: ' . $r['project'] . ($r['party'] ? ' — ' . $r['party'] : ' — Advance'),
                    (float) $r['advance_amount'], 0.0, [(string) $r['project'], (string) $r['party'], 'advance', (string) $r['advance_notes']]);
            }
        }

        // Loan instalments paid in cash.
        if ($has('loan_payments') && $has('loans')) {
            foreach ($db->query(
                "SELECT lp.payment_date, lp.total_paid, lp.reference_no, lp.remarks, l.loan_no, l.lender_name
                 FROM loan_payments lp INNER JOIN loans l ON l.id = lp.loan_id
                 WHERE lp.payment_method = 'CASH'"
            )->getResultArray() as $r) {
                $add($r['payment_date'], (string) ($r['reference_no'] ?: $r['loan_no']), 'Manual Entry',
                    'Loan: ' . $r['lender_name'] . ' — Instalment',
                    0.0, (float) $r['total_paid'], [(string) $r['loan_no'], (string) $r['lender_name'], (string) $r['remarks']]);
            }
        }

        // Cash taken to a bank (deposit made in cash) or brought from one (withdrawal in cash).
        if ($has('bank_transactions') && $has('bank_accounts') && $db->fieldExists('payment_mode', 'bank_transactions')) {
            foreach ($db->query(
                "SELECT bt.transaction_date, bt.transaction_type, bt.amount, bt.reference_no, bt.remarks, bt.party_name, ba.bank_name, ba.account_name
                 FROM bank_transactions bt INNER JOIN bank_accounts ba ON ba.id = bt.bank_account_id
                 WHERE (bt.reference_type IN ('MANUAL_DEPOSIT', 'MANUAL_WITHDRAWAL') AND UPPER(bt.payment_mode) = 'CASH')
                    OR (bt.reference_type = 'BANK_DAYBOOK' AND UPPER(bt.category) IN ('CASH DEPOSIT', 'CASH WITHDRAWAL'))"
            )->getResultArray() as $r) {
                $toBank = $r['transaction_type'] === 'DEPOSIT';
                $add($r['transaction_date'], (string) $r['reference_no'], 'Cash Transfer',
                    'Cash Transfer: ' . ($toBank ? 'Cash → ' . $r['bank_name'] : $r['bank_name'] . ' → Cash'),
                    $toBank ? 0.0 : (float) $r['amount'], $toBank ? (float) $r['amount'] : 0.0,
                    [(string) $r['bank_name'], (string) $r['account_name'], (string) $r['party_name'], (string) $r['remarks']]);
            }
        }

        return $out;
    }

    private function _filter(array $rows, string $type, string $q): array
    {
        $needle = mb_strtolower($q);

        return array_values(array_filter($rows, static function ($r) use ($type, $needle) {
            if ($type !== '' && $r['ttype'] !== $type) {
                return false;
            }

            return $needle === '' || str_contains($r['search'], $needle);
        }));
    }

    private function _export(string $format, array $statement, array $rows, ?string $from, ?string $to, string $type, string $q)
    {
        $title   = 'Cash Book';
        $filters = [
            'From'   => $from !== null ? date('d-m-Y', strtotime($from)) : '',
            'To'     => $to !== null ? date('d-m-Y', strtotime($to)) : '',
            'Type'   => $type,
            'Search' => $q,
        ];
        $file = 'cash_book_' . date('Ymd_His');

        if ($format === 'pdf') {
            (new PdfReport())->render('pdf/cash_ledger', ['statement' => $statement, 'rows' => $rows], [
                'title' => $title, 'orientation' => 'landscape', 'filters' => $filters,
            ]);

            return null;
        }

        $excelRows = [];
        foreach ($rows as $r) {
            $excelRows[] = [$r['date'], $r['voucher'], $r['ttype'], $r['particulars'], $r['in'], $r['out'], $r['balance']];
        }

        (new ExcelReport())->ledger(
            $title,
            $filters,
            [
                'Cash Received' => number_format($statement['cash_in'], 2),
                'Cash Paid'     => number_format($statement['cash_out'], 2),
            ],
            $statement['opening'],
            ['Date', 'Voucher No', 'Type', 'Particulars', 'Cash In', 'Cash Out', 'Balance'],
            $excelRows,
            ['date', 'text', 'text', 'text', 'currency', 'currency', 'currency'],
            6,
            $statement['closing'],
            [4, 5],
            'landscape'
        )->stream($file);

        return null;
    }

    /** A valid Y-m-d query parameter, or null. */
    private function _dateParam(string $name): ?string
    {
        $value = trim((string) $this->request->getGet($name));
        $date  = \DateTime::createFromFormat('Y-m-d', $value);

        return ($date && $date->format('Y-m-d') === $value) ? $value : null;
    }
}
