<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>

	/* Release 4.4.1: header bar, KPI rows, summary strip, and tables all
	   follow the exact compact styling pattern already established on the
	   Profit & Loss report (.pl-header-bar / .pl-kpi-row / .pl-summary-strip)
	   — scoped under a "bs-" prefix so nothing shared is touched. */
	.bs-header-bar {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 10px;
		flex-wrap: wrap;
		padding: 8px 14px;
		background: #fff;
		border: 1px solid #e2e8f0;
		border-radius: 8px;
	}
	.bs-header-left { display: flex; flex-direction: column; gap: 2px; }
	.bs-header-title {
		font-size: 1rem;
		font-weight: 700;
		color: #1e293b;
		display: flex;
		align-items: center;
		gap: 8px;
	}
	.bs-header-meta { font-size: 0.78rem; color: #64748b; }
	.bs-header-sep { color: #cbd5e1; }
	.bs-header-actions { display: flex; gap: 8px; flex-wrap: wrap; align-self: center; }
	.bs-filter-toggle { font-size: 0.78rem; color: #2F7E8A; cursor: pointer; text-decoration: none; white-space: nowrap; }
	.bs-filter-toggle:hover { text-decoration: underline; }

	.bs-section-label {
		font-size: 0.8rem;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		color: #64748b;
		margin: 14px 2px 8px;
	}

	.bs-kpi-row .kpi-card {
		padding: 8px 10px;
		min-height: 70px;
		border-width: 1px;
		gap: 8px;
	}
	.bs-kpi-row .kpi-icon {
		width: 28px;
		height: 28px;
		font-size: 14px;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
	}
	.bs-kpi-row .kpi-value { font-size: 1rem; }
	.bs-kpi-row .kpi-label { font-size: 0.7rem; margin-top: 1px; }
	.bs-kpi-note { font-size: 0.62rem; color: #94a3b8; }

	.bs-equation-strip {
		display: flex;
		align-items: center;
		justify-content: center;
		flex-wrap: wrap;
		gap: 14px;
		padding: 14px 18px;
	}
	.bs-eq-item { display: flex; flex-direction: column; align-items: center; gap: 2px; min-width: 120px; }
	.bs-eq-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); }
	.bs-eq-value { font-size: 16px; font-weight: 700; color: var(--text-dark); }
	.bs-eq-op { font-size: 20px; font-weight: 700; color: #94a3b8; }

	.bs-table th, .bs-table td { padding: 7px 10px; font-size: 13px; }
	.bs-table tfoot td { font-weight: 700; background: #f1f5f9; border-top: 2px solid var(--border-color); }

</style>

<?php
	$bsSelectedProjectName = 'All Projects';
	if (!empty($project_id)) {
		foreach ($projects as $p) {
			if ((int) $p['id'] === (int) $project_id) {
				$bsSelectedProjectName = $p['name'];
				break;
			}
		}
	}
	$bsDateRangeLabel = ($start_date || $end_date)
		? (($start_date ?: 'Start') . ' → ' . ($end_date ?: 'End'))
		: 'All Time';
?>

<div class="bs-header-bar mb-3">
	<div class="bs-header-left">
		<div class="bs-header-title"><i class="bi bi-bank2"></i> Balance Sheet Report</div>
		<div class="bs-header-meta">
			<i class="bi bi-kanban me-1"></i><?= esc($bsSelectedProjectName) ?>
			<span class="bs-header-sep">•</span>
			<i class="bi bi-calendar3 me-1"></i><?= esc($bsDateRangeLabel) ?>
			<span class="bs-header-sep">•</span>
			<a class="bs-filter-toggle" data-bs-toggle="collapse" href="#bsFilters" role="button">
				<i class="bi bi-sliders"></i> Change Filters
			</a>
		</div>
	</div>
	<div class="bs-header-actions">
		<a href="<?= base_url('reports') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
		<a href="<?= base_url('reports/balance-sheet-export?' . http_build_query($_GET)) ?>" class="btn-save" style="background:#16a34a;">
			<i class="bi bi-file-earmark-excel"></i> Excel
		</a>
		<a href="<?= base_url('reports/balance-sheet-pdf?' . http_build_query($_GET)) ?>" class="btn-save" style="background:#dc2626;">
			<i class="bi bi-file-pdf"></i> PDF
		</a>
	</div>
</div>

<div class="collapse mb-3" id="bsFilters">
    <div class="card-custom">
        <div class="card-custom-header">Filters</div>
        <div class="card-custom-body">
            <form method="GET" action="<?= base_url('reports/balance-sheet') ?>">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-section">
                            <label class="form-label">Project</label>
                            <select name="project_id" class="form-control">
                                <option value="">All Projects</option>
                                <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $project_id == $p['id'] ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-section">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-section">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                        </div>
                    </div>
                    <div class="col-md-4" style="display:flex;align-items:flex-end;gap:10px;padding-bottom:14px">
						<button type="submit" class="btn-save"><i class="bi bi-search"></i> Filter</button>
						<a href="<?= base_url('reports/balance-sheet') ?>" class="btn btn-sm btn-secondary">
                       <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>
					</div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (!$is_balanced): ?>
<div class="alert" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;font-size:0.85rem;margin-bottom:14px;">
	<i class="bi bi-exclamation-triangle me-1"></i>
	<strong>Partial Balance Sheet</strong> — Cash/Capital module not implemented. Assets and Liabilities+Equity differ by <?= number_format(abs($difference), 2) ?> because Cash/Bank balance, Supplier Outstanding, and Owner Capital have no data source in this system yet.
</div>
<?php endif; ?>

<!-- ASSETS -->
<div class="bs-section-label"><i class="bi bi-building me-1"></i>Assets</div>
<div class="row g-2 mb-2 bs-kpi-row">
	<div class="col-6 col-md-3">
		<div class="kpi-card kpi-blue"><div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
			<div><div class="kpi-value"><?= number_format($cash_received, 2) ?></div><div class="kpi-label">Cash Received</div></div>
		</div>
	</div>
	<div class="col-6 col-md-3">
		<div class="kpi-card kpi-blue"><div class="kpi-icon"><i class="bi bi-receipt"></i></div>
			<div><div class="kpi-value"><?= number_format($accounts_receivable, 2) ?></div><div class="kpi-label">Accounts Receivable</div></div>
		</div>
	</div>
	<div class="col-6 col-md-3">
		<div class="kpi-card kpi-blue"><div class="kpi-icon"><i class="bi bi-boxes"></i></div>
			<div><div class="kpi-value"><?= number_format($inventory_value, 2) ?></div><div class="kpi-label">Inventory Value</div></div>
		</div>
	</div>
	<div class="col-6 col-md-3">
		<div class="kpi-card kpi-green"><div class="kpi-icon"><i class="bi bi-piggy-bank"></i></div>
			<div><div class="kpi-value"><?= number_format($total_assets, 2) ?></div><div class="kpi-label">Total Assets</div></div>
		</div>
	</div>
</div>

<!-- LIABILITIES -->
<div class="bs-section-label"><i class="bi bi-exclamation-circle me-1"></i>Liabilities</div>
<div class="row g-2 mb-2 bs-kpi-row">
	<div class="col-6 col-md-3">
		<div class="kpi-card kpi-red"><div class="kpi-icon"><i class="bi bi-arrow-down-circle"></i></div>
			<div><div class="kpi-value"><?= number_format($advance_liability, 2) ?></div><div class="kpi-label">Customer Advance Liability</div></div>
		</div>
	</div>
	<div class="col-6 col-md-3">
		<div class="kpi-card kpi-red"><div class="kpi-icon"><i class="bi bi-truck"></i></div>
			<div><div class="kpi-value"><?= number_format($supplier_outstanding, 2) ?></div>
			<div class="kpi-label">Supplier Outstanding<?= $supplier_outstanding_available ? '' : ' <span class="bs-kpi-note">(not tracked)</span>' ?></div></div>
		</div>
	</div>
	<div class="col-6 col-md-3">
		<div class="kpi-card kpi-red"><div class="kpi-icon"><i class="bi bi-list-check"></i></div>
			<div><div class="kpi-value"><?= number_format($other_liabilities, 2) ?></div>
			<div class="kpi-label">Other Liabilities<?= $other_liabilities_available ? '' : ' <span class="bs-kpi-note">(not tracked)</span>' ?></div></div>
		</div>
	</div>
	<div class="col-6 col-md-3">
		<div class="kpi-card kpi-red"><div class="kpi-icon"><i class="bi bi-journal-x"></i></div>
			<div><div class="kpi-value"><?= number_format($total_liabilities, 2) ?></div><div class="kpi-label">Total Liabilities</div></div>
		</div>
	</div>
</div>

<!-- EQUITY -->
<div class="bs-section-label"><i class="bi bi-person-badge me-1"></i>Equity</div>
<div class="row g-2 mb-3 bs-kpi-row">
	<div class="col-6 col-md-3">
		<div class="kpi-card kpi-blue"><div class="kpi-icon"><i class="bi bi-graph-up"></i></div>
			<div><div class="kpi-value"><?= number_format($total_revenue, 2) ?></div><div class="kpi-label">Total Revenue</div></div>
		</div>
	</div>
	<div class="col-6 col-md-3">
		<div class="kpi-card <?= $net_profit >= 0 ? 'kpi-green' : 'kpi-red' ?>"><div class="kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
			<div><div class="kpi-value"><?= number_format($net_profit, 2) ?></div><div class="kpi-label">Net Profit</div></div>
		</div>
	</div>
	<div class="col-6 col-md-3">
		<div class="kpi-card kpi-blue"><div class="kpi-icon"><i class="bi bi-wallet2"></i></div>
			<div><div class="kpi-value"><?= number_format($owner_capital, 2) ?></div>
			<div class="kpi-label">Owner Capital<?= $owner_capital_available ? '' : ' <span class="bs-kpi-note">(not tracked)</span>' ?></div></div>
		</div>
	</div>
	<div class="col-6 col-md-3">
		<div class="kpi-card kpi-green"><div class="kpi-icon"><i class="bi bi-bank"></i></div>
			<div><div class="kpi-value"><?= number_format($total_equity, 2) ?></div><div class="kpi-label">Total Equity</div></div>
		</div>
	</div>
</div>

<!-- ACCOUNTING EQUATION -->
<div class="card-custom mb-3">
	<div class="bs-equation-strip">
		<div class="bs-eq-item">
			<span class="bs-eq-label">Assets</span>
			<span class="bs-eq-value"><?= number_format($total_assets, 2) ?></span>
		</div>
		<span class="bs-eq-op">=</span>
		<div class="bs-eq-item">
			<span class="bs-eq-label">Liabilities</span>
			<span class="bs-eq-value"><?= number_format($total_liabilities, 2) ?></span>
		</div>
		<span class="bs-eq-op">+</span>
		<div class="bs-eq-item">
			<span class="bs-eq-label">Equity</span>
			<span class="bs-eq-value"><?= number_format($total_equity, 2) ?></span>
		</div>
		<?php if ($is_balanced): ?>
			<span class="badge-status" style="background:#dcfce7;color:#166534;"><i class="bi bi-check-circle me-1"></i>Balanced</span>
		<?php else: ?>
			<span class="badge-status" style="background:#fef3c7;color:#92400e;"><i class="bi bi-exclamation-triangle me-1"></i>Partial Balance Sheet (Cash/Capital module not implemented)</span>
		<?php endif; ?>
	</div>
</div>

<!-- DETAILED TABLES -->
<div class="row g-3">
	<div class="col-md-4">
		<div class="card-custom">
			<div class="card-custom-header">Assets</div>
			<div class="table-responsive">
				<table class="table-custom bs-table">
					<thead><tr><th>Asset</th><th style="text-align:right;">Amount</th><th>Source</th></tr></thead>
					<tbody>
						<tr><td>Cash Received From Customers</td><td style="text-align:right;"><?= number_format($cash_received, 2) ?></td><td>payments.amount</td></tr>
						<tr><td>Accounts Receivable</td><td style="text-align:right;"><?= number_format($accounts_receivable, 2) ?></td><td>sales.balance_amount</td></tr>
						<tr><td>Inventory Value</td><td style="text-align:right;"><?= number_format($inventory_value, 2) ?></td><td>stock_ledger + purchase_items</td></tr>
					</tbody>
					<tfoot><tr><td>TOTAL</td><td style="text-align:right;"><?= number_format($total_assets, 2) ?></td><td></td></tr></tfoot>
				</table>
			</div>
		</div>
	</div>
	<div class="col-md-4">
		<div class="card-custom">
			<div class="card-custom-header">Liabilities</div>
			<div class="table-responsive">
				<table class="table-custom bs-table">
					<thead><tr><th>Liability</th><th style="text-align:right;">Amount</th><th>Source</th></tr></thead>
					<tbody>
						<tr><td>Customer Advance Liability</td><td style="text-align:right;"><?= number_format($advance_liability, 2) ?></td><td>projects (unused advance)</td></tr>
						<tr><td>Supplier Outstanding</td><td style="text-align:right;"><?= number_format($supplier_outstanding, 2) ?></td><td><?= $supplier_outstanding_available ? 'purchases' : 'Not tracked' ?></td></tr>
						<tr><td>Other Liabilities</td><td style="text-align:right;"><?= number_format($other_liabilities, 2) ?></td><td><?= $other_liabilities_available ? '' : 'Not tracked' ?></td></tr>
					</tbody>
					<tfoot><tr><td>TOTAL</td><td style="text-align:right;"><?= number_format($total_liabilities, 2) ?></td><td></td></tr></tfoot>
				</table>
			</div>
		</div>
	</div>
	<div class="col-md-4">
		<div class="card-custom">
			<div class="card-custom-header">Equity</div>
			<div class="table-responsive">
				<table class="table-custom bs-table">
					<thead><tr><th>Equity Item</th><th style="text-align:right;">Amount</th><th>Source</th></tr></thead>
					<tbody>
						<tr><td>Total Revenue</td><td style="text-align:right;"><?= number_format($total_revenue, 2) ?></td><td>sales.total_amount</td></tr>
						<tr><td>Net Profit</td><td style="text-align:right;"><?= number_format($net_profit, 2) ?></td><td>P&amp;L formula</td></tr>
						<tr><td>Owner Capital</td><td style="text-align:right;"><?= number_format($owner_capital, 2) ?></td><td><?= $owner_capital_available ? '' : 'Not tracked' ?></td></tr>
					</tbody>
					<tfoot><tr><td>TOTAL</td><td style="text-align:right;"><?= number_format($total_equity, 2) ?></td><td></td></tr></tfoot>
				</table>
			</div>
		</div>
	</div>
</div>

<?= $this->endSection() ?>
