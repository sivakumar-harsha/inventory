<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>

	.dataTables_wrapper .dataTables_paginate {
		margin-top: 10px;
		text-align: right;
	}

	.dataTables_wrapper .dataTables_paginate .paginate_button {
		background: #f1f5f9 !important;
		border: 1px solid #e2e8f0 !important;
		color: #334155 !important;
		padding: 4px 10px !important;
		margin: 2px !important;
		border-radius: 6px !important;
		font-size: 12px !important;
	}

	.dataTables_wrapper .dataTables_paginate .paginate_button.current {
		background: #2F7E8A !important;
		color: #fff !important;
		border: none !important;
	}

	.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
		background: #1e293b !important;
		color: #fff !important;
	}

	.dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
		opacity: 0.5;
		cursor: not-allowed;
	}

	.dataTables_wrapper .dataTables_info {
		font-size: 12px;
		color: #64748b;
	}

	.dataTables_wrapper .dataTables_paginate {
		  float: right;
		  text-align: right;
		  padding-top: .25em;
		  padding-bottom: 7px;
		  padding-right: 10px;
		}

	/* Release 4.2 (Phase 1): compact header card, styled after the Project
	   View header bar (.project-header-bar) — filters (project/date range)
	   read as a subheader line, actions sit on the right. UI only, no new
	   data — reuses $project_id/$projects/$start_date/$end_date already
	   passed in by Reports::profitLoss(). */
	.pl-header-bar {
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
	.pl-header-left {
		display: flex;
		flex-direction: column;
		gap: 2px;
	}
	.pl-header-title {
		font-size: 1rem;
		font-weight: 700;
		color: #1e293b;
		display: flex;
		align-items: center;
		gap: 8px;
	}
	.pl-header-meta {
		font-size: 0.78rem;
		color: #64748b;
	}
	.pl-header-sep {
		color: #cbd5e1;
	}
	.pl-header-actions {
		display: flex;
		gap: 8px;
		flex-wrap: wrap;
		align-self: center;
	}

	.pl-filter-toggle {
		font-size: 0.78rem;
		color: #2F7E8A;
		cursor: pointer;
		text-decoration: none;
		white-space: nowrap;
	}
	.pl-filter-toggle:hover { text-decoration: underline; }

	/* Release 4.2 (Phase 2): 6 KPI cards, 2 rows x 3 columns, ~70px tall —
	   same scoping pattern used on Project View's .project-kpi-row so the
	   shared .kpi-card/.kpi-icon/.kpi-value/.kpi-label classes/colors stay
	   untouched for every other page. */
	.pl-kpi-row .kpi-card {
		padding: 8px 10px;
		min-height: 70px;
		border-width: 1px;
		gap: 8px;
	}
	.pl-kpi-row .kpi-icon {
		width: 28px;
		height: 28px;
		font-size: 14px;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
	}
	.pl-kpi-row .kpi-value { font-size: 1rem; }
	.pl-kpi-row .kpi-label { font-size: 0.7rem; margin-top: 1px; }

	/* Release 4.2 (Phase 3): single compact Revenue/COGS/Expenses/Net Profit
	   summary strip — no cards, no icons, no progress bars. */
	.pl-summary-strip {
		display: flex;
		align-items: center;
		justify-content: space-between;
		flex-wrap: wrap;
		padding: 10px 18px;
		gap: 10px;
	}
	.pl-summary-item {
		display: flex;
		flex-direction: column;
		gap: 2px;
		min-width: 110px;
	}
	.pl-summary-label {
		font-size: 11px;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		color: var(--text-muted);
	}
	.pl-summary-value {
		font-size: 15px;
		font-weight: 700;
		color: var(--text-dark);
	}
	.pl-summary-divider {
		width: 1px;
		height: 28px;
		background: var(--border-color);
	}

	/* Release 4.2 (Phase 4/6): dense Project Breakdown table, sticky header,
	   fixed totals row, no horizontal scroll — column widths sized so all 6
	   columns fit a normal laptop width without .table-responsive's overflow
	   kicking in. */
	.pl-table-wrap {
		max-height: 420px;
		overflow-y: auto;
		overflow-x: hidden;
	}
	#profitTable {
		width: 100%;
		table-layout: fixed;
	}
	#profitTable th, #profitTable td {
		padding: 8px 10px;
		font-size: 13px;
		height: 37px;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	#profitTable th:first-child, #profitTable td:first-child {
		width: 26%;
		white-space: normal;
	}
	#profitTable thead th {
		position: sticky;
		top: 0;
		z-index: 2;
		background: #f8fafc;
	}
	.pl-totals-row td {
		font-weight: 700;
		background: #f1f5f9;
		border-top: 2px solid var(--border-color);
		position: sticky;
		bottom: 0;
	}
	.pl-empty-note {
		padding: 14px;
		color: var(--text-muted);
		font-size: 13px;
		text-align: center;
	}

	/* Release 4.2 (Phase 5): compact Expense Category Breakdown table. */
	.pl-expense-table th, .pl-expense-table td {
		padding: 7px 10px;
		font-size: 13px;
	}

