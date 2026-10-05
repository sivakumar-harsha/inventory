<?php
$fmt   = static fn ($n) => number_format((float) $n, 2);
$dmy   = static fn ($d) => $d ? date('d/m/Y', strtotime($d)) : '-';
$slug  = static fn ($t) => str_replace(' ', '-', strtolower($t));
$qs    = http_build_query(array_filter([
    'from' => $filters['from'], 'to' => $filters['to'], 'type' => $filters['type'], 'q' => $filters['q'],
], static fn ($v) => $v !== ''));
$cid   = (int) ($customer['id'] ?? 0);
$balCls = static fn ($b) => $b > 0.004 ? 'bal-pos' : ($b < -0.004 ? 'bal-neg' : 'bal-zero');
// Release 4.9.0AI: a negative running/closing balance means the customer has
// paid more than currently owed (Customer Project Cash + advance + invoice
// payments exceeding invoice debits) - it is a credit position, not a
// negative debt. Presented with the app's existing 'Cr' suffix convention
// (see projects/statement.php, customer_ledger/view.php); the underlying
// signed value and running-balance arithmetic are unchanged.
$fmtBal = static fn ($b) => $b < -0.004 ? $fmt(abs($b)) . ' Cr' : $fmt($b);
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
	.clg-page .sl-breadcrumb { margin-bottom: 6px; }
	.clg-page .sl-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.clg-page .sl-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.clg-page .sl-breadcrumb .breadcrumb-item.active { color: #64748b; }

	/* Filters: one compact row (same as Supplier Ledger) */
	.clg-filters { display: grid; grid-template-columns: minmax(0, 2fr) repeat(2, minmax(0, 1fr)) minmax(0, 1fr) minmax(0, 1.4fr) auto; gap: 8px; align-items: end; padding: 8px 12px; margin-bottom: 6px; background: #fff; border: 1px solid var(--border-color); border-radius: var(--border-radius); }
	.clg-filters label { display: block; margin: 0 0 2px; font-size: .62rem; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); }
	.clg-filters .form-control { height: 32px; padding: 3px 8px; font-size: .8rem; }
	.clg-filters .btn-cancel { height: 32px; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }

	/* Summary strip */
	.clg-strip { display: grid; grid-template-columns: minmax(0, 2fr) repeat(4, minmax(0, 1fr)); align-items: center; gap: 2px 12px; min-height: 58px; padding: 5px 12px; margin-bottom: 8px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--border-radius); }
	.clg-strip .st-item { display: flex; flex-direction: column; min-width: 0; }
	.clg-strip .st-item > span { font-size: .62rem; line-height: 1.2; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); }
	.clg-strip .st-item > b { font-size: .86rem; line-height: 1.3; color: var(--text-dark); font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
	.clg-strip .st-name > b { font-size: .95rem; font-weight: 700; }
	.clg-strip .st-name > span.gst { font-size: .68rem; text-transform: none; letter-spacing: 0; }
	.clg-strip .st-due > b { color: #c2410c; }
	.clg-strip .st-adv > b { color: #1d4ed8; }
	.clg-strip .st-net > b { color: #166534; }
	.clg-strip .st-net.credit > b { color: #1d4ed8; }

	/* Ledger table */
	.clg-scroll { max-height: 62vh; overflow: auto; }
	.clg-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .8rem; }
	.clg-table thead th { position: sticky; top: 0; z-index: 2; background: #f1f5f9; padding: 0 10px; height: 34px; font-size: .68rem; text-transform: none; letter-spacing: .03em; color: var(--text-muted); border-bottom: 1px solid var(--border-color); white-space: nowrap; }
	.clg-table td { height: 44px; padding: 0 10px; border-bottom: 1px solid #eef2f7; vertical-align: middle; }
	.clg-table td.num, .clg-table th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
	.clg-table td.part { min-width: 220px; }
	.clg-table tr.opening td { background: #f8fafc; color: var(--text-muted); font-style: italic; height: 34px; }
	.clg-table tfoot td { position: sticky; bottom: 0; background: #f1f5f9; font-weight: 700; height: 38px; }
	.amt-debit { color: #c2410c; } .amt-credit { color: #166534; }
	.bal-pos { color: #c2410c; font-weight: 600; } .bal-zero { color: #166534; font-weight: 600; } .bal-neg { color: #1d4ed8; font-weight: 600; }
	.amt-nil { color: #cbd5e1; }

	.tbadge { display: inline-flex; align-items: center; height: 20px; max-height: 22px; padding: 0 8px; border-radius: 10px; font-size: .66rem; font-weight: 600; white-space: nowrap; }
	.tbadge.t-invoice    { background: #ffedd5; color: #9a3412; }
	.tbadge.t-payment    { background: #dcfce7; color: #166534; }
	.tbadge.t-advance    { background: #dbeafe; color: #1e40af; }
	.tbadge.t-adjustment { background: #e2e8f0; color: #475569; }
	.tbadge.t-unallocated-project-receipt { background: #f3e8ff; color: #6b21a8; }
	.tbadge.s-paid    { background: #dcfce7; color: #166534; }
	.tbadge.s-partial { background: #fef3c7; color: #92400e; }
	.tbadge.s-pending { background: #ffedd5; color: #9a3412; }

	.clg-section-title { display: flex; justify-content: space-between; align-items: center; }
	.clg-empty { padding: 26px 12px; text-align: center; color: #94a3b8; font-size: .85rem; }
	.clg-check { font-size: .72rem; font-weight: 400; color: var(--text-muted); text-transform: none; letter-spacing: 0; }

	@media (max-width: 1199.98px) {
		.clg-filters { grid-template-columns: repeat(3, minmax(0, 1fr)); }
		.clg-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
		.clg-strip .st-name { grid-column: 1 / -1; }
	}

	/* Mobile: ledger rows and invoice rows become cards */
	@media (max-width: 767.98px) {
		.clg-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
		.clg-filters > :first-child, .clg-filters > :nth-child(5) { grid-column: 1 / -1; }
		.clg-strip { grid-template-columns: 1fr; }
		.clg-scroll { max-height: none; overflow: visible; }
		.clg-table thead { display: none; }
		.clg-table, .clg-table tbody, .clg-table tfoot, .clg-table tr, .clg-table td { display: block; width: 100%; }
		.clg-table tr { margin-bottom: 8px; border: 1px solid var(--border-color); border-radius: var(--border-radius); background: #fff; padding: 4px 0; }
		.clg-table td { height: auto; min-height: 28px; display: flex; justify-content: space-between; gap: 12px; padding: 4px 12px; border: 0; text-align: right; }
		.clg-table td::before { content: attr(data-label); color: var(--text-muted); font-size: .68rem; text-transform: uppercase; text-align: left; flex: none; }
		.clg-table td.part { min-width: 0; }
		.clg-table tfoot td { position: static; }
	}
</style>

<div class="clg-page">
<nav aria-label="breadcrumb" class="sl-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Transactions</li>
        <li class="breadcrumb-item active" aria-current="page">Customer Ledger</li>
    </ol>
</nav>

<form method="get" action="<?= base_url('customer-ledger') ?>" id="clgForm" class="clg-filters">
    <div>
        <label for="fCustomer">Customer Ledger</label>
        <select name="customer_id" id="fCustomer" class="form-control">
            <option value="">— Select customer —</option>
            <?php foreach ($customers as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === (int) $filters['customer_id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div><label for="fFrom">From Date</label><input type="date" name="from" id="fFrom" class="form-control" value="<?= esc($filters['from']) ?>"></div>
    <div><label for="fTo">To Date</label><input type="date" name="to" id="fTo" class="form-control" value="<?= esc($filters['to']) ?>"></div>
    <div>
        <label for="fType">Transaction Type</label>
        <select name="type" id="fType" class="form-control">
            <option value="">All</option>
            <?php foreach ($types as $t): ?>
            <option value="<?= esc($t) ?>" <?= $filters['type'] === $t ? 'selected' : '' ?>><?= esc($t) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div><label for="fQ">Search</label><input type="search" name="q" id="fQ" class="form-control" placeholder="Voucher, invoice, remarks…" value="<?= esc($filters['q']) ?>"></div>
    <div class="dropdown">
        <?php if ($customer): ?>
        <button type="button" class="btn-cancel dropdown-toggle" data-toggle="dropdown" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-download"></i> Export</button>
        <div class="dropdown-menu dropdown-menu-right dropdown-menu-end">
            <a class="dropdown-item" href="<?= base_url('customer-ledger/export/excel/' . $cid) . ($qs ? '?' . $qs : '') ?>"><i class="bi bi-file-earmark-excel"></i> Excel</a>
            <a class="dropdown-item" href="<?= base_url('customer-ledger/export/pdf/' . $cid) . ($qs ? '?' . $qs : '') ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        </div>
        <?php else: ?>
        <button type="button" class="btn-cancel" disabled><i class="bi bi-download"></i> Export</button>
        <?php endif; ?>
    </div>
    <?php if ($filters['show_paid']): ?><input type="hidden" name="show_paid" value="1"><?php endif; ?>
</form>

<?php if (! $customer): ?>
<div class="card-custom"><div class="clg-empty">Select a customer to view the ledger.</div></div>
<?php else: ?>

<div class="clg-strip">
    <div class="st-item st-name"><b><?= esc($customer['name']) ?></b><span class="gst">GST: <?= esc($customer['gst'] ?: '—') ?></span></div>
    <div class="st-item st-adv"><span>Customer Advance</span><b>₹ <?= $fmt($summary['advance']) ?></b></div>
    <div class="st-item"><span>Outstanding Invoices</span><b><?= (int) $summary['unpaid_count'] ?></b></div>
    <div class="st-item st-due"><span>Outstanding Amount</span><b>₹ <?= $fmt($summary['outstanding']) ?></b></div>
    <?php $clBalance = (float) $summary['balance']; ?>
    <?php if ($clBalance < -0.004): ?>
    <div class="st-item st-net credit"><span>Customer Credit</span><b>₹ <?= $fmt(abs($clBalance)) ?> Cr</b></div>
    <?php else: ?>
    <div class="st-item st-net"><span>Net Receivable</span><b>₹ <?= $fmt($clBalance) ?></b></div>
    <?php endif; ?>
</div>

<div class="card-custom mb-3">
    <div class="clg-scroll">
        <table class="clg-table" id="ledgerTable">
            <thead>
                <tr>
                    <th class="sno-col">S.No.</th>
                    <th>Date</th><th>Voucher No</th><th>Type</th><th>Particulars</th><th>Method</th>
                    <th class="num">Debit</th><th class="num">Credit</th><th class="num">Running Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                <tr><td colspan="9" class="clg-empty" style="display:table-cell">No transactions found.</td></tr>
                <?php else: ?>
                <?php if (abs($opening) > 0.004): ?>
                <tr class="opening"><td colspan="8">Opening balance brought forward</td><td class="num <?= $balCls($opening) ?>"><?= $fmtBal($opening) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $t): ?>
                <tr>
                    <td class="sno-col sno-auto" data-label="S.No."></td>
                    <td data-label="Date"><?= $dmy($t['date']) ?></td>
                    <td data-label="Voucher"><?= esc($t['voucher'] ?: '-') ?></td>
                    <td data-label="Type"><span class="tbadge t-<?= $slug($t['ttype']) ?>"><?= esc($t['ttype']) ?></span></td>
                    <td data-label="Particulars" class="part"><?= esc($t['particulars']) ?></td>
                    <td data-label="Method"><?= pm_badge($t['method'], $t['ttype'] === 'Invoice' ? '—' : 'Not recorded') ?></td>
                    <td data-label="Debit" class="num <?= $t['debit'] > 0 ? 'amt-debit' : 'amt-nil' ?>"><?= $t['debit'] > 0 ? $fmt($t['debit']) : '-' ?></td>
                    <td data-label="Credit" class="num <?= $t['credit'] > 0 ? 'amt-credit' : 'amt-nil' ?>"><?= $t['credit'] > 0 ? $fmt($t['credit']) : '-' ?></td>
                    <td data-label="Balance" class="num <?= $balCls($t['balance']) ?>"><?= $fmtBal($t['balance']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (! empty($rows)): ?>
            <tfoot><tr><td colspan="8" class="num">Closing Balance</td><td class="num <?= $balCls($closing) ?>"><?= $fmtBal($closing) ?></td></tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="card-custom mb-3">
    <div class="card-custom-header clg-section-title">
        <span>Outstanding Invoices</span>
        <label class="clg-check mb-0"><input type="checkbox" id="showPaid" <?= $filters['show_paid'] ? 'checked' : '' ?>> Show paid invoices</label>
    </div>
    <div class="clg-scroll" style="max-height:none">
        <table class="clg-table">
            <thead>
                <tr><th class="sno-col">S.No.</th><th>Invoice No</th><th>Invoice Date</th><th class="num">Invoice Amount</th><th class="num">Received</th><th class="num">Outstanding</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php if (empty($bills)): ?>
                <tr><td colspan="7" class="clg-empty" style="display:table-cell">No outstanding invoices.</td></tr>
                <?php endif; ?>
                <?php foreach ($bills as $b): ?>
                <tr>
                    <td class="sno-col sno-auto" data-label="S.No."></td>
                    <td data-label="Invoice No"><?= esc($b['no']) ?></td>
                    <td data-label="Date"><?= $dmy($b['date']) ?></td>
                    <td data-label="Amount" class="num"><?= $fmt($b['amount']) ?></td>
                    <td data-label="Received" class="num amt-credit"><?= $fmt($b['paid']) ?></td>
                    <td data-label="Outstanding" class="num amt-debit"><?= $fmt($b['outstanding']) ?></td>
                    <td data-label="Status"><span class="tbadge s-<?= strtolower($b['status']) ?>"><?= $b['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card-custom mb-3">
    <div class="card-custom-header">Customer Advance History</div>
    <div class="clg-scroll" style="max-height:none">
        <table class="clg-table">
            <thead>
                <tr><th class="sno-col">S.No.</th><th>Date</th><th>Voucher</th><th>Source</th><th>Method</th><th class="num">Advance Received</th><th class="num">Total Advance</th><th>Remarks</th></tr>
            </thead>
            <tbody>
                <?php if (empty($advance_rows)): ?>
                <tr><td colspan="8"class="clg-empty" style="display:table-cell">No customer advances.</td></tr>
                <?php endif; ?>
                <?php foreach ($advance_rows as $a): ?>
                <tr>
                    <td class="sno-col sno-auto" data-label="S.No."></td>
                    <td data-label="Date"><?= $dmy($a['date']) ?></td>
                    <td data-label="Voucher"><?= esc($a['voucher']) ?></td>
                    <td data-label="Source"><?= esc($a['source']) ?></td>
                    <td data-label="Method"><?= pm_badge($a['method'] ?? '', 'Not recorded') ?></td>
                    <td data-label="Received" class="num amt-credit"><?= $fmt($a['received']) ?></td>
                    <td data-label="Total" class="num bal-neg"><?= $fmt($a['running']) ?></td>
                    <td data-label="Remarks" class="part"><?= esc($a['remarks']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(function () {
	var form = document.getElementById('clgForm'), timer;
	$('#fCustomer, #fFrom, #fTo, #fType').on('change', function () { form.submit(); });
	$('#fQ').on('input', function () { clearTimeout(timer); timer = setTimeout(function () { form.submit(); }, 600); });
	$('#showPaid').on('change', function () {
		$('input[name=show_paid]').remove();
		if (this.checked) { $(form).append('<input type="hidden" name="show_paid" value="1">'); }
		form.submit();
	});
	var q = document.getElementById('fQ');
	if (q && q.value) { q.focus(); q.setSelectionRange(q.value.length, q.value.length); }
});
</script>
<?= $this->endSection() ?>
