<?php

namespace App\Controllers;

use App\Libraries\ExcelReport;
use App\Libraries\PdfReport;
use App\Libraries\TransactionMethods;
use App\Models\BankAccountModel;
use App\Models\BankTransactionModel;
use CodeIgniter\Controller;

/**
 * Release 4.8.4I (Phase E): Bank Statement — the complete transaction history
 * of one bank account with a running balance. Read-only: every figure is read
 * from bank_transactions by BankTransactionModel::statementFor(), which walks
 * the rows once from the account's opening balance; nothing is posted or
 * recalculated here.
 *
 * Releases 4.9.0D / 4.9.0D-1: the statement is the Bank Ledger, an accountant
 * passbook. This controller only labels each row with one of six transaction
 * types, writes a human-readable Particulars line from the owning module's rows
 * (read-only lookups, never the technical remarks), applies the Type and Search
 * filters and exports the same rows. Exports use the existing route with
 * ?export=excel|pdf, so no route was added.
 *
 * Type and Search filter which rows are LISTED; every balance stays the true
 * account balance (the summary strip and the Opening / Closing rows always
 * describe the selected period, never a filtered subset).
 */
class BankStatement extends Controller
{
    /** The only transaction types the UI knows. (Opening Balance is a row, not a filter.) */
    private const TYPES = ['Customer Receipt', 'Supplier Payment', 'Expense Payment', 'Project Transaction', 'Transfer', 'Manual Entry'];

    public function index()
    {
        $accounts  = (new BankAccountModel())->orderBy('bank_name', 'ASC')->orderBy('account_number', 'ASC')->findAll();
        $accountId = (int) $this->request->getGet('bank_account_id');

        // No account picked yet: open the first one rather than an empty page.
        if ($accountId <= 0 && $accounts) {
            $accountId = (int) $accounts[0]['id'];
        }

        $from = $this->_dateParam('from');
        $to   = $this->_dateParam('to');
        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $type = (string) $this->request->getGet('type');
        $type = in_array($type, self::TYPES, true) ? $type : '';
        $q    = trim((string) $this->request->getGet('q'));

        $statement = $accountId > 0 ? (new BankTransactionModel())->statementFor($accountId, $from, $to) : [];
        $rows      = [];
        $total     = 0;

        if ($statement !== []) {
            $decorated = $this->_decorate($statement['rows'], $statement['account']);
            $total     = count($decorated);
            $rows      = $this->_filter($decorated, $type, $q);
        }

        $export = (string) $this->request->getGet('export');
        if ($statement !== [] && in_array($export, ['excel', 'pdf'], true)) {
            return $this->_export($export, $statement, $rows, $from, $to, $type, $q);
        }

        // With no date filter the statement's closing balance IS the account's balance; say so if they ever differ.
        $balanceMismatch = $statement !== [] && $from === null && $to === null
            && abs($statement['closing'] - (float) $statement['account']['current_balance']) > 0.004;

        return view('bank_statement/index', [
            'accounts'        => $accounts,
            'accountId'       => $accountId,
            'from'            => $from,
            'to'              => $to,
            'type'            => $type,
            'q'               => $q,
            'types'           => self::TYPES,
            'statement'       => $statement,
            'rows'            => $rows,
            'totalRows'       => $total,
            'balanceMismatch' => $balanceMismatch,
        ]);
    }

