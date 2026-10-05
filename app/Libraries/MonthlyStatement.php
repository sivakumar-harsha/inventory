<?php

namespace App\Libraries;

use App\Models\BankTransactionModel;
use App\Models\CashOpeningBalanceModel;

/**
 * Release 4.9.0BT: Overall Monthly Statement engine (read-only).
 *
 * A cash/bank MOVEMENT statement: opening + receipts - payments +/- transfers =
 * closing. Not a P&L, not a balance sheet, no customer/supplier positions.
 *
 *   BANK  bank_transactions is the only source of bank movement (opening_balance
 *         + earlier rows, then the month's rows classified by reference_type). The
 *         business row behind a bank posting is never read as well.
 *   CASH  the Cash Book's own derivation (App\Libraries\CashMovements, shared; includes
 *         cash invoice payments and cash loan receipts), starting from 0.
 *   TRANSFERS  bank-to-bank legs and cash<->bank manual entries are shown on their
 *         own and add up to 0 whenever both sides are inside the selected scope.
 *
 * Records with no reliable cash/bank method are NEVER classified as cash or bank;
 * they are listed as data gaps instead (gaps()).
 */
class MonthlyStatement
{
    public const MODES = ['all' => 'All', 'cash' => 'Cash', 'bank' => 'Bank'];

    public const RECEIPT_LINES = [
        'inv_pay'   => 'Customer Invoice Payments',
        'cust_adv'  => 'Customer Advances',
        'service'   => 'Service Receipts',
        'direct'    => 'Direct Project Income',
        'loan_recv' => 'Loan Received',
        'other_rec' => 'Other Receipts',
    ];

    public const PAYMENT_LINES = [
        'sup_pay'   => 'Supplier Payments',
        'sup_adv'   => 'Supplier Advances',
        'loan_pay'  => 'Loan Payments',
        'expense'   => 'Expenses',
        'other_pay' => 'Other Payments',
    ];

