<?php
$fmt    = static fn ($n) => number_format((float) $n, 2);
$dmy    = static fn ($d) => $d ? date('d/m/Y', strtotime($d)) : '-';
$qs     = http_build_query(array_filter([
    'category_id' => $f['category_id'] ?: '', 'project_id' => $f['project_id'] ?: '', 'payment_method' => $f['payment_method'],
    'date_from' => $f['date_from'], 'date_to' => $f['date_to'], 'search' => $f['search'],
], static fn ($v) => $v !== ''));
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
	.elg-page .sl-breadcrumb { margin-bottom: 6px; }
	.elg-page .sl-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.elg-page .sl-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.elg-page .sl-breadcrumb .breadcrumb-item.active { color: #64748b; }

	.elg-filters { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)) repeat(2, minmax(0, .9fr)) minmax(0, 1.3fr) auto; gap: 8px; align-items: end; padding: 8px 12px; margin-bottom: 6px; background: #fff; border: 1px solid var(--border-color); border-radius: var(--border-radius); }
	.elg-filters label { display: block; margin: 0 0 2px; font-size: .62rem; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); }
	.elg-filters .form-control { height: 32px; padding: 3px 8px; font-size: .8rem; }
	.elg-filters .btn-cancel { height: 32px; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }

	.elg-strip { position: sticky; top: 0; z-index: 3; display: flex; align-items: center; gap: 2px 12px; min-height: 44px; padding: 5px 12px; margin-bottom: 8px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--border-radius); }
	.elg-strip .st-item { display: flex; flex-direction: column; min-width: 0; }
	.elg-strip .st-item > span { font-size: .62rem; line-height: 1.2; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); }
	.elg-strip .st-item > b { font-size: .95rem; line-height: 1.3; color: var(--text-dark); font-variant-numeric: tabular-nums; white-space: nowrap; }
	.elg-strip .st-paid > b { color: #166534; }

	.elg-scroll { max-height: 62vh; overflow: auto; }
	.elg-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .8rem; }
	.elg-table thead th { position: sticky; top: 0; z-index: 2; background: #f1f5f9; padding: 0 10px; height: 34px; font-size: .68rem; text-transform: none; letter-spacing: .03em; color: var(--text-muted); border-bottom: 1px solid var(--border-color); white-space: nowrap; }
	.elg-table td { height: 44px; padding: 0 10px; border-bottom: 1px solid #eef2f7; vertical-align: middle; }
	.elg-table td.num, .elg-table th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
	.elg-table td.part { min-width: 160px; max-width: 260px; white-space: normal; word-break: break-word; }
	.amt-debit { color: #1e293b; } .amt-nil { color: #cbd5e1; }
	tr.is-cancelled td { color: #94a3b8; }

	.tbadge { display: inline-flex; align-items: center; height: 20px; max-height: 22px; padding: 0 8px; border-radius: 10px; font-size: .66rem; font-weight: 600; white-space: nowrap; }
	.tbadge.s-paid { background: #dcfce7; color: #166534; }
	.tbadge.s-cancelled { background: #e2e8f0; color: #475569; }
	.tbadge.s-pending { background: #ffedd5; color: #9a3412; }

	.elg-empty { padding: 26px 12px; text-align: center; color: #94a3b8; font-size: .85rem; }
	.elg-warn { margin-bottom: 6px; padding: 8px 12px; border-radius: var(--border-radius); background: #fef3c7; color: #92400e; font-size: .8rem; }

	@media (max-width: 1199.98px) {
		.elg-filters { grid-template-columns: repeat(3, minmax(0, 1fr)); }
		.elg-strip { position: static; }
	}
	@media (max-width: 767.98px) {
		.elg-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
		.elg-scroll { max-height: none; overflow: visible; }
		.elg-table thead { display: none; }
		.elg-table, .elg-table tbody, .elg-table tfoot, .elg-table tr, .elg-table td { display: block; width: 100%; }
		.elg-table tr { margin-bottom: 8px; border: 1px solid var(--border-color); border-radius: var(--border-radius); background: #fff; padding: 4px 0; }
		.elg-table td { height: auto; min-height: 28px; display: flex; justify-content: space-between; gap: 12px; padding: 4px 12px; border: 0; text-align: right; }
		.elg-table td::before { content: attr(data-label); color: var(--text-muted); font-size: .68rem; text-transform: uppercase; text-align: left; flex: none; }
		.elg-table td.part { min-width: 0; max-width: none; }
	}
</style>

<div class="elg-page">
<nav aria-label="breadcrumb" class="sl-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Transactions</li>
        <li class="breadcrumb-item active" aria-current="page">Expense Ledger</li>
    </ol>
</nav>

<form method="get" action="<?= base_url('expense-reports') ?>" id="elgForm" class="elg-filters">
    <div>
        <label for="fCat">Expense Category</label>
        <select name="category_id" id="fCat" class="form-control">
            <option value="">All</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === (int) $f['category_id'] ? 'selected' : '' ?>><?= esc($c['category_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="fProj">Project</label>
        <select name="project_id" id="fProj" class="form-control">
            <option value="">All</option>
            <?php foreach ($projects as $p): ?>
            <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === (int) $f['project_id'] ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="fMethod">Payment Method</label>
        <select name="payment_method" id="fMethod" class="form-control">
            <option value="">All</option>
            <?php foreach ($paymentMethods as $m): ?>
            <option value="<?= esc($m) ?>" <?= $f['payment_method'] === $m ? 'selected' : '' ?>><?= esc(pm_label($m)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div><label for="fFrom">From Date</label><input type="date" name="date_from" id="fFrom" class="form-control" value="<?= esc($f['date_from']) ?>"></div>
    <div><label for="fTo">To Date</label><input type="date" name="date_to" id="fTo" class="form-control" value="<?= esc($f['date_to']) ?>"></div>
    <div class="d-none d-xl-block"></div>
    <div><label for="fQ">Search</label><input type="search" name="search" id="fQ" class="form-control" placeholder="Voucher, paid to…" value="<?= esc($f['search']) ?>"></div>
    <div class="dropdown">
        <button type="button" class="btn-cancel dropdown-toggle" data-toggle="dropdown" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-download"></i> Export</button>
        <div class="dropdown-menu dropdown-menu-right dropdown-menu-end">
            <a class="dropdown-item" href="<?= base_url('expense-reports/export/excel') . ($qs ? '?' . $qs : '') ?>"><i class="bi bi-file-earmark-excel"></i> Excel</a>
            <a class="dropdown-item" href="<?= base_url('expense-reports/export/pdf') . ($qs ? '?' . $qs : '') ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        </div>
    </div>
</form>

<?php if (! empty($warning)): ?><div class="elg-warn"><?= esc($warning) ?></div><?php endif; ?>

<div class="elg-strip">
    <div class="st-item st-paid"><span>Total Expense Paid</span><b>₹ <?= $fmt($summary['paid_total']) ?></b></div>
</div>

<div class="card-custom mb-3">
    <div class="elg-scroll">
        <table class="elg-table">
            <thead>
                <tr>
                    <th class="sno-col">S.No.</th>
                    <th>Date</th><th>Voucher No</th><th>Category</th><th>Project</th><th>Particulars</th><th>Payment</th>
                    <th class="num">Expense Amount</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ledger_rows)): ?>
                <tr><td colspan="9" class="elg-empty" style="display:table-cell">No expenses found.</td></tr>
                <?php endif; ?>
                <?php foreach ($ledger_rows as $r): ?>
                <tr class="<?= $r['status'] === 'CANCELLED' ? 'is-cancelled' : '' ?>">
                    <td class="sno-col sno-auto" data-label="S.No."></td>
                    <td data-label="Date"><?= $dmy($r['date']) ?></td>
                    <td data-label="Voucher"><a href="<?= base_url('expenses/view/' . $r['id']) ?>"><?= esc($r['voucher']) ?></a></td>
                    <td data-label="Category"><?= esc($r['category'] ?: '-') ?></td>
                    <td data-label="Project"><?= esc($r['project'] ?: 'General') ?></td>
                    <td data-label="Particulars" class="part"><?= esc($r['particulars']) ?></td>
                    <td data-label="Payment"><?= esc(pm_label($r['payment_method'], 'Not recorded')) ?></td>
                    <td data-label="Expense Amount" class="num <?= $r['expense_amount'] > 0 ? 'amt-debit' : 'amt-nil' ?>"><?= $r['expense_amount'] > 0 ? $fmt($r['expense_amount']) : '-' ?></td>
                    <td data-label="Status"><span class="tbadge s-<?= strtolower($r['status']) ?>"><?= ucfirst(strtolower($r['status'])) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(function () {
	var form = document.getElementById('elgForm'), timer;
	$('#fCat, #fProj, #fMethod, #fFrom, #fTo').on('change', function () { form.submit(); });
	$('#fQ').on('input', function () { clearTimeout(timer); timer = setTimeout(function () { form.submit(); }, 600); });
	var q = document.getElementById('fQ');
	if (q && q.value) { q.focus(); q.setSelectionRange(q.value.length, q.value.length); }
});
</script>
<?= $this->endSection() ?>
