<?php
$fmt   = static fn ($n) => number_format((float) $n, 2);
$dmy   = static fn ($d) => $d ? date('d/m/Y', strtotime($d)) : '-';
$slug  = static fn ($t) => strtolower($t);
$qs    = http_build_query(array_filter([
    'from' => $filters['from'], 'to' => $filters['to'], 'type' => $filters['type'], 'q' => $filters['q'],
], static fn ($v) => $v !== ''));
$sid   = (int) ($supplier['id'] ?? 0);
$balCls = static fn ($b) => $b > 0.004 ? 'bal-pos' : ($b < -0.004 ? 'bal-neg' : 'bal-zero');
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
	.slg-page .sl-breadcrumb { margin-bottom: 6px; }
	.slg-page .sl-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.slg-page .sl-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.slg-page .sl-breadcrumb .breadcrumb-item.active { color: #64748b; }

	/* Filters: one compact row */
	.slg-filters { display: grid; grid-template-columns: minmax(0, 2fr) repeat(2, minmax(0, 1fr)) minmax(0, 1fr) minmax(0, 1.4fr) auto; gap: 8px; align-items: end; padding: 8px 12px; margin-bottom: 6px; background: #fff; border: 1px solid var(--border-color); border-radius: var(--border-radius); }
	.slg-filters label { display: block; margin: 0 0 2px; font-size: .62rem; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); }
	.slg-filters .form-control { height: 32px; padding: 3px 8px; font-size: .8rem; }
	.slg-filters .btn-cancel { height: 32px; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }

	/* Summary strip: same look as the Supplier Payment strip */
	.slg-strip { display: grid; grid-template-columns: minmax(0, 2fr) repeat(4, minmax(0, 1fr)); align-items: center; gap: 2px 12px; min-height: 58px; padding: 5px 12px; margin-bottom: 8px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--border-radius); }
	.slg-strip .st-item { display: flex; flex-direction: column; min-width: 0; }
	.slg-strip .st-item > span { font-size: .62rem; line-height: 1.2; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); }
	.slg-strip .st-item > b { font-size: .86rem; line-height: 1.3; color: var(--text-dark); font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
	.slg-strip .st-name > b { font-size: .95rem; font-weight: 700; }
	.slg-strip .st-name > span.gst { font-size: .68rem; text-transform: none; letter-spacing: 0; }
	.slg-strip .st-due > b { color: #c2410c; }
	.slg-strip .st-adv > b { color: #1d4ed8; }
	.slg-strip .st-net > b { color: #166534; }

	/* Ledger table */
	.slg-scroll { max-height: 62vh; overflow: auto; }
	.slg-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .8rem; }
	.slg-table thead th { position: sticky; top: 0; z-index: 2; background: #f1f5f9; padding: 0 10px; height: 34px; font-size: .68rem; text-transform: none; letter-spacing: .03em; color: var(--text-muted); border-bottom: 1px solid var(--border-color); white-space: nowrap; }
	.slg-table td { height: 44px; padding: 0 10px; border-bottom: 1px solid #eef2f7; vertical-align: middle; }
	.slg-table td.num, .slg-table th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
	.slg-table td.part { min-width: 220px; }
	.slg-table tr.opening td { background: #f8fafc; color: var(--text-muted); font-style: italic; height: 34px; }
	.slg-table tfoot td { position: sticky; bottom: 0; background: #f1f5f9; font-weight: 700; height: 38px; }
	.amt-debit { color: #c2410c; } .amt-credit { color: #166534; }
	.bal-pos { color: #c2410c; font-weight: 600; } .bal-zero { color: #166534; font-weight: 600; } .bal-neg { color: #1d4ed8; font-weight: 600; }
	.amt-nil { color: #cbd5e1; }

	.tbadge { display: inline-flex; align-items: center; height: 20px; max-height: 22px; padding: 0 8px; border-radius: 10px; font-size: .66rem; font-weight: 600; white-space: nowrap; }
	.tbadge.t-purchase   { background: #ffedd5; color: #9a3412; }
	.tbadge.t-payment    { background: #dcfce7; color: #166534; }
	.tbadge.t-advance    { background: #dbeafe; color: #1e40af; }
	.tbadge.t-adjustment { background: #e2e8f0; color: #475569; }
	.tbadge.s-paid    { background: #dcfce7; color: #166534; }
	.tbadge.s-partial { background: #fef3c7; color: #92400e; }
	.tbadge.s-pending { background: #ffedd5; color: #9a3412; }

	.slg-section-title { display: flex; justify-content: space-between; align-items: center; }
	.slg-empty { padding: 26px 12px; text-align: center; color: #94a3b8; font-size: .85rem; }
	.slg-check { font-size: .72rem; font-weight: 400; color: var(--text-muted); text-transform: none; letter-spacing: 0; }

	@media (max-width: 1199.98px) {
		.slg-filters { grid-template-columns: repeat(3, minmax(0, 1fr)); }
		.slg-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
		.slg-strip .st-name { grid-column: 1 / -1; }
	}

	/* Mobile: ledger rows and bill rows become cards */
	@media (max-width: 767.98px) {
		.slg-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
		.slg-filters > :first-child, .slg-filters > :nth-child(5) { grid-column: 1 / -1; }
		.slg-strip { grid-template-columns: 1fr; }
		.slg-scroll { max-height: none; overflow: visible; }
		.slg-table thead { display: none; }
		.slg-table, .slg-table tbody, .slg-table tfoot, .slg-table tr, .slg-table td { display: block; width: 100%; }
		.slg-table tr { margin-bottom: 8px; border: 1px solid var(--border-color); border-radius: var(--border-radius); background: #fff; padding: 4px 0; }
		.slg-table td { height: auto; min-height: 28px; display: flex; justify-content: space-between; gap: 12px; padding: 4px 12px; border: 0; text-align: right; }
		.slg-table td::before { content: attr(data-label); color: var(--text-muted); font-size: .68rem; text-transform: uppercase; text-align: left; flex: none; }
		.slg-table td.part { min-width: 0; }
		.slg-table tfoot td { position: static; }
	}
</style>

<div class="slg-page">
<nav aria-label="breadcrumb" class="sl-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Transactions</li>
        <li class="breadcrumb-item active" aria-current="page">Supplier Ledger</li>
    </ol>
</nav>

<form method="get" action="<?= base_url('supplier-ledger') ?>" id="slgForm" class="slg-filters">
    <div>
        <label for="fSupplier">Supplier Ledger</label>
        <select name="supplier_id" id="fSupplier" class="form-control">
            <option value="">— Select supplier —</option>
            <?php foreach ($suppliers as $s): ?>
            <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === (int) $filters['supplier_id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option>
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
    <div><label for="fQ">Search</label><input type="search" name="q" id="fQ" class="form-control" placeholder="Voucher, GP, bill, remarks…" value="<?= esc($filters['q']) ?>"></div>
    <div class="dropdown">
        <?php if ($supplier): ?>
        <button type="button" class="btn-cancel dropdown-toggle" data-toggle="dropdown" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-download"></i> Export</button>
        <div class="dropdown-menu dropdown-menu-right dropdown-menu-end">
            <a class="dropdown-item" href="<?= base_url('supplier-ledger/export/excel/' . $sid) . ($qs ? '?' . $qs : '') ?>"><i class="bi bi-file-earmark-excel"></i> Excel</a>
            <a class="dropdown-item" href="<?= base_url('supplier-ledger/export/pdf/' . $sid) . ($qs ? '?' . $qs : '') ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        </div>
        <?php else: ?>
        <button type="button" class="btn-cancel" disabled><i class="bi bi-download"></i> Export</button>
        <?php endif; ?>
    </div>
    <?php if ($filters['show_paid']): ?><input type="hidden" name="show_paid" value="1"><?php endif; ?>
</form>

<?php if (! $supplier): ?>
<div class="card-custom"><div class="slg-empty">Select a supplier to view the ledger.</div></div>
<?php else: ?>

<div class="slg-strip">
    <div class="st-item st-name"><b><?= esc($supplier['name']) ?></b><span class="gst">GST: <?= esc($supplier['gst'] ?: '—') ?></span></div>
    <div class="st-item st-adv"><span>Advance Available</span><b>₹ <?= $fmt($summary['advance']) ?></b></div>
    <div class="st-item"><span>Outstanding Bills</span><b><?= (int) $summary['unpaid_count'] ?></b></div>
    <div class="st-item st-due"><span>Outstanding Amount</span><b>₹ <?= $fmt($summary['outstanding']) ?></b></div>
    <div class="st-item st-net"><span>Net Payable</span><b>₹ <?= $fmt($summary['net']) ?></b></div>
</div>

<div class="card-custom mb-3">
    <div class="slg-scroll">
        <table class="slg-table" id="ledgerTable">
            <thead>
                <tr>
                    <th class="sno-col">S.No.</th>
                    <th>Date</th><th>Voucher No</th><th>Type</th><th>Particulars</th><th>Method</th>
                    <th class="num">Debit</th><th class="num">Credit</th><th class="num">Running Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                <tr><td colspan="9" class="slg-empty" style="display:table-cell"><?= $has_any ? 'No transactions found.' : 'No ledger transactions found for this supplier.' ?></td></tr>
                <?php else: ?>
                <?php if (abs($opening) > 0.004): ?>
                <tr class="opening"><td colspan="8">Opening balance brought forward</td><td class="num <?= $balCls($opening) ?>"><?= $fmt($opening) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $t): ?>
                <tr>
                    <td class="sno-col sno-auto" data-label="S.No."></td>
                    <td data-label="Date"><?= $dmy($t['date']) ?></td>
                    <td data-label="Voucher"><?= esc($t['voucher'] ?: '-') ?></td>
                    <td data-label="Type"><span class="tbadge t-<?= $slug($t['ttype']) ?>"><?= esc($t['ttype']) ?></span></td>
                    <td data-label="Particulars" class="part"><?= esc($t['particulars']) ?></td>
                    <td data-label="Method"><?= pm_badge($t["method"] ?? "", in_array($t["ttype"], ["Payment", "Advance"], true) ? "Not recorded" : "—") ?></td>
                    <td data-label="Debit" class="num <?= $t['debit'] > 0 ? 'amt-debit' : 'amt-nil' ?>"><?= $t['debit'] > 0 ? $fmt($t['debit']) : '-' ?></td>
                    <td data-label="Credit" class="num <?= $t['credit'] > 0 ? 'amt-credit' : 'amt-nil' ?>"><?= $t['credit'] > 0 ? $fmt($t['credit']) : '-' ?></td>
                    <td data-label="Balance" class="num <?= $balCls($t['balance']) ?>"><?= $fmt($t['balance']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (! empty($rows)): ?>
            <tfoot><tr><td colspan="8" class="num">Closing Balance</td><td class="num <?= $balCls($closing) ?>"><?= $fmt($closing) ?></td></tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="card-custom mb-3">
    <div class="card-custom-header slg-section-title">
        <span>Outstanding Bills</span>
        <label class="slg-check mb-0"><input type="checkbox" id="showPaid" <?= $filters['show_paid'] ? 'checked' : '' ?>> Show paid bills</label>
    </div>
    <div class="slg-scroll" style="max-height:none">
        <table class="slg-table">
            <thead>
                <tr><th class="sno-col">S.No.</th><th>GP No</th><th>Bill No</th><th>Bill Date</th><th class="num">Bill Amount</th><th class="num">Paid Amount</th><th class="num">Outstanding</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php if (empty($bills)): ?>
                <tr><td colspan="8" class="slg-empty" style="display:table-cell">No outstanding bills.</td></tr>
                <?php endif; ?>
                <?php foreach ($bills as $b): ?>
                <tr>
                    <td class="sno-col sno-auto" data-label="S.No."></td>
                    <td data-label="GP No"><?= esc($b['gp_no']) ?></td>
                    <td data-label="Bill No"><?= esc($b['bill_no'] ?: '-') ?></td>
                    <td data-label="Bill Date"><?= $dmy($b['date']) ?></td>
                    <td data-label="Bill Amount" class="num"><?= $fmt($b['amount']) ?></td>
                    <td data-label="Paid" class="num amt-credit"><?= $fmt($b['paid']) ?></td>
                    <td data-label="Outstanding" class="num amt-debit"><?= $fmt($b['outstanding']) ?></td>
                    <td data-label="Status"><span class="tbadge s-<?= strtolower($b['status']) ?>"><?= $b['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card-custom mb-3">
    <div class="card-custom-header">Advance History</div>
    <div class="slg-scroll" style="max-height:none">
        <table class="slg-table">
            <thead>
                <tr><th class="sno-col">S.No.</th><th>Date</th><th>Voucher</th><th>Method</th><th class="num">Advance Paid</th><th class="num">Advance Used</th><th class="num">Remaining Advance</th><th>Remarks</th></tr>
            </thead>
            <tbody>
                <?php if (empty($advance_rows)): ?>
                <tr><td colspan="8" class="slg-empty" style="display:table-cell">No standalone advances.</td></tr>
                <?php endif; ?>
                <?php foreach ($advance_rows as $a): ?>
                <tr>
                    <td class="sno-col sno-auto" data-label="S.No."></td>
                    <td data-label="Date"><?= $dmy($a['date']) ?></td>
                    <td data-label="Voucher"><?= esc($a['voucher']) ?></td>
                    <td data-label="Method"><?= pm_badge($a["method"] ?? "", "—") ?></td>
                    <td data-label="Paid" class="num <?= $a['paid'] > 0 ? 'amt-credit' : 'amt-nil' ?>"><?= $a['paid'] > 0 ? $fmt($a['paid']) : '-' ?></td>
                    <td data-label="Used" class="num <?= $a['used'] > 0 ? 'amt-debit' : 'amt-nil' ?>"><?= $a['used'] > 0 ? $fmt($a['used']) : '-' ?></td>
                    <td data-label="Remaining" class="num bal-neg"><?= $fmt($a['remaining']) ?></td>
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
	var form = document.getElementById('slgForm'), timer;
	$('#fSupplier, #fFrom, #fTo, #fType').on('change', function () { form.submit(); });
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