    /**
     * Adds ttype / voucher / particulars / search text to every statement line.
     * Read-only: one batch per owning table, keyed '<reference_type>:<reference_id>'.
     */
    private function _decorate(array $lines, array $account): array
    {
        if (! $lines) {
            return [];
        }

        $db   = \Config\Database::connect();
        $refs = [];
        foreach ($db->table('bank_transactions')->select('id, reference_id, party_name, payment_mode')->whereIn('id', array_column($lines, 'id'))->get()->getResultArray() as $r) {
            $refs[(int) $r['id']] = ['ref' => (int) $r['reference_id'], 'party' => (string) ($r['party_name'] ?? ''), 'mode' => (string) ($r['payment_mode'] ?? '')];
        }

        $ids = [];
        foreach ($lines as $l) {
            $ids[$l['reference_type']][] = $refs[$l['id']]['ref'] ?? 0;
        }
        $info    = $this->_lookups($db, $ids);
        $methods = TransactionMethods::forBankTransactions(array_column($lines, 'id'));

        foreach ($lines as &$l) {
            $rt   = (string) $l['reference_type'];
            if ($rt === 'LOAN_DISBURSEMENT') {
                // Display only (Release 4.9.0BB): older rows carry the remark "Loan Disbursement – lender".
                $l['remarks'] = str_replace('Loan Disbursement', 'Loan Received', (string) $l['remarks']);
            }
            $ref  = $refs[$l['id']] ?? ['ref' => 0, 'party' => ''];
            $i    = $info[$rt . ':' . $ref['ref']] ?? [];
            $docs = $i['docs'] ?? '';

            switch (true) {
                case in_array($rt, ['CUSTOMER_PAYMENT', 'SERVICE_RECEIPT', 'SALE_PAYMENT'], true) && ! empty($i['party']):
                    $ttype = 'Customer Receipt';
                    // Release 4.9.0CB: a DIRECT service receipt is not an invoice, so it is not labelled one.
                    $text  = 'Customer: ' . $i['party'] . ($docs !== '' ? ' — ' . ($i['doc_label'] ?? 'Invoice') . ' ' . $docs : '');
                    break;
                case $rt === 'SUPPLIER_PAYMENT' && ! empty($i['party']):
                    $ttype = 'Supplier Payment';
                    $text  = 'Supplier: ' . $i['party'] . ($docs !== '' ? ' — ' . $docs : '');
                    break;
                case $rt === 'SUPPLIER_ADVANCE' && ! empty($i['party']):
                    $ttype = 'Supplier Payment';
                    $text  = 'Supplier: ' . $i['party'] . ($docs !== '' ? ' — Advance on ' . $docs : ' — Advance');
                    break;
                case $rt === 'EXPENSE' && ! empty($i['category']):
                    $ttype = 'Expense Payment';
                    $text  = 'Expense: ' . $i['category'] . (! empty($i['paid_to']) ? ' — ' . $i['paid_to'] : '');
                    break;
                case in_array($rt, ['PROJECT_ADVANCE', 'PROJECT_ADVANCE_DEPOSIT'], true) && ! empty($i['project']):
                    $ttype = 'Project Transaction';
                    $text  = (($i['receipt_type'] ?? '') === 'CUSTOMER_PROJECT_CASH' ? 'Unallocated Project Receipt — ' : '') . 'Project: ' . $i['project'] . (! empty($i['party']) ? ' — ' . $i['party'] : '');
                    break;
                case $l['label'] === 'Transfer In' || $l['label'] === 'Transfer Out':
                    $ttype   = 'Transfer';
                    $other   = $l['counterparty'] !== '' ? trim(preg_replace('/\s*\(.*\)\s*$/', '', $l['counterparty'])) : 'Other account';
                    $isOut   = $l['label'] === 'Transfer Out';
                    $text    = 'Transfer: ' . ($isOut ? $account['bank_name'] . ' → ' . $other : $other . ' → ' . $account['bank_name']);
                    break;
                case in_array($rt, ['CUSTOMER_PAYMENT', 'SERVICE_RECEIPT', 'SERVICE_RECEIPT_PAYMENT', 'SALE_PAYMENT'], true):
                    $ttype = 'Customer Receipt';
                    $text  = $this->_plain($l['remarks'], ['Customer Payment - ', 'Service Receipt - ', 'Invoice Payment - '], 'Customer: ');
                    break;
                case $rt === 'SUPPLIER_PAYMENT' || $rt === 'SUPPLIER_ADVANCE':
                    $ttype = 'Supplier Payment';
                    $text  = $this->_plain($l['remarks'], ['Supplier Payment - ', 'Supplier Advance - '], 'Supplier: ');
                    break;
                case $rt === 'EXPENSE':
                    $ttype = 'Expense Payment';
                    $text  = $this->_plain($l['remarks'], ['Expense - '], 'Expense: ');
                    break;
                case str_starts_with($rt, 'PROJECT_ADVANCE'):
                    $ttype = 'Project Transaction';
                    $text  = $this->_plain($l['remarks'], ['Project Advance Received - ', 'Project Advance - ', 'Unallocated Project Receipt — '], 'Project: ');
                    break;
                default:
                    // Manual vouchers (deposit / withdrawal / daybook) and loan EMIs: who it was from / to.
                    $ttype = 'Manual Entry';
                    $who   = $ref['party'] !== '' ? $ref['party'] : ($rt === 'LOAN_PAYMENT' ? $this->_plain($l['remarks'], ['Loan EMI Payment – ', 'Loan Payment – '], 'Loan: ') : '');
                    $text  = $who !== '' && $ref['party'] !== ''
                        ? ($l['deposit'] > 0 ? 'Received from ' : 'Paid to ') . $who
                        : ($who !== '' ? $who : ($l['remarks'] !== '' ? $l['remarks'] : ($l['deposit'] > 0 ? 'Bank deposit' : 'Bank withdrawal')));
            }

            // Release 4.9.0CB: HOW it was paid, read from the owning record (never inferred from the ledger being a bank ledger).
            // Manual vouchers carry their own payment_mode; a transfer between accounts has no payment method.
            $method           = $methods[$l['id']] ?? '';
            $l['method']      = $method;
            $l['ttype']       = $ttype;
            $l['voucher']     = $l['reference'];
            $l['particulars'] = $text;
            // Hidden search text: everything the row can be found by, including the raw remarks.
            $l['search'] = mb_strtolower(implode(' ', [
                $l['voucher'], $l['reference_sub'], $text, $l['remarks'], $ttype, $l['label'], $l['category'], $l['counterparty'],
                $docs, $i['party'] ?? '', $i['project'] ?? '', $i['category'] ?? '', $i['paid_to'] ?? '', $ref['party'], pm_label($method, ''),
            ]));
        }
        unset($l);

        return $lines;
    }