</style>

<?php
	// Release 4.2 (Phase 1): display-only labels for the header — reuses the
	// exact $project_id/$projects/$start_date/$end_date already passed by
	// Reports::profitLoss(); no new query, no new data.
	$plSelectedProjectName = 'All Projects';
	if (!empty($project_id)) {
		foreach ($projects as $p) {
			if ((int) $p['id'] === (int) $project_id) {
				$plSelectedProjectName = $p['name'];
				break;
			}
		}
	}
	$plDateRangeLabel = ($start_date || $end_date)
		? (($start_date ?: 'Start') . ' → ' . ($end_date ?: 'End'))
		: 'All Time';

	// Release 4.1.1: Project Value KPI — reuses the $projects array already
	// passed in by Reports::profitLoss() (each row already carries
	// total_project_value), no new query. Single project selected -> that
	// project's value; All Projects -> sum across every project in $projects.
	$plProjectValue = 0.0;
	if (!empty($project_id)) {
		foreach ($projects as $p) {
			if ((int) $p['id'] === (int) $project_id) {
				$plProjectValue = (float) $p['total_project_value'];
				break;
			}
		}
	} else {
		foreach ($projects as $p) {
			$plProjectValue += (float) $p['total_project_value'];
		}
	}
?>

<div class="pl-header-bar mb-3">
	<div class="pl-header-left">
		<div class="pl-header-title"><i class="bi bi-graph-up-arrow"></i> Profit &amp; Loss Report</div>
		<div class="pl-header-meta">
			<i class="bi bi-kanban me-1"></i><?= esc($plSelectedProjectName) ?>
			<span class="pl-header-sep">•</span>
			<i class="bi bi-calendar3 me-1"></i><?= esc($plDateRangeLabel) ?>
			<span class="pl-header-sep">•</span>
			<a class="pl-filter-toggle" data-bs-toggle="collapse" href="#plFilters" role="button">
				<i class="bi bi-sliders"></i> Change Filters
			</a>
		</div>
	</div>
	<div class="pl-header-actions">
		<a href="<?= base_url('reports') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
		<a href="<?= base_url('reports/profit-loss-export?project_id=' . $project_id . '&start_date=' . $start_date . '&end_date='
			. $end_date) ?>" class="btn-save" style="background:#16a34a;">
			<i class="bi bi-file-earmark-excel"></i> Excel
		</a>
		<a href="<?= base_url('reports/profit-loss-pdf?' . http_build_query($_GET)) ?>" class="btn-save" style="background:#dc2626;">
			<i class="bi bi-file-pdf"></i> PDF
		</a>
	</div>
</div>

<div class="collapse mb-3" id="plFilters">
    <div class="card-custom">
        <div class="card-custom-header">Filters</div>
        <div class="card-custom-body">
            <form method="GET" action="<?= base_url('reports/profit-loss') ?>">
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

						<button type="submit" class="btn-save">
							<i class="bi bi-search"></i> Filter
						</button>

						<a href="<?= base_url('reports/profit-loss') ?>" class="btn btn-sm btn-secondary">
                       <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>

					</div>

                </div>
            </form>
        </div>
    </div>
</div>

<!-- Release 4.1.1 (Phase 2): 5 compact KPI cards, Revenue, Gross Profit,
     Expenses, Net Profit, Project Value. Gross Margin / Net Margin cards
     removed, replaced by Project Value. -->
<div class="row g-2 mb-3 pl-kpi-row">
    <div class="col-6 col-md-4">
        <div class="kpi-card kpi-blue"><div class="kpi-icon"><i class="bi bi-receipt"></i></div>
            <div><div class="kpi-value"><?= number_format($total_revenue, 2) ?></div><div class="kpi-label">Revenue</div></div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="kpi-card <?= $gross_profit >= 0 ? 'kpi-green' : 'kpi-red' ?>"><div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
            <div><div class="kpi-value"><?= number_format($gross_profit, 2) ?></div><div class="kpi-label">Gross Profit</div></div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="kpi-card kpi-red"><div class="kpi-icon"><i class="bi bi-credit-card"></i></div>
            <div><div class="kpi-value"><?= number_format($total_expenses, 2) ?></div><div class="kpi-label">Expenses</div></div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="kpi-card <?= $net_profit >= 0 ? 'kpi-green' : 'kpi-red' ?>"><div class="kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div><div class="kpi-value"><?= number_format($net_profit, 2) ?></div><div class="kpi-label">Net Profit</div></div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="kpi-card kpi-blue"><div class="kpi-icon"><i class="bi bi-briefcase"></i></div>
            <div><div class="kpi-value"><?= number_format($plProjectValue, 2) ?></div><div class="kpi-label">Project Value</div></div>
        </div>
    </div>
</div>

<!-- Release 4.2 (Phase 3): single compact Revenue/COGS/Expenses/Net Profit
     summary strip — no duplicate cards, no progress bars. -->
