<?php

namespace App\Libraries;

/**
 * Release 4.9.0BT: the derived cash-movement rules of the Cash Book (4.9.0E), moved
 * out of the CashBook controller so the Monthly Statement reads cash through the
 * very same rules instead of a second, incompatible copy.
 *
 * The app has no cash account and posts nothing for cash, so every cash movement is
 * read from the record that owns it where the payment method is Cash, plus manual
 * bank deposits / withdrawals made in Cash (cash taken to / brought from a bank).
 * Strictly read-only. Cash has no stored opening: it starts at 0.
 *
 * all() is the ONE cash derivation, used by both the Cash Book and the Monthly
 * Statement. Every row also carries a `cat` key (statement category) and `adv` (the
 * advance part of a customer voucher) that the Cash Book ignores.
 *
 * Release 4.9.0BU: invoice payments (payments.method = CASH) and loan receipts
 * (loan_receipts paid in CASH) are read for everyone; the Cash Book used to omit them.
 */
class CashMovements
{
    /**
     * date, voucher, ttype, particulars, in, out, seq, search, cat, adv, detail.
     * Unsorted: the caller orders by [date, seq].
     */
    public function all(): array
    {
        $db  = \Config\Database::connect();
        $out = [];
        $seq = 0;

        $add = static function (string $date, string $voucher, string $ttype, string $particulars, float $in, float $outAmt, array $search, string $cat = '', float $adv = 0.0, string $detail = '', string $method = '') use (&$out, &$seq): void {
            $in    = round($in, 2);
            $outAmt = round($outAmt, 2);
            if ($date === '' || ($in <= 0 && $outAmt <= 0)) {
                return;
            }
            $out[] = [
                'date' => substr($date, 0, 10), 'voucher' => $voucher, 'ttype' => $ttype, 'particulars' => $particulars,
                'in' => $in, 'out' => $outAmt, 'seq' => ++$seq,
                'search' => mb_strtolower(implode(' ', array_merge([$voucher, $ttype, $particulars], $search))),
                'cat' => $cat, 'adv' => round($adv, 2), 'detail' => $detail,
                // Release 4.9.0CB: the source record's stored payment method (display only; no rule or amount reads it).
                'method' => $method,
            ];
        };
        $has = static fn (string $t): bool => $db->tableExists($t);

        // Customer payment vouchers taken in cash.
        if ($has('customer_payments') && $has('customers')) {
            $docs = $has('customer_payment_allocations') && $has('service_receipts')
                ? '(SELECT GROUP_CONCAT(sr.receipt_no ORDER BY sr.id SEPARATOR ", ") FROM customer_payment_allocations a INNER JOIN service_receipts sr ON sr.id = a.service_receipt_id WHERE a.customer_payment_id = cp.id)'
                : "''";
            foreach ($db->query(
                "SELECT cp.payment_no, cp.payment_date, cp.payment_method, cp.total_amount, cp.advance_amount, cp.remarks, cp.reference_no, c.name AS party, $docs AS docs
                 FROM customer_payments cp INNER JOIN customers c ON c.id = cp.customer_id
                 WHERE cp.payment_method = 'CASH'"
            )->getResultArray() as $r) {
                $add($r['payment_date'], (string) $r['payment_no'], 'Customer Receipt',
                    'Customer: ' . $r['party'] . ($r['docs'] ? ' — Invoice ' . $r['docs'] : ''),
                    (float) $r['total_amount'], 0.0, [(string) $r['party'], (string) $r['docs'], (string) $r['remarks'], (string) $r['reference_no']],
                    'cust_pay', min((float) $r['advance_amount'], (float) $r['total_amount']), '', (string) $r['payment_method']);
            }
        }

        // Service receipts taken in cash: only the counter share (the rest arrived through payment vouchers).
        if ($has('service_receipts')) {
            $alloc = $has('customer_payment_allocations')
                ? 'COALESCE((SELECT SUM(a.paid_amount) FROM customer_payment_allocations a WHERE a.service_receipt_id = sr.id), 0)'
                : '0';
            foreach ($db->query(
                "SELECT sr.receipt_no, sr.receipt_date, sr.receipt_type, sr.payment_mode, sr.received_amount - $alloc AS own, sr.remarks, COALESCE(c.name, sr.customer_name) AS party
                 FROM service_receipts sr LEFT JOIN customers c ON c.id = sr.customer_id
                 WHERE sr.payment_mode = 'CASH'"
            )->getResultArray() as $r) {
                $add($r['receipt_date'], (string) $r['receipt_no'], 'Customer Receipt',
                    'Customer: ' . $r['party'] . ' — ' . ($r['receipt_type'] === 'INVOICE' ? 'Invoice' : 'Receipt') . ' ' . $r['receipt_no'],
                    max(0.0, (float) $r['own']), 0.0, [(string) $r['party'], (string) $r['remarks']], 'service', 0.0, '', (string) $r['payment_mode']);
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
                "SELECT sp.payment_no, sp.payment_date, sp.payment_method, sp.total_amount, sp.remarks, sp.reference_no, s.name AS party, $docs AS docs
                 FROM supplier_payments sp INNER JOIN suppliers s ON s.id = sp.supplier_id
                 WHERE LOWER(TRIM(sp.payment_method)) = 'cash' AND sp.payment_no NOT LIKE 'GPA-%'"
            )->getResultArray() as $r) {
                $add($r['payment_date'], (string) $r['payment_no'], 'Supplier Payment',
                    'Supplier: ' . $r['party'] . ($r['docs'] ? ' — ' . $r['docs'] : ''),
                    0.0, (float) $r['total_amount'], [(string) $r['party'], (string) $r['docs'], (string) $r['remarks'], (string) $r['reference_no']], 'sup_pay', 0.0, '', (string) $r['payment_method']);
            }
        }

