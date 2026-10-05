<?php

namespace App\Controllers;

use App\Libraries\ExcelReport;
use App\Libraries\MonthlyStatement as StatementEngine;
use App\Libraries\PdfReport;
use CodeIgniter\Controller;

/**
 * Release 4.9.0BT: Overall Monthly Statement — the consolidated cash/bank MOVEMENT of one
 * month: opening + receipts - payments +/- transfers = closing. Strictly read-only, GET
 * only, behind the auth filter. The accounting lives in App\Libraries\MonthlyStatement.
 * Exports use the same route with ?export=excel|pdf.
 */
class MonthlyStatement extends Controller
{
    public function index()
    {
        $engine = new StatementEngine();

        $month   = trim((string) $this->request->getGet('month'));
        $account = trim((string) $this->request->getGet('account'));
        $mode    = trim((string) $this->request->getGet('mode'));
        $export  = (string) $this->request->getGet('export');

        $error = null;
        if ($month === '') {
            $month = date('Y-m');
        } elseif (! StatementEngine::validMonth($month)) {
            $error = 'Invalid month. Use the format YYYY-MM (for example 2026-09).';
        }

        $accountId = 0;
        if ($account !== '' && $account !== '0') {
            $ids = array_map('intval', array_column($engine->accounts(), 'id'));
            if (! ctype_digit($account) || ! in_array((int) $account, $ids, true)) {
                $error = $error ?? 'Unknown bank account.';
            } else {
                $accountId = (int) $account;
            }
        }

        if ($mode === '') {
            $mode = 'all';
        } elseif (! isset(StatementEngine::MODES[$mode])) {
            $error = $error ?? 'Invalid mode. Choose All, Cash or Bank.';
        }

        if ($error !== null) {
            return $this->response->setStatusCode(400)->setBody(view('monthly_statement/index', [
                's' => null, 'error' => $error, 'month' => date('Y-m'), 'accountId' => 0, 'mode' => 'all',
                'accounts' => $engine->accounts(), 'modes' => StatementEngine::MODES,
            ]));
        }

        $s = $engine->build($month, $accountId, $mode);

        if ($export === 'pdf') {
            return $this->_pdf($s);
        }
        if ($export === 'excel') {
            return $this->_excel($s);
        }

        return view('monthly_statement/index', [
            's' => $s, 'error' => null, 'month' => $month, 'accountId' => $accountId, 'mode' => $mode,
            'accounts' => $s['accounts'], 'modes' => StatementEngine::MODES,
        ]);
    }

    private function _filters(array $s): array
    {
        return [
            'Month'        => $s['label'],
            'Bank Account' => $s['account'] ? $s['account']['bank_name'] . ' — ' . $s['account']['account_name'] : 'All Accounts',
            'Mode'         => StatementEngine::MODES[$s['mode']],
        ];
    }

    private function _pdf(array $s)
    {
        (new PdfReport())->render('pdf/monthly_statement', ['s' => $s], [
            'title' => 'Monthly Statement — ' . $s['label'], 'orientation' => 'landscape', 'filters' => $this->_filters($s),
            'filename' => 'monthly_statement_' . $s['month'] . '.pdf',
        ]);

        return null;
    }