    /** '<reference_type>:<reference_id>' => party / docs / project / category / paid_to, from the owning tables. */
    private function _lookups($db, array $ids): array
    {
        $out = [];
        $ids = array_map(static fn ($v) => array_values(array_unique(array_filter($v))), $ids);

        if (! empty($ids['CUSTOMER_PAYMENT'])) {
            $rows = $db->query(
                'SELECT cp.id, c.name AS party, GROUP_CONCAT(sr.receipt_no ORDER BY sr.id SEPARATOR ", ") AS docs
                 FROM customer_payments cp
                 INNER JOIN customers c ON c.id = cp.customer_id
                 LEFT JOIN customer_payment_allocations a ON a.customer_payment_id = cp.id
                 LEFT JOIN service_receipts sr ON sr.id = a.service_receipt_id
                 WHERE cp.id IN ? GROUP BY cp.id, c.name',
                [$ids['CUSTOMER_PAYMENT']]
            )->getResultArray();
            foreach ($rows as $r) {
                $out['CUSTOMER_PAYMENT:' . $r['id']] = ['party' => $r['party'], 'docs' => (string) $r['docs']];
            }
        }
        if (! empty($ids['SERVICE_RECEIPT'])) {
            $rows = $db->query(
                'SELECT sr.id, c.name AS party, sr.receipt_no AS docs, sr.receipt_type FROM service_receipts sr INNER JOIN customers c ON c.id = sr.customer_id WHERE sr.id IN ?',
                [$ids['SERVICE_RECEIPT']]
            )->getResultArray();
            foreach ($rows as $r) {
                $out['SERVICE_RECEIPT:' . $r['id']] = ['party' => $r['party'], 'docs' => (string) $r['docs'], 'doc_label' => $r['receipt_type'] === 'INVOICE' ? 'Invoice' : 'Receipt'];
            }
        }
        // Release 4.9.0I: Project Invoice Payment (payments.id) — the
        // invoice's own customer/invoice_no, same shape as CUSTOMER_PAYMENT/
        // SERVICE_RECEIPT above so it classifies as "Customer Receipt" too.
        if (! empty($ids['SALE_PAYMENT'])) {
            $rows = $db->query(
                'SELECT py.id, c.name AS party, s.invoice_no AS docs
                 FROM payments py
                 INNER JOIN sales s ON s.id = py.sale_id
                 LEFT JOIN customers c ON c.id = s.customer_id
                 WHERE py.id IN ?',
                [$ids['SALE_PAYMENT']]
            )->getResultArray();
            foreach ($rows as $r) {
                $out['SALE_PAYMENT:' . $r['id']] = ['party' => (string) $r['party'], 'docs' => (string) $r['docs']];
            }
        }
        if (! empty($ids['SUPPLIER_PAYMENT'])) {
            $rows = $db->query(
                'SELECT sp.id, s.name AS party,
                        GROUP_CONCAT(COALESCE(gp.purchase_no, CONCAT("PUR-", LPAD(a.purchase_id, 6, "0"))) ORDER BY a.id SEPARATOR ", ") AS docs
                 FROM supplier_payments sp
                 INNER JOIN suppliers s ON s.id = sp.supplier_id
                 LEFT JOIN supplier_payment_allocations a ON a.supplier_payment_id = sp.id
                 LEFT JOIN general_purchases gp ON a.purchase_type = "GENERAL" AND gp.id = a.purchase_id
                 WHERE sp.id IN ? GROUP BY sp.id, s.name',
                [$ids['SUPPLIER_PAYMENT']]
            )->getResultArray();
            foreach ($rows as $r) {
                $out['SUPPLIER_PAYMENT:' . $r['id']] = ['party' => $r['party'], 'docs' => (string) $r['docs']];
            }
        }
        if (! empty($ids['SUPPLIER_ADVANCE'])) {
            $rows = $db->query(
                'SELECT gp.id, s.name AS party, gp.purchase_no AS docs FROM general_purchases gp INNER JOIN suppliers s ON s.id = gp.supplier_id WHERE gp.id IN ?',
                [$ids['SUPPLIER_ADVANCE']]
            )->getResultArray();
            foreach ($rows as $r) {
                $out['SUPPLIER_ADVANCE:' . $r['id']] = ['party' => $r['party'], 'docs' => (string) $r['docs']];
            }
        }
        if (! empty($ids['EXPENSE'])) {
            $rows = $db->query(
                'SELECT e.id, c.category_name AS category, e.paid_to, p.name AS project
                 FROM expenses e
                 LEFT JOIN expense_categories c ON c.id = e.category_id
                 LEFT JOIN projects p ON p.id = e.project_id
                 WHERE e.id IN ?',
                [$ids['EXPENSE']]
            )->getResultArray();
            foreach ($rows as $r) {
                $out['EXPENSE:' . $r['id']] = ['category' => (string) $r['category'], 'paid_to' => (string) $r['paid_to'], 'project' => (string) $r['project']];
            }
        }
        if (! empty($ids['PROJECT_ADVANCE_DEPOSIT'])) {
            $rows = $db->query(
                'SELECT p.id, p.name AS project, c.name AS party FROM projects p LEFT JOIN customers c ON c.id = p.customer_id WHERE p.id IN ?',
                [$ids['PROJECT_ADVANCE_DEPOSIT']]
            )->getResultArray();
            foreach ($rows as $r) {
                $out['PROJECT_ADVANCE_DEPOSIT:' . $r['id']] = ['project' => (string) $r['project'], 'party' => (string) $r['party']];
            }
        }
        if (! empty($ids['PROJECT_ADVANCE'])) {
            $rows = $db->query(
                'SELECT r.id, r.receipt_type, p.name AS project, c.name AS party
                 FROM project_cash_receipts r
                 INNER JOIN projects p ON p.id = r.project_id
                 LEFT JOIN customers c ON c.id = COALESCE(r.customer_id, p.customer_id)
                 WHERE r.id IN ?',
                [$ids['PROJECT_ADVANCE']]
            )->getResultArray();
            foreach ($rows as $r) {
                $out['PROJECT_ADVANCE:' . $r['id']] = ['project' => (string) $r['project'], 'party' => (string) $r['party'], 'receipt_type' => (string) $r['receipt_type']];
            }
        }

        return $out;
    }