        // General Purchase advances paid in cash.
        if ($has('general_purchases') && $has('suppliers')) {
            foreach ($db->query(
                "SELECT gp.purchase_no, gp.purchase_date, gp.payment_method, gp.advance_paid, gp.bill_no, gp.remarks, s.name AS party
                 FROM general_purchases gp INNER JOIN suppliers s ON s.id = gp.supplier_id
                 WHERE gp.advance_paid > 0 AND LOWER(TRIM(gp.payment_method)) = 'cash'"
            )->getResultArray() as $r) {
                $add($r['purchase_date'], (string) $r['purchase_no'], 'Supplier Payment',
                    'Supplier: ' . $r['party'] . ' — Advance on ' . $r['purchase_no'],
                    0.0, (float) $r['advance_paid'], [(string) $r['party'], (string) $r['bill_no'], (string) $r['remarks']], 'sup_adv', 0.0, '', (string) $r['payment_method']);
            }
        }

        // Expenses paid in cash (cancelled ones are already reversed).
        if ($has('expenses')) {
            foreach ($db->query(
                "SELECT e.expense_no, e.expense_date, e.payment_method, e.amount, e.paid_to, e.remarks, c.category_name, p.name AS project
                 FROM expenses e
                 LEFT JOIN expense_categories c ON c.id = e.category_id
                 LEFT JOIN projects p ON p.id = e.project_id
                 WHERE e.payment_method = 'CASH' AND e.status = 'PAID'"
            )->getResultArray() as $r) {
                $add($r['expense_date'], (string) $r['expense_no'], 'Expense Payment',
                    'Expense: ' . ($r['category_name'] ?: 'General') . ($r['paid_to'] ? ' — ' . $r['paid_to'] : ''),
                    0.0, (float) $r['amount'], [(string) $r['category_name'], (string) $r['paid_to'], (string) $r['project'], (string) $r['remarks']],
                    'expense', 0.0, (string) ($r['category_name'] ?: 'General'), (string) $r['payment_method']);
            }
        }

        // Project cash receipts and project advances received in cash.
        if ($has('project_cash_receipts') && $has('projects')) {
            foreach ($db->query(
                "SELECT r.receipt_no, r.receipt_date, r.payment_method, r.amount, r.receipt_type, r.reference, r.notes, p.name AS project, c.name AS party
                 FROM project_cash_receipts r
                 INNER JOIN projects p ON p.id = r.project_id
                 LEFT JOIN customers c ON c.id = COALESCE(r.customer_id, p.customer_id)
                 WHERE r.payment_method = 'CASH'"
            )->getResultArray() as $r) {
                $add($r['receipt_date'], (string) $r['receipt_no'], 'Project Transaction',
                    ($r['receipt_type'] === 'CUSTOMER_PROJECT_CASH' ? 'Unallocated Project Receipt — ' : '') . 'Project: ' . $r['project'] . ($r['party'] ? ' — ' . $r['party'] : ''),
                    (float) $r['amount'], 0.0, [(string) $r['project'], (string) $r['party'], (string) $r['reference'], (string) $r['notes']],
                    self::receiptCategory((string) $r['receipt_type']), 0.0, '', (string) $r['payment_method']);
            }
        }
        if ($has('projects') && $db->fieldExists('advance_payment_method', 'projects')) {
            foreach ($db->query(
                "SELECT p.id, COALESCE(p.advance_date, DATE(p.created_at)) AS d, p.advance_payment_method, p.advance_amount, p.advance_notes, p.name AS project, c.name AS party
                 FROM projects p LEFT JOIN customers c ON c.id = p.customer_id
                 WHERE p.advance_amount > 0 AND UPPER(p.advance_payment_method) = 'CASH'"
            )->getResultArray() as $r) {
                $add((string) $r['d'], 'PRJ-' . str_pad((string) $r['id'], 6, '0', STR_PAD_LEFT), 'Project Transaction',
                    'Project: ' . $r['project'] . ($r['party'] ? ' — ' . $r['party'] : ' — Advance'),
                    (float) $r['advance_amount'], 0.0, [(string) $r['project'], (string) $r['party'], 'advance', (string) $r['advance_notes']], 'cust_adv', 0.0, '', (string) $r['advance_payment_method']);
            }
        }