<div class="card-custom mb-3">
    <div class="pl-summary-strip">
        <div class="pl-summary-item">
            <span class="pl-summary-label">Revenue</span>
            <span class="pl-summary-value"><?= number_format($total_revenue, 2) ?></span>
        </div>
        <div class="pl-summary-divider"></div>
        <div class="pl-summary-item">
            <span class="pl-summary-label">COGS</span>
            <span class="pl-summary-value"><?= number_format($total_cogs, 2) ?></span>
        </div>
        <div class="pl-summary-divider"></div>
        <div class="pl-summary-item">
            <span class="pl-summary-label">Expenses</span>
            <span class="pl-summary-value"><?= number_format($total_expenses, 2) ?></span>
        </div>
        <div class="pl-summary-divider"></div>
        <div class="pl-summary-item">
            <span class="pl-summary-label">Net Profit</span>
            <span class="pl-summary-value" style="color:<?= $net_profit >= 0 ? '#16a34a' : '#dc2626' ?>"><?= number_format($net_profit, 2) ?></span>
        </div>
    </div>
</div>

<!-- Release 4.2 (Phase 4): Project Breakdown — dense rows, sticky header,
     totals row fixed at bottom, no horizontal scroll. -->
<div class="card-custom">
    <div class="pl-table-wrap">
        <table id="profitTable" class="table-custom">
            <thead>
                <tr><th>Project</th><th style="text-align: right;">Revenue</th><th style="text-align: right;">COGS</th><th style="text-align: right;">Expenses</th><th style="text-align: right;">Gross Profit</th><th style="text-align: right;">Net Profit</th></tr>
            </thead>
            <tbody>
                <?php if (empty($project_breakdown)): ?>
                <tr><td colspan="6" class="pl-empty-note">No projects found for selected filters.</td></tr>
                <?php else: foreach ($project_breakdown as $pb):
                    $gross = $pb['revenue'] - $pb['cogs'];
                    $net   = $gross - $pb['expenses'];
                ?>
                <tr>
                    <td title="<?= esc($pb['project_name']) ?>"><?= esc($pb['project_name']) ?></td>
                    <td style="text-align: right;"><?= number_format($pb['revenue'], 2) ?></td>
                    <td style="text-align: right;"><?= number_format($pb['cogs'], 2) ?></td>
                    <td style="text-align: right;"><?= number_format($pb['expenses'], 2) ?></td>
                    <td class="<?= $gross >= 0 ? 'text-success' : 'text-danger' ?>" style="text-align: right;"><?= number_format($gross, 2) ?></td>
                    <td class="<?= $net >= 0 ? 'text-success' : 'text-danger' ?>" style="text-align: right;"><strong><?= number_format($net, 2) ?></strong></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
            <!-- Release 4.1 (Phase B): totals row reuses the exact KPI values
                 above (not a re-sum of the rows) so it is guaranteed to match
                 the KPI cards. A <tfoot> row is never paginated/sorted by
                 DataTables, so it always reflects the full filtered total. -->
            <tfoot>
                <tr class="pl-totals-row">
                    <td>TOTAL</td>
                    <td style="text-align: right;"><?= number_format($total_revenue, 2) ?></td>
                    <td style="text-align: right;"><?= number_format($total_cogs, 2) ?></td>
                    <td style="text-align: right;"><?= number_format($total_expenses, 2) ?></td>
                    <td style="text-align: right;"><?= number_format($gross_profit, 2) ?></td>
                    <td style="text-align: right;"><?= number_format($net_profit, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Release 4.1 (Phase C) / 4.2 (Phase 5): Expense Category Breakdown,
     filtered by the same Project/Start/End Date filters as the rest of the
     report. -->
<div class="card-custom mt-3">
    <div class="card-custom-header">Expense Category Breakdown</div>
    <div class="table-responsive">
        <?php if (empty($expense_breakdown)): ?>
            <div class="pl-empty-note">No expenses found for selected period.</div>
        <?php else: ?>
        <table class="table-custom pl-expense-table">
            <thead>
                <tr><th>Category</th><th style="text-align: right;">Amount</th></tr>
            </thead>
            <tbody>
                <?php $expenseGrandTotal = 0.0; foreach ($expense_breakdown as $ec): $expenseGrandTotal += (float) $ec['total']; ?>
                <tr>
                    <td><?= esc($ec['category']) ?></td>
                    <td style="text-align: right;"><?= number_format($ec['total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="pl-totals-row">
                    <td>TOTAL</td>
                    <td style="text-align: right;"><?= number_format($expenseGrandTotal, 2) ?></td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
    </div>
</div>

<?= $this->section('scripts') ?>
<script>
		$(document).ready(function () {
			$('#profitTable').DataTable({
				paging: true,        // ✅ pagination
				searching: false,    // ❌ remove search box
				lengthChange: false, // ❌ remove "show entries"
				info: false,          // (optional) showing "1 to 10 of X"
				ordering: true,      // (optional sorting)
				pageLength: 10,      // default rows per page

				dom: 'tpi' ,// ✅ ONLY table + pagination + info

				language: {
					paginate: {
						previous: '<i class="bi bi-chevron-left"></i>',
						next: '<i class="bi bi-chevron-right"></i>'
					}
				}
			});
		});
	</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