    private $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    /** 'YYYY-MM' -> true only for a real calendar month. */
    public static function validMonth(string $month): bool
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return false;
        }

        return \DateTime::createFromFormat('!Y-m', $month) !== false;
    }

    /** Bank accounts for the filter, in id order. */
    public function accounts(): array
    {
        return $this->db->table('bank_accounts')->select('id, bank_name, account_name, account_number, opening_balance, current_balance')
            ->orderBy('id', 'ASC')->get()->getResultArray();
    }

    /**
     * @param string $month     validated YYYY-MM
     * @param int    $accountId 0 = all accounts
     * @param string $mode      all | cash | bank
     */
    public function build(string $month, int $accountId = 0, string $mode = 'all'): array
    {
        $first = new \DateTime($month . '-01');
        $from  = $first->format('Y-m-d');
        $to    = $first->format('Y-m-t');

        $accounts = $this->accounts();
        $scope    = [];
        foreach ($accounts as $a) {
            if ($accountId === 0 || (int) $a['id'] === $accountId) {
                $scope[(int) $a['id']] = $a;
            }
        }

        // Cash is not kept per bank account: it is in scope for Cash mode, and for All mode only when no single account is picked.
        $includeBank = $mode !== 'cash';
        $includeCash = $mode === 'cash' || ($mode === 'all' && $accountId === 0);

        $rec = $pay = [];
        foreach (self::RECEIPT_LINES as $k => $l) {
            $rec[$k] = ['key' => $k, 'label' => $l, 'bank' => 0.0, 'cash' => 0.0, 'detail' => []];
        }
        foreach (self::PAYMENT_LINES as $k => $l) {
            $pay[$k] = ['key' => $k, 'label' => $l, 'bank' => 0.0, 'cash' => 0.0, 'detail' => []];
        }
        $xf = ['bank_in' => 0.0, 'bank_out' => 0.0, 'cash_to_bank' => 0.0, 'bank_to_cash' => 0.0];

        $detail = [];
        foreach ($scope as $id => $a) {
            $detail[$id] = [
                'id' => $id, 'name' => $a['bank_name'] . ' — ' . $a['account_name'], 'number' => $a['account_number'],
                'opening' => (float) $a['opening_balance'], 'deposits' => 0.0, 'withdrawals' => 0.0,
                'transfers_in' => 0.0, 'transfers_out' => 0.0, 'closing' => 0.0, 'later' => 0.0,
                'current_balance' => (float) $a['current_balance'],
            ];
        }

        $openBank = 0.0;
        if ($includeBank && $scope) {
            // Earlier months and later months: one grouped query (opening, and what happened after the month).
            foreach ($this->periodSums($from, $to) as $r) {
                $id = (int) $r['acct'];
                if (! isset($detail[$id])) {
                    continue;
                }
                if ($r['p'] === 'before') {
                    $detail[$id]['opening'] += (float) $r['s'];
                } elseif ($r['p'] === 'after') {
                    $detail[$id]['later'] += (float) $r['s'];
                }
            }

            // The month's own rows: one query.
            foreach ($this->monthRows($from, $to) as $row) {
                foreach ($this->legs($row) as [$acct, $credit]) {
                    if (! isset($detail[$acct])) {
                        continue;
                    }
                    $amt = round((float) $row['amount'], 2);
                    $d   = &$detail[$acct];

                    if ($row['reference_type'] === BankTransactionModel::REF_BANK_TRANSFER || $row['transaction_type'] === 'TRANSFER') {
                        if ($credit) {
                            $d['transfers_in'] += $amt;
                            $xf['bank_in'] += $amt;
                        } else {
                            $d['transfers_out'] += $amt;
                            $xf['bank_out'] += $amt;
                        }
                        unset($d);
                        continue;
                    }

                    $credit ? $d['deposits'] += $amt : $d['withdrawals'] += $amt;
                    unset($d);

                    if ($this->isCashTransfer($row)) {
                        $credit ? $xf['cash_to_bank'] += $amt : $xf['bank_to_cash'] += $amt;
                        continue;
                    }

                    [$sect, $key, $parts] = $this->classifyBank($row, $credit, $amt);
                    foreach ($parts as $pk => $pa) {
                        if ($sect === 'rec') {
                            $this->addLine($rec, $pk, 'bank', $pa, $row);
                        } else {
                            $this->addLine($pay, $pk, 'bank', $pa, $row);
                        }
                    }
                }
            }

            foreach ($detail as &$d) {
                $d['closing'] = $d['opening'] + $d['deposits'] - $d['withdrawals'] + $d['transfers_in'] - $d['transfers_out'];
                foreach (['opening', 'deposits', 'withdrawals', 'transfers_in', 'transfers_out', 'closing', 'later'] as $f) {
                    $d[$f] = round($d[$f], 2);
                }
                $d['reconciles'] = abs(round($d['closing'] + $d['later'], 2) - round($d['current_balance'], 2)) < 0.005;
                $openBank += $d['opening'];
            }
            unset($d);
        } else {
            $detail = [];
        }

        // Cash: the Cash Book's own derivation, walked from 0 or from the stored opening cash (Release 4.9.0CC).
        $ob       = $includeCash ? CashOpeningBalanceModel::effective((new CashOpeningBalanceModel())->current(), $to) : null;
        $openCash = $ob ? $ob['amount'] : 0.0;
        $cashXferIn = $cashXferOut = 0.0;
        if ($includeCash) {
            foreach ((new CashMovements())->all() as $m) {
                if ($m['date'] > $to) {
                    continue;
                }
                if ($ob && $m['date'] < $ob['opening_date']) {
                    continue;
                }
                if ($m['date'] < $from) {
                    $openCash += $m['in'] - $m['out'];
                    continue;
                }
                if ($m['cat'] === 'cash_xfer') {
                    $cashXferIn += $m['in'];
                    $cashXferOut += $m['out'];
                    continue;
                }
                $isIn = $m['in'] > 0;
                $amt  = $isIn ? $m['in'] : $m['out'];
                if ($m['cat'] === 'cust_pay') {
                    // A customer voucher: its advance part is a Customer Advance, the rest settles invoices.
                    $this->addLine($rec, 'cust_adv', 'cash', $m['adv'], $m);
                    $this->addLine($rec, 'inv_pay', 'cash', $amt - $m['adv'], $m);
                    continue;
                }
                $key = $m['cat'] !== '' ? $m['cat'] : ($isIn ? 'other_rec' : 'other_pay');
                if ($isIn) {
                    $this->addLine($rec, $key, 'cash', $amt, $m);
                } else {
                    $this->addLine($pay, $key, 'cash', $amt, $m);
                }
            }
            $openCash = round($openCash, 2);
        }

        $rec = $this->finish($rec);
        $pay = $this->finish($pay);

        $sum = static function (array $lines, string $f): float {
            return round(array_sum(array_column($lines, $f)), 2);
        };
        $totRec = ['bank' => $sum($rec, 'bank'), 'cash' => $sum($rec, 'cash'), 'total' => $sum($rec, 'total')];
        $totPay = ['bank' => $sum($pay, 'bank'), 'cash' => $sum($pay, 'cash'), 'total' => $sum($pay, 'total')];

        // Transfers: signed effect inside the selected scope. Both sides in scope => they cancel.
        $xfBank = $includeBank ? round($xf['bank_in'] - $xf['bank_out'] + $xf['cash_to_bank'] - $xf['bank_to_cash'], 2) : 0.0;
        $xfCash = $includeCash ? round($cashXferIn - $cashXferOut, 2) : 0.0;

        $netMove = [
            'bank'  => round($totRec['bank'] - $totPay['bank'], 2),
            'cash'  => round($totRec['cash'] - $totPay['cash'], 2),
        ];
        $netMove['total'] = round($netMove['bank'] + $netMove['cash'], 2);

        $opening = ['bank' => round($openBank, 2), 'cash' => $openCash, 'total' => round($openBank + $openCash, 2)];
        $closing = [
            'bank' => round($opening['bank'] + $netMove['bank'] + $xfBank, 2),
            'cash' => round($opening['cash'] + $netMove['cash'] + $xfCash, 2),
        ];
        $closing['total'] = round($closing['bank'] + $closing['cash'], 2);

        $bankClosingByAccount = round(array_sum(array_column($detail, 'closing')), 2);
        $cashClosingByWalk    = $includeCash ? $this->cashClosing($to, $ob) : 0.0;

        return [
            'month'       => $month,
            'label'       => $first->format('F Y'),
            'from'        => $from,
            'to'          => $to,
            'account_id'  => $accountId,
            'account'     => $accountId ? ($scope[$accountId] ?? null) : null,
            'mode'        => $mode,
            'include_bank' => $includeBank,
            'include_cash' => $includeCash,
            'accounts'    => $accounts,
            'opening'     => $opening,
            'receipts'    => array_values($rec),
            'total_receipts' => $totRec,
            'payments'    => array_values($pay),
            'total_payments' => $totPay,
            'transfers'   => [
                'bank_in' => round($xf['bank_in'], 2), 'bank_out' => round($xf['bank_out'], 2),
                'cash_to_bank' => round($xf['cash_to_bank'], 2), 'bank_to_cash' => round($xf['bank_to_cash'], 2),
                'bank' => $xfBank, 'cash' => $xfCash, 'total' => round($xfBank + $xfCash, 2),
            ],
            'net_movement' => $netMove,
            'closing'     => $closing,
            'bank_detail' => array_values($detail),
            'cash_opening_set' => $ob !== null,
            'cash_detail' => [
                'opening' => $openCash, 'receipts' => $totRec['cash'], 'payments' => $totPay['cash'],
                'transfers' => $xfCash, 'closing' => $closing['cash'],
            ],
            'checks'      => [
                'bank_ok' => ! $includeBank || abs($bankClosingByAccount - $closing['bank']) < 0.005,
                'cash_ok' => ! $includeCash || abs($cashClosingByWalk - $closing['cash']) < 0.005,
                'accounts_ok' => ! array_filter($detail, static fn ($d) => ! $d['reconciles']),
            ],
            'gaps'        => $this->gaps($to, $from, $detail),
            'notes'       => $this->notes($accountId, $mode, $includeCash, $ob),
        ];
    }

    private function finish(array $lines): array
    {
        foreach ($lines as &$l) {
            $l['bank']  = round($l['bank'], 2);
            $l['cash']  = round($l['cash'], 2);
            $l['total'] = round($l['bank'] + $l['cash'], 2);
            ksort($l['detail']);
        }
        unset($l);

        return $lines;
    }

    /** Cash closing from an independent plain walk (integrity check only). */
    private function cashClosing(string $to, ?array $ob = null): float
    {
        $bal = $ob ? $ob['amount'] : 0.0;
        foreach ((new CashMovements())->all() as $m) {
            if ($m['date'] <= $to && ! ($ob && $m['date'] < $ob['opening_date'])) {
                $bal += $m['in'] - $m['out'];
            }
        }

        return round($bal, 2);
    }

    private function notes(int $accountId, string $mode, bool $includeCash, ?array $ob = null): array
    {
        $n = [];
        if ($accountId > 0 && $mode === 'all') {
            $n[] = 'Single bank account selected: cash is not kept per bank account, so cash is excluded. Transfers and cash deposits/withdrawals are shown as movements of this account.';
        } elseif ($accountId > 0 && $mode === 'cash') {
            $n[] = 'Bank account filter does not apply to cash; showing cash only.';
        } elseif ($mode === 'bank') {
            $n[] = 'Bank only: cash is excluded, so cash deposits/withdrawals appear as transfers affecting the bank balance.';
        } elseif ($mode === 'cash') {
            $n[] = 'Cash only: bank is excluded, so cash deposits/withdrawals appear as transfers affecting the cash balance.';
        }
        if ($includeCash) {
            $n[] = $ob
                ? 'Cash is derived from the source records (Cash Book rules) and starts from the stored opening cash of ' . number_format($ob['amount'], 2) . ' on ' . date('d-m-Y', strtotime($ob['opening_date'])) . '; earlier cash movements are not counted.'
                : 'Cash is derived from the source records (Cash Book rules) and starts from zero; no opening cash is stored.';
        }

        return $n;
    }

    // -------------------------------------------------------------------- bank

    /** Grouped signed movement per account for the periods before and after the month. */
    private function periodSums(string $from, string $to): array
    {
        $p = "CASE WHEN transaction_date < ? THEN 'before' ELSE 'after' END";
        $sql = "SELECT acct, p, SUM(delta) AS s FROM (
                    SELECT bank_account_id AS acct, $p AS p,
                           CASE WHEN transaction_type IN ('DEPOSIT', 'TRANSFER_IN') THEN amount ELSE -amount END AS delta
                    FROM bank_transactions WHERE transaction_date < ? OR transaction_date > ?
                    UNION ALL
                    SELECT transfer_bank_account_id AS acct, $p AS p, amount AS delta
                    FROM bank_transactions
                    WHERE transaction_type = 'TRANSFER' AND transfer_bank_account_id IS NOT NULL AND transfer_bank_account_id <> bank_account_id
                      AND (transaction_date < ? OR transaction_date > ?)
                ) x GROUP BY acct, p";

        return $this->db->query($sql, [$from, $from, $to, $from, $from, $to])->getResultArray();
    }

    /** Every bank row of the month, with the little context classification needs (one query, no per-row lookups). */
    private function monthRows(string $from, string $to): array
    {
        return $this->db->query(
            "SELECT bt.id, bt.bank_account_id, bt.transaction_date, bt.transaction_type, bt.amount, bt.reference_type, bt.reference_id,
                    bt.payment_mode, bt.category, bt.transfer_bank_account_id,
                    pcr.receipt_type AS pcr_type, cp.advance_amount AS cp_advance, COALESCE(ec.category_name, e.category) AS exp_category
             FROM bank_transactions bt
             LEFT JOIN project_cash_receipts pcr ON bt.reference_type = 'PROJECT_ADVANCE' AND pcr.id = bt.reference_id
             LEFT JOIN customer_payments cp ON bt.reference_type IN ('CUSTOMER_PAYMENT', 'SERVICE_RECEIPT_PAYMENT') AND cp.id = bt.reference_id
             LEFT JOIN expenses e ON bt.reference_type = 'EXPENSE' AND e.id = bt.reference_id
             LEFT JOIN expense_categories ec ON ec.id = e.category_id
             WHERE bt.transaction_date >= ? AND bt.transaction_date <= ?
             ORDER BY bt.transaction_date, bt.id",
            [$from, $to]
        )->getResultArray();
    }

    /** [account, isCredit] legs a row produces (a legacy single-row TRANSFER debits one account and credits the other). */
    private function legs(array $row): array
    {
        if ($row['transaction_type'] === 'TRANSFER') {
            $legs = [[(int) $row['bank_account_id'], false]];
            if ($row['transfer_bank_account_id'] && (int) $row['transfer_bank_account_id'] !== (int) $row['bank_account_id']) {
                $legs[] = [(int) $row['transfer_bank_account_id'], true];
            }

            return $legs;
        }

        return [[(int) $row['bank_account_id'], BankTransactionModel::isCredit($row['transaction_type'])]];
    }

    private function isCashTransfer(array $row): bool
    {
        $ref = $row['reference_type'];
        if (in_array($ref, ['MANUAL_DEPOSIT', 'MANUAL_WITHDRAWAL'], true)) {
            return strtoupper((string) $row['payment_mode']) === 'CASH';
        }

        return $ref === 'BANK_DAYBOOK' && in_array(strtoupper((string) $row['category']), ['CASH DEPOSIT', 'CASH WITHDRAWAL'], true);
    }

    /** @return array{0:string,1:string,2:array<string,float>} section (rec|pay), main line, amount parts per line */
    private function classifyBank(array $row, bool $credit, float $amt): array
    {
        switch ($row['reference_type']) {
            case 'SALE_PAYMENT':
                return ['rec', 'inv_pay', ['inv_pay' => $amt]];
            case 'CUSTOMER_PAYMENT':
            case 'SERVICE_RECEIPT_PAYMENT':
                $adv = min((float) $row['cp_advance'], $amt);

                return ['rec', 'inv_pay', ['cust_adv' => round($adv, 2), 'inv_pay' => round($amt - $adv, 2)]];
            case 'PROJECT_ADVANCE_DEPOSIT':
                return ['rec', 'cust_adv', ['cust_adv' => $amt]];
            case 'PROJECT_ADVANCE':
                // Never trust the reference type name: the receipt's own type decides.
                $k = $row['pcr_type'] ? CashMovements::receiptCategory((string) $row['pcr_type']) : 'other_rec';

                return ['rec', $k, [$k => $amt]];
            case 'SERVICE_RECEIPT':
                return ['rec', 'service', ['service' => $amt]];
            case 'LOAN_DISBURSEMENT':
                return ['rec', 'loan_recv', ['loan_recv' => $amt]];
            case 'SUPPLIER_PAYMENT':
                return ['pay', 'sup_pay', ['sup_pay' => $amt]];
            case 'SUPPLIER_ADVANCE':
                return ['pay', 'sup_adv', ['sup_adv' => $amt]];
            case 'LOAN_PAYMENT':
                return ['pay', 'loan_pay', ['loan_pay' => $amt]];
            case 'EXPENSE':
                return ['pay', 'expense', ['expense' => $amt]];
        }

        return $credit ? ['rec', 'other_rec', ['other_rec' => $amt]] : ['pay', 'other_pay', ['other_pay' => $amt]];
    }

    /** Adds an amount to a statement line (by reference into the array the caller owns) with its small detail breakdown. */
    private function addLine(array &$lines, string $key, string $col, float $amt, array $src): void
    {
        $amt = round($amt, 2);
        if ($amt == 0.0) {
            return;
        }
        // Release 4.9.0ED: an Unallocated Project Receipt is a plain cash/bank receipt already in the
        // Cash Book; the statement has no separate row for it. Its money stays in the totals (so cash
        // still reconciles to the Cash Book) under the existing Customer Advances line, never a new row.
        if ($key === 'proj_cash') {
            $key = 'cust_adv';
        }
        if (! isset($lines[$key])) {
            $key = isset($lines['other_rec']) ? 'other_rec' : 'other_pay';
        }
        $lines[$key][$col] += $amt;

        $label = null;
        if ($key === 'expense') {
            $label = (string) ($src['detail'] ?? $src['exp_category'] ?? '') ?: 'General';
        } elseif ($key === 'other_rec' || $key === 'other_pay') {
            $label = trim((string) ($src['category'] ?? '')) ?: ((string) ($src['reference_type'] ?? '') ?: ($src['ttype'] ?? 'Other'));
        }
        if ($label !== null) {
            $lines[$key]['detail'][$label][$col] = ($lines[$key]['detail'][$label][$col] ?? 0.0) + $amt;
        }
    }

    // --------------------------------------------------------------- data gaps

    /**
     * Records that have no reliable cash/bank posting. They are excluded from every total above.
     * Everything dated after the month is ignored; earlier ones still matter (they affect opening).
     */
    private function gaps(string $to, string $from, array $detail): array
    {
        $out = [];
        $push = function (string $key, string $title, string $why, array $rows) use (&$out, $from): void {
            if (! $rows) {
                return;
            }
            $items = [];
            $amount = 0.0;
            $inMonth = 0;
            foreach ($rows as $r) {
                $d = substr((string) $r['d'], 0, 10);
                $items[] = ['ref' => (string) $r['ref'], 'date' => $d, 'amount' => round((float) $r['amt'], 2), 'note' => (string) ($r['note'] ?? '')];
                $amount += (float) $r['amt'];
                $inMonth += $d >= $from ? 1 : 0;
            }
            $out[] = ['key' => $key, 'title' => $title, 'why' => $why, 'count' => count($items), 'in_month' => $inMonth, 'amount' => round($amount, 2), 'items' => $items];
        };

        $push('payment_unposted', 'Invoice payment with no bank posting',
            'Bank-type payment (Bank Transfer / Cheque / Other) with no bank account or no bank row. Counted in neither bank nor cash.',
            $this->db->query(
                "SELECT CONCAT(s.invoice_no, ' / payment #', p.id) AS ref, p.payment_date AS d, p.amount AS amt, p.method AS note
                 FROM payments p INNER JOIN sales s ON s.id = p.sale_id
                 WHERE p.method <> 'CASH' AND p.payment_date <= ?
                   AND NOT EXISTS (SELECT 1 FROM bank_transactions bt WHERE bt.reference_type = 'SALE_PAYMENT' AND bt.reference_id = p.id)
                 ORDER BY p.payment_date, p.id",
                [$to]
            )->getResultArray());

        $push('gp_advance_unposted', 'Supplier advance with no bank posting',
            'General Purchase advance paid by a non-cash method but without a bank account / bank row (or with no method). Counted in neither bank nor cash.',
            $this->db->query(
                "SELECT gp.purchase_no AS ref, gp.purchase_date AS d, gp.advance_paid AS amt, COALESCE(NULLIF(TRIM(gp.payment_method), ''), '(blank)') AS note
                 FROM general_purchases gp
                 WHERE gp.advance_paid > 0 AND gp.purchase_date <= ? AND LOWER(TRIM(COALESCE(gp.payment_method, ''))) <> 'cash'
                   AND NOT EXISTS (SELECT 1 FROM bank_transactions bt WHERE bt.reference_type = 'SUPPLIER_ADVANCE' AND bt.reference_id = gp.id)
                 ORDER BY gp.purchase_date, gp.id",
                [$to]
            )->getResultArray());

        $push('supplier_voucher_unposted', 'Supplier voucher with blank method or no bank posting',
            'Voucher with an amount but a blank / unknown payment method, or a bank method with no bank row. GPA mirror vouchers and Advance Adjustment vouchers are not listed. Counted in neither bank nor cash.',
            $this->db->query(
                "SELECT sp.payment_no AS ref, sp.payment_date AS d, sp.total_amount AS amt, COALESCE(NULLIF(TRIM(sp.payment_method), ''), '(blank)') AS note
                 FROM supplier_payments sp
                 WHERE sp.total_amount > 0 AND sp.payment_date <= ? AND sp.payment_no NOT LIKE 'GPA-%'
                   AND LOWER(TRIM(COALESCE(sp.payment_method, ''))) NOT IN ('cash', ?)
                   AND NOT EXISTS (SELECT 1 FROM bank_transactions bt WHERE bt.reference_type = 'SUPPLIER_PAYMENT' AND bt.reference_id = sp.id)
                 ORDER BY sp.payment_date, sp.id",
                [$to, strtolower(\App\Models\SupplierPaymentModel::ADVANCE_ADJUSTMENT_METHOD)]
            )->getResultArray());

        $push('project_advance_unposted', 'Project advance with no payment method',
            'Advance recorded on the project without a payment method (or a bank method with no bank row). Counted in neither bank nor cash.',
            $this->db->query(
                "SELECT CONCAT('PRJ-', LPAD(p.id, 6, '0'), ' ', p.name) AS ref, COALESCE(p.advance_date, DATE(p.created_at)) AS d, p.advance_amount AS amt,
                        COALESCE(NULLIF(TRIM(p.advance_payment_method), ''), '(blank)') AS note
                 FROM projects p
                 WHERE p.advance_amount > 0 AND COALESCE(p.advance_date, DATE(p.created_at)) <= ?
                   AND UPPER(TRIM(COALESCE(p.advance_payment_method, ''))) <> 'CASH'
                   AND NOT EXISTS (SELECT 1 FROM bank_transactions bt WHERE bt.reference_type = 'PROJECT_ADVANCE_DEPOSIT' AND bt.reference_id = p.id)
                 ORDER BY d, p.id",
                [$to]
            )->getResultArray());

        $neg = [];
        foreach ($detail as $d) {
            if ($d['closing'] < 0) {
                $neg[] = ['ref' => $d['name'], 'd' => $to, 'amt' => $d['closing'], 'note' => 'closing balance'];
            } elseif ($d['opening'] < 0) {
                $neg[] = ['ref' => $d['name'], 'd' => $from, 'amt' => $d['opening'], 'note' => 'opening balance'];
            }
        }
        $push('negative_bank', 'Negative bank balance',
            'The account is overdrawn at the end (or start) of the month. Shown as recorded; nothing was adjusted.', $neg);

        return $out;
    }
}
