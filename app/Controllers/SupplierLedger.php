<?php

namespace App\Controllers;

use App\Libraries\ExcelReport;
use App\Libraries\PdfReport;
use App\Models\SupplierPaymentAllocationModel;
use App\Models\SupplierPaymentModel;
use CodeIgniter\Controller;

/**
 * Release 4.9.0A: the Supplier Ledger is one daily-transaction page — a
 * supplier picker, a summary strip, a running-balance ledger, the outstanding
 * bills and the standalone-advance history — all derived read-only from data
 * the Purchases / General Purchase / Supplier Payment tables already hold.
 * No new columns, no writes anywhere in this controller, no posting rule
 * touched.
 *
 * Every purchase bill is a debit; every cash payment against a bill, every
 * voucher advance and every General Purchase's own advance_paid-at-creation is
 * a credit. Advance already applied to a bill is a zero-value Adjustment memo
 * (the advance was credited when it was paid). Hence, by construction:
 *
 *     closing balance = Outstanding Amount - Advance Available = Net Payable
 *
 * Release 4.8.6F-1 (unchanged): a GPA-xxxxxx voucher mirrors a General
 * Purchase advance. The advance already has its credit row derived from the
 * purchase, so the voucher is used here only to label that row and is never
 * credited a second time, nor listed in the standalone advance history.
 */
class SupplierLedger extends Controller
{
    private const TYPES = ['Purchase', 'Payment', 'Advance', 'Adjustment'];

    public function index()
    {
        $db        = \Config\Database::connect();
        $suppliers = $db->table('suppliers')->select('id, name')->orderBy('name', 'ASC')->get()->getResultArray();
        $filters   = $this->_filters();

        $data = [
            'suppliers' => $suppliers,
            'filters'   => $filters,
            'types'     => self::TYPES,
            'supplier'  => null,
        ];

        if ($filters['supplier_id'] > 0) {
            $supplier = $db->table('suppliers')->where('id', $filters['supplier_id'])->get()->getRowArray();
            if (! $supplier) {
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Supplier not found.');
            }
            $data['supplier'] = $supplier;
            $data += $this->_statement($supplier, $filters);
        }

        return view('supplier_ledger/index', $data);
    }

    /** Legacy per-supplier URL: the single ledger page now serves it. */
    public function view($id)
    {
        return redirect()->to(base_url('supplier-ledger?supplier_id=' . (int) $id));
    }

    /** GET supplier-ledger/export/pdf/{id}[?from&to&type&q] — the filtered ledger only. */
    public function exportPdf($id)
    {
        [$supplier, $filters, $st] = $this->_forExport((int) $id);

        (new PdfReport())->render('pdf/supplier_statement', [
            'supplier' => $supplier,
            'filters'  => $filters,
            'summary'  => $st['summary'],
            'opening'  => $st['opening'],
            'rows'     => $st['rows'],
            'closing'  => $st['closing'],
            'has_any'  => $st['has_any'],
        ], [
            'title'       => 'Supplier Ledger - ' . $supplier['name'],
            'orientation' => 'landscape',
        ]);
    }

    /** GET supplier-ledger/export/excel/{id}[?from&to&type&q] — the filtered ledger only. */
    public function exportExcel($id)
    {
        [$supplier, $filters, $st] = $this->_forExport((int) $id);

        $rows = [];
        foreach ($st['rows'] as $t) {
            $rows[] = [$t['date'], $t['voucher'], $t['ttype'], $t['particulars'], pm_label($t['method'], in_array($t['ttype'], ['Payment', 'Advance'], true) ? 'Not recorded' : '—'), $t['debit'], $t['credit'], $t['balance']];
        }

        $applied = array_filter([
            'From'  => $filters['from'],
            'To'    => $filters['to'],
            'Type'  => $filters['type'],
            'Search' => $filters['q'],
        ], static fn ($v) => $v !== '');

        (new ExcelReport())->ledger(
            'Supplier Ledger - ' . $supplier['name'],
            $applied,
            [
                'Supplier'           => $supplier['name'],
                'GST'                => $supplier['gst'] ?? '',
                'Outstanding Amount' => number_format($st['summary']['outstanding'], 2),
                'Advance Available'  => number_format($st['summary']['advance'], 2),
                'Net Payable'        => number_format($st['summary']['net'], 2),
            ],
            $st['opening'],
            ['Date', 'Voucher No', 'Type', 'Particulars', 'Method', 'Debit', 'Credit', 'Balance'],
            $rows,
            ['date', 'text', 'text', 'text', 'text', 'currency', 'currency', 'currency'],
            7,
            $st['closing'],
            [5, 6],
            'landscape',
            $st['has_any'] ? 'No transactions found.' : 'No ledger transactions found for this supplier.'
        )->stream('supplier_ledger_' . preg_replace('/[^a-z0-9]+/i', '_', $supplier['name']) . '_' . date('Ymd_His'));
    }