        // Loan instalments paid in cash.
        if ($has('loan_payments') && $has('loans')) {
            foreach ($db->query(
                "SELECT lp.payment_date, lp.payment_method, lp.total_paid, lp.reference_no, lp.remarks, l.loan_no, l.lender_name
                 FROM loan_payments lp INNER JOIN loans l ON l.id = lp.loan_id
                 WHERE lp.payment_method = 'CASH'"
            )->getResultArray() as $r) {
                $add($r['payment_date'], (string) ($r['reference_no'] ?: $r['loan_no']), 'Manual Entry',
                    'Loan: ' . $r['lender_name'] . ' — Instalment',
                    0.0, (float) $r['total_paid'], [(string) $r['loan_no'], (string) $r['lender_name'], (string) $r['remarks']], 'loan_pay', 0.0, '', (string) $r['payment_method']);
            }
        }

        // Cash taken to a bank (deposit made in cash) or brought from one (withdrawal in cash).
        if ($has('bank_transactions') && $has('bank_accounts') && $db->fieldExists('payment_mode', 'bank_transactions')) {
            foreach ($db->query(
                'SELECT bt.transaction_date, bt.transaction_type, bt.payment_mode, bt.amount, bt.reference_no, bt.remarks, bt.party_name, bt.bank_account_id, ba.bank_name, ba.account_name
                 FROM bank_transactions bt INNER JOIN bank_accounts ba ON ba.id = bt.bank_account_id
                 WHERE ' . self::cashTransferSql('bt')
            )->getResultArray() as $r) {
                $toBank = $r['transaction_type'] === 'DEPOSIT';
                $add($r['transaction_date'], (string) $r['reference_no'], 'Cash Transfer',
                    'Cash Transfer: ' . ($toBank ? 'Cash → ' . $r['bank_name'] : $r['bank_name'] . ' → Cash'),
                    $toBank ? 0.0 : (float) $r['amount'], $toBank ? (float) $r['amount'] : 0.0,
                    [(string) $r['bank_name'], (string) $r['account_name'], (string) $r['party_name'], (string) $r['remarks']], 'cash_xfer',
                    // cashTransferSql() already selected this row as a Cash movement (a Day-book "CASH DEPOSIT/WITHDRAWAL" may carry no payment_mode).
                    0.0, '', 'CASH');
            }
        }

        // Invoice payments (Sales) taken in cash (no bank row exists for these by design).
        if ($has('payments') && $has('sales')) {
            foreach ($db->query(
                "SELECT p.id, p.payment_date, p.method, p.amount, p.reference, p.notes, s.invoice_no
                 FROM payments p INNER JOIN sales s ON s.id = p.sale_id
                 WHERE p.method = 'CASH'"
            )->getResultArray() as $r) {
                $add($r['payment_date'], (string) ($r['reference'] ?: $r['invoice_no']), 'Customer Receipt',
                    'Invoice payment: ' . $r['invoice_no'],
                    (float) $r['amount'], 0.0, [(string) $r['invoice_no'], (string) $r['notes']], 'inv_pay', 0.0, '', (string) $r['method']);
            }
        }

        // Loan received in cash (financing receipt, not income).
        if ($has('loan_receipts') && $has('loans')) {
            foreach ($db->query(
                "SELECT lr.receipt_no, lr.receipt_date, lr.payment_method, lr.amount, lr.remarks, l.loan_no, l.lender_name
                 FROM loan_receipts lr INNER JOIN loans l ON l.id = lr.loan_id
                 WHERE lr.payment_method = 'CASH'"
            )->getResultArray() as $r) {
                $add($r['receipt_date'], (string) $r['receipt_no'], 'Manual Entry',
                    'Loan received: ' . $r['lender_name'],
                    (float) $r['amount'], 0.0, [(string) $r['loan_no'], (string) $r['lender_name'], (string) $r['remarks']], 'loan_recv', 0.0, '', (string) $r['payment_method']);
            }
        }

        return $out;
    }

    /** WHERE fragment (on a bank_transactions alias) that selects a cash<->bank manual movement. Same rule for the cash and the bank side. */
    public static function cashTransferSql(string $alias = 'bt'): string
    {
        return "(($alias.reference_type IN ('MANUAL_DEPOSIT', 'MANUAL_WITHDRAWAL') AND UPPER($alias.payment_mode) = 'CASH')
            OR ($alias.reference_type = 'BANK_DAYBOOK' AND UPPER($alias.category) IN ('CASH DEPOSIT', 'CASH WITHDRAWAL')))";
    }

    /** project_cash_receipts.receipt_type -> statement category. ADVANCE is the original customer advance receipt. */
    public static function receiptCategory(string $receiptType): string
    {
        switch ($receiptType) {
            case 'CUSTOMER_PROJECT_CASH':
                return 'proj_cash';
            case 'DIRECT_INCOME':
                return 'direct';
            default:
                return 'cust_adv';
        }
    }
}
