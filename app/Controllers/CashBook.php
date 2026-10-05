<?php

namespace App\Controllers;

use App\Libraries\CashMovements;
use App\Libraries\ExcelReport;
use App\Libraries\PdfReport;
use App\Models\CashOpeningBalanceModel;
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
 * opening cash unless one is stored (Release 4.9.0CC, CashOpeningBalanceModel): from its
 * date the walk starts at that amount and earlier movements are ignored; without one, cash
 * starts at 0 with the first movement.
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

        // Release 4.9.0CC: a stored opening cash applies to a report that reaches its date.
        $ob      = CashOpeningBalanceModel::effective((new CashOpeningBalanceModel())->current(), $to);
        $balance = $ob ? $ob['amount'] : 0.0;
        $opening = $balance;
        $effFrom = ($ob && ($from === null || $from < $ob['opening_date'])) ? $ob['opening_date'] : $from;
        $in      = 0.0;
        $out     = 0.0;
        $rows    = [];

        foreach ($moves as $m) {
            if ($to !== null && $m['date'] > $to) {
                continue;
            }
            if ($ob && $m['date'] < $ob['opening_date']) {
                continue;
            }

            $balance = round($balance + $m['in'] - $m['out'], 2);

            if ($effFrom !== null && $m['date'] < $effFrom) {
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
            // The Opening Cash row: the stored opening's own date/remarks when the period starts at it.
            'opening_date'       => $effFrom,
            'opening_is_setting' => $ob !== null && $effFrom === $ob['opening_date'],
            'opening_remarks'    => $ob['remarks'] ?? null,
            'before_opening_hidden' => $ob !== null && $from !== null && $from < $ob['opening_date'],
            // Release 4.9.0CF: display only. The permanent opening that this report starts from, and how many
            // recorded cash movements dated before it are left out (they stay in their own ledgers).
            'locked_opening'     => $ob,
            'excluded_before_opening' => $ob ? CashOpeningBalanceModel::excludedBy($ob['opening_date'])['count'] : 0,
            'cash_in'  => round($in, 2),
            'cash_out' => round($out, 2),
            'closing'  => $balance,
            'rows'     => $rows,
        ];
    }

    /** Every cash movement (rules now live in App\Libraries\CashMovements, shared with the Monthly Statement): date, ttype, voucher, particulars, in, out, search, seq. */
    private function _movements(): array
    {
        return (new CashMovements())->all();
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
            $excelRows[] = [$r['date'], $r['voucher'], $r['ttype'], $r['particulars'], pm_label($r['method'] ?? '', 'Not recorded'), $r['in'], $r['out'], $r['balance']];
        }

        (new ExcelReport())->ledger(
            $title,
            $filters,
            [
                'Cash Received' => number_format($statement['cash_in'], 2),
                'Cash Paid'     => number_format($statement['cash_out'], 2),
            ],
            $statement['opening'],
            ['Date', 'Voucher No', 'Type', 'Particulars', 'Method', 'Cash In', 'Cash Out', 'Balance'],
            $excelRows,
            ['date', 'text', 'text', 'text', 'text', 'currency', 'currency', 'currency'],
            7,
            $statement['closing'],
            [5, 6],
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