    private function _excel(array $s)
    {
        // 4.9.0BZ: Section | Particulars | Debit — Money Out | Credit — Money In | Balance / Net | Date.
        // Money Out goes only in Debit, Money In only in Credit (positive, opposite cell blank); opening, closing,
        // net movement, transfers and account detail keep their sign in Balance / Net. Date holds real date cells (header + gap rows), as in 4.9.0BT.
        $showBank = $s['include_bank'];
        $showCash = $s['include_cash'];
        $b = static fn (string $t, bool $bold = true): array => ['v' => $t, 't' => 'text', 'b' => $bold];
        $m = static fn ($v, bool $bold = false): array => ['v' => round((float) $v, 2), 't' => 'currency', 'b' => $bold];
        $blank = ['v' => '', 't' => 'blank'];

        $rows = [];
        // $side: 'D' = Debit (Money Out), 'C' = Credit (Money In), 'N' = balance / net / transfer (signed)
        $row  = static function (string $section, $label, $amount, string $side, bool $bold = false) use (&$rows, $b, $m, $blank): void {
            $rows[] = [$b($section, $bold), $b((string) $label, $bold), $side === 'D' ? $m($amount, $bold) : $blank, $side === 'C' ? $m($amount, $bold) : $blank, $side === 'N' ? $m($amount, $bold) : $blank, $blank];
        };

        $rows[] = [$b('HEADER'), $b('Month', false), $blank, $blank, $blank, ['v' => $s['label'], 't' => 'text']];
        $rows[] = [$b('HEADER'), $b('Period from', false), $blank, $blank, $blank, ['v' => $s['from'], 't' => 'date']];
        $rows[] = [$b('HEADER'), $b('Period to', false), $blank, $blank, $blank, ['v' => $s['to'], 't' => 'date']];
        $rows[] = [$b('HEADER'), $b('Generated', false), $blank, $blank, $blank, ['v' => date('Y-m-d'), 't' => 'date']];
        foreach ($s['notes'] as $n) {
            $rows[] = [$b('NOTE', false), ['v' => $n, 't' => 'text'], $blank, $blank, $blank, $blank];
        }

        // Data gaps are excluded from every total, so their amounts are text, never in the Amount column.
        foreach ($s['gaps'] as $g) {
            $rows[] = [$b('DATA GAP'), $b($g['title'] . ' (' . $g['count'] . ') — ' . number_format((float) $g['amount'], 2) . ' excluded', false), $blank, $blank, $blank, $blank];
            foreach ($g['items'] as $it) {
                $rows[] = [$b('DATA GAP', false), ['v' => $it['ref'] . ($it['note'] !== '' ? ' — ' . $it['note'] : '') . ' — ' . number_format((float) $it['amount'], 2), 't' => 'text'], $blank, $blank, $blank, ['v' => $it['date'], 't' => 'date']];
            }
        }
        if ($s['gaps']) {
            $rows[] = [$b('DATA GAP', false), ['v' => 'These records are excluded from every total and may prevent complete reconciliation.', 't' => 'text'], $blank, $blank, $blank, $blank];
        }

        $in  = $s['total_receipts']['total'];
        $out = $s['total_payments']['total'];
        $net = $s['net_movement']['total'];
        $t   = $s['transfers'];

        $row('SUMMARY', 'Money In — Credit (money received)', $in, 'C');
        $row('SUMMARY', 'Money Out — Debit (money spent)', $out, 'D');
        $row('SUMMARY', 'Net Movement (Money In − Money Out)', $net, 'N', true);

        // One combined table: each category once, on its own side.
        foreach ([['C', $s['receipts']], ['D', $s['payments']]] as [$side, $lines]) {
            foreach ($lines as $l) {
                $row('TRANSACTIONS', $l['label'], $l['total'], $side);
                foreach ($l['detail'] as $label => $d) {
                    $row('TRANSACTIONS', '    ' . $label, ($d['bank'] ?? 0.0) + ($d['cash'] ?? 0.0), $side);
                }
            }
        }
        $rows[] = [$b('TRANSACTIONS'), $b('MONTHLY TOTAL'), $m($out, true), $m($in, true), $blank, $blank];

        $row('MONTHLY MOVEMENT', 'Opening Balance', $s['opening']['total'], 'N', true);
        if ($showBank && $showCash) {
            $row('MONTHLY MOVEMENT', '    Bank', $s['opening']['bank'], 'N');
            $row('MONTHLY MOVEMENT', '    Cash (derived)', $s['opening']['cash'], 'N');
        }
        $row('MONTHLY MOVEMENT', 'Money In — Credit', $in, 'C');
        $row('MONTHLY MOVEMENT', 'Money Out — Debit', $out, 'D');
        $row('MONTHLY MOVEMENT', 'Net Movement (Money In − Money Out)', $net, 'N', true);
        if (abs($t['total']) >= 0.005) {
            $row('MONTHLY MOVEMENT', 'Net Transfers (own accounts)', $t['total'], 'N');
        }
        $row('MONTHLY MOVEMENT', 'Closing Position', $s['closing']['total'], 'N', true);
        if ($showBank && $showCash) {
            $row('MONTHLY MOVEMENT', '    Bank', $s['closing']['bank'], 'N');
            $row('MONTHLY MOVEMENT', '    Cash (derived)', $s['closing']['cash'], 'N');
        }

        $tLines = [
            'Bank to bank — transfer in'  => $t['bank_in'],
            'Bank to bank — transfer out' => -$t['bank_out'],
            'Cash deposited to bank'      => $t['cash_to_bank'] * (($showBank ? 1 : 0) + ($showCash ? -1 : 0)),
            'Cash withdrawn from bank'    => $t['bank_to_cash'] * (($showCash ? 1 : 0) + ($showBank ? -1 : 0)),
        ];
        if (! array_filter($tLines, static fn ($v) => abs($v) >= 0.005) && abs($t['total']) >= 0.005) {
            // the engine gives only a net figure for this scope (e.g. Cash mode) — show it as one explicit line
            $tLines['Transfers to / from outside this view (net)'] = $t['total'];
        }
        foreach ($tLines as $label => $v) {
            if (abs($v) >= 0.005) {
                $row('TRANSFERS', $label, $v, 'N');
            }
        }
        $row('TRANSFERS', 'NET TRANSFERS' . (abs($t['total']) < 0.005 ? ' (eliminated — both sides in scope)' : ''), $t['total'], 'N', true);

        foreach ($s['bank_detail'] as $d) {
            $rows[] = [$b('BANK ACCOUNT DETAIL'), $b($d['name'] . ' (' . $d['number'] . ')'), $blank, $blank, $blank, $blank];
            foreach (['Opening Balance' => 'opening', 'Deposits' => 'deposits', 'Withdrawals' => 'withdrawals', 'Transfers In' => 'transfers_in', 'Transfers Out' => 'transfers_out', 'Closing Balance' => 'closing'] as $label => $f) {
                $row('BANK ACCOUNT DETAIL', $label, $d[$f], 'N', $f === 'closing');
            }
            $row('BANK ACCOUNT DETAIL', 'Current balance on account (today)', $d['current_balance'], 'N');
        }

        if ($showCash) {
            $c = $s['cash_detail'];
            foreach (['Opening Cash' => 'opening', 'Cash Receipts' => 'receipts', 'Cash Payments' => 'payments', 'Cash Transfers' => 'transfers', 'Closing Cash' => 'closing'] as $label => $f) {
                $row('CASH DETAIL', $label, $c[$f], 'N', $f === 'closing');
            }
        }

        (new ExcelReport())->table(
            'Monthly Statement',
            $this->_filters($s),
            ['Section', 'Particulars', 'Debit — Money Out', 'Credit — Money In', 'Balance / Net', 'Date'],
            $rows,
            ['text', 'text', 'currency', 'currency', 'currency', 'date'],
            [],
            'portrait'
        )->stream('monthly_statement_' . $s['month']);

        return null;
    }
}