    private function _forExport(int $id): array
    {
        $supplier = \Config\Database::connect()->table('suppliers')->where('id', $id)->get()->getRowArray();
        if (! $supplier) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Supplier not found.');
        }

        $filters = $this->_filters();

        return [$supplier, $filters, $this->_statement($supplier, $filters)];
    }

    private function _filters(): array
    {
        $req  = $this->request;
        $date = static fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : '';
        $type = (string) $req->getGet('type');

        return [
            'supplier_id' => (int) $req->getGet('supplier_id'),
            'from'        => $date($req->getGet('from')),
            'to'          => $date($req->getGet('to')),
            'type'        => in_array($type, self::TYPES, true) ? $type : '',
            'q'           => trim((string) $req->getGet('q')),
            'show_paid'   => (bool) $req->getGet('show_paid'),
        ];
    }

    /**
     * Everything the page shows for one supplier. Running balances are computed
     * over the supplier's WHOLE history and only then filtered, so a filtered
     * row always shows the true balance at that point; a From date carries the
     * balance before it forward as the opening balance.
     */
    private function _statement(array $supplier, array $f): array
    {
        $db         = \Config\Database::connect();
        $supplierId = (int) $supplier['id'];

        $projectBills = $db->table('purchases')->where('supplier_id', $supplierId)->orderBy('purchase_date', 'ASC')->get()->getResultArray();
        $generalBills = $db->table('general_purchases')->where('supplier_id', $supplierId)->orderBy('purchase_date', 'ASC')->get()->getResultArray();
        $vouchers     = $db->table('supplier_payments')->where('supplier_id', $supplierId)->orderBy('payment_date', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();

        // Bill label lookup, from the rows already loaded (no per-allocation query).
        $labels = [];
        foreach ($projectBills as $b) {
            $labels['PROJECT:' . $b['id']] = $b['invoice_no'] ?: ('PUR-' . str_pad((string) $b['id'], 6, '0', STR_PAD_LEFT));
        }
        foreach ($generalBills as $b) {
            $labels['GENERAL:' . $b['id']] = $b['purchase_no'];
        }

        // GP advance vouchers only label the advance row; they are never credited.
        $gpaNo = [];
        foreach ($vouchers as $p) {
            if (SupplierPaymentModel::isGpAdvance($p['payment_no'])) {
                $gpaNo[SupplierPaymentModel::gpAdvanceSourceId($p['payment_no'])] = $p['payment_no'];
            }
        }

        $rows = [];

        foreach ($projectBills as $b) {
            $ref    = $labels['PROJECT:' . $b['id']];
            $rows[] = $this->_row($b['purchase_date'], $ref, 'Purchase', 'Project purchase bill' . ($b['invoice_no'] ? ' ' . $b['invoice_no'] : ''), (float) $b['total_amount'], 0.0, $b['invoice_no'] ?? '', '');
        }

        foreach ($generalBills as $b) {
            // Advance (credit) is listed BEFORE its purchase; the date sort below
            // is stable, so same-day rows keep this order (Release 4.8.4H).
            if ((float) $b['advance_paid'] > 0) {
                $rows[] = $this->_row($b['purchase_date'], $gpaNo[(int) $b['id']] ?? $b['purchase_no'], 'Advance', 'Advance paid during purchase ' . $b['purchase_no'], 0.0, (float) $b['advance_paid'], $b['bill_no'] ?? '', '', (string) ($b['payment_method'] ?? ''));
            }
            $rows[] = $this->_row($b['purchase_date'], $b['purchase_no'], 'Purchase', 'General purchase' . ($b['bill_no'] ? ' - Bill ' . $b['bill_no'] : ''), (float) $b['grand_total'], 0.0, $b['bill_no'] ?? '', '');
        }

        // Standalone advance history (GPA vouchers excluded) is built in the same pass.
        $allocationModel = new SupplierPaymentAllocationModel();
        $advRows         = [];
        $advPaidTotal    = 0.0;
        $advUsedTotal    = 0.0;

        foreach ($vouchers as $p) {
            if (SupplierPaymentModel::isGpAdvance($p['payment_no'])) {
                continue;
            }

            $remarks     = (string) ($p['remarks'] ?? '');
            $allocations = $allocationModel->forPayment((int) $p['id']);
            $advanceLeft = SupplierPaymentModel::advanceUsedOf(
                (float) $p['total_amount'],
                (float) $p['advance_amount'],
                (float) array_sum(array_column($allocations, 'paid_amount'))
            );

            if ((float) $p['advance_amount'] > 0) {
                $rows[]  = $this->_row($p['payment_date'], $p['payment_no'], 'Advance', 'Advance paid to supplier', 0.0, (float) $p['advance_amount'], '', $remarks, (string) ($p['payment_method'] ?? ''));
                $advRows[] = ['date' => $p['payment_date'], 'voucher' => $p['payment_no'], 'method' => (string) ($p['payment_method'] ?? ''), 'paid' => (float) $p['advance_amount'], 'used' => 0.0, 'remarks' => $remarks !== '' ? $remarks : 'Advance Paid'];
                $advPaidTotal += (float) $p['advance_amount'];
            }

            foreach ($allocations as $a) {
                $billRef = $labels[$a['purchase_type'] . ':' . $a['purchase_id']] ?? ('#' . $a['purchase_id']);
                $paid    = (float) $a['paid_amount'];
                $fromAdv = min($advanceLeft, $paid);
                $advanceLeft -= $fromAdv;
                $cash    = round($paid - $fromAdv, 2);

                if ($cash > 0.004 || $fromAdv <= 0.004) {
                    $rows[] = $this->_row($p['payment_date'], $p['payment_no'], 'Payment', 'Payment against bill ' . $billRef, 0.0, $cash, $billRef, $remarks, (string) ($p['payment_method'] ?? ''));
                }
                if ($fromAdv > 0.004) {
                    $rows[]  = $this->_row($p['payment_date'], $p['payment_no'], 'Adjustment', 'Advance adjusted against ' . $billRef, 0.0, 0.0, $billRef, $remarks);
                    $advRows[] = ['date' => $p['payment_date'], 'voucher' => $p['payment_no'], 'paid' => 0.0, 'used' => $fromAdv, 'remarks' => 'Advance Used Against ' . $billRef];
                    $advUsedTotal += $fromAdv;
                }
            }
        }

        // GPA vouchers' own payments (if any ever carried allocations) are not credited: skipped above.
        usort($rows, static fn ($x, $y) => strcmp((string) $x['date'], (string) $y['date']));

        $balance = 0.0;
        foreach ($rows as &$row) {
            $balance       += $row['debit'] - $row['credit'];
            $row['balance'] = round($balance, 2);
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
            if ($needle !== '' && ! str_contains(mb_strtolower($row['voucher'] . ' ' . $row['particulars'] . ' ' . $row['ref'] . ' ' . $row['remarks'] . ' ' . $row['ttype'] . ' ' . $supplier['name']), $needle)) {
                continue;
            }
            $shown[] = $row;
        }
        $closing = $shown ? end($shown)['balance'] : $opening;

        // ---- outstanding bills ----
        $paidByBill = [];
        $sums       = $db->query(
            'SELECT spa.purchase_type, spa.purchase_id, SUM(spa.paid_amount) AS paid
             FROM supplier_payment_allocations spa
             INNER JOIN supplier_payments sp ON sp.id = spa.supplier_payment_id
             WHERE sp.supplier_id = ? GROUP BY spa.purchase_type, spa.purchase_id',
            [$supplierId]
        )->getResultArray();
        foreach ($sums as $s) {
            $paidByBill[$s['purchase_type'] . ':' . $s['purchase_id']] = (float) $s['paid'];
        }

        $bills = [];
        foreach ($generalBills as $b) {
            $bills[] = $this->_bill($b['purchase_no'], $b['bill_no'] ?? '', $b['bill_date'] ?: $b['purchase_date'], (float) $b['grand_total'], (float) $b['advance_paid'] + ($paidByBill['GENERAL:' . $b['id']] ?? 0.0));
        }
        foreach ($projectBills as $b) {
            $bills[] = $this->_bill($labels['PROJECT:' . $b['id']], $b['invoice_no'] ?? '', $b['purchase_date'], (float) $b['total_amount'], $paidByBill['PROJECT:' . $b['id']] ?? 0.0);
        }
        usort($bills, static fn ($x, $y) => strcmp((string) $x['date'], (string) $y['date']));

        $outstanding = 0.0;
        $unpaidCount = 0;
        foreach ($bills as $b) {
            if ($b['outstanding'] > 0.004) {
                $outstanding += $b['outstanding'];
                $unpaidCount++;
            }
        }
        $billsShown = array_values(array_filter($bills, static function ($b) use ($f, $needle) {
            if (! $f['show_paid'] && $b['outstanding'] <= 0.004) {
                return false;
            }

            return $needle === '' || str_contains(mb_strtolower($b['gp_no'] . ' ' . $b['bill_no']), $needle);
        }));

        // ---- advance history with running remaining ----
        usort($advRows, static fn ($x, $y) => strcmp((string) $x['date'], (string) $y['date']));
        $remaining = 0.0;
        foreach ($advRows as &$a) {
            $remaining      += $a['paid'] - $a['used'];
            $a['remaining']  = round($remaining, 2);
        }
        unset($a);

        $advance = round($advPaidTotal - $advUsedTotal, 2);

        return [
            'rows'    => $shown,
            'has_any' => $rows !== [],
            'opening' => $opening,
            'closing' => $closing,
            'bills'   => $billsShown,
            'advance_rows' => $advRows,
            'summary' => [
                'unpaid_count' => $unpaidCount,
                'outstanding'  => round($outstanding, 2),
                'advance'      => $advance,
                'net'          => round($outstanding - $advance, 2),
                'balance'      => $rows ? end($rows)['balance'] : 0.0,
            ],
        ];
    }

    // Release 4.9.0CB: $method is the stored payment method of the source voucher ('' for bills / adjustments).
    private function _row(?string $date, string $voucher, string $ttype, string $particulars, float $debit, float $credit, string $ref, string $remarks, string $method = ''): array
    {
        return [
            'date' => (string) $date, 'voucher' => $voucher, 'ttype' => $ttype, 'particulars' => $particulars,
            'debit' => $debit, 'credit' => $credit, 'ref' => $ref, 'remarks' => $remarks, 'method' => $method,
        ];
    }

    private function _bill(string $gpNo, string $billNo, ?string $date, float $amount, float $paid): array
    {
        $outstanding = round($amount - $paid, 2);

        return [
            'gp_no' => $gpNo, 'bill_no' => $billNo, 'date' => (string) $date,
            'amount' => $amount, 'paid' => round($paid, 2), 'outstanding' => $outstanding,
            'status' => $outstanding <= 0.004 ? 'PAID' : ($paid > 0.004 ? 'PARTIAL' : 'PENDING'),
        ];
    }
}