    /** Last-resort readable text from a system remark: drops the module's own prefix. */
    private function _plain(string $remarks, array $prefixes, string $label): string
    {
        foreach ($prefixes as $p) {
            if (str_starts_with($remarks, $p)) {
                $rest = trim(substr($remarks, strlen($p)));

                return $rest !== '' ? $label . $rest : rtrim($label, ': ');
            }
        }

        return $remarks !== '' ? $remarks : rtrim($label, ': ');
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
        $account = $statement['account'];
        $title   = 'Bank Ledger - ' . $account['bank_name'] . ' (' . $account['account_number'] . ')';
        $filters = [
            'From'   => $from !== null ? date('d-m-Y', strtotime($from)) : '',
            'To'     => $to !== null ? date('d-m-Y', strtotime($to)) : '',
            'Type'   => $type,
            'Search' => $q,
        ];
        $file = 'bank_ledger_' . preg_replace('/[^a-z0-9]+/i', '_', $account['bank_name'] . '_' . $account['account_number']) . '_' . date('Ymd_His');

        if ($format === 'pdf') {
            (new PdfReport())->render('pdf/bank_ledger', [
                'account' => $account, 'statement' => $statement, 'rows' => $rows,
            ], ['title' => $title, 'orientation' => 'landscape', 'filters' => $filters]);

            return null;
        }

        $excelRows = [];
        foreach ($rows as $r) {
            $excelRows[] = [$r['date'], $r['voucher'], $r['ttype'], $r['particulars'], $this->_methodText($r), $r['deposit'], $r['withdrawal'], $r['balance']];
        }

        (new ExcelReport())->ledger(
            $title,
            $filters,
            [
                'Bank'           => $account['bank_name'],
                'Account Name'   => $account['account_name'],
                'Account Number' => $account['account_number'],
                'Money In'       => number_format($statement['deposits'], 2),
                'Money Out'      => number_format($statement['withdrawals'], 2),
            ],
            $statement['opening'],
            ['Date', 'Voucher No', 'Type', 'Particulars', 'Method', 'Money In', 'Money Out', 'Balance'],
            $excelRows,
            ['date', 'text', 'text', 'text', 'text', 'currency', 'currency', 'currency'],
            7,
            $statement['closing'],
            [5, 6],
            'landscape'
        )->stream($file);

        return null;
    }

    /** Method text for an export: a transfer between accounts has no payment method; anything else with none stored is "Not recorded". */
    private function _methodText(array $r): string
    {
        return pm_label($r['method'] ?? '', $r['ttype'] === 'Transfer' ? '—' : 'Not recorded');
    }

    /** A valid Y-m-d query parameter, or null. */
    private function _dateParam(string $name): ?string
    {
        $value = trim((string) $this->request->getGet($name));
        $date  = \DateTime::createFromFormat('Y-m-d', $value);

        return ($date && $date->format('Y-m-d') === $value) ? $value : null;
    }
}
